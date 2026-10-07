<?php

namespace MasterStudy\Lms\MigrationTool;

defined( 'ABSPATH' ) || exit;

use MasterStudy\Lms\Plugin\PostType;

/**
 * Structured "not imported" report of a migration session.
 *
 * Every source item that could not be migrated — because it failed, or because MasterStudy has
 * no equivalent (e.g. open-ended questions) — is recorded here, grouped by content type, so the
 * admin can review exactly what was left behind and where it came from.
 */
class Report {

	const GROUP_QUESTIONS     = 'questions';
	const GROUP_COURSES       = 'courses';
	const GROUP_LESSONS       = 'lessons';
	const GROUP_QUIZZES       = 'quizzes';
	const GROUP_ASSIGNMENTS   = 'assignments';
	const GROUP_MEETINGS      = 'meetings';
	const GROUP_USERS         = 'users';
	const GROUP_ENROLLMENTS   = 'enrollments';
	const GROUP_PROGRESS      = 'progress';
	const GROUP_QUIZ_ATTEMPTS = 'quiz_attempts';
	const GROUP_ORDERS        = 'orders';
	const GROUP_REVIEWS       = 'reviews';
	const GROUP_DISCUSSIONS   = 'discussions';
	const GROUP_OTHER         = 'other';

	/**
	 * Item could not be migrated because of an error (the migration of that item was rolled back).
	 */
	const STATUS_FAILED = 'failed';

	/**
	 * Item has no MasterStudy equivalent and was intentionally left out.
	 */
	const STATUS_UNSUPPORTED = 'unsupported';

	/**
	 * Item was migrated, but part of its data could not be carried over.
	 */
	const STATUS_PARTIAL = 'partial';

	/**
	 * Maximum items stored per group; the count keeps growing past it.
	 */
	const GROUP_LIMIT = 500;

	/**
	 * Step name => report group, for items that fail inside the job engine.
	 */
	const STEP_GROUPS = array(
		'users'               => self::GROUP_USERS,
		'courses'             => self::GROUP_COURSES,
		'enrollments'         => self::GROUP_ENROLLMENTS,
		'orders'              => self::GROUP_ORDERS,
		'reviews'             => self::GROUP_REVIEWS,
		'wdm_reviews'         => self::GROUP_REVIEWS,
		'announcement'        => self::GROUP_OTHER,
		'questions_n_answers' => self::GROUP_DISCUSSIONS,
		'progress'            => self::GROUP_PROGRESS,
		'lesson_progress'     => self::GROUP_PROGRESS,
		'quiz_attempts'       => self::GROUP_QUIZ_ATTEMPTS,
		'quiz_results'        => self::GROUP_QUIZ_ATTEMPTS,
		'assignments'         => self::GROUP_ASSIGNMENTS,
		'google_meet'         => self::GROUP_MEETINGS,
		'wishlists'           => self::GROUP_OTHER,
	);

	/**
	 * Session currently being processed; set by the job.
	 *
	 * @var string
	 */
	private static $session_id = '';

	public static function set_session( string $session_id ): void {
		static::$session_id = $session_id;
	}

