<?php
function stm_lms_settings_course_player_section() {
	return array(
		'name'   => esc_html__( 'Course Player', 'masterstudy-lms-learning-management-system' ),
		'label'  => esc_html__( 'Course Player Settings', 'masterstudy-lms-learning-management-system' ),
		'icon'   => 'fas fa-chalkboard-teacher',
		'fields' => array(
			'course_player_theme_mode'                  => array(
				'type'    => 'select',
				'label'   => esc_html__( 'Default Theme', 'masterstudy-lms-learning-management-system' ),
				'options' => array(
					''  => esc_html__( 'Light', 'masterstudy-lms-learning-management-system' ),
					'1' => esc_html__( 'Dark', 'masterstudy-lms-learning-management-system' ),
				),
				'value'   => '',
				'hint'    => esc_html__( 'Pick a default look for the lesson page that users will see first', 'masterstudy-lms-learning-management-system' ),
			),
			'course_player_theme_fonts'                 => array(
				'type'        => 'checkbox',
				'label'       => esc_html__( 'Use theme fonts', 'masterstudy-lms-learning-management-system' ),
				'description' => esc_html__( 'Use the fonts from the theme on the lesson page', 'masterstudy-lms-learning-management-system' ),
				'value'       => false,
			),
			'course_player_brand_icon_navigation'       => array(
				'type'        => 'checkbox',
				'label'       => esc_html__( 'Show brand icon in navigation', 'masterstudy-lms-learning-management-system' ),
				'description' => esc_html__( 'Show a brand icon (like a logo) in the navigation bar', 'masterstudy-lms-learning-management-system' ),
				'value'       => false,
			),
			'course_player_brand_icon_navigation_image' => array(
				'type'       => 'image',
				'label'      => esc_html__( 'Upload an image for navigation', 'masterstudy-lms-learning-management-system' ),
				'hint'       => esc_html__( 'Upload a square image to use as the brand icon in the navigation bar', 'masterstudy-lms-learning-management-system' ),
				'dependency' => array(
					'key'   => 'course_player_brand_icon_navigation',
					'value' => 'not_empty',
				),
			),
			'course_player_discussions_sidebar'         => array(
				'type'        => 'checkbox',
				'label'       => esc_html__( 'Discussions board sidebar', 'masterstudy-lms-learning-management-system' ),
				'description' => esc_html__( 'Show a sidebar for discussion boards where instructors and students can chat', 'masterstudy-lms-learning-management-system' ),
				'value'       => true,
			),
			'course_player_youtube_video_player'        => array(
				'type'        => 'checkbox',
				'label'       => esc_html__( 'Use MasterStudy player for videos from Youtube', 'masterstudy-lms-learning-management-system' ),
				'description' => esc_html__( 'Play YouTube videos with the MasterStudy player', 'masterstudy-lms-learning-management-system' ),
				'value'       => false,
			),
			'course_player_vimeo_video_player'          => array(
				'type'        => 'checkbox',
				'label'       => esc_html__( 'Use MasterStudy player for videos from Vimeo', 'masterstudy-lms-learning-management-system' ),
				'description' => esc_html__( 'Play Vimeo videos with the MasterStudy player', 'masterstudy-lms-learning-management-system' ),
				'value'       => false,
			),
		),
	);
}
