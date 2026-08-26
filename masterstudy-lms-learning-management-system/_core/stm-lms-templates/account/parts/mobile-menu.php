<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wp;

wp_enqueue_style( 'masterstudy-account-mobile-menu' );

$menus = array(
	'home'     => array(
		'title' => __( 'Home', 'masterstudy-lms-learning-management-system' ),
		'url'   => STM_LMS_User::login_page_url(),
	),
	'courses'  => array(
		'title' => __( 'Courses', 'masterstudy-lms-learning-management-system' ),
		'url'   => ms_plugin_user_account_url( 'enrolled-courses' ),
	),
	'wishlist' => array(
		'title' => __( 'Wishlist', 'masterstudy-lms-learning-management-system' ),
		'url'   => STM_LMS_User::wishlist_url(),
	),
	'menu'     => array(
		'title' => __( 'Menu', 'masterstudy-lms-learning-management-system' ),
		'url'   => '#',
	),
);

$menus = apply_filters( 'masterstudy_account_mobile_menu_items', $menus, $current_user ?? array() );

$current_url       = trailingslashit( home_url( add_query_arg( array(), $wp->request ) ) );
$mobile_menu_class = 'masterstudy-account-mobile-menu';

if ( isset( $menus['notifications'] ) ) {
	$mobile_menu_class .= ' masterstudy-account-mobile-menu_has-notifications';
}
?>

<div class="<?php echo esc_attr( $mobile_menu_class ); ?>">
	<?php foreach ( $menus as $key => $item ) : ?>
		<?php
		$url       = isset( $item['url'] ) ? (string) $item['url'] : '#';
		$title     = isset( $item['title'] ) ? (string) $item['title'] : '';
		$item_url  = trailingslashit( strtok( $url, '?#' ) );
		$is_active = ( $item_url && $item_url === $current_url );
		$icon      = isset( $item['icon'] ) ? (string) $item['icon'] : ( 'menu' === $key ? 'stmlms-mobile-menu-hamburger' : 'stmlms-mobile-menu-' . $key );
		$badge     = $item['badge_count'] ?? null;

		$badge_count = is_numeric( $badge ) ? absint( $badge ) : 0;
		$badge_label = $badge_count > 99 ? '99+' : (string) $badge_count;

		$link_class = 'masterstudy-account-mobile-menu__link';
		if ( $is_active ) {
			$link_class .= ' masterstudy-account-mobile-menu__link_active';
		}
		?>
		<a href="<?php echo esc_url( $url ); ?>"
			class="<?php echo esc_attr( $link_class ); ?>"
			data-id="<?php echo esc_attr( $key ); ?>">
			<i class="<?php echo esc_attr( $icon ); ?>"></i>
			<div class="masterstudy-account-mobile-menu__item">
				<?php echo esc_html( $title ); ?>
			</div>
			<?php if ( $badge_count > 0 ) : ?>
				<span class="masterstudy-account-mobile-menu__badge">
					<?php echo esc_html( $badge_label ); ?>
				</span>
			<?php endif; ?>
		</a>
	<?php endforeach; ?>
</div>
