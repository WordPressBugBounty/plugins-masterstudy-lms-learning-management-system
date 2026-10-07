<?php

namespace MasterStudy\Lms\MigrationTool\Controllers;

use MasterStudy\Lms\MigrationTool\Helper;
use MasterStudy\Lms\MigrationTool\MigrationSession;
use WP_REST_Response;

/**
 * GET /migrations/active — the most recent non-terminal session with full progress
 * (`data: null` when none), the last completed migration for the admin banner and the
 * latest completed session of each LMS (`completed`: slug => session ID) to reopen its report.
 */
class GetActiveMigrationController {
	public function __invoke(): WP_REST_Response {
		$active_data = null;
		$active      = MigrationSession::get_active();

		if ( $active ) {
			$active_data = ( new GetMigrationStatusController() )->format_session( $active['session_id'], $active['session'] );
		}

		$last_completed_data = null;
		$last                = MigrationSession::get_last_completed();

		if ( $last ) {
			$lms_slug = $last['session']['lms_slug'] ?? '';
			$registry = Helper::registry();

			$last_completed_data = array(
				'session_id'   => $last['session_id'],
				'lms_slug'     => $lms_slug,
				'lms_label'    => $lms_slug && $registry->has( $lms_slug ) ? $registry->get( $lms_slug )->get_label() : '',
				'completed_at' => $last['session']['completed_at'] ?? null,
			);
		}

		return new WP_REST_Response(
			array(
				'data'           => $active_data,
				'last_completed' => $last_completed_data,
				'completed'      => (object) MigrationSession::get_last_completed_by_lms(),
			)
		);
	}
}
