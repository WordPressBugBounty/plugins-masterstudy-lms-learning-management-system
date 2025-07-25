<?php

use MasterStudy\Lms\Plugin\PostType;
use MasterStudy\Lms\Pro\addons\CourseBundle\Repository\CourseBundleRepository;
use MasterStudy\Lms\Repositories\CourseRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use \MasterStudy\Lms\Repositories\CurriculumMaterialRepository;

function stm_lms_str_replace_once( $str_pattern, $str_replacement, $string ) {
	if ( strpos( $string, $str_pattern ) !== false ) {
		$occurrence = strpos( $string, $str_pattern );

		return substr_replace( $string, $str_replacement, strpos( $string, $str_pattern ), strlen( $str_pattern ) );
	}

	return $string;
}

function stm_lms_get_wpml_binded_id( $id ) {

	if ( ! is_numeric( $id ) ) {
		return $id;
	}

	if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
		$binded_id = apply_filters( 'wpml_object_id', $id, get_post_type( $id ), false, ICL_LANGUAGE_CODE );
		if ( ! empty( $binded_id ) ) {
			return $binded_id;
		}
	}

	return $id;
}

function stm_lms_time_elapsed_string( $datetime, $full = false ) {
	$now  = new DateTime();
	$ago  = new DateTime( $datetime );
	$diff = $now->diff( $ago );

	$weeks    = floor( $diff->d / 7 );
	$diff->d -= $weeks * 7;

	$string = array(
		'y' => esc_html__( 'year', 'masterstudy-lms-learning-management-system' ),
		'm' => esc_html__( 'month', 'masterstudy-lms-learning-management-system' ),
		'w' => esc_html__( 'week', 'masterstudy-lms-learning-management-system' ),
		'd' => esc_html__( 'day', 'masterstudy-lms-learning-management-system' ),
		'h' => esc_html__( 'hour', 'masterstudy-lms-learning-management-system' ),
		'i' => esc_html__( 'minute', 'masterstudy-lms-learning-management-system' ),
		's' => esc_html__( 'second', 'masterstudy-lms-learning-management-system' ),
	);

	foreach ( $string as $k => &$v ) {
		$value = ( 'w' === $k ) ? $weeks : $diff->$k;

		if ( $value ) {
			$v = masterstudy_lms_time_elapsed_string_e( $value, $k );
		} else {
			unset( $string[ $k ] );
		}
	}

	if ( ! $full ) {
		$string = array_slice( $string, 0, 1 );
	}

	return $string ? sprintf(
	/* translators: %s: nubmer */
		esc_html__( '%s ago', 'masterstudy-lms-learning-management-system' ),
		implode( ', ', $string )
	) : esc_html__( 'just now', 'masterstudy-lms-learning-management-system' );
}

function masterstudy_lms_time_elapsed_string_e( $number, $time_key ) {
	$translate = '';

	switch ( $time_key ) {
		case 'y':
			/* translators: %s: nubmer */
			$translate = _n( '%s year', '%s years', $number, 'masterstudy-lms-learning-management-system' );
			break;
		case 'm':
			/* translators: %s: nubmer */
			$translate = _n( '%s month', '%s months', $number, 'masterstudy-lms-learning-management-system' );
			break;
		case 'w':
			/* translators: %s: nubmer */
			$translate = _n( '%s week', '%s weeks', $number, 'masterstudy-lms-learning-management-system' );
			break;
		case 'd':
		case 'days':
			/* translators: %s: nubmer */
			$translate = _n( '%s day', '%s days', $number, 'masterstudy-lms-learning-management-system' );
			break;
		case 'h':
		case 'hours':
			/* translators: %s: nubmer */
			$translate = _n( '%s hour', '%s hours', $number, 'masterstudy-lms-learning-management-system' );
			break;
		case 'i':
		case 'minutes':
			/* translators: %s: nubmer */
			$translate = _n( '%s minute', '%s minutes', $number, 'masterstudy-lms-learning-management-system' );
			break;
		case 's':
			/* translators: %s: nubmer */
			$translate = _n( '%s second', '%s seconds', $number, 'masterstudy-lms-learning-management-system' );
			break;
	}

	return $translate ? sprintf( $translate, $number ) : $translate;

}

function stm_lms_register_style( $style, $deps = array(), $inline_css = '' ) {
	$default_path = STM_LMS_URL . 'assets/css/parts/';
	if ( stm_lms_has_custom_colors() ) {
		$default_path = stm_lms_custom_styles_url() . '/stm_lms_styles/parts/';
	}

	wp_enqueue_style( 'stm-lms-' . $style, $default_path . $style . '.css', $deps, stm_lms_custom_styles_v() );

	if ( ! empty( $inline_css ) ) {
		wp_add_inline_style( 'stm-lms-' . $style, $inline_css );
	}
}

function stm_lms_register_script( $script, $deps = array(), $footer = false, $inline_scripts = '' ) {
	if ( ! stm_lms_is_masterstudy_theme() ) {
		wp_enqueue_script( 'jquery' );
	}

	$handle = "stm-lms-{$script}";
	wp_enqueue_script( $handle, STM_LMS_URL . 'assets/js/' . $script . '.js', $deps, stm_lms_custom_styles_v(), $footer );
	if ( ! empty( $inline_scripts ) ) {
		wp_add_inline_script( $handle, $inline_scripts );
	}
}

function stm_lms_module_styles( $handle, $style = 'style_1', $deps = array(), $inline_styles = '' ) {
	$path = STM_LMS_URL . 'assets/css/vc_modules/' . $handle . '/' . $style . '.css';
	if ( stm_lms_has_custom_colors() ) {
		$path = stm_lms_custom_styles_url() . '/stm_lms_styles/vc_modules/' . $handle . '/' . $style . '.css';
	}
	$handle = 'stm-' . $handle . '-' . $style;
	wp_enqueue_style( $handle, $path, $deps, stm_lms_custom_styles_v(), 'all' );

	if ( ! empty( $inline_styles ) ) {
		wp_add_inline_style( $handle, $inline_styles );
	}
}

function stm_lms_module_scripts( $handle, $style = 'style_1', $deps = array( 'jquery' ), $folder = 'js' ) {
	$path = STM_LMS_URL . 'assets/' . $folder . '/vc_modules/' . $handle . '/' . $style . '.js';
	wp_enqueue_script( 'stm-' . $handle, $path, $deps, stm_lms_custom_styles_v(), 'all' );
}


add_action( 'after_setup_theme', 'stm_lms_plugin_setups' );

function stm_lms_plugin_setups() {
	add_image_size( 'img-1100-450', 1100, 450, true );
	add_image_size( 'img-1120-800', 1120, 800, true );
	add_image_size( 'img-870-440', 870, 440, true );
	add_image_size( 'img-850-600', 850, 600, true );
	add_image_size( 'img-480-380', 480, 380, true );
	add_image_size( 'img-300-225', 300, 225, true );
	add_image_size( 'img-540-700', 540, 700, true );
	add_image_size( 'img-75-75', 75, 75, true );
}

add_action( 'admin_init', 'stm_lms_instructors' );

function stm_lms_instructors() {
	add_role(
		'stm_lms_instructor',
		__( 'Instructor', 'masterstudy-lms-learning-management-system' ),
		array(
			'read'                        => true,
			'upload_files'                => true,
			'publish_stm_lms_posts'       => true,
			'edit_stm_lms_posts'          => true,
			'delete_stm_lms_posts'        => true,
			'edit_stm_lms_post'           => true,
			'delete_stm_lms_post'         => true,
			'read_stm_lms_posts'          => true,
			'list_users'                  => true,
			'delete_others_stm_lms_posts' => false,
			'edit_others_stm_lms_posts'   => false,
			'read_private_stm_lms_posts'  => false,
		)
	);
}

function stm_lms_get_terms_array( $id, $taxonomy, $filter, $link = false, $args = '' ) {
	$terms = wp_get_post_terms( $id, $taxonomy );

	if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
		if ( $link ) {
			$links = array();
			if ( ! empty( $args ) ) {
				$args = stm_lms_array_as_string( $args );
			}
			foreach ( $terms as $term ) {
				$url     = get_term_link( $term );
				$links[] = "<a {$args} href='{$url}' title='{$term->name}'>{$term->name}</a>";
			}
			$terms = $links;
		} else {
			$terms = wp_list_pluck( $terms, $filter );
		}
	} else {
		$terms = array();
	}

	return apply_filters( 'pearl_get_terms_array', $terms );
}

function stm_lms_array_as_string( $arr ) {
	$r = implode( ' ', array_map( 'stm_lms_array_map', $arr, array_keys( $arr ) ) );

	return $r;
}

function stm_lms_array_map( $v, $k ) {
	return $k . '="' . $v . '"';
}

