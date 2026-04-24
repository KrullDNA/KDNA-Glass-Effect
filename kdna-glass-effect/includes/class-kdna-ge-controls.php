<?php
/**
 * Control injection for the KDNA Glass Effect plugin.
 *
 * Hooks Elementor's elementor/element/after_section_end action and
 * appends a "KDNA Glass Effect" section to the Style tab of every
 * supported widget. All style controls emit scoped CSS variables via
 * Elementor's selectors parameter, so per-widget overhead is a short
 * list of CSS variable declarations on the target selector.
 *
 * No CSS or SVG is emitted by this class, Session 2 wires rendering.
 *
 * @package KDNA_Glass_Effect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Element_Base;
use Elementor\Repeater;

/**
 * Class KDNA_GE_Controls
 */
class KDNA_GE_Controls {

	const SECTION_ID = 'kdna_ge_section';
	const PREFIX     = 'kdna_ge_';

	/**
	 * Register Elementor hooks. Called once from KDNA_GE_Plugin::init().
	 */
	public function register_hooks() {
		// Fire after any built-in Style section closes, so we can inject
		// the Glass Effect section at the end of the Style tab.
		add_action( 'elementor/element/after_section_end', array( $this, 'inject_controls' ), 10, 3 );
	}

	/**
	 * Injection callback. Adds the Glass Effect section to supported
	 * widgets only, and only once per widget (after any Style section ends).
	 *
	 * @param Element_Base $element Element being rendered.
	 * @param string       $section_id Section that just closed.
	 * @param array        $args Section args.
	 */
	public function inject_controls( $element, $section_id, $args ) {
		if ( ! $element instanceof Element_Base ) {
			return;
		}

		// Only append once, and only on the Style tab. Tab argument is
		// only present when the closing section is a style-tab section.
		if ( empty( $args['tab'] ) || Controls_Manager::TAB_STYLE !== $args['tab'] ) {
			return;
		}

		$widget_id = $element->get_name();
		if ( ! in_array( $widget_id, KDNA_GE_Targets::supported_widget_ids(), true ) ) {
			return;
		}

		// Avoid adding the section more than once if several Style
		// sections end on this element: key on a control flag.
		if ( $element->get_controls( self::SECTION_ID . '_injected_flag' ) ) {
			return;
		}

		$this->add_section( $element, $widget_id );
	}

