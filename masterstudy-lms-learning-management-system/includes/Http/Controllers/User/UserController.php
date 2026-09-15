<?php

namespace MasterStudy\Lms\Http\Controllers\User;

use WP_REST_Request;

class UserController extends \WP_REST_Users_Controller {
	public function search( WP_REST_Request $request ) {
		$wp_request = $this->prepare_wp_request( $request );
		$permission = $this->get_items_permissions_check( $wp_request );

		if ( is_wp_error( $permission ) || true !== $permission ) {
			return $permission;
		}

		return $this->get_items( $wp_request );
	}

	/**
	 * Prepare a WP_REST_Request with collection params.
	 */
	private function prepare_wp_request( WP_REST_Request $request ) {
		$params      = $this->get_collection_params();
		$new_request = new WP_REST_Request();

		foreach ( $params as $key => $value ) {
			if ( isset( $value['default'] ) ) {
				$new_request->set_param( $key, $value['default'] );
			}
		}

		foreach ( $request->get_params() as $key => $value ) {
			if ( 'roles' === $key ) {
				$roles_array = explode( ',', $value );
				$new_request->set_param( $key, $roles_array );
			} else {
				$new_request->set_param( $key, $value );
			}
		}

		return $new_request;
	}
}
