<?php
/**
 * @var object $course
 */


$course_status = false;
$labels        = array(
	'hot'     => __( 'Hot course', 'masterstudy-lms-learning-management-system' ),
	'new'     => __( 'New course', 'masterstudy-lms-learning-management-system' ),
	'special' => __( 'Special course', 'masterstudy-lms-learning-management-system' ),
);

if ( ! empty( $course->status ) ) {
	if ( empty( $course->status_date_start ) && empty( $course->status_date_end ) ) {
		$course_status = true;
	} else {
		$current_time = time() * 1000;
		if ( $current_time > intval( $course->status_date_start ) && $current_time < intval( $course->status_date_end ) ) {
			$course_status = true;
		}
	}
}

if ( $course_status ) {
	?>
	<span class="masterstudy-single-course-status masterstudy-single-course-status_<?php echo esc_attr( $course->status ); ?>">
		<?php echo esc_html( $labels[ $course->status ] ); ?>
	</span>
	<?php
}