	/**
	 * Build the full control section for a widget.
	 *
	 * @param Element_Base $element Element.
	 * @param string       $widget_id Elementor widget ID.
	 */
	private function add_section( $element, $widget_id ) {
		$apply_options   = KDNA_GE_Targets::apply_to_options( $widget_id );
		$default_apply   = KDNA_GE_Targets::default_apply_to( $widget_id );
		$selector_map    = KDNA_GE_Targets::selector_map_for_widget( $widget_id );

		$element->start_controls_section(
			self::SECTION_ID,
			array(
				'label' => __( 'KDNA Glass Effect', 'kdna-glass-effect' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		// Hidden flag control, used only to detect we've already injected
		// the section on this element.
		$element->add_control(
			self::SECTION_ID . '_injected_flag',
			array(
				'type'    => Controls_Manager::HIDDEN,
				'default' => '1',
			)
		);

		// 5.1 Master toggle.
		$element->add_control(
			self::PREFIX . 'enable',
			array(
				'label'        => __( 'Enable Glass Effect', 'kdna-glass-effect' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'On', 'kdna-glass-effect' ),
				'label_off'    => __( 'Off', 'kdna-glass-effect' ),
				'return_value' => 'yes',
				'default'      => '',
				'separator'    => 'before',
			)
		);

		// 5.2 Apply To.
		$element->add_control(
			self::PREFIX . 'apply_to',
			array(
				'label'     => __( 'Apply To', 'kdna-glass-effect' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => $apply_options,
				'default'   => $default_apply,
				'condition' => array( self::PREFIX . 'enable' => 'yes' ),
			)
		);

		// 5.2 Preset.
		$element->add_control(
			self::PREFIX . 'preset',
			array(
				'label'     => __( 'Preset', 'kdna-glass-effect' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'custom',
				'options'   => array(
					'custom'        => __( 'Custom', 'kdna-glass-effect' ),
					'clear'         => __( 'Clear', 'kdna-glass-effect' ),
					'frosted-light' => __( 'Frosted Light', 'kdna-glass-effect' ),
					'frosted-dark'  => __( 'Frosted Dark', 'kdna-glass-effect' ),
					'tinted'        => __( 'Tinted', 'kdna-glass-effect' ),
				),
				'condition' => array( self::PREFIX . 'enable' => 'yes' ),
			)
		);

		// 5.3 Base State Style controls (only visible when preset is custom).
		$this->add_style_controls( $element, 'base', $selector_map );

		// 5.4 Hover override subsection.
		$this->add_state_override_section(
			$element,
			'hover',
			__( 'Hover State', 'kdna-glass-effect' ),
			$selector_map,
			':hover'
		);

		// 5.5 Active override subsection.
		$this->add_state_override_section(
			$element,
			'active',
			__( 'Active State', 'kdna-glass-effect' ),
			$selector_map,
			':active'
		);

		$element->end_controls_section();
	}

	/**
	 * Add the Base State style controls. Shared by the Hover/Active
	 * subsections via the $state argument.
	 *
	 * @param Element_Base $element       Element.
	 * @param string       $state         One of 'base', 'hover', 'active'.
	 * @param array        $selector_map  Apply To => selector string.
	 * @param string       $state_suffix  Optional pseudo suffix to append to selectors (e.g. ':hover').
	 */
	private function add_style_controls( $element, $state, $selector_map, $state_suffix = '' ) {
		$is_base = ( 'base' === $state );
		$prefix  = self::PREFIX . ( $is_base ? '' : $state . '_' );

		// Conditions: for base controls, visible whenever enabled and
		// preset = custom. For hover/active, additionally require the
		// state enable switcher to be on.
		$condition = array(
			self::PREFIX . 'enable' => 'yes',
			self::PREFIX . 'preset' => 'custom',
		);
		if ( ! $is_base ) {
			$condition[ self::PREFIX . 'enable_' . $state ] = 'yes';
		}

		// Tint colour.
		$element->add_control(
			$prefix . 'tint_color',
			array(
				'label'     => __( 'Tint Colour', 'kdna-glass-effect' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => 'rgba(255,255,255,0)',
				'selectors' => $this->build_selectors(
					$selector_map,
					$state_suffix,
					'--kdna-ge-tint-color: {{VALUE}};'
				),
				'condition' => $condition,
			)
		);

		// Tint opacity.
		$element->add_control(
			$prefix . 'tint_opacity',
			array(
				'label'     => __( 'Tint Opacity', 'kdna-glass-effect' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => array(
					'px' => array( 'min' => 0, 'max' => 1, 'step' => 0.01 ),
				),
				'default'   => array(
					'unit' => 'px',
					'size' => 0,
				),
				'selectors' => $this->build_selectors(
					$selector_map,
					$state_suffix,
					'--kdna-ge-tint-opacity: {{SIZE}};'
				),
				'condition' => $condition,
			)
		);

		// Background blur (px).
		$element->add_control(
			$prefix . 'blur',
			array(
				'label'     => __( 'Background Blur', 'kdna-glass-effect' ),
				'type'      => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'     => array(
					'px' => array( 'min' => 0, 'max' => 40, 'step' => 1 ),
				),
				'default'   => array(
					'unit' => 'px',
					'size' => 8,
				),
				'selectors' => $this->build_selectors(
					$selector_map,
					$state_suffix,
					'--kdna-ge-blur: {{SIZE}}px;'
				),
				'condition' => $condition,
			)
		);

		// Edge refraction amount.
		$element->add_control(
			$prefix . 'refraction_scale',
			array(
				'label'     => __( 'Edge Refraction Amount', 'kdna-glass-effect' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => array(
					'px' => array( 'min' => 0, 'max' => 200, 'step' => 1 ),
				),
				'default'   => array(
					'unit' => 'px',
					'size' => 60,
				),
				'selectors' => $this->build_selectors(
					$selector_map,
					$state_suffix,
					'--kdna-ge-refraction-scale: {{SIZE}};'
				),
				'condition' => $condition,
			)
		);

		// Refraction width (%) - how far the displacement band extends
		// in from the edge. 0 = no band (flat centre), 100 = whole
		// element refracts.
		$element->add_control(
			$prefix . 'refraction_width',
			array(
				'label'     => __( 'Refraction Width', 'kdna-glass-effect' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => array(
					'px' => array( 'min' => 0, 'max' => 100, 'step' => 1 ),
				),
				'default'   => array(
					'unit' => 'px',
					'size' => 45,
				),
				'description' => __( 'How far the edge-refraction band extends inward from the border, as a percentage of the element radius.', 'kdna-glass-effect' ),
				'condition' => $condition,
			)
		);

		// Refraction mode - outward (default, pushes background outward),
		// inward (pulls background from outside into the rim, giving a
		// lens effect), or dual (both with a neutral fade between, for
		// the caustic "liquid glass" look).
		$element->add_control(
			$prefix . 'refraction_mode',
			array(
				'label'   => __( 'Refraction Mode', 'kdna-glass-effect' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'outward',
				'options' => array(
					'outward' => __( 'Outward', 'kdna-glass-effect' ),
					'inward'  => __( 'Inward (Lens)', 'kdna-glass-effect' ),
					'dual'    => __( 'Dual (Fade)', 'kdna-glass-effect' ),
				),
				'condition' => $condition,
			)
		);

		// Border radius (responsive dimensions).
		$element->add_responsive_control(
			$prefix . 'radius',
			array(
				'label'      => __( 'Border Radius', 'kdna-glass-effect' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'default'    => array(
					'unit'     => 'px',
					'top'      => 24,
					'right'    => 24,
					'bottom'   => 24,
					'left'     => 24,
					'isLinked' => true,
				),
				'selectors'  => $this->build_selectors(
					$selector_map,
					$state_suffix,
					'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'
				),
				'condition'  => $condition,
			)
		);

		// Rim light colour.
		$element->add_control(
			$prefix . 'rim_color',
			array(
				'label'     => __( 'Rim Light Colour', 'kdna-glass-effect' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => 'rgba(255,255,255,0.6)',
				'selectors' => $this->build_selectors(
					$selector_map,
					$state_suffix,
					'--kdna-ge-rim-color: {{VALUE}};'
				),
				'condition' => $condition,
			)
		);

		// Rim light opacity.
		$element->add_control(
			$prefix . 'rim_opacity',
			array(
				'label'     => __( 'Rim Light Opacity', 'kdna-glass-effect' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => array(
					'px' => array( 'min' => 0, 'max' => 1, 'step' => 0.01 ),
				),
				'default'   => array(
					'unit' => 'px',
					'size' => 0.6,
				),
				'selectors' => $this->build_selectors(
					$selector_map,
					$state_suffix,
					'--kdna-ge-rim-opacity: {{SIZE}};'
				),
				'condition' => $condition,
			)
		);

		// Inner highlight colour.
		$element->add_control(
			$prefix . 'inner_color',
			array(
				'label'     => __( 'Inner Highlight Colour', 'kdna-glass-effect' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => 'rgba(255,255,255,0.18)',
				'selectors' => $this->build_selectors(
					$selector_map,
					$state_suffix,
					'--kdna-ge-inner-color: {{VALUE}};'
				),
				'condition' => $condition,
			)
		);

		// Inner highlight opacity.
		$element->add_control(
			$prefix . 'inner_opacity',
			array(
				'label'     => __( 'Inner Highlight Opacity', 'kdna-glass-effect' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => array(
					'px' => array( 'min' => 0, 'max' => 1, 'step' => 0.01 ),
				),
				'default'   => array(
					'unit' => 'px',
					'size' => 0.18,
				),
				'selectors' => $this->build_selectors(
					$selector_map,
					$state_suffix,
					'--kdna-ge-inner-opacity: {{SIZE}};'
				),
				'condition' => $condition,
			)
		);

		// Border colour.
		$element->add_control(
			$prefix . 'border_color',
			array(
				'label'     => __( 'Border Colour', 'kdna-glass-effect' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => 'rgba(255,255,255,0.3)',
				'selectors' => $this->build_selectors(
					$selector_map,
					$state_suffix,
					'--kdna-ge-border-color: {{VALUE}};'
				),
				'condition' => $condition,
			)
		);

		// Border width, responsive per-side dimensions so each edge can
		// be sized independently (e.g. a thick bottom + thin top rim).
		$element->add_responsive_control(
			$prefix . 'border_width',
			array(
				'label'      => __( 'Border Width', 'kdna-glass-effect' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px' ),
				'default'    => array(
					'unit'     => 'px',
					'top'      => 1,
					'right'    => 1,
					'bottom'   => 1,
					'left'     => 1,
					'isLinked' => true,
				),
				'selectors'  => $this->build_selectors(
					$selector_map,
					$state_suffix,
					'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'
				),
				'condition'  => $condition,
			)
		);

		// Transition duration (ms). Only emitted on base state.
		if ( $is_base ) {
			$element->add_control(
				$prefix . 'transition',
				array(
					'label'      => __( 'Transition Duration', 'kdna-glass-effect' ),
					'type'       => Controls_Manager::SLIDER,
					'size_units' => array( 'ms' ),
					'range'      => array(
						'ms' => array( 'min' => 0, 'max' => 800, 'step' => 10 ),
					),
					'default'    => array(
						'unit' => 'ms',
						'size' => 250,
					),
					'selectors'  => $this->build_selectors(
						$selector_map,
						$state_suffix,
						'--kdna-ge-transition: {{SIZE}}ms;'
					),
					'condition'  => $condition,
				)
			);
		}
	}

	/**
	 * Add a state override subsection (Hover or Active).
	 *
	 * @param Element_Base $element      Element.
	 * @param string       $state        'hover' or 'active'.
	 * @param string       $label        Subsection heading label.
	 * @param array        $selector_map Apply To => selector string.
	 * @param string       $pseudo       Pseudo-class suffix (:hover / :active).
	 */
	private function add_state_override_section( $element, $state, $label, $selector_map, $pseudo ) {
		$element->add_control(
			self::PREFIX . $state . '_heading',
			array(
				'label'     => $label,
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array( self::PREFIX . 'enable' => 'yes' ),
			)
		);

		$element->add_control(
			self::PREFIX . 'enable_' . $state,
			array(
				'label'        => sprintf(
					/* translators: %s: state name, e.g. Hover */
					__( 'Enable %s Override', 'kdna-glass-effect' ),
					$label
				),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'On', 'kdna-glass-effect' ),
				'label_off'    => __( 'Off', 'kdna-glass-effect' ),
				'return_value' => 'yes',
				'default'      => '',
				'condition'    => array( self::PREFIX . 'enable' => 'yes' ),
			)
		);

		$this->add_style_controls( $element, $state, $selector_map, $pseudo );
	}

	/**
	 * Build the selectors array for a control, expanding the Apply To
	 * conditional map into one CSS entry per Apply To option.
	 *
	 * @param array  $selector_map  Apply To key => selector string.
	 * @param string $state_suffix  Pseudo-class suffix (may be empty).
	 * @param string $declaration   CSS declaration with Elementor placeholders.
	 * @return array                Elementor selectors array.
	 */
	private function build_selectors( $selector_map, $state_suffix, $declaration ) {
		$out = array();
		foreach ( $selector_map as $apply_to => $selector ) {
			$full_selector = $selector . $state_suffix;
			$out[ $full_selector ] = $this->conditional_declaration( $apply_to, $declaration );
		}
		return $out;
	}

	/**
	 * Wrap a CSS declaration so it is only emitted when the widget's
	 * Apply To setting matches. Elementor applies selectors entries
	 * unconditionally; to gate on Apply To we use a selector-level
	 * condition via the selectors key's conditions argument isn't
	 * available here, so we instead use the selectors array alongside
	 * a broader control condition. In practice each selectors key is
	 * a distinct selector string so all declarations are emitted to
	 * their respective selectors; the Apply To control only affects
	 * which element receives the .kdna-ge class at render time.
	 *
	 * @param string $apply_to    Apply To key (unused at this stage).
	 * @param string $declaration CSS declaration.
	 * @return string
	 */
	private function conditional_declaration( $apply_to, $declaration ) {
		unset( $apply_to );
		return $declaration;
	}
}
