<?php
/**
 * @var object $course
 * @var array $course_preview
 * @var boolean $with_image
 * @var boolean $mode
 */

$with_image = isset( $with_image ) ? $with_image : false;
$mode       = $mode ?? '';

?>

<div class="masterstudy-single-course-description">
	<?php if ( ! empty( $course->thumbnail ) && $with_image && ( empty( $course_preview['video_type'] ) || 'none' == $course_preview['video_type'] ) ) { ?>
		<img class="masterstudy-single-course-description__image"
			src="<?php echo esc_url( $course->thumbnail['url'] ); ?>"
			alt="<?php echo esc_html( $course->thumbnail['title'] ); ?>">
		<?php
	} else if ( ! empty( $course_preview['video_type'] ) && $with_image || 'full_width' === $mode ) {
		wp_enqueue_style( 'masterstudy-single-course-video-preview' );
		STM_LMS_Templates::show_lms_template(
			'components/video-media',
			array(
				'lesson' => (array) $course_preview ?? '',
				'id'     => $course->id,
				'mode'   => true,
			)
		);
	}
	?>
	<div class="masterstudy-single-course-description__content">
		<?php
		$post = get_post( $course->id );
		setup_postdata( $post );
		the_content();
		wp_reset_postdata();
		?>
	</div>
	<?php if ( ! empty( $course->attachments ) ) { ?>
		<div class="masterstudy-single-course-description__files">
			<?php
			STM_LMS_Templates::show_lms_template(
				'components/course/materials',
				array(
					'attachments' => $course->attachments,
				)
			);
			?>
		</div>
	<?php } ?>
</div>
