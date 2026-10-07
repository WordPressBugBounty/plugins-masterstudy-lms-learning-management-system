<?php

namespace MasterStudy\Lms\MigrationTool\Controllers;

use MasterStudy\Lms\MigrationTool\Helper;
use MasterStudy\Lms\MigrationTool\Undo;
use WP_REST_Request;
use WP_REST_Response;

/**
 * GET /migrations/preview?lms=<slug> — what a migration would copy, and what is already migrated. Read only.
 */
class PreviewMigrationController {
	public function __invoke( WP_REST_Request $request ): WP_REST_Response {
		$lms_slug = sanitize_key( (string) $request->get_param( 'lms' ) );
		$migrator = '' !== $lms_slug ? Helper::registry()->get( $lms_slug ) : null;

		if ( ! $migrator ) {
			return new WP_REST_Response(
				array(
					'error_code' => 'migration_invalid_lms',
					'message'    => esc_html__( 'Please select a valid LMS.', 'masterstudy-lms-learning-management-system' ),
				),
				400
			);
		}

		$preview = method_exists( $migrator, 'preview' ) ? $migrator->preview() : array(
			'content' => array(),
			'steps'   => array(),
		);

		$preview['lms']      = $lms_slug;
		$preview['migrated'] = Undo::summary( $lms_slug );

		return new WP_REST_Response( $preview );
	}
}
