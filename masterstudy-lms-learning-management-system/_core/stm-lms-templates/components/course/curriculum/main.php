<?php
/**
 * @var object $course
 * @var string $style
 * @var boolean $dark_mode
 *
 * masterstudy-curriculum-list_dark-mode - for dark mode
 * masterstudy-curriculum-list_classic - for classic style
 * masterstudy-curriculum-list__link_disabled - for disable click on lesson
 */

use \MasterStudy\Lms\Repositories\CurriculumRepository;

$dark_mode  = false;
$style      = isset( $style ) ? $style : 'default';
$template   = $course->is_udemy_course ? 'udemy-materials' : 'materials';
$curriculum = $course->is_udemy_course
	? get_post_meta( $course->id, 'udemy_curriculum', true )
	: ( new CurriculumRepository() )->get_curriculum( $course->id, true );

if ( empty( $curriculum ) ) {
	return;
}
?>

<div class="masterstudy-curriculum-list <?php echo esc_attr( $dark_mode ? 'masterstudy-curriculum-list_dark-mode' : '' ); ?> <?php echo esc_attr( 'classic' === $style ? 'masterstudy-curriculum-list_classic' : '' ); ?>">
	<?php
	STM_LMS_Templates::show_lms_template(
		"components/course/curriculum/{$template}",
		array(
			'course_id'  => $course->id,
			'curriculum' => $curriculum,
			'dark_mode'  => $dark_mode,
		)
	);
	?>
</div>
