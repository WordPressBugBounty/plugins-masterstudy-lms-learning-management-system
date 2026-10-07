<?php
// phpcs:ignoreFile

namespace MasterStudy\Lms\MigrationTool;

defined( 'ABSPATH' ) || exit;

/**
 * "Delete migrated data": removes everything a migration from one source LMS created in MasterStudy.
 *
 * The migration only copies (the source LMS is never modified), so undoing it is deleting:
 * - every post it created (posts with `_masterstudy_migrated_from` = source AND a `_masterstudy_migrated_source_id`
 *   key - every record the migration creates has one: course/lesson/quiz/question copies,
 *   orders, reviews, certificates, bundles, meetings, submissions, attachments) with their meta, comments and terms;
 * - every MasterStudy row pointing at those posts (curriculum, enrollments, progress, quiz attempts and answers,
 *   assignment grades, order items, lesson notes, points, SCORM runtime, subscription plan items, WPML rows);
 * - what the Ledger recorded (coupons, subscription plans, instructor roles / statuses and MasterStudy user meta given
 *   to existing users - restored to their previous values).
 *
 * Files are deleted only when the migration created them (Target::OWN_FILE_META); other migrated attachments point at
 * files the source LMS still uses, so only their database rows are removed. Settings the migration enabled
 * (MasterStudy addons, coupon / notes settings) and MasterStudy categories it created are kept.
 *
 * Works in batches (run_batch() until it reports done), so it can run over REST without timeouts.
 */
class Undo {

	/**
	 * Posts deleted per batch.
	 */
	const BATCH_SIZE = 100;

	/**
	 * Option (+ source slug) with the course copies this undo deleted: only their wishlist / "last progress"
	 * entries are dropped at the end, entries that existed before the migration stay.
	 */
	const DELETED_COURSES_OPTION = 'masterstudy_lms_migration_undo_courses_';

	/**
	 * What would be deleted.
	 *
	 * @return array{ posts: int, types: array<string, int>, enrollments: int, coupons: int, plans: int, roles: int }
	 */
	public static function summary( string $source ): array {
		global $wpdb;

		$types = array();

		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT p.post_type, COUNT(*) AS n FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = %s AND s.meta_value = %s INNER JOIN {$wpdb->postmeta} i ON i.post_id = s.post_id AND i.meta_key = '_masterstudy_migrated_source_id' GROUP BY p.post_type", Target::SOURCE_META, $source ) ) as $row ) {
			$types[ $row->post_type ] = (int) $row->n;
		}

		$enrollments = 0;

