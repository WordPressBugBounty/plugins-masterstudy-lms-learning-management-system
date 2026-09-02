<?php

namespace MasterStudy\Lms\Utility;

final class Wpml {
	private static ?array $active_languages = null;

	public static function is_enabled(): bool {
		return defined( 'ICL_SITEPRESS_VERSION' );
	}

	/**
	 * @return array{
	 *     enabled: bool,
	 *     current_language: string,
	 *     default_language: string,
	 *     languages: array<int, array{code: string, name: string, flag_url: string}>
	 * }
	 */
	public static function settings( string $requested_language = '' ): array {
		$current_language = self::is_enabled() ? (string) apply_filters( 'wpml_current_language', null ) : '';

		if ( self::is_enabled() && self::is_valid_language_code( $requested_language ) ) {
			$current_language = $requested_language;
		}

		return array(
			'enabled'          => self::is_enabled(),
			'current_language' => $current_language,
			'default_language' => self::is_enabled() ? (string) apply_filters( 'wpml_default_language', null ) : '',
			'languages'        => self::get_active_languages(),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function query_language_args( string $language_code ): array {
		if ( ! self::is_enabled() || ! self::is_valid_language_code( $language_code ) ) {
			return array();
		}

		if ( 'all' === $language_code ) {
			return array( 'suppress_wpml_where_and_join_filter' => true );
		}

		return array( 'lang' => $language_code );
	}

	/**
	 * @return mixed
	 */
	public static function with_language( string $language_code, callable $callback ) {
		if (
			! self::is_enabled()
			|| 'all' === $language_code
			|| ! self::is_valid_language_code( $language_code )
		) {
			return $callback();
		}

		$current_language = (string) apply_filters( 'wpml_current_language', null );

		if ( $language_code === $current_language ) {
			return $callback();
		}

		do_action( 'wpml_switch_language', $language_code );

		try {
			return $callback();
		} finally {
			do_action( 'wpml_switch_language', $current_language );
		}
	}

	/**
	 * @return array{
	 *     language_code: string,
	 *     translations: array<string, array{status: string, post_id: int, url: string}>
	 * }|null
	 */
	public static function post_translations( int $post_id, string $post_type ): ?array {
		if (
			! self::is_enabled()
			|| ! apply_filters( 'wpml_is_translated_post_type', false, $post_type )
		) {
			return null;
		}

		$element_type = 'post_' . $post_type;
		$language_code = (string) apply_filters(
			'wpml_element_language_code',
			null,
			array(
				'element_id'   => $post_id,
				'element_type' => $element_type,
			)
		);
		$trid          = (int) apply_filters( 'wpml_element_trid', null, $post_id, $element_type );
		$translations  = $trid
			? apply_filters( 'wpml_get_element_translations', null, $trid, $element_type )
			: array();
		$translations  = is_array( $translations ) ? $translations : array();
		$allowed_codes = self::get_allowed_language_codes( $post_id, $post_type );
		$result        = array();

		foreach ( self::get_active_languages() as $language ) {
			$code        = $language['code'];
			$translation = $translations[ $code ] ?? null;
			$translated_id = is_object( $translation ) && isset( $translation->element_id )
				? absint( $translation->element_id )
				: 0;

			if ( $code === $language_code ) {
				$result[ $code ] = array(
					'status'  => 'current',
					'post_id' => $post_id,
					'url'     => '',
				);
				continue;
			}

			$can_translate = isset( $allowed_codes[ $code ] );
			$status        = $translated_id ? 'translated' : 'missing';
			$url           = '';

			if ( $translated_id && $can_translate && current_user_can( 'edit_post', $translated_id ) ) {
				$url = self::get_edit_translation_url( $translated_id, $post_type, $code );
			} elseif ( ! $translated_id && $trid && $can_translate && self::can_create_translation( $post_id, $post_type ) ) {
				$url = self::get_add_translation_url( $trid, $post_type, $code, $language_code );
			}

			$result[ $code ] = array(
				'status'  => $status,
				'post_id' => $translated_id,
				'url'     => $url,
			);
		}

		return array(
			'language_code' => $language_code,
			'translations'  => $result,
		);
	}

	/**
	 * @return array<int, array{code: string, name: string, flag_url: string}>
	 */
	private static function get_active_languages(): array {
		if ( null !== self::$active_languages ) {
			return self::$active_languages;
		}

		self::$active_languages = array();

		if ( ! self::is_enabled() ) {
			return self::$active_languages;
		}

		global $sitepress;

		$languages = is_object( $sitepress ) && method_exists( $sitepress, 'get_active_languages' )
			? $sitepress->get_active_languages()
			: array();

		if ( ! is_array( $languages ) ) {
			$languages = apply_filters(
				'wpml_active_languages',
				null,
				array(
					'skip_missing' => 0,
					'orderby'      => 'code',
				)
			);
		}

		$languages = is_array( $languages ) ? $languages : array();

		foreach ( $languages as $key => $language ) {
			if ( ! is_array( $language ) ) {
				continue;
			}

			$code = sanitize_key( (string) ( $language['code'] ?? $key ) );

			if ( '' === $code ) {
				continue;
			}

			self::$active_languages[] = array(
				'code'     => $code,
				'name'     => (string) ( $language['display_name'] ?? $language['translated_name'] ?? $language['native_name'] ?? $code ),
				'flag_url' => self::get_flag_url( $code, $language ),
			);
		}

		return self::$active_languages;
	}

	private static function is_valid_language_code( string $language_code ): bool {
		if ( 'all' === $language_code ) {
			return true;
		}

		foreach ( self::get_active_languages() as $language ) {
			if ( $language['code'] === $language_code ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<string, mixed> $language
	 */
	private static function get_flag_url( string $code, array $language ): string {
		$flag_url = (string) ( $language['country_flag_url'] ?? '' );

		if ( '' !== $flag_url ) {
			return esc_url_raw( $flag_url );
		}

		global $sitepress;

		return is_object( $sitepress ) && method_exists( $sitepress, 'get_flag_url' )
			? esc_url_raw( (string) $sitepress->get_flag_url( $code ) )
			: '';
	}

	/**
	 * @return array<string, bool>
	 */
	private static function get_allowed_language_codes( int $post_id, string $post_type ): array {
		$languages = array();

		foreach ( self::get_active_languages() as $language ) {
			$languages[ $language['code'] ] = array( 'code' => $language['code'] );
		}

		$allowed = apply_filters(
			'wpml_active_languages_access',
			$languages,
			array(
				'action'    => 'edit',
				'post_type' => $post_type,
				'post_id'   => $post_id,
			)
		);

		if ( ! is_array( $allowed ) ) {
			return array();
		}

		$codes = array();

		foreach ( $allowed as $key => $language ) {
			$code = is_array( $language ) ? (string) ( $language['code'] ?? $key ) : (string) $key;

			if ( '' !== $code ) {
				$codes[ $code ] = true;
			}
		}

		return $codes;
	}

	private static function can_create_translation( int $post_id, string $post_type ): bool {
		$post_type_object = get_post_type_object( $post_type );

		if ( ! $post_type_object || ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}

		return current_user_can( $post_type_object->cap->create_posts );
	}

	private static function get_edit_translation_url( int $post_id, string $post_type, string $language_code ): string {
		return esc_url_raw(
			add_query_arg(
				array(
					'lang'      => $language_code,
					'action'    => 'edit',
					'post_type' => $post_type,
					'post'      => $post_id,
				),
				admin_url( 'post.php' )
			)
		);
	}

	private static function get_add_translation_url( int $trid, string $post_type, string $target_language, string $source_language ): string {
		return esc_url_raw(
			add_query_arg(
				array(
					'lang'        => $target_language,
					'post_type'   => $post_type,
					'trid'        => $trid,
					'source_lang' => $source_language,
				),
				admin_url( 'post-new.php' )
			)
		);
	}
}
