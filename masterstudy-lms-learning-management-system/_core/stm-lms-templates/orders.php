<?php

use MasterStudy\Lms\Enums\OrderStatus;

global $ms_lms_loaded_textdomain_path;
$translations_path = ! empty( $ms_lms_loaded_textdomain_path ) ? $ms_lms_loaded_textdomain_path : MS_LMS_PATH . '/languages';

wp_enqueue_script( 'ms-lms-react-orders', apply_filters( 'ms_lms_orders_js', MS_LMS_URL . 'assets/course-builder/js/main.js' ), array(), MS_LMS_VERSION, true );

wp_set_script_translations( 'ms-lms-react-orders', 'masterstudy-lms-learning-management-system', $translations_path );

$scripts      = wp_scripts();
$load_scripts = array(
	'wp-polyfill-inert',
	'regenerator-runtime',
	'wp-polyfill',
	'wp-hooks',
	'wp-i18n',
	'utils',
);

wp_localize_script(
	'ms-lms-react-orders',
	'react_orders',
	array(
		'statuses'               => array_map( 'strval', OrderStatus::cases() ),
		'is_woocommerce'         => STM_LMS_Cart::woocommerce_checkout_enabled(),
		'woocommerce_orders_url' => admin_url( 'admin.php?page=wc-orders' ),
	)
);
?>

<div id="ms_wp_react_orders" class="ms-react-app__no-container-padding"></div>
<script>
	window.lmsApiSettings = {
		lmsUrl: '<?php echo esc_url_raw( rest_url( 'masterstudy-lms/v2' ) ); ?>',
		wpUrl: '<?php echo esc_url_raw( rest_url( 'wp/v2' ) ); ?>',
		nonce: '<?php echo esc_html( wp_create_nonce( 'wp_rest' ) ); ?>',
	};

	<?php if ( function_exists( 'pll_current_language' ) ) { ?>
	window.lmsApiSettings.lang = '<?php echo esc_js( pll_current_language() ); ?>';
	<?php } ?>

	window.lmsApiSettings.locale = '<?php echo esc_attr( get_locale() ); ?>';
	window.lmsApiSettings.wp_date_format = '<?php echo esc_attr( get_option( 'date_format' ) ); ?>';
</script>
<?php
foreach ( $load_scripts as $handle ) {
	$handle_src = $scripts->registered[ $handle ]->src;
	$src_url    = filter_var( $handle_src, FILTER_VALIDATE_URL ) ? $handle_src : site_url( $handle_src );
	?>
	<script src="<?php echo esc_url( $src_url ); // phpcs:ignore ?>"></script>
<?php } ?>
<script src="<?php echo esc_url( $scripts->registered['ms-lms-react-orders']->src ); // phpcs:ignore ?>"></script>

