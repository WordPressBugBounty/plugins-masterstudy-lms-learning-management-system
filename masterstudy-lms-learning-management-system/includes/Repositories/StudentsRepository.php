<?php

namespace MasterStudy\Lms\Repositories;

use MasterStudy\Lms\Repositories\CurriculumMaterialRepository;
use MasterStudy\Lms\Plugin\PostType;

final class StudentsRepository {

	public function get_course_students( array $params = array() ) {
		global $wpdb;
		$course_table = stm_lms_user_courses_name( $wpdb );
		$user_table   = $wpdb->users;

		$fields = "{$course_table}.user_id, {$course_table}.course_id, {$course_table}.start_time, {$course_table}.progress_percent, {$user_table}.display_name";

		$per_page  = $params['per_page'] ?? 10;
		$page      = $params['page'] ?? 1;
		$course_id = $params['course_id'] ?? 0;
		$offset    = ( $page - 1 ) * $per_page;
		$filtering = '';

		if ( ! empty( $params['order'] ) && ! empty( $params['orderby'] ) ) {
			$order = strtoupper( $params['order'] );
			if ( in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
				switch ( $params['orderby'] ) {
					case 'username':
						$filtering .= " ORDER BY {$user_table}.display_name {$order}";
						break;
					case 'email':
						$filtering .= " ORDER BY {$user_table}.user_email {$order}";
						break;
					case 'ago':
						$filtering .= " ORDER BY {$course_table}.start_time {$order}";
						break;
					case 'progress_percent':
						$filtering .= " ORDER BY {$course_table}.progress_percent {$order}";
						break;
				}
			}
		}

		if ( ! empty( $params['s'] ) ) {
			$filtering = $wpdb->prepare(
				' AND LOWER(display_name) LIKE %s',
				'%' . strtolower( $params['s'] ) . '%'
			);
		}

		$students = $wpdb->get_results(
			$wpdb->prepare(
				//phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT {$fields} FROM {$course_table} INNER JOIN $user_table ON {$course_table}.user_id = {$user_table}.ID WHERE {$course_table}.course_id = %d {$filtering} LIMIT %d OFFSET %d",
				$course_id,
				$per_page,
				$offset
			),
			ARRAY_A
		);

		foreach ( $students as &$data ) {
			$data                  = ( new \STM_LMS_User_Manager_Course() )->map_students( $data );
			$student_id            = $data['user_id'];
			$data['progress_link'] = \STM_LMS_Instructor::instructor_manage_students_url() . "/?course_id=$course_id&student_id=$student_id";
		}

		$total_rows = $wpdb->get_var(
			$wpdb->prepare(
				//phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$course_table} INNER JOIN {$user_table} ON {$course_table}.user_id = {$user_table}.ID WHERE {$course_table}.course_id = %d {$filtering}",
				$course_id
			)
		);

		$output = array(
			'students'  => $students,
			'page'      => $page,
			'total'     => $this->get_course_students_count( $course_id ),
			'per_page'  => $per_page,
			'max_pages' => ceil( $total_rows / $per_page ),
		);

		return $output;
	}

	public function get_course_students_count( $course_id ) {
		global $wpdb;
		$course_table = stm_lms_user_courses_name( $wpdb );

		return $wpdb->get_var(
			$wpdb->prepare(
				//phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$course_table} WHERE course_id = %d",
				$course_id
			)
		);
	}

	public function add_student( $course_id, $data ) {
		$course_id          = intval( $course_id );
		$user               = get_user_by( 'email', $data['email'] );
		$is_enrolled        = false;
		$is_enrolled_before = false;

		if ( $user ) {
			$course             = \STM_LMS_Course::get_user_course( $user->ID, $course_id );
			$is_enrolled_before = ! empty( $course ) && intval( $course['course_id'] ) === $course_id;
		}

		$added = \STM_LMS_Instructor::add_student_to_course( array( $course_id ), array( $data['email'] ) );

		if ( ! $added['error'] ) {
			$first_name  = sanitize_text_field( trim( $user_data['first_name'] ?? '' ) );
			$last_name   = sanitize_text_field( trim( $user_data['last_name'] ?? '' ) );
			$user        = get_user_by( 'email', $data['email'] );
			$is_enrolled = true;

			if ( $user && ( $first_name || $last_name ) ) {
				wp_update_user(
					array(
						'ID'           => $user->ID,
						'first_name'   => $first_name,
						'last_name'    => $last_name,
						'display_name' => "$first_name $last_name",
					)
				);
			}
		}

		return array(
			'email'              => $data['email'],
			'student_id'         => $user ? $user->ID : 0,
			'is_enrolled'        => $is_enrolled,
			'is_enrolled_before' => $is_enrolled_before,
		);
	}

