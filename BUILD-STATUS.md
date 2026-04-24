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

## Session 2 — Rendering and Visual Effect — Not started

## Session 3 — Polish, Editor Preview Quality, and Delivery — Not started
