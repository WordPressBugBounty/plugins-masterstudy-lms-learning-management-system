<?php
/**
 * Video Media type component
 *
 * @var array $lesson - $lesson array
 * @var array $id - Video Lesson ID
 * @var int $user_id
 * @var int $course_id
 * @var bool $lesson_completed
 * @var bool $mode - boolean for turn on brand player
 * @package masterstudy
 */

use MasterStudy\Lms\Enums\LessonVideoType;

if ( empty( $lesson['video_type'] ) ) {
	return;
}

$settings                  = get_option( 'stm_lms_settings' );
$plyr_vimeo_video_player   = $settings['course_player_vimeo_video_player'] ?? false;
$plyr_youtube_video_player = $settings['course_player_youtube_video_player'] ?? false;
$is_youtube_type           = LessonVideoType::YOUTUBE === $lesson['video_type'];
$is_vimeo_type             = LessonVideoType::VIMEO === $lesson['video_type'];
$user_id                   = isset( $user_id ) ? $user_id : '';
$course_id                 = isset( $course_id ) ? $course_id : '';
$lesson_completed          = isset( $lesson_completed ) ? $lesson_completed : false;
$user_progress             = ! empty( $user_id ) && ! empty( $course_id ) ? masterstudy_lms_get_user_lesson_progress( $user_id, $course_id, $id ) ?? 0 : 0;

if ( ! empty( $mode ) ) {
	$plyr_vimeo_video_player   = $mode;
	$plyr_youtube_video_player = $mode;
}

wp_enqueue_style( 'masterstudy-course-player-video-plyr' );
wp_enqueue_style( 'masterstudy-course-player-lesson-video' );
wp_enqueue_script( 'masterstudy-course-player-lesson-video' );
wp_localize_script(
	'masterstudy-course-player-lesson-video',
	'video_player_data',
	array(
		'video_type'              => $lesson['video_type'],
		'video_progress'          => ! empty( $lesson['video_required_progress'] ),
		'plyr_youtube_player'     => $plyr_youtube_video_player,
		'plyr_vimeo_video_player' => $plyr_vimeo_video_player,
	)
);
?>

<div class="masterstudy-course-player-lesson-video">
	<?php
	if ( LessonVideoType::EMBED === $lesson['video_type'] && ! empty( $lesson['embed_ctx'] ) ) {
		?>
		<div class="masterstudy-course-player-lesson-video__embed-wrapper">
			<?php echo wp_kses( htmlspecialchars_decode( $lesson['embed_ctx'] ), stm_lms_allowed_html() ); ?>
		</div>
		<?php
	} elseif ( in_array( $lesson['video_type'], array( LessonVideoType::HTML, LessonVideoType::EXT_LINK, 'external_url' ), true ) ) {
		$uploaded_video = $lesson['external_url'] ?? '';
		$video_format   = 'mp4';

		if ( LessonVideoType::HTML === $lesson['video_type'] ) {
			$uploaded_video = $lesson['video']['url'] ?? '';
			$video_format   = explode( '.', $uploaded_video );
			$video_format   = strtolower( end( $video_format ) );
			$video_width    = ! empty( $lesson['video_width'] ) ? "max-width: {$lesson['video_width']}px" : '';
		}
		?>
		<div class="masterstudy-course-player-lesson-video__wrapper" style="<?php echo esc_attr( ! empty( $video_width ) ? $video_width : '' ); ?>">
			<video class="masterstudy-plyr-video-player" data-id="<?php echo esc_attr( $id ); ?>"
				data-poster="<?php echo esc_url( $lesson['video_poster']['url'] ?? '' ); ?>"
				controls
				controlsList="nodownload">
				<source src="<?php echo esc_url( $uploaded_video ); ?>"
					type='video/<?php echo esc_attr( $video_format ); ?>'>
			</video>
		</div>
		<?php
	} elseif ( in_array( $lesson['video_type'], array( LessonVideoType::YOUTUBE, LessonVideoType::VIMEO ), true ) ) {
		$video_id = $is_youtube_type ? ms_plugin_get_youtube_id( $lesson['youtube_url'] ) : ms_plugin_get_vimeo_id( $lesson['vimeo_url'] );

		if ( $plyr_vimeo_video_player && $is_vimeo_type || $plyr_youtube_video_player && $is_youtube_type ) {
			?>
			<div class="masterstudy-plyr-video-player" class="plyr__video-embed">
		<?php } ?>
		<iframe
			id="videoPlayer"
			src="<?php // phpcs:disable
				echo esc_attr(
					'youtube' === $lesson['video_type']
						? "https://www.youtube.com/embed/{$video_id}?&amp;iv_load_policy=3&amp;modestbranding=1&amp;playsinline=1&amp;showinfo=0&amp;rel=0&amp;enablejsapi=1&customControls=true"
						: "https://player.vimeo.com/video/{$video_id}?loop=false&amp;byline=false&amp;portrait=false&amp;title=false&amp;speed=true&amp;transparent=0&amp;gesture=media&amp;api=1&player_id=videoPlayer"
				);
			?>"
			frameborder="0"
			allowfullscreen
			allowtransparency
			allow="autoplay">
		</iframe>
		<?php if ( $plyr_vimeo_video_player && $is_vimeo_type || $plyr_youtube_video_player && $is_youtube_type ) { ?>
			</div>
		<?php }
	} elseif ( in_array( $lesson['video_type'], array( LessonVideoType::PRESTO_PLAYER, LessonVideoType::SHORTCODE ), true ) ) {
		echo 'presto_player' === $lesson['video_type'] && ! empty( $lesson['presto_player_idx'] ) ? do_shortcode( '[presto_player id="' . esc_attr( $lesson['presto_player_idx'] ) . '"]' ) : do_shortcode( $lesson['shortcode'] );
	} elseif ( LessonVideoType::VDOCIPHER === $lesson['video_type'] && ! empty( $lesson['vdocipher_id'] ) ) {
		$vdocipher_id = preg_replace( '/\[vdo id="([^"]*)"\]/', '$1', $lesson['vdocipher_id'] );

		echo do_shortcode( '[vdo id="' . esc_attr( $vdocipher_id ) . '"]' );
	}

	if ( ! empty( $lesson['video_required_progress'] ) && STM_LMS_Helpers::is_pro_plus() && ! ( 0 === $user_progress && $lesson_completed ) ) {
		?>
		<div class="masterstudy-course-player-lesson-video__progress">
			<div class="masterstudy-course-player-lesson-video__progress-container">
				<span class="masterstudy-course-player-lesson-video__progress-label">
					<?php echo esc_html__( 'Required video progress', 'masterstudy-lms-learning-management-system' ); ?>:
				</span>
				<span data-required-progress="<?php echo esc_attr( $lesson['video_required_progress'] ); ?>" id="required-video-progress" class="masterstudy-course-player-lesson-video__progress-value">
					<?php echo esc_html( $lesson['video_required_progress'] ); ?>%
				</span>
			</div>
			<div class="masterstudy-course-player-lesson-video__progress-container">
				<span class="masterstudy-course-player-lesson-video__progress-label">
					<?php echo esc_html__( 'Current video progress', 'masterstudy-lms-learning-management-system' ); ?>:
				</span>
				<span class="masterstudy-course-player-lesson-video__progress-value" data-progress="<?php echo esc_attr( $user_progress ); ?>" id="current-video-progress"><?php echo esc_html( $user_progress ); ?>%</span>
			</div>
		</div>
	<?php } ?>
</div>
