<?php
/**
 * @var string $name
 * @var bool $on
 *
 */

wp_enqueue_style( 'masterstudy-switcher' );

$checked = $on ? 'checked' : '';
?>

<label class="masterstudy-switcher">
	<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" <?php echo esc_attr( $checked ); ?>>
	<div class="masterstudy-switcher-background">
		<div class="masterstudy-switcher-handle"></div>
	</div>
</label>