	public function delete_student( $course_id, $student_id ) {
		$userdata = get_userdata( $student_id );

		if ( $userdata ) {
			stm_lms_get_delete_user_course( $student_id, $course_id );
			$meta = \STM_LMS_Helpers::parse_meta_field( $course_id );

			if ( ! empty( $meta['current_students'] ) && $meta['current_students'] > 0 ) {
				update_post_meta( $course_id, 'current_students', --$meta['current_students'] );
			}
		}
	}

	public function export_students( $course_id ) {
		$users      = stm_lms_get_course_users( $course_id );
		$users_data = array();

		foreach ( $users as $user ) {
			if ( isset( $user['user_id'] ) ) {
				$user_data    = get_userdata( $user['user_id'] );
				$users_data[] = array(
					'email'      => $user_data->user_email,
					'first_name' => $user_data->first_name,
					'last_name'  => $user_data->last_name,
				);
			}
		}

		return $users_data;
	}

	public function set_student_progress( $course_id, $student_id, $data ) {
		$item_id   = $data['item_id'];
		$completed = rest_sanitize_boolean( $data['completed'] );

		$course_materials = ( new CurriculumMaterialRepository() )->get_course_materials( $course_id );
		// phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
		if ( empty( $course_materials ) || ! in_array( $item_id, $course_materials ) ) {
			return array();
		}

		switch ( get_post_type( $item_id ) ) {
			case 'stm-lessons':
				\STM_LMS_User_Manager_Course_User::complete_lesson( $student_id, $course_id, $item_id );
				break;
			case 'stm-assignments':
				\STM_LMS_User_Manager_Course_User::complete_assignment( $student_id, $course_id, $item_id, $completed );
				break;
			case 'stm-quizzes':
				\STM_LMS_User_Manager_Course_User::complete_quiz( $student_id, $course_id, $item_id, $completed );
				break;
		}

		\STM_LMS_Course::update_course_progress( $student_id, $course_id );

		return \STM_LMS_User_Manager_Course_User::_student_progress( $course_id, $student_id );
	}

	public function reset_student_progress( $course_id, $student_id ) {
		$curriculum = ( new CurriculumRepository() )->get_curriculum( $course_id );

		if ( empty( $curriculum['materials'] ) ) {
			return array();
		}

		foreach ( $curriculum['materials'] as $material ) {
			switch ( $material['post_type'] ) {
				case 'stm-lessons':
					\STM_LMS_User_Manager_Course_User::reset_lesson( $student_id, $course_id, $material['post_id'] );
					break;
				case 'stm-assignments':
					\STM_LMS_User_Manager_Course_User::reset_assignment( $student_id, $course_id, $material['post_id'] );
					break;
				case 'stm-quizzes':
					\STM_LMS_User_Manager_Course_User::reset_quiz( $student_id, $course_id, $material['post_id'] );
					break;
			}
		}

		stm_lms_reset_user_answers( $course_id, $student_id );

		\STM_LMS_Course::update_course_progress( $student_id, $course_id, true );

		return \STM_LMS_User_Manager_Course_User::_student_progress( $course_id, $student_id );
	}

