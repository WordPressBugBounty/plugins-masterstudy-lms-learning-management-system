<?php

namespace MasterStudy\Lms\MigrationTool;

defined( 'ABSPATH' ) || exit;

/**
 * Records migration writes that cannot be found later through a MasterStudy copy (coupons, subscription
 * plans, roles and instructor statuses given to existing users), so "Delete migrated data" can undo them.
 *
 * Written inside the item transaction: a rolled-back item leaves no ledger entry.
 */
class Ledger {

	const OPTION_PREFIX = 'masterstudy_lms_migration_ledger_';

	/**
	 * Record an entry. An existing entry is kept, so the first recorded value (e.g. the previous
	 * instructor status) wins.
	 *
	 * @param string $source Source LMS slug ('' = not inside a migration: nothing is recorded).
	 * @param string $type   Entry type, e.g. coupons, subscription_plans, instructor_roles, submission_status.
	 * @param int    $id     Row or user ID.
	 * @param mixed  $value  Value to restore on undo (default true).
	 */
	public static function add( string $source, string $type, int $id, $value = true ): void {
		if ( '' === $source || $id <= 0 ) {
			return;
		}

		$ledger = static::get( $source );

		if ( isset( $ledger[ $type ] ) && array_key_exists( $id, $ledger[ $type ] ) ) {
			return;
		}

		$ledger[ $type ][ $id ] = $value;

		update_option( self::OPTION_PREFIX . $source, $ledger, false );
	}

	/**
	 * @return array<string, array<int, mixed>> Type => ( ID => value ).
	 */
	public static function get( string $source ): array {
		$ledger = get_option( self::OPTION_PREFIX . $source, array() );

		return is_array( $ledger ) ? $ledger : array();
	}

	public static function clear( string $source ): void {
		delete_option( self::OPTION_PREFIX . $source );
	}
}
