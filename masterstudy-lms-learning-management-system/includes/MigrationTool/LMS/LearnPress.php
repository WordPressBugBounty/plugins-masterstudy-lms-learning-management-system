<?php
// phpcs:ignoreFile
/**
 * LearnPress migrations.
 *
 * @package MasterStudy\Lms\MigrationTool
 */

namespace MasterStudy\Lms\MigrationTool\LMS;

defined( 'ABSPATH' ) || exit;

use MasterStudy\Lms\MigrationTool\Helper;
use MasterStudy\Lms\MigrationTool\ProTarget;
use MasterStudy\Lms\MigrationTool\Report;
use MasterStudy\Lms\MigrationTool\Target;
use MasterStudy\Lms\Plugin\PostType;

/**
 * Class LearnPress.
 *
 * Copies LearnPress data into MasterStudy: course, lesson, quiz, question, assignment and order
 * posts get NEW MasterStudy posts (Target::copy_post(), mapped by Target::copy_of()); the LearnPress
 * posts, their meta, comments and the learnpress_* tables are only read and stay exactly as they were,
 * so LearnPress keeps working after the migration.
 */
class LearnPress {

	/**
	 * Source LMS slug stored on every copied post.
	 */
	const SOURCE = 'learnpress';

	/**
	 * LearnPress question type → MasterStudy question type.
	 */
	const QUESTION_TYPE_MAP = array(
		'true_or_false'  => 'true_false',
		'single_choice'  => 'single_choice',
		'multi_choice'   => 'multi_choice',
		'fill_in_blanks' => 'fill_the_gap',
		'sorting_choice' => 'sortable',
	);

	// -------------------------------------------------------------------------
	// Batch API — count / paginate / dispatch
	// -------------------------------------------------------------------------

	/**
	 * Return the total number of source items for a migration step.
	 *
	 * @param string $step Step name.
	 * @return int
	 */
	public static function count_source_items( string $step ): int {
		global $wpdb;

		switch ( $step ) {
			case 'users':
				$capabilities_key = $wpdb->get_blog_prefix() . 'capabilities';
				return (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM (
							SELECT DISTINCT u.ID
							FROM {$wpdb->users} u
							INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
							WHERE um.meta_key = %s AND um.meta_value LIKE %s
							UNION
							SELECT DISTINCT user_id FROM {$wpdb->prefix}learnpress_user_items
							WHERE item_type = 'lp_course'
							UNION
							SELECT DISTINCT user_id FROM {$wpdb->usermeta}
							WHERE meta_key IN ( '_lpr_wish_list', '_requested_become_teacher' )
						) AS lp_users",
						$capabilities_key,
						'%lp_teacher%'
					)
				);

			case 'courses':
				// Courses and the curriculum items that belong to no course (see course_step_sql()).
				return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ( ' . self::course_step_sql() . ' ) AS lp_items' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			case 'enrollments':
				return (int) $wpdb->get_var(
					"SELECT COUNT(*) FROM {$wpdb->prefix}learnpress_user_items WHERE item_type = 'lp_course'" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				);

			case 'lesson_progress':
				$types = self::progress_item_types();

				return (int) $wpdb->get_var(
					"SELECT COUNT(*) FROM {$wpdb->prefix}learnpress_user_items WHERE item_type IN ({$types})" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				);

			case 'orders':
				return (int) $wpdb->get_var(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'lp_order'" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				);

			case 'reviews':
				return (int) $wpdb->get_var(
					"SELECT COUNT(c.comment_ID)
					 FROM {$wpdb->comments} c
					 INNER JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID
					 WHERE p.post_type = 'lp_course'
					   AND c.comment_type IN ('review', 'course_rate', 'comment', '')" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				);

			case 'quiz_results':
				return (int) $wpdb->get_var(
					"SELECT COUNT(*) FROM {$wpdb->prefix}learnpress_user_item_results lur
					 INNER JOIN {$wpdb->prefix}learnpress_user_items lui
					     ON lur.user_item_id = lui.user_item_id
					 WHERE lui.item_type = 'lp_quiz'" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				);

		}

		return 0;
	}

	/**
	 * Return one batch of source IDs starting after $cursor.
	 *
	 * Copy mode: source records never disappear, so every step is cursor based
	 * (id > $cursor ORDER BY id); $exclude (failed items) is not needed.
	 *
	 * @param string $step    Step name.
	 * @param int    $limit   Batch size.
	 * @param int    $cursor  Last processed ID (0 = first batch).
	 * @param int[]  $exclude Failed IDs (unused: the cursor moves past them).
	 * @return int[]
	 */
	public static function get_source_ids( string $step, int $limit, int $cursor, array $exclude = array() ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		switch ( $step ) {
			case 'users':
				$capabilities_key = $wpdb->get_blog_prefix() . 'capabilities';
				$sql              = $wpdb->prepare(
					"SELECT user_id FROM (
						SELECT DISTINCT u.ID AS user_id
						FROM {$wpdb->users} u
						INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
						WHERE um.meta_key = %s AND um.meta_value LIKE %s
						UNION
						SELECT DISTINCT user_id FROM {$wpdb->prefix}learnpress_user_items
						WHERE item_type = 'lp_course'
						UNION
						SELECT DISTINCT user_id FROM {$wpdb->usermeta}
						WHERE meta_key IN ( '_lpr_wish_list', '_requested_become_teacher' )
					) AS lp_users
					WHERE user_id > %d
					ORDER BY user_id ASC
					LIMIT %d",
					$capabilities_key,
					'%lp_teacher%',
					$cursor,
					$limit
				);
				break;

			case 'courses':
				$sql = $wpdb->prepare( 'SELECT ID FROM ( ' . self::course_step_sql() . ' ) AS lp_items WHERE ID > %d ORDER BY ID ASC LIMIT %d', $cursor, $limit );
				break;

			case 'enrollments':
				$sql = $wpdb->prepare(
					"SELECT user_item_id FROM {$wpdb->prefix}learnpress_user_items
					 WHERE item_type = 'lp_course' AND user_item_id > %d
					 ORDER BY user_item_id ASC LIMIT %d",
					$cursor,
					$limit
				);
				break;

			case 'lesson_progress':
				$types = self::progress_item_types();
				$sql   = $wpdb->prepare(
					"SELECT user_item_id FROM {$wpdb->prefix}learnpress_user_items
					 WHERE item_type IN ({$types}) AND user_item_id > %d
					 ORDER BY user_item_id ASC LIMIT %d",
					$cursor,
					$limit
				);
				break;

			case 'orders':
				$sql = $wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'lp_order' AND ID > %d ORDER BY ID ASC LIMIT %d",
					$cursor,
					$limit
				);
				break;

			case 'reviews':
				$sql = $wpdb->prepare(
					"SELECT c.comment_ID
					 FROM {$wpdb->comments} c
					 INNER JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID
					 WHERE p.post_type = 'lp_course'
					   AND c.comment_type IN ('review', 'course_rate', 'comment', '')
					   AND c.comment_ID > %d
					 ORDER BY c.comment_ID ASC LIMIT %d",
					$cursor,
					$limit
				);
				break;

			case 'quiz_results':
				$sql = $wpdb->prepare(
					"SELECT lur.id
					 FROM {$wpdb->prefix}learnpress_user_item_results lur
					 INNER JOIN {$wpdb->prefix}learnpress_user_items lui
					     ON lur.user_item_id = lui.user_item_id
					 WHERE lui.item_type = 'lp_quiz' AND lur.id > %d
					 ORDER BY lur.id ASC LIMIT %d",
					$cursor,
					$limit
				);
				break;

			default:
				return array();
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$ids = $wpdb->get_col( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array_map( 'intval', $ids ? $ids : array() );
	}

	/**
	 * Dispatch a single-item migration to the appropriate migrate_single_*() method.
	 *
	 * Called by MigrationProcessJob inside a SAVEPOINT wrapper.
	 * Must be idempotent — safe to call twice for the same (step, item_id) pair.
	 *
	 * @param string $step    Step name.
	 * @param int    $item_id Source item ID.
	 * @throws \Exception Rolls the item back in the job engine; item is added to the failed list.
	 */
	public static function migrate_item( string $step, int $item_id ): void {
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
			case 'lesson_progress':
				static::migrate_single_lesson_progress( $item_id );
				break;
			case 'orders':
				static::migrate_single_order( $item_id );
				break;
			case 'reviews':
				static::migrate_single_review( $item_id );
				break;
			case 'quiz_results':
				static::migrate_single_quiz_result( $item_id );
				break;
		}
	}

	// -------------------------------------------------------------------------
	// Single-item migrate methods
	// -------------------------------------------------------------------------

	/**
	 * Assign MasterStudy roles to a single LearnPress user.
	 *
	 * lp_teacher → MasterStudy instructor; enrolled students keep (or receive) a login role.
	 *
	 * @param int $user_id WP user ID.
	 * @throws \Exception If the WP user record does not exist.
	 */
	public static function migrate_single_user( int $user_id ): void {
		global $wpdb;

		$user = get_userdata( $user_id );

		// learnpress_user_items keeps the rows of deleted accounts; their enrollments/progress are reported by later steps.
		if ( ! $user ) {
			Report::add(
				Report::GROUP_USERS,
				array(
					'source_id' => $user_id,
					'title'     => sprintf( 'User #%d', $user_id ),
					'type'      => 'Deleted user',
					'reason'    => 'LearnPress still has enrollment data for this WordPress account, but the account no longer exists, so no MasterStudy role could be assigned and its learning data cannot be imported.',
					'status'    => Report::STATUS_FAILED,
				)
			);
			return;
		}

		$roles = (array) $user->roles;
		$caps  = is_array( $user->caps ) ? $user->caps : array();

		if ( in_array( 'lp_teacher', $roles, true ) || isset( $caps['lp_teacher'] ) ) {
			// make_instructor() leaves administrators untouched. The LearnPress role is kept (LearnPress keeps working).
			Target::make_instructor( $user_id );
		} elseif ( 'yes' === get_user_meta( $user_id, '_requested_become_teacher', true ) ) {
			// LearnPress "Become a teacher" request awaiting approval → MasterStudy pending instructor application.
			Target::request_instructor( $user_id );
		}

		// MasterStudy students have no dedicated role (an enrollment row is enough) — only make sure
		// users without any role can log in.
		Target::ensure_student( $user_id );

		$lp_user_meta_table = $wpdb->prefix . 'learnpress_user_meta';
		if ( self::table_exists( $lp_user_meta_table ) ) {
			$bio = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT meta_value FROM {$lp_user_meta_table} WHERE user_id = %d AND meta_key = '_lp_profile_bio' LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$user_id
				)
			);

			if ( $bio ) {
				self::set_profile( $user_id, array( 'description' => $bio ) );
			}
		}

