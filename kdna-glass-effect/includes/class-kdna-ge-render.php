<?php
/**
 * Render layer for the KDNA Glass Effect plugin.
 *
 * Hooks Elementor's render pipeline and injects the kdna-ge class (plus
 * state modifiers and preset classes) onto the correct target element
 * based on each widget's Apply To setting. Also flips a plugin-level
 * rendered flag the first time any glass widget is rendered, so
 * stylesheet + SVG filter assets can be loaded conditionally.
 *
 * @package KDNA_Glass_Effect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KDNA_GE_Render
 */
class KDNA_GE_Render {

	const BASE_CLASS   = 'kdna-ge';
	const HOVER_CLASS  = 'kdna-ge--has-hover';
	const ACTIVE_CLASS = 'kdna-ge--has-active';
	const PRESET_CLASS = 'kdna-ge-preset-';

	/**
	 * Whether at least one glass widget has rendered on this request.
	 *
	 * Checked by KDNA_GE_Assets to decide whether to enqueue the
	 * stylesheet and emit the shared SVG filter.
	 *
	 * @var bool
	 */
	private static $rendered = false;

	/**
	 * Register Elementor render-side hooks.
	 */
	public function register_hooks() {
		// Wrapper-level injection: widgets, containers, sections, columns.
		add_action( 'elementor/frontend/widget/before_render', array( $this, 'before_element_render' ) );
		add_action( 'elementor/frontend/container/before_render', array( $this, 'before_element_render' ) );
		add_action( 'elementor/frontend/section/before_render', array( $this, 'before_element_render' ) );
		add_action( 'elementor/frontend/column/before_render', array( $this, 'before_element_render' ) );

		// Inner-element injection (Button element, Heading text container)
		// is done by filtering the widget render content.
		add_filter( 'elementor/widget/render_content', array( $this, 'filter_widget_content' ), 10, 2 );
	}

	/**
	 * Public accessor for the rendered flag.
	 *
	 * @return bool
	 */
	public static function has_rendered() {
		return self::$rendered;
	}

	/**
	 * Fires before each element renders. Adds the glass classes to the
	 * widget wrapper when Apply To resolves to the wrapper.
	 *
	 * @param \Elementor\Element_Base $element Element being rendered.
	 */
	public function before_element_render( $element ) {
		$settings = $this->glass_settings( $element );
		if ( null === $settings ) {
			return;
		}

		// Flag is set for any enabled glass widget, even when the target is
		// inner (the inner branch will have to re-set it; setting here is
		// harmless and ensures wrapper-target widgets always register).
		self::$rendered = true;

		if ( KDNA_GE_Targets::APPLY_TO_WRAPPER !== $settings['apply_to'] ) {
			return;
		}

		$classes = $this->classes_for_settings( $settings );
		$element->add_render_attribute( '_wrapper', 'class', $classes );
	}

	/**
	 * Filter the inner widget content. For Button / Heading widgets with
	 * inner-element targets, inject the glass classes into the target
	 * element's class attribute via a narrow regex replace.
	 *
	 * @param string                 $content Rendered widget content.
	 * @param \Elementor\Widget_Base $widget  Widget instance.
	 * @return string
	 */
	public function filter_widget_content( $content, $widget ) {
		$settings = $this->glass_settings( $widget );
		if ( null === $settings ) {
			return $content;
		}

		self::$rendered = true;

		if ( KDNA_GE_Targets::APPLY_TO_WRAPPER === $settings['apply_to'] ) {
			return $content;
		}

		$inner_class = '';
		if ( KDNA_GE_Targets::APPLY_TO_BUTTON === $settings['apply_to'] ) {
			$inner_class = 'elementor-button';
		} elseif ( KDNA_GE_Targets::APPLY_TO_HEADING === $settings['apply_to'] ) {
			$inner_class = 'elementor-heading-title';
		}

		if ( '' === $inner_class ) {
			return $content;
		}

		$added = implode( ' ', $this->classes_for_settings( $settings ) );

		// Append our classes to the first class attribute that already
		// contains the target class. Regex is anchored to that class
		// literal to avoid mis-matching other elements.
		$pattern = '/(class\s*=\s*["\'][^"\']*?\b' . preg_quote( $inner_class, '/' ) . '\b)([^"\']*["\'])/';
		$replace = '$1 ' . $added . '$2';

		$modified = preg_replace( $pattern, $replace, $content, 1 );
		return ( null === $modified ) ? $content : $modified;
	}

	/**
	 * Resolve the glass settings for an element, or null if disabled /
	 * unsupported.
	 *
	 * @param \Elementor\Element_Base $element Element.
	 * @return array|null Associative array with 'enable', 'apply_to',
	 *                    'preset', 'hover', 'active' keys, or null.
	 */
	private function glass_settings( $element ) {
		if ( ! method_exists( $element, 'get_settings_for_display' ) ) {
			return null;
		}

		$widget_id = method_exists( $element, 'get_name' ) ? $element->get_name() : '';
		if ( ! in_array( $widget_id, KDNA_GE_Targets::supported_widget_ids(), true ) ) {
			return null;
		}

		$settings = $element->get_settings_for_display();

		if ( empty( $settings['kdna_ge_enable'] ) || 'yes' !== $settings['kdna_ge_enable'] ) {
			return null;
		}

		return array(
			'apply_to' => isset( $settings['kdna_ge_apply_to'] ) ? $settings['kdna_ge_apply_to'] : KDNA_GE_Targets::default_apply_to( $widget_id ),
			'preset'   => isset( $settings['kdna_ge_preset'] ) ? $settings['kdna_ge_preset'] : 'custom',
			'hover'    => ! empty( $settings['kdna_ge_enable_hover'] ) && 'yes' === $settings['kdna_ge_enable_hover'],
			'active'   => ! empty( $settings['kdna_ge_enable_active'] ) && 'yes' === $settings['kdna_ge_enable_active'],
		);
	}

	/**
	 * Build the class list to apply, given resolved glass settings.
	 *
	 * @param array $settings Resolved settings from glass_settings().
	 * @return string[]
	 */
	private function classes_for_settings( $settings ) {
		$classes = array( self::BASE_CLASS );

		if ( ! empty( $settings['preset'] ) && 'custom' !== $settings['preset'] ) {
			$classes[] = self::PRESET_CLASS . sanitize_html_class( $settings['preset'] );
		}
		if ( ! empty( $settings['hover'] ) ) {
			$classes[] = self::HOVER_CLASS;
		}
		if ( ! empty( $settings['active'] ) ) {
			$classes[] = self::ACTIVE_CLASS;
		}

		return $classes;
	}
}
