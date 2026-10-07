<?php

namespace MasterStudy\Lms\MigrationTool;

defined( 'ABSPATH' ) || exit;

use MasterStudy\Lms\Plugin\PostType;

/**
 * Write helpers for MasterStudy LMS Pro / Pro Plus features.
 *
 * Every helper writes storage directly (never through REST controllers or save hooks) so a
 * bulk import never calls the Zoom / Google APIs, creates WP users or sends emails. Callers
 * must check pro_active() / plus_active() first and report the item when the check fails.
 * Addons are requested through Helper::request_addon() — they are enabled after the batch
 * commits, because enabling creates tables (implicit commit).
 */
class ProTarget {

	public static function pro_active(): bool {
		return defined( 'STM_LMS_PRO_PATH' );
	}

	public static function plus_active(): bool {
		return static::pro_active() && defined( 'STM_LMS_PLUS_ENABLED' ) && STM_LMS_PLUS_ENABLED;
	}

	/**
	 * Milliseconds timestamp of UTC midnight for a site-local date — the format MasterStudy uses
	 * for every "date" field (drip, stream, zoom, google meet, sale dates).
	 *
	 * @param int $timestamp Unix time of the moment.
	 */
	public static function date_ms( int $timestamp, string $timezone = '' ): int {
		$local = wp_date( 'Y-m-d', $timestamp, static::zone( $timezone ) );

		return (int) strtotime( $local . ' 00:00:00 UTC' ) * 1000;
	}

	/**
	 * "H:i" in the given timezone (site time by default).
	 */
	public static function time_hm( int $timestamp, string $timezone = '' ): string {
		return (string) wp_date( 'H:i', $timestamp, static::zone( $timezone ) );
	}

	/**
	 * A valid IANA timezone name: the given one, else the site timezone, else UTC.
	 */
	public static function timezone( string $timezone = '' ): string {
		foreach ( array( $timezone, wp_timezone_string() ) as $zone ) {
			if ( '' !== $zone && in_array( $zone, timezone_identifiers_list( \DateTimeZone::ALL_WITH_BC ), true ) ) {
				return $zone;
			}
		}

		return 'UTC';
	}

