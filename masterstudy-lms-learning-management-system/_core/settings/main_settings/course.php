<?php
function stm_lms_settings_course_section() {
	$passed_emojis = array(
		''          => esc_html__( 'Select emoji', 'masterstudy-lms-learning-management-system' ),
		'&#128522;' => '😊 ' . esc_html__( 'Blushed smile face', 'masterstudy-lms-learning-management-system' ),
		'&#128512;' => '😀 ' . esc_html__( 'Grinning face', 'masterstudy-lms-learning-management-system' ),
		'&#128579;' => '🙃 ' . esc_html__( 'Upside down face', 'masterstudy-lms-learning-management-system' ),
		'&#128525;' => '😍 ' . esc_html__( 'Smiling face with heart-eyes', 'masterstudy-lms-learning-management-system' ),
		'&#129395;' => '🥳 ' . esc_html__( 'Partying face', 'masterstudy-lms-learning-management-system' ),
	);
	$failed_emojis = array(
		''          => esc_html__( 'Select emoji', 'masterstudy-lms-learning-management-system' ),
		'&#128542;' => '😔 ' . esc_html__( 'Pensive face', 'masterstudy-lms-learning-management-system' ),
		'&#128544;' => '😠 ' . esc_html__( 'Angry face', 'masterstudy-lms-learning-management-system' ),
		'&#128545;' => '😡 ' . esc_html__( 'Rage face', 'masterstudy-lms-learning-management-system' ),
		'&#128549;' => '😥 ' . esc_html__( 'Disappointed face', 'masterstudy-lms-learning-management-system' ),
	);

	$course_settings_primary_fields = array(
		'course_page_tab'                      => array(
			'type'        => 'select',
			'label'       => esc_html__( 'Default Tab', 'masterstudy-lms-learning-management-system' ),
			'description' => esc_html__( 'Choose the default tab to be shown when opening on the course page', 'masterstudy-lms-learning-management-system' ),
			'options'     => array(
				'description'  => esc_html__( 'Description', 'masterstudy-lms-learning-management-system' ),
				'curriculum'   => esc_html__( 'Curriculum', 'masterstudy-lms-learning-management-system' ),
				'faq'          => esc_html__( 'Faq', 'masterstudy-lms-learning-management-system' ),
				'announcement' => esc_html__( 'Announcement', 'masterstudy-lms-learning-management-system' ),
				'reviews'      => esc_html__( 'Reviews', 'masterstudy-lms-learning-management-system' ),
			),
			'value'       => 'description',
		),
		'assignments_quiz_result_emoji_show'   => array(
			'type'        => 'checkbox',
			'label'       => esc_html__( 'Emoji in Quiz and Assignments results', 'masterstudy-lms-learning-management-system' ),
			'description' => esc_html__( 'Use emojis to show results in quizzes and assignments', 'masterstudy-lms-learning-management-system' ),
			'value'       => false,
		),
		'assignments_quiz_passed_emoji'        => array(
			'type'        => 'select',
			'label'       => esc_html__( 'Quiz / Assignment Passed Emoji', 'masterstudy-lms-learning-management-system' ),
			'description' => esc_html__( 'The emoji to show when students pass', 'masterstudy-lms-learning-management-system' ),
			'options'     => $passed_emojis,
			'value'       => '',
			'dependency'  => array(
				'key'   => 'assignments_quiz_result_emoji_show',
				'value' => 'not_empty',
			),
		),
		'assignments_quiz_failed_emoji'        => array(
			'type'        => 'select',
			'label'       => esc_html__( 'Quiz / Assignment Failed Emoji', 'masterstudy-lms-learning-management-system' ),
			'description' => esc_html__( 'The emoji to show when students fail', 'masterstudy-lms-learning-management-system' ),
			'options'     => $failed_emojis,
			'value'       => '',
			'dependency'  => array(
				'key'   => 'assignments_quiz_result_emoji_show',
				'value' => 'not_empty',
			),
		),
		'pro_banner'                           => array(
			'type'  => 'pro_banner',
			'label' => esc_html__( 'All Course Layouts', 'masterstudy-lms-learning-management-system' ),
			'img'   => STM_LMS_URL . 'assets/img/pro-features/course-formats.png',
			'hint'  => 'slider',
			'desc'  => esc_html__( 'Step up to Pro today and dive into a whole new level of course customization within the Course settings.', 'masterstudy-lms-learning-management-system' ),
		),
		'course_tabs'                          => array(
			'group'       => 'started',
			'type'        => 'notice',
			'label'       => esc_html__( 'Course Tabs', 'masterstudy-lms-learning-management-system' ),
			'description' => esc_html__( 'Show a tab with the course description', 'masterstudy-lms-learning-management-system' ),
		),
		'course_tab_description'               => array(
			'type'        => 'checkbox',
			'label'       => esc_html__( 'Description tab', 'masterstudy-lms-learning-management-system' ),
			'description' => esc_html__( 'Show a tab with the course description', 'masterstudy-lms-learning-management-system' ),
			'value'       => true,
		),
		'course_tab_curriculum'                => array(
			'type'        => 'checkbox',
			'label'       => esc_html__( 'Curriculum tab', 'masterstudy-lms-learning-management-system' ),
			'description' => esc_html__( 'Show a tab with the course outline', 'masterstudy-lms-learning-management-system' ),
			'value'       => true,
		),
		'course_tab_faq'                       => array(
			'type'        => 'checkbox',
			'label'       => esc_html__( 'FAQ tab', 'masterstudy-lms-learning-management-system' ),
			'description' => esc_html__( 'Show a tab with frequently asked questions', 'masterstudy-lms-learning-management-system' ),
			'value'       => true,
		),
		'course_tab_announcement'              => array(
			'type'        => 'checkbox',
			'label'       => esc_html__( 'Announcement tab', 'masterstudy-lms-learning-management-system' ),
			'description' => esc_html__( 'Show a tab with course announcements', 'masterstudy-lms-learning-management-system' ),
			'value'       => true,
		),
		'course_tab_reviews'                   => array(
			'group'       => 'ended',
			'type'        => 'checkbox',
			'label'       => esc_html__( 'Reviews tab', 'masterstudy-lms-learning-management-system' ),
			'description' => esc_html__( 'Show a tab with course reviews', 'masterstudy-lms-learning-management-system' ),
			'value'       => true,
		),

		'course_levels_config'                 => array(
			'type'        => 'repeater',
			'label'       => esc_html__( 'Course levels', 'masterstudy-lms-learning-management-system' ),
			'description' => esc_html__( 'Set different levels for courses (like beginner, intermediate, advanced)', 'masterstudy-lms-learning-management-system' ),
			'fields'      => array(
				'id'    => array(
					'type'    => 'text',
					'label'   => esc_html__( 'Level ID', 'masterstudy-lms-learning-management-system' ),
					'columns' => '50',
				),
				'label' => array(
					'type'    => 'text',
					'label'   => esc_html__( 'Level Label', 'masterstudy-lms-learning-management-system' ),
					'columns' => '50',
				),
			),
			'value'       => array(
				array(
					'id'    => 'beginner',
					'label' => esc_html__( 'Beginner', 'masterstudy-lms-learning-management-system' ),
				),
				array(
					'id'    => 'intermediate',
					'label' => esc_html__( 'Intermediate', 'masterstudy-lms-learning-management-system' ),
				),
				array(
					'id'    => 'advanced',
					'label' => esc_html__( 'Advanced', 'masterstudy-lms-learning-management-system' ),
				),
			),
		),
		'course_allow_new_categories'          => array(
			'type'  => 'checkbox',
			'label' => esc_html__( 'Allow instructors to create new categories', 'masterstudy-lms-learning-management-system' ),
			'hint'  => esc_html__( 'Let instructors make new course categories', 'masterstudy-lms-learning-management-system' ),
		),
		'course_allow_new_question_categories' => array(
			'type'        => 'checkbox',
			'label'       => esc_html__( 'Allow instructors to create new question categories', 'masterstudy-lms-learning-management-system' ),
			'description' => esc_html__( 'Let instructors make new quiz question categories', 'masterstudy-lms-learning-management-system' ),
		),
		'course_allow_presto_player'           => array(
			'type'  => 'checkbox',
			'label' => esc_html__( 'Allow Presto Player Source for Instructors', 'masterstudy-lms-learning-management-system' ),
			'hint'  => esc_html__( 'Instructors can use videos from the Presto Player Media Hub', 'masterstudy-lms-learning-management-system' ),
		),
		'course_user_auto_enroll'              => array(
			'type'  => 'checkbox',
			'label' => esc_html__( 'Auto-enrollment for free courses', 'masterstudy-lms-learning-management-system' ),
			'hint'  => esc_html__( 'Students automatically enroll in free courses when they preview them', 'masterstudy-lms-learning-management-system' ),
		),
		'course_allow_review'                  => array(
			'type'  => 'checkbox',
			'label' => esc_html__( 'Allow reviews from non-enrolled students', 'masterstudy-lms-learning-management-system' ),
			'hint'  => esc_html__( 'Enable this if you want people who aren’t enrolled in the course to be able to leave reviews.', 'masterstudy-lms-learning-management-system' ),
			'value' => true,
		),
		'course_allow_basic_info'              => array(
			'type'        => 'checkbox',
			'label'       => esc_html__( 'Allow adding Basic info section', 'masterstudy-lms-learning-management-system' ),
			'description' => esc_html__( 'Let instructors add a section for basic info about the course', 'masterstudy-lms-learning-management-system' ),
		),
		'course_allow_requirements_info'       => array(
			'type'        => 'checkbox',
			'label'       => esc_html__( 'Allow adding Course requirements section', 'masterstudy-lms-learning-management-system' ),
			'description' => esc_html__( 'Let instructors add a section for course requirements', 'masterstudy-lms-learning-management-system' ),
		),
		'course_allow_intended_audience'       => array(
			'type'        => 'checkbox',
			'label'       => esc_html__( 'Allow adding Intended audience section', 'masterstudy-lms-learning-management-system' ),
			'description' => esc_html__( 'Let instructors add a section for who the course is for', 'masterstudy-lms-learning-management-system' ),
		),
		'course_lesson_video_types'            => array(
			'group'       => 'started',
			'type'        => 'notice',
			'label'       => esc_html__( 'Preferred Video Source', 'masterstudy-lms-learning-management-system' ),
			'description' => esc_html__( 'Choose the main type/types of video to use', 'masterstudy-lms-learning-management-system' ),
			'value'       => true,
		),
		'course_lesson_video_type_html'        => array(
			'type'    => 'checkbox',
			'label'   => esc_html__( 'HTML (MP4)', 'masterstudy-lms-learning-management-system' ),
			'toggle'  => false,
			'columns' => '33',
			'value'   => true,
		),
		'course_lesson_video_type_youtube'     => array(
			'type'    => 'checkbox',
			'label'   => esc_html__( 'YouTube', 'masterstudy-lms-learning-management-system' ),
			'toggle'  => false,
			'columns' => '33',
			'value'   => true,
		),
		'course_lesson_video_type_vimeo'       => array(
			'type'    => 'checkbox',
			'label'   => esc_html__( 'Vimeo', 'masterstudy-lms-learning-management-system' ),
			'toggle'  => false,
			'columns' => '33',
			'value'   => true,
		),
		'course_lesson_video_type_ext_link'    => array(
			'type'    => 'checkbox',
			'label'   => esc_html__( 'External link', 'masterstudy-lms-learning-management-system' ),
			'toggle'  => false,
			'columns' => '33',
			'value'   => true,
		),
		'course_lesson_video_type_embed'       => array(
			'type'    => 'checkbox',
			'label'   => esc_html__( 'Embed', 'masterstudy-lms-learning-management-system' ),
			'toggle'  => false,
			'columns' => '33',
			'value'   => true,
		),
		'course_lesson_video_type_shortcode'   => array(
			'type'    => 'checkbox',
			'label'   => esc_html__( 'Shortcode', 'masterstudy-lms-learning-management-system' ),
			'toggle'  => false,
			'columns' => '33',
			'group'   => 'ended',
			'value'   => true,
		),
		'pro_banner_audio_type'                => array(
			'type'        => 'pro_banner',
			'label'       => esc_html__( 'Audio Lesson Addon', 'masterstudy-lms-learning-management-system' ),
			'img'         => STM_LMS_URL . 'assets/img/pro-features/audio-lesson-free-banner.png',
			'hint'        => esc_html__( 'Unlock', 'masterstudy-lms-learning-management-system' ),
			'is_pro_plus' => ! is_ms_lms_addon_enabled( 'audio_lesson' ) && ! ( function_exists( 'mslms_plus_verify' ) && mslms_plus_verify() ),
			'desc'        => esc_html__( 'Now you can share audio lessons. Upload audio files or add from Spotify or SoundCloud. Mix them with text, video and quizzes in your courses.', 'masterstudy-lms-learning-management-system' ),
		),
	);

	$audio_lesson_addon_fields = apply_filters( 'masterstudy_lms_audio_lesson_course_settings_fields', array() );

	$course_settings_secondary_fields = array(
		'enable_sticky'              => array(
			'group'       => 'started',
			'type'        => 'checkbox',
			'label'       => esc_html__( 'Bottom sticky panel', 'masterstudy-lms-learning-management-system' ),
			'description' => esc_html__( 'Show a panel at the bottom of the screen that stays in place as users scroll', 'masterstudy-lms-learning-management-system' ),
		),
		'enable_sticky_title'        => array(
			'type'        => 'checkbox',
			'label'       => esc_html__( 'Title in bottom sticky panel', 'masterstudy-lms-learning-management-system' ),
			'description' => esc_html__( 'Show the course title in the sticky panel', 'masterstudy-lms-learning-management-system' ),
			'dependency'  => array(
				'key'   => 'enable_sticky',
				'value' => 'not_empty',
			),
			'columns'     => '50',
		),
		'enable_sticky_rating'       => array(
			'type'        => 'checkbox',
			'label'       => esc_html__( 'Rating in bottom sticky panel', 'masterstudy-lms-learning-management-system' ),
			'description' => esc_html__( 'Show the course rating in the sticky panel', 'masterstudy-lms-learning-management-system' ),
			'dependency'  => array(
				'key'   => 'enable_sticky',
				'value' => 'not_empty',
			),
			'columns'     => '50',
		),
		'enable_sticky_teacher'      => array(
			'type'        => 'checkbox',
			'label'       => esc_html__( 'Teacher in bottom sticky panel', 'masterstudy-lms-learning-management-system' ),
			'description' => esc_html__( "Show the teacher's name in the sticky panel", 'masterstudy-lms-learning-management-system' ),
			'dependency'  => array(
				'key'   => 'enable_sticky',
				'value' => 'not_empty',
			),
			'columns'     => '50',
		),
		'enable_sticky_category'     => array(
			'type'        => 'checkbox',
			'label'       => esc_html__( 'Category in bottom sticky panel', 'masterstudy-lms-learning-management-system' ),
			'description' => esc_html__( 'Show the course category in the sticky panel', 'masterstudy-lms-learning-management-system' ),
			'dependency'  => array(
				'key'   => 'enable_sticky',
				'value' => 'not_empty',
			),
			'columns'     => '50',
		),
		'enable_sticky_price'        => array(
			'type'        => 'checkbox',
			'label'       => esc_html__( 'Price in bottom sticky panel', 'masterstudy-lms-learning-management-system' ),
			'description' => esc_html__( 'Show the course price in the sticky panel', 'masterstudy-lms-learning-management-system' ),
			'dependency'  => array(
				'key'   => 'enable_sticky',
				'value' => 'not_empty',
			),
			'columns'     => '50',
		),
		'enable_sticky_button'       => array(
			'group'       => 'ended',
			'type'        => 'checkbox',
			'label'       => esc_html__( 'Buy button in bottom sticky panel', 'masterstudy-lms-learning-management-system' ),
			'description' => esc_html__( 'Show a buy button in the sticky panel', 'masterstudy-lms-learning-management-system' ),
			'dependency'  => array(
				'key'   => 'enable_sticky',
				'value' => 'not_empty',
			),
			'columns'     => '50',
		),
		'enable_related_courses'     => array(
			'group'       => 'started',
			'type'        => 'checkbox',
			'label'       => esc_html__( 'Related courses', 'masterstudy-lms-learning-management-system' ),
			'description' => esc_html__( 'Show courses that are similar to the one being viewed', 'masterstudy-lms-learning-management-system' ),
		),
		'related_option'             => array(
			'group'       => 'ended',
			'type'        => 'select',
			'label'       => esc_html__( 'Show related courses based on:', 'masterstudy-lms-learning-management-system' ),
			'description' => esc_html__( 'Decide how to pick the related courses (like by category or levels)', 'masterstudy-lms-learning-management-system' ),
			'options'     => array(
				'by_category' => esc_html__( 'Category', 'masterstudy-lms-learning-management-system' ),
				'by_author'   => esc_html__( 'Author', 'masterstudy-lms-learning-management-system' ),
				'by_level'    => esc_html__( 'Level', 'masterstudy-lms-learning-management-system' ),
			),
			'value'       => 'default',
			'dependency'  => array(
				'key'   => 'enable_related_courses',
				'value' => 'not_empty',
			),
		),
		'finish_popup_image_disable' => array(
			'group' => 'started',
			'type'  => 'checkbox',
			'label' => esc_html__( 'Disable default image for course completion notification', 'masterstudy-lms-learning-management-system' ),
			'hint'  => esc_html__( 'There will be no default image when a course is completed', 'masterstudy-lms-learning-management-system' ),
			'value' => false,
		),
		'finish_popup_image_failed'  => array(
			'type'       => 'image',
			'label'      => esc_html__( 'Upload an image for failed courses', 'masterstudy-lms-learning-management-system' ),
			'hint'       => esc_html__( 'Add a picture to show when a course is failed', 'masterstudy-lms-learning-management-system' ),
			'dependency' => array(
				'key'   => 'finish_popup_image_disable',
				'value' => 'empty',
			),
		),
		'finish_popup_image_success' => array(
			'type'       => 'image',
			'group'      => 'ended',
			'label'      => esc_html__( 'Upload an image for passed courses', 'masterstudy-lms-learning-management-system' ),
			'hint'       => esc_html__( 'Add a picture to show when a course is passed', 'masterstudy-lms-learning-management-system' ),
			'dependency' => array(
				'key'   => 'finish_popup_image_disable',
				'value' => 'empty',
			),
		),
	);

	$is_pro_plus          = STM_LMS_Helpers::is_pro_plus();
	$sticky_sidebar_field = array();

	if ( $is_pro_plus ) {
		$course_settings_primary_fields['course_page_tab']['dependency']      = array(
			'key'   => 'course_style',
			'value' => 'sleek-sidebar',
		);
		$course_settings_primary_fields['course_page_tab']['dependency_mode'] = 'disabled';
		$sticky_sidebar_field = array(
			'course_sticky_sidebar' => array(
				'type'         => 'checkbox',
				'label'        => esc_html__( 'Make sidebar sticky', 'masterstudy-lms-learning-management-system' ),
				'description'  => esc_html__( 'This will make a sidebar sticky on the course page when a user scrolls down.', 'masterstudy-lms-learning-management-system' ),
				'value'        => true,
				'dependency'   => array(
					array(
						'key'   => 'course_style',
						'value' => 'sleek-sidebar',
					),
					array(
						'key'   => 'course_style',
						'value' => 'dynamic-sidebar',
					),
				),
				'dependencies' => '||',
			),
		);
	}

	$course_summary_fields = array_merge(
		$sticky_sidebar_field,
		$course_settings_primary_fields,
		$audio_lesson_addon_fields,
		$course_settings_secondary_fields
	);

	$course_settings_fields = array(
		'name'   => esc_html__( 'Course', 'masterstudy-lms-learning-management-system' ),
		'label'  => esc_html__( 'Course Settings', 'masterstudy-lms-learning-management-system' ),
		'icon'   => 'fas fa-book',
		'fields' => $course_summary_fields,
	);

	if ( STM_LMS_Helpers::is_pro() ) {
		$hint_text      = esc_html__( 'Available in Pro Plus', 'masterstudy-lms-learning-management-system' );
		$preview_label  = esc_html__( 'Demo preview', 'masterstudy-lms-learning-management-system' );
		$preview_url    = 'https://masterstudy.stylemixthemes.com/lms-plugin/courses-page/basics-of-masterstudy/ ';
		$layout_options = array(
			array(
				'value'         => 'default',
				'alt'           => esc_html__( 'Default', 'masterstudy-lms-learning-management-system' ),
				'img'           => STM_LMS_URL . '/assets/img/course/default.png',
				'preview_url'   => $preview_url,
				'preview_label' => $preview_label,
				'disabled'      => false,
			),
			array(
				'value'         => 'classic',
				'alt'           => esc_html__( 'Classic', 'masterstudy-lms-learning-management-system' ),
				'img'           => STM_LMS_URL . '/assets/img/course/classic.png',
				'preview_url'   => $preview_url . '?course_style=classic',
				'preview_label' => $preview_label,
				'disabled'      => false,
			),
			array(
				'value'         => 'modern',
				'alt'           => esc_html__( 'Industrial', 'masterstudy-lms-learning-management-system' ),
				'img'           => STM_LMS_URL . '/assets/img/course/modern.png',
				'preview_url'   => $preview_url . '?course_style=modern',
				'preview_label' => $preview_label,
				'disabled'      => false,
			),
			array(
				'value'         => 'timeless',
				'alt'           => esc_html__( 'Timeless', 'masterstudy-lms-learning-management-system' ),
				'img'           => STM_LMS_URL . '/assets/img/course/timeless.png',
				'preview_url'   => $preview_url . '?course_style=timeless',
				'preview_label' => $preview_label,
				'disabled_hint' => $hint_text,
				'disabled'      => ! $is_pro_plus,
			),
			array(
				'value'         => 'sleek-sidebar',
				'alt'           => esc_html__( 'Sleek with Sidebar', 'masterstudy-lms-learning-management-system' ),
				'img'           => STM_LMS_URL . '/assets/img/course/sleek-sidebar.png',
				'preview_url'   => $preview_url . '?course_style=sleek-sidebar',
				'preview_label' => $preview_label,
				'disabled_hint' => $hint_text,
				'disabled'      => ! $is_pro_plus,
			),
			array(
				'value'         => 'minimalistic',
				'alt'           => esc_html__( 'Minimalistic', 'masterstudy-lms-learning-management-system' ),
				'img'           => STM_LMS_URL . '/assets/img/course/minimalistic.png',
				'preview_url'   => $preview_url . '?course_style=minimalistic',
				'preview_label' => $preview_label,
				'disabled_hint' => $hint_text,
				'disabled'      => ! $is_pro_plus,
			),
			array(
				'value'         => 'dynamic',
				'alt'           => esc_html__( 'Dynamic', 'masterstudy-lms-learning-management-system' ),
				'img'           => STM_LMS_URL . '/assets/img/course/dynamic.png',
				'preview_url'   => $preview_url . '?course_style=dynamic',
				'preview_label' => $preview_label,
				'disabled_hint' => $hint_text,
				'disabled'      => ! $is_pro_plus,
			),
			array(
				'value'         => 'modern-curriculum',
				'alt'           => esc_html__( 'Modern with Curriculum', 'masterstudy-lms-learning-management-system' ),
				'img'           => STM_LMS_URL . '/assets/img/course/modern-curriculum.png',
				'preview_url'   => $preview_url . '?course_style=modern-curriculum',
				'preview_label' => $preview_label,
				'disabled_hint' => $hint_text,
				'disabled'      => ! $is_pro_plus,
			),
			array(
				'value'         => 'dynamic-sidebar',
				'alt'           => esc_html__( 'Dynamic with Short Sidebar', 'masterstudy-lms-learning-management-system' ),
				'img'           => STM_LMS_URL . '/assets/img/course/dynamic-sidebar.png',
				'preview_url'   => $preview_url . '?course_style=dynamic-sidebar',
				'preview_label' => $preview_label,
				'disabled_hint' => $hint_text,
				'disabled'      => ! $is_pro_plus,
			),
			array(
				'value'         => 'full-width',
				'alt'           => esc_html__( 'Bold with Full Width Cover', 'masterstudy-lms-learning-management-system' ),
				'img'           => STM_LMS_URL . '/assets/img/course/full-width.png',
				'preview_url'   => $preview_url . '?course_style=full-width',
				'preview_label' => $preview_label,
				'disabled_hint' => $hint_text,
				'disabled'      => ! $is_pro_plus,
			),
		);

		$course_style_field = array(
			'course_style' => array(
				'type'    => 'data_select',
				'label'   => esc_html__( 'Choose a style for your Course page', 'masterstudy-lms-learning-management-system' ),
				'options' => $layout_options,
				'value'   => 'default',
				'pro'     => true,
				'pro_url' => admin_url( 'admin.php?page=stm-lms-go-pro&source=course-page-style-course-settings' ),
			),
		);

		$course_settings_fields['fields'] = array_merge( $course_style_field, $course_settings_fields['fields'] );
	}

	return $course_settings_fields;
}
