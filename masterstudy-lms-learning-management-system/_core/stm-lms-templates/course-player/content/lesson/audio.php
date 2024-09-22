<?php
/**
* @var int $item_id
* @var boolean $dark_mode
*/

use MasterStudy\Lms\Repositories\LessonRepository;

wp_enqueue_style( 'masterstudy-course-player-lesson-type-audio' );
wp_enqueue_script( 'masterstudy-course-player-audio-lesson-type' );

$lesson_data = ( new LessonRepository() )->get( $item_id );
$audio_type  = $lesson_data['audio_type'] ?? '';
?>
<div class="masterstudy-course-player-audio-lesson-type">
	<?php
	switch ( $audio_type ) {
		case 'file':
			$file_data = $lesson_data['file'] ?? '';
			if ( ! empty( $file_data['url'] ) ) {
				STM_LMS_Templates::show_lms_template(
					'components/audio-player',
					array(
						'preloader' => false,
						'src'       => $file_data['url'],
						'dark_mode' => $dark_mode,
					)
				);
			}

			break;
		case 'embed':
			$lesson_embed_ctx = $lesson_data['embed_ctx'] ?? '';
			if ( ! empty( $lesson_embed_ctx ) ) {
				?>
				<div class="masterstudy-course-player-lesson-video__embed-wrapper">
					<?php echo wp_kses( htmlspecialchars_decode( $lesson_embed_ctx ), stm_lms_allowed_html() ); ?>
				</div>
				<?php
			}
			break;
		case 'ext_link':
			$external_link = $lesson_data['external_url'] ?? '';
			if ( ! empty( $external_link ) ) {
				?>
				<audio controls class="audio-external-links-type">
					<?php foreach ( array( 'mpeg', 'webm', 'ogg', 'wav' ) as $format ) : ?>
						<source src="<?php echo esc_url( $external_link ); ?>"
							type="audio/<?php echo esc_html( $format ); ?>">
					<?php endforeach; ?>
					<?php echo esc_html__( 'Your browser does not support the audio external link.', 'masterstudy-lms-learning-management-system' ); ?>
				</audio>
				<?php
			}
			break;
		case 'shortcode':
			$lesson_shortcode = $lesson_data['shortcode'] ?? '';
			if ( ! empty( $lesson_shortcode ) ) {
				echo do_shortcode( $lesson_shortcode );
			}
			break;
	}
	?>
</div>
<?php
