<?php

namespace MasterStudy\Lms\Http\Controllers\Order;

use MasterStudy\Lms\Http\WpResponseFactory;
use MasterStudy\Lms\Plugin\PostType;
use MasterStudy\Lms\Repositories\OrderRepository;
use MasterStudy\Lms\Validation\Validator;
use WP_REST_Request;
use WP_REST_Response;

final class UpdateOrderController {
	public function __invoke( int $order_id, WP_REST_Request $request ): WP_REST_Response {
		$validator = new Validator(
			$request->get_params(),
			array(
				'status' => 'required|string',
				'note'   => 'nullable|string',
			)
		);

		if ( $validator->fails() ) {
			return WpResponseFactory::validation_failed( $validator->get_errors_array() );
		}

		if ( PostType::ORDER !== get_post_type( $order_id ) ) {
			return WpResponseFactory::not_found();
		}

		if ( ! current_user_can( 'manage_options' ) && ! \STM_LMS_Order::instructor_can_access_order( $order_id ) ) {
			return WpResponseFactory::forbidden();
		}

		$success = ( new OrderRepository() )->update_order( $order_id, $validator->get_validated() );

		return new WP_REST_Response(
			array(
				'success' => $success,
			)
		);
	}
}
