<?php

namespace MasterStudy\Lms\MigrationTool\Controllers;

use MasterStudy\Lms\MigrationTool\Helper;
use MasterStudy\Lms\MigrationTool\Undo;
use WP_REST_Request;
use WP_REST_Response;

/**
 * GET /migrations/undo?lms=<slug> — what "Delete migrated data" would remove.
 */
class GetUndoSummaryController {
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

		return new WP_REST_Response( Undo::summary( $lms_slug ) );
	}
}
