<?php
function masterstudy_get_elementor_unlock_banner( $addon = '' ) {
	$is_pro_plus   = STM_LMS_Helpers::is_pro_plus();
	$only_pro      = STM_LMS_Helpers::is_pro() && ! $is_pro_plus;
	$pro_link_text = esc_html__( 'Upgrade to PRO', 'masterstudy-lms-learning-management-system' );
	$link          = $only_pro ? 'https://stylemixthemes.com/wordpress-lms-plugin/pricing/?utm_source=wpadmin&utm_medium=elementorwidget' : admin_url( 'admin.php?page=stm-lms-go-pro&source=elementorwidget' );
	$link_text     = $only_pro ? esc_html__( 'Upgrade to PRO PLUS', 'masterstudy-lms-learning-management-system' ) : $pro_link_text;
	$icon_url      = $only_pro ? STM_LMS_URL . 'assets/img/pro-features/pro_plus.svg' : STM_LMS_URL . 'assets/img/pro-features/unlock-pro-logo.svg';

	if ( ! empty( $addon ) ) {
		$addon_name   = 'coming_soon' === $addon ? 'upcoming' : $addon;
		$addon_titles = array(
			'grades'      => __( 'Grades Addon', 'masterstudy-lms-learning-management-system' ),
			'coming_soon' => __( 'Upcoming Course Status Addon', 'masterstudy-lms-learning-management-system' ),
		);
		$link_text    = esc_html__( 'Activate', 'masterstudy-lms-learning-management-system' );
		$link         = admin_url( "admin.php?page=stm-addons&search={$addon_name}" );
	}
	?>
	<div class="masterstudy-elementor-unlock-banner">
		<?php if ( empty( $addon ) ) { ?>
			<div class="masterstudy-elementor-unlock-banner__text">
				<?php echo esc_html__( 'Get Access to', 'masterstudy-lms-learning-management-system' ); ?>
				<span class="masterstudy-elementor-unlock-banner__elements">
					<?php echo esc_html__( 'Exclusive Widgets', 'masterstudy-lms-learning-management-system' ); ?>
				</span>
				<?php echo esc_html__( 'with', 'masterstudy-lms-learning-management-system' ); ?>
				<span class="masterstudy-elementor-unlock-banner__plugin">
					<?php echo esc_html__( 'MasterStudy', 'masterstudy-lms-learning-management-system' ); ?>
					<img src="<?php echo esc_url( $icon_url ); ?>">
				</span>
			</div>
		<?php } else { ?>
			<div class="masterstudy-elementor-unlock-banner__text">
				<?php echo esc_html__( 'Activate', 'masterstudy-lms-learning-management-system' ); ?>
				<span class="masterstudy-elementor-unlock-banner__plugin">
					<?php echo esc_html( $addon_titles[ $addon ] ); ?>
				</span>
			</div>
		<?php } ?>
		<div class="masterstudy-elementor-unlock-banner__button">
			<?php
			STM_LMS_Templates::show_lms_template(
				'components/button',
				array(
					'title'  => $link_text,
					'link'   => $link,
					'style'  => 'primary',
					'size'   => 'sm',
					'id'     => 'elementor-widgets-upgrade-pro',
					'target' => '_blank',
				)
			);
			?>
		</div>
	</div>
	<?php
}
