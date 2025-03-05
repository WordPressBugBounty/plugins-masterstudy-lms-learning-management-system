<?php
class STM_Course_Data_Store_CPT extends WC_Product_Data_Store_CPT implements WC_Object_Data_Store_Interface, WC_Product_Data_Store_Interface {
	/**
	 * Method to read a product from the database.
	 *
	 * @param WC_Product
	 *
	 * @throws Exception
	 */
	public function read( &$product ) {

		add_filter( 'woocommerce_is_purchasable', '__return_true' );

		$product->set_defaults();

		if ( has_filter( 'masterstudy_woo_post_types' ) ) {
			$post_types = apply_filters( 'masterstudy_woo_post_types', array( 'product' ) );
		} else {
			$post_types = array(
				MasterStudy\Lms\Plugin\PostType::COURSE,
				'product',
			);

			if ( is_ms_lms_addon_enabled( 'enterprise_courses' ) ) {
				$post_types[] = 'stm-ent-groups';
			}

			if ( is_ms_lms_addon_enabled( 'course_bundle' ) ) {
				$post_types[] = 'stm-course-bundles';
			}
		}
		$post_object = get_post( $product->get_id() );

		if ( ! $product->get_id() || ! $post_object || ! in_array( $post_object->post_type, $post_types, true ) ) {
			throw new Exception( __( 'Invalid product.', 'masterstudy-lms-learning-management-system' ) );
		}

		$product->set_id( $post_object->ID );

		$product->set_props(
			array(
				'product_id'        => $post_object->ID,
				'name'              => $post_object->post_title,
				'slug'              => $post_object->post_name,
				'date_created'      => 0 < $post_object->post_date_gmt ? wc_string_to_timestamp( $post_object->post_date_gmt ) : null,
				'date_modified'     => 0 < $post_object->post_modified_gmt ? wc_string_to_timestamp( $post_object->post_modified_gmt ) : null,
				'status'            => $post_object->post_status,
				'description'       => $post_object->post_content,
				'short_description' => $post_object->post_excerpt,
				'parent_id'         => $post_object->post_parent,
				'menu_order'        => $post_object->menu_order,
				'reviews_allowed'   => 'open' === $post_object->comment_status,
				'post_type'         => $post_object->post_type,
			)
		);

		$not_saleable      = get_post_meta( $post_object->ID, 'not_single_sale', true );
		$price             = get_post_meta( $post_object->ID, 'price', true );
		$sale_price        = get_post_meta( $post_object->ID, 'sale_price', true );
		$sale_price_active = STM_LMS_Helpers::is_sale_price_active( $post_object->ID );
		$thumbnail_id      = get_post_thumbnail_id( $post_object->ID );

		if ( 'on' !== $not_saleable ) {
			if ( $sale_price_active && ! empty( $price ) && ! empty( $sale_price ) ) {
				$product->set_regular_price( $price );
				$product->set_sale_price( $sale_price );
				$product->set_price( $sale_price );
			} else {
				$product->set_regular_price( $price );
				$product->set_price( $price );
			}
		}

		$product->set_image_id( $thumbnail_id ?? null );
		$product->set_virtual( true );
		$product->set_sold_individually( true );
		$product->set_downloadable( true );
		$this->read_attributes( $product );
		$this->read_downloads( $product );
		$this->read_visibility( $product );
		$this->read_product_data( $product );
		$this->read_extra_data( $product );
		$product->set_object_read( true );

		do_action( 'woocommerce_product_read', $product->get_id() );
	}

	/**
	 * Get the product type based on product ID.
	 *
	 * @param int $product_id
	 *
	 * @return bool|string
	 * @since 3.0.0
	 */

	public function get_product_type( $product_id ) {
		$cache_key    = WC_Cache_Helper::get_cache_prefix( 'product_' . $product_id ) . '_type_' . $product_id;
		$product_type = wp_cache_get( $cache_key, 'products' );

		if ( $product_type ) {
			return $product_type;
		}

		$post_type    = get_post_type( $product_id );
		$post_types   = apply_filters( 'masterstudy_woo_post_types', array() );
		$post_types[] = 'product';

		if ( 'product_variation' === $post_type ) {
			$product_type = 'variation';
		} elseif ( in_array( $post_type, $post_types, true ) ) {
			$terms        = get_the_terms( $product_id, 'product_type' );
			$product_type = ! empty( $terms ) && ! is_wp_error( $terms ) ? sanitize_title( current( $terms )->name ) : 'simple';
		} else {
			$product_type = false;
		}

		wp_cache_set( $cache_key, $product_type, 'products' );

		return $product_type;
	}
}