		if ( static::table_exists( 'stm_lms_user_courses' ) ) {
			$enrollments = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}stm_lms_user_courses uc INNER JOIN {$wpdb->postmeta} s ON s.post_id = uc.course_id AND s.meta_key = %s AND s.meta_value = %s INNER JOIN {$wpdb->postmeta} i ON i.post_id = s.post_id AND i.meta_key = '_masterstudy_migrated_source_id'",
					Target::SOURCE_META,
					$source
				)
			);
		}

		$ledger = Ledger::get( $source );

		return array(
			'posts'       => array_sum( $types ),
			'types'       => $types,
			'enrollments' => $enrollments,
			'coupons'     => count( (array) ( $ledger['coupons'] ?? array() ) ),
			'plans'       => count( (array) ( $ledger['subscription_plans'] ?? array() ) ),
			'roles'       => count( (array) ( $ledger['instructor_roles'] ?? array() ) ),
		);
	}

	/**
	 * Whether there is anything to delete.
	 */
	public static function has_data( string $source ): bool {
		global $wpdb;

		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->postmeta} s INNER JOIN {$wpdb->postmeta} i ON i.post_id = s.post_id AND i.meta_key = '_masterstudy_migrated_source_id' WHERE s.meta_key = %s AND s.meta_value = %s LIMIT 1", Target::SOURCE_META, $source ) )
			|| array() !== Ledger::get( $source );
	}

	/**
	 * Delete one batch.
	 *
	 * @return array{ done: bool, deleted: int, remaining: int }
	 */
	public static function run_batch( string $source ): array {
		global $wpdb;

		Target::suppress_side_effects();

		$ids = array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT s.post_id FROM {$wpdb->postmeta} s INNER JOIN {$wpdb->postmeta} i ON i.post_id = s.post_id AND i.meta_key = '_masterstudy_migrated_source_id' WHERE s.meta_key = %s AND s.meta_value = %s ORDER BY s.post_id ASC LIMIT %d",
					Target::SOURCE_META,
					$source,
					self::BATCH_SIZE
				)
			)
		);

		if ( ! empty( $ids ) ) {
			static::remember_deleted_courses( $source, $ids );
			static::delete_related_rows( $ids );
			static::delete_posts( $ids );
			Target::reset_copies();

			return array(
				'done'      => false,
				'deleted'   => count( $ids ),
				'remaining' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} s INNER JOIN {$wpdb->postmeta} i ON i.post_id = s.post_id AND i.meta_key = '_masterstudy_migrated_source_id' WHERE s.meta_key = %s AND s.meta_value = %s", Target::SOURCE_META, $source ) ),
			);
		}

		static::undo_ledger( $source );
		static::clean_wishlists( $source );
		static::delete_sessions( $source );
		Target::recalculate_ratings();

		/**
		 * Fires after all migrated data of a source LMS was deleted.
		 *
		 * @param string $source Source LMS slug.
		 */
		do_action( 'masterstudy_lms_migration_tool_undone', $source );

		Helper::log( 'info', sprintf( 'Migration [%s]: migrated data deleted.', $source ) );

		return array(
			'done'      => true,
			'deleted'   => 0,
			'remaining' => 0,
		);
	}

	/**
	 * MasterStudy rows that point at the given (migrated) posts.
	 *
	 * @param int[] $ids Post IDs.
	 */
	private static function delete_related_rows( array $ids ): void {
		global $wpdb;

		$in = implode( ',', array_map( 'intval', $ids ) );
		$p  = $wpdb->prefix;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $in is a list of integers.
		if ( static::table_exists( 'stm_lms_curriculum_sections' ) ) {
			$wpdb->query( "DELETE m FROM {$p}stm_lms_curriculum_materials m INNER JOIN {$p}stm_lms_curriculum_sections s ON s.id = m.section_id WHERE s.course_id IN ({$in})" );
			$wpdb->query( "DELETE FROM {$p}stm_lms_curriculum_materials WHERE post_id IN ({$in})" );
			$wpdb->query( "DELETE FROM {$p}stm_lms_curriculum_sections WHERE course_id IN ({$in})" );
		}

		if ( static::table_exists( 'stm_lms_user_courses' ) ) {
			if ( static::table_exists( 'stm_lms_user_course_scorm' ) ) {
				$wpdb->query( "DELETE sc FROM {$p}stm_lms_user_course_scorm sc INNER JOIN {$p}stm_lms_user_courses uc ON uc.user_course_id = sc.user_course_id WHERE uc.course_id IN ({$in})" );
			}

			$wpdb->query( "DELETE FROM {$p}stm_lms_user_courses WHERE course_id IN ({$in})" );
		}

		$by_column = array(
			'stm_lms_user_lessons'            => array( 'lesson_id', 'course_id' ),
			'stm_lms_user_quizzes'            => array( 'quiz_id', 'course_id' ),
			'stm_lms_user_answers'            => array( 'quiz_id', 'course_id' ),
			'stm_lms_user_quizzes_times'      => array( 'quiz_id' ),
			'stm_lms_user_assignments'        => array( 'assignment_id', 'course_id', 'user_assignment_id' ),
			'stm_lms_user_assignments_times'  => array( 'assignment_id' ),
			'stm_lms_order_items'             => array( 'order_id' ),
			'stm_lms_lesson_notes'            => array( 'lesson_id', 'course_id' ),
			'stm_lms_user_points'             => array( 'course_id' ),
			'stm_lms_user_bookmarks'          => array( 'course_id', 'lesson_id' ),
			'stm_lms_user_cart'               => array( 'item_id' ),
		);

		foreach ( $by_column as $table => $columns ) {
			if ( ! static::table_exists( $table ) ) {
				continue;
			}

			$where = implode(
				' OR ',
				array_map(
					function ( $column ) use ( $in ) {
						return "`{$column}` IN ({$in})";
					},
					$columns
				)
			);

			$wpdb->query( "DELETE FROM {$p}{$table} WHERE {$where}" );
		}

		if ( static::table_exists( 'stm_lms_subscription_plan_items' ) ) {
			$wpdb->query( "DELETE FROM {$p}stm_lms_subscription_plan_items WHERE object_type = 'course' AND object_id IN ({$in})" );
		}

		// Per-course user meta MasterStudy keeps for these courses (certificate codes).
		$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ('" . implode( "','", array_map( function ( $id ) { return 'stm_lms_certificate_code_' . (int) $id; }, $ids ) ) . "')" );

		if ( static::table_exists( 'icl_translations' ) ) {
			$wpdb->query( "DELETE FROM {$p}icl_translations WHERE element_id IN ({$in}) AND element_type LIKE 'post\\_%'" );
		}
		// phpcs:enable
	}

	/**
	 * Delete migrated posts. Attachments whose file the source LMS still uses lose only their database rows.
	 *
	 * @param int[] $ids Post IDs.
	 */
	private static function delete_posts( array $ids ): void {
		global $wpdb;

		foreach ( $ids as $id ) {
			$type = get_post_type( $id );

			if ( false === $type ) {
				// Orphaned meta of a post that no longer exists.
				$wpdb->delete( $wpdb->postmeta, array( 'post_id' => $id ), array( '%d' ) );
				continue;
			}

			if ( 'attachment' === $type ) {
				if ( get_post_meta( $id, Target::OWN_FILE_META, true ) ) {
					wp_delete_attachment( $id, true );
					continue;
				}

				$wpdb->delete( $wpdb->postmeta, array( 'post_id' => $id ), array( '%d' ) );
				$wpdb->delete( $wpdb->posts, array( 'ID' => $id ), array( '%d' ) );
				clean_post_cache( $id );
				continue;
			}

			// Children (e.g. submission attachments) are re-parented by WordPress and deleted in their own turn.
			if ( ! wp_delete_post( $id, true ) ) {
				// A post type unknown on this request (addon off): remove the rows directly.
				$wpdb->delete( $wpdb->postmeta, array( 'post_id' => $id ), array( '%d' ) );
				$wpdb->delete( $wpdb->comments, array( 'comment_post_ID' => $id ), array( '%d' ) );
				$wpdb->delete( $wpdb->term_relationships, array( 'object_id' => $id ), array( '%d' ) );
				$wpdb->delete( $wpdb->posts, array( 'ID' => $id ), array( '%d' ) );
				clean_post_cache( $id );
			}
		}
	}

	/**
	 * Coupons, subscription plans, instructor roles and statuses recorded by the Ledger.
	 */
	private static function undo_ledger( string $source ): void {
		global $wpdb;

		$ledger = Ledger::get( $source );
		$p      = $wpdb->prefix;

		$coupons = array_map( 'intval', array_keys( (array) ( $ledger['coupons'] ?? array() ) ) );

		if ( $coupons && static::table_exists( 'stm_lms_coupons' ) ) {
			$wpdb->query( "DELETE FROM {$p}stm_lms_coupons WHERE id IN (" . implode( ',', $coupons ) . ')' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		$plans = array_map( 'intval', array_keys( (array) ( $ledger['subscription_plans'] ?? array() ) ) );

		if ( $plans && static::table_exists( 'stm_lms_subscription_plans' ) ) {
			$list = implode( ',', $plans );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( static::table_exists( 'stm_lms_subscription_plan_items' ) ) {
				$wpdb->query( "DELETE FROM {$p}stm_lms_subscription_plan_items WHERE plan_id IN ({$list})" );
			}
			$wpdb->query( "DELETE FROM {$p}stm_lms_subscription_plans WHERE id IN ({$list})" );
			// phpcs:enable
		}

		foreach ( array_keys( (array) ( $ledger['instructor_roles'] ?? array() ) ) as $user_id ) {
			$user = get_userdata( (int) $user_id );

			if ( $user && in_array( Target::INSTRUCTOR_ROLE, (array) $user->roles, true ) ) {
				$user->remove_role( Target::INSTRUCTOR_ROLE );
			}
		}

		// The default role given to users whose only roles belonged to the (deactivated) source LMS.
		foreach ( (array) ( $ledger['default_roles'] ?? array() ) as $user_id => $role ) {
			$user = get_userdata( (int) $user_id );

			if ( $user && is_string( $role ) && '' !== $role ) {
				$user->remove_role( $role );
			}
		}

		foreach ( (array) ( $ledger['submission_status'] ?? array() ) as $user_id => $previous ) {
			if ( '' === (string) $previous ) {
				delete_user_meta( (int) $user_id, 'submission_status' );
				delete_user_meta( (int) $user_id, 'submission_date' );
			} else {
				update_user_meta( (int) $user_id, 'submission_status', (string) $previous );
			}
		}

		foreach ( $ledger as $type => $entries ) {
			if ( 0 !== strpos( (string) $type, 'user_meta:' ) ) {
				continue;
			}

			$meta_key = substr( (string) $type, strlen( 'user_meta:' ) );

			foreach ( (array) $entries as $user_id => $previous ) {
				if ( null === $previous ) {
					delete_user_meta( (int) $user_id, $meta_key );
				} else {
					update_user_meta( (int) $user_id, $meta_key, $previous );
				}
			}
		}

		Ledger::clear( $source );
	}

	/**
	 * @param int[] $ids Post IDs about to be deleted.
	 */
	private static function remember_deleted_courses( string $source, array $ids ): void {
		$courses = array_values(
			array_filter(
				$ids,
				function ( $id ) {
					return 'stm-courses' === get_post_type( $id );
				}
			)
		);

		if ( $courses ) {
			$option = self::DELETED_COURSES_OPTION . $source;
			update_option( $option, array_values( array_unique( array_merge( array_map( 'intval', (array) get_option( $option, array() ) ), $courses ) ) ), false );
		}
	}

	/**
	 * Drop the deleted course copies from MasterStudy wishlists and from the per-course "last progress" times.
	 */
	private static function clean_wishlists( string $source ): void {
		global $wpdb;

		$option  = self::DELETED_COURSES_OPTION . $source;
		$deleted = array_flip( array_map( 'intval', (array) get_option( $option, array() ) ) );

		delete_option( $option );

		if ( ! $deleted ) {
			return;
		}

		foreach ( (array) $wpdb->get_results( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'stm_lms_wishlist'" ) as $row ) {
			$list = maybe_unserialize( $row->meta_value );

			if ( ! is_array( $list ) ) {
				continue;
			}

			$kept = array_values(
				array_filter(
					$list,
					function ( $course_id ) use ( $deleted ) {
						return ! isset( $deleted[ (int) $course_id ] );
					}
				)
			);

			if ( count( $kept ) !== count( $list ) ) {
				update_user_meta( (int) $row->user_id, 'stm_lms_wishlist', $kept );
			}
		}

		// `last_progress_time` = course ID => time (Target::recalculate_progress()).
		foreach ( (array) $wpdb->get_results( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'last_progress_time'" ) as $row ) {
			$times = maybe_unserialize( $row->meta_value );

			if ( ! is_array( $times ) ) {
				continue;
			}

			$kept = array_filter(
				$times,
				function ( $course_id ) use ( $deleted ) {
					return ! isset( $deleted[ (int) $course_id ] );
				},
				ARRAY_FILTER_USE_KEY
			);

			if ( ! $kept ) {
				delete_user_meta( (int) $row->user_id, 'last_progress_time' );
			} elseif ( count( $kept ) !== count( $times ) ) {
				update_user_meta( (int) $row->user_id, 'last_progress_time', $kept );
			}
		}
	}

	/**
	 * Forget the finished sessions of this source (their reports point at deleted posts).
	 */
	private static function delete_sessions( string $source ): void {
		global $wpdb;

		foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( MigrationSession::SESSION_PREFIX ) . '%' ) ) as $option_name ) {
			$session = get_option( $option_name );

			if ( is_array( $session ) && ( $session['lms_slug'] ?? '' ) === $source && 'running' !== ( $session['status'] ?? '' ) ) {
				delete_option( $option_name );
			}
		}
	}

	private static function table_exists( string $table ): bool {
		global $wpdb;

		static $cache = array();

		if ( ! isset( $cache[ $table ] ) ) {
			$cache[ $table ] = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . $table ) ) );
		}

		return $cache[ $table ];
	}
}
