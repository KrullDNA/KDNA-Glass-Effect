<?php
/**
 * Widget ID to target selector mapping.
 *
 * Every supported widget exposes one or more "Apply To" target selectors.
 * For most widgets the only sensible target is the widget wrapper; for
 * Button and Heading an element-level target is also offered.
 *
 * The supported widget list is filterable via kdna_ge_supported_widgets
 * so that future widgets can be added without editing the plugin.
 *
 * @package KDNA_Glass_Effect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KDNA_GE_Targets
 */
class KDNA_GE_Targets {

	const APPLY_TO_WRAPPER   = 'wrapper';
	const APPLY_TO_BUTTON    = 'button';
	const APPLY_TO_HEADING   = 'heading';

	/**
	 * Return the default, unfiltered supported widget list.
	 *
	 * Keys are Elementor widget IDs, values describe the Apply To
	 * options available for that widget.
	 *
	 * @return array<string, array>
	 */
	public static function default_map() {
		return array(
			'container'     => array(
				'label'   => 'Container',
				'targets' => array( self::APPLY_TO_WRAPPER ),
			),
			'section'       => array(
				'label'   => 'Section',
				'targets' => array( self::APPLY_TO_WRAPPER ),
			),
			'column'        => array(
				'label'   => 'Column',
				'targets' => array( self::APPLY_TO_WRAPPER ),
			),
			'button'        => array(
				'label'   => 'Button',
				'targets' => array( self::APPLY_TO_BUTTON, self::APPLY_TO_WRAPPER ),
			),
			'heading'       => array(
				'label'   => 'Heading',
				'targets' => array( self::APPLY_TO_HEADING, self::APPLY_TO_WRAPPER ),
			),
			'text-editor'   => array(
				'label'   => 'Text Editor',
				'targets' => array( self::APPLY_TO_WRAPPER ),
			),
			'image-box'     => array(
				'label'   => 'Image Box',
				'targets' => array( self::APPLY_TO_WRAPPER ),
			),
			'icon-box'      => array(
				'label'   => 'Icon Box',
				'targets' => array( self::APPLY_TO_WRAPPER ),
			),
			'call-to-action' => array(
				'label'   => 'Call to Action',
				'targets' => array( self::APPLY_TO_WRAPPER ),
			),
			'icon-list'     => array(
				'label'   => 'Icon List',
				'targets' => array( self::APPLY_TO_WRAPPER ),
			),
		);
	}

	/**
	 * Return the filtered supported widget map.
	 *
	 * @return array<string, array>
	 */
	public static function supported_widgets() {
		/**
		 * Filter the supported widgets map.
		 *
		 * @param array $map Widget ID => descriptor array with 'label' and 'targets'.
		 */
		return apply_filters( 'kdna_ge_supported_widgets', self::default_map() );
	}

	/**
	 * Return just the supported widget IDs.
	 *
	 * @return string[]
	 */
	public static function supported_widget_ids() {
		return array_keys( self::supported_widgets() );
	}

	/**
	 * Return the Apply To select options for a given widget ID.
	 *
	 * Options returned are suitable for Elementor's Select control.
	 *
	 * @param string $widget_id Elementor widget ID.
	 * @return array<string, string> Option value => label.
	 */
	public static function apply_to_options( $widget_id ) {
		$map = self::supported_widgets();
		if ( empty( $map[ $widget_id ]['targets'] ) ) {
			return array( self::APPLY_TO_WRAPPER => __( 'Widget Wrapper', 'kdna-glass-effect' ) );
		}

		$labels = array(
			self::APPLY_TO_WRAPPER => __( 'Widget Wrapper', 'kdna-glass-effect' ),
			self::APPLY_TO_BUTTON  => __( 'Button Element', 'kdna-glass-effect' ),
			self::APPLY_TO_HEADING => __( 'Text Container', 'kdna-glass-effect' ),
		);

		$options = array();
		foreach ( $map[ $widget_id ]['targets'] as $target_key ) {
			if ( isset( $labels[ $target_key ] ) ) {
				$options[ $target_key ] = $labels[ $target_key ];
			}
		}
		return $options;
	}

	/**
	 * Return the default Apply To key for a widget (first option in the list).
	 *
	 * @param string $widget_id Elementor widget ID.
	 * @return string
	 */
	public static function default_apply_to( $widget_id ) {
		$options = self::apply_to_options( $widget_id );
		$keys    = array_keys( $options );
		return $keys ? $keys[0] : self::APPLY_TO_WRAPPER;
	}

	/**
	 * Resolve an Apply To key to a CSS selector fragment relative to {{WRAPPER}}.
	 *
	 * @param string $apply_to Apply To key.
	 * @return string Selector string containing {{WRAPPER}} placeholder.
	 */
	public static function selector_for( $apply_to ) {
		switch ( $apply_to ) {
			case self::APPLY_TO_BUTTON:
				return '{{WRAPPER}} .elementor-button';
			case self::APPLY_TO_HEADING:
				return '{{WRAPPER}} .elementor-heading-title';
			case self::APPLY_TO_WRAPPER:
			default:
				return '{{WRAPPER}}';
		}
	}

	/**
	 * Build a conditional selector map keyed by Apply To values.
	 *
	 * This is used when building Elementor control selectors so a single
	 * control can emit its CSS on the correct target selector depending on
	 * the widget's Apply To choice. The returned array maps each Apply To
	 * option (as seen on the widget) to the concrete selector string.
	 *
	 * @param string $widget_id Elementor widget ID.
	 * @return array<string, string>
	 */
	public static function selector_map_for_widget( $widget_id ) {
		$map     = array();
		$options = self::apply_to_options( $widget_id );
		foreach ( array_keys( $options ) as $apply_to ) {
			$map[ $apply_to ] = self::selector_for( $apply_to );
		}
		return $map;
	}
}
