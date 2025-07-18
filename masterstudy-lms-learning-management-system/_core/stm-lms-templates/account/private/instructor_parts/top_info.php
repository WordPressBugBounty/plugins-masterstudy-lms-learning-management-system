<?php
/**
 * @var $current_user
 * @var $title
 * @var $socials
 */

if ( empty( $current_user ) ) {
	$current_user = STM_LMS_User::get_current_user();
}

stm_lms_register_style( 'user_info_top' );

if ( empty( $title ) ) {
	$title = esc_html__( 'My profile', 'masterstudy-lms-learning-management-system' );
}

$position    = ( ! empty( $current_user['meta']['position'] ) ) ? $current_user['meta']['position'] : esc_html__( 'Instructor', 'masterstudy-lms-learning-management-system' );
$show_public = STM_LMS_Options::get_option( 'instructor_public_profile', true ) || empty( STM_LMS_User::instructor_public_page_url( $current_user['id'] ) );
?>

<div class="stm_lms_user_info_top">

	<div class="stm_lms_user_info_top__title">
		<h3 class="student_name stm_lms_update_field__first_name"><?php echo esc_attr( $current_user['login'] ); ?></h3>

		<h5 class="student_name_pos">
			<?php echo wp_kses_post( $position ); ?>
		</h5>
	</div>

	<div class="stm_lms_user_info_top__info">
		<?php
		STM_LMS_Templates::show_lms_template( 'account/private/instructor_parts/rating', array( 'current_user' => $current_user ) );
		do_action( 'stm_lms_user_info_top', $current_user );
		if ( $show_public ) {
			STM_LMS_Templates::show_lms_template( 'components/public-page-block', array( 'user_id' => $current_user['id'] ) );
		}
		?>

	</div>

</div>
