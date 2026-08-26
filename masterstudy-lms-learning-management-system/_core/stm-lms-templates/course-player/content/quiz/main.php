<?php
/**
 * @var int $post_id
 * @var int $item_id
 * @var bool $is_single_quiz
 * @var string $lesson_type
 * @var array $data
 * @var boolean $dark_mode
 * @var bool $render_retake_template
 */

$is_single_quiz         = $is_single_quiz ?? false;
$render_retake_template = $render_retake_template ?? false;

wp_enqueue_style( 'masterstudy-course-player-quiz' );
wp_enqueue_script( 'masterstudy-course-player-quiz-touch' );
wp_enqueue_script( 'masterstudy-course-player-quiz' );
wp_localize_script(
	'masterstudy-course-player-quiz',
	'quiz_data',
	array(
		'start_nonce'    => wp_create_nonce( 'start_quiz' ),
		'submit_nonce'   => wp_create_nonce( 'user_answers' ),
		'h5p_nonce'      => wp_create_nonce( 'stm_lms_add_h5p_result' ),
		'ajax_url'       => admin_url( 'admin-ajax.php' ),
		'duration'       => intval( $data['duration'] ),
		'is_single_quiz' => $is_single_quiz,
		'quiz_id'        => intval( $item_id ),
		'course_id'      => intval( $post_id ),
		'random_answers' => $data['random_answers'],
		'confirmation'   => esc_html__( 'Once you submit, you will no longer be able to change your answers. Are you sure you want to submit the quiz?', 'masterstudy-lms-learning-management-system' ),
	)
);

STM_LMS_Templates::show_lms_template(
	'components/alert',
	array(
		'id'                  => 'quiz_alert',
		'title'               => esc_html__( 'Submit quiz', 'masterstudy-lms-learning-management-system' ),
		'text'                => esc_html__( 'Once you submit, you will no longer be able to change your answers. Are you sure you want to submit the quiz?', 'masterstudy-lms-learning-management-system' ),
		'submit_button_text'  => esc_html__( 'Submit', 'masterstudy-lms-learning-management-system' ),
		'cancel_button_text'  => esc_html__( 'Cancel', 'masterstudy-lms-learning-management-system' ),
		'submit_button_style' => 'primary',
		'cancel_button_style' => 'tertiary',
		'dark_mode'           => $dark_mode,
	)
);

?>
<?php
STM_LMS_Templates::show_lms_template(
	'course-player/content/quiz/view',
	array(
		'post_id'                => $post_id,
		'item_id'                => $item_id,
		'is_single_quiz'         => $is_single_quiz,
		'data'                   => $data,
		'dark_mode'              => $dark_mode,
		'render_retake_template' => $render_retake_template,
	)
);
