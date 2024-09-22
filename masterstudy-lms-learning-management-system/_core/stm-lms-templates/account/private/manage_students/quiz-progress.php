<?php
use MasterStudy\Lms\Repositories\CoursePlayerRepository;

if ( 'quiz' === $material['type'] ) {
	do_action( 'masterstudy_lms_course_player_register_assets' );

	$quizes = ( new CoursePlayerRepository() )->get_student_all_quizes( $student_id, $course_id, $material['post_id'] );
	?>

	<div class="masterstudy-student-progress-list__item-content<?php echo esc_attr( ! empty( $quizes ) ? ' masterstudy-student-progress-list__item-content_completed' : '' ); ?>">
		<?php
		foreach ( $quizes as $quiz_data ) :
			$is_answered               = ! empty( $quiz_data['last_answers'] );
			$quiz_data['show_answers'] = true;
			?>
			<div class="masterstudy-student-progress__quiz">
				<div class="masterstudy-student-progress-list__container-wrapper">
					<div class="masterstudy-student-progress-list__title">
						<span><?php echo esc_html__( 'Attempt', 'masterstudy-lms-learning-management-system' ); ?> </span>
						<span> <?php echo esc_html( $quiz_data['attempt'] ); ?> </span>
					</div>
					<div class="masterstudy-student-progress-list__meta-wrapper">
						<span class="masterstudy-student-progress-list__content-toggler"></span>
					</div>
				</div>
				<div class="masterstudy-student-progress-list__content">
					<div class="masterstudy-student-progress-list__item-content_result<?php echo esc_attr( $is_answered ? ' masterstudy-student-progress-list__item_hidden' : '' ); ?>">
						<?php
						STM_LMS_Templates::show_lms_template(
							'course-player/content/quiz/result',
							array(
								'progress'           => 100,
								'passing_grade'      => intval( $quiz_data['passing_grade'] ?? 0 ),
								'questions_quantity' => intval( $quiz_data['questions_quantity'] ?? 0 ),
								'answered_quantity'  => 0,
								'show_emoji'         => $quiz_data['show_emoji'],
								'emoji_name'         => STM_LMS_Options::get_option( 'assignments_quiz_passed_emoji' ),
							)
						);
						?>
					</div>
					<div class="masterstudy-student-progress-list__item-quiz<?php echo esc_attr( ! $is_answered ? ' masterstudy-student-progress-list__item_hidden' : '' ); ?>">
						<?php
						STM_LMS_Templates::show_lms_template(
							'course-player/content/quiz/main',
							array(
								'dark_mode'   => false,
								'post_id'     => $course_id,
								'data'        => $quiz_data,
								'item_id'     => $material['post_id'],
								'lesson_type' => $material['lesson_type'],
							)
						);
						?>
					</div>
					<div class="masterstudy-student-progress-list__item-no-answer<?php echo esc_attr( $is_answered ? ' masterstudy-student-progress-list__item_hidden' : '' ); ?>">
						<?php esc_html_e( 'Quiz has been completed by instructor.', 'masterstudy-lms-learning-management-system' ); ?>
					</div>
				</div>
			</div>
			<?php
		endforeach;
		?>
	</div>
	<div class="masterstudy-student-progress-list__item-content_empty">
		<?php esc_html_e( 'No quizzes yet...', 'masterstudy-lms-learning-management-system' ); ?>
	</div> 
	<?php
}
