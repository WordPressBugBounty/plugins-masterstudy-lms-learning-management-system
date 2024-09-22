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
		$student_reports = array(
			'student_reports' => array(
				'type'        => 'checkbox',
				'label'       => esc_html__( 'Stats for students', 'masterstudy-lms-learning-management-system' ),
				'description' => esc_html__( 'It lets students see their courses, check their progress, and look at their achievements on the dashboard.', 'masterstudy-lms-learning-management-system' ),
				'value'       => true,
			),
		);

		$main_fields['fields'] = array_merge( $student_reports, $main_fields['fields'] );
	}

	return $main_fields;
}
