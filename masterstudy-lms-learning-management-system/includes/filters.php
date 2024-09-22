<?php

/** @var \MasterStudy\Lms\Plugin $plugin */
add_filter( 'wp_rest_search_handlers', array( $plugin, 'register_search_handlers' ) );

add_filter(
	'rest_user_collection_params',
	function ( $query_params ) {
		$query_params['orderby']['enum'][] = 'rating';

		return $query_params;
	}
);

add_filter(
	'rest_user_query',
	function ( array $prepared_args, \WP_REST_Request $request ) {
		unset( $prepared_args['has_published_posts'] );

		if ( isset( $request['orderby'] ) && 'rating' === $request['orderby'] ) {
			$prepared_args['meta_key'] = 'sum_rating';
			$prepared_args['orderby']  = 'meta_value_num';
		}

		return $prepared_args;
	},
	10,
	2
);

add_filter(
	'masterstudy_lms_lesson_video_sources',
	function () {
		return array_map(
			function ( $id, $label ) {
				return array(
					'id'    => $id,
					'label' => $label,
				);
			},
			array_keys( apply_filters( 'ms_plugin_video_sources', array() ) ),
			array_values( apply_filters( 'ms_plugin_video_sources', array() ) )
		);
	}
);

function masterstudy_lms_rest_api_user( $data, $user, $request ) {
	$user_meta = get_user_meta( $user->ID );

	$data->data['avatar']        = ! empty( $user_meta['stm_lms_user_avatar'] ) ? $user_meta['stm_lms_user_avatar'][0] : get_avatar_url( $user->ID );
	$data->data['position']      = $user_meta['position'][0] ?? '';
	$data->data['facebook']      = $user_meta['facebook'][0] ?? '';
	$data->data['twitter']       = $user_meta['twitter'][0] ?? '';
	$data->data['instagram']     = $user_meta['instagram'][0] ?? '';
	$data->data['linkedin']      = $user_meta['linkedin'][0] ?? '';
	$data->data['sum_rating']    = ! empty( $user_meta['sum_rating'][0] ) ? $user_meta['sum_rating'][0] : '0';
	$data->data['total_reviews'] = ! empty( $user_meta['total_reviews'][0] ) ? $user_meta['total_reviews'][0] : '0';
	$data->data['courses']       = \STM_LMS_Instructor::get_course_quantity( $user->ID );
	$data->data['page_url']      = \STM_LMS_User::user_public_page_url( $user->ID );

	return $data;
}
add_filter( 'rest_prepare_user', 'masterstudy_lms_rest_api_user', 10, 3 );

function masterstudy_lms_double_slash_api_data( $value, $key ) {
	$double_slashes = array(
		'post_title',
		'post_content',
		'answers',
	);

	if ( in_array( $key, $double_slashes, true ) ) {
		if ( is_array( $value ) ) {
			array_walk_recursive(
				$value,
				function ( &$item, $item_key ) {
					if ( in_array( $item_key, array( 'question', 'text' ), true ) ) {
						$item = str_replace( '\\', '\\\\', $item );
					}
				}
			);
		} else {
			$value = str_replace( '\\', '\\\\', $value );
		}
	}

	return $value;
}
add_filter( 'masterstudy_lms_map_api_data', 'masterstudy_lms_double_slash_api_data', 10, 2 );

function masterstudy_lms_allow_iframe_to_instructor( $allowed_tags ) {
	if ( ! current_user_can( 'stm_lms_instructor' ) ) {
		return $allowed_tags;
	}

	$allowed_tags['iframe'] = array(
		'src'             => true,
		'width'           => true,
		'height'          => true,
		'frameborder'     => true,
		'allowfullscreen' => true,
	);

	return $allowed_tags;
}
add_filter( 'wp_kses_allowed_html', 'masterstudy_lms_allow_iframe_to_instructor', 1 );

/**
 * Register Blocks Category class.
 *
 * @access public
 * @param array $categories - block categories.
 * @return array - returns block categories
 **/
function masterstudy_lms_gutenberg_blocks_category( $categories ) {
	array_unshift(
		$categories,
		array(
			'slug'  => 'masterstudy-lms-blocks',
			'title' => esc_html__( 'Masterstudy Blocks', 'masterstudy-lms-learning-management-system' ),
		),
	);
	return $categories;
}
if ( version_compare( get_bloginfo( 'version' ), '5.8', '>=' ) ) {
	add_filter( 'block_categories_all', 'masterstudy_lms_gutenberg_blocks_category' );
} else {
	add_filter( 'block_categories', 'masterstudy_lms_gutenberg_blocks_category' );
}
// Loads block scripts only if they are used.
add_filter( 'should_load_separate_core_block_assets', '__return_true' );

function masterstudy_lms_pre_get_document_title( $title ) {
	$lms_path = get_query_var( 'lms_template' );

	if ( ! empty( $lms_path ) ) {
		$pages_config = STM_LMS_Page_Router::pages_config();
		$user_pages   = array_column( $pages_config['user_url']['sub_pages'] ?? array(), 'template' );

		if ( in_array( $lms_path, $user_pages, true ) ) {
			$settings        = get_option( 'stm_lms_settings', array() );
			$account_page_id = apply_filters( 'wpml_object_id', $settings['user_url'] ?? null, 'post' );

			return get_the_title( $account_page_id );
		}
	}

	return $title;
}
add_filter( 'pre_get_document_title', 'masterstudy_lms_pre_get_document_title', 100 );

function masterstudy_lms_capture_textdomain_path( $mofile, $domain ) {
	if ( 'masterstudy-lms-learning-management-system' === $domain ) {
		global $ms_lms_loaded_textdomain_path;
		$ms_lms_loaded_textdomain_path = dirname( $mofile );
	}

	return $mofile;
}
add_filter( 'load_textdomain_mofile', 'masterstudy_lms_capture_textdomain_path', 10, 2 );

