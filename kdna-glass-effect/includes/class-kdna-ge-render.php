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
	 * Set of refraction variants in use on this page, keyed by a
	 * deterministic variant ID. Each entry is [ 'scale' => int,
	 * 'detail' => float ]. Consumed by KDNA_GE_Assets to emit one
	 * SVG filter per unique combo.
	 *
	 * @var array<string, array>
	 */
	private static $filter_variants = array();

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
	 * Public accessor for the set of unique refraction variants in
	 * use on this page. Consumed by KDNA_GE_Assets to emit per-variant
	 * SVG filters.
	 *
	 * @return array<string, array>
	 */
	public static function get_filter_variants() {
		return self::$filter_variants;
	}

	/**
	 * Compute a deterministic filter variant ID for a given
	 * (scale, detail) pair. Same pair always yields the same ID so
	 * widgets sharing values share a single SVG filter definition.
	 *
	 * @param int   $scale  feDisplacementMap scale.
	 * @param float $detail Gradient detail / inner-stop position.
	 * @return string
	 */
	public static function variant_id( $scale, $detail ) {
		$scale_i  = max( 0, min( 200, (int) round( $scale ) ) );
		$detail_i = max( 5, min( 100, (int) round( $detail * 1000 ) ) );
		return $scale_i . '-' . $detail_i;
	}

	/**
	 * Register a variant for later footer emit. Returns the variant ID.
	 *
	 * @param int   $scale  feDisplacementMap scale.
	 * @param float $detail Gradient detail.
	 * @return string
	 */
	private function register_variant( $scale, $detail ) {
		$id = self::variant_id( $scale, $detail );
		if ( ! isset( self::$filter_variants[ $id ] ) ) {
			self::$filter_variants[ $id ] = array(
				'scale'  => (int) round( $scale ),
				'detail' => (float) $detail,
			);
		}
		return $id;
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

		$classes    = $this->classes_for_settings( $settings );
		$variant_id = $this->register_variant( $settings['scale'], $settings['detail'] );
		$style      = $this->build_inline_style( $settings, $variant_id );
		$element->add_render_attribute( '_wrapper', 'class', $classes );
		$element->add_render_attribute( '_wrapper', 'style', $style );
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

		$added      = implode( ' ', $this->classes_for_settings( $settings ) );
		$variant_id = $this->register_variant( $settings['scale'], $settings['detail'] );
		$style_frag = $this->build_inline_style( $settings, $variant_id );

		// Match the target class with boundaries that treat hyphens as
		// class-name characters, so `elementor-button` does not match
		// inside `elementor-button-wrapper` or `elementor-button-link`.
		$pattern = '/(<[^>]*?class\s*=\s*["\'][^"\']*?(?<![\w-])' . preg_quote( $inner_class, '/' ) . '(?![\w-]))([^"\']*["\'])([^>]*)>/';

		$modified = preg_replace_callback(
			$pattern,
			function ( $m ) use ( $added, $style_frag ) {
				// m[1] = up to and including the target class,
				// m[2] = remainder of class attribute value,
				// m[3] = rest of tag attributes before '>'.
				$class_part = $m[1] . ' ' . $added . $m[2];
				$rest       = $m[3];

				// Inject the filter-ref into an existing style= attr
				// if present, otherwise append a new style attribute.
				if ( preg_match( '/style\s*=\s*(["\'])([^"\']*)\1/', $rest ) ) {
					$rest = preg_replace(
						'/(style\s*=\s*(["\']))([^"\']*)(\2)/',
						'$1$3 ' . $style_frag . '$4',
						$rest,
						1
					);
				} else {
					$rest .= ' style="' . $style_frag . '"';
				}

				return $class_part . $rest . '>';
			},
			$content,
			1
		);

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

		$scale  = isset( $settings['kdna_ge_refraction_scale']['size'] ) ? (float) $settings['kdna_ge_refraction_scale']['size'] : 90.0;
		$detail = isset( $settings['kdna_ge_refraction_detail']['size'] ) ? (float) $settings['kdna_ge_refraction_detail']['size'] : 0.02;

		return array(
			'apply_to' => isset( $settings['kdna_ge_apply_to'] ) ? $settings['kdna_ge_apply_to'] : KDNA_GE_Targets::default_apply_to( $widget_id ),
			'preset'   => isset( $settings['kdna_ge_preset'] ) ? $settings['kdna_ge_preset'] : 'custom',
			'hover'    => ! empty( $settings['kdna_ge_enable_hover'] ) && 'yes' === $settings['kdna_ge_enable_hover'],
			'active'   => ! empty( $settings['kdna_ge_enable_active'] ) && 'yes' === $settings['kdna_ge_enable_active'],
			'scale'    => $scale,
			'detail'   => $detail,
			'raw'      => $settings,
		);
	}

	/**
	 * Build a full inline-style declaration string for an element
	 * with glass enabled. Emits every CSS variable the stylesheet
	 * consumes (tint, blur, rim, inner, border, transition) plus
	 * border-radius as a direct property, based on the widget's own
	 * saved values. Bypasses Elementor's per-widget CSS file cache so
	 * the effect works on first install without a Regenerate-CSS pass.
	 *
	 * @param array  $settings    Resolved glass settings.
	 * @param string $variant_id  Variant ID for the filter reference.
	 * @return string CSS declaration list (no surrounding braces).
	 */
	private function build_inline_style( $settings, $variant_id ) {
		$raw = isset( $settings['raw'] ) ? $settings['raw'] : array();

		$decls = array();
		$decls[] = '--kdna-ge-filter-ref:url(#kdna-ge-filter-' . $variant_id . ')';

		if ( ! empty( $raw['kdna_ge_tint_color'] ) ) {
			$decls[] = '--kdna-ge-tint-color:' . $raw['kdna_ge_tint_color'];
		}
		if ( isset( $raw['kdna_ge_tint_opacity']['size'] ) ) {
			$decls[] = '--kdna-ge-tint-opacity:' . (float) $raw['kdna_ge_tint_opacity']['size'];
		}
		if ( isset( $raw['kdna_ge_blur']['size'] ) ) {
			$decls[] = '--kdna-ge-blur:' . (float) $raw['kdna_ge_blur']['size'] . 'px';
		}
		if ( ! empty( $raw['kdna_ge_rim_color'] ) ) {
			$decls[] = '--kdna-ge-rim-color:' . $raw['kdna_ge_rim_color'];
		}
		if ( isset( $raw['kdna_ge_rim_opacity']['size'] ) ) {
			$decls[] = '--kdna-ge-rim-opacity:' . (float) $raw['kdna_ge_rim_opacity']['size'];
		}
		if ( ! empty( $raw['kdna_ge_inner_color'] ) ) {
			$decls[] = '--kdna-ge-inner-color:' . $raw['kdna_ge_inner_color'];
		}
		if ( isset( $raw['kdna_ge_inner_opacity']['size'] ) ) {
			$decls[] = '--kdna-ge-inner-opacity:' . (float) $raw['kdna_ge_inner_opacity']['size'];
		}
		if ( ! empty( $raw['kdna_ge_border_color'] ) ) {
			$decls[] = '--kdna-ge-border-color:' . $raw['kdna_ge_border_color'];
		}
		if ( isset( $raw['kdna_ge_border_width']['size'] ) ) {
			$decls[] = '--kdna-ge-border-width:' . (float) $raw['kdna_ge_border_width']['size'] . 'px';
		}
		if ( isset( $raw['kdna_ge_transition']['size'] ) ) {
			$decls[] = '--kdna-ge-transition:' . (float) $raw['kdna_ge_transition']['size'] . 'ms';
		}

		// Border radius: responsive dimensions, desktop values.
		if ( isset( $raw['kdna_ge_radius'] ) && is_array( $raw['kdna_ge_radius'] ) ) {
			$r = $raw['kdna_ge_radius'];
			if ( isset( $r['top'], $r['right'], $r['bottom'], $r['left'] ) ) {
				$unit = ! empty( $r['unit'] ) ? $r['unit'] : 'px';
				$decls[] = sprintf(
					'border-radius:%s%s %s%s %s%s %s%s',
					$r['top'], $unit,
					$r['right'], $unit,
					$r['bottom'], $unit,
					$r['left'], $unit
				);
			}
		}

		return implode( ';', $decls ) . ';';
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
