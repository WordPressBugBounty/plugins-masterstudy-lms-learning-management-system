<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;} //Exit if accessed directly ?>

<?php
/**
 * $var $user_id
 *
 */


$items = stm_lms_get_cart_items( $user_id, apply_filters( 'stm_lms_cart_items_fields', array( 'item_id', 'price' ) ) );
?>
<div class="masterstudy-checkout-container__top">
	<?php
		STM_LMS_Templates::show_lms_template(
			'account/private/parts/top_info',
			array(
				'title' => esc_html__( 'Checkout', 'masterstudy-lms-learning-management-system' ),
			)
		);
		?>
</div>
<?php
if ( empty( $items ) ) :
	STM_LMS_Templates::show_lms_template( 'checkout/empty-cart' );
else :
	$total = 0;
	?>
	<div class="masterstudy-checkout-container">
		<div class="masterstudy-checkout-container__left-column">
			<div class="masterstudy-checkout-table">
				<div class="masterstudy-checkout-table__header">
					<div class="masterstudy-checkout-course-info">
						<div class="masterstudy-checkout-course-info__value"><?php echo esc_html__( 'Order items', 'masterstudy-lms-learning-management-system' ); ?></div>
					</div>
				</div>
				<div class="masterstudy-checkout-table__body">
				<?php
				foreach ( $items as $item ) :
					if ( ! get_post_type( $item['item_id'] ) ) {
						continue;
					}
						$terms        = wp_get_post_terms( $item['item_id'], 'stm_lms_course_taxonomy', array( 'fields' => 'ids' ) );
						$category_ids = ! is_wp_error( $terms ) && ! empty( $terms ) ? array_map( 'intval', $terms ) : array();
						$total       += $item['price'];
					?>
					<div class="masterstudy-checkout-table__body-row">
						<div class="masterstudy-checkout-course-info">
							<div class="masterstudy-checkout-course-info__image">
								<a href="<?php echo esc_url( get_the_permalink( $item['item_id'] ) ); ?>">
								<?php
								if ( function_exists( 'stm_get_VC_attachment_img_safe' ) ) :
									echo wp_kses_post( stm_get_VC_attachment_img_safe( get_post_thumbnail_id( $item['item_id'] ), 'full' ) );
								else :
									?>
									<img src="<?php echo esc_url( get_the_post_thumbnail_url( $item['item_id'], 'full' ) ); ?>">
								<?php endif; ?>
								</a>
							</div>
							<div class="masterstudy-checkout-course-info__common">
								<div class="masterstudy-checkout-course-info__title">
									<a href="<?php echo esc_url( get_the_permalink( $item['item_id'] ) ); ?>">
										<?php echo esc_html( apply_filters( 'stm_lms_single_item_cart_title', sanitize_text_field( get_the_title( $item['item_id'] ) ), $item ) ); ?>
									</a>
									<?php
									$additional_info  = '';
									$enterprise_title = '';

									if ( ! empty( $item['enterprise'] ) && intval( $item['enterprise'] ) !== 0 ) {
										$additional_info  = '<span class="masterstudy-checkout-course-info__status">enterprise</span>';
										$enterprise_title = get_the_title( $item['enterprise'] );
									} elseif ( ! empty( $item['bundle'] ) && intval( $item['bundle'] ) !== 0 ) {
										$additional_info = '<span class="masterstudy-checkout-course-info__status">' . __( 'bundle', 'masterstudy-lms-learning-management-system' ) . '</span>';
									}

									echo wp_kses_post( $additional_info );
									?>
								</div>
								<div class="masterstudy-checkout-course-info__category">
								<?php
								if ( ! empty( $enterprise_title ) ) {
									echo esc_html__( 'for group', 'masterstudy-lms-learning-management-system' ) . ' ' . esc_html( $enterprise_title );
								} else {
									STM_LMS_Templates::show_lms_template(
										'components/course/categories',
										array(
											'term_ids' => $category_ids,
											'only_one' => false,
											'inline'   => true,
										)
									);
								}

								if ( isset( $item['bundle'] ) ) {
									$bundle_count = STM_LMS_Order::get_bundle_courses_count( $item['bundle'] );

									if ( isset( $bundle_count ) && $bundle_count > 0 ) {
										echo esc_html( $bundle_count . ' ' . esc_html__( 'courses in bundle', 'masterstudy-lms-learning-management-system' ) );
									}
								}
								?>
								</div>
							</div>
							<div class="masterstudy-checkout-course-info__price">
								<span><?php echo esc_html( STM_LMS_Helpers::display_price( $item['price'] ) ); ?></span>
								<div class="stm_lms_cart__item_delete">
									<i class="stmlms-trash1"
									<?php
									if ( ! empty( $item['enterprise'] ) ) {
										echo 'data-delete-enterprise=' . esc_attr( $item['enterprise'] );
									}
									?>
									data-delete-course="<?php echo intval( $item['item_id'] ); ?>"></i>
								</div>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
				</div>
				<div class="masterstudy-checkout-table__footer">
					<div class="masterstudy-checkout-course-info">
						<div class="masterstudy-checkout-course-info__label"><?php echo esc_html__( 'Total:', 'masterstudy-lms-learning-management-system' ); ?></div>
						<div class="masterstudy-checkout-course-info__price"><?php echo esc_html( STM_LMS_Helpers::display_price( $total ) ); ?></div>
					</div>
				</div>
			</div>
		</div>
		<div class="masterstudy-checkout-payment">
			<div id="stm_lms_checkout">
				<div class="masterstudy-checkout-table">
					<div class="masterstudy-checkout-table__header">
						<div class="masterstudy-checkout-course-info">
							<div class="masterstudy-checkout-course-info__value"><?php echo esc_html__( 'Payment method', 'masterstudy-lms-learning-management-system' ); ?></div>
						</div>
					</div>
					<div class="masterstudy-checkout-table__body">
						<?php STM_LMS_Templates::show_lms_template( 'checkout/payment_methods', compact( 'user_id', 'total' ) ); ?>
					</div>
					<div class="masterstudy-checkout-table__footer">
						<div class="masterstudy-checkout-course-info">
						<?php
							$payment_methods = STM_LMS_Options::get_option( 'payment_methods' );
							$payment_methods = is_array( $payment_methods ) ? $payment_methods : array();

							$enabled_methods = array_filter(
								$payment_methods,
								function ( $method ) {
									return isset( $method['enabled'] ) && $method['enabled'];
								}
							);

						if ( count( $enabled_methods ) > 0 ) :
							?>
								<a href="#" @click.prevent="purchase_courses()" class="btn btn-default stm_lms_pay_button" v-bind:class="{'loading' : loading}">
									<span>
									<?php
										echo esc_html__( 'Pay ', 'masterstudy-lms-learning-management-system' );
										echo esc_html( STM_LMS_Helpers::display_price( $item['price'] ) );
									?>
									</span>
								</a>
							<?php
							else :
								echo esc_html__( 'Please configure payment methods', 'masterstudy-lms-learning-management-system' );
							endif;
							?>
						</div>
					</div>
				</div>
				<transition name="slide-fade">
					<div class="stm-lms-message" v-bind:class="status" v-if="message">
						{{ message }}
					</div>
				</transition>
			</div>
		</div>
	</div>
	<?php
endif;
