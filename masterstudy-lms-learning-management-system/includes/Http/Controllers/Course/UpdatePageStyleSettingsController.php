<?php

namespace MasterStudy\Lms\Http\Controllers\Course;

use MasterStudy\Lms\Http\WpResponseFactory;
use MasterStudy\Lms\Repositories\CourseRepository;
use MasterStudy\Lms\Validation\Validator;
use WP_REST_Request;
use WP_REST_Response;

class UpdatePageStyleSettingsController {
	private CourseRepository $course_repository;

	public function __construct() {
		$this->course_repository = new CourseRepository();
	}

	public function __invoke( int $course_id, WP_REST_Request $request ): WP_REST_Response {
		$course = $this->course_repository->find_post( $course_id );

		if ( null === $course ) {
			return WpResponseFactory::not_found();
		}

		$validator = new Validator(
			$request->get_json_params(),
			array(
				'slug' => 'string',
			)
		);

		if ( $validator->fails() ) {
			return WpResponseFactory::validation_failed( $validator->get_errors_array() );
		}

		$data = $validator->get_validated();

		$this->course_repository->update_course_page_style( $course_id, $data['slug'] );

		return WpResponseFactory::ok();
	}
}
