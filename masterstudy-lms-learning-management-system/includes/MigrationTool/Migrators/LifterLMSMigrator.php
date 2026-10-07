<?php

namespace MasterStudy\Lms\MigrationTool\Migrators;

defined( 'ABSPATH' ) || exit;

use MasterStudy\Lms\MigrationTool\LMS\LifterLMS;
use MasterStudy\Lms\MigrationTool\ProTarget;

/**
 * Thin adapter that wires LifterLMS into the MigratorInterface contract.
 * All migration logic lives in the LifterLMS static class.
 */
class LifterLMSMigrator extends AbstractLMSMigrator {

	protected static function get_lms_class(): string {
		return LifterLMS::class;
	}

	public function get_slug(): string {
		return 'lifterlms';
	}

	public function get_label(): string {
		return 'LifterLMS';
	}

	public function get_plugin_file(): string {
		return 'lifterlms/lifterlms.php';
	}

	public function get_steps(): array {
		$steps = array( 'users', 'courses', 'enrollments', 'orders', 'reviews', 'lesson_progress', 'quiz_attempts' );

		// Assignment submissions (LifterLMS Assignments add-on) need the MasterStudy Pro assignments addon.
		if ( ProTarget::pro_active() ) {
			$steps[] = 'assignments';
		}

		return $this->filter_steps( $steps );
	}

	public function get_addons_to_activate( string $step ): array {
		return $this->filter_addons( array( 'assignments' => 'assignments' ), $step );
	}

	protected function unsupported_features(): array {
		$pro  = ProTarget::pro_active();
		$plus = ProTarget::plus_active();

		$features = array(
			__( 'Long answer, manually graded short answer, file upload, code and scale questions', 'masterstudy-lms-learning-management-system' ),
			__( 'Content (display-only) quiz blocks and question groups', 'masterstudy-lms-learning-management-system' ),
			__( 'Membership enrollments, membership content restrictions and pages restricted to memberships', 'masterstudy-lms-learning-management-system' ),
			__( 'Expired, cancelled and other inactive enrollments, and course completions of deleted enrollments', 'masterstudy-lms-learning-management-system' ),
			__( 'Unfinished and pending (manually graded) quiz attempts', 'masterstudy-lms-learning-management-system' ),
			__( 'Course capacity, enrollment period, catalog visibility, tags, tracks and start/end dates', 'masterstudy-lms-learning-management-system' ),
			__( 'Lessons that require a passing quiz / assignment grade (the quiz and assignment become separate curriculum items)', 'masterstudy-lms-learning-management-system' ),
			__( 'Disabled lesson quizzes (imported as draft quizzes outside the curriculum)', 'masterstudy-lms-learning-management-system' ),
			__( 'Course preview audio', 'masterstudy-lms-learning-management-system' ),
			__( 'Achievements, engagement emails and vouchers', 'masterstudy-lms-learning-management-system' ),
			__( 'Private Areas, Social Learning and other add-on content', 'masterstudy-lms-learning-management-system' ),
			__( 'Partial order refunds and payment gateway subscriptions', 'masterstudy-lms-learning-management-system' ),
		);

		if ( $pro ) {
			$features[] = __( 'Exact certificate designs (an approximate certificate is created from each template) and certificates awarded on lesson, section, quiz or track completion', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'More than one co-instructor per course (the first one is assigned)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Drip delays after a prerequisite lesson (the lesson unlocks as soon as the prerequisite is completed)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Memberships as such — each membership becomes a course bundle of its courses (one-time price; recurring membership plans are not imported)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Group seat limits, several group leaders and pending group invitations', 'masterstudy-lms-learning-management-system' );
		} else {
			$features[] = __( 'Assignments and submissions (requires MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Certificates (requires MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Course and lesson prerequisites and content drip, including course-level lesson drip (requires MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Course co-instructors (requires MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Course preview videos (requires MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Memberships (imported as course bundles with MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Groups (Groups add-on; imported as group courses with MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
		}

		if ( $plus ) {
			$features[] = __( 'Multiple one-time access plans (the first one sets the course price) and members-only access plans', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Student subscriptions of recurring access plans (plans are imported; subscribers must re-subscribe)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Paid trials, membership-only coupons and coupon plan/trial restrictions', 'masterstudy-lms-learning-management-system' );
		} else {
			$features[] = __( 'Multiple, members-only and recurring access plans — first public plan used as a one-time price (subscriptions require MasterStudy LMS Pro Plus)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Coupons (requires MasterStudy LMS Pro Plus)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Lesson audio embeds and question videos (requires MasterStudy LMS Pro Plus)', 'masterstudy-lms-learning-management-system' );
		}

		return $features;
	}

	protected function content_post_types(): array {
		return array(
			'courses'     => array( 'course' ),
			'quizzes'     => array( 'llms_quiz' ),
			'questions'   => array( 'llms_question' ),
			'assignments' => array( 'llms_assignment' ),
		);
	}

	/**
	 * Tutor LMS registers the same `lesson` post type: count only lessons that belong to a LifterLMS course.
	 */
	protected function extra_content_counts(): array {
		global $wpdb;

		return array(
			'lessons' => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_llms_parent_course' WHERE p.post_type = 'lesson' AND p.post_status NOT IN ( 'trash', 'auto-draft' )" ),
		);
	}

	/**
	 * Reviews and assignment submissions are counted for already copied courses only: preview the source records.
	 */
	protected function preview_count( string $step ): int {
		global $wpdb;

		if ( 'reviews' === $step ) {
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'llms_review' AND post_status NOT IN ( 'auto-draft' )" );
		}

		if ( 'assignments' === $step ) {
			$table = $wpdb->prefix . 'lifterlms_assignments_submissions';

			return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ? (int) $wpdb->get_var( "SELECT COUNT(DISTINCT assignment_id) FROM {$table}" ) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		return parent::preview_count( $step );
	}
}
