<?php

namespace StmLmsElementor;

use StmLmsElementor\Widgets\StmCoursesSearchbox;
use StmLmsElementor\Widgets\StmLmsSingleCourseCarousel;
use StmLmsElementor\Widgets\StmLmsCoursesCarousel;
use StmLmsElementor\Widgets\StmLmsCoursesCategories;
use StmLmsElementor\Widgets\StmLmsCoursesGrid;
use StmLmsElementor\Widgets\StmLmsFeaturedTeacher;
use StmLmsElementor\Widgets\StmLmsInstructorsCarousel;
use StmLmsElementor\Widgets\StmLmsRecentCourses;
use StmLmsElementor\Widgets\StmLmsCertificateChecker;
use StmLmsElementor\Widgets\StmLmsCourseBundles;
use StmLmsElementor\Widgets\StmLmsGoogleClassroom;
use StmLmsElementor\Widgets\StmLmsMembershipLevels;
use StmLmsElementor\Widgets\StmLmsCallToAction;
use StmLmsElementor\Widgets\MsLmsCoursesSearchbox;
use StmLmsElementor\Widgets\MsLmsInstructorsCarousel;
use StmLmsElementor\Widgets\MsLmsAuthorization;
use StmLmsElementor\Widgets\MsLmsCourses;
use StmLmsElementor\Widgets\MsLmsSlider;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Plugin class
 */
final class Plugin {