function stm_lms_minimize_word( $word, $length = '40', $affix = '...' ) {
	if ( ! empty( intval( $length ) ) ) {
		$w_length = mb_strlen( $word );
		if ( $w_length > $length ) {
			$word = mb_strimwidth( $word, 0, $length, $affix );
		}
	}

	return sanitize_text_field( $word );
}

function stm_lms_has_custom_colors() {
	$main_color      = STM_LMS_Options::get_option( 'main_color', '' );
	$secondary_color = STM_LMS_Options::get_option( 'secondary_color', '' );

	return ( ! empty( $main_color ) || ! empty( $secondary_color ) );
}

function stm_lms_custom_styles_url( $get_dir = false ) {
	$upload     = wp_upload_dir();
	$upload_url = $upload['baseurl'] ?? '';
	if ( is_ssl() ) {
		$upload_url = str_replace( 'http://', 'https://', $upload_url );
	}
	if ( $get_dir ) {
		$upload_url = $upload['basedir'];
	}

	return $upload_url;
}

function stm_lms_custom_styles_v() {
	return ( WP_DEBUG ) ? time() : get_option( 'stm_lms_styles_v', MS_LMS_VERSION );
}

add_filter( 'vc_iconpicker-type-fontawesome', 'stm_lms_add_vc_icons' );

function stm_lms_add_vc_icons( $fonts ) {
	if ( empty( $fonts ) ) {
		$fonts = array();
	}

	$icons = json_decode( file_get_contents( STM_LMS_PATH . '/assets/icons/selection.json', true ), true );
	$icons = $icons['icons'];

	$fonts['STM LMS Icons'] = array();

	foreach ( $icons as $icon ) {
		$icon_name                = $icon['properties']['name'];
		$fonts['STM LMS Icons'][] = array(
			"stmlms-{$icon_name}" => $icon_name,
		);
	}

	return $fonts;
}

function stm_lms_remove_pmpro_account_shortcode() {
	?>

	<script type="text/javascript">
		var locationUrl = "<?php echo esc_url( STM_LMS_User::user_page_url() ); ?>" + window.location.hash;
		if (window.location.href !== locationUrl) window.location = locationUrl;
	</script>
	<?php
}

add_filter( 'body_class', 'stm_lms_body_class', 1, 100 );

function pmpro_account_redirect() {
	if ( function_exists( 'pmpro_url' ) ) {
		if ( get_permalink() === pmpro_url( 'account' ) ) {
			wp_safe_redirect( STM_LMS_User::my_pmpro_url() );
			exit();
		}
	}
}
add_action( 'template_redirect', 'pmpro_account_redirect' );

function stm_lms_body_class( $classes ) {
	$classes[] = 'stm_lms_' . STM_LMS_Options::get_option( 'load_more_type', 'button' );

	return $classes;
}

/**
 * @param $file
 * @param array $args
 * @param null  $show default null
 *
 * @return string
 */
function stm_lms_render( $file, $args = array(), $show = null ) {
	$file .= '.php';
	if ( ! file_exists( $file ) ) {
		return '';
	}
	if ( is_array( $args ) ) {
		extract( $args );
	}
	ob_start();
	include $file;
	if ( ! $show ) {
		return ob_get_clean();
	}
	$allowed_tags = stm_lms_allowed_html();
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo ob_get_clean();
}


/**
 * @param $content
 *
 * @return string
 */
function stm_lms_convert_content( $content ) {
	return trim( preg_replace( '/\s\s+/', ' ', addslashes( $content ) ) );
}

/**
 * @return bool
 */
function stm_lms_is_https() {
	return ( isset( $_SERVER['HTTPS'] ) && 'on' === strtolower( $_SERVER['HTTPS'] ) ) ? true : false;
}

function rand_color( $opacity = 1 ) {
	return 'rgba(' . wp_rand( 0, 255 ) . ', ' . wp_rand( 50, 255 ) . ', ' . wp_rand( 10, 255 ) . ', ' . $opacity . ')';
}

/* TODO: This can be deleted once deprecated widgets are removed from the theme */
function stm_lms_lazyload_image( $image ) {
	if ( ! function_exists( 'stm_conf_layload_image' ) ) {
		return $image;
	}

	return stm_conf_layload_image( $image );
}

function masterstudy_get_image( $post_id, $lazyload = false, $class = null, $width = null, $height = null ) {
	$width     = ! empty( $width ) ? $width : 330;
	$height    = ! empty( $height ) ? $height : 185;
	$image_url = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), array( $width, $height ) );
	$image_url = ( ! empty( $image_url ) ) ? $image_url[0] : '';
	$image     = '<img src="' . esc_url( $image_url ?? '' ) . '" class="' . esc_attr( $class ?? '' ) . '">';

	if ( $lazyload ) {
		$image_wrapper  = '<div class="masterstudy-lazyload-image">';
		$image_wrapper .= str_replace(
			array( 'sizes="', 'srcset="', 'src="', 'class="' ),
			array( 'data-sizes="', 'data-srcset="', 'data-src="', 'class="lazyload ' ),
			$image
		);
		$image_wrapper .= '</div>';

		return $image_wrapper;
	}

	return $image;
}

function masterstudy_get_image_size( $option ) {
	return preg_match( '/^(\d+)[xх](\d+)$/ui', $option, $matches )
		? array( (int) $matches[1], (int) $matches[2] )
		: array( null, null );
}

function masterstudy_lms_wrap_timecode( $content ) {
	$pattern = '/\{(.*?)((?:\d{1,2}:\d{1,2}(?::\d{1,2})?))(.*?)\}/u';
	$content = preg_replace_callback(
		$pattern,
		function( $matches ) {
			$pre_text      = wp_kses_post( trim( $matches[1] ) );
			$timecode      = $matches[2];
			$post_text     = wp_kses_post( trim( $matches[3] ) );
			$time_parts    = explode( ':', $timecode );
			$hours         = count( $time_parts ) === 3 ? (int) $time_parts[0] : 0;
			$minutes       = count( $time_parts ) === 3 ? (int) $time_parts[1] : (int) $time_parts[0];
			$seconds       = count( $time_parts ) === 3 ? (int) $time_parts[2] : (int) $time_parts[1];
			$total_seconds = $hours * 3600 + $minutes * 60 + $seconds;

			$timecode_span  = '<span class="masterstudy-timecode" data-timecode="' . $total_seconds . '">';
			$timecode_span .= $pre_text . ' <span class="masterstudy-timecode__value">' . $timecode . '</span>' . $post_text;
			$timecode_span .= '</span>';

			return $timecode_span;
		},
		$content
	);

	return $content;
}

function stm_lms_get_string_between( $str, $start_delimiter, $end_delimiter ) {
	$contents               = array();
	$start_delimiter_length = strlen( $start_delimiter );
	$end_delimiter_length   = strlen( $end_delimiter );
	$content_end            = 0;
	$content_start          = $content_end;
	$start_from             = $content_start;
	// phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
	while ( false !== ( $content_start = strpos( $str, $start_delimiter, $start_from ) ) ) {
		$content_start += $start_delimiter_length;
		$content_end    = strpos( $str, $end_delimiter, $content_start );
		if ( false === $content_end ) {
			break;
		}
		$contents[] = array(
			'start'  => $content_start,
			'end'    => $content_end,
			'answer' => substr( $str, $content_start, $content_end - $content_start ),
		);
		$start_from = $content_end + $end_delimiter_length;
	}

	return $contents;
}

function stm_lms_str_replace_first( $from, $to, $content ) {
	$from = '/' . preg_quote( $from, '/' ) . '/';

	return preg_replace( $from, $to, $content, 1 );
}

function stm_lms_filtered_output( $data ) {
	return apply_filters( 'stm_lms_filter_output', $data );
}

function stm_lms_return( $params ) {
	return $params;
}

add_filter(
	'wpml_active_languages',
	function ( $langs ) {

		if ( empty( get_queried_object() ) && ! empty( $langs ) ) {

			$current_url = STM_LMS_Helpers::get_current_url();
			$home_url    = get_home_url();

			$sub_path = str_replace( $home_url, '', $current_url );

			foreach ( $langs as &$lang ) {
				if ( $lang['active'] ) {
					continue;
				}
				$lang['url'] = $lang['url'] . $sub_path;
			}
		};

		return $langs;

	}
);

/**
 * @deprecated deprecated since version 3.0.0
 */
function stm_lms_course_files_data() {
	return array();
}

/**
 * @deprecated deprecated since version 3.0.0
 */
function stm_lms_lesson_files_data() {
	return array();
}

