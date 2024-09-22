<?php
/**
 * @var int $id
 */

use MasterStudy\Lms\Repositories\LessonRepository;

STM_LMS_Templates::show_lms_template(
	'components/video-media',
	array(
		'lesson' => ( new LessonRepository() )->get( $id ),
		'id'     => $id,
	)
);
