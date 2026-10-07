<?php

namespace MasterStudy\Lms\MigrationTool;

defined( 'ABSPATH' ) || exit;

/**
 * "LMS Migration" tab of the MasterStudy settings page (NUXY).
 *
 * The tab holds a single read-only custom field whose Vue component mounts the
 * migration app. The app talks to the migration REST routes only — it never takes
 * part in "Save Settings" and nothing is stored in the `stm_lms_settings` option.
 */
class SettingsPage {
	public const SECTION     = 'masterstudy_lms_migration';
	public const FIELD_TYPE  = 'masterstudy_lms_migration';
	public const MENU_SLUG   = 'stm-lms-settings';
	public const HOOK_SUFFIX = 'toplevel_page_stm-lms-settings';

	public function register(): void {
		add_filter( 'wpcfto_options_page_setup', array( $this, 'add_section' ), 20 );
		add_filter( 'wpcfto_field_' . self::FIELD_TYPE, array( $this, 'field_template' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public static function url(): string {
		return admin_url( 'admin.php?page=' . self::MENU_SLUG . '#' . self::SECTION );
	}

	/**
	 * Append the tab after every other MasterStudy section (NUXY always renders Import/Export last).
	 */
	public function add_section( $setups ) {
		if ( ! current_user_can( 'manage_options' ) || ! is_array( $setups ) ) {
			return $setups;
		}

		foreach ( $setups as &$setup ) {
			if ( 'stm_lms_settings' !== ( $setup['option_name'] ?? '' ) ) {
				continue;
			}

			$setup['fields'][ self::SECTION ] = array(
				'name'   => esc_html__( 'LMS Migration', 'masterstudy-lms-learning-management-system' ),
				'icon'   => 'fa fa-right-left',
				'fields' => array(
					'masterstudy_lms_migration_app' => array(
						'type'     => self::FIELD_TYPE,
						'readonly' => true,
					),
				),
			);
		}

		return $setups;
	}

	public function field_template(): string {
		return __DIR__ . '/views/settings-field.php';
	}

	public function enqueue_assets( $hook_suffix ): void {
		if ( self::HOOK_SUFFIX !== $hook_suffix || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$style  = '_core/assets/css/parts/admin/migration-tool.css';
		$script = '_core/assets/js/admin/migration-tool.js';

		wp_enqueue_style( 'masterstudy-lms-migration', MS_LMS_URL . $style, array(), self::asset_version( $style ) );
		wp_enqueue_script( 'masterstudy-lms-migration', MS_LMS_URL . $script, array( 'vue.js', 'wpcfto_metaboxes.js' ), self::asset_version( $script ), true );

		wp_localize_script(
			'masterstudy-lms-migration',
			'masterstudyMigration',
			array(
				'restUrl'        => untrailingslashit( rest_url( 'masterstudy-lms/v2' ) ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'coursesUrl'     => admin_url( 'admin.php?page=manage_courses' ),
				'pluginsUrl'     => admin_url( 'plugins.php' ),
				'guideUrl'       => (string) apply_filters( 'masterstudy_lms_migration_tool_guide_url', 'https://docs.stylemixthemes.com/masterstudy-lms/lms-settings/lms-migration' ),
				'confirmKeyword' => 'confirm',
				'undoKeyword'    => 'delete',
				'pollInterval'   => 2000,
				'steps'          => self::step_labels(),
				'groups'         => self::group_labels(),
				'contentLabels'  => self::content_labels(),
				'reportGroups'   => self::report_groups(),
				'strings'        => self::strings(),
			)
		);
	}

	public static function asset_version( string $relative_path ): string {
		$path = MS_LMS_PATH . '/' . $relative_path;

		return file_exists( $path ) ? (string) filemtime( $path ) : MS_LMS_VERSION;
	}

	/**
	 * "Failed … import" labels of report groups, in display order.
	 */
	public static function report_groups(): array {
		return array(
			Report::GROUP_COURSES       => __( 'Failed course import', 'masterstudy-lms-learning-management-system' ),
			Report::GROUP_LESSONS       => __( 'Failed lesson import', 'masterstudy-lms-learning-management-system' ),
			Report::GROUP_QUIZZES       => __( 'Failed quiz import', 'masterstudy-lms-learning-management-system' ),
			Report::GROUP_QUESTIONS     => __( 'Failed question import', 'masterstudy-lms-learning-management-system' ),
			Report::GROUP_ASSIGNMENTS   => __( 'Failed assignment import', 'masterstudy-lms-learning-management-system' ),
			Report::GROUP_MEETINGS      => __( 'Failed live meeting import', 'masterstudy-lms-learning-management-system' ),
			Report::GROUP_USERS         => __( 'Failed user import', 'masterstudy-lms-learning-management-system' ),
			Report::GROUP_ENROLLMENTS   => __( 'Failed enrollment import', 'masterstudy-lms-learning-management-system' ),
			Report::GROUP_PROGRESS      => __( 'Failed progress import', 'masterstudy-lms-learning-management-system' ),
			Report::GROUP_QUIZ_ATTEMPTS => __( 'Failed quiz attempt import', 'masterstudy-lms-learning-management-system' ),
			Report::GROUP_ORDERS        => __( 'Failed order import', 'masterstudy-lms-learning-management-system' ),
			Report::GROUP_REVIEWS       => __( 'Failed review import', 'masterstudy-lms-learning-management-system' ),
			Report::GROUP_DISCUSSIONS   => __( 'Failed Q&A import', 'masterstudy-lms-learning-management-system' ),
			Report::GROUP_OTHER         => __( 'Other items not imported', 'masterstudy-lms-learning-management-system' ),
		);
	}

	/**
	 * "Choose what to migrate" groups (Helper::STEP_GROUPS), in display order.
	 */
	public static function group_labels(): array {
		return array(
			Helper::REQUIRED_GROUP => __( 'Courses & content', 'masterstudy-lms-learning-management-system' ),
			'users'                => __( 'Students & instructors', 'masterstudy-lms-learning-management-system' ),
			'progress'             => __( 'Enrollments & progress', 'masterstudy-lms-learning-management-system' ),
			'orders'               => __( 'Orders & coupons', 'masterstudy-lms-learning-management-system' ),
			'reviews'              => __( 'Reviews', 'masterstudy-lms-learning-management-system' ),
			'discussions'          => __( 'Discussions (Q&A)', 'masterstudy-lms-learning-management-system' ),
			'wishlists'            => __( 'Wishlists', 'masterstudy-lms-learning-management-system' ),
		);
	}

	/**
	 * Labels of the preview content counts.
	 */
	public static function content_labels(): array {
		return array(
			'courses'     => __( 'Courses', 'masterstudy-lms-learning-management-system' ),
			'lessons'     => __( 'Lessons', 'masterstudy-lms-learning-management-system' ),
			'quizzes'     => __( 'Quizzes', 'masterstudy-lms-learning-management-system' ),
			'questions'   => __( 'Questions', 'masterstudy-lms-learning-management-system' ),
			'assignments' => __( 'Assignments', 'masterstudy-lms-learning-management-system' ),
		);
	}

	/**
	 * Human labels for step names (also used as the feature chips on source cards).
	 * Plain text: the app escapes everything it renders.
	 */
	public static function step_labels(): array {
		return array(
			'users'               => __( 'Users', 'masterstudy-lms-learning-management-system' ),
			'courses'             => __( 'Courses', 'masterstudy-lms-learning-management-system' ),
			'enrollments'         => __( 'Enrollments', 'masterstudy-lms-learning-management-system' ),
			'orders'              => __( 'Orders', 'masterstudy-lms-learning-management-system' ),
			'reviews'             => __( 'Reviews', 'masterstudy-lms-learning-management-system' ),
			'wdm_reviews'         => __( 'Reviews', 'masterstudy-lms-learning-management-system' ),
			'announcement'        => __( 'Announcements', 'masterstudy-lms-learning-management-system' ),
			'questions_n_answers' => __( 'Questions & Answers', 'masterstudy-lms-learning-management-system' ),
			'progress'            => __( 'Course Progress', 'masterstudy-lms-learning-management-system' ),
			'lesson_progress'     => __( 'Course Progress', 'masterstudy-lms-learning-management-system' ),
			'quiz_attempts'       => __( 'Quiz Attempts', 'masterstudy-lms-learning-management-system' ),
			'quiz_results'        => __( 'Quiz Attempts', 'masterstudy-lms-learning-management-system' ),
			'assignments'         => __( 'Assignments', 'masterstudy-lms-learning-management-system' ),
			'google_meet'         => __( 'Google Meet', 'masterstudy-lms-learning-management-system' ),
			'wishlists'           => __( 'Wishlists', 'masterstudy-lms-learning-management-system' ),
			'lesson_notes'        => __( 'Lesson Notes', 'masterstudy-lms-learning-management-system' ),
			'coupons'             => __( 'Coupons', 'masterstudy-lms-learning-management-system' ),
		);
	}

	private static function strings(): array {
		return array(
			'title'           => __( 'LMS Migration', 'masterstudy-lms-learning-management-system' ),
			'subtitle'        => __( 'Copy courses, students and progress from another LMS plugin into MasterStudy LMS.', 'masterstudy-lms-learning-management-system' ),
			'guide'           => __( 'Migration guide', 'masterstudy-lms-learning-management-system' ),
			'selectTitle'     => __( 'Select source LMS', 'masterstudy-lms-learning-management-system' ),
			'selectText'      => __( 'Choose the plugin to migrate from. Only installed and active plugins can be selected.', 'masterstudy-lms-learning-management-system' ),
			'active'          => __( 'Active', 'masterstudy-lms-learning-management-system' ),
			'notActive'       => __( 'Not active', 'masterstudy-lms-learning-management-system' ),
			'noSources'       => __( 'No supported LMS plugin was found.', 'masterstudy-lms-learning-management-system' ),
			'noActiveTitle'   => __( 'No LMS plugin to migrate from is active', 'masterstudy-lms-learning-management-system' ),
			/* translators: %s: list of supported LMS plugin names */
			'noActiveText'    => __( 'Install or activate at least one of these plugins with its courses to start a migration: %s.', 'masterstudy-lms-learning-management-system' ),
			'goToPlugins'     => __( 'Go to Plugins', 'masterstudy-lms-learning-management-system' ),
			'loadError'       => __( 'Could not load the available LMS plugins.', 'masterstudy-lms-learning-management-system' ),
			'beforeTitle'     => __( 'Before you start', 'masterstudy-lms-learning-management-system' ),
			'backupTitle'     => __( 'Your source data stays untouched', 'masterstudy-lms-learning-management-system' ),
			'backupText'      => __( 'MasterStudy LMS creates copies of the courses, students’ progress and orders. The source LMS keeps all its data. A database backup is still recommended.', 'masterstudy-lms-learning-management-system' ),
			'keepTitle'       => __( 'Keep the source plugin active', 'masterstudy-lms-learning-management-system' ),
			'keepText'        => __( 'Don’t deactivate or update the source LMS until the migration has finished.', 'masterstudy-lms-learning-management-system' ),
			'start'           => __( 'Start migration', 'masterstudy-lms-learning-management-system' ),
			'selectHint'      => __( 'Select a source LMS to continue.', 'masterstudy-lms-learning-management-system' ),
			'confirmTitle'    => __( 'Confirm migration', 'masterstudy-lms-learning-management-system' ),
			'important'       => __( 'Important:', 'masterstudy-lms-learning-management-system' ),
			/* translators: %s: source LMS name */
			'confirmText'     => __( 'This will copy all courses, enrollments and student progress from %s into MasterStudy LMS. The source data is not changed, and running the migration again skips everything that was already copied.', 'masterstudy-lms-learning-management-system' ),
			/* translators: %s: confirmation keyword */
			'typeConfirm'     => __( 'Type %s to start the migration.', 'masterstudy-lms-learning-management-system' ),
			'cancel'          => __( 'Cancel', 'masterstudy-lms-learning-management-system' ),
			'confirmStart'    => __( 'Confirm & start', 'masterstudy-lms-learning-management-system' ),
			'starting'        => __( 'Starting…', 'masterstudy-lms-learning-management-system' ),
			/* translators: %s: source LMS name */
			'migratingFrom'   => __( 'Migrating from %s', 'masterstudy-lms-learning-management-system' ),
			'migratingSub'    => __( 'Migrating your data. This may take a few minutes.', 'masterstudy-lms-learning-management-system' ),
			/* translators: %s: source LMS name */
			'completeTitle'   => __( '%s migration complete', 'masterstudy-lms-learning-management-system' ),
			'completeSub'     => __( 'All data migrated successfully.', 'masterstudy-lms-learning-management-system' ),
			/* translators: %d: number of failed items */
			'completeFailed'  => __( 'Migration finished. %d item(s) could not be migrated — see the migration log.', 'masterstudy-lms-learning-management-system' ),
			'emptySub'        => __( 'No data was found to migrate. The source LMS may be empty.', 'masterstudy-lms-learning-management-system' ),
			'cancelledTitle'  => __( 'Migration cancelled', 'masterstudy-lms-learning-management-system' ),
			'cancelledSub'    => __( 'Items copied so far remain in MasterStudy LMS. Run the migration again to copy the rest.', 'masterstudy-lms-learning-management-system' ),
			'failedTitle'     => __( 'Migration failed', 'masterstudy-lms-learning-management-system' ),
			'failedSub'       => __( 'The migration stopped unexpectedly. Check the migration log for details.', 'masterstudy-lms-learning-management-system' ),
			'elapsed'         => __( 'Elapsed', 'masterstudy-lms-learning-management-system' ),
			'duration'        => __( 'Duration', 'masterstudy-lms-learning-management-system' ),
			/* translators: 1: processed items, 2: total items */
			'itemsOf'         => __( '%1$s of %2$s items', 'masterstudy-lms-learning-management-system' ),
			'overall'         => __( 'Overall progress', 'masterstudy-lms-learning-management-system' ),
			'failed'          => __( 'failed', 'masterstudy-lms-learning-management-system' ),
			'backgroundHint'  => __( 'You can leave this page. The migration keeps running in the background, and this view picks up where it left off when you come back.', 'masterstudy-lms-learning-management-system' ),
			'cancelMigration' => __( 'Cancel migration', 'masterstudy-lms-learning-management-system' ),
			'cancelling'      => __( 'Cancelling…', 'masterstudy-lms-learning-management-system' ),
			'cancelConfirm'   => __( 'Stop this migration? Items already copied remain in MasterStudy LMS; running it again continues with the rest.', 'masterstudy-lms-learning-management-system' ),
			'viewLog'         => __( 'View migration log', 'masterstudy-lms-learning-management-system' ),
			'hideLog'         => __( 'Hide migration log', 'masterstudy-lms-learning-management-system' ),
			'viewReport'      => __( 'View migration report', 'masterstudy-lms-learning-management-system' ),
			'emptyLog'        => __( 'No warnings or errors were logged.', 'masterstudy-lms-learning-management-system' ),
			'migrateAnother'  => __( 'Migrate another LMS', 'masterstudy-lms-learning-management-system' ),
			'runAgain'        => __( 'Run again', 'masterstudy-lms-learning-management-system' ),
			'viewCourses'     => __( 'View courses', 'masterstudy-lms-learning-management-system' ),
			/* translators: %s: source LMS name */
			'doneBanner'      => __( 'All data from %s has been migrated into MasterStudy LMS.', 'masterstudy-lms-learning-management-system' ),
			'startError'      => __( 'Failed to start migration.', 'masterstudy-lms-learning-management-system' ),
			'cancelError'     => __( 'Failed to cancel migration.', 'masterstudy-lms-learning-management-system' ),
			'statusError'     => __( 'Could not refresh migration progress. Retrying…', 'masterstudy-lms-learning-management-system' ),
			'dismiss'         => __( 'Dismiss', 'masterstudy-lms-learning-management-system' ),
			/* translators: %s: source LMS name */
			'unsupportedTitle' => __( '%s data MasterStudy LMS cannot import', 'masterstudy-lms-learning-management-system' ),
			'unsupportedText' => __( 'These features have no equivalent in MasterStudy LMS. Affected items are skipped or partly imported, and each one is listed in the import report when the migration finishes.', 'masterstudy-lms-learning-management-system' ),
			'issuesTitle'     => __( 'Import issues', 'masterstudy-lms-learning-management-system' ),
			'issuesText'      => __( 'These items were not imported, or were only partly imported. Select a group to see each item, where it came from and why.', 'masterstudy-lms-learning-management-system' ),
			'issuesSelect'    => __( 'Select a group on the left to see its items.', 'masterstudy-lms-learning-management-system' ),
			'issuesLoading'   => __( 'Loading items…', 'masterstudy-lms-learning-management-system' ),
			'issuesError'     => __( 'Could not load the items.', 'masterstudy-lms-learning-management-system' ),
			'issuesEmpty'     => __( 'No items in this group.', 'masterstudy-lms-learning-management-system' ),
			/* translators: %d: number of items not shown */
			'issuesMore'      => __( '%d more item(s) are not listed.', 'masterstudy-lms-learning-management-system' ),
			'statusFailed'    => __( 'Error', 'masterstudy-lms-learning-management-system' ),
			'statusUnsupported' => __( 'Not supported', 'masterstudy-lms-learning-management-system' ),
			'statusPartial'   => __( 'Partly imported', 'masterstudy-lms-learning-management-system' ),
			/* translators: %d: count */
			'countUnsupported' => __( '%d not supported', 'masterstudy-lms-learning-management-system' ),
			/* translators: %d: count */
			'countFailed'     => __( '%d error(s)', 'masterstudy-lms-learning-management-system' ),
			/* translators: %d: count */
			'countPartial'    => __( '%d partly imported', 'masterstudy-lms-learning-management-system' ),
			'sourceId'        => __( 'Source ID', 'masterstudy-lms-learning-management-system' ),
			'course'          => __( 'Course', 'masterstudy-lms-learning-management-system' ),
			'open'            => __( 'Open', 'masterstudy-lms-learning-management-system' ),
			'close'           => __( 'Close', 'masterstudy-lms-learning-management-system' ),
			'previewTitle'    => __( 'What will be migrated', 'masterstudy-lms-learning-management-system' ),
			'previewText'     => __( 'Counted in the source LMS. Courses and their content are always migrated; choose what else to copy.', 'masterstudy-lms-learning-management-system' ),
			'previewLoading'  => __( 'Counting items…', 'masterstudy-lms-learning-management-system' ),
			'previewError'    => __( 'Could not count the items to migrate.', 'masterstudy-lms-learning-management-system' ),
			'required'        => __( 'Always', 'masterstudy-lms-learning-management-system' ),
			/* translators: %s: number of items */
			'itemsCount'      => __( '%s items', 'masterstudy-lms-learning-management-system' ),
			'noItems'         => __( 'Nothing found', 'masterstudy-lms-learning-management-system' ),
			'noGroupsWarning' => __( 'Enrollments and progress will not be copied: students would have to enroll again in MasterStudy LMS.', 'masterstudy-lms-learning-management-system' ),
			'alreadyTitle'    => __( 'Already migrated', 'masterstudy-lms-learning-management-system' ),
			/* translators: 1: number of courses, 2: number of other items, 3: source LMS name */
			/* translators: 1: LMS name, 2: number of copied items */
			'inactiveCopies'  => __( '%1$s is not active, but %2$s items copied from it are still in MasterStudy LMS.', 'masterstudy-lms-learning-management-system' ),
			'alreadyText'     => __( '%1$s courses and %2$s other items were already copied from %3$s. Running the migration again only adds what is new.', 'masterstudy-lms-learning-management-system' ),
			'undoButton'      => __( 'Delete migrated data', 'masterstudy-lms-learning-management-system' ),
			'undoTitle'       => __( 'Delete migrated data', 'masterstudy-lms-learning-management-system' ),
			/* translators: %1$s: source LMS name */
			'undoText'        => __( 'This deletes everything that was copied from %1$s into MasterStudy LMS: the courses with their lessons, quizzes and questions, and the enrollments, progress, orders and reviews that belong to them. Anything added to these courses in MasterStudy LMS after the migration is deleted too. The %1$s data itself is not changed, so you can migrate again at any time.', 'masterstudy-lms-learning-management-system' ),
			/* translators: 1: number of courses, 2: number of items, 3: number of enrollments */
			'undoSummary'     => __( '%1$s courses · %2$s items in total · %3$s enrollments', 'masterstudy-lms-learning-management-system' ),
			'undoLoading'     => __( 'Counting migrated data…', 'masterstudy-lms-learning-management-system' ),
			'undoNothing'     => __( 'There is no migrated data to delete.', 'masterstudy-lms-learning-management-system' ),
			/* translators: %s: confirmation keyword */
			'undoType'        => __( 'Type %s to delete the migrated data.', 'masterstudy-lms-learning-management-system' ),
			'undoStart'       => __( 'Delete data', 'masterstudy-lms-learning-management-system' ),
			/* translators: 1: deleted items, 2: total items */
			'undoProgress'    => __( 'Deleting… %1$s of %2$s items', 'masterstudy-lms-learning-management-system' ),
			/* translators: %s: source LMS name */
			'undoDone'        => __( 'The data migrated from %s was deleted from MasterStudy LMS.', 'masterstudy-lms-learning-management-system' ),
			'undoError'       => __( 'Could not delete the migrated data.', 'masterstudy-lms-learning-management-system' ),
		);
	}
}
