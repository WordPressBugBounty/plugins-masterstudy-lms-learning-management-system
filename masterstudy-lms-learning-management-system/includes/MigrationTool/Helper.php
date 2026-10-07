<?php

namespace MasterStudy\Lms\MigrationTool;

defined( 'ABSPATH' ) || exit;

use MasterStudy\Lms\MigrationTool\Jobs\MigrationProcessJob;
use MasterStudy\Lms\MigrationTool\Migrators\LearnDashMigrator;
use MasterStudy\Lms\MigrationTool\Migrators\LearnPressMigrator;
use MasterStudy\Lms\MigrationTool\Migrators\LifterLMSMigrator;
use MasterStudy\Lms\MigrationTool\Migrators\MasteriyoMigrator;
use MasterStudy\Lms\MigrationTool\Migrators\TutorLMSMigrator;

/**
 * Engine helpers: migrator registry, job scheduling, logging and addon activation.
 * Shared write helpers for the MasterStudy data model live in Target.
 */
class Helper {

	const LOG_SOURCE = 'masterstudy-migration-tool';

	/**
	 * Maximum warning/error entries kept on a session for the "View migration log" panel.
	 */
	const SESSION_LOG_LIMIT = 200;

	/**
	 * Session currently processed by the job; warnings and errors are attached to it.
	 *
	 * @var string
	 */
	private static $session_id = '';

	/**
	 * Source LMS slug of the session being processed (cached per session).
	 *
	 * @var string
	 */
	private static $source = '';

	/**
	 * Step => group the admin can include or skip ("Choose what to migrate"). Unknown steps belong to
	 * the required content group.
	 */
	const STEP_GROUPS = array(
		'users'               => 'users',
		'courses'             => 'content',
		'announcement'        => 'content',
		'google_meet'         => 'content',
		'enrollments'         => 'progress',
		'progress'            => 'progress',
		'lesson_progress'     => 'progress',
		'quiz_attempts'       => 'progress',
		'quiz_results'        => 'progress',
		'assignments'         => 'progress',
		'lesson_notes'        => 'progress',
		'orders'              => 'orders',
		'coupons'             => 'orders',
		'reviews'             => 'reviews',
		'wdm_reviews'         => 'reviews',
		'questions_n_answers' => 'discussions',
		'wishlists'           => 'wishlists',
	);

	/**
	 * Group that is always migrated: every other group points at the course copies.
	 */
	const REQUIRED_GROUP = 'content';

	public static function set_session( string $session_id ): void {
		static::$session_id = $session_id;
		static::$source     = '';
	}

	/**
	 * Source LMS slug of the running session ('' outside a migration job).
	 */
	public static function current_source(): string {
		if ( '' === static::$source && '' !== static::$session_id ) {
			$session        = MigrationSession::get( static::$session_id );
			static::$source = (string) ( $session['lms_slug'] ?? '' );
		}

		return static::$source;
	}

	/**
	 * Whether a step is part of the running session (the admin may skip groups). True outside a job.
	 */
	public static function step_selected( string $step ): bool {
		if ( '' === static::$session_id ) {
			return true;
		}

		$session = MigrationSession::get( static::$session_id );
		$steps   = (array) ( $session['steps'] ?? array() );

		return ! $steps || in_array( $step, $steps, true );
	}

	public static function step_group( string $step ): string {
		/**
		 * Filters the "Choose what to migrate" group of a step.
		 *
		 * @param string $group Group key.
		 * @param string $step  Step name.
		 */
		return (string) apply_filters( 'masterstudy_lms_migration_tool_step_group', self::STEP_GROUPS[ $step ] ?? self::REQUIRED_GROUP, $step );
	}

	/**
	 * Keep the steps of the chosen groups (the required group is always kept), in migrator order.
	 *
	 * @param string[] $steps  Migrator steps.
	 * @param string[] $groups Chosen group keys.
	 * @return string[]
	 */
	public static function steps_for_groups( array $steps, array $groups ): array {
		$groups[] = self::REQUIRED_GROUP;

		return array_values(
			array_filter(
				$steps,
				function ( $step ) use ( $groups ) {
					return in_array( static::step_group( $step ), $groups, true );
				}
			)
		);
	}

	/**
	 * Source LMS migrators shipped with MasterStudy.
	 *
	 * @return string[] Migrator class names.
	 */
	public static function source_migrators(): array {
		return (array) apply_filters(
			'masterstudy_lms_migration_tool_source_migrators',
			array(
				TutorLMSMigrator::class,
				LearnDashMigrator::class,
				LearnPressMigrator::class,
				LifterLMSMigrator::class,
				MasteriyoMigrator::class,
			)
		);
	}

	public static function registry(): MigratorRegistry {
		static $registry = null;

		if ( null !== $registry ) {
			return $registry;
		}

		$registry = new MigratorRegistry();

		foreach ( static::source_migrators() as $migrator_class ) {
			if ( class_exists( $migrator_class ) ) {
				$registry->register( new $migrator_class() );
			}
		}

		/**
		 * Register additional migrators.
		 *
		 * @param MigratorRegistry $registry Registry instance.
		 */
		$registry = apply_filters( 'masterstudy_lms_migration_tool_register', $registry );

		return $registry;
	}

	/**
	 * Active source LMS plugins that have a registered migrator.
	 *
	 * @return Contracts\MigratorInterface[]
	 */
	public static function detect_source_lms(): array {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return array_filter(
			static::registry()->all(),
			array( static::class, 'is_source_active' )
		);
	}

