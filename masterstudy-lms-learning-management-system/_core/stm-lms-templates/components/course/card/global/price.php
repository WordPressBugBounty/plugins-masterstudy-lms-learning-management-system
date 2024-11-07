<?php
/**
 * @var $course
 */
?>

<div class="masterstudy-course-card__price">
	<?php if ( ! isset( $course['not_single_sale'] ) || ! $course['not_single_sale'] ) { ?>
		<div class="masterstudy-course-card__price-single <?php echo ( ! empty( $course['sale_price'] ) && $course['is_sale_active'] ) ? 'masterstudy-course-card__price-single_sale' : ''; ?>">
			<span><?php echo esc_html( ( 0 != $course['price'] ) ? STM_LMS_Helpers::display_price( $course['price'] ) : __( 'Free', 'masterstudy-lms-learning-management-system' ) ); ?></span>
		</div>
		<?php if ( ! empty( $course['sale_price'] ) && $course['is_sale_active'] ) { ?>
			<div class="masterstudy-course-card__price-sale">
				<span><?php echo esc_html( STM_LMS_Helpers::display_price( $course['sale_price'] ) ); ?></span>
			</div>
		<?php } ?>
	<?php } else { ?>
		<div class="masterstudy-course-card__price-single masterstudy-course-card__price-single_subscription">
			<i class="stmlms-subscription"></i>
			<span><?php esc_html_e( 'Members Only', 'masterstudy-lms-learning-management-system' ); ?></span>
		</div>
	<?php } ?>
</div>