	public function student_reviews_count( $student_id ) {
		global $wpdb;

		$review_post_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(p.ID) AS review_post_count
				FROM {$wpdb->prefix}posts AS p
				INNER JOIN {$wpdb->prefix}postmeta AS pm ON p.ID = pm.post_id
				WHERE pm.meta_key = 'review_user' AND pm.meta_value = %s AND p.post_type = 'stm-reviews' AND p.post_status = 'publish'",
				$student_id
			)
		);

		return intval( $review_post_count );
	}

	public function student_courses_statuses( $student_id ) {
		global $wpdb;

		$user_courses = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT course_id, progress_percent FROM {$wpdb->prefix}stm_lms_user_courses WHERE user_id = %d",
				$student_id
			),
			ARRAY_A
		);

		$statuses = array(
			'summary'     => 0,
			'completed'   => 0,
			'not_started' => 0,
			'failed'      => 0,
			'in_progress' => 0,
		);

		if ( empty( $user_courses ) ) {
			return $statuses;
		}

		foreach ( $user_courses as $course ) {
			$course_id        = $course['course_id'];
			$curriculum       = ( new CurriculumRepository() )->get_curriculum( $course_id, true );
			$course_materials = array_reduce(
				$curriculum,
				function ( $carry, $section ) {
					return array_merge( $carry, $section['materials'] ?? array() );
				},
				array()
			);
			$material_ids     = array_column( $course_materials, 'post_id' );
			$last_lesson      = ! empty( $material_ids ) ? end( $material_ids ) : 0;
			$lesson_post_type = get_post_type( $last_lesson );

			if ( PostType::QUIZ === $lesson_post_type ) {
				$last_quiz        = \STM_LMS_Helpers::simplify_db_array( stm_lms_get_user_last_quiz( $student_id, $last_lesson ) );
				$passing_grade    = get_post_meta( $last_lesson, 'passing_grade', true );
				$lesson_completed = ! empty( $last_quiz['progress'] ) && $last_quiz['progress'] >= ( $passing_grade ?? 0 ) ? 'completed' : '';
			} else {
				$lesson_completed = \STM_LMS_Lesson::is_lesson_completed( $student_id, $course_id, $last_lesson ) ? 'completed' : '';
			}

			$course_passed = intval( \STM_LMS_Options::get_option( 'certificate_threshold', 70 ) ) <= intval( $course['progress_percent'] );

			if ( ! empty( $lesson_completed ) && ! $course_passed ) {
				$statuses['failed']++;
			} elseif ( intval( $course['progress_percent'] ) > 0 ) {
				if ( $course_passed ) {
					$statuses['completed']++;
				} else {
					$statuses['in_progress']++;
				}
			} else {
				$statuses['not_started']++;
			}

			$statuses['summary']++;
		}

		return $statuses;
	}

	public function student_courses_types( $student_id ) {
		if ( ! \STM_LMS_Helpers::is_pro() ) {
			return array(
				'bundle_count'     => 0,
				'enterprise_count' => 0,
			);
		}

		global $wpdb;
		$user_email = get_user_by( 'id', $student_id )->user_email;
		$results    = $wpdb->get_row(
			$wpdb->prepare(
				"
				SELECT
				(SELECT COUNT(DISTINCT bundle_id) FROM {$wpdb->prefix}stm_lms_user_courses WHERE bundle_id > 0 AND user_id = %d) AS bundle_count,
				(SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->prefix}posts p
				JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
				WHERE p.post_type = 'stm-ent-groups'
				AND (
					(pm.meta_key = 'emails' AND pm.meta_value LIKE %s) OR
					(pm.meta_key = 'author_id' AND pm.meta_value = %d)
				)) AS enterprise_count
				",
				$student_id,
				'%' . $wpdb->esc_like( $user_email ) . '%',
				$student_id
			),
			ARRAY_A
		);

		return array_map( 'intval', $results );
	}

	public function student_completed_courses( $student_id, $fields = array(), $limit = 1 ) {
		global $wpdb;

		$table     = $wpdb->prefix . 'stm_lms_user_courses';
		$fields    = ( empty( $fields ) ) ? '*' : implode( ',', $fields );
		$threshold = \STM_LMS_Options::get_option( 'certificate_threshold', 70 );

		$query = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT {$fields} FROM {$table} WHERE user_ID = %d AND progress_percent >= %d",
			$student_id,
			$threshold
		);

		if ( -1 !== $limit ) {
			$query .= $wpdb->prepare( ' LIMIT %d', $limit );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $query, ARRAY_A );
	}

	public function student_certificates_count( $courses ) {
		if ( ! \STM_LMS_Helpers::is_pro() || ! is_ms_lms_addon_enabled( 'certificate_builder' ) ) {
			return array();
		}

		global $wpdb;
		$certificates = array();

		foreach ( $courses as $course ) {
			$course_terms    = wp_get_post_terms( $course['course_id'], 'stm_lms_course_taxonomy', array( 'fields' => 'ids' ) );
			$categories_list = implode( ',', array_map( 'intval', $course_terms ) );

			$certificate_ids = $wpdb->get_col(
				$wpdb->prepare(
					"
					SELECT p.ID
					FROM {$wpdb->posts} AS p
					INNER JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id
					WHERE p.post_type = 'stm-certificates'
					AND pm.meta_key = 'stm_category'
					AND (pm.meta_value REGEXP CONCAT('(^|,)', %s, '(,|$)'))
					ORDER BY pm.meta_value ASC
					LIMIT 1
					",
					$categories_list
				)
			);

			if ( empty( $certificate_ids ) ) {
				$certificate_ids = get_option( 'stm_default_certificate', '' );
			}

			$course_certificate = get_post_meta( $course['course_id'], 'course_certificate', true );

			$certificates[ $course['course_id'] ] = ! empty( $course_certificate ) || ! empty( $certificate_ids );
		}

		return count( $certificates );
	}

	public function student_total_points( $student_id ) {
		if ( ! \STM_LMS_Helpers::is_pro() || ! is_ms_lms_addon_enabled( 'point_system' ) ) {
			return array();
		}

		global $wpdb;

		$table        = $wpdb->prefix . 'stm_lms_user_points';
		$total_points = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT SUM(score) FROM {$table} WHERE `user_id` = %d",
				$student_id
			)
		);

		return (int) $total_points;
	}
}
