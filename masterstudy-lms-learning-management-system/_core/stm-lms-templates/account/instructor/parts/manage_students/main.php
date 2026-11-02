<?php
/**
 * @var int $course_id
 */

wp_enqueue_style( 'masterstudy-account-manage-students-main' );
wp_enqueue_script( 'masterstudy-account-manage-students-export-students' );
wp_enqueue_script( 'masterstudy-account-manage-students-main' );
wp_enqueue_style( 'masterstudy-progress' );

$coming_soon    = is_ms_lms_addon_enabled( 'coming_soon' );
$student_public = STM_LMS_Options::get_option( 'student_public_profile', true );

$students_table_columns = array(
	array(
		'label'    => __( 'Student', 'masterstudy-lms-learning-management-system' ),
		'key'      => 'student',
		'width'    => '34%',
		'sortable' => true,
		'sortKey'  => 'username',
	),
	array(
		'label'  => __( 'Subscribed', 'masterstudy-lms-learning-management-system' ),
		'key'    => 'subscribed',
		'width'  => '120px',
		'hidden' => ! $coming_soon,
	),
	array(
		'label'    => __( 'Started', 'masterstudy-lms-learning-management-system' ),
		'key'      => 'started',
		'width'    => '180px',
		'sortable' => true,
		'sortKey'  => 'ago',
	),
	array(
		'label'    => __( 'Progress', 'masterstudy-lms-learning-management-system' ),
		'key'      => 'progress',
		'width'    => '210px',
		'sortable' => true,
		'sortKey'  => 'progress_percent',
	),
	array(
		'label'     => '',
		'key'       => 'actions',
		'width'     => '182px',
		'align'     => 'end',
	),
);

