<?php
/*
 * Elementor Restaurant & Cafe Addon for Elementor Process Widget
 * Author & Copyright: NicheAddon
*/

namespace Elementor;

if (!isset(get_option( 'rcafe_bw_settings' )['nbeds_process'])) { // enable & disable

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Restaurant_Elementor_Addon_Process extends Widget_Base{

	/**
	 * Retrieve the widget name.
	*/
	public function get_name(){
		return 'narestaurant_basic_process';
	}

	/**
	 * Retrieve the widget title.
	*/
	public function get_title(){
		return esc_html__( 'Process', 'restaurant-cafe-addon-for-elementor' );
	}

	/**
	 * Retrieve the widget icon.
	*/
	public function get_icon() {
		return 'eicon-anchor';
	}

	/**
	 * Retrieve the list of categories the widget belongs to.
	*/
	public function get_categories() {
		return ['narestaurant-basic-category'];
	}

	/**
	 * Register Restaurant & Cafe Addon for Elementor Process widget controls.
	 * Adds different input fields to allow the user to change and customize the widget settings.
	*/
	protected function _register_controls(){

		$this->start_controls_section(
			'section_process',
			[
				'label' => __( 'Process Item', 'restaurant-cafe-addon-for-elementor' ),
			]
		);
		$this->add_control(
			'process_style',
			[
				'label' => __( 'Process Style', 'restaurant-cafe-addon-for-elementor' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'one' => esc_html__( 'Style One', 'restaurant-cafe-addon-for-elementor' ),
					'two' => esc_html__( 'Style Two', 'restaurant-cafe-addon-for-elementor' ),
					'three' => esc_html__( 'Style Three', 'restaurant-cafe-addon-for-elementor' ),
				],
				'default' => 'one',
			]
		);
		$repeater = new Repeater();
		$repeater->add_control(
			'upload_type',
			[
				'label' => __( 'Icon Type', 'restaurant-cafe-addon-for-elementor' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'image' => esc_html__( 'Image', 'restaurant-cafe-addon-for-elementor' ),
					'icon' => esc_html__( 'Icon', 'restaurant-cafe-addon-for-elementor' ),
				],
				'default' => 'image',
			]
		);
		$repeater->add_control(
			'process_image',
			[
				'label' => esc_html__( 'Upload Icon', 'restaurant-cafe-addon-for-elementor' ),
				'type' => Controls_Manager::MEDIA,
				'condition' => [
					'upload_type' => 'image',
				],
				'frontend_available' => true,
				'default' => [
					'url' => Utils::get_placeholder_image_src(),
				],
				'description' => esc_html__( 'Set your icon image.', 'restaurant-cafe-addon-for-elementor'),
			]
		);
		$repeater->add_control(
			'process_icon',
			[
				'label' => esc_html__( 'Select Icon', 'restaurant-cafe-addon-for-elementor' ),
				'type' => Controls_Manager::ICON,
				'options' => NAREP_Controls_Helper_Output::get_include_icons(),
				'frontend_available' => true,
				'default' => 'fa fa-cog',
				'condition' => [
					'upload_type' => 'icon',
				],
			]
		);
		$repeater->add_control(
			'step_title',
			[
				'label' => esc_html__( 'Step Title', 'restaurant-cafe-addon-for-elementor' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'Step 01', 'restaurant-cafe-addon-for-elementor' ),
				'placeholder' => esc_html__( 'Type title text here', 'restaurant-cafe-addon-for-elementor' ),
				'label_block' => true,
			]
		);
		$repeater->add_control(
			'process_title',
			[
				'label' => esc_html__( 'Title', 'restaurant-cafe-addon-for-elementor' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'Title', 'restaurant-cafe-addon-for-elementor' ),
				'placeholder' => esc_html__( 'Type title text here', 'restaurant-cafe-addon-for-elementor' ),
				'label_block' => true,
			]
		);
		$repeater->add_control(
			'title_link',
			[
				'label' => esc_html__( 'Title Link', 'restaurant-elementor-addon' ),
				'type' => Controls_Manager::URL,
				'placeholder' => 'https://your-link.com',
				'default' => [
					'url' => '',
				],
				'label_block' => true,
			]
		);
		$repeater->add_control(
			'process_content',
			[
				'label' => esc_html__( 'Content', 'restaurant-cafe-addon-for-elementor' ),
				'type' => Controls_Manager::TEXTAREA,
				'placeholder' => esc_html__( 'Type title text here', 'restaurant-cafe-addon-for-elementor' ),
				'label_block' => true,
			]
		);
		$this->add_control(
			'process_groups',
			[
				'label' => esc_html__( 'Process Items', 'restaurant-cafe-addon-for-elementor' ),
				'type' => Controls_Manager::REPEATER,
				'default' => [
					[
						'process_title' => esc_html__( 'Title', 'restaurant-cafe-addon-for-elementor' ),
					],

				],
				'fields' => $repeater->get_controls(),
				'title_field' => '{{{ process_title }}}',
			]
		);
		$this->end_controls_section();// end: Section

		// Section
			$this->start_controls_section(
				'section_style',
				[
					'label' => esc_html__( 'Section', 'restaurant-cafe-addon-for-elementor' ),
					'tab' => Controls_Manager::TAB_STYLE,
					'condition' => [
						'process_style' => 'one',
					],
				]
			);
			$this->add_responsive_control(
				'process_section_margin',
				[
					'label' => __( 'Margin', 'restaurant-cafe-addon-for-elementor' ),
					'type' => Controls_Manager::DIMENSIONS,
					'size_units' => [ 'px' ],
					'selectors' => [
						'{{WRAPPER}} .narep-process-item' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					],
				]
			);
			$this->add_responsive_control(
				'process_section_padding',
				[
					'label' => __( 'Padding', 'restaurant-cafe-addon-for-elementor' ),
					'type' => Controls_Manager::DIMENSIONS,
					'size_units' => [ 'px' ],
					'selectors' => [
						'{{WRAPPER}} .narep-process-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					],
				]
			);
			$this->add_control(
				'section_width',
				[
					'label' => esc_html__( 'Section Width', 'restaurant-cafe-addon-for-elementor' ),
					'type' => Controls_Manager::SLIDER,
					'range' => [
						'px' => [
							'min' => 0,
							'max' => 1500,
							'step' => 1,
						],
						'%' => [
							'min' => 0,
							'max' => 100,
						],
					],
					'size_units' => [ 'px', '%', 'em' ],
					'selectors' => [
						'{{WRAPPER}} .narep-process-info' => 'max-width: {{SIZE}}{{UNIT}};',
					],
				]
			);
			$this->end_controls_section();// end: Section

		// Section Two
			$this->start_controls_section(
				'section_two_style',
				[
					'label' => esc_html__( 'Section', 'restaurant-cafe-addon-for-elementor' ),
					'tab' => Controls_Manager::TAB_STYLE,
					'condition' => [
						'process_style' => 'two',
					],
				]
			);
			$this->add_control(
				'proc_bg_color',
				[
					'label' => esc_html__( 'Background Color', 'restaurant-cafe-addon-for-elementor' ),
					'type' => Controls_Manager::COLOR,
					'selectors' => [
						'{{WRAPPER}} .narep-proc-info' => 'background-color: {{VALUE}};',
					],
				]
			);
			$this->add_responsive_control(
				'process_section_two_margin',
				[
					'label' => __( 'Margin', 'restaurant-cafe-addon-for-elementor' ),
					'type' => Controls_Manager::DIMENSIONS,
					'size_units' => [ 'px' ],
					'selectors' => [
						'{{WRAPPER}} .narep-proc-info' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					],
				]
			);
			$this->add_responsive_control(
				'process_section_two_padding',
				[
					'label' => __( 'Padding', 'restaurant-cafe-addon-for-elementor' ),
					'type' => Controls_Manager::DIMENSIONS,
					'size_units' => [ 'px' ],
					'selectors' => [
						'{{WRAPPER}} .narep-proc-info' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					],
				]
			);
			$this->add_group_control(
				Group_Control_Border::get_type(),
				[
					'name' => 'secn_two_border',
					'label' => esc_html__( 'Border', 'restaurant-cafe-addon-for-elementor' ),
					'selector' => '{{WRAPPER}} .narep-proc-info',
				]
			);
			$this->add_group_control(
				Group_Control_Box_Shadow::get_type(),
				[
					'name' => 'secn_two_box_shadow',
					'label' => esc_html__( 'Section Box Shadow', 'restaurant-cafe-addon-for-elementor' ),
					'selector' => '{{WRAPPER}} .narep-proc-info',
				]
			);
			$this->add_control(
				'odd',
				[
					'label' => __( 'Odd Item', 'charity-addon-for-elementor' ),
					'type' => Controls_Manager::HEADING,
					'separator' => 'after',
				]
			);
			$this->add_group_control(
				Group_Control_Background::get_type(),
				[
					'name' => 'shade_gradient_background',
					'label' => __( 'Shade Color', 'events-addon-for-elementor' ),
					'types' => [ 'gradient' ],
					'selector' => '{{WRAPPER}} .narep-proc-item',
				]
			);
			$this->add_control(
				'even',
				[
					'label' => __( 'Even Item', 'charity-addon-for-elementor' ),
					'type' => Controls_Manager::HEADING,
					'separator' => 'after',
				]
			);
			$this->add_group_control(
				Group_Control_Background::get_type(),
				[
					'name' => 'shade_odd_gradient_background',
					'label' => __( 'Odd Items Shade Color', 'events-addon-for-elementor' ),
					'types' => [ 'gradient' ],
					'selector' => '{{WRAPPER}} .narep-proc-item.odd',
				]
			);
			$this->end_controls_section();// end: Section

		// Section Three
			$this->start_controls_section(
				'section_three_style',
				[
					'label' => esc_html__( 'Section', 'restaurant-cafe-addon-for-elementor' ),
					'tab' => Controls_Manager::TAB_STYLE,
					'condition' => [
						'process_style' => 'three',
					],
				]
			);
			$this->start_controls_tabs( 'cir_style' );
				$this->start_controls_tab(
					'ico_cir_normal',
					[
						'label' => esc_html__( 'Line And Circle', 'restaurant-cafe-addon-for-elementor' ),
					]
				);
				$this->add_control(
					'cir_bg_color',
					[
						'label' => esc_html__( 'Circle Background Color', 'restaurant-cafe-addon-for-elementor' ),
						'type' => Controls_Manager::COLOR,
						'selectors' => [
							'{{WRAPPER}} .narep-step-counter:after' => 'background-color: {{VALUE}};',
						],
					]
				);
				$this->add_control(
					'line_color',
					[
						'label' => esc_html__( 'Line Color', 'restaurant-cafe-addon-for-elementor' ),
						'type' => Controls_Manager::COLOR,
						'selectors' => [
							'{{WRAPPER}} .process-style-three .narep-process-item:after' => 'background-color: {{VALUE}};',
						],
					]
				);
				$this->end_controls_tab();  // end:Normal tab

				$this->start_controls_tab(
					'ico_cir_hover',
					[
						'label' => esc_html__( 'Hover', 'restaurant-cafe-addon-for-elementor' ),
					]
				);
				$this->add_control(
					'cir_hov_bg_color',
					[
						'label' => esc_html__( 'Circle Background Color', 'restaurant-cafe-addon-for-elementor' ),
						'type' => Controls_Manager::COLOR,
						'selectors' => [
							'{{WRAPPER}} .narep-process-item.process-done .narep-step-counter:after' => 'background-color: {{VALUE}};',
						],
					]
				);
				$this->add_control(
					'line_hov_color',
					[
						'label' => esc_html__( 'Line Color', 'restaurant-cafe-addon-for-elementor' ),
						'type' => Controls_Manager::COLOR,
						'selectors' => [
							'{{WRAPPER}} .process-style-three .narep-process-item.process-done:before' => 'background-color: {{VALUE}};',
						],
					]
				);
				$this->end_controls_tab();  // end:Normal tab
			$this->end_controls_tabs(); // end tabs

			$this->end_controls_section();// end: Section

		// Icon
			$this->start_controls_section(
				'icon_style',
				[
					'label' => esc_html__( 'Icon', 'restaurant-cafe-addon-for-elementor' ),
					'tab' => Controls_Manager::TAB_STYLE,
					'condition' => [
						'process_style' => array('one'),
					],
				]
			);
			$this->add_responsive_control(
				'process_icon_margin',
				[
					'label' => __( 'Margin', 'restaurant-cafe-addon-for-elementor' ),
					'type' => Controls_Manager::DIMENSIONS,
					'size_units' => [ 'px' ],
					'selectors' => [
						'{{WRAPPER}} .narep-process-item .narep-icon' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					],
				]
			);
			$this->add_control(
				'icon_width',
				[
					'label' => esc_html__( 'Width', 'restaurant-cafe-addon-for-elementor' ),
					'type' => Controls_Manager::SLIDER,
					'range' => [
						'px' => [
							'min' => 0,
							'max' => 1500,
							'step' => 1,
						],
						'%' => [
							'min' => 0,
							'max' => 100,
						],
					],
					'size_units' => [ 'px', '%', 'em' ],
					'selectors' => [
						'{{WRAPPER}} .narep-process-item .narep-icon' => 'width: {{SIZE}}{{UNIT}};height: {{SIZE}}{{UNIT}};',
					],
				]
			);
			$this->add_control(
				'icon_size',
				[
					'label' => esc_html__( 'Icon/Image Size', 'restaurant-cafe-addon-for-elementor' ),
					'type' => Controls_Manager::SLIDER,
					'range' => [
						'px' => [
							'min' => 0,
							'max' => 1500,
							'step' => 1,
						],
						'%' => [
							'min' => 0,
							'max' => 100,
						],
					],
					'size_units' => [ 'px', '%', 'em' ],
					'selectors' => [
						'{{WRAPPER}} .narep-process-item .narep-icon i' => 'font-size: {{SIZE}}{{UNIT}};',
						'{{WRAPPER}} .narep-process-item .narep-icon img' => 'max-width: {{SIZE}}{{UNIT}};',
					],
				]
			);
			$this->start_controls_tabs( 'secn_style' );
				$this->start_controls_tab(
					'ico_secn_normal',
					[
						'label' => esc_html__( 'Normal', 'restaurant-cafe-addon-for-elementor' ),
					]
				);
				$this->add_control(
					'ico_secn_icon_color',
					[
						'label' => esc_html__( 'Color', 'restaurant-cafe-addon-for-elementor' ),
						'type' => Controls_Manager::COLOR,
						'selectors' => [
							'{{WRAPPER}} .narep-process-item .narep-icon' => 'color: {{VALUE}};',
						],
					]
				);
				$this->add_control(
					'ico_secn_bg_color',
					[
						'label' => esc_html__( 'Background Color', 'restaurant-cafe-addon-for-elementor' ),
						'type' => Controls_Manager::COLOR,
						'selectors' => [
							'{{WRAPPER}} .narep-process-item .narep-icon' => 'background-color: {{VALUE}};',
						],
					]
				);
				$this->add_control(
					'ico_secn_bdr_color',
					[
						'label' => esc_html__( 'Line Color', 'restaurant-cafe-addon-for-elementor' ),
						'type' => Controls_Manager::COLOR,
						'selectors' => [
							'{{WRAPPER}} .narep-process-item:before' => 'background-color: {{VALUE}};',
							'{{WRAPPER}} .border-style.narep-process-item:before' => 'border-color: {{VALUE}};',
						],
					]
				);
				$this->add_group_control(
					Group_Control_Border::get_type(),
					[
						'name' => 'ico_secn_border',
						'label' => esc_html__( 'Border', 'restaurant-cafe-addon-for-elementor' ),
						'selector' => '{{WRAPPER}} .narep-process-item .narep-icon',
					]
				);
				$this->add_group_control(
					Group_Control_Box_Shadow::get_type(),
					[
						'name' => 'ico_secn_box_shadow',
						'label' => esc_html__( 'Section Box Shadow', 'restaurant-cafe-addon-for-elementor' ),
						'selector' => '{{WRAPPER}} .narep-process-item .narep-icon',
					]
				);
				$this->end_controls_tab();  // end:Normal tab

				$this->start_controls_tab(
					'ico_secn_hover',
					[
						'label' => esc_html__( 'Hover', 'restaurant-cafe-addon-for-elementor' ),
					]
				);
				$this->add_control(
					'ico_secn_hov_icon_color',
					[
						'label' => esc_html__( 'Color', 'restaurant-cafe-addon-for-elementor' ),
						'type' => Controls_Manager::COLOR,
						'selectors' => [
							'{{WRAPPER}} .narep-process-item.process-done .narep-icon' => 'color: {{VALUE}};',
						],
					]
				);
				$this->add_control(
					'ico_secn_hov_bg_color',
					[
						'label' => esc_html__( 'Background Color', 'restaurant-cafe-addon-for-elementor' ),
						'type' => Controls_Manager::COLOR,
						'selectors' => [
							'{{WRAPPER}} .narep-process-item.process-done .narep-icon, {{WRAPPER}} .narep-process-item.process-done .narep-icon span.circle' => 'background-color: {{VALUE}};',
						],
					]
				);
				$this->add_control(
					'ico_secn_hov_bdr_color',
					[
						'label' => esc_html__( 'Line Color', 'restaurant-cafe-addon-for-elementor' ),
						'type' => Controls_Manager::COLOR,
						'selectors' => [
							'{{WRAPPER}} .narep-process-item.process-done:after' => 'background-color: {{VALUE}};',
							'{{WRAPPER}} .process-done.border-style.narep-process-item:before' => 'border-color: {{VALUE}};',
						],
					]
				);
				$this->add_group_control(
					Group_Control_Border::get_type(),
					[
						'name' => 'ico_secn_hov_border',
						'label' => esc_html__( 'Border', 'restaurant-cafe-addon-for-elementor' ),
						'selector' => '{{WRAPPER}} .narep-process-item.process-done .narep-icon',
					]
				);
				$this->add_group_control(
					Group_Control_Box_Shadow::get_type(),
					[
						'name' => 'ico_secn_hov_box_shadow',
						'label' => esc_html__( 'Section Box Shadow', 'restaurant-cafe-addon-for-elementor' ),
						'selector' => '{{WRAPPER}} .narep-process-item.process-done .narep-icon',
					]
				);
				$this->end_controls_tab();  // end:Hover tab
			$this->end_controls_tabs(); // end tabs

			$this->end_controls_section();// end: Section

		// Icon
			$this->start_controls_section(
				'icon_proc_style',
				[
					'label' => esc_html__( 'Icon', 'restaurant-cafe-addon-for-elementor' ),
					'tab' => Controls_Manager::TAB_STYLE,
					'condition' => [
						'process_style' => array('two'),
					],
				]
			);
			$this->add_control(
				'icon_proc_size',
				[
					'label' => esc_html__( 'Icon Size', 'restaurant-cafe-addon-for-elementor' ),
					'type' => Controls_Manager::SLIDER,
					'range' => [
						'px' => [
							'min' => 0,
							'max' => 1500,
							'step' => 1,
						],
					],
					'size_units' => [ 'px' ],
					'selectors' => [
						'{{WRAPPER}} .narep-proc-info .narep-icon i' => 'font-size: {{SIZE}}{{UNIT}};',
					],
				]
			);
			$this->add_group_control(
				Group_Control_Background::get_type(),
				[
					'name' => 'icon_gradient_background',
					'label' => __( 'Icon Color', 'events-addon-for-elementor' ),
					'types' => [ 'gradient' ],
					'selector' => '{{WRAPPER}} .narep-proc-info .narep-icon i',
				]
			);
			$this->add_control(
				'num',
				[
					'label' => __( 'Number', 'charity-addon-for-elementor' ),
					'type' => Controls_Manager::HEADING,
					'separator' => 'after',
				]
			);
			$this->add_group_control(
				Group_Control_Typography::get_type(),
				[
					'label' => esc_html__( 'Number Typography', 'restaurant-cafe-addon-for-elementor' ),
					'name' => 'number_typography',
					'selector' => '{{WRAPPER}} .proc-count',
				]
			);
			$this->add_control(
				'number_color',
				[
					'label' => esc_html__( 'Number Color', 'restaurant-cafe-addon-for-elementor' ),
					'type' => Controls_Manager::COLOR,
					'selectors' => [
						'{{WRAPPER}} .proc-count' => 'color: {{VALUE}};',
					],
				]
			);
			$this->add_group_control(
				Group_Control_Background::get_type(),
				[
					'name' => 'number_gradient_background',
					'label' => __( 'Number Background Color', 'events-addon-for-elementor' ),
					'types' => [ 'gradient' ],
					'selector' => '{{WRAPPER}} .proc-count',
				]
			);
			$this->end_controls_section();// end: Section

		// Title
			$this->start_controls_section(
				'section_title_style',
				[
					'label' => esc_html__( 'Title', 'restaurant-cafe-addon-for-elementor' ),
					'tab' => Controls_Manager::TAB_STYLE,
				]
			);
			$this->add_responsive_control(
				'process_title_padding',
				[
					'label' => __( 'Padding', 'restaurant-cafe-addon-for-elementor' ),
					'type' => Controls_Manager::DIMENSIONS,
					'size_units' => [ 'px' ],
					'selectors' => [
						'{{WRAPPER}} .narep-process-item h4' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					],
				]
			);
			$this->add_group_control(
				Group_Control_Typography::get_type(),
				[
					'label' => esc_html__( 'Typography', 'restaurant-cafe-addon-for-elementor' ),
					'name' => 'sastool_title_typography',
					'selector' => '{{WRAPPER}} .narep-proc-info h4, {{WRAPPER}} .narep-process-info h4',
				]
			);
			$this->start_controls_tabs( 'title_style' );
				$this->start_controls_tab(
					'title_normal',
					[
						'label' => esc_html__( 'Normal', 'restaurant-cafe-addon-for-elementor' ),
					]
				);
				$this->add_control(
					'title_color',
					[
						'label' => esc_html__( 'Color', 'restaurant-cafe-addon-for-elementor' ),
						'type' => Controls_Manager::COLOR,
						'selectors' => [
							'{{WRAPPER}} .narep-proc-info h3, {{WRAPPER}} .narep-process-info h4, {{WRAPPER}} .narep-process-info h4 a, {{WRAPPER}} .narep-proc-info h3 a' => 'color: {{VALUE}};',
						],
					]
				);
				$this->end_controls_tab();  // end:Normal tab
				$this->start_controls_tab(
					'title_hover',
					[
						'label' => esc_html__( 'Hover', 'restaurant-cafe-addon-for-elementor' ),
					]
				);
				$this->add_control(
					'title_hover_color',
					[
						'label' => esc_html__( 'Color', 'restaurant-cafe-addon-for-elementor' ),
						'type' => Controls_Manager::COLOR,
						'selectors' => [
							'{{WRAPPER}} .narep-proc-info h3 a:hover, {{WRAPPER}} .narep-process-info h4 a:hover' => 'color: {{VALUE}};',
						],
					]
				);
				$this->end_controls_tab();  // end:Hover tab
			$this->end_controls_tabs(); // end tabs
			$this->end_controls_section();// end: Section

		// Steps
			$this->start_controls_section(
				'section_steps_style',
				[
					'label' => esc_html__( 'Steps', 'restaurant-cafe-addon-for-elementor' ),
					'tab' => Controls_Manager::TAB_STYLE,
				]
			);
			$this->add_responsive_control(
				'process_steps_padding',
				[
					'label' => __( 'Padding', 'restaurant-cafe-addon-for-elementor' ),
					'type' => Controls_Manager::DIMENSIONS,
					'size_units' => [ 'px' ],
					'selectors' => [
						'{{WRAPPER}} .narep-step-counter' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					],
				]
			);
			$this->add_control(
				'steps_size',
				[
					'label' => esc_html__( 'Width', 'restaurant-cafe-addon-for-elementor' ),
					'type' => Controls_Manager::SLIDER,
					'range' => [
						'px' => [
							'min' => 0,
							'max' => 1500,
							'step' => 1,
						],
					],
					'size_units' => [ 'px' ],
					'selectors' => [
						'{{WRAPPER}} .narep-step-counter' => 'max-width: {{SIZE}}{{UNIT}};',
						'{{WRAPPER}} .process-style-three .narep-process-item:after, {{WRAPPER}} .process-style-three .narep-process-item:before' => 'left: calc({{SIZE}}{{UNIT}} + 22px);',
					],
				]
			);
			$this->add_group_control(
				Group_Control_Typography::get_type(),
				[
					'label' => esc_html__( 'Typography', 'restaurant-cafe-addon-for-elementor' ),
					'name' => 'sastool_steps_typography',
					'selector' => '{{WRAPPER}} .narep-step-counter',
				]
			);
			$this->add_control(
				'steps_color',
				[
					'label' => esc_html__( 'Color', 'restaurant-cafe-addon-for-elementor' ),
					'type' => Controls_Manager::COLOR,
					'selectors' => [
						'{{WRAPPER}} .narep-step-counter' => 'color: {{VALUE}};',
					],
				]
			);
			$this->end_controls_section();// end: Section

			// Content
			$this->start_controls_section(
				'section_content_style',
				[
					'label' => esc_html__( 'Content', 'restaurant-cafe-addon-for-elementor' ),
					'tab' => Controls_Manager::TAB_STYLE,
				]
			);
			$this->add_responsive_control(
				'process_cont_padding',
				[
					'label' => __( 'Padding', 'restaurant-cafe-addon-for-elementor' ),
					'type' => Controls_Manager::DIMENSIONS,
					'size_units' => [ 'px' ],
					'selectors' => [
						'{{WRAPPER}} .narep-proc-info p, {{WRAPPER}} .narep-process-item p' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					],
				]
			);
			$this->add_group_control(
				Group_Control_Typography::get_type(),
				[
					'label' => esc_html__( 'Typography', 'restaurant-cafe-addon-for-elementor' ),
					'name' => 'sastool_content_typography',
					'selector' => '{{WRAPPER}} .narep-proc-info p, {{WRAPPER}} .narep-process-item p',
				]
			);
			$this->add_control(
				'content_color',
				[
					'label' => esc_html__( 'Color', 'restaurant-cafe-addon-for-elementor' ),
					'type' => Controls_Manager::COLOR,
					'selectors' => [
						'{{WRAPPER}} .narep-proc-info p, {{WRAPPER}} .narep-process-item p' => 'color: {{VALUE}};',
					],
				]
			);
			$this->end_controls_section();// end: Section

	}

	/**
	 * Render Process widget output on the frontend.
	 * Written in PHP and used to generate the final HTML.
	*/
	protected function render() {
		// Process query
		$settings = $this->get_settings_for_display();
		$process = $this->get_settings_for_display( 'process_groups' );
		$process_style = !empty( $settings['process_style'] ) ? $settings['process_style'] : '';

		if ($process_style === 'two') {
			$style_class = ' process-style-two nich-row';
		} elseif ($process_style === 'three') {
			$style_class = ' process-style-three';
		} else {
			$style_class = '';
		}

		$output = '';

		$output .= '<div class="narep-process-wrap'.esc_attr($style_class).'">';
			if ($process_style === 'two') {
				// Group Param Output
				$v = 1;
				foreach ( $process as $each_logo ) {
					$upload_type = !empty( $each_logo['upload_type'] ) ? $each_logo['upload_type'] : '';
					$process_title = !empty( $each_logo['process_title'] ) ? $each_logo['process_title'] : '';
					$title_link = !empty( $each_logo['title_link']['url'] ) ? esc_url($each_logo['title_link']['url']) : '';
					$title_link_external = !empty( $title_link['is_external'] ) ? 'target="_blank"' : '';
					$title_link_nofollow = !empty( $title_link['nofollow'] ) ? 'rel="nofollow"' : '';
					$title_link_attr = !empty( $title_link['url'] ) ?  $title_link_external.' '.$title_link_nofollow : '';

					$process_image = !empty( $each_logo['process_image']['id'] ) ? $each_logo['process_image']['id'] : '';
					$process_icon = !empty( $each_logo['process_icon'] ) ? $each_logo['process_icon'] : '';
					$process_content = !empty( $each_logo['process_content'] ) ? $each_logo['process_content'] : '';

					$image_url = wp_get_attachment_url( $process_image );
					$process_image = $image_url ? '<img src="'.esc_url($image_url).'" alt="'.esc_attr($process_title).'">' : '';
					$process_icon = $process_icon ? '<i class="'.esc_attr($process_icon).'"></i>' : '';

					if ($upload_type === 'icon'){
					  $icon_main = '<div class="narep-icon">'.$process_icon.'</div>';
					} else {
					  $icon_main = '<div class="narep-icon">'.$process_image.'</div>';
					}
					if ($v > 9) {
						$v = $v;
					} else {
						$v = '0'.$v;
					}
					$count = '<div class="proc-count">'.$v.'</div>';

					if ($v % 2 == 0) {
						$top = $count;
						$bottom = $icon_main;
						$odd_class = ' odd';
					} else {
						$top = $icon_main;
						$bottom = $count;
						$odd_class = '';
					}

			  	$title_link = !empty( $title_link ) ? '<a href="'.esc_url($title_link).'" '.$title_link_attr.'>'.esc_html($process_title).'</a>' : esc_html($process_title);
			  	$title = !empty( $process_title ) ? '<h4>'.$title_link.'</h4>' : '';
					$content = $process_content ? '<p>'.esc_html($process_content).'</p>' : '';

				  $output .= '<div class="nich-col-xl-3 nich-col-lg-6"><div class="narep-proc-item narep-item'.$odd_class.'">
									      <div class="narep-proc-info">
									        '.$top.$title.$content.$bottom.'
									      </div>
									    </div></div>';
									    $v++;
				}
			} elseif ($process_style === 'three') {
				// Group Param Output
				foreach ( $process as $each_logo ) {
					$step_title = !empty( $each_logo['step_title'] ) ? $each_logo['step_title'] : '';
					$process_title = !empty( $each_logo['process_title'] ) ? $each_logo['process_title'] : '';
					$title_link = !empty( $each_logo['title_link']['url'] ) ? esc_url($each_logo['title_link']['url']) : '';
					$title_link_external = !empty( $title_link['is_external'] ) ? 'target="_blank"' : '';
					$title_link_nofollow = !empty( $title_link['nofollow'] ) ? 'rel="nofollow"' : '';
					$title_link_attr = !empty( $title_link['url'] ) ?  $title_link_external.' '.$title_link_nofollow : '';

					$process_content = !empty( $each_logo['process_content'] ) ? $each_logo['process_content'] : '';

	 			  $title_link = !empty( $title_link ) ? '<a href="'.esc_url($title_link).'" '.$title_link_attr.'>'.esc_html($process_title).'</a>' : esc_html($process_title);
			  	$title = !empty( $process_title ) ? '<h4 class="process-title">'.$title_link.'</h4>' : '';
			  	$step_title = !empty( $step_title ) ? '<div class="narep-step-counter">'.$step_title.'</div>' : '';
					$content = $process_content ? '<p>'.esc_html($process_content).'</p>' : '';

				  $output .= '<div class="narep-process-item">
							          '.$step_title.'
							          <div class="narep-process-info">
							            '.$title.$content.'
							          </div>
							        </div>';
				}
			} else {
				// Group Param Output
				foreach ( $process as $each_logo ) {
					$upload_type = !empty( $each_logo['upload_type'] ) ? $each_logo['upload_type'] : '';
					$process_title = !empty( $each_logo['process_title'] ) ? $each_logo['process_title'] : '';
					$title_link = !empty( $each_logo['title_link']['url'] ) ? esc_url($each_logo['title_link']['url']) : '';
					$title_link_external = !empty( $title_link['is_external'] ) ? 'target="_blank"' : '';
					$title_link_nofollow = !empty( $title_link['nofollow'] ) ? 'rel="nofollow"' : '';
					$title_link_attr = !empty( $title_link['url'] ) ?  $title_link_external.' '.$title_link_nofollow : '';

					$process_image = !empty( $each_logo['process_image']['id'] ) ? $each_logo['process_image']['id'] : '';
					$process_icon = !empty( $each_logo['process_icon'] ) ? $each_logo['process_icon'] : '';
					$process_content = !empty( $each_logo['process_content'] ) ? $each_logo['process_content'] : '';

					$image_url = wp_get_attachment_url( $process_image );
					$process_image = $image_url ? '<img src="'.esc_url($image_url).'" alt="'.esc_attr($process_title).'">' : '';
					$process_icon = $process_icon ? '<i class="'.esc_attr($process_icon).'"></i>' : '';

					if ($upload_type === 'icon'){
					  $icon_main = $process_icon;
					} else {
					  $icon_main = $process_image;
					}

			  	$title_link = !empty( $title_link ) ? '<a href="'.esc_url($title_link).'" '.$title_link_attr.'>'.esc_html($process_title).'</a>' : esc_html($process_title);
			  	$title = !empty( $process_title ) ? '<h4 class="process-title">'.$title_link.'</h4>' : '';
					$content = $process_content ? '<p>'.esc_html($process_content).'</p>' : '';

				  $output .= '<div class="narep-process-item">
									      <div class="narep-process-info">
									        <div class="narep-icon">
									          <div class="narep-table-wrap">
									            <div class="narep-align-wrap">
									              '.$icon_main.'
									            </div>
									          </div>
									        </div>
									        '.$title.$content.'
									      </div>
									    </div>';
				}
			}

		$output .= '</div>';
		echo $output;

	}

}
Plugin::instance()->widgets_manager->register_widget_type( new Restaurant_Elementor_Addon_Process() );

} // enable & disable
