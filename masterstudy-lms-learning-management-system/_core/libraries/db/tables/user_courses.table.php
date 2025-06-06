<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

register_activation_hook( MS_LMS_FILE, 'stm_lms_user_courses' );

function stm_lms_user_courses() {
	global $wpdb;

	$table_name = stm_lms_user_courses_name( $wpdb );

	$charset_collate = $wpdb->get_charset_collate();

	$locale = get_locale();

	$sql = "CREATE TABLE $table_name (
		user_course_id mediumint(9) NOT NULL AUTO_INCREMENT,
		user_id bigint NOT NULL,
		course_id int(11) NOT NULL,
		current_lesson_id int(11),
		progress_percent mediumint(9) NOT NULL,
		final_grade tinyint(4) DEFAULT NULL,
		status varchar(45) NOT NULL DEFAULT '',
		lng_code varchar(45) NOT NULL DEFAULT '$locale',
		is_gradable tinyint(1) NOT NULL DEFAULT 0,
		subscription_id int(11),
		enterprise_id int(11) DEFAULT 0,
		bundle_id int(11) DEFAULT 0,
		start_time INT NOT NULL,
		end_time INT DEFAULT 0,
		for_points VARCHAR(255) DEFAULT '',
		PRIMARY KEY (user_course_id),
		INDEX ix_user_course_current (user_id, course_id, current_lesson_id),
		INDEX ix_user_course_enterprice (user_id, course_id, enterprise_id),
		INDEX ix_user_course_bundle (user_id, course_id, bundle_id),
		INDEX ix_user_course_start_time (user_id, course_id, start_time)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	dbDelta( $sql );
}
