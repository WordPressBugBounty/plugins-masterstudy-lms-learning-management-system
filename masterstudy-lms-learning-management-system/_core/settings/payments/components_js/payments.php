<script type="text/javascript">
	<?php
	ob_start();
	require STM_LMS_PATH . '/settings/payments/components/payments.php';
	$template = preg_replace( "/\r|\n/", '', addslashes( ob_get_clean() ) );
	?>
	Vue.component('stm-payments', {
		props: ['saved_payments'],
		data: function () {
			return {
				payment_values : {},
				payments: {
					stripe: {
						enabled: '',
						displayShow: false,
						name: '<?php esc_html_e( 'Stripe', 'masterstudy-lms-learning-management-system' ); ?>',
						img: 'stripe.svg',
						payment_description: '',
						fields: {
							stripe_public_api_key: {
								type: 'text',
								placeholder: '<?php esc_html_e( 'Enter publishable key', 'masterstudy-lms-learning-management-system' ); ?>',
								info_title: '<?php esc_html_e( 'Publishable key', 'masterstudy-lms-learning-management-system' ); ?>',
								info_description: '<?php echo wp_kses_post( __( 'Enter your Stripe publishable key. Find it in your Stripe dashboard under <strong>Developers → API Keys.</strong>', 'masterstudy-lms-learning-management-system' ) ); ?>',
							},
							secret_key: {
								type: 'text',
								placeholder: '<?php esc_html_e( 'Enter secret key', 'masterstudy-lms-learning-management-system' ); ?>',
								info_title: '<?php esc_html_e( 'Secret key', 'masterstudy-lms-learning-management-system' ); ?>',
								info_description: '<?php echo wp_kses_post( __( 'Enter your secret key from the <strong>API Keys</strong> section in your Stripe dashboard for secure payments.', 'masterstudy-lms-learning-management-system' ) ); ?>',
							},
							description: {
								type: 'textarea',
								placeholder: '<?php esc_html_e( 'Enter payment method description', 'masterstudy-lms-learning-management-system' ); ?>',
								info_title: '<?php esc_html_e( 'Checkout description', 'masterstudy-lms-learning-management-system' ); ?>',
								info_description: '<?php esc_html_e( 'Shown to users during checkout. Add a short message (e.g. “Pay securely with Stripe”).', 'masterstudy-lms-learning-management-system' ); ?>',
							},
							currency: {
								type: 'text',
								placeholder: '<?php esc_html_e( 'Enter Stripe currency code', 'masterstudy-lms-learning-management-system' ); ?>',
								info_title: '<?php esc_html_e( 'Stripe currency code', 'masterstudy-lms-learning-management-system' ); ?>',
								info_description: '<?php echo wp_kses_post( __( 'Set the currency for this payment method (e.g. USD, EUR, GBP). Use the standard 3-letter <a href="https://docs.stripe.com/currencies">ISO code</a>.', 'masterstudy-lms-learning-management-system' ) ); ?>',
							},
						},
					},
					paypal: {
						enabled: '',
						displayShow: false,
						name: "<?php esc_html_e( 'PayPal', 'masterstudy-lms-learning-management-system' ); ?>",
						img: 'paypal.svg',
						payment_description: '',
						fields: {
							paypal_email: {
								type: 'text',
								placeholder: '<?php esc_html_e( 'Enter PayPal email', 'masterstudy-lms-learning-management-system' ); ?>',
								info_title: '<?php esc_html_e( 'PayPal business email', 'masterstudy-lms-learning-management-system' ); ?>',
								info_description: '<?php esc_html_e( 'Enter the PayPal email address where you want to receive payments.', 'masterstudy-lms-learning-management-system' ); ?>',
							},
							currency_code: {
								type: 'select',
								source: 'codes',
								value : 'USD',
								placeholder: '<?php esc_html_e( 'Select currency code', 'masterstudy-lms-learning-management-system' ); ?>',
								info_title: '<?php esc_html_e( 'Currency', 'masterstudy-lms-learning-management-system' ); ?>',
								info_description: '<?php esc_html_e( 'Choose the currency for this payment method.', 'masterstudy-lms-learning-management-system' ); ?>',
							},
							paypal_mode: {
								type: 'select',
								source: 'modes',
								value : 'sandbox',
								placeholder: '<?php esc_html_e( 'Select PayPal mode', 'masterstudy-lms-learning-management-system' ); ?>',
								info_title: '<?php esc_html_e( 'Payment mode', 'masterstudy-lms-learning-management-system' ); ?>',
								info_description: '<?php esc_html_e( 'Choose Live to accept real payments or Sandbox to test.', 'masterstudy-lms-learning-management-system' ); ?>',
							},
							description: {
								type: 'textarea',
								placeholder: '<?php esc_html_e( 'Enter payment method description', 'masterstudy-lms-learning-management-system' ); ?>',
								info_title: '<?php esc_html_e( 'Checkout description', 'masterstudy-lms-learning-management-system' ); ?>',
								info_description: '<?php esc_html_e( 'This will appear as the PayPal payment option title at checkout.', 'masterstudy-lms-learning-management-system' ); ?>',
							},
						},
					},
					wire_transfer: {
						enabled: '',
						displayShow: false,
						name: "<?php esc_html_e( 'Wire Transfer', 'masterstudy-lms-learning-management-system' ); ?>",
						img: 'wire-transfer.svg',
						fields: {
							account_number: {
								type: 'text',
								placeholder: '<?php esc_html_e( 'Enter account number', 'masterstudy-lms-learning-management-system' ); ?>',
								info_title: '<?php esc_html_e( 'Account number', 'masterstudy-lms-learning-management-system' ); ?>',
								info_description: '<?php esc_html_e( 'Provide your bank account number for payments.', 'masterstudy-lms-learning-management-system' ); ?>',
							},
							holder_name: {
								type: 'text',
								placeholder: '<?php esc_html_e( 'Enter holder name', 'masterstudy-lms-learning-management-system' ); ?>',
								info_title: '<?php esc_html_e( 'Account holder name', 'masterstudy-lms-learning-management-system' ); ?>',
								info_description: '<?php esc_html_e( 'Enter the full name of the account owner.', 'masterstudy-lms-learning-management-system' ); ?>',
							},
							bank_name: {
								type: 'text',
								placeholder: '<?php esc_html_e( 'Enter bank name', 'masterstudy-lms-learning-management-system' ); ?>',
								info_title: '<?php esc_html_e( 'Bank name', 'masterstudy-lms-learning-management-system' ); ?>',
								info_description: '<?php esc_html_e( 'Enter your bank’s official name.', 'masterstudy-lms-learning-management-system' ); ?>',
							},
							swift: {
								type: 'text',
								placeholder: '<?php esc_html_e( 'Enter SWIFT/BIC code', 'masterstudy-lms-learning-management-system' ); ?>',
								info_title: '<?php esc_html_e( 'SWIFT/BIC code', 'masterstudy-lms-learning-management-system' ); ?>',
								info_description: '<?php esc_html_e( 'Enter the SWIFT or BIC code for international transfers.', 'masterstudy-lms-learning-management-system' ); ?>',
							},
							description: {
								type: 'textarea',
								placeholder: '<?php esc_html_e( 'Enter payment method description', 'masterstudy-lms-learning-management-system' ); ?>',
								info_title: '<?php esc_html_e( 'Checkout description', 'masterstudy-lms-learning-management-system' ); ?>',
								info_description: '<?php esc_html_e( 'This will appear as the wire transfer option at checkout.', 'masterstudy-lms-learning-management-system' ); ?>',
							},
						},
					},
					cash: {
						enabled: '',
						displayShow: false,
						name: "<?php esc_html_e( 'Offline Payment', 'masterstudy-lms-learning-management-system' ); ?>",
						img: 'offline.svg',
						fields: {
							description: {
								type: 'textarea',
								placeholder: '<?php esc_html_e( 'Enter payment method description', 'masterstudy-lms-learning-management-system' ); ?>',
								info_title: '<?php esc_html_e( 'Offline payment processing', 'masterstudy-lms-learning-management-system' ); ?>',
								info_description: '<?php esc_html_e( 'Accept payments offline. Orders will be created and stored in the system for your manual approval.', 'masterstudy-lms-learning-management-system' ); ?>',
							},
						},
					},
				},
				sources: {
					codes: {
						'<?php esc_html_e( 'Select Currency code', 'masterstudy-lms-learning-management-system' ); ?>' : '',
						'<?php esc_html_e( 'Australian dollar', 'masterstudy-lms-learning-management-system' ); ?>' : 'AUD',
						'<?php esc_html_e( 'Brazilian real', 'masterstudy-lms-learning-management-system' ); ?>' : 'BRL',
						'<?php esc_html_e( 'Canadian dollar', 'masterstudy-lms-learning-management-system' ); ?>' : 'CAD',
						'<?php esc_html_e( 'Czech koruna', 'masterstudy-lms-learning-management-system' ); ?>' : 'CZK',
						'<?php esc_html_e( 'Danish krone', 'masterstudy-lms-learning-management-system' ); ?>' : 'DKK',
						'<?php esc_html_e( 'Euro', 'masterstudy-lms-learning-management-system' ); ?>' : 'EUR',
						'<?php esc_html_e( 'Hong Kong dollar', 'masterstudy-lms-learning-management-system' ); ?>' : 'HKD',
						'<?php esc_html_e( 'Hungarian forint 1', 'masterstudy-lms-learning-management-system' ); ?>' : 'HUF',
						'<?php esc_html_e( 'Indian rupee', 'masterstudy-lms-learning-management-system' ); ?>' : 'INR',
						'<?php esc_html_e( 'Israeli new shekel', 'masterstudy-lms-learning-management-system' ); ?>' : 'ILS',
						'<?php esc_html_e( 'Japanese yen 1', 'masterstudy-lms-learning-management-system' ); ?>' : 'JPY',
						'<?php esc_html_e( 'Malaysian ringgit 2	', 'masterstudy-lms-learning-management-system' ); ?>' : 'MYR',
						'<?php esc_html_e( 'Mexican peso', 'masterstudy-lms-learning-management-system' ); ?>' : 'MXN',
						'<?php esc_html_e( 'New Taiwan dollar 1', 'masterstudy-lms-learning-management-system' ); ?>' : 'TWD',
						'<?php esc_html_e( 'New Zealand dollar', 'masterstudy-lms-learning-management-system' ); ?>' : 'NZD',
						'<?php esc_html_e( 'Norwegian krone', 'masterstudy-lms-learning-management-system' ); ?>' : 'NOK',
						'<?php esc_html_e( 'Philippine peso', 'masterstudy-lms-learning-management-system' ); ?>' : 'PHP',
						'<?php esc_html_e( 'Polish złoty', 'masterstudy-lms-learning-management-system' ); ?>' : 'PLN',
						'<?php esc_html_e( 'Pound sterling', 'masterstudy-lms-learning-management-system' ); ?>' : 'GBP',
						'<?php esc_html_e( 'Russian ruble', 'masterstudy-lms-learning-management-system' ); ?>' : 'RUB',
						'<?php esc_html_e( 'Singapore dollar', 'masterstudy-lms-learning-management-system' ); ?>' : 'SGD',
						'<?php esc_html_e( 'Swedish krona', 'masterstudy-lms-learning-management-system' ); ?>' : 'SEK',
						'<?php esc_html_e( 'Swiss franc', 'masterstudy-lms-learning-management-system' ); ?>' : 'CHF',
						'<?php esc_html_e( 'Thai baht', 'masterstudy-lms-learning-management-system' ); ?>' : 'THB',
						'<?php esc_html_e( 'United States dollar', 'masterstudy-lms-learning-management-system' ); ?>' : 'USD',
					},
					modes : {
						'<?php esc_html_e( 'Sandbox', 'masterstudy-lms-learning-management-system' ); ?>' : 'sandbox',
						'<?php esc_html_e( 'Live', 'masterstudy-lms-learning-management-system' ); ?>' : 'live',
					}
				}
			}
		},
		template: '<?php /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ echo stm_wpcfto_filtered_output( $template ); ?>',
		mounted: function () {
			if (this.saved_payments) this.setPaymentValues();
		},
		methods: {
			setPaymentValues() {
				var vm = this;
				for(var payment_method in vm.payments) {
					if (!vm.payments.hasOwnProperty(payment_method) && !vm.saved_payments.hasOwnProperty(payment_method)) continue;
					vm.payments[payment_method]['enabled'] = vm.saved_payments[payment_method]['enabled'];

					for(var field_name in vm.payments[payment_method]['fields']) {
						vm.$set(vm.payments[payment_method]['fields'][field_name], 'value', vm.saved_payments[payment_method]['fields'][field_name]);
					}
				}
			},
			getPaymentValues() {
				var vm = this;
				for(var payment_method in vm.payments) {

					if (!vm.payments.hasOwnProperty(payment_method)) continue;
					vm.payment_values[payment_method] = {
						'enabled' : vm.payments[payment_method]['enabled'],
					};

					if(typeof vm.payment_values[payment_method]['fields'] === 'undefined') vm.payment_values[payment_method]['fields'] = {};

					for(var field_name in vm.payments[payment_method]['fields']) {
						if (! vm.payments[payment_method]['fields'].hasOwnProperty(field_name)) continue;
						var value = (typeof vm.payments[payment_method]['fields'][field_name]['value'] === 'undefined') ? '' : vm.payments[payment_method]['fields'][field_name]['value'];

						vm.payment_values[payment_method]['fields'][field_name] = value;

					}
				}

				this.$emit('update-payments', vm.payment_values);
			},
			togglePayment(paymentKey, event) {
				this.payments[paymentKey].displayShow = !this.payments[paymentKey].displayShow;
			}
		},
		watch: {
			payments: {
				handler: function () {
					this.getPaymentValues();
				},
				deep: true
			},
		}
	})
</script>
