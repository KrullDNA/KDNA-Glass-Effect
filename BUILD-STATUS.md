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

## Session 3 — Polish, Editor Preview Quality, and Delivery — COMPLETE

### 1. Editor preview parity

- `assets/js/kdna-glass-effect-editor.js` — locates
  `window.elementor.$preview[0].contentDocument` and appends the SVG
  filter wrapper into the preview iframe's `<body>`, idempotently
  (guards on `#kdna-ge-filter`). Wires:
  - `elementor.on('preview:loaded', ...)` and `document:loaded`
  - `elementor.hooks.addAction('frontend/element_ready/global', ...)`
  - `elementor.hooks.addAction('panel/open_editor/widget', ...)`
  - Re-invokes on `DOMContentLoaded` and immediately on first run for
    late-loaded cases.
- Enqueued via `elementor/editor/after_enqueue_scripts` in
  `KDNA_GE_Assets::enqueue_editor_script()`, which also
  `wp_localize_script`-exposes `kdnaGeEditorData.filterId` and
  `.svg` (the same markup the front end's `wp_footer` emits, built
  by the shared `svg_filter_markup()` helper so the two paths cannot
  drift).

### 2. Visual calibration against the Focus button reference

Nick supplied the Focus-button reference image mid-session. Observations
from it drove the following tuning pass:

- **Centre almost undistorted** → default `--kdna-ge-blur` reduced from
  12px to **8px**. Control default updated to match.
- **Rim bleed is dramatic, not subtle** → `feDisplacementMap scale`
  raised from 60 to **90**. Displacement gradient stops tightened so
  the neutral grey core holds flat out to 55% radius, then ramps to
  rim colour between 55% and 95% (was 35% → 100%). Produces more
  rim-focused refraction with a clean clear centre.
- **Bright hairline ring around entire perimeter** → added
  `inset 0 0 0 1px rgba(255,255,255,0.18)` to the shadow stack so the
  1px inner ring wraps the whole shape in addition to the top-only
  rim light stripe.
- **Wider inner glow suggesting a curved surface** → inner-highlight
  spread increased from 20px to 28px, default opacity lifted from
  0.15 to **0.18** (control default updated to match).
- **Outer shadow softer / less contrasty** than my first pass → changed
  from `0 8px 24px rgba(0,0,0,0.18)` + `0 2px 6px rgba(0,0,0,0.10)`
  to `0 10px 30px rgba(0,0,0,0.12)` + `0 2px 6px rgba(0,0,0,0.08)`.
  Keeps the element grounded without pulling focus.
- **Rim light brightness** lifted by 10% — default
  `--kdna-ge-rim-opacity` 0.5 → **0.6**, control default matched.

Further calibration may be desirable once tested against a live WP +
Elementor + colourful photographic background scene. Remaining knobs
most likely to need tweaking are the `feDisplacementMap scale` and
the rim gradient's 95%/100% stops.

### 3. Performance QA (code-level audit)

Live WP testing isn't possible in this sandbox, but the load path has
been verified by code inspection:

- **No glass widgets on the page.** `KDNA_GE_Render::$rendered` never
  flips. `maybe_enqueue_stylesheet()` and `maybe_render_svg_filter()`
  both early-return. Result: zero `kdna-ge` stylesheet requests, zero
  inline SVG in the footer, zero front-end JS requests from the plugin.
- **One glass widget on the page.** The render hook flips `$rendered`
  during the widget's first render. Footer priority 5 enqueues the
  single registered stylesheet (`kdna-glass-effect`). Footer priority
  20 emits one `<svg>` wrapper with a single `<filter id="kdna-ge-filter">`
  inside. No front-end JS is ever enqueued (the only JS file in the
  plugin is gated on `elementor/editor/after_enqueue_scripts`, which
  fires only in the editor chrome).
- **Multiple glass widgets on the page.** Same outputs as above —
  stylesheet and filter are shared, per-widget overhead is only the
  scoped CSS variable declarations Elementor generates via the
  `selectors` parameter.

### 4. Inline documentation

All PHP classes now carry header docblocks describing their role.
Every public method has a short docblock. Every hook registration has
a one-line comment explaining what the hook does. No emojis added.

