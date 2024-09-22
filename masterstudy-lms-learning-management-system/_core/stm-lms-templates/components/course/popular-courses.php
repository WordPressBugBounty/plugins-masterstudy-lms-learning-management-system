<?php
/**
 * @var object $course
 */

use MasterStudy\Lms\Repositories\CourseRepository;

$query_args = array(
	'posts_per_page' => 4,
	'exclude'        => array( $course->id ),
	'post_status'    => 'publish',
	'post_type'      => 'stm-courses',
	'meta_key'       => 'current_students',
	'orderby'        => 'meta_value',
	'order'          => 'DESC',
	'fields'         => 'ids',
);

$course_ids = get_posts( $query_args );
$stars      = range( 1, 5 );
?>

<?php if ( ! empty( $course_ids ) ) { ?>
	<div class="masterstudy-popular-courses">
		<span class="masterstudy-popular-courses__title">
			<?php echo esc_html__( 'Popular courses', 'masterstudy-lms-learning-management-system' ); ?>
		</span>
		<ul class="masterstudy-popular-courses__list">
			<?php
			foreach ( $course_ids as $course_id ) {
				$popular_course = ( new CourseRepository() )->find( $course_id, 'grid' );
				$course_url     = STM_LMS_Course::courses_page_url() . $popular_course->slug;
				$is_sale_active = STM_LMS_Helpers::is_sale_price_active( $course_id );
				$course_status  = STM_LMS_Course::get_post_status( $course_id );
				$sale_price     = ! empty( $popular_course->sale_price ) && $is_sale_active ? true : false;

				if ( $popular_course->is_udemy_course ) {
					$author_name = $popular_course->udemy_instructor['display_name'];
				} else {
					$author      = STM_LMS_User::get_current_user( $popular_course->owner->ID );
					$author_name = $author['login'];
				}
				?>
				<li class="masterstudy-popular-courses__item">
					<div class="masterstudy-popular-courses__link">
						<a href="<?php echo esc_url( $course_url ); ?>" target="_blank" class="masterstudy-popular-courses__image-wrapper">
							<?php if ( ! empty( $course_status ) ) { ?>
								<span class="masterstudy-popular-courses__item-status <?php echo esc_attr( 'masterstudy-popular-courses__item-status_' . $course_status['status'] ); ?>">
									<?php echo esc_html( $course_status['label'] ); ?>
								</span>
							<?php } ?>
							<img src="<?php echo esc_url( ! empty( $popular_course->thumbnail['url'] ) ? $popular_course->thumbnail['url'] : '#' ); ?>" alt="<?php echo esc_html( ! empty( $popular_course->thumbnail['title'] ) ? $popular_course->thumbnail['title'] : '' ); ?>" class="masterstudy-popular-courses__image">
						</a>
						<div class="masterstudy-popular-courses__item-meta">
							<a href="<?php echo esc_url( $course_url ); ?>" target="_blank" class="masterstudy-popular-courses__item-title">
								<?php echo esc_html( stm_lms_minimize_word( $popular_course->title, 40 ) ); ?>
							</a>
							<div class="masterstudy-popular-courses__item-block">
								<?php if ( $popular_course->not_single_sale && STM_LMS_Subscriptions::subscription_enabled() ) { ?>
									<div class="masterstudy-popular-courses__subscription">
										<img class="masterstudy-popular-courses__subscription-image" src="<?php echo esc_url( STM_LMS_URL . '/assets/img/members_only.svg' ); ?>" alt="<?php esc_attr_e( 'Members only', 'masterstudy-lms-learning-management-system' ); ?>"/>
										<div class="masterstudy-popular-courses__subscription-title">
											<?php esc_html_e( 'Members only', 'masterstudy-lms-learning-management-system' ); ?>
										</div>
									</div>
								<?php } else { ?>
									<div class="masterstudy-popular-courses__price <?php echo $sale_price ? 'masterstudy-popular-courses__price_sale' : ''; ?>">
										<?php
										if ( $popular_course->not_single_sale && ! $popular_course->is_udemy_course ) {
											echo esc_html__( 'Free', 'masterstudy-lms-learning-management-system' );
										} else {
											if ( $popular_course->is_udemy_course ) {
												echo esc_html( STM_LMS_Helpers::display_price( $popular_course->price ) );
											} else {
												echo esc_html( ( ! empty( $popular_course->price ) && 0 !== floatval( $popular_course->price ) ) ? STM_LMS_Helpers::display_price( $popular_course->price ) : __( 'Free', 'masterstudy-lms-learning-management-system' ) );
											}
										}
										?>
									</div>
									<?php
									if ( $sale_price ) {
										?>
										<div class="masterstudy-popular-courses__price-sale">
											<?php echo esc_html( STM_LMS_Helpers::display_price( $popular_course->sale_price ) ); ?>
										</div>
										<?php
									}
								}
								if ( ! empty( $popular_course->rate ) && ! $popular_course->is_udemy_course ) {
									?>
									<div class="masterstudy-popular-courses__rating">
										<?php foreach ( $stars as $star ) { ?>
											<span class="masterstudy-popular-courses__rating-star <?php echo esc_attr( ( $star <= floor( $popular_course->rate['average'] ) ) ? 'masterstudy-popular-courses__rating-star_filled' : '' ); ?>"></span>
										<?php } ?>
									</div>
								<?php } elseif ( ! empty( $popular_course->rate ) && $popular_course->is_udemy_course ) { ?>
									<div class="masterstudy-popular-courses__rating">
										<?php foreach ( $stars as $star ) { ?>
											<span class="masterstudy-popular-courses__rating-star <?php echo esc_attr( ( $star <= floor( $popular_course->udemy_rate ) ) ? 'masterstudy-popular-courses__rating-star_filled' : '' ); ?>"></span>
										<?php } ?>
									</div>
								<?php } ?>
							</div>
							<a href="<?php echo esc_url( $popular_course->is_udemy_course ? $course_url : STM_LMS_User::user_public_page_url( $popular_course->owner->ID ) ); ?>" target="_blank" class="masterstudy-popular-courses__instructor">
								<?php
								printf(
									/* translators: %s Instructor */
									esc_html__( 'By %s', 'masterstudy-lms-learning-management-system' ),
									esc_html( $author_name )
								);
								?>
							</a>
						</div>
					</div>
				</li>
			<?php } ?>
		</ul>
	</div>
	<?php
}
