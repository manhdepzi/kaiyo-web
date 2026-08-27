# Step 28 — Kaiyo V1 Design System

## Control

- Status: `DONE — EXECUTABLE PRIMITIVES`
- Inputs: approved Step 27 frontend architecture, current Kaiyo cyan/slate visual baseline and WCAG 2.2 AA target
- Direction: **Maritime Precision** — calm, technical and trustworthy for mixed B2C/B2B commerce; dense staff screens must remain readable without making public pages feel administrative

## Token model

Tokens live in `resources/css/app.css`. Components consume semantic names only; raw palette values must not appear in Blade components.

| Family | Semantic tokens | Purpose |
| --- | --- | --- |
| Surface | `canvas`, `surface`, `surface-muted` | Page, panel and subdued grouping |
| Content | `ink`, `ink-muted`, `line` | Primary text, secondary text and boundaries |
| Brand | `brand`, `brand-hover`, `brand-soft`, `on-brand`, `on-brand-soft` | Primary actions, links and selected emphasis |
| Feedback | `success`, `warning`, `danger`, `info` plus soft/on variants | Status with text/icon; color alone is never the signal |
| Interaction | `focus` | Three-pixel visible keyboard focus ring with offset |

`:root` is the light/default theme. `.theme-dark` changes semantic values without changing component markup. The authentication surface uses the dark theme; future surface theme choice stays at the layout boundary.

## Typography, grid and spacing

- Font stack: Instrument Sans when locally available, followed by system UI fonts; no render-blocking remote font dependency.
- Base text is 16 px; helper text never drops below 12 px. Body line height should remain 1.5 or greater.
- Public content uses a 12-column fluid grid with a 1280 px content maximum. Customer uses 12 columns; Sales/Admin may use a 16-column dense grid at wide breakpoints.
- Breakpoints follow content pressure rather than device names: base, `sm` 640, `md` 768, `lg` 1024, `xl` 1280 and `2xl` 1536 px.
- Spacing uses Tailwind's 4 px base rhythm. Control minimum height is 44 px (`min-h-11`); dense 36 px controls are limited to staff tables and retain an adequate target through row spacing.
- Corners use `control` (10 px) and `panel` (16 px); `panel` shadow is reserved for elevated containers.

## Primitive catalog

| Component | Contract |
| --- | --- |
| `x-ui.button` | `primary`, `secondary`, `danger`, `ghost`; link or button semantics; 44 px default target; real disabled state |
| `x-ui.input` | Explicit label/id, required cue, help/error association, invalid and disabled states; forwards safe HTML attributes |
| `x-ui.alert` | `info`, `success`, `warning`, `danger`; uses `status` or `alert` semantics and never relies on color alone |
| `x-ui.badge` | Compact non-interactive state label; must contain meaningful text |
| `x-ui.card` | Semantic panel with optional heading and description |
| `x-ui.empty-state` | Named empty result with optional recovery action |
| `x-ui.skeleton` | Loading placeholder with accessible status text and reduced-motion protection |

Feature components compose these primitives and keep authorization/business transitions in server actions. A primitive does not query Eloquent, call an API or own domain truth.

## Accessibility and interaction rules

- Normal text and control labels require contrast at least 4.5:1; large text at least 3:1; focus and meaningful component boundaries at least 3:1.
- Every control is operable by keyboard, has a visible focus indicator and preserves native semantics.
- Error text is associated with its input by `aria-describedby`; invalid fields expose `aria-invalid`.
- Status announcements use appropriate live-region roles. Unknown write outcomes must not be presented as simple failure/retry.
- `prefers-reduced-motion: reduce` removes non-essential animation, smooth scrolling and long transitions.
- Icons supplement text or have accessible names. Decorative icons are hidden from assistive technology.
- No hover-only action, color-only status, focus trap without escape, or disabled-looking active control is allowed.

## Review gate

Step 28 exits only when token contrast tests, semantic component render tests, production asset build, PHPStan/Pint and the complete application suite pass. Steps 29–35 must reuse this catalog and add page-level keyboard, screen-reader, responsive and automated accessibility evidence.
