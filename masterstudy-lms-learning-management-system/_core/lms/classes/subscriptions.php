<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

STM_LMS_Subscriptions::init();

class STM_LMS_Subscriptions {
	public static function init() {
		add_action( 'pmpro_membership_level_after_other_settings', 'STM_LMS_Subscriptions::stm_lms_pmpro_settings' );
		add_action( 'pmpro_save_membership_level', 'STM_LMS_Subscriptions::stm_lms_pmpro_save_settings' );

		add_action( 'wp_ajax_stm_lms_use_membership', 'STM_LMS_Subscriptions::use_membership' );
		add_action( 'wp_ajax_nopriv_stm_lms_use_membership', 'STM_LMS_Subscriptions::use_membership' );

		add_action( 'pmpro_after_change_membership_level', 'STM_LMS_Subscriptions::subscription_changed', 10, 3 );
		add_action( 'pmpro_before_change_membership_level', 'STM_LMS_Subscriptions::before_subscription_change', 10, 4 );

		add_action( 'wp_ajax_stm_lms_change_featured', 'STM_LMS_Subscriptions::featured_status' );

		add_action( 'wp_ajax_stm_lms_delete_course_subscription', 'STM_LMS_Subscriptions::remove_subscription_course' );

		add_action( 'wp_ajax_stm_lms_get_course_cookie_redirect', 'STM_LMS_Subscriptions::stm_lms_get_course_cookie_redirect' );
		add_action( 'wp_ajax_nopriv_stm_lms_get_course_cookie_redirect', 'STM_LMS_Subscriptions::stm_lms_get_course_cookie_redirect' );

		add_action( 'wp_ajax_stm_lms_toggle_buying', 'STM_LMS_Subscriptions::admin_toggle_buying' );

		add_action(
			'pmpro_invoice_bullets_top',
			function ( $invoice ) {
				if ( isset( $_COOKIE['stm_lms_course_buy'] ) ) {
					$course_id = intval( $_COOKIE['stm_lms_course_buy'] );
					if ( get_post_type( $course_id ) === 'stm-courses' ) {
						stm_lms_register_script( 'buy/redirect_to_cookie', array( 'jquery.cookie' ), true );
					}
				}
			}
		);

		add_filter(
			'pmpro_confirmation_message',
			function ( $message ) {
				return "<div class='stm_lms_pmpro_message'>{$message}</div>";
			},
			99
		);

		/* Get membership modal data */
		add_filter( 'masterstudy_membership_modal_data', array( self::class, 'masterstudy_membership_modal_data' ), 10, 2 );
	}

	public static function admin_toggle_buying() {
		check_ajax_referer( 'stm_lms_toggle_buying', 'nonce' );

		$method   = sanitize_text_field( $_GET['m'] );
		$category = sanitize_text_field( $_GET['c'] );
		$terms    = explode( ',', $category );
		$r        = array(
			'next'    => '',
			'message' => '',
		);

		foreach ( $terms as $term ) {
			$args = array(
				'post_type'      => 'stm-courses',
				'posts_per_page' => 1,
				'tax_query'      => array(
					array(
						'taxonomy' => 'stm_lms_course_taxonomy',
						'field'    => 'id',
						'terms'    => $term,
					),
				),
			);

			if ( 'disable' === $method ) {
				$args['meta_query'] = array(
					array(
						'key'     => 'single_sale',
						'compare' => '=',
						'value'   => 'on',
					),
				);
			} else {
				$args['meta_query'] = array(
					array(
						'key'     => 'single_sale',
						'compare' => '!=',
						'value'   => 'on',
					),
				);
			}

			$q = new WP_Query( $args );

			if ( $q->have_posts() ) {
				while ( $q->have_posts() ) {
					$q->the_post();

					$id        = get_the_ID();
					$r['next'] = 'going_next';

					update_post_meta( $id, 'single_sale', ( 'disable' === $method ) ? '' : 'on' );
				}
				wp_reset_postdata();
			}
		}

		if ( 'disable' === $method ) {
			$r['message'] = sprintf( esc_html__( 'All courses in the selected categories are disabled.', 'masterstudy-lms-learning-management-system' ) );
		} else {
			$r['message'] = sprintf( esc_html__( 'All courses in the selected categories are enabled.', 'masterstudy-lms-learning-management-system' ) );
		}

		wp_send_json( $r );
	}

