<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

STM_LMS_Templates::show_lms_template( 'header' );
do_action( 'stm_lms_template_main' );
?>

<?php STM_LMS_Templates::show_lms_template( 'modals/preloader' ); ?>
	<div class="stm-lms-wrapper">
		<div class="container">
			<?php STM_LMS_Templates::show_lms_template( 'courses_taxonomy/archive' ); ?>
		</div>
	</div>
<?php
STM_LMS_Templates::show_lms_template( 'footer' );
