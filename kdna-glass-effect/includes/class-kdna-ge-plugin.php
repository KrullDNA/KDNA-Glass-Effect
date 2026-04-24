<?php
/**
 * Main plugin bootstrap.
 *
 * Verifies the runtime environment (Elementor active, minimum versions)
 * and instantiates the controls injection layer. Elementor hooks are
 * registered at file load time via this class's constructor, not inside
 * an elementor/loaded callback.
 *
 * @package KDNA_Glass_Effect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KDNA_GE_Plugin
 */
final class KDNA_GE_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var KDNA_GE_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Controls injector.
	 *
	 * @var KDNA_GE_Controls|null
	 */
	private $controls = null;

	/**
	 * Render layer.
	 *
	 * @var KDNA_GE_Render|null
	 */
	private $render = null;

	/**
	 * Assets layer.
	 *
	 * @var KDNA_GE_Assets|null
	 */
	private $assets = null;

	/**
	 * Singleton accessor.
	 *
	 * @return KDNA_GE_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Registers activation/deactivation hooks and wires
	 * Elementor-facing hooks directly, before elementor/loaded fires.
	 */
	private function __construct() {
		register_activation_hook( KDNA_GE_FILE, array( $this, 'on_activate' ) );

		// Load the controls injector at plugins_loaded so Elementor's class
		// is definitely available, but still before Elementor's init runs.
		add_action( 'plugins_loaded', array( $this, 'init' ), 20 );

		// Admin notice if Elementor is missing or out of date.
		add_action( 'admin_notices', array( $this, 'maybe_render_admin_notice' ) );
	}

	/**
	 * Activation check. Does nothing persistent, the plugin stores no
	 * options. Simply deactivates itself if Elementor is absent.
	 */
	public function on_activate() {
		if ( ! $this->elementor_is_ready() ) {
			deactivate_plugins( plugin_basename( KDNA_GE_FILE ) );
			wp_die(
				esc_html__( 'KDNA Glass Effect requires Elementor 3.20 or later to be installed and active.', 'kdna-glass-effect' ),
				esc_html__( 'Plugin dependency check', 'kdna-glass-effect' ),
				array( 'back_link' => true )
			);
		}
	}

	/**
	 * Boot the controls layer once Elementor is confirmed loaded.
	 */
	public function init() {
		if ( ! $this->elementor_is_ready() ) {
			return;
		}

		$this->controls = new KDNA_GE_Controls();
		$this->controls->register_hooks();

		$this->render = new KDNA_GE_Render();
		$this->render->register_hooks();

		$this->assets = new KDNA_GE_Assets();
		$this->assets->register_hooks();
	}

	/**
	 * Elementor readiness check.
	 *
	 * @return bool
	 */
	private function elementor_is_ready() {
		if ( ! did_action( 'elementor/loaded' ) && ! defined( 'ELEMENTOR_VERSION' ) ) {
			// plugins_loaded may run before ELEMENTOR_VERSION is defined, so
			// also check class existence.
			if ( ! class_exists( '\Elementor\Plugin' ) ) {
				return false;
			}
		}

		if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, KDNA_GE_MIN_ELEMENTOR_VERSION, '<' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Admin notice when Elementor is inactive or below the minimum version.
	 */
	public function maybe_render_admin_notice() {
		if ( $this->elementor_is_ready() ) {
			return;
		}

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$message = sprintf(
			/* translators: %s: minimum Elementor version */
			esc_html__( 'KDNA Glass Effect requires Elementor %s or later to be installed and active. The plugin will not run until Elementor is available.', 'kdna-glass-effect' ),
			KDNA_GE_MIN_ELEMENTOR_VERSION
		);

		printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html( $message ) );
	}
}
