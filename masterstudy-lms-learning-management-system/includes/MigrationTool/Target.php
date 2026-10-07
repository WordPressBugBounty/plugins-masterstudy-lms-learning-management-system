<?php
// phpcs:ignoreFile

namespace MasterStudy\Lms\MigrationTool;

defined( 'ABSPATH' ) || exit;

use MasterStudy\Lms\Enums\PricingMode;
use MasterStudy\Lms\Plugin\PostType;
use MasterStudy\Lms\Plugin\Taxonomy;
use MasterStudy\Lms\Repositories\CurriculumMaterialRepository;
use MasterStudy\Lms\Repositories\CurriculumSectionRepository;

/**
 * Shared write helpers for the MasterStudy data model.
 *
 * Every source LMS class (LMS/*.php) reads its own data and writes MasterStudy data
 * exclusively through these helpers, so storage formats live in one place.
 *
 * All helpers are idempotent where the underlying storage allows it and never send
 * emails (see suppress_side_effects()).
 */
class Target {

	/**
	 * Post meta holding the source LMS slug on every converted/created post.
	 */
	const SOURCE_META = '_masterstudy_migrated_from';

	/**
	 * Post meta holding the original source post type / id on converted/created posts.
	 */
	const SOURCE_TYPE_META = '_masterstudy_migrated_source_type';
	const SOURCE_ID_META   = '_masterstudy_migrated_source_id';

	const INSTRUCTOR_ROLE = 'stm_lms_instructor';

	/**
	 * Attachment meta: the migration created this file (a copy), so deleting the migrated data may delete it.
	 * Attachments without it point at a file the source LMS still uses.
	 */
	const OWN_FILE_META = '_masterstudy_migrated_own_file';

