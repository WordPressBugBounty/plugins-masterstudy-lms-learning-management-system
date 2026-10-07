<?php
// phpcs:ignoreFile
/**
 * LifterLMS migrations.
 *
 * Port of Masteriyo's Migration Tool LifterLMS class. Source data is read with raw
 * SQL / post meta (not through LLMS_* classes) so the migration also works when the
 * LifterLMS API is not loaded; the storage it reads is exactly what LLMS_Course,
 * LLMS_Section, LLMS_Lesson, LLMS_Quiz and LLMS_Question read.
 *
 * Copy mode: LifterLMS data is never modified. Courses, lessons, quizzes, questions, assignments and orders are
 * copied into NEW MasterStudy posts (Target::copy_post(), mapped back with Target::copy_of()); sections are only
 * read (they become curriculum sections of the course copy); the `lifterlms_*` tables are only read. Every ID
 * written into MasterStudy data is the ID of a copy.
 *
 * @package MasterStudy\Lms\MigrationTool
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

/**
 * Class LifterLMS.
 */
class LifterLMS {

	/**
	 * Source LMS slug stored on every copied / created post.
	 */
	const SOURCE = 'lifterlms';

	/**
	 * LifterLMS Assignments add-on submissions table (without the WP table prefix).
	 */
	const SUBMISSIONS_TABLE = 'lifterlms_assignments_submissions';

	/**
	 * Migrates a single LifterLMS course curriculum.
	 *
	 * LifterLMS storage: `section` posts linked by `_llms_parent_course`, `lesson` posts linked
	 * by `_llms_parent_section`, both ordered by `_llms_order`. A lesson's quiz is `_llms_quiz`.
	 *
	 * MasterStudy has no post_parent linkage — the curriculum lives only in the
	 * curriculum sections/materials tables of the course copy, rebuilt from scratch on every run.
	 * Lessons, quizzes and assignments are copied; a source item used twice gets one shared copy.
	 *
	 * Deviation from Masteriyo: Masteriyo replaces a lesson that has a quiz by the quiz (the
	 * lesson post itself is left behind as `lesson` and its content is lost). MasterStudy keeps
	 * both: the lesson is attached first and its quiz right after it.
	 *
	 * @param int $course_id LifterLMS course ID.
	 * @param int $copy_id   MasterStudy copy of the course.
	 */
	private static function migrate_course( int $course_id, int $copy_id ): void {
		$sections = self::get_children( $course_id, '_llms_parent_course', array( 'section' ) );

		Target::reset_curriculum( $copy_id );

		if ( empty( $sections ) ) {
			return;
		}

		$section_order = 0;
		$lesson_items  = array();
		$course_drip   = self::course_drip( $course_id );
		$lesson_number = 0;

		foreach ( $sections as $section ) {
			$lessons           = self::get_children( (int) $section->ID, '_llms_parent_section', array( 'lesson' ) );
			$section_published = 'publish' === get_post_status( (int) $section->ID );

			// Empty sections are dropped, like Masteriyo does.
			if ( empty( $lessons ) ) {
				continue;
			}

			++$section_order;

			$section_id     = Target::add_section( $copy_id, (string) $section->post_title, $section_order );
			$material_order = 0;

			foreach ( $lessons as $lesson ) {
				$lesson_id   = (int) $lesson->ID;
				$lesson_copy = Target::copy_post( $lesson_id, PostType::LESSON, self::SOURCE );

				self::migrate_lesson( $lesson_id, $lesson_copy, $course_id );
				Target::add_material( $section_id, $lesson_copy, ++$material_order );

				// In LifterLMS the quiz and the assignment are part of the lesson; in MasterStudy they are
				// separate curriculum items placed right after it, and they share the lesson's drip rules.
				$items = array( $lesson_copy );

				$quiz_copy = self::migrate_lesson_quiz( $lesson_id, $course_id );

				if ( $quiz_copy ) {
					Target::add_material( $section_id, $quiz_copy, ++$material_order );
					$items[] = $quiz_copy;
				}

				$assignment_copy = self::migrate_lesson_assignment( $lesson_id, $course_id );

				if ( $assignment_copy ) {
					Target::add_material( $section_id, $assignment_copy, ++$material_order );
					$items[] = $assignment_copy;
				}

				$lesson_items[ $lesson_id ] = $items;

				// Course-level drip replaces the lesson drip settings of every lesson (LLMS_Lesson::get_available_date()).
				// Lessons are numbered like LLMS_Course::get_lessons(): published lessons of published sections.
				if ( null !== $course_drip ) {
					$published = $section_published && 'publish' === get_post_status( $lesson_id );
					self::apply_course_drip( $course_drip, $published ? ++$lesson_number : 0, $items );
				} else {
					self::migrate_single_content_drip( $lesson_id, $course_id, $items );
				}

				self::report_completion_requirements( $lesson_id, $course_id, $quiz_copy, $assignment_copy );
			}
		}

		if ( null !== $course_drip ) {
			self::report_course_drip( $course_id, $copy_id, $course_drip );
		}

		self::migrate_lesson_prerequisites( $course_id, $copy_id, $lesson_items );
	}

	/**
	 * Course-level lesson drip (LifterLMS 7.x course "Restrictions" → Enable Lesson Drip): `_llms_lesson_drip` = yes and
	 * `_llms_drip_method` = start ("after course start or enrollment" — the only method LifterLMS implements). Lesson N
	 * (1-based) unlocks ( N - `_llms_ignore_lessons` ) x `_llms_days_before_available` days after the course start date
	 * (`_llms_start_date`), or after the student's enrollment date when the course has no start date; the first
	 * `_llms_ignore_lessons` lessons are available immediately. It takes precedence over the lessons' own drip settings.
	 *
	 * @param int $course_id LifterLMS course ID.
	 * @return array|null Keys days, ignore, start (Unix time, 0 = enrollment based); null when not enabled.
	 */
	private static function course_drip( int $course_id ): ?array {
		if ( 'yes' !== get_post_meta( $course_id, '_llms_lesson_drip', true ) || 'start' !== get_post_meta( $course_id, '_llms_drip_method', true ) ) {
			return null;
		}

		return array(
			'days'   => absint( get_post_meta( $course_id, '_llms_days_before_available', true ) ),
			'ignore' => max( 0, (int) get_post_meta( $course_id, '_llms_ignore_lessons', true ) ),
			'start'  => self::site_timestamp( (string) get_post_meta( $course_id, '_llms_start_date', true ) ),
		);
	}

	/**
	 * Apply the course-level drip to the curriculum items of one lesson (lesson, quiz and assignment copies).
	 * Without MasterStudy Pro nothing is locked (reported once per course by report_course_drip()).
	 *
	 * @param array $drip          course_drip() settings.
	 * @param int   $lesson_number 1-based lesson number, 0 = not a published lesson (no drip).
	 * @param int[] $items         Curriculum item copies of the lesson.
	 */
	private static function apply_course_drip( array $drip, int $lesson_number, array $items ): void {
		if ( ! ProTarget::pro_active() || $lesson_number <= $drip['ignore'] ) {
			return;
		}

		$days = ( $lesson_number - $drip['ignore'] ) * $drip['days'];

		foreach ( $items as $item_id ) {
			if ( $drip['start'] > 0 ) {
				ProTarget::drip_on_date( (int) $item_id, $drip['start'] + $days * DAY_IN_SECONDS );
			} else {
				ProTarget::drip_after_days( (int) $item_id, $days );
			}
		}
	}

	/**
	 * Without MasterStudy Pro the course-level drip cannot be applied: report it and keep the settings.
	 *
	 * @param int   $course_id LifterLMS course ID.
	 * @param int   $copy_id   MasterStudy copy of the course.
	 * @param array $drip      course_drip() settings.
	 */
	private static function report_course_drip( int $course_id, int $copy_id, array $drip ): void {
		if ( ProTarget::pro_active() ) {
			return;
		}

		Target::store_unmigrated_meta(
			$copy_id,
			'llms_course_drip',
			array(
				'method'         => 'start',
				'days'           => $drip['days'],
				'ignore_lessons' => $drip['ignore'],
				'start_date'     => (string) get_post_meta( $course_id, '_llms_start_date', true ),
			)
		);
		self::report_course(
			$course_id,
			__( 'Course lesson drip', 'masterstudy-lms-learning-management-system' ),
			__( 'Content drip requires MasterStudy LMS Pro (Sequential drip content addon), which is not active — the course-level lesson drip was not applied and the lessons are not locked; the drip settings were kept in the _migrated_llms_course_drip meta.', 'masterstudy-lms-learning-management-system' )
		);
	}