function stm_lms_get_image_url( $id, $size = 'full' ) {
	$url = '';
	if ( ! empty( $id ) ) {
		$image = wp_get_attachment_image_src( $id, $size, false );
		if ( ! empty( $image[0] ) ) {
			$url = $image[0];
		}
	}

	return $url;
}

function stm_lms_autocomplete_terms( $taxonomy = '' ) {
	$r = array();
	if ( is_admin() ) {
		$args = array(
			'hide_empty' => false,
		);
		if ( ! empty( $taxonomy ) ) {
			$args['taxonomy'] = $taxonomy;
		}
		$terms = get_terms( $args );

		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			foreach ( $terms as $term ) {
				$r[] = array(
					'label' => $term->name,
					'value' => $term->term_id,
				);
			}
		}
	}

	return apply_filters( 'stm_autocomplete_terms', $r );
}

function stm_lms_autocomplete_bundles_terms() {
	$r = array();
	if ( is_admin() && class_exists( 'MasterStudy\Lms\Pro\addons\CourseBundle\Repository\CourseBundleRepository' ) ) {
		$bundles = ( new CourseBundleRepository() )->get_bundles(
			array(
				'posts_per_page' => - 1,
				'author'         => '',
			)
		);

		foreach ( $bundles['posts'] as $value ) {
			$r[] = array(
				'label' => $value['title'],
				'value' => $value['id'],
			);
		}
	}

	return apply_filters( 'stm_autocomplete_terms', $r );
}

function stm_lms_elementor_autocomplete_bundles() {
	$r = array();
	if ( is_admin() && class_exists( 'MasterStudy\Lms\Pro\addons\CourseBundle\Repository\CourseBundleRepository' ) ) {
		$bundles = ( new CourseBundleRepository() )->get_bundles(
			array(
				'posts_per_page' => - 1,
				'author'         => '',
			)
		);

		foreach ( $bundles['posts'] as $value ) {
			$r[ $value['id'] ] = $value['title'];
		}
	}

	return apply_filters( 'stm_autocomplete_terms', $r );
}

function stm_lms_elementor_autocomplete_terms( $taxonomy = '' ) {
	$r = array();
	if ( is_admin() ) {
		$args = array(
			'hide_empty' => false,
		);
		if ( ! empty( $taxonomy ) ) {
			$args['taxonomy'] = $taxonomy;
		}
		$terms = get_terms( $args );

		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			foreach ( $terms as $term ) {
				$r[ $term->term_id ] = $term->name;
			}
		}
	}

	return apply_filters( 'stm_autocomplete_terms', $r );
}