	public static function stm_lms_get_course_cookie_redirect() {
		$r = array();

		$course_id = $_GET['course_id'] ?? null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( get_post_type( $course_id ) === 'stm-courses' ) {
			$r['url'] = get_permalink( $course_id );
		}

		wp_send_json( $r );
	}

	public static function _use_membership( $user_id, $course_id, $membership_id ) { // phpcs:ignore
		$r = array();

		/*Check if user already has course*/
		$courses = stm_lms_get_user_course( $user_id, $course_id, array( 'user_course_id' ) );
		if ( ! empty( $courses ) ) {
			stm_lms_update_start_time_in_user_course( $user_id, $course_id );
		} else {
			$sub = self::user_subscriptions( null, null, $membership_id );

			$r['sub'] = $sub;

			$subs = self::user_subscription_levels();

			$sub = null;
			if ( ! empty( $membership_id ) && ! empty( $subs ) ) {
				foreach ( $subs as $subscription ) {
					if ( intval( $subscription->ID ) === intval( $membership_id ) ) {
						$sub = $subscription;
						break;
					}
				}
			}

			if ( is_null( $sub ) && ! empty( $subs ) ) {
				$sub = reset( $subs );
			}

			if ( $sub instanceof stdClass && ! empty( $sub->quotas_left ) ) {
				$progress_percent          = 0;
				$current_lesson_id         = 0;
				$status                    = 'enrolled';
				$subscription_id           = $membership_id;
				$user_course               = compact( 'user_id', 'course_id', 'current_lesson_id', 'status', 'progress_percent', 'subscription_id' );
				$user_course['start_time'] = time();

				if ( is_ms_lms_addon_enabled( 'grades' ) && masterstudy_lms_is_course_gradable( $course_id ) ) {
					$user_course['is_gradable'] = 1;
				}

				stm_lms_add_user_course( $user_course );

				STM_LMS_Course::add_student( $course_id );

				$r['url'] = get_the_permalink( $course_id );
			}

			if ( class_exists( 'STM_LMS_Mails' ) ) {
				$user            = STM_LMS_User::get_current_user( $user_id );
				$login           = $user['login'];
				$course_title    = get_the_title( $course_id );
				$membership_plan = $sub->name;

				$email_data = array(
					'membership_plan' => $membership_plan,
					'course_title'    => $course_title,
					'blog_name'       => STM_LMS_Helpers::masterstudy_lms_get_site_name(),
					'site_url'        => \MS_LMS_Email_Template_Helpers::link( \STM_LMS_Helpers::masterstudy_lms_get_site_url() ),
					'date'            => gmdate( 'Y-m-d H:i:s' ),
					'login'           => $login,
					'user_login'      => STM_LMS_Helpers::masterstudy_lms_get_user_full_name_or_login( $user_id ),
					'course_url'      => \MS_LMS_Email_Template_Helpers::link( get_permalink( $course_id ) ),
				);

				$message = sprintf(
				/* translators: %1$s Course Title, %2$s User Login */
					esc_html__( 'Course %1$s was added to %2$s. with %3$s', 'masterstudy-lms-learning-management-system' ),
					$course_title,
					$login,
					$membership_plan
				);
				STM_LMS_Helpers::send_email( '', 'Course added.', $message, 'stm_lms_membership_course_available_for_admin', $email_data );

				$message = sprintf(
				/* translators: %1$s Course Title, %2$s User Login */
					esc_html__( 'Course %1$s is now available to learn with %2$s', 'masterstudy-lms-learning-management-system' ),
					$course_title,
					$membership_plan
				);
				STM_LMS_Helpers::send_email( $user['email'], 'Course added to User', $message, 'stm_lms_membership_course_available_for_user', $email_data );
			}
		}

		return $r;
	}

