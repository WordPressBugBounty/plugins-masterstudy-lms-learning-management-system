<?php
function stm_lms_settings_general_section() {
	return array(
		'name'   => esc_html__( 'General', 'masterstudy-lms-learning-management-system' ),
		'label'  => esc_html__( 'General Settings', 'masterstudy-lms-learning-management-system' ),
		'icon'   => 'fas fa-sliders-h',
		'fields' => array(
			/*GROUP STARTED*/
			'main_color'                     => array(
				'group'       => 'started',
				'type'        => 'color',
				'label'       => esc_html__( 'Main color', 'masterstudy-lms-learning-management-system' ),
				'description' => esc_html__( 'Set the core website color', 'masterstudy-lms-learning-management-system' ),
				'columns'     => '33',
				'group_title' => esc_html__( 'Colors', 'masterstudy-lms-learning-management-system' ),
			),
			'secondary_color'                => array(
				'group'       => 'ended',
				'type'        => 'color',
				'label'       => esc_html__( 'Secondary color', 'masterstudy-lms-learning-management-system' ),
				'description' => esc_html__( 'Set the secondary color for the site', 'masterstudy-lms-learning-management-system' ),
				'columns'     => '33',
			),
			/*GROUP ENDED*/

			/*GROUP STARTED*/
			'accent_color'                   => array(
				'group'       => 'started',
				'type'        => 'color',
				'value'       => 'rgba(34,122,255,1)',
				'label'       => esc_html__( 'Accent', 'masterstudy-lms-learning-management-system' ),
				'description' => esc_html__( 'Pick a color for buttons, quiz info, chosen options, progress bar, notice background, links, and Trial course badge in the Course Player', 'masterstudy-lms-learning-management-system' ),
				'columns'     => '33',
				'group_title' => esc_html__( 'Base colors*', 'masterstudy-lms-learning-management-system' ),
			),
			'danger_color'                   => array(
				'type'        => 'color',
				'value'       => 'rgba(255,57,69,1)',
				'label'       => esc_html__( 'Danger', 'masterstudy-lms-learning-management-system' ),
				'description' => esc_html__( 'Select a color for required but unfilled fields, wrong options chosen in quizzes, and notifications for failed quizzes and assignments', 'masterstudy-lms-learning-management-system' ),
				'columns'     => '33',
			),
			'warning_color'                  => array(
				'type'        => 'color',
				'value'       => 'rgba(255,168,0,1)',
				'label'       => esc_html__( 'Warning', 'masterstudy-lms-learning-management-system' ),
				'description' => esc_html__( 'Choose a color for warnings', 'masterstudy-lms-learning-management-system' ),
				'columns'     => '33',
			),
			'success_color'                  => array(
				'type'        => 'color',
				'value'       => 'rgba(97,204,47,1)',
				'label'       => esc_html__( 'Success', 'masterstudy-lms-learning-management-system' ),
				'description' => esc_html__( 'Choose a color for wrong options chosen in quizzes, and notifications for passed quizzes and assignments', 'masterstudy-lms-learning-management-system' ),
				'columns'     => '33',
			),
			'base_colors_info'               => array(
				'group'       => 'ended',
				'type'        => 'notification_message',
				'description' => esc_html__( '* These colors will be applied to Course Player pages, Authorization pages and popups. In future updates, they will be applied to all pages and Accent color will replace Main color.', 'masterstudy-lms-learning-management-system' ),
			),
			/*GROUP ENDED*/

			/*GROUP STARTED*/
			'currency_symbol'                => array(
				'group'       => 'started',
				'type'        => 'text',
				'label'       => esc_html__( 'Currency symbol', 'masterstudy-lms-learning-management-system' ),
				'columns'     => '50',
				'group_title' => esc_html__( 'Currency', 'masterstudy-lms-learning-management-system' ),
				'description' => esc_html__( 'The symbol for money that shows up on your site (like $ for dollars)', 'masterstudy-lms-learning-management-system' ),
			),
			'currency_position'              => array(
				'type'        => 'select',
				'label'       => esc_html__( 'Currency position', 'masterstudy-lms-learning-management-system' ),
				'value'       => 'left',
				'options'     => array(
					'left'  => esc_html__( 'Left', 'masterstudy-lms-learning-management-system' ),
					'right' => esc_html__( 'Right', 'masterstudy-lms-learning-management-system' ),
				),
				'columns'     => '50',
				'description' => esc_html__( 'Decide if the money symbol goes before or after the number', 'masterstudy-lms-learning-management-system' ),
			),
			'currency_thousands'             => array(
				'type'        => 'text',
				'label'       => esc_html__( 'Thousands separator', 'masterstudy-lms-learning-management-system' ),
				'value'       => ',',
				'columns'     => '33',
				'description' => esc_html__( 'The symbol to split large numbers into groups, like 1,000', 'masterstudy-lms-learning-management-system' ),
			),
			'currency_decimals'              => array(
				'type'        => 'text',
				'label'       => esc_html__( 'Decimals separator', 'masterstudy-lms-learning-management-system' ),
				'value'       => '.',
				'columns'     => '33',
				'description' => esc_html__( 'The symbol to show the decimal point, like 12.45', 'masterstudy-lms-learning-management-system' ),
			),
			'decimals_num'                   => array(
				'group'       => 'ended',
				'type'        => 'number',
				'label'       => esc_html__( 'Number of fractional numbers allowed', 'masterstudy-lms-learning-management-system' ),
				'value'       => 2,
				'columns'     => '33',
				'description' => esc_html__( 'Define how many numbers can be after the decimal point, like 2 in 7.49', 'masterstudy-lms-learning-management-system' ),
			),
			/*GROUP ENDED*/
			'wocommerce_checkout'            => array(
				'type'    => 'checkbox',
				'label'   => esc_html__( 'WooCommerce Checkout', 'masterstudy-lms-learning-management-system' ),
				'hint'    => esc_html__( 'Turn this on to use WooCommerce to buy courses. You need to have WooCommerce, Cart and Checkout Pages set up first', 'masterstudy-lms-learning-management-system' ),
				'pro'     => true,
				'pro_url' => admin_url( 'admin.php?page=stm-lms-go-pro&source=wocommerce-checkout-settings' ),
			),
			'guest_checkout'                 => array(
				'type'        => 'checkbox',
				'label'       => esc_html__( 'Guest checkout', 'masterstudy-lms-learning-management-system' ),
				'description' => esc_html__( 'Enable guest checkout in WooCommerce to let students buy courses', 'masterstudy-lms-learning-management-system' ),
			),
			'pro_banner_woo'                 => array(
				'type'  => 'pro_banner',
				'label' => esc_html__( 'Woocommerce Checkout', 'masterstudy-lms-learning-management-system' ),
				'img'   => STM_LMS_URL . 'assets/img/pro-features/woocommerce-checkout.png',
				'desc'  => esc_html__( 'Upgrade to Pro now and streamline your checkout process to boost your online course sales.', 'masterstudy-lms-learning-management-system' ),
				'value' => STM_LMS_Helpers::is_pro() ? '' : 'pro_banner',
			),
			'guest_checkout_notice'          => array(
				'type'         => 'notice_banner',
				'label'        => esc_html__( 'Required to enable guest checkout in WooCommerce', 'masterstudy-lms-learning-management-system' ),
				'dependency'   => array(
					array(
						'key'   => 'wocommerce_checkout',
						'value' => 'not_empty',
					),
					array(
						'key'   => 'guest_checkout',
						'value' => 'not_empty',
					),
				),
				'dependencies' => '&&',
			),
			'redirect_after_purchase'        => array(
				'type'        => 'checkbox',
				'label'       => esc_html__( 'Redirect to checkout after adding to cart', 'masterstudy-lms-learning-management-system' ),
				'description' => esc_html__( 'The feature is not available when WooCommerce checkout is enabled', 'masterstudy-lms-learning-management-system' ),
			),
			'redirect_after_purchase_notice' => array(
				'type'       => 'notice_banner',
				'label'      => esc_html__( 'The feature is not available when WooCommerce checkout is enabled', 'masterstudy-lms-learning-management-system' ),
				'dependency' => array(
					'key'   => 'wocommerce_checkout',
					'value' => 'not_empty',
				),
			),
			'author_fee'                     => array(
				'type'        => 'number',
				'label'       => esc_html__( 'Instructor earnings (%)', 'masterstudy-lms-learning-management-system' ),
				'value'       => '10',
				'pro'         => true,
				'pro_url'     => admin_url( 'admin.php?page=stm-lms-go-pro&source=instructor-earnings-settings' ),
				'description' => esc_html__( 'Put the percentage instructors will get from sales', 'masterstudy-lms-learning-management-system' ),
			),
			'courses_featured_num'           => array(
				'type'        => 'number',
				'label'       => esc_html__( 'Number of featured courses', 'masterstudy-lms-learning-management-system' ),
				'description' => esc_html__( 'Define how many courses you want to highlight as special', 'masterstudy-lms-learning-management-system' ),
				'value'       => 1,
				'pro'         => true,
				'pro_url'     => admin_url( 'admin.php?page=stm-lms-go-pro' ),
			),
			'deny_instructor_admin'          => array(
				'type'        => 'checkbox',
				'label'       => esc_html__( 'Restrict instructors from accessing the admin panel', 'masterstudy-lms-learning-management-system' ),
				'description' => esc_html__( 'Enable this to prevent instructors from using the admin panel. They will be redirected to their account pages', 'masterstudy-lms-learning-management-system' ),
			),
			'ms_plugin_preloader'            => array(
				'type'        => 'checkbox',
				'label'       => esc_html__( 'Loading animation', 'masterstudy-lms-learning-management-system' ),
				'description' => esc_html__( 'An animation that shows when something is loading', 'masterstudy-lms-learning-management-system' ),
				'value'       => false,
			),
		),
	);
}
