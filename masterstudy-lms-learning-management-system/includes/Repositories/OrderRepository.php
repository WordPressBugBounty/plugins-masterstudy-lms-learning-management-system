<?php

namespace MasterStudy\Lms\Repositories;

final class OrderRepository {

	public function get_all( array $request = array() ) : array {
		$user     = get_current_user_id();
		$per_page = $request['per_page'] ?? 10;
		$page     = $request['current_page'] ?? 1;
		$offset   = ( $page - 1 ) * $per_page;

		global $wpdb;

		$base_query = "
			LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type = %s 
			AND p.post_status = %s 
			AND pm.meta_key = 'user_id'
			AND pm.meta_value = %d
		";

		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p " . $base_query, // phpcs:ignore
				'stm-orders',
				'publish',
				$user
			)
		);

		$results = $wpdb->get_results(
			$wpdb->prepare( // phpcs:ignore
				'SELECT p.ID, p.post_date, p.post_status FROM ' . $wpdb->posts . ' p ' . $base_query . ' ORDER BY p.post_date DESC LIMIT %d OFFSET %d', // phpcs:ignore
				'stm-orders',
				'publish',
				$user,
				$per_page,
				$offset
			),
			ARRAY_A
		);

		$posts = array_map(
			function ( $post ) {
				return \STM_LMS_Order::get_order_info( $post['ID'] );
			},
			$results,
		);

		return array(
			'success'      => true,
			'orders'       => $posts,
			'pages'        => (int) ceil( $total / $per_page ),
			'current_page' => (int) $page,
			'total_orders' => (int) $total,
			'total'        => ( $total <= $offset + $per_page ),
			'i18n'         => self::translates(),
		);
	}

	public function translates() {
		return array(
			'no_order_title'       => esc_html__( 'No orders yet', 'masterstudy-lms-learning-management-system' ),
			'no_order_description' => esc_html__( 'All information about your orders will be displayed here', 'masterstudy-lms-learning-management-system' ),
		);
	}
}

