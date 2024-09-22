<?php

function stm_lms_settings_route_section() {
	$pages                = WPCFTO_Settings::stm_get_post_type_array( 'page' );
	$page_list            = stm_lms_generate_pages_list();
	$archive_page_list    = stm_lms_archive_page_list();
	$archive_courses_page = stm_lms_get_generated_archive_pages();
	$settings             = stm_wpcfto_get_options( 'stm_lms_settings' );
	if ( isset( $settings['courses_page_elementor'] ) && $settings['courses_page_elementor'] !== $archive_courses_page['elementor'] && $settings['courses_page_gutenberg'] !== $archive_courses_page['gutenberg'] ) {
		$settings['courses_page_elementor'] = ( ! empty( $archive_courses_page ) && isset( $archive_courses_page['elementor']['title'] ) ) ? $archive_courses_page['elementor']['title'] : esc_html__( 'Page not generated', 'masterstudy-lms-learning-management-system' );
		$settings['courses_page_gutenberg'] = ( ! empty( $archive_courses_page ) && isset( $archive_courses_page['gutenberg']['title'] ) ) ? $archive_courses_page['gutenberg']['title'] : esc_html__( 'Page not generated', 'masterstudy-lms-learning-management-system' );

		update_option( 'stm_lms_settings', $settings );
	}

	$data = array(
		'icon'   => 'fas fa-link',
		'name'   => esc_html__( 'LMS Pages', 'masterstudy-lms-learning-management-system' ),
		'fields' => array(

			'user_url'               => array(
				'type'        => 'select',
				'label'       => esc_html__( 'User account', 'masterstudy-lms-learning-management-system' ),
				'description' => esc_html__( 'Choose the page where users can manage their account settings and information', 'masterstudy-lms-learning-management-system' ),
				'options'     => $pages,
			),

			'user_url_profile'       => array(
				'type'        => 'select',
				'label'       => esc_html__( 'User public account', 'masterstudy-lms-learning-management-system' ),
				'description' => esc_html__( "Select the page where users' profiles will be displayed", 'masterstudy-lms-learning-management-system' ),
				'options'     => $pages,
			),

			'wishlist_url'           => array(
				'type'        => 'select',
				'label'       => esc_html__( 'Wishlist', 'masterstudy-lms-learning-management-system' ),
				'description' => esc_html__( 'Choose the page where users can view and manage their saved courses', 'masterstudy-lms-learning-management-system' ),
				'options'     => $pages,
			),

			'checkout_url'           => array(
				'type'            => 'select',
				'options'         => $pages,
				'label'           => esc_html__( 'Checkout', 'masterstudy-lms-learning-management-system' ),
				'description'     => esc_html__( 'Select the page where users will complete their course purchases', 'masterstudy-lms-learning-management-system' ),
				'dependency'      => array(
					'key'     => 'wocommerce_checkout',
					'value'   => 'not_empty',
					'section' => 'section_1',
				),
				'dependency_mode' => 'disabled',
			),
			'courses_page_elementor' => array(
				'type'           => 'text',
				'label'          => esc_html__( 'Courses page (for Elementor)', 'masterstudy-lms-learning-management-system' ),
				'description'    => esc_html__( 'Choose the page layout for displaying courses if you are using the Elementor page builder', 'masterstudy-lms-learning-management-system' ),
				'value'          => ( ! empty( $archive_courses_page['elementor'] ) ) ? $archive_courses_page['elementor']['title'] : esc_html__( 'Page not generated', 'masterstudy-lms-learning-management-system' ),
				'field_disabled' => 'yes',
			),
			'courses_page_gutenberg' => array(
				'type'           => 'text',
				'label'          => esc_html__( 'Courses page (for Gutenberg)', 'masterstudy-lms-learning-management-system' ),
				'description'    => esc_html__( 'Choose the page layout for displaying courses if you are using the Gutenberg page builder', 'masterstudy-lms-learning-management-system' ),
				'value'          => ( ! empty( $archive_courses_page['gutenberg'] ) ) ? $archive_courses_page['gutenberg']['title'] : esc_html__( 'Page not generated', 'masterstudy-lms-learning-management-system' ),
				'field_disabled' => 'yes',
			),
		),
	);

	if ( ! stm_lms_has_generated_pages( $page_list ) || ! stm_lms_has_generated_archive_pages( $archive_page_list ) ) {
		$data['fields']['lms_pages'] = array(
			'type'    => 'generate_page',
			'options' => $page_list,
			'label'   => esc_html__( 'Generate LMS Pages', 'masterstudy-lms-learning-management-system' ),
		);
	}

	return $data;
}