function stm_lms_create_unique_id( $atts ) {
	return 'module__' . md5( serialize( $atts ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
}

function stm_lms_get_VC_attachment_img_safe( $attachment_id, $size_1, $size_2 = 'large', $url = false, $retina = true ) {
	if ( function_exists( 'stm_lms_get_VC_img' ) && function_exists( 'wpb_getImageBySize' ) && ! empty( $size_1 ) ) {
		$image = stm_lms_get_VC_img( $attachment_id, $size_1, $url );
	} else {
		if ( $url ) {
			$image = stm_lms_get_image_url( $attachment_id, $size_2 );
		} else {
			$image = wp_get_attachment_image( $attachment_id, $size_2 );
		}
	}
	if ( false === $retina && strpos( $image, 'srcset' ) !== false ) {
		$image = str_replace( 'srcset', 'data-retina', $image );
	}

	return $image;
}

function stm_lms_get_VC_img( $img_id, $img_size, $url = false ) {
	$image = '';
	if ( ! empty( $img_id ) && ! empty( $img_size ) && function_exists( 'wpb_getImageBySize' ) ) {
		$img = wpb_getImageBySize(
			array(
				'attach_id'  => $img_id,
				'thumb_size' => $img_size,
			)
		);

		if ( ! empty( $img['thumbnail'] ) ) {
			$image = $img['thumbnail'];

			if ( $url ) {
				$datas = array();
				preg_match( '/src="([^"]*)"/i', $image, $datas );
				if ( ! empty( $datas[1] ) ) {
					$image = $datas[1];
				} else {
					$image = '';
				}
			}
		}
	}

	return apply_filters( 'stm_lms_get_vc_img', $image );
}


function stm_lms_get_lms_terms_with_meta( $meta_key, $taxonomy = null, $args = array() ) {
	if ( empty( $taxonomy ) ) {
		$taxonomy = 'stm_lms_course_taxonomy';
	}

	$term_args = array(
		'taxonomy'   => $taxonomy,
		'hide_empty' => false,
		'fields'     => 'all',
	);

	if ( ! empty( $meta_key ) ) {
		$term_args['meta_key']     = $meta_key;
		$term_args['meta_value']   = '';
		$term_args['meta_compare'] = '!=';
	}

	$term_args = wp_parse_args( $args, $term_args );

	$term_query = new WP_Term_Query( $term_args );

	if ( empty( $term_query->terms ) ) {
		return false;
	}

	return $term_query->terms;
}

if ( ! function_exists( 'stm_option' ) ) {
	function stm_option( $id, $fallback = false, $key = false ) {
		global $stm_option;
		if ( false === $fallback ) {
			$fallback = '';
		}
		$output = ( isset( $stm_option[ $id ] ) && '' !== $stm_option[ $id ] ) ? $stm_option[ $id ] : $fallback;
		if ( ! empty( $stm_option[ $id ] ) && $key ) {
			$output = $stm_option[ $id ][ $key ];
		}

		return $output;
	}
}

function stm_lms_is_masterstudy_theme() {
	$theme_info = wp_get_theme();

	return ( $theme_info->get( 'TextDomain' ) === 'masterstudy' || $theme_info->get( 'TextDomain' ) === 'masterstudy-child' );
}

add_action(
	'admin_init',
	function () {
		stm_lms_register_style( 'admin/admin' );
	}
);

/**
 * @deprecated 3.0.0 Use {@see '\MasterStudy\Lms\Plugin\Addons::list()'} instead.
 */
function stm_lms_available_addons() {
	$available_addons = array(
		'udemy'                   => array(
			'name'          => esc_html__( 'Udemy Importer', 'masterstudy-lms-learning-management-system' ),
			'url'           => esc_url( STM_LMS_URL . 'assets/addons/udemy.png' ),
			'settings'      => admin_url( 'admin.php?page=stm-lms-udemy-settings' ),
			'description'   => esc_html__( 'Import courses from Udemy and display them on your website. Use ready-made courses on your platform and earn commissions.', 'masterstudy-lms-learning-management-system' ),
			'pro_url'       => 'https://stylemixthemes.com/wordpress-lms-plugin/pricing/?utm_source=wpadmin&utm_medium=ms-udemy&utm_campaign=masterstudy-plugin&licenses=1&billing_cycle=annual',
			'documentation' => 'udemy-course-importer',
		),
		'prerequisite'            => array(
			'name'          => esc_html__( 'Prerequisites', 'masterstudy-lms-learning-management-system' ),
			'url'           => esc_url( STM_LMS_URL . 'assets/addons/msp.png' ),
			'description'   => esc_html__( 'Set the requirements students must complete before they are able to enroll in the next course of a higher level.', 'masterstudy-lms-learning-management-system' ),
			'pro_url'       => 'https://stylemixthemes.com/wordpress-lms-plugin/pricing/?utm_source=wpadmin&utm_medium=ms-prerequisites&utm_campaign=masterstudy-plugin&licenses=1&billing_cycle=annual',
			'documentation' => 'prerequisites',
		),
		'online_testing'          => array(
			'name'          => esc_html__( 'Online Testing', 'masterstudy-lms-learning-management-system' ),
			'url'           => esc_url( STM_LMS_URL . 'assets/addons/mst.png' ),
			'settings'      => admin_url( 'admin.php?page=stm-lms-online-testing' ),
			'description'   => esc_html__( 'Easily paste any quizzes through the shortcode to any page and check the quizzes’ performance.', 'masterstudy-lms-learning-management-system' ),
			'pro_url'       => 'https://stylemixthemes.com/wordpress-lms-plugin/pricing/?utm_source=wpadmin&utm_medium=ms-onlinetestings&utm_campaign=masterstudy-plugin&licenses=1&billing_cycle=annual',
			'documentation' => 'online-testing',
		),
		'statistics'              => array(
			'name'          => esc_html__( 'Statistics and Payout', 'masterstudy-lms-learning-management-system' ),
			'url'           => esc_url( STM_LMS_URL . 'assets/addons/statistics.png' ),
			'settings'      => admin_url( 'admin.php?page=stm_lms_statistics' ),
			'description'   => esc_html__( 'Manage all payments and track affiliated statistics for the sold courses, such as Total Profit, Total Payments, and manage authors fee.', 'masterstudy-lms-learning-management-system' ),
			'pro_url'       => 'https://stylemixthemes.com/wordpress-lms-plugin/pricing/?utm_source=wpadmin&utm_medium=ms-payouts&utm_campaign=masterstudy-plugin&licenses=1&billing_cycle=annual',
			'documentation' => 'statistics-and-payouts',
		),
		'shareware'               => array(
			'name'          => esc_html__( 'Trial Courses', 'masterstudy-lms-learning-management-system' ),
			'url'           => esc_url( STM_LMS_URL . 'assets/addons/trial_courses.png' ),
			'settings'      => admin_url( 'admin.php?page=stm-lms-shareware' ),
			'description'   => esc_html__( 'Enable free trial lessons, so that your students could try some of the modules before taking the course.', 'masterstudy-lms-learning-management-system' ),
			'pro_url'       => 'https://stylemixthemes.com/wordpress-lms-plugin/pricing/?utm_source=wpadmin&utm_medium=ms-trial&utm_campaign=masterstudy-plugin&licenses=1&billing_cycle=annual',
			'documentation' => 'trial-courses',
		),
		'sequential_drip_content' => array(
			'name'          => esc_html__( 'Drip Content', 'masterstudy-lms-learning-management-system' ),
			'url'           => esc_url( STM_LMS_URL . 'assets/addons/sequential.png' ),
			'settings'      => admin_url( 'admin.php?page=sequential_drip_content' ),
			'description'   => esc_html__( 'Use this tool to provide a proper flow of the education process, regulate the sequence of the lessons, in order, by date or in your own sequence.', 'masterstudy-lms-learning-management-system' ),
			'pro_url'       => 'https://stylemixthemes.com/wordpress-lms-plugin/pricing/?utm_source=wpadmin&utm_medium=ms-dripcontent&utm_campaign=masterstudy-plugin&licenses=1&billing_cycle=annual',
			'documentation' => 'drip-content',
		),
		'gradebook'               => array(
			'name'          => esc_html__( 'The Gradebook', 'masterstudy-lms-learning-management-system' ),
			'url'           => esc_url( STM_LMS_URL . 'assets/addons/gradebook.png' ),
			'description'   => esc_html__( 'Collect statistics of your students’ progress, check their performance, and keep track of their grades.', 'masterstudy-lms-learning-management-system' ),
			'pro_url'       => 'https://stylemixthemes.com/wordpress-lms-plugin/pricing/?utm_source=wpadmin&utm_medium=ms-gradebook&utm_campaign=masterstudy-plugin&licenses=1&billing_cycle=annual',
			'documentation' => 'the-gradebook',
		),
		'live_streams'            => array(
			'name'          => esc_html__( 'Live Streaming', 'masterstudy-lms-learning-management-system' ),
			'url'           => esc_url( STM_LMS_URL . 'assets/addons/live-stream.png' ),
			'description'   => esc_html__( 'Stream in online mode and interact with your students in real-time answering their questions and giving feedback immediately.', 'masterstudy-lms-learning-management-system' ),
			'pro_url'       => 'https://stylemixthemes.com/wordpress-lms-plugin/pricing/?utm_source=wpadmin&utm_medium=ms-livestream&utm_campaign=masterstudy-plugin&licenses=1&billing_cycle=annual',
			'documentation' => 'live-streaming',
		),
		'enterprise_courses'      => array(
			'name'          => esc_html__( 'Group Courses', 'masterstudy-lms-learning-management-system' ),
			'url'           => esc_url( STM_LMS_URL . 'assets/addons/enterprise-groups.png' ),
			'settings'      => admin_url( 'admin.php?page=enterprise_courses' ),
			'description'   => esc_html__( 'Distribute courses to a group of people. You can sell them to enterprises, or to a group of company employees.', 'masterstudy-lms-learning-management-system' ),
			'pro_url'       => 'https://stylemixthemes.com/wordpress-lms-plugin/pricing/?utm_source=wpadmin&utm_medium=ms-groupcourses&utm_campaign=masterstudy-plugin&licenses=1&billing_cycle=annual',
			'documentation' => 'group-courses',
		),
		'assignments'             => array(
			'name'          => esc_html__( 'Assignments', 'masterstudy-lms-learning-management-system' ),
			'url'           => esc_url( STM_LMS_URL . 'assets/addons/assignment.png' ),
			'settings'      => admin_url( 'admin.php?page=assignments_settings' ),
			'description'   => esc_html__( 'Use assignments to test your students, create interesting tasks for them, ask them to upload essays.', 'masterstudy-lms-learning-management-system' ),
			'pro_url'       => 'https://stylemixthemes.com/wordpress-lms-plugin/pricing/?utm_source=wpadmin&utm_medium=ms-assignments&utm_campaign=masterstudy-plugin&licenses=1&billing_cycle=annual',
			'documentation' => 'assignments',
		),
		'point_system'            => array(
			'name'          => esc_html__( 'Point system', 'masterstudy-lms-learning-management-system' ),
			'url'           => esc_url( STM_LMS_URL . 'assets/addons/points.png' ),
			'settings'      => admin_url( 'admin.php?page=point_system_settings' ),
			'description'   => esc_html__( 'Motivate and engage students by awarding them points for their progress and activity on the website.', 'masterstudy-lms-learning-management-system' ),
			'pro_url'       => 'https://stylemixthemes.com/wordpress-lms-plugin/pricing/?utm_source=wpadmin&utm_medium=ms-points&utm_campaign=masterstudy-plugin&licenses=1&billing_cycle=annual',
			'documentation' => 'point-system',
		),
		'course_bundle'           => array(
			'name'          => esc_html__( 'Course Bundle', 'masterstudy-lms-learning-management-system' ),
			'url'           => esc_url( STM_LMS_URL . 'assets/addons/bundle.png' ),
			'settings'      => admin_url( 'admin.php?page=course_bundle_settings' ),
			'description'   => esc_html__( 'Add similar or related courses to the one bundle and sell them as a package at a discount price.', 'masterstudy-lms-learning-management-system' ),
			'pro_url'       => 'https://stylemixthemes.com/wordpress-lms-plugin/pricing/?utm_source=wpadmin&utm_medium=ms-bundles&utm_campaign=masterstudy-plugin&licenses=1&billing_cycle=annual',
			'documentation' => 'course-bundles',
		),
		'multi_instructors'       => array(
			'name'          => esc_html__( 'Multi-instructors', 'masterstudy-lms-learning-management-system' ),
			'url'           => esc_url( STM_LMS_URL . 'assets/addons/multi_instructors.png' ),
			'description'   => esc_html__( 'Use the help of a colleague and assign one more instructor to the same course to share responsibilities.', 'masterstudy-lms-learning-management-system' ),
			'pro_url'       => 'https://stylemixthemes.com/wordpress-lms-plugin/pricing/?utm_source=wpadmin&utm_medium=ms-multi-instructor&utm_campaign=masterstudy-plugin&licenses=1&billing_cycle=annual',
			'documentation' => 'multi-instructors',
		),
		'google_classrooms'       => array(
			'name'          => esc_html__( 'Google Classrooms', 'masterstudy-lms-learning-management-system' ),
			'url'           => esc_url( STM_LMS_URL . 'assets/addons/google_classroom.png' ),
			'settings'      => admin_url( 'admin.php?page=google_classrooms' ),
			'description'   => esc_html__( 'Ease the process of structuring the workflow by connecting your Google Classroom account with your website and import the needed classes.', 'masterstudy-lms-learning-management-system' ),
			'pro_url'       => 'https://stylemixthemes.com/wordpress-lms-plugin/pricing/?utm_source=wpadmin&utm_medium=ms-gclassroom&utm_campaign=masterstudy-plugin&licenses=1&billing_cycle=annual',
			'documentation' => 'google-classroom',
		),
		'zoom_conference'         => array(
			'name'          => esc_html__( 'Zoom Conference', 'masterstudy-lms-learning-management-system' ),
			'url'           => esc_url( STM_LMS_URL . 'assets/addons/zoom_conference.png' ),
			'settings'      => admin_url( 'admin.php?page=stm_lms_zoom_conference' ),
			'description'   => esc_html__( 'Enjoy the new type of lesson — connect Zoom Video Conferencing with your website and interact with your students in real-time.', 'masterstudy-lms-learning-management-system' ),
			'pro_url'       => 'https://stylemixthemes.com/wordpress-lms-plugin/pricing/?utm_source=wpadmin&utm_medium=ms-zoom&utm_campaign=masterstudy-plugin&licenses=1&billing_cycle=annual',
			'documentation' => 'zoom-video-conferencing',
		),
		'scorm'                   => array(
			'name'          => esc_html__( 'Scorm', 'masterstudy-lms-learning-management-system' ),
			'url'           => esc_url( STM_LMS_URL . 'assets/addons/scorm.png' ),
			'settings'      => admin_url( 'admin.php?page=scorm_settings' ),
			'description'   => esc_html__( 'Easily upload to your LMS any course that was created with the help of different content authoring tools.', 'masterstudy-lms-learning-management-system' ),
			'pro_url'       => 'https://stylemixthemes.com/wordpress-lms-plugin/pricing/?utm_source=wpadmin&utm_medium=ms-scorm&utm_campaign=masterstudy-plugin&licenses=1&billing_cycle=annual',
			'documentation' => 'scorm',
		),
		'email_manager'           => array(
			'name'          => esc_html__( 'Email Manager', 'masterstudy-lms-learning-management-system' ),
			'url'           => esc_url( STM_LMS_URL . 'assets/addons/email_manager.png' ),
			'settings'      => admin_url( 'admin.php?page=email_manager_settings' ),
			'description'   => esc_html__( 'Adjust your email templates for different types of notifications and make your messages look good and clear.', 'masterstudy-lms-learning-management-system' ),
			'pro_url'       => 'https://stylemixthemes.com/wordpress-lms-plugin/pricing/?utm_source=wpadmin&utm_medium=ms-emailmanager&utm_campaign=masterstudy-plugin&licenses=1&billing_cycle=annual',
			'documentation' => 'email-manager',
		),
		'email_branding'          => array(
			'name'          => esc_html__( 'Email Branding', 'masterstudy-lms-learning-management-system' ),
			'url'           => esc_url( STM_LMS_URL . 'assets/addons/email_branding.png' ),
			'settings'      => admin_url( 'admin.php?page=email_manager_settings#email_template' ),
			'description'   => esc_html__( 'Adjust your email templates for different types of notifications and make your messages look good and clear.', 'masterstudy-lms-learning-management-system' ),
			'pro_url'       => 'https://stylemixthemes.com/wordpress-lms-plugin/pricing/?utm_source=wpadmin&utm_medium=ms-emailbranding&utm_campaign=masterstudy-plugin&licenses=1&billing_cycle=annual',
			'documentation' => 'email-branding',
			'pro_plus'      => true,
			'documentation' => 'email-manager',
		),
		'certificate_builder'     => array(
			'name'          => esc_html__( 'Certificate Builder', 'masterstudy-lms-learning-management-system' ),
			'url'           => esc_url( STM_LMS_URL . 'assets/addons/certtificate_builder.png' ),
			'settings'      => admin_url( 'admin.php?page=certificate_builder' ),
			'description'   => esc_html__( 'Сreate and design your own certificates to award them to students after the course completion.', 'masterstudy-lms-learning-management-system' ),
			'pro_url'       => 'https://stylemixthemes.com/wordpress-lms-plugin/pricing/?utm_source=wpadmin&utm_medium=ms-certificatebuilder&utm_campaign=masterstudy-plugin&licenses=1&billing_cycle=annual',
			'documentation' => 'certificate-builder',
		),
		'form_builder'            => array(
			'name'          => esc_html__( 'LMS Forms Editor', 'masterstudy-lms-learning-management-system' ),
			'url'           => esc_url( STM_LMS_URL . 'assets/addons/custom_fields.png' ),
			'settings'      => admin_url( 'admin.php?page=form_builder' ),
			'description'   => esc_html__( 'LMS Forms Editor is an addon that allows you to customize the profile (incl. registration) form, Become Instructor request form and Enterprise form of the MasterStudy LMS', 'masterstudy-lms-learning-management-system' ),
			'pro_url'       => 'https://stylemixthemes.com/wordpress-lms-plugin/pricing/?utm_source=wpadmin&utm_medium=ms-formbuilder&utm_campaign=masterstudy-plugin&licenses=1&billing_cycle=annual',
			'documentation' => 'lms-form-editor',
		),
		'media_library'           => array(
			'name'          => esc_html__( 'Media File Manager', 'masterstudy-lms-learning-management-system' ),
			'url'           => esc_url( STM_LMS_URL . 'assets/addons/media_library.jpg' ),
			'description'   => esc_html__( 'Manage, keep and load files of various formats while creating e-learning content in the front-end.', 'masterstudy-lms-learning-management-system' ),
			'documentation' => 'media-file-manager',
			'settings'      => admin_url( 'admin.php?page=media_library_settings' ),
			'pro_url'       => 'https://stylemixthemes.com/wordpress-lms-plugin/pricing/',
		),
		'google_meet'             => array(
			'name'          => esc_html__( 'Google Meet', 'masterstudy-lms-learning-management-system' ),
			'url'           => esc_url( STM_LMS_URL . 'assets/addons/google_meet.png' ),
			'settings'      => admin_url( 'admin.php?page=google_meet_settings' ),
			'description'   => esc_html__( 'Connect MasterStudy LMS with Google Meet to host live online classes. Students can attend live classes right from the lesson page.', 'masterstudy-lms-learning-management-system' ),
			'pro_url'       => 'https://stylemixthemes.com/wordpress-lms-plugin/pricing/?utm_source=wpadmin&utm_medium=ms-googlemeet&utm_campaign=masterstudy-plugin&licenses=1&billing_cycle=annual',
			'documentation' => 'google-meet',
			'pro_plus'      => true,
		),
	);

	return apply_filters( 'stm_lms_available_addons', $available_addons );
}

function stm_lms_addons_menu_position() {
	$menu_position = class_exists( 'Stm_Lms_Statistics' ) ? 9 : 8; // Default Post Types & Taxonomies
	$post_types    = apply_filters( 'stm_lms_post_types_array', array() );

	foreach ( $post_types as $post_type ) {
		if ( isset( $post_type['args']['show_in_menu'] ) && 'admin.php?page=stm-lms-settings' === $post_type['args']['show_in_menu'] ) {
			$menu_position ++;
		}
	}

	return $menu_position;
}

function stm_lms_allowed_html() {
	$allowed_html           = wp_kses_allowed_html( 'post' );
	$allowed_html['iframe'] = array(
		'autoplay'        => 1,
		'src'             => 1,
		'width'           => 1,
		'height'          => 1,
		'class'           => 1,
		'style'           => 1,
		'muted'           => 1,
		'loop'            => 1,
		'allowfullscreen' => array(),
		'allow'           => array(),
	);

	return apply_filters( 'stm_lms_allowed_html', $allowed_html );
}

if ( ! function_exists( 'stm_module_styles' ) && defined( 'STM_THEME_VERSION' ) === '4.4.4' ) {
	function stm_module_styles( $handle, $style = 'style_1', $deps = array(), $inline_styles = '' ) {
		if ( stm_lms_is_masterstudy_theme() ) {
			$path   = get_template_directory_uri() . '/assets/css/vc_modules/' . $handle . '/' . $style . '.css';
			$handle = 'stm-' . $handle . '-' . $style;
			wp_enqueue_style( $handle, $path, $deps, STM_LMS_VERSION, 'all' );
			if ( ! empty( $inline_styles ) ) {
				wp_add_inline_style( $handle, $inline_styles );
			}
		}
	}
}

function stm_lms_student_assignments_hide_button() {
	if ( isset( $_GET['post_type'] ) && 'stm-user-assignment' === $_GET['post_type'] || get_post_type() === 'stm-user-assignment' ) {
		?>
		<script>
			jQuery(document).ready(function ($) {
				$('.page-title-action').css('display', 'none');
			});
		</script>
		<?php
	}
}

add_filter( 'admin_footer', 'stm_lms_student_assignments_hide_button' );

function add_notice_to_nuxy_metabox() {
	$pro_url = admin_url( 'admin.php?page=stm-lms-go-pro' );
	?>
	<div class="field_overlay"></div>
	<span class="pro-notice">
		<?php esc_html_e( 'Available in', 'masterstudy-lms-learning-management-system' ); ?>
		<a href="<?php esc_url( $pro_url ); ?>" target="_blank"><?php esc_html_e( 'Pro Version', 'masterstudy-lms-learning-management-system' ); ?></a>
	</span>
	<?php
}
add_action( 'metabox_field_nuxy_notification', 'add_notice_to_nuxy_metabox' );

function stm_lms_get_terms_for_membership( $taxonomy = '', $args = array( 'parent' => 0 ), $add_childs = true ) {

	$terms = get_terms( $taxonomy, $args );

	$select = array(
		'' => esc_html__( 'Choose category', 'masterstudy-lms-learning-management-system' ),
	);

	foreach ( $terms as $term ) {
		$select[ $term->term_id ] = $term->name;

		if ( $add_childs ) {
			$term_children = get_term_children( $term->term_id, $taxonomy );

			foreach ( $term_children as $term_child_id ) {
				$term_child               = get_term_by( 'id', $term_child_id, $taxonomy );
				$select[ $term_child_id ] = "- {$term_child->name}";
			}
		};

	}

	return $select;
}

function stm_lms_course_categories_filter() {
	global $typenow;
	if ( 'stm-courses' === $typenow ) {
		$taxonomy_names = array( 'stm_lms_course_taxonomy' );
		foreach ( $taxonomy_names as $single_taxonomy ) {
			$current_taxonomy = isset( $_GET[ $single_taxonomy ] ) ? $_GET[ $single_taxonomy ] : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$taxonomy_terms   = get_terms(
				array(
					'taxonomy'   => $single_taxonomy,
					'hide_empty' => false,
				)
			);
			if ( ! empty( $taxonomy_terms ) ) {
				?>
				<select name="<?php echo esc_html( $single_taxonomy ); ?>" id="<?php echo esc_html( $single_taxonomy ); ?>" class="postform">
				<option value=''><?php echo esc_html__( 'All courses categories', 'masterstudy-lms-learning-management-system' ); ?></option>
				<?php foreach ( $taxonomy_terms as $single_term ) { ?>
					<option value="<?php echo esc_html( $single_term->slug ); ?>" <?php echo ( $current_taxonomy == $single_term->slug ) ? 'selected="selected"' : ''; //phpcs:ignore ?>><?php echo esc_html( $single_term->name . ' (' . $single_term->count . ')' ); ?></option>
				<?php } ?>
				</select>
				<?php
			}
		}
	}
}

add_action( 'restrict_manage_posts', 'stm_lms_course_categories_filter' );

/**
 * Presto to Player posts data
 * @return array
 */
function ms_plugin_presto_player_post_data( $assoc = false ) {
	$presto_player_data = get_posts(
		array(
			'post_type'      => 'pp_video_block',
			'posts_per_page' => -1,
		)
	);

	$posts = array();
	if ( count( $presto_player_data ) ) {
		foreach ( $presto_player_data as $presto_player ) {
			if ( $assoc ) {
				$posts[ $presto_player->ID ] = $presto_player->post_title;
			} else {
				$posts[] = array(
					'id'    => $presto_player->ID,
					'label' => $presto_player->post_title,
				);
			}
		}
	}

	return $posts;
}

add_filter( 'ms_plugin_presto_player_posts', 'ms_plugin_presto_player_post_data' );

/**
 * Get first index of posts
 * @return mixed|null
 */
function ms_plugin_presto_player_default() {
	$posts = ms_plugin_presto_player_post_data();
	if ( count( $posts ) > 0 ) {
		return $posts[ count( $posts ) - 1 ]['id'];
	}

	return null;
}

/**
 * @return bool
 */
function ms_plugin_presto_player_allowed() {
	if ( is_user_logged_in() ) {
		$user                 = wp_get_current_user();
		$roles                = $user->roles;
		$enable_presto_player = STM_LMS_Options::get_option( 'course_allow_presto_player', false );

		if ( ( $enable_presto_player && in_array( 'stm_lms_instructor', $roles, true ) ) || in_array( 'administrator', $roles, true ) ) {
			return true;
		}
	}

	return false;
}

add_filter( 'ms_plugin_presto_player_allowed', 'ms_plugin_presto_player_allowed' );

/**
 * @return array
 */
function ms_plugin_video_sources() {
	$options                  = array();
	$ms_plugins_video_sources = array(
		'course_lesson_video_type_html'      => array(
			'key'   => 'html',
			'label' => esc_html__( 'HTML (MP4)', 'masterstudy-lms-learning-management-system' ),
		),
		'course_lesson_video_type_youtube'   => array(
			'key'   => 'youtube',
			'label' => esc_html__( 'YouTube', 'masterstudy-lms-learning-management-system' ),
		),
		'course_lesson_video_type_vimeo'     => array(
			'key'   => 'vimeo',
			'label' => esc_html__( 'Vimeo', 'masterstudy-lms-learning-management-system' ),
		),
		'course_lesson_video_type_ext_link'  => array(
			'key'   => 'ext_link',
			'label' => esc_html__( 'External Link', 'masterstudy-lms-learning-management-system' ),
		),
		'course_lesson_video_type_embed'     => array(
			'key'   => 'embed',
			'label' => esc_html__( 'Embed', 'masterstudy-lms-learning-management-system' ),
		),
		'course_lesson_video_type_shortcode' => array(
			'key'   => 'shortcode',
			'label' => esc_html__( 'Shortcode', 'masterstudy-lms-learning-management-system' ),
		),
	);

	if ( defined( 'PRESTO_PLAYER_PLUGIN_FILE' ) && apply_filters( 'ms_plugin_presto_player_allowed', false ) ) {
		$ms_plugins_video_sources['course_lesson_video_type_pp'] = array(
			'key'   => 'presto_player',
			'label' => esc_html__( 'Presto Player', 'masterstudy-lms-learning-management-system' ),
		);
	}

	$allow_types = STM_LMS_Options::get_option( 'course_lesson_video_types' );

	foreach ( $ms_plugins_video_sources as $source_key => $source_value ) {
		$source_data = STM_LMS_Options::get_option( $source_key, false );

		if ( ( empty( $allow_types ) || ! empty( $source_data ) ) || 'presto_player' === $source_value['key'] ) {
			$options[ $source_value['key'] ] = $source_value['label'];
		}
	}

	if ( defined( 'VDOCIPHER_PLUGIN_VERSION' ) && current_user_can( 'administrator' ) ) {
		$options['vdocipher'] = esc_html__( 'VdoCipher', 'masterstudy-lms-learning-management-system' );
	}

	return $options;
}

add_filter( 'ms_plugin_video_sources', 'ms_plugin_video_sources' );

/**
 * @param $url
 * @return false|mixed|null
 */
function ms_plugin_get_youtube_id( $url ) {
	$youtube_id = null;
	if ( ! $url ) {
		return false;
	}

	preg_match( '%(?:youtube(?:-nocookie)?\.com/[live]*/*(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match );

	if ( isset( $match[1] ) ) {
		$youtube_id = $match[1];
	}

	return $youtube_id;
}

add_filter( 'ms_plugin_get_youtube_idx', 'ms_plugin_get_youtube_id' );

function ms_plugin_get_vimeo_id( $url ) {
	$vimeo_id = null;
	if ( ! $url ) {
		return false;
	}

	if ( preg_match( '%^https?:\/\/(?:www\.|player\.)?vimeo.com\/(?:channels\/(?:\w+\/)?|groups\/([^\/]*)\/videos\/|album\/(\d+)\/video\/|video\/|)(\d+)(?:$|\/|\?)(?:[?]?.*)$%im', $url, $match ) ) {
		if ( isset( $match[3] ) ) {
			$vimeo_id = $match[3];
		}
	}

	return $vimeo_id;
}

/**
 * @return int|string|null
 */
function ms_plugin_get_default_source() {
	$sources = ms_plugin_video_sources();
	if ( count( $sources ) > 0 ) {
		return array_key_first( $sources );
	}

	return '';
}

add_filter( 'ms_plugin_get_default_value', 'ms_plugin_get_default_source' );

function is_ms_lms_addon_enabled( $addon ) {
	$enabled_addons = get_option( 'stm_lms_addons' );
	return defined( 'STM_LMS_PRO_PATH' ) && isset( $enabled_addons[ $addon ] ) && 'on' === $enabled_addons[ $addon ];
}

function ms_plugin_user_account_url( $sub_page = '' ) {
	$settings        = get_option( 'stm_lms_settings', array() );
	$account_page_id = apply_filters( 'wpml_object_id', $settings['user_url'] ?? null, 'post' );

	if ( empty( $account_page_id ) || ! did_action( 'init' ) ) {
		return home_url( '/' );
	}

	$user_account_url = get_the_permalink( $account_page_id );

	if ( ! empty( $sub_page ) ) {
		$user_account_url .= "$sub_page/";
	}

	return $user_account_url;
}

function ms_plugin_manage_course_url( $course_id = null ) {
	$sub_page = $course_id ? "edit-course/$course_id" : 'edit-course';

	return ms_plugin_user_account_url( $sub_page );
}

function ms_plugin_edit_course_builder_url( $post_type ) {
	switch ( $post_type ) {
		case 'stm-courses':
			return ms_plugin_user_account_url( 'edit-course' );
		case 'stm-lessons':
			return ms_plugin_user_account_url( 'edit-lesson' );
		case 'stm-quizzes':
			return ms_plugin_user_account_url( 'edit-quiz' );
		case 'stm-questions':
			return ms_plugin_user_account_url( 'edit-question' );
		case 'stm-assignments':
			return ms_plugin_user_account_url( 'edit-assignment' );
		case 'stm-google-meets':
			return ms_plugin_user_account_url( 'edit-google-meet' );
	}

	return false;
}

function ms_plugin_edit_item_url( $id, $post_type ) {
	return ms_plugin_edit_course_builder_url( $post_type ) . "$id/";
}

function ms_plugin_favicon_url() {
	$stm_options = get_option( 'stm_option', array() );
	$favicon     = $stm_options['favicon']['url'] ?? false;

	if ( empty( $favicon ) ) {
		$favicon = file_exists( get_template_directory() . '/favicon.ico' )
			? get_template_directory_uri() . '/favicon.ico'
			: get_site_icon_url();
	}

	return apply_filters( 'masterstudy_lms_favicon_url', $favicon );
}

function ms_plugin_files_formats() {
	return array(
		'img'        => array( 'png', 'jpg', 'jpeg', 'gif', 'webp' ),
		'excel'      => array( 'xls', 'xlsx' ),
		'word'       => array( 'doc', 'docx' ),
		'powerpoint' => array( 'ppt', 'pptx' ),
		'pdf'        => array( 'pdf' ),
		'video'      => array( 'mp4', 'avi', 'flv', 'webm', 'wmv', 'mov' ),
		'audio'      => array( 'mp3', 'wma', 'aac' ),
		'archive'    => array( 'zip', 'gz', 'rar', '7z' ),
	);
}

function ms_plugin_get_course_page_style( $course_id ) {
	$course_option = get_post_meta( $course_id, 'page_style', true );
	$style         = ! empty( $course_option ) ? $course_option : get_course_style_from_categories( $course_id );

	if ( isset( $_GET['course_style'] ) ) {
		$style = sanitize_text_field( wp_unslash( $_GET['course_style'] ) );
	}
	if ( empty( $style ) ) {
		$style = STM_LMS_Options::get_option( 'course_style', 'default' );
	}

	return $style;
}

function get_course_style_from_categories( $course_id ) {
	global $wpdb;
	$style = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT tm.meta_value
			FROM {$wpdb->term_relationships} tr
			INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
			INNER JOIN {$wpdb->termmeta} tm ON t.term_id = tm.term_id
			WHERE tr.object_id = %d
			AND tt.taxonomy = %s
			AND tm.meta_key = %s
			LIMIT 1
			",
			$course_id,
			'stm_lms_course_taxonomy',
			'course_page_style'
		)
	);
	return $style;
}

function ms_plugin_attachment_data( $attachment ) {
	if ( is_object( $attachment ) ) {
		$data                   = array(
			'file_title' => $attachment->post_title,
			'file_id'    => $attachment->ID,
			'file'       => get_attached_file( $attachment->ID ),
			'url'        => wp_get_attachment_url( $attachment->ID ),
		);
		$data['filesize']       = round( filesize( $data['file'] ) / 1024 );
		$data['filesize_label'] = ( $data['filesize'] > 1000 ) ? 'mb' : 'kb';
		$data['filesize']       = ( $data['filesize'] > 1000 ) ? round( $data['filesize'] / 1024 ) : $data['filesize'];
		$data['ext']            = pathinfo( $data['file'], PATHINFO_EXTENSION );
		$data['filtered']       = array_filter(
			ms_plugin_files_formats(),
			function ( $extensions ) use ( $data ) {
				return in_array( $data['ext'], $extensions, true );
			}
		);
		$data['current_format'] = ! empty( $data['filtered'] ) ? key( $data['filtered'] ) : 'unknown';

		return $data;
	}
	return array();
}

function ms_plugin_curriculum_list() {
	return array(
		array(
			'type'        => 'multimedia',
			'icon'        => 'video',
			'icon_width'  => '19',
			'icon_height' => '19',
			'label'       => __( 'Media:', 'masterstudy-lms-learning-management-system' ),
		),
		array(
			'type'        => 'quiz',
			'icon'        => 'quiz',
			'icon_width'  => '18',
			'icon_height' => '18',
			'label'       => __( 'Quizzes:', 'masterstudy-lms-learning-management-system' ),
		),
		array(
			'type'        => 'lesson',
			'icon'        => 'text',
			'icon_width'  => '18',
			'icon_height' => '18',
			'label'       => __( 'Pages:', 'masterstudy-lms-learning-management-system' ),
		),
		array(
			'type'        => 'assignment',
			'icon'        => 'assignments',
			'icon_width'  => '16',
			'icon_height' => '16',
			'label'       => __( 'Assignments:', 'masterstudy-lms-learning-management-system' ),
		),
	);
}

function ms_plugin_base_colors() {
	$settings       = get_option( 'stm_lms_settings' );
	$default_colors = array(
		'accent'  => 'rgba(34,122,255,1)',
		'warning' => 'rgba(255,168,0,1)',
		'danger'  => 'rgba(255,57,69,1)',
		'success' => 'rgba(97,204,47,1)',
	);
	$custom_colors  = array(
		'accent'  => ! empty( $settings['accent_color'] ) ? $settings['accent_color'] : $default_colors['accent'],
		'warning' => ! empty( $settings['warning_color'] ) ? $settings['warning_color'] : $default_colors['warning'],
		'danger'  => ! empty( $settings['danger_color'] ) ? $settings['danger_color'] : $default_colors['danger'],
		'success' => ! empty( $settings['success_color'] ) ? $settings['success_color'] : $default_colors['success'],
	);
	?>
	<style>
		:root {
			<?php
			foreach ( $custom_colors as $name => $color ) {
				sscanf( $color, 'rgba(%d, %d, %d, %f)', $first, $second, $third, $opacity );
				$rgba = "$first, $second, $third";
				?>
				--<?php echo esc_html( $name ); ?>-100: <?php echo esc_html( $color ); ?>;
				--<?php echo esc_html( $name ); ?>-70: rgba(<?php echo esc_html( $rgba ); ?>, 0.7);
				--<?php echo esc_html( $name ); ?>-50: rgba(<?php echo esc_html( $rgba ); ?>, 0.5);
				--<?php echo esc_html( $name ); ?>-30: rgba(<?php echo esc_html( $rgba ); ?>, 0.3);
				--<?php echo esc_html( $name ); ?>-10: rgba(<?php echo esc_html( $rgba ); ?>, 0.1);
				--<?php echo esc_html( $name ); ?>-5: rgba(<?php echo esc_html( $rgba ); ?>, 0.05);
				--<?php echo esc_html( $name ); ?>-0: rgba(<?php echo esc_html( $rgba ); ?>, 0);
				--<?php echo esc_html( $name ); ?>-hover: rgba(<?php echo esc_html( $rgba ); ?>, 0.85);
			<?php } ?>
		}
	</style>
	<?php
}
add_action( 'wp_head', 'ms_plugin_base_colors' );
add_action( 'admin_head', 'ms_plugin_base_colors' );

function ms_plugin_authorization() {
	if ( ! is_user_logged_in() ) {
		$settings                                 = get_option( 'stm_lms_settings', array() );
		$settings['instructor_registration_page'] = $settings['instructor_registration_page'] ?? false;
		$page_id                                  = get_the_ID();
		$current_id                               = apply_filters( 'wpml_object_id', $page_id, 'post' );
		$wpml_pages                               = array(
			apply_filters( 'wpml_object_id', intval( $settings['user_url'] ), 'post' ),
			apply_filters( 'wpml_object_id', intval( $settings['instructor_url_profile'] ), 'post' ),
			apply_filters( 'wpml_object_id', intval( $settings['student_url_profile'] ), 'post' ),
		);
		$pages                                    = array(
			intval( $settings['user_url'] ),
			intval( $settings['instructor_url_profile'] ),
			intval( $settings['student_url_profile'] ),
		);
		if ( $settings['instructor_registration_page'] ) {
			$wpml_pages[] = apply_filters( 'wpml_object_id', intval( $settings['instructor_registration_page'] ), 'post' );
			$pages[]      = intval( $settings['instructor_registration_page'] );
		}

		if ( in_array( $current_id, $wpml_pages, true ) || in_array( $page_id, $pages, true ) ) {
			return;
		}

		STM_LMS_Templates::show_lms_template(
			'components/authorization/main',
			array(
				'modal' => true,
				'type'  => 'login',
			)
		);
	}
}
add_action( 'wp_footer', 'ms_plugin_authorization' );

// Deprecated; will be deleted when the theme version exceeds 4.8.27.
function enqueue_login_script() {
	wp_enqueue_script( 'stm_grecaptcha' );
	do_action( 'stm_lms_enqueue_login_script' );
}

// Deprecated; will be deleted when the theme version exceeds 4.8.27.
function enqueue_register_script() {
	if ( STM_LMS_Helpers::g_recaptcha_enabled() ) {
		wp_enqueue_script( 'vue-recaptcha' );
	}
	do_action( 'stm_lms_enqueue_register_script' );
}

function masterstudy_addons_plugin_page_tab( $tabs ) {
	if ( ! defined( 'STM_LMS_PRO_PATH' ) ) {
		$tabs['masterstudy_addons'] = __( 'MasterStudy Addons', 'masterstudy-lms-learning-management-system' );
	}

	return $tabs;
}
add_filter( 'install_plugins_tabs', 'masterstudy_addons_plugin_page_tab' );

function masterstudy_addons_plugin_page_redirect( $tabs ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_GET['tab'] ) || 'masterstudy_addons' !== $_GET['tab'] ) {
		return;
	}

	wp_safe_redirect( admin_url( 'admin.php?page=stm-addons' ) );
	exit;
}
add_action( 'install_plugins_pre_masterstudy_addons', 'masterstudy_addons_plugin_page_redirect' );

