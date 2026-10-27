<?php
/**
 * Shown instead of the enroll and buy buttons when the course has reached its students limit.
 *
 * @var array $button_classes
 */
?>
<div class="<?php echo esc_attr( implode( ' ', $button_classes ) ); ?>">
	<a href="#" class="masterstudy-buy-button__link masterstudy-buy-button__link_centered masterstudy-buy-button__link_disabled masterstudy-buy-button__link_course-full">
		<span class="masterstudy-buy-button__title"><?php echo esc_html__( 'Fully booked', 'masterstudy-lms-learning-management-system' ); ?></span>
	</a>
</div>
