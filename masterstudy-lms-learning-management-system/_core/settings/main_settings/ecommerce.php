<?php

use MasterStudy\Lms\Plugin\Addons;

function masterstudy_lms_get_currencies_array() {
	return array(
		'USD' => 'USD ($)',
		'EUR' => 'EUR (€)',
		'GBP' => 'GBP (£)',
		'CAD' => 'CAD ($)',
		'AED' => 'AED (AED)',
		'AFN' => 'AFN (؋)',
		'ALL' => 'ALL (Lek)',
		'AMD' => 'AMD (AMD)',
		'ANG' => 'ANG (ƒ)',
		'AOA' => 'AOA (Kz)',
		'ARS' => 'ARS ($)',
		'AUD' => 'AUD ($)',
		'AWG' => 'AWG (AWG)',
		'AZN' => 'AZN (₼)',
		'BAM' => 'BAM (KM)',
		'BBD' => 'BBD ($)',
		'BDT' => 'BDT (৳)',
		'BGN' => 'BGN (BGN)',
		'BHD' => 'BHD (BHD)',
		'BIF' => 'BIF (FBu)',
		'BMD' => 'BMD (BD$)',
		'BND' => 'BND (B$)',
		'BOB' => 'BOB (Bs)',
		'BRL' => 'BRL (R$)',
		'BSD' => 'BSD (B$)',
		'BTN' => 'BTN (Nu.)',
		'BWP' => 'BWP (P)',
		'BYN' => 'BYN (BYN)',
		'BZD' => 'BZD ($)',
		'CDF' => 'CDF (FDC)',
		'CHF' => 'CHF (CHF)',
		'CLP' => 'CLP ($)',
		'CNY' => 'CNY (¥)',
		'COP' => 'COP ($)',
		'CRC' => 'CRC (₡)',
		'CUP' => 'CUP ($)',
		'CVE' => 'CVE (CVE)',
		'CZK' => 'CZK (Kč)',
		'DJF' => 'DJF (Fdj)',
		'DKK' => 'DKK (Kr.)',
		'DOP' => 'DOP ($)',
		'DZD' => 'DZD (DZD)',
		'EEK' => 'EEK (KR)',
		'EGP' => 'EGP (£)',
		'ERN' => 'ERN (ERN)',
		'ETB' => 'ETB (ETB)',
		'FJD' => 'FJD ($)',
		'FKP' => 'FKP (£)',
		'GEL' => 'GEL (GEL)',
		'GHS' => 'GHS (₵)',
		'GIP' => 'GIP (£)',
		'GMD' => 'GMD (D)',
		'GNF' => 'GNF (FG)',
		'XAF' => 'XAF (F.CFA)',
		'GTQ' => 'GTQ (Q)',
		'GYD' => 'GYD ($)',
		'HKD' => 'HKD ($)',
		'HNL' => 'HNL (L)',
		'HRK' => 'HRK (kn)',
		'HTG' => 'HTG (G)',
		'HUF' => 'HUF (Ft)',
		'IDR' => 'IDR (Rp)',
		'ILS' => 'ILS (₪)',
		'INR' => 'INR (₹)',
		'IQD' => 'IQD (IQD)',
		'IRR' => 'IRR (IRR)',
		'ISK' => 'ISK (kr)',
		'JMD' => 'JMD ($)',
		'JOD' => 'JOD (JOD)',
		'JPY' => 'JPY (¥)',
		'KES' => 'KES (KES)',
		'KGS' => 'KGS (KGS)',
		'KHR' => 'KHR (៛)',
		'KMF' => 'KMF (KMF)',
		'KPW' => 'KPW (₩)',
		'KRW' => 'KRW (₩)',
		'KWD' => 'KWD (KWD)',
		'KYD' => 'KYD ($)',
		'KZT' => 'KZT (₸)',
		'LAK' => 'LAK (₭)',
		'LBP' => 'LBP (L£)',
		'LKR' => 'LKR (Rs)',
		'LRD' => 'LRD ($)',
		'LSL' => 'LSL (LSL)',
		'LTL' => 'LTL (Lt)',
		'LVL' => 'LVL (LVL)',
		'LYD' => 'LYD (LD)',
		'MAD' => 'MAD (MAD)',
		'MDL' => 'MDL (MDL)',
		'MGA' => 'MGA (Ar)',
		'MKD' => 'MKD (MKD)',
		'MMK' => 'MMK (K)',
		'MNT' => 'MNT (₮)',
		'MOP' => 'MOP (MOP)',
		'MRO' => 'MRO (UM)',
		'MUR' => 'MUR (Rs)',
		'MVR' => 'MVR (Rf)',
		'MWK' => 'MWK (MWK)',
		'MXN' => 'MXN ($)',
		'MYR' => 'MYR (RM)',
		'MZN' => 'MZN (MZN)',
		'NAD' => 'NAD ($)',
		'NGN' => 'NGN (₦)',
		'NIO' => 'NIO ($)',
		'NOK' => 'NOK (kr)',
		'NPR' => 'NPR (NPR)',
		'NZD' => 'NZD ($)',
		'OMR' => 'OMR (OMR)',
		'PAB' => 'PAB (PAB)',
		'PEN' => 'PEN (PEN)',
		'PGK' => 'PGK (K)',
		'PHP' => 'PHP (₱)',
		'PKR' => 'PKR (Rs)',
		'PLN' => 'PLN (zł)',
		'PYG' => 'PYG (₲)',
		'QAR' => 'QAR (QAR)',
		'RON' => 'RON (RON)',
		'RSD' => 'RSD (RSD)',
		'RUB' => 'RUB (₽)',
		'SAR' => 'SAR (SAR)',
		'SBD' => 'SBD ($)',
		'SCR' => 'SCR (Rs)',
		'SDG' => 'SDG (SDG)',
		'SEK' => 'SEK (kr)',
		'SGD' => 'SGD ($)',
		'SHP' => 'SHP (£)',
		'SLL' => 'SLL (SLL)',
		'SOS' => 'SOS (SOS)',
		'SRD' => 'SRD ($)',
		'SYP' => 'SYP (£)',
		'SZL' => 'SZL (SZL)',
		'THB' => 'THB (฿)',
		'TJS' => 'TJS (TJS)',
		'TMT' => 'TMT (m)',
		'TND' => 'TND (TND)',
		'TRY' => 'TRY (TRY)',
		'TTD' => 'TTD ($)',
		'TWD' => 'TWD ($)',
		'TZS' => 'TZS (TZS)',
		'UAH' => 'UAH (UAH)',
		'UGX' => 'UGX (UGX)',
		'UYU' => 'UYU ($)',
		'UZS' => 'UZS (UZS)',
		'VES' => 'VES (VES)',
		'VND' => 'VND (₫)',
		'VUV' => 'VUV (VT)',
		'WST' => 'WST ($)',
		'XCD' => 'XCD ($)',
		'XDR' => 'XDR (SDR)',
		'XOF' => 'XOF (CFA)',
		'XPF' => 'XPF (F)',
		'YER' => 'YER (YER)',
		'ZAR' => 'ZAR (R)',
		'ZMK' => 'ZMK (ZK)',
		'ZWR' => 'ZWR ($)',
	);
}

