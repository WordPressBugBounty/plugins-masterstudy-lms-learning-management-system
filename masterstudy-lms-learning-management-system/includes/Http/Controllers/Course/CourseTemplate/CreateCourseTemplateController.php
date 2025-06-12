<?php

namespace MasterStudy\Lms\Http\Controllers\Course\CourseTemplate;

use MasterStudy\Lms\Repositories\CourseTemplateRepository;
use MasterStudy\Lms\Http\WpResponseFactory;
use WP_REST_Request;

class CreateCourseTemplateController {

	public function __invoke( WP_REST_Request $request ): \WP_REST_Response {
		$data = $request->get_json_params();

		if ( empty( $data['title'] ) ) {
			return WpResponseFactory::validation_failed( 'Missing title' );
		}

		$result = ( new CourseTemplateRepository() )->create( $data['title'] );

		if ( ! is_array( $result ) ) {
			return WpResponseFactory::error(
				esc_html__( 'Course create template is failed', 'masterstudy-lms-learning-management-system' )
			);
		}

		return new \WP_REST_Response(
			$result
		);
	}
}