	/**
	 * Whether the source LMS plugin is active (the migration reads it through its own code).
	 */
	public static function is_source_active( Contracts\MigratorInterface $migrator ): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return method_exists( $migrator, 'is_source_plugin_active' )
			? $migrator->is_source_plugin_active( $migrator->get_plugin_file() )
			: is_plugin_active( $migrator->get_plugin_file() );
	}

	/**
	 * Queue one job run. Uses Action Scheduler when available (grouped per session so
	 * it can be cancelled), otherwise falls back to a single WP-Cron event.
	 */
	public static function schedule_process( string $session_id, string $lms_slug, string $step, int $cursor = 0, bool $unique = false ): void {
		$args = array( $session_id, $lms_slug, $step, $cursor );

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( MigrationProcessJob::HOOK, $args, static::group( $session_id ), $unique );
			return;
		}

		wp_schedule_single_event( time(), MigrationProcessJob::HOOK, $args );
	}

	public static function unschedule_process( string $session_id ): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( '', array(), static::group( $session_id ) );
		}

		$crons = _get_cron_array();

		foreach ( (array) $crons as $timestamp => $hooks ) {
			foreach ( (array) ( $hooks[ MigrationProcessJob::HOOK ] ?? array() ) as $event ) {
				if ( ( $event['args'][0] ?? '' ) === $session_id ) {
					wp_unschedule_event( $timestamp, MigrationProcessJob::HOOK, $event['args'] );
				}
			}
		}
	}

	public static function group( string $session_id ): string {
		return 'masterstudy-lms-migration-' . $session_id;
	}

	/**
	 * Write a migration log line. Goes to the WooCommerce logger when present
	 * (WooCommerce → Status → Logs), otherwise to the PHP error log when debug logging is on.
	 *
	 * @param string $level   info|warning|error.
	 * @param string $message Message.
	 */
	public static function log( string $level, string $message ): void {
		/**
		 * Fires for every migration log line.
		 *
		 * @param string $level   Log level.
		 * @param string $message Message.
		 */
		do_action( 'masterstudy_lms_migration_tool_log', $level, $message );

		if ( 'info' !== $level && '' !== static::$session_id ) {
			MigrationSession::append_log( static::$session_id, $level, $message, self::SESSION_LOG_LIMIT );
		}

		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->log( $level, $message, array( 'source' => self::LOG_SOURCE ) );
			return;
		}

		if ( 'info' !== $level || ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) ) {
			error_log( sprintf( '[%s] %s: %s', self::LOG_SOURCE, strtoupper( $level ), $message ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Enable a MasterStudy addon (key of the `stm_lms_addons` option).
	 *
	 * @return bool True when the addon was switched on by this call.
	 */
	public static function enable_addon( string $slug ): bool {
		if ( ! defined( 'STM_LMS_PRO_PATH' ) ) {
			static::log( 'warning', sprintf( 'Migration: cannot enable addon "%s" — MasterStudy LMS Pro is not active.', $slug ) );
			return false;
		}

		$addons = get_option( 'stm_lms_addons', array() );
		$addons = is_array( $addons ) ? $addons : array();

		if ( 'on' === ( $addons[ $slug ] ?? '' ) ) {
			return false;
		}

		$addons[ $slug ] = 'on';

		if ( class_exists( '\STM_LMS_Pro_Addons' ) && method_exists( '\STM_LMS_Pro_Addons', 'update_addons_option' ) ) {
			\STM_LMS_Pro_Addons::update_addons_option( wp_json_encode( $addons ) );
		} else {
			update_option( 'stm_lms_addons', $addons );
		}

		return true;
	}

	/**
	 * Addon slugs requested from inside an item migration, enabled after the batch commits.
	 *
	 * @var string[]
	 */
	private static $requested_addons = array();

	/**
	 * Request an addon from inside migrate_item(). Enabling runs table creation (DDL), which
	 * implicitly commits the open transaction, so the job applies requests after COMMIT.
	 */
	public static function request_addon( string $slug ): void {
		if ( ! static::is_addon_enabled( $slug ) ) {
			static::$requested_addons[ $slug ] = $slug;
		}
	}

	/**
	 * Enable every addon requested during the batch. Must run outside a transaction.
	 *
	 * @return bool Whether an addon was switched on (its code only loads with the next request).
	 */
	public static function flush_addon_requests(): bool {
		$enabled = false;

		foreach ( static::$requested_addons as $slug ) {
			if ( static::enable_addon( $slug ) ) {
				static::log( 'info', sprintf( 'Migration: enabled MasterStudy addon "%s".', $slug ) );
				$enabled = true;
			}

			static::ensure_addon_tables( $slug );
		}

		static::$requested_addons = array();

		return $enabled;
	}

	/**
	 * Tables some addons only create when they load on a later admin request (never in cron).
	 * Runs outside a transaction (dbDelta is DDL).
	 */
	private static function ensure_addon_tables( string $slug ): void {
		$tables = array(
			'scorm'        => array( 'scorm/db.php', 'stm_lms_scorm_table' ),
			'point_system' => array( 'point_system/db.php', 'stm_lms_point_system_table' ),
		);

		if ( ! isset( $tables[ $slug ] ) || ! defined( 'STM_LMS_PRO_ADDONS' ) ) {
			return;
		}

		list( $file, $function ) = $tables[ $slug ];

		if ( ! function_exists( $function ) && file_exists( STM_LMS_PRO_ADDONS . '/' . $file ) ) {
			require_once STM_LMS_PRO_ADDONS . '/' . $file;
		}

		if ( function_exists( $function ) ) {
			$function();
		}
	}

	/**
	 * Whether a MasterStudy addon is enabled.
	 */
	public static function is_addon_enabled( string $slug ): bool {
		$addons = get_option( 'stm_lms_addons', array() );

		return is_array( $addons ) && 'on' === ( $addons[ $slug ] ?? '' );
	}
}
