<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;} // Exit if accessed directly ?>

<?php
/**
 * @var $total
 */

$payment_methods = STM_LMS_Options::get_option( 'payment_methods' );
if ( ! empty( $payment_methods ) ) :
	$payment_method_names = STM_LMS_Cart::payment_methods();
	?>

	<div class="stm-lms-payment-methods">
		<?php foreach ( $payment_methods as $payment_method_code => $payment_method ) : ?>
			<?php if ( ! empty( $payment_method['enabled'] ) ) : ?>
				<div class="stm-lms-payment-method" v-bind:class="{'active' : payment_code == '<?php echo esc_attr( $payment_method_code ); ?>'}">
					<div class="stm-lms-payment-method__name">
						<label>
							<span class="wpcfto_radio">
								<input type="radio"
									name="payment_method"
									v-model="payment_code"
									value="<?php echo esc_attr( $payment_method_code ); ?>"/>
								<span class="wpcfto_radio__fake"></span>
							</span>
							<h4><?php echo esc_html( $payment_method_names[ $payment_method_code ] ); ?></h4>
						</label>
					</div>
					<?php if ( ! empty( $payment_method['fields'] ) && 'paypal' !== $payment_method_code && 'stripe' !== $payment_method_code ) : ?>
						<transition name="slide-fade">
							<div class="stm-lms-payment-method__fields"
								v-if="payment_code == '<?php echo esc_attr( $payment_method_code ); ?>'">
								<?php foreach ( $payment_method['fields'] as $payment_field_key => $payment_field ) : ?>
									<div class="stm-lms-payment-method__field">
										<div class="stm-lms-payment-method__field_label">
											<?php echo esc_html( $payment_method_names[ $payment_field_key ] ); ?>
										</div>
										<div class="stm-lms-payment-method__field_value">
											<?php echo nl2br( esc_html( $payment_field ) ); ?>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						</transition>
					<?php elseif ( 'stripe' === $payment_method_code ) : ?>
						<transition name="slide-fade">
							<div class="stm-lms-payment-method__fields"
								v-if="payment_code == '<?php echo esc_attr( $payment_method_code ); ?>'">
								<?php
								foreach ( $payment_method['fields'] as $payment_field_key => $payment_field ) :
									if ( 'secret_key' === $payment_field_key ) {
										continue;
									}
									if ( 'stripe_public_api_key' === $payment_field_key ) :
										?>
										<script type="text/javascript">
											var stripe_id = '<?php echo esc_js( $payment_field ); ?>';
										</script>
									<?php else : ?>
										<div class="stm-lms-payment-method__field">
											<div class="stm-lms-payment-method__field_label">
												<?php echo esc_html( $payment_method_names[ $payment_field_key ] ); ?>
											</div>
											<div class="stm-lms-payment-method__field_value">
												<?php echo nl2br( esc_html( $payment_field ) ); ?>
											</div>
										</div>
										<div id="stm-lms-stripe"></div>
									<?php endif; ?>
								<?php endforeach; ?>
							</div>
						</transition>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>

	<?php
else :
	esc_html__( 'No available Payment methods', 'masterstudy-lms-learning-management-system' );
endif;
