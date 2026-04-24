=== KDNA Glass Effect ===
Contributors: kdna
Tags: elementor, glass, backdrop-filter, frosted, addon
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An Elementor enhancer plugin that adds a frosted Glass Effect control set
to every supported widget. Produces backdrop blur, edge refraction, rim
lighting, inner highlight, and a soft grounding shadow.

== Description ==

KDNA Glass Effect injects a "KDNA Glass Effect" section into the Style tab
of every supported Elementor widget. When enabled on a widget, the plugin
applies a glass surface to the widget's chosen target element, driven
entirely by CSS variables and a single shared SVG filter.

**Design principles**

* Zero front-end cost when disabled — if no widget on the page uses the
  effect, the plugin's stylesheet and SVG filter are never loaded.
* Single shared SVG filter per page, regardless of how many widgets use
  the effect.
* Single shared stylesheet, loaded only on pages where at least one
  widget has the effect enabled.
* CSS variables drive everything — per-widget overhead is a short list
  of variable declarations on the widget's target selector.
* No front-end JavaScript. The effect is pure CSS + SVG.
* Supports Elementor's Atomic architecture. No reliance on legacy
  wrapper divs.

**Controls**

Every supported widget gets a "KDNA Glass Effect" section in its Style
tab containing:

* Master Enable switcher
* Apply To (widget-dependent target options)
* Preset (Custom, Clear, Frosted Light, Frosted Dark, Tinted)
* Tint Colour / Opacity, Background Blur, Edge Refraction Amount,
  Refraction Detail, Border Radius (responsive), Rim Light Colour /
  Opacity, Inner Highlight Colour / Opacity, Border Colour / Width,
  Transition Duration
* Hover State override subsection (duplicates the full style control
  set, emitted on `:hover` only when enabled)
* Active State override subsection (same, emitted on `:active`)

**Supported widgets**

Out of the box the plugin supports:

* Container
* Section (legacy)
* Column (legacy)
* Button (Apply To: Button Element or Widget Wrapper)
* Heading (Apply To: Text Container or Widget Wrapper)
* Text Editor
* Image Box
* Icon Box
* Call to Action
* Icon List

The supported widget list is filterable (see Developer Notes below).

== Installation ==

1. Upload the `kdna-glass-effect` folder to `/wp-content/plugins/`, or
   install the `.zip` via **Plugins → Add New → Upload Plugin**.
2. Activate **KDNA Glass Effect** from the Plugins screen.
3. Edit any supported widget in Elementor. The **KDNA Glass Effect**
   section appears at the bottom of the Style tab.

Requires Elementor 3.20 or later.

== Developer Notes ==

**`kdna_ge_supported_widgets` filter**

Add or remove widgets from the supported list. The filter receives an
associative array keyed by Elementor widget ID. Each entry has a
`label` and a `targets` array listing which Apply To options the widget
should expose.

`
add_filter( 'kdna_ge_supported_widgets', function ( $map ) {
    // Expose the Glass Effect controls on the Star Rating widget too.
    $map['star-rating'] = array(
        'label'   => 'Star Rating',
        'targets' => array( 'wrapper' ), // 'wrapper', 'button', 'heading'
    );
    return $map;
} );
`

Valid `targets` values are:

* `wrapper` — Elementor widget wrapper (always available).
* `button` — inner `.elementor-button` element.
* `heading` — inner `.elementor-heading-title` element.

**CSS variable reference**

All tunable values are CSS custom properties on the target selector:

* `--kdna-ge-tint-color`
* `--kdna-ge-tint-opacity`
* `--kdna-ge-blur`
* `--kdna-ge-refraction-scale`
* `--kdna-ge-refraction-detail`
* `--kdna-ge-rim-color`
* `--kdna-ge-rim-opacity`
* `--kdna-ge-inner-color`
* `--kdna-ge-inner-opacity`
* `--kdna-ge-border-color`
* `--kdna-ge-border-width`
* `--kdna-ge-transition`

**Preset classes**

If the Preset control is set to anything other than Custom, the plugin
adds one of these classes to the target element instead of emitting the
individual variable declarations:

* `kdna-ge-preset-clear`
* `kdna-ge-preset-frosted-light`
* `kdna-ge-preset-frosted-dark`
* `kdna-ge-preset-tinted`

Override these in your theme's stylesheet to customise preset looks.

**Shared SVG filter ID**

`kdna-ge-filter` — emitted once per request inside a hidden `<svg>`
block in `wp_footer`, and separately injected into the Elementor
editor preview iframe by the editor helper script.

== Browser Support ==

The edge-refraction effect relies on `backdrop-filter: url(#...)`. At
the time of writing:

* Chromium-based browsers (Chrome, Edge, Opera, Brave) — fully supported.
* Safari — fully supported (`-webkit-backdrop-filter` used alongside).
* Firefox — falls back to a plain backdrop blur with no SVG refraction
  (via `@supports`). The surface will still look like frosted glass,
  just without the rim refraction.
* Browsers without any `backdrop-filter` support fall back to a solid
  30% white tint plus a blurred `::before` pseudo-element.

== Frequently Asked Questions ==

= Does the plugin store anything in the database? =

No. All per-widget settings live inside the widget's own Elementor data.
Deactivating the plugin leaves no orphan options or database rows.

= Does the plugin load any JavaScript on the front end? =

No. The effect is pure CSS + SVG. A small JavaScript helper is enqueued
inside the Elementor editor only, to inject the SVG filter into the
preview iframe.

= Why is nothing happening in Firefox? =

Firefox does not yet support SVG filter URLs inside `backdrop-filter`.
The plugin detects this via `@supports` and falls back to a plain
backdrop blur. The rim refraction will not appear.

= Can I add the controls to a widget that isn't in the default list? =

Yes. Use the `kdna_ge_supported_widgets` filter documented above.

== Changelog ==

= 0.1.0 =
* Initial release.
* Control injection on 10 core Elementor widgets.
* Base surface with CSS variable cascade.
* Four built-in presets (Clear, Frosted Light, Frosted Dark, Tinted).
* Hover and Active state overrides.
* Shared SVG displacement filter, conditionally loaded.
* Elementor editor preview support.
* `@supports` fallbacks for Firefox and blur-less browsers.
* `kdna_ge_supported_widgets` filter.

== Upgrade Notice ==

= 0.1.0 =
Initial release.
