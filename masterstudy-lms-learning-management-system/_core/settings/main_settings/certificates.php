<?php

function stm_lms_settings_certificates_section() {
	$certificate_settings_fields = array(
		'name'   => esc_html__( 'Certificates', 'masterstudy-lms-learning-management-system' ),
		'label'  => esc_html__( 'Certificates Settings', 'masterstudy-lms-learning-management-system' ),
		'icon'   => 'fas fa-certificate',
		'fields' => array(
			'certificate_threshold'    => array(
				'type'        => 'number',
				'pro'         => true,
				'pro_url'     => admin_url( 'admin.php?page=stm-lms-go-pro' ),
				'label'       => esc_html__( 'Certificate threshold (%)', 'masterstudy-lms-learning-management-system' ),
				'description' => esc_html__( 'Set the minimum percentage score required for a student to earn a certificate upon completion of a course', 'masterstudy-lms-learning-management-system' ),
				'value'       => 70,
			),
			'instructors_certificates' => array(
				'type'        => 'checkbox',
				'pro'         => true,
				'label'       => esc_html__( 'Allow instructors to create certificates', 'masterstudy-lms-learning-management-system' ),
				'description' => esc_html__( 'Decide whether instructors are permitted to create certificates for their courses', 'masterstudy-lms-learning-management-system' ),
				'value'       => false,
			),
			'user_name_certificates'   => array(
				'type'        => 'checkbox',
				'pro'         => true,
				'label'       => esc_html__( 'Use current student name on certificate', 'masterstudy-lms-learning-management-system' ),
				'description' => esc_html__( 'If enabled, certificates will use the user\'s current name from their profile. If disabled, the initially provided name will be displayed.', 'masterstudy-lms-learning-management-system' ),
				'value'       => false,
			),
			'certificate_banner'       => array(
				'type'        => 'notification_message',
				'image'       => STM_LMS_URL . 'assets/addons/certtificate_builder.png',
				'description' => sprintf( '<h1>Certificate Builder</h1> <p>Сreate and design your own certificates to award them to students after the course completion.</p>' ),
				'buttons'     => array(
					array(
						'url'      => is_ms_lms_addon_enabled( 'certificate_builder' ) ? esc_url( admin_url( 'admin.php?page=certificate_builder' ) ) : ( admin_url( 'admin-ajax.php?action=stm_lms_enable_addon&addon=certificate_builder' ) ),
						'text'     => esc_html__( 'GET STARTED', 'masterstudy-lms-learning-management-system' ),
						'data-url' => esc_url( admin_url( 'admin.php?page=certificate_builder' ) ),
						'class'    => is_ms_lms_addon_enabled( 'certificate_builder' ) ? 'enabled' : 'disabled ',
					),
					array(
						'url'   => admin_url( 'admin.php?page=certificate_builder' ),
						'class' => 'data-url-certificate hide',
					),
				),
				'pro'         => true,
				'pro_url'     => admin_url( 'admin.php?page=stm-lms-go-pro' ),
			),
		),
	);

	if ( ! STM_LMS_Helpers::is_pro() ) {
		$certificate_settings_fields = array(
			'name'   => esc_html__( 'Certificates', 'masterstudy-lms-learning-management-system' ),
			'label'  => esc_html__( 'Certificates Settings', 'masterstudy-lms-learning-management-system' ),
			'icon'   => 'fas fa-certificate',
			'fields' => array(
				'pro_banner' => array(
					'type'  => 'pro_banner',
					'label' => esc_html__( 'Certificate Builder', 'masterstudy-lms-learning-management-system' ),
					'img'   => STM_LMS_URL . 'assets/img/pro-features/certificate.png',
					'desc'  => esc_html__( 'Upgrade to Pro and craft certificates that truly grab attention and leave a lasting impression.', 'masterstudy-lms-learning-management-system' ),
					'hint'  => esc_html__( 'Unlock', 'masterstudy-lms-learning-management-system' ),
				),
			),
		);
	}

	return $certificate_settings_fields;
}