function masterstudy_lms_course_has_certificate( $course_id ) {
	global $wpdb;

	$course_certificate = get_post_meta( $course_id, 'course_certificate', true );

	if ( 'none' === $course_certificate ) {
		return false;
	}

	$course_terms    = wp_get_post_terms( $course_id, 'stm_lms_course_taxonomy', array( 'fields' => 'ids' ) );
	$categories_list = implode( ',', array_map( 'intval', $course_terms ) );
	$certificate_ids = $wpdb->get_col(
		$wpdb->prepare(
			"
			SELECT p.ID
			FROM {$wpdb->posts} AS p
			INNER JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id
			WHERE p.post_type = 'stm-certificates'
			AND pm.meta_key = 'stm_category'
			AND (pm.meta_value REGEXP CONCAT('(^|,)', %s, '(,|$)'))
			ORDER BY pm.meta_value ASC
			LIMIT 1
			",
			$categories_list
		)
	);

	if ( empty( $certificate_ids ) ) {
		$certificate_ids = get_option( 'stm_default_certificate', '' );
	}

	return ! empty( $course_certificate ) || ! empty( $certificate_ids );
}

function masterstudy_lms_admin_bar_edit_course_menu( $wp_admin_bar ) {
	if ( ! current_user_can( 'administrator' ) || ! is_admin_bar_showing() || ! is_singular( PostType::COURSE ) ) {
		return;
	}

	$wp_admin_bar->remove_menu( 'edit' );

	$wp_admin_bar->add_menu(
		array(
			'id'    => 'edit',
			'title' => esc_html__( 'Edit with Course Builder', 'masterstudy-lms-learning-management-system' ),
			'href'  => esc_url( ms_plugin_manage_course_url( get_the_ID() ) ),
			'meta'  => array(
				'class'  => 'ab-item',
				'target' => '_blank',
			),
		)
	);
}
add_action( 'admin_bar_menu', 'masterstudy_lms_admin_bar_edit_course_menu', 100 );

