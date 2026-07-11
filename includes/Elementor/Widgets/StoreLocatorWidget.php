<?php
/**
 * Elementor "Store Locator" widget — Figma-aligned layout.
 *
 * Assets are declared via get_script_depends() / get_style_depends() so
 * Elementor enqueues them reliably on both the frontend and in the editor
 * preview iframe — completely independent of the [store_locator] shortcode
 * detection path in Assets::should_enqueue().
 *
 * @package AseerStoreLocator
 */

namespace Aseer\StoreLocator\Elementor\Widgets;

use Aseer\StoreLocator\Frontend\Assets;
use Aseer\StoreLocator\Frontend\Templates;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class StoreLocatorWidget
 */
class StoreLocatorWidget extends Widget_Base {

	/**
	 * Widget slug.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'asl-store-locator';
	}

	/**
	 * Widget title in the Elementor panel.
	 *
	 * @return string
	 */
	public function get_title() {
		return esc_html__( 'Store Locator', 'aseer-store-locator' );
	}

	/**
	 * Elementor icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-google-maps';
	}

	/**
	 * Widget category.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( 'aseer-store-locator' );
	}

	/**
	 * Frontend script dependencies.
	 *
	 * Elementor calls wp_enqueue_script() for every handle returned here,
	 * pulling in the full dependency chain (Leaflet / Google Maps / clusterer)
	 * automatically — no shortcode detection needed.
	 *
	 * @return string[]
	 */
	public function get_script_depends() {
		return array( 'aseer-store-locator' );
	}

	/**
	 * Frontend style dependencies.
	 *
	 * Includes the Google Fonts stylesheet so Barlow Condensed (and Tajawal
	 * for RTL) are always available when this widget is on the page.
	 *
	 * @return string[]
	 */
	public function get_style_depends() {
		return array( 'asl-google-fonts', 'aseer-store-locator' );
	}

