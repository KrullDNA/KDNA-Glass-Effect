# KDNA Glass Effect — Build Status

Note: the plugin name was changed from "KDNA Liquid Glass" (as written in the
brief) to **KDNA Glass Effect** at the user's direction, to avoid any reference
to a potentially trademarked term. Prefixes were changed accordingly:

- Plugin slug / directory: `kdna-glass-effect`
- PHP class prefix: `KDNA_GE_`
- CSS class prefix: `kdna-ge`
- CSS variable prefix: `--kdna-ge-`
- Filter name: `kdna_ge_supported_widgets`
- SVG filter ID (Session 2): `kdna-ge-filter`

All brief references to "Liquid Glass" or `kdna-lg-` map 1:1 to "Glass Effect"
/ `kdna-ge-` in the implementation.

---

## Session 1 — Plugin Foundation and Control Injection — COMPLETE

### Deliverables shipped

- `kdna-glass-effect.php` — plugin bootstrap with header (v0.1.0),
  activation hook, constant definitions, and direct instantiation of the
  main plugin class at file load time (no `elementor/loaded` wrapper).
- `includes/class-kdna-ge-plugin.php` — singleton bootstrap. Verifies
  Elementor is active and at/above minimum version 3.20, shows an admin
  notice otherwise, and self-deactivates on activation when Elementor is
  missing.
- `includes/class-kdna-ge-targets.php` — widget-ID → target selector map
  with `Apply To` options per widget. Exposes the filterable list via
  `kdna_ge_supported_widgets`. Helpers return option labels, default
  Apply To key, and selector strings relative to `{{WRAPPER}}`.
- `includes/class-kdna-ge-controls.php` — injects the full `KDNA Glass
  Effect` section at the end of each supported widget's Style tab.

### Supported widgets (filterable via `kdna_ge_supported_widgets`)

`container`, `section`, `column`, `button`, `heading`, `text-editor`,
`image-box`, `icon-box`, `call-to-action`, `icon-list`.

### Controls injected (Section 5 of the brief)

- **Master**: `Enable Glass Effect` switcher.
- **Common**: `Apply To` (widget-dependent options — Button offers
  Button Element/Widget Wrapper; Heading offers Text Container/Widget
  Wrapper; all others Widget Wrapper only), `Preset` (Custom, Clear,
  Frosted Light, Frosted Dark, Tinted).
- **Base State** (visible when Preset = Custom): Tint Colour, Tint
  Opacity, Background Blur, Edge Refraction Amount, Refraction Detail,
  Border Radius (responsive Dimensions), Rim Light Colour/Opacity,
  Inner Highlight Colour/Opacity, Border Colour, Border Width,
  Transition Duration.
- **Hover State**: heading, `Enable Hover Override` switcher, then a
  full duplicate of every Base State style control keyed on `:hover`.
- **Active State**: same shape as Hover, keyed on `:active`.

All style controls emit scoped CSS variables (or, for border radius,
direct `border-radius`) via Elementor's `selectors` parameter. Every
Apply To selector is written into the selectors map so the variables
land on whichever target is active.

### Hook registration

`elementor/element/after_section_end` registered at file load time via
`KDNA_GE_Plugin::instance()`. The callback checks `args['tab'] === TAB_STYLE`
and bails on widgets outside the supported map. A hidden flag control
(`kdna_ge_section_injected_flag`) prevents re-injection if multiple Style
sections close on the same element.

### No assets in this session

Per brief: no CSS file, no SVG filter, no front-end JS. Controls only.

### Known notes / issues

- The `Apply To` control currently affects *which element gets the class
  at render time* (Session 2). CSS variables are emitted to every Apply
  To target's selector — that's safe because the class is only placed
  on one of them, so the other selectors never match at runtime.
- PHP lint clean on all four files (`php -l`).
- Requires Elementor 3.20+, WordPress 6.0+, PHP 7.4+.

---

## Session 2 — Rendering and Visual Effect — COMPLETE (pending visual calibration)

### Deliverables shipped

