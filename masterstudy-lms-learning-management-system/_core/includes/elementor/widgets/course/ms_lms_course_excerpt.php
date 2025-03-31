<?php
namespace StmLmsElementor\Widgets\Course;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Typography;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class MsLmsCourseExcerpt extends Widget_Base {

	public function get_name() {
		return 'ms_lms_course_excerpt';
	}

	public function get_title() {
		return esc_html__( 'Excerpt', 'masterstudy-lms-learning-management-system' );
	}

	public function get_icon() {
		return 'stmlms-course-short-text lms-course-icon';
	}

	public function get_categories() {
		return array( 'stm_lms_course' );
	}

	public function get_style_depends() {
		return array(
			'masterstudy-single-course-components',
		);
	}

	protected function register_controls() {
		$courses = \STM_LMS_Courses::get_all_courses_for_options();

		$this->start_controls_section(
			'section',
			array(
				'label' => esc_html__( 'Content', 'masterstudy-lms-learning-management-system' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_control(
			'course',
			array(
				'label'              => esc_html__( 'Course', 'masterstudy-lms-learning-management-system' ),
				'type'               => Controls_Manager::SELECT2,
				'label_block'        => true,
				'multiple'           => false,
				'options'            => $courses,
				'default'            => ! empty( $courses ) ? key( $courses ) : '',
				'frontend_available' => true,
			)
		);
		$this->add_control(
			'preset',
			array(
				'label'              => esc_html__( 'Preset', 'masterstudy-lms-learning-management-system' ),
				'type'               => Controls_Manager::SELECT,
				'default'            => 'default',
				'frontend_available' => true,
				'options'            => array(
					'default' => esc_html__( 'Full', 'masterstudy-lms-learning-management-system' ),
					'short'   => esc_html__( 'Short', 'masterstudy-lms-learning-management-system' ),
				),
			)
		);
		$this->end_controls_section();
		$this->start_controls_section(
			'styles_section',
			array(
				'label' => esc_html__( 'Text', 'masterstudy-lms-learning-management-system' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'       => 'typography',
				'selector'   => '{{WRAPPER}} .masterstudy-single-course-excerpt',
				'conditions' => array(
					'terms' => array(
						array(
							'name'     => 'preset',
							'operator' => '===',
							'value'    => 'default',
						),
					),
				),
			)
		);
		$this->add_control(
			'color',
			array(
				'label'      => esc_html__( 'Color', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::COLOR,
				'selectors'  => array(
					'{{WRAPPER}} .masterstudy-single-course-excerpt' => 'color: {{VALUE}}',
				),
				'conditions' => array(
					'terms' => array(
						array(
							'name'     => 'preset',
							'operator' => '===',
							'value'    => 'default',
						),
					),
				),
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'       => 'visible_typography',
				'selector'   => '{{WRAPPER}} .masterstudy-single-course-excerpt__visible,
				{{WRAPPER}} .masterstudy-single-course-excerpt__continue,
				{{WRAPPER}} .masterstudy-single-course-excerpt__hidden',
				'conditions' => array(
					'terms' => array(
						array(
							'name'     => 'preset',
							'operator' => '===',
							'value'    => 'short',
						),
					),
				),
			)
		);
		$this->add_control(
			'visible_color',
			array(
				'label'      => esc_html__( 'Color', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::COLOR,
				'selectors'  => array(
					'{{WRAPPER}} .masterstudy-single-course-excerpt__visible,
					{{WRAPPER}} .masterstudy-single-course-excerpt__continue,
					{{WRAPPER}} .masterstudy-single-course-excerpt__hidden' => 'color: {{VALUE}}',
				),
				'conditions' => array(
					'terms' => array(
						array(
							'name'     => 'preset',
							'operator' => '===',
							'value'    => 'short',
						),
					),
				),
			)
		);
		$this->end_controls_section();
		$this->start_controls_section(
			'styles_button_section',
			array(
				'label'      => esc_html__( 'Show more', 'masterstudy-lms-learning-management-system' ),
				'tab'        => Controls_Manager::TAB_STYLE,
				'conditions' => array(
					'terms' => array(
						array(
							'name'     => 'preset',
							'operator' => '===',
							'value'    => 'short',
						),
					),
				),
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'button_typography',
				'selector' => '{{WRAPPER}} span.masterstudy-single-course-excerpt__more',
			)
		);
		$this->add_control(
			'button_color',
			array(
				'label'      => esc_html__( 'Color', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::COLOR,
				'selectors'  => array(
					'{{WRAPPER}} span.masterstudy-single-course-excerpt__more' => 'color: {{VALUE}};',
				),
				'conditions' => array(
					'terms' => array(
						array(
							'name'     => 'preset',
							'operator' => '===',
							'value'    => 'short',
						),
					),
				),
			)
		);
		$this->add_control(
			'button_hover_color',
			array(
				'label'      => esc_html__( 'Hover Color', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::COLOR,
				'selectors'  => array(
					'{{WRAPPER}} span.masterstudy-single-course-excerpt__more:hover' => 'color: {{VALUE}};',
				),
				'conditions' => array(
					'terms' => array(
						array(
							'name'     => 'preset',
							'operator' => '===',
							'value'    => 'short',
						),
					),
				),
			)
		);
		$this->end_controls_section();
	}

	public function get_script_depends() {
		return array( 'masterstudy-course-components-editor' );
	}

	protected function render() {
		$settings    = $this->get_settings_for_display();
		$course_id   = $settings['course'] ?? null;
		$course_data = masterstudy_get_elementor_course_data( intval( $course_id ) );

		if ( empty( $course_data ) || ! isset( $course_data['course'] ) ) {
			return;
		}

		if ( ! empty( $course_data['course']->excerpt ) || ! empty( $course_data['course']->udemy_headline ) ) {

			wp_enqueue_script( 'masterstudy-single-course-components', STM_LMS_URL . '/assets/js/components/course/main.js', array( 'jquery', 'jquery.cookie' ), MS_LMS_VERSION, true );

			\STM_LMS_Templates::show_lms_template(
				'components/course/excerpt',
				array(
					'course' => $course_data['course'],
					'long'   => 'short' === $settings['preset'] ? false : true,
				)
			);
		}
	}
}