	/**
	 * Disable MasterStudy emails for the rest of the request. Called once per job run.
	 */
	public static function suppress_side_effects(): void {
		static $done = false;

		if ( $done ) {
			return;
		}

		$done = true;

		add_filter(
			'stm_lms_filter_email_data',
			function ( $data ) {
				return array( 'enabled' => false ) + (array) $data;
			},
			PHP_INT_MAX
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Posts
	|--------------------------------------------------------------------------
	*/

	/**
	 * Create a new MasterStudy post (for source data that is not a post, e.g. table rows).
	 *
	 * @param array  $postarr   wp_insert_post() args; post_type is required.
	 * @param string $source    Source LMS slug.
	 * @param string $source_id Source record reference, used for idempotency lookups.
	 */
	public static function insert_post( array $postarr, string $source, string $source_id = '' ): int {
		if ( '' !== $source_id ) {
			$existing = static::find_migrated_post( $postarr['post_type'], $source, $source_id );

			if ( $existing ) {
				return $existing;
			}
		}

		$post_id = static::insert_unfiltered( wp_slash( $postarr ) );

		if ( is_wp_error( $post_id ) ) {
			throw new \Exception( $post_id->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		update_post_meta( $post_id, self::SOURCE_META, $source );

		if ( '' !== $source_id ) {
			update_post_meta( $post_id, self::SOURCE_ID_META, $source_id );
		}

		return (int) $post_id;
	}

	/**
	 * Post previously created by insert_post() for the same source record.
	 */
	public static function find_migrated_post( string $post_type, string $source, string $source_id ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = %s AND s.meta_value = %s
				 INNER JOIN {$wpdb->postmeta} i ON i.post_id = p.ID AND i.meta_key = %s AND i.meta_value = %s
				 WHERE p.post_type = %s LIMIT 1",
				self::SOURCE_META,
				$source,
				self::SOURCE_ID_META,
				$source_id,
				$post_type
			)
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Copies (copy mode: source content is never modified)
	|--------------------------------------------------------------------------
	*/

	/**
	 * `_masterstudy_migrated_source_id` value of a post copied from a source post.
	 */
	const COPY_KEY_PREFIX = 'post-';

	/**
	 * Source slug => array( source post ID => copy post ID ), loaded once per source and request.
	 *
	 * @var array<string, array<int, int>>
	 */
	private static $copies = array();

	/**
	 * Copy a source post into a NEW MasterStudy post. The source post, its meta, comments and terms are
	 * never modified. Idempotent: an existing copy of the same source post is returned unchanged.
	 *
	 * Copied: title, content, excerpt, status (auto-draft/inherit become draft), author, dates, slug,
	 * menu order, comment/ping status, password and the featured image. Everything else (meta, curriculum,
	 * terms) is written to the returned copy ID by the caller.
	 *
	 * @param int    $source_post_id Source post ID.
	 * @param string $post_type      MasterStudy post type.
	 * @param string $source         Source LMS slug.
	 * @param array  $overrides      wp_insert_post() fields that replace the copied ones (e.g. post_status).
	 * @return int Copy post ID.
	 */
	public static function copy_post( int $source_post_id, string $post_type, string $source, array $overrides = array() ): int {
		$existing = static::copy_of( $source, $source_post_id );

		if ( $existing ) {
			return $existing;
		}

		$post = get_post( $source_post_id );

		if ( ! $post ) {
			throw new \Exception( sprintf( 'copy_post: source post %d not found.', $source_post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$status = in_array( $post->post_status, array( 'auto-draft', 'inherit' ), true ) ? 'draft' : $post->post_status;

		$postarr = array_merge(
			array(
				'post_type'      => $post_type,
				'post_title'     => $post->post_title,
				'post_content'   => $post->post_content,
				'post_excerpt'   => $post->post_excerpt,
				'post_status'    => $status,
				'post_author'    => (int) $post->post_author,
				'post_date'      => $post->post_date,
				'post_date_gmt'  => $post->post_date_gmt,
				'post_name'      => $post->post_name,
				'menu_order'     => (int) $post->menu_order,
				'comment_status' => $post->comment_status,
				'ping_status'    => $post->ping_status,
				'post_password'  => $post->post_password,
				'post_parent'    => 0,
			),
			$overrides
		);

		$copy_id = static::insert_unfiltered( wp_slash( $postarr ) );

		if ( is_wp_error( $copy_id ) ) {
			throw new \Exception( $copy_id->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$copy_id = (int) $copy_id;

		update_post_meta( $copy_id, self::SOURCE_META, $source );
		update_post_meta( $copy_id, self::SOURCE_ID_META, self::COPY_KEY_PREFIX . $source_post_id );
		update_post_meta( $copy_id, self::SOURCE_TYPE_META, $post->post_type );

		$thumbnail = (int) get_post_meta( $source_post_id, '_thumbnail_id', true );

		if ( $thumbnail && ! array_key_exists( '_thumbnail_id', $overrides ) ) {
			update_post_meta( $copy_id, '_thumbnail_id', $thumbnail );
		}

		static::copies( $source );
		static::$copies[ $source ][ $source_post_id ] = $copy_id;

		return $copy_id;
	}

	/**
	 * wp_insert_post() without the HTML filter: the background job runs without a user, so kses would strip
	 * iframes / scripts / embeds from content that the source LMS already stored.
	 *
	 * @param array $postarr Slashed wp_insert_post() args.
	 * @return int|\WP_Error
	 */
	public static function insert_unfiltered( array $postarr ) {
		$kses = false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );

		if ( $kses ) {
			kses_remove_filters();
		}

		try {
			return wp_insert_post( $postarr, true );
		} finally {
			if ( $kses ) {
				kses_init_filters();
			}
		}
	}

	/**
	 * wp_update_post() of a MasterStudy post without the HTML filter (see insert_unfiltered()).
	 *
	 * @param array $postarr Slashed wp_update_post() args with ID.
	 * @return int|\WP_Error
	 */
	public static function update_unfiltered( array $postarr ) {
		$kses = false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );

		if ( $kses ) {
			kses_remove_filters();
		}

		try {
			return wp_update_post( $postarr, true );
		} finally {
			if ( $kses ) {
				kses_init_filters();
			}
		}
	}

	/**
	 * Attachment for a file of the source LMS, backed by its OWN copy of the file (same folder, "-ms" suffix):
	 * deleting the MasterStudy attachment (or the migrated data) never deletes a file the source still uses.
	 * Idempotent through $source_key.
	 *
	 * @param string $path       Absolute path of the source file (must be inside uploads).
	 * @param array  $postarr    wp_insert_attachment() fields (post_mime_type, post_title, post_author, post_parent…).
	 * @param string $source     Source LMS slug.
	 * @param string $source_key Idempotency key.
	 * @return int Attachment ID, 0 when the file is missing or outside uploads.
	 */
	public static function copy_file_attachment( string $path, array $postarr, string $source, string $source_key ): int {
		$existing = static::find_migrated_post( 'attachment', $source, $source_key );

		if ( $existing ) {
			return $existing;
		}

		$uploads = wp_upload_dir( null, false );
		$basedir = trailingslashit( wp_normalize_path( (string) $uploads['basedir'] ) );
		$path    = wp_normalize_path( $path );

		if ( ! is_file( $path ) || 0 !== strpos( $path, $basedir ) ) {
			return 0;
		}

		$dir  = dirname( $path );
		$name = wp_unique_filename( $dir, pathinfo( $path, PATHINFO_FILENAME ) . '-ms.' . pathinfo( $path, PATHINFO_EXTENSION ) );
		$copy = trailingslashit( $dir ) . $name;

		if ( ! copy( $path, $copy ) ) {
			return 0;
		}

		$type          = wp_check_filetype( $name );
		$attachment_id = wp_insert_attachment(
			array_merge(
				array(
					'post_mime_type' => $type['type'] ? $type['type'] : 'application/octet-stream',
					'post_title'     => sanitize_text_field( pathinfo( $name, PATHINFO_FILENAME ) ),
					'post_status'    => 'inherit',
					'guid'           => trailingslashit( (string) $uploads['baseurl'] ) . substr( $copy, strlen( $basedir ) ),
				),
				$postarr
			),
			$copy,
			0,
			true
		);

		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $copy );
			return 0;
		}

		update_post_meta( $attachment_id, self::SOURCE_META, $source );
		update_post_meta( $attachment_id, self::SOURCE_ID_META, $source_key );
		update_post_meta( $attachment_id, self::OWN_FILE_META, 1 );

		if ( 0 === strpos( (string) get_post_mime_type( $attachment_id ), 'image/' ) ) {
			if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}

			wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $copy ) );
		}

		return (int) $attachment_id;
	}

	/**
	 * MasterStudy copy of a source post (0 when it was not copied).
	 */
	public static function copy_of( string $source, int $source_post_id ): int {
		if ( $source_post_id <= 0 ) {
			return 0;
		}

		$copies = static::copies( $source );

		return $copies[ $source_post_id ] ?? 0;
	}

	/**
	 * Map a list of source post IDs to their copies; IDs without a copy are dropped, order is kept.
	 *
	 * @param int[] $source_post_ids Source post IDs.
	 * @return int[]
	 */
	public static function copies_of( string $source, array $source_post_ids ): array {
		$ids = array();

		foreach ( $source_post_ids as $source_post_id ) {
			$copy = static::copy_of( $source, (int) $source_post_id );

			if ( $copy ) {
				$ids[] = $copy;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Source post ID a MasterStudy post was copied from (0 = not a copy).
	 */
	public static function source_of( int $copy_id ): int {
		$key = (string) get_post_meta( $copy_id, self::SOURCE_ID_META, true );

		return 0 === strpos( $key, self::COPY_KEY_PREFIX ) ? (int) substr( $key, strlen( self::COPY_KEY_PREFIX ) ) : 0;
	}

	/**
	 * All copies of one source, loaded with a single query (then kept up to date by copy_post()).
	 *
	 * @return array<int, int> Source post ID => copy post ID.
	 */
	private static function copies( string $source ): array {
		global $wpdb;

		if ( ! isset( static::$copies[ $source ] ) ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT i.post_id, i.meta_value FROM {$wpdb->postmeta} i
					 INNER JOIN {$wpdb->postmeta} s ON s.post_id = i.post_id AND s.meta_key = %s AND s.meta_value = %s
					 WHERE i.meta_key = %s AND i.meta_value LIKE %s",
					self::SOURCE_META,
					$source,
					self::SOURCE_ID_META,
					$wpdb->esc_like( self::COPY_KEY_PREFIX ) . '%'
				)
			);

			static::$copies[ $source ] = array();

			foreach ( (array) $rows as $row ) {
				static::$copies[ $source ][ (int) substr( $row->meta_value, strlen( self::COPY_KEY_PREFIX ) ) ] = (int) $row->post_id;
			}
		}

		return static::$copies[ $source ];
	}

	/**
	 * Forget the loaded copy maps (the job engine rolls back failed items, which may remove copies).
	 */
	public static function reset_copies(): void {
		static::$copies = array();
	}

	/**
	 * Preserve a source field that has no direct MasterStudy equivalent under _migrated_{key}.
	 *
	 * @param mixed $value Empty values are not written.
	 */
	public static function store_unmigrated_meta( int $post_id, string $key, $value ): void {
		if ( '' === $value || null === $value || array() === $value ) {
			return;
		}

		update_post_meta( $post_id, '_migrated_' . ltrim( $key, '_' ), $value );
	}

	/*
	|--------------------------------------------------------------------------
	| Users
	|--------------------------------------------------------------------------
	*/

	/**
	 * Give a user the MasterStudy instructor role (administrators are left untouched)
	 * and mark the instructor request as approved.
	 */
	public static function make_instructor( int $user_id ): void {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			return;
		}

		if ( ! in_array( self::INSTRUCTOR_ROLE, (array) $user->roles, true ) ) {
			// WordPress recalculates the user level on role changes; keep the old one for "Delete migrated data".
			static::track_user_meta( $user_id, $GLOBALS['wpdb']->get_blog_prefix() . 'user_level' );
			$user->add_role( self::INSTRUCTOR_ROLE );
			Ledger::add( Helper::current_source(), 'instructor_roles', $user_id );
		}

		$status = (string) get_user_meta( $user_id, 'submission_status', true );

		if ( 'approved' !== $status ) {
			Ledger::add( Helper::current_source(), 'submission_status', $user_id, $status );
			update_user_meta( $user_id, 'submission_status', 'approved' );
			update_user_meta( $user_id, 'submission_date', time() );
		}
	}

	/**
	 * Record a pending "become an instructor" application (MasterStudy instructor requests list).
	 * Users who already are instructors/administrators or have an application status are left untouched.
	 *
	 * @param int $date Unix time of the application (0 = now).
	 */
	public static function request_instructor( int $user_id, int $date = 0 ): void {
		$user = get_userdata( $user_id );

		if ( ! $user || array_intersect( array( 'administrator', self::INSTRUCTOR_ROLE ), (array) $user->roles ) ) {
			return;
		}

		if ( '' !== (string) get_user_meta( $user_id, 'submission_status', true ) ) {
			return;
		}

		Ledger::add( Helper::current_source(), 'submission_status', $user_id, '' );
		update_user_meta( $user_id, 'submission_status', 'pending' );
		update_user_meta( $user_id, 'submission_date', $date > 0 ? $date : time() );
	}

	/**
	 * MasterStudy students have no dedicated role — make sure the user has at least one
	 * role so they can log in (users stripped of their only source-LMS role get subscriber).
	 */
	public static function ensure_student( int $user_id ): void {
		$user = get_userdata( $user_id );

		if ( $user && empty( $user->roles ) ) {
			$role = get_option( 'default_role', 'subscriber' );
			Ledger::add( Helper::current_source(), 'default_roles', $user_id, $role );
			static::track_user_meta( $user_id, $GLOBALS['wpdb']->get_blog_prefix() . 'user_level' );
			$user->set_role( $role );
		}
	}

	/**
	 * Record the current state of a user meta key before the migration writes it, so "Delete migrated data" can
	 * restore it (Ledger type "user_meta:<key>"; previous value, or null when the key did not exist).
	 */
	public static function track_user_meta( int $user_id, string $meta_key ): void {
		Ledger::add(
			Helper::current_source(),
			'user_meta:' . $meta_key,
			$user_id,
			metadata_exists( 'user', $user_id, $meta_key ) ? get_user_meta( $user_id, $meta_key, true ) : null
		);
	}

	/**
	 * Copy profile fields into MasterStudy user meta. Only non-empty values are written
	 * and existing MasterStudy values are never overwritten.
	 *
	 * @param array $fields Keys: description, position, facebook, twitter, instagram, linkedin, avatar_url.
	 */
	public static function set_profile( int $user_id, array $fields ): void {
		$map = array(
			'description' => 'description',
			'position'    => 'position',
			'facebook'    => 'facebook',
			'twitter'     => 'twitter',
			'instagram'   => 'instagram',
			'linkedin'    => 'linkedin',
			'avatar_url'  => 'stm_lms_user_avatar',
		);

		foreach ( $map as $field => $meta_key ) {
			$value = $fields[ $field ] ?? '';

			if ( '' === $value || null === $value || '' !== (string) get_user_meta( $user_id, $meta_key, true ) ) {
				continue;
			}

			static::track_user_meta( $user_id, $meta_key );
			update_user_meta( $user_id, $meta_key, 'avatar_url' === $field ? esc_url_raw( $value ) : wp_kses_post( $value ) );
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Course
	|--------------------------------------------------------------------------
	*/

	/**
	 * Map source category terms to stm_lms_course_taxonomy terms (created by name when missing).
	 *
	 * @param string $source_taxonomy  Source taxonomy name.
	 * @param int[]  $exclude_term_ids Source term IDs to skip (e.g. the default WordPress category).
	 * @param int    $source_post_id   Source post to read the terms from (0 = $course_id itself).
	 */
	public static function migrate_categories( int $course_id, string $source_taxonomy, array $exclude_term_ids = array(), int $source_post_id = 0 ): void {
		static $cache = array();

		// Copy mode: terms are read from the source post and assigned to the MasterStudy copy.
		$from = $source_post_id > 0 ? $source_post_id : $course_id;

		if ( ! taxonomy_exists( $source_taxonomy ) ) {
			$terms = static::raw_object_terms( $from, $source_taxonomy );
		} else {
			$terms = wp_get_object_terms( $from, $source_taxonomy );
		}

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return;
		}

		$term_ids = array();

		foreach ( $terms as $term ) {
			if ( isset( $term->term_id ) && in_array( (int) $term->term_id, array_map( 'intval', $exclude_term_ids ), true ) ) {
				continue;
			}

			$key = $term->name;

			if ( ! isset( $cache[ $key ] ) ) {
				$existing = term_exists( $term->name, Taxonomy::COURSE_CATEGORY );

				if ( ! $existing ) {
					$existing = wp_insert_term(
						$term->name,
						Taxonomy::COURSE_CATEGORY,
						array(
							'slug'        => $term->slug,
							'description' => $term->description,
						)
					);
				}

				if ( is_wp_error( $existing ) ) {
					continue;
				}

				$cache[ $key ] = (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );
			}

			$term_ids[] = $cache[ $key ];
		}

		if ( ! empty( $term_ids ) ) {
			wp_set_object_terms( $course_id, $term_ids, Taxonomy::COURSE_CATEGORY, true );
		}
	}

	/**
	 * Rebuild the parent chain of a course's source category terms on the matching MasterStudy
	 * categories (matched by name, as migrate_categories() does). Parents missing in MasterStudy are
	 * created; a MasterStudy category that already has a parent is never re-parented. Run after
	 * migrate_categories().
	 *
	 * @param string $source_taxonomy Source taxonomy name.
	 */
	public static function migrate_category_hierarchy( int $course_id, string $source_taxonomy ): void { // $course_id: the post the SOURCE terms are read from (copy mode: the source post).
		global $wpdb;

		$terms = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tt.term_id, tt.parent FROM {$wpdb->term_taxonomy} tt
				 INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
				 WHERE tr.object_id = %d AND tt.taxonomy = %s AND tt.parent > 0",
				$course_id,
				$source_taxonomy
			)
		);

		foreach ( (array) $terms as $term ) {
			$child_id  = (int) $term->term_id;
			$parent_id = (int) $term->parent;

			// Walk up the source chain (depth-limited against broken loops).
			for ( $depth = 0; $parent_id && $depth < 10; $depth++ ) {
				$child  = static::raw_term( $child_id, $source_taxonomy );
				$parent = static::raw_term( $parent_id, $source_taxonomy );

				if ( ! $child || ! $parent ) {
					break;
				}

				$ms_child  = term_exists( $child->name, Taxonomy::COURSE_CATEGORY );
				$ms_parent = term_exists( $parent->name, Taxonomy::COURSE_CATEGORY );

				if ( ! $ms_child ) {
					break;
				}

				if ( ! $ms_parent ) {
					$ms_parent = wp_insert_term(
						$parent->name,
						Taxonomy::COURSE_CATEGORY,
						array(
							'slug'        => $parent->slug,
							'description' => $parent->description,
						)
					);

					if ( is_wp_error( $ms_parent ) ) {
						break;
					}
				}

				$ms_child_id  = (int) ( is_array( $ms_child ) ? $ms_child['term_id'] : $ms_child );
				$ms_parent_id = (int) ( is_array( $ms_parent ) ? $ms_parent['term_id'] : $ms_parent );
				$current      = get_term( $ms_child_id, Taxonomy::COURSE_CATEGORY );

				if ( $current && ! is_wp_error( $current ) && 0 === (int) $current->parent && $ms_child_id !== $ms_parent_id ) {
					wp_update_term( $ms_child_id, Taxonomy::COURSE_CATEGORY, array( 'parent' => $ms_parent_id ) );
				}

				$child_id  = $parent_id;
				$parent_id = (int) $parent->parent;
			}
		}
	}

	/**
	 * Copy the image of a course's source category terms (attachment ID in source term meta) to the matching
	 * MasterStudy categories (`course_image` term meta, matched by name like migrate_categories()). An image
	 * MasterStudy already has is never replaced. Run after migrate_categories().
	 *
	 * @param string $source_taxonomy Source taxonomy name.
	 * @param string $image_meta_key  Source term meta key holding the attachment ID.
	 */
	public static function migrate_category_images( int $course_id, string $source_taxonomy, string $image_meta_key ): void { // $course_id: the post the SOURCE terms are read from (copy mode: the source post).
		global $wpdb;

		$term_ids = array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT tt.term_id FROM {$wpdb->term_taxonomy} tt
					 INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
					 WHERE tr.object_id = %d AND tt.taxonomy = %s",
					$course_id,
					$source_taxonomy
				)
			)
		);

		// Include the ancestors: migrate_category_hierarchy() recreates them in MasterStudy.
		for ( $i = 0; $i < count( $term_ids ) && $i < 50; $i++ ) { // phpcs:ignore Generic.CodeAnalysis.ForLoopWithTestFunctionCall.NotAllowed
			$parent = static::raw_term( $term_ids[ $i ], $source_taxonomy );

			if ( $parent && (int) $parent->parent && ! in_array( (int) $parent->parent, $term_ids, true ) ) {
				$term_ids[] = (int) $parent->parent;
			}
		}

		foreach ( $term_ids as $term_id ) {
			$term = static::raw_term( $term_id, $source_taxonomy );

			if ( ! $term ) {
				continue;
			}

			$image_id = absint( get_term_meta( $term_id, $image_meta_key, true ) );
			$ms_term  = term_exists( $term->name, Taxonomy::COURSE_CATEGORY );

			if ( ! $image_id || 'attachment' !== get_post_type( $image_id ) || ! $ms_term ) {
				continue;
			}

			$ms_term_id = (int) ( is_array( $ms_term ) ? $ms_term['term_id'] : $ms_term );

			if ( '' === (string) get_term_meta( $ms_term_id, 'course_image', true ) ) {
				update_term_meta( $ms_term_id, 'course_image', $image_id );
			}
		}
	}

	/**
	 * A term row of any (possibly unregistered) taxonomy.
	 *
	 * @return object|null Object with name, slug, description, parent.
	 */
	private static function raw_term( int $term_id, string $taxonomy ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT t.name, t.slug, tt.description, tt.parent FROM {$wpdb->terms} t
				 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
				 WHERE t.term_id = %d AND tt.taxonomy = %s",
				$term_id,
				$taxonomy
			)
		);
	}

	/**
	 * Terms of a taxonomy that is not registered in this request (source plugin inactive).
	 *
	 * @return object[] Objects with name, slug, description.
	 */
	private static function raw_object_terms( int $object_id, string $taxonomy ): array {
		global $wpdb;

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.term_id, t.name, t.slug, tt.description FROM {$wpdb->terms} t
				 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
				 INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
				 WHERE tr.object_id = %d AND tt.taxonomy = %s",
				$object_id,
				$taxonomy
			)
		);
	}

	/**
	 * Resolve a source difficulty ("beginner", "Intermediate", "expert", …) to a configured
	 * MasterStudy level ID and store it in the `level` meta. Unknown levels are left empty.
	 */
	public static function set_level( int $course_id, string $level ): void {
		$level = strtolower( trim( $level ) );

		if ( '' === $level || ! class_exists( '\STM_LMS_Helpers' ) ) {
			return;
		}

		$aliases = array(
			'all_levels'   => 'beginner',
			'all-levels'   => 'beginner',
			'expert'       => 'advanced',
			'basic'        => 'beginner',
			'medium'       => 'intermediate',
			'intermediate' => 'intermediate',
		);

		$levels = \STM_LMS_Helpers::get_course_levels();

		foreach ( array( $level, $aliases[ $level ] ?? '' ) as $needle ) {
			if ( '' === $needle ) {
				continue;
			}

			foreach ( $levels as $id => $label ) {
				if ( strtolower( (string) $id ) === $needle || strtolower( wp_strip_all_tags( (string) $label ) ) === $needle ) {
					update_post_meta( $course_id, 'level', $id );
					return;
				}
			}
		}
	}

	/**
	 * Set course pricing. A price of 0/null makes the course free.
	 *
	 * @param float|null $price      Regular price.
	 * @param float|null $sale_price Sale price (ignored when not lower than the regular price).
	 */
	public static function set_pricing( int $course_id, ?float $price, ?float $sale_price = null ): void {
		$price = (float) $price;

		if ( $price <= 0 ) {
			update_post_meta( $course_id, 'pricing_mode', PricingMode::FREE );
			update_post_meta( $course_id, 'single_sale', '' );
			// Without this MasterStudy labels the course "Members only".
			update_post_meta( $course_id, 'not_membership', 'on' );
			update_post_meta( $course_id, 'price', '' );
			update_post_meta( $course_id, 'sale_price', '' );
			return;
		}

		update_post_meta( $course_id, 'pricing_mode', PricingMode::PAID );
		update_post_meta( $course_id, 'single_sale', 'on' );
		update_post_meta( $course_id, 'not_membership', 'on' );
		update_post_meta( $course_id, 'price', (string) $price );
		update_post_meta( $course_id, 'sale_price', null !== $sale_price && $sale_price > 0 && $sale_price < $price ? (string) (float) $sale_price : '' );
	}

	/**
	 * Make a course an affiliate course: the buy button links to an external page.
	 *
	 * @param string $url   External purchase URL.
	 * @param string $text  Button text (empty = "Buy course").
	 * @param float  $price Price shown on the course (0 = none).
	 */
	public static function set_affiliate( int $course_id, string $url, string $text = '', float $price = 0 ): void {
		$url = esc_url_raw( trim( $url ) );

		if ( '' === $url ) {
			return;
		}

		update_post_meta( $course_id, 'pricing_mode', PricingMode::AFFILIATE );
		update_post_meta( $course_id, 'affiliate_course', 'on' );
		update_post_meta( $course_id, 'affiliate_course_link', $url );
		update_post_meta( $course_id, 'affiliate_course_text', '' !== trim( $text ) ? sanitize_text_field( $text ) : __( 'Buy course', 'masterstudy-lms-learning-management-system' ) );
		update_post_meta( $course_id, 'affiliate_course_price', $price > 0 ? (string) $price : '' );
		update_post_meta( $course_id, 'single_sale', '' );
		update_post_meta( $course_id, 'not_membership', 'on' );
	}

	/**
	 * A course nobody can enroll in on their own (e.g. a LearnDash "closed" course): paid, with one-time
	 * purchase and membership switched off, so only an admin (or a group) grants access.
	 *
	 * @param float $price Price shown on the course (0 = none).
	 */
	public static function set_closed_pricing( int $course_id, float $price = 0 ): void {
		update_post_meta( $course_id, 'pricing_mode', PricingMode::PAID );
		update_post_meta( $course_id, 'single_sale', '' );
		update_post_meta( $course_id, 'not_membership', 'on' );
		update_post_meta( $course_id, 'price', $price > 0 ? (string) $price : '' );
		update_post_meta( $course_id, 'sale_price', '' );
	}

	/**
	 * Store simple course info fields. Accepted keys: duration_info, video_duration,
	 * basic_info, requirements, intended_audience, access_duration, featured (bool),
	 * views (int), end_time (access days, int).
	 */
	public static function set_course_info( int $course_id, array $info ): void {
		foreach ( array( 'duration_info', 'video_duration', 'basic_info', 'requirements', 'intended_audience', 'access_duration' ) as $key ) {
			if ( isset( $info[ $key ] ) && '' !== trim( (string) $info[ $key ] ) ) {
				update_post_meta( $course_id, $key, wp_kses_post( $info[ $key ] ) );
			}
		}

		if ( isset( $info['featured'] ) ) {
			update_post_meta( $course_id, 'featured', $info['featured'] ? 'on' : '' );
		}

		if ( ! empty( $info['views'] ) ) {
			update_post_meta( $course_id, 'views', (int) $info['views'] );
		}

		if ( ! empty( $info['end_time'] ) ) {
			update_post_meta( $course_id, 'expiration_course', 'on' );
			update_post_meta( $course_id, 'end_time', (int) $info['end_time'] );
		}
	}

	/**
	 * Save course FAQ.
	 *
	 * @param array $faq List of ['question' => string, 'answer' => string].
	 */
	public static function set_faq( int $course_id, array $faq ): void {
		$items = array();

		foreach ( $faq as $item ) {
			$question = trim( (string) ( $item['question'] ?? '' ) );

			if ( '' === $question ) {
				continue;
			}

			$items[] = array(
				'question' => $question,
				'answer'   => (string) ( $item['answer'] ?? '' ),
			);
		}

		if ( empty( $items ) ) {
			return;
		}

		if ( class_exists( '\MasterStudy\Lms\Repositories\FaqRepository' ) ) {
			( new \MasterStudy\Lms\Repositories\FaqRepository() )->save( $course_id, $items );
			return;
		}

		update_post_meta( $course_id, 'faq', wp_json_encode( $items ) );
	}

	/**
	 * Append an announcement to the course `announcement` meta (MasterStudy keeps one HTML block per course).
	 */
	public static function add_announcement( int $course_id, string $title, string $content ): void {
		$html = '';

		if ( '' !== trim( $title ) ) {
			$html .= '<h4>' . esc_html( $title ) . '</h4>';
		}

		$html .= wpautop( wp_kses_post( $content ) );

		$current = (string) get_post_meta( $course_id, 'announcement', true );

		if ( '' !== $current && false !== strpos( $current, $html ) ) {
			return;
		}

		update_post_meta( $course_id, 'announcement', '' === $current ? $html : $current . $html );
	}

	/**
	 * Assign a certificate to a migrated course whose source course had one. The source design
	 * cannot be converted, so the site default certificate (if any) is used; otherwise the course
	 * falls back to MasterStudy's category/default certificate resolution.
	 */
	public static function assign_certificate( int $course_id ): void {
		$default = (int) get_option( 'stm_default_certificate', 0 );

		if ( $default && PostType::CERTIFICATE === get_post_type( $default ) ) {
			update_post_meta( $course_id, 'course_certificate', $default );
		}
	}

	/**
	 * Recount `current_students` for a course from the enrollment table.
	 */
	public static function refresh_students_count( int $course_id ): void {
		global $wpdb;

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}stm_lms_user_courses WHERE course_id = %d",
				$course_id
			)
		);

		update_post_meta( $course_id, 'current_students', $count );
	}

	/*
	|--------------------------------------------------------------------------
	| Curriculum
	|--------------------------------------------------------------------------
	*/

	/**
	 * Remove the course curriculum rows so a re-run rebuilds it from scratch (idempotency).
	 */
	public static function reset_curriculum( int $course_id ): void {
		global $wpdb;

		$sections = $wpdb->prefix . 'stm_lms_curriculum_sections';
		$material = $wpdb->prefix . 'stm_lms_curriculum_materials';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"DELETE m FROM {$material} m INNER JOIN {$sections} s ON s.id = m.section_id WHERE s.course_id = %d",
				$course_id
			)
		);
		$wpdb->delete( $sections, array( 'course_id' => $course_id ), array( '%d' ) );
		// phpcs:enable
	}

	/**
	 * Create a curriculum section.
	 *
	 * @param int $order 1-based position.
	 * @return int Section ID.
	 */
	public static function add_section( int $course_id, string $title, int $order ): int {
		$section = ( new CurriculumSectionRepository() )->create(
			array(
				'title'     => '' !== trim( $title ) ? $title : __( 'Section', 'masterstudy-lms-learning-management-system' ),
				'course_id' => $course_id,
				'order'     => max( 1, $order ),
			)
		);

		return (int) $section->id;
	}

	/**
	 * Attach a (already converted) lesson/quiz/assignment/meeting post to a section.
	 *
	 * @param int $order 1-based position inside the section.
	 */
	public static function add_material( int $section_id, int $post_id, int $order ): void {
		( new CurriculumMaterialRepository() )->create(
			array(
				'post_id'    => $post_id,
				'section_id' => $section_id,
				'order'      => max( 1, $order ),
			)
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Lesson
	|--------------------------------------------------------------------------
	*/

	/**
	 * Set lesson basics.
	 *
	 * @param array $data Keys: type (text|video|audio|pdf|stream|zoom_conference), duration (string),
	 *                    preview (bool), excerpt (string).
	 */
	public static function set_lesson( int $lesson_id, array $data ): void {
		update_post_meta( $lesson_id, 'type', $data['type'] ?? 'text' );

		if ( isset( $data['duration'] ) && '' !== (string) $data['duration'] ) {
			update_post_meta( $lesson_id, 'duration', (string) $data['duration'] );
		}

		if ( isset( $data['preview'] ) ) {
			update_post_meta( $lesson_id, 'preview', $data['preview'] ? 'on' : '' );
		}

		if ( ! empty( $data['excerpt'] ) ) {
			update_post_meta( $lesson_id, 'lesson_excerpt', wp_kses_post( $data['excerpt'] ) );
		}
	}

	/**
	 * Set a lesson video and switch the lesson type to video.
	 *
	 * @param string     $source youtube|vimeo|embed|external|html (self-hosted attachment)|shortcode|file.
	 * @param string|int $value  URL, embed HTML, shortcode, or attachment ID for html/file.
	 * @param int        $poster Poster attachment ID.
	 */
	public static function set_lesson_video( int $lesson_id, string $source, $value, int $poster = 0 ): void {
		if ( '' === (string) $value ) {
			return;
		}

		switch ( $source ) {
			case 'youtube':
				$video_type = 'youtube';
				update_post_meta( $lesson_id, 'lesson_youtube_url', esc_url_raw( $value ) );
				break;
			case 'vimeo':
				$video_type = 'vimeo';
				update_post_meta( $lesson_id, 'lesson_vimeo_url', esc_url_raw( $value ) );
				break;
			case 'embed':
				$video_type = 'embed';
				update_post_meta( $lesson_id, 'lesson_embed_ctx', $value );
				break;
			case 'html':
				$video_type = 'html';
				update_post_meta( $lesson_id, 'lesson_video', (int) $value );
				break;
			case 'file':
				$video_type = 'file';
				update_post_meta( $lesson_id, 'file', (int) $value );
				break;
			case 'shortcode':
				$video_type = 'shortcode';
				update_post_meta( $lesson_id, 'lesson_shortcode', $value );
				break;
			default:
				$video_type = 'ext_link';
				update_post_meta( $lesson_id, 'lesson_ext_link_url', esc_url_raw( $value ) );
		}

		update_post_meta( $lesson_id, 'type', 'video' );
		update_post_meta( $lesson_id, 'video_type', $video_type );

		if ( $poster ) {
			update_post_meta( $lesson_id, 'lesson_video_poster', $poster );
		}
	}

	/**
	 * Guess the MasterStudy video source for a raw URL / embed string.
	 *
	 * @return string youtube|vimeo|embed|external
	 */
	public static function detect_video_source( string $value ): string {
		if ( preg_match( '/<iframe|<video|<embed/i', $value ) ) {
			return 'embed';
		}

		if ( false !== strpos( $value, 'youtube.com' ) || false !== strpos( $value, 'youtu.be' ) ) {
			return 'youtube';
		}

		if ( false !== strpos( $value, 'vimeo.com' ) ) {
			return 'vimeo';
		}

		return 'external';
	}

	/**
	 * Attach downloadable files (attachment IDs) to a lesson.
	 *
	 * @param int[] $attachment_ids Attachment IDs.
	 */
	public static function set_lesson_files( int $lesson_id, array $attachment_ids ): void {
		$attachment_ids = array_values( array_unique( array_filter( array_map( 'intval', $attachment_ids ) ) ) );

		if ( empty( $attachment_ids ) ) {
			return;
		}

		$current = json_decode( (string) get_post_meta( $lesson_id, 'lesson_files', true ), true );
		$current = is_array( $current ) ? array_map( 'intval', $current ) : array();

		update_post_meta( $lesson_id, 'lesson_files', wp_json_encode( array_values( array_unique( array_merge( $current, $attachment_ids ) ) ) ) );
	}

	/**
	 * Video captions/subtitles of a lesson (attachment IDs, e.g. .vtt files). MasterStudy keeps them among the
	 * lesson files and lists their IDs in `video_captions_ids`.
	 *
	 * @param int[] $attachment_ids Caption attachment IDs.
	 */
	public static function set_lesson_captions( int $lesson_id, array $attachment_ids ): void {
		$attachment_ids = array_values( array_unique( array_filter( array_map( 'intval', $attachment_ids ) ) ) );

		if ( empty( $attachment_ids ) ) {
			return;
		}

		static::set_lesson_files( $lesson_id, $attachment_ids );

		$current = maybe_unserialize( get_post_meta( $lesson_id, 'video_captions_ids', true ) );
		$current = is_array( $current ) ? array_map( 'intval', $current ) : array();

		update_post_meta( $lesson_id, 'video_captions_ids', array_values( array_unique( array_merge( $current, $attachment_ids ) ) ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Quiz & questions
	|--------------------------------------------------------------------------
	*/

	/**
	 * Quiz answer rules.
	 *
	 * @param array $data Keys (each optional): random_answers (bool, shuffle answer order),
	 *                    required_question_ids (int[] questions that must be answered before submitting).
	 */
	public static function set_quiz_answer_rules( int $quiz_id, array $data ): void {
		if ( array_key_exists( 'random_answers', $data ) ) {
			update_post_meta( $quiz_id, 'random_answers', ! empty( $data['random_answers'] ) ? 'on' : '' );
		}

		if ( array_key_exists( 'required_question_ids', $data ) ) {
			$ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $data['required_question_ids'] ) ) ) );

			update_post_meta( $quiz_id, 'required_answers_ids', array_map( 'strval', $ids ) );
		}
	}

	/**
	 * Set quiz settings.
	 *
	 * @param array $data Keys: duration_minutes (int), passing_grade (float, percent), attempts (int, 0 = unlimited),
	 *                    random_questions (bool), show_correct_answer (bool), retake_penalty (float, percent),
	 *                    excerpt (string), style (default|pagination|global, optional).
	 */
	public static function set_quiz( int $quiz_id, array $data ): void {
		$minutes = (int) ( $data['duration_minutes'] ?? 0 );

		if ( $minutes > 0 ) {
			if ( 0 === $minutes % 1440 ) {
				update_post_meta( $quiz_id, 'duration', $minutes / 1440 );
				update_post_meta( $quiz_id, 'duration_measure', 'days' );
			} elseif ( 0 === $minutes % 60 ) {
				update_post_meta( $quiz_id, 'duration', $minutes / 60 );
				update_post_meta( $quiz_id, 'duration_measure', 'hours' );
			} else {
				update_post_meta( $quiz_id, 'duration', $minutes );
				update_post_meta( $quiz_id, 'duration_measure', 'minutes' );
			}
		} else {
			update_post_meta( $quiz_id, 'duration', '' );
			update_post_meta( $quiz_id, 'duration_measure', 'minutes' );
		}

		if ( isset( $data['passing_grade'] ) && '' !== (string) $data['passing_grade'] ) {
			update_post_meta( $quiz_id, 'passing_grade', (float) $data['passing_grade'] );
		}

		$attempts = (int) ( $data['attempts'] ?? 0 );
		update_post_meta( $quiz_id, 'quiz_attempts', $attempts > 0 ? 'limited' : 'unlimited' );

		if ( $attempts > 0 ) {
			update_post_meta( $quiz_id, 'attempts', $attempts );
		}

		if ( array_key_exists( 'random_questions', $data ) ) {
			update_post_meta( $quiz_id, 'random_questions', ! empty( $data['random_questions'] ) ? 'on' : '' );
		}

		if ( array_key_exists( 'show_correct_answer', $data ) ) {
			update_post_meta( $quiz_id, 'correct_answer', ! empty( $data['show_correct_answer'] ) ? 'on' : '' );
		}

		if ( ! empty( $data['retake_penalty'] ) ) {
			update_post_meta( $quiz_id, 're_take_cut', (float) $data['retake_penalty'] );
		}

		// Optional 'style' (default = one page, pagination = one question per page); otherwise keep the existing style.
		if ( isset( $data['style'] ) && in_array( $data['style'], array( 'default', 'pagination', 'global' ), true ) ) {
			update_post_meta( $quiz_id, 'quiz_style', $data['style'] );
		} elseif ( ! metadata_exists( 'post', $quiz_id, 'quiz_style' ) ) {
			update_post_meta( $quiz_id, 'quiz_style', 'default' );
		}

		if ( ! empty( $data['excerpt'] ) ) {
			update_post_meta( $quiz_id, 'lesson_excerpt', wp_kses_post( $data['excerpt'] ) );
		}
	}

	/**
	 * Set the ordered list of question IDs on a quiz.
	 *
	 * @param int[] $question_ids Question post IDs in display order.
	 */
	public static function set_quiz_questions( int $quiz_id, array $question_ids ): void {
		$question_ids = array_values( array_unique( array_filter( array_map( 'intval', $question_ids ) ) ) );

		update_post_meta( $quiz_id, 'questions', implode( ',', $question_ids ) );
	}

	/**
	 * Add question categories (stm_lms_question_taxonomy terms, created by name when missing).
	 *
	 * @param string[] $names Category names.
	 */
	public static function set_question_categories( int $question_id, array $names ): void {
		if ( ! taxonomy_exists( Taxonomy::QUESTION_CATEGORY ) ) {
			return;
		}

		$term_ids = array();

		foreach ( $names as $name ) {
			$name = trim( wp_strip_all_tags( (string) $name ) );

			if ( '' === $name ) {
				continue;
			}

			$existing = term_exists( $name, Taxonomy::QUESTION_CATEGORY );
			$existing = $existing ? $existing : wp_insert_term( $name, Taxonomy::QUESTION_CATEGORY );

			if ( ! is_wp_error( $existing ) ) {
				$term_ids[] = (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );
			}
		}

		if ( ! empty( $term_ids ) ) {
			wp_set_object_terms( $question_id, $term_ids, Taxonomy::QUESTION_CATEGORY, true );
		}
	}

	/**
	 * Save a MasterStudy question (post already converted to stm-questions or created).
	 *
	 * Normalized $answers input per type:
	 * - single_choice / multi_choice: list of ['text' => string, 'correct' => bool, 'image_id' => int?]
	 * - true_false: list with one item ['correct' => bool] (true when the right answer is "True")
	 * - item_match: list of ['prompt' => string, 'match' => string]
	 * - image_match: list of ['prompt' => string, 'prompt_image_id' => int, 'match' => string, 'match_image_id' => int]
	 * - keywords: list of ['text' => string]
	 * - sortable: list of ['text' => string] in the correct order
	 * - fill_the_gap: list with one item ['text' => 'Sky is |blue|'] — blanks wrapped in pipes
	 *
	 * @param string $type        MasterStudy question type.
	 * @param array  $answers     Normalized answers, see above.
	 * @param array  $extra       Keys: explanation, hint, image_id, view_type.
	 */
	public static function set_question( int $question_id, string $type, array $answers, array $extra = array() ): void {
		update_post_meta( $question_id, 'type', $type );
		update_post_meta( $question_id, 'answers', static::build_answers( $type, $answers ) );

		if ( ! empty( $extra['explanation'] ) ) {
			update_post_meta( $question_id, 'question_explanation', wp_kses_post( $extra['explanation'] ) );
		}

		if ( ! empty( $extra['hint'] ) ) {
			update_post_meta( $question_id, 'question_hint', wp_kses_post( $extra['hint'] ) );
		}

		if ( ! empty( $extra['image_id'] ) ) {
			// The course player only renders question media when `type` is set (mime type, or 'video').
			update_post_meta(
				$question_id,
				'image',
				array(
					'id'    => (int) $extra['image_id'],
					'url'   => (string) wp_get_attachment_url( (int) $extra['image_id'] ),
					'type'  => (string) get_post_mime_type( (int) $extra['image_id'] ),
					'title' => get_post_field( 'post_title', (int) $extra['image_id'] ),
				)
			);
		}

		if ( in_array( $type, array( 'single_choice', 'multi_choice', 'image_match' ), true ) ) {
			update_post_meta( $question_id, 'question_view_type', $extra['view_type'] ?? 'list' );
		}
	}

	/**
	 * Build the MasterStudy `answers` meta structure for a question type.
	 */
	public static function build_answers( string $type, array $answers ): array {
		$image = function ( $attachment_id ) {
			$attachment_id = (int) $attachment_id;

			return $attachment_id
				? array(
					'id'  => $attachment_id,
					'url' => (string) wp_get_attachment_url( $attachment_id ),
				)
				: null;
		};

		$result = array();

		switch ( $type ) {
			case 'true_false':
				$true_correct = ! empty( $answers[0]['correct'] );

				return array(
					array(
						'text'   => 'True',
						'isTrue' => $true_correct,
					),
					array(
						'text'   => 'False',
						'isTrue' => ! $true_correct,
					),
				);

			case 'item_match':
				foreach ( $answers as $answer ) {
					$result[] = array(
						'question' => (string) ( $answer['prompt'] ?? '' ),
						'text'     => (string) ( $answer['match'] ?? '' ),
						'isTrue'   => false,
					);
				}

				return $result;

			case 'image_match':
				foreach ( $answers as $answer ) {
					$result[] = array(
						'question'       => (string) ( $answer['prompt'] ?? '' ),
						'question_image' => $image( $answer['prompt_image_id'] ?? 0 ),
						'text'           => (string) ( $answer['match'] ?? '' ),
						'text_image'     => $image( $answer['match_image_id'] ?? 0 ),
						'isTrue'         => false,
					);
				}

				return $result;

			case 'fill_the_gap':
				return array(
					array(
						'text'   => (string) ( $answers[0]['text'] ?? '' ),
						'isTrue' => false,
					),
				);

			case 'keywords':
			case 'sortable':
				foreach ( $answers as $answer ) {
					$text = (string) ( $answer['text'] ?? '' );

					if ( '' !== $text ) {
						$result[] = array(
							'text'   => $text,
							'isTrue' => false,
						);
					}
				}

				return $result;

			default:
				foreach ( $answers as $answer ) {
					$result[] = array(
						'text'           => (string) ( $answer['text'] ?? '' ),
						'isTrue'         => ! empty( $answer['correct'] ),
						'text_image'     => $image( $answer['image_id'] ?? 0 ),
						'question_image' => null,
					);
				}

				return $result;
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Enrollments & progress
	|--------------------------------------------------------------------------
	*/

	/**
	 * Enroll a user in a course without firing enrollment hooks or emails.
	 * Idempotent: returns the existing row ID if the user is already enrolled.
	 *
	 * @param int      $start_time Enrollment Unix time.
	 * @param int|null $end_time   Completion Unix time, when the source marks the course completed.
	 * @return int user_course_id.
	 */
	public static function enroll( int $user_id, int $course_id, int $start_time = 0, ?int $end_time = null, int $progress = 0 ): int {
		// "Enrollments & progress" not chosen: no enrollment is created by any step (groups, bundles, orders…).
		if ( ! Helper::step_selected( 'enrollments' ) ) {
			return 0;
		}

		global $wpdb;

		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			throw new \Exception( sprintf( 'Enrollment not imported: user #%d no longer exists.', $user_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		if ( ! $course_id || PostType::COURSE !== get_post_type( $course_id ) ) {
			throw new \Exception( sprintf( 'Enrollment not imported: course #%d does not exist or was not migrated to MasterStudy.', $course_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$table    = $wpdb->prefix . 'stm_lms_user_courses';
		$existing = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT user_course_id FROM {$table} WHERE user_id = %d AND course_id = %d LIMIT 1", $user_id, $course_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		if ( $existing ) {
			return $existing;
		}

		$inserted = $wpdb->insert(
			$table,
			array(
				'user_id'           => $user_id,
				'course_id'         => $course_id,
				'current_lesson_id' => 0,
				'progress_percent'  => max( 0, min( 100, $progress ) ),
				'status'            => 'enrolled',
				'lng_code'          => get_locale(),
				'start_time'        => $start_time > 0 ? $start_time : time(),
				'end_time'          => (int) $end_time,
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%d' )
		);

		if ( false === $inserted ) {
			throw new \Exception( sprintf( 'enroll: DB insert failed for user %d course %d: %s', $user_id, $course_id, $wpdb->last_error ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		static::ensure_student( $user_id );

		return (int) $wpdb->insert_id;
	}

	public static function is_enrolled( int $user_id, int $course_id ): bool {
		global $wpdb;

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$wpdb->prefix}stm_lms_user_courses WHERE user_id = %d AND course_id = %d LIMIT 1",
				$user_id,
				$course_id
			)
		);
	}

	/**
	 * Mark a lesson (or Google Meet item) completed for a user. Idempotent.
	 */
	public static function complete_lesson( int $user_id, int $course_id, int $lesson_id, int $start_time = 0, int $end_time = 0 ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'stm_lms_user_lessons';

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$table} WHERE user_id = %d AND course_id = %d AND lesson_id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$course_id,
				$lesson_id
			)
		);

		if ( $exists ) {
			return;
		}

		$end_time   = $end_time > 0 ? $end_time : time();
		$start_time = $start_time > 0 ? $start_time : $end_time;

		$wpdb->insert(
			$table,
			array(
				'user_id'    => $user_id,
				'course_id'  => $course_id,
				'lesson_id'  => $lesson_id,
				'progress'   => 100,
				'start_time' => $start_time,
				'end_time'   => $end_time,
			),
			array( '%d', '%d', '%d', '%d', '%d', '%d' )
		);
	}

	/**
	 * Store one quiz attempt with its answers. Attempts must be added in chronological order
	 * per (user, course, quiz) — attempt_number links answers to the attempt row.
	 *
	 * @param float  $percent    Score in percent (0-100).
	 * @param bool   $passed     Whether the attempt passed.
	 * @param string $created_at MySQL datetime (site time) of the attempt.
	 * @param array  $answers    List of ['question_id' => int, 'answer' => string, 'correct' => bool].
	 */
	public static function add_quiz_attempt( int $user_id, int $course_id, int $quiz_id, float $percent, bool $passed, string $created_at = '', array $answers = array() ): void {
		global $wpdb;

		$quizzes = $wpdb->prefix . 'stm_lms_user_quizzes';

		$attempt_number = 1 + (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$quizzes} WHERE user_id = %d AND course_id = %d AND quiz_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$course_id,
				$quiz_id
			)
		);

		$inserted = $wpdb->insert(
			$quizzes,
			array(
				'user_id'    => $user_id,
				'course_id'  => $course_id,
				'quiz_id'    => $quiz_id,
				'progress'   => (int) round( max( 0, min( 100, $percent ) ) ),
				'status'     => $passed ? 'passed' : 'failed',
				'sequency'   => '[]',
				'created_at' => '' !== $created_at ? $created_at : current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			throw new \Exception( sprintf( 'add_quiz_attempt: DB insert failed for user %d quiz %d: %s', $user_id, $quiz_id, $wpdb->last_error ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		foreach ( $answers as $answer ) {
			$wpdb->insert(
				$wpdb->prefix . 'stm_lms_user_answers',
				array(
					'user_id'         => $user_id,
					'course_id'       => $course_id,
					'quiz_id'         => $quiz_id,
					'question_id'     => (int) $answer['question_id'],
					'user_answer'     => (string) ( $answer['answer'] ?? '' ),
					'correct_answer'  => ! empty( $answer['correct'] ) ? 1 : 0,
					'attempt_number'  => $attempt_number,
					'questions_order' => '',
				),
				array( '%d', '%d', '%d', '%d', '%s', '%d', '%d', '%s' )
			);
		}
	}

	/**
	 * Whether an identical attempt was already imported: same user, quiz and start time and — when given — the same
	 * score and result (a failed attempt and its passed retry may share the same second).
	 *
	 * @param float|null $percent Attempt score in percent.
	 * @param bool|null  $passed  Attempt result.
	 */
	public static function quiz_attempt_exists( int $user_id, int $quiz_id, string $created_at, ?float $percent = null, ?bool $passed = null ): bool {
		global $wpdb;

		$sql  = "SELECT 1 FROM {$wpdb->prefix}stm_lms_user_quizzes WHERE user_id = %d AND quiz_id = %d AND created_at = %s";
		$args = array( $user_id, $quiz_id, $created_at );

		if ( null !== $percent ) {
			$sql   .= ' AND progress = %d';
			$args[] = (int) round( max( 0, min( 100, $percent ) ) );
		}

		if ( null !== $passed ) {
			$sql   .= ' AND status = %s';
			$args[] = $passed ? 'passed' : 'failed';
		}

		return (bool) $wpdb->get_var( $wpdb->prepare( $sql . ' LIMIT 1', $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Recalculate progress_percent for every enrollment of the given courses (or all
	 * enrollments when empty) using MasterStudy's own progress logic.
	 *
	 * @param int[] $course_ids Course IDs.
	 */
	public static function recalculate_progress( array $course_ids = array() ): void {
		global $wpdb;

		if ( ! class_exists( '\STM_LMS_Course' ) ) {
			return;
		}

		static::suppress_side_effects();

		$table = $wpdb->prefix . 'stm_lms_user_courses';
		$where = '';

		if ( ! empty( $course_ids ) ) {
			$where = 'AND course_id IN (' . implode( ',', array_map( 'intval', $course_ids ) ) . ')';
		}

		$last_id = 0;

		do {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT user_course_id, user_id, course_id, end_time FROM {$table} WHERE user_course_id > %d {$where} ORDER BY user_course_id ASC LIMIT 500", $last_id ) );

			foreach ( (array) $rows as $row ) {
				$last_id = (int) $row->user_course_id;

				// Imported completions must not trigger the "course completed" certificate email later.
				if ( (int) $row->end_time > 0 ) {
					update_option( "masterstudy_plugin_course_completion_{$row->user_id}_{$row->course_id}", true, false );
				}

				$source_completed = (int) $row->end_time > 0;

				// The source marked the course completed: items it never tracks (e.g. Zoom meetings)
				// must not keep the student below 100%.
				if ( $source_completed ) {
					foreach ( static::lesson_material_ids( (int) $row->course_id ) as $lesson_id ) {
						static::complete_lesson( (int) $row->user_id, (int) $row->course_id, $lesson_id, (int) $row->end_time, (int) $row->end_time );
					}
				}

				\STM_LMS_Course::update_course_progress( (int) $row->user_id, (int) $row->course_id );

				$progress = (int) $wpdb->get_var( $wpdb->prepare( "SELECT progress_percent FROM {$table} WHERE user_course_id = %d", $last_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

				// update_course_progress() stamps "now" into last_progress_time (used by the certificate
				// end date); keep the source completion time instead.
				if ( $progress >= 100 && (int) $row->end_time > 0 ) {
					$times = get_user_meta( (int) $row->user_id, 'last_progress_time', true );
					$times = is_array( $times ) ? $times : array();

					$times[ (int) $row->course_id ] = (int) $row->end_time;
					update_user_meta( (int) $row->user_id, 'last_progress_time', $times );
				}

				if ( $source_completed && $progress < 100 ) {
					$progress = 100;
				}

				// Completion time only belongs to completed courses; keep the source date instead of "now".
				$wpdb->update(
					$table,
					array(
						'progress_percent' => $progress,
						'end_time'         => $progress >= 100 ? ( $source_completed ? (int) $row->end_time : time() ) : 0,
					),
					array( 'user_course_id' => $last_id )
				);
			}
		} while ( ! empty( $rows ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Orders
	|--------------------------------------------------------------------------
	*/

	/**
	 * Create a native MasterStudy order.
	 *
	 * @param array $data Keys: order_id (source order POST to copy, optional), source_id (for
	 *                    idempotent creation), user_id, items (list of ['course_id' => int, 'price' => float]),
	 *                    status (completed|pending|cancelled|…), date (Unix time), total, subtotal, taxes,
	 *                    currency, payment_code, transaction_id, post_status (optional post status of a NEW
	 *                    order post: draft|trash|private|pending, default publish; an existing order keeps its status).
	 * @param string $source Source LMS slug.
	 * @return int Order post ID.
	 */
	public static function save_order( array $data, string $source ): int {
		global $wpdb;

		$date        = (int) ( $data['date'] ?? time() );
		$order_key   = uniqid( (string) ( $data['user_id'] ?? 0 ) . $date );
		$post_status = in_array( $data['post_status'] ?? '', array( 'draft', 'trash', 'private', 'pending' ), true ) ? $data['post_status'] : 'publish';

		if ( ! empty( $data['order_id'] ) ) {
			// Copy mode: the source order post is copied, never converted.
			$order_id = static::copy_post(
				(int) $data['order_id'],
				PostType::ORDER,
				$source,
				array(
					'post_title'    => $order_key,
					'post_status'   => $post_status,
					'post_content'  => '',
					'post_excerpt'  => '',
					'post_name'     => '',
					'post_password' => '',
					'post_author'   => (int) ( $data['user_id'] ?? 0 ),
					'post_date'     => wp_date( 'Y-m-d H:i:s', $date ),
					'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $date ),
					'_thumbnail_id' => 0,
				)
			);
		} else {
			$order_id = static::insert_post(
				array(
					'post_type'     => PostType::ORDER,
					'post_status'   => $post_status,
					'post_title'    => $order_key,
					'post_date'     => wp_date( 'Y-m-d H:i:s', $date ),
					'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $date ),
					'post_author'   => (int) ( $data['user_id'] ?? 0 ),
				),
				$source,
				(string) ( $data['source_id'] ?? '' )
			);
		}

		$items = array();

		foreach ( (array) ( $data['items'] ?? array() ) as $item ) {
			if ( empty( $item['course_id'] ) ) {
				continue;
			}

			$items[] = array(
				'item_id'         => (int) $item['course_id'],
				'price'           => (float) ( $item['price'] ?? 0 ),
				'quantity'        => 1,
				'is_subscription' => 0,
			);
		}

		$status_map = array(
			'completed'  => 'completed',
			'complete'   => 'completed',
			'publish'    => 'completed',
			'paid'       => 'completed',
			'processing' => 'processing',
			'pending'    => 'pending',
			'on-hold'    => 'on-hold',
			'cancelled'  => 'cancelled',
			'canceled'   => 'cancelled',
			'failed'     => 'failed',
			'refunded'   => 'refunded',
		);

		$total = (float) ( $data['total'] ?? array_sum( array_column( $items, 'price' ) ) );

		update_post_meta( $order_id, 'user_id', (int) ( $data['user_id'] ?? 0 ) );
		update_post_meta( $order_id, 'items', $items );
		update_post_meta( $order_id, 'date', $date );
		update_post_meta( $order_id, 'status', $status_map[ strtolower( (string) ( $data['status'] ?? 'pending' ) ) ] ?? 'pending' );
		update_post_meta( $order_id, 'payment_code', (string) ( $data['payment_code'] ?? 'cash' ) );
		update_post_meta( $order_id, '_order_total', $total );
		update_post_meta( $order_id, '_order_subtotal', (float) ( $data['subtotal'] ?? $total ) );
		update_post_meta( $order_id, '_order_taxes', (float) ( $data['taxes'] ?? 0 ) );

		if ( ! metadata_exists( 'post', $order_id, 'order_key' ) ) {
			update_post_meta( $order_id, 'order_key', get_post_field( 'post_title', $order_id ) === $order_key ? $order_key : uniqid( (string) ( $data['user_id'] ?? 0 ) . $date ) );
		}

		if ( ! empty( $data['currency'] ) ) {
			update_post_meta( $order_id, '_order_currency', (string) $data['currency'] );
		}

		if ( ! empty( $data['transaction_id'] ) ) {
			update_post_meta( $order_id, 'transaction_id', (string) $data['transaction_id'] );
		}

		$order_items = $wpdb->prefix . 'stm_lms_order_items';
		$wpdb->delete( $order_items, array( 'order_id' => $order_id ), array( '%d' ) );

		foreach ( $items as $item ) {
			$wpdb->insert(
				$order_items,
				array(
					'order_id'  => $order_id,
					'object_id' => $item['item_id'],
					'quantity'  => 1,
					'price'     => $item['price'],
				),
				array( '%d', '%d', '%d', '%f' )
			);
		}

		return $order_id;
	}

	/**
	 * Buyer details and admin note of a MasterStudy order (`personal_data`, `order_note`).
	 *
	 * @param array  $personal_data Keys: country, post_code, state, city, company, phone (empty values are skipped).
	 * @param string $note          Order note text ('' = unchanged).
	 */
	public static function set_order_details( int $order_id, array $personal_data, string $note = '' ): void {
		$data = array();

		foreach ( array( 'country', 'post_code', 'state', 'city', 'company', 'phone' ) as $key ) {
			$value = trim( sanitize_text_field( (string) ( $personal_data[ $key ] ?? '' ) ) );

			if ( '' !== $value ) {
				$data[ $key ] = $value;
			}
		}

		if ( ! empty( $data ) ) {
			update_post_meta( $order_id, 'personal_data', $data );
		}

		if ( '' !== trim( $note ) ) {
			update_post_meta( $order_id, 'order_note', sanitize_textarea_field( $note ) );
		}
	}

	/**
	 * Fill the MasterStudy checkout personal data of a user (`masterstudy_personal_data` user meta) when it is empty.
	 *
	 * @param array $personal_data Keys: country, post_code, state, city, company, phone.
	 */
	public static function set_user_personal_data( int $user_id, array $personal_data ): void {
		$current = get_user_meta( $user_id, 'masterstudy_personal_data', true );

		if ( ! empty( $current ) ) {
			return;
		}

		$data = array();

		foreach ( array( 'country', 'post_code', 'state', 'city', 'company', 'phone' ) as $key ) {
			$value = trim( sanitize_text_field( (string) ( $personal_data[ $key ] ?? '' ) ) );

			if ( '' !== $value ) {
				$data[ $key ] = $value;
			}
		}

		if ( ! empty( $data ) ) {
			static::track_user_meta( $user_id, 'masterstudy_personal_data' );
			update_user_meta( $user_id, 'masterstudy_personal_data', $data );
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Reviews, wishlist, discussions
	|--------------------------------------------------------------------------
	*/

	/**
	 * Create a MasterStudy review. Idempotent per (source, source_id).
	 *
	 * @param array $data Keys: course_id, user_id, mark (1-5), content, date (MySQL, site time),
	 *                    approved (bool), source_id, post_status (optional override, e.g. 'trash').
	 */
	public static function add_review( array $data, string $source ): int {
		$course_id = (int) $data['course_id'];
		$user_id   = (int) $data['user_id'];
		$user      = get_userdata( $user_id );

		$postarr = array(
			'post_type'    => PostType::REVIEW,
			// Optional 'post_status' (e.g. 'trash' for spam/trashed source reviews) overrides the approved flag.
			'post_status'  => ! empty( $data['post_status'] ) ? (string) $data['post_status'] : ( ! empty( $data['approved'] ) ? 'publish' : 'pending' ),
			/* translators: 1: course title, 2: user name */
			'post_title'   => sprintf( __( 'Review on %1$s by %2$s', 'masterstudy-lms-learning-management-system' ), get_post_field( 'post_title', $course_id ), $user ? $user->display_name : '' ),
			'post_content' => wp_kses_post( (string) ( $data['content'] ?? '' ) ),
			'post_author'  => $user_id,
		);

		if ( ! empty( $data['date'] ) ) {
			$postarr['post_date'] = $data['date'];
		}

		$review_id = static::insert_post( $postarr, $source, (string) ( $data['source_id'] ?? '' ) );

		update_post_meta( $review_id, 'review_course', $course_id );
		update_post_meta( $review_id, 'review_user', $user_id );
		update_post_meta( $review_id, 'review_mark', max( 1, min( 5, (int) round( (float) $data['mark'] ) ) ) );

		return $review_id;
	}

	/**
	 * Rebuild course rating caches (course_marks, course_mark_average) from published reviews,
	 * and instructor rating user meta. Run once after the reviews step.
	 */
	public static function recalculate_ratings(): void {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.meta_value AS course_id, u.meta_value AS user_id, m.meta_value AS mark
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} c ON c.post_id = p.ID AND c.meta_key = 'review_course'
				 INNER JOIN {$wpdb->postmeta} u ON u.post_id = p.ID AND u.meta_key = 'review_user'
				 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'review_mark'
				 WHERE p.post_type = %s AND p.post_status = 'publish'",
				PostType::REVIEW
			)
		);

		$marks = array();

		foreach ( (array) $rows as $row ) {
			$marks[ (int) $row->course_id ][ (int) $row->user_id ] = (int) $row->mark;
		}

		$instructors = array();

		foreach ( $marks as $course_id => $course_marks ) {
			update_post_meta( $course_id, 'course_marks', $course_marks );
			update_post_meta( $course_id, 'course_mark_average', round( array_sum( $course_marks ) / count( $course_marks ), 1 ) );

			$author = (int) get_post_field( 'post_author', $course_id );

			if ( $author ) {
				$instructors[ $author ]['sum']   = ( $instructors[ $author ]['sum'] ?? 0 ) + array_sum( $course_marks );
				$instructors[ $author ]['total'] = ( $instructors[ $author ]['total'] ?? 0 ) + count( $course_marks );
			}
		}

		foreach ( $instructors as $user_id => $rating ) {
			update_user_meta( $user_id, 'sum_rating', $rating['sum'] );
			update_user_meta( $user_id, 'total_reviews', $rating['total'] );
			update_user_meta( $user_id, 'average_rating', round( $rating['sum'] / $rating['total'], 2 ) );
			delete_transient( "stm_lms_instructor_{$user_id}_rating" );
		}
	}

	/**
	 * Add courses to a user's wishlist.
	 *
	 * @param int[] $course_ids Course IDs.
	 */
	public static function add_to_wishlist( int $user_id, array $course_ids ): void {
		$current = get_user_meta( $user_id, 'stm_lms_wishlist', true );
		$current = is_array( $current ) ? $current : array();
		$merged  = array_values( array_unique( array_filter( array_map( 'intval', array_merge( $current, $course_ids ) ) ) ) );

		update_user_meta( $user_id, 'stm_lms_wishlist', $merged );
	}

	/**
	 * Add a lesson discussion comment (MasterStudy stores them as default comments on the lesson post).
	 *
	 * @param array $data Keys: post_id, user_id, content, date (MySQL site time), date_gmt, parent, approved,
	 *                    status (optional comment_approved override: '0', '1', 'spam', 'trash').
	 * @return int Comment ID.
	 */
	public static function add_discussion( array $data ): int {
		$user = get_userdata( (int) $data['user_id'] );

		$comment = array(
			'comment_post_ID'  => (int) $data['post_id'],
			'comment_content'  => wp_kses_post( (string) $data['content'] ),
			'comment_parent'   => (int) ( $data['parent'] ?? 0 ),
			'user_id'          => (int) $data['user_id'],
			'comment_author'   => $user ? $user->display_name : '',
			'comment_author_email' => $user ? $user->user_email : '',
			// Optional 'status' ('spam' / 'trash' / '0' / '1') overrides the approved flag.
			'comment_approved' => isset( $data['status'] ) && in_array( (string) $data['status'], array( '0', '1', 'spam', 'trash' ), true ) ? (string) $data['status'] : ( isset( $data['approved'] ) ? (int) (bool) $data['approved'] : 1 ),
			'comment_type'     => 'comment',
		);

		if ( ! empty( $data['date'] ) ) {
			$comment['comment_date'] = $data['date'];
		}

		if ( ! empty( $data['date_gmt'] ) ) {
			$comment['comment_date_gmt'] = $data['date_gmt'];
		}

		return (int) wp_insert_comment( wp_slash( $comment ) );
	}

	/**
	 * Lesson and meeting materials of a course (items completed through stm_lms_user_lessons).
	 *
	 * @return int[]
	 */
	public static function lesson_material_ids( int $course_id ): array {
		global $wpdb;

		return array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT m.post_id FROM {$wpdb->prefix}stm_lms_curriculum_materials m
					 INNER JOIN {$wpdb->prefix}stm_lms_curriculum_sections s ON s.id = m.section_id
					 WHERE s.course_id = %d AND m.post_type IN ( %s, %s )",
					$course_id,
					PostType::LESSON,
					PostType::GOOGLE_MEET
				)
			)
		);
	}

	/**
	 * Course IDs a curriculum post (lesson/quiz/assignment) belongs to.
	 *
	 * @return int[]
	 */
	public static function course_ids_of( int $post_id ): array {
		global $wpdb;

		return array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT s.course_id FROM {$wpdb->prefix}stm_lms_curriculum_materials m
					 INNER JOIN {$wpdb->prefix}stm_lms_curriculum_sections s ON s.id = m.section_id
					 WHERE m.post_id = %d",
					$post_id
				)
			)
		);
	}
}
