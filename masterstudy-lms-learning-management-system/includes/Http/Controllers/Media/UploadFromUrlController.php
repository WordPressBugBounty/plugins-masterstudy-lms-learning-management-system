<?php

namespace MasterStudy\Lms\Http\Controllers\Media;

use MasterStudy\Lms\Http\WpResponseFactory;
use MasterStudy\Lms\Utility\Media;
use MasterStudy\Lms\Validation\Validator;
use WP_REST_Request;
use WP_REST_Response;

final class UploadFromUrlController {
	public function __invoke( WP_REST_Request $request ): WP_REST_Response {
		$validator = new Validator(
			$request->get_params(),
			array(
				'url' => 'required|url',
			)
		);

		if ( $validator->fails() ) {
			return WpResponseFactory::validation_failed( $validator->get_errors_array() );
		}

		$validated_data = $validator->get_validated();

		$result = Media::create_attachment_from_url( $validated_data['url'], '', false );

		if ( is_wp_error( $result ) ) {
			return WpResponseFactory::error( $result->get_error_message() );
		}

		return new WP_REST_Response(
			array(
				'file' => $result,
			)
		);
	}
}
