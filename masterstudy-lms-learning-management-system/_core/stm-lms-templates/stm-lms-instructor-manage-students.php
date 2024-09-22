<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$course_id  = intval( $_GET['course_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$student_id = intval( $_GET['student_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

if ( ! is_user_logged_in() || ! STM_LMS_Instructor::instructor_can_add_students() || ! STM_LMS_Instructor::is_instructor() || ! \STM_LMS_Course::check_course_author( $course_id, get_current_user_id() ) ) {
	wp_safe_redirect( STM_LMS_User::login_page_url() );
	die;
}

$course = STM_LMS_Course::get_user_course( $student_id, $course_id );

if ( ! empty( $student_id ) && ( empty( $course ) || intval( $course['course_id'] ) !== $course_id ) ) {
	wp_safe_redirect( STM_LMS_Instructor::instructor_manage_students_url() . "/?course_id=$course_id" );
	die;
}

get_header();

do_action( 'stm_lms_template_main' );

$style = STM_LMS_Options::get_option( 'profile_style', 'default' );
?>

<?php STM_LMS_Templates::show_lms_template( 'modals/preloader' ); ?>

	<div class="stm-lms-wrapper stm-lms-wrapper--assignments user-account-page">

		<div class="container">

			<?php
			if ( ! empty( $course_id ) && empty( $student_id ) ) :
				do_action( 'stm_lms_admin_after_wrapper_start', STM_LMS_User::get_current_user() );
				?>
				<div id="stm_lms_instructor_manage_students">
					<?php STM_LMS_Templates::show_lms_template( 'account/private/manage_students/main', compact( 'course_id' ) ); ?>
				</div>
			<?php endif; ?>

			<?php
			if ( ! empty( $course_id ) && ! empty( $student_id ) ) :
				?>
				<div id="stm_lms_instructor_manage_students">
					<?php STM_LMS_Templates::show_lms_template( 'account/private/manage_students/student-progress', compact( 'course_id', 'student_id' ) ); ?>
				</div>
			<?php endif; ?>

		</div>

	</div>

<?php get_footer(); ?>
