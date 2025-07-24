<?php
namespace StmLmsElementor\Widgets\Course;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Border;
use Elementor\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class MsLmsCourseBuyButton extends Widget_Base {

	public function get_name() {
		return 'ms_lms_course_buy_button';
	}

	public function get_title() {
		return esc_html__( 'Buy Button', 'masterstudy-lms-learning-management-system' );
	}

	public function get_icon() {
		return 'stmlms-course-buy lms-course-icon';
	}

	public function get_categories() {
		return array( 'stm_lms_course' );
	}

	public function get_style_depends() {
		return array(
			'masterstudy-single-course-components',
		);
	}

	public function get_script_depends() {
		return array(
			'masterstudy-course-buy-button-editor',
		);
	}

	protected function register_controls() {
		$courses = \STM_LMS_Courses::get_all_courses_for_options();
		$context = masterstudy_lms_get_elementor_page_context( get_the_ID() );

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
				'frontend_available' => true,
				'default'            => ! empty( $context['course_for_page'] ) && isset( $courses[ $context['course_for_page'] ] )
					? $context['course_for_page']
					: ( ! empty( $courses ) ? key( $courses ) : '' ),
			)
		);
		if ( $context['is_course_template'] ) {
			$this->add_control(
				'course_note',
				array(
					'type' => \Elementor\Controls_Manager::RAW_HTML,
					'raw'  => \STM_LMS_Templates::load_lms_template( 'elementor-widgets/course-note' ),
				)
			);
		}
		$this->end_controls_section();
		$this->start_controls_section(
			'buy_button_section',
			array(
				'label' => esc_html__( 'Buy Button', 'masterstudy-lms-learning-management-system' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'buy_button_typography',
				'selector' => '{{WRAPPER}} .masterstudy-buy-button__link span.masterstudy-buy-button__title, {{WRAPPER}} .masterstudy-button-affiliate__link span.masterstudy-button-affiliate__title',
			)
		);
		$this->add_control(
			'buy_button_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .masterstudy-buy-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .masterstudy-button-affiliate' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->start_controls_tabs(
			'buy_button_tab'
		);
		$this->start_controls_tab(
			'buy_button_normal_tab',
			array(
				'label' => esc_html__( 'Normal', 'masterstudy-lms-learning-management-system' ),
			)
		);
		$this->add_control(
			'buy_button_color',
			array(
				'label'     => esc_html__( 'Color', 'masterstudy-lms-learning-management-system' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .masterstudy-buy-button__link .masterstudy-buy-button__title' => 'color: {{VALUE}}',
					'{{WRAPPER}} .masterstudy-button-affiliate__link .masterstudy-button-affiliate__title' => 'color: {{VALUE}}',
				),
			)
		);
		$this->add_control(
			'buy_button_toggler_color',
			array(
				'label'     => esc_html__( 'Toggler Color', 'masterstudy-lms-learning-management-system' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .masterstudy-buy-button_dropdown::after' => 'border-color: {{VALUE}} transparent transparent',
					'{{WRAPPER}} .masterstudy-button-affiliate__link .masterstudy-button-affiliate__title' => 'border-color: {{VALUE}} transparent transparent',
				),
			)
		);
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'buy_button_normal_background',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .masterstudy-buy-button, {{WRAPPER}} .masterstudy-button-affiliate',
			)
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'buy_button_normal_border',
				'selector' => '{{WRAPPER}} .masterstudy-buy-button, {{WRAPPER}} .masterstudy-button-affiliate',
			)
		);
		$this->end_controls_tab();
		$this->start_controls_tab(
			'buy_button_hover_tab',
			array(
				'label' => esc_html__( 'Hover', 'masterstudy-lms-learning-management-system' ),
			)
		);
		$this->add_control(
			'buy_button_hover_color',
			array(
				'label'     => esc_html__( 'Color', 'masterstudy-lms-learning-management-system' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .masterstudy-buy-button__link:hover .masterstudy-buy-button__title, {{WRAPPER}} .masterstudy-buy-button:hover .masterstudy-buy-button_dropdown::after' => 'color: {{VALUE}}',
					'{{WRAPPER}} .masterstudy-button-affiliate__link:hover .masterstudy-button-affiliate__title' => 'color: {{VALUE}}',
				),
			)
		);
		$this->add_control(
			'buy_button_toggler_hover_color',
			array(
				'label'     => esc_html__( 'Toggler Color', 'masterstudy-lms-learning-management-system' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .masterstudy-buy-button_dropdown:hover:after' => 'border-color: {{VALUE}} transparent transparent',
				),
			)
		);
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'buy_button_background_hover',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .masterstudy-buy-button:hover, {{WRAPPER}} .masterstudy-button-affiliate:hover',
			)
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'buy_button_border_hover',
				'selector' => '{{WRAPPER}} .masterstudy-buy-button:hover, {{WRAPPER}} .masterstudy-button-affiliate:hover',
			)
		);
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->end_controls_section();
		$this->start_controls_section(
			'price_section',
			array(
				'label' => esc_html__( 'Regular Price', 'masterstudy-lms-learning-management-system' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'price_typography',
				'selector' => '{{WRAPPER}} .masterstudy-buy-button__price_regular, {{WRAPPER}} .masterstudy-button-affiliate__price_regular',
			)
		);
		$this->add_control(
			'price_color',
			array(
				'label'     => esc_html__( 'Color', 'masterstudy-lms-learning-management-system' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .masterstudy-buy-button__price_regular' => 'color: {{VALUE}}',
					'{{WRAPPER}} .masterstudy-button-affiliate__price_regular' => 'color: {{VALUE}}',
				),
			)
		);
		$this->add_responsive_control(
			'price_margin',
			array(
				'label'      => esc_html__( 'Margin', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .masterstudy-buy-button__price_regular' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .masterstudy-button-affiliate__price_regular' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->end_controls_section();
		$this->start_controls_section(
			'sale_section',
			array(
				'label' => esc_html__( 'Sale Price', 'masterstudy-lms-learning-management-system' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'sale_typography',
				'selector' => '{{WRAPPER}} .masterstudy-buy-button__price_sale, {{WRAPPER}} .masterstudy-button-affiliate__price_sale',
			)
		);
		$this->add_control(
			'sale_color',
			array(
				'label'     => esc_html__( 'Color', 'masterstudy-lms-learning-management-system' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .masterstudy-buy-button__price_sale' => 'color: {{VALUE}}',
					'{{WRAPPER}} .masterstudy-button-affiliate__price_sale' => 'color: {{VALUE}}',
				),
			)
		);
		$this->add_responsive_control(
			'sale_margin',
			array(
				'label'      => esc_html__( 'Margin', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .masterstudy-buy-button__price_sale' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .masterstudy-button-affiliate__price_sale' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->end_controls_section();
		if ( is_ms_lms_addon_enabled( 'enterprise_courses' ) ) {
			$this->start_controls_section(
				'for_business_section',
				array(
					'label' => esc_html__( 'For Business Separator', 'masterstudy-lms-learning-management-system' ),
					'tab'   => Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_group_control(
				Group_Control_Typography::get_type(),
				array(
					'name'     => 'for_business_typography',
					'selector' => '{{WRAPPER}} .masterstudy-button-enterprise__title span, {{WRAPPER}}',
				)
			);
			$this->add_control(
				'for_business_color',
				array(
					'label'     => esc_html__( 'Color', 'masterstudy-lms-learning-management-system' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array(
						'{{WRAPPER}} .masterstudy-button-enterprise__title span' => 'color: {{VALUE}}',
					),
				)
			);
			$this->add_control(
				'for_business_lines_color',
				array(
					'label'     => esc_html__( 'Lines Color', 'masterstudy-lms-learning-management-system' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array(
						'{{WRAPPER}} .masterstudy-button-enterprise__title::before, {{WRAPPER}} .masterstudy-button-enterprise__title::after' => 'background-color: {{VALUE}}',
					),
				)
			);
			$this->end_controls_section();
		}
		if ( is_ms_lms_addon_enabled( 'enterprise_courses' ) ) {
			$this->start_controls_section(
				'enterprise_section',
				array(
					'label' => esc_html__( 'Buy For Group Button', 'masterstudy-lms-learning-management-system' ),
					'tab'   => Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_group_control(
				Group_Control_Typography::get_type(),
				array(
					'name'     => 'enterprise_typography',
					'selector' => '{{WRAPPER}} .masterstudy-button-enterprise__button',
				)
			);
			$this->add_control(
				'enterprise_border_radius',
				array(
					'label'      => esc_html__( 'Border Radius', 'masterstudy-lms-learning-management-system' ),
					'type'       => Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%' ),
					'selectors'  => array(
						'{{WRAPPER}} .masterstudy-button-enterprise__button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
				)
			);
			$this->start_controls_tabs(
				'enterprise_tab'
			);
			$this->start_controls_tab(
				'enterprise_normal_tab',
				array(
					'label' => esc_html__( 'Normal', 'masterstudy-lms-learning-management-system' ),
				)
			);
			$this->add_control(
				'enterprise_color',
				array(
					'label'     => esc_html__( 'Color', 'masterstudy-lms-learning-management-system' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array(
						'{{WRAPPER}} .masterstudy-button-enterprise__button' => 'color: {{VALUE}}',
					),
				)
			);
			$this->add_group_control(
				Group_Control_Background::get_type(),
				array(
					'name'     => 'enterprise_normal_background',
					'types'    => array( 'classic', 'gradient' ),
					'selector' => '{{WRAPPER}} .masterstudy-button-enterprise__button',
				)
			);
			$this->add_group_control(
				Group_Control_Border::get_type(),
				array(
					'name'     => 'enterprise_normal_border',
					'selector' => '{{WRAPPER}} .masterstudy-button-enterprise__button',
				)
			);
			$this->end_controls_tab();
			$this->start_controls_tab(
				'enterprise_hover_tab',
				array(
					'label' => esc_html__( 'Hover', 'masterstudy-lms-learning-management-system' ),
				)
			);
			$this->add_control(
				'enterprise_hover_color',
				array(
					'label'     => esc_html__( 'Color', 'masterstudy-lms-learning-management-system' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array(
						'{{WRAPPER}} .masterstudy-button-enterprise__button:hover' => 'color: {{VALUE}}',
					),
				)
			);
			$this->add_group_control(
				Group_Control_Background::get_type(),
				array(
					'name'     => 'enterprise_background_hover',
					'types'    => array( 'classic', 'gradient' ),
					'selector' => '{{WRAPPER}} .masterstudy-button-enterprise__button:hover',
				)
			);
			$this->add_group_control(
				Group_Control_Border::get_type(),
				array(
					'name'     => 'enterprise_border_hover',
					'selector' => '{{WRAPPER}} .masterstudy-button-enterprise__button:hover',
				)
			);
			$this->end_controls_tab();
			$this->end_controls_tabs();
			$this->end_controls_section();
		}
		$this->start_controls_section(
			'dropdown_section',
			array(
				'label' => esc_html__( 'Plans dropdown', 'masterstudy-lms-learning-management-system' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'dropdown_title_typography',
				'selector' => '{{WRAPPER}} .masterstudy-buy-button_plans-dropdown__title',
			)
		);
		$this->add_control(
			'dropdown_color',
			array(
				'label'     => esc_html__( 'Title Color', 'masterstudy-lms-learning-management-system' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .masterstudy-buy-button_plans-dropdown__title' => 'color: {{VALUE}}',
				),
			)
		);
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'dropdown_background',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .masterstudy-buy-button_plans-dropdown',
			)
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'dropdown_border',
				'selector' => '{{WRAPPER}} .masterstudy-buy-button_plans-dropdown',
			)
		);
		$this->add_control(
			'dropdown_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .masterstudy-buy-button_plans-dropdown' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->add_responsive_control(
			'dropdown_padding',
			array(
				'label'      => esc_html__( 'Padding', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .masterstudy-buy-button_plans-dropdown' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'dropdown_shadow',
				'selector' => '{{WRAPPER}} .masterstudy-buy-button_plans-dropdown',
			)
		);
		$this->end_controls_section();
		$this->start_controls_section(
			'plan_button_section',
			array(
				'label' => esc_html__( 'Plan Buttons', 'masterstudy-lms-learning-management-system' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'plan_button_typography',
				'selector' => '{{WRAPPER}} .masterstudy-buy-button_plans-dropdown a, {{WRAPPER}} .masterstudy-buy-button_plans-dropdown a span',
			)
		);
		$this->add_control(
			'plan_button_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'masterstudy-lms-learning-management-system' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .masterstudy-buy-button_plans-dropdown a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->start_controls_tabs(
			'plan_button_tab'
		);
		$this->start_controls_tab(
			'plan_button_normal_tab',
			array(
				'label' => esc_html__( 'Normal', 'masterstudy-lms-learning-management-system' ),
			)
		);
		$this->add_control(
			'plan_button_color',
			array(
				'label'     => esc_html__( 'Color', 'masterstudy-lms-learning-management-system' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .masterstudy-buy-button_plans-dropdown a span' => 'color: {{VALUE}}',
				),
			)
		);
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'plan_button_normal_background',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .masterstudy-buy-button_plans-dropdown a',
			)
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'plan_button_normal_border',
				'selector' => '{{WRAPPER}} .masterstudy-buy-button_plans-dropdown a',
			)
		);
		$this->end_controls_tab();
		$this->start_controls_tab(
			'plan_button_hover_tab',
			array(
				'label' => esc_html__( 'Hover', 'masterstudy-lms-learning-management-system' ),
			)
		);
		$this->add_control(
			'plan_button_hover_color',
			array(
				'label'     => esc_html__( 'Color', 'masterstudy-lms-learning-management-system' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .masterstudy-buy-button_plans-dropdown a:hover span' => 'color: {{VALUE}}',
				),
			)
		);
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'plan_button_background_hover',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .masterstudy-buy-button_plans-dropdown a:hover',
			)
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'plan_button_border_hover',
				'selector' => '{{WRAPPER}} .masterstudy-buy-button_plans-dropdown a:hover',
			)
		);
		$this->end_controls_tab();
		$this->end_controls_tabs();
		if ( is_ms_lms_addon_enabled( 'point_system' ) ) {
			$this->add_responsive_control(
				'plan_icon_width',
				array(
					'label'      => esc_html__( 'Point Icon Width', 'masterstudy-lms-learning-management-system' ),
					'type'       => Controls_Manager::SLIDER,
					'size_units' => array( '%', 'px' ),
					'selectors'  => array(
						'{{WRAPPER}} .masterstudy-points img' => 'width: {{SIZE}}{{UNIT}};',
					),
				)
			);
			$this->add_responsive_control(
				'plan_icon_height',
				array(
					'label'      => esc_html__( 'Point Icon Height', 'masterstudy-lms-learning-management-system' ),
					'type'       => Controls_Manager::SLIDER,
					'size_units' => array( '%', 'px' ),
					'selectors'  => array(
						'{{WRAPPER}} .masterstudy-points img' => 'height: {{SIZE}}{{UNIT}};',
					),
				)
			);
			$this->add_responsive_control(
				'plan_icon_margin',
				array(
					'label'      => esc_html__( 'Point Icon Margin', 'masterstudy-lms-learning-management-system' ),
					'type'       => Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%' ),
					'selectors'  => array(
						'{{WRAPPER}} .masterstudy-points img' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
				)
			);
		}
		$this->end_controls_section();
		if ( is_ms_lms_addon_enabled( 'prerequisite' ) ) {
			$this->start_controls_section(
				'prerequisites_section',
				array(
					'label' => esc_html__( 'Prerequisites Button', 'masterstudy-lms-learning-management-system' ),
					'tab'   => Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_group_control(
				Group_Control_Typography::get_type(),
				array(
					'name'     => 'prerequisites_typography',
					'selector' => '{{WRAPPER}} a.masterstudy-prerequisites__button',
				)
			);
			$this->start_controls_tabs(
				'prerequisites_tab'
			);
			$this->start_controls_tab(
				'prerequisites_normal_tab',
				array(
					'label' => esc_html__( 'Normal', 'masterstudy-lms-learning-management-system' ),
				)
			);
			$this->add_control(
				'prerequisites_color',
				array(
					'label'     => esc_html__( 'Color', 'masterstudy-lms-learning-management-system' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array(
						'{{WRAPPER}} a.masterstudy-prerequisites__button span, a.masterstudy-prerequisites__button::after' => 'color: {{VALUE}}',
					),
				)
			);
			$this->add_control(
				'prerequisites_toggler_color',
				array(
					'label'     => esc_html__( 'Toggler Color', 'masterstudy-lms-learning-management-system' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array(
						'{{WRAPPER}} .masterstudy-prerequisites__button::after' => 'border-color: {{VALUE}} transparent transparent',
					),
				)
			);
			$this->add_group_control(
				Group_Control_Background::get_type(),
				array(
					'name'     => 'prerequisites_normal_background',
					'types'    => array( 'classic', 'gradient' ),
					'selector' => '{{WRAPPER}} a.masterstudy-prerequisites__button',
				)
			);
			$this->add_group_control(
				Group_Control_Border::get_type(),
				array(
					'name'     => 'prerequisites_normal_border',
					'selector' => '{{WRAPPER}} a.masterstudy-prerequisites__button',
				)
			);
			$this->end_controls_tab();
			$this->start_controls_tab(
				'prerequisites_hover_tab',
				array(
					'label' => esc_html__( 'Hover', 'masterstudy-lms-learning-management-system' ),
				)
			);
			$this->add_control(
				'prerequisites_hover_color',
				array(
					'label'     => esc_html__( 'Color', 'masterstudy-lms-learning-management-system' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array(
						'{{WRAPPER}} a.masterstudy-prerequisites__button:hover span, a.masterstudy-prerequisites__button:hover:after' => 'color: {{VALUE}}',
					),
				)
			);
			$this->add_control(
				'prerequisites_toggler_hover_color',
				array(
					'label'     => esc_html__( 'Toggler Color', 'masterstudy-lms-learning-management-system' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array(
						'{{WRAPPER}} .masterstudy-prerequisites__button:hover:after' => 'border-color: {{VALUE}} transparent transparent',
					),
				)
			);
			$this->add_group_control(
				Group_Control_Background::get_type(),
				array(
					'name'     => 'prerequisites_background_hover',
					'types'    => array( 'classic', 'gradient' ),
					'selector' => '{{WRAPPER}} a.masterstudy-prerequisites__button:hover',
				)
			);
			$this->add_group_control(
				Group_Control_Border::get_type(),
				array(
					'name'     => 'prerequisites_border_hover',
					'selector' => '{{WRAPPER}} a.masterstudy-prerequisites__button:hover',
				)
			);
			$this->end_controls_tab();
			$this->end_controls_tabs();
			$this->end_controls_section();
			$this->start_controls_section(
				'dropdown_prerequisites_section',
				array(
					'label' => esc_html__( 'Prerequisites Dropdown', 'masterstudy-lms-learning-management-system' ),
					'tab'   => Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_group_control(
				Group_Control_Background::get_type(),
				array(
					'name'     => 'dropdown_prerequisites_background',
					'types'    => array( 'classic', 'gradient' ),
					'selector' => '{{WRAPPER}} .masterstudy-prerequisites-list',
				)
			);
			$this->add_group_control(
				Group_Control_Border::get_type(),
				array(
					'name'     => 'dropdown_prerequisites_border',
					'selector' => '{{WRAPPER}} .masterstudy-prerequisites-list',
				)
			);
			$this->add_control(
				'dropdown_prerequisites_border_radius',
				array(
					'label'      => esc_html__( 'Border Radius', 'masterstudy-lms-learning-management-system' ),
					'type'       => Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%' ),
					'selectors'  => array(
						'{{WRAPPER}} .masterstudy-prerequisites-list' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
				)
			);
			$this->add_group_control(
				Group_Control_Box_Shadow::get_type(),
				array(
					'name'     => 'dropdown_prerequisites_shadow',
					'selector' => '{{WRAPPER}} .masterstudy-prerequisites-list',
				)
			);
			$this->end_controls_section();
			$this->start_controls_section(
				'progress_section',
				array(
					'label' => esc_html__( 'Prerequisites Progress bar', 'masterstudy-lms-learning-management-system' ),
					'tab'   => Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_control(
				'progress_slider_color',
				array(
					'label'     => esc_html__( 'Filled Color', 'masterstudy-lms-learning-management-system' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array(
						'{{WRAPPER}} .masterstudy-prerequisites-list__progress-percent-striped' => 'background-color: {{VALUE}};',
					),
				)
			);
			$this->add_control(
				'progress_bar_color',
				array(
					'label'     => esc_html__( 'Empty Color', 'masterstudy-lms-learning-management-system' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array(
						'{{WRAPPER}} .masterstudy-prerequisites-list__progress::before, {{WRAPPER}} .masterstudy-prerequisites-list__progress-percent' => 'background-color: {{VALUE}};',
					),
				)
			);
			$this->end_controls_section();
			$this->start_controls_section(
				'prerequisites_title_section',
				array(
					'label' => esc_html__( 'Prerequisites Course Title', 'masterstudy-lms-learning-management-system' ),
					'tab'   => Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_group_control(
				Group_Control_Typography::get_type(),
				array(
					'name'     => 'prerequisites_title_typography',
					'selector' => '{{WRAPPER}} a.masterstudy-prerequisites-list__title',
				)
			);
			$this->add_control(
				'prerequisites_title_color',
				array(
					'label'     => esc_html__( 'Color', 'masterstudy-lms-learning-management-system' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array(
						'{{WRAPPER}} a.masterstudy-prerequisites-list__title' => 'color: {{VALUE}}',
					),
				)
			);
			$this->end_controls_section();
			$this->start_controls_section(
				'prerequisites_price_section',
				array(
					'label' => esc_html__( 'Prerequisites Course Price', 'masterstudy-lms-learning-management-system' ),
					'tab'   => Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_group_control(
				Group_Control_Typography::get_type(),
				array(
					'name'     => 'prerequisites_price_typography',
					'selector' => '{{WRAPPER}} .masterstudy-prerequisites-list__progress span, {{WRAPPER}} .masterstudy-prerequisites-list__progress label',
				)
			);
			$this->add_control(
				'prerequisites_price_color',
				array(
					'label'     => esc_html__( 'Color', 'masterstudy-lms-learning-management-system' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array(
						'{{WRAPPER}} .masterstudy-prerequisites-list__progress span, {{WRAPPER}} .masterstudy-prerequisites-list__progress label, {{WRAPPER}} .masterstudy-prerequisites-list__enrolled' => 'color: {{VALUE}}',
					),
				)
			);
			$this->end_controls_section();
			$this->start_controls_section(
				'prerequisites_info_section',
				array(
					'label' => esc_html__( 'Prerequisites Info', 'masterstudy-lms-learning-management-system' ),
					'tab'   => Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_group_control(
				Group_Control_Typography::get_type(),
				array(
					'name'     => 'prerequisites_info_typography',
					'selector' => '{{WRAPPER}} .masterstudy-prerequisites-list__explanation-title, {{WRAPPER}} .masterstudy-prerequisites-list__explanation-info',
				)
			);
			$this->add_control(
				'prerequisites_info_color',
				array(
					'label'     => esc_html__( 'Color', 'masterstudy-lms-learning-management-system' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array(
						'{{WRAPPER}} .masterstudy-prerequisites-list__explanation-title, {{WRAPPER}} .masterstudy-prerequisites-list__explanation-info' => 'color: {{VALUE}}',
					),
				)
			);
			$this->add_control(
				'prerequisites_toggler_info_color',
				array(
					'label'     => esc_html__( 'Toggler Color', 'masterstudy-lms-learning-management-system' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array(
						'{{WRAPPER}} .masterstudy-prerequisites-list__explanation-title:after' => 'border-color: {{VALUE}} transparent transparent',
					),
				)
			);
			$this->end_controls_section();
		}
		if ( is_ms_lms_addon_enabled( 'coming_soon' ) ) {
			$this->start_controls_section(
				'coming_soon_section',
				array(
					'label' => esc_html__( 'Coming Soon Button', 'masterstudy-lms-learning-management-system' ),
					'tab'   => Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_group_control(
				Group_Control_Typography::get_type(),
				array(
					'name'     => 'coming_soon_typography',
					'selector' => '{{WRAPPER}} .masterstudy-single-course-coming-button',
				)
			);
			$this->add_control(
				'coming_soon_color',
				array(
					'label'     => esc_html__( 'Color', 'masterstudy-lms-learning-management-system' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array(
						'{{WRAPPER}} .masterstudy-single-course-coming-button' => 'color: {{VALUE}}',
					),
				)
			);
			$this->add_group_control(
				Group_Control_Background::get_type(),
				array(
					'name'     => 'coming_soon_background',
					'types'    => array( 'classic', 'gradient' ),
					'selector' => '{{WRAPPER}} .masterstudy-single-course-coming-button',
				)
			);
			$this->add_group_control(
				Group_Control_Border::get_type(),
				array(
					'name'     => 'coming_soon_border',
					'selector' => '{{WRAPPER}} .masterstudy-single-course-coming-button',
				)
			);
			$this->add_control(
				'coming_soon_border_radius',
				array(
					'label'      => esc_html__( 'Border Radius', 'masterstudy-lms-learning-management-system' ),
					'type'       => Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%' ),
					'selectors'  => array(
						'{{WRAPPER}} .masterstudy-single-course-coming-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
				)
			);
			$this->end_controls_section();
		}
	}

	protected function render() {
		global $masterstudy_single_page_course_id;

		$settings    = $this->get_settings_for_display();
		$course_id   = ! empty( $masterstudy_single_page_course_id ) ? $masterstudy_single_page_course_id : $settings['course'] ?? null;
		$course_data = masterstudy_get_elementor_course_data( intval( $course_id ) );
		$editor      = Plugin::$instance->editor->is_edit_mode();
		$user_id     = $editor ? null : get_current_user_id();

		if ( empty( $course_data ) || ! isset( $course_data['course'] ) ) {
			return;
		}

		wp_enqueue_script( 'masterstudy-buy-button', STM_LMS_URL . 'assets/js/components/buy-button.js', array( 'jquery' ), MS_LMS_VERSION, true );
		wp_localize_script(
			'masterstudy-buy-button',
			'masterstudy_buy_button_data',
			array(
				'ajax_url'        => admin_url( 'admin-ajax.php' ),
				'get_nonce'       => wp_create_nonce( 'stm_lms_add_to_cart' ),
				'get_guest_nonce' => wp_create_nonce( 'stm_lms_add_to_cart_guest' ),
				'item_id'         => $course_id,
			)
		);

		if ( ! $course_data['is_coming_soon'] || $course_data['course']->coming_soon_preorder ) {
			$template_args = array(
				'post_id'              => $course_data['course']->id,
				'item_id'              => '',
				'user_id'              => $user_id,
				'dark_mode'            => false,
				'prerequisite_preview' => false,
				'hide_group_course'    => false,
			);

			if ( $editor ) {
				$template_args['has_access'] = false;
			}

			\STM_LMS_Templates::show_lms_template(
				'components/buy-button/buy-button',
				$template_args
			);
		}

		if ( $course_data['is_coming_soon'] && $course_data['course']->coming_soon_price && ! $course_data['course']->coming_soon_preorder ) {
			\STM_LMS_Templates::show_lms_template( 'components/course/coming-button' );
		}
	}
}