	/**
	 * Copy the quiz of a lesson (`_llms_quiz`) with its questions.
	 *
	 * @param int $lesson_id LifterLMS lesson ID.
	 * @param int $course_id LifterLMS course ID.
	 * @return int Quiz copy ID to add to the curriculum, 0 when the lesson has no (usable) quiz.
	 */
	private static function migrate_lesson_quiz( int $lesson_id, int $course_id ): int {
		$quiz_id = (int) get_post_meta( $lesson_id, '_llms_quiz', true );

		if ( ! $quiz_id || 'llms_quiz' !== get_post_type( $quiz_id ) ) {
			return 0;
		}

		$questions = self::get_quiz_questions( $quiz_id );

		// Masteriyo skips quizzes without questions.
		if ( empty( $questions ) ) {
			Helper::log( 'warning', sprintf( 'Migration [lifterlms]: quiz %d of lesson %d has no questions — skipped.', $quiz_id, $lesson_id ) );
			Report::add(
				Report::GROUP_QUIZZES,
				array(
					'source_id' => $quiz_id,
					'title'     => get_post_field( 'post_title', $quiz_id ),
					'type'      => __( 'Empty quiz', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'The quiz has no questions, so it was not imported and not added to the course curriculum (the LifterLMS quiz is left unchanged).', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_UNSUPPORTED,
					'parent'    => self::parent_label( 'lesson', $lesson_id ),
					'course'    => get_post_field( 'post_title', $course_id ),
					'post_id'   => self::link_id( $quiz_id ),
				)
			);
			return 0;
		}

		$question_ids = array();

		foreach ( $questions as $question ) {
			$question_ids = array_merge( $question_ids, self::process_question_migration( $question, $quiz_id, $course_id ) );
		}

		$quiz_copy = Target::copy_post( $quiz_id, PostType::QUIZ, self::SOURCE );

		// Every question was dropped (unsupported types / no answers): the quiz is migrated empty.
		if ( empty( $question_ids ) ) {
			Report::add(
				Report::GROUP_QUIZZES,
				array(
					'source_id' => $quiz_id,
					'title'     => get_post_field( 'post_title', $quiz_id ),
					'type'      => __( 'LifterLMS quiz', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'None of the quiz questions could be imported (see the questions group), so the quiz was migrated without questions.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_PARTIAL,
					'parent'    => self::parent_label( 'lesson', $lesson_id ),
					'course'    => get_post_field( 'post_title', $course_id ),
					'post_id'   => $quiz_copy,
				)
			);
		}

		Target::set_quiz_questions( $quiz_copy, $question_ids );
		self::migrate_quiz_settings( $quiz_id, $quiz_copy );

		// A quiz switched off on its lesson ("Enable Quiz" = no) is hidden from LifterLMS students: its copy is a
		// draft kept out of the curriculum, so it does not become visible.
		if ( 'no' === get_post_meta( $lesson_id, '_llms_quiz_enabled', true ) ) {
			global $wpdb;

			if ( empty( Target::course_ids_of( $quiz_copy ) ) && 'draft' !== get_post_status( $quiz_copy ) ) {
				$wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => $quiz_copy ) );
				clean_post_cache( $quiz_copy );
			}

			Report::add(
				Report::GROUP_QUIZZES,
				array(
					'source_id' => $quiz_id,
					'title'     => get_post_field( 'post_title', $quiz_id ),
					'type'      => __( 'Disabled quiz', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'The quiz was disabled on its LifterLMS lesson (hidden from students), so it was imported as a draft MasterStudy quiz and not added to the course curriculum.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_PARTIAL,
					'parent'    => self::parent_label( 'lesson', $lesson_id ),
					'course'    => get_post_field( 'post_title', $course_id ),
					'post_id'   => $quiz_copy,
				)
			);

			return 0;
		}

		return $quiz_copy;
	}

	/**
	 * LifterLMS lessons can require a passing quiz grade / assignment grade before they count as completed.
	 * MasterStudy completes a lesson on its own, so the requirement is reported.
	 *
	 * @param int $lesson_id     Lesson ID.
	 * @param int $course_id     Course ID.
	 * @param int $quiz_id       Quiz added to the curriculum (0 = none).
	 * @param int $assignment_id Assignment added to the curriculum (0 = none).
	 */
	private static function report_completion_requirements( int $lesson_id, int $course_id, int $quiz_id, int $assignment_id ): void {
		$requires = array();

		if ( $quiz_id && 'yes' === get_post_meta( $lesson_id, '_llms_require_passing_grade', true ) ) {
			$requires[] = __( 'a passing quiz grade', 'masterstudy-lms-learning-management-system' );
		}

		if ( $assignment_id && 'yes' === get_post_meta( $lesson_id, '_llms_require_assignment_passing_grade', true ) ) {
			$requires[] = __( 'a passing assignment grade', 'masterstudy-lms-learning-management-system' );
		}

		if ( empty( $requires ) ) {
			return;
		}

		self::report_lesson(
			$lesson_id,
			$course_id,
			__( 'Lesson completion requirement', 'masterstudy-lms-learning-management-system' ),
			sprintf(
				/* translators: %s: requirement list */
				__( 'The LifterLMS lesson was only completed with %s. In MasterStudy the quiz/assignment is a separate curriculum item right after the lesson and the lesson is completed on its own (turn on the sequential lock of the Sequential drip content addon to enforce the order).', 'masterstudy-lms-learning-management-system' ),
				implode( ' / ', $requires )
			)
		);
	}

	/**
	 * Copy the LifterLMS Assignments add-on assignment of a lesson (`_llms_assignment`) into a
	 * MasterStudy assignment (Pro "assignments" addon). Student submissions are imported by the
	 * `assignments` step.
	 *
	 * @param int $lesson_id LifterLMS lesson ID.
	 * @param int $course_id LifterLMS course ID.
	 * @return int Assignment copy ID to add to the curriculum, 0 when there is none or Pro is inactive.
	 */
	private static function migrate_lesson_assignment( int $lesson_id, int $course_id ): int {
		$assignment_id = (int) get_post_meta( $lesson_id, '_llms_assignment', true );

		if ( ! $assignment_id || 'llms_assignment' !== get_post_type( $assignment_id ) ) {
			return 0;
		}

		if ( 'no' === get_post_meta( $lesson_id, '_llms_assignment_enabled', true ) ) {
			Report::add(
				Report::GROUP_ASSIGNMENTS,
				array(
					'source_id' => $assignment_id,
					'title'     => get_post_field( 'post_title', $assignment_id ),
					'type'      => __( 'Disabled assignment', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'The assignment was disabled on its LifterLMS lesson (hidden from students), so it was not imported.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_UNSUPPORTED,
					'parent'    => self::parent_label( 'lesson', $lesson_id ),
					'course'    => get_post_field( 'post_title', $course_id ),
					'post_id'   => self::link_id( $assignment_id ),
				)
			);
			return 0;
		}

		if ( ! ProTarget::pro_active() ) {
			Report::add(
				Report::GROUP_ASSIGNMENTS,
				array(
					'source_id' => $assignment_id,
					'title'     => get_post_field( 'post_title', $assignment_id ),
					'type'      => __( 'LifterLMS assignment', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'Assignments require MasterStudy LMS Pro, which is not active — the assignment was not imported and is not part of the MasterStudy curriculum.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_UNSUPPORTED,
					'parent'    => self::parent_label( 'lesson', $lesson_id ),
					'course'    => get_post_field( 'post_title', $course_id ),
					'post_id'   => self::link_id( $assignment_id ),
				)
			);
			return 0;
		}

		$type = (string) get_post_meta( $assignment_id, '_llms_assignment_type', true );

		$assignment_copy = Target::copy_post( $assignment_id, PostType::ASSIGNMENT, self::SOURCE );

		$passing = get_post_meta( $assignment_id, '_llms_passing_percent', true );

		if ( '' === (string) $passing ) {
			$passing = get_post_meta( $assignment_id, '_llms_passing_grade', true );
		}

		ProTarget::set_assignment(
			$assignment_copy,
			array(
				'attempts'      => 0,
				'passing_grade' => '' !== (string) $passing ? (float) $passing : '',
			)
		);

		Target::store_unmigrated_meta( $assignment_copy, 'llms_assignment_type', $type );
		Target::store_unmigrated_meta( $assignment_copy, 'llms_points', get_post_meta( $assignment_id, '_llms_points', true ) );

		// A task list has no MasterStudy equivalent: the tasks are appended to the text of the assignment copy.
		if ( 'tasklist' === $type ) {
			$tasks = self::get_assignment_tasks( $assignment_id );

			if ( ! empty( $tasks ) ) {
				$content = (string) get_post_field( 'post_content', $assignment_copy );

				if ( false === strpos( $content, 'masterstudy-migrated-tasklist' ) ) {
					$list = '<ul class="masterstudy-migrated-tasklist">';

					foreach ( $tasks as $task ) {
						$list .= '<li>' . esc_html( $task ) . '</li>';
					}

					Target::update_unfiltered(
						array(
							'ID'           => $assignment_copy,
							'post_content' => wp_slash( $content . $list . '</ul>' ),
						)
					);
				}
			}

			Report::add(
				Report::GROUP_ASSIGNMENTS,
				array(
					'source_id' => $assignment_id,
					'title'     => get_post_field( 'post_title', $assignment_id ),
					'type'      => __( 'Task list assignment', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'MasterStudy assignments have no task lists — the tasks were added to the assignment text and students answer with text or files.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_PARTIAL,
					'parent'    => self::parent_label( 'lesson', $lesson_id ),
					'course'    => get_post_field( 'post_title', $course_id ),
					'post_id'   => $assignment_copy,
				)
			);
		}

		return $assignment_copy;
	}

	/**
	 * Task titles of a LifterLMS task list assignment (`_llms_task_{id}` meta, like question choices).
	 *
	 * @param int $assignment_id Assignment ID.
	 * @return string[]
	 */
	private static function get_assignment_tasks( int $assignment_id ): array {
		global $wpdb;

		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s ORDER BY meta_id ASC",
				$assignment_id,
				$wpdb->esc_like( '_llms_task_' ) . '%'
			)
		);

		$tasks = array();

		foreach ( $rows as $row ) {
			$task  = maybe_unserialize( $row->meta_value );
			$title = is_array( $task ) ? (string) ( $task['title'] ?? $task['task'] ?? '' ) : (string) $task;

			if ( '' !== trim( $title ) ) {
				$marker = is_array( $task ) ? (string) ( $task['marker'] ?? '' ) : '';
				$key    = $marker . '|' . count( $tasks );

				$tasks[ $key ] = wp_strip_all_tags( $title );
			}
		}

		uksort( $tasks, 'strnatcmp' );

		return array_values( $tasks );
	}

	/**
	 * Lesson prerequisites ("lesson unlocks after lesson X is completed") → MasterStudy drip
	 * "after a previous item" map (Pro "sequential_drip_content" addon).
	 *
	 * @param int   $course_id    LifterLMS course ID.
	 * @param int   $copy_id      MasterStudy copy of the course.
	 * @param array $lesson_items LifterLMS lesson ID => curriculum item copies of that lesson (lesson, quiz, assignment).
	 */
	private static function migrate_lesson_prerequisites( int $course_id, int $copy_id, array $lesson_items ): void {
		$map = array();

		foreach ( $lesson_items as $lesson_id => $items ) {
			if ( 'yes' !== get_post_meta( $lesson_id, '_llms_has_prerequisite', true ) ) {
				continue;
			}

			$prerequisite = (int) get_post_meta( $lesson_id, '_llms_prerequisite', true );
			$drip_method  = (string) get_post_meta( $lesson_id, '_llms_drip_method', true );
			$delay        = 'prerequisite' === $drip_method ? absint( get_post_meta( $lesson_id, '_llms_days_before_available', true ) ) : 0;
			$lesson_copy  = (int) $items[0];

			if ( ! $prerequisite ) {
				continue;
			}

			if ( ! ProTarget::pro_active() ) {
				Target::store_unmigrated_meta( $lesson_copy, 'llms_prerequisite', self::link_id( $prerequisite ) );
				self::report_lesson(
					$lesson_id,
					$course_id,
					__( 'Lesson prerequisite', 'masterstudy-lms-learning-management-system' ),
					__( 'Lesson prerequisites require MasterStudy LMS Pro (Sequential drip content addon), which is not active — the lesson is not locked; the prerequisite was kept in the _migrated_llms_prerequisite meta.', 'masterstudy-lms-learning-management-system' )
				);
				continue;
			}

			if ( ! isset( $lesson_items[ $prerequisite ] ) ) {
				Target::store_unmigrated_meta( $lesson_copy, 'llms_prerequisite', self::link_id( $prerequisite ) );
				self::report_lesson(
					$lesson_id,
					$course_id,
					__( 'Lesson prerequisite', 'masterstudy-lms-learning-management-system' ),
					__( 'The prerequisite lesson is not part of this course curriculum, so the lesson is not locked; the prerequisite was kept in the _migrated_llms_prerequisite meta.', 'masterstudy-lms-learning-management-system' )
				);
				continue;
			}

			// The prerequisite lesson copy is the parent; the dependent lesson and its quiz/assignment copies are the children.
			$parent         = (int) $lesson_items[ $prerequisite ][0];
			$map[ $parent ] = array_merge( $map[ $parent ] ?? array(), $items );

			if ( $delay > 0 ) {
				self::report_lesson(
					$lesson_id,
					$course_id,
					__( 'Drip content', 'masterstudy-lms-learning-management-system' ),
					/* translators: %d: number of days */
					sprintf( __( 'The lesson unlocked %d day(s) after the prerequisite lesson was completed — MasterStudy unlocks it as soon as the prerequisite lesson is completed.', 'masterstudy-lms-learning-management-system' ), $delay )
				);
			}
		}

		if ( empty( $map ) ) {
			return;
		}

		$rows = array();

		foreach ( $map as $parent => $children ) {
			$rows[] = array(
				'parent'   => (int) $parent,
				'children' => array_values( array_unique( array_map( 'intval', $children ) ) ),
			);
		}

		ProTarget::drip_after_items( $copy_id, $rows );
	}

	/**
	 * Lesson settings: video, audio, free preview (drip is applied by migrate_course()).
	 *
	 * @param int $lesson_id   LifterLMS lesson ID (read).
	 * @param int $lesson_copy MasterStudy copy of the lesson (written).
	 * @param int $course_id   LifterLMS course ID.
	 */
	private static function migrate_lesson( int $lesson_id, int $lesson_copy, int $course_id ): void {
		Target::set_lesson(
			$lesson_copy,
			array(
				'type'    => 'text',
				// Migrate free lesson preview flag.
				'preview' => 'yes' === get_post_meta( $lesson_id, '_llms_free_lesson', true ),
				'excerpt' => (string) get_post_field( 'post_excerpt', $lesson_id ),
			)
		);

		$url = self::embed_url( $lesson_id, 'video' );

		if ( '' !== $url ) {
			Target::set_lesson_video( $lesson_copy, Target::detect_video_source( $url ), $url );
		}

		// Audio lessons are a MasterStudy Pro Plus feature (addon "audio_lesson").
		$audio = self::embed_url( $lesson_id, 'audio' );

		if ( '' === $audio ) {
			return;
		}

		if ( ! ProTarget::plus_active() ) {
			$reason = __( 'Audio lessons require MasterStudy LMS Pro Plus, which is not active — the lesson audio embed was not imported; the URL was kept in the _migrated_llms_audio_embed meta.', 'masterstudy-lms-learning-management-system' );
		} elseif ( '' !== $url ) {
			$reason = __( 'The lesson has both a video and an audio embed, but a MasterStudy lesson has one media type — the video was kept and the audio URL was kept in the _migrated_llms_audio_embed meta.', 'masterstudy-lms-learning-management-system' );
		} elseif ( self::set_audio_lesson( $lesson_copy, $audio ) ) {
			return;
		} else {
			$reason = __( 'The audio embed URL is not an audio file, SoundCloud or Spotify link, so it could not be turned into a MasterStudy audio lesson; the URL was kept in the _migrated_llms_audio_embed meta.', 'masterstudy-lms-learning-management-system' );
		}

		Target::store_unmigrated_meta( $lesson_copy, 'llms_audio_embed', $audio );
		self::report_lesson( $lesson_id, $course_id, __( 'Lesson audio', 'masterstudy-lms-learning-management-system' ), $reason );
	}

	/**
	 * Video / audio embed URL of a course or lesson: `_llms_video_embed` / `_llms_audio_embed`, else the pre-3.0
	 * LifterLMS keys `_video_embed` / `_audio_embed` (sites whose database update never renamed them).
	 *
	 * @param int    $post_id Course or lesson ID.
	 * @param string $type    video|audio.
	 */
	private static function embed_url( int $post_id, string $type ): string {
		foreach ( array( '_llms_' . $type . '_embed', '_' . $type . '_embed' ) as $key ) {
			$value = trim( (string) get_post_meta( $post_id, $key, true ) );

			if ( '' !== $value ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * Turn a lesson copy into a MasterStudy audio lesson from a LifterLMS audio embed (oEmbed URL).
	 * No oEmbed HTTP request is made: audio files play directly, SoundCloud/Spotify get their player iframe.
	 *
	 * @param int    $lesson_id MasterStudy lesson copy ID.
	 * @param string $audio     LifterLMS `_llms_audio_embed` value.
	 * @return bool False when the value cannot be converted.
	 */
	private static function set_audio_lesson( int $lesson_id, string $audio ): bool {
		if ( preg_match( '/<iframe|<audio|<embed/i', $audio ) ) {
			ProTarget::set_audio_lesson( $lesson_id, 'embed', $audio );
			return true;
		}

		$url = esc_url_raw( $audio );

		if ( '' === $url ) {
			return false;
		}

		if ( preg_match( '/\.(mp3|m4a|aac|ogg|oga|wav|flac)$/i', (string) wp_parse_url( $url, PHP_URL_PATH ) ) ) {
			ProTarget::set_audio_lesson( $lesson_id, 'ext_link', $url );
			return true;
		}

		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$src  = '';

		if ( false !== strpos( $host, 'soundcloud.com' ) ) {
			$src = 'https://w.soundcloud.com/player/?url=' . rawurlencode( $url ) . '&visual=false';
		} elseif ( 'open.spotify.com' === $host ) {
			$path = (string) wp_parse_url( $url, PHP_URL_PATH );
			$src  = 0 === strpos( $path, '/embed/' ) ? $url : 'https://open.spotify.com/embed' . $path;
		}

		if ( '' === $src ) {
			return false;
		}

		ProTarget::set_audio_lesson(
			$lesson_id,
			'embed',
			sprintf( '<iframe width="100%%" height="166" frameborder="0" allow="autoplay; encrypted-media" src="%s"></iframe>', esc_url( $src ) )
		);

		return true;
	}

	/**
	 * Quiz settings.
	 *
	 * @param int $quiz_id   LifterLMS quiz ID (read).
	 * @param int $quiz_copy MasterStudy copy of the quiz (written).
	 */
	private static function migrate_quiz_settings( int $quiz_id, int $quiz_copy ): void {
		Target::set_quiz(
			$quiz_copy,
			array(
				'duration_minutes'    => 'yes' === get_post_meta( $quiz_id, '_llms_limit_time', true ) ? absint( get_post_meta( $quiz_id, '_llms_time_limit', true ) ) : 0,
				'passing_grade'       => floatval( get_post_meta( $quiz_id, '_llms_passing_percent', true ) ),
				'attempts'            => 'yes' === get_post_meta( $quiz_id, '_llms_limit_attempts', true ) ? absint( get_post_meta( $quiz_id, '_llms_allowed_attempts', true ) ) : 0,
				// Migrate quiz randomize and answer reveal settings.
				'random_questions'    => 'yes' === get_post_meta( $quiz_id, '_llms_random_questions', true ),
				'show_correct_answer' => 'yes' === get_post_meta( $quiz_id, '_llms_show_correct_answer', true ),
				'excerpt'             => (string) get_post_field( 'post_excerpt', $quiz_id ),
			)
		);
	}

	/**
	 * Migrate course info from LifterLMS.
	 *
	 * Masteriyo writes `_enrollment_limit` / `_reviews_allowed`; MasterStudy has no equivalent,
	 * so those values are preserved with store_unmigrated_meta().
	 *
	 * Pricing: Masteriyo additionally creates a WooCommerce product per access plan
	 * (create_woocommerce_product()). MasterStudy sells courses natively (and uses the course
	 * itself as the WooCommerce product in WooCommerce mode), so no product is created — the
	 * course price is taken from the first access plan via Target::set_pricing().
	 *
	 * @param int $course_id LifterLMS course ID (read).
	 * @param int $copy_id   MasterStudy copy of the course (written).
	 */
	private static function migrate_course_info( int $course_id, int $copy_id ): void {
		if ( 'yes' === get_post_meta( $course_id, '_llms_enable_capacity', true ) ) {
			$capacity = absint( get_post_meta( $course_id, '_llms_capacity', true ) );
			Target::store_unmigrated_meta( $copy_id, 'llms_capacity', $capacity );

			if ( $capacity > 0 ) {
				self::report_course(
					$course_id,
					__( 'Course capacity', 'masterstudy-lms-learning-management-system' ),
					/* translators: %d: maximum number of students */
					sprintf( __( 'MasterStudy has no enrollment limit — the capacity of %d students was not applied (kept in the _migrated_llms_capacity meta).', 'masterstudy-lms-learning-management-system' ), $capacity )
				);
			}
		}

		Target::store_unmigrated_meta( $copy_id, 'llms_reviews_enabled', (string) get_post_meta( $course_id, '_llms_reviews_enabled', true ) );

		Target::set_course_info(
			$copy_id,
			array(
				'duration_info' => (string) get_post_meta( $course_id, '_llms_length', true ),
			)
		);

		// The course audio embed and start/end dates have no direct equivalent; the video is the Pro course preview.
		foreach ( array( 'start_date', 'end_date', 'time_period' ) as $key ) {
			Target::store_unmigrated_meta( $copy_id, 'llms_' . $key, get_post_meta( $course_id, '_llms_' . $key, true ) );
		}

		Target::store_unmigrated_meta( $copy_id, 'llms_video_embed', self::embed_url( $course_id, 'video' ) );
		Target::store_unmigrated_meta( $copy_id, 'llms_audio_embed', self::embed_url( $course_id, 'audio' ) );

		$video_set = self::set_course_preview_video( $course_id, $copy_id );
		$has_video = '' !== self::embed_url( $course_id, 'video' );
		$has_audio = '' !== self::embed_url( $course_id, 'audio' );

		if ( ( $has_video && ! $video_set ) || $has_audio ) {
			self::report_course(
				$course_id,
				__( 'Course preview video/audio', 'masterstudy-lms-learning-management-system' ),
				$has_video && ! $video_set
					? __( 'Course preview videos require MasterStudy LMS Pro, which is not active — the course video/audio embed was not imported; the URLs were kept in the _migrated_llms_video_embed / _migrated_llms_audio_embed meta.', 'masterstudy-lms-learning-management-system' )
					: __( 'MasterStudy courses have no preview audio — the course audio embed was not imported; the URL was kept in the _migrated_llms_audio_embed meta.', 'masterstudy-lms-learning-management-system' )
			);
		}

		if ( '' !== (string) get_post_meta( $course_id, '_llms_start_date', true ) || '' !== (string) get_post_meta( $course_id, '_llms_end_date', true ) ) {
			self::report_course(
				$course_id,
				__( 'Course start/end dates', 'masterstudy-lms-learning-management-system' ),
				__( 'MasterStudy has no course start/end dates — the LifterLMS course start and end dates were not applied (kept in the _migrated_llms_start_date / _migrated_llms_end_date meta).', 'masterstudy-lms-learning-management-system' )
			);
		}

		self::migrate_course_availability( $course_id, $copy_id );

		$all_plans = self::get_access_plan_ids( $course_id );

		if ( empty( $all_plans ) ) {
			return;
		}

		// Members-only plans are not public prices: they never set the course price or become subscription plans.
		$product_ids  = array_values(
			array_filter(
				$all_plans,
				static function ( $plan_id ) {
					return ! self::is_members_only_plan( (int) $plan_id );
				}
			)
		);
		$members_only = array_values( array_diff( $all_plans, $product_ids ) );

		self::store_access_plans( $copy_id, $all_plans );

		if ( empty( $product_ids ) ) {
			self::set_members_only_pricing( $course_id, $copy_id, $members_only );
			return;
		}

		if ( ! empty( $members_only ) ) {
			self::report_course(
				$course_id,
				__( 'Members-only access plans', 'masterstudy-lms-learning-management-system' ),
				sprintf(
					/* translators: %d: number of access plans */
					__( '%d LifterLMS access plan(s) of the course were only available to members of a membership; they were not used for the MasterStudy price (kept in the _migrated_llms_access_plans meta).', 'masterstudy-lms-learning-management-system' ),
					count( $members_only )
				)
			);
		}

		// With Pro Plus, recurring plans become subscription plans (created in finalize_step( 'courses' ),
		// once the Subscriptions addon tables exist) and the one-time price comes from the other plans.
		$recurring     = array_values(
			array_filter(
				$product_ids,
				static function ( $plan_id ) {
					return self::is_recurring_plan( (int) $plan_id );
				}
			)
		);
		$subscriptions = ProTarget::plus_active() && ! empty( $recurring );
		$one_time      = $subscriptions ? array_values( array_diff( $product_ids, $recurring ) ) : $product_ids;

		if ( count( $one_time ) > 1 ) {
			self::report_course(
				$course_id,
				__( 'Multiple access plans', 'masterstudy-lms-learning-management-system' ),
				/* translators: %d: number of access plans */
				sprintf( __( 'MasterStudy has one price per course — only the first of %d one-time access plans was used for the price; all plans were kept in the _migrated_llms_access_plans meta.', 'masterstudy-lms-learning-management-system' ), count( $one_time ) )
			);
		}

		if ( $subscriptions ) {
			Helper::request_addon( 'subscriptions' );

			// Subscription-only course: paid, without a one-time purchase option.
			if ( empty( $one_time ) ) {
				update_post_meta( $copy_id, 'pricing_mode', PricingMode::PAID );
				update_post_meta( $copy_id, 'single_sale', '' );
				update_post_meta( $copy_id, 'price', '' );
				update_post_meta( $copy_id, 'sale_price', '' );
				return;
			}
		}

		self::set_one_time_price( $course_id, $copy_id, $one_time, $subscriptions ? array() : $recurring );
	}

	/**
	 * Keep every LifterLMS access plan of a course for reference (MasterStudy has one price per course).
	 *
	 * @param int   $copy_id     MasterStudy copy of the course.
	 * @param int[] $product_ids Access plan IDs.
	 */
	private static function store_access_plans( int $copy_id, array $product_ids ): void {
		$plans = array();

		foreach ( $product_ids as $plan_id ) {
			$plans[ (int) $plan_id ] = array(
				'availability'      => get_post_meta( $plan_id, '_llms_availability', true ),
				'memberships'       => self::plan_membership_ids( (int) $plan_id ),
				'visibility'        => implode( ',', self::term_slugs( (int) $plan_id, 'llms_access_plan_visibility' ) ),
				'title'             => get_post_field( 'post_title', $plan_id ),
				'price'             => get_post_meta( $plan_id, '_llms_price', true ),
				'sale_price'        => get_post_meta( $plan_id, '_llms_sale_price', true ),
				'on_sale'           => get_post_meta( $plan_id, '_llms_on_sale', true ),
				'is_free'           => get_post_meta( $plan_id, '_llms_is_free', true ),
				'frequency'         => get_post_meta( $plan_id, '_llms_frequency', true ),
				'period'            => get_post_meta( $plan_id, '_llms_period', true ),
				'length'            => get_post_meta( $plan_id, '_llms_length', true ),
				'trial_offer'       => get_post_meta( $plan_id, '_llms_trial_offer', true ),
				'trial_length'      => get_post_meta( $plan_id, '_llms_trial_length', true ),
				'trial_period'      => get_post_meta( $plan_id, '_llms_trial_period', true ),
				'trial_price'       => get_post_meta( $plan_id, '_llms_trial_price', true ),
				'access_expiration' => get_post_meta( $plan_id, '_llms_access_expiration', true ),
				'access_length'     => get_post_meta( $plan_id, '_llms_access_length', true ),
				'access_period'     => get_post_meta( $plan_id, '_llms_access_period', true ),
				'access_expires'    => get_post_meta( $plan_id, '_llms_access_expires', true ),
			);
		}

		// All plans are kept for reference — MasterStudy has one price per course.
		Target::store_unmigrated_meta( $copy_id, 'llms_access_plans', $plans );
	}

	/**
	 * Course price from the first one-time access plan (sale price/dates and access expiration included).
	 *
	 * @param int   $course_id LifterLMS course ID.
	 * @param int   $copy_id   MasterStudy copy of the course.
	 * @param int[] $one_time  One-time plan IDs, in plan order (may hold recurring plans without Plus).
	 * @param int[] $recurring Recurring plans imported as a one-time price (Plus inactive).
	 */
	private static function set_one_time_price( int $course_id, int $copy_id, array $one_time, array $recurring ): void {
		$product_id    = (int) reset( $one_time );
		$regular_price = (float) get_post_meta( $product_id, '_llms_price', true );
		$sale_price    = get_post_meta( $product_id, '_llms_sale_price', true );
		$is_on_sale    = get_post_meta( $product_id, '_llms_on_sale', true );
		$sale_start    = get_post_meta( $product_id, '_llms_sale_start', true );
		$sale_end      = get_post_meta( $product_id, '_llms_sale_end', true );

		if ( 'yes' === get_post_meta( $product_id, '_llms_is_free', true ) ) {
			$regular_price = 0.0;
		}

		// Without Pro Plus, recurring billing is imported as a one-time price.
		foreach ( $recurring as $plan_id ) {
			Helper::log(
				'warning',
				sprintf( 'Migration [lifterlms]: course %d has a recurring access plan %d — subscriptions require MasterStudy LMS Pro Plus; migrated as a one-time price.', $course_id, $plan_id )
			);
			self::report_course(
				$course_id,
				__( 'Recurring access plan', 'masterstudy-lms-learning-management-system' ),
				/* translators: %s: access plan title */
				sprintf( __( 'Subscriptions require MasterStudy LMS Pro Plus, which is not active — the recurring (subscription) access plan "%s" was imported as a one-time price; recurring billing is not migrated.', 'masterstudy-lms-learning-management-system' ), get_post_field( 'post_title', $plan_id ) )
			);

			if ( $regular_price <= 0 ) {
				$regular_price = (float) get_post_meta( $plan_id, '_llms_price', true );
			}
			break;
		}

		Target::set_pricing(
			$copy_id,
			$regular_price,
			'yes' === $is_on_sale && '' !== (string) $sale_price ? (float) $sale_price : null
		);

		if ( 'yes' === $is_on_sale && $regular_price > 0 ) {
			self::set_sale_dates( $copy_id, (string) $sale_start, (string) $sale_end );
		}

		// Access expiration. "limited-period" maps to MasterStudy access duration (days).
		$access_expiration = get_post_meta( $product_id, '_llms_access_expiration', true );

		if ( 'limited-period' === $access_expiration ) {
			$days = self::period_to_days(
				absint( get_post_meta( $product_id, '_llms_access_length', true ) ),
				(string) get_post_meta( $product_id, '_llms_access_period', true )
			);

			if ( $days > 0 ) {
				Target::set_course_info( $copy_id, array( 'end_time' => $days ) );
			}
		} elseif ( 'limited-date' === $access_expiration ) {
			Helper::log(
				'warning',
				sprintf( 'Migration [lifterlms]: course %d has a fixed-date access expiry — MasterStudy only supports an access duration in days; value kept in _migrated_llms_access_plans.', $course_id )
			);
			self::report_course(
				$course_id,
				__( 'Fixed-date access expiry', 'masterstudy-lms-learning-management-system' ),
				__( 'MasterStudy only supports an access duration in days — the fixed access expiry date was not applied (kept in the _migrated_llms_access_plans meta).', 'masterstudy-lms-learning-management-system' )
			);
		}
	}

	/**
	 * Course video embed (`_llms_video_embed`, an oEmbed URL or embed HTML) → MasterStudy Pro course
	 * preview video (same meta keys as the Tutor LMS reader).
	 *
	 * @param int $course_id LifterLMS course ID (read).
	 * @param int $copy_id   MasterStudy copy of the course (written).
	 * @return bool Whether the preview video was set.
	 */
	private static function set_course_preview_video( int $course_id, int $copy_id ): bool {
		$url = self::embed_url( $course_id, 'video' );

		if ( '' === $url || ! ProTarget::pro_active() ) {
			return false;
		}

		switch ( Target::detect_video_source( $url ) ) {
			case 'youtube':
				update_post_meta( $copy_id, 'video_type', 'youtube' );
				update_post_meta( $copy_id, 'youtube_url', esc_url_raw( $url ) );
				break;
			case 'vimeo':
				update_post_meta( $copy_id, 'video_type', 'vimeo' );
				update_post_meta( $copy_id, 'vimeo_url', esc_url_raw( $url ) );
				break;
			case 'embed':
				update_post_meta( $copy_id, 'video_type', 'embed' );
				update_post_meta( $copy_id, 'embed_ctx', $url );
				break;
			default:
				update_post_meta( $copy_id, 'video_type', 'ext_link' );
				update_post_meta( $copy_id, 'external_url', esc_url_raw( $url ) );
		}

		return true;
	}

	/**
	 * Get all access plan IDs for a given LifterLMS course ID.
	 *
	 * @param int $course_id LifterLMS course ID.
	 *
	 * @return array<int> Array of access plan IDs.
	 */
	private static function get_access_plan_ids( int $course_id ): array {
		global $wpdb;

		// Like LLMS_Product::get_access_plans(): published plans only (drafts / trashed plans are not sold), ordered by
		// the post `menu_order` column (the plan order of the course editor; LLMS_Post_Model maps `menu_order` to the
		// WP_Post field, there is no `_llms_menu_order` meta).
		$product_ids = array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT p.ID FROM {$wpdb->postmeta} pm
					 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'llms_access_plan' AND p.post_status = 'publish'
					 WHERE pm.meta_key = %s AND pm.meta_value = %d
					 ORDER BY p.menu_order ASC, p.ID ASC",
					'_llms_product_id',
					$course_id
				)
			)
		);

		// Plans hidden from the sales page (`llms_access_plan_visibility` term "hidden") only set the price
		// when there is no visible plan.
		$visible = array();
		$hidden  = array();

		foreach ( $product_ids as $plan_id ) {
			if ( in_array( 'hidden', self::term_slugs( $plan_id, 'llms_access_plan_visibility' ), true ) ) {
				$hidden[] = $plan_id;
			} else {
				$visible[] = $plan_id;
			}
		}

		return array_merge( $visible, $hidden );
	}

	/**
	 * Whether an access plan can only be bought by members of a LifterLMS membership (`_llms_availability` = members).
	 *
	 * @param int $plan_id Access plan ID.
	 */
	private static function is_members_only_plan( int $plan_id ): bool {
		return 'members' === get_post_meta( $plan_id, '_llms_availability', true );
	}

	/**
	 * Membership IDs an access plan is restricted to (`_llms_availability_restrictions`).
	 *
	 * @param int $plan_id Access plan ID.
	 * @return int[]
	 */
	private static function plan_membership_ids( int $plan_id ): array {
		return array_values( array_filter( array_map( 'intval', (array) maybe_unserialize( get_post_meta( $plan_id, '_llms_availability_restrictions', true ) ) ) ) );
	}

	/**
	 * Enrollment period and catalog visibility of a course — no MasterStudy equivalent, reported and kept.
	 *
	 * @param int $course_id LifterLMS course ID (read).
	 * @param int $copy_id   MasterStudy copy of the course (written).
	 */
	private static function migrate_course_availability( int $course_id, int $copy_id ): void {
		$enroll_start = (string) get_post_meta( $course_id, '_llms_enrollment_start_date', true );
		$enroll_end   = (string) get_post_meta( $course_id, '_llms_enrollment_end_date', true );

		if ( 'yes' === get_post_meta( $course_id, '_llms_enrollment_period', true ) && ( '' !== trim( $enroll_start ) || '' !== trim( $enroll_end ) ) ) {
			Target::store_unmigrated_meta(
				$copy_id,
				'llms_enrollment_period',
				array(
					'start' => $enroll_start,
					'end'   => $enroll_end,
				)
			);
			self::report_course(
				$course_id,
				__( 'Enrollment period', 'masterstudy-lms-learning-management-system' ),
				__( 'MasterStudy has no enrollment period — students can enroll at any time; the LifterLMS enrollment start/end dates were kept in the _migrated_llms_enrollment_period meta.', 'masterstudy-lms-learning-management-system' )
			);
		}

		// Catalog visibility (`llms_product_visibility`): "hidden" and "search only" courses are not listed in the catalog.
		$visibility = self::term_slugs( $course_id, 'llms_product_visibility' );

		if ( in_array( 'hidden', $visibility, true ) || ( in_array( 'search', $visibility, true ) && ! in_array( 'catalog', $visibility, true ) ) ) {
			Target::store_unmigrated_meta( $copy_id, 'llms_visibility', implode( ',', $visibility ) );
			self::report_course(
				$course_id,
				__( 'Catalog visibility', 'masterstudy-lms-learning-management-system' ),
				__( 'The course was hidden from the LifterLMS course catalog; MasterStudy has no catalog visibility setting, so it is listed in the course catalog (make it private or a draft if it must stay hidden).', 'masterstudy-lms-learning-management-system' )
			);
		}
	}

	/**
	 * A course that is only sold to members of LifterLMS memberships: it is not sold on its own in MasterStudy
	 * (paid, no one-time purchase) and stays available to MasterStudy memberships (not_membership off). With Pro,
	 * the membership becomes a course bundle that includes the course (see migrate_memberships()).
	 *
	 * @param int   $course_id LifterLMS course ID.
	 * @param int   $copy_id   MasterStudy copy of the course.
	 * @param int[] $plan_ids  Members-only access plan IDs.
	 */
	private static function set_members_only_pricing( int $course_id, int $copy_id, array $plan_ids ): void {
		update_post_meta( $copy_id, 'pricing_mode', PricingMode::PAID );
		update_post_meta( $copy_id, 'single_sale', '' );
		update_post_meta( $copy_id, 'not_membership', '' );
		update_post_meta( $copy_id, 'price', '' );
		update_post_meta( $copy_id, 'sale_price', '' );

		$memberships = array();

		foreach ( $plan_ids as $plan_id ) {
			foreach ( self::plan_membership_ids( $plan_id ) as $membership_id ) {
				$memberships[ $membership_id ] = get_post_field( 'post_title', $membership_id );
			}
		}

		self::report_course(
			$course_id,
			__( 'Members-only course', 'masterstudy-lms-learning-management-system' ),
			sprintf(
				/* translators: 1: membership titles, 2: what happens with the membership */
				__( 'Every LifterLMS access plan of the course is restricted to members (%1$s), so the course was imported as not sold on its own (no one-time price) and kept available to memberships; enrolled students keep their access. %2$s', 'masterstudy-lms-learning-management-system' ),
				empty( $memberships ) ? __( 'no membership selected', 'masterstudy-lms-learning-management-system' ) : implode( ', ', array_filter( $memberships ) ),
				ProTarget::pro_active()
					? __( 'The course is included in the course bundle created from the membership.', 'masterstudy-lms-learning-management-system' )
					: __( 'MasterStudy has no LifterLMS memberships (course bundles require MasterStudy LMS Pro), so new members cannot get the course.', 'masterstudy-lms-learning-management-system' )
			)
		);
	}

	/**
	 * Whether a LifterLMS access plan bills repeatedly (`_llms_frequency` > 0) and is not free.
	 *
	 * @param int $plan_id Access plan ID.
	 */
	private static function is_recurring_plan( int $plan_id ): bool {
		$recurring = absint( get_post_meta( $plan_id, '_llms_frequency', true ) ) > 0 || '' !== (string) get_post_meta( $plan_id, '_llms_billing_period', true );

		return $recurring && 'yes' !== get_post_meta( $plan_id, '_llms_is_free', true );
	}

	/**
	 * Create MasterStudy subscription plans (Pro Plus, type "course") for the recurring access plans of
	 * the migrated LifterLMS courses. Idempotent per access plan. Student subscriptions are not created:
	 * gateway data cannot be migrated, so active subscribers are reported.
	 */
	private static function migrate_subscription_plans(): void {
		global $wpdb;

		if ( ! ProTarget::plus_active() ) {
			return;
		}

		foreach ( self::migrated_course_ids() as $copy_id ) {
			$course_id = Target::source_of( $copy_id );

			if ( ! $course_id ) {
				continue;
			}

			foreach ( self::get_access_plan_ids( $course_id ) as $plan_id ) {
				if ( ! self::is_recurring_plan( $plan_id ) || self::is_members_only_plan( $plan_id ) ) {
					continue;
				}

				$price    = (float) get_post_meta( $plan_id, '_llms_price', true );
				$sale     = 'yes' === get_post_meta( $plan_id, '_llms_on_sale', true ) ? (float) get_post_meta( $plan_id, '_llms_sale_price', true ) : 0.0;
				$trial    = 'yes' === get_post_meta( $plan_id, '_llms_trial_offer', true );
				$period   = (string) get_post_meta( $plan_id, '_llms_period', true );
				$interval = in_array( $period, array( 'day', 'week', 'month', 'year' ), true ) ? $period : 'month';

				try {
					ProTarget::create_subscription_plan(
						array(
							'source_id'      => 'access-plan-' . $plan_id,
							'name'           => get_post_field( 'post_title', $plan_id ),
							'description'    => (string) get_post_field( 'post_content', $plan_id ),
							'type'           => 'course',
							'object_ids'     => array( $copy_id ),
							'price'          => $price,
							'sale_price'     => $sale > 0 && $sale < $price ? $sale : null,
							'interval'       => $interval,
							'interval_value' => max( 1, absint( get_post_meta( $plan_id, '_llms_frequency', true ) ) ),
							'billing_cycles' => absint( get_post_meta( $plan_id, '_llms_length', true ) ),
							'trial_days'     => $trial ? self::period_to_days( absint( get_post_meta( $plan_id, '_llms_trial_length', true ) ), (string) get_post_meta( $plan_id, '_llms_trial_period', true ) ) : 0,
							'enabled'        => 'publish' === get_post_status( $plan_id ),
						),
						self::SOURCE
					);
				} catch ( \Exception $e ) {
					self::report_course(
						$course_id,
						__( 'Recurring access plan', 'masterstudy-lms-learning-management-system' ),
						/* translators: 1: access plan title, 2: error message */
						sprintf( __( 'The recurring access plan "%1$s" could not be imported as a subscription plan: %2$s', 'masterstudy-lms-learning-management-system' ), get_post_field( 'post_title', $plan_id ), $e->getMessage() ),
						Report::STATUS_FAILED
					);
					continue;
				}

				$losses = array();

				if ( $trial && (float) get_post_meta( $plan_id, '_llms_trial_price', true ) > 0 ) {
					$losses[] = __( 'MasterStudy trials are free — the paid trial price was not applied', 'masterstudy-lms-learning-management-system' );
				}

				if ( $sale > 0 && ( '' !== (string) get_post_meta( $plan_id, '_llms_sale_start', true ) || '' !== (string) get_post_meta( $plan_id, '_llms_sale_end', true ) ) ) {
					$losses[] = __( 'the sale price was applied without its start/end dates', 'masterstudy-lms-learning-management-system' );
				}

				// Active LifterLMS subscriptions (recurring orders) of this plan (source orders are never modified).
				$subscribers = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
						 INNER JOIN {$wpdb->postmeta} pl ON pl.post_id = p.ID AND pl.meta_key = '_llms_plan_id' AND pl.meta_value = %d
						 WHERE p.post_type = 'llms_order' AND p.post_status = 'llms-active'",
						$plan_id
					)
				);

				if ( $subscribers > 0 ) {
					$losses[] = sprintf(
						/* translators: %d: number of subscribers */
						_n(
							'%d active subscriber keeps course access but must re-subscribe — payment gateway subscriptions cannot be migrated, so no MasterStudy subscription was created',
							'%d active subscribers keep course access but must re-subscribe — payment gateway subscriptions cannot be migrated, so no MasterStudy subscriptions were created',
							$subscribers,
							'masterstudy-lms-learning-management-system'
						),
						$subscribers
					);
				}

				if ( ! empty( $losses ) ) {
					self::report_course(
						$course_id,
						__( 'Recurring access plan', 'masterstudy-lms-learning-management-system' ),
						/* translators: 1: access plan title, 2: list of losses */
						sprintf( __( 'The recurring access plan "%1$s" was imported as a MasterStudy subscription plan: %2$s.', 'masterstudy-lms-learning-management-system' ), get_post_field( 'post_title', $plan_id ), implode( '; ', $losses ) )
					);
				}
			}
		}
	}

