<?php

namespace MasterStudy\Lms\Http\Controllers\Course\Curriculum;

use MasterStudy\Lms\Http\WpResponseFactory;
use MasterStudy\Lms\Repositories\CourseRepository;
use MasterStudy\Lms\Repositories\CurriculumMaterialRepository;

class DeleteMaterialController {
	public function __invoke( $course_id, $material_id ): \WP_REST_Response {
		if ( ! ( new CourseRepository() )->exists( $course_id ) ) {
			return WpResponseFactory::not_found();
		}

		$repository = new CurriculumMaterialRepository();

		if ( false === $repository->find_for_course( (int) $material_id, (int) $course_id ) ) {
			return WpResponseFactory::not_found();
		}

		$repository->delete( $material_id );

		return WpResponseFactory::ok();

	}
}
