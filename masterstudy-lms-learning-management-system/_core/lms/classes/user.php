<?php
use MasterStudy\Lms\Plugin\PostType;

STM_LMS_User::init();

class STM_LMS_User {

	public static function init() {
		$instance = new self();

		add_action( 'wp_ajax_stm_lms_login', 'STM_LMS_User::stm_lms_login' );
		add_action( 'wp_ajax_nopriv_stm_lms_login', 'STM_LMS_User::stm_lms_login' );
		add_action( 'wp_ajax_stm_lms_logout', 'STM_LMS_User::stm_lms_logout' );

		add_action( 'wp_ajax_stm_lms_register', 'STM_LMS_User::stm_lms_register' );
		add_action( 'wp_ajax_nopriv_stm_lms_register', 'STM_LMS_User::stm_lms_register' );

		add_action( 'wp_ajax_stm_lms_become_instructor', 'STM_LMS_User::apply_for_instructor' );

		add_action( 'wp_ajax_stm_lms_enterprise', 'STM_LMS_User::enterprise' );
		add_action( 'wp_ajax_nopriv_stm_lms_enterprise', 'STM_LMS_User::enterprise' );

		add_action( 'wp_ajax_stm_lms_get_user_courses', 'STM_LMS_User::get_user_courses' );

		add_action( 'wp_ajax_stm_lms_wishlist', 'STM_LMS_User::wishlist' );

		add_action( 'wsl_hook_process_login_before_wp_safe_redirect', 'STM_LMS_User::wsl_new_register_redirect_url', 100, 4 );

		add_action( 'wp_login', 'STM_LMS_User::user_logged_in', 100, 2 );

		add_action( 'show_user_profile', 'STM_LMS_User::extra_fields_display' );
		add_action( 'edit_user_profile', 'STM_LMS_User::extra_fields_display' );

		add_action( 'personal_options_update', 'STM_LMS_User::save_extra_fields' );
		add_action( 'edit_user_profile_update', 'STM_LMS_User::save_extra_fields' );
		add_action( 'user_register', 'STM_LMS_User::stm_lms_save_sum_rating_on_register' );

		add_action( 'wp_ajax_stm_lms_save_user_info', 'STM_LMS_User::save_user_info' );

		add_action( 'wp_ajax_stm_lms_lost_password', 'STM_LMS_User::stm_lms_lost_password' );
		add_action( 'wp_ajax_nopriv_stm_lms_lost_password', 'STM_LMS_User::stm_lms_lost_password' );

		add_action( 'wp_ajax_stm_lms_change_avatar', 'STM_LMS_User::stm_lms_change_avatar' );
		add_action( 'wp_ajax_stm_lms_delete_avatar', 'STM_LMS_User::stm_lms_delete_avatar' );

		add_action( 'wp_ajax_stm_lms_change_cover', array( $instance, 'stm_lms_change_cover' ) );
		add_action( 'wp_ajax_stm_lms_delete_cover', array( $instance, 'stm_lms_delete_cover' ) );

		add_action( 'wp_ajax_stm_lms_hide_become_instructor_notice', 'STM_LMS_User::hide_become_instructor_notice' );

		add_action( 'stm_lms_redirect_user', 'STM_LMS_User::redirect' );

		if ( ! empty( $_GET['user_token'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_action( 'init', 'STM_LMS_User::verify_user' );
		}

		add_action( 'after_setup_theme', 'STM_LMS_User::remove_admin_bar' );

		add_action( 'wp_ajax_stm_lms_restore_password', 'STM_LMS_User::stm_lms_restore_password' );
		add_action( 'wp_ajax_nopriv_stm_lms_restore_password', 'STM_LMS_User::stm_lms_restore_password' );

		add_action( 'stm_lms_after_user_register', 'STM_LMS_User::stm_lms_set_user_role', 10, 2 );

		add_filter( 'hidden_meta_boxes', 'STM_LMS_User::custom_hidden_meta_boxes', 10, 2 );

		add_action(
			'stm_lms_admin_after_wrapper_start',
			function ( $current_user ) {
				STM_LMS_Templates::show_lms_template( 'account/private/parts/tabs', compact( 'current_user' ) );
			}
		);
	}

	public static function custom_hidden_meta_boxes( $hidden, $screen ) {
		$post_type = $screen->id;

		if ( 'stm-courses' === $post_type ) {

			$hidden[] = 'authordiv';
			$hidden[] = 'postcustom';

		}
		return $hidden;
	}

	public function remove_user( $user_id ) {
		stm_lms_get_delete_user_courses( $user_id );
	}

	public static function remove_admin_bar() {
		if ( ! current_user_can( 'administrator' ) && ! is_admin() ) {
			show_admin_bar( false );
		}
	}

	public static function redirect() {
		if ( is_user_logged_in() ) {
			wp_safe_redirect( self::user_page_url() );
		}
	}

	public static function wsl_new_register_redirect_url( $user_id ) {
		if ( null != $user_id ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
			do_action( 'wsl_clear_user_php_session' );
			wp_safe_redirect( STM_LMS_USER::user_page_url( $user_id ) );
			die();
		}
	}

	public static function login_page_url() {
		$settings = get_option( 'stm_lms_settings', array() );

		if ( empty( $settings['user_url'] ) ) {
			return home_url( '/' );
		}

		$user_page = $settings['user_url'];

		// Polylang Compatibility
		if ( function_exists( 'pll_get_post' ) ) {
			$user_page = pll_get_post( $user_page, pll_current_language() );
		}

		return get_the_permalink( $user_page );
	}

	public static function user_page_url( $user_id = '', $force = false ) {
		if ( ! is_user_logged_in() && ! $force ) {
			return self::login_page_url();
		}

		$settings = get_option( 'stm_lms_settings', array() );

		return ( empty( $settings['user_url'] ) ) ? home_url( '/' ) : get_the_permalink( $settings['user_url'] );
	}

	public static function instructor_public_page_url( $user_id ) {
		$settings = get_option( 'stm_lms_settings', array() );

		if ( empty( $settings['instructor_url_profile'] ) || ! did_action( 'init' ) ) {
			return home_url( '/' );
		}

		return get_the_permalink( $settings['instructor_url_profile'] ) . $user_id;
	}

	public static function student_public_page_url( $user_id ) {
		$settings = get_option( 'stm_lms_settings', array() );

		if ( empty( $settings['student_url_profile'] ) || ! did_action( 'init' ) ) {
			return home_url( '/' );
		}

		return get_the_permalink( $settings['student_url_profile'] ) . $user_id;
	}

	public static function stm_lms_login() {
		check_ajax_referer( 'stm_lms_login', 'nonce' );

		$response = array(
			'status' => 'error',
		);

		$fields = array(
			'user_login',
			'user_password',
		);

		$recaptcha_passed = STM_LMS_Helpers::check_recaptcha();
		if ( ! $recaptcha_passed ) {
			$response['errors'][] = array(
				'id'    => 'recaptcha',
				'field' => 'recaptcha',
				'text'  => esc_html__( 'CAPTCHA verification failed.', 'masterstudy-lms-learning-management-system' ),
			);
			return wp_send_json( $response );
		}

		$request_body = file_get_contents( 'php://input' );
		$data         = json_decode( $request_body, true );

		foreach ( $fields as $field_key ) {
			if ( empty( $data[ $field_key ] ) ) {
				$response['errors'][] = array(
					'id'    => 'required',
					'field' => $field_key,
					'text'  => __( 'Field is required', 'masterstudy-lms-learning-management-system' ),
				);
			}
		}

		if ( ! empty( $response['errors'] ) ) {
			return wp_send_json( $response );
		}

		$get_user_by   = is_email( remove_accents( $data['user_login'] ) ) ? 'email' : 'login';
		$is_registered = get_user_by( $get_user_by, remove_accents( $data['user_login'] ) );
		if ( ! $is_registered ) {
			$response['errors'][] = array(
				'id'    => 'wrong_username',
				'field' => 'user_login',
				'text'  => esc_html__( 'Wrong username', 'masterstudy-lms-learning-management-system' ),
			);

			return wp_send_json( $response );
		}

		/* remove login failed redirect */
		remove_action( 'wp_login_failed', 'pmpro_login_failed' );

		$user = wp_signon( $data, is_ssl() );
		if ( is_wp_error( $user ) ) {
			$response['errors'][] = array(
				'id'    => 'wrong_password',
				'field' => 'user_password',
				'text'  => esc_html__( 'Wrong password', 'masterstudy-lms-learning-management-system' ),
			);
			return wp_send_json( $response );
		} else {
			$response['user_page'] = self::user_page_url( $user->ID, true );
			$response['message']   = esc_html__( 'Successfully logged in. Redirecting...', 'masterstudy-lms-learning-management-system' );
			$response['status']    = 'success';
		}

		return wp_send_json( apply_filters( 'stm_lms_login', $response ) );
	}

	public static function stm_lms_register() {
		check_ajax_referer( 'stm_lms_register', 'nonce' );

		$response = array(
			'errors' => array(),
			'status' => 'error',
		);

		$recaptcha_passed = STM_LMS_Helpers::check_recaptcha();
		if ( ! $recaptcha_passed ) {
			$response['errors'][] = array(
				'id'    => 'recaptcha',
				'field' => 'recaptcha',
				'text'  => esc_html__( 'CAPTCHA verification failed.', 'masterstudy-lms-learning-management-system' ),
			);
			return wp_send_json( $response );
		}

		$fields = array(
			'register_user_login'       => array(
				'label' => esc_html__( 'Username', 'masterstudy-lms-learning-management-system' ),
				'type'  => 'text',
			),
			'register_user_email'       => array(
				'label' => esc_html__( 'E-mail', 'masterstudy-lms-learning-management-system' ),
				'type'  => 'email',
			),
			'register_user_password'    => array(
				'label' => esc_html__( 'Password', 'masterstudy-lms-learning-management-system' ),
				'type'  => 'text',
			),
			'register_user_password_re' => array(
				'label' => esc_html__( 'Password confirm', 'masterstudy-lms-learning-management-system' ),
				'type'  => 'text',
			),
			'privacy_policy'            => array(
				'label' => esc_html__( 'Privacy Policy', 'masterstudy-lms-learning-management-system' ),
				'type'  => 'text',
			),
		);

		$request_body = file_get_contents( 'php://input' );
		$data         = json_decode( $request_body, true );

		$all_additional_fields = $data['additional'];

		if ( $data['become_instructor'] ) {
			$all_additional_fields = array_merge( $all_additional_fields, $data['additional_instructors'] );
		}

		if ( ! empty( $data['additional_instructors'] ) ) {
			$data['fields_type'] = 'custom';
			$data['fields']      = $data['additional_instructors'];
		}

		foreach ( $fields as $field_key => $field ) {
			if ( empty( $data[ $field_key ] ) ) {
				$response['errors'][] = array(
					'id'    => 'privacy_policy' === $field_key ? 'policy' : 'required',
					'field' => $field_key,
					'text'  => 'privacy_policy' === $field_key ? __( 'You must agree to our Privacy Policy', 'masterstudy-lms-learning-management-system' ) : __( 'Field is required', 'masterstudy-lms-learning-management-system' ),
				);
			} else {
				if ( 'register_user_password' !== $field_key && 'register_user_password_re' !== $field_key ) {
					$data[ $field_key ] = STM_LMS_Helpers::sanitize_fields( $data[ $field_key ], $field['type'] );
				}
				if ( empty( $data[ $field_key ] ) ) {
					$response['errors'][] = array(
						'id'    => 'valid',
						'field' => $field_key,
						'text'  => 'register_user_email' === $field_key ? esc_html__( 'Please enter a valid email', 'masterstudy-lms-learning-management-system' ) : esc_html__( 'Please enter valid value', 'masterstudy-lms-learning-management-system' ),
					);
				}
			}
		}

		if ( ! empty( $data['profile_default_fields_for_register'] ) ) {
			foreach ( $data['profile_default_fields_for_register'] as $index => $field ) {
				if ( ! empty( $field['required'] ) && empty( $field['value'] ) ) {
					$response['errors'][] = array(
						'id'    => 'required',
						'field' => $index,
						'text'  => esc_html__( 'Field is required', 'masterstudy-lms-learning-management-system' ),
					);
				}
			}
		}

		if ( ! empty( $all_additional_fields ) ) {
			foreach ( $all_additional_fields as $field ) {
				if ( ! empty( $field['required'] ) && $field['required'] && empty( $field['value'] ) ) {
					$response['errors'][] = array(
						'id'    => 'required',
						'field' => $field['slug'],
						'text'  => esc_html__( 'Field is required', 'masterstudy-lms-learning-management-system' ),
					);
				}
			}
		}

		/*Add check for login*/
		if ( sanitize_user( remove_accents( $data['register_user_login'] ), true ) !== remove_accents( $data['register_user_login'] ) ) {
			$error_text = is_multisite()
			? esc_html__( 'Must be at least 4 characters, lowercase letters and numbers only', 'masterstudy-lms-learning-management-system' )
			: esc_html__( 'Please remove tags, octets and entities from login', 'masterstudy-lms-learning-management-system' );

			$response['errors'][] = array(
				'id'    => 'tags',
				'field' => 'register_user_login',
				'text'  => $error_text,
			);
		}

		extract( $data ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		/**
		 * @var $register_user_login ;
		 * @var $register_user_email ;
		 * @var $register_user_password ;
		 * @var $register_user_password_re ;
		 */

		$register_user_login = remove_accents( $register_user_login );

		if ( username_exists( $register_user_login ) ) {
			$response['errors'][] = array(
				'id'    => 'create_username',
				'field' => 'register_user_login',
				'text'  => esc_html__( 'User with this username already exists', 'masterstudy-lms-learning-management-system' ),
			);
		}

		if ( email_exists( $register_user_email ) ) {
			$response['errors'][] = array(
				'id'    => 'create_email',
				'field' => 'register_user_email',
				'text'  => esc_html__( 'User with this email address already exists', 'masterstudy-lms-learning-management-system' ),
			);
		}

		$weak_password = STM_LMS_Options::get_option( 'registration_weak_password', false );

		if ( ! $weak_password && ! empty( $register_user_password ) ) {
			/* If Password shorter than 8 characters*/
			if ( strlen( $register_user_password ) < 8 ) {
				$response['errors'][] = array(
					'id'    => 'characters',
					'field' => 'register_user_password',
					'text'  => esc_html__( 'Password must have at least 8 characters', 'masterstudy-lms-learning-management-system' ),
				);
			}
			/* if contains letter */
			if ( ! preg_match( '#[a-z]+#', $register_user_password ) ) {
				$response['errors'][] = array(
					'id'    => 'lowercase',
					'field' => 'register_user_password',
					'text'  => esc_html__( 'Password must include at least one lowercase letter!', 'masterstudy-lms-learning-management-system' ),
				);
			}
			/* if contains number */
			if ( ! preg_match( '#[0-9]+#', $register_user_password ) ) {
				$response['errors'][] = array(
					'id'    => 'number',
					'field' => 'register_user_password',
					'text'  => esc_html__( 'Password must include at least one number!', 'masterstudy-lms-learning-management-system' ),
				);
			}
			/* if contains CAPS */
			if ( ! preg_match( '#[A-Z]+#', $register_user_password ) ) {
				$response['errors'][] = array(
					'id'    => 'capital',
					'field' => 'register_user_password',
					'text'  => esc_html__( 'Password must include at least one capital letter!', 'masterstudy-lms-learning-management-system' ),
				);
			}
		}

		if ( ! empty( $register_user_password_re ) && $register_user_password !== $register_user_password_re ) {
			$response['errors'][] = array(
				'id'    => 'not_match',
				'field' => 'register_user_password_re',
				'text'  => esc_html__( 'Passwords do not match', 'masterstudy-lms-learning-management-system' ),
			);
		}

		if ( ! empty( $response['errors'] ) ) {
			return wp_send_json( $response );
		}

		$premoderation            = STM_LMS_Options::get_option( 'user_premoderation', false );
		$registration_restriction = STM_LMS_Options::get_option( 'restrict_registration', false );

		if ( $registration_restriction ) {
			$response['errors'][] = array(
				'id'   => 'registration_restriction',
				'text' => esc_html__( 'Registration is currently restricted. Please try again later.', 'masterstudy-lms-learning-management-system' ),
			);
			return wp_send_json( $response );
		}

		/*Now we have valid data*/
		$user = wp_create_user( $register_user_login, $register_user_password, $register_user_email );

		if ( is_wp_error( $user ) ) {
			$response['errors'][] = array(
				'id'    => 'create_user',
				'field' => 'register_user_login',
				'text'  => $user->get_error_message(),
			);
			return wp_send_json( $response );
		} elseif ( $premoderation ) {
				self::_handle_premoderation( $user, $data, $register_user_email );
				$response['status'] = 'success';
		} else {
			self::_register_user( $user, $data, $register_user_email );
			$response['status']    = 'success';
			$response['user_page'] = self::user_page_url( $user, true );
			do_action( 'stm_lms_after_user_register', $user, $data );
		}

		return wp_send_json( $response );
	}

	public static function _handle_premoderation( $user, $data, $user_email ) { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		$token = bin2hex( openssl_random_pseudo_bytes( 16 ) );

		/*Setting link for 3 days*/
		set_transient( $token, $data, 3 * 24 * 60 * 60 );

		/*Delete User first and save his data to transient*/
		require_once ABSPATH . 'wp-admin/includes/ms.php';

		$reset_url  = self::login_page_url() . '?user_token=' . $token;
		$user_login = get_userdata( $user )->ID;
		$blog_name  = get_bloginfo( 'name' );

		wp_delete_user( $user );
		wpmu_delete_user( $user );

		/* translators: %s: site name */
		$subject = sprintf( esc_html__( 'Activate your account', 'masterstudy-lms-learning-management-system' ) );
		if ( ( ! STM_LMS_Helpers::is_pro_plus() && empty( get_option( 'stm_lms_email_manager_settings' ) ) ) || STM_LMS_Helpers::is_pro_plus() && empty( get_option( 'stm_lms_email_manager_settings' ) ) ) {
			$reset_url = '<a href="' . $reset_url . '">' . $reset_url . '</a>';
		}

		$template = wp_kses_post(
			'Hi {{user_login}}, <br>
					Welcome to {{blog_name}} <br>
					To start using your account, please activate it by clicking the link below: <br>
					Activation Link: {{reset_url}} <br>
					We look forward to seeing you on {{blog_name}} <br>'
		);

		$email_data_account_premoderation = array(
			'user_login' => STM_LMS_Helpers::masterstudy_lms_get_user_full_name_or_login( $user_login ),
			'blog_name'  => $blog_name,
			'reset_url'  => $reset_url,
			'site_url'   => \MS_LMS_Email_Template_Helpers::link( \STM_LMS_Helpers::masterstudy_lms_get_site_url() ),
			'date'       => gmdate( 'Y-m-d H:i:s' ),
		);

		$message = \MS_LMS_Email_Template_Helpers::render( $template, $email_data_account_premoderation );
		$subject = \MS_LMS_Email_Template_Helpers::render( $subject, $email_data_account_premoderation );

		STM_LMS_Helpers::send_email(
			$user_email,
			$subject,
			$message,
			'stm_lms_account_premoderation',
			$email_data_account_premoderation
		);
	}

	public static function verify_user() {
		$token = sanitize_text_field( $_GET['user_token'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$data = get_transient( $token );

		if ( ! empty( $data ) ) {
			extract( $data ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract

			/**
			 * @var $register_user_login
			 * @var $register_user_password
			 * @var $register_user_email
			 */

			$user = wp_create_user( $register_user_login, $register_user_password, $register_user_email );

			if ( ! is_wp_error( $user ) ) {
				self::_register_user( $user, $data, $register_user_email );
			}

			do_action( 'stm_lms_after_user_register', $user, $data );
		}

		wp_redirect( $data['redirect_page'] ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit();
	}

	public static function _user_profile_fields() { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		$get_forms = STM_LMS_Form_Builder::get_forms();
		$result    = array();

		if ( ! empty( $get_forms['forms'] ) ) {
			foreach ( $get_forms['forms'] as $form ) {
				if ( $form && ! empty( $form['fields'] ) ) {
					foreach ( $form['fields'] as $key => $field ) {
						$result[ $key ] = $field;
					}
				}
			}
		}
		if ( ! empty( $get_forms['required_fields']['profile_form'] ) ) {
			foreach ( $get_forms['required_fields']['profile_form'] as $key => $item ) {
				$result[ $key ] = $item;
			}
		}

		return $result;
	}

	public static function _register_user( $user, $data, $user_email ) { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		$login_data = array(
			'user_login'    => $data['register_user_login'],
			'user_password' => $data['register_user_password'],
			'remember'      => false,
		);
		wp_signon( $login_data, is_ssl() );

		if ( class_exists( 'STM_LMS_Form_Builder' ) ) {
			$default_fields = self::_user_profile_fields();
		}

		/*If everything is right, check for Instructor application*/
		if ( STM_LMS_Options::get_option( 'register_as_instructor', false ) ) {
			STM_LMS_Instructor::become_instructor( $data, $user );
		}

		if ( ! empty( $default_fields ) && ! empty( $data['profile_default_fields_for_register'] ) ) {
			foreach ( $data['profile_default_fields_for_register'] as $key => $field ) {
				if ( isset( $field['value'] ) && ! empty( $default_fields[ $key ] ) ) {
					update_user_meta( $user, $key, $field['value'] );
				}
			}
		}

		do_action( 'stm_lms_user_registered', $user, $data );
		$blog_name  = get_bloginfo( 'name' );
		$user_login = get_userdata( $user )->user_login;
		$login_url  = esc_url( site_url() . '/' . get_post_field( 'post_name', STM_LMS_Options::get_option( 'user_url', true ) ) );

		if ( ( ! STM_LMS_Helpers::is_pro_plus() && empty( get_option( 'stm_lms_email_manager_settings' ) ) ) || STM_LMS_Helpers::is_pro_plus() && empty( get_option( 'stm_lms_email_manager_settings' ) ) ) {
			$login_url = '<a href="' . $login_url . '">' . $login_url . '</a>';
		}

		$subject = esc_html__( 'You have successfully registered on the website.', 'masterstudy-lms-learning-management-system' );

		$template = wp_kses_post(
			'Hi {{user_login}}, <br>
					Welcome to {{blog_name}} <br>
					Your registration was successful. <br>
					You can now log in to your account using the following link: <br>
					Login URL: {{login_url}}<br>
					We are thrilled to have you on board!<br>'
		);

		$email_data_register = array(
			'blog_name'  => $blog_name,
			'user_login' => STM_LMS_Helpers::masterstudy_lms_get_user_full_name_or_login( $user ),
			'login_url'  => $login_url,
			'site_url'   => \MS_LMS_Email_Template_Helpers::link( \STM_LMS_Helpers::masterstudy_lms_get_site_url() ),
			'date'       => gmdate( 'Y-m-d H:i:s' ),
			'user_id'    => $user,
		);

		$message = \MS_LMS_Email_Template_Helpers::render( $template, $email_data_register );

		if ( ! empty( $data['additional'] ) ) {
			foreach ( $data['additional'] as $field ) {
				$label = '';
				if ( ! empty( $field['label'] ) ) {
					$label = $field['label'];
				} elseif ( ! empty( $field['slug'] ) ) {
					$label = $field['slug'];
				} elseif ( ! empty( $field['field_name'] ) ) {
					$label = $field['field_name'];
				}
				if ( ! empty( $field['slug'] ) ) {
					$email_data_register[ $field['slug'] ] = $field['value'];
				}
				if ( isset( $field['value'] ) && in_array( $field['id'], array_column( $default_fields, 'id' ) ) ) { // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
					update_user_meta( $user, $field['id'], $field['value'] );
				}
				$message .= $label . ' - ' . $field['value'] . ';<br>';
			}
		}

		STM_LMS_Helpers::send_email(
			$user_email,
			$subject,
			$message,
			'stm_lms_user_registered_on_site',
			$email_data_register
		);

		$template = wp_kses_post(
			'A new user has just registered on the site. <br> Here are the details: <br>
		Name: {{user_login}} <br>
		Email: {{user_email}} <br>
		Registration Date: {{registration_date}} <br>
		Please welcome our new member!'
		);

		$subject = esc_html__( 'New User Registered', 'masterstudy-lms-learning-management-system' );

		$email_data = array(
			'user_login'        => STM_LMS_Helpers::masterstudy_lms_get_user_full_name_or_login( $user ),
			'user_email'        => $user_email,
			'registration_date' => gmdate( 'Y-m-d H:i:s' ),
			'blog_name'         => STM_LMS_Helpers::masterstudy_lms_get_site_name(),
			'site_url'          => \MS_LMS_Email_Template_Helpers::link( \STM_LMS_Helpers::masterstudy_lms_get_site_url() ),
		);

		$message = \MS_LMS_Email_Template_Helpers::render( $template, $email_data );

		STM_LMS_Helpers::send_email(
			'',
			$subject,
			$message,
			'stm_lms_new_user_register_on_site',
			$email_data
		);

	}

	public static function stm_lms_set_user_role( $user, $data ) {
		if ( ! empty( $data['become_instructor'] ) && $data['become_instructor'] ) {
			$register_as_instructor   = STM_LMS_Options::get_option( 'register_as_instructor', false );
			$instructor_premoderation = STM_LMS_Options::get_option( 'instructor_premoderation', false );

			if ( $register_as_instructor && ! $instructor_premoderation ) {
				wp_update_user(
					array(
						'ID'   => $user,
						'role' => 'stm_lms_instructor',
					)
				);
			}
		}
	}

	public static function get_current_user( $id = '', $get_role = false, $get_meta = false, $no_avatar = false, $avatar_size = 215, $for_student = false ) {
		$user = array(
			'id' => 0,
		);

		$current_user = ( ! empty( $id ) ) ? get_userdata( $id ) : wp_get_current_user();

		$avatar_url = '';

		if ( ! empty( $current_user->ID ) && 0 != $current_user->ID ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison

			if ( ! $no_avatar ) {
				/*Get Meta*/
				$stm_lms_user_avatar = get_user_meta( $current_user->ID, 'stm_lms_user_avatar', true );
				if ( ! empty( $stm_lms_user_avatar ) ) {
					$avatar     = "<img src='{$stm_lms_user_avatar}' class='avatar photo' width='{$avatar_size}' />";
					$avatar_url = $stm_lms_user_avatar;
				} else {
					$avatar = get_avatar( $current_user->ID, $avatar_size );

					if ( preg_match( '/src=["\']([^"\']+)["\']/', $avatar, $match ) ) {
						$avatar_url = $match[1]; // Extract the URL directly
					} else {
						$avatar_url = $stm_lms_user_avatar; // Default to empty string if no match found
					}
				}
			} else {
				$avatar = '';
			}

			$is_instructor = in_array( 'administrator', $current_user->roles, true ) || in_array( 'stm_lms_instructor', $current_user->roles, true );

			$user = array(
				'id'            => $current_user->ID,
				'login'         => self::display_name( $current_user ),
				'avatar'        => $avatar,
				'avatar_url'    => $avatar_url,
				'email'         => $current_user->data->user_email,
				'url'           => $for_student ? self::student_public_page_url( $current_user->ID ) : ( $is_instructor ? self::instructor_public_page_url( $current_user->ID ) : self::student_public_page_url( $current_user->ID ) ),
				'is_instructor' => $is_instructor,
			);

			$stm_lms_user_cover = get_user_meta( $current_user->ID, 'stm_lms_user_cover', true );

			if ( $stm_lms_user_cover ) {
				$user['cover'] = wp_get_attachment_url( $stm_lms_user_cover );
			}

			if ( $get_role ) {
				$user_meta     = get_userdata( $current_user->ID );
				$user['roles'] = $user_meta->roles;
			}

			if ( $get_meta ) {
				$fields       = self::extra_fields();
				$fields       = array_merge( $fields, self::additional_fields() );
				$user['meta'] = array();
				foreach ( $fields as $field_key => $field ) {
					$meta                       = get_user_meta( $current_user->ID, $field_key, true );
					$user['meta'][ $field_key ] = ( ! empty( $meta ) ) ? $meta : '';
				}
			}
		}

		return apply_filters( 'stm_lms_current_user_data', $user );
	}

	public static function display_name( $user ) {
		$first_name = get_user_meta( $user->ID, 'first_name', true );
		$last_name  = get_user_meta( $user->ID, 'last_name', true );
		if ( ! empty( $first_name ) && ! empty( $last_name ) ) {
			$first_name .= ' ' . $last_name;
		}

		if ( empty( $first_name ) && ! empty( $user->data->display_name ) ) {
			$first_name = $user->data->display_name;
		}

		if ( ! empty( $user->data->display_name ) ) {
			return $user->data->display_name;
		}

		return ( ! empty( $first_name ) ) ? $first_name : $user->data->user_login;
	}

	public static function js_redirect( $page ) {
		?>
		<script type="text/javascript">
			window.location = '<?php echo esc_url( $page ); ?>';
		</script>
		<?php
	}

	public static function _get_user_courses( $offset, $status = 'all' ) { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		$user = self::get_current_user();

		if ( empty( $user['id'] ) ) {
			die;
		}

		$user_id        = $user['id'];
		$posts_per_page = absint( get_option( 'posts_per_page', apply_filters( 'stm_lms_get_user_courses_per_page', 10 ) ) );
		$offset         = $offset * $posts_per_page;
		$total          = 0;
		$response       = array(
			'posts'  => array(),
			'offset' => $offset,
		);

		foreach ( stm_lms_get_user_courses( $user_id ) as $course_user ) {
			if ( get_post_type( $course_user['course_id'] ) !== 'stm-courses' ) {
				stm_lms_get_delete_courses( $course_user['course_id'] );
				continue;
			}

			++$total;
		}

		$columns = array( 'course_id', 'current_lesson_id', 'progress_percent', 'subscription_id', 'start_time', 'status', 'enterprise_id', 'bundle_id', 'for_points' );
		$courses = stm_lms_get_user_courses( $user_id, $posts_per_page, $offset, $columns, null, null );

		$response['total_posts'] = $total;
		$response['total']       = $total <= $offset + $posts_per_page;
		$response['pages']       = ceil( $total / $posts_per_page );

		if ( ! empty( $courses ) ) {
			foreach ( $courses as $course ) {
				$id = $course['course_id'];

				if ( get_post_type( $id ) !== 'stm-courses' ) {
					stm_lms_get_delete_courses( $id );
					continue;
				}

				if ( ! get_post_status( $id ) ) {
					continue;
				}

				$price      = get_post_meta( $id, 'price', true );
				$sale_price = STM_LMS_Course::get_sale_price( $id );

				if ( empty( $price ) && ! empty( $sale_price ) ) {
					$price      = $sale_price;
					$sale_price = '';
				}

				$post_status                = STM_LMS_Course::get_post_status( $id );
				$image                      = ( function_exists( 'stm_get_VC_img' ) ) ? stm_get_VC_img( get_post_thumbnail_id( $id ), '272x161' ) : get_the_post_thumbnail( $id, 'img-300-225' );
				$course['progress_percent'] = ( $course['progress_percent'] > 100 ) ? 100 : $course['progress_percent'];

				if ( 'completed' === $course['status'] ) {
					$course['progress_percent'] = '100';
				}

				$current_lesson = ( ! empty( $course['current_lesson_id'] ) ) ? $course['current_lesson_id'] : STM_LMS_Lesson::get_first_lesson( $id );

				$membership_info = STM_LMS_Helpers::masterstudy_lms_check_membership_status( $user_id, $course, $id, false );

				ob_start();
				STM_LMS_Templates::show_lms_template(
					'global/expired_course',
					array(
						'course_id'     => $id,
						'expired_popup' => false,
					)
				);
				$expiration = ob_get_clean();

				$post = array(
					'id'                  => $id,
					'url'                 => get_the_permalink( $id ),
					'image_id'            => get_post_thumbnail_id( $id ),
					'title'               => get_the_title( $id ),
					'link'                => get_the_permalink( $id ),
					'image'               => $image,
					'terms'               => wp_get_post_terms( $id, 'stm_lms_course_taxonomy' ),
					'terms_list'          => stm_lms_get_terms_array( $id, 'stm_lms_course_taxonomy', 'name' ),
					'views'               => STM_LMS_Course::get_course_views( $id ),
					'price'               => STM_LMS_Helpers::display_price( $price ),
					'sale_price'          => STM_LMS_Helpers::display_price( $sale_price ),
					'post_status'         => $post_status,
					'availability'        => get_post_meta( $id, 'coming_soon_status', true ),
					'progress'            => strval( $course['progress_percent'] ),
					/* translators: %s: course complete */
					'progress_label'      => sprintf( esc_html__( '%s%% Complete', 'masterstudy-lms-learning-management-system' ), $course['progress_percent'] ),
					'current_lesson_id'   => STM_LMS_Lesson::get_lesson_url( $id, $current_lesson ),
					'course_id'           => $id,
					'lesson_id'           => $current_lesson,
					/* translators: %s: start time */
					'start_time'          => sprintf( esc_html__( 'Started %s', 'masterstudy-lms-learning-management-system' ), date_i18n( get_option( 'date_format' ), $course['start_time'] ) ),
					'duration'            => get_post_meta( $id, 'duration_info', true ),
					'expiration'          => $expiration,
					'is_expired'          => STM_LMS_Course::is_course_time_expired( get_current_user_id(), $id ),
					'membership_expired'  => $membership_info['membership_expired'],
					'membership_inactive' => $membership_info['membership_inactive'],
					'no_membership_plan'  => $membership_info['no_membership_plan'],
				);

				/* Check course complete status*/
				$curriculum       = ( new MasterStudy\Lms\Repositories\CurriculumRepository() )->get_curriculum( $id, true );
				$course_materials = array_reduce(
					$curriculum,
					function ( $carry, $section ) {
						return array_merge( $carry, $section['materials'] ?? array() );
					},
					array()
				);
				$material_ids     = array_column( $course_materials, 'post_id' );
				$last_lesson      = ! empty( $material_ids ) ? end( $material_ids ) : 0;
				$lesson_post_type = get_post_type( $last_lesson );

				if ( PostType::QUIZ === $lesson_post_type ) {
					$last_quiz        = stm_lms_get_user_last_quiz( $user_id, $last_lesson, array( 'progress' ) );
					$passing_grade    = get_post_meta( $last_lesson, 'passing_grade', true );
					$lesson_completed = ! empty( $last_quiz['progress'] ) && $last_quiz['progress'] >= ( $passing_grade ?? 0 ) ? 'completed' : '';
				} else {
					$lesson_completed = STM_LMS_Lesson::is_lesson_completed( $user_id, $id, $last_lesson ) ? 'completed' : '';
				}

				$course_passed = intval( STM_LMS_Options::get_option( 'certificate_threshold', 70 ) ) <= intval( $course['progress_percent'] );

				if ( ! empty( $lesson_completed ) && ! $course_passed ) {
					$post['complete_status'] = 'failed';
				} elseif ( intval( $course['progress_percent'] ) > 0 ) {
					$post['complete_status'] = $course_passed ? 'completed' : 'in_progress';
				} else {
					$post['complete_status'] = 'not_started';
				}

				if ( $status === $post['complete_status'] || 'all' === $status ) {
					$response['posts'][] = $post;
				}

				if ( empty( $response['posts'] ) ) {
					$response['total'] = true;
				}
			}
		}

		return $response;
	}

	public static function get_user_courses() {
		check_ajax_referer( 'stm_lms_get_user_courses', 'nonce' );

		$offset = ( ! empty( $_GET['offset'] ) ) ? intval( $_GET['offset'] ) : 0;

		$status = ( ! empty( $_GET['status'] ) ) ? sanitize_text_field( $_GET['status'] ) : 'all';

		$r = self::_get_user_courses( $offset, $status );

		wp_send_json( apply_filters( 'stm_lms_get_user_courses_filter', $r ) );
	}

	public static function get_user_meta( $user_id, $key ) {
		return get_user_meta( $user_id, $key, true );
	}

	public static function has_course_access( $course_id, $item_id = '', $add = true ) {
		$user = self::get_current_user();

		if ( empty( $user['id'] ) ) {
			return apply_filters( 'stm_lms_has_course_access', false, $course_id, $item_id );
		}

		$user_id = $user['id'];

		/*If course Author*/
		$author_id = get_post_field( 'post_author', $course_id );
		if ( $author_id == $user_id ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
			$user_course = STM_LMS_Course::get_user_course( $user_id, $course_id );
			if ( empty( $user_course ) ) {
				STM_LMS_Course::add_user_course( $course_id, $user_id, STM_LMS_Lesson::get_lesson_url( $course_id, '' ), 0 );
			}

			return true;
		}

		if ( STM_LMS_Cart::woocommerce_checkout_enabled() ) {
			wc_customer_bought_product( $user['email'], $user_id, $course_id );
		}

		$columns = array( 'user_course_id', 'enterprise_id', 'subscription_id', 'bundle_id', 'for_points' );
		$course  = stm_lms_get_user_course( $user_id, $course_id, $columns );

		$membership_info = STM_LMS_Helpers::masterstudy_lms_check_membership_status( $user_id, $course, $course_id, true );

		if ( $membership_info['membership_expired'] || $membership_info['membership_inactive'] ) {
			return apply_filters( 'stm_lms_has_course_access', 0, $course_id, $item_id );
		}

		if ( ! count( $course ) ) {
			/*If course is free*/
			$prerequisite_passed = true;
			if ( class_exists( 'STM_LMS_Prerequisites' ) ) {
				$prerequisite_passed = STM_LMS_Prerequisites::is_prerequisite( true, $course_id );
			}
			if ( $membership_info['is_free'] && $prerequisite_passed && $add ) {
				$auto_enroll = STM_LMS_Options::get_option( 'course_user_auto_enroll', false );
				if ( $auto_enroll || ! is_single() ) {
					STM_LMS_Course::add_user_course( $course_id, $user_id, STM_LMS_Lesson::get_lesson_url( $course_id, '' ), 0 );
				}
				STM_LMS_Course::add_student( $course_id );
				return true;
			}
		} else {
			/*Check for expiration*/
			$course_expired = STM_LMS_Course::is_course_time_expired( $user_id, $course_id );
			if ( $course_expired ) {
				return apply_filters( 'stm_lms_has_course_access', 0, $course_id, $item_id );
			}
		}

		return apply_filters( 'stm_lms_has_course_access', count( $course ), $course_id, $item_id );
	}

	public static function get_user_course_access_list( $user_id = null ) {
		global $wpdb;

		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		$course_table = stm_lms_user_courses_name( $wpdb );

		$course_ids = $wpdb->get_col(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT course_id FROM $course_table WHERE user_id = %d",
				$user_id
			)
		);

		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'stm-courses' AND post_author = %d AND post_status = 'publish'",
				$user_id
			)
		);

		$course_ids = array_unique(
			array_merge( $course_ids, $post_ids )
		);

		return array_flip( $course_ids );
	}

	public static function get_wishlist( $user_id = 0 ) {
		$wishlist = array();

		if ( ! empty( $user_id ) ) {
			$wishlist = get_user_meta( $user_id, 'stm_lms_wishlist', true );
			if ( empty( $wishlist ) ) {
				$wishlist = array();
			}
		} elseif ( ! is_user_logged_in() ) {
				$wishlist = ( ! empty( $_COOKIE['stm_lms_wishlist'] ) ) ? $_COOKIE['stm_lms_wishlist'] : array();
			if ( ! empty( $wishlist ) ) {
				$wishlist = array_filter( array_unique( explode( ',', $wishlist ) ) );
			}
				return $wishlist;
		}

		return $wishlist;
	}

	public static function update_wishlist( $user_id, $wishlist ) {
		return update_user_meta( $user_id, 'stm_lms_wishlist', array_unique( array_filter( $wishlist ) ) );
	}

	public static function wishlist() {
		check_ajax_referer( 'stm_lms_wishlist', 'nonce' );

		if ( empty( $_GET['post_id'] ) ) {
			die;
		}

		$user = self::get_current_user();
		if ( empty( $user['id'] ) ) {
			die;
		}
		$user_id = $user['id'];

		$r = array(
			'icon' => 'far fa-heart',
			'text' => esc_html__( 'Add to wishlist', 'masterstudy-lms-learning-management-system' ),
		);

		$post_id = intval( $_GET['post_id'] );

		$wishlist = self::get_wishlist( $user_id );

		/*Add to wishlist*/
		if ( ! in_array( $post_id, $wishlist ) ) { // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
			$wishlist[] = $post_id;
			$r          = array(
				'icon' => 'fa fa-heart',
				'text' => esc_html__( 'Remove from wishlist', 'masterstudy-lms-learning-management-system' ),
			);
		} else {
			/*Remove*/
			$index = array_search( $post_id, $wishlist ); // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
			unset( $wishlist[ $index ] );
		}

		self::update_wishlist( $user_id, $wishlist );

		wp_send_json( $r );
	}

	public static function is_wishlisted( $course_id, $user_id = '' ) {
		if ( is_user_logged_in() || ! empty( $user_id ) ) {
			if ( empty( $user_id ) ) {
				$user    = self::get_current_user();
				$user_id = $user['id'];
			}
			$wishlist = self::get_wishlist( $user_id );
		} else {
			if ( empty( $_COOKIE['stm_lms_wishlist'] ) ) {
				return false;
			}
			$wishlist = explode( ',', sanitize_text_field( $_COOKIE['stm_lms_wishlist'] ) );
		}

		return in_array( $course_id, $wishlist ); // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
	}

	public static function user_logged_in( $user_name, $user ) {
		$user_id = $user->ID;
		self::move_wishlist_to_user( $user_id );
	}

	public static function move_wishlist_to_user( $user_id ) {
		if ( empty( $_COOKIE['stm_lms_wishlist'] ) ) {
			return false;
		}
		$wishlist = explode( ',', sanitize_text_field( $_COOKIE['stm_lms_wishlist'] ) );
		self::update_wishlist( $user_id, array_merge( self::get_wishlist( $user_id ), $wishlist ) );
	}

	public static function wishlist_url( $user_id = '' ) {
		$settings = get_option( 'stm_lms_settings', array() );

		if ( empty( $settings['wishlist_url'] ) || ! did_action( 'init' ) ) {
			return home_url( '/' );
		}

		return get_the_permalink( $settings['wishlist_url'] );
	}

	public static function extra_fields() {
		$extra_fields = array(
			'facebook'                           => array(
				'label' => esc_html__( 'Facebook', 'masterstudy-lms-learning-management-system' ),
				'icon'  => 'facebook-f',
			),
			'twitter'                            => array(
				'label' => esc_html__( 'Twitter', 'masterstudy-lms-learning-management-system' ),
				'icon'  => 'twitter',
			),
			'instagram'                          => array(
				'label' => esc_html__( 'Instagram', 'masterstudy-lms-learning-management-system' ),
				'icon'  => 'instagram',
			),
			'linkedin'                           => array(
				'label' => esc_html__( 'LinkedIn', 'masterstudy-lms-learning-management-system' ),
				'icon'  => 'linkedin-in',
			),
			'position'                           => array(
				'label' => esc_html__( 'Position', 'masterstudy-lms-learning-management-system' ),
			),
			'disable_report_email_notifications' => array(
				'label' => esc_html__( 'Email Notifications', 'masterstudy-lms-learning-management-system' ),
			),
		);

		return apply_filters( 'stm_lms_extra_user_fields', $extra_fields );
	}

	public static function additional_fields() {
		$additional_fields = array(
			'description' => array(
				'label' => esc_html__( 'Bio', 'masterstudy-lms-learning-management-system' ),
			),
			'first_name'  => array(
				'label' => esc_html__( 'First name', 'masterstudy-lms-learning-management-system' ),
			),
			'last_name'   => array(
				'label' => esc_html__( 'Last name', 'masterstudy-lms-learning-management-system' ),
			),

		);

		return apply_filters( 'stm_lms_user_additional_fields', $additional_fields );
	}

	public static function rating_fields() {
		$rating_fields = array(
			'sum_rating'    => array(
				'label' => esc_html__( 'Summary rating', 'masterstudy-lms-learning-management-system' ),
			),
			'total_reviews' => array(
				'label' => esc_html__( 'Total Reviews', 'masterstudy-lms-learning-management-system' ),
			),
		);

		return apply_filters( 'stm_lms_rating_user_fields', $rating_fields );
	}

	public static function extra_fields_display( $user ) {
		?>
		<h3><?php esc_html_e( 'Extra profile information', 'masterstudy-lms-learning-management-system' ); ?></h3>

		<table class="form-table">
			<?php
			$fields = self::extra_fields();
			foreach ( $fields as $field_key => $field ) :
				?>
				<tr>
					<th>
						<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo esc_attr( $field['label'] ); ?></label>
					</th>
					<td>
						<input type="text" name="<?php echo esc_attr( $field_key ); ?>"
								id="<?php echo esc_attr( $field_key ); ?>"
								value="<?php echo esc_attr( get_the_author_meta( $field_key, $user->ID ) ); ?>"
								class="regular-text"/><br/>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>

		<?php if ( current_user_can( 'manage_options' ) ) : ?>
		<h3><?php esc_html_e( 'Rating information', 'masterstudy-lms-learning-management-system' ); ?></h3>

		<table class="form-table">
			<?php
			$fields = self::rating_fields();
			foreach ( $fields as $field_key => $field ) :
				?>
				<tr>
					<th>
						<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo esc_attr( $field['label'] ); ?></label>
					</th>
					<td>
						<input type="text" name="<?php echo esc_attr( $field_key ); ?>"
								id="<?php echo esc_attr( $field_key ); ?>"
								value="<?php echo esc_attr( get_the_author_meta( $field_key, $user->ID ) ); ?>"
								class="regular-text"/><br/>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<!-- Zoom.us        -->
			<?php if ( class_exists( 'StmZoom' ) || class_exists( 'Video_Conferencing_With_Zoom' ) ) : ?>
				<?php

				if ( class_exists( 'StmZoom' ) ) {
					$zoom_users = StmZoom::stm_zoom_get_users();
				} else {
					$zoom_users_list = video_conferencing_zoom_api_get_user_transients();
					$zoom_users      = array();
					foreach ( $zoom_users_list as $zoom_user ) {
						$zoom_users[] = array(
							'id'         => $zoom_user->id,
							'first_name' => $zoom_user->first_name,
							'email'      => $zoom_user->email,
						);
					}
				}
				$user_host = get_the_author_meta( 'stm_lms_zoom_host', $user->ID );
				?>
			<h3><?php esc_html_e( 'Zoom.us settings', 'masterstudy-lms-learning-management-system' ); ?></h3>
			<table class="form-table">
				<tr>
					<th>
						<label for="stm_lms_zoom_host"><?php esc_html_e( 'Meeting Host', 'masterstudy-lms-learning-management-system' ); ?></label>
					</th>
					<td>
						<select id="stm_lms_zoom_host" name="stm_lms_zoom_host">
							<option value=""><?php esc_html_e( 'Select host', 'masterstudy-lms-learning-management-system' ); ?></option>
							<?php foreach ( $zoom_users as $zoom_user ) : ?>
								<option value="<?php echo esc_attr( $zoom_user['id'] ); ?>"
									<?php
									! empty( $user_host ) ? selected( $user_host, $zoom_user['id'] ) : false;
									?>
								><?php echo esc_html( $zoom_user['first_name'] . ' ( ' . $zoom_user['email'] . ' )' ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>
		<?php endif; ?>
		<!-- end Zoom.us        -->
		<!--Google Classrooms Auditory-->
			<?php
			if ( class_exists( 'STM_LMS_Google_Classroom' ) ) :
				$g_c_key           = 'google_classroom_auditory';
				$auditories        = STM_LMS_Helpers::get_posts( 'stm-auditory' );
				$selected_auditory = get_the_author_meta( $g_c_key, $user->ID );
				?>
			<table class="form-table">
				<tr>
					<th>
						<label for="<?php echo esc_attr( $g_c_key ); ?>">
							<?php
							esc_html_e( 'Google Classroom auditory', 'masterstudy-lms-learning-management-system' );
							?>
						</label>
					</th>
					<td>
						<select name="<?php echo esc_attr( $g_c_key ); ?>" id="<?php echo esc_attr( $g_c_key ); ?>">
							<option value=""><?php esc_html_e( 'Select auditory', 'masterstudy-lms-learning-management-system' ); ?></option>
							<?php foreach ( $auditories as $auditory_value => $auditory_name ) : ?>
								<option value="<?php echo esc_attr( $auditory_value ); ?>"
										<?php echo esc_attr( selected( $selected_auditory, $auditory_value ) ); ?>>
									<?php echo esc_attr( $auditory_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>
		<?php endif; ?>


			<?php
	endif;
	}

	public static function save_extra_fields( $user_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}

		$fields = self::extra_fields();
		foreach ( $fields as $field_key => $field ) {
			update_user_meta( $user_id, $field_key, sanitize_text_field( $_POST[ $field_key ] ) );
		}

		if ( current_user_can( 'manage_options' ) ) {
			$fields = self::rating_fields();
			foreach ( $fields as $field_key => $field ) {
				update_user_meta( $user_id, $field_key, sanitize_text_field( $_POST[ $field_key ] ) );
			}

			if ( ! empty( $_POST['google_classroom_auditory'] ) ) {
				update_user_meta( $user_id, 'google_classroom_auditory', intval( $_POST['google_classroom_auditory'] ) );
			}

			if ( isset( $_POST['stm_lms_zoom_host'] ) ) {
				update_user_meta( $user_id, 'stm_lms_zoom_host', sanitize_text_field( $_POST['stm_lms_zoom_host'] ) );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	public static function stm_lms_save_sum_rating_on_register( $user_id ) {
		update_user_meta( $user_id, 'sum_rating', '' );
	}

	public static function save_user_info() {
		check_ajax_referer( 'stm_lms_save_user_info', 'nonce' );

		$user = self::get_current_user();
		if ( empty( $user['id'] ) ) {
			die;
		}
		$user_id = $user['id'];

		$user_data = json_decode( file_get_contents( 'php://input' ), true );

		$new_pass    = ( isset( $user_data['new_pass'] ) ) ? $user_data['new_pass'] : '';
		$new_pass_re = ( isset( $user_data['new_pass_re'] ) ) ? $user_data['new_pass_re'] : '';

		$weak_password = STM_LMS_Options::get_option( 'registration_weak_password', false );

		if ( ! empty( $new_pass ) && ! empty( $new_pass_re ) ) {
			if ( $new_pass !== $new_pass_re ) {
				wp_send_json(
					array(
						'status'  => 'error',
						'message' => esc_html__( 'New password do not match', 'masterstudy-lms-learning-management-system' ),
					)
				);
			}

			if ( ! $weak_password ) {
				if ( strlen( $new_pass ) < 8 ) {
					wp_send_json(
						array(
							'status'  => 'error',
							'message' => esc_html__( 'Password must have at least 8 characters', 'masterstudy-lms-learning-management-system' ),
						)
					);
				}

				if ( ! preg_match( '#[a-z]+#', $new_pass ) ) {
					wp_send_json(
						array(
							'status'  => 'error',
							'message' => esc_html__( 'Password must include at least one lowercase letter!', 'masterstudy-lms-learning-management-system' ),
						)
					);
				}

				if ( ! preg_match( '#[0-9]+#', $new_pass ) ) {
					wp_send_json(
						array(
							'status'  => 'error',
							'message' => esc_html__( 'Password must include at least one number!', 'masterstudy-lms-learning-management-system' ),
						)
					);
				}

				if ( ! preg_match( '#[A-Z]+#', $new_pass ) ) {
					wp_send_json(
						array(
							'status'  => 'error',
							'message' => esc_html__( 'Password must include at least one capital letter!', 'masterstudy-lms-learning-management-system' ),
						)
					);
				}
			}

			$subject = esc_html__( 'Password change', 'masterstudy-lms-learning-management-system' );
			$message = esc_html__( 'Password changed successfully.', 'masterstudy-lms-learning-management-system' );

			$email_data = array(
				'blog_name'  => STM_LMS_Helpers::masterstudy_lms_get_site_name(),
				'site_url'   => \MS_LMS_Email_Template_Helpers::link( \STM_LMS_Helpers::masterstudy_lms_get_site_url() ),
				'date'       => gmdate( 'Y-m-d H:i:s' ),
				'user_login' => \STM_LMS_Helpers::masterstudy_lms_get_user_full_name_or_login( $user_id ),
			);

			STM_LMS_Helpers::send_email(
				$user['email'],
				$subject,
				$message,
				'stm_lms_password_change',
				$email_data
			);

			wp_set_password( $new_pass, $user_id );
			wp_send_json(
				array(
					'relogin' => self::login_page_url(),
					'status'  => 'success',
					'message' => esc_html__( 'Password Changed. Re-login now', 'masterstudy-lms-learning-management-system' ),
				)
			);
		}

		$fields = self::extra_fields();
		$fields = array_merge( $fields, self::additional_fields() );

		$data = array();
		foreach ( $fields as $field_name => $field ) {
			if ( isset( $user_data[ $field_name ] ) ) {
				if ( ! empty( $field['required'] ) && empty( $user_data[ $field_name ] ) ) {
					wp_send_json(
						array(
							'status'  => 'error',
							/* translators: %s: field name */
							'message' => sprintf( esc_html__( 'Please fill %s field', 'masterstudy-lms-learning-management-system' ), $field['label'] ),
						)
					);
				}
				$new_value = wp_kses_post( $user_data[ $field_name ] );
				update_user_meta( $user_id, $field_name, $new_value );

				if ( 'disable_report_email_notifications' === $field_name && $new_value ) {
					delete_user_meta( $user_id, $field_name );
				}

				$data[ $field_name ] = $new_value;
			}
		}

		/*change nicename*/
		$nicename = '';
		if ( ! empty( $user_data['first_name'] ) ) {
			$nicename = sanitize_text_field( $user_data['first_name'] );
		}
		if ( ! empty( $user_data['last_name'] ) ) {
			$nicename = ( ! empty( $nicename ) ) ? $nicename . ' ' . sanitize_text_field( $user_data['last_name'] ) : sanitize_text_field( $user_data['last_name'] );
		}
		$display_name = '';
		if ( ! empty( $user_data['display_name'] ) ) {
			$display_name = sanitize_text_field( $user_data['display_name'] );
		}

		$current_display_name = get_the_author_meta( 'display_name', $user_id );

		if ( empty( $display_name ) ) {
			$display_name = $current_display_name;
		}

		if ( $display_name !== $current_display_name ) {
			wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => $display_name,
				)
			);
		}

		$r = array(
			'data'    => $data,
			'status'  => 'success',
			'message' => esc_html__( 'Successfully saved', 'masterstudy-lms-learning-management-system' ),
		);

		wp_send_json( $r );
	}

	public static function stm_lms_logout() {
		check_ajax_referer( 'stm_lms_logout', 'nonce' );

		wp_destroy_current_session();
		wp_clear_auth_cookie();
		wp_set_current_user( 0 );

		wp_send_json( self::login_page_url() );
	}

	public static function apply_for_instructor() {
		check_ajax_referer( 'stm_lms_become_instructor', 'nonce' );

		$user_id = get_current_user_id();

		if ( empty( $user_id ) ) {
			return;
		}

		$response = array(
			'errors' => array(),
			'status' => 'error',
		);

		if ( get_user_meta( $user_id, 'stm_lms_user_banned', true ) ) {
			$response['errors'][] = array(
				'id'    => 'banned',
				'field' => 'banned',
				'text'  => esc_html__( 'Sorry, it seems that your account has been temporarily blocked from taking this action. Please contact the website administrator for more information.', 'masterstudy-lms-learning-management-system' ),
			);

			return wp_send_json( $response );
		}

		$data = array(
			'become_instructor' => true,
			'fields_type'       => isset( $_POST['fields_type'] ) ? sanitize_text_field( wp_unslash( $_POST['fields_type'] ) ) : 'default',
		);

		if ( 'custom' === $_POST['fields_type'] && isset( $_POST['fields'] ) ) {
			$data['fields'] = $_POST['fields'];
			if ( ! empty( $data['fields'] ) ) {
				foreach ( $data['fields'] as $field ) {
					if ( ! empty( $field['required'] ) && $field['required'] && empty( $field['value'] ) ) {
						$response['errors'][] = array(
							'id'    => 'required',
							'field' => $field['slug'],
							'text'  => esc_html__( 'Field is required', 'masterstudy-lms-learning-management-system' ),
						);
					}
				}
			}
		} else {
			$data['degree']    = sanitize_text_field( $_POST['fields']['degree'] ?? '' );
			$data['expertize'] = sanitize_text_field( $_POST['fields']['expertize'] ?? '' );
		}

		if ( ! empty( $response['errors'] ) ) {
			return wp_send_json( $response );
		}

		STM_LMS_Instructor::become_instructor( $data, $user_id );

		$response['status'] = 'success';

		return wp_send_json( $response );
	}

	public static function enterprise() {
		check_ajax_referer( 'stm_lms_enterprise', 'nonce' );

		$response = array(
			'errors' => array(),
			'status' => 'error',
		);

		$default_fields = array(
			'enterprise_name'  => array(
				'label' => esc_html__( 'Name', 'masterstudy-lms-learning-management-system' ),
				'type'  => 'text',
			),
			'enterprise_email' => array(
				'label' => esc_html__( 'E-mail', 'masterstudy-lms-learning-management-system' ),
				'type'  => 'email',
			),
			'enterprise_text'  => array(
				'label' => esc_html__( 'Message', 'masterstudy-lms-learning-management-system' ),
				'type'  => 'text',
			),
		);

		$data = array(
			'fields_type' => isset( $_POST['fields_type'] ) ? sanitize_text_field( wp_unslash( $_POST['fields_type'] ) ) : 'default',
			'fields'      => $_POST['fields'] ?? array(),
		);

		if ( 'custom' === $data['fields_type'] && ! empty( $data['fields'] ) ) {
			$message   = '';
			$subject   = esc_html__( 'Enterprise Request', 'masterstudy-lms-learning-management-system' );
			$user_data = array(
				'date'      => date( 'Y-m-d H:i:s' ),
				'site_url'  => \MS_LMS_Email_Template_Helpers::link( \STM_LMS_Helpers::masterstudy_lms_get_site_url() ),
				'blog_name' => STM_LMS_Helpers::masterstudy_lms_get_site_name(),
			);

			foreach ( $data['fields'] as $field ) {
				if ( ! empty( $field['required'] ) && $field['required'] && empty( $field['value'] ) ) {
					$response['errors'][] = array(
						'id'    => 'required',
						'field' => $field['slug'],
						'text'  => esc_html__( 'Field is required', 'masterstudy-lms-learning-management-system' ),
					);
				}

				$label    = ! empty( $field['label'] ) ? esc_html( $field['label'] ) : $field['field_name'];
				$message .= $label . ' - ' . esc_html( $field['value'] ) . '<br>';
				if ( ! empty( $field['slug'] ) ) {
					$user_data[ $field['slug'] ] = $field['value'];
				}
			}

			if ( ! empty( $response['errors'] ) ) {
				return wp_send_json( $response );
			}

			STM_LMS_Helpers::send_email(
				'',
				$subject,
				$message,
				'stm_lms_enterprise',
				$user_data
			);
		} else {
			foreach ( $default_fields as $field_key => $field ) {
				if ( empty( $data['fields'][ $field_key ] ) ) {
					$response['errors'][] = array(
						'id'    => 'required',
						'field' => $field_key,
						'text'  => __( 'Field is required', 'masterstudy-lms-learning-management-system' ),
					);
				} else {
					$data['fields'][ $field_key ] = STM_LMS_Helpers::sanitize_fields( $data['fields'][ $field_key ], $field['type'] );
					if ( empty( $data['fields'][ $field_key ] ) ) {
						$response['errors'][] = array(
							'id'    => 'valid',
							'field' => $field_key,
							'text'  => 'enterprise_email' === $field_key ? esc_html__( 'Please enter a valid email', 'masterstudy-lms-learning-management-system' ) : esc_html__( 'Please enter valid value', 'masterstudy-lms-learning-management-system' ),
						);
					}
				}
			}

			if ( ! empty( $response['errors'] ) ) {
				return wp_send_json( $response );
			}

			$name    = $data['fields']['enterprise_name'];
			$email   = $data['fields']['enterprise_email'];
			$text    = $data['fields']['enterprise_text'];
			$date    = gmdate( 'Y-m-d H:i:s' );
			$subject = esc_html__( 'Enterprise Request', 'masterstudy-lms-learning-management-system' );

			$message = esc_html__( 'You have received a new enterprise inquiry', 'masterstudy-lms-learning-management-system' ) . ' <br/>' . // phpcs:disable
				esc_html__( 'from the "For Enterprise" form.', 'masterstudy-lms-learning-management-system' ) . ' <br/>' .
				esc_html__( 'Here are the details:', 'masterstudy-lms-learning-management-system' ) . ' <br/> ' .
				'<b>' . esc_html__( 'Name: ', 'masterstudy-lms-learning-management-system' ) . '</b>' . $name . ' <br>' .
				'<b>' . esc_html__( 'Email: ', 'masterstudy-lms-learning-management-system' ) . '</b>' . $email . ' <br>' .
				'<b>' . esc_html__( 'Message: ', 'masterstudy-lms-learning-management-system' ) . '</b>' . $text . ' <br>' .
				'<b>' . esc_html__( 'Submission Date: ', 'masterstudy-lms-learning-management-system' ) . '</b>' . $date . ' <br><br/>' .
				esc_html__( 'Please review this inquiry and follow up as needed.', 'masterstudy-lms-learning-management-system' ) . '</a> <br/>'; // phpcs:enable

			$email_data = array(
				'name'      => $name,
				'email'     => $email,
				'text'      => $text,
				'blog_name' => STM_LMS_Helpers::masterstudy_lms_get_site_name(),
				'site_url'  => \MS_LMS_Email_Template_Helpers::link( \STM_LMS_Helpers::masterstudy_lms_get_site_url() ),
				'date'      => gmdate( 'Y-m-d H:i:s' ),
			);

			STM_LMS_Helpers::send_email(
				'',
				$subject,
				$message,
				'stm_lms_enterprise',
				$email_data
			);
		}

		$response['status'] = 'success';

		return wp_send_json( $response );
	}

	public static function stm_lms_lost_password() {
		check_ajax_referer( 'stm_lms_lost_password', 'nonce' );

		$response     = array(
			'status' => 'success',
		);
		$request_body = file_get_contents( 'php://input' );
		$data         = json_decode( $request_body, true );
		$fields       = array( 'restore_user_login' );

		foreach ( $fields as $field ) {
			if ( empty( $data[ $field ] ) ) {
				$response['errors'][] = array(
					'id'    => 'required',
					'field' => 'restore_user_login',
					'text'  => esc_html__( 'Please fill email field', 'masterstudy-lms-learning-management-system' ),
				);
			}
		}

		if ( ! empty( $response['errors'] ) ) {
			$response['status'] = 'error';
			return wp_send_json( $response );
		}

		$get_user_by = is_email( $data['restore_user_login'] ) ? 'email' : 'login';
		$user_data   = get_user_by( $get_user_by, trim( $data['restore_user_login'] ) );
		if ( ! $user_data ) {
			$response['errors'][] = array(
				'id'    => 'no_account',
				'field' => 'restore_user_login',
				'text'  => __( 'There is no account with that username or email address.', 'masterstudy-lms-learning-management-system' ),
			);
			$response['status']   = 'error';
			return wp_send_json( $response );
		}

		// Redefining user_login ensures we return the right case in the email.
		$user_login = $user_data->user_login;
		$user_email = $user_data->user_email;
		$key        = get_password_reset_key( $user_data );

		if ( is_wp_error( $key ) ) {
			$response['errors'][] = array(
				'id'    => 'no_account',
				'field' => 'restore_user_login',
				'text'  => __( 'There is no account with that username or email address.', 'masterstudy-lms-learning-management-system' ),
			);
			$response['status']   = 'error';
			return wp_send_json( $response );
		}

		if ( is_multisite() ) {
			$site_name = get_network()->site_name;
		} else {
			$site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		}

		$token = $user_data->ID . '*' . bin2hex( openssl_random_pseudo_bytes( 16 ) );
		update_user_meta( $user_data->ID, 'restore_password_token', $token );
		$reset_url = add_query_arg( 'restore_password', $token, self::login_page_url() );

		$template = wp_kses_post(
			'Dear  {{user_login}},<br> There has been a request to reset your password for your account on {{blog_name}}.
					<br> To reset your password and set a new one, click on the link below: <br>
					<a href="{{reset_url}}" target="_blank">Reset url</a>
					<br>If you did not request this change, please ignore this email.'
		);

		$email_data = array(
			'user_login' => \STM_LMS_Helpers::masterstudy_lms_get_user_full_name_or_login( $user_data->ID ),
			'reset_url'  => $reset_url,
			'blog_name'  => $site_name,
			'site_url'   => \MS_LMS_Email_Template_Helpers::link( \STM_LMS_Helpers::masterstudy_lms_get_site_url() ),
			'date'       => gmdate( 'Y-m-d H:i:s' ),
		);

		$message = \MS_LMS_Email_Template_Helpers::render( $template, $email_data );
		$subject = esc_html__( 'Password Reset Request', 'masterstudy-lms-learning-management-system' );

		if ( ! empty( $admin_message ) ) {
			$message .= '<br>' . sanitize_text_field( $admin_message );
		}
		STM_LMS_Helpers::send_email(
			$user_email,
			$subject,
			$message,
			'stm_lms_email_user_reset_password',
			$email_data
		);

		return wp_send_json( $response );
	}

	public static function stm_lms_change_avatar( $user = array(), $files = array(), $return = false ) {
		check_ajax_referer( 'stm_lms_change_avatar', 'nonce' );

		if ( empty( $files ) ) {
			$files = $_FILES;
		}

		$is_valid_image = Validation::is_valid(
			$files,
			array(
				'file' => 'required_file|extension,png;jpg;jpeg',
			)
		);

		if ( true !== $is_valid_image ) {

			$res = array(
				'error'   => true,
				'message' => $is_valid_image[0],
			);

			if ( $return ) {
				return $res;
			} else {
				wp_send_json( $res );
			}
		}

		if ( empty( $user ) ) {
			$user = self::get_current_user();
		}

		if ( empty( $user['id'] ) ) {
			die;
		}

		/*Create directory*/
		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$dir      = wp_upload_dir();
		$base_dir = $dir['basedir'] . '/stm_lms_avatars';
		$base_url = $dir['baseurl'] . '/stm_lms_avatars';

		if ( ! is_dir( $base_dir ) ) {
			wp_mkdir_p( $base_dir );
		}

		$file_upload    = $files['file']['tmp_name'];
		$file_extension = pathinfo( $files['file']['name'], PATHINFO_EXTENSION );
		$file_name      = 'stm_lms_avatar' . $user['id'] . '.' . $file_extension;
		$file           = "{$base_dir}/{$file_name}";

		if ( file_exists( $file ) ) {
			unlink( $file );
		}

		move_uploaded_file( $file_upload, $file );

		$image = wp_get_image_editor( $file );
		if ( ! is_wp_error( $image ) ) {
			$image->resize( 512, 512, true );
			$image->save( $file );
		}

		if ( apply_filters( 'stm_lms_update_user_avatar', true ) ) {
			update_user_meta( $user['id'], 'stm_lms_user_avatar', "{$base_url}/{$file_name}?v=" . time() );
		}

		$res = array(
			'file' => "{$base_url}/{$file_name}?v=" . time(),
		);

		if ( ! $return ) {
			wp_send_json( $res );
		}

		return $res;
	}

	public static function stm_lms_delete_avatar() {
		check_ajax_referer( 'stm_lms_delete_avatar', 'nonce' );

		$user = self::get_current_user();
		if ( empty( $user['id'] ) ) {
			die;
		}

		update_user_meta( $user['id'], 'stm_lms_user_avatar', '' );

		wp_send_json(
			array(
				'file' => $avatar = get_avatar( $user['id'], '215' ),
			)
		);
	}

	public function stm_lms_change_cover() {
		check_ajax_referer( 'stm_lms_change_cover', 'nonce' );
		$extensions = 'png;jpg;jpeg;mp4;pdf';

		$user = self::get_current_user();

		if ( empty( $user['id'] ) ) {
			return;
		}

		if ( ! empty( $_POST['extensions'] ) ) {
			$extensions = sanitize_text_field( $_POST['extensions'] );
			$extensions = preg_replace( '/\s+/', '', $extensions );
			$extensions = str_replace( '.', '', $extensions );
			$extensions = str_replace( ',', ';', $extensions );
		}

		$is_valid_image = Validation::is_valid(
			$_FILES,
			array(
				'file' => 'required_file|extension,' . $extensions,
			)
		);

		if ( true !== $is_valid_image ) {
			return wp_send_json(
				array(
					'error'   => true,
					'message' => sprintf(
						/* translators: %s string */
						__( 'Field can only have one of the following extensions: %s', 'masterstudy-lms-learning-management-system-pro' ),
						esc_html( $extensions )
					),
				)
			);
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		if ( apply_filters( 'stm_lms_update_user_cover', true ) ) {
			$attachment_id = media_handle_upload( 'file', 0 );

			if ( is_wp_error( $attachment_id ) ) {
				return wp_send_json(
					array(
						'error'   => true,
						'message' => $attachment_id->get_error_message(),
					)
				);
			}

			update_user_meta( $user['id'], 'stm_lms_user_cover', $attachment_id );

			return wp_send_json(
				array(
					'files' => $_FILES,
					'id'    => $attachment_id,
					'url'   => wp_get_attachment_url( $attachment_id ),
					'error' => false,
				)
			);
		}

		return wp_send_json(
			apply_filters(
				'stm_lms_update_user_cover_error',
				array(
					'error'   => true,
					'message' => esc_html__( 'Something went wrong', 'masterstudy-lms-learning-management-system' ),
				)
			)
		);
	}

	public function stm_lms_delete_cover() {
		check_ajax_referer( 'stm_lms_delete_cover', 'nonce' );

		if ( empty( $_POST['file_id'] ) ) {
			return;
		}

		$user = self::get_current_user();

		if ( empty( $user['id'] ) ) {
			return;
		}

		if ( apply_filters( 'stm_lms_update_user_cover', true ) ) {
			wp_delete_attachment( intval( $_POST['file_id'] ), true );
			update_user_meta( $user['id'], 'stm_lms_user_cover', '' );
		}

		return wp_send_json( 'OK' );
	}

	public static function check_restore_token( $token ) {
		$token_parts = explode( '*', $token );
		if ( ! is_array( $token_parts ) && count( $token_parts ) !== 2 ) {
			return false;
		}

		$user_id        = $token_parts[0];
		$original_token = get_user_meta( $user_id, 'restore_password_token', true );

		return ( $original_token === $token ) ? intval( $user_id ) : false;
	}

	public static function stm_lms_restore_password() {
		check_ajax_referer( 'stm_lms_restore_password', 'nonce' );

		$request_body    = file_get_contents( 'php://input' );
		$data            = json_decode( $request_body, true );
		$token           = sanitize_text_field( $data['token'] );
		$password        = sanitize_text_field( $data['new_password'] );
		$repeat_password = sanitize_text_field( $data['repeat_password'] );
		$response        = array(
			'errors' => array(),
			'status' => 'error',
		);

		$user_id = self::check_restore_token( $token );

		if ( empty( $user_id ) ) {
			$response['errors'][] = array(
				'id'    => 'token',
				'field' => 'user_new_password',
				'text'  => esc_html__( 'Your token expired, try again', 'masterstudy-lms-learning-management-system' ),
			);

			return wp_send_json( $response );
		}

		$weak_password = STM_LMS_Options::get_option( 'registration_weak_password', false );

		if ( ! $weak_password && ! empty( $register_user_password ) ) {
			if ( strlen( $password ) < 8 ) {
				$response['errors'][] = array(
					'id'    => 'characters',
					'field' => 'user_new_password',
					'text'  => esc_html__( 'Password must have at least 8 characters', 'masterstudy-lms-learning-management-system' ),
				);
			}
			/* if contains letter */
			if ( ! preg_match( '#[a-z]+#', $password ) ) {
				$response['errors'][] = array(
					'id'    => 'lowercase',
					'field' => 'user_new_password',
					'text'  => esc_html__( 'Password must include at least one lowercase letter!', 'masterstudy-lms-learning-management-system' ),
				);
			}
			/* if contains number */
			if ( ! preg_match( '#[0-9]+#', $password ) ) {
				$response['errors'][] = array(
					'id'    => 'number',
					'field' => 'user_new_password',
					'text'  => esc_html__( 'Password must include at least one number!', 'masterstudy-lms-learning-management-system' ),
				);
			}
			/* if contains CAPS */
			if ( ! preg_match( '#[A-Z]+#', $password ) ) {
				$response['errors'][] = array(
					'id'    => 'capital',
					'field' => 'user_new_password',
					'text'  => esc_html__( 'Password must include at least one capital letter!', 'masterstudy-lms-learning-management-system' ),
				);
			}
		}

		if ( ! empty( $repeat_password ) && $password !== $repeat_password ) {
			$response['errors'][] = array(
				'id'    => 'not_match',
				'field' => 'user_repeat_new_password',
				'text'  => esc_html__( 'Passwords do not match', 'masterstudy-lms-learning-management-system' ),
			);
		}

		if ( ! empty( $response['errors'] ) ) {
			return wp_send_json( $response );
		}

		$user = get_user_by( 'id', $user_id );

		if ( $user ) {
			wp_set_password( $password, $user_id );
			delete_user_meta( $user_id, 'restore_password_token' );

			$user_data = array(
				'user_login'    => $user->user_login,
				'user_password' => $password,
			);

			$user_signon = wp_signon( $user_data, is_ssl() );

			if ( ! is_wp_error( $user_signon ) ) {
				$response['status'] = 'success';
			} else {
				$response['errors'][] = array(
					'id'    => 'not_user',
					'field' => 'user_new_password',
					'text'  => $user_signon->get_error_message(),
				);
			}
		} else {
			$response['errors'][] = array(
				'id'    => 'not_user',
				'field' => 'user_new_password',
				'text'  => esc_html__( 'User not found', 'masterstudy-lms-learning-management-system' ),
			);
		}

		return wp_send_json( $response );
	}

	public static function become_instructor_block( $current_user ) {
		if ( empty( $current_user['roles'] ) ) {
			$current_user = self::get_current_user( '', true, true );
		}
		$register_as_instructor = STM_LMS_Options::get_option( 'register_as_instructor', true );

		if ( ! empty( $current_user ) && ! empty( $register_as_instructor ) && ! empty( $current_user['roles'] ) ) {
			if ( ! in_array( 'stm_lms_instructor', $current_user['roles'] ) && ! in_array( 'administrator', $current_user['roles'] ) ) { // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
				STM_LMS_Templates::show_lms_template( 'account/private/parts/become_instructor', array( 'current_user' => $current_user ) );
			}
		}
	}

	public static function hide_become_instructor_notice() {
		check_ajax_referer( 'stm_lms_hide_become_instructor_notice', 'nonce' );
		if ( ! empty( $_POST['user_id'] ) ) {
			$user_id = intval( $_POST['user_id'] );
			$history = get_user_meta( $user_id, 'submission_history', true );
			if ( ! empty( $history ) && is_array( $history ) && ! empty( $history[0] && empty( $history[0]['viewed'] ) ) ) {
				$history[0]['viewed'] = 1;
				update_user_meta( $user_id, 'submission_history', $history );
			}
		}
		die();
	}

	public static function settings_url() {
		return ms_plugin_user_account_url( 'settings' );
	}

	public static function my_pmpro_url() {
		return ms_plugin_user_account_url( 'memberships-pmp' );
	}

	/**
	 * @deprecated
	 */
	public static function my_announcements_url() {
		return ms_plugin_user_account_url( 'announcement' );
	}
}
