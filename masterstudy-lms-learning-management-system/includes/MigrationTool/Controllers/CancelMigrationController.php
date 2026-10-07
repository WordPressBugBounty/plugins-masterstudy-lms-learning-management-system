<?php

namespace MasterStudy\Lms\MigrationTool\Controllers;

use MasterStudy\Lms\MigrationTool\Helper;
use MasterStudy\Lms\MigrationTool\MigrationSession;
use WP_REST_Response;

class CancelMigrationController {
	public function __invoke( string $session_id ): WP_REST_Response {
		$session_id = sanitize_text_field( $session_id );
		$session    = MigrationSession::get( $session_id );

		if ( ! $session ) {
			return new WP_REST_Response(
				array(
					'error_code' => 'migration_session_not_found',
					'message'    => esc_html__( 'Migration session not found.', 'masterstudy-lms-learning-management-system' ),
				),
				404
			);
		}

		if ( in_array( $session['status'] ?? '', array( 'completed', 'failed', 'cancelled' ), true ) ) {
			return new WP_REST_Response(
				array(
					'error_code' => 'migration_already_terminal',
					'message'    => esc_html__( 'This migration is already finished.', 'masterstudy-lms-learning-management-system' ),
				),
				400
			);
		}

		Helper::unschedule_process( $session_id );
		MigrationSession::update( $session_id, array( 'status' => 'cancelled' ) );

		// Cancel any additional active sessions left from previous aborted runs.
		for ( $limit = 10; $limit > 0; $limit-- ) {
			$extra = MigrationSession::get_active();

			if ( ! $extra ) {
				break;
			}

			Helper::unschedule_process( $extra['session_id'] );
			MigrationSession::update( $extra['session_id'], array( 'status' => 'cancelled' ) );
		}

		return new WP_REST_Response( array( 'status' => 'cancelled' ) );
	}
}
