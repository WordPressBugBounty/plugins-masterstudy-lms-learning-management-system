<?php
stm_lms_register_style( 'user-orders' );
wp_enqueue_style( 'masterstudy-pagination' );

if ( ! STM_LMS_Cart::woocommerce_checkout_enabled() ) {
	wp_enqueue_script( 'masterstudy-orders' );
	wp_localize_script(
		'masterstudy-orders',
		'masterstudy_orders',
		array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'ms_lms_nonce' ),
		)
	);
} else {
	wp_enqueue_script( 'vue.js' );
	wp_enqueue_script( 'vue-resource.js' );
	stm_lms_register_script( 'account/v1/my-orders' );
	stm_lms_register_style( 'cart' );
}
if ( STM_LMS_Cart::woocommerce_checkout_enabled() ) : ?>
	<div id="my-orders">

		<div class="stm-lms-user-orders" v-bind:class="{'loading' : loading}">

			<div class="stm-lms-user-order" v-for="(order, key) in orders" v-bind:class="'stm-lms-user-order-' + order.id">
				<h4 class="stm-lms-user-order__title" v-html="order.order_key"></h4>
				<div class="stm-lms-user-order__status">{{ order.date_formatted }}</div>
				<div class="stm-lms-user-order__status" v-bind:class="order.status">{{ order.i18n[order.status] }}</div>
				<div class="stm-lms-user-order__more" @click="openTab(key)">
					<i v-bind:class="{'stmlms-chevron-down' : !order.isOpened, 'stmlms-chevron-up' : order.isOpened}"></i>
				</div>

				<div class="stm-lms-user-order__advanced" v-if="order.isOpened">

					<table>
						<tbody>
						<tr v-for="item in order.cart_items">
							<td class="image">
								<div class="stm-lms-user-order__image" v-html="item.image"></div>
							</td>
							<td class="name">
								<span v-html="item.terms.join(', ') + ' >'" v-if="item.terms.length"></span>
								<a v-bind:href="item.link" v-html="item.title"></a>
							</td>
							<td class="price">
								<?php esc_html_e( 'Price', 'masterstudy-lms-learning-management-system' ); ?>
								<strong>{{ item.price_formatted }}</strong>
							</td>
						</tr>
						</tbody>
					</table>

				</div>
			</div>

			<h4 v-if="!orders.length"><?php esc_html_e( 'No orders.', 'masterstudy-lms-learning-management-system' ); ?></h4>

		</div>

		<a @click="getOrders()" v-if="!total" class="btn btn-default" v-bind:class="{'loading' : loading}">
			<span><?php esc_html_e( 'Show more', 'masterstudy-lms-learning-management-system' ); ?></span>
		</a>

	</div>
<?php else : ?>
	<div class="masterstudy-orders">
		<?php
		STM_LMS_Templates::show_lms_template(
			'account/private/parts/top_info',
			array(
				'title' => esc_html__( 'My Orders', 'masterstudy-lms-learning-management-system' ),
			)
		);
		?>
		<div class="masterstudy-orders-container">
			<div class="ms_lms_loader_"></div>
			<template id="masterstudy-order-template">
				<div class="masterstudy-orders-table">
					<div class="masterstudy-orders-table__header">
						<div class="masterstudy-orders-course-info">
							<div class="masterstudy-orders-course-info__id" data-order-id></div>
							<div class="order-status" data-order-status></div>
						</div>
						<div class="masterstudy-orders-course-info">
							<div class="masterstudy-orders-course-info__label"><?php echo esc_html__( 'Date', 'masterstudy-lms-learning-management-system' ); ?>:</div>
							<div class="masterstudy-orders-course-info__value" data-order-date></div>
						</div>
						<div class="masterstudy-orders-course-info">
							<div class="masterstudy-orders-course-info__label"><?php echo esc_html__( 'Payment Method', 'masterstudy-lms-learning-management-system' ); ?>:</div>
							<div class="masterstudy-orders-course-info__value" data-order-payment></div>
						</div>
					</div>
					<div class="masterstudy-orders-table__body"></div>
					<div class="masterstudy-orders-table__footer">
						<div class="masterstudy-orders-course-info">
							<div class="masterstudy-orders-course-info__label"><?php echo esc_html__( 'Total', 'masterstudy-lms-learning-management-system' ); ?>:</div>
							<div class="masterstudy-orders-course-info__price" data-order-total></div>
						</div>
					</div>
				</div>
			</template>
		</div>
		<div class="masterstudy-orders-table-navigation">
			<div class="masterstudy-orders-table-navigation__pagination"></div>
			<div class="masterstudy-orders-table-navigation__per-page">
			<?php
				STM_LMS_Templates::show_lms_template(
					'components/select',
					array(
						'select_id'    => 'orders-per-page',
						'select_width' => '170px',
						'select_name'  => 'per_page',
						'placeholder'  => esc_html__( '10 per page', 'masterstudy-lms-learning-management-system' ),
						'default'      => 10,
						'is_queryable' => false,
						'options'      => array(
							'25'  => esc_html__( '25 per page', 'masterstudy-lms-learning-management-system' ),
							'50'  => esc_html__( '50 per page', 'masterstudy-lms-learning-management-system' ),
							'75'  => esc_html__( '75 per page', 'masterstudy-lms-learning-management-system' ),
							'100' => esc_html__( '100 per page', 'masterstudy-lms-learning-management-system' ),
						),
					)
				);
			?>
			</div>
		</div>
	</div>
	<?php
endif;
