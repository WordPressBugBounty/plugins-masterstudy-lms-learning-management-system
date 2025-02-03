<?php
/**
 * @var int $id
 * @var int $user_id
 * @var int $course_id
 * @var boolean $lesson_completed
 */

use MasterStudy\Lms\Repositories\LessonRepository;

STM_LMS_Templates::show_lms_template(
	'components/video-media',
	array(
		'lesson'           => ( new LessonRepository() )->get( $id ),
		'id'               => $id,
		'user_id'          => $user_id,
		'course_id'        => $course_id,
		'lesson_completed' => $lesson_completed,
	)
);