	/**
	 * Processes migration for a single LifterLMS quiz question.
	 *
	 * Group questions are flattened (children migrated as top-level questions). Supported questions are copied
	 * into published stm-questions posts; unsupported types (content, long/short answer, upload, code, scale) and
	 * questions without usable answers are NOT copied — they are only reported (the LifterLMS question stays as is).
	 *
	 * @param object $question  Question post row (ID, post_title, post_content).
	 * @param int    $quiz_id   LifterLMS quiz ID.
	 * @param int    $course_id LifterLMS course ID (for the migration report).
	 *
	 * @return int[] MasterStudy question copy IDs to attach to the quiz copy, in order.
	 */
	private static function process_question_migration( $question, int $quiz_id, int $course_id = 0 ): array {
		$ques_id   = (int) $question->ID;
		$ques_type = (string) get_post_meta( $ques_id, '_llms_question_type', true );
		$report    = array(
			'source_id' => $ques_id,
			'title'     => (string) $question->post_title,
			'type'      => self::question_type_label( $ques_type ),
			'parent'    => self::parent_label( 'quiz', $quiz_id ),
			'course'    => $course_id ? get_post_field( 'post_title', $course_id ) : '',
			'post_id'   => $ques_id,
		);

		// Flatten group: recurse into children as top-level questions.
		if ( 'group' === $ques_type ) {
			$ids = array();

			foreach ( self::get_quiz_questions( $ques_id ) as $child ) {
				$ids = array_merge( $ids, self::process_question_migration( $child, $quiz_id, $course_id ) );
			}

			Report::add(
				Report::GROUP_QUESTIONS,
				array_merge(
					$report,
					array(
						/* translators: %d: number of questions */
						'reason' => sprintf( __( 'MasterStudy has no question groups — the group itself (title and description) was not imported; its %d child questions were added to the quiz individually.', 'masterstudy-lms-learning-management-system' ), count( $ids ) ),
						'status' => Report::STATUS_PARTIAL,
					)
				)
			);

			return $ids;
		}

		$question_type = self::determine_question_type( $ques_type, $ques_id );
		$choices       = self::get_choices( $ques_id );

		// An auto-graded short answer (Advanced Quizzes) is a single blank: its accepted answer(s) become the blank.
		if ( 'short_answer' === $ques_type && 'fill_the_gap' === $question_type ) {
			$choices = array(
				array(
					'id'      => 'short_answer',
					'choice'  => (string) get_post_meta( $ques_id, '_llms_correct_value', true ),
					'correct' => true,
					'marker'  => 'A',
				),
			);
		}

		if ( ! $question_type ) {
			Helper::log( 'warning', sprintf( 'Migration [lifterlms]: question %d of quiz %d has unsupported type "%s" — not imported.', $ques_id, $quiz_id, $ques_type ) );
			Report::add(
				Report::GROUP_QUESTIONS,
				array_merge(
					$report,
					array(
						'reason' => self::unsupported_question_reason( $ques_type ),
						'status' => Report::STATUS_UNSUPPORTED,
					)
				)
			);
			return array();
		}

		$answers = self::format_answers( $question_type, $ques_type, $choices, (string) $question->post_title );

		if ( empty( $answers ) ) {
			Helper::log( 'warning', sprintf( 'Migration [lifterlms]: question %d of quiz %d has no answers — skipped.', $ques_id, $quiz_id ) );
			Report::add(
				Report::GROUP_QUESTIONS,
				array_merge(
					$report,
					array(
						'reason' => 'true_false' === $question_type
							? __( 'No "True" choice was found, so the correct answer could not be determined — the question was not imported (the LifterLMS question is left unchanged).', 'masterstudy-lms-learning-management-system' )
							: __( 'The question has no usable answer choices (or blanks), so it was not imported (the LifterLMS question is left unchanged).', 'masterstudy-lms-learning-management-system' ),
						'status' => Report::STATUS_FAILED,
					)
				)
			);
			return array();
		}

		$ques_copy = Target::copy_post( $ques_id, PostType::QUESTION, self::SOURCE, array( 'post_status' => 'publish' ) );
		self::hide_disabled_description( $ques_id, $ques_copy );

		$report['post_id'] = $ques_copy;

		$extra = array();

		if ( 'yes' === get_post_meta( $ques_id, '_llms_clarifications_enabled', true ) ) {
			$extra['explanation'] = (string) get_post_meta( $ques_id, '_llms_clarifications', true );
		}

		$image = get_post_meta( $ques_id, '_llms_image', true );

		if ( is_array( $image ) && ! empty( $image['id'] ) && 'no' !== ( $image['enabled'] ?? 'yes' ) ) {
			$extra['image_id'] = (int) $image['id'];
		}

		if ( 'picture_choice' === $ques_type ) {
			$extra['view_type'] = 'image';
		}

		Target::set_question( $ques_copy, $question_type, $answers, $extra );
		Target::store_unmigrated_meta( $ques_copy, 'llms_points', get_post_meta( $ques_id, '_llms_points', true ) );

		// Parts of the question that could not be carried over (reported once per question).
		$dropped = array();

		$video = trim( (string) get_post_meta( $ques_id, '_llms_video_src', true ) );

		if ( 'yes' === get_post_meta( $ques_id, '_llms_video_enabled', true ) && '' !== $video && ! self::set_question_video( $ques_copy, $video, empty( $extra['image_id'] ) ) ) {
			Target::store_unmigrated_meta( $ques_copy, 'llms_video_src', $video );
			$dropped[] = ProTarget::plus_active()
				? __( 'MasterStudy questions show either an image or a video — the question video was not imported (kept in the _migrated_llms_video_src meta)', 'masterstudy-lms-learning-management-system' )
				: __( 'question videos require MasterStudy LMS Pro Plus, which is not active — the question video was not imported (kept in the _migrated_llms_video_src meta)', 'masterstudy-lms-learning-management-system' );
		}

		if ( 'reorder_pictures' === $ques_type ) {
			$dropped[] = __( 'MasterStudy sortable questions have no images — the pictures were dropped and their attachment titles used as item text', 'masterstudy-lms-learning-management-system' );
		}

		if ( 'short_answer' === $ques_type ) {
			$dropped[] = __( 'the short answer was imported as a fill-in-the-blank question with the accepted answer as the blank', 'masterstudy-lms-learning-management-system' );
		}

		if ( in_array( $ques_type, array( 'blank', 'short_answer' ), true ) ) {
			foreach ( $choices as $choice ) {
				if ( false !== strpos( self::choice_text( $choice ), '|' ) ) {
					$dropped[] = __( 'only the first accepted spelling of each blank was kept', 'masterstudy-lms-learning-management-system' );
					break;
				}
			}
		}

		if ( ! empty( $dropped ) ) {
			Report::add(
				Report::GROUP_QUESTIONS,
				array_merge(
					$report,
					array(
						/* translators: %s: list of dropped question features */
						'reason' => sprintf( __( 'Imported with losses: %s.', 'masterstudy-lms-learning-management-system' ), implode( '; ', $dropped ) ),
						'status' => Report::STATUS_PARTIAL,
					)
				)
			);
		}

		return array( $ques_copy );
	}

	/**
	 * LifterLMS only shows the question description (post content) when `_llms_description_enabled` is on;
	 * MasterStudy always renders the content, so a disabled description is moved to `_migrated_llms_description`
	 * of the question COPY (the LifterLMS question is never modified).
	 *
	 * @param int $question_id LifterLMS question ID (read).
	 * @param int $ques_copy   MasterStudy copy of the question (written).
	 */
	private static function hide_disabled_description( int $question_id, int $ques_copy ): void {
		global $wpdb;

		$content = (string) get_post_field( 'post_content', $question_id );

		if ( '' === trim( $content ) || 'yes' === get_post_meta( $question_id, '_llms_description_enabled', true ) ) {
			return;
		}

		Target::store_unmigrated_meta( $ques_copy, 'llms_description', $content );

		if ( '' !== (string) get_post_field( 'post_content', $ques_copy ) ) {
			$wpdb->update( $wpdb->posts, array( 'post_content' => '' ), array( 'ID' => $ques_copy ) );
			clean_post_cache( $ques_copy );
		}
	}

	/**
	 * Question video (Plus addon "question_media"). MasterStudy renders either the question image or
	 * its video, so the video is only set on questions without an image.
	 *
	 * @param int    $question_id MasterStudy question copy ID.
	 * @param string $url         LifterLMS `_llms_video_src` (URL or embed HTML).
	 * @param bool   $no_image    Whether the question has no image.
	 * @return bool Whether the video was set.
	 */
	private static function set_question_video( int $question_id, string $url, bool $no_image ): bool {
		if ( ! ProTarget::plus_active() || ! $no_image ) {
			return false;
		}

		$source = Target::detect_video_source( $url );
		$map    = array(
			'youtube'  => array( 'youtube', 'question_youtube_url' ),
			'vimeo'    => array( 'vimeo', 'question_vimeo_url' ),
			'embed'    => array( 'embed', 'question_embed_ctx' ),
			'external' => array( 'ext_link', 'question_ext_link_url' ),
		);

		list( $video_type, $meta_key ) = $map[ $source ] ?? $map['external'];

		Helper::request_addon( 'question_media' );
		update_post_meta( $question_id, $meta_key, 'embed' === $source ? $url : esc_url_raw( $url ) );
		update_post_meta( $question_id, 'video_type', $video_type );
		// The course player renders the question video only when image.type is "video".
		update_post_meta(
			$question_id,
			'image',
			array(
				'id'    => 0,
				'url'   => '',
				'type'  => 'video',
				'title' => '',
			)
		);

		return true;
	}

	/**
	 * Determines the MasterStudy question type based on LifterLMS data.
	 *
	 * @param string $ques_type LifterLMS question type.
	 * @param int    $ques_id   Question ID.
	 *
	 * @return string|null The mapped MasterStudy question type, or null if unsupported.
	 */
	private static function determine_question_type( string $ques_type, int $ques_id ): ?string {
		switch ( $ques_type ) {
			case 'true_false':
				return 'true_false';
			case 'choice':
			case 'picture_choice':
				return 'yes' === get_post_meta( $ques_id, '_llms_multi_choices', true ) ? 'multi_choice' : 'single_choice';
			case 'reorder':
			case 'reorder_pictures':
				return 'sortable';
			case 'blank':
				return 'fill_the_gap';
			case 'short_answer':
				// Only an automatically graded short answer has a known correct value.
				return 'yes' === get_post_meta( $ques_id, '_llms_auto_grade', true ) && '' !== trim( (string) get_post_meta( $ques_id, '_llms_correct_value', true ) ) ? 'fill_the_gap' : null;
			case 'content':
				Helper::log( 'info', sprintf( 'Migration [lifterlms]: content-type question %d is display-only (no MasterStudy equivalent).', $ques_id ) );
				return null;
		}

		return null;
	}

	/**
	 * Formats LifterLMS choices into Target::set_question() normalized answers.
	 *
	 * @param string $type      MasterStudy type.
	 * @param string $ques_type LifterLMS type.
	 * @param array  $choices   Choices from get_choices().
	 * @param string $title     Question title (holds the blanks for "blank" questions).
	 */
	private static function format_answers( string $type, string $ques_type, array $choices, string $title ): array {
		$formatted = array();

		switch ( $type ) {
			case 'true_false':
				foreach ( $choices as $choice ) {
					if ( self::is_true_choice( $choice, $choices ) ) {
						return array( array( 'correct' => self::is_correct( $choice ) ) );
					}
				}

				return array();

			case 'sortable':
				// The correct order is the marker order (A, B, C…).
				foreach ( $choices as $choice ) {
					$text = self::choice_text( $choice );

					if ( '' !== $text ) {
						$formatted[] = array( 'text' => $text );
					}
				}

				return $formatted;

			case 'fill_the_gap':
				$blanks = array();

				foreach ( $choices as $choice ) {
					// Several accepted spellings may be separated by "|" — the first one is kept.
					$parts    = explode( '|', self::choice_text( $choice ) );
					$blanks[] = trim( $parts[0] );
				}

				$blanks = array_values( array_filter( $blanks, 'strlen' ) );

				if ( empty( $blanks ) ) {
					return array();
				}

				$text  = $title;
				$count = 0;
				$text  = preg_replace_callback(
					'/\{\{\s*blank\s*\}\}|\[blank\]/i',
					static function () use ( $blanks, &$count ) {
						$value = $blanks[ $count ] ?? '';
						++$count;
						return '|' . $value . '|';
					},
					$text
				);

				// No markers in the title: append the blanks.
				if ( 0 === $count ) {
					$text = trim( $title ) . ' |' . implode( '| |', $blanks ) . '|';
				}

				return array( array( 'text' => $text ) );

			default:
				foreach ( $choices as $choice ) {
					$text     = self::choice_text( $choice );
					$image_id = self::choice_image_id( $choice );

					if ( '' === $text && ! $image_id ) {
						continue;
					}

					$formatted[] = array(
						'text'     => $text,
						'correct'  => self::is_correct( $choice ),
						'image_id' => $image_id,
					);
				}

				return $formatted;
		}
	}

	/**
	 * Count total source items for a given migration step. Fast COUNT query — no records loaded.
	 *
	 * Copy mode: source records are never modified or removed, so every step counts ALL its source items.
	 *
	 * @param string $step Step name.
	 * @return int
	 */
	public static function count_source_items( string $step ): int {
		global $wpdb;

		$user_postmeta = $wpdb->prefix . 'lifterlms_user_postmeta';
		$has_upm       = self::table_exists( $user_postmeta );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		switch ( $step ) {
			case 'users':
				$capabilities_key = $wpdb->get_blog_prefix() . 'capabilities';
				// Deleted users can still have LifterLMS rows: only existing users get a role (their rows are reported later).
				$union            = $has_upm ? "UNION SELECT DISTINCT upm.user_id FROM {$user_postmeta} upm INNER JOIN {$wpdb->users} eu ON eu.ID = upm.user_id WHERE upm.meta_key = '_status'" : '';

				return (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM (
							SELECT DISTINCT u.ID
							FROM {$wpdb->users} u
							INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
							WHERE um.meta_key = %s
							  AND ( um.meta_value LIKE %s OR um.meta_value LIKE %s OR um.meta_value LIKE %s )
							{$union}
						) AS llms_users",
						$capabilities_key,
						'%"instructor"%',
						'%"lms_manager"%',
						'%"instructors_assistant"%'
					)
				);

			case 'courses':
				return (int) $wpdb->get_var(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'course'" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				);

			case 'enrollments':
				if ( ! $has_upm ) {
					return 0;
				}

				// Groups add-on memberships (`_status` rows on llms_group posts) are imported by finalize_step( 'enrollments' ).
				// Only the latest `_status` row of a user + course counts (older rows are the status history).
				return (int) $wpdb->get_var(
					"SELECT COUNT(*) FROM {$user_postmeta} upm
					 LEFT JOIN {$wpdb->posts} g ON g.ID = upm.post_id AND g.post_type = 'llms_group'
					 WHERE upm.meta_key = '_status' AND g.ID IS NULL AND " . self::latest_status_sql( 'upm' )
				);

			case 'lesson_progress':
				if ( ! $has_upm ) {
					return 0;
				}

				// LifterLMS stores _is_complete only for lessons (plus sections/courses, ignored), never for quizzes.
				return (int) $wpdb->get_var(
					"SELECT COUNT(*) FROM {$user_postmeta} upm
					 INNER JOIN {$wpdb->posts} p ON p.ID = upm.post_id
					 WHERE upm.meta_key = '_is_complete' AND p.post_type = 'lesson'"
				);

			case 'orders':
				return (int) $wpdb->get_var(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'llms_order'" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				);

			case 'reviews':
				// Reviews of courses that were not copied stay in LifterLMS only (reported by finalize_step( 'courses' )).
				return (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->posts} r
						 INNER JOIN {$wpdb->postmeta} i ON i.meta_key = %s AND i.meta_value = CONCAT( %s, r.post_parent )
						 INNER JOIN {$wpdb->postmeta} s ON s.post_id = i.post_id AND s.meta_key = %s AND s.meta_value = %s
						 INNER JOIN {$wpdb->posts} c ON c.ID = i.post_id AND c.post_type = %s
						 WHERE r.post_type = 'llms_review'",
						Target::SOURCE_ID_META,
						Target::COPY_KEY_PREFIX,
						Target::SOURCE_META,
						self::SOURCE,
						PostType::COURSE
					)
				);

			case 'quiz_attempts':
				$attempts_table = $wpdb->prefix . 'lifterlms_quiz_attempts';

				if ( ! self::table_exists( $attempts_table ) ) {
					return 0;
				}

				return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$attempts_table}" );

			case 'assignments':
				// One item per copied assignment that has LifterLMS submissions (Pro only).
				$submissions = $wpdb->prefix . self::SUBMISSIONS_TABLE;

				if ( ! ProTarget::pro_active() || ! self::table_exists( $submissions ) ) {
					return 0;
				}

