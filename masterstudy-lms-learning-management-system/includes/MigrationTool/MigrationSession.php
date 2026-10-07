<?php

namespace MasterStudy\Lms\MigrationTool;

defined( 'ABSPATH' ) || exit;

/**
 * Manages migration session lifecycle and per-step progress state.
 */
class MigrationSession {

	const SESSION_PREFIX = 'masterstudy_lms_migration_session_';

	const TERMINAL_STATUSES = array( 'completed', 'failed', 'cancelled' );

	/**
	 * Create a new migration session. Returns the session ID.
	 *
	 * Prunes old terminal sessions before inserting so wp_options does not
	 * accumulate unbounded rows across repeated migration runs.
	 *
	 * @param string   $lms_slug LMS plugin slug.
	 * @param string[] $steps    Ordered step names.
	 */
	public static function create( string $lms_slug, array $steps ): string {
		static::cleanup_terminal_sessions();

		$session_id = substr( md5( uniqid( '', true ) ), 0, 16 );

		add_option(
			self::SESSION_PREFIX . $session_id,
			array(
				'lms_slug'     => $lms_slug,
				'status'       => 'running',
				'current_step' => $steps[0] ?? '',
				'steps'        => $steps,
				'step_states'  => array(),
				'started_at'   => time(),
				'completed_at' => null,
			),
			'',
			'no'
		);

		return $session_id;
	}

	/**
	 * Retrieve session data by ID.
	 *
	 * Always bypasses the object cache so a running background job immediately
	 * sees status changes (e.g. cancel) written by a concurrent HTTP request.
	 */
	public static function get( string $session_id ): ?array {
		wp_cache_delete( self::SESSION_PREFIX . $session_id, 'options' );
		$data = get_option( self::SESSION_PREFIX . $session_id, null );

		return is_array( $data ) ? $data : null;
	}

	public static function update( string $session_id, array $changes ): void {
		$data = static::get( $session_id );

		// Remember when the session ended (completed, failed or cancelled): the report shows how long it took.
		if ( $data && in_array( $changes['status'] ?? '', self::TERMINAL_STATUSES, true ) && empty( $data['finished_at'] ) ) {
			$changes['finished_at'] = (int) ( $changes['completed_at'] ?? time() );
		}

		if ( $data ) {
			update_option( self::SESSION_PREFIX . $session_id, array_merge( $data, $changes ), false );
		}
	}

	/**
	 * Per-step cursor/progress state, stored inline in the session option so a
	 * status poll reads the whole session in one get_option() call.
	 *
	 * @return array{ total: int, last_cursor: int, completed: int, failed: int[] }
	 */
	public static function get_step_state( string $session_id, string $step ): array {
		$session = static::get( $session_id );

		if ( $session && isset( $session['step_states'][ $step ] ) ) {
			return $session['step_states'][ $step ];
		}

		return static::default_step_state();
	}

	public static function save_step_state( string $session_id, string $step, array $state ): void {
		$session = static::get( $session_id );

		if ( ! $session ) {
			return;
		}

		$session['step_states'][ $step ] = $state;
		update_option( self::SESSION_PREFIX . $session_id, $session, false );
	}

	/**
	 * Attach a warning/error line to the session (oldest entries are dropped past $limit).
	 */
	public static function append_log( string $session_id, string $level, string $message, int $limit = 200 ): void {
		$session = static::get( $session_id );

		if ( ! $session ) {
			return;
		}

		$log   = (array) ( $session['log'] ?? array() );
		$log[] = array(
			'time'    => time(),
			'level'   => $level,
			'message' => $message,
		);

		$session['log'] = array_slice( $log, -$limit );
		update_option( self::SESSION_PREFIX . $session_id, $session, false );
	}

	/**
	 * Add an item to the session "not imported" report (see Report). Items past $limit per
	 * group are counted but not stored.
	 */
	public static function append_report( string $session_id, string $group, array $item, int $limit = 500 ): void {
		$session = static::get( $session_id );

		if ( ! $session ) {
			return;
		}

		$data = $session['report'][ $group ] ?? array(
			'total'  => 0,
			'counts' => array(),
			'items'  => array(),
		);

		++$data['total'];
		$data['counts'][ $item['status'] ] = (int) ( $data['counts'][ $item['status'] ] ?? 0 ) + 1;

		if ( count( $data['items'] ) < $limit ) {
			$data['items'][] = $item;
		}

		$session['report'][ $group ] = $data;
		update_option( self::SESSION_PREFIX . $session_id, $session, false );
	}

	public static function default_step_state(): array {
		return array(
			'total'       => 0,
			'last_cursor' => 0,
			'completed'   => 0,
			'failed'      => array(),
		);
	}

	/**
	 * Most recent completed session.
	 *
	 * @return array{ session_id: string, session: array }|null
	 */
	public static function get_last_completed(): ?array {
		foreach ( static::all_rows() as $session_id => $data ) {
			if ( 'completed' === ( $data['status'] ?? '' ) ) {
				return array(
					'session_id' => $session_id,
					'session'    => $data,
				);
			}
		}

		return null;
	}

	/**
	 * Most recent non-terminal session (running or paused).
	 *
	 * @return array{ session_id: string, session: array }|null
	 */
	public static function get_active(): ?array {
		foreach ( static::all_rows() as $session_id => $data ) {
			if ( ! in_array( $data['status'] ?? '', self::TERMINAL_STATUSES, true ) ) {
				return array(
					'session_id' => $session_id,
					'session'    => $data,
				);
			}
		}

		return null;
	}

	/**
	 * Delete a session record.
	 */
	public static function cleanup( string $session_id ): void {
		delete_option( self::SESSION_PREFIX . $session_id );
	}

	/**
	 * Most recent completed session ID of each LMS (its report can be reopened).
	 *
	 * @return array<string, string> LMS slug => session ID.
	 */
	public static function get_last_completed_by_lms(): array {
		$sessions = array();

		foreach ( static::all_rows() as $session_id => $data ) {
			$slug = (string) ( $data['lms_slug'] ?? '' );

			if ( 'completed' === ( $data['status'] ?? '' ) && '' !== $slug && ! isset( $sessions[ $slug ] ) ) {
				$sessions[ $slug ] = (string) $session_id;
			}
		}

		return $sessions;
	}

	/**
	 * Delete all terminal sessions except the most recent completed one of each LMS,
	 * which the admin page reads to show "last migration completed" and its report.
	 */
	private static function cleanup_terminal_sessions(): void {
		$kept = static::get_last_completed_by_lms();

		foreach ( static::all_rows() as $session_id => $data ) {
			if ( ! in_array( $data['status'] ?? '', self::TERMINAL_STATUSES, true ) || in_array( (string) $session_id, $kept, true ) ) {
				continue;
			}

			static::cleanup( (string) $session_id );
		}
	}

	/**
	 * All session rows, newest first, keyed by session ID.
	 *
	 * @return array<string, array>
	 */
	private static function all_rows(): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id DESC",
				$wpdb->esc_like( self::SESSION_PREFIX ) . '%'
			),
			ARRAY_A
		);

		$sessions = array();

		foreach ( (array) $rows as $row ) {
			$data = maybe_unserialize( $row['option_value'] );

			if ( is_array( $data ) ) {
				$sessions[ substr( $row['option_name'], strlen( self::SESSION_PREFIX ) ) ] = $data;
			}
		}

		return $sessions;
	}
}
