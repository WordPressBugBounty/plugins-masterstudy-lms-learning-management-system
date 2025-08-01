<?php
/**
 * Plugin Name: MasterStudy LMS WordPress Plugin – for Online Courses and Education
 * Plugin URI: http://masterstudy.stylemixthemes.com/lms-plugin/
 * Description: Create brilliant lessons with videos, graphs, images, slides and any other attachments thanks to flexible and user-friendly lesson management tool powered by WYSIWYG editor.
 * As the ultimate LMS WordPress Plugin, MasterStudy makes it simple and hassle-free to build, customize and manage your Online Education WordPress website.
 * Author: StylemixThemes
 * Author URI: https://stylemixthemes.com/
 * Text Domain: masterstudy-lms-learning-management-system
 * Version: 3.6.15
 * Masterstudy LMS Pro tested up to: 4.7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

define( 'MS_LMS_VERSION', '3.6.15' );
define( 'MS_LMS_FILE', __FILE__ );
define( 'MS_LMS_PATH', dirname( MS_LMS_FILE ) );
define( 'MS_LMS_URL', plugin_dir_url( MS_LMS_FILE ) );

require_once MS_LMS_PATH . '/vendor/autoload.php';
require_once MS_LMS_PATH . '/includes/init.php';
/* Load Core version */
require_once MS_LMS_PATH . '/_core/init.php';
