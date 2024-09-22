<?php

namespace MasterStudy\Lms\Http\Controllers\Student;

use WP_REST_Response;
use MasterStudy\Lms\Http\WpResponseFactory;
use MasterStudy\Lms\Repositories\CourseRepository;
use MasterStudy\Lms\Repositories\StudentsRepository;

class ExportStudentsController {
	public function __invoke( $course_id ) {
		if ( ! ( new CourseRepository() )->exists( $course_id ) ) {
			return WpResponseFactory::not_found();
		}

		return new WP_REST_Response( ( new StudentsRepository() )->export_students( $course_id ) );
	}
}
