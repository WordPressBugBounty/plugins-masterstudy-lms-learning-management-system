<?php
/**
 * @var object $course
 */

$stars = range( 1, 5 );
$rate  = $course->is_udemy_course ? $course->udemy_rate : $course->rate['average'];

if ( $course->is_udemy_course ) {
	foreach ( $course->udemy_rating_distribution as $index => $review ) {
		$marks_array[ $review['rating'] ] = $review['count'];
	}
	$marks = array_sum( $marks_array );
} else {
	$marks = count( $course->marks );
}
?>

<div class="masterstudy-single-course-rating">
	<div class="masterstudy-single-course-rating__wrapper">
		<?php foreach ( $stars as $star ) { ?>
			<span class="masterstudy-single-course-rating__star <?php echo esc_attr( $star <= floor( $rate ) ? 'masterstudy-single-course-rating__star_filled ' : '' ); ?>"></span>
		<?php } ?>
		<div class="masterstudy-single-course-rating__count">
			<?php echo (float) $rate === (int) $rate ? (int) $rate : esc_html( $rate ); ?>
		</div>
	</div>
	<div class="masterstudy-single-course-rating__quantity">
		<?php
		printf(
			esc_html(
				/* translators: %d integer marks */
				_n(
					'%s review',
					'%s reviews',
					$marks,
					'masterstudy-lms-learning-management-system'
				)
			),
			esc_html( $marks )
		);
		?>
	</div>
</div>