	/**
	 * Register widget controls — Content + Style tabs.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->register_content_controls();
		$this->register_style_controls();
	}

	// -------------------------------------------------------------------------
	// Content tab
	// -------------------------------------------------------------------------

	/**
	 * Register Content-tab controls.
	 *
	 * @return void
	 */
	private function register_content_controls() {

		/* ---------- Hero section ---------- */
		$this->start_controls_section(
			'section_hero',
			array(
				'label' => esc_html__( 'Hero', 'aseer-store-locator' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_hero',
			array(
				'label'        => esc_html__( 'Show Hero', 'aseer-store-locator' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'aseer-store-locator' ),
				'label_off'    => esc_html__( 'No', 'aseer-store-locator' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'hero_title',
			array(
				'label'     => esc_html__( 'Title', 'aseer-store-locator' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => esc_html__( 'Find a Store', 'aseer-store-locator' ),
				'condition' => array( 'show_hero' => 'yes' ),
			)
		);

		$this->add_control(
			'hero_subtitle',
			array(
				'label'     => esc_html__( 'Subtitle', 'aseer-store-locator' ),
				'type'      => Controls_Manager::TEXTAREA,
				'default'   => esc_html__( 'Search Aseer Time Group branches worldwide. Find your nearest location, explore our brands, and plan your visit.', 'aseer-store-locator' ),
				'condition' => array( 'show_hero' => 'yes' ),
			)
		);

		$this->end_controls_section();

		/* ---------- Map section ---------- */
		$this->start_controls_section(
			'section_map',
			array(
				'label' => esc_html__( 'Map', 'aseer-store-locator' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'map_height',
			array(
				'label'       => esc_html__( 'Map Height', 'aseer-store-locator' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '689px',
				'description' => esc_html__( 'CSS value, e.g. 689px or 80vh.', 'aseer-store-locator' ),
			)
		);

		$this->add_control(
			'default_zoom',
			array(
				'label'       => esc_html__( 'Default Zoom', 'aseer-store-locator' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 1,
				'max'         => 20,
				'step'        => 1,
				'default'     => '',
				'description' => esc_html__( 'Leave empty to use the value from Store Locator → Settings.', 'aseer-store-locator' ),
			)
		);

		$this->add_control(
			'default_center',
			array(
				'label'       => esc_html__( 'Default Center (lat,lng)', 'aseer-store-locator' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => '29.3759,47.9774',
				'description' => esc_html__( 'Leave empty to use the value from Store Locator → Settings.', 'aseer-store-locator' ),
			)
		);

		$this->end_controls_section();
	}

	// -------------------------------------------------------------------------
	// Style tab
	// -------------------------------------------------------------------------

	/**
	 * Register Style-tab controls.
	 *
	 * @return void
	 */
	private function register_style_controls() {

		/* ---------- Colors section ---------- */
		$this->start_controls_section(
			'section_style_colors',
			array(
				'label' => esc_html__( 'Colors', 'aseer-store-locator' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'color_primary',
			array(
				'label'       => esc_html__( 'Primary Color', 'aseer-store-locator' ),
				'description' => esc_html__( 'Active borders, Find Store button, highlights.', 'aseer-store-locator' ),
				'type'        => Controls_Manager::COLOR,
				'default'     => '#AF202B',
				'selectors'   => array(
					'{{WRAPPER}} .asl-locator' => '--asl-color-primary: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'color_primary_hover',
			array(
				'label'     => esc_html__( 'Primary Hover Color', 'aseer-store-locator' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#D12027',
				'selectors' => array(
					'{{WRAPPER}} .asl-locator' => '--asl-color-primary-hover: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'color_secondary',
			array(
				'label'       => esc_html__( 'Secondary Color', 'aseer-store-locator' ),
				'description' => esc_html__( 'Open Map / Get Directions button.', 'aseer-store-locator' ),
				'type'        => Controls_Manager::COLOR,
				'default'     => '#007537',
				'selectors'   => array(
					'{{WRAPPER}} .asl-locator' => '--asl-color-secondary: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'color_heading',
			array(
				'label'     => esc_html__( 'Hero Title Color', 'aseer-store-locator' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#012027',
				'selectors' => array(
					'{{WRAPPER}} .asl-locator__title' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'color_page_bg',
			array(
				'label'     => esc_html__( 'Page Background', 'aseer-store-locator' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#F4F1EA',
				'selectors' => array(
					'{{WRAPPER}} .asl-locator' => '--asl-color-page: {{VALUE}}; background: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();

		/* ---------- Hero Typography section ---------- */
		$this->start_controls_section(
			'section_style_hero',
			array(
				'label'     => esc_html__( 'Hero Text', 'aseer-store-locator' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_hero' => 'yes' ),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'typography_title',
				'label'    => esc_html__( 'Title Typography', 'aseer-store-locator' ),
				'selector' => '{{WRAPPER}} .asl-locator__title',
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'typography_subtitle',
				'label'    => esc_html__( 'Subtitle Typography', 'aseer-store-locator' ),
				'selector' => '{{WRAPPER}} .asl-locator__subtitle',
			)
		);

		$this->add_control(
			'hero_text_align',
			array(
				'label'     => esc_html__( 'Alignment', 'aseer-store-locator' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'left'   => array(
						'title' => esc_html__( 'Left', 'aseer-store-locator' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Center', 'aseer-store-locator' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => esc_html__( 'Right', 'aseer-store-locator' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'default'   => 'center',
				'selectors' => array(
					'{{WRAPPER}} .asl-locator__hero' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();

		/* ---------- Cards section ---------- */
		$this->start_controls_section(
			'section_style_cards',
			array(
				'label' => esc_html__( 'Store Cards', 'aseer-store-locator' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'card_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'aseer-store-locator' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 30 ),
				),
				'default'    => array( 'unit' => 'px', 'size' => 10 ),
				'selectors'  => array(
					'{{WRAPPER}} .asl-card' => 'border-radius: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'view_map_bg',
			array(
				'label'     => esc_html__( 'Mobile "View Map" Button Color', 'aseer-store-locator' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#000000',
				'selectors' => array(
					'{{WRAPPER}} .asl-locator__view-map' => 'background: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	/**
	 * Render widget output on the frontend.
	 *
	 * The widget is fully self-sufficient: assets are already declared via
	 * get_script_depends() / get_style_depends() so Elementor enqueues them.
	 * We additionally call wp_enqueue_* here as a belt-and-suspenders guard
	 * for edge cases (e.g. when the widget is previewed inside a popup or
	 * a template that bypasses Elementor's normal dependency resolution).
	 *
	 * Style tab controls are applied automatically by Elementor via the
	 * `selectors` array defined in register_style_controls() — no manual
	 * inline CSS needed for those.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		// Belt-and-suspenders: directly enqueue our assets in case Elementor's
		// dependency chain didn't catch them (e.g. inside a popup template).
		// Both handles are already registered in Assets::register_assets().
		wp_enqueue_style( 'asl-google-fonts' );
		wp_enqueue_style( 'aseer-store-locator' );
		wp_enqueue_script( 'aseer-store-locator' );

		$atts = array(
			'height'         => ! empty( $settings['map_height'] ) ? $settings['map_height'] : '689px',
			'default_zoom'   => '' !== $settings['default_zoom'] ? (string) $settings['default_zoom'] : '',
			'default_center' => ! empty( $settings['default_center'] ) ? $settings['default_center'] : '',
			'show_hero'      => ( 'yes' === $settings['show_hero'] ) ? '1' : '0',
			'hero_title'     => ! empty( $settings['hero_title'] ) ? $settings['hero_title'] : '',
			'hero_subtitle'  => ! empty( $settings['hero_subtitle'] ) ? $settings['hero_subtitle'] : '',
			'instance_id'    => 'asl-locator-' . $this->get_id(),
		);

		// Signal the late-enqueue guard in Assets so it doesn't try to
		// double-enqueue when the shortcode path also runs on the same page.
		Assets::$is_active = true;

		Templates::get_template( 'locator.php', array( 'atts' => $atts ) );
	}
}
