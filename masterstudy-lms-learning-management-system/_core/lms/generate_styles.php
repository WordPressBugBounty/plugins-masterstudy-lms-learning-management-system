<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} //Exit if accessed directly


add_action( 'init', 'stm_lms_generate_styles' );

function stm_lms_generate_styles() {

	if ( current_user_can( 'manage_options' ) ) {

		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$upload              = wp_upload_dir();
		$upload_dir          = $upload['basedir'];
		$upload_dir          = $upload_dir . '/stm_lms_styles';
		$upload_dir_parts    = $upload_dir . '/parts';
		$upload_dir_vc_parts = $upload_dir . '/vc_modules';

		if ( ! $wp_filesystem->is_dir( $upload_dir ) ) {
			wp_mkdir_p( $upload_dir );
		}

		if ( ! $wp_filesystem->is_dir( $upload_dir_parts ) ) {
			wp_mkdir_p( $upload_dir_parts );
		}

		$inner_folders = array(
			'account',
			'account/v1',
			'instructor',
			'manage_students',
			'admin',
			'app',
			'bundles',
			'assignments',
			'google_meet',
			'google_meet/meet-admin',
			'google_meet/stm-google-meet-form',
			'co_courses',
			'coming_soon',
			'course',
			'courses',
			'courses',
			'expiration',
			'float_menu',
			'google_classroom',
			'lesson',
			'lesson/total_progress',
			'noconflict',
			'panel',
			'scorm',
			'wizard',
			'nuxy',
			'dashboard',
			'countdown',
		);

		foreach ( $inner_folders as $inner_folder ) {

			$inner_folder = $upload_dir_parts . '/' . $inner_folder . '/';

			if ( ! $wp_filesystem->is_dir( $inner_folder ) ) {
				$wp_filesystem->mkdir( $inner_folder );
			}
		}

		if ( ! $wp_filesystem->is_dir( $upload_dir_vc_parts ) ) {
			wp_mkdir_p( $upload_dir_vc_parts );
		}

		$vc_folders = array(
			'course_category',
			'courses_carousel',
			'courses_grid',
			'featured_teacher',
			'instructors_carousel',
			'quiz-pagination',
			'recent_courses',
			'searchbox',
			'single_course_carousel',
			'vue-autocomplete',
		);

		foreach ( $vc_folders as $vc_folder ) {

			$vc_folder = $upload_dir_vc_parts . '/' . $vc_folder . '/';
			if ( ! $wp_filesystem->is_dir( $vc_folder ) ) {
				$wp_filesystem->mkdir( $vc_folder );
			}
		}

		/*Create img folder*/
		$image_dir = $upload_dir . '/img';
		if ( ! $wp_filesystem->is_dir( $image_dir ) ) {
			wp_mkdir_p( $image_dir );
		}

		$image_inner = array( 'starfull.svg', 'staremptyl.svg' );

		foreach ( $image_inner as $image ) {
			copy( STM_LMS_PATH . "/assets/img/{$image}", $image_dir . "/{$image}" );
		}

		if ( stm_lms_has_custom_colors() ) {

			$styles          = array();
			$css_styles_path = STM_LMS_PATH . '/assets/css/';
			if ( is_dir( $css_styles_path ) ) {
				$css_styles = array_diff( scandir( $css_styles_path ), array( '..', '.', 'parts' ) );
			}

			$css_styles_parts_path = STM_LMS_PATH . '/assets/css/parts/';
			if ( is_dir( $css_styles_parts_path ) ) {
				$css_styles_parts = array_diff( scandir( $css_styles_parts_path ), array( '..', '.' ) );
			}
			$css_styles_vc_modules = STM_LMS_PATH . '/assets/css/vc_modules/';
			if ( is_dir( $css_styles_vc_modules ) ) {
				$css_styles_vc = array_diff( scandir( $css_styles_vc_modules ), array( '..', '.' ) );
			}

			/*Courses Styles*/
			foreach ( $css_styles as $style ) {
				$styles[ $style ] = $css_styles_path . $style;
			}

			foreach ( $css_styles_parts as $style ) {
				$styles[ "parts/{$style}" ] = $css_styles_parts_path . $style;
			}

			foreach ( $css_styles_vc as $style ) {
				$styles[ "vc_modules/{$style}" ] = $css_styles_vc_modules . $style;
			}
			foreach ( $inner_folders as $inner_folder ) {
				$css_styles_parts_path_inner = $css_styles_parts_path . $inner_folder;
				if ( is_dir( $css_styles_parts_path_inner ) ) {
					$css_courses_styles_parts = array_diff(
						scandir(
							$css_styles_parts_path_inner
						),
						array(
							'..',
							'.',
						)
					);
				}

				foreach ( $css_courses_styles_parts as $style ) {
					$styles[ "parts/{$inner_folder}/{$style}" ] = $css_styles_parts_path_inner . '/' . $style;
				}
			}
			foreach ( $vc_folders as $vc_folder ) {

				if ( is_dir( $css_styles_vc_modules ) ) {
					$css_courses_styles_parts = array_diff(
						scandir(
							$css_styles_vc_modules
							. '/' .
							$vc_folder
						),
						array(
							'..',
							'.',
						)
					);
				}

				foreach ( $css_courses_styles_parts as $style ) {
					$styles[ "vc_modules/{$vc_folder}/{$style}" ] = $css_styles_vc_modules . $vc_folder . '/' . $style;
				}
			}
			foreach ( $styles as $style_name => $style ) {
				if ( is_dir( $style ) || ! file_exists( $style ) ) {
					continue;
				}
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$css = stm_lms_change_css_styles( file_get_contents( $style ) );
				$wp_filesystem->put_contents( $upload_dir . '/' . $style_name, $css, FS_CHMOD_FILE );
			}

			stm_lms_update_styles_version();
		}
	}
}

function stm_lms_change_css_styles( $css_content ) {
	$main_color      = STM_LMS_Options::get_option( 'main_color', '#385bce' );
	$secondary_color = STM_LMS_Options::get_option( 'secondary_color', '#17d292' );

	/*IMG Path*/
	$img_path = STM_LMS_URL . 'assets/';

	$original_colors = array(
		'#2985f7',
		'#385bce',
		'#17d292',
		'#5cbc87',
		'#109d87',
		'#195ec8',
		'#0050b4',
		'rgba(0,242,65,1)',
		'#21c0b7',
		'#e42cd9',
		'#f35151',
		'../../../',
		'../../',
	);
	$replace_colors  = array(
		$main_color,
		$main_color,
		$secondary_color,
		$secondary_color,
		$main_color,
		$main_color,
		$secondary_color,
		$main_color,
		$main_color,
		$main_color,
		$main_color,
		$img_path,
		$img_path,
	);

	return str_replace( $original_colors, $replace_colors, $css_content ?? '' );
}

function stm_lms_update_styles_version() {
	$version = intval( get_option( 'stm_lms_styles_v', 1 ) );
	update_option( 'stm_lms_styles_v', ++$version );

}