	public static function use_membership() {
		check_ajax_referer( 'stm_lms_use_membership', 'nonce' );

		/*Check if has course id*/
		if ( empty( $_GET['course_id'] ) ) {
			die;
		}

		$course_id = intval( $_GET['course_id'] );

		/*Check if logged in*/
		$current_user = STM_LMS_User::get_current_user();

		if ( empty( $current_user['id'] ) ) {
			die;
		}
		$user_id = $current_user['id'];

		$membership_id = ( ! empty( $_GET['membership_id'] ) ) ? intval( $_GET['membership_id'] ) : '';

		wp_send_json( self::_use_membership( $user_id, $course_id, $membership_id ) );
	}

	public static function subscription_enabled() {
		return ( defined( 'PMPRO_VERSION' ) );
	}

	public static function level_url() {
		if ( ! self::subscription_enabled() ) {
			return false;
		}

		$membership_levels = pmpro_getOption( 'levels_page_id' );
		return ( get_the_permalink( $membership_levels ) );
	}

	public static function checkout_url() {
		if ( ! self::subscription_enabled() ) {
			return false;
		}

		$checkout_page = pmpro_getOption( 'checkout_page_id' );
		return ( get_the_permalink( $checkout_page ) );
	}

	public static function user_has_subscription( $user_id ) {
		if ( ! self::subscription_enabled() ) {
			return false;
		}

		return ! empty( pmpro_getMembershipLevelForUser( $user_id ) );
	}

	public static function user_subscriptions( $all = false, $user_id = '', $subscription_id = '*' ) {

		if ( ! self::subscription_enabled() ) {
			return false;
		}

		$subs = object;

		if ( is_user_logged_in() && function_exists( 'pmpro_hasMembershipLevel' ) && pmpro_hasMembershipLevel() ) {
			if ( empty( $user_id ) ) {
				$user = STM_LMS_User::get_current_user();
				if ( empty( $user['id'] ) ) {
					return $subs;
				}
				$user_id = $user['id'];
			}
			$subs = pmpro_getMembershipLevelForUser( $user_id );

			$subscriptions = ( ! empty( $subs->ID ) ) ? count( stm_lms_get_user_courses_by_subscription( $user_id, $subscription_id, array( 'user_course_id' ), 0 ) ) : 0;

			if ( ! empty( $subs ) ) {
				$subs->course_number = ( ! empty( $subs->ID ) ) ? self::get_course_number( $subs->ID ) : 0;
				$subs->used_quotas   = $subscriptions;
				$subs->quotas_left   = $subs->course_number - $subs->used_quotas;
			}
		}

		return $subs;
	}

	public static function user_subscription_levels( $all = false, $user_id = '' ) {
		if ( ! self::subscription_enabled() ) {
			return false;
		}

		$data = array();

		if ( is_user_logged_in() && function_exists( 'pmpro_hasMembershipLevel' ) && pmpro_hasMembershipLevel() ) {

			if ( empty( $user_id ) ) {
				$user = STM_LMS_User::get_current_user();
				if ( empty( $user['id'] ) ) {
					return $data;
				}
				$user_id = $user['id'];
			}

			$levels = pmpro_getMembershipLevelsForUser( $user_id );

			if ( ! empty( $levels ) ) {
				foreach ( $levels as $subs ) {
					$subscriptions = ( ! empty( $subs->ID ) ) ? count( stm_lms_get_user_courses_by_subscription( $user_id, $subs->subscription_id, array( 'user_course_id' ), 0 ) ) : 0;

					$subs->course_number = ( ! empty( $subs->ID ) ) ? self::get_course_number( $subs->ID ) : 0;
					$subs->used_quotas   = $subscriptions;
					$subs->quotas_left   = $subs->course_number - $subs->used_quotas;

					$data[] = $subs;
				}
			}
		}

		return $data;
	}

