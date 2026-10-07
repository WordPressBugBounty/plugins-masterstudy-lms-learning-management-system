<?php
// phpcs:ignoreFile
/**
 * Masteriyo migrations.
 *
 * Mirror (in reverse) of Masteriyo's `LMS/MasterStudy.php` migrator: every step Masteriyo
 * performs when pulling MasterStudy data in is performed here in the opposite direction,
 * pulling Masteriyo data into MasterStudy. The migration COPIES: course, lesson, quiz,
 * question, assignment, meeting and order posts get NEW MasterStudy copies (Target::copy_post());
 * Masteriyo sections become MasterStudy curriculum sections of the copy; announcements, wishlists,
 * reviews, Q&A, enrollments, progress, attempts and SCORM data become new MasterStudy records.
 * Nothing Masteriyo stores is modified or deleted, so Masteriyo keeps working, and every step
 * is cursor based over all source records.
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
 * Class Masteriyo.
 */
class Masteriyo {

	/**
	 * Source LMS slug stored on every copied/created post.
	 */
	const SOURCE = 'masteriyo';

	/**
	 * Masteriyo post types.
	 */
	const COURSE       = 'mto-course';
	const SECTION      = 'mto-section';
	const LESSON       = 'mto-lesson';
	const QUIZ         = 'mto-quiz';
	const QUESTION     = 'mto-question';
	const ORDER        = 'mto-order';
	const ANNOUNCEMENT = 'mto-announcement';
	const WISHLIST     = 'mto-wishlist-item';
	const ASSIGNMENT   = 'mto-assignment';
	const REPLY        = 'mto-assignment-reply';
	const ZOOM         = 'mto-zoom';
	const GOOGLE_MEET  = 'mto-google-meet';
	const CERTIFICATE  = 'mto-certificate';
	const BUNDLE       = 'mto-bundle';
	const GROUP        = 'mto-group';
	const COUPON       = 'mto-coupon';

	/**
	 * Masteriyo comment types.
	 */
	const COMMENT_REVIEW = 'mto_course_review';
	const COMMENT_QA     = 'mto_course_qa';
	const COMMENT_FAQ    = 'mto_course_faq';

	const COMMENT_LESSON_REVIEW = 'mto_lesson_review';
	const COMMENT_QUIZ_REVIEW   = 'mto_quiz_review';

	/**
	 * Masteriyo roles.
	 */
	const ROLE_INSTRUCTOR = 'masteriyo_instructor';
	const ROLE_STUDENT    = 'masteriyo_student';
	const ROLE_MANAGER    = 'masteriyo_manager';