wp_localize_script(
	'masterstudy-account-manage-students-main',
	'manage_students_data',
	array(
		'columns'        => array_values( $students_table_columns ),
		'student_public' => (bool) $student_public,
		'empty_text'     => esc_html__( 'No Students Found.', 'masterstudy-lms-learning-management-system' ),
		'error_text'     => esc_html__( 'Something went wrong. Please try again.', 'masterstudy-lms-learning-management-system' ),
		'progress_text'  => esc_html__( 'Course progress:', 'masterstudy-lms-learning-management-system' ),
		'progress_cta'   => esc_html__( 'Progress', 'masterstudy-lms-learning-management-system' ),
		'retry_text'     => esc_html__( 'Retry', 'masterstudy-lms-learning-management-system' ),
	)
);
?>
<div id="masterstudy-manage-students" class="masterstudy-manage-students">
	<div class="masterstudy-manage-students__top">
		<div class="masterstudy-manage-students__top-container">
			<?php
			STM_LMS_Templates::show_lms_template(
				'components/button',
				array(
					'title'         => '',
					'link'          => STM_LMS_User::user_page_url(),
					'style'         => 'secondary',
					'size'          => 'sm',
					'icon_position' => 'left',
					'icon_name'     => 'arrow-left',
					'class'         => 'masterstudy-manage-students__back-btn',
				)
			);
			?>
			<div class="masterstudy-manage-students__top-info">
				<div class="masterstudy-manage-students__top-info-container">
					<div class="masterstudy-manage-students__course-title">
						<?php echo esc_html( get_the_title( $course_id ) ); ?>
					</div>
					<div class="masterstudy-manage-students__count">
						<span class="masterstudy-manage-students__count-number"></span>
						<span class="masterstudy-manage-students__count-label">
							<?php echo esc_html__( 'students', 'masterstudy-lms-learning-management-system' ); ?>
						</span>
					</div>
				</div>
				<?php
				STM_LMS_Templates::show_lms_template(
					'components/button',
					array(
						'title'         => esc_html__( 'Add', 'masterstudy-lms-learning-management-system' ),
						'link'          => '#',
						'style'         => 'primary',
						'size'          => 'sm',
						'id'            => 'add-student',
						'icon_position' => 'left',
						'icon_name'     => '',
					)
				);
				?>
			</div>
		</div>
		<div class="masterstudy-table__toolbar">
			<div class="masterstudy-table__toolbar-search-wrapper">
				<?php
				STM_LMS_Templates::show_lms_template(
					'components/search',
					array(
						'search_name'  => 's',
						'is_queryable' => false,
						'placeholder'  => esc_html__( 'Search student', 'masterstudy-lms-learning-management-system' ),
					)
				);
				?>
			</div>
			<?php
			STM_LMS_Templates::show_lms_template(
				'components/button',
				array(
					'title' => esc_html__( 'Import CSV', 'masterstudy-lms-learning-management-system' ),
					'link'  => '#',
					'style' => 'secondary',
					'size'  => 'sm',
					'id'    => 'import-students-via-csv',
				)
			);
			STM_LMS_Templates::show_lms_template(
				'components/button',
				array(
					'title' => esc_html__( 'Export CSV', 'masterstudy-lms-learning-management-system' ),
					'link'  => '#',
					'style' => 'secondary',
					'size'  => 'sm',
					'id'    => 'export-students-to-csv',
				)
			);
			?>
		</div>
	</div>

	<div class="masterstudy-manage-students__table">
		<div data-masterstudy-table="manage-students">
			<template data-masterstudy-table-card>
				<div class="masterstudy-manage-students__card-row masterstudy-manage-students__card-row_student">
					<div data-masterstudy-table-key="student"></div>
				</div>
				<?php if ( $coming_soon ) : ?>
					<div class="masterstudy-manage-students__card-row">
						<span class="masterstudy-manage-students__card-label"><?php echo esc_html__( 'Subscribed:', 'masterstudy-lms-learning-management-system' ); ?></span>
						<span class="masterstudy-manage-students__card-value" data-masterstudy-table-key="subscribed"></span>
					</div>
				<?php endif; ?>
				<div class="masterstudy-manage-students__card-row">
					<span class="masterstudy-manage-students__card-label"><?php echo esc_html__( 'Started:', 'masterstudy-lms-learning-management-system' ); ?></span>
					<span class="masterstudy-manage-students__card-value" data-masterstudy-table-key="started"></span>
				</div>
				<div class="masterstudy-manage-students__card-row">
					<span class="masterstudy-manage-students__card-label"><?php echo esc_html__( 'Course progress:', 'masterstudy-lms-learning-management-system' ); ?></span>
					<span class="masterstudy-manage-students__card-value" data-masterstudy-table-key="progress"></span>
				</div>
				<div class="masterstudy-manage-students__card-row masterstudy-manage-students__card-row_actions">
					<div data-masterstudy-table-key="actions"></div>
				</div>
			</template>
		</div>
		<div class="masterstudy-manage-students__per-page" data-masterstudy-table-per-page="manage-students">
			<?php
			STM_LMS_Templates::show_lms_template(
				'components/select',
				array(
					'select_id'    => 'manage-students-per-page',
					'select_width' => '170px',
					'select_name'  => 'per_page',
					'placeholder'  => esc_html__( '10 per page', 'masterstudy-lms-learning-management-system' ),
					'default'      => 10,
					'is_queryable' => false,
					'clearable'    => false,
					'options'      => array(
						'10'  => esc_html__( '10 per page', 'masterstudy-lms-learning-management-system' ),
						'25'  => esc_html__( '25 per page', 'masterstudy-lms-learning-management-system' ),
						'50'  => esc_html__( '50 per page', 'masterstudy-lms-learning-management-system' ),
						'75'  => esc_html__( '75 per page', 'masterstudy-lms-learning-management-system' ),
						'100' => esc_html__( '100 per page', 'masterstudy-lms-learning-management-system' ),
					),
				)
			);
			?>
		</div>
		<?php
		STM_LMS_Templates::show_lms_template(
			'components/alert',
			array(
				'id'                  => 'masterstudy-manage-students-delete-student',
				'title'               => esc_html__( 'Delete student', 'masterstudy-lms-learning-management-system' ),
				'text'                => esc_html__( 'Are you sure you want to delete this student from course ?', 'masterstudy-lms-learning-management-system' ),
				'submit_button_text'  => esc_html__( 'Delete', 'masterstudy-lms-learning-management-system' ),
				'cancel_button_text'  => esc_html__( 'Cancel', 'masterstudy-lms-learning-management-system' ),
				'submit_button_style' => 'danger',
				'cancel_button_style' => 'tertiary',
				'dark_mode'           => false,
			)
		);
		?>
	</div>
</div>
<?php
STM_LMS_Templates::show_lms_template( 'account/instructor/parts/manage_students/import-modal', compact( 'course_id' ) );
?>