- `includes/class-kdna-ge-render.php` — hooks
  `elementor/frontend/{widget,container,section,column}/before_render` to
  add the `kdna-ge` class (plus `kdna-ge--has-hover`,
  `kdna-ge--has-active`, and `kdna-ge-preset-[name]`) to the widget
  wrapper when Apply To resolves to the wrapper. For Button / Heading
  inner-element targets, hooks `elementor/widget/render_content` and
  regex-injects the same class list into `.elementor-button` or
  `.elementor-heading-title` respectively. Flips
  `KDNA_GE_Render::has_rendered()` the first time any glass widget
  renders.
- `includes/class-kdna-ge-assets.php` — registers the stylesheet at
  `wp_enqueue_scripts` (priority 5) but defers the actual enqueue to
  `wp_footer` priority 5, guarded by `has_rendered()`. At
  `wp_footer` priority 20 emits the shared SVG filter
  (`<filter id="kdna-ge-filter">`) with a pre-baked two-pass radial
  displacement gradient as an inline `data:image/svg+xml` URL in
  `feImage`. Also enqueues the stylesheet inside the Elementor editor
  preview via `elementor/preview/enqueue_styles`.
- `assets/css/kdna-glass-effect.css` — implements the `.kdna-ge` base
  surface per Section 6 of the brief using CSS variables for every
  tunable value, with `-webkit-backdrop-filter` alongside
  `backdrop-filter` throughout. Ships four preset classes
  (`kdna-ge-preset-clear`, `-frosted-light`, `-frosted-dark`,
  `-tinted`) that override the variable cascade. `@supports`
  fallbacks: one for browsers without SVG-URL backdrop-filter support
  (Firefox) dropping the `url(#...)` and falling back to pure blur; a
  second for browsers with no backdrop-filter at all, falling back to a
  solid 30% white tint plus a blurred `::before` pseudo-element.
- `kdna-glass-effect.php` — now requires the render and assets classes.
- `includes/class-kdna-ge-plugin.php` — instantiates both
  `KDNA_GE_Render` and `KDNA_GE_Assets` alongside `KDNA_GE_Controls` in
  the `init()` boot sequence.

### Rendered-flag lifecycle

1. Elementor renders an element. `before_element_render()` checks glass
   settings; if enabled, sets `$rendered = true` regardless of target.
2. For inner-element targets, `filter_widget_content()` re-sets the flag
   as a belt-and-braces measure.
3. `wp_footer` priority 5 checks the flag — if false, stylesheet is
   never enqueued.
4. `wp_footer` priority 20 checks the flag — if false, SVG filter is
   never emitted.

### Acceptance check-list

- [x] `@supports` fallback present for both SVG-URL and blur-less
      browsers.
- [x] `-webkit-backdrop-filter` paired with `backdrop-filter` on every
      declaration.
- [x] Preset classes implemented for Clear, Frosted Light, Frosted
      Dark, Tinted.
- [x] Hover / Active CSS variable cascade activates only when the
      modifier classes are present (handled automatically by the
      variable scoping — Session 1 conditions the control selectors on
      the per-state enable switcher).
- [x] No front-end JavaScript.
- [x] PHP lint clean across all files.

### Pending visual calibration (Session 3)

The default values and displacement gradient stops are a sensible
starting point but have **not yet been compared side-by-side against
the Focus button reference image**. Session 3 is the calibration pass
and should iterate on:

- Displacement gradient stops in
  `KDNA_GE_Assets::displacement_data_url()` (currently 0%/35% grey core
  → 100% red/green split at the rim, with a screen-blend pass to mix
  X and Y displacement).
- `feDisplacementMap` `scale` attribute (currently fixed at 60 per the
  brief's preferred lightweight option).
- Box-shadow outer shadow Y-offset, blur, and opacity — currently
  `0 8px 24px rgba(0,0,0,.18)` plus a tighter `0 2px 6px rgba(0,0,0,.10)`.
- Inner rim/highlight inset spread values.
- Default CSS variable values versus the reference pill.

### Known notes / issues

- Firefox does not support `url()` inside `backdrop-filter`, so the
  first `@supports not` block kicks it into a pure-blur fallback. Edge
  refraction will not be visible on Firefox.
- The inner-element class injection relies on the widget rendering
  exactly the expected class literal (`.elementor-button` /
  `.elementor-heading-title`). Child themes or third-party overrides
  that strip those classes will revert the effect to wrapper-only.

---

## Session 3 — Polish, Editor Preview Quality, and Delivery — Not started
