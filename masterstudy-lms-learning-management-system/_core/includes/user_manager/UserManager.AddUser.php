<?php

new STM_LMS_User_Manager_Add_User();

class STM_LMS_User_Manager_Add_User {

	public function __construct() {
		add_action( 'wp_ajax_stm_lms_dashboard_add_user_to_course', array( $this, 'add_user' ) );
	}

	public function add_user() {
		check_ajax_referer( 'stm_lms_dashboard_add_user_to_course', 'nonce' );

		$request_body = file_get_contents( 'php://input' );

		$data = json_decode( $request_body, true );

		if ( empty( $data['email'] ) ) {
			die;
		}

		$email     = sanitize_text_field( $data['email'] );
		$course_id = absint( $data['course_id'] ?? 0 );
		$user_id   = get_current_user_id();

		if ( ! is_email( $email ) || empty( $course_id ) || empty( $user_id ) ) {
			wp_send_json(
				array(
					'status'  => 'error',
					'message' => esc_html__( 'Invalid request.', 'masterstudy-lms-learning-management-system' ),
				)
			);
		}

		if ( ! STM_LMS_Course::check_course_author( $course_id, $user_id ) && ! current_user_can( 'edit_post', $course_id ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Unauthorized request.', 'masterstudy-lms-learning-management-system' ),
				),
				403
			);
		}

		$adding = STM_LMS_Instructor::add_student_to_course( array( $course_id ), array( $email ) );

		if ( ! $adding['error'] ) {
			$adding['status'] = 'success';
		}

		wp_send_json( $adding );
	}
}
