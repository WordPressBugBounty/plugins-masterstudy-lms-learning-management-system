<?php

namespace MasterStudy\Lms\MigrationTool\Controllers;

use MasterStudy\Lms\MigrationTool\MigrationSession;
use MasterStudy\Lms\MigrationTool\Report;
use WP_REST_Response;

/**
 * GET /migrations/{session_id}/report/{group} — items of one "not imported" group.
 */
class GetMigrationReportController {
	public function __invoke( string $session_id, string $group ): WP_REST_Response {
		$session = MigrationSession::get( sanitize_text_field( $session_id ) );

		if ( ! $session ) {
			return new WP_REST_Response(
				array(
					'error_code' => 'migration_session_not_found',
					'message'    => esc_html__( 'Migration session not found.', 'masterstudy-lms-learning-management-system' ),
				),
				404
			);
		}

		$group = sanitize_key( $group );

		return new WP_REST_Response(
			array(
				'group'   => $group,
				'summary' => Report::summary( $session )[ $group ] ?? null,
				'items'   => Report::items( $session, $group ),
			)
		);
	}
}