function masterstudy_lms_admin_bar_settings_menu( $wp_admin_bar ) {
	if ( ! current_user_can( 'administrator' ) || ! is_admin_bar_showing() ) {
		return;
	}

	$wp_admin_bar->add_node(
		array(
			'id'    => 'lms-settings',
			'title' => '<img src="' . STM_LMS_URL . 'assets/admin/icon.svg" /> ' . esc_html__( 'Settings', 'masterstudy-lms-learning-management-system' ),
			'href'  => admin_url( 'admin.php?page=stm-lms-settings' ),
			'meta'  => array(
				'title' => esc_html__( 'Settings', 'masterstudy-lms-learning-management-system' ),
			),
		)
	);
}
add_action( 'admin_bar_menu', 'masterstudy_lms_admin_bar_settings_menu', 40 );

function masterstudy_find_id_in_array( $array, $target_id ) {
	if ( is_array( $array ) && ! empty( $array ) ) {
		foreach ( $array as $item ) {
			if ( isset( $item->id ) && intval( $item->id ) === intval( $target_id ) ) {
				return true;
			}
		}
		return false;
	}
};

function masterstudy_course_header_meta_data() {
	$course = ( new CourseRepository() )->find( get_the_ID() );

	if ( $course ) {
		$course_url = STM_LMS_Course::courses_page_url() . $course->slug;
		?>
		<!-- Open Graph meta tags for Facebook and LinkedIn -->
		<meta property="og:title" content="<?php echo esc_attr( $course->title ); ?>" />
		<meta property="og:description" content="<?php echo esc_attr( $course->excerpt ); ?>" />
		<meta property="og:image" content="<?php echo esc_url( $course->thumbnail['url'] ?? '' ); ?>" />
		<meta property="og:url" content="<?php echo esc_url( $course_url ); ?>" />
		<!-- Twitter Card meta tags -->
		<meta name="twitter:card" content="summary_large_image" />
		<meta name="twitter:title" content="<?php echo esc_attr( $course->title ); ?>" />
		<meta name="twitter:description" content="<?php echo esc_attr( $course->excerpt ); ?>" />
		<meta name="twitter:image" content="<?php echo esc_url( $course->thumbnail['url'] ?? '' ); ?>" />
		<meta name="twitter:url" content="<?php echo esc_url( $course_url ); ?>" />
		<?php
	}
}
add_action( 'wp_head', 'masterstudy_course_header_meta_data' );

