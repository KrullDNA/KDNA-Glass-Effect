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

	const HANDLE    = 'kdna-glass-effect';
	const FILTER_ID = 'kdna-ge-filter';

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

		// Elementor editor: enqueue the stylesheet in the editor preview
		// unconditionally so the effect is visible while editing.
		add_action( 'elementor/preview/enqueue_styles', array( $this, 'enqueue_for_preview' ) );
		add_action( 'elementor/preview/enqueue_scripts', array( $this, 'enqueue_for_preview' ) );
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

		$data_url = self::displacement_data_url();
		$filter   = self::FILTER_ID;

		// The filter is emitted once per request. x/y/width/height are
		// percentages so the filter sizes to each host element.
		echo '<svg xmlns="http://www.w3.org/2000/svg" style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true" focusable="false">';
		echo '<defs>';
		echo '<filter id="' . esc_attr( $filter ) . '" x="0%" y="0%" width="100%" height="100%" color-interpolation-filters="sRGB">';
		echo '<feImage href="' . esc_attr( $data_url ) . '" xlink:href="' . esc_attr( $data_url ) . '" result="kdnaGeDisplacement" preserveAspectRatio="none" />';
		echo '<feDisplacementMap in="SourceGraphic" in2="kdnaGeDisplacement" scale="60" xChannelSelector="R" yChannelSelector="G" />';
		echo '</filter>';
		echo '</defs>';
		echo '</svg>';
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
	public static function displacement_data_url() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none">'
			. '<defs>'
			. '<radialGradient id="kdnaGeRadial" cx="50%" cy="50%" r="60%" fx="50%" fy="50%">'
			. '<stop offset="0%" stop-color="rgb(127,127,128)" />'
			. '<stop offset="35%" stop-color="rgb(127,127,128)" />'
			. '<stop offset="100%" stop-color="rgb(255,128,128)" />'
			. '</radialGradient>'
			. '<radialGradient id="kdnaGeRadialY" cx="50%" cy="50%" r="60%" fx="50%" fy="50%">'
			. '<stop offset="0%" stop-color="rgb(127,127,128)" />'
			. '<stop offset="35%" stop-color="rgb(127,127,128)" />'
			. '<stop offset="100%" stop-color="rgb(127,255,128)" />'
			. '</radialGradient>'
			. '</defs>'
			. '<rect width="100" height="100" fill="url(#kdnaGeRadial)" />'
			. '<rect width="100" height="100" fill="url(#kdnaGeRadialY)" style="mix-blend-mode:screen" />'
			. '</svg>';

		return 'data:image/svg+xml;utf8,' . rawurlencode( $svg );
	}
}