		self::migrate_user_profile( $user_id );
	}

	/**
	 * LearnPress profile extras: social links (`_lp_extra_info`), uploaded profile picture
	 * (`_lp_profile_picture`, path relative to the uploads dir) and the Wishlist add-on list (`_lpr_wish_list`,
	 * mapped to the course copies — the courses step runs first). The bio is the WordPress `description` field
	 * in LearnPress 4, which MasterStudy uses as well, so it needs no copy.
	 *
	 * @param int $user_id WP user ID.
	 */
	private static function migrate_user_profile( int $user_id ): void {
		$fields = array();
		$extra  = maybe_unserialize( get_user_meta( $user_id, '_lp_extra_info', true ) );

		if ( is_array( $extra ) ) {
			foreach ( array( 'facebook', 'twitter', 'linkedin', 'instagram' ) as $network ) {
				if ( ! empty( $extra[ $network ] ) && is_string( $extra[ $network ] ) ) {
					$fields[ $network ] = esc_url_raw( $extra[ $network ] );
				}
			}
		}

		$picture = get_user_meta( $user_id, '_lp_profile_picture', true );

		if ( is_string( $picture ) && '' !== trim( $picture ) ) {
			if ( preg_match( '#^https?://#i', $picture ) ) {
				$fields['avatar_url'] = $picture;
			} else {
				$uploads = wp_upload_dir( null, false );

				if ( file_exists( trailingslashit( $uploads['basedir'] ) . ltrim( $picture, '/' ) ) ) {
					$fields['avatar_url'] = trailingslashit( $uploads['baseurl'] ) . ltrim( $picture, '/' );
				}
			}
		}

		if ( ! empty( $fields ) ) {
			self::set_profile( $user_id, $fields );
		}

		$wishlist = self::id_list( maybe_unserialize( get_user_meta( $user_id, '_lpr_wish_list', true ) ) );

		if ( empty( $wishlist ) ) {
			return;
		}

		$copies = Target::copies_of( self::SOURCE, $wishlist );

		if ( ! empty( $copies ) ) {
			Target::add_to_wishlist( $user_id, $copies );
		}

		if ( count( $copies ) < count( $wishlist ) ) {
			Report::add(
				Report::GROUP_USERS,
				array(
					'source_id' => $user_id,
					'title'     => self::user_label( $user_id ),
					'type'      => 'Wishlist',
					'reason'    => sprintf( 'The wishlist lists %d course(s) that were not migrated to MasterStudy; they were left out of the MasterStudy wishlist.', count( $wishlist ) - count( $copies ) ),
					'status'    => Report::STATUS_PARTIAL,
				)
			);
		}
	}

	/**
	 * Target::set_profile() for the fields whose user meta row does not exist yet: copy mode never changes an
	 * existing user meta value (WordPress creates an empty `description` row for every user, which LearnPress 4 and
	 * MasterStudy both use as the biography), so a field that already has a row is reported instead.
	 *
	 * @param int   $user_id WP user ID.
	 * @param array $fields  Target::set_profile() fields.
	 */
	private static function set_profile( int $user_id, array $fields ): void {
		$meta_keys = array(
			'description' => 'description',
			'facebook'    => 'facebook',
			'twitter'     => 'twitter',
			'instagram'   => 'instagram',
			'linkedin'    => 'linkedin',
			'avatar_url'  => 'stm_lms_user_avatar',
		);
		$kept      = array();

		foreach ( $fields as $field => $value ) {
			$meta_key = $meta_keys[ $field ] ?? '';

			if ( '' === $meta_key || '' === (string) $value ) {
				continue;
			}

			if ( ! metadata_exists( 'user', $user_id, $meta_key ) ) {
				continue;
			}

			// An identical value is not a loss; a different one is.
			if ( (string) get_user_meta( $user_id, $meta_key, true ) !== (string) $value ) {
				$kept[] = $field;
			}

			unset( $fields[ $field ] );
		}

		if ( ! empty( $fields ) ) {
			Target::set_profile( $user_id, $fields );
		}

		if ( ! empty( $kept ) ) {
			Report::add(
				Report::GROUP_USERS,
				array(
					'source_id' => $user_id,
					'title'     => self::user_label( $user_id ),
					'type'      => 'Profile fields',
					'reason'    => sprintf( 'The user already has a value for %s (shared with LearnPress or set in MasterStudy), so the LearnPress value was not copied - existing user data is never changed.', implode( ', ', $kept ) ),
					'status'    => Report::STATUS_PARTIAL,
				)
			);
		}
	}

	/**
	 * Copy a single LearnPress course to a new MasterStudy stm-courses post.
	 *
	 * Copies lp_lesson → stm-lessons, lp_quiz → stm-quizzes, lp_question → stm-questions (and lp_assignment →
	 * stm-assignments with Pro), builds the curriculum sections/materials of the copy and writes the course meta.
	 * Items shared by several courses get one copy. Idempotent: copies are reused and the curriculum is rebuilt.
	 * Items that belong to no course come through the same step (see course_step_sql()).
	 *
	 * @param int $course_id LearnPress lp_course (or orphan item) post ID.
	 * @throws \Exception If the post does not exist or is not a LearnPress course/item.
	 */
	public static function migrate_single_course( int $course_id ): void {
		$post = get_post( $course_id );

		if ( ! $post ) {
			throw new \Exception(
				sprintf( 'LearnPress course #%d no longer exists.', $course_id ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		if ( in_array( $post->post_type, array( 'lp_lesson', 'lp_quiz', 'lp_question', 'lp_assignment' ), true ) ) {
			self::migrate_orphan_item( $post );
			return;
		}

		if ( 'lp_course' !== $post->post_type ) {
			throw new \Exception(
				sprintf( 'Post #%d "%s" is not a LearnPress course (post type "%s").', $course_id, $post->post_title, $post->post_type ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		// Course keeps its status; pending (awaiting review) becomes draft like the other migrators.
		$copy_id = Target::copy_post( $course_id, PostType::COURSE, self::SOURCE, 'pending' === $post->post_status ? array( 'post_status' => 'draft' ) : array() );
		$items   = self::process_course_curriculum( $course_id, $copy_id );

		self::update_course_meta( $course_id, $copy_id, $items );
	}

	/**
	 * Migrate a LearnPress enrollment to a MasterStudy stm_lms_user_courses row (in the course copy).
	 *
	 * LearnPress keeps one learnpress_user_items row (item_type = 'lp_course') per enrollment period: a course
	 * retake or repurchase adds a row and LearnPress itself uses the LATEST one. So the latest row of the user +
	 * course pair decides the status and the start date (older rows are skipped here), and the course counts as
	 * completed when ANY row was graduated "passed". The rows are only read.
	 * Idempotent: an existing MasterStudy enrollment is kept.
	 *
	 * @param int $user_item_id Primary key of the learnpress_user_items row.
	 * @throws \Exception If the row is not found.
	 */
	public static function migrate_single_enrollment( int $user_item_id ): void {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT user_item_id, user_id, item_id, status, start_time, end_time, ref_id, ref_type, graduation
				 FROM {$wpdb->prefix}learnpress_user_items
				 WHERE user_item_id = %d AND item_type = 'lp_course'",
				$user_item_id
			)
		);

		if ( ! $row ) {
			throw new \Exception(
				sprintf( 'LearnPress enrollment record #%d no longer exists.', $user_item_id ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		$user_id   = (int) $row->user_id;
		$course_id = (int) $row->item_id;

		// Every enrollment period of the pair, latest first.
		$periods = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_item_id, status, start_time, end_time, ref_id, ref_type, graduation
				 FROM {$wpdb->prefix}learnpress_user_items
				 WHERE user_id = %d AND item_id = %d AND item_type = 'lp_course'
				 ORDER BY user_item_id DESC",
				$user_id,
				$course_id
			)
		);

		// An older period: the latest row of the pair imports the enrollment.
		if ( ! empty( $periods ) && (int) $periods[0]->user_item_id !== $user_item_id ) {
			return;
		}

		$passed_period = null;

		foreach ( $periods as $period ) {
			if ( 'passed' === (string) $period->graduation ) {
				$passed_period = $period;
				break;
			}
		}

		$copy_id = Target::copy_of( self::SOURCE, $course_id );
		$report    = array(
			'source_id' => $user_item_id,
			'title'     => self::enrollment_title( $user_id, $course_id ),
			'course'    => self::post_label( $course_id ),
			'post_id'   => self::report_post_id( $course_id ),
		);

		// MasterStudy has no inactive enrollment status — an enrollment row always grants access,
		// so blocked / cancelled (order cancelled or refunded) / pending LearnPress enrollments are not migrated.
		if ( in_array( (string) $row->status, array( 'blocked', 'cancel', 'cancelled', 'pending' ), true ) ) {
			Helper::log( 'warning', sprintf( 'Migration: LearnPress enrollment of user %d in course %d is %s — not migrated.', $user_id, $course_id, (string) $row->status ) );
			Report::add(
				Report::GROUP_ENROLLMENTS,
				$report + array(
					'type'   => sprintf( 'Inactive enrollment (%s)', (string) $row->status ),
					'reason' => 'MasterStudy has no blocked/cancelled/pending enrollment status (an enrollment always grants access), so the inactive enrollment was not imported.',
					'status' => Report::STATUS_UNSUPPORTED,
				)
			);
			return;
		}

		// learnpress_user_items keeps the rows of deleted accounts.
		if ( ! get_userdata( $user_id ) ) {
			Report::add(
				Report::GROUP_ENROLLMENTS,
				$report + array(
					'type'   => sprintf( 'LearnPress enrollment (%s)', (string) $row->status ),
					'reason' => 'The student account no longer exists, so the enrollment could not be imported.',
					'status' => Report::STATUS_FAILED,
				)
			);
			return;
		}

		if ( ! $copy_id ) {
			Report::add(
				Report::GROUP_ENROLLMENTS,
				$report + array(
					'type'   => sprintf( 'LearnPress enrollment (%s)', (string) $row->status ),
					'reason' => get_post( $course_id ) ? 'The course was not migrated to MasterStudy (see the courses report), so the enrollment has nowhere to be recorded.' : sprintf( 'The course #%d no longer exists, so the enrollment could not be imported.', $course_id ),
					'status' => Report::STATUS_FAILED,
				)
			);
			return;
		}

		// A course finished with graduation "failed" was not passed: MasterStudy treats an end date as a completed
		// course (100%, certificate), so it keeps the real progress instead. A period passed earlier (before a
		// retake or repurchase) still counts as completed.
		$date_start   = self::parse_lp_timestamp( $row->start_time, self::order_time( (int) $row->ref_id, (string) $row->ref_type ) );
		$failed       = ! $passed_period && 'failed' === (string) $row->graduation;
		$is_completed = $passed_period || ( ! $failed && in_array( $row->status, array( 'completed', 'finished' ), true ) );
		$completed_at = $passed_period ? $passed_period : $row;
		$date_end     = $is_completed ? self::parse_lp_timestamp( $completed_at->end_time, self::parse_lp_timestamp( $completed_at->start_time, time() ) ) : null;

		// Target::enroll() keeps an existing enrollment (a second migration run); the report is written every run.
		try {
			Target::enroll( $user_id, $copy_id, $date_start, $date_end, $is_completed ? 100 : 0 );
		} catch ( \Exception $e ) {
			Helper::log(
				'error',
				sprintf( 'Migration: DB insert failed for enrollment user %d course %d: %s', $user_id, $course_id, $e->getMessage() )
			);
			Report::add(
				Report::GROUP_ENROLLMENTS,
				$report + array(
					'type'   => sprintf( 'LearnPress enrollment (%s)', (string) $row->status ),
					'reason' => sprintf( 'The MasterStudy enrollment could not be saved: %s', $e->getMessage() ),
					'status' => Report::STATUS_FAILED,
				)
			);
			return;
		}

		if ( $failed ) {
			Report::add(
				Report::GROUP_ENROLLMENTS,
				$report + array(
					'type'   => sprintf( 'Failed course (%s)', (string) $row->status ),
					'reason' => 'LearnPress graded the course as failed. MasterStudy has no failed-course state, so the enrollment was imported with its actual lesson/quiz progress and is not marked completed.',
					'status' => Report::STATUS_PARTIAL,
				)
			);
		}

		self::migrate_certificate_code( array_map( 'intval', wp_list_pluck( $periods, 'user_item_id' ) ), $user_id, $course_id, $copy_id );
	}

	/**
	 * Date of the LearnPress order an enrollment row references (the enrollment start when the row has none).
	 *
	 * @param int    $ref_id   learnpress_user_items.ref_id.
	 * @param string $ref_type learnpress_user_items.ref_type.
	 * @return int Unix time, now when the row references no existing order.
	 */
	private static function order_time( int $ref_id, string $ref_type ): int {
		$order = $ref_id && in_array( $ref_type, array( '', 'lp_order' ), true ) ? get_post( $ref_id ) : null;
		$time  = $order && 'lp_order' === $order->post_type ? (int) get_post_time( 'U', true, $order ) : 0;

		return $time > 0 ? $time : time();
	}

	/**
	 * Migrate a single LearnPress lesson or quiz progress row.
	 *
	 * Resolves the course copy through the MasterStudy curriculum tables (built during the courses
	 * step). A completed lesson becomes a stm_lms_user_lessons row of the lesson copy; quiz completion in
	 * MasterStudy is a passed stm_lms_user_quizzes attempt, which the quiz_results step (run before this one)
	 * already imported, so lp_quiz rows need nothing. With MasterStudy LMS Pro, lp_assignment rows (Assignments
	 * add-on) become student submissions. Course progress is recalculated once in finalize_step(). The source
	 * row is only read.
	 *
	 * @param int $user_item_id Primary key of the learnpress_user_items row.
	 * @throws \Exception If the row is not found.
	 */
	public static function migrate_single_lesson_progress( int $user_item_id ): void {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT user_id, item_id, item_type, status, graduation, start_time, end_time, ref_id
				 FROM {$wpdb->prefix}learnpress_user_items
				 WHERE user_item_id = %d AND item_type IN ('lp_lesson','lp_quiz','lp_assignment')",
				$user_item_id
			)
		);

		if ( ! $row ) {
			throw new \Exception(
				sprintf( 'LearnPress lesson/quiz progress record #%d no longer exists.', $user_item_id ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		$user_id   = (int) $row->user_id;
		$item_id   = (int) $row->item_id;
		$copy_id   = Target::copy_of( self::SOURCE, $item_id );
		$course_id = self::resolve_course_id( $item_id, (int) $row->ref_id );
		$report    = array(
			'source_id' => $user_item_id,
			'title'     => sprintf( '%s by %s', self::post_label( $item_id ), self::user_label( $user_id ) ),
			'type'      => self::progress_type_label( (string) $row->item_type ),
			'course'    => $course_id ? get_post_field( 'post_title', $course_id ) : ( $row->ref_id ? self::post_label( (int) $row->ref_id ) : '' ),
			'post_id'   => $copy_id ? $copy_id : ( get_post( $item_id ) ? $item_id : 0 ),
		);

		if ( ! get_userdata( $user_id ) ) {
			Report::add(
				'lp_assignment' === $row->item_type ? Report::GROUP_ASSIGNMENTS : Report::GROUP_PROGRESS,
				$report + array(
					'reason' => 'The student account no longer exists, so the progress could not be imported.',
					'status' => Report::STATUS_FAILED,
				)
			);
			return;
		}

		if ( ! $course_id ) {
			Helper::log(
				'warning',
				sprintf( 'Migration: %s %d (user %d) is not in any migrated course curriculum — skipping.', $row->item_type, $item_id, $user_id )
			);
			Report::add(
				Report::GROUP_PROGRESS,
				$report + array(
					'reason' => 'The item is not part of any migrated course curriculum, so its progress has nowhere to be recorded.',
					'status' => Report::STATUS_UNSUPPORTED,
				)
			);
			return;
		}

		if ( 'lp_assignment' === $row->item_type ) {
			self::migrate_assignment_submission( $user_item_id, $row, $course_id );
			return;
		}

		if ( 'lp_quiz' === $row->item_type ) {
			self::migrate_quiz_without_result( $user_item_id, $row, $course_id, $copy_id, $report );
			return;
		}

		$completed = in_array( $row->status, array( 'completed', 'passed' ), true );

		// MasterStudy only records completed lessons (a row = completed); "started" has no equivalent.
		if ( 'lp_lesson' === $row->item_type && $completed ) {
			$now = time();

			Target::complete_lesson(
				$user_id,
				$course_id,
				$copy_id,
				self::parse_lp_timestamp( $row->start_time, $now ),
				self::parse_lp_timestamp( $row->end_time, $now )
			);
		} elseif ( 'lp_lesson' === $row->item_type ) {
			Report::add(
				Report::GROUP_PROGRESS,
				array(
					'type'   => sprintf( 'Unfinished lesson (%s)', (string) $row->status ),
					'reason' => 'MasterStudy only records completed lessons, so a started but unfinished lesson was not imported.',
					'status' => Report::STATUS_UNSUPPORTED,
				) + $report
			);
		}
	}

	/**
	 * A quiz LearnPress marks completed but whose attempt has no learnpress_user_item_results row (the quiz_results
	 * step imports the attempts that have one): MasterStudy counts a quiz as done only through a passed attempt, so
	 * the attempt is recreated from the LearnPress graduation — passed with the quiz passing grade as score, failed
	 * with 0 — without answers, and reported. Without a graduation there is nothing to rebuild it from (reported).
	 *
	 * @param int    $user_item_id learnpress_user_items primary key (lp_quiz row).
	 * @param object $row          The row (user_id, item_id, status, graduation, start_time, end_time).
	 * @param int    $course_id    MasterStudy course copy ID.
	 * @param int    $copy_id      Quiz copy ID.
	 * @param array  $report       Base report item.
	 */
	private static function migrate_quiz_without_result( int $user_item_id, object $row, int $course_id, int $copy_id, array $report ): void {
		global $wpdb;

		if ( 'completed' !== (string) $row->status || ! $copy_id ) {
			return;
		}

		$has_result = self::table_exists( $wpdb->prefix . 'learnpress_user_item_results' ) && $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$wpdb->prefix}learnpress_user_item_results WHERE user_item_id = %d LIMIT 1",
				$user_item_id
			)
		);

		if ( $has_result ) {
			return;
		}

		$report['type'] = 'Quiz attempt without result';

		if ( ! in_array( (string) $row->graduation, array( 'passed', 'failed' ), true ) ) {
			Report::add(
				Report::GROUP_QUIZ_ATTEMPTS,
				$report + array(
					'reason' => 'LearnPress marks the quiz completed but kept neither a result nor a passed/failed grade for it, so there is no attempt to import.',
					'status' => Report::STATUS_UNSUPPORTED,
				)
			);
			return;
		}

		$user_id    = (int) $row->user_id;
		$passed     = 'passed' === (string) $row->graduation;
		$grade      = (float) get_post_meta( $copy_id, 'passing_grade', true );
		$percent    = $passed ? ( $grade > 0 ? $grade : 100.0 ) : 0.0;
		$ended_at   = self::parse_lp_timestamp( $row->end_time, self::parse_lp_timestamp( $row->start_time, time() ) );
		$created_at = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $ended_at ) );

		if ( ! Target::quiz_attempt_exists( $user_id, $copy_id, $created_at, (float) $percent, (bool) $passed ) ) {
			Target::add_quiz_attempt( $user_id, $course_id, $copy_id, $percent, $passed, $created_at );
		}

		Report::add(
			Report::GROUP_QUIZ_ATTEMPTS,
			$report + array(
				'reason' => sprintf(
					'LearnPress kept no result for this completed quiz (no score, no answers), so the attempt was imported as %s from its grade with a score of %s%%%s.',
					$passed ? 'passed' : 'failed',
					round( $percent, 2 ),
					$passed ? ' (the quiz passing grade)' : ''
				),
				'status' => Report::STATUS_PARTIAL,
			)
		);
	}

	/**
	 * Copy a single LearnPress order to a new MasterStudy stm-orders post with accurate status mapping
	 * (one MasterStudy order per user of a multi-user manual order).
	 *
	 * The lp_order post and its learnpress_order_items rows are only read. Idempotent: the copy (and the orders
	 * keyed by order + user) are reused and their order item rows are rebuilt (Target::save_order()).
	 *
	 * @param int $order_id LearnPress lp_order post ID.
	 * @throws \Exception If the post does not exist or is not an lp_order post.
	 */
	public static function migrate_single_order( int $order_id ): void {
		$post = get_post( $order_id );

		if ( ! $post ) {
			throw new \Exception(
				sprintf( 'LearnPress order #%d no longer exists.', $order_id ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		if ( 'lp_order' !== $post->post_type ) {
			throw new \Exception(
				sprintf( 'Post #%d is not a LearnPress order (post type "%s").', $order_id, $post->post_type ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		$status_map = array(
			'lp-pending'    => 'pending',
			'lp-checkout'   => 'pending',
			'lp-processing' => 'processing',
			'lp-on-hold'    => 'on-hold',
			'lp-completed'  => 'completed',
			'lp-cancelled'  => 'cancelled',
			'lp-refunded'   => 'refunded',
			'lp-failed'     => 'failed',
		);
		$lp_status  = 'trash' === $post->post_status ? (string) get_post_meta( $order_id, '_wp_trash_meta_status', true ) : $post->post_status;
		$ms_status  = $status_map[ $lp_status ] ?? 'pending';
		$order_time = (int) get_post_time( 'U', true, $post );
		$reports    = array();
		$items      = array();

		foreach ( self::get_lp_order_items( $order_id ) as $lp_order_item ) {
			if ( null === $lp_order_item->course_id ) {
				$reports[] = array(
					'title'  => sprintf( 'Order #%d: %s', $order_id, (string) $lp_order_item->name ),
					'type'   => sprintf( 'Order item (%s)', (string) $lp_order_item->item_type ),
					'reason' => 'The order line is not a course (e.g. a certificate or package purchase); MasterStudy orders only hold courses, so the line was dropped from the migrated order (the order total is kept).',
				);
				continue;
			}

			$reason = '';
			$item   = self::migrate_order_item( $lp_order_item, $reason );

			if ( $item ) {
				$items[] = $item;
				continue;
			}

			$reports[] = array(
				'title'  => sprintf( 'Order #%d: %s', $order_id, (string) $lp_order_item->name ),
				'type'   => 'Order item',
				'reason' => $reason,
			);
		}

		// Manual LearnPress orders can be assigned to several users; MasterStudy orders have one, so every buyer gets
		// an order. LearnPress may already have split such an order into per-user child orders (lp_order posts with
		// this order as post_parent), which are imported themselves: their users are left out here (no double count).
		$buyers  = self::order_buyers( $order_id );
		$covered = array_intersect( $buyers, self::child_order_buyers( $order_id ) );
		$owners  = empty( $buyers ) ? array( 0 ) : array_values( array_diff( $buyers, $covered ) );

		if ( empty( $owners ) ) {
			Report::add(
				Report::GROUP_ORDERS,
				array(
					'source_id' => $order_id,
					'title'     => sprintf( 'Order #%d', $order_id ),
					'type'      => 'Multi-user parent order',
					'reason'    => 'LearnPress split this multi-user order into one child order per user; the child orders were imported (one MasterStudy order per user), so the parent order is not imported a second time.',
					'status'    => Report::STATUS_PARTIAL,
					'post_id'   => $order_id,
				)
			);
			return;
		}

		foreach ( $owners as $index => $buyer ) {
			$unmigrated    = count( $buyers ) > 1 ? array( 'lp_user_ids' => $buyers ) : array();
			$owner_reports = 0 === $index ? $reports : array();
			$data          = self::migrate_order_meta( $order_id, (int) $buyer, $owner_reports, $unmigrated );

			// save_order() publishes the order: a trashed or unfinished (draft) LearnPress order keeps that post status.
			if ( in_array( $post->post_status, array( 'trash', 'draft', 'auto-draft' ), true ) ) {
				$data['post_status'] = 'trash' === $post->post_status ? 'trash' : 'draft';
			}

			// The first buyer gets the copy of the order post, every other buyer an order keyed by order + user.
			$data += 0 === $index ? array( 'order_id' => $order_id ) : array( 'source_id' => sprintf( 'order-%d-user-%d', $order_id, (int) $buyer ) );

			$copy_id = Target::save_order(
				array_merge(
					$data,
					array(
						'items'  => $items,
						'status' => $ms_status,
						'date'   => $order_time ? $order_time : time(),
					)
				),
				self::SOURCE
			);

			foreach ( $unmigrated as $key => $value ) {
				Target::store_unmigrated_meta( $copy_id, $key, $value );
			}

			foreach ( $owner_reports as $report ) {
				Report::add(
					Report::GROUP_ORDERS,
					$report + array(
						'source_id' => $order_id,
						'status'    => Report::STATUS_PARTIAL,
						'post_id'   => $copy_id,
					)
				);
			}
		}
	}

	/**
	 * Buyers of a LearnPress order (`_user_id`: one ID, or a list for manual multi-user orders).
	 *
	 * @param int $order_id LearnPress order post ID.
	 * @return int[] Unique user IDs (empty = guest order).
	 */
	private static function order_buyers( int $order_id ): array {
		$raw = maybe_unserialize( get_post_meta( $order_id, '_user_id', true ) );
		$ids = is_array( $raw ) ? $raw : array( $raw );

		return array_values(
			array_unique(
				array_filter(
					array_map(
						function ( $id ) {
							return is_scalar( $id ) ? (int) $id : 0;
						},
						$ids
					)
				)
			)
		);
	}

	/**
	 * Users of the child orders LearnPress created for a multi-user order.
	 *
	 * @param int $order_id LearnPress order post ID.
	 * @return int[]
	 */
	private static function child_order_buyers( int $order_id ): array {
		global $wpdb;

		$users = array();

		foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'lp_order' AND post_status <> 'auto-draft'", $order_id ) ) as $child_id ) {
			$users = array_merge( $users, self::order_buyers( (int) $child_id ) );
		}

		return array_values( array_unique( $users ) );
	}

	/**
	 * Copy a LearnPress review comment to a MasterStudy stm-reviews post of the course copy.
	 *
	 * The comment itself is never modified. Idempotent: add_review() reuses the review of the same comment.
	 *
	 * @param int $comment_id WP comment ID.
	 * @throws \Exception If the comment does not exist.
	 */
	public static function migrate_single_review( int $comment_id ): void {
		$comment = get_comment( $comment_id );

		if ( ! $comment ) {
			throw new \Exception(
				sprintf( 'LearnPress review comment #%d no longer exists.', $comment_id ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		$course_id = (int) $comment->comment_post_ID;
		$copy_id   = Target::copy_of( self::SOURCE, $course_id );
		$author    = '' !== $comment->comment_author ? $comment->comment_author : self::user_label( (int) $comment->user_id );
		$report    = array(
			'source_id' => $comment_id,
			'course'    => self::post_label( $course_id ),
			'post_id'   => self::report_post_id( $course_id ),
		);

		// Spam / trashed reviews must not become visible MasterStudy reviews.
		if ( in_array( (string) $comment->comment_approved, array( 'spam', 'trash', 'post-trashed' ), true ) ) {
			Report::add(
				Report::GROUP_REVIEWS,
				$report + array(
					'title'  => sprintf( 'Review by %s: %s', $author, wp_trim_words( $comment->comment_content, 12 ) ),
					'type'   => sprintf( 'Review marked %s', (string) $comment->comment_approved ),
					'reason' => 'Spam and trashed reviews are not imported (the comment stays in LearnPress).',
					'status' => Report::STATUS_UNSUPPORTED,
				)
			);
			return;
		}

		$rating = (float) get_comment_meta( $comment_id, '_lpr_rating', true );

		if ( $rating <= 0 ) {
			$rating = (float) get_comment_meta( $comment_id, 'rating', true );
		}

		if ( $rating <= 0 ) {
			$rating = (float) $comment->comment_karma;
		}

		if ( $rating <= 0 ) {
			// MasterStudy reviews require a 1-5 mark; an unrated course comment is not turned into a fake rating.
			Helper::log( 'info', sprintf( 'Migration: LearnPress course comment %d has no rating — not converted to a review.', $comment_id ) );
			Report::add(
				Report::GROUP_REVIEWS,
				$report + array(
					'title'  => sprintf( 'Comment by %s: %s', $author, wp_trim_words( $comment->comment_content, 12 ) ),
					'type'   => 'Unrated course comment',
					'reason' => 'MasterStudy reviews require a 1-5 star rating; the comment has none, so it was not turned into a review (the comment stays in LearnPress).',
					'status' => Report::STATUS_UNSUPPORTED,
				)
			);
			return;
		}

		if ( ! $copy_id ) {
			Report::add(
				Report::GROUP_REVIEWS,
				$report + array(
					'title'  => sprintf( 'Review by %s: %s', $author, wp_trim_words( $comment->comment_content, 12 ) ),
					'type'   => 'Course review',
					'reason' => 'The course was not migrated to MasterStudy (see the courses report), so the review has nowhere to be recorded.',
					'status' => Report::STATUS_FAILED,
				)
			);
			return;
		}

		$review_id = Target::add_review(
			array(
				'course_id' => $copy_id,
				'user_id'   => (int) $comment->user_id,
				'mark'      => $rating,
				'content'   => $comment->comment_content,
				'date'      => $comment->comment_date,
				// Reviews awaiting moderation in LearnPress stay pending in MasterStudy.
				'approved'  => '1' === (string) $comment->comment_approved,
				'source_id' => 'comment:' . $comment_id,
			),
			self::SOURCE
		);

		Target::store_unmigrated_meta( $review_id, 'lp_review_title', (string) get_comment_meta( $comment_id, '_lpr_review_title', true ) );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Build the curriculum sections of a course copy from the LearnPress curriculum, copying the lesson/quiz
	 * (and, with Pro, assignment) posts.
	 *
	 * Reads learnpress_sections / learnpress_section_items directly (the storage behind
	 * LP_Course::get_curriculum()) so it does not depend on the LearnPress API being loaded.
	 *
	 * @param int $course_id LearnPress course post ID.
	 * @param int $copy_id   MasterStudy course copy ID.
	 * @return array<int, int> Source item ID => copy ID of the items attached to the curriculum, in order.
	 */
	private static function process_course_curriculum( int $course_id, int $copy_id ): array {
		global $wpdb;

		$description = self::column_exists( $wpdb->prefix . 'learnpress_sections', 'section_description' ) ? 'section_description' : "''";
		$sections    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT section_id, section_name, {$description} AS section_description
				 FROM {$wpdb->prefix}learnpress_sections
				 WHERE section_course_id = %d
				 ORDER BY section_order ASC, section_id ASC",
				$course_id
			)
		);

		// Re-runs rebuild the curriculum of the copy from scratch.
		Target::reset_curriculum( $copy_id );

		$attached = array();

		if ( empty( $sections ) ) {
			return $attached;
		}

		$section_order = 0;
		$descriptions  = array();
		foreach ( $sections as $section ) {
			++$section_order;

			if ( '' !== trim( wp_strip_all_tags( (string) $section->section_description ) ) ) {
				$descriptions[ (string) $section->section_name ] = (string) $section->section_description;
			}

			$section_id = Target::add_section( $copy_id, (string) $section->section_name, $section_order );

			$items = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT item_id, item_type
					 FROM {$wpdb->prefix}learnpress_section_items
					 WHERE section_id = %d
					 ORDER BY item_order ASC, section_item_id ASC",
					(int) $section->section_id
				)
			);

			$item_order = 0;
			foreach ( (array) $items as $item ) {
				$item_id  = (int) $item->item_id;
				$raw_type = get_post_type( $item_id );

				if ( 'lp_quiz' === $raw_type ) {
					$item_copy = self::copy_quiz( $item_id, $course_id );
				} elseif ( 'lp_lesson' === $raw_type ) {
					$item_copy = self::copy_lesson( $item_id, $course_id );
				} elseif ( ProTarget::pro_active() && 'lp_assignment' === $raw_type ) {
					$item_copy = self::copy_assignment( $item_id );
				} else {
					// Other LearnPress item types (H5P, …, and assignments without Pro) have no MasterStudy equivalent.
					Helper::log(
						'warning',
						$raw_type
							? sprintf( 'Migration: course %d item %d of type "%s" is not supported — not copied.', $course_id, $item_id, $raw_type )
							: sprintf( 'Migration: course %d item %d ("%s") no longer exists — skipped.', $course_id, $item_id, (string) $item->item_type )
					);
					self::report_unsupported_curriculum_item( $item_id, $raw_type ? $raw_type : (string) $item->item_type, (bool) $raw_type, $course_id, (string) $section->section_name );
					continue;
				}

				// A trashed item is copied (the copy stays in the trash) but not shown in the curriculum.
				if ( 'trash' === get_post_status( $item_id ) ) {
					Report::add(
						Report::GROUP_LESSONS,
						array(
							'source_id' => $item_id,
							'title'     => self::post_label( $item_id ),
							'type'      => 'Trashed curriculum item',
							'reason'    => 'The item is in the trash, so it was copied (the copy is in the trash too) but not added to the MasterStudy curriculum (restore it and add it in the course builder if needed).',
							'status'    => Report::STATUS_PARTIAL,
							'parent'    => 'Section: ' . (string) $section->section_name,
							'course'    => self::post_label( $course_id ),
							'post_id'   => $item_copy,
						)
					);
					continue;
				}

				++$item_order;
				Target::add_material( $section_id, $item_copy, $item_order );
				$attached[ $item_id ] = $item_copy;
			}
		}

		if ( ! empty( $descriptions ) ) {
			Target::store_unmigrated_meta( $copy_id, 'lp_section_descriptions', $descriptions );
			self::report_course_partial(
				$course_id,
				'Section descriptions',
				sprintf(
					'MasterStudy curriculum sections have no description, so the descriptions of these sections were not imported (kept in _migrated_lp_section_descriptions): %s.',
					implode( ', ', array_keys( $descriptions ) )
				)
			);
		}

		return $attached;
	}

	/**
	 * Copy a LearnPress lesson (settings, preview, materials, comments). Idempotent (the copy is reused).
	 *
	 * @param int $lesson_id LearnPress lesson post ID.
	 * @param int $course_id LearnPress course post ID (0 = lesson outside any course), for the report.
	 * @return int Lesson copy ID.
	 */
	private static function copy_lesson( int $lesson_id, int $course_id ): int {
		$copy_id = Target::copy_post( $lesson_id, PostType::LESSON, self::SOURCE );
		$type    = (string) get_post_meta( $lesson_id, 'type', true );

		Target::set_lesson(
			$copy_id,
			array(
				'type'     => '' !== $type ? $type : 'text',
				'duration' => self::format_lp_duration( $lesson_id ),
			)
		);
		self::migrate_single_lesson_preview( $lesson_id, $copy_id );
		self::migrate_materials( $lesson_id, 'lp_lesson', $copy_id, $course_id );
		self::copy_lesson_comments( $lesson_id, $copy_id );

		return $copy_id;
	}

	/**
	 * Copy a LearnPress quiz with its questions and settings. Idempotent (copies are reused).
	 *
	 * @param int $quiz_id   LearnPress quiz post ID.
	 * @param int $course_id LearnPress course post ID (0 = quiz outside any course), for the report.
	 * @return int Quiz copy ID.
	 */
	private static function copy_quiz( int $quiz_id, int $course_id ): int {
		$copy_id = Target::copy_post( $quiz_id, PostType::QUIZ, self::SOURCE );

		self::migrate_quiz_questions( $quiz_id, $copy_id, $course_id );
		self::migrate_single_quiz_meta( $quiz_id, $copy_id, $course_id );

		return $copy_id;
	}

	/**
	 * Copy an Assignments add-on assignment (MasterStudy LMS Pro). The introduction LearnPress shows before the
	 * student starts becomes part of the task text of the copy. Idempotent (the copy is reused).
	 *
	 * @param int $assignment_id LearnPress assignment post ID.
	 * @return int Assignment copy ID.
	 */
	private static function copy_assignment( int $assignment_id ): int {
		$intro     = trim( (string) get_post_meta( $assignment_id, '_lp_introduction', true ) );
		$content   = (string) get_post_field( 'post_content', $assignment_id );
		$overrides = array();

		if ( '' !== $intro && false === strpos( $content, $intro ) ) {
			$overrides['post_content'] = $intro . "\n\n" . $content;
		}

		$copy_id = Target::copy_post( $assignment_id, PostType::ASSIGNMENT, self::SOURCE, $overrides );

		self::migrate_assignment_meta( $assignment_id, $copy_id );

		return $copy_id;
	}

	/**
	 * Copy the comments of a LearnPress lesson to its copy (MasterStudy lesson discussions), keeping threads and
	 * moderation status. Idempotent: every copied comment carries the source comment ID in its comment meta.
	 *
	 * @param int $lesson_id LearnPress lesson post ID.
	 * @param int $copy_id   Lesson copy ID.
	 */
	private static function copy_lesson_comments( int $lesson_id, int $copy_id ): void {
		global $wpdb;

		$comments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->comments} WHERE comment_post_ID = %d AND comment_type IN ( 'comment', '' ) ORDER BY comment_ID ASC",
				$lesson_id
			)
		);

		if ( empty( $comments ) ) {
			return;
		}

		$copied = array();

		foreach ( (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.meta_value, m.comment_id FROM {$wpdb->commentmeta} m
				 INNER JOIN {$wpdb->comments} c ON c.comment_ID = m.comment_id AND c.comment_post_ID = %d
				 WHERE m.meta_key = %s",
				$copy_id,
				Target::SOURCE_ID_META
			)
		) as $row ) {
			$copied[ (string) $row->meta_value ] = (int) $row->comment_id;
		}

		foreach ( $comments as $comment ) {
			$key = 'comment:' . (int) $comment->comment_ID;

			if ( isset( $copied[ $key ] ) ) {
				continue;
			}

			$status = (string) $comment->comment_approved;
			$parent = (int) $comment->comment_parent;
			$new_id = Target::add_discussion(
				array(
					'post_id'  => $copy_id,
					'user_id'  => (int) $comment->user_id,
					'content'  => $comment->comment_content,
					'date'     => $comment->comment_date,
					'date_gmt' => $comment->comment_date_gmt,
					'parent'   => $parent ? (int) ( $copied[ 'comment:' . $parent ] ?? 0 ) : 0,
					'approved' => '1' === $status,
					'status'   => 'post-trashed' === $status ? 'trash' : $status,
				)
			);

			if ( ! $new_id ) {
				continue;
			}

			add_comment_meta( $new_id, Target::SOURCE_META, self::SOURCE, true );
			add_comment_meta( $new_id, Target::SOURCE_ID_META, $key, true );
			$copied[ $key ] = $new_id;
		}
	}

	/**
	 * Copy the supported questions of a LearnPress quiz and set the question list of the quiz copy.
	 *
	 * @param int $quiz_id   LearnPress quiz post ID.
	 * @param int $copy_id   Quiz copy ID.
	 * @param int $course_id LearnPress course post ID (0 when none), for the report.
	 */
	private static function migrate_quiz_questions( int $quiz_id, int $copy_id, int $course_id ): void {
		global $wpdb;

		$questions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT question_id, questions.post_content, questions.post_author,
					questions.post_status, questions.post_title, questions.post_type,
					question_type_meta.meta_value as question_type,
					question_mark_meta.meta_value as question_mark
				FROM {$wpdb->prefix}learnpress_quiz_questions
				LEFT JOIN {$wpdb->posts} questions ON question_id = questions.ID
				LEFT JOIN {$wpdb->postmeta} question_type_meta
					ON question_id = question_type_meta.post_id AND question_type_meta.meta_key = '_lp_type'
				LEFT JOIN {$wpdb->postmeta} question_mark_meta
					ON question_id = question_mark_meta.post_id AND question_mark_meta.meta_key = '_lp_mark'
				WHERE quiz_id = %d
				ORDER BY question_order ASC, quiz_question_id ASC",
				$quiz_id
			)
		);

		$question_ids = array();
		$marks        = array();

		foreach ( (array) $questions as $question ) {
			$question_copy = self::copy_question( $question, $quiz_id, $course_id );

			if ( $question_copy ) {
				$question_ids[] = $question_copy;
				$marks[]        = '' !== (string) $question->question_mark ? (float) $question->question_mark : 1.0;
			}
		}

		// MasterStudy scores every question equally; LearnPress weights them by mark.
		if ( count( array_unique( array_map( 'strval', $marks ) ) ) > 1 ) {
			self::report_quiz_partial(
				$quiz_id,
				$course_id,
				'Question points',
				sprintf( 'The LearnPress questions carry different points (%s); MasterStudy scores every question equally, so attempt percentages may differ. The points are kept in _migrated_lp_mark question meta.', implode( ', ', $marks ) )
			);
		}

		Target::set_quiz_questions( $copy_id, $question_ids );
	}

	/**
	 * Copy one LearnPress question (row of migrate_quiz_questions() / migrate_orphan_item()) to an stm-questions post.
	 * Questions MasterStudy cannot use are reported and not copied.
	 *
	 * @param object $question  Row: question_id, post_content, post_author, post_status, post_title, post_type, question_type, question_mark.
	 * @param int    $quiz_id   LearnPress quiz post ID (0 for a question bank question).
	 * @param int    $course_id LearnPress course post ID (0 when none).
	 * @return int Question copy ID, 0 when the question cannot be used in a MasterStudy quiz.
	 */
	private static function copy_question( object $question, int $quiz_id, int $course_id ): int {
		$question_id = (int) $question->question_id;

		if ( ! $question->post_type ) {
			self::report_question(
				$question,
				$quiz_id,
				$course_id,
				'Missing question',
				'The quiz references a question post that no longer exists, so it could not be imported.',
				Report::STATUS_FAILED
			);
			return 0; // Orphan quiz_questions row.
		}

		$lp_type = (string) $question->question_type;
		$ms_type = self::QUESTION_TYPE_MAP[ $lp_type ] ?? null;
		$rows    = self::get_question_answer_rows( $question_id );

		if ( ! $ms_type ) {
			Helper::log(
				'warning',
				sprintf( 'Migration: question %d (quiz %d, course %d) has unsupported LearnPress type "%s" — not copied.', $question_id, $quiz_id, $course_id, $lp_type )
			);
			self::report_question(
				$question,
				$quiz_id,
				$course_id,
				self::question_type_label( $lp_type ),
				'MasterStudy has no equivalent of this LearnPress question type, so the question was not copied (it stays in LearnPress) and is not part of the MasterStudy quiz.',
				Report::STATUS_UNSUPPORTED
			);
			return 0;
		}

		$fib = 'fill_in_blanks' === $lp_type && ! empty( $rows ) ? self::fib_passage( $rows[0] ) : null;

		// fill_in_blanks without text (or without a single blank) cannot become a fill-the-gap question.
		if ( 'fill_in_blanks' === $lp_type && ( ! $fib || empty( $fib['ids'] ) ) ) {
			self::report_question(
				$question,
				$quiz_id,
				$course_id,
				self::question_type_label( $lp_type ),
				$fib
					? 'The fill-in-the-blanks question has no blank in its LearnPress text, so it was not copied (it stays in LearnPress) and is not part of the MasterStudy quiz.'
					: 'The fill-in-the-blanks question has no blank text in LearnPress, so it was not copied (it stays in LearnPress) and is not part of the MasterStudy quiz.',
				Report::STATUS_UNSUPPORTED
			);
			return 0;
		}

		$copy_id = Target::copy_post( $question_id, PostType::QUESTION, self::SOURCE );
		$extra   = array(
			'explanation' => (string) get_post_meta( $question_id, '_lp_explanation', true ),
			'hint'        => (string) get_post_meta( $question_id, '_lp_hint', true ),
		);

		Target::store_unmigrated_meta( $copy_id, 'lp_mark', (string) $question->question_mark );

		if ( $fib ) {
			Target::set_question( $copy_id, $ms_type, array( array( 'text' => $fib['text'] ) ), $extra );

			if ( ! empty( $fib['notes'] ) ) {
				Target::store_unmigrated_meta( $copy_id, 'lp_blanks', $fib['blanks'] );
				self::report_question(
					$question,
					$quiz_id,
					$course_id,
					self::question_type_label( $lp_type ),
					sprintf( 'The question was imported, but MasterStudy checks every gap against one answer, case-insensitively: %s. Review the question (the LearnPress blank settings are kept in _migrated_lp_blanks).', implode( '; ', $fib['notes'] ) ),
					Report::STATUS_PARTIAL,
					$copy_id
				);
			}

			return $copy_id;
		}

		if ( empty( $rows ) ) {
			self::report_question(
				$question,
				$quiz_id,
				$course_id,
				self::question_type_label( $lp_type ),
				'The question has no answer options in LearnPress, so it was added to the quiz without answers — review it in MasterStudy.',
				Report::STATUS_PARTIAL,
				$copy_id
			);
		}

		$answers = array();

		if ( 'true_false' === $ms_type ) {
			$true_value = self::true_answer_value( $rows );
			$correct    = false;

			foreach ( $rows as $row ) {
				if ( $row['value'] === $true_value ) {
					$correct = $row['correct'];
				}
			}

			$answers[] = array( 'correct' => $correct );
		} else {
			foreach ( $rows as $row ) {
				$answers[] = array(
					'text'    => $row['title'],
					'correct' => $row['correct'],
				);
			}
		}

		Target::set_question( $copy_id, $ms_type, $answers, $extra );
		return $copy_id;
	}

	/**
	 * Fetch the LearnPress answer rows of a question in display order.
	 *
	 * LearnPress 4 keeps title / value / is_true columns; a LearnPress 3 table that was never upgraded keeps them
	 * serialized in answer_data (text, value, is_true) with an answer_order column.
	 *
	 * @param int $question_id LearnPress question post ID.
	 * @return array<int, array{id: int, title: string, value: string, correct: bool}>
	 */
	private static function get_question_answer_rows( int $question_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'learnpress_question_answers';

		if ( ! self::column_exists( $table, 'title' ) && self::column_exists( $table, 'answer_data' ) ) {
			$rows = (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT question_answer_id, answer_data FROM {$table} WHERE question_id = %d ORDER BY answer_order ASC, question_answer_id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$question_id
				),
				ARRAY_A
			);

			foreach ( $rows as $i => $row ) {
				$data       = maybe_unserialize( $row['answer_data'] );
				$data       = is_array( $data ) ? $data : array();
				$rows[ $i ] = array(
					'question_answer_id' => $row['question_answer_id'],
					'title'              => $data['text'] ?? '',
					'value'              => $data['value'] ?? '',
					'is_true'            => $data['is_true'] ?? '',
				);
			}
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT question_answer_id, title, value, is_true FROM {$table} WHERE question_id = %d ORDER BY `order` ASC, question_answer_id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$question_id
				),
				ARRAY_A
			);
		}

		$answers = array();
		foreach ( (array) $rows as $row ) {
			$answers[] = array(
				'id'      => (int) $row['question_answer_id'],
				'title'   => (string) $row['title'],
				'value'   => (string) $row['value'],
				'correct' => 'yes' === $row['is_true'],
			);
		}

		return $answers;
	}

	/**
	 * LearnPress fill-in-the-blanks passage → MasterStudy fill-the-gap text (every blank becomes |answer|).
	 *
	 * LearnPress 4 keeps the passage in the answer title with one [fib fill="…" id="…"] shortcode per blank (single
	 * or double quotes, any attribute order, HTML-encoded values) and the blank settings in the `_blanks` answer meta
	 * (id => fill, comparison equal|any|range, match_case). LearnPress grades against `_blanks`, so its fill wins over
	 * the shortcode attribute. Older passages use {{blank}} placeholders filled in `_blanks` order.
	 *
	 * @param array $row Answer row from get_question_answer_rows().
	 * @return array{text: string, ids: string[], notes: string[], blanks: array} ids: blank IDs in passage order
	 *               (empty = no blank); notes: blank settings MasterStudy cannot reproduce.
	 */
	private static function fib_passage( array $row ): array {
		$blanks = self::fib_blanks( (int) ( $row['id'] ?? 0 ) );
		$title  = (string) $row['title'];
		$ids    = array();
		$notes  = array();

		$gap = function ( array $blank, $fill ) use ( &$notes ): string {
			$fill       = trim( wp_strip_all_tags( html_entity_decode( (string) $fill, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
			$comparison = strtolower( (string) ( $blank['comparison'] ?? '' ) );

			if ( 'any' === $comparison && false !== strpos( $fill, ',' ) ) {
				$options = array_values( array_filter( array_map( 'trim', explode( ',', $fill ) ), 'strlen' ) );
				$notes[] = sprintf( 'the blank "%s" accepts any of these answers, the gap only the first one', $fill );
				$fill    = (string) ( $options[0] ?? $fill );
			} elseif ( 'range' === $comparison ) {
				$notes[] = sprintf( 'the blank "%s" accepts a number range, the gap expects this exact text', $fill );
			}

			if ( in_array( strtolower( (string) ( $blank['match_case'] ?? '' ) ), array( '1', 'yes', 'true', 'on' ), true ) ) {
				$notes[] = sprintf( 'the blank "%s" is case-sensitive', $fill );
			}

			if ( '' === $fill ) {
				$notes[] = 'a blank has no answer';
			}

			// A pipe would end the gap early.
			return '|' . str_replace( '|', '/', $fill ) . '|';
		};

		if ( preg_match( '/\[fib\b/i', $title ) ) {
			$text = preg_replace_callback(
				'/\[fib\b([^\]]*)\]/i',
				function ( $matches ) use ( $blanks, &$ids, $gap ) {
					$atts  = shortcode_parse_atts( trim( rtrim( trim( $matches[1] ), '/' ) ) );
					$atts  = is_array( $atts ) ? $atts : array();
					$id    = (string) ( $atts['id'] ?? '' );
					$blank = '' !== $id && isset( $blanks[ $id ] ) ? $blanks[ $id ] : array();
					$fill  = '' !== trim( (string) ( $blank['fill'] ?? '' ) ) ? $blank['fill'] : ( $atts['fill'] ?? '' );
					$ids[] = '' !== $id ? $id : (string) count( $ids );

					return $gap( $blank, $fill );
				},
				$title
			);
		} elseif ( false !== strpos( $title, '{{blank}}' ) ) {
			$list = array_values( $blanks );
			$text = preg_replace_callback(
				'/\{\{blank\}\}/',
				function () use ( $list, &$ids, $gap ) {
					$blank = $list[ count( $ids ) ] ?? array();
					$ids[] = (string) ( $blank['id'] ?? count( $ids ) );

					return $gap( $blank, $blank['fill'] ?? '' );
				},
				$title
			);
		} else {
			$text = $title;
		}

		return array(
			'text'   => is_string( $text ) ? $text : $title,
			'ids'    => $ids,
			'notes'  => array_values( array_unique( $notes ) ),
			'blanks' => $blanks,
		);
	}

	/**
	 * `_blanks` meta of a LearnPress fill-in-the-blanks answer (learnpress_question_answermeta), keyed by blank ID.
	 *
	 * @param int $answer_id learnpress_question_answers.question_answer_id.
	 * @return array<string, array> Blank ID => blank settings (fill, comparison, match_case…).
	 */
	private static function fib_blanks( int $answer_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'learnpress_question_answermeta';

		if ( ! $answer_id || ! self::table_exists( $table ) ) {
			return array();
		}

		$raw = maybe_unserialize(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT meta_value FROM {$table} WHERE learnpress_question_answer_id = %d AND meta_key = '_blanks' ORDER BY meta_id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$answer_id
				)
			)
		);

		if ( is_string( $raw ) && '' !== $raw ) {
			$raw = json_decode( $raw, true );
		}

		$blanks = array();

		foreach ( is_array( $raw ) ? $raw : array() as $key => $blank ) {
			if ( ! is_array( $blank ) ) {
				continue;
			}

			$id = isset( $blank['id'] ) && '' !== (string) $blank['id'] ? (string) $blank['id'] : ( is_int( $key ) ? 'blank-' . count( $blanks ) : (string) $key );

			$blanks[ $id ] = array( 'id' => $id ) + $blank;
		}

		return $blanks;
	}

	/**
	 * Value of the LearnPress true/false answer row that represents "True"
	 * (the row titled "True", otherwise the first row — LearnPress creates True first).
	 *
	 * @param array $rows Rows from get_question_answer_rows().
	 */
	private static function true_answer_value( array $rows ): string {
		foreach ( $rows as $row ) {
			if ( 'true' === strtolower( trim( $row['title'] ) ) ) {
				return $row['value'];
			}
		}

		return isset( $rows[0] ) ? $rows[0]['value'] : '';
	}

	/**
	 * Write all course-level data of a LearnPress course to its MasterStudy copy (source meta is only read).
	 *
	 * @param int             $course_id LearnPress course post ID.
	 * @param int             $copy_id   MasterStudy course copy ID.
	 * @param array<int, int> $items     Curriculum items attached to the copy: source item ID => copy ID.
	 */
	private static function update_course_meta( int $course_id, int $copy_id, array $items ): void {
		// LearnPress 4 keeps the regular price in _lp_regular_price (_lp_price is the computed current price);
		// LearnPress 3 data only has _lp_price, and upgraded courses can keep an empty _lp_regular_price next to it.
		$_lp_price       = floatval( get_post_meta( $course_id, '_lp_regular_price', true ) );
		$_lp_price       = $_lp_price > 0 ? $_lp_price : floatval( get_post_meta( $course_id, '_lp_price', true ) );
		$_lp_sale_price  = floatval( get_post_meta( $course_id, '_lp_sale_price', true ) );
		$_lp_sale_start  = get_post_meta( $course_id, '_lp_sale_start', true );
		$_lp_sale_end    = get_post_meta( $course_id, '_lp_sale_end', true );
		$_lp_level       = (string) get_post_meta( $course_id, '_lp_level', true );

		Target::set_pricing( $copy_id, $_lp_price, $_lp_sale_price > 0 ? $_lp_sale_price : null );

		if ( $_lp_price > 0 && $_lp_sale_price > 0 && $_lp_sale_price < $_lp_price ) {
			self::set_sale_dates( $copy_id, $_lp_sale_start, $_lp_sale_end );
		}

		$info = array(
			'duration_info' => self::format_lp_duration( $course_id ),
		);

		$lists = array(
			'basic_info'        => '_lp_key_features',
			'requirements'      => '_lp_requirements',
			'intended_audience' => '_lp_target_audiences',
		);

		foreach ( $lists as $info_key => $meta_key ) {
			$list = self::html_list( self::list_items( get_post_meta( $course_id, $meta_key, true ) ) );

			if ( '' !== $list ) {
				$info[ $info_key ] = $list;
			}
		}

		if ( metadata_exists( 'post', $course_id, '_lp_featured' ) ) {
			$info['featured'] = 'yes' === get_post_meta( $course_id, '_lp_featured', true );
		}

		// LearnPress can block access once the course duration has passed since enrollment.
		if ( 'yes' === get_post_meta( $course_id, '_lp_block_expire_duration', true ) ) {
			$minutes = self::parse_lp_duration( $course_id );

			if ( $minutes > 0 ) {
				$info['end_time'] = (int) ceil( $minutes / 1440 );
			}
		}

		Target::set_course_info( $copy_id, $info );

		// Buy button linking to an external page → MasterStudy affiliate course (LearnPress only shows it on paid courses).
		$external = trim( (string) get_post_meta( $course_id, '_lp_external_link_buy_course', true ) );

		if ( '' !== $external && $_lp_price > 0 ) {
			Target::set_affiliate( $copy_id, $external, '', $_lp_sale_price > 0 && $_lp_sale_price < $_lp_price ? $_lp_sale_price : $_lp_price );
		}

		self::report_course_settings( $course_id, $copy_id, '' !== $external && $_lp_price <= 0 );

		Target::set_level( $copy_id, 'all' === strtolower( $_lp_level ) ? 'all_levels' : $_lp_level );
		Target::migrate_categories( $copy_id, 'course_category', array(), $course_id );
		Target::migrate_category_hierarchy( $course_id, 'course_category' );
		self::report_course_tags( $course_id, $copy_id );

		$author = (int) get_post_field( 'post_author', $course_id );
		if ( $author ) {
			Target::make_instructor( $author );
		}

		self::migrate_single_course_faqs( $course_id, $copy_id );
		self::migrate_materials( $course_id, 'lp_course', $copy_id, $course_id );

		// LearnPress add-ons: certificates, content drip, prerequisites (set once every course is copied, see
		// finalize_step()), co-instructors, coming soon, announcements.
		self::migrate_course_certificate( $course_id, $copy_id );
		self::migrate_content_drip( $course_id, $copy_id, $items );
		self::report_prerequisites_without_pro( $course_id );
		self::migrate_co_instructors( $course_id, $copy_id );
		self::migrate_coming_soon( $course_id, $copy_id );
		self::migrate_announcements( $course_id, $copy_id );
	}

	/**
	 * Report (and keep in _migrated_lp_* meta of the copy) the LearnPress course settings MasterStudy has no equivalent for.
	 *
	 * @param int  $course_id        LearnPress course post ID.
	 * @param int  $copy_id          Course copy ID.
	 * @param bool $unused_external  Whether an external buy link was set on a free course (not imported).
	 */
	private static function report_course_settings( int $course_id, int $copy_id, bool $unused_external ): void {
		global $wpdb;

		$max_students = (int) get_post_meta( $course_id, '_lp_max_students', true );
		$retakes      = absint( get_post_meta( $course_id, '_lp_retake_count', true ) );
		$fake         = (int) get_post_meta( $course_id, '_lp_students', true );
		$evaluation   = (string) get_post_meta( $course_id, '_lp_course_result', true );
		$dropped      = array();

		$checks = array(
			'lp_max_students'             => array( $max_students > 0, sprintf( 'enrollment limit (%d students)', $max_students ) ),
			'lp_no_required_enroll'       => array( 'yes' === get_post_meta( $course_id, '_lp_no_required_enroll', true ), 'open access without enrollment' ),
			'lp_retake_count'             => array( $retakes > 0, sprintf( 'course retakes (%d)', $retakes ) ),
			'lp_students'                 => array( $fake > 0, sprintf( 'extra students added to the displayed count (%d)', $fake ) ),
			'lp_course_result'            => array(
				'' !== $evaluation && 'evaluate_lesson' !== $evaluation,
				sprintf( 'course result evaluation "%s" (passing condition %s%%) — MasterStudy completes a course when all its items are done', $evaluation, (string) get_post_meta( $course_id, '_lp_passing_condition', true ) ),
			),
			'lp_block_finished'           => array( 'yes' === get_post_meta( $course_id, '_lp_block_finished', true ), 'blocking the content after the course is finished' ),
			'lp_allow_course_repurchase'  => array(
				'yes' === get_post_meta( $course_id, '_lp_allow_course_repurchase', true ),
				sprintf( 'course repurchase (%s)', (string) get_post_meta( $course_id, '_lp_course_repurchase_option', true ) ),
			),
			'lp_offline_course'           => array(
				'yes' === get_post_meta( $course_id, '_lp_offline_course', true ),
				sprintf( 'offline course (%s, %s)', (string) get_post_meta( $course_id, '_lp_deliver_type', true ), (string) get_post_meta( $course_id, '_lp_address', true ) ),
			),
			'lp_price_prefix'             => array( '' !== trim( (string) get_post_meta( $course_id, '_lp_price_prefix', true ) ), 'price prefix text' ),
			'lp_price_suffix'             => array( '' !== trim( (string) get_post_meta( $course_id, '_lp_price_suffix', true ) ), 'price suffix text' ),
			'lp_featured_review'          => array( '' !== trim( (string) get_post_meta( $course_id, '_lp_featured_review', true ) ), 'featured review text' ),
			'lp_course_forum'             => array( (int) get_post_meta( $course_id, '_lp_course_forum', true ) > 0, sprintf( 'linked bbPress forum #%d (the forum stays in bbPress)', (int) get_post_meta( $course_id, '_lp_course_forum', true ) ) ),
			'lp_external_link_buy_course' => array( $unused_external, 'external buy link on a free course' ),
		);

		foreach ( $checks as $key => $check ) {
			if ( ! $check[0] ) {
				continue;
			}

			$dropped[] = $check[1];
			Target::store_unmigrated_meta( $copy_id, $key, get_post_meta( $course_id, '_' . $key, true ) );
		}

		// Paid Memberships Pro add-on: membership-level access is configured in PMPro, not on the MasterStudy course.
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s AND meta_value NOT IN ( '', 'a:0:{}' ) LIMIT 1", $course_id, $wpdb->esc_like( '_lp_pmpro' ) . '%' ) ) ) {
			$dropped[] = 'Paid Memberships Pro level access (set up MasterStudy membership access in PMPro again)';
		}

		if ( ! empty( $dropped ) ) {
			self::report_course_partial(
				$course_id,
				'Course settings',
				sprintf( 'MasterStudy has no equivalent for: %s. The course was imported without them (values kept in _migrated_lp_* meta).', implode( '; ', $dropped ) )
			);
		}
	}

	/**
	 * Course tags (course_tag): MasterStudy has no course tag taxonomy.
	 *
	 * @param int $course_id LearnPress course post ID.
	 * @param int $copy_id   Course copy ID.
	 */
	private static function report_course_tags( int $course_id, int $copy_id ): void {
		global $wpdb;

		$tags = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT t.name FROM {$wpdb->terms} t
				 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id AND tt.taxonomy = 'course_tag'
				 INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
				 WHERE tr.object_id = %d ORDER BY t.name ASC",
				$course_id
			)
		);

		if ( empty( $tags ) ) {
			return;
		}

		Target::store_unmigrated_meta( $copy_id, 'lp_course_tags', $tags );
		self::report_course_partial(
			$course_id,
			'Course tags',
			sprintf( 'MasterStudy has no course tag taxonomy, so these tags were not assigned: %s (kept in _migrated_lp_course_tags).', implode( ', ', $tags ) )
		);
	}

	/**
	 * Coming Soon Courses add-on → MasterStudy upcoming course (Plus addon "coming_soon").
	 *
	 * @param int $course_id LearnPress course post ID.
	 * @param int $copy_id   Course copy ID.
	 */
	private static function migrate_coming_soon( int $course_id, int $copy_id ): void {
		if ( 'yes' !== get_post_meta( $course_id, '_lp_coming_soon', true ) ) {
			return;
		}

		$raw   = get_post_meta( $course_id, '_lp_coming_soon_end_time', true );
		$start = is_numeric( $raw ) ? (int) $raw : 0;

		if ( ! $start && is_string( $raw ) && '' !== trim( $raw ) ) {
			$date  = date_create( trim( $raw ), wp_timezone() );
			$start = $date ? $date->getTimestamp() : 0;
		}

		// The coming-soon period is over: LearnPress already shows the course as available.
		if ( $start && $start <= time() ) {
			return;
		}

		if ( ! ProTarget::plus_active() ) {
			self::report_requires_pro( $course_id, 'Coming soon', 'The LearnPress "coming soon" state (Coming Soon Courses add-on)', 'MasterStudy LMS Pro Plus (Upcoming Courses addon)' );
			return;
		}

		ProTarget::set_coming_soon(
			$copy_id,
			array(
				'start'        => $start,
				'show_details' => 'yes' === get_post_meta( $course_id, '_lp_coming_soon_details', true ),
				'show_price'   => true,
				'preordering'  => false,
			)
		);

		$message = trim( wp_strip_all_tags( (string) get_post_meta( $course_id, '_lp_coming_soon_msg', true ) ) );

		if ( '' !== $message || 'yes' === get_post_meta( $course_id, '_lp_coming_soon_countdown', true ) ) {
			Target::store_unmigrated_meta( $copy_id, 'lp_coming_soon_msg', $message );
			self::report_course_partial(
				$course_id,
				'Coming soon',
				'The course was imported as an upcoming course; the custom coming-soon message and countdown display settings have no MasterStudy equivalent (the message is kept in _migrated_lp_coming_soon_msg).'
			);
		}
	}

	/**
	 * Announcements add-on (lp_announcements posts linked to courses by _lp_course_announcement) → the
	 * MasterStudy course announcement. Only published announcements are imported (MasterStudy shows
	 * the announcement to every student); the others are reported.
	 *
	 * @param int $course_id LearnPress course post ID.
	 * @param int $copy_id   Course copy ID.
	 */
	private static function migrate_announcements( int $course_id, int $copy_id ): void {
		global $wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_lp_course_announcement'
				 WHERE p.post_type = 'lp_announcements' AND p.post_status <> 'auto-draft'
				   AND ( m.meta_value = %s OR m.meta_value LIKE %s )
				 ORDER BY p.post_date ASC, p.ID ASC",
				(string) $course_id,
				'%' . $wpdb->esc_like( '"' . $course_id . '"' ) . '%'
			)
		);

		foreach ( array_map( 'intval', (array) $ids ) as $announcement_id ) {
			// The LIKE may match a longer serialized value; confirm the course is really listed.
			if ( ! in_array( $course_id, self::id_list( get_post_meta( $announcement_id, '_lp_course_announcement', false ) ), true ) ) {
				continue;
			}

			$announcement = get_post( $announcement_id );

			if ( 'publish' === $announcement->post_status ) {
				Target::add_announcement( $copy_id, $announcement->post_title, $announcement->post_content );
				continue;
			}

			Report::add(
				Report::GROUP_OTHER,
				array(
					'source_id' => $announcement_id,
					'title'     => $announcement->post_title,
					'type'      => sprintf( 'Announcement (%s)', $announcement->post_status ),
					'reason'    => 'Only published announcements are imported: the MasterStudy course announcement is shown to every student.',
					'status'    => Report::STATUS_UNSUPPORTED,
					'course'    => self::post_label( $course_id ),
					'post_id'   => $announcement_id,
				)
			);
		}
	}

	/**
	 * Get LearnPress order items.
	 *
	 * @param int $order_id Order post ID.
	 * @return array Order item objects.
	 */
	private static function get_lp_order_items( int $order_id ): array {
		global $wpdb;

		// LearnPress 4.1+ also stores the purchased item in item_id / item_type columns (courses keep the
		// _course_id item meta); other item types (e.g. certificates) are not courses. The upgrade from older
		// versions adds both columns as NULL-able and leaves them NULL on existing lines: those are courses.
		if ( self::column_exists( $wpdb->prefix . 'learnpress_order_items', 'item_id' ) ) {
			return (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT oi.order_item_id as id, oi.order_item_name as name, COALESCE( oi.item_type, '' ) as item_type,
						IF( COALESCE( oi.item_type, '' ) IN ( '', 'lp_course' ), COALESCE( NULLIF( oim.meta_value, '' ), NULLIF( oi.item_id, 0 ), 0 ), NULL ) as course_id
					FROM {$wpdb->prefix}learnpress_order_items oi
					LEFT JOIN {$wpdb->prefix}learnpress_order_itemmeta oim
						ON oi.order_item_id = oim.learnpress_order_item_id AND oim.meta_key = '_course_id'
					WHERE oi.order_id = %d
					ORDER BY oi.order_item_id ASC",
					$order_id
				)
			);
		}

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT oi.order_item_id as id, oi.order_item_name as name, 'lp_course' as item_type,
					oim.meta_value as course_id
				FROM {$wpdb->prefix}learnpress_order_items oi
				INNER JOIN {$wpdb->prefix}learnpress_order_itemmeta oim
					ON oi.order_item_id = oim.learnpress_order_item_id AND oim.meta_key = '_course_id'
				WHERE oi.order_id = %d
				ORDER BY oi.order_item_id ASC",
				$order_id
			)
		);
	}

	/**
	 * Map one LP order item to a MasterStudy order item (course copy).
	 *
	 * @param object $lp_item LP order item object (id, name, course_id).
	 * @param string $reason  Receives why the line was dropped.
	 * @return array|null ['course_id' => course copy ID, 'price' => float], or null when the course is gone or not copied.
	 */
	private static function migrate_order_item( object $lp_item, string &$reason = '' ): ?array {
		global $wpdb;

		$lp_metas = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM {$wpdb->prefix}learnpress_order_itemmeta WHERE learnpress_order_item_id = %d",
				$lp_item->id
			)
		);

		$meta_map = array();
		foreach ( (array) $lp_metas as $m ) {
			$meta_map[ $m->meta_key ] = $m->meta_value;
		}

		$course_id = (int) ( $meta_map['_course_id'] ?? $lp_item->course_id );

		if ( ! $course_id || ! get_post( $course_id ) ) {
			Helper::log( 'warning', sprintf( 'Migration: order item %d (%s) references missing course %d — skipped.', (int) $lp_item->id, (string) $lp_item->name, $course_id ) );
			$reason = sprintf( 'The order item references course #%d, which no longer exists, so the line was dropped from the migrated order.', $course_id );
			return null;
		}

		$copy_id = Target::copy_of( self::SOURCE, $course_id );

		if ( ! $copy_id ) {
			$reason = sprintf( 'The order item references course "%s", which was not migrated to MasterStudy, so the line was dropped from the migrated order.', self::post_label( $course_id ) );
			return null;
		}

		// _total / _subtotal are line totals: MasterStudy order items hold one course at its unit price.
		$price    = isset( $meta_map['_total'] ) && '' !== $meta_map['_total'] ? $meta_map['_total'] : ( $meta_map['_subtotal'] ?? 0 );
		$quantity = max( 1, (int) ( $meta_map['_quantity'] ?? 1 ) );

		return array(
			'course_id' => $copy_id,
			'price'     => round( (float) $price / $quantity, 2 ),
		);
	}

	/**
	 * Read LP order postmeta into Target::save_order() data.
	 *
	 * @param int   $order_id    Order post ID.
	 * @param int   $customer_id Buyer of this MasterStudy order (0 = guest, see order_buyers()).
	 * @param array $reports     Receives report items (title, type, reason) added once the copy exists.
	 * @param array $unmigrated  Receives _migrated_* meta for the copy (key => value).
	 * @return array
	 */
	private static function migrate_order_meta( int $order_id, int $customer_id, array &$reports, array &$unmigrated ): array {
		// Guest checkout: LearnPress keeps the buyer e-mail in _checkout_email; use the matching account if any.
		if ( ! (int) $customer_id ) {
			$email = sanitize_email( (string) get_post_meta( $order_id, '_checkout_email', true ) );
			$user  = '' !== $email ? get_user_by( 'email', $email ) : false;

			if ( $user ) {
				$customer_id = $user->ID;
			} elseif ( '' !== $email ) {
				$unmigrated['lp_checkout_email'] = $email;
				$reports[]                       = array(
					'title'  => sprintf( 'Order #%d', $order_id ),
					'type'   => 'Guest order',
					'reason' => sprintf( 'The order was placed as a guest (%s) and no WordPress account uses that e-mail, so the migrated order has no user.', $email ),
				);
			}
		}

		if ( (int) $customer_id && ! get_userdata( (int) $customer_id ) ) {
			$reports[] = array(
				'title'  => sprintf( 'Order #%d', $order_id ),
				'type'   => 'Order of a deleted user',
				'reason' => sprintf( 'The order belongs to user #%d, whose account no longer exists; it was imported with that user ID for the sales history.', (int) $customer_id ),
			);
		}

		$data = array(
			'user_id'        => (int) $customer_id,
			'total'          => (float) get_post_meta( $order_id, '_order_total', true ),
			'subtotal'       => (float) get_post_meta( $order_id, '_order_subtotal', true ),
			'currency'       => (string) get_post_meta( $order_id, '_order_currency', true ),
			'payment_code'   => (string) get_post_meta( $order_id, '_payment_method', true ),
			'transaction_id' => (string) get_post_meta( $order_id, '_transaction_id', true ),
		);

		if ( '' === $data['payment_code'] ) {
			unset( $data['payment_code'] );
		}

		foreach ( array( 'total' => '_order_total', 'subtotal' => '_order_subtotal' ) as $key => $meta_key ) {
			if ( ! metadata_exists( 'post', $order_id, $meta_key ) ) {
				unset( $data[ $key ] );
			}
		}

		// The LearnPress-only keys (_user_ip_address, _user_agent, _checkout_email, _order_version…) stay on the
		// LearnPress order, which is kept.
		return $data;
	}

	/**
	 * Store sale date limits (MasterStudy expects millisecond timestamps).
	 *
	 * @param int   $course_id Course ID.
	 * @param mixed $start     LearnPress _lp_sale_start (datetime string or Unix time).
	 * @param mixed $end       LearnPress _lp_sale_end (datetime string or Unix time).
	 */
	private static function set_sale_dates( int $course_id, $start, $end ): void {
		foreach ( array( 'sale_price_dates_start' => $start, 'sale_price_dates_end' => $end ) as $key => $value ) {
			if ( empty( $value ) ) {
				continue;
			}

			$ts = is_numeric( $value ) ? (int) $value : strtotime( (string) $value );

			if ( $ts ) {
				update_post_meta( $course_id, $key, $ts * 1000 );
			}
		}
	}

	/**
	 * Resolve the MasterStudy course copy of a LearnPress lesson/quiz/assignment from the curriculum tables,
	 * preferring the copy of the course referenced by the LearnPress user item.
	 *
	 * @param int $item_id LearnPress lesson/quiz/assignment post ID.
	 * @param int $ref_id  learnpress_user_items.ref_id (the LearnPress course for lesson/quiz rows).
	 * @return int Course copy ID, 0 when the item copy is in no MasterStudy curriculum.
	 */
	private static function resolve_course_id( int $item_id, int $ref_id ): int {
		$item_copy  = Target::copy_of( self::SOURCE, $item_id );
		$course_ids = $item_copy ? Target::course_ids_of( $item_copy ) : array();

		if ( empty( $course_ids ) ) {
			return 0;
		}

		$ref_copy = Target::copy_of( self::SOURCE, $ref_id );

		return $ref_copy && in_array( $ref_copy, $course_ids, true ) ? $ref_copy : (int) $course_ids[0];
	}

	/**
	 * Convert a LearnPress DATETIME string (GMT) to a Unix timestamp.
	 *
	 * Returns $fallback if the value is empty, NULL, or the MySQL zero datetime
	 * ('0000-00-00 00:00:00'), which LP uses as "not set".
	 *
	 * @param string|null $value    Raw value from the learnpress_user_items table.
	 * @param int         $fallback Value to return when $value is absent or zero.
	 * @return int
	 */
	private static function parse_lp_timestamp( $value, int $fallback ): int {
		if ( ! $value || '0000-00-00 00:00:00' === $value ) {
			return $fallback;
		}
		$ts = strtotime( $value . ' UTC' );
		return $ts ? $ts : $fallback;
	}

	/**
	 * Convert the _lp_duration meta string to minutes.
	 *
	 * @param int $post_id Post ID with _lp_duration meta.
	 * @return int Duration in minutes, 0 if not parseable.
	 */
	private static function parse_lp_duration( int $post_id ): int {
		$parts = explode( ' ', (string) get_post_meta( $post_id, '_lp_duration', true ) );

		if ( count( $parts ) < 2 ) {
			return 0;
		}

		$n    = absint( $parts[0] );
		$unit = strtolower( $parts[1] );

		switch ( $unit ) {
			case 'minute':
			case 'minutes':
				return $n;
			case 'hour':
			case 'hours':
				return $n * 60;
			case 'day':
			case 'days':
				return $n * 1440;
			case 'week':
			case 'weeks':
				return $n * 10080;
		}

		return 0;
	}

	/**
	 * Human-readable _lp_duration ("3 hours") for MasterStudy's free-text duration fields.
	 *
	 * @param int $post_id Post ID with _lp_duration meta.
	 * @return string Empty when not set or zero.
	 */
	private static function format_lp_duration( int $post_id ): string {
		$parts = explode( ' ', trim( (string) get_post_meta( $post_id, '_lp_duration', true ) ) );
		$n     = absint( $parts[0] ?? 0 );

		if ( ! $n ) {
			return '';
		}

		switch ( rtrim( strtolower( $parts[1] ?? 'minute' ), 's' ) ) {
			case 'hour':
				/* translators: %d: number of hours */
				return sprintf( _n( '%d hour', '%d hours', $n, 'masterstudy-lms-learning-management-system' ), $n );
			case 'day':
				/* translators: %d: number of days */
				return sprintf( _n( '%d day', '%d days', $n, 'masterstudy-lms-learning-management-system' ), $n );
			case 'week':
				/* translators: %d: number of weeks */
				return sprintf( _n( '%d week', '%d weeks', $n, 'masterstudy-lms-learning-management-system' ), $n );
			default:
				/* translators: %d: number of minutes */
				return sprintf( _n( '%d minute', '%d minutes', $n, 'masterstudy-lms-learning-management-system' ), $n );
		}
	}

	/**
	 * Build a <ul> from a LearnPress list meta (key features, requirements, audiences).
	 *
	 * @param array $items List items.
	 */
	private static function html_list( array $items ): string {
		$html = '';

		foreach ( $items as $item ) {
			$item = is_array( $item ) ? implode( ' ', $item ) : (string) $item;

			if ( '' !== trim( $item ) ) {
				$html .= '<li>' . wp_kses_post( $item ) . '</li>';
			}
		}

		return '' !== $html ? '<ul>' . $html . '</ul>' : '';
	}

	/**
	 * Items of a LearnPress list meta (key features, requirements, audiences): LearnPress 4 saves an array, older
	 * data and imports a plain string with one item per line.
	 *
	 * @param mixed $value Meta value.
	 * @return array
	 */
	private static function list_items( $value ): array {
		$value = maybe_unserialize( $value );

		if ( is_array( $value ) ) {
			return $value;
		}

		if ( ! is_scalar( $value ) || '' === trim( (string) $value ) ) {
			return array();
		}

		return preg_split( '/\r\n|\r|\n/', trim( (string) $value ) );
	}

	/**
	 * Record a quiz question that was not (fully) imported.
	 *
	 * @param object $question  Row from migrate_quiz_questions().
	 * @param int    $quiz_id   Quiz post ID.
	 * @param int    $course_id Course post ID.
	 * @param string $type      Source type label.
	 * @param string $reason    Why it was not imported.
	 * @param string $status    Report::STATUS_* constant.
	 * @param int    $copy_id   Question copy ID (0 = not copied: the report links the LearnPress question).
	 */
	private static function report_question( object $question, int $quiz_id, int $course_id, string $type, string $reason, string $status, int $copy_id = 0 ): void {
		$question_id = (int) $question->question_id;
		$title       = trim( (string) $question->post_title );

		if ( '' === $title ) {
			$title = '' !== trim( wp_strip_all_tags( (string) $question->post_content ) )
				? wp_trim_words( (string) $question->post_content, 15 )
				: sprintf( 'Question #%d', $question_id );
		}

		Report::add(
			Report::GROUP_QUESTIONS,
			array(
				'source_id' => $question_id,
				'title'     => $title,
				'type'      => $type,
				'reason'    => $reason,
				'status'    => $status,
				'parent'    => $quiz_id ? 'Quiz: ' . self::post_label( $quiz_id ) : '',
				'course'    => $course_id ? self::post_label( $course_id ) : '',
				'post_id'   => $copy_id ? $copy_id : ( $question->post_type ? $question_id : 0 ),
			)
		);
	}

	/**
	 * Record a quiz setting that MasterStudy imports only in part.
	 *
	 * @param int    $quiz_id   LearnPress quiz post ID.
	 * @param int    $course_id LearnPress course post ID (0 when none).
	 * @param string $type      Report type label.
	 * @param string $reason    Reason.
	 */
	private static function report_quiz_partial( int $quiz_id, int $course_id, string $type, string $reason ): void {
		Report::add(
			Report::GROUP_QUIZZES,
			array(
				'source_id' => $quiz_id,
				'title'     => self::post_label( $quiz_id ),
				'type'      => $type,
				'reason'    => $reason,
				'status'    => Report::STATUS_PARTIAL,
				'course'    => $course_id ? self::post_label( $course_id ) : '',
				'post_id'   => self::report_post_id( $quiz_id ),
			)
		);
	}

	/**
	 * Post to link in the report: the MasterStudy copy when there is one, otherwise the LearnPress post.
	 *
	 * @param int $post_id LearnPress post ID.
	 */
	private static function report_post_id( int $post_id ): int {
		$copy_id = Target::copy_of( self::SOURCE, $post_id );

		if ( $copy_id ) {
			return $copy_id;
		}

		return $post_id && get_post( $post_id ) ? $post_id : 0;
	}

	/**
	 * SQL selecting the IDs the courses step iterates: every LearnPress course, plus the lessons, quizzes,
	 * questions (and assignments with MasterStudy LMS Pro) that no LearnPress course uses — items outside every
	 * course curriculum and question-bank questions outside every quiz. Auto-drafts are ignored.
	 */
	private static function course_step_sql(): string {
		global $wpdb;

		$item_types = ProTarget::pro_active() ? "'lp_lesson','lp_quiz','lp_assignment'" : "'lp_lesson','lp_quiz'";

		return "SELECT p.ID FROM {$wpdb->posts} p
			WHERE p.post_type = 'lp_course'
			  OR ( p.post_status <> 'auto-draft'
			    AND (
			      ( p.post_type IN ({$item_types}) AND " . self::outside_courses_sql( 'p.ID' ) . " )
			      OR ( p.post_type = 'lp_question'
			        AND NOT EXISTS (
			          SELECT 1 FROM {$wpdb->prefix}learnpress_quiz_questions qq
			          INNER JOIN {$wpdb->posts} q ON q.ID = qq.quiz_id AND q.post_type = 'lp_quiz'
			          WHERE qq.question_id = p.ID
			        ) )
			    ) )";
	}

	/**
	 * SQL condition: the item is in no LearnPress curriculum section of an existing LearnPress course.
	 *
	 * @param string $column Item ID column.
	 */
	private static function outside_courses_sql( string $column ): string {
		global $wpdb;

		return "NOT EXISTS (
			SELECT 1 FROM {$wpdb->prefix}learnpress_section_items si
			INNER JOIN {$wpdb->prefix}learnpress_sections s ON s.section_id = si.section_id
			INNER JOIN {$wpdb->posts} c ON c.ID = s.section_course_id AND c.post_type = 'lp_course'
			WHERE si.item_id = {$column}
		)";
	}

	/**
	 * Without MasterStudy LMS Pro, assignments that belong to no course are not copied: report them
	 * (curriculum assignments are reported by the courses step).
	 */
	private static function report_orphan_assignments(): void {
		global $wpdb;

		if ( ProTarget::pro_active() ) {
			return;
		}

		$ids = $wpdb->get_col(
			"SELECT p.ID FROM {$wpdb->posts} p WHERE p.post_type = 'lp_assignment' AND p.post_status NOT IN ('auto-draft','trash') AND " . self::outside_courses_sql( 'p.ID' ) . ' ORDER BY p.ID ASC' // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		foreach ( array_map( 'intval', (array) $ids ) as $assignment_id ) {
			Report::add(
				Report::GROUP_ASSIGNMENTS,
				array(
					'source_id' => $assignment_id,
					'title'     => self::post_label( $assignment_id ),
					'type'      => 'Assignment outside any course',
					'reason'    => 'LearnPress assignments require MasterStudy LMS Pro (Assignments addon) — the assignment (not part of any course) and its submissions stay in LearnPress and were not copied.',
					'status'    => Report::STATUS_UNSUPPORTED,
					'post_id'   => $assignment_id,
				)
			);
		}
	}

	/**
	 * A lesson, quiz, question or assignment that belongs to no LearnPress course: copied (status kept) so it is
	 * available in the MasterStudy library, and reported because it is in no curriculum.
	 *
	 * @param \WP_Post $post Source post.
	 */
	private static function migrate_orphan_item( \WP_Post $post ): void {
		global $wpdb;

		$item_id = (int) $post->ID;
		$reason  = 'The item is not part of any LearnPress course, so it was copied (status kept) and is available in the MasterStudy library, but it is not attached to any course curriculum.';

		switch ( $post->post_type ) {
			case 'lp_lesson':
				$copy_id = self::copy_lesson( $item_id, 0 );
				$group   = Report::GROUP_LESSONS;
				$label   = 'Lesson outside any course';
				break;

			case 'lp_quiz':
				$copy_id = self::copy_quiz( $item_id, 0 );
				$group   = Report::GROUP_QUIZZES;
				$label   = 'Quiz outside any course';
				break;

			case 'lp_assignment':
				$copy_id = self::copy_assignment( $item_id );
				$group   = Report::GROUP_ASSIGNMENTS;
				$label   = 'Assignment outside any course';
				break;

			default:
				$row = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT p.ID AS question_id, p.post_content, p.post_author, p.post_status, p.post_title, p.post_type,
							t.meta_value AS question_type, m.meta_value AS question_mark
						 FROM {$wpdb->posts} p
						 LEFT JOIN {$wpdb->postmeta} t ON t.post_id = p.ID AND t.meta_key = '_lp_type'
						 LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_lp_mark'
						 WHERE p.ID = %d",
						$item_id
					)
				);

				// An unsupported type is reported (and not copied) by copy_question() itself.
				$copy_id = $row ? self::copy_question( $row, 0, 0 ) : 0;

				if ( ! $copy_id ) {
					return;
				}

				$group  = Report::GROUP_QUESTIONS;
				$label  = 'Question outside any quiz';
				$reason = 'The question bank question is not used in any LearnPress quiz, so it was copied (status kept) and is available in the MasterStudy question library, but it is not part of any quiz.';
		}

		Report::add(
			$group,
			array(
				'source_id' => $item_id,
				'title'     => self::post_label( $item_id ),
				'type'      => $label,
				'reason'    => $reason,
				'status'    => Report::STATUS_PARTIAL,
				'post_id'   => $copy_id,
			)
		);
	}

	/**
	 * Record a LearnPress curriculum item (assignment, H5P, …) that has no MasterStudy equivalent.
	 *
	 * @param int    $item_id      Item post ID.
	 * @param string $item_type    Item post type (or the section item type when the post is gone).
	 * @param bool   $exists       Whether the item post still exists.
	 * @param int    $course_id    Course post ID.
	 * @param string $section_name Curriculum section name.
	 */
	private static function report_unsupported_curriculum_item( int $item_id, string $item_type, bool $exists, int $course_id, string $section_name ): void {
		$labels = array(
			'lp_assignment' => array( Report::GROUP_ASSIGNMENTS, 'LearnPress assignment' ),
			'lp_h5p'        => array( Report::GROUP_OTHER, 'H5P content' ),
		);

		list( $group, $type ) = $labels[ $item_type ] ?? array(
			Report::GROUP_LESSONS,
			sprintf( 'Curriculum item (%s)', '' !== $item_type ? $item_type : 'unknown' ),
		);

		if ( ! $exists ) {
			$reason = 'The curriculum item post no longer exists, so it could not be imported.';
		} elseif ( 'lp_assignment' === $item_type ) {
			$reason = 'LearnPress assignments require MasterStudy LMS Pro (Assignments addon) — the assignment and its student submissions stay in LearnPress and were not added to the MasterStudy curriculum.';
		} else {
			$reason = 'MasterStudy has no equivalent of this curriculum item type — the item stays in LearnPress and was not added to the MasterStudy curriculum.';
		}

		Report::add(
			$group,
			array(
				'source_id' => $item_id,
				'title'     => self::post_label( $item_id ),
				'type'      => $type,
				'reason'    => $reason,
				'status'    => $exists ? Report::STATUS_UNSUPPORTED : Report::STATUS_FAILED,
				'parent'    => '' !== $section_name ? 'Section: ' . $section_name : '',
				'course'    => self::post_label( $course_id ),
				'post_id'   => $exists ? $item_id : 0,
			)
		);
	}

	/**
	 * Human-readable label of a LearnPress question type.
	 *
	 * @param string $lp_type LearnPress _lp_type value.
	 */
	private static function question_type_label( string $lp_type ): string {
		$labels = array(
			'true_or_false'  => 'True or false',
			'single_choice'  => 'Single choice',
			'multi_choice'   => 'Multiple choice',
			'fill_in_blanks' => 'Fill in the blanks',
			'sorting_choice' => 'Sorting choice',
		);

		if ( isset( $labels[ $lp_type ] ) ) {
			return $labels[ $lp_type ];
		}

		return '' !== $lp_type ? ucfirst( str_replace( array( '_', '-' ), ' ', $lp_type ) ) : 'Unknown type';
	}

	/**
	 * Post title for the report, falling back to "#ID".
	 *
	 * @param int $post_id Post ID.
	 */
	private static function post_label( int $post_id ): string {
		$title = get_post( $post_id ) ? get_post_field( 'post_title', $post_id ) : '';

		return '' !== $title ? $title : sprintf( '#%d', $post_id );
	}

	/**
	 * User e-mail for the report, falling back to "User #ID".
	 *
	 * @param int $user_id WP user ID.
	 */
	private static function user_label( int $user_id ): string {
		$user = $user_id ? get_userdata( $user_id ) : false;

		return $user ? $user->user_email : sprintf( 'User #%d', $user_id );
	}

	/**
	 * "user@x → Course X" title of an enrollment.
	 *
	 * @param int $user_id   WP user ID.
	 * @param int $course_id Course post ID.
	 */
	private static function enrollment_title( int $user_id, int $course_id ): string {
		return sprintf( '%s → %s', self::user_label( $user_id ), self::post_label( $course_id ) );
	}

	/**
	 * IDs of the courses migrated from LearnPress.
	 *
	 * @return int[]
	 */
	private static function migrated_course_ids(): array {
		global $wpdb;

		return array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT p.ID FROM {$wpdb->posts} p
					 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s AND pm.meta_value = %s
					 WHERE p.post_type = %s",
					Target::SOURCE_META,
					self::SOURCE,
					PostType::COURSE
				)
			)
		);
	}

	/**
	 * Run once after a step completes: prerequisites, course packages → bundles and coupons after courses
	 * (every course copy exists then), student counters after enrollments, rating caches after reviews, and
	 * course progress after quiz results / lesson progress.
	 *
	 * @param string $step Step name.
	 */
	public static function finalize_step( string $step ): void {
		switch ( $step ) {
			case 'courses':
				self::migrate_prerequisites();
				self::migrate_course_packages();
				self::migrate_coupons();
				self::report_collections();
				self::report_orphan_assignments();
				break;

			case 'enrollments':
				foreach ( self::migrated_course_ids() as $course_id ) {
					Target::refresh_students_count( $course_id );
				}
				break;

			case 'reviews':
				Target::recalculate_ratings();
				break;

			case 'quiz_results':
			case 'lesson_progress':
				$course_ids = self::migrated_course_ids();

				if ( ! empty( $course_ids ) ) {
					Target::recalculate_progress( $course_ids );
				}
				break;
		}
	}

	/**
	 * Copy LearnPress quiz-level settings to MasterStudy quiz meta of the copy.
	 *
	 * @param int $quiz_id   LearnPress quiz post ID.
	 * @param int $copy_id   Quiz copy ID.
	 * @param int $course_id LearnPress course post ID (0 when none), for the report.
	 */
	private static function migrate_single_quiz_meta( int $quiz_id, int $copy_id, int $course_id = 0 ): void {
		$data = array(
			'duration_minutes' => self::parse_lp_duration( $quiz_id ),
		);

		$pass_mark = get_post_meta( $quiz_id, '_lp_passing_grade', true );
		if ( '' !== $pass_mark ) {
			$data['passing_grade'] = absint( $pass_mark );
		}

		// LearnPress counts RE-takes (0 = one attempt only, -1 = unlimited); MasterStudy counts attempts (0 = unlimited).
		$retakes = get_post_meta( $quiz_id, '_lp_retake_count', true );
		if ( is_numeric( $retakes ) ) {
			$data['attempts'] = (int) $retakes < 0 ? 0 : (int) $retakes + 1;
		}

		// LearnPress 4 "Show correct answer" (_lp_show_correct_review); older data only has "Review" (_lp_review).
		$review = get_post_meta( $quiz_id, '_lp_show_correct_review', true );
		$review = '' !== $review ? $review : get_post_meta( $quiz_id, '_lp_review', true );
		if ( '' !== $review ) {
			$data['show_correct_answer'] = 'yes' === $review;
		}

		// Random Quiz add-on: questions shown in random order.
		if ( metadata_exists( 'post', $quiz_id, '_lp_random_mode' ) ) {
			$data['random_questions'] = 'yes' === get_post_meta( $quiz_id, '_lp_random_mode', true );
		}

		// LearnPress shows N questions per page (default 1). One per page is MasterStudy's "pagination" style;
		// several per page have no equivalent and keep the global style.
		$pagination = get_post_meta( $quiz_id, '_lp_pagination', true );

		if ( is_numeric( $pagination ) ) {
			$data['style'] = 1 === (int) $pagination ? 'pagination' : 'global';
		}

		Target::set_quiz( $copy_id, $data );
		Target::store_unmigrated_meta( $copy_id, 'lp_pagination', (string) $pagination );

		$dropped = array();

		if ( is_numeric( $pagination ) && (int) $pagination > 1 ) {
			$dropped[] = sprintf( '%d questions per page (MasterStudy shows one page or one question per page)', (int) $pagination );
		}

		if ( 'yes' === get_post_meta( $quiz_id, '_lp_instant_check', true ) ) {
			$dropped[] = 'instant answer check';
		}

		if ( 'yes' === get_post_meta( $quiz_id, '_lp_negative_marking', true ) ) {
			$dropped[] = 'negative marking' . ( 'yes' === get_post_meta( $quiz_id, '_lp_minus_skip_questions', true ) ? ' (including skipped questions)' : '' );
		}

		if ( ! empty( $dropped ) ) {
			foreach ( array( 'lp_instant_check' => '_lp_instant_check', 'lp_negative_marking' => '_lp_negative_marking', 'lp_minus_skip_questions' => '_lp_minus_skip_questions' ) as $key => $meta_key ) {
				Target::store_unmigrated_meta( $copy_id, $key, (string) get_post_meta( $quiz_id, $meta_key, true ) );
			}

			self::report_quiz_partial(
				$quiz_id,
				$course_id,
				'Quiz settings',
				sprintf( 'MasterStudy has no equivalent for: %s. The quiz was imported without them.', implode( ', ', $dropped ) )
			);
		}
	}

	/**
	 * Migrate one LearnPress quiz result row to a MasterStudy quiz attempt (+ answers) of the quiz copy.
	 * The result row is only read; idempotent per (user, quiz copy, attempt time).
	 *
	 * @param int $result_id Primary key of the learnpress_user_item_results row.
	 * @throws \Exception If the row is not found.
	 */
	private static function migrate_single_quiz_result( int $result_id ): void {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT lur.result, lur.user_item_id, lui.user_id, lui.item_id AS quiz_id, lui.start_time, lui.end_time,
					lui.status, lui.graduation, lui.ref_id
				 FROM {$wpdb->prefix}learnpress_user_item_results lur
				 INNER JOIN {$wpdb->prefix}learnpress_user_items lui
				     ON lur.user_item_id = lui.user_item_id
				 WHERE lur.id = %d AND lui.item_type = 'lp_quiz'",
				$result_id
			)
		);

		if ( ! $row ) {
			throw new \Exception(
				sprintf( 'LearnPress quiz result #%d no longer exists.', $result_id ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		$user_id   = (int) $row->user_id;
		$quiz_id   = (int) $row->quiz_id;
		$copy_id   = Target::copy_of( self::SOURCE, $quiz_id );
		$course_id = self::resolve_course_id( $quiz_id, (int) $row->ref_id );
		$report    = array(
			'source_id' => $result_id,
			'title'     => sprintf( 'Attempt #%d by %s', $result_id, self::user_label( $user_id ) ),
			'type'      => 'Quiz attempt',
			'parent'    => 'Quiz: ' . self::post_label( $quiz_id ),
			'course'    => $course_id ? get_post_field( 'post_title', $course_id ) : ( $row->ref_id ? self::post_label( (int) $row->ref_id ) : '' ),
			'post_id'   => $copy_id ? $copy_id : ( get_post( $quiz_id ) ? $quiz_id : 0 ),
		);

		if ( ! get_userdata( $user_id ) ) {
			Report::add(
				Report::GROUP_QUIZ_ATTEMPTS,
				$report + array(
					'reason' => 'The student account no longer exists, so the attempt could not be imported.',
					'status' => Report::STATUS_FAILED,
				)
			);
			return;
		}

		if ( ! $course_id ) {
			Helper::log(
				'warning',
				sprintf( 'Migration: quiz %d (user %d) is not in any migrated course curriculum — skipping quiz result.', $quiz_id, $user_id )
			);
			Report::add(
				Report::GROUP_QUIZ_ATTEMPTS,
				$report + array(
					'reason' => 'The quiz is not part of any migrated course curriculum, so the attempt has nowhere to be recorded.',
					'status' => Report::STATUS_UNSUPPORTED,
				)
			);
			return;
		}

		$data = json_decode( (string) $row->result, true );

		// An attempt still in progress has no result to import.
		if ( ! is_array( $data ) && 'completed' !== $row->status ) {
			Report::add(
				Report::GROUP_QUIZ_ATTEMPTS,
				array(
					'type'   => sprintf( 'Unfinished quiz attempt (%s)', (string) $row->status ),
					'reason' => 'The attempt was still in progress and has no result, so there is nothing to import.',
					'status' => Report::STATUS_UNSUPPORTED,
				) + $report
			);
			return;
		}

		$data    = is_array( $data ) ? $data : array();
		$percent = self::result_percent( $data );

		if ( isset( $data['pass'] ) ) {
			$passed = (bool) $data['pass'];
		} elseif ( in_array( $row->graduation, array( 'passed', 'failed' ), true ) ) {
			$passed = 'passed' === $row->graduation;
		} else {
			$passed = $percent >= (float) get_post_meta( $copy_id, 'passing_grade', true );
		}

		$now      = time();
		$ended_at = self::parse_lp_timestamp( $row->end_time, self::parse_lp_timestamp( $row->start_time, $now ) );

		// LearnPress 4 keeps one user item per quiz and one result row per attempt (retakes add rows); only the
		// latest attempt's times are on the user item. Earlier attempts are dated one minute apart before it so
		// every attempt stays distinct (and in order) for the idempotency check.
		$later = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}learnpress_user_item_results WHERE user_item_id = %d AND id > %d",
				(int) $row->user_item_id,
				$result_id
			)
		);

		$ended_at  -= $later * MINUTE_IN_SECONDS;
		$created_at = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $ended_at ) );

		$skipped = 0;
		$answers = self::result_answers( $data, $skipped );

		// Idempotency: an attempt already migrated is not added again (its report is written every run).
		if ( ! Target::quiz_attempt_exists( $user_id, $copy_id, $created_at, (float) $percent, (bool) $passed ) ) {
			Target::add_quiz_attempt( $user_id, $course_id, $copy_id, $percent, $passed, $created_at, $answers );
		}

		if ( $skipped > 0 ) {
			Report::add(
				Report::GROUP_QUIZ_ATTEMPTS,
				array(
					'type'   => 'Quiz attempt answers',
					'reason' => sprintf( 'The score was imported, but %d answer(s) to questions that were not migrated (unsupported or missing) were dropped.', $skipped ),
					'status' => Report::STATUS_PARTIAL,
				) + $report
			);
		}
	}

	/**
	 * Score percent of a LearnPress quiz result (LP 4 "result" key, or mark totals).
	 *
	 * @param array $data Decoded result JSON.
	 */
	private static function result_percent( array $data ): float {
		if ( isset( $data['result'] ) && is_numeric( $data['result'] ) ) {
			return (float) $data['result'];
		}

		if ( isset( $data['mark']['total'] ) ) {
			$total = floatval( $data['mark']['total'] );
			$mark  = isset( $data['mark']['mark'] ) ? floatval( $data['mark']['mark'] ) : 0.0;

			return $total > 0 ? $mark / $total * 100 : 0.0;
		}

		if ( isset( $data['user_mark'], $data['mark'] ) && is_numeric( $data['mark'] ) && (float) $data['mark'] > 0 ) {
			return (float) $data['user_mark'] / (float) $data['mark'] * 100;
		}

		return 0.0;
	}

	/**
	 * Convert the LearnPress "answered" map of a result into MasterStudy answer rows
	 * (user_answer string formats per question type, see Target::add_quiz_attempt()).
	 *
	 * @param array $data    Decoded result JSON.
	 * @param int   $skipped Receives the number of answers dropped (question not migrated or unsupported type).
	 * @return array List of ['question_id', 'answer', 'correct'].
	 */
	private static function result_answers( array $data, int &$skipped = 0 ): array {
		// LearnPress 4 keeps per-question data under "questions"; older results under "answered".
		if ( isset( $data['questions'] ) && is_array( $data['questions'] ) ) {
			$answered = $data['questions'];
		} elseif ( isset( $data['answered'] ) && is_array( $data['answered'] ) ) {
			$answered = $data['answered'];
		} else {
			return array();
		}

		$result = array();

		foreach ( $answered as $question_id => $info ) {
			// Keys are LearnPress question IDs; answers belong to the question copies (answer rows are read from the source).
			$question_id = (int) $question_id;
			$copy_id     = Target::copy_of( self::SOURCE, $question_id );
			$info        = is_array( $info ) ? $info : array( 'answered' => $info );
			$type        = $copy_id ? (string) get_post_meta( $copy_id, 'type', true ) : '';

			if ( ! $copy_id || PostType::QUESTION !== get_post_type( $copy_id ) || '' === $type ) {
				++$skipped;
				continue;
			}

			$raw    = $info['answered'] ?? '';
			$rows   = self::get_question_answer_rows( $question_id );
			$titles = array();

			foreach ( $rows as $row ) {
				$titles[ $row['value'] ] = $row['title'];
			}

			$values = is_array( $raw ) ? array_values( $raw ) : ( '' === (string) $raw ? array() : array( (string) $raw ) );

			switch ( $type ) {
				case 'true_false':
					$answer = empty( $values ) ? '' : ( (string) $values[0] === self::true_answer_value( $rows ) ? 'True' : 'False' );
					break;

				case 'single_choice':
					$answer = empty( $values ) ? '' : (string) ( $titles[ (string) $values[0] ] ?? $values[0] );
					break;

				case 'multi_choice':
					$answer = implode(
						',',
						array_map(
							function ( $value ) use ( $titles ) {
								return rawurlencode( (string) ( $titles[ (string) $value ] ?? $value ) );
							},
							$values
						)
					);
					break;

				case 'sortable':
					$answer = empty( $values ) ? '' : '[stm_lms_sortable]' . implode(
						'[stm_lms_sep]',
						array_map(
							function ( $value ) use ( $titles ) {
								return (string) ( $titles[ (string) $value ] ?? $value );
							},
							$values
						)
					);
					break;

				case 'fill_the_gap':
					// LearnPress keys the filled words by blank ID: put them in passage order.
					$blank_ids = ! empty( $rows ) ? self::fib_passage( $rows[0] )['ids'] : array();

					if ( is_array( $raw ) && array_intersect( array_map( 'strval', array_keys( $raw ) ), $blank_ids ) ) {
						$values = array_map(
							function ( $blank_id ) use ( $raw ) {
								return is_scalar( $raw[ $blank_id ] ?? '' ) ? (string) ( $raw[ $blank_id ] ?? '' ) : '';
							},
							$blank_ids
						);
					}

					$answer = implode( ',', array_map( 'strval', $values ) );
					break;

				default:
					++$skipped;
					continue 2;
			}

			$result[] = array(
				'question_id' => $copy_id,
				'answer'      => $answer,
				'correct'     => ! empty( $info['correct'] ),
			);
		}

		return $result;
	}

	/**
	 * Enable preview on a lesson copy whose LearnPress lesson has _lp_preview = 'yes'.
	 *
	 * @param int $lesson_id LearnPress lesson post ID.
	 * @param int $copy_id   Lesson copy ID.
	 */
	private static function migrate_single_lesson_preview( int $lesson_id, int $copy_id ): void {
		if ( 'yes' !== get_post_meta( $lesson_id, '_lp_preview', true ) ) {
			return;
		}

		$type = (string) get_post_meta( $copy_id, 'type', true );

		Target::set_lesson(
			$copy_id,
			array(
				'type'    => '' !== $type ? $type : 'text',
				'preview' => true,
			)
		);
	}

	/**
	 * LearnPress 4.2 downloadable materials (learnpress_files) → MasterStudy lesson files / course
	 * materials of the copy. Uploaded files are registered as attachments (idempotent per material); external
	 * links have no MasterStudy equivalent (materials are media-library files) and are reported.
	 *
	 * @param int    $post_id   LearnPress lesson or course post ID.
	 * @param string $item_type learnpress_files.item_type (lp_lesson|lp_course).
	 * @param int    $copy_id   Lesson or course copy ID.
	 * @param int    $course_id LearnPress course post ID (for the report).
	 */
	private static function migrate_materials( int $post_id, string $item_type, int $copy_id, int $course_id ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'learnpress_files';

		if ( ! self::table_exists( $table ) ) {
			return;
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT file_id, file_name, file_type, method, file_path FROM {$table} WHERE item_id = %d AND item_type = %s ORDER BY orders ASC, file_id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$post_id,
				$item_type
			)
		);

		if ( empty( $rows ) ) {
			return;
		}

		$uploads = wp_upload_dir( null, false );
		$author  = (int) get_post_field( 'post_author', $post_id );
		$ids     = array();
		$dropped = array();

		foreach ( $rows as $row ) {
			$path = trim( (string) $row->file_path );
			$name = (string) $row->file_name;

			if ( 'external' === $row->method || preg_match( '#^https?://#i', $path ) ) {
				$dropped[] = sprintf( '%s (external link %s)', $name, $path );
				continue;
			}

			$absolute = 0 === strpos( wp_normalize_path( $path ), wp_normalize_path( (string) $uploads['basedir'] ) )
				? $path
				: trailingslashit( (string) $uploads['basedir'] ) . ltrim( $path, '/' );
			$ext      = pathinfo( $absolute, PATHINFO_EXTENSION );
			$filename = '' !== $name ? $name . ( '' !== $ext && false === strpos( $name, '.' ) ? '.' . $ext : '' ) : '';

			$attachment_id = self::import_file(
				array(
					'file'     => $absolute,
					'filename' => $filename,
				),
				$author,
				'material-' . (int) $row->file_id
			);

			if ( $attachment_id ) {
				$ids[] = $attachment_id;
			} else {
				$dropped[] = sprintf( '%s (file not found)', $name );
			}
		}

		if ( 'lp_lesson' === $item_type ) {
			Target::set_lesson_files( $copy_id, $ids );
		} elseif ( ! empty( $ids ) ) {
			$current = json_decode( (string) get_post_meta( $copy_id, 'course_files', true ), true );
			$current = is_array( $current ) ? array_map( 'intval', $current ) : array();

			update_post_meta( $copy_id, 'course_files', wp_json_encode( array_values( array_unique( array_merge( $current, $ids ) ) ) ) );
		}

		if ( ! empty( $dropped ) ) {
			Report::add(
				'lp_lesson' === $item_type ? Report::GROUP_LESSONS : Report::GROUP_COURSES,
				array(
					'source_id' => $post_id,
					'title'     => self::post_label( $post_id ),
					'type'      => 'Downloadable materials',
					'reason'    => sprintf( 'Not imported: %s. MasterStudy materials are media-library files.', implode( ', ', $dropped ) ),
					'status'    => Report::STATUS_PARTIAL,
					'course'    => $course_id ? self::post_label( $course_id ) : '',
					'post_id'   => $copy_id,
				)
			);
		}
	}

	/**
	 * Copy _lp_faqs of a LearnPress course to the MasterStudy course FAQ of the copy.
	 *
	 * @param int $course_id LearnPress course post ID.
	 * @param int $copy_id   Course copy ID.
	 */
	private static function migrate_single_course_faqs( int $course_id, int $copy_id ): void {
		$faqs = maybe_unserialize( get_post_meta( $course_id, '_lp_faqs', true ) );

		if ( ! is_array( $faqs ) || empty( $faqs ) ) {
			return;
		}

		$items = array();

		foreach ( $faqs as $faq ) {
			if ( ! is_array( $faq ) ) {
				continue;
			}

			// LearnPress 4 stores [question, answer] pairs; older data uses named keys.
			$question = isset( $faq['question'] ) ? $faq['question'] : ( $faq[0] ?? '' );
			$answer   = isset( $faq['answer'] ) ? $faq['answer'] : ( $faq[1] ?? '' );
			$question = sanitize_text_field( (string) $question );
			$answer   = wp_kses_post( (string) $answer );

			if ( ! $question && ! $answer ) {
				continue;
			}

			$items[] = array(
				'question' => $question,
				'answer'   => $answer,
			);
		}

		Target::set_faq( $copy_id, $items );
	}

	// -------------------------------------------------------------------------
	// LearnPress add-ons → MasterStudy LMS Pro
	// -------------------------------------------------------------------------

	/**
	 * learnpress_user_items types handled by the lesson_progress step, as an SQL list.
	 * Assignment submissions are only imported when MasterStudy LMS Pro is active.
	 */
	private static function progress_item_types(): string {
		return ProTarget::pro_active() ? "'lp_lesson','lp_quiz','lp_assignment'" : "'lp_lesson','lp_quiz'";
	}

	/**
	 * Report type label of a learnpress_user_items row.
	 *
	 * @param string $item_type learnpress_user_items.item_type.
	 */
	private static function progress_type_label( string $item_type ): string {
		$labels = array(
			'lp_quiz'       => 'Quiz progress',
			'lp_assignment' => 'Assignment submission',
		);

		return $labels[ $item_type ] ?? 'Lesson progress';
	}

	/**
	 * Copy Assignments add-on settings to the stm-assignments copy (the introduction is merged into the copy's
	 * content by copy_assignment()).
	 *
	 * @param int $assignment_id LearnPress assignment post ID.
	 * @param int $copy_id       Assignment copy ID.
	 */
	private static function migrate_assignment_meta( int $assignment_id, int $copy_id ): void {
		$max_mark = (float) get_post_meta( $assignment_id, '_lp_mark', true );
		$passing  = get_post_meta( $assignment_id, '_lp_passing_grade', true );
		$data     = array(
			// Shared media-library files are referenced by ID, not duplicated.
			'files' => self::attachment_ids( get_post_meta( $assignment_id, '_lp_attachments', false ) ),
		);

		// LearnPress counts retakes, MasterStudy counts attempts.
		if ( metadata_exists( 'post', $assignment_id, '_lp_retake_count' ) ) {
			$data['attempts'] = absint( get_post_meta( $assignment_id, '_lp_retake_count', true ) ) + 1;
		}

		// LearnPress passing grade is a mark out of _lp_mark; MasterStudy expects a percent.
		if ( is_numeric( $passing ) ) {
			$data['passing_grade'] = self::mark_to_percent( (float) $passing, $max_mark );
		}

		$duration = self::lp_duration_parts( $assignment_id );

		if ( $duration ) {
			$data['time_limit']      = $duration[0];
			$data['time_limit_unit'] = $duration[1];
		}

		ProTarget::set_assignment( $copy_id, $data );

		// Upload restrictions (_lp_upload_files, _lp_file_extension, _lp_upload_file_limit) are global
		// Assignments settings in MasterStudy; the LearnPress values stay on the LearnPress assignment.
	}

	/**
	 * Import one Assignments add-on submission (learnpress_user_items row, item_type lp_assignment).
	 *
	 * @param int    $user_item_id learnpress_user_items primary key.
	 * @param object $row          The row (user_id, item_id, status, graduation, start_time, end_time).
	 * @param int    $course_id    MasterStudy course ID.
	 * @throws \Exception When the student no longer exists (item is rolled back and reported).
	 */
	private static function migrate_assignment_submission( int $user_item_id, object $row, int $course_id ): void {
		$user_id       = (int) $row->user_id;
		$assignment_id = (int) $row->item_id;
		$copy_id       = Target::copy_of( self::SOURCE, $assignment_id );
		$title         = sprintf( '%s by %s', self::post_label( $assignment_id ), self::user_label( $user_id ) );

		if ( ! $copy_id || PostType::ASSIGNMENT !== get_post_type( $copy_id ) ) {
			Report::add(
				Report::GROUP_ASSIGNMENTS,
				array(
					'source_id' => $user_item_id,
					'title'     => $title,
					'type'      => 'Assignment submission',
					'reason'    => 'The assignment was not copied to a MasterStudy assignment, so the submission has nowhere to be recorded.',
					'status'    => Report::STATUS_UNSUPPORTED,
					'course'    => get_post_field( 'post_title', $course_id ),
					'post_id'   => get_post( $assignment_id ) ? $assignment_id : 0,
				)
			);
			return;
		}

		// The submission takes ownership of its files (post_parent), so existing attachments get their own attachment row.
		$meta       = self::user_item_meta( $user_item_id );
		$content    = (string) ( is_scalar( $meta['_lp_assignment_answer_note'] ?? '' ) ? ( $meta['_lp_assignment_answer_note'] ?? '' ) : '' );
		$missing    = 0;
		$instructor = (int) get_post_field( 'post_author', $assignment_id );
		$files      = self::import_submission_files( $meta['_lp_assignment_answer_upload'] ?? null, $user_id, 'assignment-answer-' . $user_item_id, $missing );
		$feedback   = self::import_submission_files( $meta['_lp_assignment_evaluate_upload'] ?? null, $instructor, 'assignment-evaluation-' . $user_item_id, $missing );
		$grade      = null;

		if ( isset( $meta['_lp_assignment_mark'] ) && is_numeric( $meta['_lp_assignment_mark'] ) ) {
			$grade = self::mark_to_percent( (float) $meta['_lp_assignment_mark'], (float) get_post_meta( $assignment_id, '_lp_mark', true ) );
		}

		$status = self::assignment_submission_status( (string) $row->status, (string) $row->graduation, $grade, $copy_id );

		if ( 'draft' === $status && '' === trim( wp_strip_all_tags( $content ) ) && empty( $files ) ) {
			Report::add(
				Report::GROUP_ASSIGNMENTS,
				array(
					'source_id' => $user_item_id,
					'title'     => $title,
					'type'      => sprintf( 'Unsubmitted assignment (%s)', (string) $row->status ),
					'reason'    => 'The student started the assignment but saved no answer or file, so there is nothing to import.',
					'status'    => Report::STATUS_UNSUPPORTED,
					'course'    => get_post_field( 'post_title', $course_id ),
					'post_id'   => $copy_id,
				)
			);
			return;
		}

		$now = time();

		ProTarget::add_assignment_submission(
			array(
				'assignment_id'          => $copy_id,
				'course_id'              => $course_id,
				'student_id'             => $user_id,
				'content'                => $content,
				'status'                 => $status,
				'grade'                  => in_array( $status, array( 'passed', 'not_passed' ), true ) ? $grade : null,
				'review'                 => is_scalar( $meta['_lp_assignment_instructor_note'] ?? '' ) ? (string) ( $meta['_lp_assignment_instructor_note'] ?? '' ) : '',
				'attachments'            => $files,
				'instructor_attachments' => $feedback,
				'date'                   => self::parse_lp_timestamp( $row->end_time, self::parse_lp_timestamp( $row->start_time, $now ) ),
				'source_id'              => 'user-item-' . $user_item_id,
			),
			self::SOURCE
		);

		if ( $missing > 0 ) {
			Report::add(
				Report::GROUP_ASSIGNMENTS,
				array(
					'source_id' => $user_item_id,
					'title'     => $title,
					'type'      => 'Assignment submission files',
					'reason'    => sprintf( 'The submission was imported, but %d uploaded file(s) could not be found on disk and were not attached.', $missing ),
					'status'    => Report::STATUS_PARTIAL,
					'course'    => get_post_field( 'post_title', $course_id ),
					'post_id'   => $copy_id,
				)
			);
		}
	}

	/**
	 * Map a LearnPress assignment status/graduation to a MasterStudy submission status.
	 *
	 * @param string     $status        learnpress_user_items.status (started|completed|evaluated).
	 * @param string     $graduation    learnpress_user_items.graduation (passed|failed|in-progress).
	 * @param float|null $grade         Grade in percent, when evaluated.
	 * @param int        $assignment_id Assignment copy ID (MasterStudy passing grade).
	 * @return string draft|pending|passed|not_passed
	 */
	private static function assignment_submission_status( string $status, string $graduation, ?float $grade, int $assignment_id ): string {
		if ( 'passed' === $graduation ) {
			return 'passed';
		}

		if ( 'failed' === $graduation ) {
			return 'not_passed';
		}

		if ( 'evaluated' === $status && null !== $grade ) {
			return $grade >= (float) get_post_meta( $assignment_id, 'passing_grade', true ) ? 'passed' : 'not_passed';
		}

		if ( in_array( $status, array( 'completed', 'submitted', 'evaluated', 'finished' ), true ) ) {
			return 'pending';
		}

		return 'draft';
	}

	/**
	 * Convert a LearnPress mark (out of $max) to a percent. Values above $max are taken as a percent.
	 *
	 * @param float $value Mark.
	 * @param float $max   Maximum mark (_lp_mark).
	 */
	private static function mark_to_percent( float $value, float $max ): float {
		if ( $max > 0 && $value <= $max ) {
			return round( $value / $max * 100, 2 );
		}

		return max( 0.0, min( 100.0, $value ) );
	}

	/**
	 * _lp_duration as [amount, MasterStudy unit] (minutes|hours|days|weeks), or null when not set.
	 *
	 * @param int $post_id Post ID.
	 */
	private static function lp_duration_parts( int $post_id ): ?array {
		$parts  = explode( ' ', trim( (string) get_post_meta( $post_id, '_lp_duration', true ) ) );
		$amount = (float) ( $parts[0] ?? 0 );

		if ( $amount <= 0 ) {
			return null;
		}

		$units = array(
			'minute' => 'minutes',
			'hour'   => 'hours',
			'day'    => 'days',
			'week'   => 'weeks',
		);

		return array( $amount, $units[ rtrim( strtolower( $parts[1] ?? 'minute' ), 's' ) ] ?? 'minutes' );
	}

	/**
	 * Metadata of a learnpress_user_items row (learnpress_user_itemmeta), unserialized.
	 * LearnPress 4 keeps long values in extra_value.
	 *
	 * @param int $user_item_id learnpress_user_items primary key.
	 * @return array<string, mixed>
	 */
	private static function user_item_meta( int $user_item_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'learnpress_user_itemmeta';

		if ( ! self::table_exists( $table ) ) {
			return array();
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE learnpress_user_item_id = %d ORDER BY meta_id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_item_id
			),
			ARRAY_A
		);

		$meta = array();

		foreach ( (array) $rows as $row ) {
			$value = isset( $row['extra_value'] ) && '' !== (string) $row['extra_value'] ? $row['extra_value'] : ( $row['meta_value'] ?? '' );
			$value = maybe_unserialize( $value );

			if ( is_string( $value ) && in_array( substr( ltrim( $value ), 0, 1 ), array( '[', '{' ), true ) ) {
				$decoded = json_decode( $value, true );
				$value   = is_array( $decoded ) ? $decoded : $value;
			}

			$meta[ (string) $row['meta_key'] ] = $value;
		}

		return $meta;
	}

	/**
	 * Whether a database table exists (cached per request).
	 *
	 * @param string $table Full table name.
	 */
	private static function table_exists( string $table ): bool {
		static $cache = array();

		if ( ! isset( $cache[ $table ] ) ) {
			global $wpdb;

			$cache[ $table ] = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		}

		return $cache[ $table ];
	}

	/**
	 * Whether a table has a column (cached per request; false when the table is missing).
	 *
	 * @param string $table  Full table name.
	 * @param string $column Column name.
	 */
	private static function column_exists( string $table, string $column ): bool {
		static $cache = array();

		$key = $table . '.' . $column;

		if ( ! isset( $cache[ $key ] ) ) {
			global $wpdb;

			$cache[ $key ] = self::table_exists( $table )
				&& (bool) $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", $column ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		return $cache[ $key ];
	}

	/**
	 * Resolve LearnPress submission files to attachment IDs owned by the migration. LearnPress stores student
	 * uploads as file arrays (filename, file, url, type) outside the media library, so an attachment is created
	 * for each (idempotent per source id). The MasterStudy submission re-parents its files, so a file that already
	 * is a media-library attachment gets its own attachment row (same file) instead of the existing one being changed.
	 *
	 * @param mixed  $raw     Stored value (file arrays, attachment IDs, URLs).
	 * @param int    $author  Attachment author.
	 * @param string $prefix  Source id prefix for idempotency.
	 * @param int    $missing Incremented for every file that could not be resolved.
	 * @return int[]
	 */
	private static function import_submission_files( $raw, int $author, string $prefix, int &$missing ): array {
		$raw = maybe_unserialize( $raw );

		if ( null === $raw || '' === $raw || array() === $raw ) {
			return array();
		}

		if ( is_scalar( $raw ) ) {
			$raw = preg_split( '/[\s,]+/', trim( (string) $raw ), -1, PREG_SPLIT_NO_EMPTY );
		} elseif ( isset( $raw['file'] ) || isset( $raw['url'] ) ) {
			$raw = array( $raw );
		}

		$ids   = array();
		$index = 0;

		foreach ( (array) $raw as $key => $file ) {
			++$index;

			if ( is_numeric( $file ) ) {
				$file = array( 'id' => (int) $file );
			} elseif ( is_string( $file ) ) {
				$file = array( 'url' => $file );
			}

			if ( ! is_array( $file ) ) {
				continue;
			}

			$attachment_id = self::import_file( $file, $author, $prefix . '-' . ( is_string( $key ) ? sanitize_key( $key ) : $index ), true );

			if ( $attachment_id ) {
				$ids[] = $attachment_id;
			} else {
				++$missing;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Register a LearnPress-uploaded file as a WordPress attachment (the file itself is not copied).
	 *
	 * @param array  $file      Keys: file (absolute path), url, type (mime), filename|name, id.
	 * @param int    $author    Attachment author.
	 * @param string $source_id Idempotency key.
	 * @param bool   $own_row   True = never return an existing (non-migration) attachment: a file that already is an
	 *                          attachment gets a new attachment row pointing at the same file, so the caller may change
	 *                          it (e.g. re-parent it) without touching the original.
	 * @return int Attachment ID, 0 when the file cannot be found.
	 */
	private static function import_file( array $file, int $author, string $source_id, bool $own_row = false ): int {
		$existing = Target::find_migrated_post( 'attachment', self::SOURCE, $source_id );

		if ( $existing ) {
			return $existing;
		}

		$url   = is_string( $file['url'] ?? null ) ? trim( $file['url'] ) : '';
		$path  = is_string( $file['file'] ?? null ) ? $file['file'] : ( is_string( $file['path'] ?? null ) ? $file['path'] : '' );
		$found = ! empty( $file['id'] ) && is_numeric( $file['id'] ) && 'attachment' === get_post_type( (int) $file['id'] ) ? (int) $file['id'] : 0;

		if ( ! $found && '' !== $url ) {
			$found = (int) attachment_url_to_postid( $url );
		}

		if ( $found ) {
			if ( ! $own_row ) {
				return $found;
			}

			// Same file, new attachment row.
			$attached = get_attached_file( $found );
			$path     = is_string( $attached ) && '' !== $attached ? $attached : $path;
			$url      = (string) wp_get_attachment_url( $found );
			$file    += array(
				'type'     => (string) get_post_mime_type( $found ),
				'filename' => wp_basename( '' !== $path ? $path : $url ),
			);
		} elseif ( ! empty( $file['id'] ) && '' === $url && '' === $path ) {
			return 0; // An attachment ID that no longer exists.
		}

		$uploads = wp_upload_dir( null, false );
		$basedir = wp_normalize_path( (string) $uploads['basedir'] );
		$baseurl = (string) $uploads['baseurl'];

		if ( '' === $path && '' !== $url && 0 === strpos( $url, $baseurl ) ) {
			$path = $basedir . substr( $url, strlen( $baseurl ) );
		}

		$path       = wp_normalize_path( $path );
		$exists     = '' !== $path && file_exists( $path );
		$in_uploads = $exists && 0 === strpos( $path, trailingslashit( $basedir ) );

		// Own row of an existing media file: give it its own copy of the file too, so deleting the MasterStudy
		// attachment can never delete the file LearnPress still uses.
		if ( $found && $own_row && $in_uploads ) {
			$dir  = dirname( $path );
			$copy = wp_unique_filename( $dir, pathinfo( $path, PATHINFO_FILENAME ) . '-ms.' . pathinfo( $path, PATHINFO_EXTENSION ) );

			if ( copy( $path, trailingslashit( $dir ) . $copy ) ) {
				$path     = wp_normalize_path( trailingslashit( $dir ) . $copy );
				$url      = $baseurl . substr( $path, strlen( $basedir ) );
				$own_file = true;
			}
		}

		if ( $in_uploads && '' === $url ) {
			$url = $baseurl . substr( $path, strlen( $basedir ) );
		}

		// A local file that is gone cannot be attached; an external URL is kept as the attachment URL.
		if ( '' === $url || ( ! $exists && 0 === strpos( $url, $baseurl ) ) ) {
			return 0;
		}

		$name = (string) ( $file['filename'] ?? ( $file['name'] ?? '' ) );
		$name = '' !== $name ? $name : wp_basename( $exists ? $path : (string) wp_parse_url( $url, PHP_URL_PATH ) );
		$mime = is_string( $file['type'] ?? null ) && false !== strpos( $file['type'], '/' ) ? $file['type'] : (string) wp_check_filetype( $name )['type'];

		$attachment_id = wp_insert_attachment(
			array(
				'post_title'     => sanitize_text_field( pathinfo( $name, PATHINFO_FILENAME ) ),
				'post_mime_type' => '' !== $mime ? $mime : 'application/octet-stream',
				'post_status'    => 'inherit',
				'post_author'    => $author,
				'guid'           => esc_url_raw( $url ),
			),
			$in_uploads ? $path : false,
			0,
			true
		);

		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return 0;
		}

		update_post_meta( $attachment_id, Target::SOURCE_META, self::SOURCE );
		update_post_meta( $attachment_id, Target::SOURCE_ID_META, $source_id );

		if ( ! empty( $own_file ) ) {
			update_post_meta( $attachment_id, Target::OWN_FILE_META, 1 );
		}

		return (int) $attachment_id;
	}

	/**
	 * Attachment IDs from a meta value (arrays, serialized arrays, comma lists).
	 *
	 * @param mixed $raw Meta value(s).
	 * @return int[]
	 */
	private static function attachment_ids( $raw ): array {
		return array_values(
			array_filter(
				self::id_list( $raw ),
				function ( $id ) {
					return 'attachment' === get_post_type( $id );
				}
			)
		);
	}

	/**
	 * Flatten IDs stored as several meta rows, (serialized) arrays or comma lists.
	 *
	 * @param mixed $raw Meta value(s), e.g. get_post_meta( $id, $key, false ).
	 * @return int[]
	 */
	private static function id_list( $raw ): array {
		$ids = array();

		foreach ( (array) $raw as $value ) {
			$value = maybe_unserialize( $value );

			foreach ( is_array( $value ) ? $value : array( $value ) as $item ) {
				if ( ! is_scalar( $item ) ) {
					continue;
				}

				foreach ( preg_split( '/[\s,]+/', (string) $item, -1, PREG_SPLIT_NO_EMPTY ) as $part ) {
					if ( is_numeric( $part ) && (int) $part > 0 ) {
						$ids[] = (int) $part;
					}
				}
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Report a course-level LearnPress add-on setting skipped because MasterStudy LMS Pro is inactive.
	 *
	 * @param int    $course_id LearnPress course post ID.
	 * @param string $type      Report type label.
	 * @param string $what      What was skipped (sentence subject).
	 */
	private static function report_requires_pro( int $course_id, string $type, string $what, string $edition = 'MasterStudy LMS Pro' ): void {
		Report::add(
			Report::GROUP_COURSES,
			array(
				'source_id' => $course_id,
				'title'     => get_post_field( 'post_title', $course_id ),
				'type'      => $type,
				'reason'    => sprintf( '%1$s requires %2$s, so it was not imported.', $what, $edition ),
				'status'    => Report::STATUS_UNSUPPORTED,
				'course'    => get_post_field( 'post_title', $course_id ),
				'post_id'   => self::report_post_id( $course_id ),
			)
		);
	}

	/**
	 * Report a course-level add-on setting imported only in part.
	 *
	 * @param int    $course_id LearnPress course post ID.
	 * @param string $type      Report type label.
	 * @param string $reason    Reason.
	 */
	private static function report_course_partial( int $course_id, string $type, string $reason ): void {
		Report::add(
			Report::GROUP_COURSES,
			array(
				'source_id' => $course_id,
				'title'     => get_post_field( 'post_title', $course_id ),
				'type'      => $type,
				'reason'    => $reason,
				'status'    => Report::STATUS_PARTIAL,
				'course'    => get_post_field( 'post_title', $course_id ),
				'post_id'   => self::report_post_id( $course_id ),
			)
		);
	}

	/**
	 * Certificates add-on certificate (lp_cert) assigned to a course, 0 when none.
	 *
	 * @param int $course_id Course post ID.
	 */
	private static function course_certificate_id( int $course_id ): int {
		$cert_id = (int) get_post_meta( $course_id, '_lp_cert', true );

		return $cert_id && 'lp_cert' === get_post_type( $cert_id ) ? $cert_id : 0;
	}

	/**
	 * Certificates add-on → a MasterStudy certificate approximating the source design (once per
	 * lp_cert) assigned to the course copy.
	 *
	 * @param int $course_id LearnPress course post ID.
	 * @param int $copy_id   Course copy ID.
	 */
	private static function migrate_course_certificate( int $course_id, int $copy_id ): void {
		$cert_id = self::course_certificate_id( $course_id );

		if ( ! $cert_id ) {
			return;
		}

		if ( ! ProTarget::pro_active() ) {
			self::report_requires_pro( $course_id, 'Course certificate', sprintf( 'The LearnPress certificate "%s" (Certificates add-on)', self::post_label( $cert_id ) ) );
			return;
		}

		$background = self::certificate_background( $cert_id );

		$certificate_id = ProTarget::create_certificate(
			array(
				'source_id'     => $cert_id,
				'title'         => self::post_label( $cert_id ),
				'background_id' => $background,
				'orientation'   => self::attachment_orientation( $background ),
			),
			self::SOURCE
		);

		ProTarget::set_course_certificate( $copy_id, $certificate_id );

		self::report_course_partial(
			$course_id,
			'Course certificate',
			sprintf(
				'The LearnPress certificate "%1$s" was recreated as a MasterStudy certificate (%2$s, with title, student name, course, date and instructor fields) — the original layer design is approximated; review it in the Certificate Builder.',
				self::post_label( $cert_id ),
				$background ? 'same background image' : 'no background image could be found'
			)
		);
	}

	/**
	 * Background image attachment of an lp_cert post (template image, then featured image).
	 *
	 * @param int $cert_id lp_cert post ID.
	 */
	private static function certificate_background( int $cert_id ): int {
		foreach ( array( '_lp_cert_template', '_lp_cert_background', '_thumbnail_id' ) as $key ) {
			$value = get_post_meta( $cert_id, $key, true );

			if ( is_numeric( $value ) && 'attachment' === get_post_type( (int) $value ) ) {
				return (int) $value;
			}

			if ( is_string( $value ) && '' !== $value && ! is_numeric( $value ) ) {
				$attachment_id = attachment_url_to_postid( $value );

				if ( $attachment_id ) {
					return $attachment_id;
				}
			}
		}

		return 0;
	}

	/**
	 * Certificate orientation from the background image size.
	 *
	 * @param int $attachment_id Attachment ID (0 = landscape).
	 * @return string landscape|portrait
	 */
	private static function attachment_orientation( int $attachment_id ): string {
		$meta = $attachment_id ? wp_get_attachment_metadata( $attachment_id ) : array();

		return is_array( $meta ) && ! empty( $meta['width'] ) && ! empty( $meta['height'] ) && (int) $meta['height'] > (int) $meta['width'] ? 'portrait' : 'landscape';
	}

	/**
	 * Keep a Certificates add-on verification code of an enrollment working in MasterStudy (course copy).
	 *
	 * @param int[] $user_item_ids learnpress_user_items rows of the course enrollment (every period, latest first).
	 * @param int   $user_id       WP user ID.
	 * @param int   $course_id     LearnPress course post ID.
	 * @param int   $copy_id       Course copy ID.
	 */
	private static function migrate_certificate_code( array $user_item_ids, int $user_id, int $course_id, int $copy_id ): void {
		if ( ! ProTarget::pro_active() || ! self::course_certificate_id( $course_id ) ) {
			return;
		}

		foreach ( $user_item_ids as $user_item_id ) {
			$meta = self::user_item_meta( (int) $user_item_id );

			foreach ( array( '_lp_cert_key', '_lp_certificate_key', '_lp_cert_code', '_lp_certificate_code' ) as $key ) {
				if ( ! empty( $meta[ $key ] ) && is_scalar( $meta[ $key ] ) ) {
					ProTarget::set_certificate_code( $user_id, $copy_id, (string) $meta[ $key ] );
					return;
				}
			}
		}
	}

	/**
	 * Content Drip add-on per-item settings, keyed by item ID. Reads the course-level
	 * _lp_drip_items map and falls back to per-item _lp_item_drip_* meta.
	 *
	 * @param int   $course_id Course post ID.
	 * @param int[] $item_ids  Curriculum item IDs.
	 * @return array<int, array>
	 */
	private static function drip_item_settings( int $course_id, array $item_ids ): array {
		$items = array();
		$raw   = maybe_unserialize( get_post_meta( $course_id, '_lp_drip_items', true ) );

		foreach ( is_array( $raw ) ? $raw : array() as $key => $settings ) {
			if ( ! is_array( $settings ) ) {
				continue;
			}

			$item_id = (int) ( $settings['item_id'] ?? ( $settings['id'] ?? $key ) );

			if ( $item_id ) {
				$items[ $item_id ] = $settings;
			}
		}

		foreach ( $item_ids as $item_id ) {
			if ( isset( $items[ $item_id ] ) ) {
				continue;
			}

			$settings = array();

			foreach ( (array) get_post_meta( $item_id ) as $key => $values ) {
				if ( 0 === strpos( (string) $key, '_lp_item_drip_' ) ) {
					$settings[ substr( (string) $key, strlen( '_lp_item_drip_' ) ) ] = maybe_unserialize( $values[0] ?? '' );
				}
			}

			if ( ! empty( $settings ) ) {
				$items[ $item_id ] = $settings;
			}
		}

		return $items;
	}

	/**
	 * Delay of a drip item setting in minutes (0 = none).
	 *
	 * @param array $settings Item settings.
	 */
	private static function drip_interval_minutes( array $settings ): int {
		$interval = $settings['interval'] ?? ( $settings['delay'] ?? null );
		$unit     = (string) ( $settings['interval_unit'] ?? ( $settings['interval_type'] ?? ( $settings['unit'] ?? 'day' ) ) );
		$number   = 0.0;

		if ( is_array( $interval ) ) {
			$values = array_values( $interval );
			$number = (float) ( $values[0] ?? 0 );
			$unit   = is_string( $values[1] ?? null ) ? $values[1] : $unit;
		} elseif ( is_string( $interval ) && preg_match( '/^\s*(\d+(?:\.\d+)?)\s*([a-z]+)/i', $interval, $matches ) ) {
			$number = (float) $matches[1];
			$unit   = $matches[2];
		} elseif ( is_numeric( $interval ) ) {
			$number = (float) $interval;
		}

		$factors = array(
			'minute' => 1,
			'hour'   => 60,
			'day'    => 1440,
			'week'   => 10080,
			'month'  => 43200,
		);

		return $number > 0 ? (int) ceil( $number * ( $factors[ rtrim( strtolower( $unit ), 's' ) ] ?? 1440 ) ) : 0;
	}

	/**
	 * Unix time of a drip date (timestamp, milliseconds or site-local date string).
	 *
	 * @param mixed $value Stored date.
	 */
	private static function drip_date( $value ): int {
		if ( is_numeric( $value ) ) {
			$value = (int) $value;

			return $value > 100000000000 ? (int) ( $value / 1000 ) : $value;
		}

		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return 0;
		}

		$date = date_create( str_replace( '/', '-', trim( $value ) ), wp_timezone() );

		return $date ? $date->getTimestamp() : 0;
	}

	/**
	 * Content Drip add-on → MasterStudy drip content of the course copy (sequential lock, unlock date / days
	 * after enrollment, or "after item" map).
	 *
	 * @param int             $course_id LearnPress course post ID.
	 * @param int             $copy_id   Course copy ID (curriculum already built).
	 * @param array<int, int> $items_map Curriculum items of the copy: LearnPress item ID => copy ID.
	 */
	private static function migrate_content_drip( int $course_id, int $copy_id, array $items_map ): void {
		if ( 'yes' !== get_post_meta( $course_id, '_lp_content_drip_enable', true ) ) {
			return;
		}

		$type = strtolower( (string) get_post_meta( $course_id, '_lp_content_drip_drip_type', true ) );

		if ( ! ProTarget::pro_active() ) {
			self::report_requires_pro( $course_id, 'Content drip', sprintf( 'LearnPress content drip (%s)', '' !== $type ? $type : 'specific' ) );
			return;
		}

		$curriculum = array_keys( $items_map );
		$items      = self::drip_item_settings( $course_id, $curriculum );
		$dropped    = array();

		if ( false !== strpos( $type, 'sequential' ) ) {
			ProTarget::sequential_course( $copy_id );

			foreach ( $items as $settings ) {
				if ( self::drip_interval_minutes( $settings ) > 0 ) {
					$dropped[] = 'delays between items (items unlock as soon as the previous one is completed)';
					break;
				}
			}
		} elseif ( false !== strpos( $type, 'prerequisite' ) ) {
			$map = array();

			foreach ( $items as $item_id => $settings ) {
				if ( ! in_array( $item_id, $curriculum, true ) ) {
					continue;
				}

				foreach ( self::id_list( $settings['prerequisite'] ?? ( $settings['prerequisites'] ?? array() ) ) as $parent ) {
					if ( $parent !== $item_id && in_array( $parent, $curriculum, true ) ) {
						$map[ $items_map[ $parent ] ][] = $items_map[ $item_id ];
					}
				}

				if ( self::drip_interval_minutes( $settings ) > 0 ) {
					$dropped['delay'] = 'delays after completing the prerequisite items';
				}
			}

			$rows = array();

			foreach ( $map as $parent => $children ) {
				$rows[] = array(
					'parent'   => $parent,
					'children' => array_values( array_unique( $children ) ),
				);
			}

			ProTarget::drip_after_items( $copy_id, $rows );

			if ( empty( $rows ) ) {
				$dropped[] = 'the prerequisite items (none could be read)';
			}
		} else {
			$applied = 0;

			foreach ( $items as $item_id => $settings ) {
				if ( ! in_array( $item_id, $curriculum, true ) ) {
					continue;
				}

				$kind    = strtolower( (string) ( $settings['type'] ?? '' ) );
				$date    = self::drip_date( $settings['date'] ?? ( $settings['specific_date'] ?? '' ) );
				$minutes = self::drip_interval_minutes( $settings );

				if ( 'immediately' === $kind ) {
					continue;
				}

				if ( $date && ( in_array( $kind, array( 'specific', 'specific_date', 'date' ), true ) || ! $minutes ) ) {
					ProTarget::drip_on_date( $items_map[ $item_id ], $date );
					++$applied;
				} elseif ( $minutes > 0 ) {
					if ( 0 !== $minutes % 1440 ) {
						$dropped['rounding'] = 'delays shorter than whole days (rounded up to days)';
					}

					ProTarget::drip_after_days( $items_map[ $item_id ], max( 1, (int) ceil( $minutes / 1440 ) ) );
					++$applied;
				}
			}

			if ( ! $applied ) {
				$dropped[] = 'the item schedule (no delay or date could be read)';
			}
		}

		if ( ! empty( $dropped ) ) {
			self::report_course_partial(
				$course_id,
				'Content drip',
				sprintf( 'LearnPress content drip was imported, but MasterStudy could not carry over: %s.', implode( ', ', array_unique( $dropped ) ) )
			);
		}
	}

	/**
	 * LearnPress courses required by the Prerequisites add-on (other existing LearnPress courses only).
	 *
	 * @param int $course_id LearnPress course post ID.
	 * @return int[]
	 */
	private static function prerequisite_ids( int $course_id ): array {
		return array_values(
			array_filter(
				self::id_list( get_post_meta( $course_id, '_lp_course_prerequisite', false ) ),
				function ( $id ) use ( $course_id ) {
					return $id !== $course_id && 'lp_course' === get_post_type( $id );
				}
			)
		);
	}

	/**
	 * Without MasterStudy LMS Pro, the Prerequisites add-on data of a course is reported (courses step).
	 *
	 * @param int $course_id LearnPress course post ID.
	 */
	private static function report_prerequisites_without_pro( int $course_id ): void {
		if ( ProTarget::pro_active() ) {
			return;
		}

		$ids = self::prerequisite_ids( $course_id );

		if ( ! empty( $ids ) ) {
			self::report_requires_pro( $course_id, 'Course prerequisites', sprintf( 'The prerequisite course(s) %s (Prerequisites add-on)', implode( ', ', array_map( array( __CLASS__, 'post_label' ), $ids ) ) ) );
		}
	}

	/**
	 * Prerequisites add-on → MasterStudy course prerequisites of the course copies. Runs once after the courses
	 * step, when every prerequisite course has its copy.
	 */
	private static function migrate_prerequisites(): void {
		global $wpdb;

		if ( ! ProTarget::pro_active() ) {
			return;
		}

		$course_ids = $wpdb->get_col(
			"SELECT DISTINCT p.ID FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_lp_course_prerequisite'
			 WHERE p.post_type = 'lp_course' ORDER BY p.ID ASC" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		foreach ( array_map( 'intval', (array) $course_ids ) as $course_id ) {
			$copy_id = Target::copy_of( self::SOURCE, $course_id );
			$ids     = self::prerequisite_ids( $course_id );

			if ( ! $copy_id || empty( $ids ) ) {
				continue;
			}

			$copies  = Target::copies_of( self::SOURCE, $ids );
			$missing = array_filter(
				$ids,
				function ( $id ) {
					return ! Target::copy_of( self::SOURCE, $id );
				}
			);

			if ( ! empty( $missing ) ) {
				self::report_course_partial(
					$course_id,
					'Course prerequisites',
					sprintf( 'Prerequisite course(s) %s were not migrated to MasterStudy, so the MasterStudy course does not require them.', implode( ', ', array_map( array( __CLASS__, 'post_label' ), $missing ) ) )
				);
			}

			if ( empty( $copies ) ) {
				continue;
			}

			// LearnPress requires the prerequisite courses to be passed (course passing condition, default 80%).
			$levels = array();

			foreach ( $ids as $id ) {
				$condition = get_post_meta( $id, '_lp_passing_condition', true );

				if ( is_numeric( $condition ) && (float) $condition > 0 ) {
					$levels[] = min( 100.0, (float) $condition );
				}
			}

			ProTarget::set_prerequisites( $copy_id, $copies, empty( $levels ) ? 100.0 : min( $levels ) );
		}
	}

	/**
	 * Co-Instructors add-on → MasterStudy co-instructor of the course copy (MasterStudy keeps one).
	 *
	 * @param int $course_id LearnPress course post ID.
	 * @param int $copy_id   Course copy ID.
	 */
	private static function migrate_co_instructors( int $course_id, int $copy_id ): void {
		$author = (int) get_post_field( 'post_author', $course_id );
		$ids    = array_values(
			array_filter(
				self::id_list( get_post_meta( $course_id, '_lp_co_teacher', false ) ),
				function ( $id ) use ( $author ) {
					return $id !== $author && get_userdata( $id );
				}
			)
		);

		if ( empty( $ids ) ) {
			return;
		}

		$labels = array_map( array( __CLASS__, 'user_label' ), $ids );

		if ( ! ProTarget::pro_active() ) {
			self::report_requires_pro( $course_id, 'Co-instructors', sprintf( 'The co-instructor(s) %s (Co-Instructors add-on)', implode( ', ', $labels ) ) );
			return;
		}

		ProTarget::set_co_instructor( $copy_id, $ids[0] );

		if ( count( $ids ) > 1 ) {
			self::report_course_partial(
				$course_id,
				'Co-instructors',
				sprintf( 'MasterStudy keeps one co-instructor per course: %1$s was imported; not imported: %2$s.', $labels[0], implode( ', ', array_slice( $labels, 1 ) ) )
			);
		}
	}

	/**
	 * First non-empty meta value among candidate keys (add-on storage differs between versions).
	 *
	 * @param int      $post_id Post ID.
	 * @param string[] $keys    Candidate meta keys.
	 * @return mixed '' when none is set.
	 */
	private static function first_meta( int $post_id, array $keys ) {
		foreach ( $keys as $key ) {
			$value = get_post_meta( $post_id, $key, true );

			if ( '' !== $value && null !== $value && array() !== $value ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * Upsell add-on coupons (lp_coupon posts, code = post title) → MasterStudy coupons (Pro Plus).
	 * A coupon whose discount cannot be read is reported instead of being created with a wrong value.
	 * Runs once after the courses step.
	 */
	private static function migrate_coupons(): void {
		global $wpdb;

		$coupon_ids = $wpdb->get_col(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('lp_coupon','learnpress_coupon') AND post_status NOT IN ('auto-draft') ORDER BY ID ASC" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		foreach ( array_map( 'intval', (array) $coupon_ids ) as $coupon_id ) {
			$coupon = get_post( $coupon_id );
			$report = array(
				'source_id' => $coupon_id,
				'title'     => $coupon->post_title,
				'type'      => 'Coupon (Upsell add-on)',
				'status'    => Report::STATUS_UNSUPPORTED,
				'post_id'   => $coupon_id,
			);

			if ( 'trash' === $coupon->post_status ) {
				Report::add( Report::GROUP_ORDERS, $report + array( 'reason' => 'The coupon is in the trash, so it was not imported.' ) );
				continue;
			}

			if ( ! ProTarget::plus_active() ) {
				Report::add( Report::GROUP_ORDERS, $report + array( 'reason' => 'Coupons require MasterStudy LMS Pro Plus (Coupons), so the coupon was not imported.' ) );
				continue;
			}

			$amount = self::first_meta( $coupon_id, array( '_lp_coupon_amount', '_lp_discount_amount', '_lp_coupon_discount_amount', '_lp_discount_value', '_lp_amount' ) );

			if ( ! is_numeric( $amount ) || (float) $amount <= 0 ) {
				Report::add( Report::GROUP_ORDERS, $report + array( 'reason' => 'The coupon discount could not be read, so no MasterStudy coupon was created.' ) );
				continue;
			}

			$type    = strtolower( (string) self::first_meta( $coupon_id, array( '_lp_coupon_type', '_lp_discount_type', '_lp_coupon_discount_type' ) ) );
			$listed  = self::id_list( self::first_meta( $coupon_id, array( '_lp_coupon_courses', '_lp_include_courses', '_lp_course_ids' ) ) );
			$courses = Target::copies_of( self::SOURCE, $listed );

			// A coupon limited to courses of which none was migrated must not become an "all courses" coupon.
			if ( ! empty( $listed ) && empty( $courses ) ) {
				Report::add( Report::GROUP_ORDERS, $report + array( 'reason' => 'None of the courses the coupon applies to were migrated to MasterStudy, so the coupon was not imported.' ) );
				continue;
			}

			$created = ProTarget::create_coupon(
				array(
					'code'             => $coupon->post_title,
					'title'            => $coupon->post_title,
					'type'             => false !== strpos( $type, 'percent' ) ? 'percent' : 'amount',
					'amount'           => (float) $amount,
					'status'           => 'publish' === $coupon->post_status ? 'active' : 'inactive',
					'usage_limit'      => (int) self::first_meta( $coupon_id, array( '_lp_coupon_usage_limit', '_lp_usage_limit' ) ),
					'user_usage_limit' => (int) self::first_meta( $coupon_id, array( '_lp_coupon_usage_limit_per_user', '_lp_usage_limit_per_user' ) ),
					'used_count'       => (int) self::first_meta( $coupon_id, array( '_lp_coupon_usage_count', '_lp_usage_count' ) ),
					'start'            => self::drip_date( self::first_meta( $coupon_id, array( '_lp_coupon_start_date', '_lp_start_date' ) ) ),
					'end'              => self::drip_date( self::first_meta( $coupon_id, array( '_lp_coupon_expiry_date', '_lp_coupon_end_date', '_lp_expiry_date', '_lp_end_date' ) ) ),
					'course_ids'       => $courses,
				)
			);

			if ( ! $created ) {
				Report::add( Report::GROUP_ORDERS, $report + array( 'reason' => 'A MasterStudy coupon with this code already exists (or the coupons table is missing), so the coupon was not imported.' ) );
			}
		}
	}

	/**
	 * Collections add-on (lp_collection): MasterStudy has no course collections.
	 */
	private static function report_collections(): void {
		global $wpdb;

		$ids = $wpdb->get_col(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'lp_collection' AND post_status NOT IN ('trash','auto-draft') ORDER BY ID ASC" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		foreach ( array_map( 'intval', (array) $ids ) as $collection_id ) {
			Report::add(
				Report::GROUP_COURSES,
				array(
					'source_id' => $collection_id,
					'title'     => get_post_field( 'post_title', $collection_id ),
					'type'      => 'Course collection (Collections add-on)',
					'reason'    => 'MasterStudy has no course collections; the collection page was left untouched (its courses were imported individually).',
					'status'    => Report::STATUS_UNSUPPORTED,
					'post_id'   => $collection_id,
				)
			);
		}
	}

	/**
	 * Upsell add-on course packages → MasterStudy course bundles of the course copies (idempotent per package).
	 * Runs once after the courses step, when every course has its copy.
	 */
	private static function migrate_course_packages(): void {
		global $wpdb;

		$package_ids = $wpdb->get_col(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('learnpress_package','lp_package') AND post_status NOT IN ('trash','auto-draft') ORDER BY ID ASC" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		foreach ( (array) $package_ids as $package_id ) {
			$package = get_post( (int) $package_id );

			if ( ! $package ) {
				continue;
			}

			$report = array(
				'source_id' => $package->ID,
				'title'     => $package->post_title,
				'type'      => 'Course package (Upsell add-on)',
				'status'    => Report::STATUS_UNSUPPORTED,
				'post_id'   => $package->ID,
			);

			if ( ! ProTarget::pro_active() ) {
				Report::add( Report::GROUP_COURSES, $report + array( 'reason' => 'Course packages require MasterStudy LMS Pro (Course Bundles addon), so the package was not imported.' ) );
				continue;
			}

			$course_ids = array();

			foreach ( array( '_lp_package_courses', '_lp_package_course_ids', '_lp_course_ids', '_lp_courses', 'lp_package_courses' ) as $key ) {
				$course_ids = array_merge( $course_ids, self::id_list( get_post_meta( $package->ID, $key, false ) ) );
			}

			$course_ids = Target::copies_of( self::SOURCE, array_unique( $course_ids ) );

			if ( empty( $course_ids ) ) {
				Report::add( Report::GROUP_COURSES, $report + array( 'reason' => 'The package courses could not be read, or none of them were migrated, so no course bundle was created.' ) );
				continue;
			}

			$regular = (float) get_post_meta( $package->ID, '_lp_regular_price', true );
			$regular = $regular > 0 ? $regular : (float) get_post_meta( $package->ID, '_lp_price', true );
			$regular = $regular > 0 ? $regular : (float) get_post_meta( $package->ID, '_lp_package_price', true );
			$sale    = (float) get_post_meta( $package->ID, '_lp_sale_price', true );

			ProTarget::create_bundle(
				array(
					'source_id'    => $package->ID,
					'title'        => $package->post_title,
					'content'      => $package->post_content,
					'author'       => (int) $package->post_author,
					'price'        => $sale > 0 && ( $sale < $regular || $regular <= 0 ) ? $sale : $regular,
					'course_ids'   => $course_ids,
					'thumbnail_id' => (int) get_post_thumbnail_id( $package->ID ),
					'status'       => in_array( $package->post_status, array( 'publish', 'private' ), true ) ? $package->post_status : 'draft',
				),
				self::SOURCE
			);
		}

		// finalize_step() runs outside the batch transaction; enable the requested addons now.
		Helper::flush_addon_requests();
	}
}
