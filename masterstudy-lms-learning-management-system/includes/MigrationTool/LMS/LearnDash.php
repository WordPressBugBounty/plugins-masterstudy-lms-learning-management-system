<?php
// phpcs:ignoreFile
/**
 * LearnDash migrations.
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
 * Class LearnDash.
 *
 * Reads LearnDash (sfwd-lms) data straight from the database — the LearnDash plugin does not
 * need to be active — and writes MasterStudy data through Target.
 *
 * Copy mode: LearnDash data is never modified. Courses, lessons, topics (→ lessons), quizzes, questions and
 * transactions (→ orders) are COPIED into new MasterStudy posts (Target::copy_post(), one copy per source
 * post, shared by every course that uses it); every ID written into MasterStudy data is a copy ID. The
 * LearnDash tables (activity, WpProQuiz), `ld_course_steps` and the LearnDash user meta are only read.
 *
 * LearnDash storage used here:
 * - Posts: sfwd-courses, sfwd-lessons, sfwd-topic, sfwd-quiz, sfwd-question, sfwd-transactions,
 *   sfwd-assignment, groups.
 * - Course structure: `ld_course_steps` course meta (hierarchical 'h' steps) and `course_sections` (JSON).
 * - Settings: `_sfwd-courses`, `_sfwd-lessons`, `_sfwd-topic`, `_sfwd-quiz` post meta arrays.
 * - Progress: {prefix}learndash_user_activity (+ _meta) rows.
 * - Questions / attempts: WpProQuiz tables ({prefix}learndash_pro_quiz_* or legacy {prefix}pro_quiz_*).
 * - Groups: `groups` posts + `_groups` settings, `learndash_group_{users|leaders}_{id}` user meta,
 *   `learndash_group_enrolled_{id}` course meta.
 * - Add-ons: Course Reviews (`ld_review` comments + `rating` comment meta), coupons (`ld-coupon` posts),
 *   Instructor Role shared instructors (`ir_shared_instructor_ids` course meta).
 */
class LearnDash {

	/**
	 * Source slug stored on every copied/created post (matches the migrator slug).
	 */
	const SOURCE = 'sfwd-lms';

	/**
	 * Comment meta on a copied lesson discussion comment: "sfwd-lms:<source comment ID>" (idempotency).
	 */
	const COMMENT_SOURCE_META = 'masterstudy_migrated_source_comment';

	/**
	 * Comment type of the LearnDash Course Reviews add-on.
	 */
	const REVIEW_COMMENT_TYPE = 'ld_review';

	/**
	 * "Ratings, Reviews, and Feedback for LearnDash" (WisdmLabs) stores each review as a post of this type: author =
	 * student, content / title = review, meta `wdm_course_review_review_on_course` (course) and
	 * `wdm_course_review_review_rating` (stars); pending while moderation is on.
	 */
	const WDM_REVIEW_POST_TYPE = 'wdm_course_review';

	/**
	 * Lesson/topic/quiz term names collected per course while its curriculum is built.
	 *
	 * @var array<int, string[]>
	 */
	private static $step_terms = array();

	// ──────────────────────────────────────────────────────────────────────────
	// Batch API — called by LearnDashMigrator
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * Return the total number of source items for the given migration step.
	 *
	 * @param string $step Step name.
	 * @return int
	 */
	public static function count_source_items( string $step ): int {
		global $wpdb;

		switch ( $step ) {
			case 'users':
				$ld_activity_table = $wpdb->prefix . 'learndash_user_activity';
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				return (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM (
							SELECT DISTINCT u.ID
							FROM {$wpdb->users} u
							INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
							WHERE um.meta_key = %s AND ( um.meta_value LIKE %s OR um.meta_value LIKE %s )
							UNION
							SELECT DISTINCT user_id AS ID
							FROM {$ld_activity_table}
							WHERE activity_type = %s
						) AS combined",
						$wpdb->get_blog_prefix() . 'capabilities',
						'%group_leader%',
						'%wdm_instructor%',
						'access'
					)
				);
				// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			case 'courses':
				return (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
						'sfwd-courses'
					)
				);

