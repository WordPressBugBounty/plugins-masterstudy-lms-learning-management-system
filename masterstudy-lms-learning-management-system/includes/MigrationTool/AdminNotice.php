<?php

namespace MasterStudy\Lms\MigrationTool;

defined( 'ABSPATH' ) || exit;

/**
 * "<LMS> detected on this site" admin notice linking to the LMS Migration settings tab.
 *
 * Built with the MasterStudy admin notices library (same look as the other MasterStudy notices) and shown to
 * administrators on the Dashboard, Plugins and MasterStudy settings screens while a supported source LMS is active
 * and has not been migrated yet. "No Thanks" hides it for good (library transient).
 */
class AdminNotice {
	/**
	 * Library discard key: "No Thanks" stores the transient stm_{key}_notice_setting.
	 */
	public const DISCARD_KEY = 'masterstudy_lms_migration';

	private const SCREENS = array( 'dashboard', 'plugins', SettingsPage::HOOK_SUFFIX );

	public function register(): void {
		// The screen is known here and the notice is still registered before admin_notices runs.
		add_action( 'current_screen', array( $this, 'init_notice' ) );
	}

	/**
	 * Source LMS migrators the notice should mention.
	 *
	 * @return Contracts\MigratorInterface[]
	 */
	public static function sources(): array {
		if ( ! current_user_can( 'manage_options' ) || get_transient( 'stm_' . self::DISCARD_KEY . '_notice_setting' ) ) {
			return array();
		}

		if ( MigrationSession::get_active() ) {
			return array();
		}

		$last     = MigrationSession::get_last_completed();
		$migrated = $last ? (string) ( $last['session']['lms_slug'] ?? '' ) : '';

		return array_values(
			array_filter(
				Helper::detect_source_lms(),
				function ( $migrator ) use ( $migrated ) {
					return $migrator->get_slug() !== $migrated;
				}
			)
		);
	}

	/**
	 * @param \WP_Screen $screen Current admin screen.
	 */
	public function init_notice( $screen ): void {
		if ( ! $screen || ! in_array( $screen->id, self::SCREENS, true ) || ! function_exists( 'stm_admin_notices_init' ) ) {
			return;
		}

		$sources = self::sources();

		if ( empty( $sources ) ) {
			return;
		}

		$labels = array_map(
			function ( $migrator ) {
				return $migrator->get_label();
			},
			$sources
		);

		$names = 1 === count( $labels )
			? $labels[0]
			/* translators: 1: comma separated LMS names, 2: last LMS name */
			: sprintf( __( '%1$s and %2$s', 'masterstudy-lms-learning-management-system' ), implode( ', ', array_slice( $labels, 0, -1 ) ), end( $labels ) );

		stm_admin_notices_init(
			array(
				'notice_type'            => 'cb-info',
				'notice_logo'            => 'ms.svg',
				/* translators: %s: LMS plugin name(s) */
				'notice_title'           => esc_html( sprintf( __( '%s detected on this site', 'masterstudy-lms-learning-management-system' ), $names ) ),
				'notice_desc'            => esc_html__( 'Copy your courses, students, enrollments and progress into MasterStudy LMS in a few clicks with the LMS Migration tool. Your current LMS data is not changed.', 'masterstudy-lms-learning-management-system' ),
				// Buttons two and three: the library opens button one in a new tab.
				'notice_btn_two_title'   => esc_html__( 'Start migration', 'masterstudy-lms-learning-management-system' ),
				'notice_btn_two'         => SettingsPage::url(),
				'notice_btn_three_title' => esc_html__( 'No Thanks', 'masterstudy-lms-learning-management-system' ),
				'notice_btn_three_class' => 'no-bg',
				'notice_btn_three'       => '#',
				'notice_btn_three_attrs' => 'data-type=discard data-key=' . self::DISCARD_KEY,
			)
		);
	}
}