	public static function save_course_number( $level_id ) {
		if ( isset( $_REQUEST['stm_lms_course_number'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			update_option(
				'stm_lms_course_number_' . $level_id,
				sanitize_text_field( $_REQUEST['stm_lms_course_number'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			);
		}
		if ( isset( $_REQUEST['stm_lms_featured_courses_number'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			update_option(
				'stm_lms_featured_courses_number_' . $level_id,
				intval( $_REQUEST['stm_lms_featured_courses_number'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			);
		}
		if ( isset( $_REQUEST['stm_lms_plan_group'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			update_option(
				'stm_lms_plan_group_' . $level_id,
				sanitize_text_field( $_REQUEST['stm_lms_plan_group'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			);
		}

		if ( isset( $_POST['stm_lms_course_private_category'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$terms = array();
			if ( is_array( $_POST['stm_lms_course_private_category'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				foreach ( $_POST['stm_lms_course_private_category'] as $selected_option ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
					$selected_option = sanitize_text_field( $selected_option );
					array_push( $terms, $selected_option );
				}
			}
			update_option( 'stm_lms_plan_private_category_' . $level_id, $terms );
		}
	}

	public static function get_course_number( $level_id ) {
		$course_limit = get_option( 'stm_lms_course_number_' . $level_id, 0 );
		if ( 'unlim' === $course_limit || 'unlimited' === $course_limit ) {
			$course_limit = 1000001;
		}
		return $course_limit;
	}

	public static function get_featured_courses_number( $level_id ) {
		return get_option( 'stm_lms_featured_courses_number_' . $level_id, 0 );
	}

	public static function get_plan_group( $level_id ) {
		return get_option( 'stm_lms_plan_group_' . $level_id, 0 );
	}

	public static function get_plan_private_category( $level_id ) {
		return get_option( 'stm_lms_plan_private_category_' . $level_id, 0 );
	}

	public static function stm_lms_pmpro_settings() {
		$level_id        = ( ! empty( $_GET['edit'] ) ) ? intval( $_GET['edit'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$course_limit    = get_option( 'stm_lms_course_number_' . $level_id, 0 );
		$course_number   = $course_limit;
		$course_featured = self::get_featured_courses_number( $level_id );
		$plan_group      = self::get_plan_group( $level_id );
		$category        = self::get_plan_private_category( $level_id );
		$terms           = stm_lms_get_terms_for_membership( 'stm_lms_course_taxonomy', array( 'hide_empty' => false ), false );

		if ( empty( $plan_group ) ) {
			$plan_group = '';
		}

		stm_lms_register_script( 'admin/pmpro', array( 'vue.js', 'vue-resource.js' ) );
		?>

		<h3 class="topborder"><?php esc_html_e( 'STM LMS Settings', 'masterstudy-lms-learning-management-system' ); ?></h3>
		<table class="form-table">
			<tbody>
			<tr class="membership_categories">
				<th scope="row" valign="top">
					<label><?php esc_html_e( 'Number of available courses in subscription', 'masterstudy-lms-learning-management-system' ); ?>: </label>
				</th>
				<td>
					<input name="stm_lms_course_number" type="text" size="10" title="Number of available" value="<?php echo esc_attr( $course_number ); ?>" />
					<small><?php esc_html_e( 'User can enroll several courses after subscription. Enter "unlim" to grant access to an unlimited number of courses', 'masterstudy-lms-learning-management-system' ); ?></small>
				</td>
			</tr>

			<tr class="membership_categories">
				<th scope="row" valign="top">
					<label><?php esc_html_e( 'Number of featured courses quote in subscription', 'masterstudy-lms-learning-management-system' ); ?>: </label>
				</th>
				<td>
					<input name="stm_lms_featured_courses_number" type="text" size="10" title="Number of featured" value="<?php echo esc_attr( $course_featured ); ?>" />
					<small><?php esc_html_e( 'Instructors can mark their courses as featured', 'masterstudy-lms-learning-management-system' ); ?></small>
				</td>
			</tr>

			<tr class="membership_categories">
				<th scope="row" valign="top">
					<label><?php esc_html_e( 'Plan tab name', 'masterstudy-lms-learning-management-system' ); ?>:</label>
				</th>
				<td>
					<input name="stm_lms_plan_group" type="text" size="10" title="Plan tab name" value="<?php echo esc_attr( $plan_group ); ?>" />
					<small><?php esc_html_e( 'Show plans under the different tabs (tabs with the same name will be displayed together)', 'masterstudy-lms-learning-management-system' ); ?></small>
				</td>
			</tr>

			<?php if ( ! empty( $terms ) ) : ?>

				<tr class="membership_categories">
					<th colspan="2">
						<h3><?php esc_attr_e( 'Private category', 'masterstudy-lms-learning-management-system' ); ?></h3>
					</th>
				</tr>

				<tr class="membership_categories">
					<th scope="row" valign="top">
						<label><?php esc_html_e( 'Courses category available for this plan', 'masterstudy-lms-learning-management-system' ); ?>: </label>
					</th>
					<td>
						<select name="stm_lms_course_private_category[]" multiple="multiple" title="Courses category">
							<?php
							foreach ( $terms as $term_id => $term_label ) :
								if ( '' === $term_id ) {
									continue;
								} else {
									?>
									<option
										value="<?php echo esc_attr( $term_id ); ?>"
										<?php
										// phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
										if ( is_array( $category ) && in_array( $term_id, $category ) ) {
											echo 'selected="selected"';
										}
										?>
									>
										<?php echo esc_html( $term_label ); ?>
									</option>
									<?php
								}
							endforeach;
							?>
						</select>
						<small><?php esc_html_e( 'User can enroll several courses from chosen category after subscription', 'masterstudy-lms-learning-management-system' ); ?></small>
						<p class="stm_membership_notice" style="display:none;color:#FF3333;"><?php esc_html_e( 'You need to select courses category for this plan!', 'masterstudy-lms-learning-management-system' ); ?></p>
					</td>
				</tr>

			<?php endif; ?>

			<tr class="membership_categories toggle_all_category_courses" style="display: none;">
				<th scope="row" valign="top">
					<label class="toggle_all_category_courses__label">
						<?php esc_html_e( 'Disable/Enable one-time purchase on courses in category - ', 'masterstudy-lms-learning-management-system' ); ?>
						<strong style="color : #195ec8"></strong> </label>
				</th>
				<td>
					<div id="toggle_all_category_courses">
						<!--not_single_sale-->
						<div class="" v-if="!inProgress">
							<a href="#" class="button button-primary" style="margin-right: 7px" @click.prevent="toggle('disable')">
								<?php esc_html_e( 'Disable for all', 'masterstudy-lms-learning-management-system' ); ?>
							</a>
							<a href="#" class="button button-primary" @click.prevent="toggle('enable')">
								<?php esc_html_e( 'Enable for all', 'masterstudy-lms-learning-management-system' ); ?>
							</a>
						</div>

						<div v-else>
							<div class="toggle_all_category_courses__course" v-html="current_course"></div>
							<small><?php esc_html_e( 'Do not reload the page...', 'masterstudy-lms-learning-management-system' ); ?></small>
						</div>
					</div>
				</td>
			</tr>

			</tbody>
		</table>
		<?php
	}

	public static function stm_lms_pmpro_save_settings( $level_id ) {
		self::save_course_number( $level_id );
		return $level_id;
	}

	public static function check_user_current_subs() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		self::remove_overquoted( get_current_user_id() );
	}

	public static function check_user_subscription_courses( $user_id, $is_cancelled ) {
		if ( empty( $is_cancelled ) ) {
			self::remove_overquoted( $user_id );
		}
	}

	public static function remove_overquoted( $user_id ) {
		/*Delete overquoted courses only*/
		$sub_info = self::user_subscriptions( true, $user_id );

		if ( ! empty( $sub_info->quotas_left ) && $sub_info->quotas_left < 0 ) {
			$limit = $sub_info->used_quotas - $sub_info->course_number;

			$courses = stm_lms_get_user_courses_by_subscription(
				$user_id,
				'*',
				array( 'course_id', 'start_time' ),
				$limit,
				'start_time ASC'
			);

			if ( ! empty( $courses ) ) {
				foreach ( $courses as $course ) {
					stm_lms_get_delete_user_course( $user_id, $course['course_id'] );
				}
			}
		}
	}

	public static function remove_subscription_course() {
		check_ajax_referer( 'stm_lms_delete_course_subscription', 'nonce' );

		if ( empty( $_GET['course_id'] ) ) {
			die;
		}

		$user_id = get_current_user_id();
		if ( empty( $user_id ) ) {
			die;
		}

		$course_id = intval( $_GET['course_id'] );

		stm_lms_get_delete_user_course( $user_id, $course_id );

		wp_send_json( array( 'success' ) );
	}

	/*FEATURED*/
	public static function featured_status() {
		check_ajax_referer( 'stm_lms_change_featured', 'nonce' );

		$user = STM_LMS_User::get_current_user();

		if ( empty( $user['id'] ) || empty( $_GET['post_id'] ) ) {
			die;
		}

		$post_id  = intval( $_GET['post_id'] );
		$featured = get_post_meta( $post_id, 'featured', true );
		$featured = ( empty( $featured ) ) ? 'on' : '';
		$quota    = self::get_featured_quota();

		if ( ! $quota ) {
			$featured = '';
		}

		update_post_meta( $post_id, 'featured', $featured );

		if ( self::get_featured_quota() < 0 ) {
			self::check_user_featured_courses();
		}

		wp_send_json(
			array(
				'featured'        => $featured,
				'total_quota'     => self::default_featured_quota() + self::pmpro_plan_quota(),
				'available_quota' => self::get_featured_quota(),
				'used_quota'      => self::get_user_featured_count(),
			)
		);
	}

	public static function before_subscription_change( $level_id, $user_id, $old_levels, $cancelled_level_id ) {
		self::check_user_featured_courses();
		self::check_user_subscription_courses( $user_id, $cancelled_level_id );
	}

	public static function subscription_changed( $level_id, $user_id, $cancelled_level_id ) {
		self::check_user_featured_courses();
		self::check_user_subscription_courses( $user_id, $cancelled_level_id );
	}

	public static function check_user_featured_courses() {
		$my_quota        = self::get_featured_quota();
		$available_quota = self::default_featured_quota() + self::pmpro_plan_quota();

		if ( $my_quota < 0 ) {
			$args = array(
				'post_type'        => 'stm-courses',
				'post_status'      => 'publish',
				'orderby'          => 'date',
				'order'            => 'ASC',
				'suppress_filters' => true,
				'offset'           => $available_quota,
				'posts_per_page'   => -1,
				'meta_query'       => array(
					array(
						'key'     => 'featured',
						'value'   => 'on',
						'compare' => '=',
					),
				),
			);

			$q = new WP_Query( $args );

			if ( $q->have_posts() ) {
				while ( $q->have_posts() ) {
					$q->the_post();
					update_post_meta( get_the_ID(), 'featured', '' );
				}
			}
		}
	}

	public static function default_featured_quota() {
		$options = get_option( 'stm_lms_settings', array() );
		$quota   = isset( $options['courses_featured_num'] ) ? intval( $options['courses_featured_num'] ) : 1;

		return apply_filters( 'stm_lms_default_featured_quota', $quota );
	}

	public static function pmpro_plan_quota( $user_id = '' ) {
		if ( ! self::subscription_enabled() ) {
			return 0;
		}

		$subs = 0;

		if ( is_user_logged_in() && function_exists( 'pmpro_hasMembershipLevel' ) && pmpro_hasMembershipLevel() ) {
			if ( empty( $user_id ) ) {
				$user = STM_LMS_User::get_current_user();
				if ( empty( $user['id'] ) ) {
					return $subs;
				}
				$user_id = $user['id'];
			}
			$subs = pmpro_getMembershipLevelForUser( $user_id );

			$subs = self::get_featured_courses_number( $subs->id );
		}

		return intval( $subs );
	}

	public static function get_user_featured_count( $user_id = '' ) {
		if ( empty( $user_id ) ) {
			$user = STM_LMS_User::get_current_user();
			if ( empty( $user['id'] ) ) {
				return 0;
			}
			$user_id = $user['id'];
		}

		$args = array(
			'post_type'      => 'stm-courses',
			'post_status'    => array( 'publish' ),
			'posts_per_page' => - 1,
			'author'         => $user_id,
			'meta_query'     => array(
				array(
					'key'     => 'featured',
					'value'   => 'on',
					'compare' => '=',
				),
			),
		);

		$q = new WP_Query( $args );

		return $q->found_posts;
	}

	public static function get_featured_quota() {
		return self::default_featured_quota() + self::pmpro_plan_quota() - self::get_user_featured_count();
	}

	public static function get_membership_status_by_course( $user_id, $course_subscription_id ) {
		$membership_status   = false;
		$membership_expired  = false;
		$membership_inactive = true;

		global $wpdb;
		$table = $wpdb->prefix . 'pmpro_memberships_users';

		// Check if the table exists
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$wpdb->esc_like( $table )
			)
		);

		if ( $table_exists !== $table ) {
			return array(
				'membership_status'   => false,
				'membership_expired'  => false,
				'membership_inactive' => true,
			);
		} // phpcs:disable
		$membership_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT membership_id FROM {$table} WHERE id = %d",
				$course_subscription_id
			)
		);

		$rows = array();

		if ( $membership_id ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE membership_id = %d AND user_id = %d ORDER BY id ASC",
					$membership_id,
					$user_id
				),
				ARRAY_A
			);
		} // phpcs:enable
		foreach ( $rows as $membership ) {
			$membership_status = $membership['status'];

			if ( 'active' === $membership_status || 'changed' === $membership_status ) {
				$membership_inactive = false;
				break;
			} elseif ( 'expired' === $membership_status ) {
				$membership_expired  = true;
				$membership_inactive = false;
			} elseif ( 'cancelled' === $membership_status ) {
				$membership_inactive = true;
			}
		}

		return array(
			'membership_status'   => $membership_status,
			'membership_expired'  => $membership_expired,
			'membership_inactive' => $membership_inactive,
		);
	}


	public static function membership_plan_available() {
		global $wpdb;
		$result = $wpdb->get_row( "SELECT * FROM {$wpdb->pmpro_membership_levels}", ARRAY_A );

		return ! empty( $result );
	}

	public static function masterstudy_membership_modal_data( $post_id, $membership_list ) {
		global $masterstudy_course_player_template;

		$user     = STM_LMS_User::get_current_user();
		$settings = get_option( 'stm_lms_settings' );

		$subscription    = array_shift( $membership_list );
		$subs_levels     = self::user_subscription_levels();
		$subscription_id = reset( $subs_levels )->ID;
		$user_approval   = get_user_meta( $user['id'], 'pmpro_approval_' . $subscription_id, true );
		$needs_approval  = ! empty( $user_approval['status'] ) && in_array( $user_approval['status'], array( 'pending', 'denied' ), true );

		$data = array(
			'post_id'         => $post_id,
			'membership_list' => $membership_list,
			'subscription'    => $subscription,
			'needs_approval'  => $needs_approval,
			'theme_fonts'     => $settings['course_player_theme_fonts'] ?? false,
		);
		if ( is_user_logged_in() ) {
			$user_mode         = get_user_meta( $user['id'], 'masterstudy_course_player_theme_mode', true );
			$data['dark_mode'] = ! empty( $user_mode ) ? $user_mode : $masterstudy_course_player_template && ( $settings['course_player_theme_mode'] ?? false );
		} else {
			$data['dark_mode'] = $masterstudy_course_player_template && $settings['course_player_theme_mode'] ?? false;
		}

		return $data;
	}
}
