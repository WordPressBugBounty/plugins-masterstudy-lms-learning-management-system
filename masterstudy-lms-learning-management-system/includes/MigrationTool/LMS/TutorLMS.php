<?php
// phpcs:ignoreFile
/**
 * Migration Tool: Tutor LMS → MasterStudy LMS.
 *
 * Mirrors Masteriyo's Tutor LMS migration (same steps and per-item logic) in COPY mode: every Tutor post the
 * migration uses is copied into a NEW MasterStudy post (Target::copy_post()) and every MasterStudy row points at
 * the copies. Tutor data (posts, meta, comments, terms, users, its tables and options) is only read — Tutor LMS
 * keeps working after the migration. Steps are cursor based over all source records and idempotent.
 */
namespace MasterStudy\Lms\MigrationTool\LMS;

defined( 'ABSPATH' ) || exit;

use MasterStudy\Lms\Enums\PricingMode;
use MasterStudy\Lms\MigrationTool\Helper;
use MasterStudy\Lms\MigrationTool\ProTarget;
use MasterStudy\Lms\MigrationTool\Report;
use MasterStudy\Lms\MigrationTool\Target;
use MasterStudy\Lms\Plugin\PostType;
use MasterStudy\Lms\Plugin\Taxonomy;

class TutorLMS {

	/**
	 * Source slug stored on every migrated record (Target::SOURCE_META).
	 */
	const SOURCE = 'tutor';

	/**
	 * User meta flag guarding the one-time profile copy.
	 */
	const PROFILE_MIGRATED_META = '_masterstudy_tutor_profile_migrated';

	/**
	 * User meta marking a user processed by the users step (value: self::SOURCE).
	 */
	const USER_MIGRATED_META = Target::SOURCE_META;

	/**
	 * User meta Tutor sets on every student / instructor applicant.
	 */
	const STUDENT_META    = '_is_tutor_student';
	const INSTRUCTOR_META = '_is_tutor_instructor';

	/**
	 * Course copy post meta: Tutor topic ID => MasterStudy curriculum section built from that topic
	 * (the google_meet step places straggler meetings with it; source topics are never modified).
	 */
	const TOPIC_SECTIONS_META = '_masterstudy_tutor_topic_sections';

	/**
	 * `_masterstudy_migrated_source_id` prefix of a comment copied onto a MasterStudy lesson (comment meta).
	 */
	const COMMENT_KEY_PREFIX = 'comment-';

	/**
	 * Tutor comment type of an assignment submission.
	 */
	const SUBMISSION_COMMENT_TYPE = 'tutor_assignment';

	/**
	 * Tutor LMS Pro private lesson note comment type (LessonNotes::COMMENT_TYPE) and its meta.
	 */
	const NOTE_COMMENT_TYPE = 'lesson_note';
	const NOTE_META         = '_tutor_note_info';

	/**
	 * Tutor Pro built-in certificate templates (key => name, orientation).
	 */
	const CERTIFICATE_TEMPLATES = array(
		'default'     => array( 'Default', 'landscape' ),
		'template_1'  => array( 'Abstract Landscape', 'landscape' ),
		'template_2'  => array( 'Abstract Portrait', 'portrait' ),
		'template_3'  => array( 'Decorative Landscape', 'landscape' ),
		'template_4'  => array( 'Decorative Portrait', 'portrait' ),
		'template_5'  => array( 'Geometric Landscape', 'landscape' ),
		'template_6'  => array( 'Geometric Portrait', 'portrait' ),
		'template_7'  => array( 'Minimal Landscape', 'landscape' ),
		'template_8'  => array( 'Minimal Portrait', 'portrait' ),
		'template_9'  => array( 'Floating Landscape', 'landscape' ),
		'template_10' => array( 'Floating Portrait', 'portrait' ),
		'template_11' => array( 'Stripe Landscape', 'landscape' ),
		'template_12' => array( 'Stripe Portrait', 'portrait' ),
	);

