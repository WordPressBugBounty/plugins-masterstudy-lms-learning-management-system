<?php $course_free_status = masterstudy_lms_course_free_status( $course['single_sale'], $course['price'] ); ?>

<div class="ms_lms_courses_card_item_info_price">
	<?php if ( $course['single_sale'] && ! $course_free_status['zero_price'] ) { ?>
		<?php if ( ! empty( $course['sale_price'] ) && $course['is_sale_active'] ) { ?>
			<div class="ms_lms_courses_card_item_info_price_sale">
				<span><?php echo esc_html( STM_LMS_Helpers::display_price( $course['sale_price'] ) ); ?></span>
			</div>
			<?php
		}
		if ( $course['availability'] && is_ms_lms_addon_enabled( 'coming_soon' ) ) {
			STM_LMS_Templates::show_lms_template(
				'global/coming_soon',
				array(
					'course_id' => $course['id'],
					'mode'      => 'card',
				),
			);
		} else {
			?>
			<div class="ms_lms_courses_card_item_info_price_single <?php echo ( ! empty( $course['sale_price'] ) && $course['is_sale_active'] ) ? 'sale' : ''; ?>">
				<span><?php echo esc_html( STM_LMS_Helpers::display_price( $course['price'] ) ); ?></span>
			</div>
			<?php
		}
		?>
	<?php } elseif ( ! $course['single_sale'] && STM_LMS_Subscriptions::subscription_enabled() && ! $course['not_in_membership'] ) { ?>
		<div class="ms_lms_courses_card_item_info_price_single subscription">
			<i class="stmlms-subscription"></i>
			<span><?php esc_html_e( 'Members Only', 'masterstudy-lms-learning-management-system' ); ?></span>
		</div>
	<?php } elseif ( $course_free_status['is_free'] ) { ?>
		<div class="ms_lms_courses_card_item_info_price_single">
			<span><?php echo esc_html__( 'Free', 'masterstudy-lms-learning-management-system' ); ?></span>
		</div>
	<?php } ?>
</div>
