<?php
define( 'STM_LMS_FILE', __FILE__ );
define( 'STM_LMS_DIR', __DIR__ );
define( 'STM_LMS_PATH', dirname( STM_LMS_FILE ) );
define( 'STM_LMS_URL', plugin_dir_url( STM_LMS_FILE ) );
define( 'STM_LMS_VERSION', '3.6.15' );
define( 'STM_LMS_DB_VERSION', '3.6.8' );
define( 'STM_LMS_BASE_API_URL', '/wp-json/lms' );
define( 'STM_LMS_LIBRARY', STM_LMS_PATH . '/libraries' );
define( 'STM_LMS_LIBRARY_URL', STM_LMS_URL . 'libraries/' );
define( 'STM_LMS_ELEMENTOR_WIDGETS', STM_LMS_PATH . '/includes/elementor/widgets' );

require_once STM_LMS_PATH . '/lms/classes/vendor/autoload.php';
require_once STM_LMS_PATH . '/lms/classes/abstract/autoload.php';
require_once STM_LMS_PATH . '/lms/classes/models/autoload.php';
require_once STM_LMS_PATH . '/libraries/autoload.php';
require_once STM_LMS_PATH . '/includes/post_type/posts.php';

require_once STM_LMS_PATH . '/lms/main.php';
require_once STM_LMS_PATH . '/lms/widgets/main.php';
require_once STM_LMS_PATH . '/lms/init.php';
require_once STM_LMS_PATH . '/lms/route.php';

require_once STM_LMS_PATH . '/libraries/nuxy/NUXY.php';
require_once STM_LMS_PATH . '/includes/user_manager/main.php';
require_once STM_LMS_PATH . '/includes/visual_composer/main.php';
require_once STM_LMS_PATH . '/includes/starter-theme/classes/class-loader.php';

if ( ! class_exists( 'Vc_Manager' ) ) {
	require_once STM_LMS_PATH . '/includes/shortcodes/shortcodes.php';
}

if ( did_action( 'elementor/loaded' ) ) {
	require STM_LMS_PATH . '/includes/elementor/StmLmsElementor.php';
}

require_once STM_LMS_PATH . '/settings/order/main.php';
require_once STM_LMS_PATH . '/settings/custom_fields/send_email/main.php';
require_once STM_LMS_PATH . '/settings/custom_fields/grades_table/main.php';
require_once STM_LMS_PATH . '/settings/custom_fields/course_templates/main.php';
require_once STM_LMS_PATH . '/settings/lms_wpcfto_helpers.php';
require_once STM_LMS_PATH . '/settings/page_generator/main.php';
require_once STM_LMS_PATH . '/settings/main_settings.php';

if ( is_admin() ) {
	require_once STM_LMS_PATH . '/lms/generate_styles.php';
	require_once STM_LMS_PATH . '/lms/admin_helpers.php';
	require_once STM_LMS_PATH . '/libraries/db/fix_rating.php';
	require_once STM_LMS_PATH . '/includes/wizard/main.php';

	/*Settings Config*/
	require_once STM_LMS_PATH . '/settings/lms_metaboxes.php';
	require_once STM_LMS_PATH . '/settings/course_taxonomy.php';
	require_once STM_LMS_PATH . '/settings/stm_lms_shortcodes/main.php';
	require_once STM_LMS_PATH . '/settings/stm_lms_certificate_banner/main.php';
	require_once STM_LMS_PATH . '/settings/demo_import/main.php';
	require_once STM_LMS_PATH . '/settings/order/main.php';
	require_once STM_LMS_PATH . '/settings/payments/main.php';
	require_once STM_LMS_PATH . '/settings/payout/main.php';
}