	private static function zone( string $timezone ): ?\DateTimeZone {
		return '' === $timezone ? null : new \DateTimeZone( static::timezone( $timezone ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Assignments (Pro addon "assignments")
	|--------------------------------------------------------------------------
	*/

	/**
	 * Set assignment settings on an (already converted) stm-assignments post.
	 *
	 * @param array $data Keys: attempts (int, 0 = global setting), passing_grade (percent),
	 *                    time_limit (float), time_limit_unit (minutes|hours|days|weeks), files (int[]).
	 */
	public static function set_assignment( int $assignment_id, array $data ): void {
		Helper::request_addon( 'assignments' );

		update_post_meta( $assignment_id, 'assignment_tries', ! empty( $data['attempts'] ) ? (int) $data['attempts'] : '' );

		if ( isset( $data['passing_grade'] ) && '' !== (string) $data['passing_grade'] ) {
			update_post_meta( $assignment_id, 'passing_grade', (int) round( (float) $data['passing_grade'] ) );
		}

		if ( ! empty( $data['time_limit'] ) ) {
			$unit = in_array( $data['time_limit_unit'] ?? '', array( 'minutes', 'hours', 'days', 'weeks' ), true ) ? $data['time_limit_unit'] : 'minutes';
			update_post_meta( $assignment_id, 'assignment_time_limit', (float) $data['time_limit'] );
			update_post_meta( $assignment_id, 'assignment_time_limit_unit', $unit );
		}

		$files = array_values( array_filter( array_map( 'intval', (array) ( $data['files'] ?? array() ) ) ) );

		if ( ! empty( $files ) ) {
			update_post_meta( $assignment_id, 'assignment_files', wp_json_encode( $files ) );
		}
	}

	/**
	 * Create a student assignment submission (stm-user-assignment post + stm_lms_user_assignments row).
	 * Submissions must be added in chronological order per (assignment, student). Idempotent per source_id.
	 *
	 * @param array  $data   Keys: assignment_id, course_id, student_id, content (answer HTML), status
	 *                       (draft|pending|passed|not_passed), grade (percent|null), review (instructor comment),
	 *                       attachments (int[] student files), instructor_attachments (int[]), date (Unix time),
	 *                       source_id (string, idempotency key), copy_attachments (bool, optional: attach new
	 *                       attachment records instead of re-parenting the given source attachments — copy mode).
	 * @param string $source Source LMS slug.
	 * @return int Submission post ID.
	 */
	public static function add_assignment_submission( array $data, string $source ): int {
		global $wpdb;

		Helper::request_addon( 'assignments' );

		$assignment_id = (int) $data['assignment_id'];
		$student_id    = (int) $data['student_id'];
		$course_id     = (int) $data['course_id'];
		$status        = in_array( $data['status'] ?? '', array( 'draft', 'pending', 'passed', 'not_passed' ), true ) ? $data['status'] : 'pending';
		$date          = (int) ( $data['date'] ?? time() );
		$source_id     = (string) ( $data['source_id'] ?? '' );

		if ( '' !== $source_id ) {
			$existing = Target::find_migrated_post( PostType::USER_ASSIGNMENT, $source, $source_id );

			if ( $existing ) {
				return $existing;
			}
		}

		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'stm_lms_user_assignments' ) ) ) {
			if ( function_exists( 'stm_lms_user_assignments_table' ) ) {
				stm_lms_user_assignments_table();
			}

			if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'stm_lms_user_assignments' ) ) ) {
				throw new \Exception( 'Assignment submission not imported: the assignments table is missing — enable the Assignments addon and run the migration again.' );
			}
		}

		$student = get_userdata( $student_id );

		if ( ! $student ) {
			throw new \Exception( sprintf( 'Assignment submission not imported: student #%d no longer exists.', $student_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$try_num = 1 + (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} a ON a.post_id = p.ID AND a.meta_key = 'assignment_id' AND a.meta_value = %d
				 INNER JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = 'student_id' AND s.meta_value = %d
				 WHERE p.post_type = %s",
				$assignment_id,
				$student_id,
				PostType::USER_ASSIGNMENT
			)
		);

		// The status/email save hook would overwrite the imported status and grade.
		$hook_removed = remove_action( 'save_post', 'stm_lms_student_assignments_assignment_saved', 99999 );

		$post_status = 'draft' === $status ? 'draft' : ( 'pending' === $status ? 'pending' : 'publish' );

		$submission_id = Target::insert_post(
			array(
				'post_type'     => PostType::USER_ASSIGNMENT,
				'post_status'   => $post_status,
				'post_title'    => sprintf( '%s on "%s"', $student->user_login, get_post_field( 'post_title', $assignment_id ) ),
				'post_content'  => wp_kses_post( (string) ( $data['content'] ?? '' ) ),
				'post_author'   => $student_id,
				'post_date'     => wp_date( 'Y-m-d H:i:s', $date ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $date ),
				'meta_input'    => array(
					'assignment_id'          => $assignment_id,
					'student_id'             => $student_id,
					'course_id'              => $course_id,
					'try_num'                => $try_num,
					'status'                 => $status,
					'student_attachments'    => array_values( array_map( 'intval', (array) ( $data['attachments'] ?? array() ) ) ),
					'instructor_attachments' => array_values( array_map( 'intval', (array) ( $data['instructor_attachments'] ?? array() ) ) ),
					'editor_comment'         => wp_kses_post( (string) ( $data['review'] ?? '' ) ),
					'who_view'               => 1,
				),
			),
			$source,
			$source_id
		);

		if ( $hook_removed ) {
			add_action( 'save_post', 'stm_lms_student_assignments_assignment_saved', 99999, 3 );
		}

		// Attachments are looked up by post_parent = submission.
		$owners = array(
			'attachments'            => $student_id,
			'instructor_attachments' => (int) get_post_field( 'post_author', $assignment_id ),
		);

		foreach ( $owners as $key => $owner_id ) {
			// Optional 'copy_attachments' (copy mode): the attachments belong to the source LMS and are never modified —
			// each one gets a new attachment record owned by the submission (same file, not duplicated).
			if ( ! empty( $data['copy_attachments'] ) ) {
				$copies = array();

				foreach ( (array) ( $data[ $key ] ?? array() ) as $attachment_id ) {
					$copy = static::copy_attachment_record( (int) $attachment_id, $submission_id, $owner_id, $source );

					if ( $copy ) {
						$copies[] = $copy;
					}
				}

				update_post_meta( $submission_id, 'attachments' === $key ? 'student_attachments' : 'instructor_attachments', $copies );
				continue;
			}

			foreach ( (array) ( $data[ $key ] ?? array() ) as $attachment_id ) {
				if ( (int) $attachment_id && 'attachment' === get_post_type( (int) $attachment_id ) ) {
					$wpdb->update( $wpdb->posts, array( 'post_parent' => $submission_id ), array( 'ID' => (int) $attachment_id ) );
					update_post_meta( (int) $attachment_id, 'attachment_author', $owner_id );
					clean_post_cache( (int) $attachment_id );
				}
			}
		}

		$grade = isset( $data['grade'] ) && null !== $data['grade'] ? max( 0, min( 100, (int) round( (float) $data['grade'] ) ) ) : null;

		if ( null === $grade && in_array( $status, array( 'passed', 'not_passed' ), true ) ) {
			$grade = 'passed' === $status ? 100 : 0;
		}

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'stm_lms_user_assignments',
			array(
				'user_id'            => $student_id,
				'course_id'          => $course_id,
				'assignment_id'      => $assignment_id,
				'user_assignment_id' => $submission_id,
				'grade'              => $grade,
				'status'             => $status,
				'updated_at'         => $date,
			),
			array( '%d', '%d', '%d', '%d', null === $grade ? null : '%d', '%s', '%d' )
		);

		if ( false === $inserted ) {
			throw new \Exception( sprintf( 'Assignment submission not imported: %s', $wpdb->last_error ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		return $submission_id;
	}

	/**
	 * New attachment record for a source attachment, owned by a MasterStudy post (MasterStudy finds assignment
	 * files by post_parent). The source attachment is not modified and the file itself is shared, not copied.
	 * Idempotent per (attachment, parent).
	 *
	 * @param int $attachment_id Source attachment ID.
	 * @param int $parent_id     MasterStudy post the copy is attached to.
	 * @param int $owner_id      User who owns the file (`attachment_author`).
	 * @return int Attachment copy ID, 0 when the source is not an attachment.
	 */
	private static function copy_attachment_record( int $attachment_id, int $parent_id, int $owner_id, string $source ): int {
		$attachment = $attachment_id ? get_post( $attachment_id ) : null;

		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return 0;
		}

		$copy_id = Target::insert_post(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_title'     => $attachment->post_title,
				'post_content'   => $attachment->post_content,
				'post_excerpt'   => $attachment->post_excerpt,
				'post_mime_type' => $attachment->post_mime_type,
				'post_author'    => $owner_id,
				'post_parent'    => $parent_id,
				'post_date'      => $attachment->post_date,
				'post_date_gmt'  => $attachment->post_date_gmt,
				'guid'           => $attachment->guid,
			),
			$source,
			'attachment-' . $attachment_id . '-' . $parent_id
		);

		if ( metadata_exists( 'post', $copy_id, '_wp_attached_file' ) ) {
			return $copy_id;
		}

		// The copy gets its own file: deleting it in MasterStudy must never delete the file the source LMS uses.
		$relative = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
		$uploads  = wp_upload_dir( null, false );
		$path     = '' !== $relative ? trailingslashit( $uploads['basedir'] ) . $relative : '';

		if ( '' !== $path && is_readable( $path ) ) {
			$dir      = dirname( $path );
			$filename = wp_unique_filename( $dir, pathinfo( $path, PATHINFO_FILENAME ) . '-ms.' . pathinfo( $path, PATHINFO_EXTENSION ) );

			if ( copy( $path, trailingslashit( $dir ) . $filename ) ) {
				$relative = ltrim( trailingslashit( dirname( $relative ) ) . $filename, './' );
				$relative = '.' === dirname( (string) get_post_meta( $attachment_id, '_wp_attached_file', true ) ) ? $filename : $relative;

				Target::update_unfiltered(
					array(
						'ID'   => $copy_id,
						'guid' => trailingslashit( $uploads['baseurl'] ) . $relative,
					)
				);
				update_post_meta( $copy_id, '_wp_attached_file', $relative );
				update_post_meta( $copy_id, Target::OWN_FILE_META, 1 );

				if ( 0 === strpos( (string) $attachment->post_mime_type, 'image/' ) ) {
					if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
						require_once ABSPATH . 'wp-admin/includes/image.php';
					}

					wp_update_attachment_metadata( $copy_id, wp_generate_attachment_metadata( $copy_id, trailingslashit( $uploads['basedir'] ) . $relative ) );
				}
			}
		}

		update_post_meta( $copy_id, 'attachment_author', $owner_id );

		return $copy_id;
	}

	/*
	|--------------------------------------------------------------------------
	| Live lessons
	|--------------------------------------------------------------------------
	*/

	/**
	 * Turn a lesson into a Zoom conference lesson for an EXISTING meeting (no Zoom API call).
	 *
	 * @param array $data Keys: meeting_id (numeric Zoom id), join_url, password, start (Unix time),
	 *                    duration (minutes), timezone (IANA), agenda, host_id (WP user).
	 * @return bool False when the meeting id is not numeric (caller should fall back and report).
	 */
	public static function set_zoom_lesson( int $lesson_id, array $data, string $source ): bool {
		$meeting_id = (int) preg_replace( '/\D/', '', (string) ( $data['meeting_id'] ?? '' ) );

		if ( ! $meeting_id ) {
			return false;
		}

		Helper::request_addon( 'zoom_conference' );

		$start    = (int) ( $data['start'] ?? 0 );
		$timezone = static::timezone( (string) ( $data['timezone'] ?? '' ) );
		$password = (string) ( $data['password'] ?? '' );

		$meeting_post = Target::insert_post(
			array(
				'post_type'    => 'ms-zoom',
				'post_status'  => 'publish',
				'post_title'   => get_post_field( 'post_title', $lesson_id ),
				'post_author'  => (int) ( $data['host_id'] ?? get_post_field( 'post_author', $lesson_id ) ),
				'meta_input'   => array(
					'stm_date'      => $start ? static::date_ms( $start, $timezone ) : '',
					'stm_time'      => $start ? static::time_hm( $start, $timezone ) : '',
					'stm_timezone'  => $timezone,
					'stm_password'  => $password,
					'stm_agenda'    => wp_kses_post( (string) ( $data['agenda'] ?? '' ) ),
					'stm_zoom_data' => array(
						'id'       => $meeting_id,
						'join_url' => esc_url_raw( (string) ( $data['join_url'] ?? 'https://zoom.us/j/' . $meeting_id ) ),
						'password' => $password,
						'type'     => 2,
						'duration' => (int) ( $data['duration'] ?? 0 ),
					),
				),
			),
			$source,
			'zoom-lesson-' . $lesson_id
		);

		update_post_meta( $lesson_id, 'type', 'zoom_conference' );
		update_post_meta( $lesson_id, 'meeting_created', $meeting_post );
		update_post_meta( $lesson_id, 'timezone', $timezone );
		update_post_meta( $lesson_id, 'stm_password', $password );

		if ( $start ) {
			update_post_meta( $lesson_id, 'stream_start_date', static::date_ms( $start, $timezone ) );
			update_post_meta( $lesson_id, 'stream_start_time', static::time_hm( $start, $timezone ) );

			if ( ! empty( $data['duration'] ) ) {
				$end = $start + (int) $data['duration'] * MINUTE_IN_SECONDS;
				update_post_meta( $lesson_id, 'stream_end_date', static::date_ms( $end, $timezone ) );
				update_post_meta( $lesson_id, 'stream_end_time', static::time_hm( $end, $timezone ) );
			}
		}

		delete_post_meta( $lesson_id, 'video_type' );

		return true;
	}

	/**
	 * Turn a lesson into a YouTube live-stream lesson (addon "live_streams"). Only YouTube URLs render.
	 *
	 * @return bool False when the URL is not a YouTube URL.
	 */
	public static function set_stream_lesson( int $lesson_id, string $url, int $start = 0, int $end = 0 ): bool {
		if ( false === strpos( $url, 'youtube.com' ) && false === strpos( $url, 'youtu.be' ) ) {
			return false;
		}

		Helper::request_addon( 'live_streams' );

		update_post_meta( $lesson_id, 'type', 'stream' );
		update_post_meta( $lesson_id, 'lesson_stream_url', esc_url_raw( $url ) );

		if ( $start ) {
			update_post_meta( $lesson_id, 'stream_start_date', static::date_ms( $start ) );
			update_post_meta( $lesson_id, 'stream_start_time', static::time_hm( $start ) );
		}

		if ( $end ) {
			update_post_meta( $lesson_id, 'stream_end_date', static::date_ms( $end ) );
			update_post_meta( $lesson_id, 'stream_end_time', static::time_hm( $end ) );
		}

		delete_post_meta( $lesson_id, 'video_type' );

		return true;
	}

	/**
	 * Write Google Meet data on an (already converted) stm-google-meets post — no Google API call.
	 * Plus only.
	 *
	 * @param array $data Keys: url (join link), summary, start, end (Unix times), timezone.
	 */
	public static function set_google_meet( int $meet_id, array $data ): void {
		Helper::request_addon( 'google_meet' );

		$start    = (int) ( $data['start'] ?? 0 );
		$end      = (int) ( $data['end'] ?? 0 );
		$timezone = static::timezone( (string) ( $data['timezone'] ?? '' ) );

		update_post_meta( $meet_id, 'stm_gma_summary', wp_strip_all_tags( (string) ( $data['summary'] ?? '' ) ) );
		update_post_meta( $meet_id, 'stm_gma_timezone', $timezone );
		update_post_meta( $meet_id, 'stm_gma_visibility', 'default' );
		update_post_meta( $meet_id, 'stm_gma_attendees', '' );

		if ( $start ) {
			update_post_meta( $meet_id, 'stm_gma_start_date', static::date_ms( $start, $timezone ) );
			update_post_meta( $meet_id, 'stm_gma_start_time', static::time_hm( $start, $timezone ) );
		}

		if ( $end ) {
			update_post_meta( $meet_id, 'stm_gma_end_date', static::date_ms( $end, $timezone ) );
			update_post_meta( $meet_id, 'stm_gma_end_time', static::time_hm( $end, $timezone ) );
		}

		if ( ! empty( $data['url'] ) ) {
			update_post_meta( $meet_id, 'google_meet_link', esc_url_raw( (string) $data['url'] ) );
		}
	}

	/**
	 * Remove the Google Meet save hook that calls the Google API; returns a restore callback.
	 */
	public static function mute_google_meet_hooks(): callable {
		global $wp_filter;

		$class   = 'MasterStudy\\Lms\\Pro\\AddonsPlus\\GoogleMeet\\Services\\GoogleCalendarEvent';
		$removed = array();

		foreach ( (array) ( isset( $wp_filter['save_post'] ) ? $wp_filter['save_post']->callbacks : array() ) as $priority => $callbacks ) {
			foreach ( $callbacks as $entry ) {
				if ( is_array( $entry['function'] ) && is_object( $entry['function'][0] ) && is_a( $entry['function'][0], $class ) ) {
					$removed[] = array( $entry['function'], $priority, (int) $entry['accepted_args'] );
				}
			}
		}

		foreach ( $removed as $callback ) {
			remove_action( 'save_post', $callback[0], $callback[1] );
		}

		return function () use ( $removed ) {
			foreach ( $removed as $callback ) {
				add_action( 'save_post', $callback[0], $callback[1], $callback[2] );
			}
		};
	}

	/**
	 * Audio lesson (Plus addon "audio_lesson").
	 *
	 * @param string     $source file|ext_link|embed|shortcode.
	 * @param string|int $value  Attachment id (file), URL, embed HTML or shortcode.
	 */
	public static function set_audio_lesson( int $lesson_id, string $source, $value, int $required_progress = 0 ): void {
		Helper::request_addon( 'audio_lesson' );

		$keys = array(
			'file'      => 'file',
			'ext_link'  => 'lesson_ext_link_url',
			'embed'     => 'lesson_embed_ctx',
			'shortcode' => 'lesson_shortcode',
		);

		$source = isset( $keys[ $source ] ) ? $source : 'ext_link';

		update_post_meta( $lesson_id, 'type', 'audio' );
		update_post_meta( $lesson_id, 'audio_type', $source );
		update_post_meta( $lesson_id, $keys[ $source ], 'file' === $source ? (int) $value : $value );
		update_post_meta( $lesson_id, 'audio_required_progress', max( 0, min( 100, $required_progress ) ) );
		delete_post_meta( $lesson_id, 'video_type' );
	}

	/*
	|--------------------------------------------------------------------------
	| Course access rules
	|--------------------------------------------------------------------------
	*/

	/**
	 * Unlock a curriculum item on a date (addon "sequential_drip_content").
	 */
	public static function drip_on_date( int $item_id, int $timestamp ): void {
		Helper::request_addon( 'sequential_drip_content' );
		static::enable_date_lock();

		update_post_meta( $item_id, 'lesson_start_date', static::date_ms( $timestamp ) );
		update_post_meta( $item_id, 'lesson_start_time', static::time_hm( $timestamp ) );
	}

	/**
	 * Unlock a curriculum item N days after enrollment.
	 */
	public static function drip_after_days( int $item_id, int $days ): void {
		if ( $days < 1 ) {
			return;
		}

		Helper::request_addon( 'sequential_drip_content' );
		static::enable_date_lock();

		update_post_meta( $item_id, 'lesson_lock_from_start', 'on' );
		update_post_meta( $item_id, 'lesson_lock_start_days', $days );
	}

	/**
	 * Lock course items until the previous one is completed.
	 */
	public static function sequential_course( int $course_id ): void {
		Helper::request_addon( 'sequential_drip_content' );
		update_post_meta( $course_id, 'lock_lesson', 'on' );
	}

	/**
	 * Upcoming ("coming soon") course (Plus addon "coming_soon"). Schedules the addon's own
	 * availability event when a future start is given; no student e-mails are sent.
	 *
	 * @param array $data Keys: start (Unix time the course opens, 0 = no date), show_price (bool),
	 *                    show_details (bool), preordering (bool).
	 */
	public static function set_coming_soon( int $course_id, array $data ): void {
		Helper::request_addon( 'coming_soon' );

		$start = (int) ( $data['start'] ?? 0 );

		update_post_meta( $course_id, 'coming_soon_status', '1' );
		update_post_meta( $course_id, 'coming_soon_show_course_price', ! empty( $data['show_price'] ) ? '1' : '' );
		update_post_meta( $course_id, 'coming_soon_show_course_details', ! empty( $data['show_details'] ) ? '1' : '' );
		update_post_meta( $course_id, 'coming_soon_preordering', ! empty( $data['preordering'] ) ? '1' : '' );

		if ( $start > 0 ) {
			update_post_meta( $course_id, 'coming_soon_date', static::date_ms( $start ) );
			update_post_meta( $course_id, 'coming_soon_time', static::time_hm( $start ) );

			if ( $start > time() && ! wp_next_scheduled( 'masterstudy_lms_coming_soon_course', array( $course_id ) ) ) {
				wp_schedule_single_event( $start, 'masterstudy_lms_coming_soon_course', array( $course_id ) );
			}
		}
	}

	/**
	 * Parent → children unlock map ("child unlocks after parent is completed").
	 *
	 * @param array $map List of ['parent' => post_id, 'children' => int[]].
	 */
	public static function drip_after_items( int $course_id, array $map ): void {
		$data = array();

		foreach ( $map as $row ) {
			$parent = (int) ( $row['parent'] ?? 0 );

			if ( ! $parent ) {
				continue;
			}

			$data[] = array(
				'parent' => array(
					'id'        => $parent,
					'post_type' => get_post_type( $parent ),
					'title'     => get_post_field( 'post_title', $parent ),
				),
				'childs' => array_values(
					array_map(
						function ( $child ) {
							return array(
								'id'        => (int) $child,
								'post_type' => get_post_type( (int) $child ),
								'title'     => get_post_field( 'post_title', (int) $child ),
							);
						},
						array_filter( (array) ( $row['children'] ?? array() ) )
					)
				),
			);
		}

		if ( empty( $data ) ) {
			return;
		}

		Helper::request_addon( 'sequential_drip_content' );
		update_post_meta( $course_id, 'drip_content', wp_json_encode( $data ) );
	}

	/**
	 * Date/day drip rules only take effect with the global "lock before start" setting on.
	 */
	private static function enable_date_lock(): void {
		$settings = get_option( 'stm_lms_settings', array() );

		if ( is_array( $settings ) && empty( $settings['lock_before_start'] ) ) {
			$settings['lock_before_start'] = true;
			update_option( 'stm_lms_settings', $settings );
		}
	}

	/**
	 * Course prerequisites (addon "prerequisite").
	 *
	 * @param int[] $course_ids    Required (already migrated) course IDs.
	 * @param float $passing_level Required progress percent in each (0 = enrollment is enough).
	 */
	public static function set_prerequisites( int $course_id, array $course_ids, float $passing_level = 0 ): void {
		$course_ids = array_values( array_unique( array_filter( array_map( 'intval', $course_ids ) ) ) );

		if ( empty( $course_ids ) ) {
			return;
		}

		Helper::request_addon( 'prerequisite' );

		update_post_meta( $course_id, 'prerequisites', implode( ',', $course_ids ) );
		update_post_meta( $course_id, 'prerequisite_passing_level', $passing_level > 0 ? $passing_level : '' );
	}

	/**
	 * Co-instructor (addon "multi_instructors"). MasterStudy keeps ONE co-instructor.
	 */
	public static function set_co_instructor( int $course_id, int $user_id ): void {
		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			return;
		}

		Helper::request_addon( 'multi_instructors' );
		Target::make_instructor( $user_id );
		update_post_meta( $course_id, 'co_instructor', $user_id );
	}

	/*
	|--------------------------------------------------------------------------
	| Certificates (addon "certificate_builder")
	|--------------------------------------------------------------------------
	*/

	/**
	 * Create (once per source template) a simple MasterStudy certificate approximating a source design:
	 * the source background image plus title, student name, course name, date and instructor fields.
	 *
	 * @param array $data Keys: source_id (template id), title, background_id (attachment), orientation
	 *                    (landscape|portrait), heading (text on the certificate).
	 * @return int Certificate post ID.
	 */
	public static function create_certificate( array $data, string $source ): int {
		Helper::request_addon( 'certificate_builder' );

		$landscape = 'portrait' !== ( $data['orientation'] ?? 'landscape' );
		$width     = $landscape ? 900 : 600;
		$top       = $landscape ? 120 : 200;
		$field     = function ( $type, $content, $y, $height, $size, $bold = false ) use ( $width ) {
			return array(
				'type'    => $type,
				'content' => $content,
				'x'       => 0,
				'y'       => $y,
				'w'       => $width,
				'h'       => $height,
				'styles'  => array(
					'fontSize'       => $size . 'px',
					'fontFamily'     => 'Montserrat',
					'color'          => array( 'hex' => '#000' ),
					'textAlign'      => 'center',
					'textDecoration' => '',
					'fontStyle'      => '',
					'fontWeight'     => $bold ? 'true' : '',
				),
				'classes' => '',
			);
		};

		$fields = array(
			$field( 'text', (string) ( $data['heading'] ?? __( 'Certificate of Completion', 'masterstudy-lms-learning-management-system' ) ), $top, 50, 32, true ),
			$field( 'student_name', '-Student Name-', $top + 90, 50, 28, true ),
			$field( 'course_name', '-Course Name-', $top + 160, 40, 20 ),
			$field( 'end_date', '-End Date-', $top + 220, 30, 14 ),
			$field( 'author', '-Instructor-', $top + 260, 30, 14 ),
		);

		$certificate_id = Target::insert_post(
			array(
				'post_type'   => PostType::CERTIFICATE,
				'post_status' => 'publish',
				'post_title'  => (string) ( $data['title'] ?? __( 'Migrated certificate', 'masterstudy-lms-learning-management-system' ) ),
				'meta_input'  => array(
					'stm_orientation' => $landscape ? 'landscape' : 'portrait',
					'stm_fields'      => wp_json_encode( $fields, JSON_HEX_APOS | JSON_UNESCAPED_UNICODE ),
					'stm_category'    => '',
					'code'            => substr( md5( uniqid( '', true ) ), 0, 6 ),
				),
			),
			$source,
			'certificate-' . (string) ( $data['source_id'] ?? '0' )
		);

		if ( ! empty( $data['background_id'] ) && 'attachment' === get_post_type( (int) $data['background_id'] ) ) {
			set_post_thumbnail( $certificate_id, (int) $data['background_id'] );
		}

		return $certificate_id;
	}

	/**
	 * Assign a certificate to a course.
	 */
	public static function set_course_certificate( int $course_id, int $certificate_id ): void {
		if ( $certificate_id && PostType::CERTIFICATE === get_post_type( $certificate_id ) ) {
			Helper::request_addon( 'certificate_builder' );
			update_post_meta( $course_id, 'course_certificate', $certificate_id );
		}
	}

	/**
	 * Keep a source certificate verification code working (public certificate checker).
	 */
	public static function set_certificate_code( int $user_id, int $course_id, string $code ): void {
		if ( '' !== $code ) {
			update_user_meta( $user_id, 'stm_lms_certificate_code_' . $course_id, sanitize_text_field( $code ) );
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Bundles, groups, coupons, subscriptions
	|--------------------------------------------------------------------------
	*/

	/**
	 * Create a course bundle (addon "course_bundle").
	 *
	 * @param array $data Keys: source_id, title, content, author, price, course_ids (int[]), thumbnail_id, status.
	 */
	public static function create_bundle( array $data, string $source ): int {
		Helper::request_addon( 'course_bundle' );

		$course_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) ( $data['course_ids'] ?? array() ) ) ) ) );

		$bundle_id = Target::insert_post(
			array(
				'post_type'    => PostType::COURSE_BUNDLES,
				'post_status'  => in_array( $data['status'] ?? 'publish', array( 'publish', 'draft', 'private' ), true ) ? $data['status'] : 'publish',
				'post_title'   => (string) ( $data['title'] ?? '' ),
				'post_content' => wp_kses_post( (string) ( $data['content'] ?? '' ) ),
				'post_author'  => (int) ( $data['author'] ?? get_current_user_id() ),
				'meta_input'   => array(
					'stm_lms_bundle_ids'   => array_map( 'strval', $course_ids ),
					'stm_lms_bundle_price' => isset( $data['price'] ) ? (string) (float) $data['price'] : '',
				),
			),
			$source,
			'bundle-' . (string) ( $data['source_id'] ?? '' )
		);

		if ( ! empty( $data['thumbnail_id'] ) ) {
			set_post_thumbnail( $bundle_id, (int) $data['thumbnail_id'] );
		}

		return $bundle_id;
	}

	/**
	 * Create an enterprise group (addon "enterprise_courses") and enroll members in its courses.
	 * No WP users are created and no emails are sent.
	 *
	 * @param array $data Keys: source_id, title, admin_id (leader), member_ids (int[]), course_ids (int[]).
	 * @return int Group post ID.
	 */
	public static function create_group( array $data, string $source ): int {
		global $wpdb;

		Helper::request_addon( 'enterprise_courses' );

		$members = array_values(
			array_filter(
				array_unique( array_map( 'intval', (array) ( $data['member_ids'] ?? array() ) ) ),
				function ( $member_id ) {
					return $member_id && get_userdata( $member_id );
				}
			)
		);

		// Background jobs have no current user: fall back to the first member, never to user 0.
		$admin_id = (int) ( $data['admin_id'] ?? 0 );

		if ( ! $admin_id || ! get_userdata( $admin_id ) ) {
			$admin_id = (int) ( $members[0] ?? 0 );
		}

		if ( ! $admin_id ) {
			throw new \Exception( sprintf( 'Group "%s" not imported: it has no existing leader or member.', (string) ( $data['title'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		$emails  = array();

		foreach ( $members as $member_id ) {
			$user = get_userdata( $member_id );

			if ( $user && $member_id !== $admin_id ) {
				$emails[] = $user->user_email;
			}
		}

		$group_id = Target::insert_post(
			array(
				'post_type'   => PostType::COURSE_GROUPS,
				'post_status' => 'draft',
				'post_title'  => (string) ( $data['title'] ?? '' ),
				'post_author' => $admin_id,
				'meta_input'  => array(
					'author_id' => $admin_id,
					'emails'    => implode( ',', $emails ),
				),
			),
			$source,
			'group-' . (string) ( $data['source_id'] ?? '' )
		);

		$table = $wpdb->prefix . 'stm_lms_user_courses';

		foreach ( array_values( array_unique( array_filter( array_map( 'intval', (array) ( $data['course_ids'] ?? array() ) ) ) ) ) as $course_id ) {
			if ( PostType::COURSE !== get_post_type( $course_id ) ) {
				continue;
			}

			// The group admin's own row defines the group courses.
			foreach ( array_unique( array_merge( array( $admin_id ), $members ) ) as $user_id ) {
				if ( ! get_userdata( $user_id ) ) {
					continue;
				}

				$was_enrolled = Target::is_enrolled( $user_id, $course_id );

				Target::enroll( $user_id, $course_id );

				// A member's own (e.g. purchased) enrollment stays personal — turning it into a group
				// enrollment would revoke access if the group is removed. The admin row always links:
				// it is what defines the group's courses.
				if ( $was_enrolled && $user_id !== $admin_id ) {
					continue;
				}

				// Link the enrollment to the group unless it already belongs to another group.
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$table} SET enterprise_id = %d WHERE user_id = %d AND course_id = %d AND ( enterprise_id IS NULL OR enterprise_id = 0 )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$group_id,
						$user_id,
						$course_id
					)
				);
			}

			Target::refresh_students_count( $course_id );
		}

		return $group_id;
	}

	/**
	 * Create a coupon (Plus). Idempotent by code.
	 *
	 * @param array $data Keys: code, title, type (percent|amount), amount, status (active|inactive),
	 *                    usage_limit, user_usage_limit, min_amount, start (Unix), end (Unix),
	 *                    course_ids (int[]; empty = all courses).
	 * @return int Coupon row ID, 0 when the code already exists.
	 */
	public static function create_coupon( array $data ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'stm_lms_coupons';
		$code  = strtoupper( sanitize_text_field( (string) ( $data['code'] ?? '' ) ) );

		if ( '' === $code || ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			return 0;
		}

		if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE code = %s", $code ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return 0;
		}

		$courses = array_values( array_filter( array_map( 'intval', (array) ( $data['course_ids'] ?? array() ) ) ) );
		$now     = current_time( 'mysql' );

		// Coupons are stored but never applied at checkout unless the setting is on.
		$settings = get_option( 'stm_lms_settings', array() );

		if ( is_array( $settings ) && empty( $settings['enable_coupon_code'] ) ) {
			$settings['enable_coupon_code'] = true;
			update_option( 'stm_lms_settings', $settings );
		}

		$wpdb->insert(
			$table,
			array(
				'title'               => (string) ( $data['title'] ?? $code ),
				'coupon_status'       => 'inactive' === ( $data['status'] ?? '' ) ? 'inactive' : 'active',
				'code'                => $code,
				'discount_type'       => 'amount' === ( $data['type'] ?? '' ) ? 'amount' : 'percent',
				'discount'            => (float) ( $data['amount'] ?? 0 ),
				'product_type'        => empty( $courses ) ? 'all-courses' : 'specific-courses',
				'usage_limit'         => ! empty( $data['usage_limit'] ) ? (int) $data['usage_limit'] : null,
				'used_count'          => (int) ( $data['used_count'] ?? 0 ),
				'user_usage_limit'    => ! empty( $data['user_usage_limit'] ) ? (int) $data['user_usage_limit'] : null,
				'min_purchase_amount' => ! empty( $data['min_amount'] ) ? (float) $data['min_amount'] : null,
				'start_at'            => ! empty( $data['start'] ) ? wp_date( 'Y-m-d H:i:s', (int) $data['start'] ) : null,
				'end_at'              => ! empty( $data['end'] ) ? wp_date( 'Y-m-d H:i:s', (int) $data['end'] ) : null,
				'items'               => wp_json_encode( $courses ),
				'created_at'          => $now,
				'updated_at'          => $now,
			)
		);

		$coupon_id = (int) $wpdb->insert_id;

		Ledger::add( Helper::current_source(), 'coupons', $coupon_id );

		return $coupon_id;
	}

	/**
	 * Create a subscription plan (Plus addon "subscriptions") for courses or the whole site.
	 * Gateway data is not migrated — imported subscriptions do not renew automatically.
	 *
	 * @param array $data Keys: source_id, name, description, type (full_site|course|category), object_ids (int[]),
	 *                    price, sale_price, interval (day|week|month|year), interval_value, billing_cycles,
	 *                    trial_days, enrollment_fee, enabled (bool).
	 * @return int Plan ID.
	 */
	public static function create_subscription_plan( array $data, string $source ): int {
		global $wpdb;

		Helper::request_addon( 'subscriptions' );

		$table = $wpdb->prefix . 'stm_lms_subscription_plans';

		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			throw new \Exception( 'Subscription tables are missing — enable the Subscriptions addon and run the migration again.' );
		}

		$option_key = 'masterstudy_lms_migrated_plan_' . md5( $source . '|' . (string) ( $data['source_id'] ?? '' ) );
		$existing   = (int) get_option( $option_key );

		if ( $existing && $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d", $existing ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $existing;
		}

		$type     = in_array( $data['type'] ?? '', array( 'full_site', 'course', 'category' ), true ) ? $data['type'] : 'course';
		$interval = in_array( $data['interval'] ?? '', array( 'day', 'week', 'month', 'year' ), true ) ? $data['interval'] : 'month';

		$wpdb->insert(
			$table,
			array(
				'type'               => $type,
				'name'               => (string) ( $data['name'] ?? '' ),
				'description'        => mb_substr( wp_strip_all_tags( (string) ( $data['description'] ?? '' ) ), 0, 255 ),
				'recurring_value'    => max( 1, (int) ( $data['interval_value'] ?? 1 ) ),
				'recurring_interval' => $interval,
				'billing_cycles'     => (int) ( $data['billing_cycles'] ?? 0 ),
				'price'              => (float) ( $data['price'] ?? 0 ),
				'sale_price'         => ! empty( $data['sale_price'] ) ? (float) $data['sale_price'] : null,
				'plan_features'      => wp_json_encode( array() ),
				'enrollment_fee'     => (float) ( $data['enrollment_fee'] ?? 0 ),
				'trial_period'       => (int) ( $data['trial_days'] ?? 0 ),
				'is_certified'       => 1,
				'is_enabled'         => ! isset( $data['enabled'] ) || $data['enabled'] ? 1 : 0,
			)
		);

		$plan_id = (int) $wpdb->insert_id;

		if ( ! $plan_id ) {
			throw new \Exception( sprintf( 'Subscription plan "%s" could not be created: %s', (string) ( $data['name'] ?? '' ), $wpdb->last_error ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		if ( 'full_site' !== $type ) {
			foreach ( array_filter( array_map( 'intval', (array) ( $data['object_ids'] ?? array() ) ) ) as $object_id ) {
				$wpdb->insert(
					$wpdb->prefix . 'stm_lms_subscription_plan_items',
					array(
						'plan_id'     => $plan_id,
						'object_type' => $type,
						'object_id'   => $object_id,
					)
				);

				if ( 'course' === $type ) {
					update_post_meta( $object_id, 'subscriptions', 'on' );
				}
			}
		}

		update_option( $option_key, $plan_id, false );
		Ledger::add( $source, 'subscription_plans', $plan_id );

		return $plan_id;
	}

	/*
	|--------------------------------------------------------------------------
	| SCORM (addon "scorm")
	|--------------------------------------------------------------------------
	*/

	/**
	 * Register an already-extracted SCORM package directory as the course SCORM package.
	 * The package is copied into uploads/wpcfto_files/ so it survives removal of the source plugin.
	 *
	 * @param string $source_dir Absolute path of the extracted package (must contain imsmanifest.xml).
	 * @return bool False when the package is missing or cannot be copied.
	 */
	public static function set_scorm_package( int $course_id, string $source_dir, string $version = '' ): bool {
		$source_dir = untrailingslashit( wp_normalize_path( $source_dir ) );

		if ( ! is_dir( $source_dir ) || ! file_exists( $source_dir . '/imsmanifest.xml' ) ) {
			return false;
		}

		$uploads = wp_upload_dir();
		$name    = 'migrated-scorm-' . $course_id . '-' . sanitize_file_name( basename( $source_dir ) );
		$target  = wp_normalize_path( $uploads['basedir'] . '/wpcfto_files/' . $name );

		if ( ! file_exists( $target . '/imsmanifest.xml' ) ) {
			if ( ! function_exists( 'copy_dir' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			global $wp_filesystem;

			if ( ! $wp_filesystem ) {
				WP_Filesystem();
			}

			wp_mkdir_p( $target );

			if ( ! $wp_filesystem || is_wp_error( copy_dir( $source_dir, $target ) ) || ! file_exists( $target . '/imsmanifest.xml' ) ) {
				return false;
			}
		}

		if ( '' === $version ) {
			$manifest = (string) file_get_contents( $target . '/imsmanifest.xml' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$version  = false !== strpos( $manifest, '2004' ) ? '2004' : '1.2';
		}

		Helper::request_addon( 'scorm' );

		update_post_meta(
			$course_id,
			'scorm_package',
			wp_json_encode(
				array(
					'error'         => '',
					'path'          => $target,
					'url'           => $uploads['baseurl'] . '/wpcfto_files/' . $name,
					'scorm_version' => $version,
				)
			)
		);

		return true;
	}

	/**
	 * Copy learner SCORM runtime values (CMI key => value) to the MasterStudy SCORM table of an enrollment.
	 * Existing values of the enrollment are replaced, like the SCORM player does on every commit.
	 *
	 * @param int                  $user_course_id stm_lms_user_courses.user_course_id.
	 * @param array<string, mixed> $values         CMI parameter => value.
	 * @return bool False when the SCORM table is missing (addon never enabled).
	 */
	public static function set_scorm_runtime( int $user_course_id, array $values ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'stm_lms_user_course_scorm';

		if ( ! $user_course_id || $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
			return false;
		}

		Helper::request_addon( 'scorm' );

		$wpdb->delete( $table, array( 'user_course_id' => $user_course_id ), array( '%d' ) );

		foreach ( $values as $parameter => $value ) {
			$parameter = substr( (string) $parameter, 0, 45 );

			if ( '' === $parameter || ! is_scalar( $value ) ) {
				continue;
			}

			$wpdb->insert(
				$table,
				array(
					'user_course_id' => $user_course_id,
					'parameter'      => $parameter,
					'value'          => (string) $value,
				),
				array( '%d', '%s', '%s' )
			);
		}

		return true;
	}

	/*
	|--------------------------------------------------------------------------
	| Course preview video (Plus) and enterprise price (addon "enterprise_courses")
	|--------------------------------------------------------------------------
	*/

	/**
	 * Course preview (intro) video shown instead of the course image (Plus, includes/plus course preview).
	 *
	 * @param string     $source youtube|vimeo|embed|html (attachment)|shortcode|ext_link.
	 * @param string|int $value  URL, embed HTML, shortcode or attachment ID.
	 * @param int        $poster Poster attachment ID.
	 */
	public static function set_course_preview_video( int $course_id, string $source, $value, int $poster = 0 ): void {
		if ( '' === (string) $value ) {
			return;
		}

		switch ( $source ) {
			case 'youtube':
				update_post_meta( $course_id, 'youtube_url', esc_url_raw( (string) $value ) );
				break;
			case 'vimeo':
				update_post_meta( $course_id, 'vimeo_url', esc_url_raw( (string) $value ) );
				break;
			case 'embed':
				update_post_meta( $course_id, 'embed_ctx', (string) $value );
				break;
			case 'html':
				update_post_meta( $course_id, 'video', (int) $value );
				break;
			case 'shortcode':
				update_post_meta( $course_id, 'shortcode', (string) $value );
				break;
			default:
				$source = 'ext_link';
				update_post_meta( $course_id, 'external_url', esc_url_raw( (string) $value ) );
		}

		update_post_meta( $course_id, 'video_type', $source );

		if ( $poster ) {
			update_post_meta( $course_id, 'video_poster', $poster );
		}
	}

	/**
	 * Price for buying a course for a group (enterprise groups addon).
	 */
	public static function set_enterprise_price( int $course_id, float $price ): void {
		if ( $price <= 0 ) {
			return;
		}

		Helper::request_addon( 'enterprise_courses' );
		update_post_meta( $course_id, 'enterprise_price', (string) $price );
	}

	/*
	|--------------------------------------------------------------------------
	| Lesson notes (Plus, table stm_lms_lesson_notes)
	|--------------------------------------------------------------------------
	*/

	/**
	 * Whether the Plus lesson notes table exists (created by MasterStudy Pro Plus on load).
	 */
	public static function lesson_notes_available(): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'stm_lms_lesson_notes';

		return static::plus_active() && $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}

	/**
	 * Add a private student note to a lesson (Plus lesson notes). Idempotent per
	 * (user, lesson, created_at, body). Notes are only ever shown to their author.
	 *
	 * @param array $data Keys: user_id, course_id, lesson_id, lesson_type (text|video|audio), body,
	 *                    selected_text (highlighted passage), media_time (seconds), created_at (GMT MySQL datetime).
	 * @return int Note row ID.
	 */
	public static function add_lesson_note( array $data ): int {
		global $wpdb;

		if ( ! static::lesson_notes_available() ) {
			throw new \Exception( 'Lesson note not imported: the MasterStudy LMS Pro Plus lesson notes table is missing.' );
		}

		$table      = $wpdb->prefix . 'stm_lms_lesson_notes';
		$user_id    = (int) ( $data['user_id'] ?? 0 );
		$lesson_id  = (int) ( $data['lesson_id'] ?? 0 );
		$body       = trim( wp_strip_all_tags( (string) ( $data['body'] ?? '' ) ) );
		$created_at = ! empty( $data['created_at'] ) ? (string) $data['created_at'] : current_time( 'mysql', true );
		$type       = in_array( $data['lesson_type'] ?? '', array( 'text', 'video', 'audio' ), true ) ? $data['lesson_type'] : 'text';

		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			throw new \Exception( sprintf( 'Lesson note not imported: user #%d no longer exists.', $user_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$existing = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE user_id = %d AND lesson_id = %d AND created_at = %s AND body = %s LIMIT 1",
				$user_id,
				$lesson_id,
				$created_at,
				$body
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $existing ) {
			return $existing;
		}

		$selected   = trim( wp_strip_all_tags( (string) ( $data['selected_text'] ?? '' ) ) );
		$media_time = isset( $data['media_time'] ) && '' !== (string) $data['media_time'] ? max( 0, (int) $data['media_time'] ) : null;

		$inserted = $wpdb->insert(
			$table,
			array(
				'user_id'       => $user_id,
				'course_id'     => (int) ( $data['course_id'] ?? 0 ),
				'lesson_id'     => $lesson_id,
				'lesson_type'   => $type,
				'body'          => mb_substr( $body, 0, 10000 ),
				'selected_text' => '' !== $selected ? mb_substr( $selected, 0, 2000 ) : null,
				'anchor_text'   => '' !== $selected ? mb_substr( $selected, 0, 2000 ) : null,
				'content_hash'  => null,
				'media_time'    => $media_time,
				'created_at'    => $created_at,
				'updated_at'    => ! empty( $data['updated_at'] ) ? (string) $data['updated_at'] : $created_at,
			),
			// wpdb writes NULL for null values whatever the format.
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			throw new \Exception( sprintf( 'Lesson note not imported: %s', $wpdb->last_error ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		return (int) $wpdb->insert_id;
	}

	/*
	|--------------------------------------------------------------------------
	| Point system (addon "point_system", table stm_lms_user_points)
	|--------------------------------------------------------------------------
	*/

	/**
	 * Record points a user earned (point history row). Idempotent per (user, object, action), like the
	 * addon's own stm_lms_check_point_added(), so MasterStudy never awards the same action twice.
	 * The table only exists once the addon is enabled — request it and flush the requests outside a
	 * transaction first.
	 *
	 * @param int    $object_id Object the points are for (course ID for "certificate_received").
	 * @param string $action_id Point system action, e.g. certificate_received.
	 * @param int    $timestamp Unix time the points were earned.
	 * @return bool False when the table is missing.
	 */
	public static function add_user_points( int $user_id, int $object_id, string $action_id, int $score, int $timestamp, int $course_id = 0 ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'stm_lms_user_points';

		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			return false;
		}

		$exists = $wpdb->get_var(
			$wpdb->prepare( "SELECT 1 FROM {$table} WHERE user_id = %d AND id = %d AND action_id = %s LIMIT 1", $user_id, $object_id, $action_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		if ( $exists ) {
			return true;
		}

		return false !== $wpdb->insert(
			$table,
			array(
				'user_id'   => $user_id,
				'id'        => $object_id,
				'course_id' => $course_id ? $course_id : $object_id,
				'action_id' => $action_id,
				'score'     => $score,
				'timestamp' => $timestamp > 0 ? $timestamp : time(),
				'completed' => 0,
			),
			array( '%d', '%d', '%d', '%s', '%d', '%d', '%d' )
		);
	}
}
