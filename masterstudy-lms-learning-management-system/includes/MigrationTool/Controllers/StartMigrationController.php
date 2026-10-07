<?php

namespace MasterStudy\Lms\MigrationTool\Controllers;

use MasterStudy\Lms\MigrationTool\Helper;
use MasterStudy\Lms\MigrationTool\MigrationSession;
use WP_REST_Request;
use WP_REST_Response;

class StartMigrationController {
	public function __invoke( WP_REST_Request $request ): WP_REST_Response {
		$lms_slug = sanitize_text_field( $request->get_param( 'lms_name' ) );
		$registry = Helper::registry();

		if ( empty( $lms_slug ) || ! $registry->has( $lms_slug ) ) {
			return new WP_REST_Response(
				array(
					'error_code' => 'migration_invalid_lms',
					'message'    => esc_html__( 'Please select a valid LMS.', 'masterstudy-lms-learning-management-system' ),
				),
				400
			);
		}

		if ( ! Helper::is_source_active( $registry->get( $lms_slug ) ) ) {
			return new WP_REST_Response(
				array(
					'error_code' => 'migration_source_inactive',
					'message'    => esc_html__( 'Activate the source LMS plugin first: the migration reads its data through the plugin.', 'masterstudy-lms-learning-management-system' ),
				),
				400
			);
		}

		if ( MigrationSession::get_active() ) {
			return new WP_REST_Response(
				array(
					'error_code' => 'migration_already_running',
					'message'    => esc_html__( 'A migration is already running.', 'masterstudy-lms-learning-management-system' ),
				),
				409
			);
		}

		$migrator = $registry->get( $lms_slug );
		$steps    = $migrator->get_steps();
		$groups   = $request->get_param( 'groups' );

		// "Choose what to migrate": keep the steps of the chosen groups (courses & content are always migrated).
		if ( is_array( $groups ) ) {
			$steps = Helper::steps_for_groups( $steps, array_map( 'sanitize_key', $groups ) );
		}

		$session_id = MigrationSession::create( $lms_slug, $steps );
		$first_step = null;

		foreach ( $steps as $step ) {
			try {
				$total = $migrator->count_source_items( $step );
			} catch ( \Throwable $e ) {
				$total = 0;
			}

			MigrationSession::save_step_state(
				$session_id,
				$step,
				array(
					'total'       => $total,
					'last_cursor' => 0,
					'completed'   => 0,
					'failed'      => array(),
				)
			);

			if ( null === $first_step && $total > 0 ) {
				$first_step = $step;
			}
		}

		if ( null === $first_step ) {
			MigrationSession::update(
				$session_id,
				array(
					'status'       => 'completed',
					'completed_at' => time(),
				)
			);

			return new WP_REST_Response(
				array(
					'session_id' => $session_id,
					'status'     => 'completed',
				)
			);
		}

		MigrationSession::update( $session_id, array( 'current_step' => $first_step ) );

		Helper::log(
			'info',
			sprintf( 'Migration session %s started for "%s" — first step with data: "%s".', $session_id, $migrator->get_label(), $first_step )
		);

		Helper::schedule_process( $session_id, $lms_slug, $first_step, 0 );

		return new WP_REST_Response(
			array(
				'session_id' => $session_id,
				'status'     => 'running',
			)
		);
	}
}
