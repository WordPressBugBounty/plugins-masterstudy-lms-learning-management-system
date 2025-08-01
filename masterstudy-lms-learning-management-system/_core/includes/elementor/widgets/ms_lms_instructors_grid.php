<?php
namespace StmLmsElementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;
use Elementor\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class MsLmsInstructorsGrid extends Widget_Base {

	public function __construct( $data = array(), $args = null ) {
		parent::__construct( $data, $args );
		wp_register_style( 'masterstudy-fonts', STM_LMS_URL . 'assets/css/variables/fonts.css', array(), STM_LMS_VERSION, false );
		wp_register_style( 'ms_lms_instructors_grid', STM_LMS_URL . 'assets/css/elementor-widgets/instructors-grid.css', array(), STM_LMS_VERSION, false );
	}

	public function get_name() {
		return 'ms_lms_instructors_grid';
	}

	public function get_title() {
		return esc_html__( 'Instructors Grid', 'masterstudy-lms-learning-management-system' );
	}

	public function get_style_depends() {
		return array( 'masterstudy-fonts', 'ms_lms_instructors_grid' );
	}

	public function get_icon() {
		return 'stmlms-instructors-grid lms-icon';
	}

	public function get_categories() {
		return array( 'stm_lms' );
	}

	public static function show_reviews() {
		return \STM_LMS_Options::get_option( 'course_tab_reviews', true );
	}

	protected function register_controls() {
		$this->register_content_controls_presets();
		$this->register_content_controls_header();
		$this->register_content_controls_card();
		$this->register_style_controls_title();
		$this->register_style_controls_description();
		$this->register_style_controls_instructor_card();
		$this->register_style_controls_instructor_name();
		$this->register_style_controls_instructor_position();
		$this->register_style_controls_instructor_courses();
		$this->register_style_controls_instructor_picture();
		if ( self::show_reviews() ) {
			$this->register_style_controls_instructor_reviews();
			$this->register_style_controls_instructor_reviews_count();
		}
		$this->register_style_controls_instructor_social();
		$this->register_style_controls_view_all_button();
	}

	protected function register_content_controls_presets() {

		$this->start_controls_section(
			'presets_section',
			array(
				'label' => esc_html__( 'Presets', 'masterstudy-lms-learning-management-system' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_control(
			'widget_header_presets',
			array(
				'label'   => esc_html__( 'Headers', 'masterstudy-lms-learning-management-system' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'style_1',
				'options' => array(
					'style_1' => esc_html__( 'With Subtitle', 'masterstudy-lms-learning-management-system' ),
					'style_2' => esc_html__( 'No Subtitle', 'masterstudy-lms-learning-management-system' ),
				),
			)
		);
		$this->add_control(
			'instructor_card_presets',
			array(
				'label'   => esc_html__( 'Card', 'masterstudy-lms-learning-management-system' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'style_1',
				'options' => array(
					'style_1' => esc_html__( 'Profile Picture Rounded', 'masterstudy-lms-learning-management-system' ),
					'style_2' => esc_html__( 'Profile Picture Rounded Dark', 'masterstudy-lms-learning-management-system' ),
					'style_3' => esc_html__( 'Bordered Card', 'masterstudy-lms-learning-management-system' ),
					'style_4' => esc_html__( 'Squared Profile Picture', 'masterstudy-lms-learning-management-system' ),
					'style_5' => esc_html__( 'Bordered Squared', 'masterstudy-lms-learning-management-system' ),
					'style_6' => esc_html__( 'Profile Picture Above Container', 'masterstudy-lms-learning-management-system' ),
				),
			)
		);
		$this->add_control(
			'socials_presets',
			array(
				'label'   => esc_html__( 'Socials', 'masterstudy-lms-learning-management-system' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'style_1',
				'options' => array(
					'style_1' => esc_html__( 'No Background', 'masterstudy-lms-learning-management-system' ),
					'style_2' => esc_html__( 'With Background', 'masterstudy-lms-learning-management-system' ),
				),
			)
		);
		$this->end_controls_section();
	}

	protected function register_content_controls_header() {
		$this->start_controls_section(
			'header_section',
			array(
				'label' => esc_html__( 'Header', 'masterstudy-lms-learning-management-system' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_control(
			'widget_title',
			array(
				'label'       => esc_html__( 'Title', 'masterstudy-lms-learning-management-system' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Our Instructors', 'masterstudy-lms-learning-management-system' ),
				'placeholder' => esc_html__( 'Type your title here', 'masterstudy-lms-learning-management-system' ),
			)
		);
		$this->add_control(
			'widget_description',
			array(
				'label'       => esc_html__( 'Description', 'masterstudy-lms-learning-management-system' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 5,
				'default'     => esc_html__( 'The float menu makes the interaction between students and the site more comfortable. It increases usability and attractiveness of the page.', 'masterstudy-lms-learning-management-system' ),
				'placeholder' => esc_html__( 'Type your description here', 'masterstudy-lms-learning-management-system' ),
				'conditions'  => array(
					'terms' => array(
						array(
							'name'     => 'widget_header_presets',
							'operator' => '!==',
							'value'    => 'style_2',
						),
					),
				),
			)
		);
		$this->add_control(
			'show_view_all',
			array(
				'label'        => esc_html__( '"View all" Button', 'masterstudy-lms-learning-management-system' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Show', 'masterstudy-lms-learning-management-system' ),
				'label_off'    => esc_html__( 'Hide', 'masterstudy-lms-learning-management-system' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->end_controls_section();
	}

	protected function register_content_controls_card() {
		$show_reviews = self::show_reviews();

		$this->start_controls_section(
			'instructor_section',
			array(
				'label' => esc_html__( 'Card', 'masterstudy-lms-learning-management-system' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$sort_options = array(
			'date'            => esc_html__( 'Registered Date', 'masterstudy-lms-learning-management-system' ),
			'course_quantity' => esc_html__( 'Quantity of Courses', 'masterstudy-lms-learning-management-system' ),
		);

		if ( $show_reviews ) {
			$sort_options['sum_rating'] = esc_html__( 'Rating', 'masterstudy-lms-learning-management-system' );
		}

		$this->add_control(
			'instructors_to_show_choice',
			array(
				'label'   => esc_html__( 'Instructors Per Page', 'masterstudy-lms-learning-management-system' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'number',
				'options' => array(
					'all'    => esc_html__( 'All', 'masterstudy-lms-learning-management-system' ),
					'number' => esc_html__( 'Select Quantity', 'masterstudy-lms-learning-management-system' ),
				),
			)
		);
		$this->add_control(
			'instructors_to_show',
			array(
				'label'              => esc_html__( 'Quantity', 'masterstudy-lms-learning-management-system' ),
				'type'               => Controls_Manager::NUMBER,
				'min'                => 0,
				'max'                => 100,
				'step'               => 1,
				'default'            => 8,
				'frontend_available' => true,
				'conditions'         => array(
					'terms' => array(
						array(
							'name'     => 'instructors_to_show_choice',
							'operator' => '===',
							'value'    => 'number',
						),
					),
				),
			)
		);
		$this->add_control(
			'instructors_sort_by',
			array(
				'label'   => esc_html__( 'Sort By', 'masterstudy-lms-learning-management-system' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'date',
				'options' => $sort_options,
			)
		);
		$this->add_control(
			'show_avatars',
			array(
				'label'        => esc_html__( 'Profile Picture', 'masterstudy-lms-learning-management-system' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Show', 'masterstudy-lms-learning-management-system' ),
				'label_off'    => esc_html__( 'Hide', 'masterstudy-lms-learning-management-system' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->add_control(
			'show_instructor_position',
			array(
				'label'        => esc_html__( 'Position', 'masterstudy-lms-learning-management-system' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Show', 'masterstudy-lms-learning-management-system' ),
				'label_off'    => esc_html__( 'Hide', 'masterstudy-lms-learning-management-system' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->add_control(
			'show_instructor_course_quantity',
			array(
				'label'        => esc_html__( 'Number of Courses', 'masterstudy-lms-learning-management-system' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Show', 'masterstudy-lms-learning-management-system' ),
				'label_off'    => esc_html__( 'Hide', 'masterstudy-lms-learning-management-system' ),
				'return_value' => 'yes',
				'default'      => '',
			)
		);
		if ( $show_reviews ) {
			$this->add_control(
				'show_reviews',
				array(
					'label'        => esc_html__( 'Reviews', 'masterstudy-lms-learning-management-system' ),
					'type'         => Controls_Manager::SWITCHER,
					'label_on'     => esc_html__( 'Show', 'masterstudy-lms-learning-management-system' ),
					'label_off'    => esc_html__( 'Hide', 'masterstudy-lms-learning-management-system' ),
					'return_value' => 'yes',
					'default'      => 'yes',
				)
			);
			$this->add_control(
				'show_reviews_count',
				array(
					'label'        => esc_html__( 'Reviews Count', 'masterstudy-lms-learning-management-system' ),
					'type'         => Controls_Manager::SWITCHER,
					'label_on'     => esc_html__( 'Show', 'masterstudy-lms-learning-management-system' ),
					'label_off'    => esc_html__( 'Hide', 'masterstudy-lms-learning-management-system' ),
					'return_value' => 'yes',
					'default'      => '',
					'condition'    => array(
						'show_reviews' => 'yes',
					),
				)
			);
		}
		$this->add_control(
			'show_socials',
			array(
				'label'        => esc_html__( 'Social Links', 'masterstudy-lms-learning-management-system' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Show', 'masterstudy-lms-learning-management-system' ),
				'label_off'    => esc_html__( 'Hide', 'masterstudy-lms-learning-management-system' ),
				'return_value' => 'yes',
				'default'      => '',
			)
		);
		$this->end_controls_section();
	}

	protected function register_style_controls_title() {

		$this->start_controls_section(
			'section_widget_title',
			array(
				'label' => esc_html__( 'Title', 'masterstudy-lms-learning-management-system' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'widget_title_typography',
				'selector' => '{{WRAPPER}} .ms_lms_instructors_grid__header_title',
			)
		);
		$this->add_control(
			'widget_title_color',
			array(
				'label'     => esc_html__( 'Color', 'masterstudy-lms-learning-management-system' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__header_title' => 'color: {{VALUE}}',
				),
			)
		);
		$this->add_responsive_control(
			'widget_title_align',
			array(
				'label'      => esc_html__( 'Alignment', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::CHOOSE,
				'options'    => array(
					'flex-start' => array(
						'title' => esc_html__( 'Left', 'masterstudy-lms-learning-management-system' ),
						'icon'  => 'eicon-h-align-left',
					),
					'center'     => array(
						'title' => esc_html__( 'Center', 'masterstudy-lms-learning-management-system' ),
						'icon'  => 'eicon-h-align-center',
					),
					'flex-end'   => array(
						'title' => esc_html__( 'Right', 'masterstudy-lms-learning-management-system' ),
						'icon'  => 'eicon-h-align-right',
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__header_title' => 'align-self: {{VALUE}};',
				),
				'conditions' => array(
					'terms' => array(
						array(
							'name'     => 'widget_header_presets',
							'operator' => '!==',
							'value'    => 'style_2',
						),
					),
				),
			)
		);
		$this->add_responsive_control(
			'widget_title_padding',
			array(
				'label'      => esc_html__( 'Padding', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__header_title' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->add_responsive_control(
			'widget_title_margin',
			array(
				'label'      => esc_html__( 'Margin', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__header_title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'default'    => array(
					'top'    => '0',
					'right'  => '0',
					'bottom' => '0',
					'left'   => '0',
					'unit'   => 'px',
				),
			)
		);
		$this->end_controls_section();
	}

	protected function register_style_controls_description() {

		$this->start_controls_section(
			'section_widget_description',
			array(
				'label'      => esc_html__( 'Description', 'masterstudy-lms-learning-management-system' ),
				'tab'        => Controls_Manager::TAB_STYLE,
				'conditions' => array(
					'terms' => array(
						array(
							'name'     => 'widget_header_presets',
							'operator' => '!==',
							'value'    => 'style_2',
						),
					),
				),
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'widget_description_typography',
				'selector' => '{{WRAPPER}} .ms_lms_instructors_grid__header_description',
			)
		);
		$this->add_control(
			'widget_description_color',
			array(
				'label'     => esc_html__( 'Color', 'masterstudy-lms-learning-management-system' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__header_description' => 'color: {{VALUE}}',
				),
			)
		);
		$this->add_responsive_control(
			'widget_description_align',
			array(
				'label'     => esc_html__( 'Alignment', 'masterstudy-lms-learning-management-system' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'left'    => array(
						'title' => esc_html__( 'Left', 'masterstudy-lms-learning-management-system' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center'  => array(
						'title' => esc_html__( 'Center', 'masterstudy-lms-learning-management-system' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'   => array(
						'title' => esc_html__( 'Right', 'masterstudy-lms-learning-management-system' ),
						'icon'  => 'eicon-text-align-right',
					),
					'justify' => array(
						'title' => esc_html__( 'Justified', 'masterstudy-lms-learning-management-system' ),
						'icon'  => 'eicon-text-align-justify',
					),
				),
				'default'   => 'center',
				'selectors' => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__header_description' => 'text-align: {{VALUE}};',
				),
			)
		);
		$this->add_responsive_control(
			'widget_description_padding',
			array(
				'label'      => esc_html__( 'Padding', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__header_description' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'default'    => array(
					'top'    => '20',
					'right'  => '0',
					'bottom' => '10',
					'left'   => '0',
					'unit'   => 'px',
				),
			)
		);
		$this->add_responsive_control(
			'widget_description_margin',
			array(
				'label'      => esc_html__( 'Margin', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__header_description' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'default'    => array(
					'top'    => '0',
					'right'  => '0',
					'bottom' => '0',
					'left'   => '0',
					'unit'   => 'px',
				),
			)
		);
		$this->end_controls_section();
	}

	protected function register_style_controls_instructor_card() {

		$this->start_controls_section(
			'section_instructor_card',
			array(
				'label' => esc_html__( 'Instructor\'s Card', 'masterstudy-lms-learning-management-system' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->start_controls_tabs(
			'instructor_card_tab'
		);
		$this->start_controls_tab(
			'instructor_card_normal_tab',
			array(
				'label' => esc_html__( 'Normal', 'masterstudy-lms-learning-management-system' ),
			)
		);
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'instructor_card_background',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .ms_lms_instructors_grid__item_wrapper',
			)
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'instructor_card_border',
				'selector' => '{{WRAPPER}} .ms_lms_instructors_grid__item_wrapper',
			)
		);
		$this->add_control(
			'instructor_card_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__item_wrapper' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'instructor_card_box_shadow',
				'selector' => '{{WRAPPER}} .ms_lms_instructors_grid__item_wrapper',
			)
		);
		$this->end_controls_tab();
		$this->start_controls_tab(
			'instructor_card_hover_tab',
			array(
				'label' => esc_html__( 'Hover', 'masterstudy-lms-learning-management-system' ),
			)
		);
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'instructor_card_hover_background',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .ms_lms_instructors_grid__item_wrapper:hover',
			)
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'instructor_card_hover_border',
				'selector' => '{{WRAPPER}} .ms_lms_instructors_grid__item_wrapper:hover',
			)
		);
		$this->add_control(
			'instructor_card_hover_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__item_wrapper:hover' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'instructor_card_hover_box_shadow',
				'selector' => '{{WRAPPER}} .ms_lms_instructors_grid__item_wrapper:hover',
			)
		);
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->add_control(
			'instructor_card_divider',
			array(
				'type' => Controls_Manager::DIVIDER,
			)
		);
		$this->add_responsive_control(
			'instructor_card_padding',
			array(
				'label'      => esc_html__( 'Padding', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__item_wrapper' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->end_controls_section();
	}

	protected function register_style_controls_instructor_name() {

		$this->start_controls_section(
			'section_instructor_name',
			array(
				'label' => esc_html__( 'Instructor\'s Name', 'masterstudy-lms-learning-management-system' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'instructor_name_typography',
				'selector' => '{{WRAPPER}} .ms_lms_instructors_grid__item_title',
			)
		);
		$this->add_control(
			'instructor_name_color',
			array(
				'label'     => esc_html__( 'Color', 'masterstudy-lms-learning-management-system' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__item_title' => 'color: {{VALUE}}',
				),
			)
		);
		$this->add_responsive_control(
			'instructor_name_padding',
			array(
				'label'      => esc_html__( 'Padding', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__item_title' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->add_responsive_control(
			'instructor_name_margin',
			array(
				'label'      => esc_html__( 'Margin', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__item_title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'default'    => array(
					'top'    => '0',
					'right'  => '0',
					'bottom' => '0',
					'left'   => '0',
					'unit'   => 'px',
				),
			)
		);
		$this->end_controls_section();
	}

	protected function register_style_controls_instructor_position() {

		$this->start_controls_section(
			'section_instructor_position',
			array(
				'label'     => esc_html__( 'Instructor\'s Position', 'masterstudy-lms-learning-management-system' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'show_instructor_position' => 'yes',
				),
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'instructor_position_typography',
				'selector' => '{{WRAPPER}} .ms_lms_instructors_grid__item_position',
			)
		);
		$this->add_control(
			'instructor_position_color',
			array(
				'label'     => esc_html__( 'Color', 'masterstudy-lms-learning-management-system' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__item_position' => 'color: {{VALUE}}',
				),
			)
		);
		$this->add_responsive_control(
			'instructor_position_padding',
			array(
				'label'      => esc_html__( 'Padding', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__item_position' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->add_responsive_control(
			'instructor_position_margin',
			array(
				'label'      => esc_html__( 'Margin', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__item_position' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'default'    => array(
					'top'    => '5',
					'right'  => '0',
					'bottom' => '0',
					'left'   => '0',
					'unit'   => 'px',
				),
			)
		);
		$this->end_controls_section();
	}

	protected function register_style_controls_instructor_courses() {

		$this->start_controls_section(
			'section_instructor_course_quantity',
			array(
				'label'     => esc_html__( 'Instructor\'s Number of Courses', 'masterstudy-lms-learning-management-system' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'show_instructor_course_quantity' => 'yes',
				),
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'instructor_course_quantity_typography',
				'selector' => '{{WRAPPER}} .ms_lms_instructors_grid__item_courses',
			)
		);
		$this->add_control(
			'instructor_course_quantity_color',
			array(
				'label'     => esc_html__( 'Color', 'masterstudy-lms-learning-management-system' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__item_courses' => 'color: {{VALUE}}',
				),
			)
		);
		$this->add_responsive_control(
			'instructor_course_quantity_padding',
			array(
				'label'      => esc_html__( 'Padding', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__item_courses' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->add_responsive_control(
			'instructor_course_quantity_margin',
			array(
				'label'      => esc_html__( 'Margin', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__item_courses' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'default'    => array(
					'top'    => '15',
					'right'  => '0',
					'bottom' => '0',
					'left'   => '0',
					'unit'   => 'px',
				),
			)
		);
		$this->end_controls_section();
	}

	protected function register_style_controls_instructor_picture() {

		$this->start_controls_section(
			'section_instructor_image',
			array(
				'label'     => esc_html__( 'Instructor\'s Profile Picture', 'masterstudy-lms-learning-management-system' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'show_avatars' => 'yes',
				),
			)
		);
		$this->add_responsive_control(
			'instructor_image_width',
			array(
				'label'      => esc_html__( 'Image Width', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( '%', 'px' ),
				'selectors'  => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__item_avatar_img' => 'width: {{SIZE}}{{UNIT}};',
				),
			)
		);
		$this->add_responsive_control(
			'instructor_image_height',
			array(
				'label'      => esc_html__( 'Image Height', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( '%', 'px' ),
				'range'      => array(
					'%' => array(
						'min' => 0,
						'max' => 100,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__item_avatar_img' => 'height: {{SIZE}}{{UNIT}};',
				),
			)
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'           => 'instructor_image_border',
				'selector'       => '{{WRAPPER}} .ms_lms_instructors_grid__item_avatar_img',
				'fields_options' => array(
					'border' => array(
						'label' => esc_html__( 'Image Border Type', 'masterstudy-lms-learning-management-system' ),
					),
				),
			)
		);
		$this->add_control(
			'instructor_image_border_radius',
			array(
				'label'      => esc_html__( 'Image Border Radius', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__item_avatar_img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->add_responsive_control(
			'instructor_image_layout_padding',
			array(
				'label'      => esc_html__( 'Layout Padding', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__item_avatar_link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'           => 'instructor_image_layout_background',
				'types'          => array( 'classic', 'gradient' ),
				'selector'       => '{{WRAPPER}} .ms_lms_instructors_grid__item_avatar_link',
				'fields_options' => array(
					'background' => array(
						'label' => esc_html__( 'Layout Background Type', 'masterstudy-lms-learning-management-system' ),
					),
				),
			)
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'           => 'instructor_image_layout_border',
				'selector'       => '{{WRAPPER}} .ms_lms_instructors_grid__item_avatar_link',
				'fields_options' => array(
					'border' => array(
						'label' => esc_html__( 'Layout Border Type', 'masterstudy-lms-learning-management-system' ),
					),
				),
			)
		);
		$this->add_control(
			'instructor_image_layout_border_radius',
			array(
				'label'      => esc_html__( 'Layout Border Radius', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__item_avatar_link' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->end_controls_section();
	}

	protected function register_style_controls_instructor_reviews() {

		$this->start_controls_section(
			'section_instructor_reviews',
			array(
				'label'     => esc_html__( 'Instructor\'s Reviews', 'masterstudy-lms-learning-management-system' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'show_reviews' => 'yes',
				),
			)
		);
		$this->add_control(
			'instructor_reviews_stars_color',
			array(
				'label'     => esc_html__( 'Empty Stars Color', 'masterstudy-lms-learning-management-system' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__item_rating_stars::before' => 'color: {{VALUE}}',
				),
			)
		);
		$this->add_control(
			'instructor_reviews_stars_filled_color',
			array(
				'label'     => esc_html__( 'Filled Stars Color', 'masterstudy-lms-learning-management-system' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__item_rating_stars_filled::after' => 'color: {{VALUE}}',
				),
			)
		);
		$this->end_controls_section();
	}

	protected function register_style_controls_instructor_reviews_count() {

		$this->start_controls_section(
			'section_instructor_reviews_count',
			array(
				'label'     => esc_html__( 'Instructor\'s Reviews Count', 'masterstudy-lms-learning-management-system' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'show_reviews_count' => 'yes',
				),
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'instructor_reviews_count_typography',
				'selector' => '{{WRAPPER}} .ms_lms_instructors_grid__item_rating_quantity',
			)
		);
		$this->add_control(
			'instructor_reviews_count_color',
			array(
				'label'     => esc_html__( 'Color', 'masterstudy-lms-learning-management-system' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__item_rating_quantity' => 'color: {{VALUE}}',
				),
			)
		);
		$this->add_responsive_control(
			'instructor_reviews_count_padding',
			array(
				'label'      => esc_html__( 'Padding', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__item_rating_quantity' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->add_responsive_control(
			'instructor_reviews_count_margin',
			array(
				'label'      => esc_html__( 'Margin', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__item_rating' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'default'    => array(
					'top'    => '15',
					'right'  => '0',
					'bottom' => '0',
					'left'   => '0',
					'unit'   => 'px',
				),
			)
		);
		$this->end_controls_section();
	}

	protected function register_style_controls_instructor_social() {

		$this->start_controls_section(
			'socials_links',
			array(
				'label'     => esc_html__( 'Instructor\'s Social Links', 'masterstudy-lms-learning-management-system' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'show_socials' => 'yes',
				),
			)
		);
		$this->start_controls_tabs(
			'socials_links_tab'
		);
		$this->start_controls_tab(
			'socials_links_normal_tab',
			array(
				'label' => esc_html__( 'Normal', 'masterstudy-lms-learning-management-system' ),
			)
		);
		$this->add_control(
			'socials_links_color',
			array(
				'label'     => esc_html__( 'Color', 'masterstudy-lms-learning-management-system' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__item_socials_link i' => 'color: {{VALUE}}',
				),
			)
		);
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'socials_links_background',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .ms_lms_instructors_grid__item_socials_link',
			)
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'socials_links_border',
				'selector' => '{{WRAPPER}} .ms_lms_instructors_grid__item_socials_link',
			)
		);
		$this->add_control(
			'socials_links_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__item_socials_link' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'socials_links_box_shadow',
				'selector' => '{{WRAPPER}} .ms_lms_instructors_grid__item_socials_link',
			)
		);
		$this->end_controls_tab();
		$this->start_controls_tab(
			'socials_links_hover_tab',
			array(
				'label' => esc_html__( 'Hover', 'masterstudy-lms-learning-management-system' ),
			)
		);
		$this->add_control(
			'socials_links_hover_color',
			array(
				'label'     => esc_html__( 'Color', 'masterstudy-lms-learning-management-system' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__item_socials_link:hover i' => 'color: {{VALUE}}',
				),
			)
		);
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'socials_links_hover_background',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .ms_lms_instructors_grid__item_socials_link:hover',
			)
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'socials_links_hover_border',
				'selector' => '{{WRAPPER}} .ms_lms_instructors_grid__item_socials_link:hover',
			)
		);
		$this->add_control(
			'socials_links_hover_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__item_socials_link:hover' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'socials_links_hover_box_shadow',
				'selector' => '{{WRAPPER}} .ms_lms_instructors_grid__item_socials_link:hover',
			)
		);
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->add_control(
			'socials_links_divider',
			array(
				'type' => Controls_Manager::DIVIDER,
			)
		);
		$this->add_responsive_control(
			'socials_links_margin',
			array(
				'label'      => esc_html__( 'Margin', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__item_socials' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->end_controls_section();
	}

	protected function register_style_controls_view_all_button() {

		$this->start_controls_section(
			'section_view_all_button',
			array(
				'label'     => esc_html__( '"View all" Button', 'masterstudy-lms-learning-management-system' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'show_view_all' => 'yes',
				),
			)
		);
		$this->start_controls_tabs(
			'view_all_button_tab'
		);
		$this->start_controls_tab(
			'view_all_button_normal_tab',
			array(
				'label' => esc_html__( 'Normal', 'masterstudy-lms-learning-management-system' ),
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'view_all_button_typography',
				'selector' => '{{WRAPPER}} .ms_lms_instructors_grid__header_view_all',
			)
		);
		$this->add_control(
			'view_all_button_color',
			array(
				'label'     => esc_html__( 'Color', 'masterstudy-lms-learning-management-system' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__header_view_all' => 'color: {{VALUE}}',
				),
			)
		);
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'view_all_button_background',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .ms_lms_instructors_grid__header_view_all',
			)
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'view_all_button_border',
				'selector' => '{{WRAPPER}} .ms_lms_instructors_grid__header_view_all',
			)
		);
		$this->add_control(
			'view_all_button_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__header_view_all' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'view_all_button_box_shadow',
				'selector' => '{{WRAPPER}} .ms_lms_instructors_grid__header_view_all',
			)
		);
		$this->end_controls_tab();
		$this->start_controls_tab(
			'view_all_button_hover_tab',
			array(
				'label' => esc_html__( 'Hover', 'masterstudy-lms-learning-management-system' ),
			)
		);
		$this->add_control(
			'view_all_button_color_hover',
			array(
				'label'     => esc_html__( 'Color', 'masterstudy-lms-learning-management-system' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__header_view_all:hover' => 'color: {{VALUE}}',
				),
			)
		);
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'view_all_button_background_hover',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .ms_lms_instructors_grid__header_view_all:hover',
			)
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'view_all_button_border_hover',
				'selector' => '{{WRAPPER}} .ms_lms_instructors_grid__header_view_all:hover',
			)
		);
		$this->add_control(
			'view_all_button_border_radius_hover',
			array(
				'label'      => esc_html__( 'Border Radius', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__header_view_all:hover' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'view_all_button_box_shadow_hover',
				'selector' => '{{WRAPPER}} .ms_lms_instructors_grid__header_view_all:hover',
			)
		);
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->add_responsive_control(
			'view_all_button_padding',
			array(
				'label'      => esc_html__( 'Padding', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__header_view_all' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->add_responsive_control(
			'view_all_button_margin',
			array(
				'label'      => esc_html__( 'Margin', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__header_view_all' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->add_responsive_control(
			'view_all_button_align',
			array(
				'label'      => esc_html__( 'Alignment', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::CHOOSE,
				'options'    => array(
					'flex-start' => array(
						'title' => esc_html__( 'Left', 'masterstudy-lms-learning-management-system' ),
						'icon'  => 'eicon-h-align-left',
					),
					'center'     => array(
						'title' => esc_html__( 'Center', 'masterstudy-lms-learning-management-system' ),
						'icon'  => 'eicon-h-align-center',
					),
					'flex-end'   => array(
						'title' => esc_html__( 'Right', 'masterstudy-lms-learning-management-system' ),
						'icon'  => 'eicon-h-align-right',
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .ms_lms_instructors_grid__header_view_all' => 'align-self: {{VALUE}};',
				),
				'conditions' => array(
					'terms' => array(
						array(
							'name'     => 'widget_header_presets',
							'operator' => '!==',
							'value'    => 'style_2',
						),
					),
				),
			)
		);
		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();

		$sort_args = array();

		if ( 'date' === $settings['instructors_sort_by'] ) {
			$sort_args = array(
				'orderby' => 'registered',
				'order'   => 'DESC',
			);
		}

		if ( ! empty( $settings['instructors_to_show'] ) && 'number' === $settings['instructors_to_show_choice'] ) {
			$sort_args['number'] = intval( $settings['instructors_to_show'] );
		}

		$instructors = \STM_LMS_Instructor::get_instructors( $sort_args );
		foreach ( $instructors as &$instructor ) {
			$course_quantity             = \STM_LMS_Instructor::get_course_quantity( $instructor->ID );
			$instructor->course_quantity = $course_quantity;
			$instructor->sum_rating      = intval( get_user_meta( $instructor->ID, 'sum_rating', true ) );
		}
		if ( 'course_quantity' === $settings['instructors_sort_by'] ) {
			usort(
				$instructors,
				function( $a, $b ) {
					return $b->course_quantity - $a->course_quantity;
				}
			);
		} elseif ( 'sum_rating' === $settings['instructors_sort_by'] ) {
			usort(
				$instructors,
				function( $a, $b ) {
					return $b->sum_rating - $a->sum_rating;
				}
			);
		}
		$atts = array(
			'instructors'                     => $instructors,
			'widget_header_presets'           => $settings['widget_header_presets'],
			'instructor_card_presets'         => $settings['instructor_card_presets'],
			'socials_presets'                 => $settings['socials_presets'],
			'widget_title'                    => $settings['widget_title'],
			'show_view_all'                   => $settings['show_view_all'],
			'widget_description'              => $settings['widget_description'],
			'show_avatars'                    => $settings['show_avatars'],
			'show_instructor_position'        => $settings['show_instructor_position'],
			'show_instructor_course_quantity' => $settings['show_instructor_course_quantity'],
			'show_reviews'                    => $settings['show_reviews'] ?? false,
			'show_reviews_count'              => $settings['show_reviews_count'] ?? false,
			'show_socials'                    => $settings['show_socials'],
		);
		\STM_LMS_Templates::show_lms_template( 'elementor-widgets/instructors-grid/main', $atts );
	}

	protected function content_template() {
	}
}