				return (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(DISTINCT x.assignment_id) FROM {$submissions} x
						 INNER JOIN {$wpdb->postmeta} i ON i.meta_key = %s AND i.meta_value = CONCAT( %s, x.assignment_id )
						 INNER JOIN {$wpdb->postmeta} s ON s.post_id = i.post_id AND s.meta_key = %s AND s.meta_value = %s
						 INNER JOIN {$wpdb->posts} p ON p.ID = i.post_id AND p.post_type = %s",
						Target::SOURCE_ID_META,
						Target::COPY_KEY_PREFIX,
						Target::SOURCE_META,
						self::SOURCE,
						PostType::ASSIGNMENT
					)
				);
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return 0;
	}

	/**
	 * Return paginated source IDs for a given migration step.
	 *
	 * Copy mode: every step is cursor based (`id > $cursor ORDER BY id`) over ALL source items — source records are
	 * never modified or removed, and every item migration is idempotent (copies and created records are reused).
	 *
	 * @param string $step    Step name.
	 * @param int    $limit   Batch size.
	 * @param int    $cursor  Last processed ID (0 = first batch).
	 * @param int[]  $exclude Failed IDs (unused: the cursor already moves past them).
	 * @return int[]
	 */
	public static function get_source_ids( string $step, int $limit, int $cursor, array $exclude = array() ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		global $wpdb;

		$user_postmeta = $wpdb->prefix . 'lifterlms_user_postmeta';
		$has_upm       = self::table_exists( $user_postmeta );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		switch ( $step ) {
			case 'users':
				$capabilities_key = $wpdb->get_blog_prefix() . 'capabilities';
				// Deleted users can still have LifterLMS rows: only existing users get a role (their rows are reported later).
				$union            = $has_upm ? "UNION SELECT DISTINCT upm.user_id FROM {$user_postmeta} upm INNER JOIN {$wpdb->users} eu ON eu.ID = upm.user_id WHERE upm.meta_key = '_status'" : '';

				$ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT user_id FROM (
							SELECT DISTINCT u.ID AS user_id
							FROM {$wpdb->users} u
							INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
							WHERE um.meta_key = %s
							  AND ( um.meta_value LIKE %s OR um.meta_value LIKE %s OR um.meta_value LIKE %s )
							{$union}
						) AS llms_users
						WHERE user_id > %d
						ORDER BY user_id ASC
						LIMIT %d",
						$capabilities_key,
						'%"instructor"%',
						'%"lms_manager"%',
						'%"instructors_assistant"%',
						$cursor,
						$limit
					)
				);
				break;

			case 'courses':
				$ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'course' AND ID > %d ORDER BY ID ASC LIMIT %d",
						$cursor,
						$limit
					)
				);
				break;

			case 'enrollments':
				if ( ! $has_upm ) {
					return array();
				}

				$ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT upm.meta_id FROM {$user_postmeta} upm
						 LEFT JOIN {$wpdb->posts} g ON g.ID = upm.post_id AND g.post_type = 'llms_group'
						 WHERE upm.meta_key = '_status' AND g.ID IS NULL AND upm.meta_id > %d
						   AND " . self::latest_status_sql( 'upm' ) . '
						 ORDER BY upm.meta_id ASC
						 LIMIT %d',
						$cursor,
						$limit
					)
				);
				break;

			case 'lesson_progress':
				if ( ! $has_upm ) {
					return array();
				}

				$ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT upm.meta_id FROM {$user_postmeta} upm
						 INNER JOIN {$wpdb->posts} p ON p.ID = upm.post_id
						 WHERE upm.meta_key = '_is_complete' AND p.post_type = 'lesson' AND upm.meta_id > %d
						 ORDER BY upm.meta_id ASC
						 LIMIT %d",
						$cursor,
						$limit
					)
				);
				break;

			case 'orders':
				$ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'llms_order' AND ID > %d ORDER BY ID ASC LIMIT %d",
						$cursor,
						$limit
					)
				);
				break;

			case 'reviews':
				$ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT r.ID FROM {$wpdb->posts} r
						 INNER JOIN {$wpdb->postmeta} i ON i.meta_key = %s AND i.meta_value = CONCAT( %s, r.post_parent )
						 INNER JOIN {$wpdb->postmeta} s ON s.post_id = i.post_id AND s.meta_key = %s AND s.meta_value = %s
						 INNER JOIN {$wpdb->posts} c ON c.ID = i.post_id AND c.post_type = %s
						 WHERE r.post_type = 'llms_review' AND r.ID > %d
						 ORDER BY r.ID ASC
						 LIMIT %d",
						Target::SOURCE_ID_META,
						Target::COPY_KEY_PREFIX,
						Target::SOURCE_META,
						self::SOURCE,
						PostType::COURSE,
						$cursor,
						$limit
					)
				);
				break;

			case 'quiz_attempts':
				// Already imported attempts are skipped by migrate_single_quiz_attempt() (Target::quiz_attempt_exists()).
				$attempts_table = $wpdb->prefix . 'lifterlms_quiz_attempts';

				if ( ! self::table_exists( $attempts_table ) ) {
					return array();
				}

				$ids = $wpdb->get_col(
					$wpdb->prepare( "SELECT id FROM {$attempts_table} WHERE id > %d ORDER BY id ASC LIMIT %d", $cursor, $limit )
				);
				break;

			case 'assignments':
				// Source assignment IDs with submissions whose assignment was copied (submissions are imported idempotently).
				$submissions = $wpdb->prefix . self::SUBMISSIONS_TABLE;

				if ( ! ProTarget::pro_active() || ! self::table_exists( $submissions ) ) {
					return array();
				}

				$ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT DISTINCT x.assignment_id FROM {$submissions} x
						 INNER JOIN {$wpdb->postmeta} i ON i.meta_key = %s AND i.meta_value = CONCAT( %s, x.assignment_id )
						 INNER JOIN {$wpdb->postmeta} s ON s.post_id = i.post_id AND s.meta_key = %s AND s.meta_value = %s
						 INNER JOIN {$wpdb->posts} p ON p.ID = i.post_id AND p.post_type = %s
						 WHERE x.assignment_id > %d
						 ORDER BY x.assignment_id ASC
						 LIMIT %d",
						Target::SOURCE_ID_META,
						Target::COPY_KEY_PREFIX,
						Target::SOURCE_META,
						self::SOURCE,
						PostType::ASSIGNMENT,
						$cursor,
						$limit
					)
				);
				break;

			default:
				$ids = array();
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array_map( 'intval', $ids ? $ids : array() );
	}

	/**
	 * Dispatch a single-item migration to the appropriate migrate_single_*() method.
	 *
	 * Called by MigrationProcessJob inside a SAVEPOINT wrapper.
	 * Must be idempotent — safe to call twice for the same (step, item_id) pair.
	 *
	 * @param string $step    Step name matching a key in LifterLMSMigrator::get_steps().
	 * @param int    $item_id Source item ID.
	 * @throws \Exception Triggers ROLLBACK in the job engine; item is added to the failed list.
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
			case 'quiz_attempts':
				static::migrate_single_quiz_attempt( $item_id );
				break;
			case 'assignments':
				static::migrate_single_assignment_submissions( $item_id );
				break;
		}
	}

	/**
	 * Assign MasterStudy roles to a single LifterLMS user.
	 *
	 * Gives LifterLMS instructors / LMS managers / instructor's assistants the MasterStudy instructor role
	 * (administrators keep their role). Copy mode: the LifterLMS roles are kept, so LifterLMS keeps working.
	 * MasterStudy students have no dedicated role — a user without any role gets the default role so they can
	 * still log in.
	 *
	 * Idempotent: the MasterStudy role is only added once; safe to call twice.
	 *
	 * @param int $user_id WP user ID.
	 * @throws \Exception If the WP user record does not exist.
	 */
	public static function migrate_single_user( int $user_id ): void {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			throw new \Exception(
				/* translators: %d: user ID */
				sprintf( __( 'WordPress user #%d does not exist (it may have been deleted), so no MasterStudy role could be assigned.', 'masterstudy-lms-learning-management-system' ), $user_id ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		$llms_instructor_roles = array( 'instructor', 'lms_manager', 'instructors_assistant' );
		$roles                 = (array) $user->roles;
		$caps                  = is_array( $user->caps ) ? $user->caps : array();
		$user_roles            = array_merge( $roles, array_keys( $caps ) );

		$has_llms_instructor = ! empty( array_intersect( $llms_instructor_roles, $user_roles ) );

		if ( $has_llms_instructor ) {
			$assistant_only = in_array( 'instructors_assistant', $user_roles, true ) && empty( array_intersect( array( 'instructor', 'lms_manager', 'administrator' ), $user_roles ) );

			// make_instructor() leaves administrators untouched; the LifterLMS roles are kept (copy mode).
			Target::make_instructor( $user_id );

			// Instructor's assistants edit the courses they are assigned to; MasterStudy co-instructors need the
			// instructor role, which also lets them create their own courses.
			if ( $assistant_only ) {
				Report::add(
					Report::GROUP_USERS,
					array(
						'source_id' => $user_id,
						'title'     => self::user_label( $user_id ),
						'type'      => __( 'Instructor\'s assistant', 'masterstudy-lms-learning-management-system' ),
						'reason'    => __( 'MasterStudy has no instructor\'s assistant role — the user was given the MasterStudy instructor role (needed to co-teach the assigned courses), which also allows creating own courses.', 'masterstudy-lms-learning-management-system' ),
						'status'    => Report::STATUS_PARTIAL,
					)
				);
			}
		}

		// Any user left without a role (e.g. enrolled students) keeps a login-capable role.
		Target::ensure_student( $user_id );
	}

	/**
	 * Copy a single LifterLMS course into a NEW MasterStudy stm-courses post (with its curriculum).
	 *
	 * Idempotent: an existing copy is reused and its curriculum / settings are rebuilt.
	 *
	 * @param int $course_id LifterLMS course post ID.
	 * @throws \Exception If the post does not exist or is not a LifterLMS course.
	 */
	public static function migrate_single_course( int $course_id ): void {
		$post = get_post( $course_id );

		if ( ! $post ) {
			throw new \Exception(
				/* translators: %d: course ID */
				sprintf( __( 'LifterLMS course #%d does not exist (it may have been deleted during the migration).', 'masterstudy-lms-learning-management-system' ), $course_id ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		if ( 'course' !== $post->post_type ) {
			throw new \Exception(
				/* translators: 1: post ID, 2: post type */
				sprintf( __( 'Post #%1$d is not a LifterLMS course (its post type is "%2$s"), so it was not migrated.', 'masterstudy-lms-learning-management-system' ), $course_id, $post->post_type ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		$copy_id = Target::copy_post( $course_id, PostType::COURSE, self::SOURCE );

		static::migrate_course( $course_id, $copy_id );
		static::migrate_course_info( $course_id, $copy_id );

		// Course author becomes a MasterStudy instructor.
		if ( $post->post_author ) {
			Target::make_instructor( (int) $post->post_author );
		}

		$instructors = get_post_meta( $course_id, '_llms_instructors', true );
		Target::store_unmigrated_meta( $copy_id, 'llms_instructors', $instructors );

		// The course author is the MasterStudy instructor; MasterStudy Pro keeps ONE co-instructor.
		$co_instructors = array();

		foreach ( is_array( $instructors ) ? $instructors : array() as $instructor ) {
			$instructor_id = is_array( $instructor ) ? (int) ( $instructor['id'] ?? 0 ) : (int) $instructor;

			if ( $instructor_id && $instructor_id !== (int) $post->post_author && get_userdata( $instructor_id ) ) {
				$co_instructors[ $instructor_id ] = $instructor_id;
			}
		}

		$co_instructors = array_values( $co_instructors );

		if ( ! empty( $co_instructors ) && ProTarget::pro_active() ) {
			ProTarget::set_co_instructor( $copy_id, $co_instructors[0] );

			if ( count( $co_instructors ) > 1 ) {
				self::report_course(
					$course_id,
					__( 'Course co-instructors', 'masterstudy-lms-learning-management-system' ),
					/* translators: 1: co-instructor name, 2: number of instructors not assigned */
					sprintf( __( 'MasterStudy courses have one co-instructor — %1$s was assigned; %2$d additional LifterLMS instructor(s) were not assigned (kept in the _migrated_llms_instructors meta).', 'masterstudy-lms-learning-management-system' ), self::user_label( $co_instructors[0] ), count( $co_instructors ) - 1 )
				);
			}
		} elseif ( ! empty( $co_instructors ) ) {
			self::report_course(
				$course_id,
				__( 'Course co-instructors', 'masterstudy-lms-learning-management-system' ),
				/* translators: %d: number of co-instructors */
				sprintf( __( 'Co-instructors require MasterStudy LMS Pro, which is not active — only the course author was set as the instructor; %d additional LifterLMS instructor(s) were not assigned (kept in the _migrated_llms_instructors meta).', 'masterstudy-lms-learning-management-system' ), count( $co_instructors ) )
			);
		}

		// Terms are read from the LifterLMS course and assigned to the copy.
		Target::migrate_categories( $copy_id, 'course_cat', array(), $course_id );
		self::sync_category_parents( $course_id );

		// Difficulty → MasterStudy course level (resolved by label).
		$difficulty = self::term_names( $course_id, 'course_difficulty' );
		if ( ! empty( $difficulty ) ) {
			Target::set_level( $copy_id, (string) reset( $difficulty ) );
		}

		// MasterStudy has no course tag / track taxonomy.
		$tags   = self::term_names( $course_id, 'course_tag' );
		$tracks = self::term_names( $course_id, 'course_track' );
		Target::store_unmigrated_meta( $copy_id, 'llms_course_tags', $tags );
		Target::store_unmigrated_meta( $copy_id, 'llms_course_tracks', $tracks );

		if ( ! empty( $tags ) || ! empty( $tracks ) ) {
			self::report_course(
				$course_id,
				__( 'Course tags / tracks', 'masterstudy-lms-learning-management-system' ),
				/* translators: %s: comma-separated term names */
				sprintf( __( 'MasterStudy has no course tag or track taxonomy — these terms were not assigned: %s (kept in the _migrated_llms_course_tags / _migrated_llms_course_tracks meta).', 'masterstudy-lms-learning-management-system' ), implode( ', ', array_merge( $tags, $tracks ) ) )
			);
		}

		// Migrate course prerequisites. LifterLMS requires the prerequisite course (or every course of the
		// prerequisite track) to be completed; they are mapped to the copies of those courses.
		if ( 'yes' === get_post_meta( $course_id, '_llms_has_prerequisite', true ) ) {
			$prereq_ids   = array( (int) get_post_meta( $course_id, '_llms_prerequisite', true ) );
			$prereq_track = (int) get_post_meta( $course_id, '_llms_prerequisite_track', true );

			Target::store_unmigrated_meta( $copy_id, 'llms_prerequisite_track', $prereq_track );

			if ( $prereq_track > 0 ) {
				$prereq_ids = array_merge( $prereq_ids, self::track_course_ids( $prereq_track ) );
			}

			self::set_prerequisites( $course_id, $copy_id, array_diff( $prereq_ids, array( $course_id ) ) );
		}

		self::migrate_course_certificate( $course_id, $copy_id );
	}

	/**
	 * LifterLMS courses of a course track (`course_track` term).
	 *
	 * @param int $term_id Track term ID.
	 * @return int[]
	 */
	private static function track_course_ids( int $term_id ): array {
		global $wpdb;

		return array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT p.ID FROM {$wpdb->posts} p
					 INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
					 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
					 WHERE tt.term_id = %d AND tt.taxonomy = 'course_track' AND p.post_type = 'course'
					 ORDER BY p.ID ASC",
					$term_id
				)
			)
		);
	}

	/**
	 * Certificate of a course: the LifterLMS certificate template awarded on "course completed"
	 * (engagement) becomes an approximate MasterStudy certificate (Pro "certificate_builder" addon),
	 * created once per template. Courses that only have earned certificates (template deleted) get
	 * the site default certificate.
	 *
	 * @param int $course_id LifterLMS course ID (read).
	 * @param int $copy_id   MasterStudy copy of the course (written).
	 */
	private static function migrate_course_certificate( int $course_id, int $copy_id ): void {
		global $wpdb;

		$templates = self::course_certificate_templates( $course_id );
		$earned    = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} cert ON cert.ID = pm.post_id AND cert.post_type = 'llms_my_certificate'
				 WHERE pm.meta_key = '_llms_related' AND pm.meta_value = %d",
				$course_id
			)
		);

		if ( empty( $templates ) && ! $earned ) {
			return;
		}

		if ( ! ProTarget::pro_active() ) {
			Helper::log( 'warning', sprintf( 'Migration [lifterlms]: course %d has a LifterLMS certificate — certificates require MasterStudy LMS Pro and were not assigned.', $course_id ) );
			self::report_course(
				$course_id,
				__( 'Certificate', 'masterstudy-lms-learning-management-system' ),
				__( 'Certificates require MasterStudy LMS Pro, which is not active — no certificate was assigned to the course.', 'masterstudy-lms-learning-management-system' )
			);
			return;
		}

		if ( empty( $templates ) ) {
			Target::assign_certificate( $copy_id );
			self::report_course(
				$course_id,
				__( 'Certificate design', 'masterstudy-lms-learning-management-system' ),
				__( 'The LifterLMS certificate template of the course no longer exists — the site default MasterStudy certificate was assigned instead.', 'masterstudy-lms-learning-management-system' )
			);
			return;
		}

		$template_id = (int) $templates[0];
		$background  = (int) get_post_meta( $template_id, '_llms_certificate_image', true );
		$background  = $background ? $background : (int) get_post_thumbnail_id( $template_id );
		$orientation = (string) get_post_meta( $template_id, '_llms_certificate_orientation', true );

		// Legacy (v1) templates have no orientation: derive it from the background image.
		if ( ! in_array( $orientation, array( 'landscape', 'portrait' ), true ) ) {
			$image       = $background ? wp_get_attachment_metadata( $background ) : array();
			$orientation = ! empty( $image['width'] ) && ! empty( $image['height'] ) && (int) $image['height'] > (int) $image['width'] ? 'portrait' : 'landscape';
		}

		$heading = trim( wp_strip_all_tags( (string) get_post_meta( $template_id, '_llms_certificate_title', true ) ) );
		$data    = array(
			'source_id'     => (string) $template_id,
			'title'         => get_post_field( 'post_title', $template_id ),
			'background_id' => $background,
			'orientation'   => $orientation,
		);

		if ( '' !== $heading ) {
			$data['heading'] = $heading;
		}

		ProTarget::set_course_certificate( $copy_id, ProTarget::create_certificate( $data, self::SOURCE ) );

		$reason = __( 'LifterLMS certificate designs cannot be converted exactly — a MasterStudy certificate was created from the template (background image, title, student name, course, date and instructor); review its layout in the certificate builder.', 'masterstudy-lms-learning-management-system' );

		if ( count( $templates ) > 1 ) {
			/* translators: %d: number of certificate templates */
			$reason .= ' ' . sprintf( __( 'The course awarded %d certificates on completion; MasterStudy awards one, so only the first was used.', 'masterstudy-lms-learning-management-system' ), count( $templates ) );
		}

		self::report_course( $course_id, __( 'Certificate design', 'masterstudy-lms-learning-management-system' ), $reason );
	}

	/**
	 * LifterLMS certificate templates awarded when the course is completed (published `llms_engagement`
	 * posts: type "certificate", trigger "course_completed" for this course, or for any course).
	 *
	 * @param int $course_id Course ID.
	 * @return int[] `llms_certificate` template IDs, course-specific first.
	 */
	private static function course_certificate_templates( int $course_id ): array {
		global $wpdb;

		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tpl.ID FROM {$wpdb->posts} e
				 INNER JOIN {$wpdb->postmeta} et ON et.post_id = e.ID AND et.meta_key = '_llms_engagement_type' AND et.meta_value = 'certificate'
				 INNER JOIN {$wpdb->postmeta} tt ON tt.post_id = e.ID AND tt.meta_key = '_llms_trigger_type' AND tt.meta_value = 'course_completed'
				 INNER JOIN {$wpdb->postmeta} en ON en.post_id = e.ID AND en.meta_key = '_llms_engagement'
				 INNER JOIN {$wpdb->posts} tpl ON tpl.ID = CAST(en.meta_value AS UNSIGNED) AND tpl.post_type = 'llms_certificate' AND tpl.post_status = 'publish'
				 LEFT JOIN {$wpdb->postmeta} tp ON tp.post_id = e.ID AND tp.meta_key = '_llms_engagement_trigger_post'
				 WHERE e.post_type = 'llms_engagement' AND e.post_status = 'publish'
				   AND ( tp.meta_value = %d OR tp.meta_value IS NULL OR tp.meta_value IN ('', '0') )
				 ORDER BY ( tp.meta_value = %d ) DESC, e.ID ASC",
				$course_id,
				$course_id
			)
		);

		return array_values( array_unique( array_map( 'intval', wp_list_pluck( $rows, 'ID' ) ) ) );
	}

	/**
	 * Migrate a single LifterLMS enrollment record to a MasterStudy user_courses row of the course copy.
	 *
	 * Operates on one lifterlms_user_postmeta row by its meta_id primary key; the row is only read.
	 *
	 * Only the LATEST `_status` row of a user + course/membership decides (LifterLMS keeps the status history as
	 * separate rows); older rows are skipped. MasterStudy has no inactive enrollment: only 'enrolled' rows create an
	 * enrollment; expired/cancelled/unenrolled statuses are kept in user meta and not enrolled. A course the user
	 * completed in LifterLMS keeps its completion date (progress 100%). Membership enrollments are
	 * reported and kept in user meta. MasterStudy stores no order link on enrollments (Masteriyo's
	 * _order_id/_price itemmeta is dropped).
	 *
	 * @param int $meta_id Primary key of the lifterlms_user_postmeta row (meta_key = '_status').
	 * @throws \Exception If the row does not exist, the course was not copied, or the DB insert fails.
	 */
	public static function migrate_single_enrollment( int $meta_id ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'lifterlms_user_postmeta';

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT user_id, post_id, meta_value, updated_date
				 FROM {$table}
				 WHERE meta_id = %d AND meta_key = '_status'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$meta_id
			)
		);

		if ( ! $row ) {
			throw new \Exception(
				/* translators: %d: LifterLMS user postmeta row ID */
				sprintf( __( 'LifterLMS enrollment record #%d no longer exists.', 'masterstudy-lms-learning-management-system' ), $meta_id ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		$user_id   = (int) $row->user_id;
		$course_id = (int) $row->post_id;
		$post_type = get_post_type( $course_id );

		// Status history: LLMS_Student::insert_status_postmeta() adds a row per change and LifterLMS reads the latest one
		// (ORDER BY updated_date DESC, meta_id DESC) — an older row ("enrolled" before "cancelled") must not enroll.
		if ( self::newer_status_exists( $meta_id, $user_id, $course_id, (string) $row->updated_date ) ) {
			return;
		}

		if ( ! $post_type || ! get_userdata( $user_id ) ) {
			Helper::log( 'warning', sprintf( 'Migration [lifterlms]: enrollment row %d references a missing user %d / post %d — skipped.', $meta_id, $user_id, $course_id ) );
			Report::add(
				Report::GROUP_ENROLLMENTS,
				array(
					'source_id' => $meta_id,
					'title'     => self::enrollment_label( $user_id, $course_id ),
					'type'      => __( 'Orphaned enrollment', 'masterstudy-lms-learning-management-system' ),
					'reason'    => $post_type
						? __( 'The enrolled user no longer exists, so the enrollment was skipped.', 'masterstudy-lms-learning-management-system' )
						: __( 'The enrolled course or membership no longer exists, so the enrollment was skipped.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_FAILED,
					'course'    => $post_type ? get_post_field( 'post_title', $course_id ) : '',
					'post_id'   => $post_type ? self::link_id( $course_id ) : 0,
				)
			);
			return;
		}

		// Membership enrollments (llms_membership): MasterStudy has no LifterLMS memberships. The courses a
		// membership auto-enrolls into have their own course enrollment rows, which are migrated normally.
		if ( 'llms_membership' === $post_type ) {
			self::store_membership_enrollment( $user_id, $course_id, (string) $row->meta_value, (string) $row->updated_date );
			return;
		}

		$copy_id = 'course' === $post_type ? Target::copy_of( self::SOURCE, $course_id ) : 0;

		// Courses that were not copied (their migration failed) have no MasterStudy course to enroll in.
		if ( ! $copy_id || PostType::COURSE !== get_post_type( $copy_id ) ) {
			$message = sprintf(
				/* translators: 1: user label, 2: post title, 3: post type, 4: LifterLMS enrollment row ID */
				__( 'Enrollment of %1$s in "%2$s" was not migrated because the course itself was not migrated (post type "%3$s"; LifterLMS record #%4$d).', 'masterstudy-lms-learning-management-system' ),
				self::user_label( $user_id ),
				get_post_field( 'post_title', $course_id ),
				$post_type,
				$meta_id
			);

			throw new \Exception( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		if ( Target::is_enrolled( $user_id, $copy_id ) ) {
			return;
		}

		// Older LifterLMS versions stored capitalized statuses ("Enrolled").
		if ( 'enrolled' === strtolower( trim( (string) $row->meta_value ) ) ) {
			// A course completed in LifterLMS (`_is_complete` = yes on the course) keeps its completion date,
			// which makes Target::recalculate_progress() force the progress to 100%.
			$completed_at = self::course_completion_time( $user_id, $course_id );

			Target::enroll( $user_id, $copy_id, self::enrollment_start_time( $user_id, $course_id, (string) $row->updated_date ), $completed_at > 0 ? $completed_at : null );
		} else {
			self::store_inactive_enrollment( $user_id, $course_id, (string) $row->meta_value, (string) $row->updated_date );
		}
	}

	/**
	 * Migrate a single lifterlms_user_postmeta lesson completion row to stm_lms_user_lessons (lesson copy).
	 *
	 * The source row is only read. Quiz completion is not derived from lesson rows (Masteriyo's lesson → quiz
	 * activity mapping): lessons with a quiz are migrated as lessons, and quiz completion comes from the
	 * quiz_attempts step (a passed stm_lms_user_quizzes row).
	 *
	 * @param int $meta_id Primary key of the lifterlms_user_postmeta row (meta_key = '_is_complete').
	 * @throws \Exception If the row does not exist.
	 */
	public static function migrate_single_lesson_progress( int $meta_id ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'lifterlms_user_postmeta';

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT user_id, post_id, meta_value, updated_date
				 FROM {$table}
				 WHERE meta_id = %d AND meta_key = '_is_complete'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$meta_id
			)
		);

		if ( ! $row ) {
			throw new \Exception(
				/* translators: %d: LifterLMS user postmeta row ID */
				sprintf( __( 'LifterLMS lesson progress record #%d no longer exists.', 'masterstudy-lms-learning-management-system' ), $meta_id ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		$user_id     = (int) $row->user_id;
		$lesson_id   = (int) $row->post_id;
		$completed   = ( 'yes' === $row->meta_value );
		$lesson_copy = Target::copy_of( self::SOURCE, $lesson_id );
		$course_id   = $lesson_copy ? self::resolve_course_id( $lesson_copy, Target::copy_of( self::SOURCE, (int) get_post_meta( $lesson_id, '_llms_parent_course', true ) ) ) : 0;

		if ( ! get_userdata( $user_id ) ) {
			if ( $completed ) {
				Report::add(
					Report::GROUP_PROGRESS,
					array(
						'source_id' => $meta_id,
						/* translators: 1: user label, 2: lesson title */
						'title'     => sprintf( __( '%1$s → %2$s', 'masterstudy-lms-learning-management-system' ), self::user_label( $user_id ), get_post_field( 'post_title', $lesson_id ) ),
						'type'      => __( 'Lesson completion', 'masterstudy-lms-learning-management-system' ),
						'reason'    => __( 'The student no longer exists, so the lesson completion was skipped.', 'masterstudy-lms-learning-management-system' ),
						'status'    => Report::STATUS_FAILED,
						'post_id'   => self::link_id( $lesson_id ),
					)
				);
			}

			return;
		}

		if ( ! $lesson_copy || PostType::LESSON !== get_post_type( $lesson_copy ) || ! $course_id ) {
			Helper::log( 'warning', sprintf( 'Migration [lifterlms]: No migrated course found for lesson %d — skipping progress row %d.', $lesson_id, $meta_id ) );

			// Only completed lessons carry progress worth reporting.
			if ( $completed ) {
				Report::add(
					Report::GROUP_PROGRESS,
					array(
						'source_id' => $meta_id,
						/* translators: 1: user label, 2: lesson title */
						'title'     => sprintf( __( '%1$s → %2$s', 'masterstudy-lms-learning-management-system' ), self::user_label( $user_id ), get_post_field( 'post_title', $lesson_id ) ),
						'type'      => __( 'Lesson completion', 'masterstudy-lms-learning-management-system' ),
						'reason'    => __( 'The lesson is not part of any migrated MasterStudy course, so its completion was skipped.', 'masterstudy-lms-learning-management-system' ),
						'status'    => Report::STATUS_FAILED,
						'post_id'   => self::link_id( $lesson_id ),
					)
				);
			}

			return;
		}

		// MasterStudy only records completed lessons (a row means "completed").
		if ( $completed ) {
			$time = self::to_timestamp( (string) $row->updated_date );
			Target::complete_lesson( $user_id, $course_id, $lesson_copy, $time, $time );
		}
	}

	/**
	 * Copy a single LifterLMS order into a NEW MasterStudy stm-orders post (Target::save_order()).
	 *
	 * Idempotent: an existing copy is reused and its meta / order items are rebuilt.
	 *
	 * @param int $order_id LifterLMS llms_order post ID.
	 * @throws \Exception If the post does not exist or is not an llms_order post.
	 */
	public static function migrate_single_order( int $order_id ): void {
		$post = get_post( $order_id );

		if ( ! $post ) {
			throw new \Exception(
				/* translators: %d: order ID */
				sprintf( __( 'LifterLMS order #%d does not exist (it may have been deleted during the migration).', 'masterstudy-lms-learning-management-system' ), $order_id ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		if ( 'llms_order' !== $post->post_type ) {
			throw new \Exception(
				/* translators: 1: post ID, 2: post type */
				sprintf( __( 'Post #%1$d is not a LifterLMS order (its post type is "%2$s"), so it was not migrated.', 'masterstudy-lms-learning-management-system' ), $order_id, $post->post_type ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		static::migrate_order( $order_id, $post );
	}

	/**
	 * Migrate a single LifterLMS review (llms_review CPT) to a NEW MasterStudy stm-reviews post of the course copy.
	 *
	 * The LifterLMS review is never modified (idempotent per review ID). LifterLMS reviews may carry no star
	 * rating; MasterStudy requires a 1-5 mark, so a missing rating defaults to 5 (logged).
	 *
	 * @param int $review_id LifterLMS llms_review post ID.
	 * @throws \Exception If the post does not exist or is not an llms_review post.
	 */
	public static function migrate_single_review( int $review_id ): void {
		$llms_review = get_post( $review_id );

		if ( ! $llms_review || 'llms_review' !== $llms_review->post_type ) {
			throw new \Exception(
				/* translators: %d: review ID */
				sprintf( __( 'LifterLMS review #%d does not exist or is not a LifterLMS review.', 'masterstudy-lms-learning-management-system' ), $review_id ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		$course_id = (int) $llms_review->post_parent;
		$copy_id   = Target::copy_of( self::SOURCE, $course_id );
		$user_id   = (int) $llms_review->post_author;

		if ( ! $copy_id || PostType::COURSE !== get_post_type( $copy_id ) ) {
			throw new \Exception(
				/* translators: %d: course ID */
				sprintf( __( 'The reviewed LifterLMS course #%d was not migrated, so the review was not imported.', 'masterstudy-lms-learning-management-system' ), $course_id ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}
		$rating    = self::review_rating( $review_id );
		$no_rating = $rating <= 0;

		if ( $no_rating ) {
			$rating = 5;
			Helper::log( 'info', sprintf( 'Migration [lifterlms]: review %d has no rating — migrated with 5 stars.', $review_id ) );
		}

		$review = array(
			'course_id' => $copy_id,
			'user_id'   => $user_id,
			'mark'      => $rating,
			'content'   => $llms_review->post_content,
			'date'      => $llms_review->post_date,
			'approved'  => 'publish' === $llms_review->post_status,
			'source_id' => (string) $review_id,
		);

		// A trashed LifterLMS review must not become visible or wait for moderation: it stays in the trash.
		if ( 'trash' === $llms_review->post_status ) {
			$review['post_status'] = 'trash';
		}

		$new_review_id = Target::add_review( $review, self::SOURCE );

		Target::store_unmigrated_meta( $new_review_id, 'review_title', sanitize_text_field( $llms_review->post_title ) );

		if ( ! get_userdata( $user_id ) ) {
			Report::add(
				Report::GROUP_REVIEWS,
				array(
					'source_id' => $review_id,
					'title'     => $llms_review->post_title,
					'type'      => __( 'Review of a deleted user', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'The reviewer no longer exists — the review was imported without an author name.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_PARTIAL,
					'course'    => get_post_field( 'post_title', $course_id ),
					'post_id'   => $new_review_id,
				)
			);
		}

		if ( $no_rating ) {
			Report::add(
				Report::GROUP_REVIEWS,
				array(
					'source_id' => $review_id,
					'title'     => $llms_review->post_title,
					'type'      => __( 'Review without rating', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'MasterStudy reviews require a 1–5 star rating — the LifterLMS review had none and was imported with 5 stars.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_PARTIAL,
					'course'    => get_post_field( 'post_title', $course_id ),
					'post_id'   => $new_review_id,
				)
			);
		}

	}

	/**
	 * Star rating of a LifterLMS review. Core reviews are text only; rating add-ons / customizations store it in
	 * `_llms_rating` or `rating` (the key Masteriyo reads). Clamped to 1-5, 0 = no rating.
	 *
	 * @param int $review_id llms_review post ID.
	 */
	private static function review_rating( int $review_id ): float {
		foreach ( array( '_llms_rating', 'rating' ) as $key ) {
			$value = get_post_meta( $review_id, $key, true );

			if ( is_numeric( $value ) && (float) $value > 0 ) {
				return min( 5.0, (float) $value );
			}
		}

		return 0.0;
	}

	/**
	 * Copies an order from LifterLMS to MasterStudy (the LifterLMS order is only read).
	 *
	 * @param int      $order_id LifterLMS order ID.
	 * @param \WP_Post $post     Order post.
	 */
	private static function migrate_order( int $order_id, \WP_Post $post ): void {
		global $wpdb;

		$order_status_map = array(
			'llms-completed'      => 'completed',
			'llms-active'         => 'completed',
			'llms-expired'        => 'completed',
			'llms-on-hold'        => 'on-hold',
			'llms-pending-cancel' => 'cancelled',
			'llms-pending'        => 'pending',
			'llms-cancelled'      => 'cancelled',
			'llms-refunded'       => 'refunded',
			'llms-failed'         => 'failed',
		);
		// A trashed order keeps the status it had before it was trashed (and stays in the trash below).
		$source_status = 'trash' === $post->post_status ? (string) get_post_meta( $order_id, '_wp_trash_meta_status', true ) : $post->post_status;
		$status        = $order_status_map[ $source_status ] ?? 'pending';

		$course_id    = absint( get_post_meta( $order_id, '_llms_product_id', true ) );
		$total        = get_post_meta( $order_id, '_llms_total', true );
		$subtotal     = self::order_price_before_coupon( $order_id );
		$items        = array();
		$items_report = array();
		$product_type = get_post_meta( $order_id, '_llms_product_type', true );
		$course_copy  = 'course' === get_post_type( $course_id ) ? Target::copy_of( self::SOURCE, $course_id ) : 0;

		if ( $course_copy && PostType::COURSE === get_post_type( $course_copy ) ) {
			// MasterStudy item prices are before the coupon (the coupon meta is applied on top): the plan price the
			// student paid — its sale price when the order was placed during a sale.
			$items[] = array(
				'course_id' => $course_copy,
				'price'     => null !== $subtotal ? $subtotal : (float) $total,
			);
		} else {
			Helper::log( 'warning', sprintf( 'Migration [lifterlms]: order %d is for %s %d, not a migrated course — imported without items.', $order_id, $product_type ? $product_type : 'product', $course_id ) );
			// Reported once the order copy exists (linked to it).
			$items_report = array(
				'source_id' => $order_id,
				'title'     => $post->post_title,
				'type'      => 'membership' === $product_type
					? __( 'Membership order', 'masterstudy-lms-learning-management-system' )
					: __( 'LifterLMS order', 'masterstudy-lms-learning-management-system' ),
				'reason'    => 'membership' === $product_type
					/* translators: %s: membership title */
					? sprintf( __( 'The order is for the membership "%s" — MasterStudy has no memberships, so the order was imported without items.', 'masterstudy-lms-learning-management-system' ), get_post_field( 'post_title', $course_id ) )
					: __( 'The ordered product is not a migrated course (it may have been deleted), so the order was imported without items.', 'masterstudy-lms-learning-management-system' ),
				'status'    => Report::STATUS_PARTIAL,
			);
		}

		$payment_gateway = (string) get_post_meta( $order_id, '_llms_payment_gateway', true );

		// LifterLMS "manual" is the offline/bank transfer gateway.
		if ( 'manual' === $payment_gateway ) {
			$payment_gateway = 'wire_transfer';
		} elseif ( '' === $payment_gateway ) {
			$payment_gateway = 'cash';
		}

		$date = strtotime( $post->post_date_gmt . ' UTC' );

		if ( ! $date || $date <= 0 ) {
			$date = (int) strtotime( $post->post_date );
		}

		$currency = (string) get_post_meta( $order_id, '_llms_currency', true );

		if ( '' !== $currency && function_exists( 'get_woocommerce_currency_symbol' ) ) {
			$currency = html_entity_decode( get_woocommerce_currency_symbol( $currency ), ENT_QUOTES );
		}

		$order_copy = Target::save_order(
			array(
				'order_id'       => $order_id,
				'user_id'        => absint( get_post_meta( $order_id, '_llms_user_id', true ) ),
				'items'          => $items,
				'status'         => $status,
				'date'           => $date > 0 ? $date : time(),
				'total'          => (float) $total,
				'subtotal'       => null !== $subtotal ? $subtotal : (float) $total,
				'currency'       => $currency,
				'payment_code'   => $payment_gateway,
				'transaction_id' => self::order_transaction_id( $order_id ),
			),
			self::SOURCE
		);

		// Billing details have no MasterStudy equivalent.
		$billing = array();

		foreach ( array( 'email', 'first_name', 'last_name', 'address_1', 'address_2', 'city', 'zip', 'country', 'state', 'phone' ) as $field ) {
			$value = (string) get_post_meta( $order_id, '_llms_billing_' . $field, true );

			if ( '' !== $value ) {
				$billing[ $field ] = $value;
			}
		}

		Target::store_unmigrated_meta( $order_copy, 'llms_billing', $billing );
		Target::store_unmigrated_meta( $order_copy, 'llms_user_ip_address', (string) get_post_meta( $order_id, '_llms_user_ip_address', true ) );
		Target::store_unmigrated_meta( $order_copy, 'llms_order_status', $post->post_status );
		Target::store_unmigrated_meta( $order_copy, 'llms_plan_id', (int) get_post_meta( $order_id, '_llms_plan_id', true ) );

		// A sale order is imported at its sale price; the regular price is kept for reference.
		if ( self::order_on_sale( $order_id ) ) {
			Target::store_unmigrated_meta( $order_copy, 'llms_original_total', (float) get_post_meta( $order_id, '_llms_original_total', true ) );
		}

		self::set_order_coupon( $order_id, $order_copy, count( $items ), null !== $subtotal ? $subtotal : (float) $total );

		// Target::save_order() publishes the order copy; a trashed LifterLMS order must stay in the trash.
		if ( 'trash' === $post->post_status && 'trash' !== get_post_status( $order_copy ) ) {
			$wpdb->update( $wpdb->posts, array( 'post_status' => 'trash' ), array( 'ID' => $order_copy ) );
			clean_post_cache( $order_copy );
		}

		if ( ! empty( $items_report ) ) {
			Report::add( Report::GROUP_ORDERS, $items_report + array( 'post_id' => $order_copy ) );
		}

		$losses  = array();
		$user_id = absint( get_post_meta( $order_id, '_llms_user_id', true ) );

		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			$losses[] = __( 'the customer no longer exists (the order was kept for the sales history)', 'masterstudy-lms-learning-management-system' );
		}

		// Partial refunds (`_llms_refund_amount` on the order transactions) of an order that is not fully refunded.
		$refunded = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM( CAST( m.meta_value AS DECIMAL(12,2) ) ) FROM {$wpdb->posts} t
				 INNER JOIN {$wpdb->postmeta} m ON m.post_id = t.ID AND m.meta_key = '_llms_refund_amount'
				 WHERE t.post_type = 'llms_transaction' AND t.post_parent = %d",
				$order_id
			)
		);

		if ( $refunded > 0 && 'refunded' !== $status ) {
			Target::store_unmigrated_meta( $order_copy, 'llms_refunded_total', $refunded );
			/* translators: %s: refunded amount */
			$losses[] = sprintf( __( '%s was refunded, but MasterStudy orders have no partial refunds — the order total was kept', 'masterstudy-lms-learning-management-system' ), $refunded );
		}

		if ( ! empty( $losses ) ) {
			Report::add(
				Report::GROUP_ORDERS,
				array(
					'source_id' => $order_id,
					'title'     => $post->post_title,
					'type'      => __( 'LifterLMS order', 'masterstudy-lms-learning-management-system' ),
					/* translators: %s: list of differences */
					'reason'    => sprintf( __( 'The order was imported, but %s.', 'masterstudy-lms-learning-management-system' ), implode( '; ', $losses ) ),
					'status'    => Report::STATUS_PARTIAL,
					'post_id'   => $order_copy,
				)
			);
		}
	}

	/**
	 * Store the LifterLMS order coupon in the MasterStudy order coupon meta (coupon_value / coupon_type /
	 * coupon_id), like the Tutor LMS and Masteriyo readers.
	 *
	 * LifterLMS order meta: `_llms_coupon_used` (yes|no), `_llms_coupon_code`, `_llms_coupon_type`
	 * (percent|dollar), `_llms_coupon_amount` (coupon rate or amount) and `_llms_coupon_value` (discount
	 * applied to the order). A percentage coupon on a single-course order keeps its rate; otherwise the
	 * amount actually deducted is stored.
	 *
	 * @param int   $order_id    LifterLMS order ID (read).
	 * @param int   $order_copy  MasterStudy copy of the order (written).
	 * @param int   $items_count Number of course items of the order.
	 * @param float $base_price  Price the coupon applied to (the sale price of a sale order, else the regular price).
	 */
	private static function set_order_coupon( int $order_id, int $order_copy, int $items_count, float $base_price ): void {
		global $wpdb;

		$code = trim( (string) get_post_meta( $order_id, '_llms_coupon_code', true ) );

		if ( '' === $code || 'no' === get_post_meta( $order_id, '_llms_coupon_used', true ) ) {
			return;
		}

		$discount = (float) get_post_meta( $order_id, '_llms_coupon_value', true );

		// Orders without `_llms_coupon_value`: the discount is what the coupon took off the (sale) price — a sale is
		// not a coupon discount.
		if ( $discount <= 0 ) {
			$discount = max( 0, $base_price - (float) get_post_meta( $order_id, '_llms_total', true ) );
		}

		$type  = 'amount';
		$value = $discount;
		$rate  = (float) get_post_meta( $order_id, '_llms_coupon_amount', true );

		if ( $items_count <= 1 && 'percent' === get_post_meta( $order_id, '_llms_coupon_type', true ) && $rate > 0 ) {
			$type  = 'percent';
			$value = min( 100, $rate );
		}

		if ( $value <= 0 ) {
			return;
		}

		update_post_meta( $order_copy, 'coupon_value', $value );
		update_post_meta( $order_copy, 'coupon_type', $type );
		Target::store_unmigrated_meta( $order_copy, 'coupon_codes', $code );

		$ms_coupons = $wpdb->prefix . 'stm_lms_coupons';

		if ( self::table_exists( $ms_coupons ) ) {
			$ms_coupon_id = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT id FROM {$ms_coupons} WHERE code = %s LIMIT 1", strtoupper( sanitize_text_field( $code ) ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);

			if ( $ms_coupon_id ) {
				update_post_meta( $order_copy, 'coupon_id', $ms_coupon_id );
			}
		}
	}

	/**
	 * Whether the LifterLMS order was placed during a sale of its access plan (LLMS_Order::init() stores
	 * `_llms_on_sale` = yes and the plan's `_llms_sale_price`; `_llms_total` is then based on the sale price).
	 *
	 * @param int $order_id LifterLMS order ID.
	 */
	private static function order_on_sale( int $order_id ): bool {
		return 'yes' === get_post_meta( $order_id, '_llms_on_sale', true ) && '' !== trim( (string) get_post_meta( $order_id, '_llms_sale_price', true ) );
	}

	/**
	 * Price of a LifterLMS order before its coupon: `_llms_sale_price` of a sale order, else `_llms_original_total`
	 * (the plan's regular price, LLMS_Order::init()).
	 *
	 * @param int $order_id LifterLMS order ID.
	 * @return float|null Null when the order has no price meta.
	 */
	private static function order_price_before_coupon( int $order_id ): ?float {
		if ( self::order_on_sale( $order_id ) ) {
			return max( 0.0, (float) get_post_meta( $order_id, '_llms_sale_price', true ) );
		}

		$original = (string) get_post_meta( $order_id, '_llms_original_total', true );

		return '' !== $original ? (float) $original : null;
	}

	/**
	 * Gateway transaction ID of a LifterLMS order: `_llms_gateway_transaction_id` of its first succeeded transaction
	 * (`llms_transaction` posts of the order, `llms-txn-succeeded`), else of its first refunded one (a refund changes
	 * the transaction status). `_llms_gateway_source_id` is the payment source (card / customer token), not the
	 * transaction.
	 *
	 * @param int $order_id LifterLMS order ID.
	 */
	private static function order_transaction_id( int $order_id ): string {
		global $wpdb;

		$transaction = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT t.ID FROM {$wpdb->posts} t
				 WHERE t.post_type = 'llms_transaction' AND t.post_status IN ( 'llms-txn-succeeded', 'llms-txn-refunded' )
				   AND ( t.post_parent = %d OR EXISTS (
					   SELECT 1 FROM {$wpdb->postmeta} o WHERE o.post_id = t.ID AND o.meta_key = '_llms_order_id' AND o.meta_value = %s
				   ) )
				 ORDER BY t.post_status = 'llms-txn-succeeded' DESC, t.ID ASC
				 LIMIT 1",
				$order_id,
				(string) $order_id
			)
		);

		return $transaction ? trim( (string) get_post_meta( $transaction, '_llms_gateway_transaction_id', true ) ) : '';
	}

	/**
	 * Migrate a single LifterLMS quiz attempt row to stm_lms_user_quizzes / stm_lms_user_answers.
	 *
	 * The source row is only read; the attempt is stored for the quiz / question copies. Idempotent per
	 * (student, quiz copy, attempt end time). Unfinished attempts (incomplete/current/pending) have no MasterStudy
	 * equivalent and are skipped.
	 *
	 * @param int $attempt_id Primary key of the lifterlms_quiz_attempts row.
	 */
	private static function migrate_single_quiz_attempt( int $attempt_id ): void {
		global $wpdb;

		$attempts_table = $wpdb->prefix . 'lifterlms_quiz_attempts';
		$row            = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$attempts_table} WHERE id = %d", $attempt_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		if ( ! $row ) {
			return;
		}

		$quiz_id   = (int) $row['quiz_id'];
		$user_id   = (int) $row['student_id'];
		$quiz_copy = Target::copy_of( self::SOURCE, $quiz_id );

		$status_map = array(
			'pass'     => true,
			'complete' => true,
			'fail'     => false,
		);

		$report = array(
			'source_id' => $attempt_id,
			/* translators: 1: attempt ID, 2: user label */
			'title'     => sprintf( __( 'Attempt #%1$d by %2$s', 'masterstudy-lms-learning-management-system' ), $attempt_id, self::user_label( $user_id ) ),
			'parent'    => self::parent_label( 'quiz', $quiz_id ),
			'post_id'   => self::link_id( $quiz_id ),
		);

		if ( ! isset( $status_map[ $row['status'] ] ) ) {
			Helper::log( 'info', sprintf( 'Migration [lifterlms]: quiz attempt %d has status "%s" (not finished) — skipped.', $attempt_id, $row['status'] ) );
			Report::add(
				Report::GROUP_QUIZ_ATTEMPTS,
				array_merge(
					$report,
					array(
						/* translators: %s: LifterLMS attempt status */
						'type'   => sprintf( __( 'Unfinished attempt (%s)', 'masterstudy-lms-learning-management-system' ), (string) $row['status'] ),
						'reason' => __( 'MasterStudy only stores finished quiz attempts — the in-progress / pending attempt was skipped.', 'masterstudy-lms-learning-management-system' ),
						'status' => Report::STATUS_UNSUPPORTED,
					)
				)
			);
			return;
		}

		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			Report::add(
				Report::GROUP_QUIZ_ATTEMPTS,
				array_merge(
					$report,
					array(
						'type'   => __( 'LifterLMS quiz attempt', 'masterstudy-lms-learning-management-system' ),
						'reason' => __( 'The student no longer exists, so the attempt was skipped.', 'masterstudy-lms-learning-management-system' ),
						'status' => Report::STATUS_FAILED,
					)
				)
			);
			return;
		}

		if ( ! $quiz_copy || PostType::QUIZ !== get_post_type( $quiz_copy ) ) {
			Helper::log( 'warning', sprintf( 'Migration [lifterlms]: quiz %d of attempt %d was not migrated — skipped.', $quiz_id, $attempt_id ) );
			Report::add(
				Report::GROUP_QUIZ_ATTEMPTS,
				array_merge(
					$report,
					array(
						'type'   => __( 'LifterLMS quiz attempt', 'masterstudy-lms-learning-management-system' ),
						'reason' => __( 'The quiz was not migrated (it was empty, deleted or not attached to a course lesson), so the attempt was skipped.', 'masterstudy-lms-learning-management-system' ),
						'status' => Report::STATUS_FAILED,
					)
				)
			);
			return;
		}

		$course_id = self::resolve_course_id( $quiz_copy, Target::copy_of( self::SOURCE, (int) get_post_meta( (int) $row['lesson_id'], '_llms_parent_course', true ) ) );

		if ( ! $course_id ) {
			Helper::log( 'warning', sprintf( 'Migration [lifterlms]: no course found for quiz %d — attempt %d skipped.', $quiz_id, $attempt_id ) );
			Report::add(
				Report::GROUP_QUIZ_ATTEMPTS,
				array_merge(
					$report,
					array(
						'type'   => __( 'LifterLMS quiz attempt', 'masterstudy-lms-learning-management-system' ),
						'reason' => __( 'The quiz is not part of any migrated MasterStudy course curriculum, so the attempt was skipped.', 'masterstudy-lms-learning-management-system' ),
						'status' => Report::STATUS_FAILED,
					)
				)
			);
			return;
		}

		// MasterStudy records an attempt when it is submitted: use the LifterLMS end date (start date as fallback).
		$attempt_at = self::attempt_date( $row );

		$answers         = array();
		$dropped_answers = 0;
		$raw_questions   = maybe_unserialize( $row['questions'] );

		if ( is_array( $raw_questions ) ) {
			foreach ( $raw_questions as $q ) {
				$q             = (array) $q;
				$question_id   = isset( $q['id'] ) ? (int) $q['id'] : 0;
				$question_copy = Target::copy_of( self::SOURCE, $question_id );
				$question_type = $question_copy ? (string) get_post_meta( $question_copy, 'type', true ) : '';

				if ( ! $question_copy || PostType::QUESTION !== get_post_type( $question_copy ) || '' === $question_type ) {
					++$dropped_answers;
					continue;
				}

				$answers[] = array(
					'question_id' => $question_copy,
					'answer'      => self::build_user_answer( $question_id, $question_type, $q['answer'] ?? null ),
					'correct'     => self::is_truthy( $q['correct'] ?? null ),
				);
			}
		}

		// Idempotency: an attempt imported by a previous run is not added again (its losses are still reported).
		if ( ! Target::quiz_attempt_exists( $user_id, $quiz_copy, $attempt_at, (float) $row['grade'], (bool) $status_map[ $row['status'] ] ) ) {
			Target::add_quiz_attempt(
				$user_id,
				$course_id,
				$quiz_copy,
				(float) $row['grade'],
				$status_map[ $row['status'] ],
				$attempt_at,
				$answers
			);
		}

		if ( $dropped_answers > 0 ) {
			Report::add(
				Report::GROUP_QUIZ_ATTEMPTS,
				array_merge(
					$report,
					array(
						'type'   => __( 'LifterLMS quiz attempt', 'masterstudy-lms-learning-management-system' ),
						/* translators: %d: number of answers */
						'reason' => sprintf( __( 'The attempt was imported with its grade, but %d answer(s) to questions that were not imported (unsupported types or skipped questions) were dropped.', 'masterstudy-lms-learning-management-system' ), $dropped_answers ),
						'status' => Report::STATUS_PARTIAL,
						'course' => get_post_field( 'post_title', $course_id ),
					)
				)
			);
		}
	}

	/**
	 * Date of a finished LifterLMS quiz attempt (site time): `end_date`, else `start_date`, else now.
	 *
	 * @param array $row lifterlms_quiz_attempts row.
	 */
	private static function attempt_date( array $row ): string {
		foreach ( array( 'end_date', 'start_date' ) as $field ) {
			$value = (string) ( $row[ $field ] ?? '' );

			if ( '' !== $value && 0 !== strpos( $value, '0000-00-00' ) ) {
				return $value;
			}
		}

		return current_time( 'mysql' );
	}

	/**
	 * Import the student submissions of one copied assignment (LifterLMS Assignments add-on table
	 * `lifterlms_assignments_submissions`, only read) as MasterStudy submissions of the assignment copy, in
	 * chronological order. Idempotent per submission row (ProTarget source id). Submitted files stay LifterLMS
	 * attachments: the MasterStudy submission gets its own attachment records of the same files.
	 *
	 * @param int $assignment_id LifterLMS assignment ID (copied by the courses step).
	 * @throws \Exception When the assignment was not copied or is not part of a migrated course.
	 */
	private static function migrate_single_assignment_submissions( int $assignment_id ): void {
		global $wpdb;

		$assignment_copy = Target::copy_of( self::SOURCE, $assignment_id );

		if ( ! $assignment_copy || PostType::ASSIGNMENT !== get_post_type( $assignment_copy ) ) {
			throw new \Exception(
				/* translators: %d: assignment ID */
				sprintf( __( 'LifterLMS assignment #%d was not migrated, so its submissions were skipped.', 'masterstudy-lms-learning-management-system' ), $assignment_id ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		$lesson_id = (int) get_post_meta( $assignment_id, '_llms_lesson_id', true );
		$course_id = self::resolve_course_id( $assignment_copy, $lesson_id ? Target::copy_of( self::SOURCE, (int) get_post_meta( $lesson_id, '_llms_parent_course', true ) ) : 0 );

		if ( ! $course_id ) {
			throw new \Exception( __( 'The assignment is not part of any migrated MasterStudy course curriculum, so its submissions were skipped.', 'masterstudy-lms-learning-management-system' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$table = $wpdb->prefix . self::SUBMISSIONS_TABLE;
		$rows  = (array) $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE assignment_id = %d ORDER BY id ASC", $assignment_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		foreach ( $rows as $index => $row ) {
			$rows[ $index ]['_time'] = self::submission_time( $row );
		}

		// Attempts must be added in chronological order per (assignment, student).
		usort(
			$rows,
			static function ( $a, $b ) {
				return array( $a['_time'], (int) $a['id'] ) <=> array( $b['_time'], (int) $b['id'] );
			}
		);

		$status_map = array(
			'pass'       => 'passed',
			'passed'     => 'passed',
			'complete'   => 'passed',
			'fail'       => 'not_passed',
			'failed'     => 'not_passed',
			'pending'    => 'pending',
			'submitted'  => 'pending',
			'incomplete' => 'draft',
		);

		$type = (string) get_post_meta( $assignment_id, '_llms_assignment_type', true );

		foreach ( $rows as $row ) {
			$user_id = (int) ( $row['user_id'] ?? $row['student_id'] ?? 0 );
			$status  = $status_map[ strtolower( (string) ( $row['status'] ?? '' ) ) ] ?? 'pending';

			list( $content, $attachments ) = self::submission_content( $assignment_id, $type, $row['submission'] ?? '' );

			// An attempt that was started but never contains an answer carries nothing to import.
			if ( 'draft' === $status && '' === trim( wp_strip_all_tags( $content ) ) && empty( $attachments ) ) {
				continue;
			}

			if ( ! $user_id || ! get_userdata( $user_id ) ) {
				Report::add(
					Report::GROUP_ASSIGNMENTS,
					array(
						'source_id' => 'submission-' . (int) $row['id'],
						/* translators: 1: submission ID, 2: user label */
						'title'     => sprintf( __( 'Submission #%1$d by %2$s', 'masterstudy-lms-learning-management-system' ), (int) $row['id'], self::user_label( $user_id ) ),
						'type'      => __( 'Assignment submission', 'masterstudy-lms-learning-management-system' ),
						'reason'    => __( 'The student no longer exists, so the submission was skipped.', 'masterstudy-lms-learning-management-system' ),
						'status'    => Report::STATUS_FAILED,
						'parent'    => get_post_field( 'post_title', $assignment_id ),
						'course'    => get_post_field( 'post_title', $course_id ),
						'post_id'   => $assignment_copy,
					)
				);
				continue;
			}

			$grade = $row['grade'] ?? null;

			ProTarget::add_assignment_submission(
				array(
					'assignment_id'    => $assignment_copy,
					'course_id'        => $course_id,
					'student_id'       => $user_id,
					'content'          => $content,
					'status'           => $status,
					'grade'            => null !== $grade && '' !== (string) $grade && in_array( $status, array( 'passed', 'not_passed' ), true ) ? (float) $grade : null,
					'review'           => (string) ( $row['remarks'] ?? '' ),
					'attachments'      => $attachments,
					// The submitted files are LifterLMS attachments: never re-parent them.
					'copy_attachments' => true,
					'date'             => $row['_time'] ? $row['_time'] : time(),
					'source_id'        => 'assignment-submission-' . (int) $row['id'],
				),
				self::SOURCE
			);
		}
	}

	/**
	 * Unix time of a submission: submitted, else updated, else created (LifterLMS stores site time).
	 *
	 * @param array $row Submission row.
	 */
	private static function submission_time( array $row ): int {
		foreach ( array( 'submitted', 'updated', 'created' ) as $column ) {
			$time = self::to_timestamp( (string) ( $row[ $column ] ?? '' ) );

			if ( $time > 0 ) {
				return $time;
			}
		}

		return 0;
	}

	/**
	 * Answer HTML and student attachment IDs of a LifterLMS submission.
	 *
	 * Essay: HTML text. Upload: attachment ID(s). Task list: task ID => completion, rendered as a list.
	 *
	 * @param int    $assignment_id Assignment ID.
	 * @param string $type          LifterLMS assignment type (essay|upload|tasklist).
	 * @param mixed  $raw           Raw `submission` column value.
	 * @return array{0: string, 1: int[]}
	 */
	private static function submission_content( int $assignment_id, string $type, $raw ): array {
		$value = maybe_unserialize( $raw );

		if ( is_string( $value ) && in_array( substr( ltrim( $value ), 0, 1 ), array( '[', '{' ), true ) ) {
			$decoded = json_decode( $value, true );
			$value   = null !== $decoded ? $decoded : $value;
		}

		if ( 'upload' === $type || ( is_numeric( $value ) && 'attachment' === get_post_type( (int) $value ) ) ) {
			$ids = array();

			foreach ( (array) $value as $item ) {
				$id = is_array( $item ) ? (int) ( $item['id'] ?? 0 ) : (int) $item;

				if ( $id && 'attachment' === get_post_type( $id ) ) {
					$ids[] = $id;
				}
			}

			return array( '', array_values( array_unique( $ids ) ) );
		}

		if ( is_array( $value ) ) {
			$tasks = array();

			foreach ( $value as $task_id => $done ) {
				$task  = maybe_unserialize( get_post_meta( $assignment_id, '_llms_task_' . $task_id, true ) );
				$title = is_array( $task ) ? (string) ( $task['title'] ?? $task['task'] ?? '' ) : (string) $task;
				/* translators: %s: task ID */
				$title   = '' !== trim( $title ) ? wp_strip_all_tags( $title ) : sprintf( __( 'Task %s', 'masterstudy-lms-learning-management-system' ), $task_id );
				$tasks[] = '<li>' . ( self::is_truthy( $done ) || ( is_numeric( $done ) && (int) $done > 1 ) ? '[x] ' : '[ ] ' ) . esc_html( $title ) . '</li>';
			}

			return array( empty( $tasks ) ? '' : '<ul>' . implode( '', $tasks ) . '</ul>', array() );
		}

		return array( (string) $value, array() );
	}

	/**
	 * Migrate content drip settings for a single lesson (MasterStudy Pro "sequential_drip_content").
	 *
	 * - "enrollment" (N days after enrollment) → ProTarget::drip_after_days().
	 * - "date" (fixed date/time)               → ProTarget::drip_on_date().
	 * - "start" (N days after course start)    → ProTarget::drip_on_date() on course start + N days.
	 * - "prerequisite"                         → handled by migrate_lesson_prerequisites().
	 *
	 * The rule is applied to the lesson copy and to its quiz/assignment copies, which are separate MasterStudy items.
	 *
	 * @param int   $lesson_id LifterLMS lesson ID (read).
	 * @param int   $course_id LifterLMS course ID (read).
	 * @param int[] $items     Curriculum item copies of the lesson (lesson, quiz, assignment), written.
	 */
	private static function migrate_single_content_drip( int $lesson_id, int $course_id, array $items ): void {
		$drip_method = (string) get_post_meta( $lesson_id, '_llms_drip_method', true );

		if ( '' === $drip_method || 'prerequisite' === $drip_method ) {
			return;
		}

		$days   = absint( get_post_meta( $lesson_id, '_llms_days_before_available', true ) );
		$unlock = 0;

		if ( 'enrollment' === $drip_method ) {
			// 0 days after enrollment = available immediately.
			if ( $days < 1 ) {
				return;
			}
		} elseif ( 'date' === $drip_method ) {
			$date = trim( sanitize_text_field( (string) get_post_meta( $lesson_id, '_llms_date_available', true ) ) );
			$time = trim( sanitize_text_field( (string) get_post_meta( $lesson_id, '_llms_time_available', true ) ) );

			if ( '' === $date ) {
				return;
			}

			$unlock = self::site_timestamp( $date . ( '' !== $time ? ' ' . $time : '' ) );
		} elseif ( 'start' === $drip_method ) {
			$course_start = self::site_timestamp( (string) get_post_meta( $course_id, '_llms_start_date', true ) );
			$unlock       = $course_start ? $course_start + $days * DAY_IN_SECONDS : 0;
		}

		if ( 'enrollment' !== $drip_method && ! $unlock ) {
			Target::store_unmigrated_meta( (int) $items[0], 'llms_drip', self::drip_source_data( $lesson_id, $drip_method, $days ) );
			self::report_lesson(
				$lesson_id,
				$course_id,
				__( 'Drip content', 'masterstudy-lms-learning-management-system' ),
				'start' === $drip_method
					? __( 'The lesson unlocks a number of days after the course start date, but the course has no start date — the drip schedule was not applied (kept in the _migrated_llms_drip meta).', 'masterstudy-lms-learning-management-system' )
					/* translators: %s: LifterLMS drip method */
					: sprintf( __( 'The LifterLMS drip setting "%s" could not be read — the lesson is not locked (kept in the _migrated_llms_drip meta).', 'masterstudy-lms-learning-management-system' ), $drip_method )
			);
			return;
		}

		if ( ! ProTarget::pro_active() ) {
			Target::store_unmigrated_meta( (int) $items[0], 'llms_drip', self::drip_source_data( $lesson_id, $drip_method, $days ) );
			self::report_lesson(
				$lesson_id,
				$course_id,
				__( 'Drip content', 'masterstudy-lms-learning-management-system' ),
				__( 'Content drip requires MasterStudy LMS Pro (Sequential drip content addon), which is not active — the lesson is not locked; the drip schedule was kept in the _migrated_llms_drip meta.', 'masterstudy-lms-learning-management-system' )
			);
			return;
		}

		foreach ( $items as $item_id ) {
			if ( 'enrollment' === $drip_method ) {
				ProTarget::drip_after_days( (int) $item_id, $days );
			} else {
				ProTarget::drip_on_date( (int) $item_id, $unlock );
			}
		}
	}

	/**
	 * Raw LifterLMS drip settings of a lesson, kept when they cannot be applied.
	 *
	 * @param int    $lesson_id   Lesson ID.
	 * @param string $drip_method LifterLMS drip method.
	 * @param int    $days        Days before available.
	 */
	private static function drip_source_data( int $lesson_id, string $drip_method, int $days ): array {
		return array(
			'method'       => $drip_method,
			'days'         => $days,
			'date'         => get_post_meta( $lesson_id, '_llms_date_available', true ),
			'time'         => get_post_meta( $lesson_id, '_llms_time_available', true ),
			'prerequisite' => self::link_id( (int) get_post_meta( $lesson_id, '_llms_prerequisite', true ) ),
		);
	}

	/**
	 * Unix time of a LifterLMS date string (e.g. "10/15/2024 3:30 PM"), which is in site time.
	 *
	 * @param string $date Date (any strtotime() format).
	 * @return int 0 when empty or invalid.
	 */
	private static function site_timestamp( string $date ): int {
		$date = trim( $date );
		$time = '' !== $date ? strtotime( $date, 0 ) : false;

		if ( false === $time ) {
			return 0;
		}

		return (int) get_gmt_from_date( gmdate( 'Y-m-d H:i:s', $time ), 'U' );
	}

	/**
	 * Import LifterLMS coupons (`llms_coupon` posts) as MasterStudy coupons (Pro Plus). Idempotent by code.
	 * Runs after the courses step, so course restrictions resolve to the course copies.
	 */
	private static function migrate_coupons(): void {
		global $wpdb;

		$coupons = (array) $wpdb->get_results(
			"SELECT ID, post_title, post_status FROM {$wpdb->posts} WHERE post_type = 'llms_coupon' AND post_status NOT IN ('trash', 'auto-draft') ORDER BY ID ASC" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		if ( empty( $coupons ) ) {
			return;
		}

		foreach ( $coupons as $coupon ) {
			$coupon_id = (int) $coupon->ID;
			$code      = trim( (string) $coupon->post_title );
			$report    = array(
				'source_id' => $coupon_id,
				'title'     => $code,
				'type'      => __( 'LifterLMS coupon', 'masterstudy-lms-learning-management-system' ),
				'post_id'   => $coupon_id,
			);

			if ( ! ProTarget::plus_active() ) {
				Report::add(
					Report::GROUP_ORDERS,
					$report + array(
						'reason' => __( 'Coupons require MasterStudy LMS Pro Plus, which is not active — the coupon was not imported.', 'masterstudy-lms-learning-management-system' ),
						'status' => Report::STATUS_UNSUPPORTED,
					)
				);
				continue;
			}

			$courses     = array_filter( array_map( 'intval', (array) maybe_unserialize( get_post_meta( $coupon_id, '_llms_coupon_courses', true ) ) ) );
			$memberships = array_filter( array_map( 'intval', (array) maybe_unserialize( get_post_meta( $coupon_id, '_llms_coupon_membership', true ) ) ) );
			$migrated    = self::course_copies( $courses );

			// Restricted to memberships only, or to courses that were not migrated: importing it would apply to every course.
			if ( ( ! empty( $courses ) || ! empty( $memberships ) ) && empty( $migrated ) ) {
				Report::add(
					Report::GROUP_ORDERS,
					$report + array(
						'reason' => __( 'The coupon only applies to LifterLMS memberships or to courses that were not migrated, so it was not imported.', 'masterstudy-lms-learning-management-system' ),
						'status' => Report::STATUS_UNSUPPORTED,
					)
				);
				continue;
			}

			$expires = (string) get_post_meta( $coupon_id, '_llms_expiration_date', true );
			$end     = '' !== trim( $expires ) ? strtotime( $expires, 0 ) : false;
			$uses    = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_llms_coupon_id' AND meta_value = %d",
					$coupon_id
				)
			);

			$created = ProTarget::create_coupon(
				array(
					'code'        => $code,
					'title'       => $code,
					'type'        => 'dollar' === get_post_meta( $coupon_id, '_llms_discount_type', true ) ? 'amount' : 'percent',
					'amount'      => (float) get_post_meta( $coupon_id, '_llms_coupon_amount', true ),
					'status'      => 'publish' === $coupon->post_status ? 'active' : 'inactive',
					'usage_limit' => absint( get_post_meta( $coupon_id, '_llms_usage_limit', true ) ),
					'used_count'  => $uses,
					// LifterLMS coupons expire at the end of the expiration day (site time).
					'end'         => false !== $end ? self::site_timestamp( gmdate( 'Y-m-d', $end ) . ' 23:59:59' ) : 0,
					'course_ids'  => $migrated,
				)
			);

			if ( ! $created ) {
				Report::add(
					Report::GROUP_ORDERS,
					$report + array(
						'reason' => __( 'A MasterStudy coupon with the same code already exists (for example from a previous migration run), so the coupon was not imported again.', 'masterstudy-lms-learning-management-system' ),
						'status' => Report::STATUS_UNSUPPORTED,
					)
				);
				continue;
			}

			$losses = array();

			if ( ! empty( $memberships ) ) {
				$losses[] = __( 'its membership restrictions were dropped (MasterStudy has no LifterLMS memberships)', 'masterstudy-lms-learning-management-system' );
			}

			if ( count( $migrated ) < count( $courses ) ) {
				$losses[] = __( 'courses that were not migrated were removed from its course list', 'masterstudy-lms-learning-management-system' );
			}

			if ( ! in_array( (string) get_post_meta( $coupon_id, '_llms_plan_type', true ), array( '', 'any' ), true ) ) {
				$losses[] = __( 'the one-time / recurring plan restriction was dropped', 'masterstudy-lms-learning-management-system' );
			}

			if ( 'yes' === get_post_meta( $coupon_id, '_llms_enable_trial_discount', true ) ) {
				$losses[] = __( 'the trial discount was dropped', 'masterstudy-lms-learning-management-system' );
			}

			if ( ! empty( $losses ) ) {
				Report::add(
					Report::GROUP_ORDERS,
					$report + array(
						/* translators: %s: list of dropped coupon settings */
						'reason' => sprintf( __( 'The coupon was imported, but %s.', 'masterstudy-lms-learning-management-system' ), implode( '; ', $losses ) ),
						'status' => Report::STATUS_PARTIAL,
					)
				);
			}

			// Imported coupons only apply once the coupon field is enabled in the MasterStudy settings.
			self::enable_coupons();
		}
	}

	/**
	 * Turn on the MasterStudy "coupon code" setting (Pro Plus core feature, no addon).
	 */
	private static function enable_coupons(): void {
		$settings = get_option( 'stm_lms_settings', array() );

		if ( is_array( $settings ) && empty( $settings['enable_coupon_code'] ) ) {
			$settings['enable_coupon_code'] = true;
			update_option( 'stm_lms_settings', $settings );
			Helper::log( 'info', 'Migration [lifterlms]: enabled the MasterStudy coupon code setting for the imported LifterLMS coupons.' );
		}
	}

	/**
	 * Courses a LifterLMS membership gives access to: its auto-enroll courses (`_llms_auto_enroll`) and the
	 * courses whose members-only access plans are restricted to it.
	 *
	 * @param int $membership_id Membership ID.
	 * @return int[]
	 */
	private static function membership_course_ids( int $membership_id ): array {
		global $wpdb;

		$ids = array_map( 'intval', (array) maybe_unserialize( get_post_meta( $membership_id, '_llms_auto_enroll', true ) ) );

		$plans = (array) $wpdb->get_results(
			"SELECT p.ID, CAST(pr.meta_value AS UNSIGNED) AS product_id FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} av ON av.post_id = p.ID AND av.meta_key = '_llms_availability' AND av.meta_value = 'members'
			 INNER JOIN {$wpdb->postmeta} pr ON pr.post_id = p.ID AND pr.meta_key = '_llms_product_id'
			 WHERE p.post_type = 'llms_access_plan' AND p.post_status = 'publish'
			 ORDER BY p.ID ASC" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		foreach ( $plans as $plan ) {
			if ( in_array( $membership_id, self::plan_membership_ids( (int) $plan->ID ), true ) ) {
				$ids[] = (int) $plan->product_id;
			}
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * LifterLMS memberships (`llms_membership`) → MasterStudy course bundles (Pro "course_bundle" addon): the bundle
	 * holds the membership's courses and costs the membership's one-time price. Idempotent per membership.
	 * Membership enrollments and orders are handled by the enrollments / orders steps.
	 */
	private static function migrate_memberships(): void {
		global $wpdb;

		$memberships = (array) $wpdb->get_results(
			"SELECT ID, post_title, post_content, post_status, post_author FROM {$wpdb->posts}
			 WHERE post_type = 'llms_membership' AND post_status NOT IN ('trash', 'auto-draft') ORDER BY ID ASC" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		foreach ( $memberships as $membership ) {
			$membership_id = (int) $membership->ID;
			$course_ids    = self::membership_course_ids( $membership_id );
			$migrated      = self::course_copies( $course_ids );
			$report        = array(
				'source_id' => $membership_id,
				'title'     => $membership->post_title,
				'type'      => __( 'LifterLMS membership', 'masterstudy-lms-learning-management-system' ),
				'post_id'   => $membership_id,
			);

			if ( ! ProTarget::pro_active() ) {
				Report::add(
					Report::GROUP_COURSES,
					$report + array(
						'reason' => __( 'MasterStudy has no LifterLMS memberships and course bundles require MasterStudy LMS Pro, which is not active — the membership was not imported (its members keep the course enrollments they already had).', 'masterstudy-lms-learning-management-system' ),
						'status' => Report::STATUS_UNSUPPORTED,
					)
				);
				continue;
			}

			if ( empty( $migrated ) ) {
				Report::add(
					Report::GROUP_COURSES,
					$report + array(
						'reason' => __( 'The membership gives access to no migrated course (no auto-enroll or members-only courses), so no course bundle was created.', 'masterstudy-lms-learning-management-system' ),
						'status' => Report::STATUS_UNSUPPORTED,
					)
				);
				continue;
			}

			$losses    = array();
			$plans     = self::get_access_plan_ids( $membership_id );
			$price     = 0.0;
			$recurring = array();

			foreach ( $plans as $plan_id ) {
				if ( self::is_recurring_plan( $plan_id ) ) {
					$recurring[] = get_post_field( 'post_title', $plan_id );
					continue;
				}

				if ( $price > 0 || 'yes' === get_post_meta( $plan_id, '_llms_is_free', true ) ) {
					continue;
				}

				$regular = (float) get_post_meta( $plan_id, '_llms_price', true );
				$sale    = (float) get_post_meta( $plan_id, '_llms_sale_price', true );
				$price   = 'yes' === get_post_meta( $plan_id, '_llms_on_sale', true ) && $sale > 0 && $sale < $regular ? $sale : $regular;

				if ( $price > 0 && $price < $regular ) {
					/* translators: %s: sale price */
					$losses[] = sprintf( __( 'MasterStudy bundles have a single price — the sale price %s was used', 'masterstudy-lms-learning-management-system' ), $price );
				}
			}

			$status = in_array( $membership->post_status, array( 'publish', 'private' ), true ) && $price > 0 ? $membership->post_status : 'draft';

			try {
				$report['post_id'] = ProTarget::create_bundle(
					array(
						'source_id'    => 'membership-' . $membership_id,
						'title'        => $membership->post_title,
						'content'      => $membership->post_content,
						'author'       => (int) $membership->post_author,
						'price'        => $price,
						'course_ids'   => $migrated,
						'thumbnail_id' => (int) get_post_thumbnail_id( $membership_id ),
						'status'       => $status,
					),
					self::SOURCE
				);
			} catch ( \Throwable $e ) {
				Report::add(
					Report::GROUP_COURSES,
					$report + array(
						'reason' => $e->getMessage(),
						'status' => Report::STATUS_FAILED,
					)
				);
				continue;
			}

			if ( $price <= 0 ) {
				$losses[] = __( 'the membership has no paid one-time access plan, so the bundle was created as a draft without a price', 'masterstudy-lms-learning-management-system' );
			} elseif ( 'draft' === $status && 'draft' !== $membership->post_status ) {
				/* translators: %s: membership post status */
				$losses[] = sprintf( __( 'the membership was "%s", so the bundle was created as a draft', 'masterstudy-lms-learning-management-system' ), $membership->post_status );
			}

			if ( ! empty( $recurring ) ) {
				/* translators: %s: access plan titles */
				$losses[] = sprintf( __( 'its recurring access plans (%s) were not imported — MasterStudy subscriptions cover a single course or the whole site', 'masterstudy-lms-learning-management-system' ), implode( ', ', $recurring ) );
			}

			if ( count( $migrated ) < count( $course_ids ) ) {
				/* translators: %d: number of courses */
				$losses[] = sprintf( __( '%d course(s) of the membership were not migrated and were left out', 'masterstudy-lms-learning-management-system' ), count( $course_ids ) - count( $migrated ) );
			}

			Report::add(
				Report::GROUP_COURSES,
				$report + array(
					/* translators: 1: number of courses, 2: list of differences */
					'reason' => sprintf( __( 'The membership was imported as a MasterStudy course bundle of %1$d course(s). A bundle is a one-time purchase of these courses: membership content restrictions, auto-enrollment into courses added later and the membership itself were not imported%2$s.', 'masterstudy-lms-learning-management-system' ), count( $migrated ), empty( $losses ) ? '' : '; ' . implode( '; ', $losses ) ),
					'status' => Report::STATUS_PARTIAL,
				)
			);
		}
	}

	/**
	 * Course completions (`_is_complete` = yes on a course) of users without any `_status` row for that course: their
	 * enrollment was deleted in LifterLMS (LLMS_Student::delete_enrollment() removes `_status`, `_start_date` and
	 * `_enrollment_trigger` but keeps the completion), so LifterLMS no longer gives them access. They are not enrolled
	 * in MasterStudy either (that would grant access); the completion, which MasterStudy only stores on an enrollment,
	 * is reported.
	 */
	private static function report_completions_without_enrollment(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'lifterlms_user_postmeta';

		if ( ! self::table_exists( $table ) ) {
			return;
		}

		$rows = (array) $wpdb->get_results(
			"SELECT DISTINCT c.user_id, c.post_id FROM {$table} c
			 INNER JOIN {$wpdb->posts} p ON p.ID = c.post_id AND p.post_type = 'course'
			 INNER JOIN {$wpdb->users} u ON u.ID = c.user_id
			 WHERE c.meta_key = '_is_complete' AND c.meta_value = 'yes'
			   AND NOT EXISTS ( SELECT 1 FROM {$table} s WHERE s.user_id = c.user_id AND s.post_id = c.post_id AND s.meta_key = '_status' )
			 ORDER BY c.post_id ASC, c.user_id ASC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		foreach ( $rows as $row ) {
			$user_id   = (int) $row->user_id;
			$course_id = (int) $row->post_id;

			if ( ! Target::copy_of( self::SOURCE, $course_id ) ) {
				continue;
			}

			Report::add(
				Report::GROUP_ENROLLMENTS,
				array(
					'source_id' => $user_id . ':' . $course_id,
					'title'     => self::enrollment_label( $user_id, $course_id ),
					'type'      => __( 'Completed course without enrollment', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'The user completed the course, but the LifterLMS enrollment was deleted afterwards, so LifterLMS no longer gives access — the user was not enrolled in MasterStudy and the course completion (stored on enrollments in MasterStudy) was not imported.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_UNSUPPORTED,
					'course'    => get_post_field( 'post_title', $course_id ),
					'post_id'   => self::link_id( $course_id ),
				)
			);
		}
	}

	/**
	 * LifterLMS Groups add-on groups (`llms_group`, members in lifterlms_user_postmeta `_status` / `_group_role` rows on
	 * the group) → MasterStudy enterprise groups (Pro "enterprise_courses" addon) for the group's course, or the courses
	 * of the group's membership. Runs after the enrollments step, so members' own course enrollments keep their dates.
	 */
	private static function migrate_groups(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'lifterlms_user_postmeta';

		if ( ! self::table_exists( $table ) ) {
			return;
		}

		$groups      = (array) $wpdb->get_results(
			"SELECT ID, post_title, post_author FROM {$wpdb->posts}
			 WHERE post_type = 'llms_group' AND post_status NOT IN ('trash', 'auto-draft') ORDER BY ID ASC" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
		$invitations = $wpdb->prefix . 'lifterlms_group_invitations';
		$has_invites = self::table_exists( $invitations );

		foreach ( $groups as $group ) {
			$group_id = (int) $group->ID;
			$post_id  = (int) get_post_meta( $group_id, '_llms_post_id', true );
			$rows     = (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT user_id, meta_key, meta_value FROM {$table} WHERE post_id = %d AND meta_key IN ('_status', '_group_role') ORDER BY COALESCE( updated_date, '1000-01-01' ) ASC, meta_id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$group_id
				)
			);

			$status = array();
			$roles  = array();

			// Rows oldest first: the latest `_status` of a member wins (LifterLMS keeps the status history).
			foreach ( $rows as $row ) {
				if ( '_status' === $row->meta_key ) {
					$status[ (int) $row->user_id ] = strtolower( (string) $row->meta_value );
				} else {
					$roles[ (int) $row->user_id ] = (string) $row->meta_value;
				}
			}

			$active   = array_keys(
				array_filter(
					$status,
					static function ( $value ) {
						return 'enrolled' === $value;
					}
				)
			);
			$existing = array_values(
				array_filter(
					$active,
					static function ( $user_id ) {
						return (bool) get_userdata( (int) $user_id );
					}
				)
			);
			$report   = array(
				'source_id' => $group_id,
				'title'     => $group->post_title,
				'type'      => __( 'LifterLMS group', 'masterstudy-lms-learning-management-system' ),
				'post_id'   => $group_id,
			);

			if ( ! ProTarget::pro_active() ) {
				Report::add(
					Report::GROUP_ENROLLMENTS,
					$report + array(
						/* translators: %d: number of members */
						'reason' => sprintf( __( 'Groups require MasterStudy LMS Pro (Group courses addon), which is not active — the group and its %d member(s) were not imported as a group; members keep their own course enrollments.', 'masterstudy-lms-learning-management-system' ), count( $active ) ),
						'status' => Report::STATUS_UNSUPPORTED,
					)
				);
				continue;
			}

			$course_ids = self::course_copies( 'llms_membership' === get_post_type( $post_id ) ? self::membership_course_ids( $post_id ) : array( $post_id ) );

			if ( empty( $course_ids ) || empty( $existing ) ) {
				Report::add(
					Report::GROUP_ENROLLMENTS,
					$report + array(
						'reason' => empty( $course_ids )
							? __( 'The group\'s course or membership has no migrated course, so the group was not imported.', 'masterstudy-lms-learning-management-system' )
							: __( 'The group has no active member who still exists, so the group was not imported.', 'masterstudy-lms-learning-management-system' ),
						'status' => Report::STATUS_UNSUPPORTED,
					)
				);
				continue;
			}

			// MasterStudy groups have one admin: the primary admin, else an admin / leader, else the group author.
			$admin_id = 0;

			foreach ( array( 'primary_admin', 'admin', 'leader' ) as $role ) {
				foreach ( $roles as $user_id => $user_role ) {
					if ( $role === $user_role && in_array( $user_id, $existing, true ) ) {
						$admin_id = $user_id;
						break 2;
					}
				}
			}

			if ( ! $admin_id && get_userdata( (int) $group->post_author ) ) {
				$admin_id = (int) $group->post_author;
			}

			try {
				$report['post_id'] = ProTarget::create_group(
					array(
						'source_id'  => $group_id,
						'title'      => $group->post_title,
						'admin_id'   => $admin_id,
						'member_ids' => array_values( array_diff( $existing, array( $admin_id ) ) ),
						'course_ids' => $course_ids,
					),
					self::SOURCE
				);
			} catch ( \Throwable $e ) {
				Report::add(
					Report::GROUP_ENROLLMENTS,
					$report + array(
						'reason' => $e->getMessage(),
						'status' => Report::STATUS_FAILED,
					)
				);
				continue;
			}

			$losses = array();
			$seats  = absint( get_post_meta( $group_id, '_llms_seats', true ) );

			if ( $seats > 0 ) {
				/* translators: %d: number of seats */
				$losses[] = sprintf( __( 'MasterStudy groups have no seat limit — the %d seats were not applied', 'masterstudy-lms-learning-management-system' ), $seats );
			}

			$leaders = count(
				array_filter(
					$roles,
					static function ( $role ) {
						return in_array( $role, array( 'primary_admin', 'admin', 'leader' ), true );
					}
				)
			);

			if ( $leaders > 1 ) {
				/* translators: %d: number of group leaders */
				$losses[] = sprintf( __( 'MasterStudy groups have one admin — %d LifterLMS admins/leaders were imported as one admin and members', 'masterstudy-lms-learning-management-system' ), $leaders );
			}

			if ( count( $existing ) < count( $status ) ) {
				/* translators: %d: number of members */
				$losses[] = sprintf( __( '%d removed, inactive or deleted member(s) were not added', 'masterstudy-lms-learning-management-system' ), count( $status ) - count( $existing ) );
			}

			$pending = $has_invites ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$invitations} WHERE group_id = %d", $group_id ) ) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			if ( $pending > 0 ) {
				/* translators: %d: number of invitations */
				$losses[] = sprintf( __( '%d pending invitation(s) were not imported (invite these people again)', 'masterstudy-lms-learning-management-system' ), $pending );
			}

			if ( ! empty( $losses ) ) {
				Report::add(
					Report::GROUP_ENROLLMENTS,
					$report + array(
						/* translators: %s: list of differences */
						'reason' => sprintf( __( 'The group was imported, but %s.', 'masterstudy-lms-learning-management-system' ), implode( '; ', $losses ) ),
						'status' => Report::STATUS_PARTIAL,
					)
				);
			}
		}
	}

	/**
	 * Report LifterLMS engagements MasterStudy cannot reproduce: certificates awarded on other triggers than course
	 * completion, achievements (with the earned ones) and engagement emails.
	 */
	private static function report_engagements(): void {
		global $wpdb;

		$engagements = (array) $wpdb->get_results(
			"SELECT e.ID, e.post_title,
			        MAX( CASE WHEN m.meta_key = '_llms_engagement_type' THEN m.meta_value END ) AS type,
			        MAX( CASE WHEN m.meta_key = '_llms_trigger_type' THEN m.meta_value END ) AS trigger_type,
			        MAX( CASE WHEN m.meta_key = '_llms_engagement' THEN m.meta_value END ) AS template_id
			 FROM {$wpdb->posts} e
			 LEFT JOIN {$wpdb->postmeta} m ON m.post_id = e.ID AND m.meta_key IN ('_llms_engagement_type', '_llms_trigger_type', '_llms_engagement')
			 WHERE e.post_type = 'llms_engagement' AND e.post_status = 'publish'
			 GROUP BY e.ID, e.post_title ORDER BY e.ID ASC" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		foreach ( $engagements as $engagement ) {
			$type    = (string) $engagement->type;
			$trigger = (string) $engagement->trigger_type;

			if ( 'certificate' === $type && 'course_completed' === $trigger ) {
				continue;
			}

			$template = (int) $engagement->template_id;
			$earned   = in_array( $type, array( 'certificate', 'achievement' ), true )
				? (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_llms_engagement' AND m.meta_value = %d
						 WHERE p.post_type = %s",
						(int) $engagement->ID,
						'certificate' === $type ? 'llms_my_certificate' : 'llms_my_achievement'
					)
				)
				: 0;
			$title   = $template ? get_post_field( 'post_title', $template ) : $engagement->post_title;
			$trigger = '' !== $trigger ? str_replace( '_', ' ', $trigger ) : __( 'unknown trigger', 'masterstudy-lms-learning-management-system' );

			switch ( $type ) {
				case 'certificate':
					$label  = __( 'Certificate engagement', 'masterstudy-lms-learning-management-system' );
					/* translators: 1: certificate title, 2: trigger, 3: number of earned certificates */
					$reason = sprintf( __( 'The certificate "%1$s" was awarded on "%2$s" — MasterStudy only awards certificates on course completion, so it was not imported (%3$d earned certificate(s) were not imported).', 'masterstudy-lms-learning-management-system' ), $title, $trigger, $earned );
					break;
				case 'achievement':
					$label  = __( 'Achievement', 'masterstudy-lms-learning-management-system' );
					/* translators: 1: achievement title, 2: trigger, 3: number of earned achievements */
					$reason = sprintf( __( 'MasterStudy has no achievements / badges — the achievement "%1$s" (awarded on "%2$s") and %3$d earned achievement(s) were not imported.', 'masterstudy-lms-learning-management-system' ), $title, $trigger, $earned );
					break;
				case 'email':
					$label  = __( 'Engagement email', 'masterstudy-lms-learning-management-system' );
					/* translators: 1: email title, 2: trigger */
					$reason = sprintf( __( 'MasterStudy has no engagement emails — the email "%1$s" sent on "%2$s" was not imported (MasterStudy sends its own notification emails).', 'masterstudy-lms-learning-management-system' ), $title, $trigger );
					break;
				default:
					$label  = __( 'LifterLMS engagement', 'masterstudy-lms-learning-management-system' );
					/* translators: 1: engagement type, 2: trigger */
					$reason = sprintf( __( 'The "%1$s" engagement (on "%2$s") has no MasterStudy equivalent and was not imported.', 'masterstudy-lms-learning-management-system' ), $type, $trigger );
			}

			Report::add(
				Report::GROUP_OTHER,
				array(
					'source_id' => (int) $engagement->ID,
					'title'     => $engagement->post_title,
					'type'      => $label,
					'reason'    => $reason,
					'status'    => Report::STATUS_UNSUPPORTED,
					'post_id'   => (int) $engagement->ID,
				)
			);
		}
	}

	/**
	 * Report LifterLMS vouchers (enrollment codes). Codes live in `lifterlms_vouchers_codes`, redemptions in
	 * `lifterlms_voucher_code_redemptions`; redeemed codes already enrolled students (migrated as enrollments).
	 */
	private static function report_vouchers(): void {
		global $wpdb;

		$vouchers = (array) $wpdb->get_results(
			"SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'llms_voucher' AND post_status NOT IN ('trash', 'auto-draft') ORDER BY ID ASC" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		if ( empty( $vouchers ) ) {
			return;
		}

		$codes       = $wpdb->prefix . 'lifterlms_vouchers_codes';
		$redemptions = $wpdb->prefix . 'lifterlms_voucher_code_redemptions';
		$has_codes   = self::table_exists( $codes );
		$has_redeem  = $has_codes && self::table_exists( $redemptions );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $vouchers as $voucher ) {
			$voucher_id = (int) $voucher->ID;
			$total      = $has_codes ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$codes} WHERE voucher_id = %d AND is_deleted = 0", $voucher_id ) ) : 0;
			$redeemed   = $has_redeem ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$redemptions} r INNER JOIN {$codes} c ON c.id = r.code_id WHERE c.voucher_id = %d", $voucher_id ) ) : 0;

			Report::add(
				Report::GROUP_ORDERS,
				array(
					'source_id' => $voucher_id,
					'title'     => $voucher->post_title,
					'type'      => __( 'LifterLMS voucher', 'masterstudy-lms-learning-management-system' ),
					/* translators: 1: number of codes, 2: number of redemptions */
					'reason'    => sprintf( __( 'MasterStudy has no enrollment vouchers — the voucher and its %1$d code(s) were not imported; the %2$d redemption(s) already enrolled students, whose enrollments were migrated. Unused codes no longer work.', 'masterstudy-lms-learning-management-system' ), $total, $redeemed ),
					'status'    => Report::STATUS_UNSUPPORTED,
					'post_id'   => $voucher_id,
				)
			);
		}
		// phpcs:enable
	}

	/**
	 * After the courses step: report LifterLMS content that was not copied — lessons and quizzes outside any course
	 * curriculum, reviews of courses that no longer exist, pages restricted to memberships, and add-on post types
	 * (Private Areas, Social Learning, …) MasterStudy has no equivalent for.
	 */
	private static function report_leftovers(): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$lessons = (array) $wpdb->get_results(
			"SELECT DISTINCT p.ID, p.post_title FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key IN ('_llms_parent_section', '_llms_parent_course')
			 WHERE p.post_type = 'lesson' AND p.post_status NOT IN ('trash', 'auto-draft') ORDER BY p.ID ASC"
		);

		foreach ( $lessons as $lesson ) {
			if ( Target::copy_of( self::SOURCE, (int) $lesson->ID ) ) {
				continue;
			}

			Report::add(
				Report::GROUP_LESSONS,
				array(
					'source_id' => (int) $lesson->ID,
					'title'     => $lesson->post_title,
					'type'      => __( 'Lesson outside a course', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'The lesson is not in a section of any LifterLMS course (its section or course was deleted), so it was not migrated (the LifterLMS lesson is left unchanged).', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_UNSUPPORTED,
					'post_id'   => (int) $lesson->ID,
				)
			);
		}

		$quizzes = (array) $wpdb->get_results(
			"SELECT p.ID, p.post_title, CAST(l.meta_value AS UNSIGNED) AS lesson_id FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} l ON l.post_id = p.ID AND l.meta_key = '_llms_lesson_id'
			 WHERE p.post_type = 'llms_quiz' AND p.post_status NOT IN ('trash', 'auto-draft') ORDER BY p.ID ASC"
		);

		foreach ( $quizzes as $quiz ) {
			$lesson_id = (int) $quiz->lesson_id;

			// Copied quizzes, and empty quizzes of migrated lessons (reported by migrate_lesson_quiz()), are skipped.
			if ( Target::copy_of( self::SOURCE, (int) $quiz->ID ) || ( $lesson_id && Target::copy_of( self::SOURCE, $lesson_id ) && (int) get_post_meta( $lesson_id, '_llms_quiz', true ) === (int) $quiz->ID ) ) {
				continue;
			}

			Report::add(
				Report::GROUP_QUIZZES,
				array(
					'source_id' => (int) $quiz->ID,
					'title'     => $quiz->post_title,
					'type'      => __( 'Quiz outside a course', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'The quiz is not attached to a lesson of any migrated course, so it was not migrated (the LifterLMS quiz, its questions and attempts are left unchanged).', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_UNSUPPORTED,
					'post_id'   => (int) $quiz->ID,
				)
			);
		}

		$reviews = (array) $wpdb->get_results(
			"SELECT r.ID, r.post_title, r.post_parent FROM {$wpdb->posts} r WHERE r.post_type = 'llms_review' ORDER BY r.ID ASC"
		);

		foreach ( $reviews as $review ) {
			$course_copy = Target::copy_of( self::SOURCE, (int) $review->post_parent );

			// Reviews of copied courses are imported by the reviews step.
			if ( $course_copy && PostType::COURSE === get_post_type( $course_copy ) ) {
				continue;
			}

			Report::add(
				Report::GROUP_REVIEWS,
				array(
					'source_id' => (int) $review->ID,
					'title'     => $review->post_title,
					'type'      => __( 'LifterLMS review', 'masterstudy-lms-learning-management-system' ),
					/* translators: %d: course ID */
					'reason'    => sprintf( __( 'The reviewed course #%d no longer exists or was not migrated, so the review was not imported (it is left unchanged in LifterLMS).', 'masterstudy-lms-learning-management-system' ), (int) $review->post_parent ),
					'status'    => Report::STATUS_FAILED,
					'post_id'   => (int) $review->ID,
				)
			);
		}

		// WordPress posts/pages restricted to LifterLMS memberships.
		$restricted = (array) $wpdb->get_results(
			"SELECT p.ID, p.post_title, p.post_type FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_llms_is_restricted' AND m.meta_value = 'yes'
			 WHERE p.post_status NOT IN ('trash', 'auto-draft', 'inherit') AND p.post_type NOT LIKE 'llms\\_%' ORDER BY p.ID ASC"
		);

		foreach ( $restricted as $post ) {
			$levels = array_filter( array_map( 'intval', (array) maybe_unserialize( get_post_meta( (int) $post->ID, '_llms_restricted_levels', true ) ) ) );
			$names  = array_filter(
				array_map(
					static function ( $id ) {
						return get_post_field( 'post_title', $id );
					},
					$levels
				)
			);

			Report::add(
				Report::GROUP_OTHER,
				array(
					'source_id' => (int) $post->ID,
					'title'     => $post->post_title,
					/* translators: %s: post type */
					'type'      => sprintf( __( 'Restricted %s', 'masterstudy-lms-learning-management-system' ), $post->post_type ),
					/* translators: %s: membership titles */
					'reason'    => sprintf( __( 'The content was restricted to LifterLMS memberships (%s). MasterStudy cannot restrict WordPress posts or pages, so it becomes public once LifterLMS is deactivated — make it private or protect it another way.', 'masterstudy-lms-learning-management-system' ), empty( $names ) ? '-' : implode( ', ', $names ) ),
					'status'    => Report::STATUS_UNSUPPORTED,
					'post_id'   => (int) $post->ID,
				)
			);
		}

		// Add-on content: every `llms_*` post type the migration does not handle (Private Areas, Social Learning, …).
		$handled = array( 'llms_quiz', 'llms_question', 'llms_access_plan', 'llms_transaction', 'llms_order', 'llms_review', 'llms_coupon', 'llms_membership', 'llms_certificate', 'llms_my_certificate', 'llms_engagement', 'llms_achievement', 'llms_my_achievement', 'llms_email', 'llms_voucher', 'llms_group', 'llms_assignment', 'llms_form' );
		$types   = (array) $wpdb->get_results(
			"SELECT post_type, COUNT(*) AS total FROM {$wpdb->posts}
			 WHERE post_type LIKE 'llms\\_%' AND post_status NOT IN ('trash', 'auto-draft')
			   AND post_type NOT IN ('" . implode( "','", $handled ) . "')
			 GROUP BY post_type ORDER BY post_type ASC"
		);
		// phpcs:enable

		$labels = array(
			'llms_private_area' => __( 'Private Areas add-on', 'masterstudy-lms-learning-management-system' ),
			'llms_pa_post'      => __( 'Private Areas add-on', 'masterstudy-lms-learning-management-system' ),
			'llms_sl_post'      => __( 'Social Learning add-on', 'masterstudy-lms-learning-management-system' ),
			'llms_sl_story'     => __( 'Social Learning add-on', 'masterstudy-lms-learning-management-system' ),
		);

		foreach ( $types as $type ) {
			Report::add(
				Report::GROUP_OTHER,
				array(
					'source_id' => (string) $type->post_type,
					/* translators: 1: number of items, 2: post type */
					'title'     => sprintf( __( '%1$d "%2$s" item(s)', 'masterstudy-lms-learning-management-system' ), (int) $type->total, $type->post_type ),
					'type'      => $labels[ $type->post_type ] ?? __( 'LifterLMS add-on content', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'MasterStudy has no equivalent for this LifterLMS content type, so it was not imported (it is left unchanged in LifterLMS; private content stays private).', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_UNSUPPORTED,
				)
			);
		}
	}

	/**
	 * Bulk post-step operations.
	 *
	 * @param string $step Step name.
	 */
	public static function finalize_step( string $step ): void {
		switch ( $step ) {
			case 'courses':
				self::migrate_subscription_plans();
				self::migrate_coupons();
				self::migrate_memberships();
				self::report_engagements();
				self::report_vouchers();
				self::report_leftovers();
				break;

			case 'enrollments':
				self::migrate_groups();
				self::report_completions_without_enrollment();

				foreach ( self::migrated_course_ids() as $course_id ) {
					Target::refresh_students_count( $course_id );
				}
				break;

			case 'reviews':
				Target::recalculate_ratings();
				break;

			case 'lesson_progress':
			case 'quiz_attempts':
			case 'assignments':
				$course_ids = self::migrated_course_ids();

				if ( ! empty( $course_ids ) ) {
					Target::recalculate_progress( $course_ids );
				}
				break;
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Source readers
	|--------------------------------------------------------------------------
	*/

	/**
	 * Child posts linked by a LifterLMS parent meta, ordered by `_llms_order`.
	 *
	 * @param int      $parent_id  Parent post ID.
	 * @param string   $parent_key Parent meta key (_llms_parent_course / _llms_parent_section).
	 * @param string[] $post_types Post types to include.
	 * @return object[] Rows with ID, post_title.
	 */
	private static function get_children( int $parent_id, string $parent_key, array $post_types ): array {
		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s AND pm.meta_value = %d
				 LEFT JOIN {$wpdb->postmeta} o ON o.post_id = p.ID AND o.meta_key = '_llms_order'
				 WHERE p.post_type IN ({$placeholders})
				   AND p.post_status NOT IN ('trash', 'auto-draft')
				 ORDER BY CAST(o.meta_value AS UNSIGNED) ASC, p.ID ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( array( $parent_key, $parent_id ), $post_types )
			)
		);
	}

	/**
	 * Questions of a quiz (or children of a group question), ordered like LLMS_Question_Manager.
	 *
	 * @param int $parent_id Quiz ID or group question ID.
	 * @return object[] Rows with ID, post_title, post_content.
	 */
	private static function get_quiz_questions( int $parent_id ): array {
		global $wpdb;

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_content FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_llms_parent_id' AND pm.meta_value = %d
				 WHERE p.post_type = 'llms_question'
				   AND p.post_status NOT IN ('trash', 'auto-draft')
				 ORDER BY p.menu_order ASC, p.ID ASC",
				$parent_id
			)
		);
	}

	/**
	 * Choices of a question (`_llms_choice_{id}` meta), sorted by marker like LLMS_Question::get_choices().
	 *
	 * @param int $question_id Question ID.
	 * @return array[] Choice arrays: id, choice (string or ['id','src']), choice_type, correct, marker.
	 */
	private static function get_choices( int $question_id ): array {
		global $wpdb;

		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s",
				$question_id,
				$wpdb->esc_like( '_llms_choice_' ) . '%'
			)
		);

		$choices = array();

		foreach ( $rows as $row ) {
			$choice = maybe_unserialize( $row->meta_value );

			if ( ! is_array( $choice ) ) {
				continue;
			}

			if ( empty( $choice['id'] ) ) {
				$choice['id'] = substr( $row->meta_key, strlen( '_llms_choice_' ) );
			}

			$choices[] = $choice;
		}

		usort(
			$choices,
			static function ( $a, $b ) {
				return strnatcmp( (string) ( $a['marker'] ?? '' ), (string) ( $b['marker'] ?? '' ) );
			}
		);

		return $choices;
	}

	/**
	 * Display text of a choice (image choices use the attachment title).
	 *
	 * @param array $choice Choice.
	 */
	private static function choice_text( array $choice ): string {
		$value = $choice['choice'] ?? '';

		if ( is_array( $value ) ) {
			$image_id = (int) ( $value['id'] ?? 0 );

			if ( $image_id ) {
				return sanitize_text_field( get_post_field( 'post_title', $image_id ) );
			}

			return sanitize_text_field( (string) ( $value['src'] ?? '' ) );
		}

		return sanitize_text_field( (string) $value );
	}

	/**
	 * Attachment ID of an image choice.
	 *
	 * @param array $choice Choice.
	 */
	private static function choice_image_id( array $choice ): int {
		$value = $choice['choice'] ?? '';

		return is_array( $value ) ? (int) ( $value['id'] ?? 0 ) : 0;
	}

	/**
	 * Whether a choice is marked correct.
	 *
	 * @param array $choice Choice.
	 */
	private static function is_correct( array $choice ): bool {
		return self::is_truthy( $choice['correct'] ?? false );
	}

	/**
	 * Whether a true/false choice is the "True" one (by text, else the first marker).
	 *
	 * @param array   $choice  Choice.
	 * @param array[] $choices All choices of the question (sorted).
	 */
	private static function is_true_choice( array $choice, array $choices ): bool {
		$texts = array_map(
			static function ( $item ) {
				return strtolower( self::choice_text( $item ) );
			},
			$choices
		);

		if ( in_array( 'true', $texts, true ) ) {
			return 'true' === strtolower( self::choice_text( $choice ) );
		}

		return isset( $choices[0]['id'] ) && (string) $choices[0]['id'] === (string) ( $choice['id'] ?? '' );
	}

	/**
	 * LifterLMS mixes bool, 'yes'/'no' and 1/0 for flags.
	 *
	 * @param mixed $value Value.
	 */
	private static function is_truthy( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		return in_array( strtolower( (string) $value ), array( '1', 'yes', 'true', 'on' ), true );
	}

	/**
	 * Convert a LifterLMS attempt answer (choice IDs or strings) to MasterStudy's user_answer string.
	 *
	 * @param int    $question_id LifterLMS question ID (holds the `_llms_choice_*` meta).
	 * @param string $type        MasterStudy question type of its copy.
	 * @param mixed  $answer      Raw LifterLMS answer.
	 */
	private static function build_user_answer( int $question_id, string $type, $answer ): string {
		$values  = is_array( $answer ) ? array_values( $answer ) : ( null === $answer || '' === $answer ? array() : array( $answer ) );
		$choices = array();

		foreach ( self::get_choices( $question_id ) as $choice ) {
			$choices[ (string) $choice['id'] ] = $choice;
		}

		$texts = array();

		foreach ( $values as $value ) {
			$choice = $choices[ (string) $value ] ?? null;

			if ( ! $choice ) {
				$texts[] = sanitize_text_field( (string) $value );
				continue;
			}

			if ( 'true_false' === $type ) {
				$texts[] = self::is_true_choice( $choice, array_values( $choices ) ) ? 'True' : 'False';
				continue;
			}

			$text     = self::choice_text( $choice );
			$image_id = self::choice_image_id( $choice );

			if ( $image_id && in_array( $type, array( 'single_choice', 'multi_choice' ), true ) ) {
				$text .= '|' . esc_url( (string) wp_get_attachment_url( $image_id ) );
			}

			$texts[] = $text;
		}

		switch ( $type ) {
			case 'multi_choice':
				return implode( ',', array_map( 'rawurlencode', $texts ) );
			case 'sortable':
				return '[stm_lms_sortable]' . implode( '[stm_lms_sep]', $texts );
			case 'fill_the_gap':
				return implode( ',', $texts );
			default:
				return (string) reset( $texts );
		}
	}

	/**
	 * Term names of a (possibly unregistered) taxonomy.
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
					 WHERE tr.object_id = %d AND tt.taxonomy = %s
					 ORDER BY t.term_id ASC",
					$object_id,
					$taxonomy
				)
			)
		);
	}

	/**
	 * Term slugs of a (possibly unregistered) taxonomy.
	 *
	 * @param int    $object_id Object ID.
	 * @param string $taxonomy  Taxonomy.
	 * @return string[]
	 */
	private static function term_slugs( int $object_id, string $taxonomy ): array {
		global $wpdb;

		return array_map(
			'strval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT t.slug FROM {$wpdb->terms} t
					 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
					 INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
					 WHERE tr.object_id = %d AND tt.taxonomy = %s
					 ORDER BY t.term_id ASC",
					$object_id,
					$taxonomy
				)
			)
		);
	}

	/**
	 * Recreate the LifterLMS `course_cat` hierarchy on the MasterStudy course categories created by
	 * Target::migrate_categories() (which matches terms by name, without parents). Existing parents are kept.
	 *
	 * @param int $course_id Course ID.
	 */
	private static function sync_category_parents( int $course_id ): void {
		global $wpdb;

		$terms = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.term_id, t.name, tt.parent FROM {$wpdb->terms} t
				 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
				 INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
				 WHERE tr.object_id = %d AND tt.taxonomy = 'course_cat' AND tt.parent > 0",
				$course_id
			)
		);

		foreach ( $terms as $term ) {
			$child = $term;
			$guard = 0;

			// Walk up the source hierarchy: child → parent → grandparent …
			while ( (int) $child->parent > 0 && $guard++ < 10 ) {
				$parent = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT t.term_id, t.name, t.slug, tt.parent, tt.description FROM {$wpdb->terms} t
						 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id AND tt.taxonomy = 'course_cat'
						 WHERE t.term_id = %d",
						(int) $child->parent
					)
				);

				if ( ! $parent ) {
					break;
				}

				$ms_child  = term_exists( (string) $child->name, Taxonomy::COURSE_CATEGORY );
				$ms_parent = term_exists( (string) $parent->name, Taxonomy::COURSE_CATEGORY );

				if ( ! $ms_parent ) {
					$ms_parent = wp_insert_term(
						(string) $parent->name,
						Taxonomy::COURSE_CATEGORY,
						array(
							'slug'        => (string) $parent->slug,
							'description' => (string) $parent->description,
						)
					);
				}

				if ( ! $ms_child || ! $ms_parent || is_wp_error( $ms_parent ) ) {
					break;
				}

				$child_id  = (int) ( is_array( $ms_child ) ? $ms_child['term_id'] : $ms_child );
				$parent_id = (int) ( is_array( $ms_parent ) ? $ms_parent['term_id'] : $ms_parent );
				$current   = get_term( $child_id, Taxonomy::COURSE_CATEGORY );

				if ( $current && ! is_wp_error( $current ) && 0 === (int) $current->parent && $child_id !== $parent_id ) {
					wp_update_term( $child_id, Taxonomy::COURSE_CATEGORY, array( 'parent' => $parent_id ) );
				}

				$child = $parent;
			}
		}
	}

	/**
	 * Course copy a lesson/quiz/assignment copy belongs to in the MasterStudy curriculum, preferring the copy of
	 * the LifterLMS parent course.
	 *
	 * @param int $post_id        Lesson, quiz or assignment copy ID.
	 * @param int $parent_copy_id Copy of the LifterLMS parent course (0 = unknown).
	 */
	private static function resolve_course_id( int $post_id, int $parent_copy_id ): int {
		$course_ids = Target::course_ids_of( $post_id );

		if ( $parent_copy_id && in_array( $parent_copy_id, $course_ids, true ) ) {
			return $parent_copy_id;
		}

		return (int) reset( $course_ids );
	}

	/**
	 * MasterStudy copy of a LifterLMS post for report links and `_migrated_*` references: the copy when there is
	 * one, else the source post (0 when it does not exist).
	 *
	 * @param int $post_id LifterLMS post ID.
	 */
	private static function link_id( int $post_id ): int {
		$copy = Target::copy_of( self::SOURCE, $post_id );

		if ( $copy ) {
			return $copy;
		}

		return $post_id > 0 && get_post( $post_id ) ? $post_id : 0;
	}

	/**
	 * MasterStudy course copies of LifterLMS course IDs (courses that were not copied are dropped, order kept).
	 *
	 * @param int[] $course_ids LifterLMS course IDs.
	 * @return int[]
	 */
	private static function course_copies( array $course_ids ): array {
		return array_values(
			array_filter(
				Target::copies_of( self::SOURCE, array_map( 'intval', $course_ids ) ),
				static function ( $copy_id ) {
					return PostType::COURSE === get_post_type( $copy_id );
				}
			)
		);
	}

	/**
	 * MasterStudy course copies created from LifterLMS courses.
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
	 * LifterLMS dates are stored in site time (llms_current_time( 'mysql' )).
	 *
	 * @param string $date MySQL datetime, site time.
	 */
	private static function to_timestamp( string $date ): int {
		if ( '' === $date || 0 === strpos( $date, '0000-00-00' ) ) {
			return 0;
		}

		return (int) get_gmt_from_date( $date, 'U' );
	}

	/**
	 * Convert a LifterLMS access length + period to days.
	 *
	 * @param int    $length Length.
	 * @param string $period day|week|month|year.
	 */
	private static function period_to_days( int $length, string $period ): int {
		$multipliers = array(
			'day'   => 1,
			'week'  => 7,
			'month' => 30,
			'year'  => 365,
		);

		return $length * ( $multipliers[ $period ] ?? 1 );
	}

	private static function table_exists( string $table ): bool {
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	/*
	|--------------------------------------------------------------------------
	| Write helpers missing from Target (candidates to hoist)
	|--------------------------------------------------------------------------
	*/

	/**
	 * Course sale period (ms timestamps, see spec §1).
	 *
	 * @param string $start Sale start date (any strtotime() format), may be empty.
	 * @param string $end   Sale end date, may be empty.
	 */
	private static function set_sale_dates( int $course_id, string $start, string $end ): void {
		$start_ts = '' !== $start ? strtotime( $start ) : false;
		$end_ts   = '' !== $end ? strtotime( $end ) : false;

		if ( $start_ts ) {
			update_post_meta( $course_id, 'sale_price_dates_start', $start_ts * 1000 );
		}

		if ( $end_ts ) {
			update_post_meta( $course_id, 'sale_price_dates_end', $end_ts * 1000 );
		}
	}

	/**
	 * Course prerequisites (MasterStudy Pro "prerequisite" addon). LifterLMS requires the prerequisite
	 * courses to be completed, so the passing level is 100%. The prerequisites are the COPIES of the LifterLMS
	 * courses: a prerequisite course that is not copied yet (higher ID) is copied now — the courses step fills
	 * it when its turn comes (Target::copy_post() is idempotent). Without Pro the copy IDs are kept with
	 * store_unmigrated_meta().
	 *
	 * @param int   $course_id  LifterLMS course ID.
	 * @param int   $copy_id    MasterStudy copy of the course.
	 * @param int[] $course_ids Prerequisite LifterLMS course IDs.
	 */
	private static function set_prerequisites( int $course_id, int $copy_id, array $course_ids ): void {
		$copies = array();

		foreach ( array_unique( array_filter( array_map( 'intval', $course_ids ) ) ) as $prerequisite_id ) {
			// Only LifterLMS courses (deleted courses and other posts are ignored).
			if ( 'course' === get_post_type( $prerequisite_id ) ) {
				$copies[] = Target::copy_post( $prerequisite_id, PostType::COURSE, self::SOURCE );
			}
		}

		$copies = array_values( array_unique( array_filter( $copies ) ) );

		if ( empty( $copies ) ) {
			return;
		}

		if ( ! ProTarget::pro_active() ) {
			Target::store_unmigrated_meta( $copy_id, 'prerequisites', $copies );
			Helper::log( 'warning', sprintf( 'Migration [lifterlms]: course %d prerequisites require MasterStudy LMS Pro — kept in _migrated_prerequisites.', $course_id ) );
			self::report_course(
				$course_id,
				__( 'Course prerequisite', 'masterstudy-lms-learning-management-system' ),
				/* translators: %s: prerequisite course titles */
				sprintf( __( 'Course prerequisites require MasterStudy LMS Pro, which is not active — the prerequisite (%s) was not applied (kept in the _migrated_prerequisites meta).', 'masterstudy-lms-learning-management-system' ), implode( ', ', array_map( static function ( $id ) { return get_post_field( 'post_title', $id ); }, $copies ) ) )
			);
			return;
		}

		ProTarget::set_prerequisites( $copy_id, $copies, 100 );
	}

	/**
	 * SQL condition: the `_status` row aliased $alias is the latest one of its user + post, the row
	 * LLMS_Student::get_enrollment_status() reads (ORDER BY updated_date DESC, meta_id DESC LIMIT 1).
	 *
	 * @param string $alias Table alias of the lifterlms_user_postmeta row.
	 */
	private static function latest_status_sql( string $alias ): string {
		global $wpdb;

		return "NOT EXISTS (
			SELECT 1 FROM {$wpdb->prefix}lifterlms_user_postmeta newer
			WHERE newer.user_id = {$alias}.user_id AND newer.post_id = {$alias}.post_id AND newer.meta_key = '_status'
			  AND ( COALESCE( newer.updated_date, '1000-01-01' ) > COALESCE( {$alias}.updated_date, '1000-01-01' )
			     OR ( COALESCE( newer.updated_date, '1000-01-01' ) = COALESCE( {$alias}.updated_date, '1000-01-01' ) AND newer.meta_id > {$alias}.meta_id ) )
		)";
	}

	/**
	 * Whether a later `_status` row of the same user + post supersedes this one (see latest_status_sql()).
	 *
	 * @param int    $meta_id      `_status` row ID.
	 * @param int    $user_id      User ID.
	 * @param int    $post_id      Course / membership ID.
	 * @param string $updated_date Row date.
	 */
	private static function newer_status_exists( int $meta_id, int $user_id, int $post_id, string $updated_date ): bool {
		global $wpdb;

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$wpdb->prefix}lifterlms_user_postmeta
				 WHERE user_id = %d AND post_id = %d AND meta_key = '_status'
				   AND ( COALESCE( updated_date, '1000-01-01' ) > COALESCE( NULLIF( %s, '' ), '1000-01-01' )
				      OR ( COALESCE( updated_date, '1000-01-01' ) = COALESCE( NULLIF( %s, '' ), '1000-01-01' ) AND meta_id > %d ) )
				 LIMIT 1",
				$user_id,
				$post_id,
				$updated_date,
				$updated_date,
				$meta_id
			)
		);
	}

	/**
	 * Enrollment time like LLMS_Student::get_enrollment_date( 'enrolled' ): the `updated_date` of the latest
	 * `_start_date` row (its meta_value is just "yes"; a re-enrollment only adds a `_status` row, so the first
	 * enrollment date is kept), else the status row date.
	 *
	 * @param int    $user_id     User ID.
	 * @param int    $course_id   Course ID.
	 * @param string $status_date `_status` row updated_date (site time).
	 */
	private static function enrollment_start_time( int $user_id, int $course_id, string $status_date ): int {
		global $wpdb;

		$start = (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT updated_date FROM {$wpdb->prefix}lifterlms_user_postmeta
				 WHERE user_id = %d AND post_id = %d AND meta_key = '_start_date'
				 ORDER BY updated_date DESC, meta_id DESC LIMIT 1",
				$user_id,
				$course_id
			)
		);
		$time  = self::to_timestamp( $start );

		return $time > 0 ? $time : self::to_timestamp( $status_date );
	}

	/**
	 * Unix time a user completed a LifterLMS course (`_is_complete` = yes row on the course), 0 when not completed.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 */
	private static function course_completion_time( int $user_id, int $course_id ): int {
		global $wpdb;

		$date = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT updated_date FROM {$wpdb->prefix}lifterlms_user_postmeta
				 WHERE user_id = %d AND post_id = %d AND meta_key = '_is_complete' AND meta_value = 'yes'
				 ORDER BY meta_id DESC LIMIT 1",
				$user_id,
				$course_id
			)
		);

		if ( null === $date ) {
			return 0;
		}

		$time = self::to_timestamp( (string) $date );

		// A completion without a usable date still counts as completed.
		return $time > 0 ? $time : time();
	}

	/**
	 * Keep a LifterLMS membership enrollment — MasterStudy has no LifterLMS memberships, so it is reported
	 * and its status kept in the `_migrated_llms_memberships` user meta.
	 *
	 * @param int    $user_id       User ID.
	 * @param int    $membership_id Membership ID.
	 * @param string $status        LifterLMS status.
	 * @param string $date          Status date (site time).
	 */
	private static function store_membership_enrollment( int $user_id, int $membership_id, string $status, string $date ): void {
		Target::track_user_meta( $user_id, '_migrated_llms_memberships' );

		$current = get_user_meta( $user_id, '_migrated_llms_memberships', true );
		$current = is_array( $current ) ? $current : array();

		$current[ $membership_id ] = array(
			'title'  => get_post_field( 'post_title', $membership_id ),
			'status' => $status,
			'date'   => $date,
		);

		update_user_meta( $user_id, '_migrated_llms_memberships', $current );

		Helper::log( 'info', sprintf( 'Migration [lifterlms]: user %d membership %d enrollment ("%s") not migrated — kept in _migrated_llms_memberships user meta.', $user_id, $membership_id, $status ) );
		Report::add(
			Report::GROUP_ENROLLMENTS,
			array(
				'source_id' => $user_id . ':' . $membership_id,
				'title'     => self::enrollment_label( $user_id, $membership_id ),
				'type'      => __( 'Membership enrollment', 'masterstudy-lms-learning-management-system' ),
				'reason'    => __( 'MasterStudy has no LifterLMS memberships — the membership enrollment was not imported (courses the membership enrolled the user into are migrated as course enrollments; status kept in the _migrated_llms_memberships user meta).', 'masterstudy-lms-learning-management-system' ),
				'status'    => Report::STATUS_UNSUPPORTED,
				'post_id'   => $membership_id,
			)
		);
	}

	/**
	 * Keep a non-active LifterLMS enrollment status (expired/cancelled/…) — MasterStudy has no
	 * inactive enrollment row, so the user is not enrolled. The status is kept per course COPY.
	 *
	 * @param int    $user_id   User ID.
	 * @param int    $course_id LifterLMS course ID.
	 * @param string $status    LifterLMS status.
	 * @param string $date      Status date (site time).
	 */
	private static function store_inactive_enrollment( int $user_id, int $course_id, string $status, string $date ): void {
		Target::track_user_meta( $user_id, '_migrated_llms_enrollments' );

		$current = get_user_meta( $user_id, '_migrated_llms_enrollments', true );
		$current = is_array( $current ) ? $current : array();

		$current[ self::link_id( $course_id ) ] = array(
			'status' => $status,
			'date'   => $date,
		);

		update_user_meta( $user_id, '_migrated_llms_enrollments', $current );

		Helper::log( 'info', sprintf( 'Migration [lifterlms]: user %d enrollment in course %d has status "%s" — not enrolled (kept in _migrated_llms_enrollments user meta).', $user_id, $course_id, $status ) );
		Report::add(
			Report::GROUP_ENROLLMENTS,
			array(
				'source_id' => $user_id . ':' . $course_id,
				'title'     => self::enrollment_label( $user_id, $course_id ),
				/* translators: %s: LifterLMS enrollment status, e.g. Expired */
				'type'      => sprintf( __( '%s enrollment', 'masterstudy-lms-learning-management-system' ), ucfirst( str_replace( '-', ' ', $status ) ) ),
				/* translators: %s: LifterLMS enrollment status */
				'reason'    => sprintf( __( 'MasterStudy has no inactive enrollments — the "%s" LifterLMS enrollment was not imported (the user is not enrolled; status kept in the _migrated_llms_enrollments user meta).', 'masterstudy-lms-learning-management-system' ), $status ),
				'status'    => Report::STATUS_UNSUPPORTED,
				'course'    => get_post_field( 'post_title', $course_id ),
				'post_id'   => self::link_id( $course_id ),
			)
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Migration report helpers
	|--------------------------------------------------------------------------
	*/

	/**
	 * Report a course that was migrated with part of its data left behind.
	 *
	 * @param int    $course_id LifterLMS course ID (the report links its copy).
	 * @param string $type      Source feature label.
	 * @param string $reason    Why it was not imported.
	 * @param string $status    Report::STATUS_* constant.
	 */
	private static function report_course( int $course_id, string $type, string $reason, string $status = Report::STATUS_PARTIAL ): void {
		$title = get_post_field( 'post_title', $course_id );

		Report::add(
			Report::GROUP_COURSES,
			array(
				'source_id' => $course_id,
				'title'     => $title,
				'type'      => $type,
				'reason'    => $reason,
				'status'    => $status,
				'course'    => $title,
				'post_id'   => self::link_id( $course_id ),
			)
		);
	}

	/**
	 * Report a lesson that was migrated with part of its data left behind.
	 *
	 * @param int    $lesson_id LifterLMS lesson ID (the report links its copy).
	 * @param int    $course_id LifterLMS course ID.
	 * @param string $type      Source feature label.
	 * @param string $reason    Why it was not imported.
	 * @param string $status    Report::STATUS_* constant.
	 */
	private static function report_lesson( int $lesson_id, int $course_id, string $type, string $reason, string $status = Report::STATUS_PARTIAL ): void {
		$course = $course_id ? get_post_field( 'post_title', $course_id ) : '';

		Report::add(
			Report::GROUP_LESSONS,
			array(
				'source_id' => $lesson_id,
				'title'     => get_post_field( 'post_title', $lesson_id ),
				'type'      => $type,
				'reason'    => $reason,
				'status'    => $status,
				/* translators: %s: course title */
				'parent'    => '' !== $course ? sprintf( __( 'Course: %s', 'masterstudy-lms-learning-management-system' ), $course ) : '',
				'course'    => $course,
				'post_id'   => self::link_id( $lesson_id ),
			)
		);
	}

	/**
	 * "Quiz: …" / "Lesson: …" location label.
	 *
	 * @param string $kind    'quiz' or 'lesson'.
	 * @param int    $post_id Post ID.
	 */
	private static function parent_label( string $kind, int $post_id ): string {
		$title = get_post_field( 'post_title', $post_id );

		if ( 'quiz' === $kind ) {
			/* translators: %s: quiz title */
			return sprintf( __( 'Quiz: %s', 'masterstudy-lms-learning-management-system' ), $title );
		}

		/* translators: %s: lesson title */
		return sprintf( __( 'Lesson: %s', 'masterstudy-lms-learning-management-system' ), $title );
	}

	/**
	 * Readable user reference: email, else login, else "User #ID".
	 *
	 * @param int $user_id User ID.
	 */
	private static function user_label( int $user_id ): string {
		$user = $user_id ? get_userdata( $user_id ) : false;

		if ( $user ) {
			return '' !== (string) $user->user_email ? (string) $user->user_email : (string) $user->user_login;
		}

		/* translators: %d: user ID */
		return sprintf( __( 'User #%d', 'masterstudy-lms-learning-management-system' ), $user_id );
	}

	/**
	 * "user@x → Course title" label for enrollments.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course / membership ID.
	 */
	private static function enrollment_label( int $user_id, int $course_id ): string {
		$title = get_post( $course_id ) ? get_post_field( 'post_title', $course_id ) : '';

		if ( '' === $title ) {
			/* translators: %d: post ID */
			$title = sprintf( __( 'Course #%d', 'masterstudy-lms-learning-management-system' ), $course_id );
		}

		/* translators: 1: user label, 2: course title */
		return sprintf( __( '%1$s → %2$s', 'masterstudy-lms-learning-management-system' ), self::user_label( $user_id ), $title );
	}

	/**
	 * Human-readable LifterLMS question type.
	 *
	 * @param string $ques_type LifterLMS question type.
	 */
	private static function question_type_label( string $ques_type ): string {
		$labels = array(
			'choice'           => __( 'Multiple choice', 'masterstudy-lms-learning-management-system' ),
			'picture_choice'   => __( 'Picture choice', 'masterstudy-lms-learning-management-system' ),
			'true_false'       => __( 'True / false', 'masterstudy-lms-learning-management-system' ),
			'blank'            => __( 'Fill in the blank', 'masterstudy-lms-learning-management-system' ),
			'reorder'          => __( 'Reorder items', 'masterstudy-lms-learning-management-system' ),
			'reorder_pictures' => __( 'Reorder pictures', 'masterstudy-lms-learning-management-system' ),
			'long_answer'      => __( 'Long answer', 'masterstudy-lms-learning-management-system' ),
			'short_answer'     => __( 'Short answer', 'masterstudy-lms-learning-management-system' ),
			'upload'           => __( 'File upload', 'masterstudy-lms-learning-management-system' ),
			'code'             => __( 'Code', 'masterstudy-lms-learning-management-system' ),
			'scale'            => __( 'Scale', 'masterstudy-lms-learning-management-system' ),
			'content'          => __( 'Content (display only)', 'masterstudy-lms-learning-management-system' ),
			'group'            => __( 'Question group', 'masterstudy-lms-learning-management-system' ),
		);

		if ( isset( $labels[ $ques_type ] ) ) {
			return $labels[ $ques_type ];
		}

		return '' !== $ques_type ? ucfirst( str_replace( '_', ' ', $ques_type ) ) : __( 'Unknown', 'masterstudy-lms-learning-management-system' );
	}

	/**
	 * Why a LifterLMS question type could not be imported.
	 *
	 * @param string $ques_type LifterLMS question type.
	 */
	private static function unsupported_question_reason( string $ques_type ): string {
		$kept = __( 'the question was not imported and is not part of the MasterStudy quiz (the LifterLMS question is left unchanged).', 'masterstudy-lms-learning-management-system' );

		switch ( $ques_type ) {
			case 'long_answer':
				/* translators: %s: what happened to the question */
				return sprintf( __( 'MasterStudy has no open-ended (long answer) question type — %s', 'masterstudy-lms-learning-management-system' ), $kept );
			case 'short_answer':
				/* translators: %s: what happened to the question */
				return sprintf( __( 'The short answer question is graded manually in LifterLMS (no automatic grading / correct value) and MasterStudy has no manually graded question type — %s', 'masterstudy-lms-learning-management-system' ), $kept );
			case 'upload':
				/* translators: %s: what happened to the question */
				return sprintf( __( 'MasterStudy has no file upload question type — %s', 'masterstudy-lms-learning-management-system' ), $kept );
			case 'code':
				/* translators: %s: what happened to the question */
				return sprintf( __( 'MasterStudy has no code question type — %s', 'masterstudy-lms-learning-management-system' ), $kept );
			case 'scale':
				/* translators: %s: what happened to the question */
				return sprintf( __( 'MasterStudy has no scale (rating) question type — %s', 'masterstudy-lms-learning-management-system' ), $kept );
			case 'content':
				/* translators: %s: what happened to the question */
				return sprintf( __( 'Content blocks are display-only in LifterLMS and MasterStudy quizzes have no equivalent — %s', 'masterstudy-lms-learning-management-system' ), $kept );
		}

		/* translators: 1: LifterLMS question type, 2: what happened to the question */
		return sprintf( __( 'The LifterLMS question type "%1$s" has no MasterStudy equivalent — %2$s', 'masterstudy-lms-learning-management-system' ), $ques_type, $kept );
	}
}
