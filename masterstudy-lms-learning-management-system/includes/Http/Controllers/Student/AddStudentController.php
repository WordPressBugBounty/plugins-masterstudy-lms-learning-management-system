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
		$validator = new Validator(
			$request->get_params(),
			array(
				'email'      => 'required|string',
				'first_name' => 'string',
				'last_name'  => 'string',
				'course_id'  => 'nullable|integer',
			)
		);

		if ( $validator->fails() ) {
			return WpResponseFactory::validation_failed( $validator->get_errors_array() );
		}

		$data      = $validator->get_validated();
		$course_id = absint( $course_id );

		if ( ! empty( $data['course_id'] ) && absint( $data['course_id'] ) !== $course_id ) {
			return WpResponseFactory::validation_failed(
				array(
					'course_id' => array( esc_html__( 'Course ID does not match the route.', 'masterstudy-lms-learning-management-system' ) ),
				)
			);
		}

		if ( ! ( new CourseRepository() )->exists( $course_id ) ) {
			return WpResponseFactory::not_found();
		}

		if ( ! \STM_LMS_Course::check_course_author( $course_id, get_current_user_id() ) ) {
			return WpResponseFactory::forbidden();
		}

		return new WP_REST_Response( ( new StudentsRepository() )->add_student( $course_id, $data ) );
	}
}
