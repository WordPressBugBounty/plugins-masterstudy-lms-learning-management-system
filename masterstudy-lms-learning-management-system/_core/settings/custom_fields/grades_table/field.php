<?php
/**
 * Grade table field template.
 *
 * @var $field
 *
 */

wp_enqueue_style( 'masterstudy-grades-table', STM_LMS_URL . 'assets/css/components/grades-table.css', array(), MS_LMS_VERSION );
wp_enqueue_script( 'masterstudy-grades-table', STM_LMS_URL . 'assets/js/components/grades-table.js', array( 'vue.js', 'vue2-color.js', 'wpcfto_metaboxes.js' ), MS_LMS_VERSION, true );
?>

<grades_table
	:fields="<?php echo esc_attr( $field ); ?>"
	:popup_text="'<?php echo esc_attr__( 'Are you sure you want to delete this grade?', 'masterstudy-lms-learning-management-system' ); ?>'"
	:popup_confirm_button="'<?php echo esc_attr__( 'Delete', 'masterstudy-lms-learning-management-system' ); ?>'"
	:popup_cancel_button="'<?php echo esc_attr__( 'Cancel', 'masterstudy-lms-learning-management-system' ); ?>'"
	:fields_error="'<?php echo esc_attr__( 'Field is required', 'masterstudy-lms-learning-management-system' ); ?>'"
	:fields_range_error="'<?php echo esc_attr__( 'The minimum range must be less than the previous one', 'masterstudy-lms-learning-management-system' ); ?>'"
>
</grades_table>