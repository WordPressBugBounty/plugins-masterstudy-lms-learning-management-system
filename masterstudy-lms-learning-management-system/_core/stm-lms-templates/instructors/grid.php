<?php

stm_lms_register_style( 'user' );
stm_lms_register_style( 'instructors_grid' );

$user_args = array(
	'role'   => STM_LMS_Instructor::role(),
	'number' => -1,
);

$user_query        = new WP_User_Query( $user_args );
$instructor_public = STM_LMS_Options::get_option( 'instructor_public_profile', true );

if ( ! empty( $user_query->get_results() ) ) : ?>
	<div class="stm_lms_instructors__grid">
		<?php
		foreach ( $user_query->get_results() as $user ) :
			$user_profile_url = STM_LMS_User::instructor_public_page_url( $user->ID );
			$user             = STM_LMS_User::get_current_user( $user->ID, false, true );
			$reviews          = STM_LMS_Options::get_option( 'course_tab_reviews', true );
			$rating           = STM_LMS_Instructor::my_rating_v2( $user );
			?>
			<a
				<?php if ( $instructor_public ) { ?>
					href="<?php echo esc_url( $user_profile_url ); ?>"
				<?php } ?>
				class="stm_lms_instructors__single"
			>
				<div class="stm_lms_user_side">

					<?php if ( ! empty( $user['avatar'] ) ) : ?>
						<div class="stm-lms-user_avatar">
							<?php echo wp_kses_post( $user['avatar'] ); ?>
						</div>
					<?php endif; ?>

					<h3><?php echo esc_attr( $user['login'] ); ?></h3>

					<?php if ( ! empty( $user['meta']['position'] ) ) : ?>
						<h5><?php echo esc_html( sanitize_text_field( $user['meta']['position'] ) ); ?></h5>
					<?php endif; ?>

					<?php if ( ! empty( $rating['total'] ) && $reviews ) : ?>
						<div class="stm-lms-user_rating ">
							<div class="star-rating star-rating__big">
								<span style="width: <?php echo floatval( $rating['percent'] ); ?>%;"></span>
							</div>
							<strong class="rating heading_font"><?php echo floatval( $rating['average'] ); ?></strong>
							<div class="stm-lms-user_rating__total">
								<?php echo wp_kses_post( sanitize_text_field( $rating['total_marks'] ) ); ?>
							</div>
						</div>
					<?php endif; ?>

				</div>
			</a>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