			case 'enrollments':
				$table = $wpdb->prefix . 'learndash_user_activity';
				$count = self::table_exists( $table )
					? (int) $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(*) FROM {$table} WHERE activity_type = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
							'access'
						)
					)
					: 0;

				// Enrollments that only exist as `course_{id}_access_from` user meta (LearnDash's own enrollment
				// record) and group memberships are imported in finalize_step() — keep the step from being skipped.
				if ( 0 === $count ) {
					$count = (int) $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
							$wpdb->esc_like( 'course_' ) . '%' . $wpdb->esc_like( '_access_from' )
						)
					);

					// Legacy course access lists (LearnDash 2.x) — imported in finalize_step() too.
					$count += count( self::course_access_lists() );

					if ( ! ProTarget::pro_active() ) {
						$count += (int) $wpdb->get_var(
							$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'", 'groups' )
						);
					}
				}

				return $count;

			case 'lesson_progress':
				$table = $wpdb->prefix . 'learndash_user_activity';
				$count = self::table_exists( $table )
					? (int) $wpdb->get_var(
						"SELECT COUNT(*) FROM {$table} WHERE activity_type IN ('lesson', 'topic', 'quiz')" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					)
					: 0;

				// Progress kept only in the `_sfwd-course_progress` / `_sfwd-quizzes` user meta (sites whose
				// LearnDash data was never upgraded to the activity table) is imported in finalize_step().
				if ( 0 === $count ) {
					$count = (int) $wpdb->get_var(
						"SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key IN ('_sfwd-course_progress', '_sfwd-quizzes')" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					);
				}

				return $count;

			case 'orders':
				return (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
						'sfwd-transactions'
					)
				);

			case 'quiz_attempts':
				$ref_table = self::pro_quiz_table( 'statistic_ref' );
				if ( ! self::table_exists( $ref_table ) ) {
					return 0;
				}
				return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$ref_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			case 'assignments':
				return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM (' . self::assignment_lessons_sql() . ') AS assignment_lessons' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			case 'reviews':
				return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = %s", self::REVIEW_COMMENT_TYPE ) );

			case 'wdm_reviews':
				return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status <> 'auto-draft'", self::WDM_REVIEW_POST_TYPE ) );

			case 'coupons':
				return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('auto-draft', 'trash')", 'ld-coupon' ) );
		}

		return 0;
	}

	/**
	 * SQL selecting the IDs (column `lesson_id`) of every lesson/topic that owns a LearnDash assignment:
	 * lessons with uploaded submissions (sfwd-assignment `lesson_id` meta) plus lessons/topics whose
	 * "Assignment uploads" setting is on (even when nobody uploaded yet).
	 */
	private static function assignment_lessons_sql(): string {
		global $wpdb;

		$upload_on = '%' . $wpdb->esc_like( '_lesson_assignment_upload";s:2:"on"' ) . '%';

		return $wpdb->prepare(
			"SELECT DISTINCT CAST(pm.meta_value AS UNSIGNED) AS lesson_id
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE p.post_type = 'sfwd-assignment' AND pm.meta_key = 'lesson_id' AND CAST(pm.meta_value AS UNSIGNED) > 0
			 UNION
			 SELECT DISTINCT pm.post_id AS lesson_id
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key IN ('_sfwd-lessons', '_sfwd-topic') AND pm.meta_value LIKE %s
			   AND p.post_type IN ('sfwd-lessons', 'sfwd-topic') AND p.post_status NOT IN ('trash', 'auto-draft')",
			$upload_on
		);
	}

	/**
	 * Return a paginated list of source item IDs for the given migration step.
	 *
	 * Copy mode: source records are never removed, so every step is cursor based
	 * (`… > $cursor ORDER BY id LIMIT $limit` over ALL source items). Failed items stay below the cursor,
	 * so $exclude is not needed.
	 *
	 * @param string $step    Step name.
	 * @param int    $limit   Batch size.
	 * @param int    $cursor  Last processed ID (0 = first batch).
	 * @param int[]  $exclude IDs to skip (already-failed items; unused — the cursor is past them).
	 * @return int[]
	 */
	public static function get_source_ids( string $step, int $limit, int $cursor, array $exclude = array() ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		global $wpdb;

		switch ( $step ) {
			case 'users':
				// Cursor-based — user rows persist after role assignment.
				$ld_activity_table = $wpdb->prefix . 'learndash_user_activity';
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				return array_map(
					'intval',
					$wpdb->get_col(
						$wpdb->prepare(
							"SELECT id FROM (
								SELECT DISTINCT u.ID AS id
								FROM {$wpdb->users} u
								INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
								WHERE um.meta_key = %s AND ( um.meta_value LIKE %s OR um.meta_value LIKE %s )
								UNION
								SELECT DISTINCT user_id AS id
								FROM {$ld_activity_table}
								WHERE activity_type = %s
							) AS combined
							WHERE id > %d
							ORDER BY id ASC
							LIMIT %d",
							$wpdb->get_blog_prefix() . 'capabilities',
							'%group_leader%',
							'%wdm_instructor%',
							'access',
							$cursor,
							$limit
						)
					)
				);
				// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			case 'courses':
				return array_map(
					'intval',
					$wpdb->get_col(
						$wpdb->prepare(
							"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND ID > %d ORDER BY ID ASC LIMIT %d",
							'sfwd-courses',
							$cursor,
							$limit
						)
					)
				);

			case 'enrollments':
				$table = $wpdb->prefix . 'learndash_user_activity';
				if ( ! self::table_exists( $table ) ) {
					return array();
				}
				return array_map(
					'intval',
					$wpdb->get_col(
						$wpdb->prepare(
							"SELECT activity_id FROM {$table} WHERE activity_type = %s AND activity_id > %d ORDER BY activity_id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
							'access',
							$cursor,
							$limit
						)
					)
				);

			case 'lesson_progress':
				$table = $wpdb->prefix . 'learndash_user_activity';
				if ( ! self::table_exists( $table ) ) {
					return array();
				}
				return array_map(
					'intval',
					$wpdb->get_col(
						$wpdb->prepare(
							"SELECT activity_id FROM {$table} WHERE activity_type IN ('lesson', 'topic', 'quiz') AND activity_id > %d ORDER BY activity_id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
							$cursor,
							$limit
						)
					)
				);

			case 'orders':
				return array_map(
					'intval',
					$wpdb->get_col(
						$wpdb->prepare(
							"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND ID > %d ORDER BY ID ASC LIMIT %d",
							'sfwd-transactions',
							$cursor,
							$limit
						)
					)
				);

			case 'quiz_attempts':
				// Cursor-based — the WpProQuiz statistics are only read.
				$ref_table = self::pro_quiz_table( 'statistic_ref' );
				if ( ! self::table_exists( $ref_table ) ) {
					return array();
				}
				return array_map(
					'intval',
					$wpdb->get_col(
						$wpdb->prepare(
							"SELECT statistic_ref_id FROM {$ref_table} WHERE statistic_ref_id > %d ORDER BY statistic_ref_id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
							$cursor,
							$limit
						)
					)
				);

			case 'wdm_reviews':
				return array_map(
					'intval',
					$wpdb->get_col(
						$wpdb->prepare(
							"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status <> 'auto-draft' AND ID > %d ORDER BY ID ASC LIMIT %d",
							self::WDM_REVIEW_POST_TYPE,
							$cursor,
							$limit
						)
					)
				);

			case 'reviews':
				// Cursor-based — the review comments stay on the course; $exclude ignored.
				return array_map(
					'intval',
					$wpdb->get_col(
						$wpdb->prepare(
							"SELECT comment_ID FROM {$wpdb->comments} WHERE comment_type = %s AND comment_ID > %d ORDER BY comment_ID ASC LIMIT %d",
							self::REVIEW_COMMENT_TYPE,
							$cursor,
							$limit
						)
					)
				);

			case 'coupons':
				// Cursor-based — ld-coupon posts are kept (MasterStudy coupons are table rows); $exclude ignored.
				return array_map(
					'intval',
					$wpdb->get_col(
						$wpdb->prepare(
							"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('auto-draft', 'trash') AND ID > %d ORDER BY ID ASC LIMIT %d",
							'ld-coupon',
							$cursor,
							$limit
						)
					)
				);

			case 'assignments':
				// Cursor-based on the owning lesson ID — source submissions are kept; $exclude ignored.
				return array_map(
					'intval',
					$wpdb->get_col(
						$wpdb->prepare(
							'SELECT lesson_id FROM (' . self::assignment_lessons_sql() . ') AS assignment_lessons WHERE lesson_id > %d ORDER BY lesson_id ASC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
							$cursor,
							$limit
						)
					)
				);
		}

		return array();
	}

	/**
	 * Migrate a single item for the given step.
	 *
	 * @param string $step    Step name.
	 * @param int    $item_id Source item ID.
	 */
	public static function migrate_item( string $step, int $item_id ): void {
		switch ( $step ) {
			case 'users':
				self::migrate_single_user( $item_id );
				break;
			case 'courses':
				self::migrate_single_course( $item_id );
				break;
			case 'enrollments':
				self::migrate_single_enrollment( $item_id );
				break;
			case 'lesson_progress':
				self::migrate_single_lesson_progress( $item_id );
				break;
			case 'orders':
				self::migrate_single_order( $item_id );
				break;
			case 'quiz_attempts':
				self::migrate_single_quiz_attempt( $item_id );
				break;
			case 'assignments':
				self::migrate_single_assignment( $item_id );
				break;
			case 'reviews':
				self::migrate_single_review( $item_id );
				break;
			case 'wdm_reviews':
				self::migrate_single_wdm_review( $item_id );
				break;
			case 'coupons':
				self::migrate_single_coupon( $item_id );
				break;
		}
	}

	/**
	 * Bulk work that runs once after a step completes.
	 *
	 * - courses: lessons, topics, quizzes and questions that are in no course are copied on their own;
	 *   LearnDash data MasterStudy has no place for (essays, challenge exams, notifications, unpublished
	 *   groups) is reported; with MasterStudy LMS Pro, course prerequisites are applied and LearnDash Groups
	 *   become MasterStudy groups (enterprise groups) and paid groups course bundles — all courses are copied by now.
	 * - enrollments: without Pro, enroll LearnDash group members (no 'access' activity row) directly;
	 *   then recount students and import earned course points (Pro).
	 * - lesson_progress / quiz_attempts / assignments: recalculate MasterStudy course progress.
	 * - reviews: rebuild the course rating caches.
	 *
	 * @param string $step Step name.
	 */
	public static function finalize_step( string $step ): void {
		if ( 'courses' === $step ) {
			self::migrate_orphan_steps();
			self::report_leftovers();
			self::report_unpublished_groups();

			if ( ProTarget::pro_active() ) {
				self::migrate_all_prerequisites();
				self::migrate_groups();
			}

			// finalize_step runs outside the batch transaction — enable the requested addons now.
			Helper::flush_addon_requests();
			return;
		}

		if ( 'reviews' === $step || 'wdm_reviews' === $step ) {
			Target::recalculate_ratings();
			return;
		}

		if ( 'enrollments' === $step ) {
			// LearnDash's own enrollment record is the `course_{id}_access_from` user meta — import the
			// enrollments that have no 'access' activity row (e.g. added before the activity table existed).
			self::migrate_meta_enrollments();

			// Catch group-enrolled users who never visited the course page
			// and therefore have no row in wp_learndash_user_activity (activity_type='access').
			// With Pro the groups (and their enrollments) were created in the courses step.
			if ( ! ProTarget::pro_active() ) {
				self::migrate_group_enrollments();
			}
			self::refresh_all_students_counts();
			self::migrate_course_points();
			return;
		}

		if ( 'lesson_progress' === $step ) {
			// Completions kept only in the LearnDash progress user meta (not in the activity table).
			self::migrate_meta_progress();
			self::recalculate_progress();
			return;
		}

		if ( 'quiz_attempts' === $step ) {
			self::recalculate_progress();
			return;
		}

		if ( 'assignments' === $step && ProTarget::pro_active() ) {
			self::recalculate_progress();
		}
	}

	/**
	 * Recalculate the progress of the MasterStudy copies of LearnDash courses only (other MasterStudy
	 * courses are left alone; an empty list would make Target recalculate every enrollment).
	 */
	private static function recalculate_progress(): void {
		$course_ids = self::copied_course_ids();

		if ( ! empty( $course_ids ) ) {
			Target::recalculate_progress( $course_ids );
		}
	}

	/**
	 * MasterStudy copies of all LearnDash courses.
	 *
	 * @return int[]
	 */
	private static function copied_course_ids(): array {
		global $wpdb;

		$source_ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s ORDER BY ID ASC", 'sfwd-courses' ) ) );

		return Target::copies_of( self::SOURCE, $source_ids );
	}

	// ──────────────────────────────────────────────────────────────────────────
	// Per-item migration
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * Give a LearnDash group_leader (and an Instructor Role add-on `wdm_instructor`) the MasterStudy
	 * instructor role; everyone else is a student. The LearnDash roles are kept (LearnDash keeps working).
	 *
	 * @param int $user_id User ID.
	 */
	public static function migrate_single_user( int $user_id ): void {
		global $wpdb;

		$caps = get_user_meta( $user_id, $wpdb->get_blog_prefix() . 'capabilities', true );

		if ( is_array( $caps ) && ( isset( $caps['group_leader'] ) || ! empty( $caps['wdm_instructor'] ) ) ) {
			Target::make_instructor( $user_id );
		}

		Target::ensure_student( $user_id );
	}

	/**
	 * Migrate a single LearnDash course: copy it into a new MasterStudy course, build the copy's
	 * curriculum (copies of the lessons, topics and quizzes) and apply the settings. The LearnDash
	 * course is not modified.
	 *
	 * @param int $course_id Source course ID.
	 */
	public static function migrate_single_course( int $course_id ): void {
		$copy_id = Target::copy_post( $course_id, PostType::COURSE, self::SOURCE );

		self::build_course_curriculum( $course_id, $copy_id );
		self::update_masterstudy_course_from_ld( $course_id, $copy_id );

		// Course author becomes a MasterStudy instructor.
		$author_id = (int) get_post_field( 'post_author', $course_id );
		if ( $author_id ) {
			Target::make_instructor( $author_id );
		}
	}

	/**
	 * Rebuild the course curriculum: sections, lessons, topics, and quizzes.
	 *
	 * LearnDash stores section headings in the `course_sections` post-meta as a JSON
	 * array of `{order, post_title}` objects, where `order` is the 0-based index of the
	 * section heading in the COMBINED flat list of all course items (section headings +
	 * lessons). Because section headings themselves occupy positions in that list, `order`
	 * is NOT a direct lesson index. To convert: lesson_index = order − idx, where idx is
	 * the 0-based position of this section in the sections-sorted-by-order array (i.e.,
	 * the number of section headings that precede it).
	 *
	 * MasterStudy has no sub-lesson level, so LearnDash topics become lessons placed right
	 * after their parent lesson (followed by the topic's quizzes), in the same section.
	 *
	 * Copy mode: the structure is read from the source course; the sections and materials are built on the
	 * copy with the copies of the lessons/topics/quizzes (a step shared by several courses has one copy).
	 *
	 * @param int $course_id Source course ID.
	 * @param int $copy_id   MasterStudy copy of the course.
	 */
	private static function build_course_curriculum( int $course_id, int $copy_id ): void {
		// Idempotency: a re-run rebuilds the curriculum from scratch.
		Target::reset_curriculum( $copy_id );

		$total_data = self::get_course_steps( $course_id );

		if ( empty( $total_data ) ) {
			return;
		}

		// Build a map of lesson-index → section title.
		$raw_headings = get_post_meta( $course_id, 'course_sections', true );
		$raw_headings = $raw_headings ? json_decode( (string) $raw_headings, true ) : array();

		$sorted_headings = array_values( array_filter( (array) $raw_headings, 'is_array' ) );
		usort(
			$sorted_headings,
			function ( $a, $b ) {
				return (int) ( $a['order'] ?? 0 ) - (int) ( $b['order'] ?? 0 );
			}
		);

		// Lesson index → section titles starting there. Several headings can share an index (empty
		// sections in LearnDash) — all of them are kept, the last one receives the lessons.
		$sections_at = array();
		foreach ( $sorted_headings as $idx => $heading ) {
			if ( isset( $heading['order'] ) ) {
				$lesson_index                   = max( 0, (int) $heading['order'] - $idx );
				$sections_at[ $lesson_index ][] = isset( $heading['post_title'] ) && '' !== trim( (string) $heading['post_title'] )
					? (string) $heading['post_title']
					: __( 'Section', 'masterstudy-lms-learning-management-system' );
			}
		}

		// Guarantee a section at position 0 so no lessons are ever orphaned.
		if ( ! isset( $sections_at[0] ) ) {
			$sections_at[0] = array( __( 'Section', 'masterstudy-lms-learning-management-system' ) );
		}

		ksort( $sections_at );

		$section_id     = 0;
		$section_order  = 0;
		$material_order = 0;
		$i              = 0;

		$attach = function ( int $post_id ) use ( &$section_id, &$material_order ) {
			++$material_order;
			Target::add_material( $section_id, $post_id, $material_order );
		};

		$open_sections = function ( int $index ) use ( &$sections_at, &$section_id, &$section_order, &$material_order, $copy_id ) {
			foreach ( $sections_at[ $index ] ?? array() as $title ) {
				++$section_order;
				$section_id     = Target::add_section( $copy_id, (string) $title, $section_order );
				$material_order = 0;
			}
			unset( $sections_at[ $index ] );
		};

		// Copy a quiz step, attach the copy and apply the lesson drip rule to it.
		$add_quiz = function ( int $quiz_key, array $drip = array() ) use ( $course_id, $attach ) {
			self::require_step_post( $quiz_key, $course_id );

			if ( self::skip_trashed_step( $quiz_key, $course_id ) ) {
				return;
			}

			$quiz_copy = Target::copy_post( $quiz_key, PostType::QUIZ, self::SOURCE );
			$attach( $quiz_copy );
			self::migrate_ld_quiz( $quiz_key, $quiz_copy, $course_id );
			self::apply_drip( $quiz_copy, $drip );
		};

		if ( ! empty( $total_data['sfwd-lessons'] ) ) {
			foreach ( (array) $total_data['sfwd-lessons'] as $lesson_key => $lesson_data ) {
				// Create the section(s) starting at this lesson index.
				$open_sections( $i );

				$lesson_data = is_array( $lesson_data ) ? $lesson_data : array();

				self::require_step_post( (int) $lesson_key, $course_id );

				// A trashed lesson (still listed in the builder) is not part of the course; its topics and
				// quizzes are imported on their own in finalize_step().
				if ( self::skip_trashed_step( (int) $lesson_key, $course_id ) ) {
					++$i;
					continue;
				}

				// LearnDash drips whole lessons: its topics and quizzes unlock with it (applied with Pro only).
				$lesson_drip = array();
				$lesson_copy = self::migrate_ld_lesson( (int) $lesson_key, 'sfwd-lessons', $course_id, $lesson_drip );
				$attach( $lesson_copy );

				// LearnDash topics are sub-lessons → MasterStudy lessons in the same section.
				foreach ( (array) ( isset( $lesson_data['sfwd-topic'] ) ? $lesson_data['sfwd-topic'] : array() ) as $topic_key => $topic_data ) {
					self::require_step_post( (int) $topic_key, $course_id );

					if ( self::skip_trashed_step( (int) $topic_key, $course_id ) ) {
						continue;
					}

					$topic_copy = self::migrate_ld_lesson( (int) $topic_key, 'sfwd-topic', $course_id );
					self::apply_drip( $topic_copy, $lesson_drip );
					$attach( $topic_copy );

					$topic_data = is_array( $topic_data ) ? $topic_data : array();

					foreach ( array_keys( (array) ( isset( $topic_data['sfwd-quiz'] ) ? $topic_data['sfwd-quiz'] : array() ) ) as $quiz_key ) {
						$add_quiz( (int) $quiz_key, $lesson_drip );
					}
				}

				// Quizzes directly under this lesson (not under a topic).
				foreach ( array_keys( (array) ( isset( $lesson_data['sfwd-quiz'] ) ? $lesson_data['sfwd-quiz'] : array() ) ) as $quiz_key ) {
					$add_quiz( (int) $quiz_key, $lesson_drip );
				}

				++$i;
			}

			// Top-level quizzes not nested under any lesson — appended to the last lesson section.
			if ( ! empty( $total_data['sfwd-quiz'] ) ) {
				foreach ( array_keys( (array) $total_data['sfwd-quiz'] ) as $quiz_key ) {
					$add_quiz( (int) $quiz_key );
				}
			}

			// Section headings placed after the last lesson are kept as (empty) sections.
			foreach ( array_keys( $sections_at ) as $index ) {
				$open_sections( (int) $index );
			}
		}

		// Courses that contain only standalone quizzes (no lessons at all).
		if ( empty( $total_data['sfwd-lessons'] ) && ! empty( $total_data['sfwd-quiz'] ) ) {
			$section_id     = Target::add_section( $copy_id, __( 'Section', 'masterstudy-lms-learning-management-system' ), 1 );
			$material_order = 0;

			foreach ( array_keys( (array) $total_data['sfwd-quiz'] ) as $quiz_key ) {
				$add_quiz( (int) $quiz_key );
			}
		}

		self::report_step_terms( $course_id );
	}

	/**
	 * A course step that is in the trash (LearnDash hides it) is left in LearnDash and reported.
	 *
	 * @param int $post_id   Step post ID.
	 * @param int $course_id Course ID.
	 * @return bool True when the step was skipped.
	 */
	private static function skip_trashed_step( int $post_id, int $course_id ): bool {
		if ( 'trash' !== get_post_status( $post_id ) ) {
			return false;
		}

		$is_quiz = in_array( get_post_type( $post_id ), array( 'sfwd-quiz', PostType::QUIZ ), true );

		Report::add(
			$is_quiz ? Report::GROUP_QUIZZES : Report::GROUP_LESSONS,
			array(
				'source_id' => $post_id,
				'title'     => self::post_title( $post_id ),
				'type'      => $is_quiz ? __( 'LearnDash quiz', 'masterstudy-lms-learning-management-system' ) : __( 'LearnDash lesson', 'masterstudy-lms-learning-management-system' ),
				'reason'    => __( 'The course builder still lists this step, but it is in the trash in LearnDash, so it was not added to the MasterStudy curriculum (it stays in the trash).', 'masterstudy-lms-learning-management-system' ),
				'status'    => Report::STATUS_UNSUPPORTED,
				'course'    => self::post_title( $course_id ),
				'post_id'   => $post_id,
			)
		);

		return true;
	}

	/**
	 * Migrate a single LearnDash enrollment (activity_type='access') to stm_lms_user_courses of the course copy.
	 * The activity row is only read.
	 *
	 * @param int $activity_id Primary key of the wp_learndash_user_activity row.
	 */
	public static function migrate_single_enrollment( int $activity_id ): void {
		global $wpdb;

		$ld_table = $wpdb->prefix . 'learndash_user_activity';
		$activity = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$ld_table} WHERE activity_id = %d", $activity_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		if ( ! $activity ) {
			return;
		}

		$user_id   = (int) $activity['user_id'];
		$course_id = (int) $activity['course_id'];

		if ( ! $course_id ) {
			$course_id = (int) $activity['post_id'];
		}

		$copy_id = self::course_copy( $course_id );

		// A deleted user or course (or a broken row) cannot be enrolled — report it instead of failing the item.
		if ( ! $user_id || ! $course_id || ! get_userdata( $user_id ) || ! $copy_id ) {
			Report::add(
				Report::GROUP_ENROLLMENTS,
				array(
					'source_id' => $activity_id,
					'title'     => self::user_label( $user_id ) . ' → ' . ( $course_id ? self::post_title( $course_id ) : __( 'unknown course', 'masterstudy-lms-learning-management-system' ) ),
					'type'      => __( 'Course enrollment', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'The enrollment belongs to a user or course that no longer exists (or was not migrated), so it was skipped.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_UNSUPPORTED,
					'course'    => $course_id ? self::post_title( $course_id ) : '',
					'post_id'   => self::report_post_id( $course_id ),
				)
			);
			return;
		}

		// Completion: LearnDash records it as `course_completed_{id}` user meta / a 'course' activity row,
		// not on the 'access' row (older data may carry it there).
		$date_end = self::course_completed_time( $user_id, $course_id );
		if ( ! $date_end && ! empty( $activity['activity_completed'] ) && (int) $activity['activity_completed'] > 0 ) {
			$date_end = (int) $activity['activity_completed'];
		}

		// Idempotent: skip if already enrolled (e.g. by a migrated group) — only carry over the completion.
		if ( Target::is_enrolled( $user_id, $copy_id ) ) {
			self::mark_enrollment_completed( $user_id, $copy_id, $date_end );
			return;
		}

		// Expired access: LearnDash removes the access (the `course_{id}_access_from` meta) and flags the
		// user with `learndash_course_expired_{id}` — the student no longer has access, so no enrollment.
		if ( self::access_expired( $user_id, $course_id ) ) {
			self::report_expired_enrollment( $user_id, $course_id, $activity_id );
			return;
		}

		// The 'access' row is only a log entry: LearnDash grants access through the access user meta, the
		// legacy course access list or a group. Access an admin removed must not come back.
		if ( ! self::has_ld_access( $user_id, $course_id ) ) {
			Report::add(
				Report::GROUP_ENROLLMENTS,
				array(
					'source_id' => $activity_id,
					'title'     => self::user_label( $user_id ) . ' → ' . self::post_title( $course_id ),
					'type'      => __( 'Removed enrollment', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'The student no longer has access to this course in LearnDash (the access was removed), so no MasterStudy enrollment was created (their progress is kept).', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_UNSUPPORTED,
					'course'    => self::post_title( $course_id ),
					'post_id'   => $copy_id,
				)
			);
			return;
		}

		$date_start = ! empty( $activity['activity_started'] ) ? (int) $activity['activity_started'] : (int) get_user_meta( $user_id, "course_{$course_id}_access_from", true );
		$date_start = $date_start > 0 ? $date_start : time();

		// MasterStudy stores no order link or price on the enrollment row (orders are migrated separately).
		// Throws on invalid user/course, which marks the item failed.
		// A source-completed enrollment keeps its completion date, so its progress is forced to 100.
		Target::enroll( $user_id, $copy_id, $date_start, $date_end ? $date_end : null, $date_end ? 100 : 0 );
	}

	/**
	 * Migrate a single LearnDash lesson/topic/quiz activity.
	 *
	 * Completed lessons/topics become stm_lms_user_lessons rows of the lesson copy (MasterStudy has no
	 * "started" state for lessons). Quiz completion in MasterStudy comes from stm_lms_user_quizzes rows,
	 * which the quiz_attempts step imports; a completed quiz activity only produces an attempt
	 * here when LearnDash kept no statistics for that user/quiz. Course progress is recalculated
	 * once in finalize_step(). The activity row is only read.
	 *
	 * @param int $activity_id Primary key of the wp_learndash_user_activity row.
	 */
	public static function migrate_single_lesson_progress( int $activity_id ): void {
		global $wpdb;

		$ld_table = $wpdb->prefix . 'learndash_user_activity';
		$activity = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$ld_table} WHERE activity_id = %d", $activity_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		if ( ! $activity ) {
			return;
		}

		$user_id   = (int) $activity['user_id'];
		$course_id = (int) $activity['course_id'];
		$post_id   = (int) $activity['post_id'];
		$post_copy = $post_id ? Target::copy_of( self::SOURCE, $post_id ) : 0;
		$copy_id   = $course_id ? self::course_copy( $course_id ) : 0;

		if ( ! $course_id && $post_copy ) {
			$course_ids = Target::course_ids_of( $post_copy );
			$copy_id    = (int) reset( $course_ids );
			$course_id  = $copy_id ? Target::source_of( $copy_id ) : 0;
		}

		$type_labels = array(
			'lesson' => __( 'Lesson progress', 'masterstudy-lms-learning-management-system' ),
			'topic'  => __( 'Topic progress', 'masterstudy-lms-learning-management-system' ),
			'quiz'   => __( 'Quiz progress', 'masterstudy-lms-learning-management-system' ),
		);
		$type_label  = $type_labels[ $activity['activity_type'] ] ?? (string) $activity['activity_type'];

		if ( ! $post_id || ! $copy_id || ! $post_copy || ! $user_id || ! get_userdata( $user_id ) ) {
			Report::add(
				Report::GROUP_PROGRESS,
				array(
					'source_id' => $activity_id,
					'title'     => self::user_label( $user_id ) . ' → ' . ( $post_id ? self::post_title( $post_id ) : __( 'unknown item', 'masterstudy-lms-learning-management-system' ) ),
					'type'      => $type_label,
					'reason'    => __( 'The progress record is not linked to a user, lesson or course that exists in MasterStudy, so it was skipped.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_UNSUPPORTED,
					'course'    => $course_id ? self::post_title( $course_id ) : '',
					'post_id'   => self::report_post_id( $post_id ),
				)
			);

			return;
		}

		$completed = ! empty( $activity['activity_status'] );
		$started   = ! empty( $activity['activity_started'] ) ? (int) $activity['activity_started'] : 0;
		$ended     = ! empty( $activity['activity_completed'] ) ? (int) $activity['activity_completed'] : 0;

		if ( 'quiz' === $activity['activity_type'] ) {
			// One 'quiz' row per LearnDash attempt (activity_status = passed). Attempts with statistics are
			// imported (with answers) by the quiz_attempts step; finished attempts without statistics are
			// imported from the activity itself; a started-but-unsubmitted attempt has nothing to import.
			if ( ! self::activity_has_statistics( $activity ) ) {
				if ( $completed || $ended > 0 ) {
					self::maybe_add_quiz_activity_attempt( $activity, $copy_id, $post_copy );
				} else {
					Report::add(
						Report::GROUP_PROGRESS,
						array(
							'source_id' => $activity_id,
							'title'     => self::user_label( $user_id ) . ' → ' . self::post_title( $post_id ),
							/* translators: %s: progress type label, e.g. "Quiz progress" */
							'type'      => sprintf( __( '%s (not completed)', 'masterstudy-lms-learning-management-system' ), $type_label ),
							'reason'    => __( 'The quiz attempt was started but never submitted, so there is no result to import.', 'masterstudy-lms-learning-management-system' ),
							'status'    => Report::STATUS_UNSUPPORTED,
							'course'    => self::post_title( $course_id ),
							'post_id'   => $post_copy,
						)
					);
				}
			}
		} elseif ( $completed ) {
			// Idempotent — complete_lesson() skips existing rows.
			Target::complete_lesson( $user_id, $copy_id, $post_copy, $started, $ended );
		} else {
			Report::add(
				Report::GROUP_PROGRESS,
				array(
					'source_id' => $activity_id,
					'title'     => self::user_label( $user_id ) . ' → ' . self::post_title( $post_id ),
					/* translators: %s: progress type label, e.g. "Lesson progress" */
					'type'      => sprintf( __( '%s (not completed)', 'masterstudy-lms-learning-management-system' ), $type_label ),
					'reason'    => __( 'MasterStudy only stores completed lessons, so this started-but-unfinished progress was not imported.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_UNSUPPORTED,
					'course'    => self::post_title( $course_id ),
					'post_id'   => $post_copy,
				)
			);
		}
	}

	/**
	 * Migrate a single LearnDash transaction (sfwd-transactions) to a native stm-orders order
	 * (a copy of the transaction post; the transaction itself is not modified).
	 *
	 * LearnDash records a transaction for a successful payment; the PayPal IPN status (pending, refunded,
	 * reversed, denied, …) and a trashed transaction are honored, so they never become completed orders.
	 *
	 * LearnDash 4.5+ checkouts: a parent transaction (post_parent 0) holds the gateway data and has one child
	 * transaction per purchased product (subscription renewal charges are children of the product transaction).
	 * A parent without a product of its own is not an order: its children are imported (reading the gateway data
	 * from the parent). A payment made in the gateway's test (sandbox) mode (`is_test_mode`) is not imported.
	 *
	 * @param int $order_id Transaction post ID.
	 */
	public static function migrate_single_order( int $order_id ): void {
		$post = get_post( $order_id );
		if ( ! $post ) {
			return;
		}

		$course_id = (int) get_post_meta( $order_id, 'course_id', true );
		$course_id = $course_id ? $course_id : (int) get_post_meta( $order_id, 'post_id', true );

		if ( ! $course_id ) {
			// LearnDash 4.5+ parent transaction: the products (and their prices) are its child transactions.
			if ( self::has_child_transactions( $order_id ) ) {
				return;
			}

			// A renewal charge (child of a subscription transaction) pays for its parent's product.
			$course_id = (int) self::transaction_meta( $order_id, 'course_id' );
			$course_id = $course_id ? $course_id : (int) self::transaction_meta( $order_id, 'post_id' );
		}

		$customer_id = (int) get_post_meta( $order_id, 'user_id', true );
		$customer_id = $customer_id ? $customer_id : (int) $post->post_author;
		$customer_id = $customer_id ? $customer_id : (int) self::transaction_meta( $order_id, 'user_id' );

		$order_title = sprintf(
			/* translators: 1: order ID, 2: customer label */
			__( 'Order #%1$d by %2$s', 'masterstudy-lms-learning-management-system' ),
			$order_id,
			self::user_label( $customer_id )
		);

		// Sandbox payments (LearnDash 4.5+ gateways in test mode) took no money: no MasterStudy order.
		if ( filter_var( self::transaction_meta( $order_id, 'is_test_mode' ), FILTER_VALIDATE_BOOLEAN ) ) {
			Report::add(
				Report::GROUP_ORDERS,
				array(
					'source_id' => $order_id,
					'title'     => $order_title,
					'type'      => __( 'LearnDash test-mode transaction', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'The payment was made in the payment gateway\'s test (sandbox) mode, so no real money was taken and no MasterStudy order was created (the LearnDash transaction was kept).', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_UNSUPPORTED,
					'course'    => $course_id ? self::post_title( $course_id ) : '',
					'post_id'   => $order_id,
				)
			);
			return;
		}

		$course_copy = self::course_copy( $course_id );
		$is_group    = $course_id && 'groups' === get_post_type( $course_id );

		// The amount actually paid (PayPal mc_gross, Stripe stripe_price, LearnDash 4 pricing_info) wins over the
		// current course price, which may have changed since the purchase.
		$paid  = self::transaction_amount( $order_id );
		$price = null !== $paid ? $paid : ( $course_id ? self::course_price( $course_id ) : 0.0 );

		$currency = self::transaction_currency( $order_id );

		$charge_id = (string) self::transaction_meta( $order_id, 'stripe_charge_id' );
		$intent_id = (string) self::transaction_meta( $order_id, 'stripe_payment_intent_id' );
		$paypal_id = (string) self::transaction_meta( $order_id, 'paypal_txn_id' );
		$paypal_id = '' !== $paypal_id ? $paypal_id : (string) self::transaction_meta( $order_id, 'txn_id' );

		$payment_code   = 'cash';
		$transaction_id = '';

		if ( '' !== $charge_id ) {
			$transaction_id = $charge_id;
			$payment_code   = 'stripe';
		} elseif ( '' !== $intent_id ) {
			$transaction_id = $intent_id;
			$payment_code   = 'stripe';
		} elseif ( '' !== $paypal_id ) {
			$transaction_id = $paypal_id;
			$payment_code   = 'paypal';
		} else {
			// LearnDash 4 records the gateway in `ld_payment_processor` (paypal_ipn, stripe, stripe_connect, razorpay,
			// …) and, since 4.5, the gateway's own payment data in `gateway_transaction`.
			$processor      = strtolower( trim( (string) self::transaction_meta( $order_id, 'ld_payment_processor' ) ) );
			$transaction_id = self::gateway_transaction_id( $order_id, $processor );

			if ( false !== strpos( $processor, 'stripe' ) ) {
				$payment_code   = 'stripe';
				$transaction_id = '' !== $transaction_id ? $transaction_id : (string) self::transaction_meta( $order_id, 'stripe_session_id' );
			} elseif ( false !== strpos( $processor, 'paypal' ) ) {
				$payment_code = 'paypal';
			} elseif ( false !== strpos( $processor, 'razorpay' ) ) {
				$payment_code = 'razorpay';
			} elseif ( '' !== sanitize_key( $processor ) ) {
				$payment_code = sanitize_key( $processor );
			}
		}

		$source_status = (string) $post->post_status;
		$status        = self::transaction_status( $order_id, $source_status );

		// Copy mode: the order items reference the MasterStudy copy of the course. A purchased LearnDash group
		// has no MasterStudy course item (reported below).
		$order_copy = Target::save_order(
			array(
				'order_id'       => $order_id,
				'user_id'        => $customer_id,
				'items'          => $course_copy
					? array(
						array(
							'course_id' => $course_copy,
							'price'     => $price,
						),
					)
					: array(),
				'status'         => $status,
				'date'           => (int) get_post_time( 'U', true, $post ),
				'total'          => $price,
				'currency'       => $currency,
				'payment_code'   => $payment_code,
				'transaction_id' => (string) $transaction_id,
			),
			self::SOURCE
		);

		// A deleted (trashed) transaction becomes a cancelled order in the trash.
		if ( 'trash' === $source_status ) {
			self::set_post_status( $order_copy, 'trash' );
		}

		Target::store_unmigrated_meta( $order_copy, 'ld_version', self::transaction_meta( $order_id, 'learndash_version' ) );
		Target::store_unmigrated_meta( $order_copy, 'ld_payment_status', (string) self::transaction_meta( $order_id, 'payment_status' ) );
		Target::store_unmigrated_meta( $order_copy, 'ld_coupon', self::transaction_meta( $order_id, 'coupon' ) );

		if ( ! $course_id ) {
			Report::add(
				Report::GROUP_ORDERS,
				array(
					'source_id' => $order_id,
					'title'     => $order_title,
					'type'      => __( 'LearnDash transaction', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'The transaction is not linked to a course, so the order was imported without items and with a zero total.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_PARTIAL,
					'post_id'   => $order_copy,
				)
			);
		} elseif ( ! $course_copy && ! $is_group ) {
			Report::add(
				Report::GROUP_ORDERS,
				array(
					'source_id' => $order_id,
					'title'     => $order_title,
					'type'      => __( 'LearnDash transaction', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'The purchased course no longer exists or was not migrated, so the order was imported without items.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_PARTIAL,
					'course'    => self::post_title( $course_id ),
					'post_id'   => $order_copy,
				)
			);
		} elseif ( self::is_subscription_transaction( $order_id ) ) {
			Report::add(
				Report::GROUP_ORDERS,
				array(
					'source_id' => $order_id,
					'title'     => $order_title,
					'type'      => __( 'LearnDash subscription payment', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'The payment was imported as a one-time order; the recurring subscription itself (gateway profile and future renewals) cannot be moved to MasterStudy — the student must re-subscribe to be billed again.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_PARTIAL,
					'course'    => self::post_title( $course_id ),
					'post_id'   => $order_copy,
				)
			);
		}

		if ( $is_group ) {
			Report::add(
				Report::GROUP_ORDERS,
				array(
					'source_id' => $order_id,
					'title'     => $order_title,
					'type'      => __( 'LearnDash group purchase', 'masterstudy-lms-learning-management-system' ),
					'reason'    => ProTarget::pro_active()
						? __( 'The purchased LearnDash group was imported as a MasterStudy group (its members are enrolled in the group courses); MasterStudy orders hold courses, so the order was imported with the amount paid but without items.', 'masterstudy-lms-learning-management-system' )
						: __( 'Importing LearnDash groups requires MasterStudy LMS Pro, which is not active; MasterStudy orders hold courses, so the group purchase was imported with the amount paid but without items.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_PARTIAL,
					'parent'    => self::post_title( $course_id ),
					'post_id'   => $order_copy,
				)
			);
		}
	}

	/**
	 * Migrate a single WPProQuiz quiz attempt (statistic_ref row + its statistic rows)
	 * to stm_lms_user_quizzes / stm_lms_user_answers of the quiz copy. The WpProQuiz rows are only read.
	 *
	 * @param int $ref_id Primary key of the wp_learndash_pro_quiz_statistic_ref row.
	 */
	public static function migrate_single_quiz_attempt( int $ref_id ): void {
		global $wpdb;

		$ref_table  = self::pro_quiz_table( 'statistic_ref' );
		$stat_table = self::pro_quiz_table( 'statistic' );

		$ref = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$ref_table} WHERE statistic_ref_id = %d", $ref_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		if ( ! $ref ) {
			return;
		}

		$stats = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$stat_table} WHERE statistic_ref_id = %d", $ref_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		$user_id = (int) $ref['user_id'];
		$quiz_id = (int) ( $ref['quiz_post_id'] ?? 0 );
		if ( ! $quiz_id && ! empty( $ref['quiz_id'] ) ) {
			$quiz_id = self::post_id_by_meta( 'quiz_pro_id', (int) $ref['quiz_id'], 'sfwd-quiz' );
		}

		$quiz_copy = $quiz_id ? Target::copy_of( self::SOURCE, $quiz_id ) : 0;
		$quiz_copy = $quiz_copy && PostType::QUIZ === get_post_type( $quiz_copy ) ? $quiz_copy : 0;

		$course_id = (int) ( $ref['course_post_id'] ?? 0 );
		$copy_id   = $course_id ? self::course_copy( $course_id ) : 0;
		if ( ! $course_id && $quiz_copy ) {
			$course_ids = Target::course_ids_of( $quiz_copy );
			$copy_id    = (int) reset( $course_ids );
			$course_id  = $copy_id ? Target::source_of( $copy_id ) : 0;
		}

		// A deleted student, a deleted quiz or a quiz that is in no migrated course: nothing to attach the
		// attempt to. Reported (the source statistics are kept) instead of failing the item.
		if ( ! $user_id || ! get_userdata( $user_id ) || ! $quiz_copy || ! $copy_id ) {
			Report::add(
				Report::GROUP_QUIZ_ATTEMPTS,
				array(
					'source_id' => $ref_id,
					'title'     => sprintf(
						/* translators: 1: attempt ID, 2: user label */
						__( 'Attempt #%1$d by %2$s', 'masterstudy-lms-learning-management-system' ),
						$ref_id,
						self::user_label( $user_id )
					),
					'type'      => __( 'Quiz attempt', 'masterstudy-lms-learning-management-system' ),
					'reason'    => sprintf(
						/* translators: 1: quiz title or ID, 2: course title or ID */
						__( 'The attempt cannot be imported: its student, quiz or course no longer exists or the quiz is not part of any migrated course (quiz: %1$s, course: %2$s). The LearnDash statistics were kept.', 'masterstudy-lms-learning-management-system' ),
						$quiz_id ? self::post_title( $quiz_id ) : __( 'missing', 'masterstudy-lms-learning-management-system' ),
						$course_id ? self::post_title( $course_id ) : __( 'missing', 'masterstudy-lms-learning-management-system' )
					),
					'status'    => Report::STATUS_UNSUPPORTED,
					'course'    => $course_id ? self::post_title( $course_id ) : '',
					'post_id'   => self::report_post_id( $quiz_id ),
				)
			);
			return;
		}

		$attempt_item = array(
			'source_id' => $ref_id,
			'title'     => sprintf(
				/* translators: 1: attempt ID, 2: user label */
				__( 'Attempt #%1$d by %2$s', 'masterstudy-lms-learning-management-system' ),
				$ref_id,
				self::user_label( $user_id )
			),
			/* translators: %s: quiz title */
			'parent'    => sprintf( __( 'Quiz: %s', 'masterstudy-lms-learning-management-system' ), self::post_title( $quiz_id ) ),
			'course'    => self::post_title( $course_id ),
			'post_id'   => $quiz_copy,
		);
		$lost_answers = 0;

		$total_questions = count( $stats );
		$total_correct   = 0;
		$earned_marks    = 0.0;
		$attempt_marks   = 0.0;
		$has_essay       = false;
		$answers         = array();

		$qm_table = self::pro_quiz_table( 'question' );

		foreach ( $stats as $stat ) {
			$correct        = (int) $stat['correct_count'];
			$total_correct += ( $correct >= 1 ) ? 1 : 0;
			$earned_marks  += (float) $stat['points'];

			// Source question post (or the WpProQuiz row of a legacy question) → its MasterStudy question.
			$source_question = (int) ( $stat['question_post_id'] ?? 0 );
			$pro_id          = (int) ( $stat['question_id'] ?? 0 );
			$pro_id          = $pro_id ? $pro_id : ( $source_question ? (int) get_post_meta( $source_question, 'question_pro_id', true ) : 0 );
			$question_id     = self::question_copy( $source_question, $pro_id );

			// Question type (essay check) and points of the questions this attempt showed.
			$is_essay = false;
			if ( $pro_id ) {
				$question_row = $wpdb->get_row(
					$wpdb->prepare( "SELECT answer_type, points FROM {$qm_table} WHERE id = %d", $pro_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					ARRAY_A
				);
				$attempt_marks += (float) ( $question_row['points'] ?? 0 );

				if ( in_array( $question_row['answer_type'] ?? '', array( 'essay', 'assessment_answer' ), true ) ) {
					$has_essay = true;
					$is_essay  = true;
				}
			}

			if ( $question_id ) {
				$answers[] = array(
					'question_id' => $question_id,
					'answer'      => self::build_user_answer( $question_id, $pro_id, $stat['answer_data'] ?? '', $user_id ),
					'correct'     => $correct >= 1,
				);
			} elseif ( ! $is_essay ) {
				// Essay/assessment questions are not imported (reported with the attempt below).
				++$lost_answers;
			}
		}

		// LearnDash's own result of this attempt (its random question subset, shared questions and graded essays
		// included) wins; otherwise the score is computed from the questions the attempt showed.
		$ld_result = self::attempt_result( $user_id, $ref_id );
		$ld_points = $ld_result['points'] ?? null;
		$ld_total  = $ld_result['total_points'] ?? null;

		if ( isset( $ld_result['percentage'] ) && is_numeric( $ld_result['percentage'] ) ) {
			$percent = (float) $ld_result['percentage'];
		} elseif ( is_numeric( $ld_points ) && is_numeric( $ld_total ) && (float) $ld_total > 0 ) {
			$percent = (float) $ld_points / (float) $ld_total * 100;
		} elseif ( $attempt_marks > 0 ) {
			$percent = $earned_marks / $attempt_marks * 100;
		} else {
			$percent = $total_questions > 0 ? $total_correct / $total_questions * 100 : 0;
		}

		$pass_mark = (float) get_post_meta( $quiz_copy, 'passing_grade', true );
		if ( $pass_mark <= 0 ) {
			$pass_mark = self::quiz_passing_percentage( $quiz_id );
		}

		if ( isset( $ld_result['pass'] ) && is_scalar( $ld_result['pass'] ) ) {
			$passed = filter_var( $ld_result['pass'], FILTER_VALIDATE_BOOLEAN );
		} elseif ( $has_essay ) {
			// MasterStudy has no "pending review" attempt state — an ungraded attempt does not pass.
			$passed = false;
		} elseif ( $pass_mark > 0 ) {
			$passed = round( $percent, 2 ) >= $pass_mark;
		} else {
			$passed = true;
		}

		if ( $has_essay ) {
			Helper::log( 'info', sprintf( 'LearnDash migration: quiz attempt %d contains essay/assessment questions — their answers were not imported.', $ref_id ) );
			Report::add(
				Report::GROUP_QUIZ_ATTEMPTS,
				array_merge(
					$attempt_item,
					array(
						'type'   => __( 'Attempt with essay/assessment answers', 'masterstudy-lms-learning-management-system' ),
						'reason' => isset( $ld_result['pass'] )
							? __( 'MasterStudy has no essay or survey questions, so their answers were not imported; the attempt keeps the LearnDash score and result (ungraded essays count as LearnDash counted them).', 'masterstudy-lms-learning-management-system' )
							: __( 'MasterStudy has no essay or survey questions and no "pending review" state, so this ungraded attempt was imported as not passed.', 'masterstudy-lms-learning-management-system' ),
						'status' => Report::STATUS_PARTIAL,
					)
				)
			);
		}

		$created_at = self::site_datetime( (int) $ref['create_time'] );

		// Idempotent: skip the insert when this attempt was already imported.
		if ( ! Target::quiz_attempt_exists( $user_id, $quiz_copy, $created_at, (float) $percent, (bool) $passed ) ) {
			Target::add_quiz_attempt( $user_id, $copy_id, $quiz_copy, (float) $percent, $passed, $created_at, $answers );
		}

		if ( $lost_answers > 0 ) {
			Report::add(
				Report::GROUP_QUIZ_ATTEMPTS,
				array_merge(
					$attempt_item,
					array(
						'type'   => __( 'Quiz attempt', 'masterstudy-lms-learning-management-system' ),
						'reason' => sprintf(
							/* translators: %d: number of answers */
							_n(
								'%d answer belongs to a question that no longer exists or was not imported, so it was left out (the score was kept).',
								'%d answers belong to questions that no longer exist or were not imported, so they were left out (the score was kept).',
								$lost_answers,
								'masterstudy-lms-learning-management-system'
							),
							$lost_answers
						),
						'status' => Report::STATUS_PARTIAL,
					)
				)
			);
		}

	}

	/**
	 * Migrate all sfwd-assignment submissions for a single lesson into one stm-assignments
	 * post (attached to the curriculum right after the lesson copy) plus one student submission
	 * (stm-user-assignment post + stm_lms_user_assignments row) per LearnDash upload.
	 * Requires MasterStudy LMS Pro. The LearnDash lesson and uploads are never modified.
	 *
	 * @param int $lesson_id The LD lesson (or topic) that owns the assignment.
	 */
	public static function migrate_single_assignment( int $lesson_id ): void {
		$lesson_copy = Target::copy_of( self::SOURCE, $lesson_id );

		if ( ! ProTarget::pro_active() ) {
			Helper::log( 'warning', sprintf( 'LearnDash migration: assignments of lesson %d skipped — MasterStudy LMS Pro is not active.', $lesson_id ) );
			Report::add(
				Report::GROUP_ASSIGNMENTS,
				array(
					'source_id' => $lesson_id,
					/* translators: %s: lesson title */
					'title'     => sprintf( __( '%s Assignment', 'masterstudy-lms-learning-management-system' ), self::post_title( $lesson_id ) ),
					'type'      => __( 'Lesson assignment', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'Importing assignments requires MasterStudy LMS Pro, which is not active — the assignment and its student submissions were left in LearnDash.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_UNSUPPORTED,
					/* translators: %s: lesson title */
					'parent'    => sprintf( __( 'Lesson: %s', 'masterstudy-lms-learning-management-system' ), self::post_title( $lesson_id ) ),
					'course'    => self::first_course_title( $lesson_id ),
					'post_id'   => self::report_post_id( $lesson_id ),
				)
			);
			return;
		}

		$source_type = 'sfwd-topic' === get_post_type( $lesson_id ) ? 'sfwd-topic' : 'sfwd-lessons';

		$lesson_meta    = get_post_meta( $lesson_id, '_' . $source_type, true );
		$lesson_meta    = is_array( $lesson_meta ) ? $lesson_meta : array();
		// LearnDash: lesson_assignment_points_enabled / lesson_assignment_points_amount; the unprefixed
		// assignment_points_* keys are accepted for data written by other tools.
		$points_on      = $lesson_meta[ $source_type . '_lesson_assignment_points_enabled' ] ?? ( $lesson_meta[ $source_type . '_assignment_points_enabled' ] ?? '' );
		$points_amount  = $lesson_meta[ $source_type . '_lesson_assignment_points_amount' ] ?? ( $lesson_meta[ $source_type . '_assignment_points_amount' ] ?? '' );
		$points_enabled = ! empty( $points_on );
		$total_points   = $points_enabled && (int) $points_amount > 0 ? (int) $points_amount : 100;
		// "Limit number of uploaded files" — every upload is one MasterStudy attempt.
		$upload_count = (int) ( $lesson_meta[ $source_type . '_assignment_upload_limit_count' ] ?? 0 );
		$due_date       = isset( $lesson_meta[ $source_type . '_assignment_due_date' ] ) ? $lesson_meta[ $source_type . '_assignment_due_date' ] : '';
		// LearnDash: assignment_upload_limit_size (e.g. "2M"); `max_upload_size` is accepted for other tools' data.
		$max_upload     = (string) ( $lesson_meta[ $source_type . '_assignment_upload_limit_size' ] ?? ( $lesson_meta[ $source_type . '_max_upload_size' ] ?? '' ) );
		$lesson_title   = self::post_title( $lesson_id );

		if ( $lesson_copy ) {
			Target::store_unmigrated_meta( $lesson_copy, 'ld_assignment_upload_limit_count', (string) ( $lesson_meta[ $source_type . '_assignment_upload_limit_count' ] ?? '' ) );
			Target::store_unmigrated_meta( $lesson_copy, 'ld_assignment_upload_limit_extensions', $lesson_meta[ $source_type . '_assignment_upload_limit_extensions' ] ?? '' );
		}

		// Idempotent: the same lesson always maps to the same assignment post.
		$assignment_id = Target::insert_post(
			array(
				'post_type'    => PostType::ASSIGNMENT,
				'post_status'  => 'publish',
				/* translators: %s: lesson title */
				'post_title'   => sprintf( __( '%s Assignment', 'masterstudy-lms-learning-management-system' ), get_post_field( 'post_title', $lesson_id ) ),
				/* translators: %s: lesson title */
				'post_content' => '<p>' . esc_html( sprintf( __( 'Upload your assignment for the lesson "%s".', 'masterstudy-lms-learning-management-system' ), $lesson_title ) ) . '</p>',
				'post_author'  => (int) get_post_field( 'post_author', $lesson_id ),
			),
			self::SOURCE,
			'ld-lesson-assignment-' . $lesson_id
		);

		// LearnDash has no passing grade — uploads are approved manually; the upload limit caps the attempts.
		ProTarget::set_assignment( $assignment_id, array( 'attempts' => max( 0, $upload_count ) ) );

		Target::store_unmigrated_meta( $assignment_id, 'ld_total_points', $total_points );
		Target::store_unmigrated_meta( $assignment_id, 'ld_max_file_upload_size', $max_upload );
		Target::store_unmigrated_meta( $assignment_id, 'ld_due_date', is_string( $due_date ) ? sanitize_text_field( $due_date ) : $due_date );
		Target::store_unmigrated_meta( $assignment_id, 'ld_lesson_id', $lesson_id );

		if ( ! empty( $due_date ) ) {
			Report::add(
				Report::GROUP_ASSIGNMENTS,
				array(
					'source_id' => $lesson_id,
					'title'     => get_post_field( 'post_title', $assignment_id ),
					'type'      => __( 'Assignment due date', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'MasterStudy assignments have no fixed due date (only a per-attempt time limit), so the LearnDash due date was not applied (it is kept as post meta).', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_PARTIAL,
					/* translators: %s: lesson title */
					'parent'    => sprintf( __( 'Lesson: %s', 'masterstudy-lms-learning-management-system' ), $lesson_title ),
					'course'    => self::first_course_title( $lesson_id ),
					'post_id'   => $assignment_id,
				)
			);
		}

		// MasterStudy reaches assignments only through the curriculum: place it after the lesson copy.
		$course_ids = $lesson_copy ? Target::course_ids_of( $lesson_copy ) : array();
		self::attach_after_material( $lesson_copy, $assignment_id, $lesson_id );

		// Chronological order: MasterStudy numbers attempts and reads the latest row per student.
		$submissions = get_posts(
			array(
				'post_type'      => 'sfwd-assignment',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => array(
					'date' => 'ASC',
					'ID'   => 'ASC',
				),
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => 'lesson_id',
						'value' => $lesson_id,
					),
				),
			)
		);

		foreach ( $submissions as $sub ) {
			$source_id = 'ld-assignment-' . $sub->ID;

			// Idempotent: already imported (also keeps a re-run from registering the file again).
			if ( Target::find_migrated_post( PostType::USER_ASSIGNMENT, self::SOURCE, $source_id ) ) {
				continue;
			}

			$student_id = (int) $sub->post_author;
			$course_id  = self::course_copy( (int) get_post_meta( $sub->ID, 'course_id', true ) );
			$course_id  = $course_id ? $course_id : (int) reset( $course_ids );

			$submission_item = array(
				'source_id' => $sub->ID,
				'title'     => sprintf(
					/* translators: 1: student label, 2: assignment title */
					__( '%1$s → %2$s', 'masterstudy-lms-learning-management-system' ),
					self::user_label( $student_id ),
					self::post_title( $assignment_id )
				),
				'type'      => __( 'Assignment submission', 'masterstudy-lms-learning-management-system' ),
				/* translators: %s: lesson title */
				'parent'    => sprintf( __( 'Lesson: %s', 'masterstudy-lms-learning-management-system' ), $lesson_title ),
				'course'    => $course_id ? self::post_title( $course_id ) : '',
				'post_id'   => $assignment_id,
			);

			if ( ! $student_id || ! get_userdata( $student_id ) ) {
				Report::add(
					Report::GROUP_ASSIGNMENTS,
					array_merge(
						$submission_item,
						array(
							'reason' => __( 'The student who uploaded this assignment no longer exists, so the submission was not imported.', 'masterstudy-lms-learning-management-system' ),
							'status' => Report::STATUS_FAILED,
						)
					)
				);
				continue;
			}

			if ( ! $course_id ) {
				Report::add(
					Report::GROUP_ASSIGNMENTS,
					array_merge(
						$submission_item,
						array(
							'reason' => __( 'The submission is not linked to a migrated course, so it was not imported.', 'masterstudy-lms-learning-management-system' ),
							'status' => Report::STATUS_FAILED,
						)
					)
				);
				continue;
			}

			// LearnDash only has "approved" (approval_status = 1) and "awaiting approval".
			$approved = (bool) get_post_meta( $sub->ID, 'approval_status', true );
			$points   = get_post_meta( $sub->ID, 'points', true );
			$grade    = ( $approved && $points_enabled && '' !== (string) $points && $total_points > 0 )
				? min( 100, max( 0, (float) $points / $total_points * 100 ) )
				: null;

			$upload_url = (string) get_post_meta( $sub->ID, 'upload', true );
			$upload_url = '' !== $upload_url ? $upload_url : (string) get_post_meta( $sub->ID, 'file_link', true );
			$attachment = self::assignment_attachment( $sub->ID, $student_id, $upload_url );

			$content = (string) $sub->post_content;
			if ( '' !== $upload_url && ! $attachment ) {
				// The file is not in the uploads folder — keep a link so the submission is not lost.
				$file_name = (string) get_post_meta( $sub->ID, 'file_name', true );
				$content  .= sprintf( '<p><a href="%s">%s</a></p>', esc_url( $upload_url ), esc_html( '' !== $file_name ? $file_name : basename( $upload_url ) ) );
			}

			$submission_id = ProTarget::add_assignment_submission(
				array(
					'assignment_id' => $assignment_id,
					'course_id'     => $course_id,
					'student_id'    => $student_id,
					'content'       => $content,
					'status'        => $approved ? 'passed' : 'pending',
					'grade'         => $grade,
					'review'        => self::assignment_feedback( $sub->ID, $student_id ),
					'attachments'   => $attachment ? array( $attachment ) : array(),
					'date'          => (int) get_post_time( 'U', true, $sub ),
					'source_id'     => $source_id,
				),
				self::SOURCE
			);

			Target::store_unmigrated_meta( $submission_id, 'ld_upload', $upload_url );
		}
	}

	// ──────────────────────────────────────────────────────────────────────────
	// Private helpers
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * Copy a LearnDash lesson or topic into a MasterStudy lesson (one copy per source post) and apply its
	 * settings and discussion comments to the copy.
	 *
	 * @param int        $lesson_id   Source lesson/topic post ID.
	 * @param string     $source_type sfwd-lessons|sfwd-topic.
	 * @param int        $course_id   Source course being migrated (for the migration report).
	 * @param array|null $drip        Receives the drip rule of the lesson (see lesson_drip()); empty when it has
	 *                                none or Pro is not active.
	 * @return int Lesson copy ID.
	 */
	private static function migrate_ld_lesson( int $lesson_id, string $source_type, int $course_id = 0, ?array &$drip = null ): int {
		$drip    = array();
		$copy_id = Target::copy_post( $lesson_id, PostType::LESSON, self::SOURCE );

		$meta = get_post_meta( $lesson_id, '_' . $source_type, true );
		$meta = is_array( $meta ) ? $meta : array();

		// Sample lessons are only available on lessons, not topics.
		$sample = 'sfwd-lessons' === $source_type && 'on' === ( $meta['sfwd-lessons_sample_lesson'] ?? '' );

		Target::set_lesson(
			$copy_id,
			array(
				'type'    => 'text',
				'preview' => $sample,
			)
		);

		$video_enabled = 'on' === ( $meta[ $source_type . '_lesson_video_enabled' ] ?? '' );
		$video         = trim( (string) ( $meta[ $source_type . '_lesson_video_url' ] ?? '' ) );

		if ( $video_enabled && '' !== $video ) {
			$source = ( '[' === substr( $video, 0, 1 ) ) ? 'shortcode' : Target::detect_video_source( $video );
			Target::set_lesson_video( $copy_id, $source, $video );

			// LearnDash "Video Progression": the video must be watched before the step can be completed.
			// MasterStudy tracks watching for YouTube, Vimeo and direct video links (not embeds/shortcodes).
			if ( in_array( $source, array( 'youtube', 'vimeo', 'external' ), true ) ) {
				self::update_post_meta_value( $copy_id, 'video_required_progress', 100 );
			}
		}

		self::collect_step_terms( $lesson_id, $copy_id, $course_id );
		self::migrate_lesson_comments( $lesson_id, $copy_id );

		$report_item = array(
			'source_id' => $lesson_id,
			'type'      => 'sfwd-topic' === $source_type
				? __( 'LearnDash topic', 'masterstudy-lms-learning-management-system' )
				: __( 'LearnDash lesson', 'masterstudy-lms-learning-management-system' ),
			'status'    => Report::STATUS_PARTIAL,
			'course'    => $course_id ? self::post_title( $course_id ) : '',
			'post_id'   => $copy_id,
		);

		if ( 'on' === ( $meta[ $source_type . '_lesson_materials_enabled' ] ?? '' ) ) {
			Target::store_unmigrated_meta( $copy_id, 'ld_lesson_materials', (string) ( $meta[ $source_type . '_lesson_materials' ] ?? '' ) );

			if ( '' !== trim( (string) ( $meta[ $source_type . '_lesson_materials' ] ?? '' ) ) ) {
				Report::add(
					Report::GROUP_LESSONS,
					array_merge(
						$report_item,
						array(
							/* translators: %s: "LearnDash lesson" or "LearnDash topic" */
							'type'   => sprintf( __( '%s materials', 'masterstudy-lms-learning-management-system' ), $report_item['type'] ),
							'reason' => __( 'LearnDash lesson materials have no MasterStudy equivalent — they were not shown on the lesson (kept as post meta).', 'masterstudy-lms-learning-management-system' ),
						)
					)
				);
			}
		}

		// Forced lesson timer: MasterStudy only shows a duration, it does not hold the student on the page.
		$timer = trim( (string) ( $meta[ $source_type . '_forced_lesson_time' ] ?? '' ) );
		if ( 'on' === ( $meta[ $source_type . '_forced_lesson_time_enabled' ] ?? ( '' !== $timer ? 'on' : '' ) ) && '' !== $timer ) {
			Target::store_unmigrated_meta( $copy_id, 'ld_forced_lesson_time', $timer );
			self::update_post_meta_value( $copy_id, 'duration', is_numeric( $timer ) ? self::seconds_label( (int) $timer ) : $timer );
			Report::add(
				Report::GROUP_LESSONS,
				array_merge(
					$report_item,
					array(
						/* translators: %s: "LearnDash lesson" or "LearnDash topic" */
						'type'   => sprintf( __( '%s timer', 'masterstudy-lms-learning-management-system' ), $report_item['type'] ),
						'reason' => __( 'MasterStudy cannot force students to stay on a lesson for a minimum time, so the LearnDash lesson timer was imported as the lesson duration only.', 'masterstudy-lms-learning-management-system' ),
					)
				)
			);
		} elseif ( (int) get_post_meta( $lesson_id, '_learndash_course_grid_duration', true ) > 0 ) {
			// Course Grid add-on duration (seconds) of a lesson/topic → the MasterStudy lesson duration.
			self::update_post_meta_value( $copy_id, 'duration', self::seconds_label( (int) get_post_meta( $lesson_id, '_learndash_course_grid_duration', true ) ) );
		}

		Target::store_unmigrated_meta( $copy_id, 'ld_visible_after', $meta[ $source_type . '_visible_after' ] ?? '' );
		Target::store_unmigrated_meta( $copy_id, 'ld_visible_after_specific_date', $meta[ $source_type . '_visible_after_specific_date' ] ?? '' );

		$rule = self::lesson_drip( $meta, $source_type );

		if ( empty( $rule ) ) {
			return $copy_id;
		}

		if ( ProTarget::pro_active() ) {
			self::apply_drip( $copy_id, $rule );
			$drip = $rule;
			return $copy_id;
		}

		Report::add(
			Report::GROUP_LESSONS,
			array_merge(
				$report_item,
				array(
					/* translators: %s: "LearnDash lesson" or "LearnDash topic" */
					'type'   => sprintf( __( '%s drip schedule', 'masterstudy-lms-learning-management-system' ), $report_item['type'] ),
					'reason' => __( 'Importing the LearnDash drip schedule (available X days after enrollment or on a specific date) requires MasterStudy LMS Pro, which is not active, so it was not applied (kept as post meta).', 'masterstudy-lms-learning-management-system' ),
				)
			)
		);

		return $copy_id;
	}

	/**
	 * Copy the discussion of a LearnDash lesson/topic (regular WordPress comments) to its MasterStudy copy —
	 * MasterStudy lesson discussions are comments on the lesson. Threads and moderation status (pending, spam,
	 * trash) are kept; the source comments are not modified. Idempotent per source comment.
	 *
	 * @param int $lesson_id Source lesson/topic post ID.
	 * @param int $copy_id   MasterStudy lesson copy.
	 */
	private static function migrate_lesson_comments( int $lesson_id, int $copy_id ): void {
		global $wpdb;

		$comments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->comments} WHERE comment_post_ID = %d AND comment_type IN ('', 'comment') ORDER BY comment_ID ASC",
				$lesson_id
			)
		);

		if ( empty( $comments ) ) {
			return;
		}

		// Source comment ID => its copy (from an earlier run, or created below).
		$map    = array();
		$prefix = self::SOURCE . ':';
		$rows   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.comment_id, m.meta_value FROM {$wpdb->commentmeta} m
				 INNER JOIN {$wpdb->comments} c ON c.comment_ID = m.comment_id
				 WHERE c.comment_post_ID = %d AND m.meta_key = %s",
				$copy_id,
				self::COMMENT_SOURCE_META
			)
		);

		foreach ( (array) $rows as $row ) {
			if ( 0 === strpos( (string) $row->meta_value, $prefix ) ) {
				$map[ (int) substr( (string) $row->meta_value, strlen( $prefix ) ) ] = (int) $row->comment_id;
			}
		}

		$statuses = array(
			'0'            => '0',
			'1'            => '1',
			'spam'         => 'spam',
			'trash'        => 'trash',
			'post-trashed' => 'trash',
		);

		foreach ( $comments as $comment ) {
			$source_comment = (int) $comment->comment_ID;

			if ( isset( $map[ $source_comment ] ) ) {
				continue;
			}

			$copy_comment = Target::add_discussion(
				array(
					'post_id'  => $copy_id,
					'user_id'  => (int) $comment->user_id,
					'content'  => (string) $comment->comment_content,
					'date'     => (string) $comment->comment_date,
					'date_gmt' => (string) $comment->comment_date_gmt,
					'parent'   => $map[ (int) $comment->comment_parent ] ?? 0,
					'status'   => $statuses[ (string) $comment->comment_approved ] ?? '0',
				)
			);

			if ( ! $copy_comment ) {
				continue;
			}

			add_comment_meta( $copy_comment, self::COMMENT_SOURCE_META, $prefix . $source_comment, true );

			// Guests: keep the author name / e-mail (add_discussion() takes them from the user account).
			if ( ! (int) $comment->user_id ) {
				$wpdb->update(
					$wpdb->comments,
					array(
						'comment_author'       => (string) $comment->comment_author,
						'comment_author_email' => (string) $comment->comment_author_email,
						'comment_author_url'   => (string) $comment->comment_author_url,
					),
					array( 'comment_ID' => $copy_comment )
				);
				clean_comment_cache( $copy_comment );
			}

			$map[ $source_comment ] = $copy_comment;
		}
	}

	/**
	 * LearnDash lesson drip rule: "visible N days after enrollment" wins over "visible on a date",
	 * as in LearnDash. `lesson_schedule` (LearnDash 3+) picks the active one when set.
	 *
	 * @param array  $meta        `_sfwd-lessons` / `_sfwd-topic` settings.
	 * @param string $source_type sfwd-lessons|sfwd-topic.
	 * @return array ['days' => int] | ['date' => Unix time] | [] when not dripped.
	 */
	private static function lesson_drip( array $meta, string $source_type ): array {
		$schedule = (string) ( $meta[ $source_type . '_lesson_schedule' ] ?? '' );
		$days     = (int) ( $meta[ $source_type . '_visible_after' ] ?? 0 );
		$date     = self::drip_timestamp( $meta[ $source_type . '_visible_after_specific_date' ] ?? '' );

		if ( $days > 0 && 'visible_after_specific_date' !== $schedule ) {
			return array( 'days' => $days );
		}

		if ( $date > 0 && 'visible_after' !== $schedule ) {
			return array( 'date' => $date );
		}

		return array();
	}

	/**
	 * Unix time of a LearnDash "visible after specific date" value: a timestamp (LearnDash 3+),
	 * a date-picker array (aa/mm/jj/hh/mn) or a date string in site time.
	 *
	 * @param mixed $value Raw setting.
	 */
	private static function drip_timestamp( $value ): int {
		if ( is_array( $value ) ) {
			if ( empty( $value['aa'] ) || empty( $value['mm'] ) || empty( $value['jj'] ) ) {
				return 0;
			}

			$value = sprintf( '%04d-%02d-%02d %02d:%02d:00', (int) $value['aa'], (int) $value['mm'], (int) $value['jj'], (int) ( $value['hh'] ?? 0 ), (int) ( $value['mn'] ?? 0 ) );
		}

		$value = trim( (string) $value );

		if ( '' === $value ) {
			return 0;
		}

		if ( is_numeric( $value ) ) {
			return max( 0, (int) $value );
		}

		try {
			return ( new \DateTime( $value, wp_timezone() ) )->getTimestamp();
		} catch ( \Exception $e ) {
			return 0;
		}
	}

	/**
	 * Apply a LearnDash drip rule to a MasterStudy curriculum item (Pro "sequential_drip_content").
	 *
	 * @param int   $item_id Lesson or quiz post ID.
	 * @param array $drip    Rule from lesson_drip().
	 */
	private static function apply_drip( int $item_id, array $drip ): void {
		if ( empty( $drip ) || ! ProTarget::pro_active() ) {
			return;
		}

		if ( ! empty( $drip['days'] ) ) {
			ProTarget::drip_after_days( $item_id, (int) $drip['days'] );
		} elseif ( ! empty( $drip['date'] ) ) {
			ProTarget::drip_on_date( $item_id, (int) $drip['date'] );
		}
	}

	/**
	 * Migrate quiz settings and questions of a LearnDash quiz onto its MasterStudy copy.
	 *
	 * Question posts (sfwd-question) are copied to stm-questions (one copy per source question, shared by
	 * every quiz that uses it). Handles single-choice, multiple-choice, true/false, free answer (→ keywords),
	 * fill-in-the-blanks, sortable, and matching types. Essay, assessment and unknown types are not copied
	 * (MasterStudy cannot grade them) — they are reported and stay in LearnDash.
	 *
	 * @param int $quiz_id   Source quiz post ID.
	 * @param int $quiz_copy MasterStudy copy of the quiz.
	 * @param int $course_id Source course being migrated (for the migration report).
	 */
	private static function migrate_ld_quiz( int $quiz_id, int $quiz_copy, int $course_id = 0 ): void {
		global $wpdb;

		$course_title = $course_id ? self::post_title( $course_id ) : '';
		/* translators: %s: quiz title */
		$quiz_parent = sprintf( __( 'Quiz: %s', 'masterstudy-lms-learning-management-system' ), self::post_title( $quiz_id ) );

		$settings = array();

		// Read quiz_master row for quiz-level settings.
		$quiz_pro_id = (int) get_post_meta( $quiz_id, 'quiz_pro_id', true );
		$master      = null;
		if ( $quiz_pro_id ) {
			$master_table = self::pro_quiz_table( 'master' );
			$master       = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$master_table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$quiz_pro_id
				),
				ARRAY_A
			);
			if ( $master ) {
				// WpProQuiz time_limit is in seconds.
				if ( (int) $master['time_limit'] > 0 ) {
					$settings['duration_minutes'] = (int) ceil( (int) $master['time_limit'] / 60 );
				}
				// "Run only once" — WpProQuiz quiz_run_once_type: 1 = all users, 2 = registered users only,
				// 3 = anonymous users only (no limit for students). 'user' is accepted for other tools' data.
				$run_once_type        = (string) ( $master['quiz_run_once_type'] ?? '' );
				$settings['attempts'] = ( '1' === (string) ( $master['quiz_run_once'] ?? '' ) && in_array( $run_once_type, array( '1', '2', 'user' ), true ) ) ? 1 : 0;

				// Randomise question order when configured in LearnDash.
				$settings['random_questions'] = '1' === (string) ( $master['question_random'] ?? '' );
			}
		}

		$sfwd_meta = get_post_meta( $quiz_id, '_sfwd-quiz', true );
		$sfwd_meta = is_array( $sfwd_meta ) ? $sfwd_meta : array();
		$pass_mark = self::quiz_passing_percentage( $quiz_id );
		if ( $pass_mark > 0 ) {
			$settings['passing_grade'] = $pass_mark;
		}

		// LearnDash "repeats" (number of retakes allowed) when run-once is not set. LearnDash 3+ only
		// applies it with "Restrict quiz retakes" (retry_restrictions) on; older versions have no switch.
		$retry_restricted = ! isset( $sfwd_meta['sfwd-quiz_retry_restrictions'] ) || 'on' === $sfwd_meta['sfwd-quiz_retry_restrictions'];
		if ( empty( $settings['attempts'] ) && $retry_restricted && isset( $sfwd_meta['sfwd-quiz_repeats'] ) && '' !== (string) $sfwd_meta['sfwd-quiz_repeats'] ) {
			$settings['attempts'] = (int) $sfwd_meta['sfwd-quiz_repeats'] + 1;
		}

		Target::set_quiz( $quiz_copy, $settings );

		self::collect_step_terms( $quiz_id, $quiz_copy, $course_id );
		self::report_quiz_extras( $quiz_id, $quiz_copy, $quiz_pro_id, is_array( $master ) ? $master : array(), $sfwd_meta, $course_title );

		if ( ! empty( $sfwd_meta['sfwd-quiz_certificate'] ) ) {
			Target::store_unmigrated_meta( $quiz_copy, 'ld_quiz_certificate', $sfwd_meta['sfwd-quiz_certificate'] );
			Report::add(
				Report::GROUP_QUIZZES,
				array(
					'source_id' => $quiz_id,
					'title'     => self::post_title( $quiz_id ),
					'type'      => __( 'Quiz certificate', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'MasterStudy issues certificates for completed courses only, so the LearnDash quiz certificate was not carried over (kept as post meta).', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_UNSUPPORTED,
					'course'    => $course_title,
					'post_id'   => $quiz_copy,
				)
			);
		}

		// WpProQuiz quiz_modus 0-2 show one question at a time; 3 lists them on one page (split by questions_per_page).
		if ( $master && ( ( isset( $master['quiz_modus'] ) && 3 !== (int) $master['quiz_modus'] ) || (int) ( $master['questions_per_page'] ?? 0 ) > 0 ) ) {
			self::update_post_meta_value( $quiz_copy, 'quiz_style', 'pagination' );
		}

		$q_table = self::pro_quiz_table( 'question' );

		// Report item shared by all questions of this quiz.
		$question_item = array(
			'parent' => $quiz_parent,
			'course' => $course_title,
		);

		$question_ids = get_post_meta( $quiz_id, 'ld_quiz_questions', true );
		if ( empty( $question_ids ) || ! is_array( $question_ids ) ) {
			// Legacy quizzes (before LearnDash question posts) keep their questions only in the WpProQuiz
			// table — create a MasterStudy question post for each live row MasterStudy can use.
			$ms_question_ids = $quiz_pro_id ? self::migrate_legacy_questions( $quiz_id, $quiz_pro_id, $question_item ) : array();

			if ( ! $quiz_pro_id || ! self::has_legacy_questions( $quiz_pro_id ) ) {
				Report::add(
					Report::GROUP_QUIZZES,
					array(
						'source_id' => $quiz_id,
						'title'     => self::post_title( $quiz_id ),
						'type'      => __( 'LearnDash quiz', 'masterstudy-lms-learning-management-system' ),
						'reason'    => __( 'The LearnDash quiz has no questions, so it was imported as an empty quiz.', 'masterstudy-lms-learning-management-system' ),
						'status'    => Report::STATUS_PARTIAL,
						'course'    => $course_title,
						'post_id'   => $quiz_copy,
					)
				);
			}

			Target::set_quiz_questions( $quiz_copy, $ms_question_ids );
			return;
		}

		$ms_question_ids = array();

		foreach ( array_keys( $question_ids ) as $question_post_id ) {
			$question_post_id = (int) $question_post_id;

			$pro_id = (int) get_post_meta( $question_post_id, 'question_pro_id', true );
			$pro_id = $pro_id ? $pro_id : (int) $question_ids[ $question_post_id ];
			$row    = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$q_table} WHERE id = %d", $pro_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				ARRAY_A
			);

			if ( ! $row || ! get_post( $question_post_id ) ) {
				Helper::log( 'warning', sprintf( 'LearnDash migration: question %d of quiz %d skipped — question post or WpProQuiz row %d not found.', $question_post_id, $quiz_id, $pro_id ) );
				Report::add(
					Report::GROUP_QUESTIONS,
					array_merge(
						$question_item,
						array(
							'source_id' => $question_post_id,
							'title'     => $row
								? self::question_title( $row )
								/* translators: %d: question ID */
								: sprintf( __( 'Question #%d', 'masterstudy-lms-learning-management-system' ), $question_post_id ),
							'type'      => $row ? self::question_type_label( (string) $row['answer_type'] ) : __( 'Unknown', 'masterstudy-lms-learning-management-system' ),
							'reason'    => $row
								? __( 'The LearnDash question post no longer exists, so the question was not added to the quiz.', 'masterstudy-lms-learning-management-system' )
								: __( 'The question data is missing from the LearnDash (WpProQuiz) questions table, so the question was not added to the quiz.', 'masterstudy-lms-learning-management-system' ),
							'status'    => Report::STATUS_FAILED,
							'post_id'   => get_post( $question_post_id ) ? $question_post_id : 0,
						)
					)
				);
				continue;
			}

			$question_copy = self::migrate_question_row( $question_post_id, $row, $question_item );

			if ( $question_copy ) {
				$ms_question_ids[] = $question_copy;
			}
		}

		Target::set_quiz_questions( $quiz_copy, $ms_question_ids );
	}

	/**
	 * Report quiz settings MasterStudy cannot reproduce: a random subset of questions, quiz materials,
	 * quiz prerequisites, custom form fields, the leaderboard and weighted question/answer points.
	 *
	 * @param int    $quiz_id      Source quiz post ID.
	 * @param int    $quiz_copy    MasterStudy copy of the quiz.
	 * @param int    $quiz_pro_id  WpProQuiz quiz ID.
	 * @param array  $master       WpProQuiz quiz_master row (empty when missing).
	 * @param array  $sfwd_meta    `_sfwd-quiz` settings.
	 * @param string $course_title Course title for the report.
	 */
	private static function report_quiz_extras( int $quiz_id, int $quiz_copy, int $quiz_pro_id, array $master, array $sfwd_meta, string $course_title ): void {
		global $wpdb;

		$lost = array();

		if ( ! empty( $master['show_max_question'] ) && (int) ( $master['show_max_question_value'] ?? 0 ) > 0 ) {
			/* translators: %d: number of questions */
			$lost[] = sprintf( __( 'LearnDash showed only %d random questions per attempt; MasterStudy shows all questions', 'masterstudy-lms-learning-management-system' ), (int) $master['show_max_question_value'] );
		}

		if ( 'on' === ( $sfwd_meta['sfwd-quiz_quiz_materials_enabled'] ?? '' ) && '' !== trim( (string) ( $sfwd_meta['sfwd-quiz_quiz_materials'] ?? '' ) ) ) {
			Target::store_unmigrated_meta( $quiz_copy, 'ld_quiz_materials', (string) $sfwd_meta['sfwd-quiz_quiz_materials'] );
			$lost[] = __( 'quiz materials (kept as post meta)', 'masterstudy-lms-learning-management-system' );
		}

		if ( ! empty( $master['prerequisite'] ) ) {
			$lost[] = __( 'quizzes that must be passed first (quiz prerequisites)', 'masterstudy-lms-learning-management-system' );
		}

		if ( ! empty( $master['form_activated'] ) ) {
			$lost[] = __( 'custom form fields asked before or after the quiz', 'masterstudy-lms-learning-management-system' );
		}

		if ( ! empty( $master['toplist_activated'] ) ) {
			$lost[] = __( 'the leaderboard', 'masterstudy-lms-learning-management-system' );
		}

		if ( $quiz_pro_id ) {
			$points = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT COUNT(DISTINCT points) AS kinds, MAX(answer_points_activated) AS per_answer FROM ' . self::pro_quiz_table( 'question' ) . ' WHERE quiz_id = %d AND online = 1', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$quiz_pro_id
				),
				ARRAY_A
			);

			if ( $points && ( (int) $points['kinds'] > 1 || ! empty( $points['per_answer'] ) ) ) {
				$lost[] = __( 'weighted question/answer points (MasterStudy scores every question equally)', 'masterstudy-lms-learning-management-system' );
			}
		}

		if ( empty( $lost ) ) {
			return;
		}

		Report::add(
			Report::GROUP_QUIZZES,
			array(
				'source_id' => $quiz_id,
				'title'     => self::post_title( $quiz_id ),
				'type'      => __( 'Quiz settings', 'masterstudy-lms-learning-management-system' ),
				/* translators: %s: list of quiz features */
				'reason'    => sprintf( __( 'MasterStudy quizzes have no equivalent for: %s.', 'masterstudy-lms-learning-management-system' ), implode( '; ', $lost ) ),
				'status'    => Report::STATUS_PARTIAL,
				'course'    => $course_title,
				'post_id'   => $quiz_copy,
			)
		);
	}

	/**
	 * Question categories: LearnDash `ld_question_category` terms (read from the source question) and the
	 * WpProQuiz question category become MasterStudy question categories of the copy.
	 *
	 * @param int   $question_copy    MasterStudy question.
	 * @param int   $question_post_id Source sfwd-question post ID (0 for a legacy WpProQuiz-only question).
	 * @param array $row              WpProQuiz question row.
	 */
	private static function migrate_question_categories( int $question_copy, int $question_post_id, array $row ): void {
		global $wpdb;

		$names = $question_post_id ? self::term_names( $question_post_id, 'ld_question_category' ) : array();

		if ( ! empty( $row['category_id'] ) ) {
			$table = self::pro_quiz_table( 'category' );

			if ( self::table_exists( $table ) ) {
				$names[] = (string) $wpdb->get_var( $wpdb->prepare( "SELECT category_name FROM {$table} WHERE category_id = %d", (int) $row['category_id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}

		$names = array_values( array_unique( array_filter( array_map( 'trim', $names ), 'strlen' ) ) );

		if ( ! empty( $names ) ) {
			Target::set_question_categories( $question_copy, $names );
		}

		if ( $question_post_id ) {
			Target::store_unmigrated_meta( $question_copy, 'ld_question_tags', self::term_names( $question_post_id, 'ld_question_tag' ) );
		}
	}

	/**
	 * Copy one LearnDash question (sfwd-question post + WpProQuiz row) into a MasterStudy question — one copy
	 * per source question. A legacy question that only exists as a WpProQuiz row becomes a new question post.
	 * Essay, assessment and unknown types are not imported: they are reported and stay in LearnDash.
	 *
	 * @param int         $question_post_id sfwd-question post ID (0 = legacy WpProQuiz-only question).
	 * @param array       $row              WpProQuiz question row.
	 * @param array       $question_item    Report fields shared by the quiz questions (parent, course).
	 * @param string|null $status           Post status of the copy (null keeps the source status).
	 * @param int         $quiz_id          Source quiz (author of a legacy question).
	 * @return int MasterStudy question ID, 0 when the question was not imported.
	 */
	private static function migrate_question_row( int $question_post_id, array $row, array $question_item, ?string $status = 'publish', int $quiz_id = 0 ): int {
		$answer_objects = self::answer_objects( $row['answer_data'] );
		$answers        = array();
		$partial        = array();
		$accepted       = array();

		// Map all WPProQuiz answer types to MasterStudy question types.
		switch ( $row['answer_type'] ) {
			case 'single':
				$question_type = 'single_choice';
				foreach ( $answer_objects as $ao ) {
					$answers[] = array(
						'text'    => $ao['answer'],
						'correct' => $ao['correct'],
					);
				}
				break;

			case 'multiple':
				$question_type = 'multi_choice';
				foreach ( $answer_objects as $ao ) {
					$answers[] = array(
						'text'    => $ao['answer'],
						'correct' => $ao['correct'],
					);
				}
				break;

			case 'bool':
				// Not a WpProQuiz type (LearnDash true/false is a 'single' question) — kept for data written by other tools.
				$question_type = 'true_false';
				$true_correct  = true;
				foreach ( $answer_objects as $idx => $ao ) {
					if ( $ao['correct'] ) {
						$label        = strtolower( trim( wp_strip_all_tags( $ao['answer'] ) ) );
						$true_correct = 'false' === $label ? false : ( 'true' === $label ? true : 0 === $idx );
						break;
					}
				}
				$answers[] = array( 'correct' => $true_correct );
				break;

			case 'free_answer':
				// Free text answer — LearnDash accepts any of the newline-separated answers;
				// MasterStudy keywords compares one expected keyword.
				$question_type = 'keywords';
				$accepted      = isset( $answer_objects[0] ) ? preg_split( '/\r\n|\r|\n/', $answer_objects[0]['answer'] ) : array();
				$accepted      = array_values( array_filter( array_map( 'trim', (array) $accepted ), 'strlen' ) );
				if ( ! empty( $accepted ) ) {
					$answers[] = array( 'text' => $accepted[0] );
				}
				if ( count( $accepted ) > 1 ) {
					$partial[] = sprintf(
						/* translators: %s: the accepted answer that was kept */
						__( 'MasterStudy keyword questions accept a single answer — only "%s" was kept; the other accepted answers were dropped (kept as post meta).', 'masterstudy-lms-learning-management-system' ),
						$accepted[0]
					);
				}
				break;

			case 'cloze_answer':
				// Fill-in-the-blanks — convert {correct_value} tokens to MasterStudy |correct_value| blanks.
				$question_type = 'fill_the_gap';
				$cloze         = isset( $answer_objects[0] ) && false !== strpos( $answer_objects[0]['answer'], '{' )
					? $answer_objects[0]['answer']
					: (string) $row['question'];
				$answers[]     = array( 'text' => self::cloze_to_gaps( $cloze ) );

				// Several accepted variants in one blank ("{[green][lime]}") — only the first is kept.
				if ( preg_match( '/\{[^}]*\][^}]*\[/', $cloze ) ) {
					$partial[] = __( 'Some blanks accept several answers in LearnDash; MasterStudy accepts one per blank, so only the first variant was kept.', 'masterstudy-lms-learning-management-system' );
				}
				break;

			case 'sort_answer':
				// Sortable — answers are stored in the correct order; _sortString wins when set.
				$question_type = 'sortable';
				foreach ( $answer_objects as $ao ) {
					$answers[] = array(
						'text' => '' !== $ao['sort_string'] ? $ao['sort_string'] : $ao['answer'],
					);
				}
				break;

			case 'matrix_sort_answer':
			case 'matrix_sort':
				// Matrix sorting (WpProQuiz 'matrix_sort_answer') — left column (criterion) from _answer,
				// right column (element to drop) from _sortString.
				$question_type = 'item_match';
				foreach ( $answer_objects as $ao ) {
					$answers[] = array(
						'prompt' => $ao['answer'],
						'match'  => $ao['sort_string'],
					);
				}
				break;

			default:
				// essay, assessment and any unknown types — MasterStudy cannot grade them: not imported.
				Helper::log(
					'warning',
					sprintf( 'LearnDash migration: question %d (type "%s") has no MasterStudy equivalent — not imported.', $question_post_id ? $question_post_id : (int) $row['id'], $row['answer_type'] )
				);
				Report::add(
					Report::GROUP_QUESTIONS,
					array_merge(
						$question_item,
						array(
							'source_id' => $question_post_id ? $question_post_id : 'ld-pro-question-' . (int) $row['id'],
							'title'     => self::question_title( $row ),
							'type'      => self::question_type_label( (string) $row['answer_type'] ),
							'reason'    => self::unsupported_question_reason( (string) $row['answer_type'] ),
							'status'    => Report::STATUS_UNSUPPORTED,
							'post_id'   => $question_post_id && get_post( $question_post_id ) ? $question_post_id : 0,
						)
					)
				);
				return 0;
		}

		// MasterStudy shows post_title as the question text.
		$title = sanitize_text_field( wp_strip_all_tags( (string) $row['question'] ) );

		if ( $question_post_id ) {
			$overrides = array();

			if ( '' !== $title ) {
				$overrides['post_title'] = $title;
			}

			if ( null !== $status ) {
				$overrides['post_status'] = $status;
			}

			$question_copy = Target::copy_post( $question_post_id, PostType::QUESTION, self::SOURCE, $overrides );
		} else {
			$title = '' !== $title ? $title : sanitize_text_field( (string) $row['title'] );

			// Idempotent per WpProQuiz question row.
			$question_copy = Target::insert_post(
				array(
					'post_type'    => PostType::QUESTION,
					'post_status'  => null !== $status ? $status : 'publish',
					'post_title'   => '' !== $title ? $title : __( 'Question', 'masterstudy-lms-learning-management-system' ),
					'post_content' => wp_kses_post( (string) $row['question'] ),
					'post_author'  => $quiz_id ? (int) get_post_field( 'post_author', $quiz_id ) : 0,
				),
				self::SOURCE,
				self::legacy_question_key( (int) $row['id'] )
			);
		}

		Target::store_unmigrated_meta( $question_copy, 'ld_points', $row['points'] );

		if ( count( $accepted ) > 1 ) {
			Target::store_unmigrated_meta( $question_copy, 'ld_accepted_answers', $accepted );
		}

		foreach ( $partial as $reason ) {
			Report::add(
				Report::GROUP_QUESTIONS,
				array_merge(
					$question_item,
					array(
						'source_id' => $question_post_id ? $question_post_id : 'ld-pro-question-' . (int) $row['id'],
						'title'     => self::question_title( $row ),
						'type'      => self::question_type_label( (string) $row['answer_type'] ),
						'reason'    => $reason,
						'status'    => Report::STATUS_PARTIAL,
						'post_id'   => $question_copy,
					)
				)
			);
		}

		// Choice, sorting and matching questions without answers cannot be answered in MasterStudy.
		if ( empty( $answers ) ) {
			Report::add(
				Report::GROUP_QUESTIONS,
				array_merge(
					$question_item,
					array(
						'source_id' => $question_post_id ? $question_post_id : 'ld-pro-question-' . (int) $row['id'],
						'title'     => self::question_title( $row ),
						'type'      => self::question_type_label( (string) $row['answer_type'] ),
						'reason'    => __( 'The LearnDash question has no answers, so it was added to the quiz without answers — edit it before students take the quiz.', 'masterstudy-lms-learning-management-system' ),
						'status'    => Report::STATUS_PARTIAL,
						'post_id'   => $question_copy,
					)
				)
			);
		}

		$extra = array();

		// MasterStudy shows one explanation whatever the answer: the "correct" message, plus the
		// "incorrect" one when LearnDash shows a different text for wrong answers.
		$explanation = array_filter( array( trim( (string) ( $row['correct_msg'] ?? '' ) ) ) );
		$incorrect   = trim( (string) ( $row['incorrect_msg'] ?? '' ) );

		if ( '' !== $incorrect && empty( $row['correct_same_text'] ) && ! in_array( $incorrect, $explanation, true ) ) {
			$explanation[] = $incorrect;
		}

		if ( ! empty( $explanation ) ) {
			$extra['explanation'] = implode( "\n\n", $explanation );
		}

		if ( ! empty( $row['tip_enabled'] ) && ! empty( $row['tip_msg'] ) ) {
			$extra['hint'] = (string) $row['tip_msg'];
		}

		Target::set_question( $question_copy, $question_type, $answers, $extra );

		self::migrate_question_categories( $question_copy, $question_post_id, $row );

		return $question_copy;
	}

	/**
	 * Apply the LearnDash course settings (read from the source course) to the MasterStudy copy.
	 *
	 * @param int $course_id Source course ID.
	 * @param int $copy_id   MasterStudy copy of the course.
	 */
	private static function update_masterstudy_course_from_ld( int $course_id, int $copy_id ): void {
		$meta = get_post_meta( $course_id, '_sfwd-courses', true );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}

		$price        = self::course_price( $course_id );
		$max_students = absint( isset( $meta['sfwd-courses_course_seats_limit'] ) ? $meta['sfwd-courses_course_seats_limit'] : 0 );
		$price_type   = isset( $meta['sfwd-courses_course_price_type'] ) ? $meta['sfwd-courses_course_price_type'] : 'open';
		$disable_toc  = isset( $meta['sfwd-courses_course_disable_content_table'] ) ? $meta['sfwd-courses_course_disable_content_table'] : '';

		// Course-level settings that could not be carried over are reported as partial imports.
		$report_course = self::course_reporter( $course_id, $copy_id );

		// "Buy now" and "recurring" courses are paid, open/free are free. A closed course is never open to
		// self-enrollment: see migrate_closed_course().
		$is_paid = ( 'paynow' === $price_type || 'subscribe' === $price_type );

		if ( 'closed' !== $price_type ) {
			Target::set_pricing( $copy_id, $is_paid ? $price : 0.0 );
		}

		Target::store_unmigrated_meta( $copy_id, 'ld_price_type', $price_type );
		Target::store_unmigrated_meta( $copy_id, 'ld_enrollment_limit', $max_students );

		if ( $max_students > 0 ) {
			$report_course(
				__( 'Course seats limit', 'masterstudy-lms-learning-management-system' ),
				/* translators: %d: number of seats */
				sprintf( __( 'MasterStudy has no per-course seats limit, so the LearnDash limit of %d students was not applied (kept as post meta).', 'masterstudy-lms-learning-management-system' ), $max_students )
			);
		}
		// disable_content_table='on' means the TOC is hidden.
		Target::store_unmigrated_meta( $copy_id, 'ld_disable_content_table', $disable_toc );

		if ( 'subscribe' === $price_type ) {
			Target::store_unmigrated_meta(
				$copy_id,
				'ld_billing_cycle',
				trim( ( $meta['sfwd-courses_course_price_billing_p3'] ?? '' ) . ' ' . ( $meta['sfwd-courses_course_price_billing_t3'] ?? '' ) )
			);

			if ( ProTarget::plus_active() && $price > 0 ) {
				self::migrate_recurring_price( $course_id, $copy_id, $meta, $price, $report_course );
			} else {
				$report_course(
					__( 'Recurring (subscription) price', 'masterstudy-lms-learning-management-system' ),
					$price > 0
						? __( 'Recurring course pricing requires MasterStudy LMS Pro Plus, which is not active, so the subscription price was imported as a one-time price (billing cycle kept as post meta).', 'masterstudy-lms-learning-management-system' )
						: __( 'The LearnDash recurring price is empty, so the course was imported as free (billing cycle kept as post meta).', 'masterstudy-lms-learning-management-system' )
				);
			}
		}

		if ( 'closed' === $price_type ) {
			self::migrate_closed_course( $copy_id, $meta, $price, $report_course );
		}

		// LearnDash 4 custom enrollment URL of a "buy now" / recurring course (external checkout page).
		$enroll_url = $is_paid ? trim( (string) ( $meta[ 'sfwd-courses_course_price_type_' . $price_type . '_enrollment_url' ] ?? '' ) ) : '';
		if ( '' !== $enroll_url ) {
			Target::store_unmigrated_meta( $copy_id, 'ld_enrollment_url', esc_url_raw( $enroll_url ) );
			$report_course(
				__( 'Custom enrollment URL', 'masterstudy-lms-learning-management-system' ),
				__( 'The course was imported with its price and the MasterStudy checkout; the LearnDash custom enrollment URL (external checkout page) was not applied (kept as post meta).', 'masterstudy-lms-learning-management-system' )
			);
		}

		self::report_course_dates( $meta, $report_course, $copy_id );

		// Course grid / LearnDash 3.4+ short description → the MasterStudy excerpt (when empty).
		$short = (string) ( $meta['sfwd-courses_course_short_description'] ?? '' );
		$short = '' !== trim( $short ) ? $short : (string) get_post_meta( $course_id, '_learndash_course_grid_short_description', true );
		self::maybe_set_excerpt( $copy_id, $short );

		// Course Grid add-on video preview (shown instead of the course image) → Plus course preview video.
		$preview = trim( (string) get_post_meta( $course_id, '_learndash_course_grid_video_embed_code', true ) );
		if ( '' !== $preview && ! empty( get_post_meta( $course_id, '_learndash_course_grid_enable_video_preview', true ) ) ) {
			if ( ProTarget::plus_active() ) {
				$source = '[' === substr( $preview, 0, 1 ) ? 'shortcode' : Target::detect_video_source( $preview );
				ProTarget::set_course_preview_video( $copy_id, 'external' === $source ? 'ext_link' : $source, $preview );
			} else {
				Target::store_unmigrated_meta( $copy_id, 'ld_course_preview_video', $preview );
				$report_course(
					__( 'Course preview video', 'masterstudy-lms-learning-management-system' ),
					__( 'Course preview videos require MasterStudy LMS Pro Plus, which is not active, so the Course Grid video preview was not imported (kept as post meta).', 'masterstudy-lms-learning-management-system' )
				);
			}
		}

		// Categories keep their hierarchy. LearnDash can also use the WordPress post categories (not the
		// default "Uncategorized" WordPress adds on its own).
		// Copy mode: terms are read from the source course and assigned to the copy.
		Target::migrate_categories( $copy_id, 'ld_course_category', array(), $course_id );
		Target::migrate_category_hierarchy( $course_id, 'ld_course_category' );
		Target::migrate_categories( $copy_id, 'category', array( (int) get_option( 'default_category' ) ), $course_id );
		Target::migrate_category_hierarchy( $course_id, 'category' );

		// MasterStudy has no course tag taxonomy — keep the tag names.
		$course_tags = array_values( array_unique( array_merge( self::term_names( $course_id, 'ld_course_tag' ), self::term_names( $course_id, 'post_tag' ) ) ) );
		Target::store_unmigrated_meta( $copy_id, 'ld_course_tags', $course_tags );

		if ( ! empty( $course_tags ) ) {
			$report_course(
				__( 'Course tags', 'masterstudy-lms-learning-management-system' ),
				/* translators: %s: comma-separated tag names */
				sprintf( __( 'MasterStudy has no course tags, so these tags were not imported (kept as post meta): %s.', 'masterstudy-lms-learning-management-system' ), implode( ', ', $course_tags ) )
			);
		}

		$info = array();

		// Course duration (free text in MasterStudy): LearnDash core has no duration setting; the Course Grid
		// add-on stores it in seconds. `sfwd-courses_course_duration` is accepted for data written by other tools.
		$grid_duration = (int) get_post_meta( $course_id, '_learndash_course_grid_duration', true );
		$duration_raw  = isset( $meta['sfwd-courses_course_duration'] ) ? trim( (string) $meta['sfwd-courses_course_duration'] ) : '';
		if ( $grid_duration > 0 ) {
			$info['duration_info'] = self::seconds_label( $grid_duration );
		} elseif ( '' !== $duration_raw ) {
			$info['duration_info'] = $duration_raw;
		}

		// Access expiration → MasterStudy access duration in days.
		if ( 'on' === ( $meta['sfwd-courses_expire_access'] ?? '' ) && (int) ( $meta['sfwd-courses_expire_access_days'] ?? 0 ) > 0 ) {
			$info['end_time'] = (int) $meta['sfwd-courses_expire_access_days'];
		}

		if ( ! empty( $info ) ) {
			Target::set_course_info( $copy_id, $info );
		}

		if ( 'on' === ( $meta['sfwd-courses_course_materials_enabled'] ?? '' ) ) {
			Target::store_unmigrated_meta( $copy_id, 'ld_course_materials', (string) ( $meta['sfwd-courses_course_materials'] ?? '' ) );

			if ( '' !== trim( (string) ( $meta['sfwd-courses_course_materials'] ?? '' ) ) ) {
				$report_course(
					__( 'Course materials', 'masterstudy-lms-learning-management-system' ),
					__( 'LearnDash course materials have no MasterStudy equivalent, so they are not shown on the course (kept as post meta).', 'masterstudy-lms-learning-management-system' )
				);
			}
		}

		// Prerequisites need the copies of the other courses: applied with Pro in finalize_step( 'courses' ).
		self::migrate_prerequisites( $course_id, $copy_id, $meta, $report_course, false );

		// Course points: "points required to enroll" has no MasterStudy equivalent; points earned on
		// completion are imported into the MasterStudy point system (Pro) in finalize_step( 'enrollments' ).
		if ( 'on' === ( $meta['sfwd-courses_course_points_enabled'] ?? '' ) ) {
			Target::store_unmigrated_meta( $copy_id, 'ld_course_points', (string) ( $meta['sfwd-courses_course_points'] ?? '' ) );
			Target::store_unmigrated_meta( $copy_id, 'ld_course_points_access', (string) ( $meta['sfwd-courses_course_points_access'] ?? '' ) );

			if ( (float) ( $meta['sfwd-courses_course_points'] ?? 0 ) > 0 && ! ProTarget::pro_active() ) {
				$report_course(
					__( 'Course completion points', 'masterstudy-lms-learning-management-system' ),
					__( 'Importing the points students earned for completing this course requires MasterStudy LMS Pro (point system), which is not active, so they were not imported (kept as post meta).', 'masterstudy-lms-learning-management-system' )
				);
			}

			if ( (float) ( $meta['sfwd-courses_course_points_access'] ?? 0 ) > 0 ) {
				$report_course(
					__( 'Course points access', 'masterstudy-lms-learning-management-system' ),
					__( 'MasterStudy cannot require earned points to enroll in a course, so the LearnDash points requirement was not applied (kept as post meta).', 'masterstudy-lms-learning-management-system' )
				);
			}
		}

		// LearnDash 4 challenge exam (ld-exam) — MasterStudy has no placement tests.
		if ( ! empty( $meta['sfwd-courses_exam_challenge'] ) ) {
			Target::store_unmigrated_meta( $copy_id, 'ld_exam_challenge', (int) $meta['sfwd-courses_exam_challenge'] );
			$report_course(
				__( 'Challenge exam', 'masterstudy-lms-learning-management-system' ),
				__( 'MasterStudy has no challenge exams (pass an exam to complete the course), so the LearnDash challenge exam was not imported (kept in LearnDash).', 'masterstudy-lms-learning-management-system' )
			);
		}

		self::migrate_shared_instructors( $course_id, $copy_id, $report_course );

		// Linear progression is the LearnDash default; "on" means free-form navigation.
		$disable_progression = (string) ( $meta['sfwd-courses_course_disable_lesson_progression'] ?? '' );
		Target::store_unmigrated_meta( $copy_id, 'ld_disable_lesson_progression', $disable_progression );

		if ( 'on' !== $disable_progression && ProTarget::pro_active() ) {
			ProTarget::sequential_course( $copy_id );
		}

		// Courses that had a certificate in LearnDash get an approximated copy of its design (Pro).
		$ld_cert = get_post_meta( $course_id, '_ld_certificate', true );
		$ld_cert = $ld_cert ? $ld_cert : ( $meta['sfwd-courses_certificate'] ?? '' );
		if ( $ld_cert ) {
			if ( ProTarget::pro_active() ) {
				self::migrate_course_certificate( $copy_id, (int) $ld_cert, $report_course );
			} else {
				Helper::log( 'warning', sprintf( 'LearnDash migration: course %d had a certificate — not assigned, MasterStudy LMS Pro is not active.', $course_id ) );
				$report_course(
					__( 'Course certificate', 'masterstudy-lms-learning-management-system' ),
					__( 'Importing certificates requires MasterStudy LMS Pro, which is not active, so no certificate was assigned to the course.', 'masterstudy-lms-learning-management-system' )
				);
			}
			Target::store_unmigrated_meta( $copy_id, 'ld_certificate', $ld_cert );
		}
	}

	/**
	 * Report callback for course-level settings that could not be carried over (partial imports).
	 *
	 * @param int $course_id Source course ID.
	 * @param int $copy_id   MasterStudy copy of the course.
	 * @return callable ( string $type, string $reason ).
	 */
	private static function course_reporter( int $course_id, int $copy_id ): callable {
		return function ( string $type, string $reason ) use ( $course_id, $copy_id ) {
			Report::add(
				Report::GROUP_COURSES,
				array(
					'source_id' => $course_id,
					'title'     => self::post_title( $course_id ),
					'type'      => $type,
					'reason'    => $reason,
					'status'    => Report::STATUS_PARTIAL,
					'course'    => self::post_title( $course_id ),
					'post_id'   => $copy_id,
				)
			);
		};
	}

	/**
	 * With Pro: apply the prerequisites of every copied LearnDash course. Runs once all courses are copied,
	 * because a prerequisite must point at the MasterStudy copy of the required course.
	 */
	private static function migrate_all_prerequisites(): void {
		global $wpdb;

		$course_ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s ORDER BY ID ASC", 'sfwd-courses' ) ) );

		foreach ( $course_ids as $course_id ) {
			$copy_id = self::course_copy( $course_id );
			$meta    = get_post_meta( $course_id, '_sfwd-courses', true );

			if ( ! $copy_id || ! is_array( $meta ) ) {
				continue;
			}

			self::migrate_prerequisites( $course_id, $copy_id, $meta, self::course_reporter( $course_id, $copy_id ), true );
		}
	}

	/**
	 * Course prerequisites (Pro "prerequisite" addon). LearnDash requires the prerequisite courses
	 * to be completed; MasterStudy requires all listed courses, so "any of" lists with more than one
	 * course cannot be mapped.
	 *
	 * @param int      $course_id     Source course ID.
	 * @param int      $copy_id       MasterStudy copy of the course.
	 * @param array    $meta          `_sfwd-courses` settings.
	 * @param callable $report_course Course report callback (type, reason).
	 * @param bool     $apply         False in the course step (only reports what needs Pro), true in
	 *                                finalize_step( 'courses' ) once every course has its copy (Pro).
	 */
	private static function migrate_prerequisites( int $course_id, int $copy_id, array $meta, callable $report_course, bool $apply ): void {
		$prerequisite_ids = isset( $meta['sfwd-courses_course_prerequisite'] ) ? $meta['sfwd-courses_course_prerequisite'] : array();
		$enabled          = $meta['sfwd-courses_course_prerequisite_enabled'] ?? null;

		// Older LearnDash versions have no "enabled" switch — a non-empty list is active.
		if ( empty( $prerequisite_ids ) || ! is_array( $prerequisite_ids ) || ( null !== $enabled && 'on' !== $enabled ) ) {
			return;
		}

		$prerequisite_ids = array_values( array_unique( array_filter( array_map( 'intval', $prerequisite_ids ) ) ) );

		if ( empty( $prerequisite_ids ) ) {
			return;
		}

		$type = __( 'Course prerequisites', 'masterstudy-lms-learning-management-system' );

		if ( ! ProTarget::pro_active() ) {
			Target::store_unmigrated_meta( $copy_id, 'ld_prerequisites', $prerequisite_ids );
			$report_course(
				$type,
				__( 'Importing prerequisites requires MasterStudy LMS Pro, which is not active, so they were not applied (kept as post meta).', 'masterstudy-lms-learning-management-system' )
			);
			return;
		}

		if ( ! $apply ) {
			return;
		}

		// Only LearnDash courses that were copied can be required (their MasterStudy copies).
		$migrated = array();
		foreach ( $prerequisite_ids as $id ) {
			$copy = $id !== $course_id && 'sfwd-courses' === get_post_type( $id ) ? self::course_copy( $id ) : 0;

			if ( $copy ) {
				$migrated[] = $copy;
			}
		}

		$compare = strtoupper( (string) ( $meta['sfwd-courses_course_prerequisite_compare'] ?? 'ANY' ) );

		if ( count( $migrated ) > 1 && 'ALL' !== $compare ) {
			Target::store_unmigrated_meta( $copy_id, 'ld_prerequisites', $prerequisite_ids );
			$report_course(
				$type,
				__( 'The course requires any one of several LearnDash courses; MasterStudy prerequisites always require every listed course, so they were not applied (kept as post meta).', 'masterstudy-lms-learning-management-system' )
			);
			return;
		}

		// LearnDash prerequisites must be completed — require full progress in each course.
		ProTarget::set_prerequisites( $copy_id, $migrated, 100 );

		if ( count( $migrated ) < count( $prerequisite_ids ) ) {
			Target::store_unmigrated_meta( $copy_id, 'ld_prerequisites', $prerequisite_ids );
			$report_course(
				$type,
				__( 'Some prerequisite courses no longer exist or are not LearnDash courses, so they were left out of the prerequisites (kept as post meta).', 'masterstudy-lms-learning-management-system' )
			);
		}
	}

	/**
	 * Create (once per LearnDash certificate) an approximated MasterStudy certificate — the
	 * LearnDash background image plus standard fields — and assign it to the course (Pro).
	 *
	 * @param int      $course_id      MasterStudy course (the copy).
	 * @param int      $certificate_id sfwd-certificates post ID.
	 * @param callable $report_course  Course report callback (type, reason).
	 */
	private static function migrate_course_certificate( int $course_id, int $certificate_id, callable $report_course ): void {
		$type        = __( 'Course certificate', 'masterstudy-lms-learning-management-system' );
		$certificate = $certificate_id ? get_post( $certificate_id ) : null;

		if ( ! $certificate || 'sfwd-certificates' !== $certificate->post_type ) {
			Target::assign_certificate( $course_id );
			$report_course(
				$type,
				__( 'The LearnDash certificate of this course no longer exists, so the site default MasterStudy certificate (if one is set) was assigned instead.', 'masterstudy-lms-learning-management-system' )
			);
			return;
		}

		$options     = get_post_meta( $certificate_id, 'learndash_certificate_options', true );
		$orientation = is_array( $options ) && 'P' === strtoupper( (string) ( $options['pdf_page_orientation'] ?? 'L' ) ) ? 'portrait' : 'landscape';

		ProTarget::set_course_certificate(
			$course_id,
			ProTarget::create_certificate(
				array(
					'source_id'     => $certificate_id,
					'title'         => $certificate->post_title,
					'background_id' => (int) get_post_thumbnail_id( $certificate_id ),
					'orientation'   => $orientation,
				),
				self::SOURCE
			)
		);

		$report_course(
			$type,
			sprintf(
				/* translators: %s: certificate title */
				__( 'The LearnDash certificate "%s" was recreated with its background image and the standard fields (student, course, date, instructor) — its text and shortcodes were not converted, so the design is approximated. Review it in the certificate builder.', 'masterstudy-lms-learning-management-system' ),
				wp_strip_all_tags( $certificate->post_title )
			)
		);
	}

	/**
	 * LearnDash recurring course price → MasterStudy course subscription plan (Plus). The course
	 * had no one-time price in LearnDash, so one-time purchase is switched off once the plan exists.
	 *
	 * @param int      $course_id     Source course ID.
	 * @param int      $copy_id       MasterStudy copy of the course.
	 * @param array    $meta          `_sfwd-courses` settings.
	 * @param float    $price         Recurring price.
	 * @param callable $report_course Course report callback (type, reason).
	 */
	private static function migrate_recurring_price( int $course_id, int $copy_id, array $meta, float $price, callable $report_course ): void {
		$type      = __( 'Recurring (subscription) price', 'masterstudy-lms-learning-management-system' );
		$intervals = array(
			'D' => 'day',
			'W' => 'week',
			'M' => 'month',
			'Y' => 'year',
		);
		$days      = array(
			'D' => 1,
			'W' => 7,
			'M' => 30,
			'Y' => 365,
		);
		$unit      = strtoupper( (string) ( $meta['sfwd-courses_course_price_billing_t3'] ?? 'M' ) );

		// Only a free trial maps to a MasterStudy trial period (in days).
		$trial_price = (float) self::parse_amount( $meta['sfwd-courses_course_trial_price'] ?? '' );
		$trial_unit  = strtoupper( (string) ( $meta['sfwd-courses_course_trial_duration_t1'] ?? '' ) );
		$trial_days  = (int) ( $meta['sfwd-courses_course_trial_duration_p1'] ?? 0 ) * ( $days[ $trial_unit ] ?? 0 );

		try {
			ProTarget::create_subscription_plan(
				array(
					'source_id'      => 'course-' . $course_id,
					'name'           => self::post_title( $course_id ),
					'type'           => 'course',
					'object_ids'     => array( $copy_id ),
					'price'          => $price,
					'interval'       => $intervals[ $unit ] ?? 'month',
					'interval_value' => max( 1, (int) ( $meta['sfwd-courses_course_price_billing_p3'] ?? 1 ) ),
					'billing_cycles' => max( 0, (int) ( $meta['sfwd-courses_course_no_of_cycles'] ?? 0 ) ),
					'trial_days'     => $trial_price > 0 ? 0 : $trial_days,
				),
				self::SOURCE
			);
		} catch ( \Exception $e ) {
			$report_course(
				$type,
				sprintf(
					/* translators: %s: error message */
					__( 'The subscription plan could not be created (%s), so the subscription price was imported as a one-time price.', 'masterstudy-lms-learning-management-system' ),
					$e->getMessage()
				)
			);
			return;
		}

		self::update_post_meta_value( $copy_id, 'single_sale', '' );

		$report_course(
			$type,
			$trial_price > 0 && $trial_days > 0
				? __( 'The recurring price was imported as a MasterStudy subscription plan, but the paid trial was dropped (MasterStudy trials are free). Existing LearnDash subscribers keep their enrollment, but their payments are not migrated — they must re-subscribe to be billed again.', 'masterstudy-lms-learning-management-system' )
				: __( 'The recurring price was imported as a MasterStudy subscription plan. Existing LearnDash subscribers keep their enrollment, but their payments are not migrated — they must re-subscribe to be billed again.', 'masterstudy-lms-learning-management-system' )
		);
	}

	/**
	 * LearnDash "closed" course: nobody can enroll on their own (access is granted by an admin, a group or
	 * an external shop behind the custom button URL). With Pro a button URL becomes an affiliate course
	 * (the buy button links to the URL); otherwise the course is paid with one-time purchase switched off,
	 * so it is never open for free.
	 *
	 * @param int      $course_id     MasterStudy course (the copy).
	 * @param array    $meta          `_sfwd-courses` settings.
	 * @param float    $price         Displayed LearnDash price.
	 * @param callable $report_course Course report callback (type, reason).
	 */
	private static function migrate_closed_course( int $course_id, array $meta, float $price, callable $report_course ): void {
		$url = trim( (string) ( $meta['sfwd-courses_custom_button_url'] ?? '' ) );
		$url = '' !== $url ? $url : trim( (string) ( $meta['sfwd-courses_course_price_type_closed_custom_button_url'] ?? '' ) );
		$url = '' !== $url ? esc_url_raw( $url ) : '';

		Target::store_unmigrated_meta( $course_id, 'ld_custom_button_url', $url );

		if ( '' !== $url && ProTarget::pro_active() ) {
			Target::set_affiliate( $course_id, $url, '', $price );
			$report_course(
				__( 'Closed course', 'masterstudy-lms-learning-management-system' ),
				__( 'The closed LearnDash course was imported as an affiliate course: its button links to the LearnDash custom button URL. Students given access in LearnDash keep their enrollment.', 'masterstudy-lms-learning-management-system' )
			);
			return;
		}

		Target::set_closed_pricing( $course_id, $price );
		$report_course(
			__( 'Closed course', 'masterstudy-lms-learning-management-system' ),
			'' !== $url
				? __( 'MasterStudy has no "closed" access mode; the course was imported as a paid course that cannot be bought (enrollment by an admin only). The custom button URL needs MasterStudy LMS Pro (affiliate courses), so it was not applied (kept as post meta).', 'masterstudy-lms-learning-management-system' )
				: __( 'MasterStudy has no "closed" access mode; the course was imported as a paid course that cannot be bought (enrollment by an admin only).', 'masterstudy-lms-learning-management-system' )
		);
	}

	/**
	 * LearnDash 4.1 course start/end dates (enrollment window) have no MasterStudy equivalent.
	 *
	 * @param array    $meta          `_sfwd-courses` settings.
	 * @param callable $report_course Course report callback (type, reason).
	 * @param int      $course_id     MasterStudy course (the copy).
	 */
	private static function report_course_dates( array $meta, callable $report_course, int $course_id ): void {
		$start = self::drip_timestamp( $meta['sfwd-courses_course_start_date'] ?? '' );
		$end   = self::drip_timestamp( $meta['sfwd-courses_course_end_date'] ?? '' );

		if ( ! $start && ! $end ) {
			return;
		}

		Target::store_unmigrated_meta( $course_id, 'ld_course_start_date', $start ? $start : '' );
		Target::store_unmigrated_meta( $course_id, 'ld_course_end_date', $end ? $end : '' );

		// A course that starts in the future is an upcoming course (Plus "coming soon"): students can enroll
		// before the start, the content opens on the start date — as in LearnDash.
		$upcoming = $start > time() && ProTarget::plus_active();

		if ( $upcoming ) {
			ProTarget::set_coming_soon(
				$course_id,
				array(
					'start'        => $start,
					'show_price'   => true,
					'show_details' => true,
					'preordering'  => true,
				)
			);
		}

		if ( $upcoming && ! $end ) {
			return;
		}

		$report_course(
			__( 'Course start / end date', 'masterstudy-lms-learning-management-system' ),
			$upcoming
				? __( 'The future start date was imported as an upcoming ("coming soon") course; MasterStudy has no course end date, so the LearnDash end date was not applied (kept as post meta).', 'masterstudy-lms-learning-management-system' )
				: __( 'MasterStudy courses have no enrollment end date and only Pro Plus has upcoming courses (future start date), so the LearnDash course dates were not applied (kept as post meta).', 'masterstudy-lms-learning-management-system' )
		);
	}

	/**
	 * Shared instructors of the Instructor Role add-on (`ir_shared_instructor_ids`, comma separated): the
	 * first one becomes the MasterStudy co-instructor (Pro "multi_instructors" — one co-instructor).
	 *
	 * @param int      $course_id     Source course ID.
	 * @param int      $copy_id       MasterStudy copy of the course.
	 * @param callable $report_course Course report callback (type, reason).
	 */
	private static function migrate_shared_instructors( int $course_id, int $copy_id, callable $report_course ): void {
		$raw = get_post_meta( $course_id, 'ir_shared_instructor_ids', true );
		$ids = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		$ids = array_values( array_diff( $ids, array( (int) get_post_field( 'post_author', $course_id ) ) ) );

		if ( empty( $ids ) ) {
			return;
		}

		$existing = array_values( array_filter( $ids, 'get_userdata' ) );
		$type     = __( 'Shared instructors', 'masterstudy-lms-learning-management-system' );

		Target::store_unmigrated_meta( $copy_id, 'ld_shared_instructors', $ids );

		if ( ! ProTarget::pro_active() ) {
			$report_course( $type, __( 'Co-instructors require MasterStudy LMS Pro, which is not active, so the shared instructors of this course were not added (kept as post meta).', 'masterstudy-lms-learning-management-system' ) );
			return;
		}

		if ( ! empty( $existing ) ) {
			ProTarget::set_co_instructor( $copy_id, (int) $existing[0] );
		}

		if ( count( $ids ) > 1 || empty( $existing ) ) {
			$report_course(
				$type,
				sprintf(
					/* translators: %s: user label */
					__( 'MasterStudy courses have one co-instructor, so only %s was added; the other shared instructors (or deleted users) were left out (kept as post meta).', 'masterstudy-lms-learning-management-system' ),
					empty( $existing ) ? __( 'nobody', 'masterstudy-lms-learning-management-system' ) : self::user_label( (int) $existing[0] )
				)
			);
		}
	}

	/**
	 * Lesson, topic and quiz categories/tags (their own LearnDash taxonomies, or the WordPress ones):
	 * MasterStudy lessons and quizzes have no taxonomies — the names are kept as post meta and
	 * reported once per course.
	 *
	 * @param int $post_id   Source lesson/topic/quiz post ID (the terms are read from it).
	 * @param int $copy_id   MasterStudy copy (the names are kept on it).
	 * @param int $course_id Source course being migrated (0 for items in no course).
	 */
	private static function collect_step_terms( int $post_id, int $copy_id, int $course_id ): void {
		$names = array();

		foreach ( array( 'ld_lesson_category', 'ld_lesson_tag', 'ld_topic_category', 'ld_topic_tag', 'ld_quiz_category', 'ld_quiz_tag', 'category', 'post_tag' ) as $taxonomy ) {
			foreach ( self::raw_terms( $post_id, $taxonomy ) as $term ) {
				if ( 'category' === $taxonomy && (int) $term['term_id'] === (int) get_option( 'default_category' ) ) {
					continue;
				}

				$names[] = (string) $term['name'];
			}
		}

		$names = array_values( array_unique( $names ) );

		if ( empty( $names ) ) {
			return;
		}

		Target::store_unmigrated_meta( $copy_id, 'ld_terms', $names );
		self::$step_terms[ $course_id ] = array_values( array_unique( array_merge( self::$step_terms[ $course_id ] ?? array(), $names ) ) );
	}

	/**
	 * Report the lesson/topic/quiz terms collected while a course was built.
	 *
	 * @param int $course_id Course ID.
	 */
	private static function report_step_terms( int $course_id ): void {
		if ( empty( self::$step_terms[ $course_id ] ) ) {
			return;
		}

		Report::add(
			Report::GROUP_LESSONS,
			array(
				'source_id' => $course_id,
				'title'     => self::post_title( $course_id ),
				'type'      => __( 'Lesson, topic and quiz categories / tags', 'masterstudy-lms-learning-management-system' ),
				/* translators: %s: comma-separated term names */
				'reason'    => sprintf( __( 'MasterStudy lessons and quizzes have no categories or tags, so these terms were not imported (kept as post meta): %s.', 'masterstudy-lms-learning-management-system' ), implode( ', ', self::$step_terms[ $course_id ] ) ),
				'status'    => Report::STATUS_PARTIAL,
				'course'    => self::post_title( $course_id ),
				'post_id'   => self::report_post_id( $course_id ),
			)
		);

		unset( self::$step_terms[ $course_id ] );
	}

	/**
	 * "90" (seconds) → "2 min", "5400" → "1 h 30 min".
	 *
	 * @param int $seconds Seconds.
	 */
	private static function seconds_label( int $seconds ): string {
		$minutes = (int) ceil( max( 0, $seconds ) / 60 );
		$hours   = intdiv( $minutes, 60 );
		$minutes = $minutes % 60;

		if ( $hours > 0 ) {
			/* translators: 1: hours, 2: minutes */
			return $minutes > 0 ? sprintf( __( '%1$d h %2$d min', 'masterstudy-lms-learning-management-system' ), $hours, $minutes ) : sprintf( __( '%d h', 'masterstudy-lms-learning-management-system' ), $hours );
		}

		/* translators: %d: minutes */
		return sprintf( __( '%d min', 'masterstudy-lms-learning-management-system' ), $minutes );
	}

	/**
	 * Whether LearnDash grants the user access to the course: the `course_{id}_access_from` user meta,
	 * the legacy course access list, or membership of a published group that has the course.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 */
	private static function has_ld_access( int $user_id, int $course_id ): bool {
		if ( '' !== (string) get_user_meta( $user_id, "course_{$course_id}_access_from", true ) ) {
			return true;
		}

		$lists = self::course_access_lists();

		if ( in_array( $user_id, $lists[ $course_id ] ?? array(), true ) ) {
			return true;
		}

		foreach ( self::published_group_ids() as $group_id ) {
			if ( in_array( $course_id, self::group_course_ids( $group_id ), true ) && in_array( $user_id, self::group_user_ids( $group_id, 'users' ), true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Legacy course access lists (LearnDash 2.x `course_access_list`, in the course settings or as its own
	 * post meta): course ID => user IDs.
	 *
	 * @return array<int, int[]>
	 */
	private static function course_access_lists(): array {
		global $wpdb;

		static $cache = null;

		if ( null !== $cache ) {
			return $cache;
		}

		$cache = array();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}
				 WHERE ( meta_key = '_sfwd-courses' AND meta_value LIKE %s ) OR meta_key = 'course_access_list'",
				'%' . $wpdb->esc_like( 'course_access_list' ) . '%'
			),
			ARRAY_A
		);

		foreach ( (array) $rows as $row ) {
			$value = maybe_unserialize( $row['meta_value'] );

			if ( '_sfwd-courses' === $row['meta_key'] ) {
				$value = is_array( $value ) ? ( $value['sfwd-courses_course_access_list'] ?? '' ) : '';
			}

			$ids = is_array( $value ) ? $value : explode( ',', (string) $value );
			$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );

			if ( ! empty( $ids ) ) {
				$cache[ (int) $row['post_id'] ] = array_values( array_unique( array_merge( $cache[ (int) $row['post_id'] ] ?? array(), $ids ) ) );
			}
		}

		return $cache;
	}

	/**
	 * Published LearnDash group IDs.
	 *
	 * @return int[]
	 */
	private static function published_group_ids(): array {
		global $wpdb;

		static $cache = null;

		if ( null === $cache ) {
			$cache = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' ORDER BY ID ASC", 'groups' ) ) );
		}

		return $cache;
	}

	/**
	 * Retrieve the hierarchical LearnDash course steps for a given course:
	 * ['sfwd-lessons' => [lesson_id => ['sfwd-topic' => [topic_id => ['sfwd-quiz' => [...]]], 'sfwd-quiz' => [...]]],
	 *  'sfwd-quiz' => [quiz_id => []]].
	 *
	 * Uses LearnDash's API when active, otherwise the `ld_course_steps` meta, falling back to the
	 * legacy course_id / lesson_id post meta for courses built before course builder.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	private static function get_course_steps( int $course_id ): array {
		if ( class_exists( '\LDLMS_Factory_Post' ) ) {
			$steps = \LDLMS_Factory_Post::course_steps( $course_id );
			if ( $steps ) {
				return (array) $steps->get_steps();
			}
		}

		$raw = get_post_meta( $course_id, 'ld_course_steps', true );

		if ( is_array( $raw ) ) {
			if ( isset( $raw['steps']['h'] ) && is_array( $raw['steps']['h'] ) ) {
				return $raw['steps']['h'];
			}

			if ( isset( $raw['h'] ) && is_array( $raw['h'] ) ) {
				return $raw['h'];
			}

			if ( isset( $raw['sfwd-lessons'] ) || isset( $raw['sfwd-quiz'] ) ) {
				return $raw;
			}
		}

		return self::get_legacy_course_steps( $course_id );
	}

	/**
	 * Build the step hierarchy from legacy `course_id` / `lesson_id` post meta.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	private static function get_legacy_course_steps( int $course_id ): array {
		global $wpdb;

		$children = function ( string $post_type, int $lesson_id ) use ( $wpdb, $course_id ): array {
			return array_map(
				'intval',
				$wpdb->get_col(
					$wpdb->prepare(
						"SELECT p.ID FROM {$wpdb->posts} p
						 INNER JOIN {$wpdb->postmeta} c ON c.post_id = p.ID AND c.meta_key = 'course_id' AND c.meta_value = %d
						 LEFT JOIN {$wpdb->postmeta} l ON l.post_id = p.ID AND l.meta_key = 'lesson_id'
						 WHERE p.post_type = %s AND p.post_status NOT IN ('trash', 'auto-draft')
						   AND COALESCE(CAST(l.meta_value AS UNSIGNED), 0) = %d
						 ORDER BY p.menu_order ASC, p.ID ASC",
						$course_id,
						$post_type,
						$lesson_id
					)
				)
			);
		};

		$steps = array(
			'sfwd-lessons' => array(),
			'sfwd-quiz'    => array_fill_keys( $children( 'sfwd-quiz', 0 ), array() ),
		);

		foreach ( $children( 'sfwd-lessons', 0 ) as $lesson_id ) {
			$lesson = array(
				'sfwd-topic' => array(),
				'sfwd-quiz'  => array_fill_keys( $children( 'sfwd-quiz', $lesson_id ), array() ),
			);

			foreach ( $children( 'sfwd-topic', $lesson_id ) as $topic_id ) {
				$lesson['sfwd-topic'][ $topic_id ] = array(
					'sfwd-quiz' => array_fill_keys( $children( 'sfwd-quiz', $topic_id ), array() ),
				);
			}

			$steps['sfwd-lessons'][ $lesson_id ] = $lesson;
		}

		return ( empty( $steps['sfwd-lessons'] ) && empty( $steps['sfwd-quiz'] ) ) ? array() : $steps;
	}

	/**
	 * Lessons, topics, quizzes and questions that are in no course (LearnDash keeps them as standalone
	 * posts — e.g. the question bank). They are copied on their own, keeping their status, so no
	 * LearnDash content is left behind: questions land in the MasterStudy question library, lessons and
	 * quizzes can be added to a curriculum later. Trashed ones are not copied.
	 */
	private static function migrate_orphan_steps(): void {
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT ID, post_type FROM {$wpdb->posts} WHERE post_type IN ('sfwd-lessons', 'sfwd-topic', 'sfwd-quiz') AND post_status NOT IN ('trash', 'auto-draft') ORDER BY ID ASC", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		foreach ( (array) $rows as $row ) {
			$post_id = (int) $row['ID'];
			$is_quiz = 'sfwd-quiz' === $row['post_type'];

			// Copied as part of a course (its copy is in a curriculum). A copy that is in no curriculum is an
			// orphan imported by an earlier run: migrated (idempotent) and reported again.
			$existing = Target::copy_of( self::SOURCE, $post_id );

			if ( $existing && ! empty( Target::course_ids_of( $existing ) ) ) {
				continue;
			}

			try {
				if ( $is_quiz ) {
					$copy_id = Target::copy_post( $post_id, PostType::QUIZ, self::SOURCE );
					self::migrate_ld_quiz( $post_id, $copy_id );
				} else {
					$copy_id = self::migrate_ld_lesson( $post_id, (string) $row['post_type'] );
				}

				Report::add(
					$is_quiz ? Report::GROUP_QUIZZES : Report::GROUP_LESSONS,
					array(
						'source_id' => $post_id,
						'title'     => self::post_title( $post_id ),
						'type'      => $is_quiz
							? __( 'LearnDash quiz', 'masterstudy-lms-learning-management-system' )
							: ( 'sfwd-topic' === $row['post_type'] ? __( 'LearnDash topic', 'masterstudy-lms-learning-management-system' ) : __( 'LearnDash lesson', 'masterstudy-lms-learning-management-system' ) ),
						'reason'    => __( 'The item is not part of any LearnDash course, so it was imported on its own and is not in any MasterStudy curriculum (add it to a course to use it).', 'masterstudy-lms-learning-management-system' ),
						'status'    => Report::STATUS_PARTIAL,
						'post_id'   => $copy_id,
					)
				);
			} catch ( \Exception $e ) {
				Report::add(
					$is_quiz ? Report::GROUP_QUIZZES : Report::GROUP_LESSONS,
					array(
						'source_id' => $post_id,
						'title'     => self::post_title( $post_id ),
						'type'      => (string) $row['post_type'],
						/* translators: %s: error message */
						'reason'    => sprintf( __( 'The item could not be imported: %s', 'masterstudy-lms-learning-management-system' ), $e->getMessage() ),
						'status'    => Report::STATUS_FAILED,
						'post_id'   => self::report_post_id( $post_id ),
					)
				);
			}
		}

		// Questions in no copied quiz (question bank) — copied with their answers, keeping their status.
		// Questions of copied quizzes were handled (copied or reported) with their quiz.
		$in_quizzes   = self::copied_quiz_question_ids();
		$question_ids = $wpdb->get_col(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'sfwd-question' AND post_status NOT IN ('trash', 'auto-draft') ORDER BY ID ASC" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
		$q_table      = self::pro_quiz_table( 'question' );

		foreach ( (array) $question_ids as $question_id ) {
			$question_id = (int) $question_id;

			if ( isset( $in_quizzes[ $question_id ] ) ) {
				continue;
			}

			$row = self::table_exists( $q_table )
				? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$q_table} WHERE id = %d", (int) get_post_meta( $question_id, 'question_pro_id', true ) ), ARRAY_A ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				: null;

			if ( ! $row ) {
				Report::add(
					Report::GROUP_QUESTIONS,
					array(
						'source_id' => $question_id,
						'title'     => self::post_title( $question_id ),
						'type'      => __( 'Unknown', 'masterstudy-lms-learning-management-system' ),
						'reason'    => __( 'The question is in no quiz and its data is missing from the LearnDash (WpProQuiz) questions table, so it was not imported.', 'masterstudy-lms-learning-management-system' ),
						'status'    => Report::STATUS_FAILED,
						'post_id'   => $question_id,
					)
				);
				continue;
			}

			try {
				self::migrate_question_row( $question_id, $row, array(), null );
			} catch ( \Exception $e ) {
				Helper::log( 'warning', sprintf( 'LearnDash migration: question %d not imported — %s', $question_id, $e->getMessage() ) );
			}
		}
	}

	/**
	 * Source question IDs listed in (`ld_quiz_questions`) LearnDash quizzes that were copied.
	 *
	 * @return array<int, true>
	 */
	private static function copied_quiz_question_ids(): array {
		global $wpdb;

		$ids  = array();
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.post_id, pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = 'ld_quiz_questions' AND p.post_type = %s",
				'sfwd-quiz'
			),
			ARRAY_A
		);

		foreach ( (array) $rows as $row ) {
			if ( ! Target::copy_of( self::SOURCE, (int) $row['post_id'] ) ) {
				continue;
			}

			$list = maybe_unserialize( $row['meta_value'] );

			foreach ( is_array( $list ) ? array_keys( $list ) : array() as $question_id ) {
				$ids[ (int) $question_id ] = true;
			}
		}

		return $ids;
	}

	/**
	 * LearnDash data MasterStudy has no place for, reported once: essay answers (sfwd-essays), challenge
	 * exams (ld-exam) and Notifications add-on rules (ld-notification). The posts stay in LearnDash — they
	 * are private (not registered without LearnDash).
	 */
	private static function report_leftovers(): void {
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT ID, post_type, post_author, post_status FROM {$wpdb->posts} WHERE post_type IN ('sfwd-essays', 'ld-exam', 'ld-notification', 'ld-achievement', 'coursenote') AND post_status NOT IN ('trash', 'auto-draft') ORDER BY ID ASC LIMIT 1000", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		foreach ( (array) $rows as $row ) {
			$post_id = (int) $row['ID'];

			switch ( $row['post_type'] ) {
				case 'sfwd-essays':
					$quiz_id   = (int) get_post_meta( $post_id, 'quiz_post_id', true );
					$quiz_id   = $quiz_id ? $quiz_id : (int) get_post_meta( $post_id, 'quiz_id', true );
					$course_id = (int) get_post_meta( $post_id, 'course_id', true );

					Report::add(
						Report::GROUP_QUIZ_ATTEMPTS,
						array(
							'source_id' => $post_id,
							'title'     => self::user_label( (int) $row['post_author'] ) . ' → ' . self::post_title( $post_id ),
							'type'      => 'graded' === $row['post_status']
								? __( 'Essay answer (graded)', 'masterstudy-lms-learning-management-system' )
								: __( 'Essay answer (not graded)', 'masterstudy-lms-learning-management-system' ),
							'reason'    => __( 'MasterStudy has no essay questions, so this essay answer (and its grade) was not imported. It stays in LearnDash (not public).', 'masterstudy-lms-learning-management-system' ),
							'status'    => Report::STATUS_UNSUPPORTED,
							/* translators: %s: quiz title */
							'parent'    => $quiz_id ? sprintf( __( 'Quiz: %s', 'masterstudy-lms-learning-management-system' ), self::post_title( $quiz_id ) ) : '',
							'course'    => $course_id ? self::post_title( $course_id ) : '',
						)
					);
					break;

				case 'ld-exam':
					Report::add(
						Report::GROUP_QUIZZES,
						array(
							'source_id' => $post_id,
							'title'     => self::post_title( $post_id ),
							'type'      => __( 'LearnDash challenge exam', 'masterstudy-lms-learning-management-system' ),
							'reason'    => __( 'MasterStudy has no challenge exams, so the exam was not imported. It stays in LearnDash.', 'masterstudy-lms-learning-management-system' ),
							'status'    => Report::STATUS_UNSUPPORTED,
						)
					);
					break;

				case 'ld-achievement':
					Report::add(
						Report::GROUP_OTHER,
						array(
							'source_id' => $post_id,
							'title'     => self::post_title( $post_id ),
							'type'      => __( 'LearnDash achievement (badge)', 'masterstudy-lms-learning-management-system' ),
							'reason'    => __( 'MasterStudy has no badges, so the LearnDash Achievements add-on badge (and the badges students earned) was not imported.', 'masterstudy-lms-learning-management-system' ),
							'status'    => Report::STATUS_UNSUPPORTED,
						)
					);
					break;

				case 'coursenote':
					Report::add(
						Report::GROUP_OTHER,
						array(
							'source_id' => $post_id,
							'title'     => self::user_label( (int) $row['post_author'] ) . ' → ' . __( 'private note', 'masterstudy-lms-learning-management-system' ),
							'type'      => __( 'Course note (Notes add-on)', 'masterstudy-lms-learning-management-system' ),
							'reason'    => __( 'Student notes of the LearnDash notes add-on were not imported; they stay private in the add-on.', 'masterstudy-lms-learning-management-system' ),
							'status'    => Report::STATUS_UNSUPPORTED,
						)
					);
					break;

				default:
					Report::add(
						Report::GROUP_OTHER,
						array(
							'source_id' => $post_id,
							'title'     => self::post_title( $post_id ),
							'type'      => __( 'LearnDash notification', 'masterstudy-lms-learning-management-system' ),
							'reason'    => __( 'LearnDash Notifications add-on rules have no MasterStudy equivalent (MasterStudy sends its own emails), so the notification was not imported.', 'masterstudy-lms-learning-management-system' ),
							'status'    => Report::STATUS_UNSUPPORTED,
						)
					);
			}
		}
	}

	/**
	 * Groups that are not published in LearnDash (draft, pending, private, trash) give no access: they
	 * are not imported and their members are not enrolled.
	 */
	private static function report_unpublished_groups(): void {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT ID, post_status FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('publish', 'auto-draft') ORDER BY ID ASC", 'groups' ),
			ARRAY_A
		);

		foreach ( (array) $rows as $row ) {
			Report::add(
				Report::GROUP_ENROLLMENTS,
				array(
					'source_id' => (int) $row['ID'],
					'title'     => self::post_title( (int) $row['ID'] ),
					'type'      => __( 'LearnDash group', 'masterstudy-lms-learning-management-system' ),
					/* translators: %s: post status */
					'reason'    => sprintf( __( 'The group is not published in LearnDash (status: %s), so it was not imported and its members were not enrolled through it.', 'masterstudy-lms-learning-management-system' ), $row['post_status'] ),
					'status'    => Report::STATUS_UNSUPPORTED,
				)
			);
		}
	}

	/**
	 * Group settings MasterStudy groups cannot hold: group certificate, parent group, materials, start/end
	 * dates, seats limit and — without Pro — the group price.
	 *
	 * @param int $group_id LearnDash group ID.
	 */
	private static function report_group_extras( int $group_id ): void {
		$meta = get_post_meta( $group_id, '_groups', true );
		$meta = is_array( $meta ) ? $meta : array();
		$lost = array();

		if ( ! empty( $meta['groups_certificate'] ) ) {
			$lost[] = __( 'the group certificate (MasterStudy certificates are per course)', 'masterstudy-lms-learning-management-system' );
		}

		if ( (int) get_post_field( 'post_parent', $group_id ) > 0 ) {
			/* translators: %s: parent group title */
			$lost[] = sprintf( __( 'the parent group "%s" (MasterStudy groups are not nested)', 'masterstudy-lms-learning-management-system' ), self::post_title( (int) get_post_field( 'post_parent', $group_id ) ) );
		}

		if ( 'on' === ( $meta['groups_group_materials_enabled'] ?? '' ) && '' !== trim( (string) ( $meta['groups_group_materials'] ?? '' ) ) ) {
			$lost[] = __( 'group materials', 'masterstudy-lms-learning-management-system' );
		}

		if ( ! empty( $meta['groups_group_start_date'] ) || ! empty( $meta['groups_group_end_date'] ) ) {
			$lost[] = __( 'the group start / end date', 'masterstudy-lms-learning-management-system' );
		}

		if ( (int) ( $meta['groups_group_seats_limit'] ?? 0 ) > 0 ) {
			$lost[] = __( 'the seats limit', 'masterstudy-lms-learning-management-system' );
		}

		if ( self::group_price( $group_id ) > 0 && ! ProTarget::pro_active() ) {
			$lost[] = __( 'the group price (course bundles require MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
		}

		if ( empty( $lost ) ) {
			return;
		}

		Report::add(
			Report::GROUP_ENROLLMENTS,
			array(
				'source_id' => $group_id,
				'title'     => self::post_title( $group_id ),
				'type'      => __( 'Group settings', 'masterstudy-lms-learning-management-system' ),
				/* translators: %s: list of group settings */
				'reason'    => sprintf( __( 'These LearnDash group settings have no MasterStudy equivalent and were not imported: %s.', 'masterstudy-lms-learning-management-system' ), implode( '; ', $lost ) ),
				'status'    => Report::STATUS_PARTIAL,
			)
		);
	}

	/**
	 * Price of a LearnDash group that is sold ("buy now" or recurring), 0 otherwise.
	 *
	 * @param int $group_id LearnDash group ID.
	 */
	private static function group_price( int $group_id ): float {
		$meta = get_post_meta( $group_id, '_groups', true );
		$meta = is_array( $meta ) ? $meta : array();

		if ( ! in_array( (string) ( $meta['groups_group_price_type'] ?? '' ), array( 'paynow', 'subscribe' ), true ) ) {
			return 0.0;
		}

		return (float) self::parse_amount( $meta['groups_group_price'] ?? '' );
	}

	/**
	 * A sold LearnDash group is a set of courses bought together — a MasterStudy course bundle (Pro).
	 *
	 * @param int   $group_id   LearnDash group ID.
	 * @param int[] $course_ids Migrated group courses.
	 */
	private static function maybe_create_group_bundle( int $group_id, array $course_ids ): void {
		$price = self::group_price( $group_id );

		if ( $price <= 0 ) {
			return;
		}

		$meta = get_post_meta( $group_id, '_groups', true );
		$meta = is_array( $meta ) ? $meta : array();

		$bundle_id = ProTarget::create_bundle(
			array(
				'source_id'    => $group_id,
				'title'        => (string) get_post_field( 'post_title', $group_id ),
				'content'      => (string) get_post_field( 'post_content', $group_id ),
				'author'       => (int) get_post_field( 'post_author', $group_id ),
				'price'        => $price,
				'course_ids'   => $course_ids,
				'thumbnail_id' => (int) get_post_thumbnail_id( $group_id ),
				'status'       => 'publish',
			),
			self::SOURCE
		);

		Report::add(
			Report::GROUP_ENROLLMENTS,
			array(
				'source_id' => $group_id,
				'title'     => self::post_title( $group_id ),
				'type'      => __( 'Group price', 'masterstudy-lms-learning-management-system' ),
				'reason'    => 'subscribe' === ( $meta['groups_group_price_type'] ?? '' )
					? __( 'The sold group was imported as a MasterStudy course bundle; its recurring price became a one-time bundle price (bundles cannot be subscriptions).', 'masterstudy-lms-learning-management-system' )
					: __( 'The sold group was imported as a MasterStudy course bundle with the group price; new buyers get the bundle courses (not a group seat).', 'masterstudy-lms-learning-management-system' ),
				'status'    => Report::STATUS_PARTIAL,
				'post_id'   => $bundle_id,
			)
		);
	}

	/**
	 * With MasterStudy LMS Pro: points LearnDash awarded for completed courses (course "points" setting)
	 * become MasterStudy point system entries ("Course completion"); manually awarded extra points
	 * (`course_points` user meta) are reported. Runs outside the batch transaction (enables the addon).
	 */
	private static function migrate_course_points(): void {
		global $wpdb;

		$courses = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.post_id, pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = '_migrated_ld_course_points' AND p.post_type = %s",
				PostType::COURSE
			),
			ARRAY_A
		);
		$courses = array_filter(
			(array) $courses,
			function ( $row ) {
				return (float) $row['meta_value'] > 0;
			}
		);

		$extra = $wpdb->get_results( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'course_points' AND meta_value <> '' AND meta_value <> '0'", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		foreach ( (array) $extra as $row ) {
			if ( (float) $row['meta_value'] <= 0 || ! get_userdata( (int) $row['user_id'] ) ) {
				continue;
			}

			Report::add(
				Report::GROUP_USERS,
				array(
					'source_id' => (int) $row['user_id'],
					'title'     => self::user_label( (int) $row['user_id'] ),
					'type'      => __( 'Extra LearnDash points', 'masterstudy-lms-learning-management-system' ),
					/* translators: %s: number of points */
					'reason'    => sprintf( __( 'The %s points an admin awarded manually in LearnDash have no MasterStudy equivalent, so they were not imported.', 'masterstudy-lms-learning-management-system' ), (string) (float) $row['meta_value'] ),
					'status'    => Report::STATUS_UNSUPPORTED,
				)
			);
		}

		if ( empty( $courses ) || ! ProTarget::pro_active() ) {
			return;
		}

		// Completed enrollments of those courses (LearnDash awards the points on completion).
		$awards = array();

		foreach ( $courses as $row ) {
			$course_id = (int) $row['post_id'];
			$completed = $wpdb->get_results(
				$wpdb->prepare( "SELECT user_id, end_time FROM {$wpdb->prefix}stm_lms_user_courses WHERE course_id = %d AND end_time > 0", $course_id ),
				ARRAY_A
			);

			foreach ( (array) $completed as $enrollment ) {
				$awards[] = array( $course_id, (int) round( (float) $row['meta_value'] ), $enrollment );
			}
		}

		if ( empty( $awards ) ) {
			return;
		}

		// The addon creates its table when enabled — outside a transaction (finalize_step).
		Helper::request_addon( 'point_system' );
		Helper::flush_addon_requests();

		foreach ( $awards as list( $course_id, $points, $enrollment ) ) {
			if ( ! ProTarget::add_user_points( (int) $enrollment['user_id'], $course_id, 'certificate_received', $points, (int) $enrollment['end_time'] ) ) {
				Report::add(
					Report::GROUP_USERS,
					array(
						'source_id' => $course_id . ':' . (int) $enrollment['user_id'],
						'title'     => self::user_label( (int) $enrollment['user_id'] ) . ' → ' . self::post_title( $course_id ),
						'type'      => __( 'Course completion points', 'masterstudy-lms-learning-management-system' ),
						'reason'    => __( 'The MasterStudy point system table is missing, so the points were not imported. Enable the Point System addon and run the migration again.', 'masterstudy-lms-learning-management-system' ),
						'status'    => Report::STATUS_FAILED,
						'course'    => self::post_title( $course_id ),
						'post_id'   => $course_id,
					)
				);
			}
		}
	}

	/**
	 * Migrate one LearnDash Course Reviews add-on review (comment type `ld_review`, `rating` comment meta)
	 * to a MasterStudy review. Approved reviews are published, held ones pending; spam and trashed reviews
	 * are not imported.
	 *
	 * @param int $comment_id Comment ID.
	 */
	public static function migrate_single_review( int $comment_id ): void {
		$comment = get_comment( $comment_id );

		if ( ! $comment ) {
			return;
		}

		$course_id = (int) $comment->comment_post_ID;
		$copy_id   = self::course_copy( $course_id );
		$user_id   = (int) $comment->user_id;
		$rating    = (float) get_comment_meta( $comment_id, 'rating', true );
		$title     = trim( (string) get_comment_meta( $comment_id, 'review_title', true ) );
		$approved  = (string) $comment->comment_approved;
		$item      = array(
			'source_id' => $comment_id,
			'title'     => self::user_label( $user_id ) . ' → ' . self::post_title( $course_id ),
			'type'      => __( 'Course review', 'masterstudy-lms-learning-management-system' ),
			'course'    => self::post_title( $course_id ),
			'post_id'   => self::report_post_id( $course_id ),
		);

		$reason = '';

		if ( in_array( $approved, array( 'spam', 'trash', 'post-trashed' ), true ) ) {
			$reason = __( 'The review is marked as spam or trashed in LearnDash, so it was not imported.', 'masterstudy-lms-learning-management-system' );
		} elseif ( ! $user_id || ! get_userdata( $user_id ) ) {
			$reason = __( 'The review author is a guest or a deleted user; MasterStudy reviews belong to a student, so it was not imported.', 'masterstudy-lms-learning-management-system' );
		} elseif ( ! $copy_id ) {
			$reason = __( 'The reviewed course was not migrated, so the review was not imported.', 'masterstudy-lms-learning-management-system' );
		} elseif ( $rating < 1 ) {
			$reason = __( 'The review has no star rating, so it was not imported.', 'masterstudy-lms-learning-management-system' );
		}

		if ( '' !== $reason ) {
			Report::add(
				Report::GROUP_REVIEWS,
				array_merge(
					$item,
					array(
						'reason' => $reason,
						'status' => Report::STATUS_UNSUPPORTED,
					)
				)
			);
			return;
		}

		$content = (string) $comment->comment_content;

		if ( '' !== $title ) {
			$content = '<p><strong>' . esc_html( $title ) . '</strong></p>' . $content;
		}

		Target::add_review(
			array(
				'course_id' => $copy_id,
				'user_id'   => $user_id,
				'mark'      => $rating,
				'content'   => $content,
				'date'      => (string) $comment->comment_date,
				'approved'  => '1' === $approved,
				'source_id' => 'ld-review-' . $comment_id,
			),
			self::SOURCE
		);
	}

	/**
	 * Migrate one WisdmLabs "Ratings, Reviews, and Feedback" review (`wdm_course_review` post) to a MasterStudy
	 * review. Published reviews are published, pending / draft ones pending; trashed reviews are not imported.
	 *
	 * @param int $review_id Review post ID.
	 */
	public static function migrate_single_wdm_review( int $review_id ): void {
		$review = get_post( $review_id );

		if ( ! $review || self::WDM_REVIEW_POST_TYPE !== $review->post_type ) {
			return;
		}

		$course_id = (int) get_post_meta( $review_id, 'wdm_course_review_review_on_course', true );
		$copy_id   = self::course_copy( $course_id );
		$user_id   = (int) $review->post_author;
		$rating    = (float) get_post_meta( $review_id, 'wdm_course_review_review_rating', true );
		$item      = array(
			'source_id' => $review_id,
			'title'     => self::user_label( $user_id ) . ' → ' . self::post_title( $course_id ),
			'type'      => __( 'Course review (Ratings, Reviews, and Feedback)', 'masterstudy-lms-learning-management-system' ),
			'course'    => self::post_title( $course_id ),
			'post_id'   => self::report_post_id( $course_id ),
		);

		$reason = '';

		if ( 'trash' === $review->post_status ) {
			$reason = __( 'The review is trashed in LearnDash, so it was not imported.', 'masterstudy-lms-learning-management-system' );
		} elseif ( ! $user_id || ! get_userdata( $user_id ) ) {
			$reason = __( 'The review author is a deleted user; MasterStudy reviews belong to a student, so it was not imported.', 'masterstudy-lms-learning-management-system' );
		} elseif ( ! $copy_id ) {
			$reason = __( 'The reviewed course was not migrated, so the review was not imported.', 'masterstudy-lms-learning-management-system' );
		} elseif ( $rating < 1 ) {
			$reason = __( 'The review has no star rating, so it was not imported.', 'masterstudy-lms-learning-management-system' );
		}

		if ( '' !== $reason ) {
			Report::add(
				Report::GROUP_REVIEWS,
				array_merge(
					$item,
					array(
						'reason' => $reason,
						'status' => Report::STATUS_UNSUPPORTED,
					)
				)
			);
			return;
		}

		$content = (string) $review->post_content;
		$title   = trim( (string) $review->post_title );

		if ( '' !== $title ) {
			$content = '<p><strong>' . esc_html( $title ) . '</strong></p>' . $content;
		}

		Target::add_review(
			array(
				'course_id' => $copy_id,
				'user_id'   => $user_id,
				'mark'      => min( 5, $rating ),
				'content'   => $content,
				'date'      => (string) $review->post_date,
				'approved'  => 'publish' === $review->post_status,
				'source_id' => 'wdm-review-' . $review_id,
			),
			self::SOURCE
		);
	}

	/**
	 * Migrate one LearnDash 4.1+ coupon (ld-coupon post) to a MasterStudy coupon (Pro Plus). Coupons that
	 * only apply to groups are reported (MasterStudy coupons apply to courses).
	 *
	 * Settings are read from the `_ld-coupon` settings array (prefixed or not) or plain post meta.
	 *
	 * @param int $coupon_id Coupon post ID.
	 */
	public static function migrate_single_coupon( int $coupon_id ): void {
		$post = get_post( $coupon_id );

		if ( ! $post ) {
			return;
		}

		$settings = self::coupon_settings( $coupon_id );
		$code     = (string) ( $settings['code'] ?? '' );
		$item     = array(
			'source_id' => $coupon_id,
			'title'     => '' !== $code ? $code : self::post_title( $coupon_id ),
			'type'      => __( 'LearnDash coupon', 'masterstudy-lms-learning-management-system' ),
		);

		$report = function ( string $reason, string $status = Report::STATUS_UNSUPPORTED ) use ( $item ) {
			Report::add(
				Report::GROUP_ORDERS,
				array_merge(
					$item,
					array(
						'reason' => $reason,
						'status' => $status,
					)
				)
			);
		};

		if ( ! ProTarget::plus_active() ) {
			$report( __( 'Coupons require MasterStudy LMS Pro Plus, which is not active, so the coupon was not imported.', 'masterstudy-lms-learning-management-system' ) );
			return;
		}

		if ( '' === $code ) {
			$report( __( 'The coupon has no code, so it was not imported.', 'masterstudy-lms-learning-management-system' ) );
			return;
		}

		$all_courses = 'on' === (string) ( $settings['apply_to_all_courses'] ?? '' ) || '1' === (string) ( $settings['apply_to_all_courses'] ?? '' );

		// The MasterStudy copies of the coupon's LearnDash courses.
		$course_ids = array();
		foreach ( array_map( 'intval', (array) ( $settings['courses'] ?? array() ) ) as $id ) {
			$copy = self::course_copy( $id );

			if ( $copy ) {
				$course_ids[] = $copy;
			}
		}

		$course_ids  = array_values( array_unique( $course_ids ) );
		$groups_only = ! $all_courses && empty( $course_ids );

		if ( $groups_only ) {
			$report( __( 'The coupon only applies to LearnDash groups (or to courses that were not migrated); MasterStudy coupons apply to courses, so it was not imported.', 'masterstudy-lms-learning-management-system' ) );
			return;
		}

		$end    = self::drip_timestamp( $settings['end_date'] ?? '' );
		$active = 'publish' === $post->post_status && ( ! $end || $end > time() );

		$created = ProTarget::create_coupon(
			array(
				'code'        => $code,
				'title'       => (string) $post->post_title,
				'type'        => 'flat' === (string) ( $settings['type'] ?? '' ) ? 'amount' : 'percent',
				'amount'      => (float) ( $settings['amount'] ?? 0 ),
				'status'      => $active ? 'active' : 'inactive',
				'usage_limit' => (int) ( $settings['max_redemptions'] ?? 0 ),
				'used_count'  => (int) ( $settings['redemptions'] ?? 0 ),
				'start'       => self::drip_timestamp( $settings['start_date'] ?? '' ),
				'end'         => $end,
				'course_ids'  => $all_courses ? array() : $course_ids,
			)
		);

		if ( ! $created ) {
			$report( __( 'A MasterStudy coupon with the same code already exists (or the coupons table is missing), so the LearnDash coupon was not imported again.', 'masterstudy-lms-learning-management-system' ), Report::STATUS_PARTIAL );
			return;
		}

		if ( ! empty( $settings['apply_to_all_groups'] ) || ! empty( $settings['groups'] ) ) {
			$report( __( 'The coupon was imported for its courses; the LearnDash groups it also applied to have no MasterStudy coupon equivalent.', 'masterstudy-lms-learning-management-system' ), Report::STATUS_PARTIAL );
		}
	}

	/**
	 * LearnDash coupon settings (unprefixed keys: code, type, amount, redemptions, max_redemptions,
	 * start_date, end_date, apply_to_all_courses, courses, apply_to_all_groups, groups).
	 *
	 * @param int $coupon_id Coupon post ID.
	 */
	private static function coupon_settings( int $coupon_id ): array {
		$settings = array();
		$stored   = get_post_meta( $coupon_id, '_ld-coupon', true );

		foreach ( is_array( $stored ) ? $stored : array() as $key => $value ) {
			$settings[ preg_replace( '/^ld-coupon_/', '', (string) $key ) ] = $value;
		}

		foreach ( array( 'code', 'type', 'amount', 'redemptions', 'max_redemptions', 'start_date', 'end_date', 'apply_to_all_courses', 'courses', 'apply_to_all_groups', 'groups' ) as $key ) {
			if ( ! isset( $settings[ $key ] ) && metadata_exists( 'post', $coupon_id, $key ) ) {
				$settings[ $key ] = get_post_meta( $coupon_id, $key, true );
			}
		}

		return $settings;
	}

	/**
	 * Without MasterStudy LMS Pro: enroll users who were added to a LearnDash Group but never
	 * visited the course page, so they have no row in wp_learndash_user_activity (activity_type='access').
	 *
	 * Reads group membership from `learndash_group_users_{group_id}` meta (group post meta, or the
	 * user meta LearnDash actually writes) and course assignments from `ld_auto_enroll_group_course_ids`
	 * group meta (or `learndash_group_enrolled_{group_id}` course meta).
	 * Target::enroll() is idempotent, so pairs enrolled by the main step are skipped.
	 */
	private static function migrate_group_enrollments(): void {
		global $wpdb;

		$group_ids = $wpdb->get_col(
			$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'", 'groups' )
		);

		if ( empty( $group_ids ) ) {
			return;
		}

		foreach ( $group_ids as $group_id ) {
			$group_id = (int) $group_id;

			// Same sources as with Pro: group course meta + auto-enroll setting, member user meta + legacy list.
			$course_ids = self::group_course_ids( $group_id );
			$member_ids = self::group_user_ids( $group_id, 'users' );

			self::report_group_extras( $group_id );

			Report::add(
				Report::GROUP_ENROLLMENTS,
				array(
					'source_id' => $group_id,
					'title'     => self::post_title( $group_id ),
					'type'      => __( 'LearnDash group', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'Importing LearnDash groups requires MasterStudy LMS Pro, which is not active — the group was not created; its members were enrolled directly in the group courses.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_PARTIAL,
					'post_id'   => $group_id,
				)
			);

			if ( empty( $course_ids ) || empty( $member_ids ) ) {
				continue;
			}

			self::enroll_group_members( $group_id, array_map( 'intval', (array) $course_ids ), array_map( 'intval', (array) $member_ids ) );
		}
	}

	/**
	 * Enroll LearnDash group members directly in the copies of the group courses (no MasterStudy group).
	 *
	 * @param int   $group_id   LearnDash group ID.
	 * @param int[] $course_ids Source group course IDs.
	 * @param int[] $member_ids Group member user IDs.
	 */
	private static function enroll_group_members( int $group_id, array $course_ids, array $member_ids ): void {
		$group_type = __( 'Group enrollment', 'masterstudy-lms-learning-management-system' );
		/* translators: %s: group title */
		$group_parent = sprintf( __( 'Group: %s', 'masterstudy-lms-learning-management-system' ), self::post_title( $group_id ) );

		foreach ( $course_ids as $source_course_id ) {
			$source_course_id = (int) $source_course_id;
			$course_id        = self::course_copy( $source_course_id );
			if ( ! $course_id ) {
				Report::add(
					Report::GROUP_ENROLLMENTS,
					array(
						'source_id' => $group_id,
						/* translators: 1: group title, 2: course ID */
						'title'     => sprintf( __( '%1$s → Course #%2$d', 'masterstudy-lms-learning-management-system' ), self::post_title( $group_id ), $source_course_id ),
						'type'      => $group_type,
						'reason'    => __( 'The group is assigned to a course that no longer exists or was not migrated, so its members were not enrolled in it.', 'masterstudy-lms-learning-management-system' ),
						'status'    => Report::STATUS_UNSUPPORTED,
						'parent'    => $group_parent,
						'post_id'   => $group_id,
					)
				);
				continue;
			}

			foreach ( $member_ids as $user_id ) {
				$user_id = (int) $user_id;
				if ( ! $user_id || ! get_userdata( $user_id ) ) {
					Report::add(
						Report::GROUP_ENROLLMENTS,
						array(
							'source_id' => $group_id,
							'title'     => self::user_label( $user_id ) . ' → ' . self::post_title( $course_id ),
							'type'      => $group_type,
							'reason'    => __( 'The group member no longer exists as a WordPress user, so no enrollment was created.', 'masterstudy-lms-learning-management-system' ),
							'status'    => Report::STATUS_UNSUPPORTED,
							'parent'    => $group_parent,
							'course'    => self::post_title( $course_id ),
							'post_id'   => $course_id,
						)
					);
					continue;
				}

				// Skip if already enrolled via the main enrollments step.
				if ( Target::is_enrolled( $user_id, $course_id ) ) {
					continue;
				}

				$enrolled_ts = (int) get_user_meta( $user_id, "learndash_group_{$group_id}_enrolled_at", true );

				try {
					Target::enroll( $user_id, $course_id, $enrolled_ts > 0 ? $enrolled_ts : time() );
				} catch ( \Exception $e ) {
					Helper::log( 'error', sprintf( 'LearnDash migration: group %d enrollment failed — %s', $group_id, $e->getMessage() ) );
					Report::add(
						Report::GROUP_ENROLLMENTS,
						array(
							'source_id' => $group_id,
							'title'     => self::user_label( $user_id ) . ' → ' . self::post_title( $course_id ),
							'type'      => $group_type,
							/* translators: %s: error message */
							'reason'    => sprintf( __( 'The enrollment could not be saved: %s', 'masterstudy-lms-learning-management-system' ), $e->getMessage() ),
							'status'    => Report::STATUS_FAILED,
							'parent'    => $group_parent,
							'course'    => self::post_title( $course_id ),
							'post_id'   => $course_id,
						)
					);
				}
			}
		}
	}

	/**
	 * With MasterStudy LMS Pro: turn every published LearnDash Group into a MasterStudy group
	 * (addon "enterprise_courses") — first group leader → group admin, members → group members,
	 * group courses → group courses (members are enrolled with enterprise_id). No users are created
	 * and no emails are sent. Idempotent per LearnDash group.
	 */
	private static function migrate_groups(): void {
		global $wpdb;

		$group_ids = $wpdb->get_col(
			$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' ORDER BY ID ASC", 'groups' )
		);

		foreach ( (array) $group_ids as $group_id ) {
			$group_id    = (int) $group_id;
			$group_title = self::post_title( $group_id );
			$group_item  = array(
				'source_id' => $group_id,
				'title'     => $group_title,
				'type'      => __( 'LearnDash group', 'masterstudy-lms-learning-management-system' ),
				'post_id'   => $group_id,
			);

			// Only migrated courses (their MasterStudy copies) can be group courses.
			$course_ids        = array();
			$source_course_ids = array();
			foreach ( self::group_course_ids( $group_id ) as $course_id ) {
				$copy = self::course_copy( $course_id );

				if ( $copy ) {
					$course_ids[]        = $copy;
					$source_course_ids[] = $course_id;
					continue;
				}

				Report::add(
					Report::GROUP_ENROLLMENTS,
					array(
						'source_id' => $group_id,
						/* translators: 1: group title, 2: course ID */
						'title'     => sprintf( __( '%1$s → Course #%2$d', 'masterstudy-lms-learning-management-system' ), $group_title, $course_id ),
						'type'      => __( 'Group course', 'masterstudy-lms-learning-management-system' ),
						'reason'    => __( 'The group is assigned to a course that no longer exists or was not migrated, so it was left out of the MasterStudy group.', 'masterstudy-lms-learning-management-system' ),
						'status'    => Report::STATUS_UNSUPPORTED,
						/* translators: %s: group title */
						'parent'    => sprintf( __( 'Group: %s', 'masterstudy-lms-learning-management-system' ), $group_title ),
						'post_id'   => $group_id,
					)
				);
			}

			self::report_group_extras( $group_id );

			try {
				self::maybe_create_group_bundle( $group_id, $course_ids );
			} catch ( \Exception $e ) {
				Report::add(
					Report::GROUP_ENROLLMENTS,
					array_merge(
						$group_item,
						array(
							/* translators: %s: error message */
							'reason' => sprintf( __( 'The course bundle for the sold group could not be created: %s', 'masterstudy-lms-learning-management-system' ), $e->getMessage() ),
							'status' => Report::STATUS_FAILED,
						)
					)
				);
			}

			$all_members = self::group_user_ids( $group_id, 'users' );
			$members     = array_values( array_filter( $all_members, 'get_userdata' ) );
			$leaders     = array_values( array_filter( self::group_user_ids( $group_id, 'leaders' ), 'get_userdata' ) );
			$admin_id    = $leaders ? (int) $leaders[0] : (int) get_post_field( 'post_author', $group_id );

			if ( count( $members ) < count( $all_members ) ) {
				Report::add(
					Report::GROUP_ENROLLMENTS,
					array_merge(
						$group_item,
						array(
							'reason' => sprintf(
								/* translators: %d: number of users */
								_n(
									'%d group member no longer exists as a WordPress user, so it was left out of the group.',
									'%d group members no longer exist as WordPress users, so they were left out of the group.',
									count( $all_members ) - count( $members ),
									'masterstudy-lms-learning-management-system'
								),
								count( $all_members ) - count( $members )
							),
							'status' => Report::STATUS_PARTIAL,
						)
					)
				);
			}

			if ( ! $admin_id || ! get_userdata( $admin_id ) ) {
				// A MasterStudy group needs an admin — keep the members' access at least.
				Report::add(
					Report::GROUP_ENROLLMENTS,
					array_merge(
						$group_item,
						array(
							'reason' => __( 'The group has no leader or author who still exists, so no MasterStudy group could be created — its members were enrolled directly in the group courses.', 'masterstudy-lms-learning-management-system' ),
							'status' => Report::STATUS_PARTIAL,
						)
					)
				);
				self::enroll_group_members( $group_id, $source_course_ids, $members );
				continue;
			}

			try {
				$ms_group_id = ProTarget::create_group(
					array(
						'source_id'  => $group_id,
						'title'      => (string) get_post_field( 'post_title', $group_id ),
						'admin_id'   => $admin_id,
						'member_ids' => $members,
						'course_ids' => $course_ids,
					),
					self::SOURCE
				);
			} catch ( \Exception $e ) {
				Helper::log( 'error', sprintf( 'LearnDash migration: group %d not migrated — %s', $group_id, $e->getMessage() ) );
				Report::add(
					Report::GROUP_ENROLLMENTS,
					array_merge(
						$group_item,
						array(
							/* translators: %s: error message */
							'reason' => sprintf( __( 'The group could not be created: %s', 'masterstudy-lms-learning-management-system' ), $e->getMessage() ),
							'status' => Report::STATUS_FAILED,
						)
					)
				);
				continue;
			}

			// Keep the LearnDash group enrollment dates on the enrollments the group created.
			foreach ( $members as $user_id ) {
				$enrolled_ts = (int) get_user_meta( $user_id, "learndash_group_{$group_id}_enrolled_at", true );

				if ( $enrolled_ts > 0 && ! empty( $course_ids ) ) {
					$wpdb->query(
						$wpdb->prepare(
							"UPDATE {$wpdb->prefix}stm_lms_user_courses SET start_time = %d WHERE user_id = %d AND enterprise_id = %d",
							$enrolled_ts,
							$user_id,
							$ms_group_id
						)
					);
				}
			}

			if ( count( $leaders ) > 1 ) {
				Report::add(
					Report::GROUP_ENROLLMENTS,
					array_merge(
						$group_item,
						array(
							'reason' => sprintf(
								/* translators: 1: group admin label, 2: comma-separated user labels */
								__( 'MasterStudy groups have a single admin, so %1$s became the group admin; the other group leaders (%2$s) were not given access to the group.', 'masterstudy-lms-learning-management-system' ),
								self::user_label( $admin_id ),
								implode(
									', ',
									array_map(
										function ( $user_id ) {
											return self::user_label( (int) $user_id );
										},
										array_slice( $leaders, 1 )
									)
								)
							),
							'status' => Report::STATUS_PARTIAL,
						)
					)
				);
			}
		}
	}

	/**
	 * Course IDs of a LearnDash group (`learndash_group_enrolled_{id}` course meta, plus the
	 * `ld_auto_enroll_group_course_ids` group setting).
	 *
	 * @param int $group_id LearnDash group ID.
	 * @return int[]
	 */
	private static function group_course_ids( int $group_id ): array {
		global $wpdb;

		$course_ids = array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", "learndash_group_enrolled_{$group_id}" )
			)
		);

		$auto = get_post_meta( $group_id, 'ld_auto_enroll_group_course_ids', true );
		if ( is_array( $auto ) ) {
			$course_ids = array_merge( $course_ids, array_map( 'intval', $auto ) );
		}

		return array_values( array_unique( array_filter( $course_ids ) ) );
	}

	/**
	 * Members or leaders of a LearnDash group (`learndash_group_users_{id}` /
	 * `learndash_group_leaders_{id}` user meta, plus the legacy group post meta list).
	 *
	 * @param int    $group_id LearnDash group ID.
	 * @param string $kind     users|leaders.
	 * @return int[]
	 */
	private static function group_user_ids( int $group_id, string $kind ): array {
		global $wpdb;

		$key      = 'leaders' === $kind ? "learndash_group_leaders_{$group_id}" : "learndash_group_users_{$group_id}";
		$user_ids = array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s ORDER BY umeta_id ASC", $key )
			)
		);

		$legacy = get_post_meta( $group_id, $key, true );
		if ( is_array( $legacy ) ) {
			$user_ids = array_merge( $user_ids, array_map( 'intval', $legacy ) );
		}

		return array_values( array_unique( array_filter( $user_ids ) ) );
	}

	/**
	 * Recount `current_students` for every copied LearnDash course that has MasterStudy enrollments
	 * (other MasterStudy courses are left alone).
	 */
	private static function refresh_all_students_counts(): void {
		global $wpdb;

		$enrolled = array_map( 'intval', (array) $wpdb->get_col( "SELECT DISTINCT course_id FROM {$wpdb->prefix}stm_lms_user_courses" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		foreach ( array_intersect( self::copied_course_ids(), $enrolled ) as $course_id ) {
			Target::refresh_students_count( (int) $course_id );
		}
	}

	/**
	 * Unix time the user completed a LearnDash course: `course_completed_{id}` user meta, else the
	 * completed 'course' activity row. 0 when the course was not completed.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 */
	private static function course_completed_time( int $user_id, int $course_id ): int {
		global $wpdb;

		$completed = (int) get_user_meta( $user_id, "course_completed_{$course_id}", true );

		if ( $completed > 0 ) {
			return $completed;
		}

		$table = $wpdb->prefix . 'learndash_user_activity';

		if ( ! self::table_exists( $table ) ) {
			return 0;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(activity_completed) FROM {$table} WHERE user_id = %d AND post_id = %d AND activity_type = 'course' AND activity_status = 1 AND activity_completed > 0", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$course_id
			)
		);
	}

	/**
	 * Keep a LearnDash course completion on an enrollment that already exists (e.g. created by a
	 * migrated group): recalculate_progress() then forces its progress to 100.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id MasterStudy course (the copy).
	 * @param int $completed Completion Unix time (0 = not completed, nothing to do).
	 */
	private static function mark_enrollment_completed( int $user_id, int $course_id, int $completed ): void {
		global $wpdb;

		if ( $completed <= 0 ) {
			return;
		}

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}stm_lms_user_courses SET end_time = %d WHERE user_id = %d AND course_id = %d AND ( end_time IS NULL OR end_time = 0 )",
				$completed,
				$user_id,
				$course_id
			)
		);
	}

	/**
	 * Whether the user's LearnDash course access expired (LearnDash flags `learndash_course_expired_{id}` and
	 * removes the `course_{id}_access_from` meta; a re-enrollment sets the access meta again).
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 */
	private static function access_expired( int $user_id, int $course_id ): bool {
		return '' !== (string) get_user_meta( $user_id, "learndash_course_expired_{$course_id}", true )
			&& '' === (string) get_user_meta( $user_id, "course_{$course_id}_access_from", true );
	}

	/**
	 * Report an expired LearnDash enrollment that was not imported.
	 *
	 * @param int        $user_id   User ID.
	 * @param int        $course_id Course ID.
	 * @param int|string $source_id Source record (activity ID or user meta).
	 */
	private static function report_expired_enrollment( int $user_id, int $course_id, $source_id ): void {
		$expired = (int) get_user_meta( $user_id, "learndash_course_expired_{$course_id}", true );

		Report::add(
			Report::GROUP_ENROLLMENTS,
			array(
				'source_id' => $source_id,
				'title'     => self::user_label( $user_id ) . ' → ' . self::post_title( $course_id ),
				'type'      => __( 'Expired enrollment', 'masterstudy-lms-learning-management-system' ),
				'reason'    => $expired > 1
					? sprintf(
						/* translators: %s: date */
						__( 'The student\'s LearnDash course access expired on %s, so no MasterStudy enrollment was created (their progress is kept).', 'masterstudy-lms-learning-management-system' ),
						wp_date( get_option( 'date_format' ), $expired )
					)
					: __( 'The student\'s LearnDash course access expired, so no MasterStudy enrollment was created (their progress is kept).', 'masterstudy-lms-learning-management-system' ),
				'status'    => Report::STATUS_UNSUPPORTED,
				'course'    => self::post_title( $course_id ),
				'post_id'   => self::report_post_id( $course_id ),
			)
		);
	}

	/**
	 * Enrollments recorded only as LearnDash `course_{id}_access_from` user meta (no 'access' activity row).
	 * Idempotent: pairs already enrolled are skipped.
	 */
	private static function migrate_meta_enrollments(): void {
		global $wpdb;

		$last_id = 0;

		do {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT umeta_id, user_id, meta_key, meta_value FROM {$wpdb->usermeta} WHERE meta_key LIKE %s AND umeta_id > %d ORDER BY umeta_id ASC LIMIT 500",
					$wpdb->esc_like( 'course_' ) . '%' . $wpdb->esc_like( '_access_from' ),
					$last_id
				),
				ARRAY_A
			);

			foreach ( (array) $rows as $row ) {
				$last_id = (int) $row['umeta_id'];

				if ( ! preg_match( '/^course_(\d+)_access_from$/', (string) $row['meta_key'], $m ) ) {
					continue;
				}

				$user_id   = (int) $row['user_id'];
				$course_id = (int) $m[1];
				$copy_id   = self::course_copy( $course_id );

				if ( ! $user_id || ! $course_id || ( $copy_id && Target::is_enrolled( $user_id, $copy_id ) ) ) {
					continue;
				}

				$item = array(
					'source_id' => $row['umeta_id'],
					'title'     => self::user_label( $user_id ) . ' → ' . self::post_title( $course_id ),
					'type'      => __( 'Course enrollment', 'masterstudy-lms-learning-management-system' ),
					'course'    => self::post_title( $course_id ),
					'post_id'   => self::report_post_id( $course_id ),
				);

				if ( ! get_userdata( $user_id ) || ! $copy_id ) {
					Report::add(
						Report::GROUP_ENROLLMENTS,
						array_merge(
							$item,
							array(
								'reason' => __( 'The enrollment belongs to a user or course that does not exist in MasterStudy, so it was skipped.', 'masterstudy-lms-learning-management-system' ),
								'status' => Report::STATUS_UNSUPPORTED,
							)
						)
					);
					continue;
				}

				$completed = self::course_completed_time( $user_id, $course_id );

				try {
					Target::enroll( $user_id, $copy_id, (int) $row['meta_value'], $completed ? $completed : null, $completed ? 100 : 0 );
				} catch ( \Exception $e ) {
					Report::add(
						Report::GROUP_ENROLLMENTS,
						array_merge(
							$item,
							array(
								/* translators: %s: error message */
								'reason' => sprintf( __( 'The enrollment could not be saved: %s', 'masterstudy-lms-learning-management-system' ), $e->getMessage() ),
								'status' => Report::STATUS_FAILED,
							)
						)
					);
				}
			}
		} while ( ! empty( $rows ) );

		self::migrate_access_list_enrollments();
	}

	/**
	 * Enrollments recorded only in a legacy LearnDash course access list (LearnDash 2.x). The list has no
	 * enrollment date, so the course publish date is used. Idempotent.
	 */
	private static function migrate_access_list_enrollments(): void {
		foreach ( self::course_access_lists() as $course_id => $user_ids ) {
			$copy_id = self::course_copy( (int) $course_id );

			if ( ! $copy_id ) {
				continue;
			}

			foreach ( $user_ids as $user_id ) {
				if ( Target::is_enrolled( $user_id, $copy_id ) ) {
					continue;
				}

				$item = array(
					'source_id' => $course_id . ':' . $user_id,
					'title'     => self::user_label( $user_id ) . ' → ' . self::post_title( $course_id ),
					'type'      => __( 'Course access list', 'masterstudy-lms-learning-management-system' ),
					'course'    => self::post_title( $course_id ),
					'post_id'   => $copy_id,
				);

				if ( ! get_userdata( $user_id ) ) {
					Report::add(
						Report::GROUP_ENROLLMENTS,
						array_merge(
							$item,
							array(
								'reason' => __( 'The course access list names a user who no longer exists, so no enrollment was created.', 'masterstudy-lms-learning-management-system' ),
								'status' => Report::STATUS_UNSUPPORTED,
							)
						)
					);
					continue;
				}

				if ( self::access_expired( $user_id, $course_id ) ) {
					self::report_expired_enrollment( $user_id, $course_id, $item['source_id'] );
					continue;
				}

				$completed = self::course_completed_time( $user_id, $course_id );

				try {
					Target::enroll( $user_id, $copy_id, (int) get_post_time( 'U', true, $course_id ), $completed ? $completed : null, $completed ? 100 : 0 );
				} catch ( \Exception $e ) {
					Report::add(
						Report::GROUP_ENROLLMENTS,
						array_merge(
							$item,
							array(
								/* translators: %s: error message */
								'reason' => sprintf( __( 'The enrollment could not be saved: %s', 'masterstudy-lms-learning-management-system' ), $e->getMessage() ),
								'status' => Report::STATUS_FAILED,
							)
						)
					);
				}
			}
		}
	}

	/**
	 * Progress kept in LearnDash user meta that has no activity row: completed lessons/topics from
	 * `_sfwd-course_progress` and finished quiz attempts from `_sfwd-quizzes`. Runs after the activity
	 * rows were imported (and before the quiz_attempts step), so everything already imported is skipped.
	 */
	private static function migrate_meta_progress(): void {
		global $wpdb;

		$has_refs = self::table_exists( self::pro_quiz_table( 'statistic_ref' ) );
		$last_id  = 0;

		do {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT umeta_id, user_id, meta_key, meta_value FROM {$wpdb->usermeta} WHERE meta_key IN ('_sfwd-course_progress', '_sfwd-quizzes') AND umeta_id > %d ORDER BY umeta_id ASC LIMIT 200",
					$last_id
				),
				ARRAY_A
			);

			foreach ( (array) $rows as $row ) {
				$last_id = (int) $row['umeta_id'];
				$user_id = (int) $row['user_id'];
				$data    = maybe_unserialize( $row['meta_value'] );

				if ( ! $user_id || ! is_array( $data ) || ! get_userdata( $user_id ) ) {
					continue;
				}

				if ( '_sfwd-course_progress' === $row['meta_key'] ) {
					foreach ( $data as $course_id => $progress ) {
						$copy_id = self::course_copy( (int) $course_id );

						if ( ! is_array( $progress ) || ! self::tracks_progress( $user_id, $copy_id ) ) {
							continue;
						}

						$course_items = self::curriculum_post_ids( $copy_id );
						$done         = array();

						foreach ( (array) ( $progress['lessons'] ?? array() ) as $lesson_id => $flag ) {
							if ( ! empty( $flag ) ) {
								$done[] = (int) $lesson_id;
							}
						}

						foreach ( (array) ( $progress['topics'] ?? array() ) as $topics ) {
							foreach ( (array) $topics as $topic_id => $flag ) {
								if ( ! empty( $flag ) ) {
									$done[] = (int) $topic_id;
								}
							}
						}

						foreach ( array_unique( $done ) as $lesson_id ) {
							$lesson_copy = Target::copy_of( self::SOURCE, $lesson_id );

							if ( $lesson_copy && in_array( $lesson_copy, $course_items, true ) && PostType::LESSON === get_post_type( $lesson_copy ) ) {
								Target::complete_lesson( $user_id, $copy_id, $lesson_copy );
							}
						}
					}
					continue;
				}

				// _sfwd-quizzes: one entry per finished attempt, in chronological order.
				foreach ( $data as $attempt ) {
					if ( ! is_array( $attempt ) || empty( $attempt['quiz'] ) ) {
						continue;
					}

					$quiz_id = (int) $attempt['quiz'];

					// Attempts with statistics are imported (with answers) by the quiz_attempts step — same rule
					// as for the quiz activity rows: per attempt (statistic_ref_id, 0 = statistics off), or per
					// user and quiz for entries without it.
					$ref_id = isset( $attempt['statistic_ref_id'] ) && is_numeric( $attempt['statistic_ref_id'] ) ? (int) $attempt['statistic_ref_id'] : null;
					$stats  = null === $ref_id
						? $has_refs && self::has_quiz_statistics( $user_id, $quiz_id )
						: $ref_id > 0 && self::statistic_ref_exists( $ref_id );

					if ( $stats ) {
						continue;
					}

					$quiz_copy = Target::copy_of( self::SOURCE, $quiz_id );
					$copy_id   = self::course_copy( (int) ( $attempt['course'] ?? 0 ) );
					if ( $quiz_copy && ( ! $copy_id || ! in_array( $quiz_copy, self::curriculum_post_ids( $copy_id ), true ) ) ) {
						$course_ids = Target::course_ids_of( $quiz_copy );
						$copy_id    = (int) reset( $course_ids );
					}

					if ( ! $copy_id || ! $quiz_copy || PostType::QUIZ !== get_post_type( $quiz_copy ) || ! self::tracks_progress( $user_id, $copy_id ) ) {
						continue;
					}

					$time       = (int) ( ! empty( $attempt['completed'] ) ? $attempt['completed'] : ( $attempt['time'] ?? 0 ) );
					$created_at = self::site_datetime( $time > 0 ? $time : time() );

					$passed  = ! empty( $attempt['pass'] );
					$percent = isset( $attempt['percentage'] ) && is_numeric( $attempt['percentage'] ) ? (float) $attempt['percentage'] : ( $passed ? 100.0 : 0.0 );

					// Same attempt already imported from its activity row.
					if ( Target::quiz_attempt_exists( $user_id, $quiz_copy, $created_at, $percent, $passed ) ) {
						continue;
					}

					Target::add_quiz_attempt( $user_id, $copy_id, $quiz_copy, $percent, $passed, $created_at );
				}
			}
		} while ( ! empty( $rows ) );
	}

	/**
	 * Whether LearnDash progress of a user in a course should be imported: the user is enrolled in the
	 * migrated course, or the course is "open" (LearnDash tracks progress without enrollment).
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id MasterStudy course (the copy).
	 */
	private static function tracks_progress( int $user_id, int $course_id ): bool {
		if ( ! $course_id || PostType::COURSE !== get_post_type( $course_id ) ) {
			return false;
		}

		if ( Target::is_enrolled( $user_id, $course_id ) ) {
			return true;
		}

		$price_type = (string) get_post_meta( $course_id, '_migrated_ld_price_type', true );

		return '' === $price_type || 'open' === $price_type;
	}

	/**
	 * Post IDs in a MasterStudy course curriculum.
	 *
	 * @param int $course_id Course ID.
	 * @return int[]
	 */
	private static function curriculum_post_ids( int $course_id ): array {
		global $wpdb;

		static $cache = array();

		if ( ! isset( $cache[ $course_id ] ) ) {
			$cache[ $course_id ] = array_map(
				'intval',
				(array) $wpdb->get_col(
					$wpdb->prepare(
						"SELECT m.post_id FROM {$wpdb->prefix}stm_lms_curriculum_materials m
						 INNER JOIN {$wpdb->prefix}stm_lms_curriculum_sections s ON s.id = m.section_id
						 WHERE s.course_id = %d",
						$course_id
					)
				)
			);
		}

		return $cache[ $course_id ];
	}

	/**
	 * Amount actually paid for a LearnDash transaction, or null when the transaction does not record it.
	 * PayPal IPN: mc_gross; Stripe add-on: stripe_price; LearnDash 4 gateways: pricing_info (discounted price).
	 *
	 * @param int $order_id Transaction post ID.
	 */
	private static function transaction_amount( int $order_id ): ?float {
		// A LearnDash 4.5+ renewal charge without an amount of its own pays its parent's price.
		for ( $id = $order_id, $level = 0; $id && $level < 3; $id = self::parent_transaction( $id ), $level++ ) {
			$pricing = self::plain_array( get_post_meta( $id, 'pricing_info', true ) );

			if ( ! empty( $pricing ) ) {
				if ( ! empty( $pricing['discount'] ) && null !== self::parse_amount( $pricing['discounted_price'] ?? null ) ) {
					return self::parse_amount( $pricing['discounted_price'] );
				}

				if ( null !== self::parse_amount( $pricing['price'] ?? null ) ) {
					return self::parse_amount( $pricing['price'] );
				}
			}

			// mc_amount3: the recurring amount of a PayPal subscription sign-up (no mc_gross on that notification).
			foreach ( array( 'mc_gross', 'stripe_price', 'mc_amount3' ) as $key ) {
				$value = self::parse_amount( get_post_meta( $id, $key, true ) );

				if ( null !== $value ) {
					return $value;
				}
			}
		}

		return null;
	}

	/**
	 * Currency of a LearnDash transaction: a meta key ending with `_currency` (mc_currency, stripe_currency) or
	 * `pricing_info`, on the transaction or (LearnDash 4.5+) its parent.
	 *
	 * @param int $order_id Transaction post ID.
	 */
	private static function transaction_currency( int $order_id ): string {
		for ( $id = $order_id, $level = 0; $id && $level < 3; $id = self::parent_transaction( $id ), $level++ ) {
			foreach ( (array) get_post_meta( $id ) as $key => $value ) {
				if ( preg_match( '/_currency$/', (string) $key ) && '_order_currency' !== $key && '' !== trim( (string) ( $value[0] ?? '' ) ) ) {
					return strtoupper( sanitize_text_field( (string) $value[0] ) );
				}
			}

			$pricing = self::plain_array( get_post_meta( $id, 'pricing_info', true ) );
			if ( ! empty( $pricing['currency'] ) && is_scalar( $pricing['currency'] ) ) {
				return strtoupper( sanitize_text_field( (string) $pricing['currency'] ) );
			}
		}

		return '';
	}

	/**
	 * Meta of a LearnDash transaction, falling back to its parent transactions (LearnDash 4.5+ keeps the gateway
	 * data on the checkout's parent transaction; a renewal charge is a child of the product transaction).
	 *
	 * @param int    $order_id Transaction post ID.
	 * @param string $key      Meta key.
	 * @return mixed '' when no transaction of the chain has the key.
	 */
	private static function transaction_meta( int $order_id, string $key ) {
		for ( $id = $order_id, $level = 0; $id && $level < 3; $id = self::parent_transaction( $id ), $level++ ) {
			$value = get_post_meta( $id, $key, true );

			if ( '' !== $value && null !== $value && array() !== $value ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * Parent transaction of a LearnDash 4.5+ child transaction (0 = none).
	 *
	 * @param int $order_id Transaction post ID.
	 */
	private static function parent_transaction( int $order_id ): int {
		$parent_id = (int) get_post_field( 'post_parent', $order_id );

		return $parent_id && 'sfwd-transactions' === get_post_type( $parent_id ) ? $parent_id : 0;
	}

	/**
	 * Whether a transaction is a LearnDash 4.5+ parent transaction (it has child transactions).
	 *
	 * @param int $order_id Transaction post ID.
	 */
	private static function has_child_transactions( int $order_id ): bool {
		global $wpdb;

		return (bool) $wpdb->get_var(
			$wpdb->prepare( "SELECT 1 FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = %s LIMIT 1", $order_id, 'sfwd-transactions' )
		);
	}

	/**
	 * Gateway payment ID of a LearnDash 4.5+ transaction from its `gateway_transaction` data: PayPal IPN
	 * event txn_id, Razorpay event payload payment entity id, otherwise (Stripe, …) the gateway transaction id.
	 *
	 * @param int    $order_id  Transaction post ID.
	 * @param string $processor `ld_payment_processor` (lowercase).
	 */
	private static function gateway_transaction_id( int $order_id, string $processor ): string {
		$gateway = self::plain_array( self::transaction_meta( $order_id, 'gateway_transaction' ) );

		if ( empty( $gateway ) ) {
			return '';
		}

		$event   = self::plain_array( $gateway['event'] ?? array() );
		$ipn     = $event['txn_id'] ?? '';
		$payment = self::plain_array( $event['payload']['payment']['entity'] ?? array() );
		$razor   = $payment['id'] ?? '';

		$candidates = array( $gateway['id'] ?? '', $ipn, $razor );

		if ( false !== strpos( $processor, 'paypal' ) ) {
			array_unshift( $candidates, $ipn );
		} elseif ( false !== strpos( $processor, 'razorpay' ) ) {
			array_unshift( $candidates, $razor );
		}

		foreach ( $candidates as $candidate ) {
			if ( is_scalar( $candidate ) && '' !== trim( (string) $candidate ) ) {
				return sanitize_text_field( (string) $candidate );
			}
		}

		return '';
	}

	/**
	 * A stored LearnDash value (array, JSON, or an object such as a serialized DTO, possibly an incomplete class
	 * when LearnDash is not loaded) as a plain nested array.
	 *
	 * @param mixed $value Value.
	 */
	private static function plain_array( $value ): array {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			$value   = is_array( $decoded ) ? $decoded : maybe_unserialize( $value );
		}

		if ( is_object( $value ) ) {
			$props = array();

			foreach ( (array) $value as $key => $prop ) {
				// Protected / private props are keyed "\0*\0name" / "\0Class\0name".
				$key  = (string) $key;
				$nul  = strrpos( $key, "\0" );
				$name = false !== $nul ? substr( $key, $nul + 1 ) : $key;

				$props[ ltrim( $name, '_' ) ] = $prop;
			}

			$value = $props;
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		foreach ( $value as $key => $item ) {
			if ( is_array( $item ) || is_object( $item ) ) {
				$value[ $key ] = self::plain_array( $item );
			}
		}

		return $value;
	}

	/**
	 * MasterStudy order status of a LearnDash transaction: a trashed transaction is cancelled, a draft or
	 * pending one is pending, a LearnDash 5 transaction whose product was canceled is refunded / cancelled (by its
	 * cancellation reason), a PayPal IPN transaction follows its payment_status; everything else is a completed
	 * payment (LearnDash only records successful checkouts).
	 *
	 * @param int    $order_id    Transaction post ID.
	 * @param string $post_status Source post status.
	 */
	private static function transaction_status( int $order_id, string $post_status ): string {
		if ( 'trash' === $post_status ) {
			return 'cancelled';
		}

		if ( in_array( $post_status, array( 'draft', 'pending', 'auto-draft' ), true ) ) {
			return 'pending';
		}

		// LearnDash 5.x: an access removed after the purchase keeps the transaction but marks it
		// `product_status` = canceled with a `cancellation_reason` (e.g. "refunded" by the gateway handlers).
		$product_status = strtolower( trim( (string) self::transaction_meta( $order_id, 'product_status' ) ) );

		if ( in_array( $product_status, array( 'canceled', 'cancelled' ), true ) ) {
			$reason = strtolower( trim( (string) self::transaction_meta( $order_id, 'cancellation_reason' ) ) );

			return false !== strpos( $reason, 'refund' ) ? 'refunded' : 'cancelled';
		}

		$paypal = strtolower( trim( (string) self::transaction_meta( $order_id, 'payment_status' ) ) );

		// LearnDash 4.5+ PayPal IPN keeps the notification in `gateway_transaction` (event).
		if ( '' === $paypal ) {
			$gateway = self::plain_array( self::transaction_meta( $order_id, 'gateway_transaction' ) );
			$event   = self::plain_array( $gateway['event'] ?? array() );
			$paypal  = is_scalar( $event['payment_status'] ?? null ) ? strtolower( trim( (string) $event['payment_status'] ) ) : '';
		}

		$map = array(
			'completed'         => 'completed',
			'canceled_reversal' => 'completed',
			'processed'         => 'completed',
			'pending'           => 'pending',
			'in-progress'       => 'pending',
			'refunded'          => 'refunded',
			'partially_refunded' => 'refunded',
			'reversed'          => 'refunded',
			'denied'            => 'failed',
			'failed'            => 'failed',
			'expired'           => 'failed',
			'voided'            => 'cancelled',
		);

		return $map[ $paypal ] ?? 'completed';
	}

	/**
	 * Whether a LearnDash transaction pays for a recurring (subscription) price.
	 *
	 * @param int $order_id Transaction post ID.
	 */
	private static function is_subscription_transaction( int $order_id ): bool {
		// A LearnDash 4.5+ renewal charge inherits the price type of its subscription transaction.
		foreach ( array( 'price_type', 'stripe_price_type' ) as $key ) {
			if ( 'subscribe' === (string) self::transaction_meta( $order_id, $key ) ) {
				return true;
			}
		}

		return 0 === strpos( (string) self::transaction_meta( $order_id, 'txn_type' ), 'subscr_' );
	}

	/**
	 * Legacy quiz whose questions exist only in the WpProQuiz questions table (no sfwd-question posts): create
	 * a MasterStudy question post for each live row MasterStudy can use (idempotent per WpProQuiz row).
	 *
	 * @param int   $quiz_id       Source quiz post ID.
	 * @param int   $quiz_pro_id   WpProQuiz quiz ID.
	 * @param array $question_item Report fields shared by the quiz questions (parent, course).
	 * @return int[] MasterStudy question IDs in quiz order.
	 */
	private static function migrate_legacy_questions( int $quiz_id, int $quiz_pro_id, array $question_item ): array {
		global $wpdb;

		$q_table = self::pro_quiz_table( 'question' );
		$rows    = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$q_table} WHERE quiz_id = %d AND online = 1 ORDER BY sort ASC, id ASC", $quiz_pro_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		$ids = array();

		foreach ( (array) $rows as $row ) {
			$question_id = self::migrate_question_row( 0, $row, $question_item, 'publish', $quiz_id );

			if ( $question_id ) {
				$ids[] = $question_id;
			}
		}

		return $ids;
	}

	/**
	 * Whether a WpProQuiz quiz has live question rows.
	 *
	 * @param int $quiz_pro_id WpProQuiz quiz ID.
	 */
	private static function has_legacy_questions( int $quiz_pro_id ): bool {
		global $wpdb;

		$q_table = self::pro_quiz_table( 'question' );

		return self::table_exists( $q_table ) && (bool) $wpdb->get_var(
			$wpdb->prepare( "SELECT 1 FROM {$q_table} WHERE quiz_id = %d AND online = 1 LIMIT 1", $quiz_pro_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * `_masterstudy_migrated_source_id` of the question created for a legacy WpProQuiz-only question.
	 *
	 * @param int $pro_id WpProQuiz question ID.
	 */
	private static function legacy_question_key( int $pro_id ): string {
		return 'ld-pro-question-' . $pro_id;
	}

	/**
	 * MasterStudy question of a LearnDash question: the copy of the sfwd-question post, or the question
	 * created for a legacy WpProQuiz-only row. 0 when it was not imported (e.g. essay questions).
	 *
	 * @param int $question_post_id Source sfwd-question post ID (0 = unknown).
	 * @param int $pro_id           WpProQuiz question ID (0 = unknown).
	 */
	private static function question_copy( int $question_post_id, int $pro_id ): int {
		if ( ! $question_post_id && $pro_id ) {
			$question_post_id = self::post_id_by_meta( 'question_pro_id', $pro_id, 'sfwd-question' );
		}

		$copy = $question_post_id ? Target::copy_of( self::SOURCE, $question_post_id ) : 0;

		if ( ! $copy && $pro_id ) {
			$copy = Target::find_migrated_post( PostType::QUESTION, self::SOURCE, self::legacy_question_key( $pro_id ) );
		}

		return $copy && PostType::QUESTION === get_post_type( $copy ) ? $copy : 0;
	}

	/**
	 * A finished LearnDash quiz activity for which LearnDash kept no statistics (statistics
	 * disabled) would lose the attempt — import it as a single attempt using the activity
	 * status and meta (percentage / pass). Attempts that have statistics are imported by the
	 * quiz_attempts step instead.
	 *
	 * @param array $activity  learndash_user_activity row.
	 * @param int   $course_id MasterStudy course (the copy).
	 * @param int   $quiz_copy MasterStudy copy of the quiz.
	 */
	private static function maybe_add_quiz_activity_attempt( array $activity, int $course_id, int $quiz_copy ): void {
		global $wpdb;

		$user_id = (int) $activity['user_id'];

		if ( self::activity_has_statistics( $activity ) ) {
			return;
		}

		// activity_status is LearnDash's "pass" flag for the attempt; the meta carries the exact result.
		$passed  = ! empty( $activity['activity_status'] );
		$percent = $passed ? 100.0 : 0.0;

		$meta_table = $wpdb->prefix . 'learndash_user_activity_meta';
		if ( self::table_exists( $meta_table ) ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT activity_meta_key, activity_meta_value FROM {$meta_table} WHERE activity_id = %d AND activity_meta_key IN ('percentage', 'pass')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					(int) $activity['activity_id']
				),
				ARRAY_A
			);
			foreach ( (array) $rows as $row ) {
				if ( 'percentage' === $row['activity_meta_key'] && is_numeric( $row['activity_meta_value'] ) ) {
					$percent = (float) $row['activity_meta_value'];
				} elseif ( 'pass' === $row['activity_meta_key'] ) {
					$passed = (bool) $row['activity_meta_value'];
				}
			}
		}

		$time       = ! empty( $activity['activity_completed'] ) ? (int) $activity['activity_completed'] : (int) $activity['activity_started'];
		$created_at = self::site_datetime( $time > 0 ? $time : time() );

		if ( ! Target::quiz_attempt_exists( $user_id, $quiz_copy, $created_at, (float) $percent, (bool) $passed ) ) {
			Target::add_quiz_attempt( $user_id, $course_id, $quiz_copy, $percent, $passed, $created_at );
		}
	}

	/**
	 * Build the MasterStudy stm_lms_user_answers.user_answer string (spec §8) from a WpProQuiz
	 * statistic answer_data value, using the (already migrated) question's answers meta.
	 *
	 * Sort / matrix sort answers: LearnDash does not store answer indexes but the "datapos" hash of each sort
	 * element, md5( user ID . WpProQuiz question ID . original position ) (LD_QuizPro::datapos()) — sort: position
	 * in the student's order => hash; matrix sort: criterion index => hash of the element dropped on it. Plain
	 * indexes (old WpProQuiz data) are accepted too.
	 *
	 * @param int   $question_id MasterStudy question ID.
	 * @param int   $pro_id      WpProQuiz question ID.
	 * @param mixed $raw         Statistic answer_data (JSON or serialized).
	 * @param int   $user_id     Student (part of the sort element hashes).
	 */
	private static function build_user_answer( int $question_id, int $pro_id, $raw, int $user_id = 0 ): string {
		$data = is_string( $raw ) ? json_decode( $raw, true ) : null;
		if ( null === $data ) {
			$data = maybe_unserialize( $raw );
		}

		$type    = (string) get_post_meta( $question_id, 'type', true );
		$answers = get_post_meta( $question_id, 'answers', true );
		$answers = is_array( $answers ) ? array_values( $answers ) : array();

		// Sort element hash => original answer index.
		$positions = array();
		if ( in_array( $type, array( 'sortable', 'item_match' ), true ) ) {
			for ( $i = 0, $count = max( count( $answers ), count( (array) $data ) ); $i < $count; $i++ ) {
				$positions[ md5( $user_id . $pro_id . $i ) ] = $i;
			}
		}

		// Original answer index of a sort element (hash or plain index), null when unknown.
		$position = function ( $value ) use ( $positions ): ?int {
			if ( is_string( $value ) && isset( $positions[ strtolower( $value ) ] ) ) {
				return $positions[ strtolower( $value ) ];
			}

			return is_numeric( $value ) ? (int) $value : null;
		};

		// Selected indexes from a WpProQuiz flag list ([0,1,0]).
		$selected = function () use ( $data ): array {
			$picked = array();
			foreach ( (array) $data as $idx => $flag ) {
				if ( ! empty( $flag ) ) {
					$picked[] = (int) $idx;
				}
			}
			return $picked;
		};

		// Texts of the sort elements in the student's order.
		$texts_by_index = function ( $list ) use ( $answers, $position ): array {
			$texts = array();
			foreach ( (array) $list as $value ) {
				$index = $position( $value );

				if ( null !== $index && isset( $answers[ $index ] ) ) {
					$texts[] = (string) $answers[ $index ]['text'];
				} elseif ( is_scalar( $value ) ) {
					$texts[] = (string) $value;
				}
			}
			return $texts;
		};

		switch ( $type ) {
			case 'single_choice':
				$picked = $selected();
				return isset( $picked[0], $answers[ $picked[0] ] ) ? (string) $answers[ $picked[0] ]['text'] : '';

			case 'true_false':
				$picked = $selected();
				if ( ! isset( $picked[0] ) ) {
					return '';
				}
				// The migrated question's correct answer tells which LD option means "True".
				$source_correct = self::source_correct_index( $pro_id );
				$true_correct   = ! empty( $answers[0]['isTrue'] );
				$picked_correct = null !== $source_correct && $picked[0] === $source_correct;
				return ( $picked_correct === $true_correct ) ? 'True' : 'False';

			case 'multi_choice':
				$texts = array();
				foreach ( $selected() as $idx ) {
					if ( isset( $answers[ $idx ] ) ) {
						$texts[] = rawurlencode( (string) $answers[ $idx ]['text'] );
					}
				}
				return implode( ',', $texts );

			case 'keywords':
				return '[stm_lms_keywords]' . ( is_scalar( $data ) ? (string) $data : implode( '[stm_lms_sep]', array_map( 'strval', array_filter( (array) $data, 'is_scalar' ) ) ) );

			case 'fill_the_gap':
				return is_scalar( $data ) ? (string) $data : implode( ',', array_map( 'strval', array_filter( (array) $data, 'is_scalar' ) ) );

			case 'sortable':
				return '[stm_lms_sortable]' . implode( '[stm_lms_sep]', $texts_by_index( $data ) );

			case 'item_match':
				// One element per criterion, in criterion order (the MasterStudy answers keep the LearnDash order).
				$dropped = array();
				foreach ( (array) $data as $criterion => $value ) {
					$index = $position( $criterion );

					if ( null !== $index ) {
						$dropped[ $index ] = $value;
					}
				}

				$matches = array();
				foreach ( array_keys( $answers ) as $index ) {
					$value  = $dropped[ $index ] ?? null;
					$picked = null !== $value ? $position( $value ) : null;

					if ( null !== $picked && isset( $answers[ $picked ] ) ) {
						$matches[] = (string) $answers[ $picked ]['text'];
					} else {
						// Unanswered criterion, or a plain text value (other tools' data).
						$matches[] = null === $picked && is_scalar( $value ) ? (string) $value : '';
					}
				}
				return '[stm_lms_item_match]' . implode( '[stm_lms_sep]', $matches );
		}

		return is_scalar( $data ) ? (string) $data : '';
	}

	/**
	 * Index of the correct option in the source WpProQuiz answer list (true/false questions).
	 *
	 * @param int $pro_id WpProQuiz question ID.
	 */
	private static function source_correct_index( int $pro_id ): ?int {
		global $wpdb;

		if ( ! $pro_id ) {
			return null;
		}

		$q_table = self::pro_quiz_table( 'question' );
		$raw     = $wpdb->get_var( $wpdb->prepare( "SELECT answer_data FROM {$q_table} WHERE id = %d", $pro_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		foreach ( self::answer_objects( $raw ) as $idx => $ao ) {
			if ( $ao['correct'] ) {
				return (int) $idx;
			}
		}

		return null;
	}

	/**
	 * Normalize WpProQuiz answer_data (serialized WpProQuiz_Model_AnswerTypes objects) to plain arrays.
	 * Works whether or not LearnDash is loaded (unloaded classes unserialize to
	 * __PHP_Incomplete_Class, whose protected properties are read by array cast).
	 *
	 * @param mixed $raw Serialized answer_data.
	 * @return array[] List of ['answer', 'html', 'points', 'correct', 'sort_string'].
	 */
	private static function answer_objects( $raw ): array {
		$data   = maybe_unserialize( $raw );
		$result = array();

		foreach ( (array) $data as $ao ) {
			if ( ! is_object( $ao ) ) {
				continue;
			}

			// Without LearnDash loaded the objects are __PHP_Incomplete_Class: even method_exists()
			// on them throws, so only real WpProQuiz objects use their getters.
			if ( ! ( $ao instanceof \__PHP_Incomplete_Class ) && method_exists( $ao, 'getAnswer' ) ) {
				$result[] = array(
					'answer'      => (string) $ao->getAnswer(),
					'html'        => (bool) $ao->isHtml(),
					'points'      => (float) $ao->getPoints(),
					'correct'     => (bool) $ao->isCorrect(),
					'sort_string' => (string) $ao->getSortString(),
				);
				continue;
			}

			$props = array();
			foreach ( (array) $ao as $key => $value ) {
				// Protected props are keyed "\0*\0_name".
				$props[ ltrim( str_replace( "\0*\0", '', (string) $key ), '_' ) ] = $value;
			}

			$result[] = array(
				'answer'      => (string) ( $props['answer'] ?? '' ),
				'html'        => ! empty( $props['html'] ),
				'points'      => (float) ( $props['points'] ?? 0 ),
				'correct'     => ! empty( $props['correct'] ),
				'sort_string' => (string) ( $props['sortString'] ?? '' ),
			);
		}

		return $result;
	}

	/**
	 * Convert a WpProQuiz cloze text ("Sky is {blue} and {[green][lime]|2}") to MasterStudy
	 * fill_the_gap format ("Sky is |blue| and |green|"). The first accepted variant is used.
	 *
	 * @param string $text Cloze text.
	 */
	private static function cloze_to_gaps( string $text ): string {
		return (string) preg_replace_callback(
			'/\{([^}]*)\}/',
			function ( $m ) {
				$value = preg_replace( '/\|\s*\d+(\.\d+)?\s*$/', '', $m[1] );
				if ( preg_match( '/\[([^\]]*)\]/', (string) $value, $variant ) ) {
					$value = $variant[1];
				}
				return '|' . str_replace( '|', '', trim( (string) $value ) ) . '|';
			},
			$text
		);
	}

	/**
	 * LearnDash course price as a float (the raw value may contain a currency symbol).
	 *
	 * @param int $course_id Course ID.
	 */
	private static function course_price( int $course_id ): float {
		$course_meta = get_post_meta( $course_id, '_sfwd-courses', true );
		$course_meta = is_array( $course_meta ) ? $course_meta : array();
		$raw         = (string) ( $course_meta['sfwd-courses_course_price'] ?? '' );

		// LearnDash 3+ edits the price per access mode (course_price_type_{paynow|subscribe|closed}_price) and
		// normally mirrors it to course_price; fall back to the per-mode field when the mirror is empty.
		if ( '' === trim( $raw ) ) {
			$type = (string) ( $course_meta['sfwd-courses_course_price_type'] ?? '' );
			$raw  = (string) ( $course_meta[ 'sfwd-courses_course_price_type_' . $type . '_price' ] ?? '' );
		}

		return (float) self::parse_amount( $raw );
	}

	/**
	 * A LearnDash amount as a float, or null when there is none. The course / group price is free text
	 * ("$1,299.00", "1.299,00", "12,50"), so thousands separators are dropped and a decimal comma is accepted:
	 * when both "," and "." occur the last one is the decimal separator; a lone "," followed by exactly three
	 * digits (or several ",") separates thousands, otherwise it is the decimal separator; several "." separate
	 * thousands.
	 *
	 * @param mixed $value Raw amount.
	 */
	private static function parse_amount( $value ): ?float {
		if ( is_int( $value ) || is_float( $value ) ) {
			return (float) $value;
		}

		if ( ! is_scalar( $value ) ) {
			return null;
		}

		$clean = (string) preg_replace( '/[^0-9.,]/', '', (string) $value );

		if ( ! preg_match( '/\d/', $clean ) ) {
			return null;
		}

		$last_dot   = strrpos( $clean, '.' );
		$last_comma = strrpos( $clean, ',' );

		if ( false !== $last_dot && false !== $last_comma ) {
			$thousands = $last_dot > $last_comma ? ',' : '.';
			$clean     = str_replace( ',', '.', str_replace( $thousands, '', $clean ) );
		} elseif ( false !== $last_comma ) {
			$parts = explode( ',', $clean );
			$clean = ( count( $parts ) > 2 || 3 === strlen( (string) end( $parts ) ) ) ? str_replace( ',', '', $clean ) : str_replace( ',', '.', $clean );
		} elseif ( substr_count( $clean, '.' ) > 1 ) {
			$clean = str_replace( '.', '', $clean );
		}

		return is_numeric( $clean ) ? (float) $clean : null;
	}

	/**
	 * LearnDash quiz passing percentage from the `_sfwd-quiz` settings.
	 *
	 * @param int $quiz_id Quiz ID.
	 */
	private static function quiz_passing_percentage( int $quiz_id ): float {
		$meta = get_post_meta( $quiz_id, '_sfwd-quiz', true );

		if ( ! is_array( $meta ) ) {
			return 0.0;
		}

		// LearnDash stores it as `sfwd-quiz_passingpercentage` (learndash_get_setting( $quiz, 'passingpercentage' ));
		// `sfwd-quiz_passing_percentage` is accepted for data written by other tools.
		$value = $meta['sfwd-quiz_passingpercentage'] ?? ( $meta['sfwd-quiz_passing_percentage'] ?? 0 );

		return max( 0.0, (float) $value );
	}

	/**
	 * Term names of a (possibly unregistered) taxonomy for an object.
	 *
	 * @param int    $object_id Object ID.
	 * @param string $taxonomy  Taxonomy.
	 * @return string[]
	 */
	private static function term_names( int $object_id, string $taxonomy ): array {
		global $wpdb;

		return array_map(
			'strval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT t.name FROM {$wpdb->terms} t
					 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
					 INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
					 WHERE tr.object_id = %d AND tt.taxonomy = %s",
					$object_id,
					$taxonomy
				)
			)
		);
	}

	/**
	 * Terms of a (possibly unregistered) taxonomy for an object.
	 *
	 * @param int    $object_id Object ID.
	 * @param string $taxonomy  Taxonomy.
	 * @return array[] Rows with term_id, name, slug, parent.
	 */
	private static function raw_terms( int $object_id, string $taxonomy ): array {
		global $wpdb;

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.term_id, t.name, t.slug, tt.parent FROM {$wpdb->terms} t
				 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
				 INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
				 WHERE tr.object_id = %d AND tt.taxonomy = %s",
				$object_id,
				$taxonomy
			),
			ARRAY_A
		);
	}

	/**
	 * LearnDash post carrying a numeric meta value (WpProQuiz id → LearnDash post).
	 *
	 * @param string $meta_key  Meta key (quiz_pro_id | question_pro_id).
	 * @param int    $value     Meta value.
	 * @param string $post_type LearnDash post type (sfwd-quiz | sfwd-question).
	 */
	private static function post_id_by_meta( string $meta_key, int $value, string $post_type ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT pm.post_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = %s AND pm.meta_value = %d AND p.post_type = %s ORDER BY pm.post_id ASC LIMIT 1",
				$meta_key,
				$value,
				$post_type
			)
		);
	}

	/**
	 * WpProQuiz table name — {prefix}learndash_pro_quiz_{name}, or the legacy {prefix}pro_quiz_{name}.
	 *
	 * @param string $name master|question|statistic|statistic_ref.
	 */
	private static function pro_quiz_table( string $name ): string {
		global $wpdb;

		static $cache = array();

		if ( ! isset( $cache[ $name ] ) ) {
			// LearnDash 3+: {prefix}learndash_pro_quiz_*; older sites keep the WP-Pro-Quiz names {prefix}wp_pro_quiz_*.
			$cache[ $name ] = $wpdb->prefix . 'learndash_pro_quiz_' . $name;

			foreach ( array( 'learndash_pro_quiz_', 'wp_pro_quiz_', 'pro_quiz_' ) as $prefix ) {
				if ( self::table_exists( $wpdb->prefix . $prefix . $name ) ) {
					$cache[ $name ] = $wpdb->prefix . $prefix . $name;
					break;
				}
			}
		}

		return $cache[ $name ];
	}

	/**
	 * Whether a DB table exists.
	 *
	 * @param string $table Full table name.
	 */
	private static function table_exists( string $table ): bool {
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table;
	}

	/**
	 * Unix time → MySQL datetime in site time (stm_lms_user_quizzes.created_at format).
	 *
	 * @param int $timestamp Unix time.
	 */
	private static function site_datetime( int $timestamp ): string {
		return get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $timestamp > 0 ? $timestamp : time() ) );
	}

	// ── Migration report helpers ─────────────────────────────────────────────

	/**
	 * Make sure a course step (lesson, topic, quiz) listed in the LearnDash course builder still
	 * exists before it is copied, so a broken course fails with a readable message.
	 *
	 * @param int $post_id   Step post ID.
	 * @param int $course_id Course ID.
	 * @throws \Exception When the step post is missing.
	 */
	private static function require_step_post( int $post_id, int $course_id ): void {
		if ( $post_id && get_post( $post_id ) ) {
			return;
		}

		throw new \Exception( // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			sprintf(
				/* translators: 1: course title, 2: step post ID */
				__( 'Course "%1$s" could not be imported: its LearnDash course builder lists a lesson, topic or quiz (#%2$d) that no longer exists. Re-save the course in LearnDash to clean up its steps and run the migration again.', 'masterstudy-lms-learning-management-system' ),
				self::post_title( $course_id ),
				$post_id
			)
		);
	}

	/**
	 * Readable user label for the report: e-mail, or "User #ID (deleted)".
	 *
	 * @param int $user_id User ID.
	 */
	private static function user_label( int $user_id ): string {
		$user = $user_id ? get_userdata( $user_id ) : false;

		if ( $user ) {
			return '' !== (string) $user->user_email ? (string) $user->user_email : (string) $user->user_login;
		}

		return $user_id
			/* translators: %d: user ID */
			? sprintf( __( 'User #%d (deleted)', 'masterstudy-lms-learning-management-system' ), $user_id )
			: __( 'unknown user', 'masterstudy-lms-learning-management-system' );
	}

	/**
	 * Post title for the report, or "#ID" when the post has no title / is gone.
	 *
	 * @param int $post_id Post ID.
	 */
	private static function post_title( int $post_id ): string {
		$title = $post_id && get_post( $post_id ) ? wp_strip_all_tags( get_post_field( 'post_title', $post_id ) ) : '';

		return '' !== $title ? $title : '#' . $post_id;
	}

	/**
	 * Title of the first MasterStudy course that contains the copy of a post (or '' when none).
	 *
	 * @param int $post_id Source lesson/quiz post ID.
	 */
	private static function first_course_title( int $post_id ): string {
		$copy       = Target::copy_of( self::SOURCE, $post_id );
		$course_ids = $copy ? Target::course_ids_of( $copy ) : array();

		return empty( $course_ids ) ? '' : self::post_title( (int) reset( $course_ids ) );
	}

	/**
	 * MasterStudy copy of a LearnDash course (0 when the course was not copied).
	 *
	 * @param int $course_id Source course ID.
	 */
	private static function course_copy( int $course_id ): int {
		$copy = $course_id ? Target::copy_of( self::SOURCE, $course_id ) : 0;

		return $copy && PostType::COURSE === get_post_type( $copy ) ? $copy : 0;
	}

	/**
	 * Post a report item links to: the MasterStudy copy when there is one, else the source post (0 when gone).
	 *
	 * @param int $post_id Source post ID.
	 */
	private static function report_post_id( int $post_id ): int {
		if ( $post_id <= 0 ) {
			return 0;
		}

		$copy = Target::copy_of( self::SOURCE, $post_id );

		if ( $copy ) {
			return $copy;
		}

		return get_post( $post_id ) ? $post_id : 0;
	}

	/**
	 * Question text for the report, shortened.
	 *
	 * @param array $row WpProQuiz question row.
	 */
	private static function question_title( array $row ): string {
		$text = trim( wp_strip_all_tags( (string) ( $row['question'] ?? '' ) ) );
		$text = '' !== $text ? $text : (string) ( $row['title'] ?? '' );

		return wp_trim_words( $text, 25 );
	}

	/**
	 * Whether LearnDash kept quiz statistics (attempts imported by the quiz_attempts step) for a user/quiz.
	 *
	 * @param int $user_id User ID.
	 * @param int $quiz_id Quiz post ID.
	 */
	private static function has_quiz_statistics( int $user_id, int $quiz_id ): bool {
		global $wpdb;

		$ref_table = self::pro_quiz_table( 'statistic_ref' );
		if ( ! self::table_exists( $ref_table ) ) {
			return false;
		}

		$quiz_pro_id = (int) get_post_meta( $quiz_id, 'quiz_pro_id', true );

		// Tables not upgraded by LearnDash 2.5+ have no quiz_post_id column — match the WpProQuiz quiz ID.
		if ( ! self::column_exists( $ref_table, 'quiz_post_id' ) ) {
			return $quiz_pro_id && (bool) $wpdb->get_var(
				$wpdb->prepare( "SELECT 1 FROM {$ref_table} WHERE user_id = %d AND quiz_id = %d LIMIT 1", $user_id, $quiz_pro_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);
		}

		return (bool) $wpdb->get_var(
			$wpdb->prepare( "SELECT 1 FROM {$ref_table} WHERE user_id = %d AND ( quiz_post_id = %d OR ( quiz_post_id = 0 AND quiz_id = %d ) ) LIMIT 1", $user_id, $quiz_id, $quiz_pro_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Whether the attempt of a LearnDash 'quiz' activity row has WpProQuiz statistics (the quiz_attempts step
	 * then imports it, with its answers). Matched per attempt through the `statistic_ref_id` activity meta
	 * (0 = statistics were off for that attempt, or they were deleted since); rows without that meta (data
	 * older than LearnDash 2.5) fall back to "the user has statistics for the quiz".
	 *
	 * @param array $activity learndash_user_activity row.
	 */
	private static function activity_has_statistics( array $activity ): bool {
		global $wpdb;

		$meta_table = $wpdb->prefix . 'learndash_user_activity_meta';
		$ref_id     = self::table_exists( $meta_table )
			? $wpdb->get_var(
				$wpdb->prepare(
					"SELECT activity_meta_value FROM {$meta_table} WHERE activity_id = %d AND activity_meta_key = 'statistic_ref_id' LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					(int) $activity['activity_id']
				)
			)
			: null;

		if ( null === $ref_id || ! is_numeric( $ref_id ) ) {
			return self::has_quiz_statistics( (int) $activity['user_id'], (int) $activity['post_id'] );
		}

		return (int) $ref_id > 0 && self::statistic_ref_exists( (int) $ref_id );
	}

	/**
	 * Whether a WpProQuiz statistic_ref row (one quiz attempt) exists.
	 *
	 * @param int $ref_id statistic_ref_id.
	 */
	private static function statistic_ref_exists( int $ref_id ): bool {
		global $wpdb;

		$ref_table = self::pro_quiz_table( 'statistic_ref' );

		return self::table_exists( $ref_table ) && (bool) $wpdb->get_var(
			$wpdb->prepare( "SELECT 1 FROM {$ref_table} WHERE statistic_ref_id = %d LIMIT 1", $ref_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * LearnDash's own result of a quiz attempt with statistics: the meta of the 'quiz' activity row whose
	 * `statistic_ref_id` is the attempt (LearnDash 2.5+), else the matching `_sfwd-quizzes` user meta entry.
	 *
	 * @param int $user_id User ID.
	 * @param int $ref_id  statistic_ref_id of the attempt.
	 * @return array Keys (when known): percentage, pass, points, total_points.
	 */
	private static function attempt_result( int $user_id, int $ref_id ): array {
		global $wpdb;

		$keys           = array( 'percentage', 'pass', 'points', 'total_points' );
		$activity_table = $wpdb->prefix . 'learndash_user_activity';
		$meta_table     = $wpdb->prefix . 'learndash_user_activity_meta';

		if ( self::table_exists( $activity_table ) && self::table_exists( $meta_table ) ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$activity_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT a.activity_id FROM {$activity_table} a
					 INNER JOIN {$meta_table} m ON m.activity_id = a.activity_id AND m.activity_meta_key = 'statistic_ref_id' AND m.activity_meta_value = %s
					 WHERE a.user_id = %d AND a.activity_type = 'quiz'
					 ORDER BY a.activity_id DESC LIMIT 1",
					(string) $ref_id,
					$user_id
				)
			);

			$rows = $activity_id
				? $wpdb->get_results(
					$wpdb->prepare(
						"SELECT activity_meta_key, activity_meta_value FROM {$meta_table} WHERE activity_id = %d AND activity_meta_key IN ('percentage', 'pass', 'points', 'total_points')",
						$activity_id
					),
					ARRAY_A
				)
				: array();
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			$result = array();
			foreach ( (array) $rows as $row ) {
				$result[ $row['activity_meta_key'] ] = maybe_unserialize( $row['activity_meta_value'] );
			}

			if ( ! empty( $result ) ) {
				return $result;
			}
		}

		foreach ( (array) get_user_meta( $user_id, '_sfwd-quizzes', true ) as $attempt ) {
			if ( is_array( $attempt ) && isset( $attempt['statistic_ref_id'] ) && (int) $attempt['statistic_ref_id'] === $ref_id ) {
				return array_intersect_key( $attempt, array_flip( $keys ) );
			}
		}

		return array();
	}

	/**
	 * Whether a table has a column (false when the table is missing).
	 *
	 * @param string $table  Full table name.
	 * @param string $column Column name.
	 */
	private static function column_exists( string $table, string $column ): bool {
		global $wpdb;

		static $cache = array();

		if ( ! isset( $cache[ $table ] ) ) {
			$cache[ $table ] = self::table_exists( $table ) ? array_map( 'strval', (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}" ) ) : array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		return in_array( $column, $cache[ $table ], true );
	}

	/**
	 * Human-readable label of a WpProQuiz answer type.
	 *
	 * @param string $answer_type WpProQuiz answer_type.
	 */
	private static function question_type_label( string $answer_type ): string {
		$labels = array(
			'single'             => __( 'Single choice', 'masterstudy-lms-learning-management-system' ),
			'multiple'           => __( 'Multiple choice', 'masterstudy-lms-learning-management-system' ),
			'bool'               => __( 'True / false', 'masterstudy-lms-learning-management-system' ),
			'free_answer'        => __( 'Free answer', 'masterstudy-lms-learning-management-system' ),
			'cloze_answer'       => __( 'Fill in the blank', 'masterstudy-lms-learning-management-system' ),
			'sort_answer'        => __( 'Sorting', 'masterstudy-lms-learning-management-system' ),
			'matrix_sort_answer' => __( 'Matrix sorting', 'masterstudy-lms-learning-management-system' ),
			'matrix_sort'        => __( 'Matrix sorting', 'masterstudy-lms-learning-management-system' ),
			'essay'              => __( 'Essay / open answer', 'masterstudy-lms-learning-management-system' ),
			'assessment_answer'  => __( 'Assessment (survey)', 'masterstudy-lms-learning-management-system' ),
		);

		if ( isset( $labels[ $answer_type ] ) ) {
			return $labels[ $answer_type ];
		}

		return '' !== $answer_type ? ucwords( str_replace( '_', ' ', $answer_type ) ) : __( 'Unknown', 'masterstudy-lms-learning-management-system' );
	}

	/**
	 * Report reason for a question type MasterStudy cannot import.
	 *
	 * @param string $answer_type WpProQuiz answer_type.
	 */
	private static function unsupported_question_reason( string $answer_type ): string {
		switch ( $answer_type ) {
			case 'essay':
				return __( 'MasterStudy has no essay (open answer) question type, so the question was not imported (it stays in LearnDash) and is not part of the MasterStudy quiz.', 'masterstudy-lms-learning-management-system' );
			case 'assessment_answer':
				return __( 'MasterStudy has no assessment (survey / rating scale) question type, so the question was not imported (it stays in LearnDash) and is not part of the MasterStudy quiz.', 'masterstudy-lms-learning-management-system' );
		}

		return __( 'This LearnDash question type has no MasterStudy equivalent, so the question was not imported (it stays in LearnDash) and is not part of the MasterStudy quiz.', 'masterstudy-lms-learning-management-system' );
	}

	// ── Write helpers Target lacks (candidates for hoisting into Target) ──────

	/**
	 * Write a single MasterStudy post meta value Target has no dedicated helper for
	 * (quiz `quiz_style`, course `single_sale`).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Value.
	 */
	private static function update_post_meta_value( int $post_id, string $key, $value ): void {
		update_post_meta( $post_id, $key, $value );
	}

	/**
	 * Set the post status of a MasterStudy copy (Target::save_order() always publishes the order).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $status  Post status.
	 */
	private static function set_post_status( int $post_id, string $status ): void {
		global $wpdb;

		$wpdb->update( $wpdb->posts, array( 'post_status' => $status ), array( 'ID' => $post_id ) );
		clean_post_cache( $post_id );
	}

	/**
	 * Fill an empty post excerpt of a MasterStudy copy (MasterStudy shows it as the course short description).
	 *
	 * @param int    $post_id Post ID (the copy).
	 * @param string $excerpt Excerpt HTML.
	 */
	private static function maybe_set_excerpt( int $post_id, string $excerpt ): void {
		global $wpdb;

		if ( '' === trim( $excerpt ) || '' !== trim( (string) get_post_field( 'post_excerpt', $post_id ) ) ) {
			return;
		}

		$wpdb->update( $wpdb->posts, array( 'post_excerpt' => wp_kses_post( $excerpt ) ), array( 'ID' => $post_id ) );
		clean_post_cache( $post_id );
	}

	/**
	 * Insert a curriculum material right after another material in every section that
	 * contains it (shifting the following materials down). Idempotent per section.
	 *
	 * @param int $after_post_id    Post already in the curriculum (the lesson copy; 0 = not copied).
	 * @param int $post_id          Post to insert (assignment).
	 * @param int $source_lesson_id Source LearnDash lesson (for the report).
	 */
	private static function attach_after_material( int $after_post_id, int $post_id, int $source_lesson_id = 0 ): void {
		global $wpdb;

		$materials = $wpdb->prefix . 'stm_lms_curriculum_materials';

		$rows = $after_post_id
			? $wpdb->get_results(
				$wpdb->prepare( "SELECT section_id, `order` AS position FROM {$materials} WHERE post_id = %d", $after_post_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				ARRAY_A
			)
			: array();

		if ( empty( $rows ) ) {
			$source_lesson_id = $source_lesson_id ? $source_lesson_id : $after_post_id;
			Helper::log( 'warning', sprintf( 'LearnDash migration: assignment %d not attached — lesson %d is not in any MasterStudy curriculum.', $post_id, $source_lesson_id ) );
			Report::add(
				Report::GROUP_ASSIGNMENTS,
				array(
					'source_id' => $source_lesson_id,
					'title'     => get_post_field( 'post_title', $post_id ),
					'type'      => __( 'Lesson assignment', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'The lesson that owns this assignment is not part of any migrated course, so the assignment was created but not added to a curriculum (students cannot reach it).', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_PARTIAL,
					/* translators: %s: lesson title */
					'parent'    => sprintf( __( 'Lesson: %s', 'masterstudy-lms-learning-management-system' ), self::post_title( $source_lesson_id ) ),
					'post_id'   => $post_id,
				)
			);
			return;
		}

		foreach ( $rows as $row ) {
			$section_id = (int) $row['section_id'];
			$position   = (int) $row['position'];

			$exists = $wpdb->get_var(
				$wpdb->prepare( "SELECT 1 FROM {$materials} WHERE section_id = %d AND post_id = %d LIMIT 1", $section_id, $post_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);

			if ( $exists ) {
				continue;
			}

			$wpdb->query(
				$wpdb->prepare( "UPDATE {$materials} SET `order` = `order` + 1 WHERE section_id = %d AND `order` > %d", $section_id, $position ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);

			Target::add_material( $section_id, $post_id, $position + 1 );
		}
	}

	/**
	 * Attachment for a LearnDash assignment upload. LearnDash stores uploads as plain files in
	 * uploads/assignments/ (no attachment post); MasterStudy shows submission files only as
	 * attachments, so the existing file is registered as a NEW attachment (nothing is copied or downloaded).
	 * A file that already has an attachment post is not reused: MasterStudy would re-parent it to the
	 * submission, which would modify existing data — the submission links to the file instead.
	 *
	 * @param int    $submission_id sfwd-assignment post ID.
	 * @param int    $student_id    Student (attachment author).
	 * @param string $upload_url    File URL.
	 * @return int Attachment ID, 0 when the file is missing, outside the uploads folder or already an attachment.
	 */
	private static function assignment_attachment( int $submission_id, int $student_id, string $upload_url ): int {
		if ( '' === $upload_url || attachment_url_to_postid( $upload_url ) ) {
			return 0;
		}

		$uploads = wp_upload_dir( null, false );
		$path    = rawurldecode( (string) get_post_meta( $submission_id, 'file_path', true ) );

		if ( ( '' === $path || ! is_file( $path ) ) && 0 === strpos( $upload_url, (string) $uploads['baseurl'] ) ) {
			$path = $uploads['basedir'] . rawurldecode( (string) wp_parse_url( substr( $upload_url, strlen( (string) $uploads['baseurl'] ) ), PHP_URL_PATH ) );
		}

		$real = '' !== $path ? realpath( $path ) : false;
		$base = realpath( (string) $uploads['basedir'] );

		if ( ! $real || ! $base || ! is_file( $real ) || 0 !== strpos( wp_normalize_path( $real ), trailingslashit( wp_normalize_path( $base ) ) ) ) {
			return 0;
		}

		// Rebuild the path on the configured uploads dir so WordPress stores a relative file path.
		$file      = trailingslashit( (string) $uploads['basedir'] ) . ltrim( substr( wp_normalize_path( $real ), strlen( trailingslashit( wp_normalize_path( $base ) ) ) ), '/' );
		$file_name = (string) get_post_meta( $submission_id, 'file_name', true );

		// Own copy of the file: deleting the MasterStudy submission must not delete LearnDash's file.
		return Target::copy_file_attachment(
			$file,
			array(
				'post_title'  => sanitize_text_field( '' !== $file_name ? $file_name : basename( $real ) ),
				'post_author' => $student_id,
			),
			self::SOURCE,
			'assignment-file-' . $submission_id
		);
	}

	/**
	 * Instructor feedback on a LearnDash assignment: approved comments left on the upload by anyone
	 * other than the student, oldest first.
	 *
	 * @param int $submission_id sfwd-assignment post ID.
	 * @param int $student_id    Student ID.
	 * @return string HTML.
	 */
	private static function assignment_feedback( int $submission_id, int $student_id ): string {
		$comments = get_comments(
			array(
				'post_id' => $submission_id,
				'status'  => 'approve',
				'orderby' => 'comment_date_gmt',
				'order'   => 'ASC',
			)
		);

		$feedback = array();

		foreach ( (array) $comments as $comment ) {
			if ( (int) $comment->user_id === $student_id || '' === trim( (string) $comment->comment_content ) ) {
				continue;
			}

			$feedback[] = sprintf(
				'<p><strong>%s:</strong></p>%s',
				esc_html( '' !== (string) $comment->comment_author ? (string) $comment->comment_author : self::user_label( (int) $comment->user_id ) ),
				wpautop( wp_kses_post( (string) $comment->comment_content ) )
			);
		}

		return implode( "\n", $feedback );
	}
}
