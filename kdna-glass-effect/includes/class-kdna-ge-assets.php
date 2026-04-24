<?php
/**
 * Assets layer for the KDNA Glass Effect plugin.
 *
 * Conditionally enqueues the main stylesheet and emits a single shared
 * SVG filter to wp_footer, but only when at least one glass widget has
 * rendered on the current request (checked via KDNA_GE_Render::has_rendered()).
 *
 * @package KDNA_Glass_Effect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KDNA_GE_Assets
 */
class KDNA_GE_Assets {

	const HANDLE        = 'kdna-glass-effect';
	const EDITOR_HANDLE = 'kdna-glass-effect-editor';
	const FILTER_ID     = 'kdna-ge-filter';

	/**
	 * Register asset-side hooks.
	 */
	public function register_hooks() {
		// Register the stylesheet early so we can wp_enqueue_style at
		// any point once the rendered flag is set.
		add_action( 'wp_enqueue_scripts', array( $this, 'register_stylesheet' ), 5 );

		// Late enqueue: after elements have had a chance to render into
		// the page, if any glass widget rendered, enqueue the stylesheet.
		add_action( 'wp_footer', array( $this, 'maybe_enqueue_stylesheet' ), 5 );

		// Footer-time SVG filter, guarded by the rendered flag.
		add_action( 'wp_footer', array( $this, 'maybe_render_svg_filter' ), 20 );

		// Elementor editor: stylesheet in the preview iframe so the
		// effect is visible while editing.
		add_action( 'elementor/preview/enqueue_styles', array( $this, 'enqueue_for_preview' ) );

		// Editor chrome (parent document): JS helper that injects the
		// shared SVG filter into the preview iframe document, which
		// does not always receive the wp_footer SVG emit.
		add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'enqueue_editor_script' ) );
	}

	/**
	 * Register the stylesheet. Enqueue is deferred to maybe_enqueue_stylesheet().
	 */
	public function register_stylesheet() {
		wp_register_style(
			self::HANDLE,
			KDNA_GE_URL . 'assets/css/kdna-glass-effect.css',
			array(),
			KDNA_GE_VERSION
		);
	}

	/**
	 * Enqueue the stylesheet at footer-time when glass has rendered.
	 */
	public function maybe_enqueue_stylesheet() {
		if ( ! KDNA_GE_Render::has_rendered() ) {
			return;
		}
		wp_enqueue_style( self::HANDLE );
	}

	/**
	 * Output the shared SVG filter into wp_footer when glass has rendered.
	 *
	 * Uses an inline data URL in feImage containing a pre-baked radial
	 * displacement gradient. R channel drives X displacement, G channel
	 * drives Y displacement. Centre is neutral grey (no displacement),
	 * edges push toward maximum R/G to produce edge-only refraction.
	 */
	public function maybe_render_svg_filter() {
		if ( ! KDNA_GE_Render::has_rendered() ) {
			return;
		}
		echo self::svg_filter_markup( KDNA_GE_Render::get_filter_variants() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Audited markup with attr-escaped data URLs.
	}

	/**
	 * Build the full <svg>...</svg> filter wrapper as a string.
	 *
	 * Emits a default filter (#kdna-ge-filter) plus one filter per
	 * unique (scale, detail) combo recorded by KDNA_GE_Render. The
	 * per-variant filter IDs follow the pattern
	 * `kdna-ge-filter-{scale}-{detail*1000}` and are targeted from the
	 * element's `--kdna-ge-filter-ref` inline style.
	 *
	 * Shared by the wp_footer emit and by the editor JS helper so the
	 * two code paths cannot drift apart.
	 *
	 * @param array $variants Variants map from KDNA_GE_Render.
	 * @return string
	 */
	public static function svg_filter_markup( $variants = array() ) {
		$svg  = '<svg xmlns="http://www.w3.org/2000/svg" style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true" focusable="false">';
		$svg .= '<defs>';

		// Default fallback filter, used when no variant has been
		// registered yet (e.g. editor preview before any glass widget
		// renders).
		$svg .= self::single_filter_markup( self::FILTER_ID, 90, 0.02 );

		foreach ( $variants as $id => $variant ) {
			$filter_id = 'kdna-ge-filter-' . $id;
			$svg      .= self::single_filter_markup( $filter_id, $variant['scale'], $variant['detail'] );
		}

		$svg .= '</defs>';
		$svg .= '</svg>';

		return $svg;
	}

	/**
	 * Build a single <filter> element with the given ID, scale, and
	 * detail (gradient inner-stop position).
	 *
	 * @param string $filter_id DOM ID for the filter.
	 * @param int    $scale     feDisplacementMap scale attribute.
	 * @param float  $detail    Gradient detail, 0.005..0.1. Higher =
	 *                          narrower neutral core / sharper rim.
	 * @return string
	 */
	private static function single_filter_markup( $filter_id, $scale, $detail ) {
		$scale    = max( 0, min( 200, (int) round( $scale ) ) );
		$data_url = self::displacement_data_url( $detail );

		return '<filter id="' . esc_attr( $filter_id ) . '" x="0%" y="0%" width="100%" height="100%" color-interpolation-filters="sRGB">'
			. '<feImage href="' . esc_attr( $data_url ) . '" xlink:href="' . esc_attr( $data_url ) . '" result="kdnaGeDisplacement" preserveAspectRatio="none" />'
			. '<feDisplacementMap in="SourceGraphic" in2="kdnaGeDisplacement" scale="' . esc_attr( (string) $scale ) . '" xChannelSelector="R" yChannelSelector="G" />'
			. '</filter>';
	}

	/**
	 * Enqueue the stylesheet inside the Elementor editor preview iframe.
	 */
	public function enqueue_for_preview() {
		// Register may not have run yet in the preview context.
		if ( ! wp_style_is( self::HANDLE, 'registered' ) ) {
			$this->register_stylesheet();
		}
		wp_enqueue_style( self::HANDLE );
	}

	/**
	 * Enqueue the editor-only helper script in the Elementor editor
	 * parent document. The script locates the preview iframe and
	 * injects the SVG filter definition into it.
	 */
	public function enqueue_editor_script() {
		wp_enqueue_script(
			self::EDITOR_HANDLE,
			KDNA_GE_URL . 'assets/js/kdna-glass-effect-editor.js',
			array(),
			KDNA_GE_VERSION,
			true
		);

		wp_localize_script(
			self::EDITOR_HANDLE,
			'kdnaGeEditorData',
			array(
				'filterId' => self::FILTER_ID,
				'svg'      => self::svg_filter_markup(),
			)
		);
	}

	/**
	 * Build the data URL for the displacement gradient SVG.
	 *
	 * The gradient is a 100x100 radial with a flat grey core out to
	 * ~35% radius (neutral = no displacement), transitioning to high R
	 * at the rim for outward displacement. The exact stops here are a
	 * sensible starting point; Session 3 calibrates them against the
	 * Focus button reference.
	 *
	 * @return string
	 */
	public static function displacement_data_url( $detail = 0.02 ) {
		// Detail (0.005..0.1) maps to the inner-stop position (20..65%).
		// Higher detail = sharper, more rim-concentrated refraction.
		$detail = max( 0.005, min( 0.1, (float) $detail ) );
		$inner  = (int) round( 65 - ( ( $detail - 0.005 ) / 0.095 ) * 45 );

		// Two overlaid radials, one driving R (X axis displacement) and
		// one driving G (Y axis), screen-blended so each pixel carries
		// both an X and Y push outward from centre.
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none">'
			. '<defs>'
			. '<radialGradient id="kdnaGeRadial" cx="50%" cy="50%" r="55%" fx="50%" fy="50%">'
			. '<stop offset="0%" stop-color="rgb(127,127,128)" />'
			. '<stop offset="' . $inner . '%" stop-color="rgb(127,127,128)" />'
			. '<stop offset="95%" stop-color="rgb(235,128,128)" />'
			. '<stop offset="100%" stop-color="rgb(255,128,128)" />'
			. '</radialGradient>'
			. '<radialGradient id="kdnaGeRadialY" cx="50%" cy="50%" r="55%" fx="50%" fy="50%">'
			. '<stop offset="0%" stop-color="rgb(127,127,128)" />'
			. '<stop offset="' . $inner . '%" stop-color="rgb(127,127,128)" />'
			. '<stop offset="95%" stop-color="rgb(127,235,128)" />'
			. '<stop offset="100%" stop-color="rgb(127,255,128)" />'
			. '</radialGradient>'
			. '</defs>'
			. '<rect width="100" height="100" fill="url(#kdnaGeRadial)" />'
			. '<rect width="100" height="100" fill="url(#kdnaGeRadialY)" style="mix-blend-mode:screen" />'
			. '</svg>';

		return 'data:image/svg+xml;utf8,' . rawurlencode( $svg );
	}
}
