<?php
/**
 * Plugin Name: KDNA Glass Effect
 * Plugin URI:  https://kdna.com.au/
 * Description: Injects a Glass Effect background control set into every supported Elementor widget, producing a frosted glass aesthetic with edge refraction, backdrop blur, rim lighting, and inner highlights. Zero front-end cost when disabled.
 * Version:     0.1.1
 * Author:      KDNA
 * Author URI:  https://kdna.com.au/
 * Text Domain: kdna-glass-effect
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Elementor tested up to: 3.23.0
 * Elementor Pro tested up to: 3.23.0
 *
 * @package KDNA_Glass_Effect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KDNA_GE_VERSION', '0.1.1' );
define( 'KDNA_GE_FILE', __FILE__ );
define( 'KDNA_GE_DIR', plugin_dir_path( __FILE__ ) );
define( 'KDNA_GE_URL', plugin_dir_url( __FILE__ ) );
define( 'KDNA_GE_MIN_ELEMENTOR_VERSION', '3.20.0' );
define( 'KDNA_GE_MIN_PHP_VERSION', '7.4' );

require_once KDNA_GE_DIR . 'includes/class-kdna-ge-targets.php';
require_once KDNA_GE_DIR . 'includes/class-kdna-ge-controls.php';
require_once KDNA_GE_DIR . 'includes/class-kdna-ge-render.php';
require_once KDNA_GE_DIR . 'includes/class-kdna-ge-assets.php';
require_once KDNA_GE_DIR . 'includes/class-kdna-ge-plugin.php';

/**
 * Bootstrap.
 *
 * Elementor hooks must be registered at file load time, not inside an
 * elementor/loaded callback, because elementor/loaded may have already
 * fired by the time this file runs, which would cause silent hook
 * registration failures.
 */
KDNA_GE_Plugin::instance();