function masterstudy_lms_course_free_status( $single_sale, $price ) {
	return array(
		'is_free'    => $single_sale && 0.0 === floatval( $price ),
		'zero_price' => empty( $price ) || 0.0 === floatval( $price ),
	);
}

function masterstudy_lms_show_course_templates_modal() {
	$screen = get_current_screen();

	if (
		( 'edit-page' === $screen->id ) ||
		( 'edit-stm_lms_course_taxonomy' === $screen->id ) ||
		( 'toplevel_page_stm-lms-settings' === $screen->id )
	) {
		$single_page = 'edit-page' === $screen->id ? true : false;
		STM_LMS_Templates::show_lms_template( 'components/course-templates', array( 'single_page' => $single_page ) );
	}
}
add_action( 'admin_footer', 'masterstudy_lms_show_course_templates_modal' );

function masterstudy_lms_get_native_templates() {
	$is_pro      = STM_LMS_Helpers::is_pro();
	$is_pro_plus = STM_LMS_Helpers::is_pro_plus();

	return array(
		array(
			'name'     => 'default',
			'title'    => esc_html__( 'Default', 'masterstudy-lms-learning-management-system' ),
			'disabled' => false,
		),
		array(
			'name'     => 'classic',
			'title'    => esc_html__( 'Classic', 'masterstudy-lms-learning-management-system' ),
			'disabled' => ! $is_pro,
		),
		array(
			'name'     => 'modern',
			'title'    => esc_html__( 'Industrial', 'masterstudy-lms-learning-management-system' ),
			'disabled' => ! $is_pro,
		),
		array(
			'name'     => 'timeless',
			'title'    => esc_html__( 'Timeless', 'masterstudy-lms-learning-management-system' ),
			'disabled' => ! $is_pro_plus,
		),
		array(
			'name'     => 'sleek-sidebar',
			'title'    => esc_html__( 'Sleek with Sidebar', 'masterstudy-lms-learning-management-system' ),
			'disabled' => ! $is_pro_plus,
		),
		array(
			'name'     => 'minimalistic',
			'title'    => esc_html__( 'Minimalistic', 'masterstudy-lms-learning-management-system' ),
			'disabled' => ! $is_pro_plus,
		),
		array(
			'name'     => 'dynamic',
			'title'    => esc_html__( 'Dynamic', 'masterstudy-lms-learning-management-system' ),
			'disabled' => ! $is_pro_plus,
		),
		array(
			'name'     => 'modern-curriculum',
			'title'    => esc_html__( 'Modern with Curriculum', 'masterstudy-lms-learning-management-system' ),
			'disabled' => ! $is_pro_plus,
		),
		array(
			'name'     => 'dynamic-sidebar',
			'title'    => esc_html__( 'Dynamic with Short Sidebar', 'masterstudy-lms-learning-management-system' ),
			'disabled' => ! $is_pro_plus,
		),
		array(
			'name'     => 'full-width',
			'title'    => esc_html__( 'Bold with Full Width Cover', 'masterstudy-lms-learning-management-system' ),
			'disabled' => ! $is_pro_plus,
		),
	);
}

function masterstudy_lms_get_user_enrolled_date( $course_id, $user_id ) {
	if ( empty( $course_id ) || empty( $user_id ) ) {
		return;
	}

	global $wpdb;

	$table_name = $wpdb->prefix . 'stm_lms_user_courses';

	return $wpdb->get_var(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT start_time FROM {$table_name} WHERE user_id = %d AND course_id = %d LIMIT 1",
			$user_id,
			$course_id
		)
	);
}
