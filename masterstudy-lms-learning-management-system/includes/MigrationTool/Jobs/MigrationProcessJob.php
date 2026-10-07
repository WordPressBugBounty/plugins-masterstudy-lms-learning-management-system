<?php

namespace MasterStudy\Lms\MigrationTool\Jobs;

defined( 'ABSPATH' ) || exit;

use MasterStudy\Lms\MigrationTool\Contracts\MigratorInterface;
use MasterStudy\Lms\MigrationTool\Helper;
use MasterStudy\Lms\MigrationTool\MigrationSession;
use MasterStudy\Lms\MigrationTool\Report;
use MasterStudy\Lms\MigrationTool\Target;

/**
 * Background batch processor for LMS migration.
 * Self-queues until a step is complete, then advances to the next step.
 */
class MigrationProcessJob {

	const HOOK = 'masterstudy_lms/migration/process';

	/**
	 * Items processed per batch. Balances throughput against per-item memory cost.
	 */
	const BATCH_SIZE = 100;

	public function register(): void {
		add_action( self::HOOK, array( $this, 'handle' ), 10, 4 );
		add_action( 'action_scheduler_failed_action', array( $this, 'handle_failed_action' ), 10, 2 );
	}

	/**
	 * Re-queue a migration job when Action Scheduler marks it as failed (e.g. hard timeout).
	 *
	 * Without this, a timed-out job leaves the session permanently stuck in 'running'
	 * with no pending action to continue it.
	 */
	public function handle_failed_action( $action_id, $timeout = 0 ): void {
		if ( ! class_exists( '\ActionScheduler' ) ) {
			return;
		}

		$action = \ActionScheduler::store()->fetch_action( $action_id );

		if ( ! $action || self::HOOK !== $action->get_hook() ) {
			return;
		}

		$args       = $action->get_args();
		$session_id = $args[0] ?? '';
		$lms_slug   = $args[1] ?? '';
		$step       = $args[2] ?? '';

		if ( ! $session_id || ! $lms_slug || ! $step ) {
			return;
		}

		$session = MigrationSession::get( $session_id );

		if ( ! $session || 'running' !== $session['status'] ) {
			return;
		}

		// Use the last successfully saved cursor — may be ahead of the failed job's cursor.
		$state  = MigrationSession::get_step_state( $session_id, $step );
		$cursor = (int) ( $state['last_cursor'] ?? 0 );

		Helper::log(
			'warning',
			sprintf( 'Migration [%s] step "%s": job %d failed after %ds — re-queuing from cursor %d.', $lms_slug, $step, $action_id, $timeout, $cursor )
		);

		Helper::schedule_process( $session_id, $lms_slug, $step, $cursor, true );
	}

