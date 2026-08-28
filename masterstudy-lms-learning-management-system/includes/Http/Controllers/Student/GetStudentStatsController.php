<?php

namespace MasterStudy\Lms\Http\Controllers\Student;

use MasterStudy\Lms\Http\WpResponseFactory;
use MasterStudy\Lms\Utility\UserAuthorization;
use WP_REST_Request;
use WP_REST_Response;
use MasterStudy\Lms\Repositories\StudentsRepository;

class GetStudentStatsController {
	private StudentsRepository $students_repository;

	public function __construct() {
		$this->students_repository = new StudentsRepository();
	}

	public function __invoke( $student_id, WP_REST_Request $request ): \WP_REST_Response {
		$student_id = (int) $student_id;

		if ( ! UserAuthorization::can_access_private_data( $student_id ) ) {
			return WpResponseFactory::forbidden();
		}

		$courses = $this->students_repository->student_completed_courses( $student_id, array( 'course_id' ), -1 );

		return new \WP_REST_Response(
			array(
				'reviews'           => $this->students_repository->student_reviews_count( $student_id ),
				'courses_statuses'  => $this->students_repository->student_courses_statuses( $student_id ),
				'courses_types'     => $this->students_repository->student_courses_types( $student_id ),
				'total_points'      => $this->students_repository->student_total_points( $student_id ),
				'certificates'      => $this->students_repository->student_certificates_count( $courses ),
				'total_quizzes'     => $this->students_repository->student_total_quizzes( $student_id ),
				'total_assignments' => $this->students_repository->student_total_assignments( $student_id ),
			)
		);
	}
}
