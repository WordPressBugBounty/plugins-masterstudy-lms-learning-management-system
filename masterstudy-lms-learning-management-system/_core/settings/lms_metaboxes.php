<?php

add_filter(
	'stm_wpcfto_boxes',
	function ( $boxes ) {
		$data_boxes = array(
			// TODO Remove Question Settings (stm_question_settings)
			'stm_question_settings' => array(
				'post_type' => array( 'stm-questions' ),
				'label'     => esc_html__( 'Question Settings', 'masterstudy-lms-learning-management-system' ),
			),
			'stm_reviews'           => array(
				'post_type' => array( 'stm-reviews' ),
				'label'     => esc_html__( 'Review info', 'masterstudy-lms-learning-management-system' ),
			),
			'stm_order_info'        => array(
				'post_type'      => array( 'stm-orders' ),
				'label'          => esc_html__( 'Order info', 'masterstudy-lms-learning-management-system' ),
				'skip_post_type' => 1,
			),
		);

		return array_merge( $data_boxes, $boxes );
	}
);

add_filter(
	'stm_wpcfto_fields',
	function ( $fields ) {
		$courses = ( class_exists( 'WPCFTO_Settings' ) ) ? WPCFTO_Settings::stm_get_post_type_array( 'stm-courses' ) : array();

		$data_fields = array(
			// TODO Remove stm_courses_settings after few releases - stm_courses_settings, stm_lesson_settings
			'stm_courses_settings'  => array(),
			'stm_lesson_settings'   => array(),
			'stm_question_settings' => array(
				'section_question_settings' => array(
					'name'   => esc_html__( 'Question Settings', 'masterstudy-lms-learning-management-system' ),
					'fields' => array(
						'type'                 => array(
							'type'    => 'select',
							'label'   => esc_html__( 'Question type', 'masterstudy-lms-learning-management-system' ),
							'options' => array(
								'single_choice' => esc_html__( 'Single choice', 'masterstudy-lms-learning-management-system' ),
								'multi_choice'  => esc_html__( 'Multi choice', 'masterstudy-lms-learning-management-system' ),
								'true_false'    => esc_html__( 'True or False', 'masterstudy-lms-learning-management-system' ),
								'item_match'    => esc_html__( 'Item Match', 'masterstudy-lms-learning-management-system' ),
								'image_match'   => esc_html__( 'Image Match', 'masterstudy-lms-learning-management-system' ),
								'keywords'      => esc_html__( 'Keywords', 'masterstudy-lms-learning-management-system' ),
								'fill_the_gap'  => esc_html__( 'Fill the Gap', 'masterstudy-lms-learning-management-system' ),
							),
							'value'   => 'single_choice',
						),
						'answers'              => array(
							'type'         => 'answers',
							'label'        => esc_html__( 'Answers', 'masterstudy-lms-learning-management-system' ),
							'requirements' => 'type',
						),
						'question_explanation' => array(
							'type'  => 'textarea',
							'label' => esc_html__( 'Question result explanation', 'masterstudy-lms-learning-management-system' ),
						),
						'question_view_type'   => array(
							'type' => 'not_exist',
						),
					),
				),
			),
			'stm_reviews'           => array(
				'section_data' => array(
					'name'   => esc_html__( 'Review info', 'masterstudy-lms-learning-management-system' ),
					'fields' => array(
						'review_course' => array(
							'type'    => 'select',
							'label'   => esc_html__( 'Course Reviewed', 'masterstudy-lms-learning-management-system' ),
							'options' => $courses,
						),
						'review_user'   => array(
							'type'      => 'autocomplete',
							'post_type' => array( 'post' ),
							'label'     => esc_html__( 'User Reviewed', 'masterstudy-lms-learning-management-system' ),
							'limit'     => 1,
						),
						'review_mark'   => array(
							'type'    => 'select',
							'label'   => esc_html__( 'User Review mark', 'masterstudy-lms-learning-management-system' ),
							'options' => array(
								'5' => '5',
								'4' => '4',
								'3' => '3',
								'2' => '2',
								'1' => '1',
							),
						),
					),
				),
			),
			'stm_order_info'        => array(
				'order_info' => array(
					'name'   => esc_html__( 'Order', 'masterstudy-lms-learning-management-system' ),
					'fields' => array(
						'order' => array(
							'type' => 'order',
						),
					),
				),
			),
		);

		return array_merge( $data_fields, $fields );
	}
);
