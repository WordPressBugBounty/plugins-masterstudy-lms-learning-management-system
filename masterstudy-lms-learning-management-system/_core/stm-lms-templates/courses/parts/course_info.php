<?php
/**
 * @var $post_id
 * @var $post_status
 * @var $rates
 * @var $average
 * @var $total
 * @var $percent
 * @var $sale_price
 * @var $price
 * @var $author_id
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$author_info = STM_LMS_User::get_current_user( $author_id );
$level       = get_post_meta( $post_id, 'level', true );
$duration    = get_post_meta( $post_id, 'duration_info', true );
$lectures    = STM_LMS_Course::curriculum_info( $post_id );
$reviews     = STM_LMS_Options::get_option( 'course_tab_reviews', true );

$rating = get_post_meta( $post_id, 'course_marks', true );

if ( ! empty( $rating ) ) {
	$rates   = STM_LMS_Course::course_average_rate( $rating );
	$average = $rates['average'];
	$percent = $rates['percent'];
	$total   = count( $rating );
}
?>

<div class="stm_lms_courses__single--info">
	<?php if ( ! empty( $author_info['login'] ) ) : ?>
		<div class="stm_lms_courses__single--info_author">
			<div class="stm_lms_courses__single--info_author__avatar">
				<?php echo wp_kses_post( $author_info['avatar'] ); ?>
			</div>
			<div class="stm_lms_courses__single--info_author__login">
				<?php echo wp_kses_post( $author_info['login'] ); ?>
			</div>
		</div>
	<?php endif; ?>

	<div class="stm_lms_courses__single--info_title">
		<a href="<?php the_permalink(); ?>">
			<h4><?php the_title(); ?></h4>
		</a>
	</div>

	<div class="stm_lms_courses__single--info_rate">

		<?php if ( ! empty( $average ) && $reviews ) : ?>
			<div class="star-rating star-rating__big">
				<span style="width: <?php echo esc_attr( $percent ); ?>%">
					<strong class="rating"><?php echo esc_html( $average ); ?></strong>
				</span>
			</div>

			<div class="average-rating-stars__av heading_font">
				<strong><?php echo esc_html( floatval( $average ) ); ?></strong>
				<?php if ( ! empty( $total ) ) : ?>
					(<?php echo esc_html( intval( $total ) ); ?>)
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $post_status ) ) : ?>
			<div class="stm_lms_courses__single--info_status <?php echo esc_attr( $post_status['status'] ); ?>">
				<?php echo esc_html( $post_status['label'] ); ?>
			</div>
		<?php endif; ?>

	</div>

	<div class="stm_lms_courses__single--info_excerpt">
		<?php echo wp_kses_post( stm_lms_minimize_word( strip_shortcodes( get_the_excerpt() ), 150, '...' ) ); ?>
	</div>

	<div class="stm_lms_courses__single--info_meta">
		<?php STM_LMS_Templates::show_lms_template( 'courses/parts/meta', compact( 'level', 'duration', 'lectures' ) ); ?>
	</div>

	<div class="stm_lms_courses__single--info_preview">
		<a href="<?php the_permalink(); ?>" title="<?php the_title(); ?>" class="heading_font">
			<?php esc_html_e( 'Preview this course', 'masterstudy-lms-learning-management-system' ); ?>
		</a>
	</div>

	<div class="stm_lms_courses__single--info_bottom">
		<?php STM_LMS_Templates::show_lms_template( 'global/wish-list', array( 'course_id' => $post_id ) ); ?>

		<?php
		$is_course_coming_soon = STM_LMS_Helpers::masterstudy_lms_is_course_coming_soon( $post_id );
		if ( ! $is_course_coming_soon ) {
			do_action( 'stm_lms_archive_card_price', compact( 'price', 'sale_price', 'post_id' ) );
		}
		?>
	</div>


</div>
