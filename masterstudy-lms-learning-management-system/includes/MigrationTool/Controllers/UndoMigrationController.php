<?php

namespace MasterStudy\Lms\MigrationTool\Controllers;

use MasterStudy\Lms\MigrationTool\Helper;
use MasterStudy\Lms\MigrationTool\MigrationSession;
use MasterStudy\Lms\MigrationTool\Undo;
use WP_REST_Request;
use WP_REST_Response;

/**
 * POST /migrations/undo { lms } — delete one batch of the data migrated from a source LMS.
 * The client repeats the call until `done` is true.
 */
class UndoMigrationController {
	public function __invoke( WP_REST_Request $request ): WP_REST_Response {
		$lms_slug = sanitize_key( (string) $request->get_param( 'lms' ) );

		if ( '' === $lms_slug || ! Helper::registry()->has( $lms_slug ) ) {
			return new WP_REST_Response(
				array(
					'error_code' => 'migration_invalid_lms',
					'message'    => esc_html__( 'Please select a valid LMS.', 'masterstudy-lms-learning-management-system' ),
				),
				400
			);
		}

		if ( MigrationSession::get_active() ) {
			return new WP_REST_Response(
				array(
					'error_code' => 'migration_already_running',
					'message'    => esc_html__( 'A migration is running. Wait until it finishes or cancel it first.', 'masterstudy-lms-learning-management-system' ),
				),
				409
			);
		}

		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}

		return new WP_REST_Response( Undo::run_batch( $lms_slug ) );
	}
}