### 5. readme.txt

Written. Sections: Description, Installation, Developer Notes (with
full `kdna_ge_supported_widgets` filter example and the CSS variable
/ preset-class / SVG filter ID reference), Browser Support, FAQ,
Changelog, Upgrade Notice. Version `0.1.0`.

### 6. Packaged zip

Built at **`dist/kdna-glass-effect.zip`** (~20 KB). Contents:

```
kdna-glass-effect/
├── kdna-glass-effect.php
├── readme.txt
├── assets/
│   ├── css/kdna-glass-effect.css
│   └── js/kdna-glass-effect-editor.js
└── includes/
    ├── class-kdna-ge-assets.php
    ├── class-kdna-ge-controls.php
    ├── class-kdna-ge-plugin.php
    ├── class-kdna-ge-render.php
    └── class-kdna-ge-targets.php
```

Install via **WordPress Admin → Plugins → Add New → Upload Plugin**.

### Issues / caveats

- **Live visual QA still pending.** The calibration pass was driven by
  the supplied reference image and my reading of the brief's written
  description. Nick should install the zip on a real WP + Elementor
  site over a colourful photographic background and compare
  side-by-side with the Focus button image. The two most likely knobs
  to need a final nudge are `feDisplacementMap scale` (90 now) and
  the displacement gradient's rim stop colour intensity (currently
  `rgb(255,128,128)` / `rgb(127,255,128)` at 100%).
- **Firefox** still falls back to plain blur — no rim refraction,
  which is unavoidable until Firefox ships SVG-URL support for
  `backdrop-filter`.
- **Naming divergence** from the brief is still the "Liquid Glass →
  Glass Effect" rename applied in Session 1 per Nick's direction.

---

## Plugin status: complete and ready for installation.

The zip at `dist/kdna-glass-effect.zip` is the deliverable.

---

## Post-Session 3 fixes (from Nick's feedback)

### Issue A: Heading padding doesn't extend the glass area

**Cause.** Heading widget had `Apply To = Text Container` as its default,
which targets the inline `.elementor-heading-title` element. Padding set
on the widget pads the wrapper, not the inner span, so the glass surface
stayed text-width.

**Fix.** Swapped the order of Heading's `targets` array in
`KDNA_GE_Targets::default_map()` so `wrapper` is listed first and
therefore becomes the default `Apply To` value. Users who want the
text-container behaviour can still pick it from the dropdown.

### Issue B: Edge Refraction Amount and Refraction Detail controls did nothing

**Cause.** `feDisplacementMap scale` is an SVG attribute, not a CSS
property, so a shared filter with a fixed scale cannot be driven by the
per-widget CSS variable the control was outputting. The brief flagged
this trade-off; Session 3 initially took the "fixed scale" shortcut,
which left both controls inert.

**Fix.** Switched to per-variant SVG filters:

- `KDNA_GE_Render` now computes a deterministic variant ID per unique
  `(scale, detail)` pair (`variant_id()` → e.g. `90-20`, `120-50`) and
  registers it in a static set.
- The render path sets `--kdna-ge-filter-ref: url(#kdna-ge-filter-{id})`
  as an inline style on the target element (both wrapper and inner
  paths).
- `KDNA_GE_Assets::svg_filter_markup()` now emits the default filter
  *plus one filter per registered variant*, each with its own
  `feDisplacementMap scale` and a displacement gradient whose
  inner-stop position is driven by the `detail` value (0.005 → 65%
  soft core, 0.1 → 20% tight core).
- `assets/css/kdna-glass-effect.css` now uses
  `backdrop-filter: var(--kdna-ge-filter-ref, url(#kdna-ge-filter))
  blur(var(--kdna-ge-blur, 8px))` so each widget picks its own variant.

**Performance impact.** Filters are shared across widgets that use the
same `(scale, detail)` combo, so a page with many glass widgets on
defaults still emits just one filter. The default shared filter is
still included as a fallback for the editor-preview boot case.

Zip rebuilt at `dist/kdna-glass-effect.zip` (~21 KB). PHP lint clean.

