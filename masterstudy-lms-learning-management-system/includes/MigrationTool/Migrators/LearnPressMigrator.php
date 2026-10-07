<?php

namespace MasterStudy\Lms\MigrationTool\Migrators;

defined( 'ABSPATH' ) || exit;

use MasterStudy\Lms\MigrationTool\LMS\LearnPress;
use MasterStudy\Lms\MigrationTool\ProTarget;

/**
 * Thin adapter that wires LearnPress into the MigratorInterface contract.
 * All migration logic lives in the LearnPress static class.
 */
class LearnPressMigrator extends AbstractLMSMigrator {

	protected static function get_lms_class(): string {
		return LearnPress::class;
	}

	public function get_slug(): string {
		return 'learnpress';
	}

	public function get_label(): string {
		return 'LearnPress';
	}

	public function get_plugin_file(): string {
		return 'learnpress/learnpress.php';
	}

	/**
	 * Courses run first: the users step maps Wishlist add-on courses to their MasterStudy copies.
	 */
	public function get_steps(): array {
		return $this->filter_steps( array( 'courses', 'users', 'enrollments', 'orders', 'reviews', 'quiz_results', 'lesson_progress' ) );
	}

	/**
	 * What is not imported in the current environment — most LearnPress add-on data is imported
	 * when MasterStudy LMS Pro is active. LearnPress itself is never changed (MasterStudy gets copies).
	 *
	 * @return string[]
	 */
	protected function unsupported_features(): array {
		$features = array(
			__( 'Add-on question types (only true/false, single/multiple choice, fill in the blanks and sorting are copied; the others stay in LearnPress)', 'masterstudy-lms-learning-management-system' ),
			__( 'H5P content and its progress', 'masterstudy-lms-learning-management-system' ),
		);

		if ( ProTarget::pro_active() ) {
			$features[] = __( 'Certificate designs (recreated as a standard MasterStudy certificate with the same background)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Per-assignment upload limits (MasterStudy uses the global Assignments settings)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Additional co-instructors (MasterStudy keeps one co-instructor per course)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Content drip delays shorter than a day and delays after prerequisite items', 'masterstudy-lms-learning-management-system' );
		} else {
			$features[] = __( 'Assignments and student submissions (requires MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Certificates (requires MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Content drip, prerequisites and co-instructors (requires MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Upsell course packages (requires MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
		}

		if ( ! ProTarget::plus_active() ) {
			$features[] = __( 'Coming soon courses (requires MasterStudy LMS Pro Plus)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Upsell coupons (requires MasterStudy LMS Pro Plus)', 'masterstudy-lms-learning-management-system' );
		}

		return array_merge(
			$features,
			array(
				__( 'Course settings without an equivalent: enrollment limit, open access, retakes, extra displayed students, result evaluation method, blocking after finishing, repurchase, offline course, price prefix/suffix, featured review, bbPress forum link', 'masterstudy-lms-learning-management-system' ),
				__( 'Course tags, section descriptions and course collections', 'masterstudy-lms-learning-management-system' ),
				__( 'Several questions per page, per-question points, instant check and negative marking quiz settings', 'masterstudy-lms-learning-management-system' ),
				__( 'Blocked, cancelled and pending enrollments; failed course grades (imported with the real progress, not completed)', 'masterstudy-lms-learning-management-system' ),
				__( 'External-link downloadable materials (uploaded material files are imported)', 'masterstudy-lms-learning-management-system' ),
				__( 'Started but unfinished lessons and in-progress quiz attempts', 'masterstudy-lms-learning-management-system' ),
				__( 'Spam and trashed reviews, and course comments without a star rating', 'masterstudy-lms-learning-management-system' ),
				__( 'Non-course order lines (e.g. certificate purchases); multi-user manual orders become one order per user', 'masterstudy-lms-learning-management-system' ),
				__( 'Fill-in-the-blanks answer rules (several accepted answers, number ranges, case-sensitive blanks: MasterStudy checks one answer per gap, case-insensitively)', 'masterstudy-lms-learning-management-system' ),
				__( 'Scores and answers of completed quizzes LearnPress kept no result for (imported as passed/failed from the grade)', 'masterstudy-lms-learning-management-system' ),
				__( 'Unpublished announcements and the coming-soon message', 'masterstudy-lms-learning-management-system' ),
			)
		);
	}

	protected function content_post_types(): array {
		return array(
			'courses'     => array( 'lp_course' ),
			'lessons'     => array( 'lp_lesson' ),
			'quizzes'     => array( 'lp_quiz' ),
			'questions'   => array( 'lp_question' ),
			'assignments' => array( 'lp_assignment' ),
		);
	}
}
