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

		// Diagnostic HTML comment (always emitted) so installers can
		// confirm the plugin is loading and inspect render state.
		add_action( 'wp_footer', array( $this, 'diagnostic_comment' ), 4 );

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

		// Also inline the stylesheet contents directly, so the effect
		// is not dependent on the external CSS file being reachable
		// (some server setups block or 404 plugin asset URLs).
		$css_path = KDNA_GE_DIR . 'assets/css/kdna-glass-effect.css';
		if ( is_readable( $css_path ) ) {
			$css = file_get_contents( $css_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false !== $css ) {
				printf(
					"<style id=\"kdna-glass-effect-inline\">\n%s\n</style>\n",
					$css // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				);
			}
		}
	}

	/**
	 * Emit a short diagnostic HTML comment into wp_footer every
	 * request. Helps installers confirm the plugin is actually running
	 * and see whether any glass widgets flipped the render flag.
	 */
	public function diagnostic_comment() {
		$rendered = KDNA_GE_Render::has_rendered() ? 'yes' : 'no';
		$variants = KDNA_GE_Render::get_filter_variants();
		printf(
			'<!-- KDNA Glass Effect v%s | rendered=%s | variants=%d -->' . "\n",
			esc_html( KDNA_GE_VERSION ),
			esc_html( $rendered ),
			count( $variants )
		);
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
		$svg .= self::single_filter_markup( self::FILTER_ID, 90, 70, 'dual' );

		foreach ( $variants as $id => $variant ) {
			$filter_id = 'kdna-ge-filter-' . $id;
			$mode      = isset( $variant['mode'] ) ? $variant['mode'] : 'outward';
			$width     = isset( $variant['width'] ) ? $variant['width'] : 45;
			$svg      .= self::single_filter_markup( $filter_id, $variant['scale'], $width, $mode );
		}

		$svg .= '</defs>';
		$svg .= '</svg>';

		return $svg;
	}

	/**
	 * Build a single <filter> element with the given ID, scale, width,
	 * and mode. Mode determines the gradient direction:
	 *  - 'outward' : rim pushes content away from centre (default).
	 *  - 'inward'  : rim pulls content toward centre (lens effect).
	 *  - 'dual'    : outward near the rim, neutral mid-band, inward
	 *                just inside the band, with smooth fades — the
	 *                caustic liquid-glass look.
	 *
	 * @param string $filter_id DOM ID for the filter.
	 * @param int    $scale     feDisplacementMap scale attribute.
	 * @param float  $width     Refraction band width percentage (0..100).
	 * @param string $mode      'outward' | 'inward' | 'dual'.
	 * @return string
	 */
	private static function single_filter_markup( $filter_id, $scale, $width, $mode ) {
		$scale    = max( 0, min( 200, (int) round( $scale ) ) );
		$data_url = self::displacement_data_url( $width, $mode );

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
	public static function displacement_data_url( $width = 70, $mode = 'dual' ) {
		// Band spans from (100 - width)% out to 100% radius.
		$width = max( 0, min( 100, (float) $width ) );
		$inner = (int) round( max( 0, 100 - $width ) ); // inner edge of band
		$mid   = (int) round( $inner + ( $width * 0.55 ) ); // neutral midband for dual mode

		// Outward mode: peak at rim pulls toward +R/+G (push away from
		// centre). Inward mode: peak at rim pulls toward 0/0 (pull
		// from outside into rim). Dual mode: outward near rim with a
		// neutral fade, inward just inside.
		if ( 'inward' === $mode ) {
			$x_stops = '<stop offset="0%" stop-color="rgb(127,127,128)" />'
				. '<stop offset="' . $inner . '%" stop-color="rgb(127,127,128)" />'
				. '<stop offset="95%" stop-color="rgb(20,128,128)" />'
				. '<stop offset="100%" stop-color="rgb(0,128,128)" />';
			$y_stops = '<stop offset="0%" stop-color="rgb(127,127,128)" />'
				. '<stop offset="' . $inner . '%" stop-color="rgb(127,127,128)" />'
				. '<stop offset="95%" stop-color="rgb(127,20,128)" />'
				. '<stop offset="100%" stop-color="rgb(127,0,128)" />';
		} elseif ( 'dual' === $mode ) {
			$x_stops = '<stop offset="0%" stop-color="rgb(127,127,128)" />'
				. '<stop offset="' . $inner . '%" stop-color="rgb(127,127,128)" />'
				. '<stop offset="' . ( $inner + 5 ) . '%" stop-color="rgb(40,128,128)" />'
				. '<stop offset="' . $mid . '%" stop-color="rgb(127,127,128)" />'
				. '<stop offset="95%" stop-color="rgb(235,128,128)" />'
				. '<stop offset="100%" stop-color="rgb(255,128,128)" />';
			$y_stops = '<stop offset="0%" stop-color="rgb(127,127,128)" />'
				. '<stop offset="' . $inner . '%" stop-color="rgb(127,127,128)" />'
				. '<stop offset="' . ( $inner + 5 ) . '%" stop-color="rgb(127,40,128)" />'
				. '<stop offset="' . $mid . '%" stop-color="rgb(127,127,128)" />'
				. '<stop offset="95%" stop-color="rgb(127,235,128)" />'
				. '<stop offset="100%" stop-color="rgb(127,255,128)" />';
		} else {
			// outward
			$x_stops = '<stop offset="0%" stop-color="rgb(127,127,128)" />'
				. '<stop offset="' . $inner . '%" stop-color="rgb(127,127,128)" />'
				. '<stop offset="95%" stop-color="rgb(235,128,128)" />'
				. '<stop offset="100%" stop-color="rgb(255,128,128)" />';
			$y_stops = '<stop offset="0%" stop-color="rgb(127,127,128)" />'
				. '<stop offset="' . $inner . '%" stop-color="rgb(127,127,128)" />'
				. '<stop offset="95%" stop-color="rgb(127,235,128)" />'
				. '<stop offset="100%" stop-color="rgb(127,255,128)" />';
		}

		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none">'
			. '<defs>'
			. '<radialGradient id="kdnaGeRadial" cx="50%" cy="50%" r="55%" fx="50%" fy="50%">' . $x_stops . '</radialGradient>'
			. '<radialGradient id="kdnaGeRadialY" cx="50%" cy="50%" r="55%" fx="50%" fy="50%">' . $y_stops . '</radialGradient>'
			. '</defs>'
			. '<rect width="100" height="100" fill="url(#kdnaGeRadial)" />'
			. '<rect width="100" height="100" fill="url(#kdnaGeRadialY)" style="mix-blend-mode:screen" />'
			. '</svg>';

		return 'data:image/svg+xml;utf8,' . rawurlencode( $svg );
	}
}
