<?php

namespace MasterStudy\Lms\Routing\Swagger\Routes\Course;

use MasterStudy\Lms\Routing\Swagger\Fields\Addon;
use MasterStudy\Lms\Routing\Swagger\Fields\Post;
use MasterStudy\Lms\Routing\Swagger\ResponseInterface;
use MasterStudy\Lms\Routing\Swagger\Route;

class Edit extends Route implements ResponseInterface {
	public function response(): array {
		return array(
			'course'              => Post::as_object(),
			'addons'              => Addon::as_array(),
			'plugins'             => array(
				'type'        => 'array',
				'description' => 'List of Plugins and boolean value of if plugin is active for Course Builder features.',
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'lms_pro'       => array(
							'type'        => 'boolean',
							'description' => 'MasterStudy LMS Pro plugin.',
						),
						'presto_player' => array(
							'type'        => 'boolean',
							'description' => 'Presto Player plugin.',
						),
						'vdocipher'     => array(
							'type'        => 'boolean',
							'description' => 'VdoCipher plugin.',
						),
						'pmpro'         => array(
							'type'        => 'boolean',
							'description' => 'Paid Membership Pro plugin.',
						),
						'eroom'         => array(
							'type'        => 'boolean',
							'description' => 'eRoom - Zoom Meetings & Webinar plugin.',
						),
					),
				),
			),
			'options'             => array(
				'type'        => 'array',
				'description' => 'Extra Options for Course Settings.',
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'max_upload_size'           => array(
							'type'        => 'string',
							'description' => 'Maximum media upload size. Human readable.',
							'exmaple'     => '8 MB',
						),
						'is_instructor'             => array(
							'type'        => 'boolean',
							'description' => 'Current user has instructor role.',
						),
						'create_category_allowed'   => array(
							'type'        => 'boolean',
							'description' => 'Is an instructor allowed to create new Course Categories.',
						),
						'question_category_allowed' => array(
							'type'        => 'boolean',
							'description' => 'Is an instructor allowed to create new Question Categories.',
						),
						'course_premoderation'      => array(
							'type'        => 'boolean',
							'description' => 'Administrator will moderate Courses before Publishing.',
						),
						'course_style'              => array(
							'type'        => 'boolean',
							'description' => 'Course Page Style.',
						),
						'currency_symbol'           => array(
							'type'        => 'string',
							'description' => 'Currency symbol.',
						),
						'quiz_attempts'             => array(
							'type'        => 'string',
							'description' => 'Quiz attempts limited or unlimited.',
						),
						'presto_player_allowed'     => array(
							'type'        => 'boolean',
							'description' => 'True when LMS option "course_allow_presto_player" enabled or current user is Admin.',
						),
						'sequential_drip_content'   => array(
							'type'        => 'object',
							'description' => 'Drip Content addon settings, only if addon enabled',
							'properties'  => array(
								'locked'            => array(
									'type'        => 'boolean',
									'description' => 'Lock lessons sequentially',
								),
								'lock_before_start' => array(
									'type'        => 'boolean',
									'description' => 'Lock lesson before it starts',
								),
							),
						),
					),
				),
			),
			'urls'                => array(
				'type'        => 'array',
				'description' => 'URLs from WordPress environment.',
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'courses'           => array(
							'type'        => 'string',
							'format'      => 'uri',
							'description' => 'Courses page URL.',
						),
						'user_account'      => array(
							'type'        => 'string',
							'format'      => 'uri',
							'description' => 'User Account dashboard page URL.',
						),
						'dashboard_courses' => array(
							'type'        => 'string',
							'format'      => 'uri',
							'description' => 'Dashboard > LMS > Courses URL.',
						),
						'addons'            => array(
							'type'        => 'string',
							'format'      => 'uri',
							'description' => 'Dashboard > LMS Addons page URL.',
						),
						'plugins'           => array(
							'type'        => 'string',
							'format'      => 'uri',
							'description' => 'Dashboard > Plugins page URL.',
						),
						'settings'          => array(
							'type'        => 'string',
							'format'      => 'uri',
							'description' => 'Dashboard > LMS Settings page URL.',
						),
					),
				),
			),
			'lesson_types'        => array(
				'type'             => 'array',
				'uniqueItems'      => true,
				'collectionFormat' => 'multi',
				'items'            => array(
					'type'       => 'string',
					'properties' => array(
						'lesson_type' => array(
							'type' => 'string',
						),
					),
				),
			),
			'video_sources'       => array(
				'type'        => 'array',
				'description' => 'Source type - List of all Video Types. Available for lesson type Video.',
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'    => array(
							'type'        => 'string',
							'description' => 'Video Source slug/value.',
						),
						'label' => array(
							'type'        => 'string',
							'description' => 'Video Source label.',
						),
					),
				),
			),
			'presto_player_posts' => array(
				'type'        => 'array',
				'description' => 'Select Video - List of all Presto Player Posts. Available for lesson type Video.',
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'    => array(
							'type'        => 'string',
							'description' => 'Presto Player Post ID.',
						),
						'title' => array(
							'type'        => 'string',
							'description' => 'Presto Player Post Title.',
						),
					),
				),
			),
			'timezones'           => array(
				'type'        => 'array',
				'description' => 'List of all Timezones.',
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'    => array(
							'type'        => 'string',
							'description' => 'Timezone value.',
						),
						'label' => array(
							'type'        => 'string',
							'description' => 'Timezone label.',
						),
					),
				),
			),
			'current_user_id'     => array(
				'type'        => 'integer',
				'description' => 'Current User ID.',
			),
		);
	}

	public function get_summary(): string {
		return 'Edit an Existing Course';
	}

	public function get_description(): string {
		return 'Returns Course, Addons and Curriculum data.';
	}
}
