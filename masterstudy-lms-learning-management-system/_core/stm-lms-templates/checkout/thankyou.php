<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$order_id        = get_query_var( 'masterstudy-orders-received' );
$payment_methods = STM_LMS_Options::get_option( 'payment_methods' );

stm_lms_register_style( 'user-orders' );

STM_LMS_Templates::show_lms_template( 'header' );

$order_info = \STM_LMS_Order::get_order_info( $order_id );
if ( ! empty( $order_info ) ) :
	?>

<div class="stm-lms-wrapper">
	<div class="container">
		<div class="masterstudy-orders masterstudy-thank-you-page">
			<div class="masterstudy-orders-box">
				<div class="masterstudy-orders-box__title"><?php echo esc_html__( 'Thank you for your order!', 'masterstudy-lms-learning-management-system' ); ?></div>
				<div class="masterstudy-orders-box__info">
					<div class="masterstudy-orders-box__info-label"><?php echo esc_html__( 'Order ID:', 'masterstudy-lms-learning-management-system' ); ?></div>
					<div class="masterstudy-orders-box__info-value">
						<div class="masterstudy-orders-box__info-label"><?php echo esc_attr( $order_id ); ?></div>
					</div>
				</div>
				<div class="masterstudy-orders-box__info">
					<div class="masterstudy-orders-box__info-label"><?php echo esc_html__( 'Date:', 'masterstudy-lms-learning-management-system' ); ?></div>
					<div class="masterstudy-orders-box__info-value"><?php echo esc_attr( $order_info['date_formatted'] ); ?></div>
				</div>
			</div>
			<?php
			if ( ! empty( $payment_methods['wire_transfer'] ) && $payment_methods['wire_transfer']['enabled'] && 'wire_transfer' === $order_info['payment_code'] ) :
				$wire_transfer = $payment_methods['wire_transfer']['fields'];
				?>
			<div class="masterstudy-payment-methods">
				<div class="masterstudy-payment-methods__title">
					<?php echo esc_html__( 'Bank Details', 'masterstudy-lms-learning-management-system' ); ?>
				</div>

				<div class="masterstudy-payment-methods__table">
					<div class="masterstudy-payment-methods__table-column">
						<div class="masterstudy-payment-methods__name"><?php echo esc_html__( 'Bank', 'masterstudy-lms-learning-management-system' ); ?></div>
						<div class="masterstudy-payment-methods__value"><?php echo esc_html( $wire_transfer['bank_name'] ); ?></div>
					</div>
					<div class="masterstudy-payment-methods__table-column">
						<div class="masterstudy-payment-methods__name"><?php echo esc_html__( 'Recipient', 'masterstudy-lms-learning-management-system' ); ?></div>
						<div class="masterstudy-payment-methods__value"><?php echo esc_html( $wire_transfer['holder_name'] ); ?></div>
					</div>
					<div class="masterstudy-payment-methods__table-column">
						<div class="masterstudy-payment-methods__name"><?php echo esc_html__( 'Account Number', 'masterstudy-lms-learning-management-system' ); ?></div>
						<div class="masterstudy-payment-methods__value"><?php echo esc_html( $wire_transfer['account_number'] ); ?></div>
					</div>
					<div class="masterstudy-payment-methods__table-column">
						<div class="masterstudy-payment-methods__name"><?php echo esc_html__( 'Amount to be paid', 'masterstudy-lms-learning-management-system' ); ?></div>
						<div class="masterstudy-payment-methods__value"><?php echo esc_attr( $order_info['total'] ); ?></div>
					</div>
				</div>
			</div>
			<?php endif; ?>
			<div class="masterstudy-orders-container">
				<div class="masterstudy-orders-table">
					<div class="masterstudy-orders-table__header">
						<div class="masterstudy-orders-course-info">
							<?php echo esc_html__( 'Order details', 'masterstudy-lms-learning-management-system' ); ?>
						</div>
					</div>
					<div class="masterstudy-orders-table__body">
						<?php foreach ( $order_info['cart_items'] as $key => $item ) : ?>
						<div class="masterstudy-orders-table__body-row">
							<div class="masterstudy-orders-course-info">
								<div class="masterstudy-orders-course-info__image">
									<?php if ( ! empty( $item['image'] ) ) : ?>
										<a href="<?php echo esc_url( $item['link'] ); ?>"><?php echo wp_kses_post( $item['image'] ); ?></a>
									<?php else : ?>
										<img src="<?php echo esc_url( STM_LMS_URL . 'assets/img/image_not_found.png' ); ?>" alt="<?php echo esc_html( $item['title'] ); ?>">
									<?php endif; ?>
								</div>
								<div class="masterstudy-orders-course-info__common">
									<div class="masterstudy-orders-course-info__title">
										<?php if ( ! empty( $item['image'] ) ) : ?>
											<a href="<?php echo esc_url( $item['link'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a>
										<?php else : ?>
											<em><?php echo esc_html__( 'N/A', 'masterstudy-lms-learning-management-system' ); ?></em>
										<?php endif; ?>

										<?php if ( ! empty( $item['bundle'] ) ) : ?>
											<span class="order-status"><?php echo esc_html__( 'bundle', 'masterstudy-lms-learning-management-system' ); ?></span>
										<?php endif; ?>
										<?php if ( ! empty( $item['enterprise'] ) ) : ?>
											<span class="order-status"><?php echo esc_html__( 'enterprise', 'masterstudy-lms-learning-management-system' ); ?></span>
										<?php endif; ?>
									</div>
									<div class="masterstudy-orders-course-info__category">
										<?php
										if ( ! empty( $item['enterprise'] ) ) {
											printf( esc_html__( 'for group', 'masterstudy-lms-learning-management-system' ) . ' %s', esc_html( get_the_title( $item['enterprise'] ) ) );
										} else {
											echo esc_html( implode( ', ', $item['terms'] ) );
										}

										if ( isset( $item['bundle_courses_count'] ) && $item['bundle_courses_count'] > 0 ) {
											echo esc_html( $item['bundle_courses_count'] . ' ' . esc_html__( 'courses in bundle', 'masterstudy-lms-learning-management-system' ) );
										}
										?>
									</div>
								</div>
								<div class="masterstudy-orders-course-info__price"><?php echo esc_attr( $item['price_formatted'] ); ?></div>
								<?php
									STM_LMS_Templates::show_lms_template(
										'components/button',
										array(
											'title' => esc_html__( 'Go to course', 'masterstudy-lms-learning-management-system' ),
											'link'  => esc_url( $item['link'] ),
											'style' => 'secondary masterstudy-orders-course-info__button',
											'size'  => 'sm',
										)
									);
								?>
							</div>
						</div>
						<?php endforeach; ?>
					</div>
					<div class="masterstudy-orders-table__footer">
						<div class="masterstudy-orders-course-info">
							<div class="masterstudy-orders-course-info__label"><?php echo esc_html__( 'Total', 'masterstudy-lms-learning-management-system' ); ?>:</div>
							<div class="masterstudy-orders-course-info__price"><?php echo esc_attr( $order_info['total'] ); ?></div>
						</div>
					</div>
				</div>
			</div>
			<div class="masterstudy-orders-row">
				<div class="masterstudy-orders-column">
					<div class="masterstudy-orders-table">
						<div class="masterstudy-orders-table__header">
							<div class="masterstudy-orders-course-info"><?php echo esc_html__( 'Student info', 'masterstudy-lms-learning-management-system' ); ?></div>
						</div>
						<div class="masterstudy-orders-table__body">
							<div class="masterstudy-orders-table__body-row">
								<div class="masterstudy-orders-course-info">
									<div class="masterstudy-orders-course-info__label"><?php echo esc_html__( 'Full name:', 'masterstudy-lms-learning-management-system' ); ?></div>
									<div class="masterstudy-orders-course-info__value"><?php echo esc_attr( $order_info['user']['login'] ); ?></div>
								</div>
							</div>
							<div class="masterstudy-orders-table__body-row">
								<div class="masterstudy-orders-course-info">
									<div class="masterstudy-orders-course-info__label"><?php echo esc_html__( 'Email:', 'masterstudy-lms-learning-management-system' ); ?></div>
									<div class="masterstudy-orders-course-info__value"><?php echo esc_attr( $order_info['user']['email'] ); ?></div>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="masterstudy-orders-column">
					<div class="masterstudy-orders-table">
						<div class="masterstudy-orders-table__header">
							<div class="masterstudy-orders-course-info"><?php echo esc_html__( 'Payment info', 'masterstudy-lms-learning-management-system' ); ?></div>
						</div>
						<div class="masterstudy-orders-table__body">
							<div class="masterstudy-orders-table__body-row">
								<div class="masterstudy-orders-course-info">
									<div class="masterstudy-orders-course-info__label"><?php echo esc_html__( 'Payment method:', 'masterstudy-lms-learning-management-system' ); ?></div>
									<div class="masterstudy-orders-course-info__value masterstudy-payment-method"><?php echo wp_kses_post( 'wire_transfer' === $order_info['payment_code'] ? esc_html__( 'wire transfer', 'text-domain' ) : $order_info['payment_code'] ); ?></div>
								</div>
							</div>
							<div class="masterstudy-orders-table__body-row">
								<div class="masterstudy-orders-course-info">
									<div class="masterstudy-orders-course-info__label"><?php echo esc_html__( 'Total:', 'masterstudy-lms-learning-management-system' ); ?></div>
									<div class="masterstudy-orders-course-info__value"><?php echo esc_attr( $order_info['total'] ); ?></div>
								</div>
							</div>
							<div class="masterstudy-orders-table__body-row">
								<div class="masterstudy-orders-course-info">
									<div class="masterstudy-orders-course-info__label"><?php echo esc_html__( 'Status:', 'masterstudy-lms-learning-management-system' ); ?></div>
									<div class="masterstudy-orders-course-info__value"><span class="order-status <?php echo esc_attr( $order_info['status'] ); ?>"><?php echo esc_attr( $order_info['status'] ); ?></span></div>
								</div>
							</div>
							<div class="masterstudy-orders-table__body-row">
								<div class="masterstudy-orders-course-info">
									<div class="masterstudy-orders-course-info__label"><?php echo esc_html__( 'Order ID:', 'masterstudy-lms-learning-management-system' ); ?></div>
									<div class="masterstudy-orders-course-info__value"><?php echo esc_attr( $order_id ); ?></div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="masterstudy-orders-button">
				<?php
				STM_LMS_Templates::show_lms_template(
					'components/button',
					array(
						'title' => esc_html__( 'View all orders', 'masterstudy-lms-learning-management-system' ),
						'link'  => esc_url( get_permalink( STM_LMS_Options::get_option( 'user_url' ) ) . 'my-orders/' ),
						'style' => 'secondary',
						'size'  => 'sm',
					)
				);
				?>
			</div>
		</div>
	</div>
</div>

	<?php
endif;
STM_LMS_Templates::show_lms_template( 'footer' );
