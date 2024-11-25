<?php
/**
 * @var array $course
 * @var boolean $public
 * @var boolean $reviews
 */

wp_enqueue_style( 'masterstudy-course-card' );

$course = STM_LMS_Courses::get_course_submetas( $course, null );
$public = isset( $public ) ?? false;
?>

<div class="masterstudy-course-card">
	<div class="masterstudy-course-card__wrapper">
		<?php if ( ! empty( $course['featured'] ) ) { ?>
			<div class="masterstudy-course-card__featured">
				<span><?php echo esc_html__( 'Featured', 'masterstudy-lms-learning-management-system' ); ?></span>
			</div>
			<?php
		}
		if ( ! empty( $course['current_status'] ) ) {
			?>
			<div class="masterstudy-course-card__status <?php echo esc_attr( $course['current_status']['status'] ?? '' ); ?>">
				<span><?php echo esc_html( $course['current_status']['label'] ); ?></span>
			</div>
		<?php } ?>
		<a href="<?php echo esc_url( $course['url'] ); ?>" class="masterstudy-course-card__image-link">
			<img src="<?php echo esc_url( $course['image'] ); ?>" class="masterstudy-course-card__image">
		</a>
		<div class="masterstudy-course-card__info">
			<?php if ( ! empty( $course['terms'] ) ) { ?>
				<span class="masterstudy-course-card__info-category">
					<a href="<?php echo esc_url( STM_LMS_Course::courses_page_url() . '?terms[]=' . $course['terms']->term_id . '&category[]=' . $course['terms']->term_id ); ?>">
						<?php echo esc_html( $course['terms']->name ); ?>
					</a>
				</span>
			<?php } ?>
				<a href="<?php echo esc_url( $course['url'] ); ?>" class="masterstudy-course-card__info-title">
					<h3><?php echo esc_html( $course['post_title'] ); ?></h3>
				</a>
			<?php
			if ( isset( $course['progress'] ) && $course['progress'] > 0 && ! $public ) {
				STM_LMS_Templates::show_lms_template( 'components/course/card/global/progress-bar', array( 'progress' => $course['progress'] ) );
			}
			?>
			<div class="masterstudy-course-card__meta">
				<?php
				STM_LMS_Templates::show_lms_template( 'components/course/card/global/lectures', array( 'course' => $course ) );
				if ( ! empty( $course['duration_info'] ) ) {
					STM_LMS_Templates::show_lms_template( 'components/course/card/global/duration', array( 'course' => $course ) );
				}
				?>
			</div>
			<?php
			if ( $course['availability'] && is_ms_lms_addon_enabled( 'coming_soon' ) ) {
				STM_LMS_Templates::show_lms_template(
					'global/coming_soon',
					array(
						'course_id' => $course['id'],
						'mode'      => 'card',
					),
				);
			} else {
				?>
				<div class="masterstudy-course-card__bottom">
					<?php
					if ( $reviews ) {
						STM_LMS_Templates::show_lms_template( 'components/course/card/global/rating', array( 'course' => $course ) );
					}
					STM_LMS_Templates::show_lms_template( 'components/course/card/global/price', array( 'course' => $course ) );
					?>
				</div>
			<?php } ?>
		</div>
	</div>
	<?php STM_LMS_Templates::show_lms_template( 'components/course/card/global/popup', array( 'course' => $course ) ); ?>
</div>
<?php