	/**
	 * Process all batches for a step within this job execution.
	 *
	 * Loops through batches until the step is exhausted OR a PHP resource limit
	 * (time/memory) is hit. On limit: re-queues itself so the next cron cycle
	 * continues from the saved cursor. On exhaustion: advances to the next step via
	 * a new job so each step runs in its own PHP process.
	 */
	public function handle( string $session_id, string $lms_slug, string $step, int $cursor = 0 ): void {
		global $wpdb;

		// Non-blocking per-session mutex; MySQL auto-releases on connection close.
		$lock = 'masterstudy_mig_' . $session_id;

		if ( ! (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ) {
			return;
		}

		Helper::set_session( $session_id );
		Report::set_session( $session_id );

		try {
			$this->run_handle( $session_id, $lms_slug, $step, $cursor );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	private function run_handle( string $session_id, string $lms_slug, string $step, int $cursor = 0 ): void {
		global $wpdb;

		$session = MigrationSession::get( $session_id );

		if ( ! $session || 'running' !== $session['status'] ) {
			return;
		}

		$migrator = Helper::registry()->get( $lms_slug );

		if ( ! $migrator ) {
			MigrationSession::update( $session_id, array( 'status' => 'failed' ) );
			Helper::log( 'error', sprintf( 'Migration session %s: migrator "%s" is not registered.', $session_id, $lms_slug ) );
			return;
		}

		// The source is read through its own plugin code: stop instead of copying half of it.
		if ( ! Helper::is_source_active( $migrator ) ) {
			MigrationSession::update( $session_id, array( 'status' => 'failed' ) );
			Helper::log( 'error', sprintf( 'Migration [%1$s]: %2$s was deactivated during the migration. Activate %2$s and run the migration again; items already copied are kept and are not copied twice.', $lms_slug, $migrator->get_label() ) );
			return;
		}

		Target::suppress_side_effects();

		$state = MigrationSession::get_step_state( $session_id, $step );
		// DB is authoritative: a stale pending action may carry an older cursor in its args.
		$cursor = max( $cursor, (int) $state['last_cursor'] );

		// Self-cleaning steps ignore $cursor, so a failed item stays in the source table and
		// reappears every batch — the skip-set prevents re-recording the same failure forever.
		$state['failed'] = array_values( array_unique( array_map( 'intval', (array) $state['failed'] ) ) );
		$failed_set      = array_fill_keys( $state['failed'], true );

		if ( 0 === (int) $state['total'] && 0 === $cursor && 0 === (int) $state['completed'] ) {
			$state['total'] = $migrator->count_source_items( $step );
			MigrationSession::save_step_state( $session_id, $step, $state );

			if ( 0 === $state['total'] ) {
				Helper::log( 'info', sprintf( 'Migration [%s] step "%s": skipped — no source items found.', $lms_slug, $step ) );
				$this->advance_step( $session_id, $lms_slug, $step, $migrator );
				return;
			}
		}

		if ( 0 === $cursor && 0 === (int) $state['completed'] ) {
			// An addon switched on now only loads with the next request: run the step there, so the addon's
			// hooks are in place (e.g. passed assignments counted in the course progress).
			if ( $this->maybe_activate_equivalent_addons( $migrator, $step ) && empty( $state['addons_reloaded'] ) ) {
				$state['addons_reloaded'] = true;
				MigrationSession::save_step_state( $session_id, $step, $state );
				Helper::schedule_process( $session_id, $lms_slug, $step, $cursor );
				return;
			}

			Helper::log( 'info', sprintf( 'Migration [%s] step "%s": started — %d item(s) to migrate.', $lms_slug, $step, $state['total'] ) );
		}

		$addons_enabled = false;

		$start         = microtime( true );
		$memory_limit  = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) ? ini_get( 'memory_limit' ) : '256M' );
		$memory_limit  = $memory_limit > 0 ? $memory_limit : PHP_INT_MAX;
		$php_max_exec  = (int) ini_get( 'max_execution_time' );
		$as_time_limit = (int) apply_filters( 'action_scheduler_queue_runner_time_limit', 30 );
		// Use the tighter of the two limits so we always re-queue before PHP or the runner kills the process.
		$time_limit = (int) apply_filters(
			'masterstudy_lms_migration_run_time_limit',
			$php_max_exec > 0
				? max( min( $as_time_limit - 5, $php_max_exec - 10 ), 20 )
				: max( $as_time_limit - 5, 25 )
		);
		$limit_hit  = false;

		// IDs already handled in this run. Steps are cursor based (copy mode never removes source records);
		// an ID returned twice would loop forever, so it is failed instead.
		$seen = array();

		while ( true ) {
			$batch_size = (int) apply_filters( 'masterstudy_lms_migration_batch_size', self::BATCH_SIZE );
			$ids        = $migrator->get_source_ids( $step, $batch_size, $cursor, array_keys( $failed_set ) );

			if ( empty( $ids ) ) {
				break;
			}

			$processable = array_filter(
				$ids,
				function ( $id ) use ( $failed_set ) {
					return ! isset( $failed_set[ (int) $id ] );
				}
			);

			if ( empty( $processable ) ) {
				MigrationSession::save_step_state( $session_id, $step, $state );
				break;
			}

			$wpdb->query( 'START TRANSACTION' );

			foreach ( $ids as $id ) {
				if ( isset( $failed_set[ (int) $id ] ) ) {
					$cursor = (int) $id;
					continue;
				}

				// Check BEFORE each item so a slow batch never runs past the time window.
				if ( memory_get_usage( true ) / $memory_limit > (float) apply_filters( 'masterstudy_lms_migration_memory_threshold', 0.85 ) ||
					( microtime( true ) - $start ) > $time_limit ) {
					$limit_hit = true;
					break;
				}

				if ( isset( $seen[ (int) $id ] ) ) {
					$state['failed'][]       = (int) $id;
					$failed_set[ (int) $id ] = true;
					$message                 = 'The source record was migrated but not removed from the source query, so it was stopped to prevent an endless loop.';
					Helper::log( 'error', sprintf( 'Migration [%s] step "%s" item %d: %s', $lms_slug, $step, $id, $message ) );
					Report::add_failure( $step, (int) $id, $message );
					$cursor = (int) $id;
					continue;
				}

				$seen[ (int) $id ] = true;

				$savepoint = 'sp_' . (int) $id;
				$wpdb->query( "SAVEPOINT {$savepoint}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

				try {
					$migrator->migrate_item( $step, (int) $id );
					++$state['completed'];
					$wpdb->query( "RELEASE SAVEPOINT {$savepoint}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				} catch ( \Throwable $e ) {
					$wpdb->query( "ROLLBACK TO SAVEPOINT {$savepoint}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					// The rollback may have removed copies created by this item.
					Target::reset_copies();
					$state['failed'][]       = (int) $id;
					$failed_set[ (int) $id ] = true;
					Helper::log( 'error', sprintf( 'Migration [%s] step "%s" item %d: %s', $lms_slug, $step, $id, $e->getMessage() ) );
					Report::add_failure( $step, (int) $id, $e->getMessage() );
				}

				$cursor = (int) $id;
			}

			$wpdb->query( 'COMMIT' );

			$addons_enabled = Helper::flush_addon_requests() || $addons_enabled;

			$state['last_cursor'] = $cursor;
			MigrationSession::save_step_state( $session_id, $step, $state );

			// Respect pause/cancel between batches.
			$session = MigrationSession::get( $session_id );

			if ( ! $session || 'running' !== $session['status'] ) {
				return;
			}

			if ( $limit_hit ) {
				break;
			}
		}

		if ( $limit_hit ) {
			Helper::schedule_process( $session_id, $lms_slug, $step, $cursor );
			return;
		}

		// Addons switched on during this run load with the next request: finalize the step there.
		if ( $addons_enabled ) {
			Helper::schedule_process( $session_id, $lms_slug, $step, $cursor );
			return;
		}

		try {
			$migrator->finalize_step( $step );
		} catch ( \Throwable $e ) {
			Helper::log( 'warning', sprintf( 'Migration [%s] step "%s" finalize_step: %s', $lms_slug, $step, $e->getMessage() ) );
		}

		Helper::flush_addon_requests();

		$this->advance_step( $session_id, $lms_slug, $step, $migrator );
	}

	/**
	 * Enable MasterStudy addons equivalent to the source LMS feature being migrated.
	 * Silently skips slugs that are unknown or already enabled.
	 */
	private function maybe_activate_equivalent_addons( MigratorInterface $migrator, string $step ): bool {
		$enabled = false;

		foreach ( $migrator->get_addons_to_activate( $step ) as $slug ) {
			if ( Helper::enable_addon( $slug ) ) {
				Helper::log( 'info', sprintf( 'Migration: enabled MasterStudy addon "%s" for step "%s".', $slug, $step ) );
				$enabled = true;
			}
		}

		return $enabled;
	}

	/**
	 * Advance to the next step with items, or mark the session complete.
	 *
	 * Non-empty steps are queued as a new job so each step runs in its own PHP process.
	 * Empty steps are skipped synchronously so they require no extra cron tick.
	 */
	private function advance_step( string $session_id, string $lms_slug, string $step, ?MigratorInterface $migrator = null ): void {
		$session = MigrationSession::get( $session_id );
		$steps   = (array) ( $session['steps'] ?? array() );
		$idx     = array_search( $step, $steps, true );

		if ( false === $idx ) {
			static::complete( $session_id );
			Helper::log( 'warning', sprintf( 'Migration session %s: step "%s" not found in steps list — marking complete.', $session_id, $step ) );
			return;
		}

		$state = MigrationSession::get_step_state( $session_id, $step );

		Helper::log(
			'info',
			sprintf( 'Migration [%s] step "%s": complete — %d succeeded, %d failed.', $lms_slug, $step, $state['completed'], count( $state['failed'] ) )
		);

		$next_idx = $idx + 1;

		while ( isset( $steps[ $next_idx ] ) ) {
			$next       = $steps[ $next_idx ];
			$next_state = MigrationSession::get_step_state( $session_id, $next );

			// Counts are taken at session start, but some steps only get source data from earlier
			// steps (e.g. assignments converted by the courses step) — recount before skipping.
			if ( 0 === (int) $next_state['total'] && 0 === (int) $next_state['completed'] ) {
				if ( null === $migrator ) {
					$migrator = Helper::registry()->get( $lms_slug );
				}

				try {
					$next_state['total'] = $migrator ? (int) $migrator->count_source_items( $next ) : 0;
				} catch ( \Throwable $e ) {
					$next_state['total'] = 0;
				}

				if ( $next_state['total'] > 0 ) {
					MigrationSession::save_step_state( $session_id, $next, $next_state );
				}
			}

			MigrationSession::update( $session_id, array( 'current_step' => $next ) );

			if ( (int) $next_state['total'] > 0 ) {
				Helper::schedule_process( $session_id, $lms_slug, $next, 0 );
				return;
			}

			Helper::log( 'info', sprintf( 'Migration [%s] step "%s": skipped — no source items found.', $lms_slug, $next ) );

			++$next_idx;
		}

		static::complete( $session_id );

		/**
		 * Fires when a migration session has processed every step.
		 *
		 * @param string $session_id Session ID.
		 * @param string $lms_slug   Migrator slug.
		 */
		do_action( 'masterstudy_lms_migration_tool_completed', $session_id, $lms_slug );

		Helper::log( 'info', sprintf( 'Migration session %s [%s]: all steps complete.', $session_id, $lms_slug ) );
	}

	private static function complete( string $session_id ): void {
		MigrationSession::update(
			$session_id,
			array(
				'status'       => 'completed',
				'completed_at' => time(),
			)
		);
	}
}
