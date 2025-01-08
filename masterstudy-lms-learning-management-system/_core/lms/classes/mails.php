<?php
STM_LMS_Mails::init();

class STM_LMS_Mails {

	public static function init() {
		add_action( 'masterstudy_lms_order_completed', array( self::class, 'masterstudy_lms_order_completed' ), 10, 4 );
		add_action( 'add_user_course', array( self::class, 'add_user_course' ), 10, 2 );
		add_action( 'masterstudy_lms_course_saved', array( self::class, 'course_saved' ), 10, 2 );
	}

	public static function wp_mail_html() {
		return 'text/html';
	}

	public static function send_email_to_instructor( $cart_items, $user_login, $order_id, $settings, $template_name, $send_test_mode = false ) {
		$instructor_items  = array();
		$instructor_emails = array();

		if ( $send_test_mode ) {
			self::process_instructor_order_mail( $template_name, $user_login, $order_id, array(), get_option( 'admin_email' ), $settings, $send_test_mode );
			die;
		}

		if ( ! empty( $cart_items ) ) {
			foreach ( $cart_items as $item ) {
				$item_id = $item['item_id'];
				$post    = get_post( $item_id );

				if ( $post ) {
					$author_id = $post->post_author;
					$author    = get_userdata( $author_id );

					if ( in_array( 'stm_lms_instructor', $author->roles ) ) {
						$instructor_email = $author->user_email;
						if ( ! in_array( $instructor_email, $instructor_emails ) ) {
							$instructor_emails[] = $instructor_email;
						}
						$instructor_items[ $instructor_email ][] = $item;
					}
				}
			}
		}
		foreach ( $instructor_emails as $email ) {
			if ( ! empty( $instructor_items[ $email ] ) ) {
				self::process_instructor_order_mail( $template_name, $user_login, $order_id, $instructor_items[ $email ], $email, $settings, $send_test_mode );
			}
		}
	}

	public static function process_instructor_order_mail( $template_name, $user_login, $order_id, $instructor_emails, $email, $settings, $send_test_mode ) {
		$message = str_replace(
			array( '{{user_login}}', '{{site_url}}' ),
			array( $user_login, '<a href="' . site_url() . '" target="_blank">' . get_bloginfo( 'name' ) . '</a>' ),
			$settings['stm_lms_new_order_instructor'] ?? 'You made a Sale'
		);

		$context = \STM_LMS_Templates::load_lms_template(
			$template_name,
			array(
				'send_test_mode'   => $send_test_mode,
				'order_id'         => $order_id,
				'message'          => $message,
				'is_instructor'    => true,
				'settings'         => $settings,
				'instructor_items' => $instructor_emails, // Items specific to the instructor
				'title'            => $settings['stm_lms_new_order_instructor_title'] ?? '',
				'customer_section' => true,
			)
		);

		add_filter( 'wp_mail_content_type', 'STM_LMS_Helpers::set_html_content_type' );
		wp_mail( $email, $settings['stm_lms_new_order_instructor_subject'] ?? 'You made a Sale!', $context );
		remove_filter( 'wp_mail_content_type', 'STM_LMS_Helpers::set_html_content_type' );
	}

	public static function send_email_to_admin( $user_login, $order_id, $settings, $template_name, $send_test_mode = false ) {
		$message = str_replace(
			array( '{{user_login}}', '{{site_url}}' ),
			array( $user_login, '<a href="' . site_url() . '" target="_blank">' . get_bloginfo( 'name' ) . '</a>' ),
			$settings['stm_lms_new_order'] ?? 'New Order'
		);
		$context = \STM_LMS_Templates::load_lms_template(
			$template_name,
			array(
				'send_test_mode'   => $send_test_mode,
				'order_id'         => $order_id,
				'message'          => $message,
				'instructor_items' => array(),
				'settings'         => $settings,
				'is_instructor'    => false,
				'title'            => $settings['stm_lms_new_order_title'] ?? '',
				'customer_section' => true,
			)
		);

		// Assume admin email is set in settings
		$admin_email = $settings['admin_email'] ?? get_option( 'admin_email' );
		add_filter( 'wp_mail_content_type', 'STM_LMS_Helpers::set_html_content_type' );
		wp_mail( $admin_email, $settings['stm_lms_new_order_subject'] ?? 'New Order Received', $context );
		remove_filter( 'wp_mail_content_type', 'STM_LMS_Helpers::set_html_content_type' );
	}

	public static function send_email_to_student( $user, $order_id, $settings, $template_name, $send_test_mode = false ) {
		$user_value = $send_test_mode ? $user : $user['login'];
		$user_email = $send_test_mode ? $user : $user['email'];
		$message    = str_replace(
			array( '{{user_login}}', '{{site_url}}' ),
			array(
				$user_value,
				'<a href="' . site_url() . '" target="_blank">' . get_bloginfo( 'name' ) . '</a>',
			),
			$settings['stm_lms_new_order_accepted'] ?? 'Your Order has been Accepted.'
		);

		$context = \STM_LMS_Templates::load_lms_template(
			$template_name,
			array(
				'send_test_mode'   => $send_test_mode,
				'order_id'         => $order_id,
				'instructor_items' => array(),
				'is_instructor'    => false,
				'settings'         => $settings,
				'message'          => $message,
				'title'            => $settings['stm_lms_new_order_accepted_title'] ?? 'Order Confirmation',
				'customer_section' => false,
			)
		);

		add_filter( 'wp_mail_content_type', 'STM_LMS_Helpers::set_html_content_type' );
		wp_mail( $user_email, $settings['stm_lms_new_order_accepted_subject'] ?? 'Order Accepted', $context );
		remove_filter( 'wp_mail_content_type', 'STM_LMS_Helpers::set_html_content_type' );
	}

