<?php

namespace MasterStudy\Lms\Http\Controllers\Student;

use MasterStudy\Lms\Http\WpResponseFactory;
use MasterStudy\Lms\Repositories\CourseRepository;
use MasterStudy\Lms\Repositories\StudentsRepository;

final class DeleteStudentController {

	public function __invoke( $course_id, $student_id ) {
		$repo = new CourseRepository();

		if ( ! $repo->exists( $course_id ) ) {
			return WpResponseFactory::not_found();
		}

		if ( ! \STM_LMS_Course::check_course_author( $course_id, get_current_user_id() ) ) {
			return WpResponseFactory::forbidden();
		}

		( new StudentsRepository() )->delete_student( $course_id, $student_id );

		return WpResponseFactory::ok();
	}
}