function stm_lms_settings_ecommerce_section() {
	$is_payout_enabled = is_ms_lms_addon_enabled( Addons::STATISTICS );
	$is_pro            = STM_LMS_Helpers::is_pro();
	$submenu_currency  = esc_html__( 'Currency', 'masterstudy-lms-learning-management-system' );
	$submenu_checkout  = esc_html__( 'Checkout', 'masterstudy-lms-learning-management-system' );
	$submenu_payment   = esc_html__( 'Payment Methods', 'masterstudy-lms-learning-management-system' );
	$submenu_payout    = esc_html__( 'Payout', 'masterstudy-lms-learning-management-system' );

	$currency_fields = array(
		'transactions_currency'    => array(
			'type'          => 'search-select',
			'label'         => esc_html__( 'Transactions currency', 'masterstudy-lms-learning-management-system' ),
			'columns'       => '50',
			'description'   => esc_html__( 'Choose the currency for all transactions.', 'masterstudy-lms-learning-management-system' ),
			'submenu'       => $submenu_currency,
			'submenu_title' => true,
			'options'       => masterstudy_lms_get_currencies_array(),
		),
		'currency_symbol'    => array(
			'type'          => 'text',
			'label'         => esc_html__( 'Currency symbol', 'masterstudy-lms-learning-management-system' ),
			'columns'       => '50',
			'description'   => esc_html__( 'The symbol for money that shows up on your site (like $ for dollars)', 'masterstudy-lms-learning-management-system' ),
			'submenu'       => $submenu_currency,
			'submenu_title' => true,
		),
		'currency_position'  => array(
			'type'        => 'select',
			'label'       => esc_html__( 'Currency position', 'masterstudy-lms-learning-management-system' ),
			'value'       => 'left',
			'options'     => array(
				'left'  => esc_html__( 'Left', 'masterstudy-lms-learning-management-system' ),
				'right' => esc_html__( 'Right', 'masterstudy-lms-learning-management-system' ),
			),
			'columns'     => '50',
			'description' => esc_html__( 'Decide if the money symbol goes before or after the number', 'masterstudy-lms-learning-management-system' ),
			'submenu'     => $submenu_currency,
		),
		'currency_thousands' => array(
			'type'        => 'text',
			'label'       => esc_html__( 'Thousands separator', 'masterstudy-lms-learning-management-system' ),
			'value'       => ',',
			'columns'     => '33',
			'description' => esc_html__( 'The symbol to split large numbers into groups, like 1,000', 'masterstudy-lms-learning-management-system' ),
			'submenu'     => $submenu_currency,
		),
		'currency_decimals'  => array(
			'type'        => 'text',
			'label'       => esc_html__( 'Decimals separator', 'masterstudy-lms-learning-management-system' ),
			'value'       => '.',
			'columns'     => '33',
			'description' => esc_html__( 'The symbol to show the decimal point, like 12.45', 'masterstudy-lms-learning-management-system' ),
			'submenu'     => $submenu_currency,
		),
		'decimals_num'       => array(
			'type'        => 'number',
			'label'       => esc_html__( 'Number of fractional numbers allowed', 'masterstudy-lms-learning-management-system' ),
			'value'       => 2,
			'columns'     => '33',
			'description' => esc_html__( 'Define how many numbers can be after the decimal point, like 2 in 7.49', 'masterstudy-lms-learning-management-system' ),
			'submenu'     => $submenu_currency,
		),
	);

	$checkout_fields = array(
		'ecommerce_engine'                     => array(
			'type'        => 'select',
			'label'       => esc_html__( 'Select eCommerce Engine', 'masterstudy-lms-learning-management-system' ),
			'value'       => 'native',
			'options'     => array(
				'native'      => esc_html__( 'Native', 'masterstudy-lms-learning-management-system' ),
				'woocommerce' => esc_html__( 'WooCommerce Checkout', 'masterstudy-lms-learning-management-system' ),
			),
			'columns'     => '50',
			'description' => esc_html__( 'eCommerce engine to manage payments. Native uses MasterStudy’s built-in payment methods, while WooCommerce requires its plugin and setup accordingly.', 'masterstudy-lms-learning-management-system' ),
			'submenu'     => $submenu_checkout,
			'pro'         => true,
		),
		'pro_banner_woo'                       => array(
			'type'    => 'pro_banner',
			'label'   => esc_html__( 'Woocommerce Checkout', 'masterstudy-lms-learning-management-system' ),
			'img'     => STM_LMS_URL . 'assets/img/pro-features/woocommerce-checkout.png',
			'desc'    => esc_html__( 'Upgrade to Pro now and streamline your checkout process to boost your online course sales.', 'masterstudy-lms-learning-management-system' ),
			'value'   => STM_LMS_Helpers::is_pro() ? '' : 'pro_banner',
			'submenu' => $submenu_checkout,
		),
		'guest_checkout'                       => array(
			'type'        => 'checkbox',
			'label'       => esc_html__( 'Guest checkout', 'masterstudy-lms-learning-management-system' ),
			'description' => esc_html__( 'Allow guests to register an account during checkout', 'masterstudy-lms-learning-management-system' ),
			'submenu'     => $submenu_checkout,
		),
		'guest_checkout_notice'                => array(
			'type'         => 'notice_banner',
			'label'        => esc_html__( 'Required to enable guest checkout in WooCommerce', 'masterstudy-lms-learning-management-system' ),
			'dependency'   => array(
				array(
					'key'   => 'ecommerce_engine',
					'value' => 'woocommerce',
				),
				array(
					'key'   => 'guest_checkout',
					'value' => 'not_empty',
				),
			),
			'dependencies' => '&&',
			'submenu'      => $submenu_checkout,
		),
		'redirect_after_purchase'              => array(
			'type'        => 'checkbox',
			'label'       => esc_html__( 'Redirect to checkout after adding to cart', 'masterstudy-lms-learning-management-system' ),
			'description' => esc_html__( 'The feature is not available when WooCommerce checkout is enabled', 'masterstudy-lms-learning-management-system' ),
			'submenu'     => $submenu_checkout,
		),
		'redirect_after_purchase_notice'       => array(
			'type'       => 'notice_banner',
			'label'      => esc_html__( 'The feature is not available when WooCommerce checkout is enabled', 'masterstudy-lms-learning-management-system' ),
			'dependency' => array(
				'key'   => 'ecommerce_engine',
				'value' => 'woocommerce',
			),
			'submenu'    => $submenu_checkout,
		),
		'woocommerce_course_visibility'        => array(
			'type'       => 'checkbox',
			'label'      => esc_html__( 'Display courses on WooCommerce shop page', 'masterstudy-lms-learning-management-system' ),
			'hint'       => esc_html__( 'Enable this setting if you want to show courses in the product catalog of WooCommerce shop page', 'masterstudy-lms-learning-management-system' ),
			'pro'        => true,
			'dependency' => array(
				'key'   => 'ecommerce_engine',
				'value' => 'woocommerce',
			),
			'pro_url'    => admin_url( 'admin.php?page=stm-lms-go-pro&source=wocommerce-checkout-settings' ),
			'submenu'    => $submenu_checkout,
		),
		'woocommerce_course_visibility_notice' => array(
			'type'         => 'notice_banner',
			'label'        => sprintf(
			/* translators: %s link to plugin */
				esc_html__( 'If price filtering doesn’t work for courses and products, try regenerating `Product lookup tables` in %s.', 'masterstudy-lms-learning-management-system' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=tools' ) ) . '" target="_blank">' . esc_html__( 'WooCommerce settings', 'masterstudy-lms-learning-management-system' ) . '</a>'
			),
			'dependency'   => array(
				array(
					'key'   => 'ecommerce_engine',
					'value' => 'woocommerce',
				),
				array(
					'key'   => 'woocommerce_course_visibility',
					'value' => 'not_empty',
				),
			),
			'dependencies' => '&&',
			'submenu'      => $submenu_checkout,
		),

		/*GROUP STARTED*/
		// phpcs:disable Squiz.PHP.CommentedOutCode.Found
		/*'woo_country'                          => array(
			'group'       => 'started',
			'type'        => 'checkbox',
			'label'       => esc_html__( 'Country', 'masterstudy-lms-learning-management-system' ),
			'description' => esc_html__( 'Если включены Taxes отключить нельзя', 'masterstudy-lms-learning-management-system' ),
			'group_title' => esc_html__( 'Checkout Fields', 'masterstudy-lms-learning-management-system' ),
			'submenu'     => $submenu_checkout,
		),
		'woo_postcode'                         => array(
			'type'        => 'checkbox',
			'label'       => esc_html__( 'Postcode', 'masterstudy-lms-learning-management-system' ),
			'description' => esc_html__( 'Если включены Taxes отключить нельзя', 'masterstudy-lms-learning-management-system' ),
			'submenu'     => $submenu_checkout,
		),
		'woo_state'                            => array(
			'type'        => 'checkbox',
			'label'       => esc_html__( 'State', 'masterstudy-lms-learning-management-system' ),
			'description' => esc_html__( 'Если включены Taxes отключить нельзя', 'masterstudy-lms-learning-management-system' ),
			'submenu'     => $submenu_checkout,
		),
		'woo_town_city'                        => array(
			'type'        => 'checkbox',
			'label'       => esc_html__( 'Town/City', 'masterstudy-lms-learning-management-system' ),
			'description' => esc_html__( 'Если включены Taxes отключить нельзя', 'masterstudy-lms-learning-management-system' ),
			'submenu'     => $submenu_checkout,
		),
		'woo_company_name'                     => array(
			'type'    => 'checkbox',
			'label'   => esc_html__( 'Company Name', 'masterstudy-lms-learning-management-system' ),
			'submenu' => $submenu_checkout,
		),
		'woo_phone_number'                     => array(
			'type'    => 'checkbox',
			'label'   => esc_html__( 'Phone Number', 'masterstudy-lms-learning-management-system' ),
			'submenu' => $submenu_checkout,
			'group'   => 'ended''
		),*/
		// phpcs:enable
		/*GROUP ENDED*/
	);

	$currency_fields = array_merge( $currency_fields, $checkout_fields ?? array() );

	$payment_fields = array(
		'payment_methods' => array(
			'type'    => 'payments',
			'label'   => esc_html__( 'Payment Methods', 'masterstudy-lms-learning-management-system' ),
			'submenu' => $submenu_payment,
		),
	);

	$currency_fields = array_merge( $currency_fields, $payment_fields ?? array() );

	$payout_fields = array(
		'admin_fee'  => array(
			'group'       => 'started',
			'type'        => 'number',
			'label'       => esc_html__( 'Admin Comission (%)', 'masterstudy-lms-learning-management-system' ),
			'value'       => '90',
			'pro'         => true,
			'pro_url'     => admin_url( 'admin.php?page=stm-lms-go-pro&source=instructor-earnings-settings' ),
			'description' => esc_html__( 'Put the percentage admin will get from sales', 'masterstudy-lms-learning-management-system' ),
			'submenu'     => $submenu_payout,
		),
		'author_fee' => array(
			'type'        => 'number',
			'label'       => esc_html__( 'Instructor earnings (%)', 'masterstudy-lms-learning-management-system' ),
			'value'       => '10',
			'pro'         => true,
			'pro_url'     => admin_url( 'admin.php?page=stm-lms-go-pro&source=instructor-earnings-settings' ),
			'description' => esc_html__( 'Put the percentage instructors will get from sales', 'masterstudy-lms-learning-management-system' ),
			'submenu'     => $submenu_payout,
			'group'       => 'ended',
		),
		'pro_banner' => array(
			'type'    => 'pro_banner',
			'label'   => esc_html__( 'Payouts', 'masterstudy-lms-learning-management-system' ),
			'img'     => STM_LMS_URL . 'assets/img/pro-features/payouts.png',
			'desc'    => esc_html__( 'Make paying instructors easier with automated payouts to ensure timely and hassle-free earnings.', 'masterstudy-lms-learning-management-system' ),
			'hint'    => esc_html__( 'Automate', 'masterstudy-lms-learning-management-system' ),
			'submenu' => $submenu_payout,
		),
	);

	if ( STM_LMS_Helpers::is_pro() ) {
		$payout_fields['payout'] = array(
			'pro'     => true,
			'pro_url' => admin_url( 'admin.php?page=stm-lms-go-pro' ),
			'type'    => 'payout',
			'label'   => esc_html__( 'Masterstudy LMS PRO Payout', 'masterstudy-lms-learning-management-system' ),
			'submenu' => $submenu_payout,
		);
	}

	if ( ! $is_pro || ! $is_payout_enabled ) {
		$payout_fields = array(
			'pro_banner_payout' => array(
				'type'      => 'pro_banner',
				'label'     => esc_html__( 'Payouts', 'masterstudy-lms-learning-management-system' ),
				'img'       => STM_LMS_URL . 'assets/img/pro-features/addons/payouts.png',
				'desc'      => esc_html__( 'Make paying instructors easier with automated payouts to ensure timely and hassle-free earnings.' ),
				'hint'      => esc_html__( 'Automate', 'masterstudy-lms-learning-management-system' ),
				'is_enable' => $is_pro && ! $is_payout_enabled,
				'is_pro'    => true,
				'search'    => esc_html__( 'Payouts', 'masterstudy-lms-learning-management-system' ),
				'utm_url'   => 'https://stylemixthemes.com/wordpress-lms-plugin/pricing/?utm_source=mswpadmin&utm_medium=payouts&utm_campaign=masterstudy-plugin',
				'submenu'   => $submenu_payout,
			),
		);
	}

	$currency_fields = array_merge( $currency_fields, $payout_fields ?? array() );

	return array(
		'name'   => esc_html__( 'Ecommerce', 'masterstudy-lms-learning-management-system' ),
		'label'  => esc_html__( 'Ecommerce', 'masterstudy-lms-learning-management-system' ),
		'icon'   => 'fas fa-money-check-alt',
		'fields' => $currency_fields,
	);
}
