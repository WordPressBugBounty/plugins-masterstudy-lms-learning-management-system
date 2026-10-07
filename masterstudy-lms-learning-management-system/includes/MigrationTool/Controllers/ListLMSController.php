<?php

namespace MasterStudy\Lms\MigrationTool\Controllers;

use MasterStudy\Lms\MigrationTool\Helper;
use MasterStudy\Lms\MigrationTool\Target;
use WP_REST_Response;

class ListLMSController {
	public function __invoke(): WP_REST_Response {
		global $wpdb;

		$data = array();

		// Copies per source: an LMS that was deactivated after its migration still needs "Delete migrated data".
		$migrated = wp_list_pluck(
			(array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT f.meta_value AS source, COUNT(*) AS total FROM {$wpdb->postmeta} f INNER JOIN {$wpdb->postmeta} k ON k.post_id = f.post_id AND k.meta_key = '_masterstudy_migrated_source_id' WHERE f.meta_key = %s GROUP BY f.meta_value",
					Target::SOURCE_META
				)
			),
			'total',
			'source'
		);

		foreach ( Helper::registry()->all() as $migrator ) {
			$data[] = array(
				'name'        => $migrator->get_slug(),
				'label'       => $migrator->get_label(),
				'steps'       => $migrator->get_steps(),
				'unsupported' => method_exists( $migrator, 'get_unsupported' ) ? $migrator->get_unsupported() : array(),
				'active'      => $migrator->is_source_plugin_active( $migrator->get_plugin_file() ),
				'migrated'    => (int) ( $migrated[ $migrator->get_slug() ] ?? 0 ),
			);
		}

		return new WP_REST_Response( array( 'data' => $data ) );
	}
}