	/**
	 * Build the curriculum of a course copy from the Masteriyo course.
	 *
	 * Sections are mto-section posts (post_parent = course, menu_order); items are lesson/quiz/
	 * assignment/zoom/google-meet posts with post_parent = section. MasterStudy has no post_parent
	 * linkage: the curriculum lives only in the sections/materials tables, so each item is copied
	 * and the copy attached in order. The Masteriyo sections and items stay as they are.
	 *
	 * @param int $course_id Masteriyo course ID.
	 * @param int $copy_id   MasterStudy course (copy) ID.
	 */
	private static function migrate_course( int $course_id, int $copy_id ): void {
		global $wpdb;

		// Rebuild the curriculum from scratch so a re-run never duplicates rows.
		Target::reset_curriculum( $copy_id );

		$sections = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title FROM {$wpdb->posts}
				 WHERE post_type = %s AND post_parent = %d AND post_status <> 'trash'
				 ORDER BY menu_order ASC, ID ASC",
				self::SECTION,
				$course_id
			)
		);

		$section_order = 0;
		$course_title  = self::title_of( $course_id );

		foreach ( (array) $sections as $section ) {
			++$section_order;

			$section_id = (int) $section->ID;
			$ms_section = Target::add_section( $copy_id, (string) $section->post_title, $section_order );
			$context    = array(
				/* translators: %s: section title */
				'parent' => sprintf( __( 'Section: %s', 'masterstudy-lms-learning-management-system' ), $section->post_title ),
				'course' => $course_title,
			);

			$items = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID, post_type, post_status FROM {$wpdb->posts}
					 WHERE post_parent = %d AND post_type IN (%s, %s, %s, %s, %s) AND post_status NOT IN ('trash', 'auto-draft')
					 ORDER BY menu_order ASC, ID ASC",
					$section_id,
					self::LESSON,
					self::QUIZ,
					self::ASSIGNMENT,
					self::ZOOM,
					self::GOOGLE_MEET
				)
			);

			$item_order = 0;

			foreach ( (array) $items as $item ) {
				$item_id   = (int) $item->ID;
				$item_copy = self::migrate_item_post( $item_id, (string) $item->post_type, $copy_id, $context );

				if ( ! $item_copy ) {
					// Not imported (e.g. Pro-only target without MasterStudy Pro) - already reported.
					continue;
				}

				// Masteriyo only shows published items to learners, while a MasterStudy curriculum shows every
				// attached item: drafts / pending / private / scheduled items are copied but not attached.
				if ( 'publish' !== $item->post_status ) {
					self::report_hidden_item( $item_id, $item_copy, (string) $item->post_type, (string) $item->post_status, $context );
					continue;
				}

				++$item_order;
				Target::add_material( $ms_section, $item_copy, $item_order );
			}
		}
	}

	/**
	 * Copy one Masteriyo curriculum post (lesson, quiz, assignment, Zoom, Google Meet) to MasterStudy.
	 * An item that already has a copy is reused (and its data refreshed), never duplicated.
	 *
	 * @param int    $item_id   Source post ID.
	 * @param string $post_type Source post type.
	 * @param int    $course_id MasterStudy course (copy) ID, 0 for orphan items.
	 * @param array  $context   Report context (parent, course).
	 * @return int Copy ID, 0 when the item was not imported (Pro-only target without Pro).
	 */
	private static function migrate_item_post( int $item_id, string $post_type, int $course_id, array $context = array() ): int {
		switch ( $post_type ) {
			case self::QUIZ:
				return self::migrate_quiz( $item_id, $course_id );
			case self::ASSIGNMENT:
				return self::migrate_assignment( $item_id, $course_id, $context );
			case self::ZOOM:
				return self::migrate_zoom( $item_id, $context );
			case self::GOOGLE_MEET:
				return self::migrate_google_meet( $item_id, $context );
			default:
				return self::migrate_lesson( $item_id, $context );
		}
	}

	/**
	 * Report a copied curriculum item that is not published and therefore not added to the curriculum.
	 *
	 * @param int    $item_id   Source item ID.
	 * @param int    $copy_id   MasterStudy copy ID.
	 * @param string $post_type Source post type.
	 * @param string $status    Source post status.
	 * @param array  $context   Report context.
	 */
	private static function report_hidden_item( int $item_id, int $copy_id, string $post_type, string $status, array $context ): void {
		$groups = array(
			self::QUIZ        => Report::GROUP_QUIZZES,
			self::ASSIGNMENT  => Report::GROUP_ASSIGNMENTS,
			self::ZOOM        => Report::GROUP_MEETINGS,
			self::GOOGLE_MEET => Report::GROUP_MEETINGS,
		);

		self::report(
			$groups[ $post_type ] ?? Report::GROUP_LESSONS,
			array(
				'source_id' => $item_id,
				'title'     => self::title_of( $item_id ),
				'type'      => self::item_type_label( $post_type ),
				'reason'    => sprintf(
					/* translators: %s: post status */
					__( 'The item was not published in Masteriyo (status "%s") and hidden from learners — it was imported with the same status but not added to the MasterStudy curriculum, which shows every attached item. Publish it and add it to the curriculum when it is ready.', 'masterstudy-lms-learning-management-system' ),
					$status
				),
				'status'    => Report::STATUS_PARTIAL,
				'post_id'   => $copy_id,
			),
			$context
		);
	}

	/**
	 * Human-readable label of a Masteriyo curriculum post type.
	 *
	 * @param string $post_type Post type.
	 */
	private static function item_type_label( string $post_type ): string {
		$labels = array(
			self::LESSON      => __( 'Lesson', 'masterstudy-lms-learning-management-system' ),
			self::QUIZ        => __( 'Quiz', 'masterstudy-lms-learning-management-system' ),
			self::ASSIGNMENT  => __( 'Assignment', 'masterstudy-lms-learning-management-system' ),
			self::ZOOM        => __( 'Zoom meeting', 'masterstudy-lms-learning-management-system' ),
			self::GOOGLE_MEET => __( 'Google Meet', 'masterstudy-lms-learning-management-system' ),
			self::QUESTION    => __( 'Question', 'masterstudy-lms-learning-management-system' ),
		);

		return $labels[ $post_type ] ?? $post_type;
	}

	/**
	 * Copy a Masteriyo lesson to a MasterStudy lesson.
	 *
	 * @param int   $lesson_id Masteriyo lesson ID.
	 * @param array $context   Report context (parent, course).
	 * @return int Copy ID (lessons are a free target).
	 */
	private static function migrate_lesson( int $lesson_id, array $context = array() ): int {
		$post = get_post( $lesson_id );
		$copy = Target::copy_post( $lesson_id, PostType::LESSON, self::SOURCE );

		$source       = (string) get_post_meta( $lesson_id, '_video_source', true );
		$url          = trim( (string) get_post_meta( $lesson_id, '_video_source_url', true ) );
		$poster       = (int) get_post_meta( $lesson_id, '_thumbnail_id', true );
		$pdf          = maybe_unserialize( get_post_meta( $lesson_id, '_pdf', true ) );
		$audio_source = (string) get_post_meta( $lesson_id, '_audio_source', true );
		$audio_url    = trim( (string) get_post_meta( $lesson_id, '_audio_source_url', true ) );
		$audio_files  = self::attachment_ids( maybe_unserialize( get_post_meta( $lesson_id, '_audio_source_files', true ) ) );
		$pdf_ids      = self::attachment_ids( $pdf );
		$playback     = absint( get_post_meta( $lesson_id, '_video_playback_time', true ) );

		Target::set_lesson(
			$copy,
			array(
				'type'     => 'text',
				'duration' => $playback ? self::format_seconds( $playback ) : '',
				'preview'  => self::to_bool( get_post_meta( $lesson_id, '_enable_preview', true ) ),
				'excerpt'  => $post ? $post->post_excerpt : '',
			)
		);

		if ( '' !== $url ) {
			switch ( $source ) {
				case 'embed-video':
					Target::set_lesson_video( $copy, 'embed', $url, $poster );
					break;
				case 'youtube':
					Target::set_lesson_video( $copy, 'youtube', $url, $poster );
					break;
				case 'vimeo':
					Target::set_lesson_video( $copy, 'vimeo', $url, $poster );
					break;
				case 'bunny-net':
					$bunny = self::bunny_video( $url );
					Target::set_lesson_video( $copy, $bunny['source'], $bunny['value'], $poster );
					break;
				case 'self-hosted':
					// Masteriyo stores the attachment ID as a string for self-hosted videos.
					if ( is_numeric( $url ) ) {
						Target::set_lesson_video( $copy, 'html', absint( $url ), $poster );
					} else {
						Target::set_lesson_video( $copy, 'external', $url, $poster );
					}
					break;
				case 'live-stream':
					$stream_start = self::date_meta_timestamp( $lesson_id, array( '_starts_at', 'starts_at' ) );
					$stream_end   = self::date_meta_timestamp( $lesson_id, array( '_ends_at', 'ends_at' ) );

					if ( ! ProTarget::pro_active() || ! ProTarget::set_stream_lesson( $copy, $url, $stream_start, $stream_end ) ) {
						Target::set_lesson_video( $copy, Target::detect_video_source( $url ), $url, $poster );

						self::report(
							Report::GROUP_LESSONS,
							array(
								'source_id' => $lesson_id,
								'title'     => $post ? $post->post_title : '',
								'type'      => __( 'Live stream lesson', 'masterstudy-lms-learning-management-system' ),
								'reason'    => ProTarget::pro_active()
									? __( 'MasterStudy live stream lessons only play YouTube streams — the stream URL was imported as a regular video lesson without the start/end schedule.', 'masterstudy-lms-learning-management-system' )
									: __( 'Live stream lessons require MasterStudy LMS Pro — the stream URL was imported as a regular video lesson without the start/end schedule.', 'masterstudy-lms-learning-management-system' ),
								'status'    => Report::STATUS_PARTIAL,
								'post_id'   => $copy,
							),
							$context
						);
					}
					break;
				default:
					Target::set_lesson_video( $copy, Target::detect_video_source( $url ), $url, $poster );
			}
		} elseif ( ! empty( $pdf_ids ) ) {
			if ( ProTarget::pro_active() ) {
				update_post_meta( $copy, 'type', 'pdf' );
				update_post_meta( $copy, 'pdf_file_ids', $pdf_ids );
			} else {
				self::report(
					Report::GROUP_LESSONS,
					array(
						'source_id' => $lesson_id,
						'title'     => $post ? $post->post_title : '',
						'type'      => __( 'PDF lesson', 'masterstudy-lms-learning-management-system' ),
						'reason'    => __( 'PDF lessons require MasterStudy LMS Pro — the PDF was attached as a lesson material and the lesson was imported as a text lesson.', 'masterstudy-lms-learning-management-system' ),
						'status'    => Report::STATUS_PARTIAL,
						'post_id'   => $copy,
					),
					$context
				);
			}

			Target::set_lesson_files( $copy, $pdf_ids );
		} elseif ( '' !== $audio_url || ! empty( $audio_files ) ) {
			if ( ! self::set_lesson_audio( $copy, $audio_source, $audio_url, $audio_files ) ) {
				Target::store_unmigrated_meta( $copy, 'audio_source', $audio_source );
				Target::store_unmigrated_meta( $copy, 'audio_source_url', $audio_url );
				Target::store_unmigrated_meta( $copy, 'audio_source_files', $audio_files );
				Helper::log( 'warning', sprintf( 'Migration: Lesson %d is an audio lesson — audio lessons require MasterStudy LMS Pro Plus; kept as a text lesson.', $lesson_id ) );

				self::report(
					Report::GROUP_LESSONS,
					array(
						'source_id' => $lesson_id,
						'title'     => $post ? $post->post_title : '',
						'type'      => __( 'Audio lesson', 'masterstudy-lms-learning-management-system' ),
						'reason'    => ProTarget::plus_active()
							? __( 'The audio lesson has no usable audio file or URL — the lesson was imported as a text lesson.', 'masterstudy-lms-learning-management-system' )
							: __( 'Audio lessons require MasterStudy LMS Pro Plus — the lesson was imported as a text lesson without its audio.', 'masterstudy-lms-learning-management-system' ),
						'status'    => Report::STATUS_PARTIAL,
						'post_id'   => $copy,
					),
					$context
				);
			}
		}

		$files = self::attachment_ids( maybe_unserialize( get_post_meta( $lesson_id, '_download_materials', true ) ) );

		if ( ! empty( $files ) ) {
			Target::set_lesson_files( $copy, $files );
		}

		self::migrate_lesson_video_extras( $lesson_id, $copy, $post, $context );

		return $copy;
	}

	/**
	 * MasterStudy video source for a Bunny.net (Bunny Stream) URL: player URLs become an embedded iframe,
	 * direct media files an external link, embed code is kept as is.
	 *
	 * @param string $url Masteriyo `_video_source_url`.
	 * @return array{source: string, value: string}
	 */
	private static function bunny_video( string $url ): array {
		if ( preg_match( '/<iframe|<video|<embed/i', $url ) ) {
			return array(
				'source' => 'embed',
				'value'  => $url,
			);
		}

		if ( preg_match( '#\.(mp4|m3u8|webm|mov)(\?|$)#i', $url ) ) {
			return array(
				'source' => 'external',
				'value'  => $url,
			);
		}

		return array(
			'source' => 'embed',
			'value'  => sprintf(
				'<iframe src="%s" loading="lazy" style="border:0;width:100%%;aspect-ratio:16/9;" allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;" allowfullscreen="true"></iframe>',
				esc_url( $url )
			),
		);
	}

	/**
	 * Lesson subtitles (`_subtitle_meta`) → MasterStudy video captions; video chapters (`_video_meta`), lesson
	 * passwords and custom fields have no MasterStudy equivalent and are reported (they stay in the Masteriyo lesson).
	 *
	 * @param int           $lesson_id Masteriyo lesson ID.
	 * @param int           $copy_id   MasterStudy lesson (copy) ID.
	 * @param \WP_Post|null $post      Source post.
	 * @param array         $context   Report context.
	 */
	private static function migrate_lesson_video_extras( int $lesson_id, int $copy_id, $post, array $context ): void {
		$captions = array();
		$external = 0;

		foreach ( (array) maybe_unserialize( get_post_meta( $lesson_id, '_subtitle_meta', true ) ) as $subtitle ) {
			if ( ! is_array( $subtitle ) || empty( $subtitle ) ) {
				continue;
			}

			$attachment_id = absint( $subtitle['subtitle_id'] ?? 0 );

			if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
				$source        = $subtitle['subtitle_source'] ?? '';
				$source        = is_array( $source ) ? (string) reset( $source ) : (string) $source;
				$attachment_id = '' !== $source ? (int) attachment_url_to_postid( $source ) : 0;
			}

			if ( $attachment_id ) {
				$captions[] = $attachment_id;
			} else {
				++$external;
			}
		}

		Target::set_lesson_captions( $copy_id, $captions );

		$lost       = array();
		$video_meta = maybe_unserialize( get_post_meta( $lesson_id, '_video_meta', true ) );
		$chapters   = is_array( $video_meta ) ? array_merge( (array) ( $video_meta['time_stamps'] ?? array() ), (array) ( $video_meta['timestamps'] ?? array() ) ) : array();

		if ( $external ) {
			/* translators: %d: number of subtitle tracks */
			$lost[] = sprintf( __( '%d subtitle track(s) that are not files in the media library', 'masterstudy-lms-learning-management-system' ), $external );
		}

		if ( ! empty( array_filter( $chapters ) ) ) {
			/* translators: %d: number of chapters */
			$lost[] = sprintf( __( '%d video chapter(s) (MasterStudy has no video chapters)', 'masterstudy-lms-learning-management-system' ), count( array_filter( $chapters ) ) );
		}

		if ( $post && '' !== (string) $post->post_password ) {
			$lost[] = __( 'the lesson password (the MasterStudy course player does not ask for it — the lesson is open to enrolled students)', 'masterstudy-lms-learning-management-system' );
		}

		if ( ! empty( maybe_unserialize( get_post_meta( $lesson_id, '_custom_fields', true ) ) ) ) {
			$lost[] = __( 'custom fields', 'masterstudy-lms-learning-management-system' );
		}

		if ( empty( $lost ) ) {
			return;
		}

		self::report(
			Report::GROUP_LESSONS,
			array(
				'source_id' => $lesson_id,
				'title'     => $post ? $post->post_title : '',
				'type'      => __( 'Lesson settings', 'masterstudy-lms-learning-management-system' ),
				/* translators: %s: list of lesson settings */
				'reason'    => sprintf( __( 'The lesson was imported without: %s. The values stay in the Masteriyo lesson.', 'masterstudy-lms-learning-management-system' ), implode( '; ', $lost ) ),
				'status'    => Report::STATUS_PARTIAL,
				'post_id'   => $copy_id,
			),
			$context
		);
	}

	/**
	 * Copy a Masteriyo quiz (and its questions) to a MasterStudy quiz.
	 *
	 * @param int $quiz_id   Masteriyo quiz ID.
	 * @param int $course_id MasterStudy course (copy) ID, 0 for orphan quizzes.
	 * @return int Copy ID (quizzes are a free target).
	 */
	private static function migrate_quiz( int $quiz_id, int $course_id ): int {
		$post         = get_post( $quiz_id );
		$question_ids = self::quiz_question_ids( $quiz_id );
		$pass_percent = self::quiz_pass_percent( $quiz_id, $question_ids );
		$copy         = Target::copy_post( $quiz_id, PostType::QUIZ, self::SOURCE );

		// Source question ID => MasterStudy question (copy) ID, in quiz order.
		$ms_questions = array();
		$context      = array(
			/* translators: %s: quiz title */
			'parent' => sprintf( __( 'Quiz: %s', 'masterstudy-lms-learning-management-system' ), $post ? $post->post_title : '#' . $quiz_id ),
			'course' => self::title_of( $course_id ),
		);

		foreach ( $question_ids as $question_id ) {
			// Masteriyo only shows published questions in a quiz (masteriyo_get_all_question_ids_by_quiz()).
			$status        = (string) get_post_status( $question_id );
			$question_copy = self::process_question_migration( $question_id, $context );

			if ( ! $question_copy ) {
				continue;
			}

			if ( 'publish' !== $status ) {
				self::report(
					Report::GROUP_QUESTIONS,
					array(
						'source_id' => $question_id,
						'title'     => self::title_of( $question_id ),
						'type'      => self::question_type_label( (string) get_post_meta( $question_id, '_type', true ) ),
						/* translators: %s: post status */
						'reason'    => sprintf( __( 'The question was not published in Masteriyo (status "%s"), so learners never saw it — it was imported with the same status but not added to the quiz.', 'masterstudy-lms-learning-management-system' ), $status ),
						'status'    => Report::STATUS_PARTIAL,
						'post_id'   => $question_copy,
					),
					$context
				);
				continue;
			}

			$ms_questions[ $question_id ] = $question_copy;
		}

		$per_page = absint( get_post_meta( $quiz_id, '_questions_display_per_page', true ) );

		Target::set_quiz(
			$copy,
			array(
				// The Masteriyo quiz builder saves _duration in minutes (60 * hours + minutes), although
				// the model/REST schema comment says seconds; the PDF exporter also reads it as minutes.
				'duration_minutes'    => absint( get_post_meta( $quiz_id, '_duration', true ) ),
				'passing_grade'       => $pass_percent,
				'attempts'            => absint( get_post_meta( $quiz_id, '_attempts_allowed', true ) ),
				'random_questions'    => self::to_bool( get_post_meta( $quiz_id, '_randomize', true ) ),
				'show_correct_answer' => self::to_bool( get_post_meta( $quiz_id, '_reveal_mode', true ) ),
				'excerpt'             => $post ? $post->post_excerpt : '',
				// Questions per page: 0 = all on one page; MasterStudy paginates one question per page.
				'style'               => $per_page > 0 && $per_page < count( $ms_questions ) ? 'pagination' : 'default',
			)
		);

		Target::set_quiz_questions( $copy, array_values( $ms_questions ) );

		self::migrate_quiz_answer_rules( $quiz_id, $copy, $ms_questions, $per_page, $context );

		return $copy;
	}

	/**
	 * Quiz answer rules: "require all questions attempted" → required questions, per-question answer shuffling →
	 * quiz-level random answers. Settings MasterStudy cannot represent exactly are reported.
	 *
	 * @param int   $quiz_id   Masteriyo quiz ID (settings are read from it).
	 * @param int   $copy_id   MasterStudy quiz (copy) ID.
	 * @param int[] $questions Attached questions: Masteriyo question ID => MasterStudy question (copy) ID.
	 * @param int   $per_page  Masteriyo questions per page (0 = all).
	 * @param array $context   Report context.
	 */
	private static function migrate_quiz_answer_rules( int $quiz_id, int $copy_id, array $questions, int $per_page, array $context ): void {
		$question_ids = array_map( 'intval', array_keys( $questions ) );
		$shuffled     = array_values(
			array_filter(
				$question_ids,
				function ( $question_id ) {
					return self::to_bool( get_post_meta( $question_id, '_randomize', true ) );
				}
			)
		);

		$rules = array( 'random_answers' => ! empty( $shuffled ) );

		if ( self::to_bool( get_post_meta( $quiz_id, '_require_all_questions_attempted', true ) ) ) {
			$rules['required_question_ids'] = array_values( $questions );
		}

		Target::set_quiz_answer_rules( $copy_id, $rules );

		$lost = array();

		if ( ! empty( $shuffled ) && count( $shuffled ) < count( $question_ids ) ) {
			/* translators: 1: shuffled questions, 2: all questions */
			$lost[] = sprintf( __( 'answer shuffling was set on %1$d of %2$d questions — MasterStudy shuffles the answers of every question in the quiz', 'masterstudy-lms-learning-management-system' ), count( $shuffled ), count( $question_ids ) );
		}

		if ( $per_page > 1 && $per_page < count( $question_ids ) ) {
			/* translators: %d: questions per page */
			$lost[] = sprintf( __( 'Masteriyo showed %d questions per page — MasterStudy pagination shows one question per page', 'masterstudy-lms-learning-management-system' ), $per_page );
		}

		$feedback = 0;

		foreach ( $question_ids as $question_id ) {
			if ( '' !== trim( (string) get_post_meta( $question_id, '_positive_feedback', true ) ) || '' !== trim( (string) get_post_meta( $question_id, '_negative_feedback', true ) ) ) {
				++$feedback;
			}
		}

		if ( $feedback ) {
			/* translators: %d: number of questions */
			$lost[] = sprintf( __( '%d question(s) have separate feedback for correct and wrong answers — MasterStudy has one explanation per question (the feedback texts are kept in the question meta)', 'masterstudy-lms-learning-management-system' ), $feedback );
		}

		if ( empty( $lost ) ) {
			return;
		}

		self::report(
			Report::GROUP_QUIZZES,
			array(
				'source_id' => $quiz_id,
				'title'     => self::title_of( $quiz_id ),
				'type'      => __( 'Quiz settings', 'masterstudy-lms-learning-management-system' ),
				'reason'    => ucfirst( implode( '; ', $lost ) ) . '.',
				'status'    => Report::STATUS_PARTIAL,
				'post_id'   => $copy_id,
			),
			$context
		);
	}

	/**
	 * Question IDs of a Masteriyo quiz in Masteriyo's display order (masteriyo_get_all_question_ids_by_quiz():
	 * relation table menu_order first, else the question's own menu_order): the questions the quiz owns
	 * (post_parent) plus question-bank questions linked through masteriyo_quiz_question_rel.
	 *
	 * @param int $quiz_id Masteriyo quiz ID.
	 * @return int[]
	 */
	private static function quiz_question_ids( int $quiz_id ): array {
		global $wpdb;

		$orders = array();
		$owned  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, menu_order FROM {$wpdb->posts}
				 WHERE post_type = %s AND post_parent = %d AND post_status <> 'trash'",
				self::QUESTION,
				$quiz_id
			)
		);

		foreach ( (array) $owned as $row ) {
			$orders[ (int) $row->ID ] = (int) $row->menu_order;
		}

		$rel_table = $wpdb->prefix . 'masteriyo_quiz_question_rel';

		if ( self::table_exists( $rel_table ) ) {
			$linked = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT question_id, menu_order FROM {$rel_table} WHERE quiz_id = %d ORDER BY id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$quiz_id
				)
			);

			foreach ( (array) $linked as $row ) {
				if ( (int) $row->question_id ) {
					$orders[ (int) $row->question_id ] = (int) $row->menu_order;
				}
			}
		}

		uksort(
			$orders,
			function ( $a, $b ) use ( $orders ) {
				return $orders[ $a ] === $orders[ $b ] ? $a <=> $b : $orders[ $a ] <=> $orders[ $b ];
			}
		);

		return array_keys( $orders );
	}

	/**
	 * Quiz pass mark as a percentage. Masteriyo stores it in points (default) or percent;
	 * quizzes Masteriyo migrated from MasterStudy carry the original percent in _pass_mark.
	 *
	 * @param int   $quiz_id      Quiz ID.
	 * @param int[] $question_ids Question IDs (for the full mark fallback).
	 * @return float|string Percent, or '' when unknown.
	 */
	private static function quiz_pass_percent( int $quiz_id, array $question_ids ) {
		$pass_mark = get_post_meta( $quiz_id, '_pass_mark', true );

		if ( '' === (string) $pass_mark ) {
			return get_post_meta( $quiz_id, 'passing_grade', true );
		}

		$type = (string) get_post_meta( $quiz_id, '_pass_mark_type', true );

		if ( 'percentage' === $type || ( '' === $type && metadata_exists( 'post', $quiz_id, 'passing_grade' ) ) ) {
			return min( 100, (float) $pass_mark );
		}

		$full_mark = self::quiz_full_mark( $quiz_id, $question_ids );

		return $full_mark > 0 ? round( min( 100, (float) $pass_mark / $full_mark * 100 ), 2 ) : '';
	}

	/**
	 * Quiz full mark: _full_mark meta, else the sum of question points (1 per question by default).
	 *
	 * @param int   $quiz_id      Quiz ID.
	 * @param int[] $question_ids Question IDs.
	 */
	private static function quiz_full_mark( int $quiz_id, array $question_ids ): float {
		$full_mark = (float) get_post_meta( $quiz_id, '_full_mark', true );

		if ( $full_mark > 0 ) {
			return $full_mark;
		}

		foreach ( $question_ids as $question_id ) {
			$points     = get_post_meta( $question_id, '_points', true );
			$full_mark += '' === (string) $points ? 1 : (float) $points;
		}

		return $full_mark;
	}

	/**
	 * Copy a single Masteriyo quiz question to a MasterStudy question.
	 *
	 * A question-bank question shared by several quizzes gets ONE copy (reused afterwards). Types MasterStudy
	 * cannot represent, and questions without usable answers, are not copied — they are reported (and skipped
	 * from the quiz question list, like Masteriyo does). The Masteriyo answers JSON is kept under _migrated_answers.
	 *
	 * @param int   $question_id Masteriyo question ID.
	 * @param array $context     Report context (parent quiz, course).
	 * @return int MasterStudy question (copy) ID, 0 when the question was not imported.
	 */
	private static function process_question_migration( int $question_id, array $context = array() ): int {
		$post = get_post( $question_id );

		if ( ! $post ) {
			self::report(
				Report::GROUP_QUESTIONS,
				array(
					'source_id' => $question_id,
					/* translators: %d: question ID */
					'title'     => sprintf( __( 'Question #%d', 'masterstudy-lms-learning-management-system' ), $question_id ),
					'type'      => __( 'Question bank question', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'The question linked to this quiz no longer exists, so it could not be added to the quiz.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_FAILED,
				),
				$context
			);

			return 0;
		}

		// A question-bank question shared by several quizzes is copied once.
		$existing = Target::copy_of( self::SOURCE, $question_id );

		if ( $existing ) {
			return $existing;
		}

		if ( self::QUESTION !== $post->post_type ) {
			self::report(
				Report::GROUP_QUESTIONS,
				array(
					'source_id' => $question_id,
					'title'     => $post->post_title,
					'type'      => $post->post_type,
					/* translators: %s: post type */
					'reason'    => sprintf( __( 'The post linked to this quiz is a "%s" post, not a Masteriyo question, so it was not added to the quiz.', 'masterstudy-lms-learning-management-system' ), $post->post_type ),
					'status'    => Report::STATUS_UNSUPPORTED,
					'post_id'   => $question_id,
				),
				$context
			);

			return 0;
		}

		$mto_type  = (string) get_post_meta( $question_id, '_type', true );
		$raw       = json_decode( $post->post_content, true );
		$answers   = self::normalize_mto_answers( $raw );
		$ms_type   = self::determine_question_type( $mto_type, $answers );
		$formatted = $ms_type ? self::format_answers( $answers, $mto_type, $ms_type, $raw ) : array();

		// fill_the_gap may produce a single-item array — only skip truly empty results.
		if ( ! $ms_type || empty( $formatted ) ) {
			Helper::log(
				'warning',
				$ms_type
					? sprintf( 'Migration: Question %d has no usable answers — not imported.', $question_id )
					: sprintf( 'Migration: Question %d of type "%s" has no MasterStudy equivalent — not imported.', $question_id, $mto_type )
			);

			self::report(
				Report::GROUP_QUESTIONS,
				array(
					'source_id' => $question_id,
					'title'     => $post->post_title,
					'type'      => self::question_type_label( $mto_type ),
					'reason'    => self::unsupported_question_reason( $mto_type, (bool) $ms_type ),
					'status'    => Report::STATUS_UNSUPPORTED,
					'post_id'   => $question_id,
				),
				$context
			);

			return 0;
		}

		// Masteriyo keeps answers JSON in post_content and the description in post_excerpt;
		// MasterStudy keeps the question content in post_content.
		$copy = Target::copy_post(
			$question_id,
			PostType::QUESTION,
			self::SOURCE,
			array(
				'post_content' => (string) $post->post_excerpt,
				'post_excerpt' => '',
			)
		);

		Target::store_unmigrated_meta( $copy, 'answers', $post->post_content );
		Target::store_unmigrated_meta( $copy, 'points', get_post_meta( $question_id, '_points', true ) );
		Target::store_unmigrated_meta( $copy, 'positive_feedback', get_post_meta( $question_id, '_positive_feedback', true ) );
		Target::store_unmigrated_meta( $copy, 'negative_feedback', get_post_meta( $question_id, '_negative_feedback', true ) );

		$explanation = (string) get_post_meta( $question_id, '_answer_explanation', true );

		Target::set_question(
			$copy,
			$ms_type,
			$formatted,
			array(
				'explanation' => '' !== $explanation ? $explanation : (string) get_post_meta( $question_id, '_feedback', true ),
			)
		);

		return $copy;
	}

	/**
	 * Determines the MasterStudy question type for a Masteriyo question type.
	 *
	 * @param string $mto_type Masteriyo question type.
	 * @param array  $answers  Normalized Masteriyo answers.
	 * @return string|null The mapped MasterStudy type, or null if unsupported.
	 */
	private static function determine_question_type( $mto_type, array $answers ) {
		switch ( $mto_type ) {
			case 'true-false':
				return 'true_false';
			case 'multiple-choice':
				return 'multi_choice';
			case 'single-choice':
				return 'single_choice';
			case 'fill-in-the-blanks':
				return 'fill_the_gap';
			case 'sortable':
				return 'sortable';
			case 'matching':
				// Rows are TextToText {prompt, match}, ImageToText {prompt, match, image} or
				// ImageToImage {prompt, match, imagePrompt, imageMatch}; any image needs image_match.
				foreach ( $answers as $answer ) {
					if ( self::matching_prompt_image( $answer ) || self::matching_match_image( $answer ) ) {
						return 'image_match';
					}
				}
				return 'item_match';
			case 'text-answer':
				// Text answers are manually graded; only those with expected keywords (e.g. MasterStudy
				// keywords questions migrated into Masteriyo) can be graded by MasterStudy.
				foreach ( $answers as $answer ) {
					if ( '' !== trim( (string) ( $answer['name'] ?? '' ) ) ) {
						return 'keywords';
					}
				}
				return null;
			default:
				return null;
		}
	}

	/**
	 * Human-readable label of a Masteriyo question type.
	 *
	 * @param string $mto_type Masteriyo question type.
	 */
	private static function question_type_label( string $mto_type ): string {
		$labels = array(
			'true-false'         => __( 'True / False', 'masterstudy-lms-learning-management-system' ),
			'single-choice'      => __( 'Single choice', 'masterstudy-lms-learning-management-system' ),
			'multiple-choice'    => __( 'Multiple choice', 'masterstudy-lms-learning-management-system' ),
			'fill-in-the-blanks' => __( 'Fill in the blanks', 'masterstudy-lms-learning-management-system' ),
			'sortable'           => __( 'Sortable', 'masterstudy-lms-learning-management-system' ),
			'matching'           => __( 'Matching', 'masterstudy-lms-learning-management-system' ),
			'text-answer'        => __( 'Text answer (open-ended)', 'masterstudy-lms-learning-management-system' ),
			'audio'              => __( 'Audio answer', 'masterstudy-lms-learning-management-system' ),
			'video'              => __( 'Video answer', 'masterstudy-lms-learning-management-system' ),
		);

		if ( isset( $labels[ $mto_type ] ) ) {
			return $labels[ $mto_type ];
		}

		return '' !== $mto_type ? ucwords( str_replace( array( '-', '_' ), ' ', $mto_type ) ) : __( 'Unknown type', 'masterstudy-lms-learning-management-system' );
	}

	/**
	 * Report reason for a question that was not imported.
	 *
	 * @param string $mto_type   Masteriyo question type.
	 * @param bool   $no_answers Whether the type is supported but the question has no usable answers.
	 */
	private static function unsupported_question_reason( string $mto_type, bool $no_answers = false ): string {
		if ( $no_answers ) {
			return __( 'The question has no usable answers — it was not imported and not added to the quiz (it stays in Masteriyo).', 'masterstudy-lms-learning-management-system' );
		}

		switch ( $mto_type ) {
			case 'text-answer':
				return __( 'MasterStudy has no open-ended (manually graded) question type — the question was not imported and not added to the quiz (it stays in Masteriyo).', 'masterstudy-lms-learning-management-system' );
			case 'audio':
			case 'video':
				return __( 'MasterStudy has no audio/video answer question type — the question was not imported and not added to the quiz (it stays in Masteriyo).', 'masterstudy-lms-learning-management-system' );
		}

		return sprintf(
			/* translators: %s: Masteriyo question type */
			__( 'MasterStudy has no equivalent for the Masteriyo "%s" question type — the question was not imported and not added to the quiz (it stays in Masteriyo).', 'masterstudy-lms-learning-management-system' ),
			$mto_type
		);
	}

	/**
	 * Masteriyo answers decoded from post_content, as a list of arrays.
	 *
	 * @param mixed $raw Decoded JSON.
	 */
	private static function normalize_mto_answers( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$answers = array();

		foreach ( $raw as $answer ) {
			$answers[] = is_array( $answer ) ? $answer : array( 'name' => (string) $answer );
		}

		return $answers;
	}

	/**
	 * Formats Masteriyo answers into Target::set_question() normalized answers.
	 *
	 * @param array  $answers  Normalized Masteriyo answers.
	 * @param string $mto_type Masteriyo type.
	 * @param string $ms_type  MasterStudy type.
	 * @param mixed  $raw      Decoded post_content (fill-in-the-blanks may be a plain string).
	 */
	private static function format_answers( array $answers, $mto_type, $ms_type, $raw ) {
		$formatted = array();

		switch ( $ms_type ) {
			case 'true_false':
				$true_correct = null;

				foreach ( $answers as $answer ) {
					$name = strtolower( trim( wp_strip_all_tags( (string) ( $answer['name'] ?? '' ) ) ) );

					if ( 'true' === $name ) {
						$true_correct = ! empty( $answer['correct'] );
					} elseif ( 'false' === $name && null === $true_correct ) {
						$true_correct = empty( $answer['correct'] );
					}
				}

				if ( null === $true_correct ) {
					$true_correct = ! empty( $answers[0]['correct'] );
				}

				return array( array( 'correct' => $true_correct ) );

			case 'fill_the_gap':
				if ( is_string( $raw ) ) {
					$text = $raw;
				} else {
					$text = implode( '', array_map( 'strval', wp_list_pluck( $answers, 'name' ) ) );
				}

				// Masteriyo blanks are {{answer}}; MasterStudy blanks are |answer|. Questions
				// Masteriyo migrated from MasterStudy may already carry |answer| markers.
				$text = preg_replace( '/{{\s*([^{}]*?)\s*}}/', '|$1|', $text );

				if ( '' === trim( wp_strip_all_tags( $text ) ) ) {
					return array();
				}

				return array( array( 'text' => $text ) );

			case 'sortable':
			case 'keywords':
				foreach ( $answers as $answer ) {
					$text = sanitize_text_field( $answer['name'] ?? '' );

					if ( '' !== $text ) {
						$formatted[] = array( 'text' => $text );
					}
				}

				return $formatted;

			case 'item_match':
				foreach ( $answers as $answer ) {
					// `name`/`match_answer` come from questions Masteriyo migrated from MasterStudy.
					$prompt = sanitize_text_field( $answer['prompt'] ?? $answer['name'] ?? '' );
					$match  = sanitize_text_field( $answer['match'] ?? $answer['match_answer'] ?? '' );

					if ( '' !== $prompt || '' !== $match ) {
						$formatted[] = array(
							'prompt' => $prompt,
							'match'  => $match,
						);
					}
				}

				return $formatted;

			case 'image_match':
				foreach ( $answers as $answer ) {
					$prompt       = sanitize_text_field( $answer['prompt'] ?? '' );
					$match        = sanitize_text_field( $answer['match'] ?? '' );
					$prompt_image = self::matching_prompt_image( $answer );
					$match_image  = self::matching_match_image( $answer );

					if ( '' === $prompt && '' === $match && ! $prompt_image && ! $match_image ) {
						continue;
					}

					$formatted[] = array(
						'prompt'          => $prompt,
						'prompt_image_id' => $prompt_image,
						'match'           => $match,
						'match_image_id'  => $match_image,
					);
				}

				return $formatted;

			default:
				foreach ( $answers as $answer ) {
					$choice = sanitize_text_field( $answer['name'] ?? '' );

					if ( '' !== $choice ) {
						$formatted[] = array(
							'text'     => $choice,
							'correct'  => self::to_bool( $answer['correct'] ?? false ),
							'image_id' => absint( $answer['image'] ?? 0 ),
						);
					}
				}

				return $formatted;
		}
	}

	/**
	 * Prompt image attachment of a Masteriyo matching row: `imagePrompt` (ImageToImage) or `image` (ImageToText).
	 *
	 * @param array $answer Matching row.
	 */
	private static function matching_prompt_image( array $answer ): int {
		if ( 'ImageToImage' === ( $answer['type'] ?? '' ) || ! empty( $answer['imagePrompt'] ) ) {
			return absint( $answer['imagePrompt'] ?? 0 );
		}

		return absint( $answer['image'] ?? 0 );
	}

	/**
	 * Match image attachment of a Masteriyo matching row (ImageToImage only).
	 *
	 * @param array $answer Matching row.
	 */
	private static function matching_match_image( array $answer ): int {
		return absint( $answer['imageMatch'] ?? 0 );
	}

	/**
	 * Copy a Masteriyo assignment (Pro addon) to a MasterStudy assignment (Pro), with its
	 * student submissions (mto-assignment-reply posts).
	 *
	 * @param int   $assignment_id mto-assignment post ID.
	 * @param int   $course_id     MasterStudy course (copy) ID.
	 * @param array $context       Report context (parent, course).
	 * @return int Copy ID, 0 when MasterStudy Pro is not active (not imported).
	 */
	private static function migrate_assignment( int $assignment_id, int $course_id, array $context = array() ): int {
		if ( ! ProTarget::pro_active() ) {
			Helper::log( 'warning', sprintf( 'Migration: Assignment %d skipped — assignments require MasterStudy LMS Pro.', $assignment_id ) );

			self::report(
				Report::GROUP_ASSIGNMENTS,
				array(
					'source_id' => $assignment_id,
					'title'     => self::title_of( $assignment_id ),
					'type'      => __( 'Masteriyo assignment', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'Assignments require MasterStudy LMS Pro — the assignment and its submissions were not imported and not added to the course curriculum (they stay in Masteriyo).', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_UNSUPPORTED,
					'post_id'   => $assignment_id,
				),
				$context
			);

			return 0;
		}

		$total_points = (float) get_post_meta( $assignment_id, '_total_points', true );
		$pass_points  = (float) get_post_meta( $assignment_id, '_pass_points', true );
		$due_date     = trim( (string) get_post_meta( $assignment_id, '_due_date', true ) );
		$copy         = Target::copy_post( $assignment_id, PostType::ASSIGNMENT, self::SOURCE );

		ProTarget::set_assignment(
			$copy,
			array(
				'attempts'      => absint( self::first_meta( $assignment_id, array( '_max_attempts', '_attempts_allowed' ) ) ),
				'passing_grade' => $total_points > 0 && $pass_points > 0 ? min( 100, $pass_points / $total_points * 100 ) : '',
				'files'         => self::attachment_ids( maybe_unserialize( get_post_meta( $assignment_id, '_download_materials', true ) ) ),
			)
		);

		Target::store_unmigrated_meta( $copy, 'total_points', get_post_meta( $assignment_id, '_total_points', true ) );
		Target::store_unmigrated_meta( $copy, 'pass_points', get_post_meta( $assignment_id, '_pass_points', true ) );
		Target::store_unmigrated_meta( $copy, 'due_date', $due_date );

		if ( '' !== $due_date ) {
			self::report(
				Report::GROUP_ASSIGNMENTS,
				array(
					'source_id' => $assignment_id,
					'title'     => self::title_of( $assignment_id ),
					'type'      => __( 'Assignment due date', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'MasterStudy assignments have no due date — the assignment was imported without it.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_PARTIAL,
					'post_id'   => $copy,
				),
				$context
			);
		}

		self::migrate_assignment_replies( $assignment_id, $copy, $course_id, $total_points, $pass_points, $context );

		return $copy;
	}

	/**
	 * Import the student submissions (mto-assignment-reply posts) of an assignment, oldest first.
	 * The Masteriyo replies stay; a reply already imported is skipped.
	 *
	 * @param int   $assignment_id Masteriyo assignment ID.
	 * @param int   $copy_id       MasterStudy assignment (copy) ID.
	 * @param int   $course_id     MasterStudy course (copy) ID.
	 * @param float $total_points  Masteriyo assignment total points.
	 * @param float $pass_points   Masteriyo assignment pass points.
	 * @param array $context       Report context (parent, course).
	 */
	private static function migrate_assignment_replies( int $assignment_id, int $copy_id, int $course_id, float $total_points, float $pass_points, array $context ): void {
		global $wpdb;

		$replies = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->posts}
				 WHERE post_type = %s AND post_parent = %d AND post_status NOT IN ('trash', 'auto-draft')
				 ORDER BY post_date ASC, ID ASC",
				self::REPLY,
				$assignment_id
			)
		);

		if ( empty( $replies ) ) {
			return;
		}

		// The table is created when the Assignments addon is enabled at the start of the step
		// (see required_addons()); creating it here would commit the open transaction.
		if ( ! self::table_exists( $wpdb->prefix . 'stm_lms_user_assignments' ) ) {
			self::report(
				Report::GROUP_ASSIGNMENTS,
				array(
					'source_id' => $assignment_id,
					'title'     => self::title_of( $assignment_id ),
					'type'      => __( 'Assignment submissions', 'masterstudy-lms-learning-management-system' ),
					/* translators: %d: number of submissions */
					'reason'    => sprintf( __( '%d student submission(s) were not imported because the MasterStudy assignment table is missing — enable the Assignments addon and run the migration again.', 'masterstudy-lms-learning-management-system' ), count( $replies ) ),
					'status'    => Report::STATUS_PARTIAL,
					'post_id'   => $copy_id,
				),
				$context
			);

			return;
		}

		foreach ( $replies as $reply ) {
			$reply_id   = (int) $reply->ID;
			$student_id = (int) $reply->post_author;
			$source_key = 'assignment-reply-' . $reply_id;

			if ( Target::find_migrated_post( PostType::USER_ASSIGNMENT, self::SOURCE, $source_key ) ) {
				continue;
			}

			if ( ! $student_id || ! get_userdata( $student_id ) ) {
				self::report(
					Report::GROUP_ASSIGNMENTS,
					array(
						'source_id' => $reply_id,
						/* translators: %s: user */
						'title'     => sprintf( __( 'Submission by %s', 'masterstudy-lms-learning-management-system' ), self::user_label( $student_id ) ),
						'type'      => __( 'Assignment submission', 'masterstudy-lms-learning-management-system' ),
						'reason'    => __( 'The student who submitted this assignment no longer exists, so the submission was not imported.', 'masterstudy-lms-learning-management-system' ),
						'status'    => Report::STATUS_FAILED,
						/* translators: %s: assignment title */
						'parent'    => sprintf( __( 'Assignment: %s', 'masterstudy-lms-learning-management-system' ), self::title_of( $assignment_id ) ),
						'post_id'   => $copy_id,
					),
					$context
				);

				continue;
			}

			$earned  = self::first_meta( $reply_id, array( '_earned_points', '_points', '_grade' ) );
			$percent = '' !== $earned && is_numeric( $earned ) && $total_points > 0 ? (float) $earned / $total_points * 100 : null;
			$status  = self::assignment_reply_status( $reply, $earned, $total_points, $pass_points );
			$content = '' !== trim( (string) $reply->post_content ) ? (string) $reply->post_content : self::first_meta( $reply_id, array( '_answer', '_content' ) );

			$files = array();

			foreach ( array( '_attachments', '_files', '_download_materials' ) as $files_key ) {
				$files = array_merge( $files, self::attachment_ids( maybe_unserialize( get_post_meta( $reply_id, $files_key, true ) ) ) );
			}

			$upload = self::first_meta( $reply_id, array( '_upload' ) );

			if ( '' !== $upload && ! is_numeric( $upload ) ) {
				$upload_id = attachment_url_to_postid( $upload );

				if ( $upload_id ) {
					$files[] = $upload_id;
				} else {
					$content .= sprintf( '<p><a href="%1$s">%1$s</a></p>', esc_url( $upload ) );
				}
			}

			ProTarget::add_assignment_submission(
				array(
					'assignment_id'    => $copy_id,
					'course_id'        => $course_id,
					'student_id'       => $student_id,
					'content'          => $content,
					'status'           => $status,
					'grade'            => in_array( $status, array( 'passed', 'not_passed' ), true ) ? $percent : null,
					'review'           => self::first_meta( $reply_id, array( '_note', '_feedback', '_review', '_instructor_note', '_remarks' ) ),
					'attachments'      => array_values( array_unique( array_filter( $files ) ) ),
					// MasterStudy lists the files attached to (post_parent =) the submission: the Masteriyo attachments
					// stay with the Masteriyo reply, the submission gets its own attachment records.
					'copy_attachments' => true,
					'date'             => self::gmt_to_timestamp( $reply->post_date_gmt ) ? self::gmt_to_timestamp( $reply->post_date_gmt ) : self::local_to_timestamp( $reply->post_date ),
					'source_id'        => $source_key,
				),
				self::SOURCE
			);
		}
	}

	/**
	 * MasterStudy submission status (draft|pending|passed|not_passed) of a Masteriyo assignment reply.
	 *
	 * @param object $reply        Reply post row.
	 * @param string $earned       Earned points ('' when not graded).
	 * @param float  $total_points Assignment total points.
	 * @param float  $pass_points  Assignment pass points.
	 */
	private static function assignment_reply_status( $reply, string $earned, float $total_points, float $pass_points ): string {
		$reply_id = (int) $reply->ID;
		$graded   = '' !== $earned && is_numeric( $earned );
		$by_score = function () use ( $earned, $graded, $total_points, $pass_points ) {
			if ( ! $graded ) {
				return 'pending';
			}

			if ( $pass_points > 0 ) {
				return (float) $earned >= $pass_points ? 'passed' : 'not_passed';
			}

			return $total_points > 0 && (float) $earned <= 0 ? 'not_passed' : 'passed';
		};

		$candidates = array(
			(string) get_post_meta( $reply_id, '_result', true ),
			(string) get_post_meta( $reply_id, '_status', true ),
			(string) $reply->post_status,
		);

		foreach ( $candidates as $raw ) {
			$raw = strtolower( trim( $raw ) );

			if ( in_array( $raw, array( 'approved', 'passed', 'pass', 'accepted', 'completed', 'complete' ), true ) ) {
				return 'passed';
			}

			if ( in_array( $raw, array( 'rejected', 'failed', 'fail', 'declined', 'not_passed' ), true ) ) {
				return 'not_passed';
			}

			if ( in_array( $raw, array( 'reviewed', 'graded', 'evaluated', 'marked' ), true ) ) {
				return $by_score();
			}

			if ( 'draft' === $raw ) {
				return 'draft';
			}
		}

		// "Submitted/pending/publish": graded only when the instructor already reviewed it.
		if ( $graded && '' !== self::first_meta( $reply_id, array( '_reviewed_at' ) ) ) {
			return $by_score();
		}

		return 'pending';
	}

	/**
	 * Copy a Masteriyo Zoom meeting (Pro addon) to a MasterStudy Zoom conference lesson (Pro)
	 * linked to the EXISTING meeting — no Zoom API call. Without a numeric meeting ID the join
	 * link is kept as a YouTube stream lesson or as a link in a text lesson.
	 *
	 * @param int   $zoom_id mto-zoom post ID.
	 * @param array $context Report context (parent, course).
	 * @return int Copy ID, 0 when MasterStudy Pro is not active (not imported).
	 */
	private static function migrate_zoom( int $zoom_id, array $context = array() ): int {
		if ( ! ProTarget::pro_active() ) {
			Helper::log( 'warning', sprintf( 'Migration: Zoom meeting %d skipped — Zoom lessons require MasterStudy LMS Pro.', $zoom_id ) );

			self::report(
				Report::GROUP_MEETINGS,
				array(
					'source_id' => $zoom_id,
					'title'     => self::title_of( $zoom_id ),
					'type'      => __( 'Masteriyo Zoom meeting', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'Zoom meetings require MasterStudy LMS Pro — the meeting was not imported and not added to the course curriculum (it stays in Masteriyo).', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_UNSUPPORTED,
					'post_id'   => $zoom_id,
				),
				$context
			);

			return 0;
		}

		$post       = get_post( $zoom_id );
		$meeting_id = self::first_meta( $zoom_id, array( '_meeting_id', '_zoom_meeting_id', '_id' ) );
		$join_url   = self::first_meta( $zoom_id, array( '_join_url', '_meeting_url', '_url' ) );
		$password   = self::first_meta( $zoom_id, array( '_password', '_passcode' ) );
		$timezone   = self::first_meta( $zoom_id, array( '_time_zone', '_timezone' ) );
		$starts_at  = self::date_meta_timestamp( $zoom_id, array( '_starts_at', '_start_time' ), $timezone );
		$ends_at    = self::date_meta_timestamp( $zoom_id, array( '_ends_at', '_end_time' ), $timezone );
		$duration   = absint( get_post_meta( $zoom_id, '_duration', true ) );

		if ( ! $duration && $starts_at && $ends_at > $starts_at ) {
			$duration = (int) round( ( $ends_at - $starts_at ) / MINUTE_IN_SECONDS );
		}

		if ( ! $ends_at && $starts_at && $duration ) {
			$ends_at = $starts_at + $duration * MINUTE_IN_SECONDS;
		}

		$copy = Target::copy_post( $zoom_id, PostType::LESSON, self::SOURCE );

		Target::set_lesson( $copy, array( 'type' => 'text' ) );

		Target::store_unmigrated_meta( $copy, 'zoom_meeting_id', $meeting_id );
		Target::store_unmigrated_meta( $copy, 'zoom_join_url', $join_url );
		Target::store_unmigrated_meta( $copy, 'zoom_password', $password );
		Target::store_unmigrated_meta( $copy, 'zoom_time_zone', $timezone );

		$linked = ProTarget::set_zoom_lesson(
			$copy,
			array(
				'meeting_id' => $meeting_id,
				'join_url'   => $join_url,
				'password'   => $password,
				'start'      => $starts_at,
				'duration'   => $duration,
				'timezone'   => $timezone,
				'agenda'     => $post ? (string) $post->post_excerpt : '',
				'host_id'    => $post ? (int) $post->post_author : 0,
			),
			self::SOURCE
		);

		if ( $linked ) {
			return $copy;
		}

		if ( '' !== $join_url && ProTarget::set_stream_lesson( $copy, $join_url, $starts_at, $ends_at ) ) {
			return $copy;
		}

		$content = (string) get_post_field( 'post_content', $copy );

		if ( '' !== $join_url && false === strpos( $content, $join_url ) ) {
			global $wpdb;

			// Direct write on the copy: no save hooks (they may call the Zoom API for lessons).
			$wpdb->update(
				$wpdb->posts,
				array(
					'post_content' => $content . sprintf(
						'<p><a href="%1$s" target="_blank" rel="noopener">%2$s</a></p>',
						esc_url( $join_url ),
						esc_html__( 'Join the meeting', 'masterstudy-lms-learning-management-system' )
					),
				),
				array( 'ID' => $copy )
			);
			clean_post_cache( $copy );
		}

		self::report(
			Report::GROUP_MEETINGS,
			array(
				'source_id' => $zoom_id,
				'title'     => self::title_of( $zoom_id ),
				'type'      => __( 'Masteriyo Zoom meeting', 'masterstudy-lms-learning-management-system' ),
				'reason'    => '' !== $join_url
					? __( 'The meeting has no numeric Zoom meeting ID, so it could not be linked as a MasterStudy Zoom lesson — it was imported as a text lesson with the join link.', 'masterstudy-lms-learning-management-system' )
					: __( 'The meeting has no Zoom meeting ID or join URL — it was imported as an empty text lesson.', 'masterstudy-lms-learning-management-system' ),
				'status'    => Report::STATUS_PARTIAL,
				'post_id'   => $copy,
			),
			$context
		);

		return $copy;
	}

	/**
	 * Copy a Masteriyo Google Meet item to a MasterStudy Google Meet (Pro Plus) without calling
	 * the Google API: the existing meeting link and schedule are written directly.
	 *
	 * @param int   $meet_id mto-google-meet post ID.
	 * @param array $context Report context (parent, course).
	 * @return int Copy ID, 0 when MasterStudy Pro Plus is not active (not imported).
	 */
	private static function migrate_google_meet( int $meet_id, array $context = array() ): int {
		if ( ! ProTarget::plus_active() ) {
			Helper::log( 'warning', sprintf( 'Migration: Google Meet %d skipped — Google Meet requires MasterStudy LMS Pro Plus.', $meet_id ) );

			self::report(
				Report::GROUP_MEETINGS,
				array(
					'source_id' => $meet_id,
					'title'     => self::title_of( $meet_id ),
					'type'      => __( 'Masteriyo Google Meet', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'Google Meet requires MasterStudy LMS Pro Plus — the meeting was not imported and not added to the course curriculum (it stays in Masteriyo).', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_UNSUPPORTED,
					'post_id'   => $meet_id,
				),
				$context
			);

			return 0;
		}

		// GoogleMeetRepository stores `_`-prefixed keys; Masteriyo's own Tutor LMS importer writes them
		// without the prefix, so both variants are read.
		$post       = get_post( $meet_id );
		$meet_url   = self::first_meta( $meet_id, array( '_meet_url', 'meet_url' ) );
		$meeting_id = self::first_meta( $meet_id, array( '_meeting_id', 'meeting_id' ) );
		$timezone   = self::first_meta( $meet_id, array( '_time_zone', 'time_zone' ) );
		$starts_at  = self::date_meta_timestamp( $meet_id, array( '_starts_at', 'starts_at' ), $timezone );
		$ends_at    = self::date_meta_timestamp( $meet_id, array( '_ends_at', 'ends_at' ), $timezone );

		// Creating the copy fires save_post: the Google Calendar hook must be muted before.
		$restore = ProTarget::mute_google_meet_hooks();

		try {
			$copy = Target::copy_post( $meet_id, PostType::GOOGLE_MEET, self::SOURCE );

			ProTarget::set_google_meet(
				$copy,
				array(
					'url'      => $meet_url,
					'summary'  => $post ? ( '' !== trim( (string) $post->post_content ) ? $post->post_content : $post->post_title ) : '',
					'start'    => $starts_at,
					'end'      => $ends_at,
					'timezone' => $timezone,
				)
			);
		} finally {
			$restore();
		}

		// The Google event ID is left out on purpose: with it MasterStudy calls the Google API on enrollment.
		Target::store_unmigrated_meta( $copy, 'google_meet_event_id', $meeting_id );
		Target::store_unmigrated_meta( $copy, 'google_calendar_url', self::first_meta( $meet_id, array( '_calender_url', 'calender_url' ) ) );

		if ( '' === $meet_url ) {
			self::report(
				Report::GROUP_MEETINGS,
				array(
					'source_id' => $meet_id,
					'title'     => self::title_of( $meet_id ),
					'type'      => __( 'Masteriyo Google Meet', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'The meeting has no Google Meet link — it was imported without a join button.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_PARTIAL,
					'post_id'   => $copy,
				),
				$context
			);
		}

		return $copy;
	}

	/**
	 * Migrate course info from the Masteriyo course to its MasterStudy copy.
	 *
	 * @param int $course_id Masteriyo course ID (read).
	 * @param int $copy_id   MasterStudy course (copy) ID (written).
	 */
	private static function migrate_course_info( int $course_id, int $copy_id ): void {
		$course_report = array(
			'source_id' => $course_id,
			'title'     => self::title_of( $course_id ),
			'status'    => Report::STATUS_PARTIAL,
			'post_id'   => $copy_id,
		);
		$access_mode   = (string) get_post_meta( $course_id, '_access_mode', true );
		$regular_price = (float) get_post_meta( $course_id, '_regular_price', true );
		$sale_price    = get_post_meta( $course_id, '_sale_price', true );
		$sale_price    = '' !== (string) $sale_price ? (float) $sale_price : null;

		if ( '' === $access_mode ) {
			$visibility  = self::term_names( $course_id, 'course_visibility' );
			$access_mode = in_array( 'paid', array_map( 'strtolower', $visibility ), true ) ? 'one_time' : 'open';
		}

		if ( in_array( $access_mode, array( 'open', 'need_registration' ), true ) ) {
			Target::set_pricing( $copy_id, 0 );
		} else {
			Target::set_pricing( $copy_id, $regular_price, $sale_price );
			// Sale dates are serialized Masteriyo\DateTime objects: read the raw meta (see date_meta_timestamp()).
			self::set_sale_dates(
				$copy_id,
				self::date_meta_timestamp( $course_id, array( '_date_on_sale_from' ) ),
				self::date_meta_timestamp( $course_id, array( '_date_on_sale_to' ) )
			);
		}

		if ( 'recurring' === $access_mode ) {
			self::migrate_subscription_pricing( $course_id, $copy_id, $regular_price, $sale_price, $course_report );
		}

		if ( 'close' === $access_mode ) {
			Report::add(
				Report::GROUP_COURSES,
				array_merge(
					$course_report,
					array(
						'type'   => __( 'Closed course', 'masterstudy-lms-learning-management-system' ),
						'reason' => __( 'MasterStudy has no "closed" access mode — the course was imported as open for enrollment with its regular price.', 'masterstudy-lms-learning-management-system' ),
					)
				)
			);
		}

		Target::store_unmigrated_meta( $copy_id, 'access_mode', $access_mode );

		$level = self::course_difficulty( $course_id );

		if ( '' !== $level ) {
			Target::set_level( $copy_id, $level );
		}

		$duration = absint( get_post_meta( $course_id, '_duration', true ) );

		Target::set_course_info(
			$copy_id,
			array(
				'basic_info'    => (string) get_post_meta( $course_id, '_highlights', true ),
				'duration_info' => $duration ? self::format_minutes( $duration ) : '',
			)
		);

		Target::store_unmigrated_meta( $copy_id, 'enable_course_retake', get_post_meta( $course_id, '_enable_course_retake', true ) );
		Target::store_unmigrated_meta( $copy_id, 'enrollment_limit', absint( get_post_meta( $course_id, '_enrollment_limit', true ) ) ?: '' ); // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
		Target::store_unmigrated_meta( $copy_id, 'featured_video_source', get_post_meta( $course_id, '_featured_video_source', true ) );
		Target::store_unmigrated_meta( $copy_id, 'featured_video_url', get_post_meta( $course_id, '_featured_video_url', true ) );
		Target::store_unmigrated_meta( $copy_id, 'course_tags', implode( ', ', self::term_names( $course_id, 'course_tag' ) ) );

		// Enrollment expiration (days after enrollment) maps to the MasterStudy course expiration.
		if ( self::to_bool( get_post_meta( $course_id, '_enrollment_expiration_enabled', true ) ) ) {
			$expiration_days = absint( get_post_meta( $course_id, '_enrollment_expiration_duration', true ) );

			Target::store_unmigrated_meta( $copy_id, 'enrollment_expiration_duration', $expiration_days ?: '' ); // phpcs:ignore Universal.Operators.DisallowShortTernary.Found

			if ( $expiration_days ) {
				Target::set_course_info( $copy_id, array( 'end_time' => $expiration_days ) );
			}
		}

		self::migrate_course_flow( $course_id, $copy_id, $course_report );
		self::migrate_scorm( $course_id, $copy_id, $course_report );
		self::migrate_course_certificate( $course_id, $copy_id, $course_report );
		// Prerequisites need the copies of the other courses: see migrate_all_prerequisites() (finalize).
		self::migrate_co_instructors( $course_id, $copy_id, $course_report );
		self::migrate_course_visibility( $course_id, $copy_id, $course_report );
		self::migrate_coming_soon( $course_id, $copy_id, $course_report );
		self::migrate_preview_video( $course_id, $copy_id, $course_report );
		self::migrate_group_price( $course_id, $copy_id, $course_report );
		self::report_course_settings( $course_id, $course_report );
	}

	/**
	 * Masteriyo `course_visibility` terms: "featured" → MasterStudy featured course; catalog/search exclusion has
	 * no MasterStudy equivalent (the course is listed in the catalog) and is reported.
	 *
	 * @param int   $course_id     Masteriyo course ID.
	 * @param int   $copy_id       MasterStudy course (copy) ID.
	 * @param array $course_report Report defaults.
	 */
	private static function migrate_course_visibility( int $course_id, int $copy_id, array $course_report ): void {
		$terms = array_map( 'strtolower', self::term_names( $course_id, 'course_visibility' ) );

		if ( in_array( 'featured', $terms, true ) ) {
			Target::set_course_info( $copy_id, array( 'featured' => true ) );
		}

		$hidden = array_intersect( array( 'exclude-from-catalog', 'exclude-from-search' ), $terms );

		if ( empty( $hidden ) ) {
			return;
		}

		Target::store_unmigrated_meta( $copy_id, 'catalog_visibility', implode( ',', $hidden ) );

		Report::add(
			Report::GROUP_COURSES,
			array_merge(
				$course_report,
				array(
					'type'   => __( 'Catalog visibility', 'masterstudy-lms-learning-management-system' ),
					/* translators: %s: hidden from catalog / search */
					'reason' => sprintf( __( 'The course was hidden in Masteriyo (%s). MasterStudy has no per-course catalog visibility — the course is listed in the course catalog and search; make it private or draft if it must stay hidden.', 'masterstudy-lms-learning-management-system' ), implode( ', ', $hidden ) ),
				)
			)
		);
	}

	/**
	 * Masteriyo "coming soon" (core feature) → MasterStudy upcoming course (Pro Plus addon "coming_soon").
	 * Only a launch date still in the future is migrated.
	 *
	 * @param int   $course_id     Masteriyo course ID.
	 * @param int   $copy_id       MasterStudy course (copy) ID.
	 * @param array $course_report Report defaults.
	 */
	private static function migrate_coming_soon( int $course_id, int $copy_id, array $course_report ): void {
		if ( ! self::to_bool( get_post_meta( $course_id, '_course_coming_soon_enable', true ) ) ) {
			return;
		}

		$start = absint( get_post_meta( $course_id, '_course_coming_soon_timestamp', true ) );
		$start = $start ? $start : self::date_meta_timestamp( $course_id, array( '_course_coming_soon_ending_date' ) );

		if ( ! $start || $start <= time() ) {
			return;
		}

		if ( ProTarget::plus_active() ) {
			ProTarget::set_coming_soon(
				$copy_id,
				array(
					'start'        => $start,
					'show_price'   => true,
					'show_details' => ! self::to_bool( get_post_meta( $course_id, '_course_coming_soon_hide_meta_data', true ) ),
					'preordering'  => false,
				)
			);

			return;
		}

		Report::add(
			Report::GROUP_COURSES,
			array_merge(
				$course_report,
				array(
					'type'   => __( 'Coming soon course', 'masterstudy-lms-learning-management-system' ),
					/* translators: %s: launch date */
					'reason' => sprintf( __( 'The course was "coming soon" until %s. Upcoming courses require MasterStudy LMS Pro Plus — the course is open for enrollment right away.', 'masterstudy-lms-learning-management-system' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $start ) ),
				)
			)
		);
	}

	/**
	 * Masteriyo course featured (preview) video → MasterStudy course preview video (Pro Plus).
	 *
	 * @param int   $course_id     Masteriyo course ID.
	 * @param int   $copy_id       MasterStudy course (copy) ID.
	 * @param array $course_report Report defaults.
	 */
	private static function migrate_preview_video( int $course_id, int $copy_id, array $course_report ): void {
		$source = (string) get_post_meta( $course_id, '_featured_video_source', true );
		$url    = trim( (string) get_post_meta( $course_id, '_featured_video_url', true ) );

		if ( '' === $url ) {
			return;
		}

		if ( ! ProTarget::plus_active() ) {
			Report::add(
				Report::GROUP_COURSES,
				array_merge(
					$course_report,
					array(
						'type'   => __( 'Course preview video', 'masterstudy-lms-learning-management-system' ),
						'reason' => __( 'Course preview videos require MasterStudy LMS Pro Plus — the course shows its featured image instead (the video is kept in the course meta).', 'masterstudy-lms-learning-management-system' ),
					)
				)
			);

			return;
		}

		switch ( $source ) {
			case 'youtube':
			case 'vimeo':
				ProTarget::set_course_preview_video( $copy_id, $source, $url );
				break;
			case 'embed-video':
				ProTarget::set_course_preview_video( $copy_id, 'embed', $url );
				break;
			case 'self-hosted':
				ProTarget::set_course_preview_video( $copy_id, is_numeric( $url ) ? 'html' : 'ext_link', is_numeric( $url ) ? absint( $url ) : $url );
				break;
			case 'bunny-net':
				$bunny = self::bunny_video( $url );
				ProTarget::set_course_preview_video( $copy_id, 'external' === $bunny['source'] ? 'ext_link' : $bunny['source'], $bunny['value'] );
				break;
			default:
				$detected = Target::detect_video_source( $url );
				ProTarget::set_course_preview_video( $copy_id, 'external' === $detected ? 'ext_link' : $detected, $url );
		}
	}

	/**
	 * Masteriyo group course pricing (group-courses addon) → MasterStudy enterprise (group) price (Pro).
	 * MasterStudy has one flat group price: the first fixed-price tier is used.
	 *
	 * @param int   $course_id     Masteriyo course ID.
	 * @param int   $copy_id       MasterStudy course (copy) ID.
	 * @param array $course_report Report defaults.
	 */
	private static function migrate_group_price( int $course_id, int $copy_id, array $course_report ): void {
		if ( ! self::to_bool( get_post_meta( $course_id, '_group_courses_enabled', true ) ) ) {
			return;
		}

		$tiers = json_decode( (string) get_post_meta( $course_id, '_group_courses_pricing_tiers', true ), true );
		$tiers = is_array( $tiers ) ? array_values( array_filter( $tiers, 'is_array' ) ) : array();
		$price = 0.0;
		$lost  = array();

		if ( empty( $tiers ) ) {
			// Legacy single group price.
			$price = (float) get_post_meta( $course_id, '_group_courses_group_price', true );
		}

		foreach ( $tiers as $tier ) {
			if ( 'per-seat' === ( $tier['seat_model'] ?? '' ) ) {
				continue;
			}

			$regular = (float) ( $tier['regular_price'] ?? 0 );
			$sale    = (float) ( $tier['sale_price'] ?? 0 );
			$price   = $sale > 0 && $sale < $regular ? $sale : $regular;
			break;
		}

		if ( count( $tiers ) > 1 ) {
			/* translators: %d: number of pricing tiers */
			$lost[] = sprintf( __( '%d group pricing tiers — MasterStudy has one group price, the first fixed-price tier was used', 'masterstudy-lms-learning-management-system' ), count( $tiers ) );
		}

		if ( ! empty( $tiers ) && $price <= 0 ) {
			$lost[] = __( 'per-seat group pricing has no MasterStudy equivalent — no group price was set', 'masterstudy-lms-learning-management-system' );
		}

		if ( ! ProTarget::pro_active() ) {
			$lost = array( __( 'group (enterprise) prices require MasterStudy LMS Pro — the course can only be bought by individual students', 'masterstudy-lms-learning-management-system' ) );
		} elseif ( $price > 0 ) {
			ProTarget::set_enterprise_price( $copy_id, $price );
		}

		if ( empty( $lost ) ) {
			return;
		}

		Report::add(
			Report::GROUP_COURSES,
			array_merge(
				$course_report,
				array(
					'type'   => __( 'Group course pricing', 'masterstudy-lms-learning-management-system' ),
					'reason' => ucfirst( implode( '; ', $lost ) ) . '.',
				)
			)
		);
	}

	/**
	 * Report Masteriyo course settings that have no MasterStudy equivalent (the source meta stays on the course).
	 *
	 * @param int   $course_id     Masteriyo course ID.
	 * @param int   $copy_id       MasterStudy course (copy) ID.
	 * @param array $course_report Report defaults.
	 */
	private static function report_course_settings( int $course_id, array $course_report ): void {
		$lost = array();
		$meta = function ( $key ) use ( $course_id ) {
			return get_post_meta( $course_id, $key, true );
		};

		if ( absint( $meta( '_enrollment_limit' ) ) ) {
			/* translators: %d: seat limit */
			$lost[] = sprintf( __( 'enrollment limit (%d students)', 'masterstudy-lms-learning-management-system' ), absint( $meta( '_enrollment_limit' ) ) );
		}

		if ( self::to_bool( $meta( '_enable_course_retake' ) ) ) {
			$lost[] = __( 'course retake', 'masterstudy-lms-learning-management-system' );
		}

		if ( self::to_bool( $meta( '_enable_end_date' ) ) && self::date_meta_timestamp( $course_id, array( '_end_date' ) ) > time() ) {
			$lost[] = __( 'course end date (Masteriyo closes the course and deactivates its enrollments on that date)', 'masterstudy-lms-learning-management-system' );
		}

		if ( self::to_bool( $meta( '_enable_cohort_mode' ) ) ) {
			$lost[] = __( 'cohort mode (course start date, enrollment window)', 'masterstudy-lms-learning-management-system' );
		} elseif ( self::date_meta_timestamp( $course_id, array( '_enrollment_opens_on', '_enrollment_closes_on' ) ) ) {
			$lost[] = __( 'enrollment window', 'masterstudy-lms-learning-management-system' );
		}

		if ( self::to_bool( $meta( '_review_after_course_completion' ) ) ) {
			$lost[] = __( 'reviews only after course completion', 'masterstudy-lms-learning-management-system' );
		}

		if ( self::to_bool( $meta( '_disable_course_content' ) ) ) {
			$lost[] = __( 'disabled course content', 'masterstudy-lms-learning-management-system' );
		}

		if ( absint( $meta( '_fake_enrolled_count' ) ) ) {
			$lost[] = __( 'extra (fake) enrolled count', 'masterstudy-lms-learning-management-system' );
		}

		if ( '' !== trim( (string) $meta( '_course_badge' ) ) ) {
			$lost[] = __( 'course badge', 'masterstudy-lms-learning-management-system' );
		}

		if ( '' !== trim( wp_strip_all_tags( (string) $meta( '_purchase_note' ) ) ) ) {
			$lost[] = __( 'purchase note', 'masterstudy-lms-learning-management-system' );
		}

		if ( metadata_exists( 'post', $course_id, '_show_curriculum' ) && ! self::to_bool( $meta( '_show_curriculum' ) ) ) {
			$lost[] = __( 'hidden curriculum (MasterStudy always shows the curriculum)', 'masterstudy-lms-learning-management-system' );
		}

		$welcome = maybe_unserialize( $meta( '_welcome_message_to_first_time_user' ) );

		if ( is_array( $welcome ) && ! empty( $welcome['enabled'] ) && 'Welcome to the Course.' !== trim( (string) ( $welcome['title'] ?? '' ) ) ) {
			$lost[] = __( 'welcome message', 'masterstudy-lms-learning-management-system' );
		}

		if ( ! empty( maybe_unserialize( $meta( '_custom_fields' ) ) ) ) {
			$lost[] = __( 'custom fields', 'masterstudy-lms-learning-management-system' );
		}

		if ( ! empty( self::term_names( $course_id, 'course_tag' ) ) ) {
			$lost[] = __( 'tags (MasterStudy courses have categories only)', 'masterstudy-lms-learning-management-system' );
		}

		$links = array(
			'_wc_product_id'              => __( 'WooCommerce product link (the WooCommerce product no longer enrolls buyers — sell the course through MasterStudy checkout or its WooCommerce integration)', 'masterstudy-lms-learning-management-system' ),
			'_lemon_squeezy_product_id'   => __( 'Lemon Squeezy product link', 'masterstudy-lms-learning-management-system' ),
			'_google_classroom_course_id' => __( 'Google Classroom link', 'masterstudy-lms-learning-management-system' ),
			'bp_course_group'             => __( 'BuddyPress group link', 'masterstudy-lms-learning-management-system' ),
			'_multiple_currency_enabled'  => __( 'multiple-currency (price zone) prices', 'masterstudy-lms-learning-management-system' ),
		);

		foreach ( $links as $key => $label ) {
			$value = $meta( $key );

			if ( '_multiple_currency_enabled' === $key ? self::to_bool( $value ) : ( '' !== (string) $value && '0' !== (string) $value ) ) {
				$lost[] = $label;
			}
		}

		if ( empty( $lost ) ) {
			return;
		}

		Report::add(
			Report::GROUP_COURSES,
			array_merge(
				$course_report,
				array(
					'type'   => __( 'Course settings', 'masterstudy-lms-learning-management-system' ),
					/* translators: %s: list of settings */
					'reason' => sprintf( __( 'MasterStudy has no equivalent for: %s. The values stay in the Masteriyo course.', 'masterstudy-lms-learning-management-system' ), implode( '; ', $lost ) ),
				)
			)
		);
	}

	/**
	 * Masteriyo course FAQ (Pro "course-faq" addon) → MasterStudy course FAQ.
	 *
	 * Each FAQ is an `mto_course_faq` comment on the course: the answer in comment_content, the question
	 * in the `_title` comment meta and the position in comment_karma (Masteriyo's own importers write
	 * exactly this). The FAQ comments stay on the Masteriyo course.
	 *
	 * @param int $course_id Masteriyo course ID.
	 * @param int $copy_id   MasterStudy course (copy) ID.
	 */
	private static function migrate_course_faq( int $course_id, int $copy_id ): void {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT comment_ID, comment_content FROM {$wpdb->comments}
				 WHERE comment_post_ID = %d AND comment_type = %s AND comment_approved NOT IN ('trash', 'spam')
				 ORDER BY comment_karma ASC, comment_ID ASC",
				$course_id,
				self::COMMENT_FAQ
			)
		);

		if ( empty( $rows ) ) {
			return;
		}

		$faq = array();

		foreach ( $rows as $row ) {
			$question = trim( wp_strip_all_tags( (string) get_comment_meta( (int) $row->comment_ID, '_title', true ) ) );
			$answer   = (string) $row->comment_content;

			if ( '' === $question && '' === trim( $answer ) ) {
				continue;
			}

			$faq[] = array(
				// MasterStudy needs a question; an answer-only FAQ keeps its text as the question.
				'question' => '' !== $question ? $question : wp_trim_words( wp_strip_all_tags( $answer ), 20 ),
				'answer'   => wp_kses_post( $answer ),
			);
		}

		Target::set_faq( $copy_id, $faq );
	}

	/**
	 * Masteriyo recurring (subscription) course price → MasterStudy course subscription plan (Pro Plus).
	 * Gateway subscriptions cannot be migrated: subscribers keep their enrollment but must re-subscribe.
	 *
	 * @param int        $course_id     Masteriyo course ID.
	 * @param int        $copy_id       MasterStudy course (copy) ID.
	 * @param float      $regular_price Recurring price.
	 * @param float|null $sale_price    Recurring sale price.
	 * @param array      $course_report Report defaults.
	 */
	private static function migrate_subscription_pricing( int $course_id, int $copy_id, float $regular_price, ?float $sale_price, array $course_report ): void {
		$period   = strtolower( (string) get_post_meta( $course_id, '_billing_period', true ) );
		$interval = max( 1, absint( get_post_meta( $course_id, '_billing_interval', true ) ) );
		$expire   = absint( get_post_meta( $course_id, '_billing_expire_after', true ) ); // Months, 0 = never.

		Target::store_unmigrated_meta( $copy_id, 'billing_period', get_post_meta( $course_id, '_billing_period', true ) );
		Target::store_unmigrated_meta( $copy_id, 'billing_interval', get_post_meta( $course_id, '_billing_interval', true ) );
		Target::store_unmigrated_meta( $copy_id, 'billing_expire_after', get_post_meta( $course_id, '_billing_expire_after', true ) );

		if ( ! ProTarget::plus_active() ) {
			Helper::log( 'warning', sprintf( 'Migration: Course %d uses recurring (subscription) pricing — subscriptions require MasterStudy LMS Pro Plus; migrated as a one-time price.', $course_id ) );

			Report::add(
				Report::GROUP_COURSES,
				array_merge(
					$course_report,
					array(
						'type'   => __( 'Subscription course', 'masterstudy-lms-learning-management-system' ),
						'reason' => __( 'Recurring (subscription) pricing requires MasterStudy LMS Pro Plus — the course was imported with a one-time price.', 'masterstudy-lms-learning-management-system' ),
					)
				)
			);

			return;
		}

		$period = in_array( $period, array( 'day', 'week', 'month', 'year' ), true ) ? $period : 'month';
		$cycles = 0;

		if ( $expire ) {
			$months = array(
				'day'   => 1 / 30,
				'week'  => 7 / 30,
				'month' => 1,
				'year'  => 12,
			);

			$cycles = max( 1, (int) round( $expire / ( $months[ $period ] * $interval ) ) );
		}

		ProTarget::create_subscription_plan(
			array(
				'source_id'      => 'course-' . $course_id,
				'name'           => self::title_of( $course_id ),
				'type'           => 'course',
				'object_ids'     => array( $copy_id ),
				'price'          => $regular_price,
				'sale_price'     => null !== $sale_price && $sale_price > 0 && $sale_price < $regular_price ? $sale_price : null,
				'interval'       => $period,
				'interval_value' => $interval,
				'billing_cycles' => $cycles,
			),
			self::SOURCE
		);

		// A Masteriyo recurring course has no one-time price: sell it through the plan only.
		update_post_meta( $copy_id, 'single_sale', '' );

		$subscribers = self::active_enrollment_count( $course_id );

		if ( $subscribers ) {
			Report::add(
				Report::GROUP_COURSES,
				array_merge(
					$course_report,
					array(
						'type'   => __( 'Subscription course', 'masterstudy-lms-learning-management-system' ),
						/* translators: %d: number of subscribers */
						'reason' => sprintf( __( 'The recurring price was imported as a MasterStudy subscription plan. Payment gateway subscriptions cannot be migrated: %d active subscriber(s) keep their enrollment but must re-subscribe to be billed again.', 'masterstudy-lms-learning-management-system' ), $subscribers ),
					)
				)
			);
		}
	}

	/**
	 * Masteriyo course flow (sequential / date / days drip) → MasterStudy drip content (Pro).
	 *
	 * @param int   $course_id     Masteriyo course ID.
	 * @param int   $copy_id       MasterStudy course (copy) ID, curriculum already built.
	 * @param array $course_report Report defaults.
	 */
	private static function migrate_course_flow( int $course_id, int $copy_id, array $course_report ): void {
		$flow = (string) get_post_meta( $course_id, '_flow', true );

		if ( ( '' === $flow || 'free-flow' === $flow ) && self::to_bool( get_post_meta( $course_id, '_content_drip_enable', true ) ) ) {
			$flow = (string) get_post_meta( $course_id, '_content_drip_type', true );
		}

		if ( '' === $flow || 'free-flow' === $flow ) {
			return;
		}

		Target::store_unmigrated_meta( $copy_id, 'flow', $flow );

		if ( ! ProTarget::pro_active() ) {
			Report::add(
				Report::GROUP_COURSES,
				array_merge(
					$course_report,
					array(
						'type'   => __( 'Course flow / drip content', 'masterstudy-lms-learning-management-system' ),
						/* translators: %s: Masteriyo course flow (sequential, date, days) */
						'reason' => sprintf( __( 'The Masteriyo "%s" course flow requires MasterStudy LMS Pro (drip content) — all lessons are available at once.', 'masterstudy-lms-learning-management-system' ), $flow ),
					)
				)
			);

			return;
		}

		if ( 'sequential' === $flow ) {
			ProTarget::sequential_course( $copy_id );
			return;
		}

		if ( ! in_array( $flow, array( 'date', 'days' ), true ) ) {
			Report::add(
				Report::GROUP_COURSES,
				array_merge(
					$course_report,
					array(
						'type'   => __( 'Course flow / drip content', 'masterstudy-lms-learning-management-system' ),
						/* translators: %s: Masteriyo course flow */
						'reason' => sprintf( __( 'The Masteriyo "%s" course flow has no MasterStudy equivalent — all lessons are available at once.', 'masterstudy-lms-learning-management-system' ), $flow ),
					)
				)
			);

			return;
		}

		// Drip settings are read from the Masteriyo item and written to its copy in the curriculum.
		foreach ( self::course_material_ids( $copy_id ) as $item_copy ) {
			$item_id = Target::source_of( $item_copy );

			if ( ! $item_id ) {
				continue;
			}

			if ( 'date' === $flow ) {
				$unlock_at = self::local_to_timestamp( get_post_meta( $item_id, '_content_drip_date', true ) );

				if ( $unlock_at ) {
					ProTarget::drip_on_date( $item_copy, $unlock_at );
				}

				continue;
			}

			ProTarget::drip_after_days( $item_copy, absint( get_post_meta( $item_id, '_content_drip_days', true ) ) );
		}
	}

	/**
	 * Masteriyo SCORM course package → MasterStudy SCORM course (Pro). The extracted package is
	 * copied from uploads/masteriyo/scorm/ so it survives removal of Masteriyo.
	 *
	 * @param int   $course_id     Masteriyo course ID.
	 * @param int   $copy_id       MasterStudy course (copy) ID.
	 * @param array $course_report Report defaults.
	 */
	private static function migrate_scorm( int $course_id, int $copy_id, array $course_report ): void {
		$raw = trim( (string) get_post_meta( $course_id, '_scorm_package', true ) );

		if ( '' === $raw ) {
			return;
		}

		Target::store_unmigrated_meta( $copy_id, 'scorm_package', $raw );

		$package  = json_decode( $raw, true );
		$imported = false;

		if ( ProTarget::pro_active() && is_array( $package ) && ! empty( $package['scorm_dir_name'] ) && ! empty( $package['file_name'] ) ) {
			$uploads = wp_upload_dir();
			$dir     = trailingslashit( $uploads['basedir'] ) . 'masteriyo/scorm/' . basename( (string) $package['scorm_dir_name'] ) . '/' . basename( (string) $package['file_name'] );
			$version = in_array( (string) ( $package['scorm_version'] ?? '' ), array( '1.2', '2004' ), true ) ? (string) $package['scorm_version'] : '';

			$imported = ProTarget::set_scorm_package( $copy_id, $dir, $version );
		}

		if ( $imported ) {
			// Learners' SCORM runtime data follows with their enrollments (migrate_scorm_runtime()).
			return;
		} elseif ( ! ProTarget::pro_active() ) {
			$reason = __( 'SCORM courses require MasterStudy LMS Pro — the SCORM package was not imported.', 'masterstudy-lms-learning-management-system' );
		} else {
			$reason = __( 'The SCORM package files were not found in uploads/masteriyo/scorm (or could not be copied) — upload the package again in MasterStudy (SCORM addon).', 'masterstudy-lms-learning-management-system' );
		}

		Report::add(
			Report::GROUP_COURSES,
			array_merge(
				$course_report,
				array(
					'type'   => __( 'SCORM course', 'masterstudy-lms-learning-management-system' ),
					'reason' => $reason,
				)
			)
		);
	}

	/**
	 * Masteriyo course certificate → an approximated MasterStudy certificate (Pro), created once per
	 * Masteriyo template with its background image and orientation.
	 *
	 * @param int   $course_id     Masteriyo course ID.
	 * @param int   $copy_id       MasterStudy course (copy) ID.
	 * @param array $course_report Report defaults.
	 */
	private static function migrate_course_certificate( int $course_id, int $copy_id, array $course_report ): void {
		$certificate_id = (int) get_post_meta( $course_id, '_certificate_id', true );
		$enabled        = get_post_meta( $course_id, '_certificate_enabled', true );

		if ( ! $certificate_id && ! self::to_bool( $enabled ) ) {
			return;
		}

		Target::store_unmigrated_meta( $copy_id, 'certificate_id', $certificate_id ?: '' ); // phpcs:ignore Universal.Operators.DisallowShortTernary.Found

		// The template is only issued when the certificate is enabled on the course.
		if ( metadata_exists( 'post', $course_id, '_certificate_enabled' ) && ! self::to_bool( $enabled ) ) {
			return;
		}

		if ( ! ProTarget::pro_active() ) {
			Helper::log( 'warning', sprintf( 'Migration: Course %d had a certificate — certificates require MasterStudy LMS Pro.', $course_id ) );

			Report::add(
				Report::GROUP_COURSES,
				array_merge(
					$course_report,
					array(
						'type'   => __( 'Course certificate', 'masterstudy-lms-learning-management-system' ),
						'reason' => __( 'Certificates require MasterStudy LMS Pro — the course was imported without a certificate.', 'masterstudy-lms-learning-management-system' ),
					)
				)
			);

			return;
		}

		$template = $certificate_id ? get_post( $certificate_id ) : null;

		if ( ! $template || self::CERTIFICATE !== $template->post_type ) {
			Target::assign_certificate( $copy_id );

			Report::add(
				Report::GROUP_COURSES,
				array_merge(
					$course_report,
					array(
						'type'   => __( 'Course certificate', 'masterstudy-lms-learning-management-system' ),
						'reason' => __( 'The Masteriyo certificate template no longer exists — the MasterStudy default certificate (if one is set) was assigned instead.', 'masterstudy-lms-learning-management-system' ),
					)
				)
			);

			return;
		}

		$design = self::certificate_design( $template );

		$ms_certificate = ProTarget::create_certificate(
			array(
				'source_id'     => $certificate_id,
				'title'         => '' !== trim( $template->post_title ) ? $template->post_title : sprintf( 'Certificate #%d', $certificate_id ),
				'background_id' => $design['background_id'],
				'orientation'   => $design['orientation'],
			),
			self::SOURCE
		);

		ProTarget::set_course_certificate( $copy_id, $ms_certificate );

		Report::add(
			Report::GROUP_COURSES,
			array_merge(
				$course_report,
				array(
					'type'   => __( 'Course certificate', 'masterstudy-lms-learning-management-system' ),
					/* translators: %s: certificate title */
					'reason' => sprintf( __( 'Certificate design approximated — "%s" was recreated as a MasterStudy certificate with its background image and orientation plus the standard fields (student, course, date, instructor). Review it in the certificate builder.', 'masterstudy-lms-learning-management-system' ), $template->post_title ),
				)
			)
		);
	}

	/**
	 * Background attachment and orientation of a Masteriyo certificate (Gutenberg block or PDF draft JSON).
	 *
	 * @param \WP_Post $template mto-certificate post.
	 * @return array{background_id: int, orientation: string}
	 */
	private static function certificate_design( \WP_Post $template ): array {
		$background  = 0;
		$orientation = 'landscape';
		$urls        = array();
		$json        = json_decode( (string) $template->post_content, true );

		if ( is_array( $json ) && isset( $json['settings'] ) && is_array( $json['settings'] ) ) {
			$layout = (array) ( $json['settings']['layout'] ?? array() );
			$bg     = (array) ( $json['settings']['background'] ?? array() );
			$props  = (array) ( $bg['imageProps'] ?? array() );

			if ( ! empty( $layout['orientation'] ) ) {
				$orientation = 'portrait' === strtolower( (string) $layout['orientation'] ) ? 'portrait' : 'landscape';
			} elseif ( isset( $layout['width'], $layout['height'] ) && (float) $layout['height'] > (float) $layout['width'] ) {
				$orientation = 'portrait';
			}

			// PDF draft (builder) format: imageProps.id is a client-side random id, never an attachment ID —
			// the background attachment can only be resolved from its URL.
			$urls = array( $bg['image'] ?? '', $props['originalSrc'] ?? '', $props['src'] ?? '' );
		} else {
			foreach ( parse_blocks( (string) $template->post_content ) as $block ) {
				if ( 'masteriyo/certificate' !== ( $block['blockName'] ?? '' ) ) {
					continue;
				}

				$attrs       = (array) ( $block['attrs'] ?? array() );
				$background  = absint( $attrs['backgroundImageID'] ?? 0 );
				$urls        = array( $attrs['backgroundImageURL'] ?? '' );
				$orientation = in_array( strtoupper( (string) ( $attrs['pageOrientation'] ?? 'L' ) ), array( 'P', 'PORTRAIT' ), true ) ? 'portrait' : 'landscape';
				break;
			}
		}

		if ( $background && 'attachment' !== get_post_type( $background ) ) {
			$background = 0;
		}

		foreach ( $urls as $url ) {
			if ( $background ) {
				break;
			}

			if ( ! is_string( $url ) || '' === $url || 0 === strpos( $url, 'data:' ) ) {
				continue;
			}

			$background = (int) attachment_url_to_postid( $url );

			// A resized/cropped variant ("image-1024x768.png") resolves through the original file URL.
			if ( ! $background ) {
				$original = (string) preg_replace( '/-\d+x\d+(\.[a-z0-9]+)(\?.*)?$/i', '$1', $url );

				$background = $original !== $url ? (int) attachment_url_to_postid( $original ) : 0;
			}
		}

		if ( ! $background ) {
			$background = (int) get_post_thumbnail_id( $template );
		}

		return array(
			'background_id' => $background,
			'orientation'   => $orientation,
		);
	}

	/**
	 * Prerequisites of every copied Masteriyo course. Runs once the courses step is done (finalize), so a
	 * prerequisite course copied after the course that requires it is found as well.
	 */
	private static function migrate_all_prerequisites(): void {
		global $wpdb;

		$course_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_prerequisites_courses'
				 WHERE p.post_type = %s
				 ORDER BY p.ID ASC",
				self::COURSE
			)
		);

		foreach ( array_map( 'intval', (array) $course_ids ) as $course_id ) {
			$copy_id = Target::copy_of( self::SOURCE, $course_id );

			if ( ! $copy_id ) {
				continue;
			}

			$course_report = array(
				'source_id' => $course_id,
				'title'     => self::title_of( $course_id ),
				'status'    => Report::STATUS_PARTIAL,
				'post_id'   => $copy_id,
			);

			try {
				self::migrate_prerequisites( $course_id, $copy_id, $course_report );
			} catch ( \Throwable $e ) {
				Report::add(
					Report::GROUP_COURSES,
					array_merge(
						$course_report,
						array(
							'type'   => __( 'Course prerequisites', 'masterstudy-lms-learning-management-system' ),
							'reason' => $e->getMessage(),
							'status' => Report::STATUS_FAILED,
						)
					)
				);
			}
		}
	}

	/**
	 * Masteriyo course prerequisites → MasterStudy prerequisites (Pro): the copies of the prerequisite courses.
	 * Masteriyo requires the prerequisite courses to be completed, so the passing level is 100%.
	 *
	 * @param int   $course_id     Masteriyo course ID.
	 * @param int   $copy_id       MasterStudy course (copy) ID.
	 * @param array $course_report Report defaults.
	 */
	private static function migrate_prerequisites( int $course_id, int $copy_id, array $course_report ): void {
		$ids = array_values( array_diff( self::id_list( get_post_meta( $course_id, '_prerequisites_courses', true ) ), array( $course_id ) ) );

		if ( empty( $ids ) ) {
			return;
		}

		if ( metadata_exists( 'post', $course_id, '_prerequisites_enable' ) && ! self::to_bool( get_post_meta( $course_id, '_prerequisites_enable', true ) ) ) {
			return;
		}

		// Only Masteriyo courses that were copied are valid prerequisites.
		$copies = array();

		foreach ( $ids as $id ) {
			$prerequisite_copy = self::COURSE === get_post_type( $id ) ? Target::copy_of( self::SOURCE, $id ) : 0;

			if ( $prerequisite_copy ) {
				$copies[] = $prerequisite_copy;
			}
		}

		Target::store_unmigrated_meta( $copy_id, 'prerequisites_courses', $copies );

		if ( ! ProTarget::pro_active() ) {
			Report::add(
				Report::GROUP_COURSES,
				array_merge(
					$course_report,
					array(
						'type'   => __( 'Course prerequisites', 'masterstudy-lms-learning-management-system' ),
						'reason' => __( 'Course prerequisites require MasterStudy LMS Pro — the course was imported without them.', 'masterstudy-lms-learning-management-system' ),
					)
				)
			);

			return;
		}

		ProTarget::set_prerequisites( $copy_id, $copies, 100 );

		if ( count( $copies ) < count( $ids ) ) {
			Report::add(
				Report::GROUP_COURSES,
				array_merge(
					$course_report,
					array(
						'type'   => __( 'Course prerequisites', 'masterstudy-lms-learning-management-system' ),
						/* translators: %d: number of courses */
						'reason' => sprintf( __( '%d prerequisite course(s) no longer exist or were not migrated and were not added to the prerequisites.', 'masterstudy-lms-learning-management-system' ), count( $ids ) - count( $copies ) ),
					)
				)
			);
		}
	}

	/**
	 * Masteriyo additional course authors → MasterStudy co-instructor (Pro). MasterStudy keeps one.
	 *
	 * @param int   $course_id     Masteriyo course ID.
	 * @param int   $copy_id       MasterStudy course (copy) ID.
	 * @param array $course_report Report defaults.
	 */
	private static function migrate_co_instructors( int $course_id, int $copy_id, array $course_report ): void {
		$author  = (int) get_post_field( 'post_author', $course_id );
		$authors = array_values(
			array_filter(
				array_diff( array_unique( array_map( 'absint', (array) get_post_meta( $course_id, '_additional_authors', false ) ) ), array( 0, $author ) ),
				function ( $user_id ) {
					return (bool) get_userdata( $user_id );
				}
			)
		);

		if ( empty( $authors ) ) {
			return;
		}

		if ( ! ProTarget::pro_active() ) {
			Target::store_unmigrated_meta( $copy_id, 'additional_authors', $authors );

			Report::add(
				Report::GROUP_COURSES,
				array_merge(
					$course_report,
					array(
						'type'   => __( 'Co-instructors', 'masterstudy-lms-learning-management-system' ),
						/* translators: %s: user names */
						'reason' => sprintf( __( 'Co-instructors require MasterStudy LMS Pro — %s were not added to the course.', 'masterstudy-lms-learning-management-system' ), self::user_labels( $authors ) ),
					)
				)
			);

			return;
		}

		ProTarget::set_co_instructor( $copy_id, $authors[0] );

		$extra = array_slice( $authors, 1 );

		if ( ! empty( $extra ) ) {
			Target::store_unmigrated_meta( $copy_id, 'additional_authors', $extra );

			Report::add(
				Report::GROUP_COURSES,
				array_merge(
					$course_report,
					array(
						'type'   => __( 'Co-instructors', 'masterstudy-lms-learning-management-system' ),
						/* translators: 1: user name, 2: user names */
						'reason' => sprintf( __( 'MasterStudy supports one co-instructor per course — %1$s was added; %2$s were not.', 'masterstudy-lms-learning-management-system' ), self::user_label( $authors[0] ), self::user_labels( $extra ) ),
					)
				)
			);
		}
	}

	/**
	 * MasterStudy addons that must be enabled BEFORE the courses step starts, because enabling them
	 * creates tables (DDL commits the open transaction) that the step writes to.
	 *
	 * @return string[] Addon slugs.
	 */
	public static function required_addons(): array {
		if ( ! ProTarget::pro_active() ) {
			return array();
		}

		global $wpdb;

		$addons = array();

		if ( self::count_posts( self::ASSIGNMENT ) ) {
			$addons[] = 'assignments';
		}

		if ( ProTarget::plus_active() ) {
			$recurring = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->postmeta} m
					 INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
					 WHERE p.post_type = %s AND m.meta_key = '_access_mode' AND m.meta_value = 'recurring'",
					self::COURSE
				)
			);

			if ( $recurring ) {
				$addons[] = 'subscriptions';
			}
		}

		return $addons;
	}

	/**
	 * Count total source items for a given migration step. Fast COUNT query — no records loaded.
	 *
	 * @param string $step Step name.
	 * @return int
	 */
	public static function count_source_items( string $step ): int {
		global $wpdb;

		switch ( $step ) {
			case 'users':
				// Count all Masteriyo role holders UNION all enrolled students.
				if ( ! self::table_exists( $wpdb->prefix . 'masteriyo_user_items' ) ) {
					return (int) $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value LIKE %s",
							$wpdb->get_blog_prefix() . 'capabilities',
							'%' . $wpdb->esc_like( '"masteriyo_' ) . '%'
						)
					);
				}

				return (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM (
							SELECT DISTINCT um.user_id
							FROM {$wpdb->usermeta} um
							INNER JOIN {$wpdb->users} u ON u.ID = um.user_id
							WHERE um.meta_key = %s AND um.meta_value LIKE %s
							UNION
							SELECT DISTINCT CAST( ui.user_id AS UNSIGNED ) FROM {$wpdb->prefix}masteriyo_user_items ui
							INNER JOIN {$wpdb->users} eu ON eu.ID = CAST( ui.user_id AS UNSIGNED )
							WHERE ui.item_type = 'user_course'
						) AS mto_users",
						$wpdb->get_blog_prefix() . 'capabilities',
						'%' . $wpdb->esc_like( '"masteriyo_' ) . '%'
					)
				);

			case 'courses':
				return self::count_posts( self::COURSE );

			case 'enrollments':
				if ( ! self::table_exists( $wpdb->prefix . 'masteriyo_user_items' ) ) {
					return 0;
				}

				return (int) $wpdb->get_var(
					"SELECT COUNT(*) FROM {$wpdb->prefix}masteriyo_user_items WHERE item_type = 'user_course'" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				);

			case 'orders':
				return self::count_posts( self::ORDER );

			case 'reviews':
				return (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = %s AND comment_parent = 0",
						self::COMMENT_REVIEW
					)
				);

			case 'announcement':
				return self::count_posts( self::ANNOUNCEMENT );

			case 'questions_n_answers':
				// Course Q&A threads plus lesson comments and quiz reviews (all become MasterStudy discussions).
				return (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type IN (%s, %s, %s) AND comment_parent = 0",
						self::COMMENT_QA,
						self::COMMENT_LESSON_REVIEW,
						self::COMMENT_QUIZ_REVIEW
					)
				);

			case 'lesson_progress':
				if ( ! self::table_exists( $wpdb->prefix . 'masteriyo_user_activities' ) ) {
					return 0;
				}

				return (int) $wpdb->get_var(
					"SELECT COUNT(*) FROM {$wpdb->prefix}masteriyo_user_activities WHERE activity_type IN ('lesson', 'google-meet', 'zoom')" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				);

			case 'quiz_attempts':
				if ( ! self::table_exists( $wpdb->prefix . 'masteriyo_quiz_attempts' ) ) {
					return 0;
				}

				return (int) $wpdb->get_var(
					"SELECT COUNT(*) FROM {$wpdb->prefix}masteriyo_quiz_attempts" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				);

			case 'wishlists':
				return (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(DISTINCT post_author) FROM {$wpdb->posts} WHERE post_type = %s",
						self::WISHLIST
					)
				);
		}

		return 0;
	}

	/**
	 * Return one batch of source IDs for a given migration step.
	 *
	 * Copy mode never modifies or removes a source record, so every step is cursor based:
	 * `id > $cursor ORDER BY id LIMIT $limit` over ALL source records ($exclude is not needed).
	 *
	 * @param string $step    Step name.
	 * @param int    $limit   Batch size.
	 * @param int    $cursor  Last processed ID (0 = first batch).
	 * @param int[]  $exclude IDs that already failed (unused: the cursor moves past them).
	 * @return int[]
	 */
	public static function get_source_ids( string $step, int $limit, int $cursor, array $exclude = array() ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		switch ( $step ) {
			case 'users':
				$capabilities_key = $wpdb->get_blog_prefix() . 'capabilities';
				$like             = '%' . $wpdb->esc_like( '"masteriyo_' ) . '%';

				if ( ! self::table_exists( $wpdb->prefix . 'masteriyo_user_items' ) ) {
					$ids = $wpdb->get_col(
						$wpdb->prepare(
							"SELECT DISTINCT user_id FROM {$wpdb->usermeta}
							 WHERE meta_key = %s AND meta_value LIKE %s AND user_id > %d
							 ORDER BY user_id ASC LIMIT %d",
							$capabilities_key,
							$like,
							$cursor,
							$limit
						)
					);

					return array_map( 'intval', $ids ? $ids : array() );
				}

				$ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT user_id FROM (
							SELECT DISTINCT um.user_id AS user_id
							FROM {$wpdb->usermeta} um
							INNER JOIN {$wpdb->users} u ON u.ID = um.user_id
							WHERE um.meta_key = %s AND um.meta_value LIKE %s
							UNION
							SELECT DISTINCT CAST( ui.user_id AS UNSIGNED ) AS user_id FROM {$wpdb->prefix}masteriyo_user_items ui
							INNER JOIN {$wpdb->users} eu ON eu.ID = CAST( ui.user_id AS UNSIGNED )
							WHERE ui.item_type = 'user_course'
						) AS mto_users
						WHERE user_id > %d
						ORDER BY user_id ASC
						LIMIT %d",
						$capabilities_key,
						$like,
						$cursor,
						$limit
					)
				);
				return array_map( 'intval', $ids ? $ids : array() );

			case 'courses':
				return self::post_ids( self::COURSE, $limit, $cursor );

			case 'orders':
				return self::post_ids( self::ORDER, $limit, $cursor );

			case 'announcement':
				return self::post_ids( self::ANNOUNCEMENT, $limit, $cursor );

			case 'enrollments':
				if ( ! self::table_exists( $wpdb->prefix . 'masteriyo_user_items' ) ) {
					return array();
				}

				$ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT id FROM {$wpdb->prefix}masteriyo_user_items
						 WHERE item_type = 'user_course' AND id > %d
						 ORDER BY id ASC
						 LIMIT %d",
						$cursor,
						$limit
					)
				);
				return array_map( 'intval', $ids ? $ids : array() );

			case 'reviews':
			case 'questions_n_answers':
				$types = 'reviews' === $step ? array( self::COMMENT_REVIEW, self::COMMENT_REVIEW, self::COMMENT_REVIEW ) : array( self::COMMENT_QA, self::COMMENT_LESSON_REVIEW, self::COMMENT_QUIZ_REVIEW );
				$ids   = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT comment_ID FROM {$wpdb->comments}
						 WHERE comment_type IN (%s, %s, %s) AND comment_parent = 0 AND comment_ID > %d
						 ORDER BY comment_ID ASC
						 LIMIT %d",
						array_merge( $types, array( $cursor, $limit ) )
					)
				);
				return array_map( 'intval', $ids ? $ids : array() );

			case 'lesson_progress':
				if ( ! self::table_exists( $wpdb->prefix . 'masteriyo_user_activities' ) ) {
					return array();
				}

				$ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT id FROM {$wpdb->prefix}masteriyo_user_activities
						 WHERE activity_type IN ('lesson', 'google-meet', 'zoom') AND id > %d
						 ORDER BY id ASC
						 LIMIT %d",
						$cursor,
						$limit
					)
				);
				return array_map( 'intval', $ids ? $ids : array() );

			case 'quiz_attempts':
				if ( ! self::table_exists( $wpdb->prefix . 'masteriyo_quiz_attempts' ) ) {
					return array();
				}

				$ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT id FROM {$wpdb->prefix}masteriyo_quiz_attempts WHERE id > %d ORDER BY id ASC LIMIT %d",
						$cursor,
						$limit
					)
				);
				return array_map( 'intval', $ids ? $ids : array() );

			case 'wishlists':
				$ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT DISTINCT post_author FROM {$wpdb->posts}
						 WHERE post_type = %s AND post_author > %d
						 ORDER BY post_author ASC LIMIT %d",
						self::WISHLIST,
						$cursor,
						$limit
					)
				);
				return array_map( 'intval', $ids ? $ids : array() );
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array();
	}

	/**
	 * Dispatch a single-item migration to the appropriate migrate_single_*() method.
	 *
	 * Called by MigrationProcessJob inside a SAVEPOINT. Must be idempotent — safe to call
	 * twice for the same (step, item_id) pair.
	 *
	 * @param string $step    Step name matching a key in MasteriyoMigrator::get_steps().
	 * @param int    $item_id Source item ID (post ID, comment ID, user ID or table row ID).
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
			case 'orders':
				static::migrate_single_order( $item_id );
				break;
			case 'reviews':
				static::migrate_single_review( $item_id );
				break;
			case 'announcement':
				static::migrate_single_announcement( $item_id );
				break;
			case 'questions_n_answers':
				static::migrate_single_qa( $item_id );
				break;
			case 'lesson_progress':
				static::migrate_single_lesson_progress( $item_id );
				break;
			case 'quiz_attempts':
				static::migrate_single_quiz_attempt( $item_id );
				break;
			case 'wishlists':
				static::migrate_single_wishlist( $item_id );
				break;
		}
	}

	/**
	 * Give a single Masteriyo user the matching MasterStudy role and profile data.
	 *
	 * Instructors and managers (masteriyo_instructor / masteriyo_manager) also become MasterStudy
	 * instructors (administrators are left untouched). Students have no MasterStudy role — they only
	 * need a login role. Users are shared: the Masteriyo roles and every existing profile value are kept,
	 * MasterStudy data is only added where the user has none yet.
	 *
	 * Idempotent: role checks make a second run a no-op.
	 *
	 * @param int $user_id WP user ID.
	 * @throws \Exception If the WP user record does not exist.
	 */
	public static function migrate_single_user( int $user_id ): void {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			throw new \Exception(
				sprintf( 'User #%d no longer exists in WordPress, so their Masteriyo roles and profile could not be migrated.', $user_id ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		$roles = (array) $user->roles;
		$caps  = is_array( $user->caps ) ? $user->caps : array();

		// Masteriyo keeps the approval in wp_users.user_status: 0 = active, 1 = e-mail not verified, 1000 = instructor
		// waiting for approval. The legacy `_approved` meta still forces an instructor active.
		$user_status = (int) $user->user_status;
		$approved    = 0 === $user_status || self::to_bool( get_user_meta( $user_id, '_approved', true ) );
		$applied     = 'applied' === (string) get_user_meta( $user_id, '_instructor_apply_status', true );

		foreach ( array( self::ROLE_INSTRUCTOR, self::ROLE_MANAGER ) as $role ) {
			if ( in_array( $role, $roles, true ) || isset( $caps[ $role ] ) ) {
				if ( $approved || in_array( 'administrator', $roles, true ) ) {
					// make_instructor() leaves administrators untouched.
					Target::make_instructor( $user_id );
				} else {
					$applied = true;
				}

				break;
			}
		}

		if ( $applied && ! in_array( 'administrator', $roles, true ) && ! in_array( Target::INSTRUCTOR_ROLE, (array) get_userdata( $user_id )->roles, true ) ) {
			// Not approved in Masteriyo: a pending MasterStudy instructor application, never an instructor.
			Target::request_instructor( $user_id, strtotime( (string) $user->user_registered . ' UTC' ) );

			Report::add(
				Report::GROUP_USERS,
				array(
					'source_id' => $user_id,
					'title'     => self::user_label( $user_id ),
					'type'      => __( 'Instructor awaiting approval', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'The instructor was not approved in Masteriyo — the user was imported as a student with a pending instructor application (approve it in MasterStudy → Instructors).', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_PARTIAL,
				)
			);
		}

		if ( 1 === $user_status && ! $approved ) {
			Report::add(
				Report::GROUP_USERS,
				array(
					'source_id' => $user_id,
					'title'     => self::user_label( $user_id ),
					'type'      => __( 'Unverified e-mail', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'The user had not verified the e-mail address, so Masteriyo blocked the login. MasterStudy has no pending-verification state for existing accounts — the account can log in after the migration.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_PARTIAL,
				)
			);
		}

		$is_manager = in_array( self::ROLE_MANAGER, $roles, true ) || isset( $caps[ self::ROLE_MANAGER ] );

		if ( $is_manager && ! in_array( 'administrator', $roles, true ) ) {
			Report::add(
				Report::GROUP_USERS,
				array(
					'source_id' => $user_id,
					'title'     => self::user_label( $user_id ),
					'type'      => __( 'Masteriyo manager', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'MasterStudy has no LMS manager role — the user was given the MasterStudy instructor role, without the manager permissions (the Masteriyo manager role is kept for Masteriyo).', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_PARTIAL,
				)
			);
		}

		$profile_image = (int) get_user_meta( $user_id, '_profile_image_id', true );

		Target::set_profile(
			$user_id,
			array(
				'facebook'   => (string) get_user_meta( $user_id, '_public_profile_facebook_url', true ),
				'linkedin'   => (string) get_user_meta( $user_id, '_public_profile_linkedin_url', true ),
				'avatar_url' => $profile_image ? (string) wp_get_attachment_url( $profile_image ) : '',
			)
		);

		self::migrate_profile_bio_and_website( $user_id );

		// Masteriyo billing / public profile details → MasterStudy checkout personal data (only when empty).
		$field = function ( array $keys ) use ( $user_id ) {
			foreach ( $keys as $key ) {
				$value = trim( (string) get_user_meta( $user_id, $key, true ) );

				if ( '' !== $value ) {
					return $value;
				}
			}

			return '';
		};

		Target::set_user_personal_data(
			$user_id,
			array(
				'country'   => $field( array( '_billing_country', '_public_profile_country' ) ),
				'post_code' => $field( array( '_billing_postcode', '_public_profile_postcode' ) ),
				'state'     => $field( array( '_billing_state', '_public_profile_state' ) ),
				'city'      => $field( array( '_billing_city', '_public_profile_city' ) ),
				'company'   => $field( array( '_billing_company_name' ) ),
				'phone'     => $field( array( '_billing_phone', '_public_profile_phone' ) ),
			)
		);

		// The Masteriyo roles stay; a user without any registered role (Masteriyo inactive) gets a login role.
		Target::ensure_student( $user_id );
	}

	/**
	 * Masteriyo public profile bio and website → WordPress `description` (the MasterStudy bio) and `user_url`.
	 *
	 * The WordPress user profile is shared with Masteriyo, so an existing value is never changed: the bio
	 * (`_public_profile_biographical_info`) is only added when the user has no `description` at all, and a
	 * bio or website that would have to change the WordPress profile is reported instead. Masteriyo's phone,
	 * address and Behance fields have no MasterStudy equivalent and are not copied.
	 *
	 * @param int $user_id User ID.
	 */
	private static function migrate_profile_bio_and_website( int $user_id ): void {
		$bio         = trim( (string) get_user_meta( $user_id, '_public_profile_biographical_info', true ) );
		$description = trim( (string) get_user_meta( $user_id, 'description', true ) );
		$lost        = array();

		if ( '' !== $bio && $bio !== $description && ( '' === $description || false !== strpos( $bio, $description ) ) ) {
			if ( ! metadata_exists( 'user', $user_id, 'description' ) ) {
				add_user_meta( $user_id, 'description', wp_kses_post( $bio ), true );
			} else {
				$lost[] = __( 'public profile bio', 'masterstudy-lms-learning-management-system' );
			}
		}

		$website = esc_url_raw( trim( (string) get_user_meta( $user_id, '_public_profile_website_url', true ) ) );
		$user    = get_userdata( $user_id );

		if ( '' !== $website && $user && $website !== trim( (string) $user->user_url ) ) {
			$lost[] = __( 'website', 'masterstudy-lms-learning-management-system' );
		}

		if ( empty( $lost ) ) {
			return;
		}

		Report::add(
			Report::GROUP_USERS,
			array(
				'source_id' => $user_id,
				'title'     => self::user_label( $user_id ),
				'type'      => __( 'Profile details', 'masterstudy-lms-learning-management-system' ),
				/* translators: %s: profile fields */
				'reason'    => sprintf( __( 'The Masteriyo %s was not copied to the WordPress user profile, which Masteriyo and MasterStudy share and the migration does not change — copy it in the user profile if needed.', 'masterstudy-lms-learning-management-system' ), implode( ', ', $lost ) ),
				'status'    => Report::STATUS_PARTIAL,
			)
		);
	}

	/**
	 * Copy a single Masteriyo course to a MasterStudy stm-courses post (with its curriculum).
	 *
	 * Does NOT migrate enrollments — enrollments are exclusively owned by the 'enrollments' step.
	 * Announcements are owned by the 'announcement' step.
	 * Idempotent: an existing copy is reused and its data rebuilt, never duplicated.
	 *
	 * @param int $course_id Masteriyo mto-course post ID.
	 * @throws \Exception If the post does not exist or is not an mto-course post.
	 */
	public static function migrate_single_course( int $course_id ): void {
		$post = get_post( $course_id );

		if ( ! $post ) {
			throw new \Exception(
				sprintf( 'Course #%d no longer exists, so it could not be migrated.', $course_id ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		if ( self::COURSE !== $post->post_type ) {
			throw new \Exception(
				sprintf( 'Post #%d "%s" is not a Masteriyo course (its post type is "%s"), so it was not migrated as a course.', $course_id, $post->post_title, $post->post_type ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		$copy_id = Target::copy_post( $course_id, PostType::COURSE, self::SOURCE );

		static::migrate_course( $course_id, $copy_id );

		Target::migrate_categories( $copy_id, 'course_cat', array(), $course_id );
		Target::migrate_category_hierarchy( $course_id, 'course_cat' );
		Target::migrate_category_images( $course_id, 'course_cat', '_featured_image' );

		static::migrate_course_info( $course_id, $copy_id );

		static::migrate_course_faq( $course_id, $copy_id );

		// Course author becomes a MasterStudy instructor — unless Masteriyo had not approved them yet (the users
		// step turned them into a pending MasterStudy instructor application).
		$author = (int) $post->post_author;

		if ( $author && ! in_array( (string) get_user_meta( $author, 'submission_status', true ), array( 'pending', 'rejected' ), true ) ) {
			Target::make_instructor( $author );
		}
	}

	/**
	 * Migrate a single Masteriyo enrollment (masteriyo_user_items row) to stm_lms_user_courses of the course copy.
	 * The Masteriyo row stays.
	 *
	 * Idempotent: Target::enroll() returns the existing enrollment.
	 *
	 * @param int $user_item_id Primary key of the masteriyo_user_items row.
	 * @throws \Exception If the row does not exist or the DB insert fails.
	 */
	public static function migrate_single_enrollment( int $user_item_id ): void {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT user_id, item_id, status, date_start, date_end
				 FROM {$wpdb->prefix}masteriyo_user_items
				 WHERE id = %d AND item_type = 'user_course'",
				$user_item_id
			)
		);

		if ( ! $row ) {
			throw new \Exception(
				sprintf( 'Masteriyo enrollment record #%d was not found.', $user_item_id ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		$user_id   = (int) $row->user_id;
		$course_id = (int) $row->item_id;
		$copy_id   = $course_id ? Target::copy_of( self::SOURCE, $course_id ) : 0;
		$link_id   = $copy_id ? $copy_id : ( $course_id && get_post( $course_id ) ? $course_id : 0 );

		// Masteriyo sets an enrollment "inactive" while its order is not completed (or when an admin
		// deactivates it): the student has no access, so it must not become a MasterStudy enrollment.
		if ( 'inactive' === $row->status && $user_id && $course_id ) {
			$order_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT meta_value FROM {$wpdb->prefix}masteriyo_user_itemmeta WHERE user_item_id = %d AND meta_key = '_order_id' LIMIT 1",
					$user_item_id
				)
			);

			Report::add(
				Report::GROUP_ENROLLMENTS,
				array(
					'source_id' => $user_item_id,
					'title'     => self::user_label( $user_id ) . ' → ' . self::title_of( $course_id ),
					'type'      => __( 'Inactive enrollment', 'masterstudy-lms-learning-management-system' ),
					'reason'    => $order_id
						/* translators: %d: order ID */
						? sprintf( __( 'The enrollment was inactive in Masteriyo (order #%d not completed), so the student was not enrolled — completing the imported order in MasterStudy enrolls them.', 'masterstudy-lms-learning-management-system' ), $order_id )
						: __( 'The enrollment was inactive in Masteriyo (the student had no access), so the student was not enrolled.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_UNSUPPORTED,
					'course'    => self::title_of( $course_id ),
					'post_id'   => $link_id,
				)
			);
		} elseif ( $user_id && $course_id && ( ! get_userdata( $user_id ) || ! $copy_id || PostType::COURSE !== get_post_type( $copy_id ) ) ) {
			Report::add(
				Report::GROUP_ENROLLMENTS,
				array(
					'source_id' => $user_item_id,
					'title'     => self::user_label( $user_id ) . ' → ' . self::title_of( $course_id ),
					'type'      => __( 'Enrollment', 'masterstudy-lms-learning-management-system' ),
					'reason'    => ! get_userdata( $user_id )
						? __( 'The enrolled user no longer exists, so the enrollment was skipped.', 'masterstudy-lms-learning-management-system' )
						: __( 'The course no longer exists or was not migrated, so the enrollment was skipped.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_UNSUPPORTED,
					'course'    => self::title_of( $course_id ),
					'post_id'   => $link_id,
				)
			);
		} elseif ( $user_id && $course_id ) {
			// Masteriyo tracks completion on the course_progress activity row only; `date_end` is an expiry date
			// that core never writes (a stored one is read as the access end, not as a completion).
			$end_time = self::course_completed_at( $user_id, $course_id );

			$user_course_id = Target::enroll( $user_id, $copy_id, self::gmt_to_timestamp( $row->date_start ), $end_time ? $end_time : null );

			self::migrate_scorm_runtime( $user_item_id, $user_course_id, $user_id, $course_id, $copy_id );

			// Keep Masteriyo certificate verification codes ({course}-{template}-{student}) valid.
			$source_certificate = (int) get_post_meta( $copy_id, '_migrated_certificate_id', true );

			if ( $end_time && $source_certificate && ProTarget::pro_active() && '' !== (string) get_post_meta( $copy_id, 'course_certificate', true ) ) {
				ProTarget::set_certificate_code( $user_id, $copy_id, sprintf( '%d-%d-%d', $course_id, $source_certificate, $user_id ) );
			}

			if ( 'active' !== $row->status ) {
				Helper::log( 'info', sprintf( 'Migration: Enrollment of user %d in course %d had Masteriyo status "%s" — migrated as enrolled.', $user_id, $course_id, $row->status ) );

				Report::add(
					Report::GROUP_ENROLLMENTS,
					array(
						'source_id' => $user_item_id,
						'title'     => self::user_label( $user_id ) . ' → ' . self::title_of( $course_id ),
						/* translators: %s: Masteriyo enrollment status */
						'type'      => sprintf( __( '%s enrollment', 'masterstudy-lms-learning-management-system' ), ucfirst( (string) $row->status ) ),
						/* translators: %s: Masteriyo enrollment status */
						'reason'    => sprintf( __( 'MasterStudy has no enrollment status — the "%s" enrollment was imported as an active enrollment.', 'masterstudy-lms-learning-management-system' ), $row->status ),
						'status'    => Report::STATUS_PARTIAL,
						'course'    => self::title_of( $course_id ),
						'post_id'   => $copy_id,
					)
				);
			}
		} else {
			Report::add(
				Report::GROUP_ENROLLMENTS,
				array(
					'source_id' => $user_item_id,
					'title'     => self::user_label( $user_id ) . ' → ' . ( $course_id ? self::title_of( $course_id ) : __( 'unknown course', 'masterstudy-lms-learning-management-system' ) ),
					'type'      => __( 'Enrollment', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'The enrollment record has no user or course, so it was skipped.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_UNSUPPORTED,
					'course'    => $course_id ? self::title_of( $course_id ) : '',
					'post_id'   => $link_id,
				)
			);
		}
	}

	/**
	 * Learner SCORM runtime data (masteriyo_user_scorm_course, keyed by the Masteriyo enrollment row) →
	 * MasterStudy stm_lms_user_course_scorm (Pro SCORM addon), keyed by the new enrollment. The Masteriyo rows stay.
	 *
	 * @param int $user_item_id   Masteriyo enrollment row ID.
	 * @param int $user_course_id MasterStudy enrollment row ID.
	 * @param int $user_id        User ID.
	 * @param int $course_id      Masteriyo course ID.
	 * @param int $copy_id        MasterStudy course (copy) ID.
	 */
	private static function migrate_scorm_runtime( int $user_item_id, int $user_course_id, int $user_id, int $course_id, int $copy_id ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'masteriyo_user_scorm_course';

		if ( ! self::table_exists( $table ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT parameter, value FROM {$table} WHERE user_course_id = %d ORDER BY id ASC", $user_item_id ) );

		if ( empty( $rows ) ) {
			return;
		}

		$values = array();

		foreach ( $rows as $row ) {
			$values[ (string) $row->parameter ] = (string) $row->value;
		}

		$copied = ProTarget::pro_active() && '' !== (string) get_post_meta( $copy_id, 'scorm_package', true ) && ProTarget::set_scorm_runtime( $user_course_id, $values );

		if ( $copied ) {
			return;
		}

		Report::add(
			Report::GROUP_PROGRESS,
			array(
				'source_id' => $user_item_id,
				'title'     => self::user_label( $user_id ) . ' → ' . self::title_of( $course_id ),
				'type'      => __( 'SCORM progress', 'masterstudy-lms-learning-management-system' ),
				'reason'    => ProTarget::pro_active()
					/* translators: %d: number of values */
					? sprintf( __( 'The course SCORM package was not imported, so %d SCORM runtime value(s) (score, status, bookmark) of the learner were not imported.', 'masterstudy-lms-learning-management-system' ), count( $values ) )
					/* translators: %d: number of values */
					: sprintf( __( 'SCORM courses require MasterStudy LMS Pro — %d SCORM runtime value(s) (score, status, bookmark) of the learner were not imported.', 'masterstudy-lms-learning-management-system' ), count( $values ) ),
				'status'    => Report::STATUS_UNSUPPORTED,
				'course'    => self::title_of( $course_id ),
				'post_id'   => $copy_id,
			)
		);
	}

	/**
	 * Copy a single Masteriyo order to a native MasterStudy stm-orders post. The Masteriyo order, its items
	 * and notes stay.
	 *
	 * Idempotent: the existing copy is reused and its data rewritten (order items rebuilt).
	 *
	 * @param int $order_id Masteriyo mto-order post ID.
	 * @throws \Exception If the post does not exist or is not an mto-order post.
	 */
	public static function migrate_single_order( int $order_id ): void {
		global $wpdb;

		$post = get_post( $order_id );

		if ( ! $post ) {
			throw new \Exception(
				sprintf( 'Order #%d no longer exists, so it could not be migrated.', $order_id ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		if ( self::ORDER !== $post->post_type ) {
			throw new \Exception(
				sprintf( 'Post #%d is not a Masteriyo order (its post type is "%s"), so it was not migrated as an order.', $order_id, $post->post_type ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		$items_table = $wpdb->prefix . 'masteriyo_order_items';
		$meta_table  = $wpdb->prefix . 'masteriyo_order_itemmeta';
		$items       = array();
		$dropped     = array();
		$missing     = 0;
		$coupons     = array();

		if ( self::table_exists( $items_table ) ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT i.order_item_id, i.order_item_name, i.order_item_type, c.meta_value AS course_id,
					        t.meta_value AS total, s.meta_value AS subtotal, cc.meta_value AS code, d.meta_value AS discount
					 FROM {$items_table} i
					 LEFT JOIN {$meta_table} c ON c.order_item_id = i.order_item_id AND c.meta_key = 'course_id'
					 LEFT JOIN {$meta_table} t ON t.order_item_id = i.order_item_id AND t.meta_key = 'total'
					 LEFT JOIN {$meta_table} s ON s.order_item_id = i.order_item_id AND s.meta_key = 'subtotal'
					 LEFT JOIN {$meta_table} cc ON cc.order_item_id = i.order_item_id AND cc.meta_key = 'code'
					 LEFT JOIN {$meta_table} d ON d.order_item_id = i.order_item_id AND d.meta_key = 'discount'
					 WHERE i.order_id = %d
					 ORDER BY i.order_item_id ASC",
					$order_id
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			foreach ( (array) $rows as $row ) {
				if ( 'coupon' === $row->order_item_type ) {
					$code = trim( (string) ( '' !== (string) $row->code ? $row->code : $row->order_item_name ) );

					if ( '' !== $code ) {
						$coupons[] = array(
							'code'     => $code,
							'discount' => (float) $row->discount,
						);
					}

					continue;
				}

				if ( 'course' !== $row->order_item_type || ! (int) $row->course_id ) {
					// Tax/fee/shipping lines only affect totals, which are kept.
					if ( ! in_array( $row->order_item_type, array( 'tax', 'shipping', 'free', 'fee' ), true ) ) {
						$dropped[] = (string) $row->order_item_type;
					}

					continue;
				}

				$course_copy = Target::copy_of( self::SOURCE, (int) $row->course_id );

				if ( ! $course_copy ) {
					++$missing;
					continue;
				}

				// MasterStudy item prices are before the coupon (the coupon meta is applied on top).
				$items[] = array(
					'course_id' => $course_copy,
					'price'     => (float) ( null !== $row->subtotal && '' !== (string) $row->subtotal ? $row->subtotal : $row->total ),
				);
			}
		}

		$payment = (string) get_post_meta( $order_id, '_payment_method', true );

		if ( 'offline' === $payment ) {
			$payment = 'cash';
		}

		$currency = (string) get_post_meta( $order_id, '_currency', true );

		if ( '' !== $currency && function_exists( 'masteriyo_get_currency_symbol' ) ) {
			$currency = html_entity_decode( (string) masteriyo_get_currency_symbol( $currency ), ENT_QUOTES, 'UTF-8' );
		}

		$total    = (float) get_post_meta( $order_id, '_total', true );
		$taxes    = (float) get_post_meta( $order_id, '_tax_total', true );
		$discount = (float) get_post_meta( $order_id, '_discount_total', true );

		// A trashed order keeps its pre-trash status (history) but must stay out of every MasterStudy list and
		// revenue report; an auto-draft is an order never saved in Masteriyo.
		$source_status = (string) $post->post_status;
		$order_status  = $source_status;

		if ( 'trash' === $source_status ) {
			$order_status = (string) get_post_meta( $order_id, '_wp_trash_meta_status', true );
			$order_status = '' !== $order_status ? $order_status : 'cancelled';
		} elseif ( 'auto-draft' === $source_status ) {
			$order_status = 'pending';
		}

		$copy_id = Target::save_order(
			array(
				'order_id'       => $order_id,
				'user_id'        => (int) get_post_meta( $order_id, '_customer_id', true ),
				'items'          => $items,
				'status'         => $order_status,
				'date'           => (int) get_post_time( 'U', true, $post ),
				'total'          => $total,
				'subtotal'       => max( 0, $total - $taxes + $discount ),
				'taxes'          => $taxes,
				'currency'       => $currency,
				'payment_code'   => '' !== $payment ? $payment : 'cash',
				'transaction_id' => (string) get_post_meta( $order_id, '_transaction_id', true ),
			),
			self::SOURCE
		);

		Target::store_unmigrated_meta( $copy_id, 'order_status', $post->post_status );
		Target::store_unmigrated_meta( $copy_id, 'discount_total', $discount ? $discount : '' );

		if ( in_array( $source_status, array( 'trash', 'auto-draft' ), true ) ) {
			// save_order() publishes the copy: MasterStudy lists and revenue only count published orders.
			$wpdb->update( $wpdb->posts, array( 'post_status' => 'trash' === $source_status ? 'trash' : 'draft' ), array( 'ID' => $copy_id ) );
			clean_post_cache( $copy_id );

			Report::add(
				Report::GROUP_ORDERS,
				array(
					'source_id' => $order_id,
					/* translators: %d: order ID */
					'title'     => sprintf( __( 'Order #%d', 'masterstudy-lms-learning-management-system' ), $order_id ),
					'type'      => 'trash' === $source_status ? __( 'Trashed order', 'masterstudy-lms-learning-management-system' ) : __( 'Unsaved draft order', 'masterstudy-lms-learning-management-system' ),
					'reason'    => 'trash' === $source_status
						? __( 'The order was in the Masteriyo trash — it was imported into the trash, so it is not listed or counted in MasterStudy revenue.', 'masterstudy-lms-learning-management-system' )
						: __( 'The order was an unsaved draft in Masteriyo — it was imported as a draft, so it is not listed or counted in MasterStudy revenue.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_UNSUPPORTED,
					'post_id'   => $copy_id,
				)
			);
		}

		self::migrate_order_details( $order_id, $copy_id, $post );

		if ( ! empty( $coupons ) ) {
			self::set_order_coupon( $copy_id, $coupons, $discount, count( $items ) + $missing <= 1 );
		}

		if ( $missing ) {
			Report::add(
				Report::GROUP_ORDERS,
				array(
					'source_id' => $order_id,
					/* translators: %d: order ID */
					'title'     => sprintf( __( 'Order #%d', 'masterstudy-lms-learning-management-system' ), $order_id ),
					'type'      => __( 'Order of a course that was not migrated', 'masterstudy-lms-learning-management-system' ),
					/* translators: %d: number of items */
					'reason'    => sprintf( __( '%d ordered course(s) no longer exist or were not migrated, so they are not in the imported order — the order total was kept.', 'masterstudy-lms-learning-management-system' ), $missing ),
					'status'    => Report::STATUS_PARTIAL,
					'post_id'   => $copy_id,
				)
			);
		}

		if ( ! empty( $dropped ) ) {
			Report::add(
				Report::GROUP_ORDERS,
				array(
					'source_id' => $order_id,
					/* translators: %d: order ID */
					'title'     => sprintf( __( 'Order #%d', 'masterstudy-lms-learning-management-system' ), $order_id ),
					'type'      => __( 'Order with non-course items', 'masterstudy-lms-learning-management-system' ),
					/* translators: 1: number of items, 2: item types */
					'reason'    => sprintf( __( '%1$d order item(s) that are not single courses (%2$s) were not imported — the order total was kept.', 'masterstudy-lms-learning-management-system' ), count( $dropped ), implode( ', ', array_unique( $dropped ) ) ),
					'status'    => Report::STATUS_PARTIAL,
					'post_id'   => $copy_id,
				)
			);
		}
	}

	/**
	 * Masteriyo order billing fields → MasterStudy order personal data; the customer note and the order notes
	 * (mto_order_note comments, they stay on the Masteriyo order) → the MasterStudy order note. Billing name, e-mail
	 * and street address have no MasterStudy field (they stay in the Masteriyo order). Refund records are reported.
	 *
	 * @param int      $order_id Masteriyo order ID.
	 * @param int      $copy_id  MasterStudy order (copy) ID.
	 * @param \WP_Post $post     Source order post.
	 */
	private static function migrate_order_details( int $order_id, int $copy_id, \WP_Post $post ): void {
		global $wpdb;

		$billing = function ( $key ) use ( $order_id ) {
			return trim( (string) get_post_meta( $order_id, '_billing_' . $key, true ) );
		};

		$note_lines    = array();
		$customer_note = trim( (string) ( '' !== trim( (string) $post->post_excerpt ) ? $post->post_excerpt : get_post_meta( $order_id, '_customer_note', true ) ) );

		if ( '' !== $customer_note ) {
			/* translators: %s: customer note */
			$note_lines[] = sprintf( __( 'Customer note: %s', 'masterstudy-lms-learning-management-system' ), $customer_note );
		}

		$notes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT comment_ID, comment_content, comment_date FROM {$wpdb->comments} WHERE comment_post_ID = %d AND comment_type = 'mto_order_note' ORDER BY comment_date ASC, comment_ID ASC",
				$order_id
			)
		);

		foreach ( (array) $notes as $note ) {
			$text = trim( wp_strip_all_tags( (string) $note->comment_content ) );

			if ( '' !== $text ) {
				$note_lines[] = sprintf( '[%s] %s', $note->comment_date, $text );
			}
		}

		Target::set_order_details(
			$copy_id,
			array(
				'country'   => $billing( 'country' ),
				'post_code' => $billing( 'postcode' ),
				'state'     => $billing( 'state' ),
				'city'      => $billing( 'city' ),
				'company'   => $billing( 'company' ),
				'phone'     => $billing( 'phone' ),
			),
			implode( "\n", $note_lines )
		);

		$refunds = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'mto-order-refund' AND post_parent = %d", $order_id )
		);

		if ( $refunds ) {
			Report::add(
				Report::GROUP_ORDERS,
				array(
					'source_id' => $order_id,
					/* translators: %d: order ID */
					'title'     => sprintf( __( 'Order #%d', 'masterstudy-lms-learning-management-system' ), $order_id ),
					'type'      => __( 'Partial refunds', 'masterstudy-lms-learning-management-system' ),
					/* translators: %d: number of refunds */
					'reason'    => sprintf( __( 'MasterStudy orders have no refund records — %d refund record(s) of the order were not imported (the order total and status were kept).', 'masterstudy-lms-learning-management-system' ), $refunds ),
					'status'    => Report::STATUS_PARTIAL,
					'post_id'   => $copy_id,
				)
			);
		}
	}

	/**
	 * Store the Masteriyo coupon line(s) of an order in the MasterStudy order coupon meta
	 * (coupon_value / coupon_type / coupon_id), like the Tutor LMS reader.
	 *
	 * A single percentage coupon on a single-course order keeps its rate; otherwise (fixed or deleted
	 * coupons, several coupons, several courses) the amount actually deducted is stored.
	 *
	 * @param int   $order_id     MasterStudy order (copy) ID.
	 * @param array $coupons      List of ['code' => string, 'discount' => float].
	 * @param float $discount     Order discount total (fallback amount).
	 * @param bool  $keep_percent Whether a percentage coupon may be stored as its rate.
	 */
	private static function set_order_coupon( int $order_id, array $coupons, float $discount, bool $keep_percent ): void {
		global $wpdb;

		$amount = array_sum( array_column( $coupons, 'discount' ) );
		$amount = $amount > 0 ? $amount : $discount;
		$code   = (string) $coupons[0]['code'];
		$type   = 'amount';
		$value  = $amount;

		if ( $keep_percent && 1 === count( $coupons ) ) {
			$coupon_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title = %s ORDER BY ID ASC LIMIT 1",
					self::COUPON,
					$code
				)
			);

			if ( $coupon_id && false !== strpos( strtolower( self::first_meta( $coupon_id, array( '_discount_type', '_type' ) ) ), 'percent' ) ) {
				$rate = (float) self::first_meta( $coupon_id, array( '_discount_amount', '_amount', '_discount' ) );

				if ( $rate > 0 ) {
					$type  = 'percent';
					$value = min( 100, $rate );
				}
			}
		}

		if ( $value <= 0 ) {
			return;
		}

		update_post_meta( $order_id, 'coupon_value', $value );
		update_post_meta( $order_id, 'coupon_type', $type );
		Target::store_unmigrated_meta( $order_id, 'coupon_codes', implode( ', ', array_column( $coupons, 'code' ) ) );

		$ms_coupons = $wpdb->prefix . 'stm_lms_coupons';

		if ( self::table_exists( $ms_coupons ) ) {
			$ms_coupon_id = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT id FROM {$ms_coupons} WHERE code = %s LIMIT 1", strtoupper( sanitize_text_field( $code ) ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);

			if ( $ms_coupon_id ) {
				update_post_meta( $order_id, 'coupon_id', $ms_coupon_id );
			}
		}
	}

	/**
	 * Migrate a single Masteriyo course review comment to a MasterStudy stm-reviews post.
	 *
	 * Replies (MasterStudy has no review replies) are kept on the review as unmigrated meta.
	 * The Masteriyo review and its replies stay. Idempotent per review (Target::add_review() source key).
	 *
	 * @param int $comment_id Masteriyo mto_course_review comment ID.
	 * @throws \Exception If the comment does not exist or is not a Masteriyo review.
	 */
	public static function migrate_single_review( int $comment_id ): void {
		global $wpdb;

		$review = get_comment( $comment_id );

		if ( ! $review || self::COMMENT_REVIEW !== $review->comment_type ) {
			throw new \Exception(
				sprintf( 'Review comment #%d was not found or is not a Masteriyo course review.', $comment_id ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		$user_id = (int) $review->user_id;

		if ( ! $user_id && $review->comment_author_email ) {
			$user    = get_user_by( 'email', $review->comment_author_email );
			$user_id = $user ? (int) $user->ID : 0;
		}

		$course_id   = (int) $review->comment_post_ID;
		$course_copy = Target::copy_of( self::SOURCE, $course_id );

		if ( ! $course_copy ) {
			Report::add(
				Report::GROUP_REVIEWS,
				array(
					'source_id' => $comment_id,
					'title'     => self::user_label( $user_id ) . ' → ' . self::title_of( $course_id ),
					'type'      => __( 'Review', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'The reviewed course no longer exists or was not migrated, so the review was not imported.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_UNSUPPORTED,
					'course'    => self::title_of( $course_id ),
				)
			);

			return;
		}

		// Spam and trashed reviews go to the MasterStudy trash (recoverable, never shown or counted).
		$discarded = in_array( (string) $review->comment_approved, array( 'spam', 'trash', 'post-trashed' ), true );

		$review_id = Target::add_review(
			array(
				'course_id'   => $course_copy,
				'user_id'     => $user_id,
				'mark'        => max( 1, (int) $review->comment_karma ),
				'content'     => $review->comment_content,
				'date'        => $review->comment_date,
				'approved'    => '1' === (string) $review->comment_approved,
				'post_status' => $discarded ? 'trash' : '',
				'source_id'   => 'review-' . $comment_id,
			),
			self::SOURCE
		);

		if ( $discarded ) {
			Report::add(
				Report::GROUP_REVIEWS,
				array(
					'source_id' => $comment_id,
					'title'     => self::user_label( $user_id ) . ' → ' . self::title_of( $course_id ),
					'type'      => 'spam' === (string) $review->comment_approved ? __( 'Spam review', 'masterstudy-lms-learning-management-system' ) : __( 'Trashed review', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'The review was marked as spam or trashed in Masteriyo — it was imported into the MasterStudy trash (not shown and not counted in the course rating).', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_UNSUPPORTED,
					'course'    => self::title_of( $course_id ),
					'post_id'   => $review_id,
				)
			);
		}

		Target::store_unmigrated_meta( $review_id, 'review_title', (string) get_comment_meta( $comment_id, '_title', true ) );

		$reply_ids = array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT comment_ID FROM {$wpdb->comments} WHERE comment_parent = %d AND comment_type = %s ORDER BY comment_ID ASC",
					$comment_id,
					self::COMMENT_REVIEW
				)
			)
		);

		$replies = array();

		foreach ( $reply_ids as $reply_id ) {
			$reply = get_comment( $reply_id );

			if ( $reply ) {
				$replies[] = array(
					'user_id' => (int) $reply->user_id,
					'author'  => $reply->comment_author,
					'date'    => $reply->comment_date,
					'content' => $reply->comment_content,
				);
			}
		}

		Target::store_unmigrated_meta( $review_id, 'review_replies', $replies );

		$lost = array();

		if ( (int) $review->comment_karma < 1 ) {
			$lost[] = __( 'the review had no star rating and was imported as a 1-star review', 'masterstudy-lms-learning-management-system' );
		}

		if ( ! empty( $replies ) ) {
			/* translators: %d: number of replies */
			$lost[] = sprintf( __( 'MasterStudy reviews have no replies — %d reply(ies) were not imported', 'masterstudy-lms-learning-management-system' ), count( $replies ) );
		}

		if ( ! empty( $lost ) ) {
			Report::add(
				Report::GROUP_REVIEWS,
				array(
					'source_id' => $comment_id,
					'title'     => self::user_label( $user_id ) . ' → ' . self::title_of( $course_id ),
					'type'      => (int) $review->comment_karma < 1 ? __( 'Review without rating', 'masterstudy-lms-learning-management-system' ) : __( 'Review with replies', 'masterstudy-lms-learning-management-system' ),
					'reason'    => ucfirst( implode( '; ', $lost ) ) . '.',
					'status'    => Report::STATUS_PARTIAL,
					'course'    => self::title_of( $course_id ),
					'post_id'   => $review_id,
				)
			);
		}
	}

	/**
	 * Migrate a single Masteriyo course announcement to the `announcement` meta of the course copy.
	 *
	 * MasterStudy keeps one HTML block per course, so announcements are appended (never twice). The
	 * Masteriyo announcement post stays.
	 *
	 * @param int $post_id mto-announcement post ID.
	 * @throws \Exception If the post does not exist.
	 */
	public static function migrate_single_announcement( int $post_id ): void {
		$announcement = get_post( $post_id );

		if ( ! $announcement || self::ANNOUNCEMENT !== $announcement->post_type ) {
			throw new \Exception( sprintf( 'Masteriyo announcement #%d was not found.', $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$course_id = (int) get_post_meta( $post_id, '_course_id', true );
		$copy_id   = $course_id ? Target::copy_of( self::SOURCE, $course_id ) : 0;

		if ( ! $copy_id || PostType::COURSE !== get_post_type( $copy_id ) ) {
			Report::add(
				Report::GROUP_OTHER,
				array(
					'source_id' => $post_id,
					'title'     => $announcement->post_title,
					'type'      => __( 'Orphan announcement', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'The announcement is not linked to a course that exists in MasterStudy, so there was no course to add it to — it was not imported.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_UNSUPPORTED,
					'post_id'   => $post_id,
				)
			);

			return;
		}

		if ( 'publish' === $announcement->post_status ) {
			$current = self::plain_text( (string) get_post_meta( $copy_id, 'announcement', true ) );
			$content = self::plain_text( $announcement->post_content );

			if ( '' === $content || '' === $current || false === strpos( $current, $content ) ) {
				Target::add_announcement( $copy_id, $announcement->post_title, $announcement->post_content );
			}
		} elseif ( 'trash' !== $announcement->post_status ) {
			// The MasterStudy course announcement is public: unpublished announcements are kept in the course meta.
			$kept  = get_post_meta( $copy_id, '_migrated_unpublished_announcements', true );
			$kept  = is_array( $kept ) ? $kept : array();
			$entry = array(
				'title'   => $announcement->post_title,
				'content' => $announcement->post_content,
				'status'  => $announcement->post_status,
				'date'    => $announcement->post_date,
			);

			if ( ! in_array( $entry, $kept, true ) ) {
				$kept[] = $entry;
				update_post_meta( $copy_id, '_migrated_unpublished_announcements', $kept );
			}

			Report::add(
				Report::GROUP_OTHER,
				array(
					'source_id' => $post_id,
					'title'     => $announcement->post_title,
					'type'      => __( 'Unpublished announcement', 'masterstudy-lms-learning-management-system' ),
					/* translators: %s: post status */
					'reason'    => sprintf( __( 'The announcement was not published in Masteriyo (status "%s"). The MasterStudy course announcement is public, so it was not added — its text is kept in the course meta _migrated_unpublished_announcements.', 'masterstudy-lms-learning-management-system' ), $announcement->post_status ),
					'status'    => Report::STATUS_UNSUPPORTED,
					'course'    => self::title_of( $course_id ),
					'post_id'   => $copy_id,
				)
			);
		} else {
			Report::add(
				Report::GROUP_OTHER,
				array(
					'source_id' => $post_id,
					'title'     => $announcement->post_title,
					'type'      => __( 'Trashed announcement', 'masterstudy-lms-learning-management-system' ),
					'reason'    => __( 'The announcement was in the trash, so it was not added to the course.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_UNSUPPORTED,
					'course'    => self::title_of( $course_id ),
					'post_id'   => $copy_id,
				)
			);
		}
	}

	/**
	 * Migrate a single Masteriyo discussion thread (root comment + all replies) to MasterStudy discussions.
	 *
	 * - Course Q&A (mto_course_qa) is attached to the course; MasterStudy discussions live on curriculum items,
	 *   so the thread goes to the first lesson of the course copy.
	 * - Lesson comments (mto_lesson_review) and quiz reviews (mto_quiz_review) go to the copy of their lesson/quiz.
	 * Comment statuses are kept (pending, spam and trash stay non-public). A course without any migrated item keeps
	 * the thread text in the course copy meta. The Masteriyo comments stay; every imported comment remembers its
	 * source comment, so a second run does not duplicate the thread.
	 *
	 * @param int $comment_id Root comment ID.
	 * @throws \Exception If the comment does not exist.
	 */
	public static function migrate_single_qa( int $comment_id ): void {
		$root  = get_comment( $comment_id );
		$types = array( self::COMMENT_QA, self::COMMENT_LESSON_REVIEW, self::COMMENT_QUIZ_REVIEW );

		if ( ! $root || ! in_array( $root->comment_type, $types, true ) ) {
			throw new \Exception( sprintf( 'Masteriyo discussion thread #%d was not found.', $comment_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$type      = (string) $root->comment_type;
		$source_id = (int) $root->comment_post_ID;
		$thread    = self::comment_thread( $root );

		if ( self::COMMENT_QA === $type ) {
			$course_id   = $source_id;
			$course_copy = Target::copy_of( self::SOURCE, $course_id );
			$target_id   = $course_copy ? self::first_course_lesson( $course_copy ) : 0;
		} else {
			$item_copy   = Target::copy_of( self::SOURCE, $source_id );
			$target_id   = $item_copy && in_array( get_post_type( $item_copy ), array( PostType::LESSON, PostType::QUIZ ), true ) ? $item_copy : 0;
			$courses     = $target_id ? Target::course_ids_of( $target_id ) : array();
			$course_copy = $courses ? (int) $courses[0] : Target::copy_of( self::SOURCE, (int) get_post_meta( $source_id, '_course_id', true ) );
			$course_id   = $course_copy ? Target::source_of( $course_copy ) : (int) get_post_meta( $source_id, '_course_id', true );
		}

		$context = array(
			'source_id' => $comment_id,
			/* translators: %s: user */
			'title'     => sprintf( __( 'Discussion started by %s', 'masterstudy-lms-learning-management-system' ), self::user_label( (int) $root->user_id ) ),
			'type'      => self::COMMENT_QA === $type ? __( 'Course Q&A', 'masterstudy-lms-learning-management-system' ) : ( self::COMMENT_QUIZ_REVIEW === $type ? __( 'Quiz review', 'masterstudy-lms-learning-management-system' ) : __( 'Lesson comment', 'masterstudy-lms-learning-management-system' ) ),
			'course'    => $course_id ? self::title_of( $course_id ) : '',
		);

		if ( ! $target_id ) {
			// No lesson/quiz to attach to: keep the text on the course copy (or report it when there is none).
			if ( $course_copy ) {
				$kept  = get_post_meta( $course_copy, '_migrated_unattached_discussions', true );
				$kept  = is_array( $kept ) ? $kept : array();
				$entry = array_map(
					function ( $comment ) {
						return array(
							'user_id' => (int) $comment->user_id,
							'author'  => $comment->comment_author,
							'date'    => $comment->comment_date,
							'parent'  => (int) $comment->comment_parent,
							'status'  => (string) $comment->comment_approved,
							'content' => $comment->comment_content,
						);
					},
					$thread
				);

				if ( ! in_array( $entry, $kept, true ) ) {
					$kept[] = $entry;
					update_post_meta( $course_copy, '_migrated_unattached_discussions', $kept );
				}
			}

			Report::add(
				Report::GROUP_DISCUSSIONS,
				$context + array(
					'reason'  => $course_copy
						/* translators: %d: number of comments */
						? sprintf( __( 'MasterStudy discussions live on lessons and quizzes, and there is no migrated lesson or quiz to attach this thread to — its %d comment(s) are kept in the course meta _migrated_unattached_discussions.', 'masterstudy-lms-learning-management-system' ), count( $thread ) )
						: __( 'The thread belongs to a lesson, quiz or course that no longer exists or was not migrated, so it was not imported.', 'masterstudy-lms-learning-management-system' ),
					'status'  => Report::STATUS_UNSUPPORTED,
					'post_id' => $course_copy ? $course_copy : ( get_post( $source_id ) ? $source_id : 0 ),
				)
			);

			return;
		}

		$map = array( (int) $root->comment_parent => 0 );

		foreach ( $thread as $comment ) {
			$status   = (string) $comment->comment_approved;
			$existing = self::imported_comment( (int) $comment->comment_ID );

			if ( $existing ) {
				$map[ (int) $comment->comment_ID ] = $existing;
				continue;
			}

			$new_id = Target::add_discussion(
				array(
					'post_id'  => $target_id,
					'user_id'  => (int) $comment->user_id,
					'content'  => $comment->comment_content,
					'date'     => $comment->comment_date,
					'date_gmt' => $comment->comment_date_gmt,
					'parent'   => (int) ( $map[ (int) $comment->comment_parent ] ?? 0 ),
					'approved' => '1' === $status,
					'status'   => 'post-trashed' === $status ? 'trash' : $status,
				)
			);

			if ( $new_id ) {
				add_comment_meta( $new_id, Target::SOURCE_META, self::SOURCE, true );
				add_comment_meta( $new_id, Target::SOURCE_ID_META, 'comment-' . (int) $comment->comment_ID, true );
			}

			$map[ (int) $comment->comment_ID ] = $new_id;
		}

		if ( self::COMMENT_QUIZ_REVIEW === $type && (int) $root->comment_karma > 0 ) {
			Report::add(
				Report::GROUP_DISCUSSIONS,
				$context + array(
					/* translators: %d: star rating */
					'reason'  => sprintf( __( 'The quiz review was imported as a quiz discussion without its %d-star rating and title (MasterStudy quizzes have no ratings).', 'masterstudy-lms-learning-management-system' ), (int) $root->comment_karma ),
					'status'  => Report::STATUS_PARTIAL,
					'post_id' => $target_id,
				)
			);
		}
	}

	/**
	 * MasterStudy discussion comment imported from a Masteriyo comment (0 = not imported yet).
	 *
	 * @param int $comment_id Masteriyo comment ID.
	 */
	private static function imported_comment( int $comment_id ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT i.comment_id FROM {$wpdb->commentmeta} i
				 INNER JOIN {$wpdb->commentmeta} f ON f.comment_id = i.comment_id AND f.meta_key = %s AND f.meta_value = %s
				 WHERE i.meta_key = %s AND i.meta_value = %s
				 LIMIT 1",
				Target::SOURCE_META,
				self::SOURCE,
				Target::SOURCE_ID_META,
				'comment-' . $comment_id
			)
		);
	}

	/**
	 * A comment and all its replies of the same type, parents before children (breadth-first).
	 *
	 * @param \WP_Comment $root Root comment.
	 * @return \WP_Comment[]
	 */
	private static function comment_thread( \WP_Comment $root ): array {
		global $wpdb;

		$thread = array();
		$queue  = array( $root );
		$seen   = array();

		while ( ! empty( $queue ) ) {
			$comment = array_shift( $queue );

			if ( isset( $seen[ (int) $comment->comment_ID ] ) ) {
				continue;
			}

			$seen[ (int) $comment->comment_ID ] = true;
			$thread[]                           = $comment;

			$children = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT comment_ID FROM {$wpdb->comments} WHERE comment_parent = %d AND comment_type = %s ORDER BY comment_ID ASC",
					(int) $comment->comment_ID,
					$root->comment_type
				)
			);

			foreach ( (array) $children as $child_id ) {
				$child = get_comment( (int) $child_id );

				if ( $child ) {
					$queue[] = $child;
				}
			}
		}

		return $thread;
	}

	/**
	 * Migrate a single Masteriyo lesson activity row to stm_lms_user_lessons of the lesson copy. The row stays.
	 *
	 * MasterStudy only records completed lessons (a row = completed); started lessons are reported.
	 *
	 * @param int $activity_id Primary key of the masteriyo_user_activities row.
	 * @throws \Exception If the row does not exist.
	 */
	public static function migrate_single_lesson_progress( int $activity_id ): void {
		global $wpdb;

		$activities_tbl = $wpdb->prefix . 'masteriyo_user_activities';

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT a.user_id, a.item_id, a.activity_type, a.activity_status, a.created_at, a.modified_at, a.completed_at, p.item_id AS course_id
				 FROM {$activities_tbl} a
				 LEFT JOIN {$activities_tbl} p ON p.id = a.parent_id AND p.activity_type = 'course_progress'
				 WHERE a.id = %d AND a.activity_type IN ('lesson', 'google-meet', 'zoom')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$activity_id
			)
		);

		if ( ! $row ) {
			throw new \Exception(
				sprintf( 'Masteriyo lesson progress record #%d was not found.', $activity_id ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		$user_id     = (int) $row->user_id;
		$lesson_id   = (int) $row->item_id;
		$lesson_copy = $lesson_id ? Target::copy_of( self::SOURCE, $lesson_id ) : 0;
		$course_copy = (int) $row->course_id ? Target::copy_of( self::SOURCE, (int) $row->course_id ) : 0;

		if ( ! $course_copy && $lesson_copy ) {
			$course_ids  = Target::course_ids_of( $lesson_copy );
			$course_copy = $course_ids ? (int) $course_ids[0] : 0;
		}

		if ( ! $course_copy && $lesson_id ) {
			$course_copy = Target::copy_of( self::SOURCE, (int) get_post_meta( $lesson_id, '_course_id', true ) );
		}

		$course_id = $course_copy ? Target::source_of( $course_copy ) : (int) $row->course_id;

		// Lessons, Zoom lessons and Google Meets are completed through stm_lms_user_lessons — once copied.
		$copied = $lesson_copy && in_array( get_post_type( $lesson_copy ), array( PostType::LESSON, PostType::GOOGLE_MEET ), true );

		if ( 'lesson' === $row->activity_type ) {
			self::migrate_lesson_notes( $activity_id, $user_id, $course_copy, $lesson_id, $lesson_copy, (string) $row->modified_at );
		}

		if ( 'completed' === $row->activity_status && $user_id && $lesson_id && $course_copy && $copied && get_userdata( $user_id ) ) {
			$end_time = self::gmt_to_timestamp( $row->completed_at );

			Target::complete_lesson( $user_id, $course_copy, $lesson_copy, self::gmt_to_timestamp( $row->created_at ), $end_time );
		} elseif ( 'completed' === $row->activity_status && $lesson_id && ! $copied && in_array( get_post_type( $lesson_id ), array( self::GOOGLE_MEET, self::ZOOM ), true ) ) {
			Report::add(
				Report::GROUP_PROGRESS,
				array(
					'source_id' => $activity_id,
					'title'     => self::user_label( $user_id ) . ' → ' . self::title_of( $lesson_id ),
					'type'      => self::item_type_label( (string) get_post_type( $lesson_id ) ),
					'reason'    => __( 'The meeting was not imported (MasterStudy LMS Pro / Pro Plus is required), so its completion was not imported either.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_UNSUPPORTED,
					'course'    => $course_id ? self::title_of( $course_id ) : '',
					'post_id'   => $lesson_copy ? $lesson_copy : $lesson_id,
				)
			);
		} else {
			$completed = 'completed' === $row->activity_status;

			Report::add(
				Report::GROUP_PROGRESS,
				array(
					'source_id' => $activity_id,
					'title'     => self::user_label( $user_id ) . ' → ' . ( $lesson_id ? self::title_of( $lesson_id ) : __( 'unknown lesson', 'masterstudy-lms-learning-management-system' ) ),
					'type'      => $completed ? __( 'Completed lesson', 'masterstudy-lms-learning-management-system' ) : __( 'In-progress lesson', 'masterstudy-lms-learning-management-system' ),
					'reason'    => $completed
						? __( 'The lesson completion is not linked to a user, lesson or course that exists in MasterStudy, so it was skipped.', 'masterstudy-lms-learning-management-system' )
						: __( 'MasterStudy only stores completed lessons — progress on a lesson that was started but not completed was not imported.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_UNSUPPORTED,
					'course'    => $course_id ? self::title_of( $course_id ) : '',
					'post_id'   => $lesson_copy ? $lesson_copy : ( $lesson_id && get_post( $lesson_id ) ? $lesson_id : 0 ),
				)
			);
		}
	}

	/**
	 * Masteriyo player notes of a lesson (activity meta `notes`: [{time_stamp, notes_info: [{title, description}]}])
	 * → MasterStudy lesson notes (Pro Plus). Notes are private to their author in both plugins.
	 *
	 * @param int    $activity_id Lesson activity row ID.
	 * @param int    $user_id     Learner.
	 * @param int    $course_id   MasterStudy course (copy) ID.
	 * @param int    $lesson_id   Masteriyo lesson ID.
	 * @param int    $lesson_copy MasterStudy lesson (copy) ID, 0 when not copied.
	 * @param string $modified_at Activity modification time (GMT).
	 */
	private static function migrate_lesson_notes( int $activity_id, int $user_id, int $course_id, int $lesson_id, int $lesson_copy, string $modified_at ): void {
		global $wpdb;

		$raw = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->prefix}masteriyo_user_activitymeta WHERE user_activity_id = %d AND meta_key = 'notes' LIMIT 1",
				$activity_id
			)
		);

		$groups = maybe_unserialize( (string) $raw );
		$notes  = array();

		foreach ( is_array( $groups ) ? $groups : array() as $group ) {
			$group = is_object( $group ) ? (array) $group : $group;

			if ( ! is_array( $group ) ) {
				continue;
			}

			foreach ( (array) ( $group['notes_info'] ?? $group['notesInfo'] ?? array() ) as $note ) {
				$note  = is_object( $note ) ? (array) $note : (array) $note;
				$title = trim( wp_strip_all_tags( (string) ( $note['title'] ?? '' ) ) );
				$body  = trim( wp_strip_all_tags( (string) ( $note['description'] ?? '' ) ) );

				if ( '' === $title && '' === $body ) {
					continue;
				}

				$notes[] = array(
					'body'       => '' !== $title && '' !== $body ? $title . "\n\n" . $body : $title . $body,
					'media_time' => $group['time_stamp'] ?? $group['timeStamp'] ?? null,
				);
			}
		}

		if ( empty( $notes ) ) {
			return;
		}

		$imported = 0;

		if ( ProTarget::lesson_notes_available() && get_userdata( $user_id ) && $lesson_copy && PostType::LESSON === get_post_type( $lesson_copy ) ) {
			$created = '' !== $modified_at && 0 !== strpos( $modified_at, '0000-00-00' ) ? $modified_at : current_time( 'mysql', true );
			$type    = (string) get_post_meta( $lesson_copy, 'type', true );

			foreach ( $notes as $index => $note ) {
				ProTarget::add_lesson_note(
					array(
						'user_id'     => $user_id,
						'course_id'   => $course_id,
						'lesson_id'   => $lesson_copy,
						'lesson_type' => in_array( $type, array( 'video', 'audio' ), true ) ? $type : 'text',
						'body'        => $note['body'],
						'media_time'  => is_numeric( $note['media_time'] ) ? (int) $note['media_time'] : null,
						// One second apart keeps notes distinct and in their original order.
						'created_at'  => gmdate( 'Y-m-d H:i:s', strtotime( $created . ' UTC' ) + $index ),
					)
				);
				++$imported;
			}
		}

		if ( $imported === count( $notes ) ) {
			return;
		}

		Report::add(
			Report::GROUP_PROGRESS,
			array(
				'source_id' => $activity_id,
				'title'     => self::user_label( $user_id ) . ' → ' . self::title_of( $lesson_id ),
				'type'      => __( 'Lesson notes', 'masterstudy-lms-learning-management-system' ),
				'reason'    => ProTarget::plus_active()
					/* translators: %d: number of notes */
					? sprintf( __( '%d private lesson note(s) could not be imported (the lesson or the learner was not migrated).', 'masterstudy-lms-learning-management-system' ), count( $notes ) )
					/* translators: %d: number of notes */
					: sprintf( __( 'Lesson notes require MasterStudy LMS Pro Plus — %d private lesson note(s) of the learner were not imported.', 'masterstudy-lms-learning-management-system' ), count( $notes ) ),
				'status'    => Report::STATUS_UNSUPPORTED,
				'course'    => $course_id ? self::title_of( $course_id ) : '',
				'post_id'   => $lesson_copy ? $lesson_copy : ( get_post( $lesson_id ) ? $lesson_id : 0 ),
			)
		);
	}

	/**
	 * Migrate a single Masteriyo quiz attempt to stm_lms_user_quizzes (+ stm_lms_user_answers).
	 *
	 * Attempts are processed in ID (chronological) order, which keeps MasterStudy's implicit
	 * attempt_number linkage correct. The attempt is stored for the quiz copy; the Masteriyo row stays.
	 *
	 * @param int $attempt_id masteriyo_quiz_attempts.id primary key.
	 * @throws \Exception If the row does not exist or the DB insert fails.
	 */
	public static function migrate_single_quiz_attempt( int $attempt_id ): void {
		global $wpdb;

		$attempts_tbl = $wpdb->prefix . 'masteriyo_quiz_attempts';

		$attempt = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$attempts_tbl} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$attempt_id
			)
		);

		if ( ! $attempt ) {
			throw new \Exception(
				sprintf( 'Masteriyo quiz attempt #%d was not found.', $attempt_id ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		$user_id   = (int) $attempt->user_id;
		$quiz_id   = (int) $attempt->quiz_id;
		$course_id = (int) $attempt->course_id;
		$quiz_copy = $quiz_id ? Target::copy_of( self::SOURCE, $quiz_id ) : 0;

		// An attempt still in progress has no MasterStudy equivalent (only submitted attempts are stored); an
		// attempt of a deleted user or of a quiz that was not migrated has nowhere to go.
		$orphan = $user_id && $quiz_id && ( ! get_userdata( $user_id ) || ! $quiz_copy || PostType::QUIZ !== get_post_type( $quiz_copy ) );

		if ( ! $user_id || ! $quiz_id || $orphan || 'attempt_started' === $attempt->attempt_status ) {
			$unfinished = $user_id && $quiz_id && ! $orphan;

			Report::add(
				Report::GROUP_QUIZ_ATTEMPTS,
				array(
					'source_id' => $attempt_id,
					/* translators: 1: attempt ID, 2: user */
					'title'     => sprintf( __( 'Attempt #%1$d by %2$s', 'masterstudy-lms-learning-management-system' ), $attempt_id, self::user_label( $user_id ) ),
					'type'      => $unfinished ? __( 'Unfinished quiz attempt', 'masterstudy-lms-learning-management-system' ) : __( 'Quiz attempt', 'masterstudy-lms-learning-management-system' ),
					'reason'    => $unfinished
						? __( 'The attempt was started but never submitted — MasterStudy only stores submitted attempts.', 'masterstudy-lms-learning-management-system' )
						: __( 'The attempt is not linked to a user or quiz that exists in MasterStudy, so it was skipped.', 'masterstudy-lms-learning-management-system' ),
					'status'    => Report::STATUS_UNSUPPORTED,
					/* translators: %s: quiz title */
					'parent'    => $quiz_id ? sprintf( __( 'Quiz: %s', 'masterstudy-lms-learning-management-system' ), self::title_of( $quiz_id ) ) : '',
					'course'    => $course_id ? self::title_of( $course_id ) : '',
					'post_id'   => $quiz_copy ? $quiz_copy : ( $quiz_id && get_post( $quiz_id ) ? $quiz_id : 0 ),
				)
			);

			return;
		}

		$course_copy = $course_id ? Target::copy_of( self::SOURCE, $course_id ) : 0;

		if ( ! $course_copy ) {
			$course_ids  = Target::course_ids_of( $quiz_copy );
			$course_copy = $course_ids ? (int) $course_ids[0] : 0;
			$course_id   = $course_copy ? Target::source_of( $course_copy ) : $course_id;
		}

		$started_gmt = $attempt->attempt_started_at ? $attempt->attempt_started_at : gmdate( 'Y-m-d H:i:s' );
		$created_at  = get_date_from_gmt( $started_gmt );

		$total_marks = (float) $attempt->total_marks;
		$percent     = $total_marks > 0 ? (float) $attempt->earned_marks / $total_marks * 100 : 0.0;

		if ( in_array( $attempt->attempt_status, array( 'passed', 'failed' ), true ) ) {
			// Attempts Masteriyo migrated from MasterStudy carry the original status.
			$passed = 'passed' === $attempt->attempt_status;
		} else {
			$verdict = static::attempt_passed( $attempt, $quiz_id, $percent );

			if ( null !== $verdict ) {
				$passed = $verdict;
			} else {
				$passing = get_post_meta( $quiz_copy, 'passing_grade', true );
				$passed  = '' === (string) $passing ? $percent > 0 : $percent >= (float) $passing;
			}
		}

		// Idempotency — skip if this exact attempt was already migrated (score + result: a retry can share the second).
		if ( Target::quiz_attempt_exists( $user_id, $quiz_copy, $created_at, $percent, $passed ) ) {
			return;
		}

		Target::add_quiz_attempt(
			$user_id,
			$course_copy,
			$quiz_copy,
			$percent,
			$passed,
			$created_at,
			self::convert_attempt_answers( maybe_unserialize( $attempt->answers ) )
		);

		if ( 'attempt_pending' === $attempt->attempt_status ) {
			Report::add(
				Report::GROUP_QUIZ_ATTEMPTS,
				array(
					'source_id' => $attempt_id,
					/* translators: 1: attempt ID, 2: user */
					'title'     => sprintf( __( 'Attempt #%1$d by %2$s', 'masterstudy-lms-learning-management-system' ), $attempt_id, self::user_label( $user_id ) ),
					'type'      => __( 'Attempt pending review', 'masterstudy-lms-learning-management-system' ),
					/* translators: %s: score percent */
					'reason'    => sprintf( __( 'The attempt was still waiting for manual grading in Masteriyo — it was imported with its current automatic score (%s%%).', 'masterstudy-lms-learning-management-system' ), round( $percent, 2 ) ),
					'status'    => Report::STATUS_PARTIAL,
					/* translators: %s: quiz title */
					'parent'    => sprintf( __( 'Quiz: %s', 'masterstudy-lms-learning-management-system' ), self::title_of( $quiz_id ) ),
					'course'    => $course_id ? self::title_of( $course_id ) : '',
					'post_id'   => $quiz_copy,
				)
			);
		}
	}

	/**
	 * Migrate all Masteriyo wishlist items of a single user to the MasterStudy wishlist meta (course copies).
	 * The mto-wishlist-item posts stay.
	 *
	 * @param int $user_id WordPress user ID.
	 */
	public static function migrate_single_wishlist( int $user_id ): void {
		global $wpdb;

		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_parent FROM {$wpdb->posts} WHERE post_type = %s AND post_author = %d",
				self::WISHLIST,
				$user_id
			)
		);

		$course_ids = array();

		$missing = 0;

		foreach ( (array) $items as $item ) {
			$course_copy = (int) $item->post_parent ? Target::copy_of( self::SOURCE, (int) $item->post_parent ) : 0;

			if ( $course_copy && PostType::COURSE === get_post_type( $course_copy ) ) {
				$course_ids[] = $course_copy;
			} else {
				++$missing;
			}
		}

		if ( $missing ) {
			Report::add(
				Report::GROUP_OTHER,
				array(
					'source_id' => $user_id,
					/* translators: %s: user */
					'title'     => sprintf( __( 'Wishlist of %s', 'masterstudy-lms-learning-management-system' ), self::user_label( $user_id ) ),
					'type'      => __( 'Wishlist', 'masterstudy-lms-learning-management-system' ),
					/* translators: %d: number of courses */
					'reason'    => sprintf( __( '%d wishlisted course(s) no longer exist or were not migrated, so they were left out of the wishlist.', 'masterstudy-lms-learning-management-system' ), $missing ),
					'status'    => Report::STATUS_PARTIAL,
				)
			);
		}

		if ( $user_id && ! empty( $course_ids ) && get_userdata( $user_id ) ) {
			Target::add_to_wishlist( $user_id, $course_ids );
		} elseif ( ! empty( $course_ids ) ) {
			Report::add(
				Report::GROUP_OTHER,
				array(
					'source_id' => $user_id,
					/* translators: %s: user */
					'title'     => sprintf( __( 'Wishlist of %s', 'masterstudy-lms-learning-management-system' ), self::user_label( $user_id ) ),
					'type'      => __( 'Wishlist', 'masterstudy-lms-learning-management-system' ),
					/* translators: %d: number of courses */
					'reason'    => sprintf( __( 'The wishlist owner no longer exists — %d wishlisted course(s) were not imported.', 'masterstudy-lms-learning-management-system' ), count( $course_ids ) ),
					'status'    => Report::STATUS_UNSUPPORTED,
				)
			);
		}
	}

	/**
	 * Bulk recalculations run once after a step completes (only for the MasterStudy copies of Masteriyo courses).
	 *
	 * @param string $step Step name.
	 */
	public static function finalize_step( string $step ): void {
		switch ( $step ) {
			case 'courses':
				self::migrate_orphan_items();
				self::migrate_all_prerequisites();
				self::migrate_bundles();
				self::migrate_coupons();
				self::report_unsupported_records();

				// Groups enroll their members, so they wait for the enrollments step (source dates kept)
				// unless that step has nothing to do and would not reach its finalize.
				if ( 0 === self::count_source_items( 'enrollments' ) ) {
					self::migrate_groups();
				}

				// finalize_step() runs outside the batch transaction; enable requested addons now.
				Helper::flush_addon_requests();
				break;

			case 'enrollments':
				self::migrate_groups();

				foreach ( self::course_copies() as $course_id ) {
					Target::refresh_students_count( $course_id );
				}

				Helper::flush_addon_requests();
				break;

			case 'reviews':
				Target::recalculate_ratings();
				break;

			case 'lesson_progress':
			case 'quiz_attempts':
				$course_ids = self::course_copies();

				// An empty list would recalculate every enrollment of the site.
				if ( ! empty( $course_ids ) ) {
					Target::recalculate_progress( $course_ids );
				}
				break;
		}
	}

	/**
	 * MasterStudy copies of all Masteriyo courses.
	 *
	 * @return int[]
	 */
	private static function course_copies(): array {
		$copies = array();

		foreach ( self::source_post_ids( self::COURSE, true ) as $course_id ) {
			$copy_id = Target::copy_of( self::SOURCE, $course_id );

			if ( $copy_id && PostType::COURSE === get_post_type( $copy_id ) ) {
				$copies[] = $copy_id;
			}
		}

		return $copies;
	}

	/**
	 * Curriculum posts no migrated course curriculum reached — items of a deleted or trashed section, and
	 * question-bank questions that belong to no copied quiz. They are copied (status kept) so their content
	 * lives on in the MasterStudy lesson / question libraries, and reported.
	 */
	private static function migrate_orphan_items(): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT i.ID, i.post_type, i.post_status FROM {$wpdb->posts} i
				 LEFT JOIN {$wpdb->posts} s ON s.ID = i.post_parent AND s.post_type = %s AND s.post_status <> 'trash'
				 WHERE i.post_type IN (%s, %s, %s, %s, %s) AND i.post_status NOT IN ('trash', 'auto-draft') AND s.ID IS NULL
				 ORDER BY i.ID ASC",
				self::SECTION,
				self::LESSON,
				self::QUIZ,
				self::ASSIGNMENT,
				self::ZOOM,
				self::GOOGLE_MEET
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		foreach ( (array) $items as $item ) {
			$item_id     = (int) $item->ID;
			$course_copy = Target::copy_of( self::SOURCE, (int) get_post_meta( $item_id, '_course_id', true ) );
			$course_copy = PostType::COURSE === get_post_type( $course_copy ) ? $course_copy : 0;
			$context     = array(
				'parent' => __( 'No section', 'masterstudy-lms-learning-management-system' ),
				'course' => $course_copy ? self::title_of( $course_copy ) : '',
			);

			try {
				$item_copy = self::migrate_item_post( $item_id, (string) $item->post_type, $course_copy, $context );

				if ( ! $item_copy ) {
					continue;
				}

				self::report(
					array(
						self::QUIZ        => Report::GROUP_QUIZZES,
						self::ASSIGNMENT  => Report::GROUP_ASSIGNMENTS,
						self::ZOOM        => Report::GROUP_MEETINGS,
						self::GOOGLE_MEET => Report::GROUP_MEETINGS,
					)[ $item->post_type ] ?? Report::GROUP_LESSONS,
					array(
						'source_id' => $item_id,
						'title'     => self::title_of( $item_id ),
						'type'      => self::item_type_label( (string) $item->post_type ),
						'reason'    => __( 'The item is not in the curriculum of any course (its section was deleted or trashed) — it was imported with its status into the MasterStudy library, not into a course.', 'masterstudy-lms-learning-management-system' ),
						'status'    => Report::STATUS_PARTIAL,
						'post_id'   => $item_copy,
					),
					$context
				);
			} catch ( \Throwable $e ) {
				self::report(
					Report::GROUP_LESSONS,
					array(
						'source_id' => $item_id,
						'title'     => self::title_of( $item_id ),
						'type'      => self::item_type_label( (string) $item->post_type ),
						'reason'    => $e->getMessage(),
						'status'    => Report::STATUS_FAILED,
						'post_id'   => $item_id,
					),
					$context
				);
			}
		}

		// Question-bank questions not used by any copied quiz (bank-only, or of a deleted quiz). Questions of a copied
		// quiz were handled (and, when unsupported, reported) with that quiz.
		$questions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_parent FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('trash', 'auto-draft') ORDER BY ID ASC",
				self::QUESTION
			)
		);

		$rel_table = $wpdb->prefix . 'masteriyo_quiz_question_rel';
		$linked    = array();

		if ( self::table_exists( $rel_table ) ) {
			foreach ( (array) $wpdb->get_results( "SELECT quiz_id, question_id FROM {$rel_table}" ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$linked[ (int) $row->question_id ][] = (int) $row->quiz_id;
			}
		}

		foreach ( (array) $questions as $question ) {
			$question_id = (int) $question->ID;
			$context     = array( 'parent' => __( 'Question bank', 'masterstudy-lms-learning-management-system' ) );
			$quiz_ids    = array_merge( array( (int) $question->post_parent ), $linked[ $question_id ] ?? array() );

			// Used by a copied quiz: handled with that quiz. A re-run reuses the copy of an orphan question.
			if ( ! empty( Target::copies_of( self::SOURCE, array_filter( $quiz_ids ) ) ) ) {
				continue;
			}

			try {
				$question_copy = self::process_question_migration( $question_id, $context );

				if ( ! $question_copy ) {
					continue;
				}

				self::report(
					Report::GROUP_QUESTIONS,
					array(
						'source_id' => $question_id,
						'title'     => self::title_of( $question_id ),
						'type'      => self::question_type_label( (string) get_post_meta( $question_id, '_type', true ) ),
						'reason'    => (int) $question->post_parent
							? __( 'The question belongs to a quiz that no longer exists — it was imported into the MasterStudy question library only.', 'masterstudy-lms-learning-management-system' )
							: __( 'The question-bank question is not used by any quiz — it was imported into the MasterStudy question library only.', 'masterstudy-lms-learning-management-system' ),
						'status'    => Report::STATUS_PARTIAL,
						'post_id'   => $question_copy,
					),
					$context
				);
			} catch ( \Throwable $e ) {
				self::report(
					Report::GROUP_QUESTIONS,
					array(
						'source_id' => $question_id,
						'title'     => self::title_of( $question_id ),
						'reason'    => $e->getMessage(),
						'status'    => Report::STATUS_FAILED,
						'post_id'   => $question_id,
					),
					$context
				);
			}
		}
	}

	/**
	 * Masteriyo records MasterStudy cannot import at all, reported once per kind with their count.
	 */
	private static function report_unsupported_records(): void {
		global $wpdb;

		$kinds = array(
			'mto-earning'      => array( __( 'Instructor earnings', 'masterstudy-lms-learning-management-system' ), /* translators: %d: number of records */ __( 'Revenue-sharing earning records have no MasterStudy equivalent (MasterStudy Pro calculates instructor payouts from its own orders) — %d record(s) were not imported.', 'masterstudy-lms-learning-management-system' ) ),
			'mto-withdraw'     => array( __( 'Withdrawal requests', 'masterstudy-lms-learning-management-system' ), /* translators: %d: number of records */ __( 'Instructor withdrawal requests have no MasterStudy equivalent — %d request(s) were not imported; settle open requests before switching.', 'masterstudy-lms-learning-management-system' ) ),
			'mto-price-zone'   => array( __( 'Price zones', 'masterstudy-lms-learning-management-system' ), /* translators: %d: number of records */ __( 'Multiple-currency price zones have no MasterStudy equivalent — %d zone(s) were not imported; courses keep their base price.', 'masterstudy-lms-learning-management-system' ) ),
			'mto-subscription' => array( __( 'Gateway subscriptions', 'masterstudy-lms-learning-management-system' ), /* translators: %d: number of records */ __( 'Payment-gateway subscriptions cannot be moved to another plugin — %d subscription record(s) were not imported; subscribers keep their enrollment but must re-subscribe.', 'masterstudy-lms-learning-management-system' ) ),
			'mto-webhook'      => array( __( 'Webhooks', 'masterstudy-lms-learning-management-system' ), /* translators: %d: number of records */ __( 'Masteriyo webhooks have no MasterStudy equivalent — %d webhook(s) were not imported.', 'masterstudy-lms-learning-management-system' ) ),
			'mto-grade'        => array( __( 'Gradebook grades', 'masterstudy-lms-learning-management-system' ), /* translators: %d: number of records */ __( 'Gradebook grade definitions have no importable MasterStudy equivalent — %d grade(s) were not imported.', 'masterstudy-lms-learning-management-system' ) ),
		);

		foreach ( $kinds as $post_type => $labels ) {
			$count = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('trash', 'auto-draft')", $post_type )
			);

			if ( ! $count ) {
				continue;
			}

			Report::add(
				Report::GROUP_OTHER,
				array(
					'source_id' => $post_type,
					'title'     => $labels[0],
					'type'      => $post_type,
					'reason'    => sprintf( $labels[1], $count ),
					'status'    => Report::STATUS_UNSUPPORTED,
				)
			);
		}
	}

	/**
	 * Masteriyo course bundles (mto-bundle) → MasterStudy course bundles (Pro). Idempotent per bundle.
	 */
	private static function migrate_bundles(): void {
		foreach ( self::source_post_ids( self::BUNDLE ) as $bundle_id ) {
			$bundle = get_post( $bundle_id );
			$report = array(
				'source_id' => $bundle_id,
				'title'     => $bundle ? $bundle->post_title : '',
				'type'      => __( 'Course bundle', 'masterstudy-lms-learning-management-system' ),
				'post_id'   => $bundle_id,
			);

			if ( ! ProTarget::pro_active() ) {
				Report::add(
					Report::GROUP_COURSES,
					$report + array(
						'reason' => __( 'Course bundles require MasterStudy LMS Pro — the bundle was not imported.', 'masterstudy-lms-learning-management-system' ),
						'status' => Report::STATUS_UNSUPPORTED,
					)
				);
				continue;
			}

			try {
				$course_ids = array();

				foreach ( array( '_course_ids', '_courses', '_bundle_courses', 'course_ids' ) as $key ) {
					$course_ids = self::id_list( get_post_meta( $bundle_id, $key, true ) );

					if ( ! empty( $course_ids ) ) {
						break;
					}
				}

				$migrated = self::course_copy_ids( $course_ids );

				if ( empty( $migrated ) ) {
					Report::add(
						Report::GROUP_COURSES,
						$report + array(
							'reason' => __( 'The bundle has no courses that were migrated to MasterStudy, so it was not imported.', 'masterstudy-lms-learning-management-system' ),
							'status' => Report::STATUS_UNSUPPORTED,
						)
					);
					continue;
				}

				$regular = (float) self::first_meta( $bundle_id, array( '_regular_price', '_price' ) );
				$sale    = (float) self::first_meta( $bundle_id, array( '_sale_price' ) );
				$status  = $bundle ? $bundle->post_status : 'draft';

				ProTarget::create_bundle(
					array(
						'source_id'    => $bundle_id,
						'title'        => $bundle ? $bundle->post_title : '',
						'content'      => $bundle ? $bundle->post_content : '',
						'author'       => $bundle ? (int) $bundle->post_author : 0,
						'price'        => $sale > 0 && $sale < $regular ? $sale : $regular,
						'course_ids'   => $migrated,
						'thumbnail_id' => (int) get_post_thumbnail_id( $bundle_id ),
						'status'       => in_array( $status, array( 'publish', 'private' ), true ) ? $status : 'draft',
					),
					self::SOURCE
				);

				$lost = array();

				if ( $sale > 0 && $sale < $regular ) {
					/* translators: %s: sale price */
					$lost[] = sprintf( __( 'MasterStudy bundles have a single price — the sale price (%s) was used', 'masterstudy-lms-learning-management-system' ), $sale );
				}

				if ( count( $migrated ) < count( $course_ids ) ) {
					/* translators: %d: number of courses */
					$lost[] = sprintf( __( '%d bundled course(s) were not migrated and were left out', 'masterstudy-lms-learning-management-system' ), count( $course_ids ) - count( $migrated ) );
				}

				if ( ! empty( $lost ) ) {
					Report::add(
						Report::GROUP_COURSES,
						$report + array(
							'reason' => ucfirst( implode( '; ', $lost ) ) . '.',
							'status' => Report::STATUS_PARTIAL,
						)
					);
				}
			} catch ( \Throwable $e ) {
				Report::add(
					Report::GROUP_COURSES,
					$report + array(
						'reason' => $e->getMessage(),
						'status' => Report::STATUS_FAILED,
					)
				);
			}
		}
	}

	/**
	 * Masteriyo coupons (mto-coupon) → MasterStudy coupons (Pro Plus). Idempotent by coupon code.
	 */
	private static function migrate_coupons(): void {
		$created = false;

		foreach ( self::source_post_ids( self::COUPON ) as $coupon_id ) {
			$coupon = get_post( $coupon_id );
			$code   = $coupon ? trim( (string) $coupon->post_title ) : '';
			$code   = '' !== $code ? $code : self::first_meta( $coupon_id, array( '_code' ) );
			$report = array(
				'source_id' => $coupon_id,
				'title'     => $code,
				'type'      => __( 'Coupon', 'masterstudy-lms-learning-management-system' ),
			);

			if ( ! ProTarget::plus_active() ) {
				Report::add(
					Report::GROUP_OTHER,
					$report + array(
						'reason' => __( 'Coupons require MasterStudy LMS Pro Plus — the coupon was not imported.', 'masterstudy-lms-learning-management-system' ),
						'status' => Report::STATUS_UNSUPPORTED,
					)
				);
				continue;
			}

			if ( '' === $code ) {
				Report::add(
					Report::GROUP_OTHER,
					$report + array(
						'reason' => __( 'The coupon has no code, so it was not imported.', 'masterstudy-lms-learning-management-system' ),
						'status' => Report::STATUS_UNSUPPORTED,
					)
				);
				continue;
			}

			$course_ids = array();

			foreach ( array( '_course_ids', '_applies_to_course_ids', '_courses' ) as $key ) {
				$course_ids = self::id_list( get_post_meta( $coupon_id, $key, true ) );

				if ( ! empty( $course_ids ) ) {
					break;
				}
			}

			$restricted = ! empty( $course_ids );
			$course_ids = self::course_copy_ids( $course_ids );

			if ( $restricted && empty( $course_ids ) ) {
				Report::add(
					Report::GROUP_OTHER,
					$report + array(
						'reason' => __( 'The coupon only applies to courses that were not migrated, so it was not imported.', 'masterstudy-lms-learning-management-system' ),
						'status' => Report::STATUS_UNSUPPORTED,
					)
				);
				continue;
			}

			$type = strtolower( self::first_meta( $coupon_id, array( '_discount_type', '_type' ) ) );

			$row_id = ProTarget::create_coupon(
				array(
					'code'             => $code,
					'title'            => $code,
					'type'             => false !== strpos( $type, 'percent' ) ? 'percent' : 'amount',
					'amount'           => (float) self::first_meta( $coupon_id, array( '_discount_amount', '_amount', '_discount' ) ),
					'status'           => $coupon && in_array( $coupon->post_status, array( 'publish', 'active' ), true ) ? 'active' : 'inactive',
					'usage_limit'      => absint( self::first_meta( $coupon_id, array( '_usage_limit_per_coupon', '_usage_limit' ) ) ),
					'user_usage_limit' => absint( self::first_meta( $coupon_id, array( '_usage_limit_per_user' ) ) ),
					'used_count'       => absint( self::first_meta( $coupon_id, array( '_usage_count', '_used_count' ) ) ),
					'min_amount'       => (float) self::first_meta( $coupon_id, array( '_minimum_amount', '_min_amount' ) ),
					'start'            => self::date_meta_timestamp( $coupon_id, array( '_start_at', '_starts_at', '_start_date' ) ),
					'end'              => self::date_meta_timestamp( $coupon_id, array( '_expire_at', '_expires_at', '_end_at', '_expiry_date' ) ),
					'course_ids'       => $course_ids,
				)
			);

			$created = $created || $row_id > 0;
		}

		// Coupons only apply at checkout once the coupon setting is on.
		if ( $created ) {
			$settings = get_option( 'stm_lms_settings', array() );

			if ( is_array( $settings ) && empty( $settings['enable_coupon_code'] ) ) {
				$settings['enable_coupon_code'] = true;
				update_option( 'stm_lms_settings', $settings );
			}
		}
	}

	/**
	 * Masteriyo group courses (mto-group) → MasterStudy enterprise groups (Pro). Members are matched
	 * to existing WordPress users by email — no users are created and no emails are sent.
	 */
	private static function migrate_groups(): void {
		foreach ( self::source_post_ids( self::GROUP ) as $group_id ) {
			$group  = get_post( $group_id );
			$report = array(
				'source_id' => $group_id,
				'title'     => $group ? $group->post_title : '',
				'type'      => __( 'Masteriyo group', 'masterstudy-lms-learning-management-system' ),
			);

			if ( ! ProTarget::pro_active() ) {
				Report::add(
					Report::GROUP_ENROLLMENTS,
					$report + array(
						'reason' => __( 'Group courses require MasterStudy LMS Pro — the group was not imported (its members keep their individual enrollments).', 'masterstudy-lms-learning-management-system' ),
						'status' => Report::STATUS_UNSUPPORTED,
					)
				);
				continue;
			}

			// Masteriyo only grants a group access while it is published (its order completed); a draft group's
			// member enrollments are inactive, so it must not enroll anybody in MasterStudy.
			if ( $group && 'publish' !== $group->post_status ) {
				Report::add(
					Report::GROUP_ENROLLMENTS,
					$report + array(
						/* translators: %s: post status */
						'reason' => sprintf( __( 'The group was not active in Masteriyo (status "%s", its order is not completed), so it was not imported and its members were not enrolled.', 'masterstudy-lms-learning-management-system' ), $group->post_status ),
						'status' => Report::STATUS_UNSUPPORTED,
					)
				);
				continue;
			}

			try {
				$emails  = maybe_unserialize( get_post_meta( $group_id, '_emails', true ) );
				$emails  = is_array( $emails ) ? $emails : array_filter( array_map( 'trim', explode( ',', (string) $emails ) ) );
				$members = array();
				$missing = 0;

				foreach ( $emails as $email ) {
					$user = is_string( $email ) ? get_user_by( 'email', sanitize_email( $email ) ) : false;

					if ( $user ) {
						$members[] = (int) $user->ID;
					} else {
						++$missing;
					}
				}

				$course_ids = array();
				$inactive   = 0;

				foreach ( (array) maybe_unserialize( get_post_meta( $group_id, 'masteriyo_course_data', true ) ) as $data ) {
					$course_id = is_array( $data ) ? absint( $data['course_id'] ?? 0 ) : absint( $data );
					$course_id = $course_id ? Target::copy_of( self::SOURCE, $course_id ) : 0;

					if ( ! $course_id || PostType::COURSE !== get_post_type( $course_id ) ) {
						continue;
					}

					// Groups whose order is not completed do not grant access in Masteriyo either.
					if ( is_array( $data ) && 'inactive' === ( $data['enrolled_status'] ?? '' ) ) {
						++$inactive;
						continue;
					}

					$course_ids[] = $course_id;
				}

				ProTarget::create_group(
					array(
						'source_id'  => $group_id,
						'title'      => $group ? $group->post_title : '',
						'admin_id'   => $group ? (int) $group->post_author : 0,
						'member_ids' => $members,
						'course_ids' => $course_ids,
					),
					self::SOURCE
				);

				$lost = array();

				if ( $missing ) {
					/* translators: %d: number of emails */
					$lost[] = sprintf( __( '%d member email(s) have no WordPress account and were not added to the group', 'masterstudy-lms-learning-management-system' ), $missing );
				}

				if ( $inactive ) {
					/* translators: %d: number of courses */
					$lost[] = sprintf( __( '%d group course(s) with an unpaid order were not linked to the group', 'masterstudy-lms-learning-management-system' ), $inactive );
				}

				if ( ! empty( $lost ) ) {
					Report::add(
						Report::GROUP_ENROLLMENTS,
						$report + array(
							'reason' => ucfirst( implode( '; ', $lost ) ) . '.',
							'status' => Report::STATUS_PARTIAL,
						)
					);
				}
			} catch ( \Throwable $e ) {
				Report::add(
					Report::GROUP_ENROLLMENTS,
					$report + array(
						'reason' => $e->getMessage(),
						'status' => Report::STATUS_FAILED,
					)
				);
			}
		}
	}

	/**
	 * IDs of source posts of a type (non-trashed ones unless $all_statuses).
	 *
	 * @param string $post_type    Post type.
	 * @param bool   $all_statuses Include trashed and auto-draft posts.
	 * @return int[]
	 */
	private static function source_post_ids( string $post_type, bool $all_statuses = false ): array {
		global $wpdb;

		$status_sql = $all_statuses ? '' : " AND post_status NOT IN ('trash', 'auto-draft')";

		return array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s{$status_sql} ORDER BY ID ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$post_type
				)
			)
		);
	}

	/**
	 * MasterStudy course copies of Masteriyo course IDs (courses that were not copied are dropped).
	 *
	 * @param int[] $course_ids Masteriyo course IDs.
	 * @return int[]
	 */
	private static function course_copy_ids( array $course_ids ): array {
		$copies = array();

		foreach ( $course_ids as $course_id ) {
			$copy_id = self::COURSE === get_post_type( (int) $course_id ) ? Target::copy_of( self::SOURCE, (int) $course_id ) : 0;

			if ( $copy_id && PostType::COURSE === get_post_type( $copy_id ) ) {
				$copies[] = $copy_id;
			}
		}

		return array_values( array_unique( $copies ) );
	}

	/**
	 * Curriculum post IDs of a MasterStudy course, in curriculum order.
	 *
	 * @param int $course_id Course ID.
	 * @return int[]
	 */
	private static function course_material_ids( int $course_id ): array {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT m.post_id FROM {$wpdb->prefix}stm_lms_curriculum_materials m
					 INNER JOIN {$wpdb->prefix}stm_lms_curriculum_sections s ON s.id = m.section_id
					 WHERE s.course_id = %d
					 ORDER BY s.`order` ASC, m.`order` ASC",
					$course_id
				)
			)
		);
		// phpcs:enable
	}

	/**
	 * Active Masteriyo enrollments of a course.
	 *
	 * @param int $course_id Course ID.
	 */
	private static function active_enrollment_count( int $course_id ): int {
		global $wpdb;

		if ( ! self::table_exists( $wpdb->prefix . 'masteriyo_user_items' ) ) {
			return 0;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}masteriyo_user_items WHERE item_type = 'user_course' AND item_id = %d AND status = 'active'",
				$course_id
			)
		);
	}

	/**
	 * Post/user IDs from a Masteriyo value: int list, list of ['id' => …]/['value' => …], JSON or a comma list.
	 *
	 * @param mixed $value Value.
	 * @return int[]
	 */
	private static function id_list( $value ): array {
		$value = maybe_unserialize( $value );

		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			$value   = is_array( $decoded ) ? $decoded : explode( ',', $value );
		}

		$ids = array();

		foreach ( (array) $value as $item ) {
			if ( is_object( $item ) ) {
				$item = (array) $item;
			}

			$ids[] = is_array( $item ) ? absint( $item['id'] ?? $item['value'] ?? 0 ) : absint( is_scalar( $item ) ? trim( (string) $item ) : 0 );
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Unix time from a site-local date string (Masteriyo drip dates), or a Unix time.
	 *
	 * @param mixed $value Value.
	 */
	private static function local_to_timestamp( $value ): int {
		if ( ! is_scalar( $value ) || '' === trim( (string) $value ) || 0 === strpos( (string) $value, '0000-00-00' ) ) {
			return 0;
		}

		if ( is_numeric( $value ) ) {
			return (int) $value;
		}

		try {
			return ( new \DateTime( (string) $value, wp_timezone() ) )->getTimestamp();
		} catch ( \Exception $e ) {
			return 0;
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Helpers
	|--------------------------------------------------------------------------
	*/

	/**
	 * Masteriyo's own verdict for a finished attempt (masteriyo_get_quiz_effective_pass_mark()): a point pass mark is
	 * scaled to the marks the attempt was graded out of and compared with the earned marks; a percentage pass mark
	 * is compared with the attempt percent. Null when the quiz has no pass mark.
	 *
	 * @param object $attempt        Row of masteriyo_quiz_attempts.
	 * @param int    $source_quiz_id Masteriyo quiz ID.
	 * @param float  $percent        Attempt percent.
	 */
	private static function attempt_passed( $attempt, int $source_quiz_id, float $percent ): ?bool {
		$pass_mark = get_post_meta( $source_quiz_id, '_pass_mark', true );

		if ( '' === (string) $pass_mark || ! is_numeric( $pass_mark ) ) {
			return null;
		}

		if ( 'percentage' === (string) get_post_meta( $source_quiz_id, '_pass_mark_type', true ) ) {
			return $percent + 0.0001 >= (float) $pass_mark;
		}

		$total     = (float) $attempt->total_marks;
		$full_mark = (float) get_post_meta( $source_quiz_id, '_full_mark', true );
		$effective = ( $full_mark > 0 && $total > 0 ) ? round( (float) $pass_mark * $total / $full_mark, 2 ) : (float) $pass_mark;
		$effective = min( max( 0.0, $effective ), max( 0.0, $total ) );

		return (float) $attempt->earned_marks + 0.0001 >= $effective;
	}

	/**
	 * Convert Masteriyo attempt answers to Target::add_quiz_attempt() answers.
	 *
	 * Supported shapes: [qid => ['answered' => mixed, 'correct' => bool]], the legacy
	 * [qid => mixed], and [['id' => qid, 'given_answer' => string, 'is_correct' => bool]]
	 * (attempts Masteriyo migrated from MasterStudy). Masteriyo question IDs are mapped to their MasterStudy
	 * copies; answers to questions that were not copied (not part of the MasterStudy quiz) are dropped.
	 *
	 * @param mixed $answers Unserialized answers.
	 */
	private static function convert_attempt_answers( $answers ): array {
		if ( ! is_array( $answers ) ) {
			return array();
		}

		$result = array();

		foreach ( $answers as $key => $answer ) {
			if ( is_array( $answer ) && isset( $answer['id'], $answer['given_answer'] ) ) {
				// Already in MasterStudy format.
				$question_copy = Target::copy_of( self::SOURCE, (int) $answer['id'] );

				if ( $question_copy ) {
					$result[] = array(
						'question_id' => $question_copy,
						'answer'      => (string) $answer['given_answer'],
						'correct'     => ! empty( $answer['is_correct'] ),
					);
				}

				continue;
			}

			// Questions without a MasterStudy type (text/audio/video answers) were not copied: not part of the quiz.
			$question_id = (int) $key ? Target::copy_of( self::SOURCE, (int) $key ) : 0;

			if ( ! $question_id || '' === (string) get_post_meta( $question_id, 'type', true ) ) {
				continue;
			}

			$given   = is_array( $answer ) && array_key_exists( 'answered', $answer ) ? $answer['answered'] : $answer;
			$correct = is_array( $answer ) && array_key_exists( 'correct', $answer ) ? self::to_bool( $answer['correct'] ) : false;

			$result[] = array(
				'question_id' => $question_id,
				'answer'      => self::format_user_answer( $question_id, $given ),
				'correct'     => $correct,
			);
		}

		return $result;
	}

	/**
	 * MasterStudy `user_answer` string for a Masteriyo given answer (spec §8).
	 *
	 * @param int   $question_id MasterStudy question (copy) ID.
	 * @param mixed $given       Masteriyo given answer.
	 */
	private static function format_user_answer( int $question_id, $given ): string {
		$type    = (string) get_post_meta( $question_id, 'type', true );
		$answers = maybe_unserialize( get_post_meta( $question_id, 'answers', true ) );
		$answers = is_array( $answers ) ? $answers : array();
		$given   = self::to_array( $given );
		$sep     = '[stm_lms_sep]';

		$texts = array();

		foreach ( $given as $value ) {
			$texts[] = is_array( $value ) ? (string) ( $value['name'] ?? '' ) : (string) $value;
		}

		switch ( $type ) {
			case 'multi_choice':
				$chosen = array();

				foreach ( $texts as $text ) {
					$picked   = self::find_answer( $answers, 'text', $text );
					$chosen[] = $picked ? self::answer_with_image( $picked ) : $text;
				}

				return implode( ',', array_map( 'rawurlencode', $chosen ) );

			case 'sortable':
				return '[stm_lms_sortable]' . implode( $sep, $texts );

			case 'keywords':
				return '[stm_lms_keywords]' . implode( $sep, $texts );

			case 'fill_the_gap':
				return implode( ',', $texts );

			case 'item_match':
			case 'image_match':
				// Given rows: {prompt, match} (text / image-to-text) or {imagePrompt, imageMatch} (image-to-image).
				$by_text  = array();
				$by_image = array();

				foreach ( $given as $value ) {
					if ( ! is_array( $value ) ) {
						continue;
					}

					if ( isset( $value['prompt'] ) && ! is_array( $value['prompt'] ) ) {
						$by_text[ (string) $value['prompt'] ] = (string) ( $value['match'] ?? '' );
					} elseif ( isset( $value['imagePrompt'] ) && ! is_array( $value['imagePrompt'] ) ) {
						$by_image[ (int) $value['imagePrompt'] ] = (int) ( $value['imageMatch'] ?? 0 );
					}
				}

				$parts = array();

				foreach ( $answers as $answer ) {
					$prompt       = (string) ( $answer['question'] ?? '' );
					$prompt_image = (int) ( $answer['question_image']['id'] ?? 0 );

					if ( 'item_match' === $type ) {
						$parts[] = $by_text[ $prompt ] ?? '';
						continue;
					}

					// MasterStudy image_match answer per row: "{match text}|{match image url}" of the chosen match.
					$picked = null;
					$chosen = '';

					if ( $prompt_image && isset( $by_image[ $prompt_image ] ) ) {
						$picked = self::find_answer( $answers, 'text_image', $by_image[ $prompt_image ] );
					} elseif ( '' !== $prompt && isset( $by_text[ $prompt ] ) ) {
						$chosen = $by_text[ $prompt ];
						$picked = self::find_answer( $answers, 'text', $chosen );
					}

					$parts[] = $picked ? self::answer_with_image( $picked ) : $chosen;
				}

				return ( 'item_match' === $type ? '[stm_lms_item_match]' : '[stm_lms_image_match]' ) . implode( $sep, $parts );

			default:
				// single_choice / true_false: the chosen answer's text ("text|image url" for image choices).
				$text   = (string) ( $texts[0] ?? '' );
				$picked = self::find_answer( $answers, 'text', $text );

				return $picked ? self::answer_with_image( $picked ) : $text;
		}
	}

	/**
	 * Stored MasterStudy answer row matching a chosen value.
	 *
	 * @param array      $answers MasterStudy `answers` meta.
	 * @param string     $field   'text' (compare the text) or 'text_image' (compare the attachment ID).
	 * @param string|int $value   Chosen text or attachment ID.
	 * @return array|null
	 */
	private static function find_answer( array $answers, string $field, $value ): ?array {
		foreach ( $answers as $candidate ) {
			if ( ! is_array( $candidate ) ) {
				continue;
			}

			if ( 'text_image' === $field ) {
				if ( (int) $value && (int) ( $candidate['text_image']['id'] ?? 0 ) === (int) $value ) {
					return $candidate;
				}

				continue;
			}

			if ( '' !== (string) $value && (string) ( $candidate['text'] ?? '' ) === (string) $value ) {
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * MasterStudy answer string of a stored answer row: "text", or "text|image url" when it has an image.
	 *
	 * @param array $answer Stored answer row.
	 */
	private static function answer_with_image( array $answer ): string {
		$url = (string) ( $answer['text_image']['url'] ?? '' );

		return (string) ( $answer['text'] ?? '' ) . ( '' !== $url ? '|' . $url : '' );
	}

	/**
	 * Configure a MasterStudy audio lesson (Pro Plus audio_lesson addon).
	 *
	 * @param int    $lesson_id Lesson ID.
	 * @param string $source    Masteriyo audio source (self-hosted|external|embed-audio).
	 * @param string $url       Audio URL / embed code / attachment ID.
	 * @param int[]  $files     Self-hosted audio attachment IDs.
	 * @return bool False when MasterStudy Pro Plus is not active or there is no audio to link.
	 */
	private static function set_lesson_audio( int $lesson_id, string $source, string $url, array $files ): bool {
		if ( ! ProTarget::plus_active() ) {
			return false;
		}

		if ( 'embed-audio' === $source && '' !== $url ) {
			ProTarget::set_audio_lesson( $lesson_id, 'embed', $url );
		} elseif ( ! empty( $files ) || ( 'self-hosted' === $source && is_numeric( $url ) ) ) {
			ProTarget::set_audio_lesson( $lesson_id, 'file', ! empty( $files ) ? (int) $files[0] : absint( $url ) );
		} elseif ( '' !== $url && ! is_numeric( $url ) ) {
			ProTarget::set_audio_lesson( $lesson_id, 'ext_link', esc_url_raw( $url ) );
		} else {
			return false;
		}

		return true;
	}

	/**
	 * Write the MasterStudy sale price dates (ms timestamps).
	 *
	 * @param int $course_id Course ID.
	 * @param int $from      Sale start (Unix time, 0 = none).
	 * @param int $to        Sale end (Unix time, 0 = none).
	 */
	private static function set_sale_dates( int $course_id, int $from, int $to ): void {
		if ( $from ) {
			update_post_meta( $course_id, 'sale_price_dates_start', $from * 1000 );
		}

		if ( $to ) {
			update_post_meta( $course_id, 'sale_price_dates_end', $to * 1000 );
		}
	}

	/**
	 * Masteriyo course difficulty name (course_difficulty taxonomy, via _difficulty_id or the term relation).
	 *
	 * @param int $course_id Course ID.
	 */
	private static function course_difficulty( int $course_id ): string {
		global $wpdb;

		$term_id = (int) get_post_meta( $course_id, '_difficulty_id', true );

		if ( $term_id ) {
			$name = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT t.name FROM {$wpdb->terms} t
					 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
					 WHERE t.term_id = %d AND tt.taxonomy = 'course_difficulty'",
					$term_id
				)
			);

			if ( $name ) {
				return (string) $name;
			}
		}

		$names = self::term_names( $course_id, 'course_difficulty' );

		return $names ? (string) $names[0] : '';
	}

	/**
	 * Term names of a (possibly unregistered) taxonomy for a post.
	 *
	 * @param int    $object_id Post ID.
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
	 * Completion time from the Masteriyo course_progress activity, 0 when not completed.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 */
	private static function course_completed_at( int $user_id, int $course_id ): int {
		global $wpdb;

		$tbl = $wpdb->prefix . 'masteriyo_user_activities';

		if ( ! self::table_exists( $tbl ) ) {
			return 0;
		}

		$completed_at = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT completed_at FROM {$tbl}
				 WHERE user_id = %d AND item_id = %d AND activity_type = 'course_progress' AND activity_status = 'completed'
				 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$course_id
			)
		);

		return self::gmt_to_timestamp( $completed_at );
	}

	/**
	 * First lesson of a MasterStudy course curriculum (falls back to any material).
	 *
	 * @param int $course_id Course ID.
	 */
	private static function first_course_lesson( int $course_id ): int {
		global $wpdb;

		$sections  = $wpdb->prefix . 'stm_lms_curriculum_sections';
		$materials = $wpdb->prefix . 'stm_lms_curriculum_materials';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$material = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT m.post_id FROM {$materials} m
				 INNER JOIN {$sections} s ON s.id = m.section_id
				 WHERE s.course_id = %d
				 ORDER BY ( m.post_type = %s ) DESC, s.`order` ASC, m.`order` ASC
				 LIMIT 1",
				$course_id,
				PostType::LESSON
			)
		);
		// phpcs:enable

		return $material;
	}

	/**
	 * Add an item to the migration report, filling missing keys from the context.
	 *
	 * @param string $group   Report group.
	 * @param array  $item    Report item.
	 * @param array  $context Defaults such as parent and course.
	 */
	private static function report( string $group, array $item, array $context = array() ): void {
		Report::add( $group, array_merge( $context, $item ) );
	}

	/**
	 * Raw post title, or "#ID" when the post has no title.
	 *
	 * @param int $post_id Post ID.
	 */
	private static function title_of( int $post_id ): string {
		if ( ! $post_id ) {
			return '';
		}

		$title = (string) get_post_field( 'post_title', $post_id );

		return '' !== $title ? $title : '#' . $post_id;
	}

	/**
	 * Readable user label for the report: "Display name (#ID)".
	 *
	 * @param int $user_id User ID.
	 */
	private static function user_label( int $user_id ): string {
		$user = $user_id ? get_userdata( $user_id ) : false;

		if ( $user ) {
			return sprintf( '%s (#%d)', $user->display_name, $user_id );
		}

		/* translators: %d: user ID */
		return sprintf( __( 'User #%d', 'masterstudy-lms-learning-management-system' ), $user_id );
	}

	/**
	 * Comma-separated user labels.
	 *
	 * @param int[] $user_ids User IDs.
	 */
	private static function user_labels( array $user_ids ): string {
		$labels = array();

		foreach ( $user_ids as $user_id ) {
			$labels[] = self::user_label( (int) $user_id );
		}

		return implode( ', ', $labels );
	}

	/**
	 * Count posts of a type.
	 *
	 * @param string $post_type Post type.
	 */
	private static function count_posts( string $post_type ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", $post_type )
		);
	}

	/**
	 * Cursor-based batch of post IDs of a type (all statuses).
	 *
	 * @param string $post_type Post type.
	 * @param int    $limit     Batch size.
	 * @param int    $cursor    Last processed ID.
	 * @return int[]
	 */
	private static function post_ids( string $post_type, int $limit, int $cursor ): array {
		global $wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				 WHERE post_type = %s AND ID > %d
				 ORDER BY ID ASC
				 LIMIT %d",
				$post_type,
				$cursor,
				$limit
			)
		);

		return array_map( 'intval', $ids ? $ids : array() );
	}

	/**
	 * Whether a DB table exists (Masteriyo tables are gone once the plugin is uninstalled).
	 *
	 * @param string $table Full table name.
	 */
	private static function table_exists( string $table ): bool {
		static $cache = array();

		if ( ! isset( $cache[ $table ] ) ) {
			global $wpdb;

			$cache[ $table ] = $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		return $cache[ $table ];
	}

	/**
	 * First non-empty meta value among candidate keys.
	 *
	 * @param int      $post_id Post ID.
	 * @param string[] $keys    Meta keys.
	 */
	private static function first_meta( int $post_id, array $keys ): string {
		foreach ( $keys as $key ) {
			$value = get_post_meta( $post_id, $key, true );

			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				return trim( (string) $value );
			}
		}

		return '';
	}

	/**
	 * Attachment IDs from a Masteriyo value (int list, or list of ['id' => …] items).
	 *
	 * @param mixed $value Value.
	 * @return int[]
	 */
	private static function attachment_ids( $value ): array {
		if ( is_string( $value ) && '' !== $value ) {
			$decoded = json_decode( $value, true );
			$value   = is_array( $decoded ) ? $decoded : array( $value );
		}

		$ids = array();

		foreach ( (array) $value as $item ) {
			if ( is_object( $item ) ) {
				$item = (array) $item;
			}

			$ids[] = is_array( $item ) ? absint( $item['id'] ?? 0 ) : absint( $item );
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Unix time from a Masteriyo meta value (Unix time or date string).
	 *
	 * @param mixed $value Value.
	 */
	private static function to_timestamp( $value ): int {
		if ( is_object( $value ) && method_exists( $value, 'getTimestamp' ) ) {
			return (int) $value->getTimestamp();
		}

		if ( ! is_scalar( $value ) || '' === trim( (string) $value ) || 0 === strpos( (string) $value, '0000-00-00' ) ) {
			return 0;
		}

		if ( is_numeric( $value ) ) {
			return (int) $value;
		}

		$time = strtotime( (string) $value );

		return false === $time ? 0 : (int) $time;
	}

	/**
	 * Unix time of the first non-empty date meta among candidate keys.
	 *
	 * Masteriyo date props (sale dates, created_at, …) are stored as serialized Masteriyo\DateTime
	 * objects; unserialized without that class (Masteriyo inactive) they become incomplete objects
	 * and the time is lost, so the raw meta value is parsed instead of get_post_meta().
	 *
	 * @param int      $post_id  Post ID.
	 * @param string[] $keys     Meta keys.
	 * @param string   $timezone Timezone of date strings without an offset (default: UTC).
	 */
	private static function date_meta_timestamp( int $post_id, array $keys, string $timezone = '' ): int {
		global $wpdb;

		foreach ( $keys as $key ) {
			$raw = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s ORDER BY meta_id ASC LIMIT 1",
					$post_id,
					$key
				)
			);

			$time = self::parse_date_value( $raw, $timezone );

			if ( $time ) {
				return $time;
			}
		}

		return 0;
	}

	/**
	 * Unix time from a raw Masteriyo date value: serialized (Masteriyo\)DateTime, Unix time or date string.
	 *
	 * @param mixed  $raw      Raw meta value.
	 * @param string $timezone Timezone of date strings without an offset (default: UTC).
	 */
	private static function parse_date_value( $raw, string $timezone = '' ): int {
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return 0;
		}

		if ( is_serialized( $raw ) ) {
			if ( preg_match( '/^O:\d+:"([^"]+)"/', $raw, $class ) ) {
				if ( in_array( $class[1], array( 'Masteriyo\DateTime', 'DateTime', 'DateTimeImmutable' ), true ) && class_exists( $class[1] ) ) {
					$value = @unserialize( $raw, array( 'allowed_classes' => array( $class[1] ) ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize

					if ( $value instanceof \DateTimeInterface ) {
						return (int) $value->getTimestamp();
					}
				}

				// DateTime payload: s:4:"date";s:26:"2026-09-17 18:36:52.000000";…s:8:"timezone";s:6:"+00:00".
				if ( preg_match( '/s:4:"date";s:\d+:"([^"]+)"/', $raw, $date ) ) {
					$zone = preg_match( '/s:8:"timezone";s:\d+:"([^"]+)"/', $raw, $tz ) ? $tz[1] : 'UTC';

					return self::date_string_to_timestamp( $date[1], $zone );
				}

				return 0;
			}

			$raw = maybe_unserialize( $raw );

			if ( ! is_scalar( $raw ) ) {
				return 0;
			}

			$raw = (string) $raw;
		}

		if ( is_numeric( $raw ) ) {
			return (int) $raw;
		}

		return '' !== $timezone ? self::date_string_to_timestamp( $raw, $timezone ) : self::to_timestamp( $raw );
	}

	/**
	 * Unix time of a date string; the timezone only applies when the string carries no offset itself.
	 *
	 * @param string $date     Date string.
	 * @param string $timezone Timezone name or offset.
	 */
	private static function date_string_to_timestamp( string $date, string $timezone ): int {
		if ( '' === trim( $date ) || 0 === strpos( $date, '0000-00-00' ) ) {
			return 0;
		}

		try {
			$zone = new \DateTimeZone( '' !== $timezone ? $timezone : 'UTC' );
		} catch ( \Exception $e ) {
			$zone = new \DateTimeZone( 'UTC' );
		}

		try {
			return ( new \DateTime( $date, $zone ) )->getTimestamp();
		} catch ( \Exception $e ) {
			return self::to_timestamp( $date );
		}
	}

	/**
	 * Unix time from a Masteriyo table datetime (stored in UTC).
	 *
	 * @param mixed $value MySQL datetime.
	 */
	private static function gmt_to_timestamp( $value ): int {
		if ( ! is_string( $value ) || '' === $value || 0 === strpos( $value, '0000-00-00' ) ) {
			return 0;
		}

		$time = strtotime( $value . ' UTC' );

		return false === $time ? 0 : (int) $time;
	}

	/**
	 * Masteriyo boolean meta (true/'1'/'yes'/'on') to bool.
	 *
	 * @param mixed $value Value.
	 */
	private static function to_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		return in_array( strtolower( trim( (string) $value ) ), array( '1', 'yes', 'on', 'true' ), true );
	}

	/**
	 * Cast a given answer to a list.
	 *
	 * @param mixed $value Value.
	 */
	private static function to_array( $value ): array {
		if ( is_object( $value ) ) {
			$value = json_decode( wp_json_encode( $value ), true );
		}

		if ( is_array( $value ) ) {
			// A single matching/sortable object is itself an associative array.
			return isset( $value['prompt'] ) || isset( $value['imagePrompt'] ) || isset( $value['name'] ) ? array( $value ) : array_values( $value );
		}

		return '' === (string) $value ? array() : array( $value );
	}

	/**
	 * Normalized plain text for announcement de-duplication.
	 *
	 * @param string $html HTML.
	 */
	private static function plain_text( string $html ): string {
		$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' );

		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Human-readable duration for lessons ("m:ss" / "h:mm:ss").
	 *
	 * @param int $seconds Seconds.
	 */
	private static function format_seconds( int $seconds ): string {
		if ( $seconds >= HOUR_IN_SECONDS ) {
			return sprintf( '%d:%02d:%02d', intdiv( $seconds, HOUR_IN_SECONDS ), intdiv( $seconds % HOUR_IN_SECONDS, 60 ), $seconds % 60 );
		}

		return sprintf( '%d:%02d', intdiv( $seconds, 60 ), $seconds % 60 );
	}

	/**
	 * Human-readable course duration from minutes.
	 *
	 * @param int $minutes Minutes.
	 */
	private static function format_minutes( int $minutes ): string {
		$hours = intdiv( $minutes, 60 );
		$rest  = $minutes % 60;

		if ( ! $hours ) {
			/* translators: %d: minutes */
			return sprintf( _n( '%d minute', '%d minutes', $rest, 'masterstudy-lms-learning-management-system' ), $rest );
		}

		/* translators: %d: hours */
		$text = sprintf( _n( '%d hour', '%d hours', $hours, 'masterstudy-lms-learning-management-system' ), $hours );

		if ( $rest ) {
			/* translators: %d: minutes */
			$text .= ' ' . sprintf( _n( '%d minute', '%d minutes', $rest, 'masterstudy-lms-learning-management-system' ), $rest );
		}

		return $text;
	}
}
