<?php
function stm_lms_settings_analytics_section() {
	$is_pro_plus = STM_LMS_Helpers::is_pro_plus();
	$main_fields = array(
		'name'   => esc_html__( 'Reports & Analytics', 'masterstudy-lms-learning-management-system' ),
		'label'  => esc_html__( 'Reports & Analytics', 'masterstudy-lms-learning-management-system' ),
		'icon'   => 'fas fa-chart-pie',
		'fields' => array(
			'pro_banner' => array(
				'type'        => 'pro_banner',
				'label'       => esc_html__( 'Reports & Analytics', 'masterstudy-lms-learning-management-system' ),
				'img'         => STM_LMS_URL . 'assets/img/pro-features/analytics.png',
				'desc'        => esc_html__( 'Track your success with Reports and Statistics! See your earnings, courses, students, and certificates in one place. Students can also see their progress, course bundles, group courses, reviews, certificates and points.', 'masterstudy-lms-learning-management-system' ),
				'hint'        => esc_html__( 'Unlock', 'masterstudy-lms-learning-management-system' ),
				'is_pro_plus' => ! $is_pro_plus,
				'utm_url'     => 'https://stylemixthemes.com/wordpress-lms-plugin/pricing/?utm_source=mswpadmin&utm_medium=reports-&-analytics-button&utm_campaign=masterstudy-plugin',
			),
		),
	);

	if ( $is_pro_plus ) {
		$analytics_reports = array(
			'instructors_reports' => array(
				'type'        => 'checkbox',
				'label'       => esc_html__( 'Reports for instructors', 'masterstudy-lms-learning-management-system' ),
				'description' => esc_html__( 'Show detailed course reports to instructors.', 'masterstudy-lms-learning-management-system' ),
				'value'       => true,
			),
			'instructors_payouts' => array(
				'type'        => 'checkbox',
				'label'       => esc_html__( "Display information about the instructor's profit on the course", 'masterstudy-lms-learning-management-system' ),
				'description' => esc_html__( 'Show only if the admin enabled the “Show Payouts statistics to instructor” option in the settings.', 'masterstudy-lms-learning-management-system' ),
				'value'       => true,
			),
			'student_reports'     => array(
				'type'        => 'checkbox',
				'label'       => esc_html__( 'Reports for students', 'masterstudy-lms-learning-management-system' ),
				'description' => esc_html__( 'Allow students to see reports on enrolled courses.', 'masterstudy-lms-learning-management-system' ),
				'value'       => true,
			),
		);

		$main_fields['fields'] = array_merge( $analytics_reports, $main_fields['fields'] );
	}

	return $main_fields;
}
