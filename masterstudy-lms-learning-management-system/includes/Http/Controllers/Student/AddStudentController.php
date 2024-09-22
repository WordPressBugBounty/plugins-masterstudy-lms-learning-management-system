<?php

namespace MasterStudy\Lms\Http\Controllers\Student;

use WP_REST_Request;
use WP_REST_Response;
use MasterStudy\Lms\Validation\Validator;
use MasterStudy\Lms\Http\WpResponseFactory;
use MasterStudy\Lms\Repositories\CourseRepository;
use MasterStudy\Lms\Repositories\StudentsRepository;

class AddStudentController {
	public function __invoke( $course_id, WP_REST_Request $request ) {
		if ( ! ( new CourseRepository() )->exists( $course_id ) ) {
			return WpResponseFactory::not_found();
		}

		$validator = new Validator(
			$request->get_params(),
			array(
				'email'      => 'required|string',
				'first_name' => 'string',
				'last_name'  => 'string',
			)
		);

		if ( $validator->fails() ) {
			return WpResponseFactory::validation_failed( $validator->get_errors_array() );
		}

		$data = $validator->get_validated();

		return new WP_REST_Response( ( new StudentsRepository() )->add_student( $course_id, $data ) );
	}
}
