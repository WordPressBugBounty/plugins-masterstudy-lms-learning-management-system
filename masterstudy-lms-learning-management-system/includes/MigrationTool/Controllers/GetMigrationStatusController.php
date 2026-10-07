<?php

namespace MasterStudy\Lms\MigrationTool\Controllers;

use MasterStudy\Lms\MigrationTool\MigrationSession;
use MasterStudy\Lms\MigrationTool\Report;
use WP_REST_Response;

class GetMigrationStatusController {
	public function __invoke( string $session_id ): WP_REST_Response {
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

		return new WP_REST_Response( $this->format_session( $session_id, $session ) );
	}

	public function format_session( string $session_id, array $session ): array {
		$steps           = array();
		$weight_sum      = 0.0;
		$non_empty_steps = 0;
		$is_completed    = 'completed' === ( $session['status'] ?? '' );
		$current_idx     = array_search( $session['current_step'] ?? '', (array) $session['steps'], true );

		if ( false === $current_idx ) {
			$current_idx = count( (array) $session['steps'] );
		}

		foreach ( (array) $session['steps'] as $idx => $step ) {
			$state = $session['step_states'][ $step ] ?? MigrationSession::default_step_state();
			$total = (int) $state['total'];
			$done  = min( (int) $state['completed'], $total );
			$past  = $is_completed || $idx < $current_idx;

			if ( $total > 0 ) {
				$pct         = $past ? 100 : (int) round( $done / $total * 100 );
				$weight_sum += $past ? 1.0 : $done / $total;
				++$non_empty_steps;
			} else {
				$pct = $past ? 100 : 0;
			}

			$steps[ $step ] = array(
				'total'     => $total,
				'completed' => $done,
				'failed'    => count( (array) $state['failed'] ),
				'offset'    => $done,
				'pct'       => $pct,
			);
		}

		$overall_pct = $non_empty_steps > 0 ? (int) floor( $weight_sum / $non_empty_steps * 100 ) : ( $is_completed ? 100 : 0 );

		if ( ! $is_completed && $overall_pct >= 100 ) {
			$overall_pct = 99;
		}

		// Nudge WP-Cron so pending jobs start promptly on hosts where loopback
		// requests are throttled. Non-blocking — adds no latency.
		if ( 'running' === $session['status'] ) {
			spawn_cron();
		}

		// Running: time so far. Finished: how long it took (sessions saved without an end time show none).
		$end = in_array( $session['status'], MigrationSession::TERMINAL_STATUSES, true )
			? (int) ( $session['finished_at'] ?? $session['completed_at'] ?? 0 )
			: time();

		return array(
			'session_id'      => $session_id,
			'status'          => $session['status'],
			'lms_slug'        => $session['lms_slug'],
			'current_step'    => $session['current_step'],
			'elapsed_seconds' => $end ? max( 0, $end - (int) $session['started_at'] ) : null,
			'steps'           => $steps,
			'overall_pct'     => $overall_pct,
			'report'          => Report::summary( $session ),
			'log'             => array_values( (array) ( $session['log'] ?? array() ) ),
			'failed_total'    => array_sum( array_column( $steps, 'failed' ) ),
		);
	}
}
