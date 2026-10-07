<?php

namespace MasterStudy\Lms\MigrationTool\Migrators;

defined( 'ABSPATH' ) || exit;

use MasterStudy\Lms\MigrationTool\LMS\Masteriyo;
use MasterStudy\Lms\MigrationTool\ProTarget;

/**
 * Thin adapter that wires Masteriyo into the MigratorInterface contract.
 * All migration logic lives in the Masteriyo static class.
 */
class MasteriyoMigrator extends AbstractLMSMigrator {

	protected static function get_lms_class(): string {
		return Masteriyo::class;
	}

	public function get_slug(): string {
		return 'masteriyo';
	}

	public function get_label(): string {
		return 'Masteriyo';
	}

	public function get_plugin_file(): string {
		return 'learning-management-system/lms.php';
	}

	public function get_steps(): array {
		return $this->filter_steps( array( 'users', 'courses', 'enrollments', 'orders', 'reviews', 'announcement', 'questions_n_answers', 'lesson_progress', 'quiz_attempts', 'wishlists' ) );
	}

	/**
	 * Addons enabled before the courses step starts (outside the batch transaction): enabling them
	 * creates the tables the step writes assignment submissions and subscription plans to.
	 */
	public function get_addons_to_activate( string $step ): array {
		return (array) apply_filters(
			'masterstudy_lms_migration_tool_addons_to_activate',
			'courses' === $step ? Masteriyo::required_addons() : array(),
			$step,
			$this->get_slug()
		);
	}

	/**
	 * Masteriyo features MasterStudy cannot import (fully or partly) in the current environment.
	 *
	 * @return string[]
	 */
	protected function unsupported_features(): array {
		$features = array(
			__( 'Text answer (open-ended), audio and video answer questions', 'masterstudy-lms-learning-management-system' ),
			__( 'Separate feedback for correct / wrong answers (MasterStudy has one explanation per question) and per-question answer shuffling (MasterStudy shuffles per quiz)', 'masterstudy-lms-learning-management-system' ),
			__( 'Unpublished lessons, quizzes and questions are imported but not added to the curriculum (Masteriyo hid them from learners)', 'masterstudy-lms-learning-management-system' ),
			__( 'Video chapters, lesson passwords, external subtitle URLs and custom fields', 'masterstudy-lms-learning-management-system' ),
			__( 'Course settings without an equivalent: enrollment limit, course retake, end date / cohort mode / enrollment window, hidden from catalog, hidden curriculum, fake enrolled count, course badge, purchase note, welcome message, tags', 'masterstudy-lms-learning-management-system' ),
			__( 'WooCommerce, Lemon Squeezy, Google Classroom and BuddyPress course links, and multiple-currency price zones', 'masterstudy-lms-learning-management-system' ),
			__( 'Order billing name, e-mail and street address (they stay in the Masteriyo order — MasterStudy orders store country, state, city, post code, company and phone) and refund records', 'masterstudy-lms-learning-management-system' ),
			__( 'Unpublished announcements (the MasterStudy course announcement is public)', 'masterstudy-lms-learning-management-system' ),
			__( 'Unverified e-mail state of learners (MasterStudy has no pending-verification state for existing accounts)', 'masterstudy-lms-learning-management-system' ),
			__( 'Masteriyo public profile bio and website when they would change the WordPress user profile (users are shared with Masteriyo and are not modified)', 'masterstudy-lms-learning-management-system' ),
		);

		if ( ProTarget::pro_active() ) {
			$features[] = __( 'Assignment due dates', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Certificate designs (approximated: background, orientation and standard fields)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'More than one co-instructor per course', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Group pricing tiers and per-seat group prices (MasterStudy has one group price)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Zoom meetings without a numeric meeting ID (imported as lessons with the join link)', 'masterstudy-lms-learning-management-system' );
		} else {
			$features[] = __( 'Assignments and submissions (requires MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Zoom meetings and live stream lessons (requires MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Certificates (requires MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Course flow / drip content and prerequisites (requires MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Co-instructors, course bundles, group courses and group prices (requires MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'SCORM packages, learner SCORM progress and PDF lessons (requires MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
		}

		if ( ! ProTarget::plus_active() ) {
			$features[] = __( 'Google Meet, audio lessons, coupons, subscription pricing, course preview videos, coming soon courses and learner lesson notes (requires MasterStudy LMS Pro Plus)', 'masterstudy-lms-learning-management-system' );
		} else {
			$features[] = __( 'Active payment-gateway subscriptions (subscribers must re-subscribe)', 'masterstudy-lms-learning-management-system' );
		}

		$features[] = __( 'Inactive enrollments (unpaid, expired or deactivated) and draft groups — not imported; completing the imported order enrolls the student', 'masterstudy-lms-learning-management-system' );
		$features[] = __( 'Unfinished quiz attempts and in-progress lessons', 'masterstudy-lms-learning-management-system' );
		$features[] = __( 'Review replies and quiz review ratings', 'masterstudy-lms-learning-management-system' );
		$features[] = __( 'Gradebook, revenue sharing (earnings/withdrawals), webhooks and manager role', 'masterstudy-lms-learning-management-system' );

		return $features;
	}

	protected function content_post_types(): array {
		return array(
			'courses'     => array( 'mto-course' ),
			'lessons'     => array( 'mto-lesson' ),
			'quizzes'     => array( 'mto-quiz' ),
			'questions'   => array( 'mto-question' ),
			'assignments' => array( 'mto-assignment' ),
		);
	}
}
