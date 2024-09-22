<?php

namespace MasterStudy\Lms\Http\Controllers\Student;

use WP_REST_Response;
use WP_REST_Request;
use MasterStudy\Lms\Http\WpResponseFactory;
use MasterStudy\Lms\Repositories\CourseRepository;
use MasterStudy\Lms\Repositories\StudentsRepository;
use MasterStudy\Lms\Validation\Validator;

final class GetStudentsController {

	public function __invoke( WP_REST_Request $request ) {
		$validator = new Validator(
			$request->get_params(),
			array(
				's'         => 'nullable|string',
				'page'      => 'nullable|integer',
				'per_page'  => 'nullable|integer',
				'order'     => 'nullable|string',
				'orderby'   => 'nullable|string',
				'course_id' => 'nullable|integer',
			)
		);

		if ( $validator->fails() ) {
			return WpResponseFactory::validation_failed( $validator->get_errors_array() );
		}

		$params = $validator->get_validated();
		$repo   = new CourseRepository();

		if ( ! $repo->exists( $params['course_id'] ?? 0 ) ) {
			return WpResponseFactory::not_found();
		}

		if ( ! \STM_LMS_Course::check_course_author( $params['course_id'], get_current_user_id() ) ) {
			return WpResponseFactory::forbidden();
		}

		return new WP_REST_Response( ( new StudentsRepository() )->get_course_students( $params ) );
	}
}
