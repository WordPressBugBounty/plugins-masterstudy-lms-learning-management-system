<?php

namespace MasterStudy\Lms\MigrationTool\Migrators;

defined( 'ABSPATH' ) || exit;

use MasterStudy\Lms\MigrationTool\LMS\TutorLMS;
use MasterStudy\Lms\MigrationTool\ProTarget;

/**
 * Thin adapter that wires Tutor LMS into the MigratorInterface contract.
 * All migration logic lives in the TutorLMS static class.
 */
class TutorLMSMigrator extends AbstractLMSMigrator {

	/**
	 * Plugin basename of Tutor LMS Pro.
	 */
	const PRO_PLUGIN_FILE = 'tutor-pro/tutor-pro.php';

	/**
	 * Steps backed by Tutor LMS Pro data. They run whenever the data exists — site owners often
	 * deactivate Tutor LMS Pro before migrating, and its posts, meta and tables stay in place.
	 */
	const PRO_STEPS = array( 'google_meet', 'lesson_notes' );

	protected static function get_lms_class(): string {
		return TutorLMS::class;
	}

	public function get_slug(): string {
		return 'tutor';
	}

	public function get_label(): string {
		return 'Tutor LMS';
	}

	public function get_plugin_file(): string {
		return 'tutor/tutor.php';
	}

	public function get_steps(): array {
		// 'assignments' imports student submissions; it runs after 'courses' (assignments copied, addon enabled).
		return $this->filter_steps( array( 'users', 'courses', 'enrollments', 'assignments', 'orders', 'reviews', 'announcement', 'questions_n_answers', 'lesson_notes', 'progress', 'quiz_attempts', 'google_meet', 'wishlists' ) );
	}

	public function get_addons_to_activate( string $step ): array {
		return $this->filter_addons(
			array(
				'assignments' => 'assignments',
				'google_meet' => 'google_meet',
			),
			$step
		);
	}

	/**
	 * Tutor LMS features that are not imported, fully or partly, in the current environment
	 * (see the migration report).
	 *
	 * @return string[]
	 */
	protected function unsupported_features(): array {
		$features = array(
			__( 'Open-ended and short-answer questions', 'masterstudy-lms-learning-management-system' ),
			__( 'Image answering, draw/pin-on-image, scale and coordinates questions', 'masterstudy-lms-learning-management-system' ),
			__( 'H5P questions and lessons', 'masterstudy-lms-learning-management-system' ),
			__( 'Subscription-plan order items (the orders are imported without them)', 'masterstudy-lms-learning-management-system' ),
			__( 'Pending, cancelled and expired enrollments', 'masterstudy-lms-learning-management-system' ),
			__( 'Individual (per-student) enrollment expiry dates, enrollment periods and paused enrollment', 'masterstudy-lms-learning-management-system' ),
			__( 'Unfinished quiz attempts', 'masterstudy-lms-learning-management-system' ),
			__( 'Unpublished lessons, quizzes and assignments (copied, but kept out of the curriculum)', 'masterstudy-lms-learning-management-system' ),
			__( 'Public (guest) courses, student limits and course tags', 'masterstudy-lms-learning-management-system' ),
			__( 'Instructor earnings, withdrawals and individual commission rates', 'masterstudy-lms-learning-management-system' ),
			__( 'Content Bank assignments linked (not copied) to a topic', 'masterstudy-lms-learning-management-system' ),
			__( 'Instructor bio and website when the WordPress profile already has these fields (existing user data is never changed)', 'masterstudy-lms-learning-management-system' ),
		);

		if ( ProTarget::pro_active() ) {
			$features[] = __( 'Certificate designs (approximated: background image with the standard fields)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Additional co-instructors (only one is kept)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Content drip items with several prerequisites (only the last one is enforced)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Zoom meetings without a Zoom meeting ID (imported as text lessons with the join link)', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'Course bundle order items (recorded as the bundle\'s courses, with the bundle price on the first course)', 'masterstudy-lms-learning-management-system' );
		} else {
			$features[] = __( 'Assignments and submissions, Zoom meetings, certificates, prerequisites, content drip, co-instructors, course bundles and revenue sharing rates (requires MasterStudy LMS Pro)', 'masterstudy-lms-learning-management-system' );
		}

		if ( ProTarget::plus_active() ) {
			$features[] = __( 'Bundle and membership-plan coupons', 'masterstudy-lms-learning-management-system' );
			$features[] = __( 'One-time and bundle subscription plans; active subscriptions (subscribers must re-subscribe)', 'masterstudy-lms-learning-management-system' );
		} else {
			$features[] = __( 'Google Meet sessions, coupons, subscription and membership plans, "coming soon" courses, private lesson notes and the gradebook scale (requires MasterStudy LMS Pro Plus)', 'masterstudy-lms-learning-management-system' );
		}

		return $features;
	}

	protected function content_post_types(): array {
		return array(
			'courses'     => array( 'courses' ),
			'quizzes'     => array( 'tutor_quiz' ),
			'assignments' => array( 'tutor_assignments' ),
		);
	}

	protected function extra_content_counts(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'tutor_quiz_questions';

		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			$table = '';
		}

		return array(
			// LifterLMS registers the same `lesson` post type: count only lessons inside a Tutor topic.
			'lessons'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} l INNER JOIN {$wpdb->posts} t ON t.ID = l.post_parent AND t.post_type = 'topics' WHERE l.post_type = 'lesson' AND l.post_status NOT IN ( 'trash', 'auto-draft' )" ),
			'questions' => $table ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) : 0, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Progress is migrated per MasterStudy enrollment (0 before the enrollments exist): preview the Tutor enrollments.
	 */
	protected function preview_count( string $step ): int {
		global $wpdb;

		if ( 'progress' === $step ) {
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'tutor_enrolled' AND post_status = 'completed'" );
		}

		return parent::preview_count( $step );
	}
}