	public static function masterstudy_lms_order_completed( $user, $cart_items, $payment_code, $order_id ) {
		$settings      = class_exists( 'STM_LMS_Email_Manager' ) ? STM_LMS_Email_Manager::stm_lms_get_settings() : array();
		$template_name = 'emails/order-template';

		if ( STM_LMS_Helpers::is_pro_plus() && is_ms_lms_addon_enabled( 'email_manager' ) ) {
			$template_name = 'emails/order-template-plus';
		}

		$user       = STM_LMS_User::get_current_user( $user );
		$user_login = $user['login'];

		$send_admin      = $settings['stm_lms_new_order_enable'] ?? true;
		$send_instructor = $settings['stm_lms_new_order_instructor_enable'] ?? true;
		$send_student    = $settings['stm_lms_new_order_accepted_enable'] ?? true;

		if ( $send_instructor ) {
			self::send_email_to_instructor( $cart_items, $user_login, $order_id, $settings, $template_name );
		}

		if ( $send_admin ) {
			self::send_email_to_admin( $user_login, $order_id, $settings, $template_name );
		}

		if ( $send_student ) {
			self::send_email_to_student( $user, $order_id, $settings, $template_name );
		}

	}

	public static function add_user_course( $user_id, $course_id ) {
		$user    = STM_LMS_User::get_current_user( $user_id );
		$authors = array();

		if ( ! empty( $course_id ) ) {
			$post_author   = get_post_field( 'post_author', $course_id );
			$co_instructor = get_post_meta( $course_id, 'co_instructor', true );

			if ( ! empty( $post_author ) ) {
				$post_author_info = get_userdata( $post_author );

				if ( ! empty( $co_instructor_info->user_email ) ) {
					$authors[] = $post_author_info->user_email;
				}
			}

			if ( ! empty( $co_instructor ) ) {
				$co_instructor_info = get_userdata( $co_instructor );

				if ( ! empty( $co_instructor_info->user_email ) ) {
					$authors[] = $co_instructor_info->user_email;
				}
			}
		}

		$course_title = get_the_title( $course_id );
		$login        = $user['login'];
		$message      = sprintf(
			/* translators: %1$s Course Title, %2$s User Login */
			esc_html__( 'Course %1$s was added to %2$s.', 'masterstudy-lms-learning-management-system' ),
			$course_title,
			$login
		);

		if ( apply_filters( 'stm_lms_send_admin_course_notice', true ) ) {
			self::send_email( 'Course added to User', $message, '', $authors, 'stm_lms_course_added_to_user', compact( 'course_title', 'login' ) );
		}

		$message = sprintf(
			/* translators: %s Course Title */
			esc_html__( 'Course %s is now available to learn.', 'masterstudy-lms-learning-management-system' ),
			$course_title
		);

		self::send_email( 'Course added.', $message, $user['email'], array(), 'stm_lms_course_available_for_user', compact( 'course_title' ) );
	}

	public static function course_saved( $post_id, $course ) {
		$action  = ! empty( $course['id'] )
			? esc_html__( 'updated', 'masterstudy-lms-learning-management-system' )
			: esc_html__( 'created', 'masterstudy-lms-learning-management-system' );
		$title   = $course['title'] ?? get_the_title( $post_id );
		$user    = STM_LMS_User::get_current_user();
		$message = sprintf(
			/* translators: %s: course info */
			esc_html__( 'Course %1$s %2$s by instructor (%3$s). Please review this information from the Dashboard.', 'masterstudy-lms-learning-management-system' ),
			$title,
			$action,
			$user['login']
		);

		self::send_email(
			esc_html__( 'Course added/updated', 'masterstudy-lms-learning-management-system' ),
			$message,
			get_option( 'admin_email' ),
			array(),
			'stm_lms_course_added',
			array(
				'course_title' => $title,
				'user_login'   => $user['login'],
				'action'       => $action,
			)
		);
	}

	public static function send_email( $subject, $message, $to = '', $additional_receivers = array(), $filter = 'stm_lms_send_email_filter', $data = array() ) {
		$to        = ( ! empty( $to ) ) ? $to : get_option( 'admin_email' );
		$receivers = array_unique( array_merge( array( $to ), $additional_receivers ) );

		add_filter( 'wp_mail_content_type', array( self::class, 'wp_mail_html' ) );

		$data = apply_filters(
			'stm_lms_filter_email_data',
			array(
				'subject'     => $subject,
				'message'     => $message,
				'vars'        => $data,
				'filter_name' => $filter,
			)
		);

		if ( class_exists( 'STM_LMS_Email_Manager' ) ) {
			$email_manager = STM_LMS_Email_Manager::stm_lms_get_settings();

			add_filter(
				'wp_mail_from',
				function ( $from_email ) use ( $email_manager ) {
					return $email_manager['stm_lms_email_template_header_email'] ?? $from_email;
				}
			);
		}

		if ( ! isset( $data['enabled'] ) || ( isset( $data['enabled'] ) && $data['enabled'] ) ) {
			wp_mail( $receivers, $data['subject'], $data['message'] );
		}

		remove_filter( 'wp_mail_content_type', array( self::class, 'wp_mail_html' ) );
	}

}
