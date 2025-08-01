<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

function stm_lms_wp_head() {
	?>
	<script type="text/javascript">
		var stm_lms_ajaxurl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
		var stm_lms_resturl = '<?php echo rest_url( 'stm-lms/v1', 'json' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>';
		var ms_lms_resturl = '<?php echo rest_url( 'masterstudy-lms/v2', 'json' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>';
		var ms_lms_nonce = '<?php echo wp_create_nonce( 'wp_rest' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>';
		var stm_ajax_add_pear_hb = '<?php echo wp_create_nonce( 'stm_ajax_add_pear_hb' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>';
		<?php if ( function_exists( 'pll_current_language' ) ) : ?>
		var pll_current_language = '<?php echo esc_js( pll_current_language() ); ?>';
		<?php endif; ?>
	</script>
	<style>
		.vue_is_disabled {
			display: none;
		}
		#wp-admin-bar-lms-settings img {
			max-width: 16px;
			vertical-align: sub;
		}
	</style>
	<?php
}
add_action( 'wp_head', 'stm_lms_wp_head' );
add_action( 'admin_head', 'stm_lms_wp_head' );

function stm_lms_enqueue_ss() {
	$assets = STM_LMS_URL . 'assets';
	$base   = STM_LMS_URL . 'libraries/nuxy/metaboxes/assets/'; // Rewrite STM_WPCFTO_URL

	wp_register_style( 'masterstudy-fonts', $assets . '/css/variables/fonts.css', null, MS_LMS_VERSION );
	wp_enqueue_style( 'font-awesome-min', $assets . '/vendors/font-awesome.min.css', null, MS_LMS_VERSION, 'all' );
	wp_enqueue_style( 'stm_lms_icons', $assets . '/icons/style.css', null, MS_LMS_VERSION );
	wp_enqueue_style( 'video.js', $assets . '/vendors/video-js.min.css', null, MS_LMS_VERSION, 'all' );
	wp_register_style( 'owl.carousel', $assets . '/vendors/owl.carousel.min.css', null, MS_LMS_VERSION, 'all' );
	wp_register_style( 'masterstudy_lazysizes', $assets . '/css/lazysizes.css', null, MS_LMS_VERSION );

	wp_enqueue_script( 'jquery' );

	if ( STM_LMS_Helpers::is_stripe_enabled() ) {
		wp_enqueue_script( 'stripe.js', 'https://js.stripe.com/v3/#lms_defer', array(), false, false ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NoExplicitVersion
	}

	wp_register_script( 'vue.js', $base . 'js/vue.min.js', array( 'jquery' ), MS_LMS_VERSION, false );
	wp_register_script( 'vue-resource.js', $base . 'js/vue-resource.min.js', array( 'vue.js' ), MS_LMS_VERSION, false );
	wp_register_script( 'vue2-editor.js', $base . 'js/vue2-editor.min.js', array( 'vue.js' ), MS_LMS_VERSION, false );
	wp_register_script( 'vue2-datepicker', $base . 'js/vue2-datepicker.min.js', array( 'vue.js' ), MS_LMS_VERSION, false );

	if ( STM_LMS_Helpers::g_recaptcha_enabled() ) :
		$recaptcha = STM_LMS_Helpers::g_recaptcha_keys();

		wp_register_script(
			'stm_grecaptcha',
			'https://www.google.com/recaptcha/api.js?T=1&render=' . $recaptcha['public'],
			array( 'jquery' ),
			MS_LMS_VERSION,
			true
		);
	endif;

	wp_register_script( 'jquery.cookie', $assets . '/vendors/jquery.cookie.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'resize-sensor', $assets . '/vendors/ResizeSensor.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'sticky-sidebar', $assets . '/vendors/sticky-sidebar.min.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'video.js', $assets . '/vendors/video.min.js', array(), MS_LMS_VERSION, true );
	wp_register_script( 'owl.carousel', $assets . '/vendors/owl.carousel.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'vue2-autocomplete', $assets . '/vendors/vue2-autocomplete.js', array( 'vue.js' ), MS_LMS_VERSION, true );
	wp_register_script( 'stm-lms-countdown', $assets . '/js/countdown.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'lazysizes', $assets . '/vendors/lazysizes.min.js', array(), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy_lazysizes', $assets . '/js/lazyload.js', array( 'jquery', 'lazysizes' ), MS_LMS_VERSION, true );
	wp_register_script( 'jquery.countdown', $assets . '/vendors/jquery.countdown.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'js.countdown', $assets . '/vendors/js.countdown.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_localize_script(
		'jquery.countdown',
		'stm_lms_jquery_countdown_vars',
		array(
			'days'    => __( 'Days', 'masterstudy-lms-learning-management-system' ),
			'hours'   => __( 'Hours', 'masterstudy-lms-learning-management-system' ),
			'minutes' => __( 'Minutes', 'masterstudy-lms-learning-management-system' ),
			'seconds' => __( 'Seconds', 'masterstudy-lms-learning-management-system' ),
		)
	);
	wp_register_script( 'stm-lms-wishlist', $assets . '/js/wishlist.js', array( 'jquery' ), MS_LMS_VERSION, true );

	if ( stm_lms_has_custom_colors() ) {
		wp_enqueue_style( 'masterstudy-lms-learning-management-system', stm_lms_custom_styles_url() . '/stm_lms_styles/stm_lms.css', array(), stm_lms_custom_styles_v() );
	} else {
		wp_enqueue_style( 'masterstudy-lms-learning-management-system', $assets . '/css/stm_lms.css', array(), MS_LMS_VERSION );
	}

	if ( is_rtl() ) {
		wp_enqueue_style( 'masterstudy-lms-learning-management-system-rtl-styles', $assets . '/css/rtl-styles.css', array(), MS_LMS_VERSION );
	}

	if ( function_exists( 'vc_asset_url' ) ) {
		wp_register_style( 'stm_lms_wpb_front_css', vc_asset_url( 'css/js_composer.min.css' ), array(), MS_LMS_VERSION );
	}

	stm_lms_register_script( 'lms' );
	wp_localize_script(
		'stm-lms-lms',
		'stm_lms_vars',
		array(
			'symbol'             => STM_LMS_Options::get_option( 'currency_symbol', '$' ),
			'position'           => STM_LMS_Options::get_option( 'currency_position', 'left' ),
			'currency_thousands' => STM_LMS_Options::get_option( 'currency_thousands', ',' ),
			'wp_rest_nonce'      => wp_create_nonce( 'wp_rest' ),
			'translate'          => array(
				'delete' => esc_html__( 'Are you sure you want to delete this course from cart?', 'masterstudy-lms-learning-management-system' ),
			),
		)
	);

	if ( STM_LMS_Subscriptions::subscription_enabled() ) {
		stm_lms_register_style( 'pmpro' );
	}

	/*Enqueue not MasterStudy theme related styles*/
	if ( ! stm_lms_is_masterstudy_theme() ) {
		stm_lms_register_style( 'noconflict/main' );
	}
}

function stm_lms_enqueue_component_scripts( $hook_suffix ) {
	/*Components scripts registration*/
	wp_register_script( 'masterstudy-lamejs', STM_LMS_URL . 'assets/vendors/lamejs.js', array( 'jquery' ), MS_LMS_VERSION, true );

	/*Pages styles & scripts*/
	wp_register_style( 'masterstudy-login-page', STM_LMS_URL . 'assets/css/pages/login.css', null, MS_LMS_VERSION );
	wp_register_script( 'masterstudy-enrolled-courses', STM_LMS_URL . 'assets/js/account/v1/enrolled-courses.js', array( 'jquery', 'vue.js', 'vue-resource.js' ), MS_LMS_VERSION, true );
	wp_localize_script(
		'masterstudy-enrolled-courses',
		'student_data',
		array(
			'id'         => get_current_user_id(),
			'hide_stats' => __( 'Hide Statistics', 'masterstudy-lms-learning-management-system' ),
			'show_stats' => __( 'Show Statistics', 'masterstudy-lms-learning-management-system' ),
		)
	);

	wp_register_script( 'masterstudy-list-students', STM_LMS_URL . 'assets/js/students.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_style( 'masterstudy-list-students', STM_LMS_URL . 'assets/css/parts/students.css', array(), MS_LMS_VERSION, 'all' );

	wp_register_script( 'masterstudy-enrolled-quizzes', STM_LMS_URL . 'assets/js/enrolled-quizzes.js', array( 'jquery' ), MS_LMS_VERSION, true );

	/*Components vendors*/
	wp_register_script( 'masterstudy-select2', STM_LMS_URL . 'assets/vendors/select2.min.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_style( 'masterstudy-select2', STM_LMS_URL . 'assets/vendors/select2.min.css', array(), MS_LMS_VERSION );

	/*Components scripts registration*/
	wp_register_script( 'masterstudy-authorization-main', STM_LMS_URL . 'assets/js/components/authorization/main.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-authorization-ajax', STM_LMS_URL . 'assets/js/components/authorization/ajax.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_localize_script(
		'masterstudy-authorization-ajax',
		'masterstudy_authorization_data',
		array(
			'bad'    => esc_html__( 'Bad', 'masterstudy-lms-learning-management-system' ),
			'normal' => esc_html__( 'Normal', 'masterstudy-lms-learning-management-system' ),
			'good'   => esc_html__( 'Good', 'masterstudy-lms-learning-management-system' ),
			'hard'   => esc_html__( 'Hard', 'masterstudy-lms-learning-management-system' ),
		)
	);
	wp_register_script( 'masterstudy-authorization-new-pass', STM_LMS_URL . 'assets/js/components/authorization/new-pass.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-countdown', STM_LMS_URL . 'assets/js/components/countdown.js', array( 'jquery', 'jquery.countdown', 'js.countdown' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-curriculum-accordion', STM_LMS_URL . 'assets/js/components/curriculum-accordion.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-curriculum-list', STM_LMS_URL . 'assets/js/components/course/curriculum.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-discussions', STM_LMS_URL . 'assets/js/components/discussions.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-hint', STM_LMS_URL . 'assets/js/components/hint.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-loader', STM_LMS_URL . 'assets/js/components/loader.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-pagination', STM_LMS_URL . 'assets/js/components/pagination.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-ajax-pagination', STM_LMS_URL . 'assets/js/components/ajax-pagination.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-api-pagination', STM_LMS_URL . 'assets/js/components/api-pagination.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-tabs-pagination', STM_LMS_URL . 'assets/js/components/tabs-pagination.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-wp-editor', STM_LMS_URL . 'assets/js/components/wp-editor.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-buy-button', STM_LMS_URL . 'assets/js/components/buy-button.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-dark-mode-button', STM_LMS_URL . 'assets/js/components/dark-mode-button.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-radio-buttons', STM_LMS_URL . 'assets/js/components/radio-buttons.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-message', STM_LMS_URL . 'assets/js/components/message.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-audio-player', STM_LMS_URL . 'assets/js/components/audio-player.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-video-recorder', STM_LMS_URL . 'assets/js/components/video-recorder.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-audio-recorder', STM_LMS_URL . 'assets/js/components/audio-recorder.js', array( 'jquery', 'masterstudy-lamejs' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-modals', STM_LMS_URL . 'assets/js/components/modals/modals.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-message-modal', STM_LMS_URL . 'assets/js/components/modals/message.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-membership-trigger', STM_LMS_URL . 'assets/js/components/modals/membership-trigger.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-membership-add-to-cart', STM_LMS_URL . 'assets/js/components/modals/membership-add-to-cart.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-search', STM_LMS_URL . 'assets/js/components/search.js', array(), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-sort-indicator', STM_LMS_URL . 'assets/js/components/sort-indicator.js', array(), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-attachment-media', STM_LMS_URL . 'assets/js/components/attachment-media.js', array( 'masterstudy-video-recorder', 'masterstudy-audio-recorder', 'masterstudy-audio-player' ), MS_LMS_VERSION, true );
	wp_register_script( 'ms_lms_courses_searchbox_autocomplete', STM_LMS_URL . 'assets/vendors/vue2-autocomplete.js', array( 'jquery', 'vue.js', 'vue2-autocomplete' ), MS_LMS_VERSION, true );
	wp_register_script( 'ms_lms_courses_searchbox', STM_LMS_URL . 'assets/js/elementor-widgets/course-search-box/course-search-box.js', array( 'jquery', 'vue.js' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-single-course-complete', STM_LMS_URL . 'assets/js/components/course/complete.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-single-course-stickybar', STM_LMS_URL . 'assets/js/components/course/stickybar.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-single-course-components', STM_LMS_URL . 'assets/js/components/course/main.js', array( 'jquery', 'jquery.cookie' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-file-upload', STM_LMS_URL . 'assets/js/components/file-upload.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-orders', STM_LMS_URL . 'assets/js/orders/main.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-pmpro-checkout', STM_LMS_URL . 'assets/js/pmpro-checkout.js', array( 'jquery', 'masterstudy-select2' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-course-templates', STM_LMS_URL . 'assets/js/components/course-templates.js', array( 'jquery', 'masterstudy-select2' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-pricing', STM_LMS_URL . 'assets/js/components/pricing.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-image-upload', STM_LMS_URL . 'assets/js/components/image-upload.js', array( 'jquery' ), MS_LMS_VERSION, true );
	wp_register_script( 'masterstudy-share', STM_LMS_URL . 'assets/js/components/share.js', array( 'jquery' ), MS_LMS_VERSION, true );

	/*Single Course Video*/
	wp_register_style( 'masterstudy-single-course-video-plyr', STM_LMS_URL . 'assets/css/components/course/plyr.css', null, MS_LMS_VERSION );
	wp_register_style( 'masterstudy-single-course-video', STM_LMS_URL . 'assets/css/components/course/video.css', null, MS_LMS_VERSION );
	wp_register_script( 'plyr', STM_LMS_URL . 'assets/vendors/plyr/plyr.js', array(), MS_LMS_VERSION, false );
	wp_register_script( 'masterstudy-single-course-video', STM_LMS_URL . 'assets/js/components/course/video.js', array( 'jquery', 'plyr' ), MS_LMS_VERSION, true );

	/*Single Course styles & scripts registration*/
	wp_register_style( 'masterstudy-single-course-default', STM_LMS_URL . 'assets/css/course/main.css', array(), MS_LMS_VERSION );
	wp_register_script( 'masterstudy-single-course-default', STM_LMS_URL . 'assets/js/course/main.js', array( 'jquery' ), MS_LMS_VERSION, true );

	/*Public Accounts styles & scripts registration*/
	wp_register_style( 'masterstudy-instructor-public-account', STM_LMS_URL . 'assets/css/public-accounts/instructor.css', array( 'masterstudy-course-card', 'masterstudy-pagination' ), MS_LMS_VERSION );
	wp_register_script( 'masterstudy-instructor-public-account', STM_LMS_URL . 'assets/js/public-accounts/instructor.js', array( 'jquery', 'masterstudy-api-pagination' ), MS_LMS_VERSION, true );
	wp_register_style( 'masterstudy-student-public-account', STM_LMS_URL . 'assets/css/public-accounts/student.css', array(), MS_LMS_VERSION );
	wp_register_script( 'masterstudy-student-public-account', STM_LMS_URL . 'assets/js/public-accounts/student.js', array( 'jquery', 'masterstudy-api-pagination' ), MS_LMS_VERSION, true );
	/*Analytics preview page styles & scripts*/
	wp_register_style( 'masterstudy-analytics-preview-page', STM_LMS_URL . 'assets/css/analytics-preview.css', array(), MS_LMS_VERSION );
	wp_register_script( 'masterstudy-analytics-preview-page', STM_LMS_URL . 'assets/js/analytics-preview.js', array( 'jquery' ), MS_LMS_VERSION, true );

	/*Components styles registration*/
	wp_register_style( 'masterstudy-search', STM_LMS_URL . 'assets/css/components/search.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-sort-indicator', STM_LMS_URL . 'assets/css/components/sort-indicator.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-loader', STM_LMS_URL . 'assets/css/components/loader.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-single-course-complete', STM_LMS_URL . 'assets/css/components/course/complete.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-single-course-curriculum-list', STM_LMS_URL . 'assets/css/components/course/curriculum.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-single-course-components', STM_LMS_URL . 'assets/css/components/course/main.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-course-card', STM_LMS_URL . 'assets/css/components/course/card.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-student-course-card', STM_LMS_URL . 'assets/css/components/course/student-card.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-authorization', STM_LMS_URL . 'assets/css/components/authorization.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-components-fonts', STM_LMS_URL . 'assets/css/components/fonts.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-alert', STM_LMS_URL . 'assets/css/components/alert.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-back-link', STM_LMS_URL . 'assets/css/components/back-link.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-button', STM_LMS_URL . 'assets/css/components/button.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-countdown', STM_LMS_URL . 'assets/css/components/countdown.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-curriculum-accordion', STM_LMS_URL . 'assets/css/components/curriculum-accordion.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-curriculum-list', STM_LMS_URL . 'assets/css/components/curriculum-list.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-discussions', STM_LMS_URL . 'assets/css/components/discussions.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-file-attachment', STM_LMS_URL . 'assets/css/components/file-attachment.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-file-upload', STM_LMS_URL . 'assets/css/components/file-upload.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-hint', STM_LMS_URL . 'assets/css/components/hint.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-nav-button', STM_LMS_URL . 'assets/css/components/nav-button.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-pagination', STM_LMS_URL . 'assets/css/components/pagination.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-progress', STM_LMS_URL . 'assets/css/components/progress.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-switch-button', STM_LMS_URL . 'assets/css/components/switch-button.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-tabs-pagination', STM_LMS_URL . 'assets/css/components/tabs-pagination.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-tabs', STM_LMS_URL . 'assets/css/components/tabs.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-wp-editor', STM_LMS_URL . 'assets/css/components/wp-editor.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-buy-button', STM_LMS_URL . 'assets/css/components/buy-button.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-dark-mode-button', STM_LMS_URL . 'assets/css/components/dark-mode-button.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-message', STM_LMS_URL . 'assets/css/components/message.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-radio-buttons', STM_LMS_URL . 'assets/css/components/radio-buttons.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-audio-player', STM_LMS_URL . 'assets/css/components/audio-player.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-video-player', STM_LMS_URL . 'assets/css/components/video-player.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-video-recorder', STM_LMS_URL . 'assets/css/components/video-recorder.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-audio-recorder', STM_LMS_URL . 'assets/css/components/audio-recorder.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-become-instructor-modal', STM_LMS_URL . 'assets/css/components/become-instructor-modal.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-enterprise-modal', STM_LMS_URL . 'assets/css/components/enterprise-modal.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-message-modal', STM_LMS_URL . 'assets/css/components/message-modal.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-membership-modal', STM_LMS_URL . 'assets/css/components/membership-modal.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-attachment-media', STM_LMS_URL . 'assets/css/components/attachment-media.css', array( 'masterstudy-audio-player', 'masterstudy-video-player', 'masterstudy-file-attachment' ), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-call-to-action', STM_LMS_URL . 'assets/css/elementor-widgets/call-to-action.css', array(), STM_LMS_VERSION, false );
	wp_register_style( 'masterstudy-icon-box', STM_LMS_URL . 'assets/css/elementor-widgets/icon-box.css', array(), STM_LMS_VERSION, false );
	wp_register_style( 'masterstudy-membership-levels', STM_LMS_URL . 'assets/css/elementor-widgets/membership-levels.css', array(), STM_LMS_VERSION, false );
	wp_register_style( 'masterstudy-review-card', STM_LMS_URL . 'assets/css/components/review-card.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-statistics-block', STM_LMS_URL . 'assets/css/components/statistics-block.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-stats-loader', STM_LMS_URL . 'assets/css/components/stats-loader.css', array(), MS_LMS_VERSION );
	wp_register_style( 'ms_lms_courses_searchbox', STM_LMS_URL . 'assets/css/elementor-widgets/course-search-box/course-search-box.css', array(), STM_LMS_VERSION, false );
	wp_register_style( 'profile-auth-links-style', STM_LMS_URL . 'assets/css/elementor-widgets/auth-links.css', array(), STM_LMS_VERSION, false );
	wp_register_style( 'stm_lms_icons', STM_LMS_URL . 'assets/icons/style.css', null, STM_LMS_VERSION );
	wp_register_style( 'font-awesome-min', STM_LMS_URL . 'assets/vendors/font-awesome.min.css', null, STM_LMS_VERSION, 'all' );
	wp_register_style( 'linear', STM_LMS_URL . 'libraries/nuxy/taxonomy_meta/assets/linearicons/linear.css', null, STM_LMS_VERSION, 'all' );
	wp_register_style( 'premium-templates', STM_LMS_URL . 'assets/css/parts/premium-templates/premium-templates.css', array(), MS_LMS_VERSION, 'all' );
	wp_register_style( 'masterstudy-course-templates', STM_LMS_URL . 'assets/css/components/course-templates.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-course-templates-modal', STM_LMS_URL . 'assets/css/components/course-templates-modal.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-separator', STM_LMS_URL . 'assets/css/components/separator.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-image-upload', STM_LMS_URL . 'assets/css/components/image-upload.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-pricing', STM_LMS_URL . 'assets/css/components/pricing.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-switcher', STM_LMS_URL . 'assets/css/components/switcher.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-share', STM_LMS_URL . 'assets/css/components/share.css', array(), MS_LMS_VERSION );
	wp_register_style( 'masterstudy-public-page-block', STM_LMS_URL . 'assets/css/components/public-page-block.css', array(), MS_LMS_VERSION );

	masterstudy_enqueue_students_page( $hook_suffix );
}

function masterstudy_enqueue_students_page( string $hook_suffix ) {
	$lms_template          = get_query_var( 'lms_template' );
	$is_student_admin_page = ( 'masterstudy_page_manage_students' === $hook_suffix );
	$is_student_list       = ( 'stm-lms-enrolled-students' === $lms_template );
	$is_student_item       = ( 'stm-lms-enrolled-student' === $lms_template );

	if ( $is_student_admin_page || $is_student_list ) {
		$date_scripts_styles = masterstudy_datepicker();

		if ( ! empty( $date_scripts_styles ) ) {
			foreach ( $date_scripts_styles as $handle ) {
				if ( wp_script_is( $handle, 'registered' ) ) {
					wp_enqueue_script( $handle );
				}

				if ( wp_style_is( $handle, 'registered' ) ) {
					wp_enqueue_style( $handle );
				}
			}
		}

		wp_enqueue_style( 'linear' );
		wp_enqueue_style( 'masterstudy-list-students' );
		wp_enqueue_style( 'masterstudy-pagination' );
		wp_enqueue_script( 'masterstudy-select2' );
		wp_enqueue_style( 'masterstudy-select2' );
		wp_enqueue_style( 'font-awesome-min' );
		wp_dequeue_style( 'font-awesome' );

		if ( stm_lms_has_custom_colors() ) {
			wp_enqueue_style( 'masterstudy-lms-learning-management-system', stm_lms_custom_styles_url() . '/stm_lms_styles/stm_lms.css', array(), stm_lms_custom_styles_v() );
		} else {
			wp_enqueue_style( 'masterstudy-lms-learning-management-system', STM_LMS_URL . 'assets/css/stm_lms.css', array(), MS_LMS_VERSION );
		}
	}

	if ( $is_student_admin_page || $is_student_list || $is_student_item ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$student_id = absint( is_admin() ? ( isset( $_GET['user_id'] ) ? wp_unslash( $_GET['user_id'] ) : 0 ) : get_query_var( 'student_id' ) );

		if ( empty( $student_id ) ) {
			wp_localize_script(
				'masterstudy-list-students',
				'stats_data',
				array(
					'custom_period'    => __( 'Date range', 'masterstudy-lms-learning-management-system' ),
					'user_account_url' => STM_LMS_User::login_page_url(),
					'is_students'      => $is_student_admin_page || $is_student_list,
					'is_student'       => $is_student_item,
					'locale'           => masterstudy_get_locale_info(),
					'is_admin'         => is_admin(),
				)
			);

			wp_enqueue_script( 'masterstudy-list-students' );
		}

		wp_add_inline_script(
			'masterstudy-list-students',
			"const defaultDateRanges = getDefaultDateRanges();
					const currentUrl = window.location.href;
					const userAccountDashboardPage = currentUrl === stats_data.user_account_url;
					let storedPeriodKey = localStorage.getItem( 'StudentsListSelectedPeriodKey' );
					let selectedPeriod;

					if ( storedPeriodKey && defaultDateRanges[ storedPeriodKey ] && !userAccountDashboardPage ) {
						selectedPeriod = defaultDateRanges[ storedPeriodKey ];
					} else {
						const defaultDateRange = typeof customDateRange != 'undefined' ? customDateRange : defaultDateRanges.this_month;
						const lmsDateRange = userAccountDashboardPage ? defaultDateRanges.all_time : defaultDateRange;
						const storedPeriod = !userAccountDashboardPage ? localStorage.getItem( 'StudentsListSelectedPeriod' ) : null;
						selectedPeriod = storedPeriod ? JSON.parse( storedPeriod ) : lmsDateRange;
					}"
		);
	}
}

function stm_lms_enqueue_vss() {
	if ( apply_filters( 'stm_lms_enqueue_bootstrap', true ) ) {
		wp_enqueue_style( 'masterstudy-bootstrap', STM_LMS_URL . 'assets/vendors/bootstrap.min.css', array(), MS_LMS_VERSION, 'all' );
		wp_enqueue_style( 'masterstudy-bootstrap-custom', STM_LMS_URL . 'assets/vendors/ms-bootstrap-custom.css', array(), MS_LMS_VERSION, 'all' );

		wp_enqueue_script( 'masterstudy-bootstrap', STM_LMS_URL . 'assets/vendors/bootstrap.min.js', array( 'jquery' ), MS_LMS_VERSION, true );
		wp_enqueue_script( 'masterstudy-bootstrap-custom', STM_LMS_URL . 'assets/vendors/ms-bootstrap-custom.js', array( 'jquery' ), MS_LMS_VERSION, true );
	}
}

add_action( 'wp_enqueue_scripts', 'stm_lms_enqueue_ss' );
add_action( 'wp_enqueue_scripts', 'stm_lms_enqueue_vss', 1 );
add_action( 'wp_enqueue_scripts', 'stm_lms_enqueue_component_scripts' );
add_action( 'admin_enqueue_scripts', 'stm_lms_enqueue_component_scripts' );

add_action( 'admin_head', 'stm_lms_nonces' );
add_action( 'wp_head', 'stm_lms_nonces' );

function stm_lms_nonces() {
	$nonces = array(
		'stm_install_starter_theme',
		'load_modal',
		'load_content',
		'start_quiz',
		'user_answers',
		'get_order_info',
		'user_orders',
		'stm_lms_get_instructor_courses',
		'stm_lms_add_comment',
		'stm_lms_manage_students',
		'stm_lms_get_comments',
		'stm_lms_login',
		'stm_lms_register',
		'stm_lms_become_instructor',
		'stm_lms_enterprise',
		'stm_lms_get_user_courses',
		'stm_lms_get_user_quizzes',
		'stm_lms_wishlist',
		'stm_lms_save_user_info',
		'stm_lms_lost_password',
		'stm_lms_change_avatar',
		'stm_lms_delete_avatar',
		'stm_lms_complete_lesson',
		'stm_lms_use_membership',
		'stm_lms_change_featured',
		'stm_lms_delete_course_subscription',
		'stm_lms_get_reviews',
		'stm_lms_add_review',
		'stm_lms_add_to_cart',
		'stm_lms_delete_from_cart',
		'stm_lms_purchase',
		'stm_lms_send_message',
		'stm_lms_get_user_conversations',
		'stm_lms_get_user_messages',
		'stm_lms_clear_new_messages',
		'wpcfto_save_settings',
		'stm_lms_tables_update',
		'stm_lms_get_enterprise_groups',
		'stm_lms_get_enterprise_group',
		'stm_lms_add_enterprise_group',
		'stm_lms_delete_enterprise_group',
		'stm_lms_add_to_cart_enterprise',
		'stm_lms_get_user_ent_courses',
		'stm_lms_delete_user_ent_courses',
		'stm_lms_add_user_ent_courses',
		'stm_lms_change_ent_group_admin',
		'stm_lms_delete_user_from_group',
		'stm_lms_import_groups',
		'stm_lms_edit_user_answer',
		'stm_lms_get_user_points_history',
		'stm_lms_buy_for_points',
		'stm_lms_get_point_users',
		'stm_lms_get_user_points_history_admin',
		'stm_lms_change_points',
		'stm_lms_delete_points',
		'stm_lms_get_user_bundles',
		'stm_lms_change_bundle_status',
		'stm_lms_delete_bundle',
		'stm_lms_check_certificate_code',
		'stm_lms_get_google_classroom_courses',
		'stm_lms_get_google_classroom_course',
		'stm_lms_get_google_classroom_publish_course',
		'stm_lms_get_g_c_get_archive_page',
		'install_zoom_addon',
		'stm_lms_get_course_cookie_redirect',
		'stm_get_certificates',
		'stm_get_certificate_fields',
		'stm_save_certificate',
		'stm_upload_certificate_images',
		'stm_generate_certificates_preview',
		'stm_save_default_certificate',
		'stm_delete_default_certificate',
		'stm_save_certificate_category',
		'stm_delete_certificate_category',
		'stm_get_certificate_categories',
		'stm_get_certificate',
		'stm_delete_certificate',
		'stm_lms_get_users_submissions',
		'stm_lms_update_user_status',
		'stm_lms_hide_become_instructor_notice',
		'stm_lms_ban_user',
		'stm_lms_save_forms',
		'stm_lms_get_forms',
		'stm_lms_upload_form_file',
		'stm_lms_dashboard_get_course_students',
		'stm_lms_dashboard_delete_user_from_course',
		'stm_lms_dashboard_add_user_to_course',
		'stm_lms_dashboard_import_users_to_course',
		'stm_lms_dashboard_export_course_students_to_csv',
		'stm_lms_add_to_cart_guest',
		'stm_lms_fast_login',
		'stm_lms_fast_register',
		'stm_lms_change_lms_author',
		'stm_lms_add_student_manually',
		'stm_lms_change_course_status',
		'stm_lms_total_progress',
		'stm_lms_add_h5p_result',
		'stm_lms_toggle_buying',
		'stm_lms_logout',
		'stm_lms_restore_password',
		'stm_lms_hide_announcement',
		'stm_lms_get_curriculum_v2',
		'stm_lms_dashboard_get_student_progress',
		'stm_lms_dashboard_set_student_item_progress',
		'stm_lms_dashboard_reset_student_progress',
		'stm_lms_dashboard_get_courses_list',
		'stm_lms_dashboard_get_student_assignments',
		'stm_lms_dashboard_get_student_quizzes',
		'stm_lms_dashboard_get_student_quiz',
		'stm_lms_wizard_save_settings',
		'stm_lms_wizard_save_business_type',
		'stm_lms_get_enrolled_assingments',
		'stm-lms-starter-theme-install',
		'stm_lms_enrolled_quizzes',
	);

	$nonces_list = array();

	foreach ( $nonces as $nonce_name ) {
		$nonces_list[ $nonce_name ] = wp_create_nonce( $nonce_name );
	}

	?>
	<script>
		var stm_lms_nonces = <?php echo wp_json_encode( $nonces_list ); ?>;
	</script>
	<?php
}

function masterstudy_datepicker(): array {
	$locale  = masterstudy_get_locale_info();
	$handles = array(
		'masterstudy-date-helpers',
		'masterstudy-loaders-helpers',
		'masterstudy-datatables-library',
		'masterstudy-datatables-helpers',
		'masterstudy-datatables',
		'masterstudy-date-field',
		'masterstudy-datepicker-library',
		'masterstudy-datepicker-locale',
		'masterstudy-datepicker-helpers',
		'masterstudy-datepicker',
	);

	wp_register_script( 'masterstudy-date-helpers', STM_LMS_URL . 'assets/js/analytics/helpers/date.js', array( 'jquery' ), STM_LMS_VERSION, true );
	wp_register_script( 'masterstudy-loaders-helpers', STM_LMS_URL . 'assets/js/analytics/helpers/loaders.js', array( 'jquery' ), STM_LMS_VERSION, true );
	wp_register_script( 'masterstudy-datatables-library', STM_LMS_URL . 'assets/vendors/datatables.min.js', array( 'jquery' ), STM_LMS_VERSION, true );
	wp_register_script( 'masterstudy-datatables-helpers', STM_LMS_URL . 'assets/js/analytics/helpers/datatables.js', array( 'jquery', 'masterstudy-datatables-library' ), STM_LMS_VERSION, true );

	wp_register_style( 'masterstudy-datatables-library', STM_LMS_URL . 'assets/vendors/datatables.min.css', null, STM_LMS_VERSION );
	wp_register_style( 'masterstudy-datatables', STM_LMS_URL . 'assets/css/components/analytics/datatables.css', null, STM_LMS_VERSION );
	wp_register_style( 'masterstudy-date-field', STM_LMS_URL . 'assets/css/components/analytics/date-field.css', null, STM_LMS_VERSION );
	wp_register_style( 'masterstudy-datepicker-library', STM_LMS_URL . 'assets/vendors/flatpickr.min.css', null, STM_LMS_VERSION );
	wp_register_style( 'masterstudy-datepicker', STM_LMS_URL . 'assets/css/components/analytics/datepicker.css', null, STM_LMS_VERSION );

	wp_register_script( 'masterstudy-datepicker-library', STM_LMS_URL . 'assets/vendors/flatpickr.min.js', array( 'jquery' ), STM_LMS_VERSION, true );
	wp_register_script( 'masterstudy-datepicker-locale', "https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/{$locale['current_locale']}.js", array( 'masterstudy-datepicker-library' ), STM_LMS_VERSION, true );
	wp_register_script( 'masterstudy-datepicker-helpers', STM_LMS_URL . 'assets/js/analytics/helpers/datepicker.js', array( 'jquery', 'masterstudy-datepicker-library', 'masterstudy-datepicker-locale' ), STM_LMS_VERSION, true );

	wp_localize_script(
		'masterstudy-datatables-helpers',
		'table_data',
		array(
			'per_page_placeholder' => __( 'per page', 'masterstudy-lms-learning-management-system' ),
			'not_found'            => __( 'No matching items found', 'masterstudy-lms-learning-management-system' ),
			'not_available'        => __( 'No data to display', 'masterstudy-lms-learning-management-system' ),
			'not_started_lesson'   => __( 'Not Started', 'masterstudy-lms-learning-management-system' ),
			'failed_lesson'        => __( 'Failed', 'masterstudy-lms-learning-management-system' ),
			'completed_lesson'     => __( 'Complete', 'masterstudy-lms-learning-management-system' ),
			'progress_lesson'      => __( 'In progress', 'masterstudy-lms-learning-management-system' ),
			'img_route'            => STM_LMS_URL,
		)
	);
	return $handles;
}

if ( ! function_exists( 'masterstudy_get_locale_info' ) ) {
	function masterstudy_get_locale_info(): array {
		$locale         = get_locale();
		$current_locale = 'en';
		$locales        = array(
			'en_US' => 'en',
			'en_GB' => 'en',
			'ru_RU' => 'ru',
			'ja'    => 'ja',
			'zh_CN' => 'zh',
			'de_DE' => 'de',
			'it_IT' => 'it',
			'fr_FR' => 'fr',
			'es_ES' => 'es',
		);

		if ( array_key_exists( $locale, $locales ) ) {
			$current_locale = $locales[ $locale ];
		}

		return array(
			'current_locale' => $current_locale,
			'firstDayOfWeek' => get_option( 'start_of_week', 0 ),
		);
	}
}