	/**
	 * Dispatch per-item migration to the correct single-item method.
	 *
	 * Must be idempotent — safe to call twice for the same (step, item_id) pair.
	 *
	 * @param string $step    Step name matching a key in get_steps().
	 * @param int    $item_id Source item ID (post ID, comment ID, user ID or table row ID).
	 * @throws \Exception Triggers ROLLBACK in the job engine; item is added to the failed list.
	 */
	public static function migrate_item( string $step, int $item_id ): void {
		// Some steps call tutor_utils() without a per-call guard. Fail fast here with a
		// clear message rather than letting PHP throw an undefined-function Error mid-item.
		if ( in_array( $step, array( 'courses', 'questions_n_answers' ), true ) && ! function_exists( 'tutor_utils' ) ) {
			throw new \Exception(
				sprintf( 'Tutor LMS is not loaded — the "%s" step needs the Tutor LMS plugin to be active.', $step ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		switch ( $step ) {
			case 'users':
				static::migrate_single_user( $item_id );
				break;
			case 'courses':
				static::migrate_single_course( $item_id );
				break;
			case 'enrollments':
				static::migrate_single_enrollment( $item_id );
				break;
			case 'assignments':
				static::migrate_assignment_submissions( $item_id );
				break;
			case 'orders':
				static::migrate_single_order( $item_id );
				break;
			case 'reviews':
				static::migrate_single_review( $item_id );
				break;
			case 'announcement':
				static::migrate_single_announcement( $item_id );
				break;
			case 'questions_n_answers':
				static::migrate_single_qa( $item_id );
				break;
			case 'progress':
				static::migrate_single_progress( $item_id );
				break;
			case 'quiz_attempts':
				static::migrate_single_quiz_attempt( $item_id );
				break;
			case 'wishlists':
				static::migrate_single_wishlist_user( $item_id );
				break;
			case 'lesson_notes':
				static::migrate_single_lesson_note( $item_id );
				break;
			case 'google_meet':
				static::migrate_single_google_meet( $item_id );
				break;
		}
	}

	/**
	 * Count total source items for a given step. Uses fast COUNT queries — no records loaded.
	 *
	 * @param string $step Step name.
	 * @return int
	 */
	public static function count_source_items( string $step ): int {
		global $wpdb;

		switch ( $step ) {
			case 'users':
				$users_where = static::users_source_where();
				return (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->users} u WHERE {$users_where['sql']}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$users_where['args']
					)
				);

			case 'courses':
				return (int) $wpdb->get_var(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'courses'"
				);

			case 'enrollments':
				return (int) $wpdb->get_var(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'tutor_enrolled'"
				);

			case 'orders':
				if ( ! function_exists( 'tutor_utils' ) ) {
					return 0;
				}
				$monetize_by = \tutor_utils()->get_option( 'monetize_by' );
				if ( 'wc' === $monetize_by ) {
					return 0; // WC orders handled inside enrollments step.
				}
				if ( 'edd' === $monetize_by ) {
					return (int) $wpdb->get_var(
						"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'edd_payment'"
					);
				}
				if ( 'tutor' === $monetize_by ) {
					$orders_tbl = $wpdb->prefix . 'tutor_orders';
					if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $orders_tbl ) ) ) {
						return 0;
					}
					return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$orders_tbl}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				}
				return 0;

			case 'reviews':
				return (int) $wpdb->get_var(
					"SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = 'tutor_course_rating'"
				);

			case 'announcement':
				return (int) $wpdb->get_var(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'tutor_announcements'"
				);

			case 'questions_n_answers':
				return (int) $wpdb->get_var(
					"SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = 'tutor_q_and_a' AND comment_parent = 0"
				);

			case 'progress':
				// MasterStudy enrollments of the course copies whose student completed Tutor lessons (the rows the step
				// iterates). 0 at session start: the engine recounts once the enrollments step has run.
				return (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*)
						 FROM {$wpdb->prefix}stm_lms_user_courses uc
						 INNER JOIN {$wpdb->postmeta} s ON s.post_id = uc.course_id AND s.meta_key = %s AND s.meta_value = %s
						 INNER JOIN {$wpdb->postmeta} i ON i.post_id = uc.course_id AND i.meta_key = %s AND i.meta_value LIKE %s
						 WHERE EXISTS (
						       SELECT 1 FROM {$wpdb->usermeta} um
						       WHERE um.user_id = uc.user_id
						         AND um.meta_key LIKE %s
						         AND um.meta_value != ''
						   )",
						Target::SOURCE_META,
						self::SOURCE,
						Target::SOURCE_ID_META,
						$wpdb->esc_like( Target::COPY_KEY_PREFIX ) . '%',
						$wpdb->esc_like( '_tutor_completed_lesson_id_' ) . '%'
					)
				);

			case 'quiz_attempts':
				$attempts_tbl = $wpdb->prefix . 'tutor_quiz_attempts';
				if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $attempts_tbl ) ) ) {
					return 0;
				}
				return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$attempts_tbl}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			case 'assignments':
				// Tutor assignments with submissions (source post IDs; their copies are made by the courses step).
				$assignments = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(DISTINCT c.comment_post_ID)
						 FROM {$wpdb->comments} c
						 INNER JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID AND p.post_type = 'tutor_assignments'
						 WHERE c.comment_type = %s",
						self::SUBMISSION_COMMENT_TYPE
					)
				);
				// Submissions are a MasterStudy Pro target — without Pro they are not imported.
				if ( $assignments && ! ProTarget::pro_active() ) {
					Helper::log( 'warning', sprintf( 'Tutor LMS: submissions of %d assignment(s) not migrated — requires MasterStudy LMS Pro.', $assignments ) );
					return 0;
				}
				return $assignments;

			case 'google_meet':
				// stm-google-meets is a MasterStudy Pro Plus target — without Plus the meetings are not copied.
				if ( ! ProTarget::plus_active() ) {
					$pending = (int) $wpdb->get_var(
						"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'tutor-google-meet'"
					);
					if ( $pending ) {
						Helper::log( 'warning', sprintf( 'Tutor LMS: %d Google Meet item(s) not migrated — requires MasterStudy LMS Pro Plus.', $pending ) );
					}
					return 0;
				}
				return (int) $wpdb->get_var(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'tutor-google-meet'"
				);

			case 'wishlists':
				return (int) $wpdb->get_var(
					"SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key = '_tutor_course_wishlist'"
				);

			case 'lesson_notes':
				// Tutor Pro private notes. Always processed: without MasterStudy Pro Plus they are reported.
				return (int) $wpdb->get_var(
					$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = %s", self::NOTE_COMMENT_TYPE )
				);
		}

		return 0;
	}

	/**
	 * Return one batch of source IDs.
	 *
	 * Copy mode never removes or changes a source record, so every step is cursor based ("> $cursor" over the
	 * source ID, ascending). Failed items are behind the cursor already, so $exclude is not needed.
	 *
	 * @param string $step    Step name.
	 * @param int    $limit   Batch size.
	 * @param int    $cursor  Last processed ID (0 = first batch).
	 * @param int[]  $exclude IDs that already failed in this session (unused: they are behind the cursor).
	 * @return int[]
	 */
	public static function get_source_ids( string $step, int $limit, int $cursor, array $exclude = array() ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		switch ( $step ) {
			case 'users':
				$users_where = static::users_source_where();
				$ids         = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT u.ID
						 FROM {$wpdb->users} u
						 WHERE {$users_where['sql']}
						   AND u.ID > %d
						 ORDER BY u.ID ASC
						 LIMIT %d",
						array_merge( $users_where['args'], array( $cursor, $limit ) )
					)
				);
				break;

			case 'courses':
			case 'enrollments':
			case 'announcement':
				$types = array(
					'courses'      => 'courses',
					'enrollments'  => 'tutor_enrolled',
					'announcement' => 'tutor_announcements',
				);
				$ids   = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND ID > %d ORDER BY ID ASC LIMIT %d",
						$types[ $step ],
						$cursor,
						$limit
					)
				);
				break;

			case 'orders':
				if ( ! function_exists( 'tutor_utils' ) ) {
					return array();
				}
				$monetize_by = \tutor_utils()->get_option( 'monetize_by' );
				if ( 'edd' === $monetize_by ) {
					$ids = $wpdb->get_col(
						$wpdb->prepare(
							"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'edd_payment' AND ID > %d ORDER BY ID ASC LIMIT %d",
							$cursor,
							$limit
						)
					);
					break;
				}
				if ( 'tutor' === $monetize_by && static::table_exists( $wpdb->prefix . 'tutor_orders' ) ) {
					$ids = $wpdb->get_col(
						$wpdb->prepare(
							"SELECT id FROM {$wpdb->prefix}tutor_orders WHERE id > %d ORDER BY id ASC LIMIT %d",
							$cursor,
							$limit
						)
					);
					break;
				}
				// WooCommerce orders are recorded inside the enrollments step.
				return array();

			case 'reviews':
			case 'questions_n_answers':
			case 'lesson_notes':
				$where = array(
					'reviews'             => "comment_type = 'tutor_course_rating'",
					'questions_n_answers' => "comment_type = 'tutor_q_and_a' AND comment_parent = 0",
					'lesson_notes'        => $wpdb->prepare( 'comment_type = %s', self::NOTE_COMMENT_TYPE ),
				);
				$ids   = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT comment_ID FROM {$wpdb->comments} WHERE {$where[ $step ]} AND comment_ID > %d ORDER BY comment_ID ASC LIMIT %d",
						$cursor,
						$limit
					)
				);
				break;

			case 'progress':
				// MasterStudy enrollments of the course copies whose student completed Tutor lessons.
				$ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT uc.user_course_id
						 FROM {$wpdb->prefix}stm_lms_user_courses uc
						 INNER JOIN {$wpdb->postmeta} s ON s.post_id = uc.course_id AND s.meta_key = %s AND s.meta_value = %s
						 INNER JOIN {$wpdb->postmeta} i ON i.post_id = uc.course_id AND i.meta_key = %s AND i.meta_value LIKE %s
						 WHERE uc.user_course_id > %d
						   AND EXISTS (
						       SELECT 1 FROM {$wpdb->usermeta} um
						       WHERE um.user_id = uc.user_id
						         AND um.meta_key LIKE %s
						         AND um.meta_value != ''
						   )
						 ORDER BY uc.user_course_id ASC
						 LIMIT %d",
						Target::SOURCE_META,
						self::SOURCE,
						Target::SOURCE_ID_META,
						$wpdb->esc_like( Target::COPY_KEY_PREFIX ) . '%',
						$cursor,
						$wpdb->esc_like( '_tutor_completed_lesson_id_' ) . '%',
						$limit
					)
				);
				break;

			case 'quiz_attempts':
				if ( ! static::table_exists( $wpdb->prefix . 'tutor_quiz_attempts' ) ) {
					return array();
				}
				$ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT attempt_id FROM {$wpdb->prefix}tutor_quiz_attempts WHERE attempt_id > %d ORDER BY attempt_id ASC LIMIT %d",
						$cursor,
						$limit
					)
				);
				break;

			case 'wishlists':
				$ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT DISTINCT user_id FROM {$wpdb->usermeta}
						 WHERE meta_key = '_tutor_course_wishlist' AND user_id > %d
						 ORDER BY user_id ASC
						 LIMIT %d",
						$cursor,
						$limit
					)
				);
				break;

			case 'assignments':
				// Tutor assignments with submissions (source post IDs).
				if ( ! ProTarget::pro_active() ) {
					return array();
				}
				$ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT DISTINCT c.comment_post_ID
						 FROM {$wpdb->comments} c
						 INNER JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID AND p.post_type = 'tutor_assignments'
						 WHERE c.comment_type = %s
						   AND c.comment_post_ID > %d
						 ORDER BY c.comment_post_ID ASC
						 LIMIT %d",
						self::SUBMISSION_COMMENT_TYPE,
						$cursor,
						$limit
					)
				);
				break;

			case 'google_meet':
				// Without MasterStudy Pro Plus there is no Google Meet target (the courses step reports the meetings).
				if ( ! ProTarget::plus_active() ) {
					return array();
				}
				$ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'tutor-google-meet' AND ID > %d ORDER BY ID ASC LIMIT %d",
						$cursor,
						$limit
					)
				);
				break;

			default:
				return array();
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array_map( 'intval', $ids ? $ids : array() );
	}

	/**
	 * Run once after a step has processed every item.
	 *
	 * - courses: prerequisites (every course copy exists now), course bundles (Pro), coupons and subscription plans
	 *   (Pro Plus) — they reference course copies; WPML rows for the copies.
	 * - enrollments: recount `current_students` of the course copies.
	 * - assignments: recalculate progress of the course copies that received submissions.
	 * - reviews: rebuild course/instructor rating caches.
	 * - progress / quiz_attempts: recalculate progress_percent with MasterStudy's own logic.
	 *
	 * Only copies made from Tutor are touched (never posts converted by an earlier in-place migration).
	 *
	 * @param string $step Step name.
	 */
	public static function finalize_step( string $step ): void {
		switch ( $step ) {
			case 'courses':
				static::migrate_prerequisites();
				static::migrate_bundles();
				static::migrate_coupons();
				static::migrate_subscription_plans();
				if ( ProTarget::plus_active() ) {
					static::report_unsellable_courses();
				}
				static::migrate_gradebook_scale();
				static::migrate_commission_settings();
				static::sync_translations();
				// finalize_step runs outside the batch transaction — enable the addons requested above now.
				Helper::flush_addon_requests();
				break;

			case 'orders':
			case 'google_meet':
				// Copies of EDD payments / straggler meetings get WPML rows like their source posts.
				static::sync_translations();
				break;

			case 'assignments':
				$course_ids = array_intersect( static::migrated_course_ids(), static::submission_course_ids() );
				if ( ! empty( $course_ids ) ) {
					Target::recalculate_progress( array_values( $course_ids ) );
				}
				break;

			case 'enrollments':
				foreach ( static::migrated_course_ids() as $course_id ) {
					if ( $course_id ) {
						Target::refresh_students_count( $course_id );
					}
				}
				break;

			case 'reviews':
				Target::recalculate_ratings();
				break;

			case 'progress':
			case 'quiz_attempts':
				Target::recalculate_progress( static::migrated_course_ids() );
				break;

			case 'lesson_notes':
				static::enable_lesson_notes_setting();
				break;
		}
	}

	/**
	 * Migrate a single TutorLMS student/instructor user to a MasterStudy student/instructor.
	 *
	 * Users are shared by both plugins: the Tutor roles and user meta are kept, MasterStudy data is added
	 * (instructor role, application status, profile fields that do not exist yet).
	 *
	 * @param int $user_id WP user ID.
	 * @throws \Exception If user does not exist.
	 */
	public static function migrate_single_user( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			throw new \Exception( sprintf( 'WordPress user #%d no longer exists.', $user_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$roles = (array) $user->roles;
		$caps  = is_array( $user->caps ) ? $user->caps : array();

		// Tutor has no student role: students are flagged with the `_is_tutor_student` user meta.
		$is_student    = in_array( 'tutor_student', $roles, true ) || isset( $caps['tutor_student'] ) || '' !== (string) get_user_meta( $user_id, self::STUDENT_META, true );
		$status        = static::instructor_status( $user_id );
		$is_instructor = ( in_array( 'tutor_instructor', $roles, true ) || isset( $caps['tutor_instructor'] ) ) && 'blocked' !== $status;

		if ( $is_instructor ) {
			// make_instructor() leaves administrators untouched.
			Target::make_instructor( $user_id );
		} else {
			static::migrate_instructor_application( $user_id, $status );
		}

		// MasterStudy students have no role — only make sure the user is not left role-less.
		Target::ensure_student( $user_id );

		// Profile (bio, job title, photo, socials) for students and instructors alike; the courses step
		// also copies instructor profiles — the profile-migrated guard prevents double-processing.
		static::maybe_migrate_profile( $user_id );

		// Marker of a user processed by the users step (the courses step then leaves the instructor application to it).
		if ( ! in_array( self::SOURCE, (array) get_user_meta( $user_id, self::USER_MIGRATED_META, false ), true ) ) {
			add_user_meta( $user_id, self::USER_MIGRATED_META, self::SOURCE );
		}

		if ( ! $is_student && ! $is_instructor ) {
			Helper::log( 'info', sprintf( 'Tutor LMS: user %d migrated as a student (Tutor instructor applicant without the instructor role).', $user_id ) );
		}
	}

	/**
	 * Copy a user's Tutor profile to MasterStudy once (guarded by PROFILE_MIGRATED_META).
	 *
	 * @param int $user_id User ID.
	 */
	private static function maybe_migrate_profile( int $user_id ): void {
		if ( metadata_exists( 'user', $user_id, self::PROFILE_MIGRATED_META ) ) {
			return;
		}

		static::migrate_instructor_profile( $user_id );
		add_user_meta( $user_id, self::PROFILE_MIGRATED_META, '1', true );
	}

	/**
	 * Tutor instructor application status (`_tutor_instructor_status`): pending, approved, blocked,
	 * try_again (rejected) or '' (never applied).
	 *
	 * @param int $user_id User ID.
	 */
	private static function instructor_status( int $user_id ): string {
		return (string) get_user_meta( $user_id, '_tutor_instructor_status', true );
	}

	/**
	 * Whether a Tutor user may get the MasterStudy instructor role: blocked instructors (Tutor removed their
	 * role) and pending / rejected applicants must not gain instructor rights through the migration.
	 *
	 * @param int $user_id User ID.
	 */
	private static function instructor_allowed( int $user_id ): bool {
		return ! in_array( static::instructor_status( $user_id ), array( 'blocked', 'pending', 'try_again' ), true );
	}

	/**
	 * Carry a Tutor instructor application that did not end in an active instructor over to MasterStudy:
	 * pending → pending "become an instructor" request, rejected → rejected request, blocked → banned user.
	 *
	 * @param int    $user_id User ID.
	 * @param string $status  Tutor instructor status.
	 */
	private static function migrate_instructor_application( int $user_id, string $status ): void {
		switch ( $status ) {
			case 'pending':
				Target::request_instructor( $user_id, static::tutor_time_to_unix( get_user_meta( $user_id, self::INSTRUCTOR_META, true ) ) );
				break;

			case 'try_again':
				// Only added when the user has no MasterStudy application yet (existing user meta is never changed).
				if ( ! metadata_exists( 'user', $user_id, 'submission_status' ) ) {
					add_user_meta( $user_id, 'submission_status', 'rejected', true );
					add_user_meta( $user_id, 'submission_date', static::tutor_time_to_unix( get_user_meta( $user_id, '_is_tutor_instructor_rejected', true ) ), true );
				}
				break;

			case 'blocked':
				// Reported in every session (the users step reaches every Tutor user); the ban is only added once.
				Target::track_user_meta( $user_id, 'stm_lms_user_banned' );
				add_user_meta( $user_id, 'stm_lms_user_banned', true, true );

				Report::add(
					Report::GROUP_USERS,
					array(
						'source_id' => $user_id,
						'title'     => static::user_label( $user_id ),
						'type'      => __( 'Blocked instructor', 'masterstudy-lms-learning-management-system' ),
						'reason'    => __( 'The instructor was blocked in Tutor LMS. The user was imported without the MasterStudy instructor role and is marked as banned; their courses keep them as the author.', 'masterstudy-lms-learning-management-system' ),
						'status'    => Report::STATUS_PARTIAL,
					)
				);
				break;
		}
	}

	/**
	 * WHERE clause (alias `u` = users table) matching every Tutor user: Tutor instructors (role/cap), students and
	 * instructor applicants (Tutor user meta). Re-processing a user is idempotent.
	 *
	 * @return array{sql: string, args: array}
	 */
	private static function users_source_where(): array {
		global $wpdb;

		return array(
			'sql'  => "( EXISTS (
					SELECT 1 FROM {$wpdb->usermeta} cap
					WHERE cap.user_id = u.ID AND cap.meta_key = %s AND ( cap.meta_value LIKE %s OR cap.meta_value LIKE %s )
				) OR EXISTS (
					SELECT 1 FROM {$wpdb->usermeta} flag
					WHERE flag.user_id = u.ID AND flag.meta_key IN ( %s, %s )
				) )",
			'args' => array(
				$wpdb->get_blog_prefix() . 'capabilities',
				'%' . $wpdb->esc_like( '"tutor_student"' ) . '%',
				'%' . $wpdb->esc_like( '"tutor_instructor"' ) . '%',
				self::STUDENT_META,
				self::INSTRUCTOR_META,
			),
		);
	}

	/**
	 * Migrate a single TutorLMS course CPT to MasterStudy.
	 *
	 * Copies the 'courses' post into a new 'stm-courses' post, assigns the instructor role, writes the course meta
	 * and builds the copy's curriculum (sections/materials tables) from the Tutor topics, copying lessons, quizzes,
	 * questions, assignments and meetings (an item shared by two courses gets one copy). The Tutor course, its
	 * topics and items are never modified. Does NOT process enrollments — those are owned by the 'enrollments' step.
	 *
	 * @param int $course_id TutorLMS course post ID.
	 * @throws \Exception If the post record does not exist.
	 */
	public static function migrate_single_course( int $course_id ): void {
		$course = get_post( $course_id );
		if ( ! $course || 'courses' !== $course->post_type ) {
			throw new \Exception( sprintf( 'Tutor LMS course #%d no longer exists.', $course_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		// A course pending review must not go live in MasterStudy.
		$copy_id = Target::copy_post(
			$course_id,
			PostType::COURSE,
			self::SOURCE,
			'pending' === $course->post_status ? array( 'post_status' => 'draft' ) : array()
		);

		if ( 0 !== (int) $course->post_author ) {
			$user_id = (int) $course->post_author;
			$user    = get_userdata( $user_id );
			if ( $user ) {
				// A blocked or not yet approved Tutor instructor keeps authoring the course, but is not given
				// the MasterStudy instructor role (users step: ban / pending application — applied here only for an
				// author the users step did not process).
				if ( static::instructor_allowed( $user_id ) ) {
					Target::make_instructor( $user_id );
				} elseif ( ! in_array( self::SOURCE, (array) get_user_meta( $user_id, self::USER_MIGRATED_META, false ), true ) ) {
					static::migrate_instructor_application( $user_id, static::instructor_status( $user_id ) );
				}

				Target::ensure_student( $user_id );
				static::maybe_migrate_profile( $user_id );
			}
		}

		static::update_masterstudy_course_from_tutor( $course_id, $copy_id );

		// For EDD: keep the download on the course copy for reference (orders resolve the course through the Tutor
		// course's `_tutor_course_product_id`, see course_for_download()).
		if ( function_exists( 'tutor_utils' ) && 'edd' === \tutor_utils()->get_option( 'monetize_by' ) ) {
			$product_id = (int) get_post_meta( $course_id, '_tutor_course_product_id', true );
			if ( $product_id && function_exists( 'edd_get_download' ) && \edd_get_download( $product_id ) ) {
				update_post_meta( $copy_id, '_edd_download_id', $product_id );
			}
		}

		// MasterStudy has no post_parent linkage — the curriculum lives only in the
		// sections/materials tables. Rebuild it from scratch so a re-run is idempotent.
		Target::reset_curriculum( $copy_id );

		$sections = get_posts(
			array(
				'post_type'        => 'topics',
				'post_parent'      => $course_id,
				'posts_per_page'   => -1,
				'orderby'          => array(
					'menu_order' => 'ASC',
					'ID'         => 'ASC',
				),
				'post_status'      => 'any',
				'suppress_filters' => true,
			)
		);

		$section_order  = 0;
		$topic_sections = array();

		foreach ( $sections as $section ) {
			$section_id = Target::add_section( $copy_id, $section->post_title, ++$section_order );

			// Remember the section built from this topic so the google_meet step can place stragglers.
			$topic_sections[ (int) $section->ID ] = $section_id;

			// Topic children plus Content Bank lessons linked to the topic (Tutor Pro), in Tutor's order.
			$items = static::topic_items( $section, $course_id );

			$material_order = 0;

			foreach ( $items as $item ) {
				$material_id = static::migrate_curriculum_item( $item, $copy_id, $course_id );

				// MasterStudy shows every curriculum item to enrolled students whatever its post status, while
				// Tutor only lists published items — an unpublished item is copied but kept out of the curriculum.
				if ( $material_id && ! in_array( $item->post_status, array( 'publish', 'inherit' ), true ) ) {
					static::report_curriculum_item(
						Report::GROUP_LESSONS,
						$item,
						$course_id,
						static::curriculum_item_label( $item->post_type ),
						sprintf(
							/* translators: %s: post status */
							__( 'The item is not published in Tutor LMS (status "%s"), so students could not see it. It was copied with the same status but not added to the course curriculum — MasterStudy shows every curriculum item to enrolled students. Publish it and add it to the curriculum when it is ready.', 'masterstudy-lms-learning-management-system' ),
							$item->post_status
						),
						Report::STATUS_PARTIAL,
						$material_id
					);
					$material_id = 0;
				}

				if ( $material_id ) {
					Target::add_material( $section_id, $material_id, ++$material_order );
				}

				/**
				 * Fires for each TutorLMS curriculum item during the courses step.
				 *
				 * TutorLMS section children the shared migration does not handle can be migrated here.
				 *
				 * @param \WP_Post $item       TutorLMS curriculum item post object (the source post, never modified).
				 * @param int      $section_id MasterStudy curriculum section ID (stm_lms_curriculum_sections.id).
				 * @param int      $course_id  MasterStudy course post ID (the copy).
				 */
				do_action( 'masterstudy_lms_migration_tool_tutorlms_curriculum_item', $item, $section_id, $copy_id );
			}
		}

		update_post_meta( $copy_id, self::TOPIC_SECTIONS_META, $topic_sections );

		// Course-level live sessions (Zoom / Google Meet attached to the course, not a topic)
		// have no curriculum position in Tutor — collect them into a trailing section.
		$live_items = get_posts(
			array(
				'post_type'        => array( 'tutor_zoom_meeting', 'tutor-google-meet' ),
				'post_parent'      => $course_id,
				'posts_per_page'   => -1,
				'orderby'          => array(
					'menu_order' => 'ASC',
					'ID'         => 'ASC',
				),
				'post_status'      => 'any',
				'suppress_filters' => true,
			)
		);

		foreach ( $live_items as $item ) {
			$material_id = static::migrate_curriculum_item( $item, $copy_id, $course_id );

			if ( $material_id ) {
				static::append_to_live_sessions( $copy_id, $material_id );
			}
		}

		// "After finishing prerequisites" drip references curriculum items — map it once all of them are copied.
		static::migrate_drip_prerequisites( $course_id, $copy_id );
	}

	/**
	 * Migrate a single TutorLMS enrollment (tutor_enrolled CPT) to a MasterStudy user_courses row of the course copy.
	 * The Tutor enrollment post stays.
	 *
	 * @param int $enrollment_post_id Post ID of the tutor_enrolled CPT.
	 * @throws \Exception If the enrollment post or its user does not exist.
	 */
	public static function migrate_single_enrollment( int $enrollment_post_id ): void {
		$enrolled_post = get_post( $enrollment_post_id );
		if ( ! $enrolled_post || 'tutor_enrolled' !== $enrolled_post->post_type ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \Exception(
				sprintf( 'Tutor LMS enrollment #%d no longer exists.', $enrollment_post_id )
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$course_id = (int) $enrolled_post->post_parent;
		$user_id   = (int) $enrolled_post->post_author;
		$user      = get_userdata( $user_id );

		// Tutor also enrolls the buyer in the bundle post itself; every bundle course has its own enrollment
		// (tagged with `_tutor_bundle_id`), which is what MasterStudy needs.
		if ( 'course-bundle' === get_post_type( $course_id ) ) {
			if ( ! ProTarget::pro_active() ) {
				Report::add(
					Report::GROUP_ENROLLMENTS,
					array(
						'source_id' => $enrollment_post_id,
						'title'     => sprintf( '%1$s → %2$s', static::user_label( $user_id ), get_post_field( 'post_title', $course_id ) ),
						'type'      => __( 'Bundle enrollment', 'masterstudy-lms-learning-management-system' ),
						'reason'    => __( 'Course bundles require MasterStudy LMS Pro — the enrollments in the bundle courses were imported, the bundle purchase itself was not.', 'masterstudy-lms-learning-management-system' ),
						'status'    => Report::STATUS_UNSUPPORTED,
					)
				);
			}
			return;
		}

		if ( ! $user ) {
			// Left behind when a WordPress user was deleted: nothing to enroll.
			Report::add(
				Report::GROUP_ENROLLMENTS,
				array(
					'source_id' => $enrollment_post_id,
					'title'     => sprintf( '%1$s → %2$s', static::user_label( $user_id ), get_post_field( 'post_title', $course_id ) ),
					'type'      => static::enrollment_status_label( (string) $enrolled_post->post_status ),
					'reason'    => __( 'The enrolled user no longer exists, so there is nothing to import.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_UNSUPPORTED,
					'course'    => get_post_field( 'post_title', $course_id ),
					'post_id'   => static::report_post_id( $course_id ),
				)
			);
			return;
		}

		$email  = sanitize_email( $user->user_email );
		$result = static::update_tutor_enrolled_user_to_masterstudy_enrolled_user( $course_id, $email, $enrolled_post );

		if ( false !== $result && function_exists( 'tutor_utils' ) ) {
			$order_id    = get_post_meta( $enrollment_post_id, '_tutor_enrolled_by_order_id', true );
			$monetize_by = \tutor_utils()->get_option( 'monetize_by' );
			if ( 'wc' === $monetize_by && $order_id ) {
				static::sync_wc_order_with_masterstudy( (int) $order_id, Target::copy_of( self::SOURCE, $course_id ), $user_id );
			}
		}
	}

	/**
	 * Migrate a single order for EDD or Tutor native checkout to a MasterStudy stm-orders post.
	 *
	 * WC orders are handled inside migrate_single_enrollment() — this method is a no-op for WC.
	 *
	 * @param int $order_id EDD payment post ID or Tutor native order ID.
	 * @throws \Exception If an EDD payment or its course cannot be resolved (Tutor orders are always imported).
	 */
	public static function migrate_single_order( int $order_id ): void {
		global $wpdb;

		if ( ! function_exists( 'tutor_utils' ) ) {
			return;
		}

		$monetize_by = \tutor_utils()->get_option( 'monetize_by' );

		if ( 'edd' === $monetize_by ) {
			if ( ! function_exists( 'edd_get_payment' ) ) {
				throw new \Exception( sprintf( 'EDD payment #%d not imported: Easy Digital Downloads is not active. Activate it and run the migration again.', $order_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
			$payment = \edd_get_payment( $order_id );
			if ( ! $payment || ! $payment->ID ) {
				throw new \Exception( sprintf( 'EDD payment #%d no longer exists.', $order_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			// A cart can hold several downloads: every download sold by a copied Tutor course becomes an order item.
			$items     = array();
			$left_out  = array();
			$course_id = 0;
			foreach ( (array) $payment->cart_details as $item ) {
				$product_id    = (int) ( $item['id'] ?? 0 );
				$source_course = $product_id ? static::course_for_download( $product_id ) : 0;
				$linked        = $source_course ? Target::copy_of( self::SOURCE, $source_course ) : 0;

				if ( $linked && PostType::COURSE === get_post_type( $linked ) ) {
					$items[] = array(
						'course_id' => $linked,
						'price'     => (float) ( $item['price'] ?? $item['item_price'] ?? 0 ),
					);
				} else {
					$left_out[] = (string) ( $item['name'] ?? ( '#' . $product_id ) );
				}

				$course_id = $course_id ? $course_id : $source_course;
			}

			if ( ! $course_id ) {
				// Mirrors Masteriyo: the order is skipped — the item is not retried in this session.
				throw new \Exception( sprintf( 'EDD payment #%d is not linked to any Tutor LMS course — none of its downloads is sold by a course.', $order_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			if ( empty( $items ) ) {
				throw new \Exception( sprintf( 'EDD payment #%1$d: course "%2$s" (#%3$d) has not been migrated yet — run the Courses step first.', $order_id, get_post_field( 'post_title', $course_id ), $course_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			// The edd_payment post is copied into a new MasterStudy order (Target::save_order()).
			static::insert_edd_order( $order_id, $items );

			if ( ! empty( $left_out ) ) {
				Report::add(
					Report::GROUP_ORDERS,
					array(
						'source_id' => $order_id,
						/* translators: %d: order ID */
						'title'     => sprintf( __( 'Order #%d', 'masterstudy-lms-learning-management-system' ), $order_id ),
						'type'      => __( 'EDD payment', 'masterstudy-lms-learning-management-system' ),
						/* translators: %s: list of downloads */
						'reason'    => sprintf( __( 'The order was imported without these downloads, which are not migrated courses: %s.', 'masterstudy-lms-learning-management-system' ), implode( ', ', $left_out ) ),
						'status'    => Report::STATUS_PARTIAL,
						'post_id'   => static::report_post_id( $order_id ),
					)
				);
			}
			return;
		}

		if ( 'tutor' === $monetize_by ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$items = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT item_id, regular_price, sale_price, discount_price FROM {$wpdb->prefix}tutor_order_items WHERE order_id = %d ORDER BY id ASC",
					$order_id
				)
			);

			if ( empty( $items ) ) {
				// Very old orders without item rows: the course is only known through the enrollment it created.
				$course_id = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT p.post_parent
						 FROM {$wpdb->postmeta} pm
						 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
						 WHERE pm.meta_key = '_tutor_enrolled_by_order_id'
						   AND pm.meta_value = %s
						   AND p.post_type = 'tutor_enrolled'
						 LIMIT 1",
						(string) $order_id
					)
				);
				$items     = $course_id ? $course_id : array();
			}

			// Items that cannot be resolved are reported and left out — the order itself is always imported.
			// The Tutor order rows stay; the MasterStudy order is idempotent (source key tutor-order-{id}).
			static::insert_tutor_native_order( $order_id, $items );
			return;
		}
	}

	/**
	 * Migrate a single TutorLMS course rating comment to a MasterStudy review (stm-reviews post) of the course copy.
	 *
	 * MasterStudy reviews are posts: a review post is created per rating comment (idempotent per comment ID); the
	 * rating comment stays. Rating caches are rebuilt once in finalize_step().
	 *
	 * @param int $comment_id WP comment ID of the tutor_course_rating comment.
	 * @throws \Exception If the comment does not exist or its course was not migrated.
	 */
	public static function migrate_single_review( int $comment_id ): void {
		$review = get_comment( $comment_id );
		if ( ! $review ) {
			throw new \Exception( sprintf( 'Tutor LMS review (comment #%d) no longer exists.', $comment_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$source_course = (int) $review->comment_post_ID;
		$course_id     = Target::copy_of( self::SOURCE, $source_course );

		if ( ! $course_id ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \Exception( sprintf( 'Review #%1$d: course "%2$s" (#%3$d) has not been migrated — run the Courses step first.', $comment_id, get_post_field( 'post_title', $source_course ), $source_course ) );
		}

		$rating_raw = get_comment_meta( $comment_id, 'tutor_rating', true );
		$rating     = ( '' !== $rating_raw && false !== $rating_raw ) ? $rating_raw : $review->comment_karma;

		$review_id = Target::add_review(
			array(
				'course_id' => $course_id,
				'user_id'   => (int) $review->user_id,
				'mark'      => max( 1, (float) $rating ),
				'content'   => $review->comment_content ?? '',
				'date'      => $review->comment_date,
				'approved'  => in_array( $review->comment_approved ?? '', array( 'approved', '1', 1 ), true ),
				'source_id' => 'rating-' . $comment_id,
			),
			self::SOURCE
		);

		// Spam / trashed reviews must never go live (nor into the moderation queue): kept in the MasterStudy trash.
		if ( in_array( (string) $review->comment_approved, array( 'spam', 'trash', 'post-trashed' ), true ) && 'trash' !== get_post_status( $review_id ) ) {
			global $wpdb;

			$wpdb->update( $wpdb->posts, array( 'post_status' => 'trash' ), array( 'ID' => $review_id ) );
			update_post_meta( $review_id, '_wp_trash_meta_status', 'pending' );
			update_post_meta( $review_id, '_wp_trash_meta_time', time() );
			clean_post_cache( $review_id );
		}

		// Tutor allows a review without stars; MasterStudy requires a mark, so it is imported as 1 star.
		if ( (float) $rating < 1 ) {
			Report::add(
				Report::GROUP_REVIEWS,
				array(
					'source_id' => $comment_id,
					/* translators: 1: reviewer, 2: course title */
					'title'     => sprintf( __( 'Review by %1$s on %2$s', 'masterstudy-lms-learning-management-system' ), static::user_label( (int) $review->user_id ), get_post_field( 'post_title', $course_id ) ),
					'type'      => __( 'Review without rating', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'The Tutor LMS review has no star rating; MasterStudy requires one, so it was imported with 1 star.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_PARTIAL,
					'course'    => get_post_field( 'post_title', $course_id ),
					'post_id'   => $review_id,
				)
			);
		}
	}

	/**
	 * Migrate a single tutor_announcements post into the `announcement` block of the course copy.
	 *
	 * MasterStudy keeps one HTML announcement per course, so published announcements are appended to it (in ID
	 * order; idempotent). Drafts are never published — their content is kept on the copy as unmigrated meta.
	 * The Tutor announcement stays.
	 *
	 * @param int $post_id tutor_announcements post ID.
	 * @throws \Exception If the post does not exist or its course was not migrated.
	 */
	public static function migrate_single_announcement( int $post_id ): void {
		$announcement = get_post( $post_id );
		if ( ! $announcement ) {
			throw new \Exception( sprintf( 'Tutor LMS announcement #%d no longer exists.', $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$source_course = (int) $announcement->post_parent;

		if ( ! $source_course || ! get_post( $source_course ) ) {
			throw new \Exception( sprintf( 'Announcement "%1$s" (#%2$d) belongs to course #%3$d, which no longer exists.', $announcement->post_title, $post_id, $source_course ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$course_id = Target::copy_of( self::SOURCE, $source_course );

		if ( ! $course_id ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \Exception( sprintf( 'Announcement "%1$s" (#%2$d): course "%3$s" (#%4$d) has not been migrated — run the Courses step first.', $announcement->post_title, $post_id, get_post_field( 'post_title', $source_course ), $source_course ) );
		}

		if ( 'publish' === $announcement->post_status ) {
			Target::add_announcement( $course_id, $announcement->post_title, $announcement->post_content );
			return;
		}

		Report::add(
			Report::GROUP_OTHER,
			array(
				'source_id' => $post_id,
				'title'     => $announcement->post_title,
				/* translators: %s: announcement post status */
				'type'      => sprintf( __( 'Announcement (%s)', 'masterstudy-lms-learning-management-system' ), $announcement->post_status ),
				'reason'    => __( 'MasterStudy keeps one published announcement per course — unpublished announcements are not shown; the text is kept in the course meta for reference.', 'masterstudy-lms-learning-management-system' ),
				'status'    => Report::STATUS_UNSUPPORTED,
				'course'    => get_post_field( 'post_title', $course_id ),
				'post_id'   => $course_id,
			)
		);
		Target::store_unmigrated_meta(
			$course_id,
			'tutor_announcement_' . $post_id,
			array(
				'title'   => $announcement->post_title,
				'content' => $announcement->post_content,
				'status'  => $announcement->post_status,
				'date'    => $announcement->post_date,
			)
		);
	}

	/**
	 * Migrate a single TutorLMS Q&A thread (parent question + all replies) to a MasterStudy lesson discussion.
	 *
	 * Tutor Q&A is course-level; MasterStudy discussions live on lesson posts, so the thread is copied (reply
	 * parents kept) onto the first lesson of the course copy. The Tutor Q&A comments stay; each comment is copied
	 * once (idempotent per comment ID).
	 *
	 * @param int $comment_id Parent Q&A comment ID.
	 * @throws \Exception If the parent comment does not exist or the course copy has no lesson.
	 */
	public static function migrate_single_qa( int $comment_id ): void {
		global $wpdb;

		$question = get_comment( $comment_id );
		if ( ! $question ) {
			throw new \Exception( sprintf( 'Tutor LMS Q&A thread (comment #%d) no longer exists.', $comment_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$source_course = (int) $question->comment_post_ID;
		$course_id     = Target::copy_of( self::SOURCE, $source_course );
		$lesson_id     = $course_id ? static::first_course_lesson( $course_id ) : 0;

		if ( ! $lesson_id ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \Exception( sprintf( 'Q&A thread #%1$d of course "%2$s" (#%3$d) could not be copied: MasterStudy discussions live on lessons and this course has no migrated lesson.', $comment_id, get_post_field( 'post_title', $source_course ), $source_course ) );
		}

		$thread = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT comment_ID FROM {$wpdb->comments}
				 WHERE comment_type = 'tutor_q_and_a' AND ( comment_ID = %d OR comment_parent = %d )
				 ORDER BY comment_ID ASC",
				$comment_id,
				$comment_id
			)
		);

		$parents = array();

		foreach ( (array) $thread as $qa_id ) {
			$qa = get_comment( (int) $qa_id );

			if ( ! $qa ) {
				continue;
			}

			$approved = (string) $qa->comment_approved;
			$status   = in_array( $approved, array( 'approved', '1' ), true ) ? '1' : ( in_array( $approved, array( 'spam', 'trash' ), true ) ? $approved : '0' );

			$parents[ (int) $qa->comment_ID ] = static::copy_comment( $qa, $lesson_id, $parents[ (int) $qa->comment_parent ] ?? 0, $status );
		}
	}

	/**
	 * Migrate lesson completion progress for a single MasterStudy enrollment.
	 *
	 * @param int $user_course_id stm_lms_user_courses.user_course_id.
	 */
	public static function migrate_single_progress( int $user_course_id ): void {
		global $wpdb;

		$user_course = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT user_id, course_id FROM {$wpdb->prefix}stm_lms_user_courses WHERE user_course_id = %d",
				$user_course_id
			)
		);

		if ( ! $user_course ) {
			return;
		}

		$user_id   = (int) $user_course->user_id;
		$course_id = (int) $user_course->course_id;

		// Lessons, Zoom lessons and Google Meet sessions (copies): Tutor records all of them as
		// `_tutor_completed_lesson_id_{source id}`.
		$lesson_ids = array();

		foreach ( Target::lesson_material_ids( $course_id ) as $copy_id ) {
			$source_id = Target::source_of( $copy_id );

			if ( $source_id ) {
				$lesson_ids[ $source_id ] = $copy_id;
			}
		}

		if ( empty( $lesson_ids ) ) {
			return;
		}

		$completions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT CAST(REPLACE(meta_key, '_tutor_completed_lesson_id_', '') AS UNSIGNED) AS lesson_id,
				        meta_value AS completed_ts
				 FROM {$wpdb->usermeta}
				 WHERE user_id = %d
				   AND meta_key LIKE %s
				   AND meta_value != ''",
				$user_id,
				$wpdb->esc_like( '_tutor_completed_lesson_id_' ) . '%'
			)
		);

		$now = time();

		foreach ( (array) $completions as $c ) {
			$lid = (int) $c->lesson_id;
			if ( ! isset( $lesson_ids[ $lid ] ) ) {
				continue;
			}

			$ts = static::tutor_time_to_unix( $c->completed_ts );
			Target::complete_lesson( $user_id, $course_id, $lesson_ids[ $lid ], $ts > 0 ? $ts : $now, $ts > 0 ? $ts : $now );
		}
	}

	/**
	 * Migrate a single Tutor quiz attempt (with its answers) to MasterStudy user_quizzes/user_answers of the quiz copy.
	 *
	 * Idempotent via (user, quiz, attempt start time) deduplication guard. The Tutor attempt rows stay.
	 *
	 * @param int $attempt_id tutor_quiz_attempts.attempt_id.
	 * @throws \Exception If the attempt record is missing or the insert fails.
	 */
	public static function migrate_single_quiz_attempt( int $attempt_id ): void {
		global $wpdb;

		$attempt = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}tutor_quiz_attempts WHERE attempt_id = %d",
				$attempt_id
			)
		);

		if ( ! $attempt ) {
			throw new \Exception( sprintf( 'Tutor LMS quiz attempt #%d no longer exists.', $attempt_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		if ( empty( $attempt->attempt_started_at ) || '0000-00-00 00:00:00' === $attempt->attempt_started_at ) {
			static::report_quiz_attempt(
				$attempt,
				__( 'Attempt without start date', 'masterstudy-lms-learning-management-system' ),
				__( 'The Tutor LMS attempt has no start date, so it cannot be placed in the quiz history — it was skipped.', 'masterstudy-lms-learning-management-system' ),
				Report::STATUS_UNSUPPORTED
			);
			return;
		}

		// MasterStudy has no "in progress" attempt state — an unfinished attempt would count as a failed one.
		if ( 'attempt_started' === $attempt->attempt_status ) {
			Helper::log( 'info', sprintf( 'Tutor LMS: quiz attempt %d skipped — the attempt was never submitted.', $attempt_id ) );
			static::report_quiz_attempt(
				$attempt,
				__( 'Unfinished attempt', 'masterstudy-lms-learning-management-system' ),
				__( 'The attempt was started but never submitted; MasterStudy has no "in progress" attempt state, so it was skipped.', 'masterstudy-lms-learning-management-system' ),
				Report::STATUS_UNSUPPORTED
			);
			return;
		}

		$user_id        = (int) $attempt->user_id;
		$source_quiz_id = (int) $attempt->quiz_id;
		$quiz_id        = Target::copy_of( self::SOURCE, $source_quiz_id );
		$course_id      = Target::copy_of( self::SOURCE, (int) $attempt->course_id );

		if ( ! $quiz_id || PostType::QUIZ !== get_post_type( $quiz_id ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \Exception( sprintf( 'Quiz attempt #%1$d by %2$s: quiz "%3$s" (#%4$d) has not been migrated — run the Courses step first.', $attempt_id, static::user_label( (int) $attempt->user_id ), get_post_field( 'post_title', $source_quiz_id ), $source_quiz_id ) );
		}

		if ( ! $course_id || ! in_array( $course_id, Target::course_ids_of( $quiz_id ), true ) ) {
			$course_ids = Target::course_ids_of( $quiz_id );
			$course_id  = $course_ids ? (int) $course_ids[0] : 0;
		}

		if ( ! $course_id ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \Exception( sprintf( 'Quiz attempt #%1$d by %2$s: quiz "%3$s" (#%4$d) is not part of any migrated course.', $attempt_id, static::user_label( $user_id ), get_post_field( 'post_title', $source_quiz_id ), $source_quiz_id ) );
		}

		$raw_answers = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT aa.question_id, aa.given_answer, aa.achieved_mark, aa.is_correct,
				        q.question_title, q.question_type
				 FROM {$wpdb->prefix}tutor_quiz_attempt_answers aa
				 LEFT JOIN {$wpdb->prefix}tutor_quiz_questions q ON aa.question_id = q.question_id
				 WHERE aa.quiz_attempt_id = %d
				 ORDER BY aa.attempt_answer_id ASC",
				$attempt_id
			)
		);

		$tutor_question_ids = array_values( array_unique( array_map( 'intval', wp_list_pluck( (array) $raw_answers, 'question_id' ) ) ) );
		$ms_question_map    = array();

		if ( ! empty( $tutor_question_ids ) ) {
			$q_ids_in = implode( ',', array_fill( 0, count( $tutor_question_ids ), '%s' ) );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$q_results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT pm.post_id, pm.meta_value FROM {$wpdb->postmeta} pm
					 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = %s
					 WHERE pm.meta_key = '_tutor_question_id' AND pm.meta_value IN ({$q_ids_in})",
					array_merge( array( PostType::QUESTION ), array_map( 'strval', $tutor_question_ids ) )
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( (array) $q_results as $r ) {
				$ms_question_map[ (int) $r->meta_value ] = (int) $r->post_id;
			}
		}

		$answers       = array();
		$dropped_types = array();

		foreach ( (array) $raw_answers as $raw ) {
			$ms_question_id = $ms_question_map[ (int) $raw->question_id ] ?? 0;
			$ms_type        = $ms_question_id ? (string) get_post_meta( $ms_question_id, 'type', true ) : '';

			// Unsupported source types have no MasterStudy question type — nothing to grade against.
			if ( ! $ms_question_id || '' === $ms_type ) {
				$dropped_types[] = static::question_type_label( (string) $raw->question_type );
				continue;
			}

			$answers[] = array(
				'question_id' => $ms_question_id,
				'answer'      => static::build_user_answer( $ms_type, (int) $raw->question_id, (string) $raw->question_type, (string) $raw->given_answer ),
				'correct'     => (bool) $raw->is_correct,
			);
		}

		$total_marks = (float) $attempt->total_marks;
		$percent     = $total_marks > 0 ? ( (float) $attempt->earned_marks / $total_marks ) * 100 : 0.0;

		// Tutor stores the verdict in `result` (pass|fail|pending); older data used attempt_status.
		$result = strtolower( (string) ( $attempt->result ?? '' ) );
		if ( 'pass' === $result || 'pass' === $attempt->attempt_status ) {
			$passed = true;
		} elseif ( 'fail' === $result || 'fail' === $attempt->attempt_status ) {
			$passed = false;
		} else {
			$passing_grade = (float) get_post_meta( $quiz_id, 'passing_grade', true );
			$passed        = $percent >= $passing_grade;
		}

		// Idempotency: score + result too — a failed attempt and its passed retry can share the same second.
		if ( Target::quiz_attempt_exists( $user_id, $quiz_id, $attempt->attempt_started_at, $percent, $passed ) ) {
			return;
		}

		Target::add_quiz_attempt( $user_id, $course_id, $quiz_id, $percent, $passed, $attempt->attempt_started_at, $answers );

		if ( ! empty( $dropped_types ) ) {
			static::report_quiz_attempt(
				$attempt,
				__( 'Quiz attempt', 'masterstudy-lms-learning-management-system' ),
				sprintf(
					/* translators: 1: number of answers, 2: question type labels */
					__( 'The attempt was imported with its score, but %1$d answer(s) to questions MasterStudy could not import (%2$s) were left out of the answer details.', 'masterstudy-lms-learning-management-system' ),
					count( $dropped_types ),
					implode( ', ', array_unique( $dropped_types ) )
				),
				Report::STATUS_PARTIAL,
				$course_id
			);
		}
	}

	/**
	 * Migrate a single tutor-google-meet post that the courses step did not place in a
	 * curriculum (course-level meetings, or topic meetings of courses copied before).
	 *
	 * Copies the meeting into a new stm-google-meets post (MasterStudy Pro Google Meet schema) and attaches the
	 * copy to the curriculum of the course copy. The Tutor meeting is never modified.
	 *
	 * @param int $post_id tutor-google-meet post ID.
	 */
	public static function migrate_single_google_meet( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post ) {
			throw new \Exception( sprintf( 'Tutor LMS Google Meet #%d no longer exists.', $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		if ( ! ProTarget::plus_active() ) {
			Helper::log( 'warning', sprintf( 'Tutor LMS: Google Meet %d not migrated — requires MasterStudy LMS Pro Plus.', $post_id ) );
			static::report_curriculum_item(
				Report::GROUP_MEETINGS,
				$post,
				0,
				__( 'Tutor Google Meet', 'masterstudy-lms-learning-management-system' ),
				__( 'Google Meet sessions require MasterStudy LMS Pro Plus (Google Meet addon), which is not active — the meeting was not copied.', 'masterstudy-lms-learning-management-system' ),
				Report::STATUS_UNSUPPORTED
			);
			return;
		}

		// Resolve the Tutor course and topic (source data), then the course copy and the section built from the topic.
		$source_course = 0;
		$topic_id      = 0;
		$parent        = $post->post_parent ? get_post( $post->post_parent ) : null;

		if ( $parent && 'topics' === $parent->post_type ) {
			$source_course = (int) $parent->post_parent;
			$topic_id      = (int) $parent->ID;
		} elseif ( $parent ) {
			$source_course = (int) $parent->ID;
		}

		if ( ! $source_course ) {
			$source_course = (int) get_post_meta( $post_id, '_tutor_google_meet_course_id', true );
		}

		$course_id = Target::copy_of( self::SOURCE, $source_course );
		$meet_id   = static::convert_google_meet( $post_id );

		if ( ! $course_id || PostType::COURSE !== get_post_type( $course_id ) ) {
			Helper::log( 'warning', sprintf( 'Tutor LMS: Google Meet %d copied but not attached to a curriculum — its course was not found or not migrated.', $post_id ) );
			Report::add(
				Report::GROUP_MEETINGS,
				array(
					'source_id' => $post_id,
					'title'     => $post->post_title,
					'type'      => __( 'Tutor Google Meet', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'The meeting was copied, but its course was not found or not migrated, so it is not attached to any course curriculum.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_PARTIAL,
					'course'    => $source_course ? get_post_field( 'post_title', $source_course ) : '',
					'post_id'   => $meet_id,
				)
			);
			return;
		}

		// Already in the curriculum, or an unpublished topic meeting the courses step kept out of it (reported there).
		if ( in_array( $course_id, Target::course_ids_of( $meet_id ), true ) || ( $topic_id && ! in_array( $post->post_status, array( 'publish', 'inherit' ), true ) ) ) {
			return;
		}

		$sections   = get_post_meta( $course_id, self::TOPIC_SECTIONS_META, true );
		$section_id = $topic_id && is_array( $sections ) ? (int) ( $sections[ $topic_id ] ?? 0 ) : 0;

		if ( $section_id && static::section_belongs_to_course( $section_id, $course_id ) ) {
			Target::add_material( $section_id, $meet_id, static::next_material_order( $section_id ) );
			return;
		}

		static::append_to_live_sessions( $course_id, $meet_id );
	}

	/**
	 * Migrate every Tutor submission (`tutor_assignment` comment) of one assignment to MasterStudy Pro
	 * student assignments of the assignment copy, oldest first so attempt numbers follow the source order.
	 *
	 * The source comments are kept; each submission is imported once (idempotent per comment ID).
	 *
	 * @param int $source_assignment_id Tutor assignment post ID (copied to stm-assignments by the courses step).
	 * @throws \Exception If the assignment is missing or was not migrated.
	 */
	public static function migrate_assignment_submissions( int $source_assignment_id ): void {
		global $wpdb;

		$assignment = get_post( $source_assignment_id );
		if ( ! $assignment ) {
			throw new \Exception( sprintf( 'Tutor LMS assignment #%d no longer exists.', $source_assignment_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$assignment_id = Target::copy_of( self::SOURCE, $source_assignment_id );

		if ( ! $assignment_id || PostType::ASSIGNMENT !== get_post_type( $assignment_id ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \Exception( sprintf( 'Submissions of assignment "%1$s" (#%2$d) not imported: the assignment has not been migrated — run the Courses step first.', $assignment->post_title, $source_assignment_id ) );
		}

		$submissions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT comment_ID, comment_parent, user_id, comment_content, comment_approved, comment_date, comment_date_gmt
				 FROM {$wpdb->comments}
				 WHERE comment_post_ID = %d AND comment_type = %s
				 ORDER BY comment_ID ASC",
				$source_assignment_id,
				self::SUBMISSION_COMMENT_TYPE
			)
		);

		$marks          = static::assignment_marks( $source_assignment_id );
		$assignment_ids = Target::course_ids_of( $assignment_id );

		foreach ( (array) $submissions as $submission ) {
			$comment_id = (int) $submission->comment_ID;
			$student_id = (int) $submission->user_id;
			// Tutor keeps the course ID in comment_parent.
			$course_id  = Target::copy_of( self::SOURCE, (int) $submission->comment_parent );
			$title      = sprintf( '%1$s — %2$s', static::user_label( $student_id ), $assignment->post_title );

			if ( ! $course_id || ! in_array( $course_id, $assignment_ids, true ) ) {
				$course_id = $assignment_ids ? (int) $assignment_ids[0] : 0;
			}

			$failure = '';
			if ( ! $student_id || ! get_userdata( $student_id ) ) {
				$failure = __( 'The student who submitted it no longer exists.', 'masterstudy-lms-learning-management-system' );
			} elseif ( ! $course_id ) {
				$failure = __( 'The assignment is not part of any migrated course.', 'masterstudy-lms-learning-management-system' );
			}

			if ( '' !== $failure ) {
				Report::add(
					Report::GROUP_ASSIGNMENTS,
					array(
						'source_id' => $comment_id,
						'title'     => $title,
						'type'      => __( 'Assignment submission', 'masterstudy-lms-learning-management-system' ),
						'reason'    => $failure,
						'status'    => Report::STATUS_FAILED,
						'post_id'   => $assignment_id,
					)
				);
				continue;
			}

			// Tutor: submitting = started, not submitted; submitted + evaluate_time = reviewed (pass if mark ≥ pass mark).
			$grade     = null;
			$evaluated = '' !== (string) get_comment_meta( $comment_id, 'evaluate_time', true );
			$mark_raw  = (string) get_comment_meta( $comment_id, 'assignment_mark', true );

			if ( 'submitting' === $submission->comment_approved ) {
				$status = 'draft';
			} elseif ( $evaluated || '' !== $mark_raw ) {
				$mark   = (float) $mark_raw;
				$status = $mark >= $marks['pass'] ? 'passed' : 'not_passed';
				$grade  = $marks['total'] > 0 ? $mark / $marks['total'] * 100 : null;
			} else {
				$status = 'pending';
			}

			$missing_files = array();
			$attachments   = static::submission_attachments( $comment_id, $student_id, $missing_files );

			$date = static::local_datetime_to_unix( (string) $submission->comment_date );
			if ( ! $date && ! empty( $submission->comment_date_gmt ) && '0000-00-00 00:00:00' !== $submission->comment_date_gmt ) {
				$date = (int) strtotime( $submission->comment_date_gmt . ' UTC' );
			}

			$submission_id = ProTarget::add_assignment_submission(
				array(
					'assignment_id' => $assignment_id,
					'course_id'     => $course_id,
					'student_id'    => $student_id,
					'content'       => (string) $submission->comment_content,
					'status'        => $status,
					'grade'         => $grade,
					'review'        => (string) get_comment_meta( $comment_id, 'instructor_note', true ),
					'attachments'   => $attachments,
					'date'          => $date ? $date : time(),
					'source_id'     => 'assignment-submission-' . $comment_id,
				),
				self::SOURCE
			);

			if ( ! empty( $missing_files ) ) {
				Report::add(
					Report::GROUP_ASSIGNMENTS,
					array(
						'source_id' => $comment_id,
						'title'     => $title,
						'type'      => __( 'Assignment submission', 'masterstudy-lms-learning-management-system' ),
						/* translators: %s: comma-separated file names */
						'reason'    => sprintf( __( 'The submission was imported, but these uploaded files were not found on the server: %s.', 'masterstudy-lms-learning-management-system' ), implode( ', ', $missing_files ) ),
						'status'    => Report::STATUS_PARTIAL,
						'course'    => get_post_field( 'post_title', $course_id ),
						'post_id'   => $submission_id,
					)
				);
			}
		}
	}

	/**
	 * Records a WooCommerce order in MasterStudy for a migrated enrollment.
	 *
	 * MasterStudy sells courses directly as WC products, so the Tutor WC order stays the
	 * payment source of truth; a MasterStudy order (payment_code 'woocommerce') is saved
	 * for the order history, linked back via `_wc_order_id`. The Tutor product is linked
	 * to the course via `stm_lms_product_id` in the courses step.
	 *
	 * @param int $order_id  WC order ID.
	 * @param int $course_id MasterStudy course post ID.
	 * @param int $user_id   Enrolled user ID (fallback when the WC order has no customer).
	 */
	public static function sync_wc_order_with_masterstudy( $order_id, $course_id, $user_id = 0 ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			static::report_skipped_order(
				(int) $order_id,
				__( 'WooCommerce order', 'masterstudy-lms-learning-management-system' ),
				__( 'WooCommerce is inactive, so the order could not be read — the enrollment was imported without its order record.', 'masterstudy-lms-learning-management-system' ),
				(int) $course_id,
				(int) $user_id
			);
			return;
		}

		$wc_order = \wc_get_order( $order_id );
		if ( ! $wc_order ) {
			static::report_skipped_order(
				(int) $order_id,
				__( 'WooCommerce order', 'masterstudy-lms-learning-management-system' ),
				__( 'The WooCommerce order linked to the enrollment no longer exists — the enrollment was imported without its order record.', 'masterstudy-lms-learning-management-system' ),
				(int) $course_id,
				(int) $user_id
			);
			return;
		}

		$product_id = (int) get_post_meta( $course_id, '_wc_product_id', true );
		$price      = 0.0;

		foreach ( $wc_order->get_items() as $order_item ) {
			if ( ! is_a( $order_item, 'WC_Order_Item_Product' ) ) {
				continue;
			}
			// Tag the order item if it matches this course's WC product.
			if ( $product_id && (int) $order_item->get_product_id() === $product_id ) {
				$order_item->update_meta_data( '_masterstudy_lms-course', 'yes' );
				$order_item->save_meta_data();
				$price = (float) $order_item->get_total();
			}
		}

		$source_id = 'wc-' . (int) $order_id;
		$items     = array(
			array(
				'course_id' => (int) $course_id,
				'price'     => $price,
			),
		);

		// One WC order can hold several Tutor courses (one enrollment each) — merge into one MasterStudy order.
		$existing = Target::find_migrated_post( PostType::ORDER, self::SOURCE, $source_id );
		if ( $existing ) {
			foreach ( (array) get_post_meta( $existing, 'items', true ) as $existing_item ) {
				$existing_course = (int) ( $existing_item['item_id'] ?? 0 );
				if ( $existing_course && $existing_course !== (int) $course_id ) {
					$items[] = array(
						'course_id' => $existing_course,
						'price'     => (float) ( $existing_item['price'] ?? 0 ),
					);
				}
			}
		}

		$date_created  = $wc_order->get_date_created();
		$buyer_user_id = (int) $wc_order->get_customer_id();

		$ms_order_id = Target::save_order(
			array(
				'source_id'      => $source_id,
				'user_id'        => $buyer_user_id ? $buyer_user_id : (int) $user_id,
				'items'          => $items,
				'status'         => static::convert_wc_status( $wc_order->get_status() ),
				'date'           => $date_created ? $date_created->getTimestamp() : time(),
				'total'          => (float) $wc_order->get_total(),
				'subtotal'       => (float) $wc_order->get_subtotal(),
				'taxes'          => (float) $wc_order->get_total_tax(),
				'currency'       => $wc_order->get_currency(),
				'payment_code'   => 'woocommerce',
				'transaction_id' => $wc_order->get_transaction_id(),
			),
			self::SOURCE
		);

		update_post_meta( $ms_order_id, '_wc_order_id', (int) $order_id );
	}

	/**
	 * Copies an EDD payment post into a new MasterStudy order (stm-orders); the payment stays.
	 *
	 * @param int       $payment_id EDD payment post ID.
	 * @param int|array $course_id  MasterStudy course post ID (the whole payment total is its price), or a list of
	 *                              ['course_id' => int, 'price' => float] items (multi-download carts).
	 */
	public static function insert_edd_order( $payment_id, $course_id ) {
		if ( ! function_exists( 'edd_get_payment' ) ) {
			return;
		}

		$payment = \edd_get_payment( $payment_id );
		if ( ! $payment || ! $payment->ID ) {
			Helper::log( 'warning', sprintf( 'Order %d skipped: EDD payment record not found.', $payment_id ) );
			static::report_skipped_order(
				(int) $payment_id,
				__( 'EDD payment', 'masterstudy-lms-learning-management-system' ),
				__( 'The EDD payment record could not be loaded.', 'masterstudy-lms-learning-management-system' ),
				is_array( $course_id ) ? (int) ( $course_id[0]['course_id'] ?? 0 ) : (int) $course_id
			);
			return;
		}

		$status_map = array(
			'complete'    => 'completed',
			'publish'     => 'completed',
			'pending'     => 'pending',
			'processing'  => 'pending',
			'refunded'    => 'refunded',
			'failed'      => 'failed',
			'cancelled'   => 'cancelled',
			'revoked'     => 'cancelled',
			'preapproval' => 'pending',
			'abandoned'   => 'cancelled',
		);

		$payment_date = $payment->date ? $payment->date : current_time( 'mysql' );
		$edd_currency = function_exists( 'edd_get_currency' ) ? \edd_get_currency() : get_option( 'woocommerce_currency', 'USD' );
		$total        = (float) $payment->total;
		// Use pre-discount subtotal; $payment->total is the post-discount amount.
		$subtotal = isset( $payment->subtotal ) && $payment->subtotal > 0 ? (float) $payment->subtotal : $total;

		$items = is_array( $course_id )
			? $course_id
			: array(
				array(
					'course_id' => (int) $course_id,
					'price'     => $total,
				),
			);
		$course_id = (int) ( $items[0]['course_id'] ?? 0 );

		$order_id = Target::save_order(
			array(
				'order_id'       => (int) $payment_id,
				'user_id'        => absint( $payment->user_id ),
				'items'          => $items,
				'status'         => $status_map[ $payment->status ] ?? 'pending',
				'date'           => static::local_datetime_to_unix( $payment_date ),
				'total'          => $total,
				'subtotal'       => $subtotal,
				'taxes'          => (float) ( $payment->tax ?? 0 ),
				'currency'       => ! empty( $payment->currency ) ? $payment->currency : $edd_currency,
				'payment_code'   => ! empty( $payment->gateway ) ? $payment->gateway : 'edd',
				'transaction_id' => $payment->transaction_id ?? '',
			),
			self::SOURCE
		);

		Target::store_unmigrated_meta(
			$order_id,
			'edd_billing',
			array_filter(
				array(
					'email'          => $payment->email ?? '',
					'first_name'     => is_array( $payment->user_info ) ? ( $payment->user_info['first_name'] ?? '' ) : '',
					'last_name'      => is_array( $payment->user_info ) ? ( $payment->user_info['last_name'] ?? '' ) : '',
					'completed_date' => $payment->completed_date ?? '',
				)
			)
		);
	}

	/**
	 * Creates a MasterStudy order (stm-orders) from a Tutor native checkout record.
	 *
	 * Course items are imported as is. A course bundle item becomes the bundle's courses (bundle price on the first
	 * one) when MasterStudy Pro is active and the bundle was migrated; subscription-plan items and anything else
	 * that cannot be resolved is left out of the order and reported. The Tutor coupon discount is stored in the
	 * MasterStudy coupon meta (coupon_value / coupon_type / coupon_id).
	 *
	 * @param int       $order_id Tutor order ID (tutor_orders.id).
	 * @param array|int $items    tutor_order_items rows (item_id, regular_price, sale_price, discount_price),
	 *                            or a single Tutor / MasterStudy course post ID (legacy signature).
	 */
	public static function insert_tutor_native_order( $order_id, $items = array() ) {
		global $wpdb;

		$order_id = (int) $order_id;

		if ( is_numeric( $items ) ) {
			$items = (int) $items ? array( (object) array( 'item_id' => (int) $items ) ) : array();
		}

		$customers_table = $wpdb->prefix . 'tutor_customers';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$customers_table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $customers_table ) ) === $customers_table;

		if ( $customers_table_exists ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$tutor_order = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT o.*, c.billing_first_name, c.billing_last_name, c.billing_email,
					        c.billing_phone, c.billing_address, c.billing_city, c.billing_state,
					        c.billing_country, c.billing_zip_code
					 FROM {$wpdb->prefix}tutor_orders o
					 LEFT JOIN {$customers_table} c ON c.user_id = o.user_id
					 WHERE o.id = %d",
					$order_id
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$tutor_order = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT o.* FROM {$wpdb->prefix}tutor_orders o WHERE o.id = %d",
					$order_id
				)
			);
		}

		$first_item = $items ? (int) ( reset( $items )->item_id ?? 0 ) : 0;

		if ( ! $tutor_order ) {
			Helper::log( 'warning', sprintf( 'Order %d skipped: Tutor order record not found.', $order_id ) );
			static::report_skipped_order(
				$order_id,
				__( 'Tutor LMS order', 'masterstudy-lms-learning-management-system' ),
				__( 'The Tutor LMS order record could not be loaded.', 'masterstudy-lms-learning-management-system' ),
				$first_item
			);
			return;
		}

		$user_id = absint( $tutor_order->user_id );

		// Map Tutor order/payment status to a MasterStudy order status.
		$status = 'pending';
		if ( 'paid' === $tutor_order->payment_status || 'completed' === $tutor_order->order_status ) {
			$status = 'completed';
		} elseif ( in_array( $tutor_order->payment_status, array( 'refunded', 'partially-refunded' ), true ) ) {
			$status = 'refunded';
		} elseif ( 'failed' === $tutor_order->payment_status ) {
			$status = 'failed';
		} elseif ( in_array( $tutor_order->order_status, array( 'cancelled', 'trash' ), true ) ) {
			$status = 'cancelled';
		}

		$order_date    = ! empty( $tutor_order->created_at_gmt ) ? strtotime( $tutor_order->created_at_gmt . ' UTC' ) : time();
		$order_date    = $order_date ? (int) $order_date : time();
		$total         = isset( $tutor_order->total_price ) ? (float) $tutor_order->total_price : 0.0;
		$coupon_code   = sanitize_text_field( (string) ( $tutor_order->coupon_code ?? '' ) );
		$coupon_amount = ! empty( $tutor_order->coupon_amount ) ? (float) $tutor_order->coupon_amount : 0.0;
		$tutor_option  = get_option( 'tutor_option' );
		// Prefer the per-order currency column (if any); fall back to the site-wide setting.
		$currency = ! empty( $tutor_order->currency )
			? $tutor_order->currency
			: ( ( is_array( $tutor_option ) && ! empty( $tutor_option['currency_code'] ) )
				? $tutor_option['currency_code']
				: ( ( is_array( $tutor_option ) && ! empty( $tutor_option['tutor_currency'] ) ) ? $tutor_option['tutor_currency'] : get_option( 'woocommerce_currency', 'USD' ) ) );

		// Price of an item without price columns (legacy course-ID call): the order amount before tax and coupon.
		$fallback_price = ( isset( $tutor_order->pre_tax_price ) && '' !== (string) $tutor_order->pre_tax_price ? (float) $tutor_order->pre_tax_price : $total ) + $coupon_amount;

		$is_subscription_order = in_array( (string) ( $tutor_order->order_type ?? '' ), array( 'subscription', 'renewal' ), true );
		$ms_items              = array();
		$bundle_courses        = array();
		$left_out              = array();

		foreach ( (array) $items as $item ) {
			$item_id = (int) ( $item->item_id ?? 0 );
			$price   = isset( $item->regular_price ) ? static::tutor_item_price( $item ) : $fallback_price;

			if ( $is_subscription_order ) {
				/* translators: %d: Tutor subscription plan ID */
				$left_out[] = sprintf( __( 'subscription plan #%d (subscription orders have no MasterStudy order item equivalent)', 'masterstudy-lms-learning-management-system' ), $item_id );
				continue;
			}

			$item_type = $item_id ? get_post_type( $item_id ) : false;
			// Tutor course items are sold as their MasterStudy copies (a MasterStudy course ID is kept as is).
			$course_id = 'courses' === $item_type ? Target::copy_of( self::SOURCE, $item_id ) : ( PostType::COURSE === $item_type ? $item_id : 0 );

			if ( $course_id ) {
				$ms_items[] = array(
					'course_id' => $course_id,
					'price'     => $price,
				);
				continue;
			}

			if ( 'course-bundle' === $item_type ) {
				$ms_bundle_id = ProTarget::pro_active() ? Target::find_migrated_post( PostType::COURSE_BUNDLES, self::SOURCE, 'bundle-' . $item_id ) : 0;
				$courses      = $ms_bundle_id ? static::migrated_courses_only( (array) get_post_meta( $ms_bundle_id, 'stm_lms_bundle_ids', true ) ) : array();

				if ( empty( $courses ) ) {
					$left_out[] = sprintf(
						/* translators: 1: bundle title, 2: reason */
						__( 'course bundle "%1$s" (%2$s)', 'masterstudy-lms-learning-management-system' ),
						get_post_field( 'post_title', $item_id ),
						! ProTarget::pro_active()
							? __( 'course bundles require MasterStudy LMS Pro', 'masterstudy-lms-learning-management-system' )
							: __( 'the bundle or its courses were not migrated', 'masterstudy-lms-learning-management-system' )
					);
					continue;
				}

				// MasterStudy order items are courses: the bundle price goes on the first course of the bundle.
				foreach ( $courses as $index => $bundle_course_id ) {
					$ms_items[] = array(
						'course_id' => $bundle_course_id,
						'price'     => 0 === $index ? $price : 0.0,
					);
				}

				$bundle_courses[ $ms_bundle_id ] = $courses;
				continue;
			}

			$left_out[] = 'courses' === $item_type
				/* translators: %s: course title */
				? sprintf( __( 'course "%s" (not migrated)', 'masterstudy-lms-learning-management-system' ), get_post_field( 'post_title', $item_id ) )
				/* translators: %d: Tutor order item ID */
				: sprintf( __( 'item #%d (no longer exists)', 'masterstudy-lms-learning-management-system' ), $item_id );
		}

		// MasterStudy subtotal = sum of the item prices before the coupon (sale prices already applied).
		$subtotal = ! empty( $ms_items )
			? array_sum( array_column( $ms_items, 'price' ) )
			: ( isset( $tutor_order->subtotal_price ) ? (float) $tutor_order->subtotal_price : $total );

		$ms_order_id = Target::save_order(
			array(
				'source_id'      => 'tutor-order-' . $order_id,
				'user_id'        => $user_id,
				'items'          => $ms_items,
				'status'         => $status,
				'date'           => $order_date,
				'total'          => $total,
				'subtotal'       => $subtotal,
				'taxes'          => ! empty( $tutor_order->tax_amount ) ? (float) $tutor_order->tax_amount : 0.0,
				'currency'       => $currency,
				'payment_code'   => ! empty( $tutor_order->payment_method ) ? $tutor_order->payment_method : 'tutor',
				'transaction_id' => $tutor_order->transaction_id ?? '',
			),
			self::SOURCE
		);

		if ( ! empty( $tutor_order->note ) ) {
			update_post_meta( $ms_order_id, 'order_note', sanitize_textarea_field( $tutor_order->note ) );
		}

		if ( '' !== $coupon_code && $coupon_amount > 0 ) {
			static::set_order_coupon( $ms_order_id, $coupon_code, $coupon_amount, count( $ms_items ) <= 1 );
		}

		// Raw coupon / manual (sale) discount data kept for reference; discount_amount is not a coupon.
		$discount_amount = ! empty( $tutor_order->discount_amount ) ? (float) $tutor_order->discount_amount : 0.0;
		if ( '' !== $coupon_code || $discount_amount > 0 ) {
			Target::store_unmigrated_meta(
				$ms_order_id,
				'tutor_coupon',
				array(
					'code'            => $coupon_code,
					'coupon_amount'   => $coupon_amount,
					'discount'        => $discount_amount,
					'discount_type'   => (string) ( $tutor_order->discount_type ?? '' ),
					'discount_reason' => sanitize_text_field( (string) ( $tutor_order->discount_reason ?? '' ) ),
				)
			);
		}

		if ( ! empty( $tutor_order->tax_type ) ) {
			Target::store_unmigrated_meta( $ms_order_id, 'tutor_tax_type', sanitize_text_field( $tutor_order->tax_type ) );
		}

		// MasterStudy orders have no refunded amount — a partial refund keeps the order completed.
		if ( ! empty( $tutor_order->refund_amount ) && (float) $tutor_order->refund_amount > 0 ) {
			Target::store_unmigrated_meta( $ms_order_id, 'tutor_refund_amount', (float) $tutor_order->refund_amount );

			if ( 'refunded' !== $status ) {
				/* translators: %s: refunded amount */
				$left_out[] = sprintf( __( 'partial refund of %s (MasterStudy orders have no partial refunds)', 'masterstudy-lms-learning-management-system' ), (string) $tutor_order->refund_amount );
			}
		}

		Target::store_unmigrated_meta(
			$ms_order_id,
			'tutor_billing',
			array_filter(
				array(
					'first_name' => $tutor_order->billing_first_name ?? '',
					'last_name'  => $tutor_order->billing_last_name ?? '',
					'email'      => $tutor_order->billing_email ?? '',
					'phone'      => $tutor_order->billing_phone ?? '',
					'address'    => $tutor_order->billing_address ?? '',
					'city'       => $tutor_order->billing_city ?? '',
					'state'      => $tutor_order->billing_state ?? '',
					'country'    => $tutor_order->billing_country ?? '',
					'zip_code'   => $tutor_order->billing_zip_code ?? '',
				)
			)
		);

		// A paid bundle grants its courses: make sure the buyer is enrolled in each one, linked to the bundle.
		if ( 'completed' === $status && $user_id && get_userdata( $user_id ) ) {
			foreach ( $bundle_courses as $ms_bundle_id => $courses ) {
				foreach ( $courses as $bundle_course_id ) {
					$was_enrolled   = Target::is_enrolled( $user_id, $bundle_course_id );
					$user_course_id = Target::enroll( $user_id, $bundle_course_id, $order_date );

					$wpdb->query(
						$wpdb->prepare(
							"UPDATE {$wpdb->prefix}stm_lms_user_courses SET bundle_id = %d WHERE user_course_id = %d AND ( bundle_id IS NULL OR bundle_id = 0 )",
							(int) $ms_bundle_id,
							$user_course_id
						)
					);

					if ( ! $was_enrolled ) {
						Target::refresh_students_count( $bundle_course_id );
					}
				}
			}
		}

		if ( ! empty( $left_out ) || empty( $ms_items ) ) {
			Report::add(
				Report::GROUP_ORDERS,
				array(
					'source_id' => $order_id,
					/* translators: 1: order ID, 2: buyer */
					'title'     => sprintf( __( 'Order #%1$d — %2$s', 'masterstudy-lms-learning-management-system' ), $order_id, static::user_label( $user_id ) ),
					'type'      => __( 'Tutor LMS order', 'masterstudy-lms-learning-management-system' ),
					'reason'    => ! empty( $left_out )
						/* translators: %s: list of order items */
						? sprintf( __( 'The order was imported without these items: %s.', 'masterstudy-lms-learning-management-system' ), implode( '; ', $left_out ) )
						: __( 'The order has no items that could be linked to a migrated course — it was imported without items.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_PARTIAL,
					'post_id'   => $ms_order_id,
				)
			);
		}
	}

	/**
	 * Price a Tutor order item sold for before the order coupon: the sale price when lower than the
	 * regular price, else the regular price (Tutor OrderModel::calculate_order_price()).
	 *
	 * @param object $item tutor_order_items row.
	 */
	private static function tutor_item_price( $item ): float {
		$regular = (float) ( $item->regular_price ?? 0 );
		$sale    = $item->sale_price ?? null;

		if ( null !== $sale && '' !== (string) $sale && (float) $sale < $regular ) {
			return (float) $sale;
		}

		return $regular;
	}

	/**
	 * Store a Tutor coupon discount in the MasterStudy order coupon meta.
	 *
	 * Percentage coupons on a single-item order keep their rate; otherwise (flat or deleted coupons, or a
	 * coupon that may cover only some items) the amount actually deducted is stored. coupon_id links the
	 * MasterStudy coupon imported from the same code (Pro Plus), when it exists.
	 *
	 * @param int    $ms_order_id   MasterStudy order ID.
	 * @param string $code          Tutor coupon code.
	 * @param float  $coupon_amount Discount the coupon applied to the order (tutor_orders.coupon_amount).
	 * @param bool   $keep_percent  Whether a percentage coupon may be stored as its rate.
	 */
	private static function set_order_coupon( int $ms_order_id, string $code, float $coupon_amount, bool $keep_percent = true ): void {
		global $wpdb;

		$type  = 'amount';
		$value = $coupon_amount;

		if ( $keep_percent && static::table_exists( $wpdb->prefix . 'tutor_coupons' ) ) {
			$coupon = $wpdb->get_row(
				$wpdb->prepare( "SELECT discount_type, discount_amount FROM {$wpdb->prefix}tutor_coupons WHERE coupon_code = %s LIMIT 1", $code )
			);

			if ( $coupon && 'percentage' === $coupon->discount_type && (float) $coupon->discount_amount > 0 ) {
				$type  = 'percent';
				$value = (float) $coupon->discount_amount;
			}
		}

		update_post_meta( $ms_order_id, 'coupon_value', $value );
		update_post_meta( $ms_order_id, 'coupon_type', $type );

		$ms_coupons = $wpdb->prefix . 'stm_lms_coupons';
		$coupon_id  = static::table_exists( $ms_coupons )
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			? (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$ms_coupons} WHERE code = %s LIMIT 1", strtoupper( $code ) ) )
			: 0;

		if ( $coupon_id ) {
			update_post_meta( $ms_order_id, 'coupon_id', $coupon_id );
		}
	}

	/**
	 * Migrate all TutorLMS wishlist entries for a single user to the MasterStudy wishlist (course copies).
	 *
	 * Tutor stores one `_tutor_course_wishlist` usermeta row per wishlisted course; the rows stay.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return void
	 */
	private static function migrate_single_wishlist_user( int $user_id ): void {
		$rows       = get_user_meta( $user_id, '_tutor_course_wishlist', false );
		$course_ids = array();

		foreach ( (array) $rows as $row ) {
			foreach ( (array) maybe_unserialize( $row ) as $course_id ) {
				$copy_id = Target::copy_of( self::SOURCE, (int) $course_id );
				if ( $copy_id && PostType::COURSE === get_post_type( $copy_id ) ) {
					$course_ids[] = $copy_id;
				}
			}
		}

		if ( ! empty( $course_ids ) ) {
			Target::add_to_wishlist( $user_id, $course_ids );
		}
	}

	/**
	 * Migrate one Tutor Pro private lesson note (`lesson_note` comment) to a MasterStudy Pro Plus lesson note of the
	 * lesson copy. Notes are private to their author: they go to the private notes table (never to the public lesson
	 * discussion). The Tutor note stays; the import is idempotent (ProTarget::add_lesson_note()).
	 *
	 * @param int $comment_id Note comment ID.
	 * @throws \Exception If the note no longer exists or cannot be written.
	 */
	public static function migrate_single_lesson_note( int $comment_id ): void {
		$note = get_comment( $comment_id );

		if ( ! $note || self::NOTE_COMMENT_TYPE !== $note->comment_type ) {
			throw new \Exception( sprintf( 'Tutor LMS lesson note (comment #%d) no longer exists.', $comment_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$source_lesson = (int) $note->comment_post_ID;
		$lesson_id     = Target::copy_of( self::SOURCE, $source_lesson );
		$user_id       = (int) $note->user_id;
		$report        = array(
			'source_id' => $comment_id,
			/* translators: 1: note author, 2: lesson title */
			'title'     => sprintf( __( 'Note by %1$s on "%2$s"', 'masterstudy-lms-learning-management-system' ), static::user_label( $user_id ), get_post_field( 'post_title', $source_lesson ) ),
			'type'      => __( 'Private lesson note', 'masterstudy-lms-learning-management-system' ),
			'post_id'   => $lesson_id ? $lesson_id : $source_lesson,
		);

		if ( ! $lesson_id || PostType::LESSON !== get_post_type( $lesson_id ) ) {
			Report::add(
				Report::GROUP_OTHER,
				$report + array(
					'reason' => __( 'The lesson was not migrated (it is not part of a migrated course), so the private note was not imported.', 'masterstudy-lms-learning-management-system' ),
					'status' => Report::STATUS_UNSUPPORTED,
				)
			);
			return;
		}

		$course_ids = Target::course_ids_of( $lesson_id );

		if ( ! ProTarget::lesson_notes_available() || empty( $course_ids ) || ! $user_id || ! get_userdata( $user_id ) ) {
			if ( ! ProTarget::lesson_notes_available() ) {
				$reason = __( 'Private lesson notes require MasterStudy LMS Pro Plus — the note was not imported (it stays in Tutor LMS). Run the migration again with Pro Plus to import it.', 'masterstudy-lms-learning-management-system' );
			} elseif ( empty( $course_ids ) ) {
				$reason = __( 'The lesson is not part of any migrated course curriculum — the note was not imported.', 'masterstudy-lms-learning-management-system' );
			} else {
				$reason = __( 'The note\'s author no longer exists — the note was not imported.', 'masterstudy-lms-learning-management-system' );
			}

			Report::add(
				Report::GROUP_OTHER,
				$report + array(
					'reason' => $reason,
					'status' => Report::STATUS_UNSUPPORTED,
					'course' => $course_ids ? get_post_field( 'post_title', (int) $course_ids[0] ) : '',
				)
			);
			return;
		}

		$info        = json_decode( (string) get_comment_meta( $comment_id, self::NOTE_META, true ), true );
		$info        = is_array( $info ) ? $info : array();
		$note_type   = (string) ( $info['type'] ?? '' );
		$lesson_type = (string) get_post_meta( $lesson_id, 'type', true );

		if ( 'video' === $note_type ) {
			$type = 'video';
		} elseif ( 'highlight' !== $note_type && in_array( $lesson_type, array( 'video', 'audio' ), true ) ) {
			$type = $lesson_type;
		} else {
			$type = 'text';
		}

		$created = ! empty( $note->comment_date_gmt ) && '0000-00-00 00:00:00' !== $note->comment_date_gmt
			? (string) $note->comment_date_gmt
			: get_gmt_from_date( (string) $note->comment_date );

		ProTarget::add_lesson_note(
			array(
				'user_id'       => $user_id,
				'course_id'     => (int) $course_ids[0],
				'lesson_id'     => $lesson_id,
				'lesson_type'   => $type,
				'body'          => (string) $note->comment_content,
				'selected_text' => 'highlight' === $note_type ? (string) ( $info['text'] ?? '' ) : '',
				'media_time'    => 'video' === $note_type && isset( $info['video_start'] ) && is_numeric( $info['video_start'] ) ? (int) $info['video_start'] : null,
				'created_at'    => $created,
			)
		);
	}

	/**
	 * Turn on the MasterStudy course player "Lesson notes" setting once Tutor notes were imported
	 * (the notes are not shown to their authors otherwise).
	 */
	private static function enable_lesson_notes_setting(): void {
		global $wpdb;

		if ( ! ProTarget::lesson_notes_available() ) {
			return;
		}

		$imported = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}stm_lms_lesson_notes n
				 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = n.lesson_id AND pm.meta_key = %s AND pm.meta_value = %s",
				Target::SOURCE_META,
				self::SOURCE
			)
		);

		$settings = get_option( 'stm_lms_settings', array() );

		if ( $imported && is_array( $settings ) && empty( $settings['course_player_lesson_notes'] ) ) {
			$settings['course_player_lesson_notes'] = true;
			update_option( 'stm_lms_settings', $settings );
			Helper::log( 'info', sprintf( 'Tutor LMS: %d lesson note(s) imported — the course player "Lesson notes" setting was turned on.', $imported ) );
		}
	}

	/**
	 * Copy one Tutor curriculum item and return the MasterStudy post ID (the copy) to attach to the curriculum,
	 * or 0 when the item cannot be attached.
	 *
	 * @param \WP_Post $item             Tutor curriculum item (source post, never modified).
	 * @param int      $course_id        MasterStudy course post ID (the copy).
	 * @param int      $source_course_id Tutor course post ID.
	 */
	private static function migrate_curriculum_item( \WP_Post $item, int $course_id, int $source_course_id ): int {
		switch ( $item->post_type ) {
			case 'lesson':
			case 'cb-lesson':
				// cb-lesson: a Tutor Pro Content Bank lesson linked to the topic (a lesson linked into several courses is
				// copied once and shared by their curricula).
				return static::update_tutor_lesson_to_masterstudy( $item, $course_id, $source_course_id );

			case 'tutor_quiz':
				return static::update_tutor_course_quiz_to_masterstudy( $item, $course_id, $source_course_id );

			case 'tutor_assignments':
				return static::update_tutor_assignment_to_masterstudy( $item, $course_id, $source_course_id );

			case 'tutor_zoom_meeting':
				return static::update_tutor_zoom_to_masterstudy( $item, $course_id, $source_course_id );

			case 'tutor-google-meet':
				// Tutor Pro data is migrated whenever it exists, whether Tutor LMS Pro is still active or not.
				if ( ! ProTarget::plus_active() ) {
					Helper::log( 'warning', sprintf( 'Tutor LMS: Google Meet %d in course %d not migrated — requires MasterStudy LMS Pro Plus.', $item->ID, $source_course_id ) );
					static::report_curriculum_item(
						Report::GROUP_MEETINGS,
						$item,
						$source_course_id,
						__( 'Tutor Google Meet', 'masterstudy-lms-learning-management-system' ),
						__( 'Google Meet sessions require MasterStudy LMS Pro Plus (Google Meet addon), which is not active — the meeting was not copied.', 'masterstudy-lms-learning-management-system' ),
						Report::STATUS_UNSUPPORTED
					);
					return 0;
				}
				return static::convert_google_meet( $item->ID );
		}

		return 0;
	}

	/**
	 * Curriculum items of a Tutor topic in Tutor's order: the topic's own lessons, quizzes, assignments and
	 * live sessions, merged with the Content Bank lessons linked to the topic (Tutor Pro TopicContentExtender:
	 * usage rows without a copied post, ordered with the native items by content order).
	 *
	 * @param \WP_Post $topic     Topic post.
	 * @param int      $course_id Course post ID.
	 * @return \WP_Post[]
	 */
	private static function topic_items( \WP_Post $topic, int $course_id ): array {
		global $wpdb;

		$items = get_posts(
			array(
				'post_type'        => array( 'lesson', 'tutor_quiz', 'tutor_assignments', 'tutor_zoom_meeting', 'tutor-google-meet' ),
				'post_parent'      => $topic->ID,
				'posts_per_page'   => -1,
				'orderby'          => array(
					'menu_order' => 'ASC',
					'ID'         => 'ASC',
				),
				'post_status'      => 'any',
				'suppress_filters' => true,
			)
		);

		$usage = $wpdb->prefix . 'tutor_cb_content_usage';

		if ( ! static::table_exists( $usage ) ) {
			return $items;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$linked = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT content_id, content_order, object_type FROM {$usage}
				 WHERE course_id = %d AND topic_id = %d AND content_id IS NOT NULL
				   AND ( copied_post_id IS NULL OR copied_post_id = 0 )
				   AND ( quiz_id IS NULL OR quiz_id = 0 )
				 ORDER BY content_order ASC, id ASC",
				$course_id,
				$topic->ID
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $linked ) ) {
			return $items;
		}

		$rows = array();

		foreach ( $items as $index => $item ) {
			$rows[] = array( (int) $item->menu_order, 0, $index, $item );
		}

		foreach ( $linked as $index => $link ) {
			$post = get_post( (int) $link->content_id );

			if ( ! $post ) {
				continue;
			}

			// Tutor lists linked Content Bank lessons only; a linked assignment is never shown to students.
			if ( 'lesson' !== $link->object_type ) {
				static::report_curriculum_item(
					Report::GROUP_ASSIGNMENTS,
					$post,
					$course_id,
					__( 'Content Bank item', 'masterstudy-lms-learning-management-system' ),
					__( 'The Content Bank item is linked to the topic, but Tutor LMS only shows linked Content Bank lessons — it was not added to the course curriculum.', 'masterstudy-lms-learning-management-system' ),
					Report::STATUS_UNSUPPORTED
				);
				continue;
			}

			$rows[] = array( (int) $link->content_order, 1, $index, $post );
		}

		usort(
			$rows,
			function ( $a, $b ) {
				return array( $a[0], $a[1], $a[2] ) <=> array( $b[0], $b[1], $b[2] );
			}
		);

		return array_column( $rows, 3 );
	}

	/**
	 * Question rows of a Tutor quiz in Tutor's order: its own questions merged with the Content Bank questions
	 * linked to it (Tutor Pro QuestionContentExtender: usage rows without a copied question).
	 *
	 * @param int $quiz_id Quiz post ID.
	 * @return object[] tutor_quiz_questions rows.
	 */
	private static function quiz_question_rows( int $quiz_id ): array {
		global $wpdb;

		// get_questions_by_quiz() returns false for a quiz without questions.
		$questions = $quiz_id > 0 ? \tutor_utils()->get_questions_by_quiz( $quiz_id ) : array();
		$questions = is_array( $questions ) ? $questions : array();
		$usage     = $wpdb->prefix . 'tutor_cb_content_usage';

		if ( ! $quiz_id || ! static::table_exists( $usage ) ) {
			return $questions;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$linked = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT u.content_order, q.* FROM {$usage} u
				 INNER JOIN {$wpdb->prefix}tutor_quiz_questions q ON q.question_id = u.question_id
				 WHERE u.quiz_id = %d AND ( u.copied_question_id IS NULL OR u.copied_question_id = 0 )
				 ORDER BY u.content_order ASC, u.id ASC",
				$quiz_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $linked ) ) {
			return $questions;
		}

		$rows = array();
		$seen = array();

		foreach ( $questions as $index => $question ) {
			$seen[ (int) $question->question_id ] = true;
			$rows[]                               = array( (int) $question->question_order, 0, $index, $question );
		}

		foreach ( $linked as $index => $question ) {
			if ( isset( $seen[ (int) $question->question_id ] ) ) {
				continue;
			}

			$seen[ (int) $question->question_id ] = true;
			$rows[]                               = array( (int) $question->content_order, 1, $index, $question );
		}

		usort(
			$rows,
			function ( $a, $b ) {
				return array( $a[0], $a[1], $a[2] ) <=> array( $b[0], $b[1], $b[2] );
			}
		);

		return array_column( $rows, 3 );
	}

	/**
	 * Report label of a Tutor curriculum item post type.
	 *
	 * @param string $post_type Post type.
	 */
	private static function curriculum_item_label( string $post_type ): string {
		$labels = array(
			'lesson'             => __( 'Lesson', 'masterstudy-lms-learning-management-system' ),
			'cb-lesson'          => __( 'Content Bank lesson', 'masterstudy-lms-learning-management-system' ),
			'tutor_quiz'         => __( 'Quiz', 'masterstudy-lms-learning-management-system' ),
			'tutor_assignments'  => __( 'Tutor assignment', 'masterstudy-lms-learning-management-system' ),
			'tutor_zoom_meeting' => __( 'Tutor Zoom meeting', 'masterstudy-lms-learning-management-system' ),
			'tutor-google-meet'  => __( 'Tutor Google Meet', 'masterstudy-lms-learning-management-system' ),
		);

		return $labels[ $post_type ] ?? $post_type;
	}

	/**
	 * Copy a Tutor LMS quiz post (and its questions) into a MasterStudy quiz (stm-quizzes).
	 *
	 * @param \WP_Post $item             Tutor LMS quiz post object (source, never modified).
	 * @param int      $course_id        MasterStudy course post ID (the copy).
	 * @param int      $source_course_id Tutor course post ID (0 = the source of $course_id).
	 * @return int Quiz copy post ID.
	 */
	public static function update_tutor_course_quiz_to_masterstudy( $item, $course_id, $source_course_id = 0 ) {
		if ( 'tutor_quiz' !== $item->post_type ) {
			return 0;
		}

		$source_course_id = $source_course_id ? (int) $source_course_id : Target::source_of( (int) $course_id );
		$source_quiz_id   = (int) $item->ID;
		$quiz_id          = Target::copy_post( $source_quiz_id, PostType::QUIZ, self::SOURCE );

		// Quiz questions plus Content Bank questions linked to the quiz (Tutor Pro), in Tutor's order.
		$questions    = static::quiz_question_rows( $source_quiz_id );
		$question_ids = array();
		foreach ( (array) $questions as $question ) {
			$question_id = static::process_question_migration_from_tutor( $question, $quiz_id, $course_id );
			if ( $question_id ) {
				$question_ids[] = $question_id;
			}
		}

		Target::set_quiz_questions( $quiz_id, $question_ids );

		if ( ! empty( $questions ) && empty( $question_ids ) ) {
			Report::add(
				Report::GROUP_QUIZZES,
				array(
					'source_id' => $source_quiz_id,
					'title'     => $item->post_title,
					'type'      => __( 'Quiz', 'masterstudy-lms-learning-management-system' ),
					/* translators: %d: number of questions */
					'reason'    => sprintf( __( 'None of the quiz\'s %d question(s) could be imported (unsupported question types), so the quiz is empty in MasterStudy.', 'masterstudy-lms-learning-management-system' ), count( (array) $questions ) ),
					'status'    => Report::STATUS_PARTIAL,
					'parent'    => static::curriculum_parent_label( $item ),
					'course'    => get_post_field( 'post_title', $course_id ),
					'post_id'   => $quiz_id,
				)
			);
		}

		$quiz_options = get_post_meta( $source_quiz_id, 'tutor_quiz_option', true );
		$quiz_options = is_array( $quiz_options ) ? $quiz_options : array();

		// Mirrors Tutor 4 Quiz::normalize_quiz_settings(): the explicit v4 flags win; legacy quizzes derive them
		// from feedback_mode ('reveal' = show answers, 'retry' = retakes allowed, 'default' = neither).
		$feedback_mode = (string) ( $quiz_options['feedback_mode'] ?? '' );
		$allow_retry   = array_key_exists( 'limit_attempts_allowed', $quiz_options )
			? '1' === (string) $quiz_options['limit_attempts_allowed']
			: 'retry' === $feedback_mode;
		$show_answers  = array_key_exists( 'enable_answer_reveal', $quiz_options )
			? '1' === (string) $quiz_options['enable_answer_reveal']
			: 'reveal' === $feedback_mode;

		// Tutor (Quiz::can_retry_quiz / get_effective_attempts_allowed): without retakes a student gets one attempt;
		// with retakes `attempts_allowed` applies (Tutor default 10), 0 meaning unlimited (as in MasterStudy).
		$attempts = $allow_retry ? max( 0, (int) ( $quiz_options['attempts_allowed'] ?? 10 ) ) : 1;

		Target::set_quiz(
			$quiz_id,
			array(
				'duration_minutes'    => (int) ceil( static::quiz_duration_to_seconds( is_array( $quiz_options['time_limit'] ?? null ) ? $quiz_options['time_limit'] : array() ) / MINUTE_IN_SECONDS ),
				'passing_grade'       => (float) ( $quiz_options['passing_grade'] ?? 0 ),
				'attempts'            => $attempts,
				'random_questions'    => ! empty( $quiz_options['randomize_question'] ) || 'rand' === ( $quiz_options['questions_order'] ?? '' ),
				'show_correct_answer' => $show_answers,
				'excerpt'             => $item->post_excerpt,
			)
		);

		// MasterStudy "pagination" = one question per screen with a question navigator. That matches Tutor only when
		// the question pagination is explicitly on (v4 enable_pagination, legacy 'question_pagination' layout);
		// 'single_question' alone is Tutor 4's default layout, so it keeps the MasterStudy default style.
		$layout = (string) ( $quiz_options['question_layout_view'] ?? '' );
		if ( 'question_pagination' === $layout || ( '1' === (string) ( $quiz_options['enable_pagination'] ?? '' ) && 'question_below_each_other' !== $layout ) ) {
			update_post_meta( $quiz_id, 'quiz_style', 'pagination' );
		}

		// Options with no MasterStudy equivalent (pass_is_required, max_questions_for_answer, …) are kept for reference.
		Target::store_unmigrated_meta( $quiz_id, 'tutor_quiz_option', $quiz_options );

		static::migrate_item_drip( $source_quiz_id, $source_course_id, $quiz_id );

		/**
		 * Fires after a TutorLMS curriculum item has been migrated.
		 *
		 * @param int $item_id   Post ID of the migrated lesson/quiz/assignment (the MasterStudy copy).
		 * @param int $course_id Course post ID (the MasterStudy copy).
		 */
		do_action( 'masterstudy_lms_migration_tool_tutorlms_item_migrated', $quiz_id, $course_id );

		return $quiz_id;
	}

	/**
	 * Copy a Tutor LMS lesson post into a MasterStudy lesson (stm-lessons), with its lesson comments.
	 *
	 * @param \WP_Post $item             Tutor LMS lesson post object (source, never modified).
	 * @param int      $course_id        MasterStudy course post ID (the copy).
	 * @param int      $source_course_id Tutor course post ID (0 = the source of $course_id).
	 * @return int Lesson copy post ID.
	 */
	public static function update_tutor_lesson_to_masterstudy( $item, $course_id, $source_course_id = 0 ) {
		if ( ! in_array( $item->post_type, array( 'lesson', 'cb-lesson' ), true ) ) {
			return 0;
		}

		$source_course_id = $source_course_id ? (int) $source_course_id : Target::source_of( (int) $course_id );
		$source_lesson_id = (int) $item->ID;
		$lesson_id        = Target::copy_post( $source_lesson_id, PostType::LESSON, self::SOURCE );

		$video      = static::map_video_meta( $source_lesson_id );
		$is_preview = get_post_meta( $source_lesson_id, '_is_preview', true );

		Target::set_lesson(
			$lesson_id,
			array(
				'type'     => 'text',
				'duration' => $video ? $video['duration'] : '',
				'preview'  => '1' === (string) $is_preview || 'yes' === $is_preview,
				'excerpt'  => $item->post_excerpt,
			)
		);

		if ( ! empty( $video ) ) {
			Target::set_lesson_video( $lesson_id, $video['ms_source'], $video['value'], $video['poster'] );
		}

		// Attachments — tutor stores them in _tutor_attachments (shared attachment posts, referenced by ID).
		$attachments = maybe_unserialize( get_post_meta( $source_lesson_id, '_tutor_attachments', true ) );
		if ( ! empty( $attachments ) && is_array( $attachments ) ) {
			Target::set_lesson_files( $lesson_id, $attachments );
		}

		// Lesson comments become the MasterStudy lesson discussion (Tutor Pro private notes are imported by the
		// lesson_notes step into the private notes table, never as comments).
		static::copy_lesson_comments( $source_lesson_id, $lesson_id );

		static::migrate_item_drip( $source_lesson_id, $source_course_id, $lesson_id );

		/** This action is documented in includes/MigrationTool/LMS/TutorLMS.php */
		do_action( 'masterstudy_lms_migration_tool_tutorlms_item_migrated', $lesson_id, $course_id );

		return $lesson_id;
	}

	/**
	 * Copy a Tutor Pro assignment into a MasterStudy Pro assignment (stm-assignments).
	 *
	 * Student submissions are imported by the assignments step. Without MasterStudy Pro the
	 * assignment is not copied (reported).
	 *
	 * @param \WP_Post $item             Tutor assignment post (source, never modified).
	 * @param int      $course_id        MasterStudy course post ID (the copy).
	 * @param int      $source_course_id Tutor course post ID.
	 * @return int Assignment copy post ID, or 0 when not migrated.
	 */
	private static function update_tutor_assignment_to_masterstudy( \WP_Post $item, int $course_id, int $source_course_id ): int {
		if ( ! ProTarget::pro_active() ) {
			Helper::log( 'warning', sprintf( 'Tutor LMS: assignment %d in course %d not migrated — requires MasterStudy LMS Pro.', $item->ID, $source_course_id ) );
			static::report_curriculum_item(
				Report::GROUP_ASSIGNMENTS,
				$item,
				$source_course_id,
				__( 'Tutor assignment', 'masterstudy-lms-learning-management-system' ),
				__( 'Assignments require MasterStudy LMS Pro (Assignments addon), which is not active — the assignment and its submissions were not copied and are not in the course curriculum.', 'masterstudy-lms-learning-management-system' ),
				Report::STATUS_UNSUPPORTED
			);
			return 0;
		}

		$source_id = (int) $item->ID;
		$option    = get_post_meta( $source_id, 'assignment_option', true );
		$option    = is_array( $option ) ? $option : array();

		$assignment_id = Target::copy_post( $source_id, PostType::ASSIGNMENT, self::SOURCE );

		$marks = static::assignment_marks( $source_id );

		$retry_allowed = '0' !== (string) ( $option['is_retry_allowed'] ?? '1' );
		$tries         = $retry_allowed ? (int) ( $option['attempts_allowed'] ?? 5 ) : 1;

		$duration = is_array( $option['time_duration'] ?? null ) ? $option['time_duration'] : array();
		$unit_map = array(
			'hours' => 'hours',
			'days'  => 'days',
			'weeks' => 'weeks',
		);

		// Requests the Assignments addon (deferred until the batch commits).
		ProTarget::set_assignment(
			$assignment_id,
			array(
				'attempts'        => $tries,
				'passing_grade'   => $marks['total'] > 0 ? (int) round( $marks['pass'] / $marks['total'] * 100 ) : 0,
				'time_limit'      => (int) ( $duration['value'] ?? 0 ),
				'time_limit_unit' => $unit_map[ $duration['time'] ?? '' ] ?? 'hours',
				'files'           => (array) maybe_unserialize( get_post_meta( $source_id, '_tutor_assignment_attachments', true ) ),
			)
		);

		Target::store_unmigrated_meta( $assignment_id, 'tutor_assignment_option', $option );

		static::migrate_item_drip( $source_id, $source_course_id, $assignment_id );

		/** This action is documented in includes/MigrationTool/LMS/TutorLMS.php */
		do_action( 'masterstudy_lms_migration_tool_tutorlms_item_migrated', $assignment_id, $course_id );

		return $assignment_id;
	}

	/**
	 * Copy a Tutor Pro Zoom meeting into a MasterStudy Pro Zoom conference lesson for the existing meeting
	 * (ms-zoom post + lesson meta). The Zoom API is never called.
	 *
	 * Without a numeric meeting ID the lesson becomes a YouTube stream lesson (YouTube URLs only)
	 * or a text lesson with the join link. Without MasterStudy Pro the meeting is not copied (reported).
	 *
	 * @param \WP_Post $item             Tutor zoom meeting post (source, never modified).
	 * @param int      $course_id        MasterStudy course post ID (the copy).
	 * @param int      $source_course_id Tutor course post ID.
	 * @return int Lesson copy post ID, or 0 when not migrated.
	 */
	private static function update_tutor_zoom_to_masterstudy( \WP_Post $item, int $course_id, int $source_course_id ): int {
		if ( ! ProTarget::pro_active() ) {
			Helper::log( 'warning', sprintf( 'Tutor LMS: Zoom meeting %d in course %d not migrated — requires MasterStudy LMS Pro.', $item->ID, $source_course_id ) );
			static::report_curriculum_item(
				Report::GROUP_MEETINGS,
				$item,
				$source_course_id,
				__( 'Tutor Zoom meeting', 'masterstudy-lms-learning-management-system' ),
				__( 'Zoom meetings require MasterStudy LMS Pro (Zoom Conference addon), which is not active — the meeting was not copied and is not in the course curriculum.', 'masterstudy-lms-learning-management-system' ),
				Report::STATUS_UNSUPPORTED
			);
			return 0;
		}

		$source_id = (int) $item->ID;
		$data      = json_decode( (string) get_post_meta( $source_id, '_tutor_zm_data', true ), true );
		$data      = is_array( $data ) ? $data : array();

		$lesson_id = Target::copy_post( $source_id, PostType::LESSON, self::SOURCE );

		$duration = (int) get_post_meta( $source_id, '_tutor_zm_duration', true );
		$unit     = (string) get_post_meta( $source_id, '_tutor_zm_duration_unit', true );
		$minutes  = 'hr' === $unit ? $duration * 60 : $duration;
		$minutes  = $minutes > 0 ? $minutes : (int) ( $data['duration'] ?? 0 );
		$start    = static::zoom_start_time( $source_id, $data );
		$join_url = (string) ( $data['join_url'] ?? '' );

		Target::set_lesson(
			$lesson_id,
			array(
				'type'     => 'text',
				'duration' => $minutes > 0 ? static::format_duration( 0, $minutes, 0 ) : '',
				'excerpt'  => $item->post_excerpt,
			)
		);

		// Requests the Zoom Conference addon (deferred until the batch commits).
		$is_zoom = ProTarget::set_zoom_lesson(
			$lesson_id,
			array(
				'meeting_id' => (string) ( $data['id'] ?? '' ),
				'join_url'   => $join_url,
				'password'   => (string) ( $data['password'] ?? '' ),
				'start'      => $start,
				'duration'   => $minutes,
				'timezone'   => static::live_timezone( (string) ( $data['timezone'] ?? '' ) ),
				'agenda'     => '' !== trim( $item->post_content ) ? $item->post_content : (string) ( $data['agenda'] ?? '' ),
				'host_id'    => (int) $item->post_author,
			),
			self::SOURCE
		);

		if ( ! $is_zoom ) {
			$end = $start && $minutes > 0 ? $start + $minutes * MINUTE_IN_SECONDS : 0;

			if ( '' === $join_url || ! ProTarget::set_stream_lesson( $lesson_id, $join_url, $start, $end ) ) {
				static::append_join_link( $lesson_id, $join_url );
				static::report_curriculum_item(
					Report::GROUP_MEETINGS,
					$item,
					$source_course_id,
					__( 'Tutor Zoom meeting', 'masterstudy-lms-learning-management-system' ),
					__( 'The Zoom meeting has no Zoom meeting ID, so it cannot be linked to a MasterStudy Zoom lesson — it was imported as a text lesson with the join link.', 'masterstudy-lms-learning-management-system' ),
					Report::STATUS_PARTIAL,
					$lesson_id
				);
			}
		}

		Target::store_unmigrated_meta(
			$lesson_id,
			'tutor_zoom_meeting',
			array_filter(
				array(
					'meeting_id' => $data['id'] ?? '',
					'password'   => $data['password'] ?? '',
					'timezone'   => $data['timezone'] ?? '',
					'start_url'  => $data['start_url'] ?? '',
				)
			)
		);

		/** This action is documented in includes/MigrationTool/LMS/TutorLMS.php */
		do_action( 'masterstudy_lms_migration_tool_tutorlms_item_migrated', $lesson_id, $course_id );

		return $lesson_id;
	}

	/**
	 * Enroll a Tutor student in the MasterStudy copy of a Tutor course.
	 *
	 * MasterStudy has no inactive enrollment state, so only completed (active) Tutor
	 * enrollments are written; pending/cancelled ones are skipped and reported.
	 *
	 * @param int      $course_id     Tutor course post ID (the enrollment's post_parent).
	 * @param string   $email         Enrolled user email.
	 * @param \WP_Post $enrolled_user Enrollment post object (tutor_enrolled CPT).
	 * @return int|false MasterStudy user_course_id, or false when skipped.
	 */
	public static function update_tutor_enrolled_user_to_masterstudy_enrolled_user( $course_id, $email, $enrolled_user ) {
		global $wpdb;

		$course_id = (int) $course_id;
		$copy_id   = PostType::COURSE === get_post_type( $course_id ) ? $course_id : Target::copy_of( self::SOURCE, $course_id );

		$user_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->users} WHERE user_email = %s",
				$email
			)
		);

		if ( ! $user_row || empty( $user_row->ID ) ) {
			Helper::log( 'warning', sprintf( 'Enrollment skipped: user with email "%s" not found.', $email ) );
			Report::add(
				Report::GROUP_ENROLLMENTS,
				array(
					'source_id' => $enrolled_user->ID,
					'title'     => sprintf( '%1$s → %2$s', static::user_label( (int) $enrolled_user->post_author ), get_post_field( 'post_title', $course_id ) ),
					'type'      => __( 'Enrollment', 'masterstudy-lms-learning-management-system' ),
					/* translators: %s: user email */
					'reason'    => sprintf( __( 'No user with the email "%s" was found, so the enrollment could not be created.', 'masterstudy-lms-learning-management-system' ), $email ),
					'status'    => Report::STATUS_FAILED,
					'course'    => get_post_field( 'post_title', $course_id ),
					'post_id'   => static::report_post_id( $course_id ),
				)
			);
			return false;
		}

		$user_id = (int) $user_row->ID;

		// The Tutor student flag and roles are kept (Tutor LMS keeps working); only make sure the user can log in.
		Target::ensure_student( $user_id );

		if ( 'completed' !== $enrolled_user->post_status ) {
			Helper::log(
				'info',
				sprintf( 'Enrollment %d skipped: Tutor status "%s" (user %d, course %d) has no active MasterStudy equivalent.', $enrolled_user->ID, $enrolled_user->post_status, $user_id, $course_id )
			);
			Report::add(
				Report::GROUP_ENROLLMENTS,
				array(
					'source_id' => $enrolled_user->ID,
					'title'     => sprintf( '%1$s → %2$s', static::user_label( $user_id ), get_post_field( 'post_title', $course_id ) ),
					'type'      => static::enrollment_status_label( (string) $enrolled_user->post_status ),
					/* translators: %s: Tutor LMS enrollment status */
					'reason'    => sprintf( __( 'MasterStudy has no inactive enrollment state — only completed Tutor LMS enrollments are imported (this one is "%s").', 'masterstudy-lms-learning-management-system' ), $enrolled_user->post_status ),
					'status'    => Report::STATUS_UNSUPPORTED,
					'course'    => get_post_field( 'post_title', $course_id ),
					'post_id'   => static::report_post_id( $course_id ),
				)
			);
			return false;
		}

		if ( ! $copy_id || PostType::COURSE !== get_post_type( $copy_id ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \Exception( sprintf( 'Enrollment #%1$d of %2$s: course "%3$s" (#%4$d) has not been migrated yet — run the Courses step first.', $enrolled_user->ID, static::user_label( $user_id ), get_post_field( 'post_title', $course_id ), $course_id ) );
		}

		$source_course = Target::source_of( $copy_id );
		$source_course = $source_course ? $source_course : $course_id;

		// Tutor records course completion as a 'course_completed' comment; its content is the certificate hash.
		$completion = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT comment_date_gmt, comment_content FROM {$wpdb->comments}
				 WHERE comment_type = 'course_completed' AND comment_post_ID = %d AND user_id = %d
				 ORDER BY comment_ID DESC LIMIT 1",
				$source_course,
				$user_id
			)
		);
		$completed_gmt = $completion ? (string) $completion->comment_date_gmt : '';

		$start = ! empty( $enrolled_user->post_date_gmt ) && '0000-00-00 00:00:00' !== $enrolled_user->post_date_gmt
			? (int) strtotime( $enrolled_user->post_date_gmt . ' UTC' )
			: static::local_datetime_to_unix( $enrolled_user->post_date );
		$end   = $completed_gmt ? (int) strtotime( $completed_gmt . ' UTC' ) : null;

		$user_course_id = Target::enroll( $user_id, $copy_id, $start, $end );

		static::check_enrollment_access( $enrolled_user, $user_id, $source_course, $copy_id, $start, $user_course_id );

		if ( ProTarget::pro_active() ) {
			// Keep the Tutor certificate verification code working in the MasterStudy certificate checker.
			if ( $completion && '' !== trim( (string) $completion->comment_content ) ) {
				ProTarget::set_certificate_code( $user_id, $copy_id, trim( (string) $completion->comment_content ) );
			}

			// Enrollment bought through a Tutor bundle → link it to the migrated MasterStudy bundle.
			$tutor_bundle_id = (int) get_post_meta( $enrolled_user->ID, '_tutor_bundle_id', true );
			$bundle_id       = $tutor_bundle_id ? Target::find_migrated_post( PostType::COURSE_BUNDLES, self::SOURCE, 'bundle-' . $tutor_bundle_id ) : 0;

			if ( $bundle_id ) {
				$wpdb->update( $wpdb->prefix . 'stm_lms_user_courses', array( 'bundle_id' => $bundle_id ), array( 'user_course_id' => $user_course_id ) );
			}
		}

		return $user_course_id;
	}

	/**
	 * Access details of an imported enrollment that MasterStudy handles differently:
	 * - an enrollment whose MasterStudy access already expired must not trigger the "access ended" e-mail;
	 * - a per-student expiry date (Tutor Pro) and subscription-granted access cannot be carried over.
	 *
	 * @param \WP_Post $enrolled_post  tutor_enrolled post.
	 * @param int      $user_id        Student ID.
	 * @param int      $source_course  Tutor course ID.
	 * @param int      $course_id      MasterStudy course ID (the copy).
	 * @param int      $start          Enrollment Unix time.
	 * @param int      $user_course_id MasterStudy enrollment row ID.
	 */
	private static function check_enrollment_access( \WP_Post $enrolled_post, int $user_id, int $source_course, int $course_id, int $start, int $user_course_id ): void {
		global $wpdb;

		$days = 'on' === get_post_meta( $course_id, 'expiration_course', true ) ? (int) get_post_meta( $course_id, 'end_time', true ) : 0;

		if ( $days > 0 && $start + $days * DAY_IN_SECONDS < time() ) {
			update_user_meta( $user_id, '_stm_lms_course_expiration_email_sent_' . $course_id, true );
		}

		$notes         = array();
		$custom_expiry = (int) get_user_meta( $user_id, 'tutor_course_enrollment_expiry_date_' . $source_course, true );

		if ( $custom_expiry > 0 ) {
			/* translators: %s: date */
			$notes[] = sprintf( __( 'the student\'s individual access expiry date (%s) — MasterStudy applies the course access duration to every student', 'masterstudy-lms-learning-management-system' ), wp_date( 'Y-m-d H:i', static::tutor_time_to_unix( $custom_expiry ) ) );
		}

		$subscription_id = (int) get_post_meta( $enrolled_post->ID, '_tutor_subscription_id', true );

		if ( $subscription_id ) {
			$ms_plan_id = static::migrated_plan_for_subscription( $subscription_id );

			if ( $ms_plan_id ) {
				// Link the enrollment to the imported plan, as MasterStudy does for plan enrollments.
				$wpdb->update( $wpdb->prefix . 'stm_lms_user_courses', array( 'subscription_id' => $ms_plan_id ), array( 'user_course_id' => $user_course_id ) );
				$notes[] = __( 'the student\'s Tutor LMS subscription (payment gateway subscriptions cannot be migrated) — the enrollment is linked to the imported plan and the student must subscribe again', 'masterstudy-lms-learning-management-system' );
			} else {
				$notes[] = __( 'the access came from a Tutor LMS subscription, which could not be imported (requires MasterStudy LMS Pro Plus) — the enrollment was imported as a regular enrollment', 'masterstudy-lms-learning-management-system' );
			}
		}

		if ( empty( $notes ) ) {
			return;
		}

		Report::add(
			Report::GROUP_ENROLLMENTS,
			array(
				'source_id' => $enrolled_post->ID,
				'title'     => sprintf( '%1$s → %2$s', static::user_label( $user_id ), get_post_field( 'post_title', $course_id ) ),
				'type'      => __( 'Enrollment', 'masterstudy-lms-learning-management-system' ),
				/* translators: %s: list of differences */
				'reason'    => sprintf( __( 'The enrollment was imported, but not: %s.', 'masterstudy-lms-learning-management-system' ), implode( '; ', $notes ) ),
				'status'    => Report::STATUS_PARTIAL,
				'course'    => get_post_field( 'post_title', $course_id ),
				'post_id'   => $course_id,
			)
		);
	}

	/**
	 * MasterStudy subscription plan imported from the plan of a Tutor subscription (0 when none).
	 *
	 * @param int $subscription_id tutor_subscriptions.id.
	 */
	private static function migrated_plan_for_subscription( int $subscription_id ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'tutor_subscriptions';

		if ( ! ProTarget::plus_active() || ! static::table_exists( $table ) ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$plan_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT plan_id FROM {$table} WHERE id = %d", $subscription_id ) );

		// Same key as ProTarget::create_subscription_plan() (source_id "plan-{id}").
		return $plan_id ? (int) get_option( 'masterstudy_lms_migrated_plan_' . md5( self::SOURCE . '|plan-' . $plan_id ) ) : 0;
	}

	/**
	 * Write all MasterStudy course meta of a course copy from the TutorLMS course post meta.
	 *
	 * @param int $course_id Tutor course post ID (read only).
	 * @param int $copy_id   MasterStudy course copy (0 = the copy of $course_id).
	 * @return void
	 */
	public static function update_masterstudy_course_from_tutor( $course_id, $copy_id = 0 ) {
		$course_id = (int) $course_id;
		$copy_id   = $copy_id ? (int) $copy_id : Target::copy_of( self::SOURCE, $course_id );

		if ( ! function_exists( 'tutor_utils' ) || ! $copy_id ) {
			return; // TutorLMS inactive (or no copy) — course copied but pricing/settings skipped.
		}

		// Source features dropped for this course — reported once as a partial import.
		$dropped = array();

		// --- Pricing ---
		$regular_price = '';
		$sale_price    = '';
		$product_id    = (int) \tutor_utils()->get_course_product_id( $course_id );
		$monetize_by   = \tutor_utils()->get_option( 'monetize_by' );
		$wc_product    = null;

		if ( 'wc' === $monetize_by && function_exists( 'wc_get_product' ) ) {
			$wc_product = $product_id ? \wc_get_product( $product_id ) : null;
			if ( $wc_product ) {
				$regular_price = \wc_get_price_to_display( $wc_product, array( 'price' => $wc_product->get_regular_price() ) );
				$sale_price    = '' !== (string) $wc_product->get_sale_price()
					? \wc_get_price_to_display( $wc_product, array( 'price' => $wc_product->get_sale_price() ) )
					: '';

				// Link the Tutor product to the course copy (MasterStudy's product → course lookup key). Added only
				// when the product has no MasterStudy course yet: existing product meta is never changed.
				update_post_meta( $copy_id, '_wc_product_id', $product_id );
				add_post_meta( $product_id, 'stm_lms_product_id', $copy_id, true );

				if ( $wc_product->is_type( 'subscription' ) || $wc_product->is_type( 'variable-subscription' ) ) {
					Target::store_unmigrated_meta( $copy_id, 'tutor_wc_subscription_product', $product_id );
					$dropped[] = __( 'WooCommerce subscription pricing', 'masterstudy-lms-learning-management-system' );
				}
			}
		} elseif ( 'edd' === $monetize_by && \tutor_utils()->has_edd() ) {
			if ( function_exists( 'edd_has_variable_prices' ) && \edd_has_variable_prices( $product_id ) ) {
				$prices        = \edd_get_variable_prices( $product_id );
				$amounts       = wp_list_pluck( $prices, 'amount' );
				$regular_price = $amounts ? (string) max( $amounts ) : '';
				$sale_price    = $amounts ? (string) min( $amounts ) : '';
			} elseif ( function_exists( 'edd_get_download_price' ) ) {
				$regular_price = (string) \edd_get_download_price( $product_id );
			}
		} else {
			// Tutor native checkout — prices stored without underscore prefix.
			$regular_price = (string) get_post_meta( $course_id, 'tutor_course_price', true );
			$sale_price    = (string) get_post_meta( $course_id, 'tutor_course_sale_price', true );
		}

		// --- Price type ---
		$public_course = get_post_meta( $course_id, '_tutor_is_public_course', true ) === 'yes';
		$purchasable   = \tutor_utils()->is_course_purchasable( $course_id );

		if ( ! $public_course && $purchasable ) {
			Target::set_pricing(
				$copy_id,
				'' !== (string) $regular_price ? (float) $regular_price : 0.0,
				'' !== (string) $sale_price ? (float) $sale_price : null
			);
		} else {
			Target::set_pricing( $copy_id, 0.0 );
		}

		// Tutor Pro subscriptions: a paid course may be sold through subscription / membership plans only.
		$selling_option = ! $public_course && $purchasable && 'tutor' === $monetize_by ? static::selling_option( $course_id ) : '';

		if ( '' !== $selling_option ) {
			$note = static::set_selling_option( $copy_id, $selling_option, (float) $regular_price );

			if ( '' !== $note ) {
				$dropped[] = $note;
			}
		}

		// Public (guest-access) courses have no MasterStudy equivalent — they become free courses.
		if ( $public_course ) {
			Target::store_unmigrated_meta( $copy_id, 'tutor_is_public_course', 'yes' );
			$dropped[] = __( 'public (guest) access — imported as a free course', 'masterstudy-lms-learning-management-system' );
		}

		// --- Course settings ---
		$course_settings = maybe_unserialize( get_post_meta( $course_id, '_tutor_course_settings', true ) );
		$course_settings = is_array( $course_settings ) ? $course_settings : array();

		// Enrollment expiry (days after enrollment) — Tutor enforces it only while the global setting is on.
		$expiry_days = (int) ( $course_settings['enrollment_expiry'] ?? 0 );
		if ( $expiry_days > 0 ) {
			if ( (bool) \tutor_utils()->get_option( 'enrollment_expiry_enabled', false ) ) {
				Target::set_course_info( $copy_id, array( 'end_time' => $expiry_days ) );
			} else {
				Target::store_unmigrated_meta( $copy_id, 'tutor_enrollment_expiry_days', $expiry_days );
			}
		}

		// Enrollment period / paused enrollment have no MasterStudy equivalent.
		if ( 'yes' === ( $course_settings['pause_enrollment'] ?? '' ) || 'yes' === ( $course_settings['course_enrollment_period'] ?? '' ) ) {
			Target::store_unmigrated_meta(
				$copy_id,
				'tutor_enrollment_period',
				array_intersect_key( $course_settings, array_flip( array( 'pause_enrollment', 'course_enrollment_period', 'enrollment_starts_at', 'enrollment_ends_at' ) ) )
			);
			$dropped[] = __( 'enrollment period / paused enrollment', 'masterstudy-lms-learning-management-system' );
		}

		// Tutor Pro "coming soon" (upcoming) course.
		$coming_soon_note = static::migrate_coming_soon( $course_id, $copy_id );
		if ( '' !== $coming_soon_note ) {
			$dropped[] = $coming_soon_note;
		}

		$max_students = (int) ( $course_settings['maximum_students'] ?? 0 );
		if ( $max_students > 0 ) {
			Target::store_unmigrated_meta( $copy_id, 'tutor_maximum_students', $max_students );
			$dropped[] = __( 'maximum students limit', 'masterstudy-lms-learning-management-system' );
		}

		if ( ! empty( $course_settings['enable_course_retake'] ) ) {
			Target::store_unmigrated_meta( $copy_id, 'tutor_enable_course_retake', $course_settings['enable_course_retake'] );
			$dropped[] = __( 'course retake setting', 'masterstudy-lms-learning-management-system' );
		}

		$duration_raw  = maybe_unserialize( get_post_meta( $course_id, '_course_duration', true ) );
		$duration_info = '';
		if ( is_array( $duration_raw ) && ( isset( $duration_raw['hours'] ) || isset( $duration_raw['minutes'] ) ) ) {
			$duration_info = static::format_duration( (int) ( $duration_raw['hours'] ?? 0 ), (int) ( $duration_raw['minutes'] ?? 0 ), (int) ( $duration_raw['seconds'] ?? 0 ) );
		} elseif ( is_array( $duration_raw ) ) {
			$duration_info = static::format_duration( 0, static::convert_time_limit_to_minutes( $duration_raw ), 0 );
		}

		// TutorLMS allows HTML in the course benefits field; Target sanitizes on write.
		Target::set_course_info(
			$copy_id,
			array(
				'duration_info'     => $duration_info,
				'basic_info'        => static::lines_to_list( (string) get_post_meta( $course_id, '_tutor_course_benefits', true ) ),
				'requirements'      => static::lines_to_list( (string) get_post_meta( $course_id, '_tutor_course_requirements', true ) ),
				'intended_audience' => static::lines_to_list( (string) get_post_meta( $course_id, '_tutor_course_target_audience', true ) ),
			)
		);

		// Material includes has no MasterStudy field — keep for reference.
		Target::store_unmigrated_meta( $copy_id, 'tutor_course_material_includes', get_post_meta( $course_id, '_tutor_course_material_includes', true ) );

		// --- Level ---
		// 'all_levels' means no specific difficulty in TutorLMS — leave the MasterStudy level empty.
		$course_level = (string) get_post_meta( $course_id, '_tutor_course_level', true );
		if ( '' !== $course_level && ! in_array( $course_level, array( 'all_level', 'all_levels' ), true ) ) {
			Target::set_level( $copy_id, $course_level );
		}

		// --- Taxonomy: categories with their parent chain and images (MasterStudy has no course tag taxonomy) ---
		// The Tutor terms are read from the source course; only MasterStudy terms are written.
		Target::migrate_categories( $copy_id, 'course-category', array(), $course_id );
		Target::migrate_category_hierarchy( $course_id, 'course-category' );
		static::migrate_category_images( $course_id );

		$tags = wp_get_post_terms( $course_id, 'course-tag', array( 'fields' => 'names' ) );
		if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) {
			Target::store_unmigrated_meta( $copy_id, 'tutor_course_tags', $tags );
			$dropped[] = __( 'course tags', 'masterstudy-lms-learning-management-system' );
		}

		// --- Co-instructors (Tutor Pro multi instructors); MasterStudy keeps a single co-instructor ---
		$co_instructors = static::migrate_co_instructor( $course_id, $copy_id );
		if ( $co_instructors > 0 && ! ProTarget::pro_active() ) {
			$dropped[] = __( 'co-instructor (saved, but inactive — requires MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
		}
		if ( $co_instructors > 1 ) {
			$dropped[] = __( 'additional co-instructors (MasterStudy keeps one)', 'masterstudy-lms-learning-management-system' );
		}

		// Q&A enabled flag — MasterStudy discussions are always per lesson; keep the flag for reference.
		$enable_qa = get_post_meta( $course_id, '_tutor_enable_qa', true );
		if ( '' !== $enable_qa ) {
			Target::store_unmigrated_meta( $copy_id, 'tutor_enable_qa', $enable_qa );
		}

		// Certificate: an approximated MasterStudy certificate (source background + standard fields) per Tutor template.
		$tutor_cert_template = (string) get_post_meta( $course_id, 'tutor_course_certificate_template', true );
		if ( '' !== $tutor_cert_template && ! in_array( $tutor_cert_template, array( 'none', 'off' ), true ) ) {
			if ( ProTarget::pro_active() ) {
				$certificate_id = static::migrate_certificate_template( $tutor_cert_template );
				if ( $certificate_id ) {
					ProTarget::set_course_certificate( $copy_id, $certificate_id );
					$dropped[] = __( 'certificate design — approximated (background image with the standard certificate fields); review it in the Certificate Builder', 'masterstudy-lms-learning-management-system' );
				} else {
					Target::assign_certificate( $copy_id );
					$dropped[] = __( 'certificate design — the default MasterStudy certificate is used', 'masterstudy-lms-learning-management-system' );
				}
			} else {
				Helper::log( 'warning', sprintf( 'Tutor LMS: certificate of course %d not migrated — requires MasterStudy LMS Pro.', $course_id ) );
				$dropped[] = __( 'certificate (requires MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
			}
			Target::store_unmigrated_meta( $copy_id, 'tutor_course_certificate_template', $tutor_cert_template );
		}

		// Course intro video — MasterStudy Pro course preview keys.
		if ( ! ProTarget::pro_active() && ! empty( static::map_video_meta( $course_id ) ) ) {
			$dropped[] = __( 'course intro video (requires MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
		}
		static::set_course_preview_video( $course_id, $copy_id );

		// Course attachments (shared attachment posts, referenced by ID).
		$raw_attachments = maybe_unserialize( get_post_meta( $course_id, '_tutor_attachments', true ) );
		if ( ! empty( $raw_attachments ) && is_array( $raw_attachments ) ) {
			static::set_course_files( $copy_id, $raw_attachments );
		}

		// Prerequisites — Tutor requires completing each prerequisite course. They are written by finalize_step()
		// once every course copy exists (a prerequisite may be copied after this course).
		if ( ! ProTarget::pro_active() && ! empty( static::prerequisite_course_ids( $course_id ) ) ) {
			$dropped[] = __( 'prerequisites (saved, but inactive — requires MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
		}

		// Course flow / content drip type — Tutor applies it only while content drip is enabled on the course.
		$tutor_drip_type = (string) ( $course_settings['content_drip_type'] ?? '' );
		$drip_enabled    = ! empty( $course_settings['enable_content_drip'] );

		if ( $drip_enabled && 'unlock_sequentially' === $tutor_drip_type ) {
			if ( ProTarget::pro_active() ) {
				ProTarget::sequential_course( $copy_id );
			} else {
				update_post_meta( $copy_id, 'lock_lesson', 'on' );
				$dropped[] = __( 'sequential content lock (saved, but inactive — requires MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
			}
		}

		// With Pro the item unlock map is written once the curriculum is rebuilt (migrate_drip_prerequisites()).
		if ( $drip_enabled && 'after_finishing_prerequisites' === $tutor_drip_type && ! ProTarget::pro_active() ) {
			Target::store_unmigrated_meta( $copy_id, 'tutor_drip_after_prerequisites', true );
			$dropped[] = __( '"after finishing prerequisites" content drip (requires MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
		}

		if ( $drip_enabled && in_array( $tutor_drip_type, array( 'unlock_by_date', 'specific_days' ), true ) && ! ProTarget::pro_active() ) {
			$dropped[] = __( 'content drip schedule (saved, but inactive — requires MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
		}

		if ( ! empty( $dropped ) ) {
			Report::add(
				Report::GROUP_COURSES,
				array(
					'source_id' => $course_id,
					'title'     => get_post_field( 'post_title', $copy_id ),
					'type'      => __( 'Course settings', 'masterstudy-lms-learning-management-system' ),
					/* translators: %s: comma-separated list of course features */
					'reason'    => sprintf( __( 'The course was imported, but these Tutor LMS settings were not carried over: %s.', 'masterstudy-lms-learning-management-system' ), implode( '; ', $dropped ) ),
					'status'    => Report::STATUS_PARTIAL,
					'course'    => get_post_field( 'post_title', $copy_id ),
					'post_id'   => $copy_id,
				)
			);
		}
	}

	/**
	 * Processes migration for a single Tutor quiz question: creates the MasterStudy question
	 * post (Tutor questions are table rows) and stores its answers in the MasterStudy format.
	 *
	 * Questions MasterStudy cannot use (h5p, open-ended, short-answer, image-answer, …) are not copied at all —
	 * they are reported (linked to the quiz copy) and left out of the quiz.
	 *
	 * @param object $question  Tutor quiz question row.
	 * @param int    $quiz_id   MasterStudy quiz post ID (the copy).
	 * @param int    $course_id MasterStudy course ID (the copy).
	 * @return int Question post ID to add to the quiz, or 0.
	 */
	public static function process_question_migration_from_tutor( $question, $quiz_id, $course_id ) {
		// Tutor 4 variants (single choice, image matching) are flags in question_settings — map the effective type.
		$tutor_type    = static::effective_question_type( $question );
		$question_type = static::map_question_type( $tutor_type );

		if ( is_null( $question_type ) ) {
			Helper::log(
				'warning',
				sprintf( 'Question %d not added to quiz %d: question type "%s" has no MasterStudy equivalent.', $question->question_id, $quiz_id, $question->question_type )
			);
			static::report_question( $question, (int) $quiz_id, (int) $course_id, (int) $quiz_id, static::unsupported_question_reason( (string) $question->question_type ), Report::STATUS_UNSUPPORTED );
			return 0;
		}

		$existing_question_id = static::find_question_post( (int) $question->question_id );

		if ( $existing_question_id && '' !== (string) get_post_meta( $existing_question_id, 'type', true ) ) {
			return $existing_question_id;
		}

		$answers_tutor = static::answer_list_by_question( (int) $question->question_id, $question->question_type );

		$question_id = Target::insert_post(
			array(
				'post_type'      => PostType::QUESTION,
				'post_status'    => 'publish',
				'post_title'     => wp_strip_all_tags( (string) $question->question_title ),
				'post_content'   => wp_kses_post( (string) ( $question->question_description ?? '' ) ),
				'post_author'    => (int) get_post_field( 'post_author', $quiz_id ),
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			),
			self::SOURCE,
			'question-' . (int) $question->question_id
		);

		update_post_meta( $question_id, '_tutor_question_id', (string) $question->question_id );
		Target::store_unmigrated_meta( $question_id, 'tutor_question_mark', (string) $question->question_mark );

		if ( empty( $answers_tutor ) ) {
			static::report_question(
				$question,
				$quiz_id,
				$course_id,
				$question_id,
				__( 'The question has no answers in Tutor LMS — it was imported and added to the quiz, but has nothing to grade against until answers are added.', 'masterstudy-lms-learning-management-system' ),
				Report::STATUS_PARTIAL
			);
		}

		$answers   = array();
		$has_image = false;

		switch ( $tutor_type ) {
			case 'true_false':
				$true_correct = false;
				foreach ( $answers_tutor as $index => $answer_tutor ) {
					// The "True" row — by title, falling back to the first row (Tutor creates True first).
					$is_true_row = 'true' === strtolower( trim( (string) $answer_tutor->answer_title ) ) || ( 0 === $index && ! static::has_true_title( $answers_tutor ) );
					if ( $is_true_row ) {
						$true_correct = (bool) $answer_tutor->is_correct;
					}
				}
				$answers[] = array( 'correct' => $true_correct );
				break;

			case 'single_choice':
			case 'multiple_choice':
				foreach ( $answers_tutor as $answer_tutor ) {
					$image_id  = (int) ( $answer_tutor->image_id ?? 0 );
					$has_image = $has_image || $image_id > 0;
					$answers[] = array(
						'text'     => (string) $answer_tutor->answer_title,
						'correct'  => (bool) $answer_tutor->is_correct,
						'image_id' => $image_id,
					);
				}
				break;

			case 'fill_in_the_blank':
				// Tutor: answer_title holds the sentence with {dash} placeholders, answer_two_gap_match "a|b".
				$answer_tutor = $answers_tutor[0] ?? null;
				if ( $answer_tutor ) {
					$gaps     = array_map( 'trim', explode( '|', (string) $answer_tutor->answer_two_gap_match ) );
					$sentence = (string) $answer_tutor->answer_title;
					foreach ( $gaps as $gap ) {
						$pos = strpos( $sentence, '{dash}' );
						if ( false === $pos ) {
							break;
						}
						$sentence = substr_replace( $sentence, '|' . $gap . '|', $pos, strlen( '{dash}' ) );
					}
					$answers[] = array( 'text' => $sentence );
				}
				break;

			case 'ordering':
				// Rows are already sorted by answer_order ASC = the correct order.
				foreach ( $answers_tutor as $answer_tutor ) {
					$answers[] = array( 'text' => (string) $answer_tutor->answer_title );
				}
				break;

			case 'matching':
				// Left column = prompt (answer_title), right column = match value (answer_two_gap_match).
				foreach ( $answers_tutor as $answer_tutor ) {
					$answers[] = array(
						'prompt' => (string) $answer_tutor->answer_title,
						'match'  => (string) ( $answer_tutor->answer_two_gap_match ?? '' ),
					);
				}
				break;

			case 'image_matching':
				// Legacy `image_matching` and Tutor 4 `matching` + is_image_matching: each drop zone shows the row
				// image (image_id), the draggable label is the row answer_title (answer_two_gap_match is empty).
				foreach ( $answers_tutor as $answer_tutor ) {
					$answers[] = array(
						'prompt'          => '',
						'prompt_image_id' => (int) ( $answer_tutor->image_id ?? 0 ),
						'match'           => (string) $answer_tutor->answer_title,
						'match_image_id'  => 0,
					);
				}
				break;
		}

		Target::set_question(
			$question_id,
			$question_type,
			$answers,
			array(
				'explanation' => (string) ( $question->answer_explanation ?? '' ),
				'view_type'   => $has_image ? 'image' : 'list',
			)
		);

		return $question_id;
	}

	/**
	 * Gets the list of answers for a given question ID and type.
	 *
	 * @param int    $question_id   Tutor quiz question ID.
	 * @param string $question_type Tutor question type slug.
	 * @return array
	 */
	public static function answer_list_by_question( int $question_id, string $question_type ): array {
		global $wpdb;
		$answers = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}tutor_quiz_question_answers
				 WHERE belongs_question_id = %d
				   AND belongs_question_type = %s
				 ORDER BY answer_order ASC, answer_id ASC",
				$question_id,
				$question_type
			)
		);
		return is_array( $answers ) && count( $answers ) ? array_map( array( __CLASS__, 'unslash_answer' ), $answers ) : array();
	}

	/**
	 * Convert WooCommerce order status to a MasterStudy order status.
	 *
	 * @param string $status WooCommerce order status.
	 * @return string MasterStudy order status.
	 */
	public static function convert_wc_status( $status ) {
		$map = array(
			'processing'    => 'processing',
			'pending'       => 'pending',
			'cancelled'     => 'cancelled',
			'on-hold'       => 'on-hold',
			'completed'     => 'completed',
			'refunded'      => 'refunded',
			'failed'        => 'failed',
			'wc-processing' => 'processing',
			'wc-pending'    => 'pending',
			'wc-cancelled'  => 'cancelled',
			'wc-on-hold'    => 'on-hold',
			'wc-completed'  => 'completed',
			'wc-refunded'   => 'refunded',
			'wc-failed'     => 'failed',
		);

		$new_status = $map[ $status ] ?? 'pending';
		return 'processing' === $new_status ? 'pending' : $new_status;
	}

	/**
	 * Convert a TutorLMS time_limit array to minutes.
	 *
	 * @param mixed $time_limit TutorLMS time_limit option array.
	 * @return int Duration in minutes.
	 */
	public static function convert_time_limit_to_minutes( $time_limit ) {
		$time_value = isset( $time_limit['time_value'] ) ? (int) $time_limit['time_value'] : 0;
		$time_type  = isset( $time_limit['time_type'] ) ? $time_limit['time_type'] : '';

		switch ( $time_type ) {
			case 'hours':
				return $time_value * 60;
			case 'days':
				return $time_value * 24 * 60;
			case 'time_limit_seconds':
			case 'seconds':
				return (int) round( $time_value / 60 );
			case 'minutes':
			default:
				return $time_value;
		}
	}

	/**
	 * Extract video metadata from a TutorLMS post's _video meta.
	 *
	 * Tutor stores the source as 'youtube', 'vimeo', 'embedded', 'external_url', 'html5' or
	 * 'shortcode' (Masteriyo's 'source_*' spellings are accepted too).
	 *
	 * @param int $post_id TutorLMS post ID (course or lesson).
	 * @return array{ms_source: string, value: string|int, poster: int, duration: string}|array Empty when no video.
	 */
	public static function map_video_meta( int $post_id ): array {
		$video = maybe_unserialize( get_post_meta( $post_id, '_video', true ) );
		if ( empty( $video ) || ! is_array( $video ) ) {
			return array();
		}

		$raw_source = (string) ( $video['source'] ?? '' );
		$raw_source = 0 === strpos( $raw_source, 'source_' ) ? substr( $raw_source, 7 ) : $raw_source;

		$runtime  = is_array( $video['runtime'] ?? null ) ? $video['runtime'] : array();
		$duration = static::format_duration( (int) ( $runtime['hours'] ?? 0 ), (int) ( $runtime['minutes'] ?? 0 ), (int) ( $runtime['seconds'] ?? 0 ) );

		$poster = $video['poster'] ?? 0;
		$poster = is_numeric( $poster ) ? (int) $poster : (int) attachment_url_to_postid( (string) $poster );

		switch ( $raw_source ) {
			case 'youtube':
				$ms_source = 'youtube';
				$value     = (string) ( $video['source_youtube'] ?? '' );
				break;
			case 'vimeo':
				$ms_source = 'vimeo';
				$value     = (string) ( $video['source_vimeo'] ?? '' );
				break;
			case 'embedded':
				$ms_source = 'embed';
				$value     = (string) ( $video['source_embedded'] ?? '' );
				break;
			case 'external_url':
				$ms_source = 'external';
				$value     = (string) ( $video['source_external_url'] ?? '' );
				break;
			case 'html5':
				$ms_source = 'html';
				$value     = (int) ( $video['source_video_id'] ?? 0 );
				break;
			case 'shortcode':
				$ms_source = 'shortcode';
				$value     = (string) ( $video['source_shortcode'] ?? '' );
				break;
			default:
				return array();
		}

		if ( empty( $value ) ) {
			return array();
		}

		return array(
			'ms_source' => $ms_source,
			'value'     => $value,
			'poster'    => $poster,
			'duration'  => $duration,
		);
	}

	/**
	 * Map a TutorLMS question type slug to the MasterStudy equivalent.
	 *
	 * Returns null for types that have no MasterStudy equivalent.
	 *
	 * @param string $tutor_type TutorLMS question_type value.
	 * @return string|null MasterStudy question type, or null if unsupported.
	 */
	public static function map_question_type( string $tutor_type ): ?string {
		$map = array(
			'true_false'        => 'true_false',
			'single_choice'     => 'single_choice',
			'multiple_choice'   => 'multi_choice',
			'fill_in_the_blank' => 'fill_the_gap',
			'ordering'          => 'sortable',
			'matching'          => 'item_match',
			'image_matching'    => 'image_match',
		);
		return $map[ $tutor_type ] ?? null;
	}

	/**
	 * Effective Tutor question type of a question row.
	 *
	 * Tutor ≥ 3 stores single choice as `multiple_choice` with question_settings
	 * `has_multiple_correct_answer = '0'` (radio buttons), and Tutor 4 image matching as `matching`
	 * with `is_image_matching = '1'`. Legacy `single_choice` / `image_matching` rows keep their type.
	 * A `multiple_choice` row without the flag is pre-v3 data, which Tutor renders as checkboxes.
	 *
	 * @param object $question Tutor quiz question row (question_type, question_settings).
	 * @return string 'single_choice', 'image_matching' or the stored question_type.
	 */
	public static function effective_question_type( $question ): string {
		$type     = (string) ( $question->question_type ?? '' );
		$settings = maybe_unserialize( $question->question_settings ?? '' );
		$settings = is_array( $settings ) ? $settings : array();

		if ( 'multiple_choice' === $type && isset( $settings['has_multiple_correct_answer'] ) && '1' !== (string) $settings['has_multiple_correct_answer'] ) {
			return 'single_choice';
		}

		if ( 'matching' === $type && '1' === (string) ( $settings['is_image_matching'] ?? '' ) ) {
			return 'image_matching';
		}

		return $type;
	}

	/**
	 * Convert a TutorLMS quiz time_limit option array to seconds.
	 *
	 * @param array $time_limit TutorLMS time_limit option with time_value and time_type keys.
	 * @return int Duration in seconds. Returns 0 if no limit set.
	 */
	public static function quiz_duration_to_seconds( array $time_limit ): int {
		$value    = (int) ( $time_limit['time_value'] ?? 0 );
		$type     = $time_limit['time_type'] ?? 'minutes';
		$unit_map = array(
			'seconds' => 1,
			'minutes' => MINUTE_IN_SECONDS,
			'hours'   => HOUR_IN_SECONDS,
			'days'    => DAY_IN_SECONDS,
			'weeks'   => WEEK_IN_SECONDS,
		);
		return $value * ( $unit_map[ $type ] ?? MINUTE_IN_SECONDS );
	}

	/**
	 * Copy TutorLMS profile metadata to the MasterStudy profile fields (user meta).
	 *
	 * Users are shared by both plugins and existing user data is never changed: a MasterStudy field is only added
	 * when the user has no value for it yet. The bio (WordPress "Biographical Info", created empty for every user)
	 * and the website (WordPress user URL) therefore stay as they are — a Tutor bio / website that is not the same
	 * is reported.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return void
	 */
	public static function migrate_instructor_profile( int $user_id ): void {
		$user     = get_userdata( $user_id );
		$bio      = (string) get_user_meta( $user_id, '_tutor_profile_bio', true );
		$photo_id = (int) get_user_meta( $user_id, '_tutor_profile_photo', true );
		$website  = (string) get_user_meta( $user_id, '_tutor_profile_website', true );

		if ( ! $user ) {
			return;
		}

		$profile = array(
			'description' => $bio,
			'position'    => sanitize_text_field( (string) get_user_meta( $user_id, '_tutor_profile_job_title', true ) ),
			'avatar_url'  => $photo_id ? (string) wp_get_attachment_url( $photo_id ) : '',
		);

		foreach ( array( 'facebook', 'twitter', 'linkedin', 'instagram' ) as $network ) {
			$handle = get_user_meta( $user_id, "_tutor_profile_{$network}", true );
			if ( $handle ) {
				$profile[ $network ] = sanitize_text_field( $handle );
			}
		}

		// Target::set_profile() field => user meta key; fields the user already has (even empty) are left alone.
		$keys = array(
			'description' => 'description',
			'position'    => 'position',
			'facebook'    => 'facebook',
			'twitter'     => 'twitter',
			'instagram'   => 'instagram',
			'linkedin'    => 'linkedin',
			'avatar_url'  => 'stm_lms_user_avatar',
		);
		$kept = array();

		foreach ( $keys as $field => $meta_key ) {
			if ( '' !== (string) ( $profile[ $field ] ?? '' ) && metadata_exists( 'user', $user_id, $meta_key ) ) {
				if ( 'description' === $field && trim( wp_strip_all_tags( $bio ) ) !== trim( wp_strip_all_tags( (string) get_user_meta( $user_id, 'description', true ) ) ) ) {
					$kept[] = __( 'bio', 'masterstudy-lms-learning-management-system' );
				}
				unset( $profile[ $field ] );
			}
		}

		Target::set_profile( $user_id, $profile );

		if ( '' !== trim( $website ) && untrailingslashit( esc_url_raw( $website ) ) !== untrailingslashit( (string) $user->user_url ) ) {
			$kept[] = __( 'website', 'masterstudy-lms-learning-management-system' );
		}

		// Profile cover image (attachment ID in both plugins); an existing MasterStudy cover is kept.
		$cover_id = (int) get_user_meta( $user_id, '_tutor_cover_photo', true );
		if ( $cover_id && 'attachment' === get_post_type( $cover_id ) ) {
			Target::track_user_meta( $user_id, 'stm_lms_user_cover' );
			add_user_meta( $user_id, 'stm_lms_user_cover', $cover_id, true );
		}

		// No MasterStudy profile field — kept for reference.
		$github = get_user_meta( $user_id, '_tutor_profile_github', true );
		if ( $github ) {
			add_user_meta( $user_id, '_migrated_tutor_social_github', sanitize_text_field( $github ), true );
		}

		if ( ! empty( $kept ) ) {
			Report::add(
				Report::GROUP_USERS,
				array(
					'source_id' => $user_id,
					'title'     => static::user_label( $user_id ),
					'type'      => __( 'Instructor profile', 'masterstudy-lms-learning-management-system' ),
					/* translators: %s: list of profile fields */
					'reason'    => sprintf( __( 'The Tutor LMS profile %s was not copied: MasterStudy shows the WordPress profile fields, and existing user data is never changed by the migration. Copy it in the user profile if needed.', 'masterstudy-lms-learning-management-system' ), implode( ', ', $kept ) ),
					'status'    => Report::STATUS_PARTIAL,
				)
			);
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Private helpers
	|--------------------------------------------------------------------------
	*/

	/**
	 * IDs of the MasterStudy course copies made from Tutor LMS courses (never courses converted in place by an
	 * earlier migration version).
	 *
	 * @return int[]
	 */
	private static function migrated_course_ids(): array {
		global $wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s AND pm.meta_value = %s
				 INNER JOIN {$wpdb->postmeta} i ON i.post_id = p.ID AND i.meta_key = %s AND i.meta_value LIKE %s
				 WHERE p.post_type = %s",
				Target::SOURCE_META,
				self::SOURCE,
				Target::SOURCE_ID_META,
				$wpdb->esc_like( Target::COPY_KEY_PREFIX ) . '%',
				PostType::COURSE
			)
		);

		$ids = array_map( 'intval', (array) $ids );

		// Recalculating "all" when nothing matches would touch unrelated enrollments — pass an impossible ID instead.
		return empty( $ids ) ? array( 0 ) : $ids;
	}

	/**
	 * Course IDs of the MasterStudy assignment submissions imported from Tutor LMS.
	 *
	 * @return int[]
	 */
	private static function submission_course_ids(): array {
		global $wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT c.meta_value
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = %s AND s.meta_value = %s
				 INNER JOIN {$wpdb->postmeta} c ON c.post_id = p.ID AND c.meta_key = 'course_id'
				 WHERE p.post_type = %s",
				Target::SOURCE_META,
				self::SOURCE,
				PostType::USER_ASSIGNMENT
			)
		);

		return array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
	}

	/**
	 * Tutor course that sells an EDD download (`_tutor_course_product_id`), 0 when none.
	 *
	 * @param int $download_id EDD download post ID.
	 */
	private static function course_for_download( int $download_id ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_tutor_course_product_id' AND pm.meta_value = %s
				 WHERE p.post_type = 'courses'
				 ORDER BY p.ID ASC LIMIT 1",
				(string) $download_id
			)
		);
	}

	/**
	 * Tutor prerequisite courses of a Tutor course (`_tutor_course_prerequisites_ids`), itself excluded.
	 *
	 * @param int $course_id Tutor course post ID.
	 * @return int[] Tutor course IDs.
	 */
	private static function prerequisite_course_ids( int $course_id ): array {
		$raw = maybe_unserialize( get_post_meta( $course_id, '_tutor_course_prerequisites_ids', true ) );

		if ( empty( $raw ) || ! is_array( $raw ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map( 'intval', $raw ),
				function ( $id ) use ( $course_id ) {
					return $id && $course_id !== $id && 'courses' === get_post_type( $id );
				}
			)
		);
	}

	/**
	 * Course prerequisites of every course copy (finalize of the courses step: all copies exist by then).
	 * Tutor requires completing each prerequisite course; without MasterStudy Pro they are saved but inactive.
	 */
	private static function migrate_prerequisites(): void {
		foreach ( static::migrated_course_ids() as $copy_id ) {
			$source_id = $copy_id ? Target::source_of( $copy_id ) : 0;
			$required  = $source_id ? Target::copies_of( self::SOURCE, static::prerequisite_course_ids( $source_id ) ) : array();

			if ( empty( $required ) ) {
				continue;
			}

			if ( ProTarget::pro_active() ) {
				ProTarget::set_prerequisites( $copy_id, $required, 100 );
			} else {
				update_post_meta( $copy_id, 'prerequisites', implode( ',', $required ) );
			}
		}
	}

	/**
	 * Question post previously created for a Tutor question row.
	 */
	private static function find_question_post( int $tutor_question_id ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT pm.post_id FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = %s
				 WHERE pm.meta_key = '_tutor_question_id' AND pm.meta_value = %s
				 LIMIT 1",
				PostType::QUESTION,
				(string) $tutor_question_id
			)
		);
	}

	/**
	 * Strip the slashes Tutor stores in answer text columns.
	 *
	 * @param object $answer Answer row.
	 * @return object
	 */
	private static function unslash_answer( $answer ) {
		foreach ( array( 'answer_title', 'answer_two_gap_match' ) as $field ) {
			if ( isset( $answer->$field ) ) {
				$answer->$field = stripslashes( (string) $answer->$field );
			}
		}

		return $answer;
	}

	/**
	 * Whether a true/false answer list has a row literally titled "True".
	 *
	 * @param object[] $answers Answer rows.
	 */
	private static function has_true_title( array $answers ): bool {
		foreach ( $answers as $answer ) {
			if ( 'true' === strtolower( trim( (string) $answer->answer_title ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build the MasterStudy `user_answer` string (spec §8) from a Tutor given_answer.
	 *
	 * @param string $ms_type           MasterStudy question type.
	 * @param int    $tutor_question_id Tutor question ID.
	 * @param string $tutor_type        Tutor question type.
	 * @param string $given_answer      Tutor given_answer column value.
	 */
	private static function build_user_answer( string $ms_type, int $tutor_question_id, string $tutor_type, string $given_answer ): string {
		static $cache = array();

		$key = $tutor_question_id . ':' . $tutor_type;
		if ( ! isset( $cache[ $key ] ) ) {
			$rows = array();
			foreach ( static::answer_list_by_question( $tutor_question_id, $tutor_type ) as $row ) {
				$rows[ (int) $row->answer_id ] = $row;
			}
			$cache[ $key ] = $rows;
		}
		$rows = $cache[ $key ];

		$given = maybe_unserialize( $given_answer );

		$choice = function ( $row ) {
			$text     = (string) $row->answer_title;
			$image_id = (int) ( $row->image_id ?? 0 );
			$url      = $image_id ? (string) wp_get_attachment_url( $image_id ) : '';

			return '' !== $url ? $text . '|' . esc_url( $url ) : $text;
		};

		switch ( $ms_type ) {
			case 'true_false':
				$row = $rows[ (int) $given ] ?? null;
				if ( ! $row ) {
					return '';
				}
				if ( static::has_true_title( array_values( $rows ) ) ) {
					return 'true' === strtolower( trim( (string) $row->answer_title ) ) ? 'True' : 'False';
				}
				// Localized titles: the first row is "True".
				return array_key_first( $rows ) === (int) $row->answer_id ? 'True' : 'False';

			case 'single_choice':
				// Radio answers are stored as one answer ID; take the first ID if an array slipped through.
				$given = is_array( $given ) ? reset( $given ) : $given;
				$row   = $rows[ (int) $given ] ?? null;
				return $row ? $choice( $row ) : '';

			case 'multi_choice':
				$values = array();
				foreach ( (array) $given as $answer_id ) {
					if ( isset( $rows[ (int) $answer_id ] ) ) {
						$values[] = rawurlencode( $choice( $rows[ (int) $answer_id ] ) );
					}
				}
				return implode( ',', $values );

			case 'fill_the_gap':
				return implode( ',', array_map( 'strval', (array) $given ) );

			case 'sortable':
				$values = array();
				foreach ( (array) $given as $answer_id ) {
					if ( isset( $rows[ (int) $answer_id ] ) ) {
						$values[] = (string) $rows[ (int) $answer_id ]->answer_title;
					}
				}
				return '[stm_lms_sortable]' . implode( '[stm_lms_sep]', $values );

			case 'item_match':
				// Dropped items (in prompt order) show their answer_two_gap_match text.
				$values = array();
				foreach ( (array) $given as $answer_id ) {
					$values[] = isset( $rows[ (int) $answer_id ] ) ? (string) $rows[ (int) $answer_id ]->answer_two_gap_match : '';
				}
				return '[stm_lms_item_match]' . implode( '[stm_lms_sep]', $values );

			case 'image_match':
				// Legacy image_matching and Tutor 4 matching + is_image_matching: given_answer lists the dropped
				// answer IDs in drop-zone (answer_order) order; the dropped label is that row's answer_title.
				$values = array();
				foreach ( (array) $given as $answer_id ) {
					$values[] = isset( $rows[ (int) $answer_id ] ) ? (string) $rows[ (int) $answer_id ]->answer_title : '';
				}
				return '[stm_lms_image_match]' . implode( '[stm_lms_sep]', $values );
		}

		return is_scalar( $given ) ? (string) $given : '';
	}

	/**
	 * Per-item content drip (Tutor `_content_drip_settings`) → MasterStudy Pro drip meta of the item copy.
	 * Without Pro the raw meta is still written (inactive until Pro is installed).
	 *
	 * @param int $item_id   Tutor lesson/quiz/assignment post ID (read only).
	 * @param int $course_id Tutor course post ID (read only).
	 * @param int $copy_id   MasterStudy copy of the item (0 = the copy of $item_id).
	 */
	private static function migrate_item_drip( int $item_id, int $course_id, int $copy_id = 0 ): void {
		$copy_id         = $copy_id ? $copy_id : Target::copy_of( self::SOURCE, $item_id );
		$course_settings = maybe_unserialize( get_post_meta( $course_id, '_tutor_course_settings', true ) );
		$drip_type       = is_array( $course_settings ) ? (string) ( $course_settings['content_drip_type'] ?? '' ) : '';

		if ( ! $copy_id || ! in_array( $drip_type, array( 'unlock_by_date', 'specific_days' ), true ) ) {
			return;
		}

		// Tutor applies the drip rules only while content drip is enabled on the course.
		if ( ! is_array( $course_settings ) || empty( $course_settings['enable_content_drip'] ) ) {
			return;
		}

		$drip = maybe_unserialize( get_post_meta( $item_id, '_content_drip_settings', true ) );
		if ( ! is_array( $drip ) ) {
			return;
		}

		if ( ProTarget::pro_active() ) {
			// Tutor compares the unlock date (site-local wall clock) with current_time().
			$unlock = 'unlock_by_date' === $drip_type && ! empty( $drip['unlock_date'] ) ? static::local_datetime_to_unix( (string) $drip['unlock_date'] ) : 0;

			if ( $unlock ) {
				ProTarget::drip_on_date( $copy_id, $unlock );
			}

			if ( 'specific_days' === $drip_type ) {
				ProTarget::drip_after_days( $copy_id, (int) ( $drip['after_xdays_of_enroll'] ?? 0 ) );
			}

			return;
		}

		if ( 'unlock_by_date' === $drip_type && ! empty( $drip['unlock_date'] ) ) {
			$ts = strtotime( (string) $drip['unlock_date'] );
			if ( $ts ) {
				update_post_meta( $copy_id, 'lesson_start_date', strtotime( gmdate( 'Y-m-d', $ts ) ) * 1000 );
				update_post_meta( $copy_id, 'lesson_start_time', gmdate( 'H:i', $ts ) );
			}
		}

		if ( 'specific_days' === $drip_type && ! empty( $drip['after_xdays_of_enroll'] ) ) {
			update_post_meta( $copy_id, 'lesson_lock_from_start', '1' );
			update_post_meta( $copy_id, 'lesson_lock_start_days', (int) $drip['after_xdays_of_enroll'] );
		}
	}

	/**
	 * Copy a tutor-google-meet post into a new stm-google-meets post and map its meta (MasterStudy Pro Google
	 * Meet schema). The Tutor meeting and its meta are never modified. Idempotent (one copy per meeting).
	 *
	 * @param int $post_id tutor-google-meet post ID.
	 * @return int Google Meet copy post ID.
	 */
	private static function convert_google_meet( int $post_id ): int {
		$post = get_post( $post_id );

		// Real Tutor Pro keys first; Masteriyo's documented keys as fallback.
		$meet_url = (string) get_post_meta( $post_id, 'tutor-google-meet-link', true );
		$starts   = (string) get_post_meta( $post_id, 'tutor-google-meet-start-datetime', true );
		$ends     = (string) get_post_meta( $post_id, 'tutor-google-meet-end-datetime', true );
		$details  = (string) get_post_meta( $post_id, 'tutor-google-meet-event-details', true );

		$meet_url = '' !== $meet_url ? $meet_url : (string) get_post_meta( $post_id, '_tutor_google_meet_meeting_url', true );
		$starts   = '' !== $starts ? $starts : (string) get_post_meta( $post_id, '_tutor_google_meet_start_datetime', true );
		$ends     = '' !== $ends ? $ends : (string) get_post_meta( $post_id, '_tutor_google_meet_end_datetime', true );
		$details  = '' !== $details ? $details : (string) get_post_meta( $post_id, '_tutor_google_meet_event_details', true );

		$event = '' !== $details ? json_decode( $details, true ) : array();
		$event = is_array( $event ) ? $event : array();

		if ( '' === $meet_url ) {
			$meet_url = (string) ( $event['meet_link'] ?? $event['hangoutLink'] ?? '' );
		}

		$starts = '' !== $starts ? $starts : (string) ( $event['start_datetime'] ?? '' );
		$ends   = '' !== $ends ? $ends : (string) ( $event['end_datetime'] ?? '' );

		// Tutor stores the start/end as wall-clock datetimes in the event timezone.
		$timezone = (string) ( $event['timezone'] ?? '' );

		// The Google Meet save hook calls the Google API — keep it detached while the post is written.
		$restore = ProTarget::mute_google_meet_hooks();

		try {
			$meet_id = Target::copy_post( $post_id, PostType::GOOGLE_MEET, self::SOURCE );

			// Requests the Google Meet addon (deferred until the batch commits). The Google event ID and
			// attendees are not carried over, so enrolling students never calls the Google API.
			ProTarget::set_google_meet(
				$meet_id,
				array(
					'url'      => $meet_url,
					'summary'  => $post ? $post->post_content : '',
					'start'    => static::zoned_datetime_to_unix( $starts, $timezone ),
					'end'      => static::zoned_datetime_to_unix( $ends, $timezone ),
					'timezone' => static::live_timezone( $timezone ),
				)
			);
		} finally {
			$restore();
		}

		if ( in_array( $event['visibility'] ?? '', array( 'public', 'private' ), true ) ) {
			update_post_meta( $meet_id, 'stm_gma_visibility', $event['visibility'] );
		}

		return $meet_id;
	}

	/**
	 * Append a material to the course's trailing "Live Sessions" section (created on demand).
	 *
	 * @param int $course_id Course post ID.
	 * @param int $post_id   Converted material post ID.
	 */
	private static function append_to_live_sessions( int $course_id, int $post_id ): void {
		global $wpdb;

		$title    = __( 'Live Sessions', 'masterstudy-lms-learning-management-system' );
		$sections = $wpdb->prefix . 'stm_lms_curriculum_sections';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$section_id = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$sections} WHERE course_id = %d AND title = %s ORDER BY id DESC LIMIT 1", $course_id, $title )
		);

		if ( ! $section_id ) {
			$max_order  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(`order`) FROM {$sections} WHERE course_id = %d", $course_id ) );
			$section_id = Target::add_section( $course_id, $title, $max_order + 1 );
		}
		// phpcs:enable

		Target::add_material( $section_id, $post_id, static::next_material_order( $section_id ) );
	}

	/**
	 * Next 1-based material position inside a section.
	 */
	private static function next_material_order( int $section_id ): int {
		global $wpdb;

		return 1 + (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(`order`) FROM {$wpdb->prefix}stm_lms_curriculum_materials WHERE section_id = %d",
				$section_id
			)
		);
	}

	/**
	 * Whether a curriculum section row belongs to a course.
	 */
	private static function section_belongs_to_course( int $section_id, int $course_id ): bool {
		global $wpdb;

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$wpdb->prefix}stm_lms_curriculum_sections WHERE id = %d AND course_id = %d",
				$section_id,
				$course_id
			)
		);
	}

	/**
	 * Curriculum material post IDs of a course, in curriculum order.
	 *
	 * @param int    $course_id Course post ID.
	 * @param string $post_type Material post type.
	 * @return int[]
	 */
	private static function course_material_ids( int $course_id, string $post_type ): array {
		global $wpdb;

		return array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT m.post_id FROM {$wpdb->prefix}stm_lms_curriculum_materials m
					 INNER JOIN {$wpdb->prefix}stm_lms_curriculum_sections s ON s.id = m.section_id
					 WHERE s.course_id = %d AND m.post_type = %s
					 ORDER BY s.`order` ASC, m.`order` ASC",
					$course_id,
					$post_type
				)
			)
		);
	}

	/**
	 * First lesson of a course curriculum (target for course-level Q&A threads).
	 */
	private static function first_course_lesson( int $course_id ): int {
		$lesson_ids = static::course_material_ids( $course_id, PostType::LESSON );

		return $lesson_ids ? (int) $lesson_ids[0] : 0;
	}

	/**
	 * Total and pass marks of a Tutor assignment (option first, v2 meta as fallback).
	 *
	 * @param int $assignment_id Assignment post ID.
	 * @return array{total: float, pass: float}
	 */
	private static function assignment_marks( int $assignment_id ): array {
		$option = get_post_meta( $assignment_id, 'assignment_option', true );
		$option = is_array( $option ) ? $option : array();

		return array(
			'total' => (float) ( $option['total_mark'] ?? get_post_meta( $assignment_id, '_tutor_assignment_total_mark', true ) ),
			'pass'  => (float) ( $option['pass_mark'] ?? get_post_meta( $assignment_id, '_tutor_assignment_pass_mark', true ) ),
		);
	}

	/**
	 * Attachment posts for the files a student uploaded with a Tutor submission.
	 *
	 * Tutor keeps the files in uploads and stores only their paths (`uploaded_attachments` JSON), so an
	 * attachment post is registered for each existing file (once per file — idempotent).
	 *
	 * @param int      $comment_id    Submission comment ID.
	 * @param int      $student_id    Student user ID.
	 * @param string[] $missing_files Receives the names of files that no longer exist.
	 * @return int[] Attachment IDs.
	 */
	private static function submission_attachments( int $comment_id, int $student_id, array &$missing_files ): array {
		$files = json_decode( (string) get_comment_meta( $comment_id, 'uploaded_attachments', true ), true );

		if ( ! is_array( $files ) || empty( $files ) ) {
			return array();
		}

		$uploads = wp_get_upload_dir();
		$ids     = array();

		foreach ( array_values( $files ) as $index => $file ) {
			if ( ! is_array( $file ) ) {
				continue;
			}

			$source_id = 'assignment-file-' . $comment_id . '-' . $index;
			$existing  = Target::find_migrated_post( 'attachment', self::SOURCE, $source_id );

			if ( $existing ) {
				$ids[] = $existing;
				continue;
			}

			$relative = ltrim( wp_normalize_path( (string) ( $file['uploaded_path'] ?? '' ) ), '/' );
			$path     = wp_normalize_path( trailingslashit( $uploads['basedir'] ) . $relative );
			$name     = (string) ( $file['name'] ?? basename( $relative ) );

			if ( '' === $relative || false !== strpos( $relative, '..' ) || ! is_file( $path ) ) {
				$missing_files[] = '' !== $name ? $name : '#' . ( $index + 1 );
				continue;
			}

			// Own copy of the file: deleting the MasterStudy submission must not delete Tutor's file.
			$filetype      = wp_check_filetype( $path );
			$attachment_id = Target::copy_file_attachment(
				$path,
				array(
					'post_mime_type' => ! empty( $file['type'] ) ? (string) $file['type'] : (string) $filetype['type'],
					'post_title'     => sanitize_text_field( pathinfo( $name, PATHINFO_FILENAME ) ),
					'post_author'    => $student_id,
				),
				self::SOURCE,
				$source_id
			);

			if ( ! $attachment_id ) {
				$missing_files[] = $name;
				continue;
			}

			$ids[] = (int) $attachment_id;
		}

		return $ids;
	}

	/**
	 * Unix start time of a Tutor Zoom meeting: the Zoom API start (UTC), else the Tutor wall-clock
	 * start in the meeting timezone.
	 *
	 * @param int   $post_id Zoom meeting post ID.
	 * @param array $data    Decoded `_tutor_zm_data`.
	 */
	private static function zoom_start_time( int $post_id, array $data ): int {
		$api_start = (string) ( $data['start_time'] ?? '' );

		if ( '' !== $api_start && preg_match( '/(Z|[+\-]\d{2}:?\d{2})$/', $api_start ) ) {
			$ts = strtotime( $api_start );

			if ( $ts ) {
				return (int) $ts;
			}
		}

		return static::zoned_datetime_to_unix( (string) get_post_meta( $post_id, '_tutor_zm_start_datetime', true ), (string) ( $data['timezone'] ?? '' ) );
	}

	/**
	 * Wall-clock datetime in a timezone (site timezone when invalid) → Unix timestamp (0 when empty/invalid).
	 *
	 * @param string $datetime Datetime string.
	 * @param string $timezone IANA timezone.
	 */
	private static function zoned_datetime_to_unix( string $datetime, string $timezone ): int {
		if ( '' === trim( $datetime ) || '0000-00-00 00:00:00' === $datetime ) {
			return 0;
		}

		try {
			$zone = '' !== $timezone && in_array( $timezone, timezone_identifiers_list(), true ) ? new \DateTimeZone( $timezone ) : wp_timezone();

			return ( new \DateTime( $datetime, $zone ) )->getTimestamp();
		} catch ( \Exception $e ) {
			return 0;
		}
	}

	/**
	 * Timezone passed to the ProTarget live-lesson helpers. They write the wall-clock date/time in the
	 * site timezone, so the site zone is used whenever it is a named zone (the absolute time stays
	 * right); otherwise the source zone is kept.
	 *
	 * @param string $source_timezone Source meeting timezone.
	 */
	private static function live_timezone( string $source_timezone ): string {
		$site = wp_timezone_string();

		return ProTarget::timezone() === $site ? $site : $source_timezone;
	}

	/**
	 * Append a "Join the meeting" link to a lesson's content (fallback for meetings that cannot be linked).
	 *
	 * @param int    $post_id Lesson post ID.
	 * @param string $url     Join URL.
	 */
	private static function append_join_link( int $post_id, string $url ): void {
		global $wpdb;

		$url = esc_url_raw( $url );

		if ( '' === $url ) {
			return;
		}

		$content = (string) get_post_field( 'post_content', $post_id );

		if ( false !== strpos( $content, $url ) || false !== strpos( $content, esc_url( $url ) ) ) {
			return;
		}

		$link = sprintf(
			'<p><a href="%1$s" target="_blank" rel="noopener">%2$s</a></p>',
			esc_url( $url ),
			esc_html__( 'Join the meeting', 'masterstudy-lms-learning-management-system' )
		);

		$wpdb->update( $wpdb->posts, array( 'post_content' => ( '' !== trim( $content ) ? $content . "\n\n" : '' ) . $link ), array( 'ID' => $post_id ) );
		clean_post_cache( $post_id );
	}

	/**
	 * Tutor "after finishing prerequisites" drip (per-item `_content_drip_settings[prerequisites]`) →
	 * MasterStudy Pro "unlock after item" map of the course copy. MasterStudy enforces one parent per item, so the
	 * prerequisite that comes last in the curriculum is kept.
	 *
	 * @param int $source_course_id Tutor course post ID (read only).
	 * @param int $course_id        MasterStudy course copy (curriculum already built).
	 */
	private static function migrate_drip_prerequisites( int $source_course_id, int $course_id ): void {
		global $wpdb;

		$settings = maybe_unserialize( get_post_meta( $source_course_id, '_tutor_course_settings', true ) );

		if ( ! ProTarget::pro_active() || ! is_array( $settings ) || empty( $settings['enable_content_drip'] ) || 'after_finishing_prerequisites' !== ( $settings['content_drip_type'] ?? '' ) ) {
			return;
		}

		$materials = array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT m.post_id FROM {$wpdb->prefix}stm_lms_curriculum_materials m
					 INNER JOIN {$wpdb->prefix}stm_lms_curriculum_sections s ON s.id = m.section_id
					 WHERE s.course_id = %d
					 ORDER BY s.`order` ASC, m.`order` ASC",
					$course_id
				)
			)
		);
		$position  = array_flip( $materials );
		$children  = array();
		$partial   = array();

		foreach ( $materials as $item_id ) {
			// The drip rule is read from the Tutor item; its prerequisites (Tutor IDs) are mapped to the copies.
			$drip = maybe_unserialize( get_post_meta( Target::source_of( $item_id ), '_content_drip_settings', true ) );

			$prerequisites = array_filter(
				Target::copies_of( self::SOURCE, array_map( 'intval', is_array( $drip ) ? (array) ( $drip['prerequisites'] ?? array() ) : array() ) ),
				function ( $id ) use ( $position, $item_id ) {
					return $id !== $item_id && isset( $position[ $id ] );
				}
			);

			if ( empty( $prerequisites ) ) {
				continue;
			}

			usort(
				$prerequisites,
				function ( $a, $b ) use ( $position ) {
					return $position[ $a ] <=> $position[ $b ];
				}
			);

			$children[ end( $prerequisites ) ][] = $item_id;

			if ( count( $prerequisites ) > 1 ) {
				$partial[] = get_post_field( 'post_title', $item_id );
			}
		}

		$map = array();

		foreach ( $children as $parent => $items ) {
			$map[] = array(
				'parent'   => (int) $parent,
				'children' => $items,
			);
		}

		ProTarget::drip_after_items( $course_id, $map );

		if ( ! empty( $partial ) ) {
			Report::add(
				Report::GROUP_COURSES,
				array(
					'source_id' => $source_course_id,
					'title'     => get_post_field( 'post_title', $course_id ),
					'type'      => __( 'Content drip', 'masterstudy-lms-learning-management-system' ),
					/* translators: %s: comma-separated item titles */
					'reason'    => sprintf( __( 'MasterStudy unlocks an item after one previous item only — for these items with several Tutor prerequisites only the last one in the curriculum is enforced: %s.', 'masterstudy-lms-learning-management-system' ), implode( ', ', $partial ) ),
					'status'    => Report::STATUS_PARTIAL,
					'course'    => get_post_field( 'post_title', $course_id ),
					'post_id'   => $course_id,
				)
			);
		}
	}

	/**
	 * MasterStudy certificate approximating a Tutor certificate template (created once per template).
	 *
	 * Built-in Tutor Pro templates keep their background image; Certificate Builder templates
	 * (`tutor_cb_{id}`) keep their title and orientation.
	 *
	 * @param string $template Tutor template key.
	 * @return int Certificate post ID, or 0 when it could not be created.
	 */
	private static function migrate_certificate_template( string $template ): int {
		$title       = '';
		$orientation = 'landscape';
		$background  = 0;

		if ( preg_match( '/^tutor_cb_(\d+)$/', $template, $matches ) ) {
			$builder = get_post( (int) $matches[1] );

			if ( $builder && 'tutor_certificate' === $builder->post_type ) {
				$title       = $builder->post_title;
				$orientation = (string) get_post_meta( $builder->ID, 'tutor_certificate_dimension', true );
			}
		} elseif ( isset( self::CERTIFICATE_TEMPLATES[ $template ] ) ) {
			list( $title, $orientation ) = self::CERTIFICATE_TEMPLATES[ $template ];
			$background                  = static::certificate_background( $template );
		}

		if ( '' === $title ) {
			$title = ucwords( str_replace( array( '_', '-' ), ' ', $template ) );
		}

		return ProTarget::create_certificate(
			array(
				'source_id'     => $template,
				/* translators: %s: Tutor LMS certificate template name */
				'title'         => sprintf( __( '%s (Tutor LMS)', 'masterstudy-lms-learning-management-system' ), $title ),
				'background_id' => $background,
				'orientation'   => 'portrait' === $orientation ? 'portrait' : 'landscape',
			),
			self::SOURCE
		);
	}

	/**
	 * Attachment holding a built-in Tutor Pro certificate background (copied into uploads once).
	 *
	 * @param string $template Built-in template key.
	 * @return int Attachment ID, or 0 when the image is not available.
	 */
	private static function certificate_background( string $template ): int {
		$source_id = 'certificate-background-' . $template;
		$existing  = Target::find_migrated_post( 'attachment', self::SOURCE, $source_id );

		if ( $existing ) {
			return $existing;
		}

		$file = WP_PLUGIN_DIR . '/tutor-pro/addons/tutor-certificate/templates/' . $template . '/background.png';

		if ( ! preg_match( '/^[a-z0-9_]+$/', $template ) || ! is_readable( $file ) ) {
			return 0;
		}

		$upload = wp_upload_bits( 'tutor-certificate-' . $template . '.png', null, (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( ! empty( $upload['error'] ) ) {
			return 0;
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/png',
				/* translators: %s: Tutor LMS certificate template key */
				'post_title'     => sprintf( __( 'Tutor LMS certificate background (%s)', 'masterstudy-lms-learning-management-system' ), $template ),
				'post_content'   => '',
				'post_status'    => 'inherit',
				'guid'           => $upload['url'],
			),
			$upload['file'],
			0,
			true
		);

		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return 0;
		}

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
		update_post_meta( $attachment_id, Target::SOURCE_META, self::SOURCE );
		update_post_meta( $attachment_id, Target::SOURCE_ID_META, $source_id );
		update_post_meta( $attachment_id, Target::OWN_FILE_META, 1 );

		return (int) $attachment_id;
	}

	/**
	 * Tutor Pro course bundles (`course-bundle` posts) → MasterStudy Pro bundles. Idempotent per bundle.
	 */
	private static function migrate_bundles(): void {
		global $wpdb;

		$bundles = $wpdb->get_results(
			"SELECT ID, post_title, post_content, post_author, post_status FROM {$wpdb->posts}
			 WHERE post_type = 'course-bundle' AND post_status NOT IN ( 'trash', 'auto-draft', 'inherit' )
			 ORDER BY ID ASC"
		);

		foreach ( (array) $bundles as $bundle ) {
			$bundle_id = (int) $bundle->ID;
			$report    = array(
				'source_id' => $bundle_id,
				'title'     => $bundle->post_title,
				'type'      => __( 'Tutor course bundle', 'masterstudy-lms-learning-management-system' ),
				'post_id'   => $bundle_id,
			);

			if ( ! ProTarget::pro_active() ) {
				Report::add(
					Report::GROUP_COURSES,
					$report + array(
						'reason' => __( 'Course bundles require MasterStudy LMS Pro (Course Bundle addon) — the bundle was not imported.', 'masterstudy-lms-learning-management-system' ),
						'status' => Report::STATUS_UNSUPPORTED,
					)
				);
				continue;
			}

			$source_ids = array_values( array_filter( array_map( 'intval', explode( ',', (string) get_post_meta( $bundle_id, 'bundle-course-ids', true ) ) ) ) );
			$course_ids = static::migrated_courses_only( $source_ids );

			try {
				$ms_bundle_id = ProTarget::create_bundle(
					array(
						'source_id'    => $bundle_id,
						'title'        => $bundle->post_title,
						'content'      => $bundle->post_content,
						'author'       => (int) $bundle->post_author,
						'price'        => static::bundle_price( $bundle_id ),
						'course_ids'   => $course_ids,
						'thumbnail_id' => (int) get_post_thumbnail_id( $bundle_id ),
						'status'       => in_array( $bundle->post_status, array( 'publish', 'private' ), true ) ? $bundle->post_status : 'draft',
					),
					self::SOURCE
				);
			} catch ( \Exception $e ) {
				Report::add(
					Report::GROUP_COURSES,
					$report + array(
						'reason' => $e->getMessage(),
						'status' => Report::STATUS_FAILED,
					)
				);
				continue;
			}

			if ( count( $course_ids ) < count( $source_ids ) ) {
				Report::add(
					Report::GROUP_COURSES,
					array(
						'reason'  => sprintf(
							/* translators: %d: number of courses */
							__( 'The bundle was imported without %d of its courses, which were not migrated.', 'masterstudy-lms-learning-management-system' ),
							count( $source_ids ) - count( $course_ids )
						),
						'status'  => Report::STATUS_PARTIAL,
						'post_id' => $ms_bundle_id,
					) + $report
				);
			}
		}
	}

	/**
	 * Price a Tutor bundle sells for (sale price when lower), from Tutor native meta or its WooCommerce product.
	 *
	 * @param int $bundle_id Bundle post ID.
	 */
	private static function bundle_price( int $bundle_id ): float {
		$regular = (float) get_post_meta( $bundle_id, 'tutor_course_price', true );
		$sale    = (float) get_post_meta( $bundle_id, 'tutor_course_sale_price', true );

		$product_id = (int) get_post_meta( $bundle_id, '_tutor_course_product_id', true );
		$product    = $product_id && function_exists( 'wc_get_product' ) ? \wc_get_product( $product_id ) : null;

		if ( $product ) {
			$regular = (float) $product->get_regular_price();
			$sale    = (float) $product->get_sale_price();
		}

		return $sale > 0 && $sale < $regular ? $sale : $regular;
	}

	/**
	 * Tutor native coupons (`tutor_coupons`) → MasterStudy Pro Plus coupons. Idempotent by code.
	 */
	private static function migrate_coupons(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'tutor_coupons';

		if ( ! static::table_exists( $table ) ) {
			return;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$coupons = $wpdb->get_results( "SELECT * FROM {$table} WHERE coupon_status IS NULL OR coupon_status != 'trash' ORDER BY id ASC" );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$imported = 0;

		foreach ( (array) $coupons as $coupon ) {
			$code   = (string) $coupon->coupon_code;
			$report = array(
				'source_id' => (int) $coupon->id,
				'title'     => '' !== (string) $coupon->coupon_title ? $coupon->coupon_title . ' (' . $code . ')' : $code,
				'type'      => __( 'Tutor coupon', 'masterstudy-lms-learning-management-system' ),
			);

			if ( ! ProTarget::plus_active() ) {
				Report::add(
					Report::GROUP_ORDERS,
					$report + array(
						'reason' => __( 'Coupons require MasterStudy LMS Pro Plus — the coupon was not imported.', 'masterstudy-lms-learning-management-system' ),
						'status' => Report::STATUS_UNSUPPORTED,
					)
				);
				continue;
			}

			$references = array_map(
				'intval',
				(array) $wpdb->get_col(
					$wpdb->prepare( "SELECT reference_id FROM {$wpdb->prefix}tutor_coupon_applications WHERE coupon_code = %s", $code )
				)
			);
			$notes      = array();
			$course_ids = array();
			$applies_to = (string) $coupon->applies_to;

			switch ( $applies_to ) {
				case 'all_courses_and_bundles':
				case 'all_courses':
				case '':
					break;

				case 'specific_courses':
					$course_ids = static::migrated_courses_only( $references );
					break;

				case 'specific_category':
					$course_ids = static::migrated_courses_only( static::courses_in_tutor_categories( $references ) );
					$notes[]    = __( 'the category restriction was converted to the courses currently in those categories', 'masterstudy-lms-learning-management-system' );
					break;

				default:
					$course_ids = array();
					$applies_to = 'unsupported';
			}

			if ( 'unsupported' === $applies_to || ( in_array( $applies_to, array( 'specific_courses', 'specific_category' ), true ) && empty( $course_ids ) ) ) {
				Report::add(
					Report::GROUP_ORDERS,
					$report + array(
						/* translators: %s: Tutor "applies to" value */
						'reason' => sprintf( __( 'The coupon applies to "%s", which has no MasterStudy coupon equivalent (or none of its courses were migrated) — it was not imported.', 'masterstudy-lms-learning-management-system' ), (string) $coupon->applies_to ),
						'status' => Report::STATUS_UNSUPPORTED,
					)
				);
				continue;
			}

			if ( 'automatic' === $coupon->coupon_type ) {
				$notes[] = __( 'automatic coupons are not applied automatically in MasterStudy — students must enter the code', 'masterstudy-lms-learning-management-system' );
			}

			$min_amount   = 'minimum_purchase' === $coupon->purchase_requirement ? (float) $coupon->purchase_requirement_value : 0;
			$min_quantity = 'minimum_quantity' === $coupon->purchase_requirement ? (int) $coupon->purchase_requirement_value : 0;
			$start        = ! empty( $coupon->start_date_gmt ) && '0000-00-00 00:00:00' !== $coupon->start_date_gmt ? (int) strtotime( $coupon->start_date_gmt . ' UTC' ) : 0;
			$end          = ! empty( $coupon->expire_date_gmt ) && '0000-00-00 00:00:00' !== $coupon->expire_date_gmt ? (int) strtotime( $coupon->expire_date_gmt . ' UTC' ) : 0;

			$coupon_id = ProTarget::create_coupon(
				array(
					'code'             => $code,
					'title'            => '' !== (string) $coupon->coupon_title ? (string) $coupon->coupon_title : $code,
					'type'             => 'flat' === $coupon->discount_type ? 'amount' : 'percent',
					'amount'           => (float) $coupon->discount_amount,
					'status'           => 'active' === $coupon->coupon_status ? 'active' : 'inactive',
					'usage_limit'      => (int) $coupon->total_usage_limit,
					'user_usage_limit' => (int) $coupon->per_user_usage_limit,
					'used_count'       => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}tutor_coupon_usages WHERE coupon_code = %s", $code ) ),
					'min_amount'       => $min_amount,
					'start'            => $start,
					'end'              => $end,
					'course_ids'       => $course_ids,
				)
			);

			// 0 = the code already exists (imported by an earlier run) — nothing to report again.
			if ( ! $coupon_id ) {
				continue;
			}

			++$imported;

			if ( $min_quantity > 0 ) {
				$wpdb->update( $wpdb->prefix . 'stm_lms_coupons', array( 'min_course_quantity' => $min_quantity ), array( 'id' => $coupon_id ) );
			}

			if ( ! empty( $notes ) ) {
				Report::add(
					Report::GROUP_ORDERS,
					$report + array(
						/* translators: %s: list of differences */
						'reason' => sprintf( __( 'The coupon was imported, but: %s.', 'masterstudy-lms-learning-management-system' ), implode( '; ', $notes ) ),
						'status' => Report::STATUS_PARTIAL,
					)
				);
			}
		}

		$settings = get_option( 'stm_lms_settings', array() );

		if ( $imported && ( ! is_array( $settings ) || empty( $settings['enable_coupon_code'] ) ) ) {
			Helper::log( 'warning', sprintf( 'Tutor LMS: %d coupon(s) imported — enable coupons in the MasterStudy LMS settings so students can use them.', $imported ) );
		}
	}

	/**
	 * Tutor Pro subscription plans → MasterStudy Pro Plus subscription plans. Gateway data cannot be
	 * migrated, so user subscriptions are not created (active subscribers are reported).
	 */
	private static function migrate_subscription_plans(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'tutor_subscription_plans';

		if ( ! static::table_exists( $table ) ) {
			return;
		}

		$plans = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $plans ) ) {
			return;
		}

		if ( ProTarget::plus_active() ) {
			// The plan tables must exist before the first insert — enable the addon now (outside any transaction).
			Helper::request_addon( 'subscriptions' );
			Helper::flush_addon_requests();
		}

		$subscriptions_table   = $wpdb->prefix . 'tutor_subscriptions';
		$has_subscriptions     = static::table_exists( $subscriptions_table );
		$membership_full_site  = false;
		$membership_categories = array();
		$trial_days          = array(
			'hour'  => 1 / 24,
			'day'   => 1,
			'week'  => 7,
			'month' => 30,
			'year'  => 365,
		);

		foreach ( $plans as $plan ) {
			$plan_id = (int) $plan->id;
			$report  = array(
				'source_id' => $plan_id,
				'title'     => (string) $plan->plan_name,
				'type'      => __( 'Tutor subscription plan', 'masterstudy-lms-learning-management-system' ),
			);

			if ( ! ProTarget::plus_active() ) {
				Report::add(
					Report::GROUP_ORDERS,
					$report + array(
						'reason' => __( 'Subscription plans require MasterStudy LMS Pro Plus (Subscriptions addon) — the plan was not imported.', 'masterstudy-lms-learning-management-system' ),
						'status' => Report::STATUS_UNSUPPORTED,
					)
				);
				continue;
			}

			$type       = (string) $plan->plan_type;
			$object_ids = array();
			$reason     = '';

			$items = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT object_id FROM {$wpdb->prefix}tutor_subscription_plan_items WHERE plan_id = %d AND object_name = %s",
					$plan_id,
					'category' === $type ? 'category' : 'course'
				)
			);
			$items = array_map( 'intval', (array) $items );

			if ( 'onetime' === $plan->payment_type ) {
				$reason = __( 'One-time (lifetime) membership plans have no MasterStudy equivalent — MasterStudy plans are recurring.', 'masterstudy-lms-learning-management-system' );
			} elseif ( ! in_array( $plan->recurring_interval, array( 'day', 'week', 'month', 'year' ), true ) ) {
				/* translators: %s: billing interval */
				$reason = sprintf( __( 'MasterStudy plans cannot bill every "%s".', 'masterstudy-lms-learning-management-system' ), (string) $plan->recurring_interval );
			} elseif ( 'course' === $type ) {
				$object_ids = static::migrated_courses_only( $items );
			} elseif ( 'category' === $type ) {
				$object_ids = static::ms_category_ids( $items );
			} elseif ( 'full_site' !== $type ) {
				/* translators: %s: plan type */
				$reason = sprintf( __( 'Plans of type "%s" have no MasterStudy equivalent.', 'masterstudy-lms-learning-management-system' ), $type );
			}

			if ( '' === $reason && 'full_site' !== $type && empty( $object_ids ) ) {
				$reason = __( 'None of the plan\'s courses or categories were migrated.', 'masterstudy-lms-learning-management-system' );
			}

			if ( '' !== $reason ) {
				Report::add(
					Report::GROUP_ORDERS,
					$report + array(
						'reason' => $reason . ' ' . __( 'The plan was not imported.', 'masterstudy-lms-learning-management-system' ),
						'status' => Report::STATUS_UNSUPPORTED,
					)
				);
				continue;
			}

			$now       = time();
			$sale_from = ! empty( $plan->sale_price_from ) ? static::local_datetime_to_unix( (string) $plan->sale_price_from ) : 0;
			$sale_to   = ! empty( $plan->sale_price_to ) ? static::local_datetime_to_unix( (string) $plan->sale_price_to ) : 0;
			$on_sale   = (float) $plan->sale_price > 0 && ( ! $sale_from || $sale_from <= $now ) && ( ! $sale_to || $sale_to > $now );
			$trial     = (int) $plan->trial_value > 0 ? (int) ceil( (int) $plan->trial_value * ( $trial_days[ (string) $plan->trial_interval ] ?? 1 ) ) : 0;

			try {
				$ms_plan_id = ProTarget::create_subscription_plan(
					array(
						'source_id'      => 'plan-' . $plan_id,
						'name'           => (string) $plan->plan_name,
						'description'    => '' !== (string) $plan->short_description ? (string) $plan->short_description : (string) $plan->description,
						'type'           => $type,
						'object_ids'     => $object_ids,
						'price'          => (float) $plan->regular_price,
						'sale_price'     => $on_sale ? (float) $plan->sale_price : 0,
						'interval'       => (string) $plan->recurring_interval,
						'interval_value' => (int) $plan->recurring_value,
						'billing_cycles' => (int) $plan->recurring_limit,
						'trial_days'     => $trial,
						'enrollment_fee' => (float) $plan->enrollment_fee,
						'enabled'        => (bool) $plan->is_enabled,
					),
					self::SOURCE
				);
			} catch ( \Exception $e ) {
				Report::add(
					Report::GROUP_ORDERS,
					$report + array(
						'reason' => $e->getMessage(),
						'status' => Report::STATUS_FAILED,
					)
				);
				continue;
			}

			// Display attributes create_subscription_plan() does not set.
			$wpdb->update(
				$wpdb->prefix . 'stm_lms_subscription_plans',
				array(
					'is_featured'   => (int) ( $plan->is_featured ?? 0 ),
					'featured_text' => (string) ( $plan->featured_text ?? '' ),
					'plan_order'    => (int) ( $plan->plan_order ?? 0 ),
				),
				array( 'id' => $ms_plan_id )
			);

			$notes = array();

			if ( $has_subscriptions ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$active = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$subscriptions_table} WHERE plan_id = %d AND status = 'active'", $plan_id ) );

				if ( $active ) {
					/* translators: %d: number of subscribers */
					$notes[] = sprintf( _n( '%d active subscriber must re-subscribe (payment gateway subscriptions cannot be migrated)', '%d active subscribers must re-subscribe (payment gateway subscriptions cannot be migrated)', $active, 'masterstudy-lms-learning-management-system' ), $active );
				}
			}

			// Tutor grants a full-site / category member access to every (paid) course it covers, whatever
			// the course selling option — MasterStudy plans only cover courses included in memberships.
			if ( 'full_site' === $type ) {
				$membership_full_site = true;
			} elseif ( 'category' === $type ) {
				$membership_categories = array_merge( $membership_categories, $object_ids );
			}

			if ( ! empty( $plan->restriction_mode ) ) {
				$notes[] = __( 'the plan\'s course include/exclude restriction was not carried over', 'masterstudy-lms-learning-management-system' );
			}

			if ( ! empty( $notes ) ) {
				Report::add(
					Report::GROUP_ORDERS,
					$report + array(
						/* translators: %s: list of differences */
						'reason' => sprintf( __( 'The plan was imported, but: %s.', 'masterstudy-lms-learning-management-system' ), implode( '; ', $notes ) ),
						'status' => Report::STATUS_PARTIAL,
					)
				);
			}
		}

		if ( ProTarget::plus_active() ) {
			static::include_courses_in_memberships( $membership_full_site, $membership_categories );
		}
	}

	/**
	 * Include the paid courses a migrated Tutor full-site / category membership plan covers in MasterStudy
	 * memberships (not_membership off) — Tutor lets such members enroll whatever the course selling option.
	 *
	 * @param bool  $full_site    Whether a full-site plan was imported.
	 * @param int[] $category_ids MasterStudy category IDs covered by imported category plans.
	 */
	private static function include_courses_in_memberships( bool $full_site, array $category_ids ): void {
		$category_ids = array_values( array_unique( array_filter( array_map( 'intval', $category_ids ) ) ) );

		if ( ! $full_site && empty( $category_ids ) ) {
			return;
		}

		foreach ( static::migrated_course_ids() as $course_id ) {
			if ( ! $course_id || PricingMode::PAID !== get_post_meta( $course_id, 'pricing_mode', true ) ) {
				continue;
			}

			$covered = $full_site;

			if ( ! $covered ) {
				$terms   = wp_get_object_terms( $course_id, Taxonomy::COURSE_CATEGORY, array( 'fields' => 'ids' ) );
				$covered = ! is_wp_error( $terms ) && (bool) array_intersect( array_map( 'intval', $terms ), $category_ids );
			}

			if ( $covered ) {
				update_post_meta( $course_id, 'not_membership', '' );
			}
		}
	}

	/**
	 * Report Tutor subscription-only / membership-only courses that no imported MasterStudy plan sells.
	 */
	private static function report_unsellable_courses(): void {
		foreach ( static::migrated_course_ids() as $course_id ) {
			$option = $course_id ? (string) get_post_meta( $course_id, '_migrated_tutor_course_selling_option', true ) : '';

			if ( ! in_array( $option, array( 'subscription', 'membership' ), true ) ) {
				continue;
			}

			$sellable = 'subscription' === $option
				? 'on' === get_post_meta( $course_id, 'subscriptions', true )
				: '' === (string) get_post_meta( $course_id, 'not_membership', true ) && static::has_membership_plan();

			if ( $sellable ) {
				continue;
			}

			Report::add(
				Report::GROUP_COURSES,
				array(
					'source_id' => $course_id,
					'title'     => get_post_field( 'post_title', $course_id ),
					'type'      => __( 'Course selling option', 'masterstudy-lms-learning-management-system' ),
					'reason'    => 'subscription' === $option
						? __( 'The course was sold only through Tutor LMS course subscription plans and none of them could be imported — it cannot be purchased until you add a subscription plan or a price.', 'masterstudy-lms-learning-management-system' )
						: __( 'The course was sold only through Tutor LMS membership plans and none of them could be imported — it is "members only" until you add a membership plan or a price.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_PARTIAL,
					'course'    => get_post_field( 'post_title', $course_id ),
					'post_id'   => $course_id,
				)
			);
		}
	}

	/**
	 * Whether MasterStudy has a full-site or category subscription plan.
	 */
	private static function has_membership_plan(): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'stm_lms_subscription_plans';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return static::table_exists( $table ) && (bool) $wpdb->get_var( "SELECT id FROM {$table} WHERE type IN ( 'full_site', 'category' ) LIMIT 1" );
	}

	/**
	 * Effective Tutor Pro selling option of a paid, natively sold course: 'subscription' (course plans only),
	 * 'both' (one-time + course plans), 'membership' (membership plans only), 'all', or '' (one-time purchase).
	 * Tutor's "membership only" mode makes every paid course membership-only.
	 *
	 * @param int $course_id Tutor course post ID.
	 */
	private static function selling_option( int $course_id ): string {
		if ( function_exists( 'tutor_utils' ) && (bool) \tutor_utils()->get_option( 'membership_only_mode', false ) ) {
			return 'membership';
		}

		$option = (string) get_post_meta( $course_id, 'tutor_course_selling_option', true );

		return in_array( $option, array( 'subscription', 'both', 'membership', 'all' ), true ) ? $option : '';
	}

	/**
	 * Apply a Tutor selling option on top of the course pricing: a course without a one-time purchase stays
	 * paid (never free) with no single sale; membership courses are included in MasterStudy memberships.
	 * Course subscription plans are linked when the plans are imported (courses step finalize, Pro Plus).
	 *
	 * @param int    $course_id     MasterStudy course post ID (the copy).
	 * @param string $option        Selling option (see selling_option()).
	 * @param float  $regular_price Tutor regular price.
	 * @return string Report note ('' when fully carried over).
	 */
	private static function set_selling_option( int $course_id, string $option, float $regular_price ): string {
		$one_time   = in_array( $option, array( 'both', 'all' ), true ) && $regular_price > 0;
		$membership = in_array( $option, array( 'membership', 'all' ), true );

		Target::store_unmigrated_meta( $course_id, 'tutor_course_selling_option', $option );

		if ( ! $one_time ) {
			// Not sold on its own — but never a free course.
			update_post_meta( $course_id, 'pricing_mode', PricingMode::PAID );
			update_post_meta( $course_id, 'single_sale', '' );
		}

		update_post_meta( $course_id, 'not_membership', $membership ? '' : 'on' );

		if ( ProTarget::plus_active() ) {
			return '';
		}

		return $one_time
			? __( 'subscription / membership plan access (requires MasterStudy LMS Pro Plus) — only the one-time purchase was kept', 'masterstudy-lms-learning-management-system' )
			: __( 'the course was sold only through Tutor LMS subscription / membership plans, which require MasterStudy LMS Pro Plus — it is imported as a paid course without a one-time purchase, so it cannot be bought until you add a price or plans', 'masterstudy-lms-learning-management-system' );
	}

	/**
	 * Tutor Pro gradebook scale (`tutor_gradebooks`) → MasterStudy Pro Plus grade table (Grades addon), unless the
	 * MasterStudy grade table was already configured. Student gradebook results are derived data (MasterStudy
	 * computes grades from quiz and assignment results).
	 */
	private static function migrate_gradebook_scale(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'tutor_gradebooks';

		if ( ! static::table_exists( $table ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$grades = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY percent_to DESC, percent_from DESC" );

		if ( empty( $grades ) ) {
			return;
		}

		$report   = array(
			'source_id' => 'gradebook',
			'title'     => __( 'Gradebook grade scale', 'masterstudy-lms-learning-management-system' ),
			'type'      => __( 'Tutor gradebook', 'masterstudy-lms-learning-management-system' ),
		);
		$settings = get_option( 'stm_lms_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();

		if ( ! ProTarget::plus_active() || ! empty( $settings['grades_table'] ) ) {
			Report::add(
				Report::GROUP_OTHER,
				$report + array(
					'reason' => ! ProTarget::plus_active()
						? __( 'Grade scales require MasterStudy LMS Pro Plus (Grades addon) — the Tutor LMS gradebook scale was not imported.', 'masterstudy-lms-learning-management-system' )
						: __( 'MasterStudy already has a grade scale, which was kept — the Tutor LMS gradebook scale was not imported.', 'masterstudy-lms-learning-management-system' ),
					'status' => Report::STATUS_UNSUPPORTED,
				)
			);
			return;
		}

		$scale = array();

		foreach ( $grades as $grade ) {
			$config  = maybe_unserialize( $grade->grade_config ?? '' );
			$color   = is_array( $config ) ? sanitize_hex_color( (string) ( $config['grade_color'] ?? '' ) ) : '';
			$scale[] = array(
				'grade' => sanitize_text_field( (string) $grade->grade_name ),
				'point' => (float) $grade->grade_point,
				'range' => array( (int) $grade->percent_from, (int) $grade->percent_to ),
				'color' => $color ? $color : '#227AFF',
			);
		}

		$settings['grades_table'] = $scale;
		update_option( 'stm_lms_settings', $settings );
		Helper::request_addon( 'grades' );
	}

	/**
	 * Tutor revenue sharing (tutor_option) → MasterStudy Pro instructor / admin commission, when MasterStudy has none
	 * configured. Tutor earnings, withdrawals and per-instructor commission rates are reported: MasterStudy computes
	 * instructor earnings from its own orders.
	 */
	private static function migrate_commission_settings(): void {
		global $wpdb;

		$option = get_option( 'tutor_option' );
		$option = is_array( $option ) ? $option : array();
		$notes  = array();

		$sharing = $option['enable_revenue_sharing'] ?? '';
		if ( in_array( $sharing, array( 'on', '1', 1, true ), true ) && isset( $option['earning_instructor_commission'] ) ) {
			$settings = get_option( 'stm_lms_settings', array() );
			$settings = is_array( $settings ) ? $settings : array();

			if ( ProTarget::pro_active() && ! isset( $settings['author_fee'] ) && ! isset( $settings['admin_fee'] ) ) {
				$settings['author_fee'] = (string) (float) $option['earning_instructor_commission'];
				$settings['admin_fee']  = (string) (float) ( $option['earning_admin_commission'] ?? ( 100 - (float) $option['earning_instructor_commission'] ) );
				update_option( 'stm_lms_settings', $settings );
			} else {
				$notes[] = ProTarget::pro_active()
					? __( 'the Tutor LMS revenue sharing rates (MasterStudy commission rates were already set and kept)', 'masterstudy-lms-learning-management-system' )
					: __( 'the Tutor LMS revenue sharing rates (instructor earnings require MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
			}
		}

		foreach ( array( 'tutor_earnings' => __( '%d instructor earning record(s)', 'masterstudy-lms-learning-management-system' ), 'tutor_withdraws' => __( '%d withdrawal request(s)', 'masterstudy-lms-learning-management-system' ) ) as $suffix => $label ) {
			$table = $wpdb->prefix . $suffix;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count = static::table_exists( $table ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) : 0;

			if ( $count ) {
				$notes[] = sprintf( $label, $count );
			}
		}

		$custom_rates = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key = 'tutor_instructor_amount_type' AND meta_value IN ( 'fixed', 'percent' )" );
		if ( $custom_rates ) {
			/* translators: %d: number of instructors */
			$notes[] = sprintf( __( 'individual commission rates of %d instructor(s)', 'masterstudy-lms-learning-management-system' ), $custom_rates );
		}

		if ( empty( $notes ) ) {
			return;
		}

		Report::add(
			Report::GROUP_ORDERS,
			array(
				'source_id' => 'earnings',
				'title'     => __( 'Instructor earnings and withdrawals', 'masterstudy-lms-learning-management-system' ),
				'type'      => __( 'Tutor revenue sharing', 'masterstudy-lms-learning-management-system' ),
				/* translators: %s: list of items */
				'reason'    => sprintf( __( 'Not imported: %s. MasterStudy calculates instructor earnings from its own orders — settle pending Tutor LMS withdrawals before switching.', 'masterstudy-lms-learning-management-system' ), implode( '; ', $notes ) ),
				'status'    => Report::STATUS_UNSUPPORTED,
			)
		);
	}

	/**
	 * WPML: give every Tutor copy a translation row like its source post (`icl_translations`): same language codes,
	 * element_type of the MasterStudy post type, and ONE new trid per source trid — so copies of translations of the
	 * same Tutor post stay linked to each other, and never to the Tutor posts. Source rows are never changed.
	 * Idempotent: copies that already have a row are skipped, later copies join the trid of their group.
	 */
	private static function sync_translations(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'icl_translations';

		if ( ! static::table_exists( $table ) ) {
			return;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.ID AS copy_id, c.post_type AS copy_type, t.trid AS source_trid, t.language_code, t.source_language_code,
				        ct.trid AS copy_trid
				 FROM {$wpdb->posts} c
				 INNER JOIN {$wpdb->postmeta} s ON s.post_id = c.ID AND s.meta_key = %s AND s.meta_value = %s
				 INNER JOIN {$wpdb->postmeta} i ON i.post_id = c.ID AND i.meta_key = %s AND i.meta_value LIKE %s
				 INNER JOIN {$wpdb->posts} src ON src.ID = CAST( SUBSTRING( i.meta_value, %d ) AS UNSIGNED )
				 INNER JOIN {$table} t ON t.element_id = src.ID AND t.element_type = CONCAT( 'post_', src.post_type )
				 LEFT JOIN {$table} ct ON ct.element_id = c.ID AND ct.element_type = CONCAT( 'post_', c.post_type )
				 ORDER BY t.trid ASC, t.translation_id ASC",
				Target::SOURCE_META,
				self::SOURCE,
				Target::SOURCE_ID_META,
				$wpdb->esc_like( Target::COPY_KEY_PREFIX ) . '%',
				strlen( Target::COPY_KEY_PREFIX ) + 1
			)
		);

		if ( empty( $rows ) ) {
			return;
		}

		// Source trid => trid of the copies (from copies that already have a row).
		$trids = array();

		foreach ( $rows as $row ) {
			if ( null !== $row->copy_trid ) {
				$trids[ (int) $row->source_trid ] = (int) $row->copy_trid;
			}
		}

		$next = 1 + (int) $wpdb->get_var( "SELECT MAX(trid) FROM {$table}" );

		foreach ( $rows as $row ) {
			if ( null !== $row->copy_trid ) {
				continue;
			}

			$source_trid = (int) $row->source_trid;

			if ( ! isset( $trids[ $source_trid ] ) ) {
				$trids[ $source_trid ] = $next++;
			}

			// A language already taken in the copies' group (two copies of one source language) cannot be added.
			$taken = $wpdb->get_var(
				$wpdb->prepare( "SELECT 1 FROM {$table} WHERE trid = %d AND language_code = %s LIMIT 1", $trids[ $source_trid ], $row->language_code )
			);

			if ( $taken ) {
				continue;
			}

			$wpdb->insert(
				$table,
				array(
					'element_type'         => 'post_' . $row->copy_type,
					'element_id'           => (int) $row->copy_id,
					'trid'                 => $trids[ $source_trid ],
					'language_code'        => (string) $row->language_code,
					'source_language_code' => null === $row->source_language_code ? null : (string) $row->source_language_code,
				),
				array( '%s', '%d', '%d', '%s', '%s' )
			);
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * MasterStudy course IDs for a list of Tutor course IDs: the copies of the Tutor courses that were migrated
	 * (IDs that already are MasterStudy courses are kept). Order kept, duplicates and unmigrated courses dropped.
	 *
	 * @param int[] $ids Tutor (or MasterStudy) course IDs.
	 * @return int[]
	 */
	private static function migrated_courses_only( array $ids ): array {
		$courses = array();

		foreach ( array_map( 'intval', $ids ) as $id ) {
			$type = $id ? get_post_type( $id ) : '';
			$id   = 'courses' === $type ? Target::copy_of( self::SOURCE, $id ) : ( PostType::COURSE === $type ? $id : 0 );

			if ( $id && PostType::COURSE === get_post_type( $id ) ) {
				$courses[] = $id;
			}
		}

		return array_values( array_unique( $courses ) );
	}

	/**
	 * IDs of the posts assigned to Tutor `course-category` terms (works while Tutor is inactive).
	 *
	 * @param int[] $term_ids Tutor term IDs.
	 * @return int[]
	 */
	private static function courses_in_tutor_categories( array $term_ids ): array {
		global $wpdb;

		$term_ids = array_values( array_filter( array_map( 'intval', $term_ids ) ) );

		if ( empty( $term_ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $term_ids ), '%d' ) );

		return array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT DISTINCT tr.object_id FROM {$wpdb->term_relationships} tr
					 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
					 WHERE tt.taxonomy = 'course-category' AND tt.term_id IN ({$placeholders})",
					$term_ids
				)
			)
		);
	}

	/**
	 * MasterStudy course category IDs matching Tutor `course-category` terms by name
	 * (Target::migrate_categories() creates them by name during the courses step).
	 *
	 * @param int[] $term_ids Tutor term IDs.
	 * @return int[]
	 */
	private static function ms_category_ids( array $term_ids ): array {
		global $wpdb;

		$ids = array();

		foreach ( array_filter( array_map( 'intval', $term_ids ) ) as $term_id ) {
			$name = (string) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT t.name FROM {$wpdb->terms} t
					 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
					 WHERE t.term_id = %d AND tt.taxonomy = 'course-category'",
					$term_id
				)
			);

			$term = '' !== $name ? term_exists( $name, Taxonomy::COURSE_CATEGORY ) : null;

			if ( $term ) {
				$ids[] = (int) ( is_array( $term ) ? $term['term_id'] : $term );
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Whether a database table exists.
	 *
	 * @param string $table Full table name.
	 */
	private static function table_exists( string $table ): bool {
		global $wpdb;

		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}

	/**
	 * Merge attachment IDs into the course `course_files` JSON meta.
	 *
	 * @param int   $course_id      MasterStudy course post ID (the copy).
	 * @param array $attachment_ids Attachment IDs.
	 */
	private static function set_course_files( int $course_id, array $attachment_ids ): void {
		$attachment_ids = array_values( array_filter( array_map( 'intval', $attachment_ids ) ) );

		if ( empty( $attachment_ids ) ) {
			return;
		}

		$current = json_decode( (string) get_post_meta( $course_id, 'course_files', true ), true );
		$current = is_array( $current ) ? array_map( 'intval', $current ) : array();

		update_post_meta( $course_id, 'course_files', wp_json_encode( array_values( array_unique( array_merge( $current, $attachment_ids ) ) ) ) );
	}

	/**
	 * Tutor course category images (`thumbnail_id` term meta) → MasterStudy category image (`course_image`),
	 * for the course categories and their parents. Existing MasterStudy images are kept.
	 *
	 * @param int $course_id Tutor course post ID (its Tutor terms are read; only MasterStudy term meta is written).
	 */
	private static function migrate_category_images( int $course_id ): void {
		global $wpdb;

		$term_ids = array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT tt.term_id FROM {$wpdb->term_taxonomy} tt
					 INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
					 WHERE tr.object_id = %d AND tt.taxonomy = 'course-category'",
					$course_id
				)
			)
		);

		$seen = array();

		while ( ! empty( $term_ids ) ) {
			$term_id = array_shift( $term_ids );

			if ( isset( $seen[ $term_id ] ) || count( $seen ) > 50 ) {
				continue;
			}

			$seen[ $term_id ] = true;

			$term = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT t.name, tt.parent FROM {$wpdb->terms} t
					 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
					 WHERE t.term_id = %d AND tt.taxonomy = 'course-category'",
					$term_id
				)
			);

			if ( ! $term ) {
				continue;
			}

			if ( (int) $term->parent ) {
				$term_ids[] = (int) $term->parent;
			}

			$image_id = (int) get_term_meta( $term_id, 'thumbnail_id', true );
			$ms_term  = $image_id && 'attachment' === get_post_type( $image_id ) ? term_exists( $term->name, Taxonomy::COURSE_CATEGORY ) : null;

			if ( ! $ms_term ) {
				continue;
			}

			$ms_term_id = (int) ( is_array( $ms_term ) ? $ms_term['term_id'] : $ms_term );

			if ( '' === (string) get_term_meta( $ms_term_id, 'course_image', true ) ) {
				update_term_meta( $ms_term_id, 'course_image', $image_id );
			}
		}
	}

	/**
	 * Tutor Pro "coming soon" course (`_tutor_course_enable_coming_soon` on a scheduled course) → MasterStudy
	 * Pro Plus upcoming course: the copy is published and shown as "coming soon" until the Tutor release date.
	 *
	 * @param int $course_id Tutor course post ID (read only).
	 * @param int $copy_id   MasterStudy course copy.
	 * @return string Report note ('' when carried over or not a coming soon course).
	 */
	private static function migrate_coming_soon( int $course_id, int $copy_id ): string {
		global $wpdb;

		$course = get_post( $course_id );

		if ( ! $course || ! get_post_meta( $course_id, '_tutor_course_enable_coming_soon', true ) ) {
			return '';
		}

		Target::store_unmigrated_meta(
			$copy_id,
			'tutor_coming_soon',
			array_filter(
				array(
					'thumbnail_id'       => (int) get_post_meta( $course_id, '_tutor_course_coming_soon_thumbnail_id', true ),
					'curriculum_preview' => (string) get_post_meta( $course_id, '_tutor_course_enable_curriculum_preview', true ),
				)
			)
		);

		// Tutor shows the "coming soon" card only for a course scheduled for a future date.
		$start = 'future' === $course->post_status && ! empty( $course->post_date_gmt ) && '0000-00-00 00:00:00' !== $course->post_date_gmt
			? (int) strtotime( $course->post_date_gmt . ' UTC' )
			: 0;

		if ( $start <= time() ) {
			return '';
		}

		if ( ! ProTarget::plus_active() ) {
			return __( '"coming soon" listing (requires MasterStudy LMS Pro Plus) — the course stays scheduled and is published on its release date', 'masterstudy-lms-learning-management-system' );
		}

		ProTarget::set_coming_soon(
			$copy_id,
			array(
				'start'        => $start,
				'show_price'   => true,
				'show_details' => (bool) get_post_meta( $course_id, '_tutor_course_enable_curriculum_preview', true ),
				'preordering'  => false,
			)
		);

		// A MasterStudy upcoming course is a published course with the coming soon flag and date.
		if ( 'publish' !== get_post_status( $copy_id ) ) {
			$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $copy_id ) );
			clean_post_cache( $copy_id );
		}

		return '';
	}

	/**
	 * Course intro video → MasterStudy Pro (Plus) course preview meta of the copy. Kept as unmigrated meta without Pro.
	 *
	 * @param int $course_id Tutor course post ID (read only).
	 * @param int $copy_id   MasterStudy course copy.
	 */
	private static function set_course_preview_video( int $course_id, int $copy_id ): void {
		$video = static::map_video_meta( $course_id );

		if ( empty( $video ) ) {
			return;
		}

		if ( ! ProTarget::pro_active() ) {
			Target::store_unmigrated_meta( $copy_id, 'tutor_course_video', $video );
			return;
		}

		switch ( $video['ms_source'] ) {
			case 'youtube':
				update_post_meta( $copy_id, 'video_type', 'youtube' );
				update_post_meta( $copy_id, 'youtube_url', esc_url_raw( $video['value'] ) );
				break;
			case 'vimeo':
				update_post_meta( $copy_id, 'video_type', 'vimeo' );
				update_post_meta( $copy_id, 'vimeo_url', esc_url_raw( $video['value'] ) );
				break;
			case 'embed':
				update_post_meta( $copy_id, 'video_type', 'embed' );
				update_post_meta( $copy_id, 'embed_ctx', $video['value'] );
				break;
			case 'html':
				update_post_meta( $copy_id, 'video_type', 'html' );
				update_post_meta( $copy_id, 'video', (int) $video['value'] );
				break;
			case 'shortcode':
				update_post_meta( $copy_id, 'video_type', 'shortcode' );
				update_post_meta( $copy_id, 'shortcode', $video['value'] );
				break;
			default:
				update_post_meta( $copy_id, 'video_type', 'ext_link' );
				update_post_meta( $copy_id, 'external_url', esc_url_raw( $video['value'] ) );
		}

		if ( $video['poster'] ) {
			update_post_meta( $copy_id, 'video_poster', $video['poster'] );
		}
	}

	/**
	 * Tutor Pro multi instructors (`_tutor_instructor_course_id` user meta) → MasterStudy
	 * `co_instructor` (single) of the copy. Extra co-instructors are kept as unmigrated meta.
	 *
	 * @param int $course_id Tutor course post ID (read only).
	 * @param int $copy_id   MasterStudy course copy.
	 * @return int Number of Tutor co-instructors (MasterStudy keeps the first one).
	 */
	private static function migrate_co_instructor( int $course_id, int $copy_id ): int {
		global $wpdb;

		$author = (int) get_post_field( 'post_author', $course_id );
		$ids    = array_values(
			array_diff(
				array_map(
					'intval',
					(array) $wpdb->get_col(
						$wpdb->prepare(
							"SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = '_tutor_instructor_course_id' AND meta_value = %s ORDER BY user_id ASC",
							(string) $course_id
						)
					)
				),
				array( $author )
			)
		);

		if ( empty( $ids ) ) {
			return 0;
		}

		if ( ProTarget::pro_active() ) {
			// Requests the Multi-instructors addon (deferred until the batch commits).
			ProTarget::set_co_instructor( $copy_id, $ids[0] );
		} elseif ( '' === (string) get_post_meta( $copy_id, 'co_instructor', true ) ) {
			update_post_meta( $copy_id, 'co_instructor', $ids[0] );
		}

		foreach ( $ids as $user_id ) {
			if ( static::instructor_allowed( $user_id ) ) {
				Target::make_instructor( $user_id );
			}
		}

		if ( count( $ids ) > 1 ) {
			Target::store_unmigrated_meta( $copy_id, 'tutor_co_instructors', $ids );
		}

		return count( $ids );
	}

	/**
	 * Copy the lesson comments of a Tutor lesson onto its MasterStudy copy (MasterStudy lists a lesson's comments as
	 * its discussion). Only regular comments are copied — never private notes or other Tutor comment types.
	 *
	 * @param int $source_lesson_id Tutor lesson post ID (read only).
	 * @param int $lesson_id        MasterStudy lesson copy.
	 */
	private static function copy_lesson_comments( int $source_lesson_id, int $lesson_id ): void {
		global $wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT comment_ID FROM {$wpdb->comments}
				 WHERE comment_post_ID = %d AND comment_type IN ( 'comment', '' )
				 ORDER BY comment_ID ASC",
				$source_lesson_id
			)
		);

		$parents = array();

		foreach ( (array) $ids as $id ) {
			$comment = get_comment( (int) $id );

			if ( $comment ) {
				$parents[ (int) $comment->comment_ID ] = static::copy_comment( $comment, $lesson_id, $parents[ (int) $comment->comment_parent ] ?? 0, (string) $comment->comment_approved );
			}
		}
	}

	/**
	 * Copy one comment onto a MasterStudy post as a regular comment (author, dates and content kept). Idempotent:
	 * the copy carries the source comment ID in its comment meta.
	 *
	 * @param \WP_Comment $comment  Source comment (never modified).
	 * @param int         $post_id  MasterStudy post (lesson copy).
	 * @param int         $parent   Parent comment copy (0 = top level).
	 * @param string      $approved comment_approved of the copy ('1', '0', 'spam', 'trash').
	 * @return int Comment copy ID.
	 */
	private static function copy_comment( \WP_Comment $comment, int $post_id, int $parent, string $approved ): int {
		global $wpdb;

		$key      = self::COMMENT_KEY_PREFIX . (int) $comment->comment_ID;
		$existing = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT c.comment_ID FROM {$wpdb->comments} c
				 INNER JOIN {$wpdb->commentmeta} s ON s.comment_id = c.comment_ID AND s.meta_key = %s AND s.meta_value = %s
				 INNER JOIN {$wpdb->commentmeta} i ON i.comment_id = c.comment_ID AND i.meta_key = %s AND i.meta_value = %s
				 WHERE c.comment_post_ID = %d LIMIT 1",
				Target::SOURCE_META,
				self::SOURCE,
				Target::SOURCE_ID_META,
				$key,
				$post_id
			)
		);

		if ( $existing ) {
			return $existing;
		}

		$copy_id = (int) wp_insert_comment(
			wp_slash(
				array(
					'comment_post_ID'      => $post_id,
					'comment_author'       => (string) $comment->comment_author,
					'comment_author_email' => (string) $comment->comment_author_email,
					'comment_author_url'   => (string) $comment->comment_author_url,
					'comment_author_IP'    => (string) $comment->comment_author_IP,
					'comment_date'         => (string) $comment->comment_date,
					'comment_date_gmt'     => (string) $comment->comment_date_gmt,
					'comment_content'      => (string) $comment->comment_content,
					'comment_karma'        => (int) $comment->comment_karma,
					'comment_approved'     => in_array( $approved, array( '1', '0', 'spam', 'trash' ), true ) ? $approved : '0',
					'comment_agent'        => (string) $comment->comment_agent,
					'comment_type'         => 'comment',
					'comment_parent'       => $parent,
					'user_id'              => (int) $comment->user_id,
				)
			)
		);

		if ( ! $copy_id ) {
			throw new \Exception( sprintf( 'Comment #%1$d could not be copied to post #%2$d.', (int) $comment->comment_ID, $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		add_comment_meta( $copy_id, Target::SOURCE_META, self::SOURCE, true );
		add_comment_meta( $copy_id, Target::SOURCE_ID_META, $key, true );

		return $copy_id;
	}

	/*
	|--------------------------------------------------------------------------
	| Migration report helpers
	|--------------------------------------------------------------------------
	*/

	/**
	 * Readable user reference for report titles: email, else "User #ID".
	 *
	 * @param int $user_id WordPress user ID.
	 */
	private static function user_label( int $user_id ): string {
		$user = $user_id ? get_userdata( $user_id ) : false;

		if ( $user && '' !== (string) $user->user_email ) {
			return (string) $user->user_email;
		}

		/* translators: %d: user ID */
		return sprintf( __( 'User #%d', 'masterstudy-lms-learning-management-system' ), $user_id );
	}

	/**
	 * Post a report item links to: the MasterStudy copy of a Tutor post when it exists, otherwise the Tutor post.
	 *
	 * @param int $post_id Tutor post ID (or 0).
	 */
	private static function report_post_id( int $post_id ): int {
		$copy_id = Target::copy_of( self::SOURCE, $post_id );

		return $copy_id ? $copy_id : $post_id;
	}

	/**
	 * Report label of a skipped Tutor enrollment status, e.g. "Pending enrollment".
	 *
	 * @param string $status tutor_enrolled post status.
	 */
	private static function enrollment_status_label( string $status ): string {
		$labels = array(
			'pending'   => __( 'Pending enrollment', 'masterstudy-lms-learning-management-system' ),
			'cancel'    => __( 'Cancelled enrollment', 'masterstudy-lms-learning-management-system' ),
			'cancelled' => __( 'Cancelled enrollment', 'masterstudy-lms-learning-management-system' ),
			'expired'   => __( 'Expired enrollment', 'masterstudy-lms-learning-management-system' ),
			'draft'     => __( 'Draft enrollment', 'masterstudy-lms-learning-management-system' ),
			'trash'     => __( 'Trashed enrollment', 'masterstudy-lms-learning-management-system' ),
		);

		/* translators: %s: Tutor LMS enrollment status */
		return $labels[ $status ] ?? sprintf( __( 'Enrollment (%s)', 'masterstudy-lms-learning-management-system' ), $status );
	}

	/**
	 * Human label of a Tutor question type slug.
	 *
	 * @param string $type Tutor question_type.
	 */
	private static function question_type_label( string $type ): string {
		$labels = array(
			'open_ended'       => __( 'Open ended', 'masterstudy-lms-learning-management-system' ),
			'short_answer'     => __( 'Short answer', 'masterstudy-lms-learning-management-system' ),
			'image_answering'  => __( 'Image answering', 'masterstudy-lms-learning-management-system' ),
			'h5p'              => __( 'H5P', 'masterstudy-lms-learning-management-system' ),
			'draw_image'       => __( 'Draw on image', 'masterstudy-lms-learning-management-system' ),
			'pin_image'        => __( 'Pin on image', 'masterstudy-lms-learning-management-system' ),
			'scale'            => __( 'Scale', 'masterstudy-lms-learning-management-system' ),
			'coordinates'      => __( 'Coordinates', 'masterstudy-lms-learning-management-system' ),
		);

		if ( isset( $labels[ $type ] ) ) {
			return $labels[ $type ];
		}

		return '' !== $type ? ucfirst( str_replace( array( '_', '-' ), ' ', $type ) ) : __( 'Unknown', 'masterstudy-lms-learning-management-system' );
	}

	/**
	 * Report reason for a Tutor question type that has no MasterStudy equivalent.
	 *
	 * @param string $type Tutor question_type.
	 */
	private static function unsupported_question_reason( string $type ): string {
		return sprintf(
			/* translators: %s: question type label */
			__( 'MasterStudy has no "%s" question type — the question was not imported and is missing from the quiz (it stays in Tutor LMS).', 'masterstudy-lms-learning-management-system' ),
			static::question_type_label( $type )
		);
	}

	/**
	 * Record a Tutor quiz question that was not (fully) imported.
	 *
	 * @param object $question  Tutor quiz question row.
	 * @param int    $quiz_id   Quiz post ID (the copy).
	 * @param int    $course_id Course post ID (the copy).
	 * @param int    $post_id   Post to link (question post, or the quiz copy when the question was not imported).
	 * @param string $reason    Why.
	 * @param string $status    Report::STATUS_*.
	 */
	private static function report_question( $question, int $quiz_id, int $course_id, int $post_id, string $reason, string $status ): void {
		$title = wp_strip_all_tags( (string) ( $question->question_title ?? '' ) );

		Report::add(
			Report::GROUP_QUESTIONS,
			array(
				'source_id' => (int) $question->question_id,
				/* translators: %d: Tutor question ID */
				'title'     => '' !== $title ? $title : sprintf( __( 'Question #%d', 'masterstudy-lms-learning-management-system' ), (int) $question->question_id ),
				'type'      => static::question_type_label( (string) $question->question_type ),
				'reason'    => $reason,
				'status'    => $status,
				/* translators: %s: quiz title */
				'parent'    => sprintf( __( 'Quiz: %s', 'masterstudy-lms-learning-management-system' ), get_post_field( 'post_title', $quiz_id ) ),
				'course'    => get_post_field( 'post_title', $course_id ),
				'post_id'   => $post_id,
			)
		);
	}

	/**
	 * "Topic: …" label of a Tutor curriculum item (pre-migration snapshot), or '' for course-level items.
	 *
	 * @param \WP_Post $item Tutor curriculum item.
	 */
	private static function curriculum_parent_label( \WP_Post $item ): string {
		$parent = $item->post_parent ? get_post( $item->post_parent ) : null;

		if ( ! $parent || 'topics' !== $parent->post_type ) {
			return '';
		}

		/* translators: %s: Tutor topic title */
		return sprintf( __( 'Topic: %s', 'masterstudy-lms-learning-management-system' ), $parent->post_title );
	}

	/**
	 * Record a Tutor curriculum item (assignment, meeting, unpublished item) that was not (fully) imported.
	 *
	 * @param string   $group     Report::GROUP_*.
	 * @param \WP_Post $item      Tutor curriculum item.
	 * @param int      $course_id Tutor course post ID (0 = resolve from the topic).
	 * @param string   $type      Source type label.
	 * @param string   $reason    Why.
	 * @param string   $status    Report::STATUS_*.
	 * @param int      $post_id   Post to link (0 = the item's copy when it exists, else the Tutor item).
	 */
	private static function report_curriculum_item( string $group, \WP_Post $item, int $course_id, string $type, string $reason, string $status, int $post_id = 0 ): void {
		if ( ! $course_id && $item->post_parent ) {
			$parent    = get_post( $item->post_parent );
			$course_id = $parent && 'topics' === $parent->post_type ? (int) $parent->post_parent : (int) $item->post_parent;
		}

		Report::add(
			$group,
			array(
				'source_id' => $item->ID,
				'title'     => $item->post_title,
				'type'      => $type,
				'reason'    => $reason,
				'status'    => $status,
				'parent'    => static::curriculum_parent_label( $item ),
				'course'    => $course_id ? get_post_field( 'post_title', $course_id ) : '',
				'post_id'   => $post_id ? $post_id : static::report_post_id( (int) $item->ID ),
			)
		);
	}

	/**
	 * Record a Tutor quiz attempt that was not (fully) imported.
	 *
	 * @param object $attempt   tutor_quiz_attempts row.
	 * @param string $type      Source type label.
	 * @param string $reason    Why.
	 * @param string $status    Report::STATUS_*.
	 * @param int    $course_id Resolved course ID (0 = the attempt's own course_id).
	 */
	private static function report_quiz_attempt( $attempt, string $type, string $reason, string $status, int $course_id = 0 ): void {
		$quiz_id   = (int) $attempt->quiz_id;
		$course_id = $course_id ? $course_id : (int) $attempt->course_id;

		Report::add(
			Report::GROUP_QUIZ_ATTEMPTS,
			array(
				'source_id' => (int) $attempt->attempt_id,
				/* translators: 1: attempt ID, 2: user email */
				'title'     => sprintf( __( 'Attempt #%1$d by %2$s', 'masterstudy-lms-learning-management-system' ), (int) $attempt->attempt_id, static::user_label( (int) $attempt->user_id ) ),
				'type'      => $type,
				'reason'    => $reason,
				'status'    => $status,
				/* translators: %s: quiz title */
				'parent'    => $quiz_id ? sprintf( __( 'Quiz: %s', 'masterstudy-lms-learning-management-system' ), get_post_field( 'post_title', $quiz_id ) ) : '',
				'course'    => $course_id ? get_post_field( 'post_title', $course_id ) : '',
				'post_id'   => static::report_post_id( $quiz_id ),
			)
		);
	}

	/**
	 * Record an order that could not be imported.
	 *
	 * @param int    $order_id  Source order ID.
	 * @param string $type      Source type label.
	 * @param string $reason    Why.
	 * @param int    $course_id Course post ID.
	 * @param int    $user_id   Buyer user ID, when known.
	 */
	private static function report_skipped_order( int $order_id, string $type, string $reason, int $course_id, int $user_id = 0 ): void {
		/* translators: %d: order ID */
		$title = sprintf( __( 'Order #%d', 'masterstudy-lms-learning-management-system' ), $order_id );

		if ( $user_id ) {
			$title .= ' — ' . static::user_label( $user_id );
		}

		Report::add(
			Report::GROUP_ORDERS,
			array(
				'source_id' => $order_id,
				'title'     => $title,
				'type'      => $type,
				'reason'    => $reason,
				'status'    => Report::STATUS_FAILED,
				'course'    => $course_id ? get_post_field( 'post_title', $course_id ) : '',
				'post_id'   => $course_id ? static::report_post_id( $course_id ) : 0,
			)
		);
	}

	/**
	 * Human-readable duration ("1h 30m", "45m 10s").
	 */
	private static function format_duration( int $hours, int $minutes, int $seconds ): string {
		$total   = $hours * HOUR_IN_SECONDS + $minutes * MINUTE_IN_SECONDS + $seconds;
		$hours   = intdiv( $total, HOUR_IN_SECONDS );
		$minutes = intdiv( $total % HOUR_IN_SECONDS, MINUTE_IN_SECONDS );
		$seconds = $total % MINUTE_IN_SECONDS;
		$parts   = array();

		if ( $hours ) {
			$parts[] = $hours . 'h';
		}
		if ( $minutes ) {
			$parts[] = $minutes . 'm';
		}
		if ( $seconds && ! $hours ) {
			$parts[] = $seconds . 's';
		}

		return implode( ' ', $parts );
	}

	/**
	 * Tutor stores benefits/requirements/audience as one item per line — render plain text as a list.
	 */
	private static function lines_to_list( string $text ): string {
		$text = trim( $text );

		if ( '' === $text || $text !== wp_strip_all_tags( $text ) ) {
			return $text;
		}

		$lines = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $text ) ) );

		if ( count( $lines ) < 2 ) {
			return $text;
		}

		return '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $lines ) ) . '</li></ul>';
	}

	/**
	 * Tutor's tutor_time() values are site-local timestamps (time() + gmt_offset); a few
	 * older records hold a datetime string. Returns a Unix timestamp, or 0.
	 *
	 * @param mixed $value Stored value.
	 */
	private static function tutor_time_to_unix( $value ): int {
		if ( is_numeric( $value ) ) {
			return (int) $value - (int) round( (float) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
		}

		return static::local_datetime_to_unix( (string) $value );
	}

	/**
	 * Site-local MySQL datetime → Unix timestamp (0 when empty/invalid).
	 */
	private static function local_datetime_to_unix( string $datetime ): int {
		if ( '' === $datetime || '0000-00-00 00:00:00' === $datetime ) {
			return 0;
		}

		$ts = strtotime( get_gmt_from_date( $datetime ) . ' UTC' );

		return $ts ? (int) $ts : 0;
	}
}
