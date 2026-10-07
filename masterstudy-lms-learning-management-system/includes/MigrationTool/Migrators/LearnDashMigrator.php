<?php

namespace MasterStudy\Lms\MigrationTool\Migrators;

defined( 'ABSPATH' ) || exit;

use MasterStudy\Lms\MigrationTool\LMS\LearnDash;
use MasterStudy\Lms\MigrationTool\ProTarget;

/**
 * Thin adapter that wires LearnDash into the MigratorInterface contract.
 * All migration logic lives in the LearnDash static class.
 */
class LearnDashMigrator extends AbstractLMSMigrator {

	protected static function get_lms_class(): string {
		return LearnDash::class;
	}

	public function get_slug(): string {
		return 'sfwd-lms';
	}

	public function get_label(): string {
		return 'LearnDash';
	}

	public function get_plugin_file(): string {
		return 'sfwd-lms/sfwd_lms.php';
	}

	public function get_steps(): array {
		return $this->filter_steps( array( 'users', 'courses', 'enrollments', 'lesson_progress', 'quiz_attempts', 'orders', 'assignments', 'reviews', 'wdm_reviews', 'coupons' ) );
	}

	public function get_addons_to_activate( string $step ): array {
		return $this->filter_addons( array( 'assignments' => 'assignments' ), $step );
	}

	/**
	 * LearnDash features MasterStudy cannot import (fully or partly) in the current environment —
	 * MasterStudy LMS Pro / Pro Plus import more.
	 *
	 * @return string[]
	 */
	protected function unsupported_features(): array {
		$features = array(
			__( 'Essay (open answer) questions and essay answers', 'masterstudy-lms-learning-management-system' ),
			__( 'Assessment (survey) questions', 'masterstudy-lms-learning-management-system' ),
			__( 'Essay submissions and manual grading (attempts with essays keep the LearnDash score and result, or are imported as not passed when LearnDash kept none)', 'masterstudy-lms-learning-management-system' ),
			__( 'Weighted question and answer points', 'masterstudy-lms-learning-management-system' ),
			__( 'Quiz random question subsets, quiz prerequisites, custom quiz form fields and leaderboards', 'masterstudy-lms-learning-management-system' ),
			__( 'Course, lesson and quiz materials', 'masterstudy-lms-learning-management-system' ),
			__( 'Course seats limit, course end date, course tags and lesson/topic/quiz categories and tags', 'masterstudy-lms-learning-management-system' ),
			__( 'Forced lesson timers (imported as the lesson duration only)', 'masterstudy-lms-learning-management-system' ),
			__( 'Started but unfinished lesson progress and unsubmitted quiz attempts', 'masterstudy-lms-learning-management-system' ),
			__( 'Expired or removed course access (such enrollments are not imported)', 'masterstudy-lms-learning-management-system' ),
			__( 'Course points required to enroll and manually awarded points', 'masterstudy-lms-learning-management-system' ),
			__( 'Quiz certificates and group certificates (MasterStudy certificates are per course)', 'masterstudy-lms-learning-management-system' ),
			__( 'Challenge exams, the Notifications add-on and unpublished groups', 'masterstudy-lms-learning-management-system' ),
			__( 'Nested groups, group materials, group dates and seats limits', 'masterstudy-lms-learning-management-system' ),
			__( 'Existing recurring subscriptions (payments are imported as one-time orders; students must re-subscribe to be billed)', 'masterstudy-lms-learning-management-system' ),
			__( 'Spam, trashed and guest course reviews', 'masterstudy-lms-learning-management-system' ),
			__( 'Transactions made in a payment gateway\'s test (sandbox) mode', 'masterstudy-lms-learning-management-system' ),
		);

		if ( ProTarget::pro_active() ) {
			$features[] = __( 'Certificate designs (approximated: background image and standard fields)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Prerequisites that require "any one of" several courses', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Extra group leaders (MasterStudy groups have one admin) and extra shared instructors (one co-instructor)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Assignment due dates', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Recurring group prices (sold groups become course bundles with a one-time price)', 'masterstudy-lms-learning-management-system' );
		} else {
			$features[] = __( 'Assignments and submissions (requires MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Certificates (requires MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Course prerequisites (requires MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Lesson drip schedules and linear lesson progression (requires MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'LearnDash Groups — members are enrolled directly in the group courses (requires MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Sold groups as course bundles (requires MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Shared instructors (requires MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Points earned for completed courses (requires MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Closed course button URL (affiliate courses require MasterStudy LMS Pro; closed courses are imported as not purchasable)', 'masterstudy-lms-learning-management-system' );
		}

		if ( ProTarget::plus_active() ) {
			$features[] = __( 'Existing recurring subscriptions (subscribers keep access but must re-subscribe to be billed)', 'masterstudy-lms-learning-management-system' );
		} else {
			$features[] = __( 'Recurring (subscription) course pricing — imported as a one-time price (requires MasterStudy LMS Pro Plus)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Coupons (requires MasterStudy LMS Pro Plus)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Course start dates as upcoming courses and Course Grid preview videos (require MasterStudy LMS Pro Plus)', 'masterstudy-lms-learning-management-system' );
		}

		return $features;
	}

	protected function content_post_types(): array {
		return array(
			'courses'   => array( 'sfwd-courses' ),
			'lessons'   => array( 'sfwd-lessons', 'sfwd-topic' ),
			'quizzes'   => array( 'sfwd-quiz' ),
			'questions' => array( 'sfwd-question' ),
		);
	}
}