	private static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		add_action( 'elementor/init', array( $this, 'init' ) );
	}

	public function init() {
		require STM_LMS_PATH . '/includes/elementor/helpers/ajax_actions.php';

		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
		add_action( 'elementor/elements/categories_registered', array( $this, 'add_elementor_widget_categories' ) );
		add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'editor_before_enqueue_scripts' ) );
		add_action( 'elementor/editor/before_enqueue_styles', array( $this, 'editor_icons' ) );
		add_action( 'elementor/preview/enqueue_styles', array( $this, 'editor_styles' ) );
		add_action( 'elementor/preview/enqueue_scripts', array( $this, 'editor_scripts' ) );
	}

	public function add_elementor_widget_categories( $elements_manager ) {
		$new_categories = array(
			'stm_lms'     => array(
				'title' => esc_html__( 'MasterStudy | New Widgets', 'masterstudy-lms-learning-management-system' ),
			),
			'stm_lms_old' => array(
				'title' => esc_html__( 'MasterStudy', 'masterstudy-lms-learning-management-system' ),
			),
		);
		$categories     = array_merge( $new_categories, $elements_manager->get_categories() );
		$set_categories = function( $categories ) {
			$this->categories = $categories;
		};
		$set_categories->call( $elements_manager, $categories );
	}

	public function editor_before_enqueue_scripts() {
		wp_enqueue_style( 'lms-elementor', STM_LMS_URL . 'assets/css/lms-elementor.css', array(), MS_LMS_VERSION, 'all' );
	}

	public function editor_scripts() {
		wp_register_script( 'stm_lms_add_overlay', STM_LMS_URL . 'assets/js/elementor-widgets/helpers/add-overlay.js', array(), MS_LMS_VERSION, true );
		wp_localize_script(
			'stm_lms_add_overlay',
			'stm_lms_add_overlay_change',
			array(
				'nonce'    => wp_create_nonce( 'add-overlay' ),
				'ajax_url' => admin_url( 'admin-ajax.php' ),
			)
		);
		/* swiper slider for widgets */
		wp_enqueue_script( 'ms_lms_swiper_slider', STM_LMS_URL . 'assets/vendors/swiper-bundle.min.js', array( 'elementor-frontend' ), MS_LMS_VERSION, true );
		/* slider widget scripts */
		wp_enqueue_script( 'ms_lms_slider_editor', STM_LMS_URL . 'assets/js/elementor-widgets/slider/slider-editor.js', array( 'elementor-frontend' ), MS_LMS_VERSION, true );
		/* courses widget scripts */
		wp_enqueue_script( 'ms_lms_courses_editor_select2', STM_LMS_URL . 'assets/vendors/select2.min.js', array( 'elementor-frontend' ), MS_LMS_VERSION, true );
		wp_enqueue_script( 'ms_lms_courses_editor', STM_LMS_URL . 'assets/js/elementor-widgets/courses/courses-editor.js', array( 'elementor-frontend' ), MS_LMS_VERSION, true );
		wp_enqueue_script( 'masterstudy_authorization_editor', STM_LMS_URL . 'assets/js/elementor-widgets/authorization.js', array( 'elementor-frontend' ), MS_LMS_VERSION, true );
		wp_localize_script(
			'ms_lms_courses_editor',
			'ms_lms_courses_archive_filter',
			array(
				'nonce'    => wp_create_nonce( 'filtering' ),
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'editor'   => true,
			)
		);
		/* course search box widget scripts */
		wp_enqueue_script( 'ms_lms_courses_searchbox_editor_autocomplete', STM_LMS_URL . '/assets/vendors/vue2-autocomplete.js', array( 'elementor-frontend' ), MS_LMS_VERSION, true );
		wp_enqueue_script( 'ms_lms_courses_searchbox_editor', STM_LMS_URL . '/assets/js/elementor-widgets/course-search-box/course-search-box-editor.js', array( 'jquery', 'elementor-frontend' ), MS_LMS_VERSION, true );
		/* instructors carousel widget scripts */
		wp_enqueue_script( 'ms_lms_instructors_carousel_editor', STM_LMS_URL . '/assets/js/elementor-widgets/instructors-carousel/instructors-carousel-editor.js', array( 'elementor-frontend' ), MS_LMS_VERSION, true );
		wp_localize_script(
			'ms_lms_instructors_carousel_editor',
			'ms_lms_instructors_carousel_mode',
			array(
				'editor' => true,
			)
		);

		if ( is_ms_lms_addon_enabled( 'coming_soon' ) ) {
			stm_lms_register_style( 'coming_soon/coming_soon' );
			wp_enqueue_style( 'masterstudy-countdown' );
			wp_enqueue_script( 'masterstudy-countdown' );
		}

		/* testimonials carousel widget scripts */
		wp_enqueue_script( 'lms-testimonials-carousel-editor', STM_LMS_URL . '/assets/js/elementor-widgets/testimonials_carousel_editor.js', array( 'elementor-frontend' ), MS_LMS_VERSION, true );
	}

	public function editor_styles() {
		wp_register_style( 'stm_lms_add_overlay', STM_LMS_URL . 'assets/css/elementor-widgets/helpers/add-overlay.css', array(), MS_LMS_VERSION, false );
		wp_enqueue_style( 'stm_lms_add_overlay' );
	}

	public function editor_icons() {
		wp_enqueue_style( 'stm_lms_icons', STM_LMS_URL . 'assets/icons/style.css', null, STM_LMS_VERSION );
	}

	private function includes() {
		require STM_LMS_PATH . '/includes/elementor/helpers/add-controls-class.php';
		require STM_LMS_PATH . '/includes/elementor/helpers/add-overlay.php';
		require STM_LMS_PATH . '/includes/elementor/widgets/stm_lms_membership_levels.php';
		require STM_LMS_PATH . '/includes/elementor/widgets/stm_lms_call_to_action.php';
		require STM_LMS_PATH . '/includes/elementor/widgets/stm_lms_profile_auth_links.php';
		require STM_LMS_PATH . '/includes/elementor/widgets/stm_lms_testimonials_carousel.php';
		require STM_LMS_PATH . '/includes/elementor/widgets/deprecated/stm_courses_searchbox.php';
		require STM_LMS_PATH . '/includes/elementor/widgets/deprecated/stm_lms_courses_carousel.php';
		require STM_LMS_PATH . '/includes/elementor/widgets/deprecated/stm_lms_courses_categories.php';
		require STM_LMS_PATH . '/includes/elementor/widgets/deprecated/stm_lms_courses_grid.php';
		require STM_LMS_PATH . '/includes/elementor/widgets/deprecated/stm_lms_featured_teacher.php';
		require STM_LMS_PATH . '/includes/elementor/widgets/deprecated/stm_lms_instructors_carousel.php';
		require STM_LMS_PATH . '/includes/elementor/widgets/deprecated/stm_lms_recent_courses.php';
		require STM_LMS_PATH . '/includes/elementor/widgets/deprecated/stm_lms_single_course_carousel.php';
		require STM_LMS_PATH . '/includes/elementor/widgets/ms_lms_courses_searchbox.php';
		require STM_LMS_PATH . '/includes/elementor/widgets/ms_lms_instructors_carousel.php';
		require STM_LMS_PATH . '/includes/elementor/widgets/ms_lms_authorization.php';
		require STM_LMS_PATH . '/includes/elementor/widgets/courses/ms_lms_courses.php';
		require STM_LMS_PATH . '/includes/elementor/widgets/slider/ms_lms_slider.php';
		// Pro widgets
		require STM_LMS_PATH . '/includes/elementor/widgets/deprecated/stm_lms_certificate_checker.php';
		require STM_LMS_PATH . '/includes/elementor/widgets/deprecated/stm_lms_course_bundles.php';
		require STM_LMS_PATH . '/includes/elementor/widgets/deprecated/stm_lms_google_classroom.php';
	}

	public function register_widgets( $widgets_manager ) {
		$this->includes();
		$widgets_manager->register( new StmCoursesSearchbox() );
		$widgets_manager->register( new StmLmsSingleCourseCarousel() );
		$widgets_manager->register( new StmLmsCoursesCarousel() );
		$widgets_manager->register( new StmLmsCoursesCategories() );
		$widgets_manager->register( new StmLmsCoursesGrid() );
		$widgets_manager->register( new StmLmsFeaturedTeacher() );
		$widgets_manager->register( new StmLmsInstructorsCarousel() );
		$widgets_manager->register( new StmLmsRecentCourses() );
		$widgets_manager->register( new \StmLmsProTestimonials() );
		$widgets_manager->register( new \StmLmsProfileAuthLinks() );
		$widgets_manager->register( new StmLmsCallToAction() );
		$widgets_manager->register( new MsLmsCoursesSearchbox() );
		$widgets_manager->register( new MsLmsInstructorsCarousel() );
		$widgets_manager->register( new MsLmsAuthorization() );
		$widgets_manager->register( new MsLmsCourses() );
		$widgets_manager->register( new MsLmsSlider() );
		if ( defined( 'STM_LMS_PRO_PATH' ) ) {
			$widgets_manager->register( new StmLmsCertificateChecker() );
		}
		if ( class_exists( 'MasterStudy\Lms\Pro\addons\CourseBundle\CourseBundle' ) ) {
			$widgets_manager->register( new StmLmsCourseBundles() );
		}
		if ( class_exists( 'STM_LMS_Google_Classroom' ) ) {
			$widgets_manager->register( new StmLmsGoogleClassroom() );
		}
		if ( defined( 'PMPRO_VERSION' ) ) {
			$widgets_manager->register( new StmLmsMembershipLevels() );
		}
	}
}

\StmLmsElementor\Plugin::instance();
