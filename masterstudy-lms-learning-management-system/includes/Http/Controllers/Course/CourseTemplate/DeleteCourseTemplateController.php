<?php

namespace MasterStudy\Lms\Http\Controllers\Course\CourseTemplate;

use MasterStudy\Lms\Http\WpResponseFactory;
use MasterStudy\Lms\Repositories\CourseTemplateRepository;

class DeleteCourseTemplateController {

	public function __invoke( int $template_id ) {
		$result = ( new CourseTemplateRepository() )->delete( $template_id );

		if ( ! $result ) {
			return WpResponseFactory::error(
				esc_html__( 'Course delete template is failed', 'masterstudy-lms-learning-management-system' )
			);
		}

		return new \WP_REST_Response(
			array(
				'status' => 'success',
			)
		);
	}
}
