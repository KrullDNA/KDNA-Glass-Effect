/*!
 * KDNA Glass Effect — editor preview helper.
 *
 * The shared SVG filter is emitted to wp_footer on the front end, but
 * the Elementor editor preview is a separate iframe that doesn't always
 * include wp_footer output. This helper ensures a copy of the same
 * filter definition is present inside the preview document whenever it
 * loads or whenever widgets are added / edited.
 *
 * The filter markup, filter ID, and displacement data URL are provided
 * by PHP via wp_localize_script on the handle "kdna-glass-effect-editor".
 */

( function ( window ) {
	'use strict';

	if ( typeof window === 'undefined' ) {
		return;
	}

	var DATA = window.kdnaGeEditorData || null;
	if ( ! DATA || ! DATA.svg || ! DATA.filterId ) {
		return;
	}

	/**
	 * Return the current Elementor preview document, or null if it's not
	 * ready yet.
	 */
	function getPreviewDocument() {
		try {
			if ( window.elementor && window.elementor.$preview && window.elementor.$preview.length ) {
				return window.elementor.$preview[ 0 ].contentDocument;
			}
		} catch ( err ) {
			// Access to the iframe document may throw before it's ready.
		}
		return null;
	}

	/**
	 * Ensure the SVG filter is present in the given document. Idempotent.
	 *
	 * @param {Document} doc The target document.
	 */
	function injectFilter( doc ) {
		if ( ! doc || ! doc.body ) {
			return;
		}
		if ( doc.getElementById( DATA.filterId ) ) {
			return;
		}

		var wrapper = doc.createElement( 'div' );
		wrapper.setAttribute( 'data-kdna-ge-editor-filter', '1' );
		wrapper.style.position = 'absolute';
		wrapper.style.width    = '0';
		wrapper.style.height   = '0';
		wrapper.style.overflow = 'hidden';
		wrapper.innerHTML      = DATA.svg;
		doc.body.appendChild( wrapper );
	}

	/**
	 * Inject into the current preview iframe if available.
	 */
	function injectIntoPreview() {
		injectFilter( getPreviewDocument() );
	}

	/**
	 * Wire Elementor editor lifecycle events.
	 */
	function wireEditorHooks() {
		if ( ! window.elementor || ! window.elementor.hooks ) {
			// Editor not ready yet; try again shortly.
			window.setTimeout( wireEditorHooks, 250 );
			return;
		}

		// Initial injection once the preview iframe is available.
		if ( window.elementor.on ) {
			window.elementor.on( 'preview:loaded', injectIntoPreview );
			window.elementor.on( 'document:loaded', injectIntoPreview );
		}

		// Re-inject whenever a widget/element renders or re-renders in
		// case the preview <body> was replaced.
		try {
			window.elementor.hooks.addAction(
				'panel/open_editor/widget',
				injectIntoPreview
			);
			window.elementor.hooks.addAction(
				'frontend/element_ready/global',
				injectIntoPreview
			);
		} catch ( err ) {
			// Some Elementor builds throw if the hook system isn't ready.
		}

		// Cover the case where we loaded after preview was already ready.
		injectIntoPreview();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', wireEditorHooks );
	} else {
		wireEditorHooks();
	}
} )( window );