	/**
	 * Record an item that was not (fully) imported.
	 *
	 * @param string $group One of the GROUP_* constants.
	 * @param array  $item  {
	 *     @type int|string $source_id   Source record ID (post, comment or table row).
	 *     @type string     $title       Item title (question text, course name, …).
	 *     @type string     $type        Source type label, e.g. "Open ended", "H5P", "Zoom meeting".
	 *     @type string     $reason      Why it was not imported.
	 *     @type string     $status      STATUS_* constant. Default STATUS_UNSUPPORTED.
	 *     @type string     $parent      Where it lives, e.g. "Quiz: Final exam".
	 *     @type string     $course      Course title it belongs to.
	 *     @type int        $post_id     Post ID to link to (the MasterStudy copy when one exists, otherwise the source post).
	 * }
	 */
	public static function add( string $group, array $item ): void {
		// Titles often come from get_the_title() (HTML-escaped); the UI escapes on output, so store plain text.
		$plain = function ( $value ) {
			return html_entity_decode( wp_strip_all_tags( (string) $value ), ENT_QUOTES, 'UTF-8' );
		};

		$item = array(
			'source_id' => (string) ( $item['source_id'] ?? '' ),
			'title'     => $plain( $item['title'] ?? '' ),
			'type'      => (string) ( $item['type'] ?? '' ),
			'reason'    => $plain( $item['reason'] ?? '' ),
			'status'    => (string) ( $item['status'] ?? self::STATUS_UNSUPPORTED ),
			'parent'    => $plain( $item['parent'] ?? '' ),
			'course'    => $plain( $item['course'] ?? '' ),
			'post_id'   => (int) ( $item['post_id'] ?? 0 ),
		);

		if ( '' === $item['title'] && $item['post_id'] ) {
			$item['title'] = $plain( get_post_field( 'post_title', $item['post_id'] ) );
		}

		/**
		 * Fires for every item added to the migration report.
		 *
		 * @param string $group Report group.
		 * @param array  $item  Normalized report item.
		 */
		do_action( 'masterstudy_lms_migration_tool_report_item', $group, $item );

		// A question MasterStudy cannot use must not appear as a live question in the question library.
		// Only MasterStudy copies are touched - a source post is never modified.
		if ( self::GROUP_QUESTIONS === $group && self::STATUS_UNSUPPORTED === $item['status'] && $item['post_id']
			&& PostType::QUESTION === get_post_type( $item['post_id'] ) && 'draft' !== get_post_status( $item['post_id'] )
			&& '' !== (string) get_post_meta( $item['post_id'], Target::SOURCE_META, true ) ) {
			wp_update_post(
				array(
					'ID'          => $item['post_id'],
					'post_status' => 'draft',
				)
			);
		}

		if ( '' === static::$session_id ) {
			return;
		}

		MigrationSession::append_report( static::$session_id, $group, $item, self::GROUP_LIMIT );
	}

	/**
	 * Record an item that failed inside the job engine (exception → rollback).
	 */
	public static function add_failure( string $step, int $item_id, string $message ): void {
		/**
		 * Steps whose item IDs are post IDs in every source LMS. Other steps iterate table rows,
		 * comments or users, so their IDs must not be resolved as posts.
		 *
		 * @param string[] $steps Step names.
		 */
		$post_steps = (array) apply_filters( 'masterstudy_lms_migration_tool_post_steps', array( 'courses', 'google_meet', 'assignments' ) );
		$post       = in_array( $step, $post_steps, true ) ? get_post( $item_id ) : null;

		static::add(
			self::STEP_GROUPS[ $step ] ?? self::GROUP_OTHER,
			array(
				'source_id' => $item_id,
				'title'     => $post ? $post->post_title : '',
				'type'      => $post ? $post->post_type : '',
				'reason'    => $message,
				'status'    => self::STATUS_FAILED,
				'post_id'   => $post ? $item_id : 0,
			)
		);
	}

	/**
	 * Per-group totals for the status endpoint.
	 *
	 * @return array<string, array{ total: int, failed: int, unsupported: int, partial: int }>
	 */
	public static function summary( array $session ): array {
		$summary = array();

		foreach ( (array) ( $session['report'] ?? array() ) as $group => $data ) {
			$summary[ $group ] = array(
				'total'       => (int) ( $data['total'] ?? 0 ),
				'failed'      => (int) ( $data['counts'][ self::STATUS_FAILED ] ?? 0 ),
				'unsupported' => (int) ( $data['counts'][ self::STATUS_UNSUPPORTED ] ?? 0 ),
				'partial'     => (int) ( $data['counts'][ self::STATUS_PARTIAL ] ?? 0 ),
			);
		}

		return $summary;
	}

	/**
	 * Items of one group, with links resolved for the current user.
	 */
	public static function items( array $session, string $group ): array {
		$items = (array) ( $session['report'][ $group ]['items'] ?? array() );

		return array_map(
			function ( $item ) {
				$item['edit_url'] = $item['post_id'] && get_post( $item['post_id'] ) ? (string) get_edit_post_link( $item['post_id'], 'raw' ) : '';
				$item['post_type'] = $item['post_id'] ? (string) get_post_type( $item['post_id'] ) : '';

				return $item;
			},
			array_values( $items )
		);
	}
}
