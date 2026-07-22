# PHP Server Monitor 4.3.6-hs Shell Design

## Objective

Release `4.3.6-hs` from the known-good `4.3.2-hs` application content and
correct the Hope UI application shell without adopting the failed 4.3.3–4.3.5
release contents.

## Root causes

- Hope UI owns sidebar state through `sidebar-mini`, but `hs-monitor.css`
  independently hides the mobile sidebar with `body.sidebar-open`. No runtime
  ever creates that body class, so the mobile button changes Hope state while
  the custom rule keeps the menu off screen.
- Hope's native navbar and project overrides both size icon buttons. Their
  different line boxes leave the theme glyph vertically offset and too faint
  in light mode.
- The search input uses the Hope input-group markup but lacks a shell-specific
  surface contract, so border, icon segment and background vary with navbar
  customizer classes.
- Profile and logout are rendered twice: in the top account dropdown and in a
  second sidebar section.

## Selected approach

Use Hope UI's existing `sidebar-mini` state as the only desktop and mobile
sidebar controller. A small project runtime will synchronize accessibility
labels, provide an overlay on mobile, close the drawer after navigation or
outside interaction, and leave Hope's class names intact. No legacy Bootstrap
4, jQuery search, Font Awesome or parallel sidebar state is introduced.

## Shell structure

- The sidebar header follows the supplied Hope UI partial: brand followed by
  the absolute edge toggle. The arrow rotates when `sidebar-mini` is active.
- The top navbar contains the mobile/sidebar toggle, responsive Hope search,
  theme, customizer, install, notification and account controls.
- Profile and logout exist only in the account dropdown.
- A semantic `button` backdrop is present only while the mobile drawer is
  open. It is hidden from keyboard and assistive technology otherwise.

## Theme and search contract

- Every compact top-bar control has a 44 by 44 pixel interactive box and a
  centered 20 pixel SVG.
- Light mode uses a dark slate icon; dark mode uses an off-white icon. Hover
  and focus use the active accent without changing vertical geometry.
- Search icon and input share one 44 pixel surface, one border and Hope's
  radius. Light and dark modes define readable background, placeholder, text
  and focus ring. It is visible from tablet widths and becomes a compact
  search control on narrow phones without horizontal overflow.

## Responsive behavior

- At widths below 1200 px, `sidebar-mini` means closed and the full sidebar is
  translated off canvas; removing it opens the drawer over full-width content.
- Main content always starts at x=0 below the breakpoint.
- Opening the drawer updates `aria-expanded`, exposes the backdrop and locks
  background scrolling. Selecting a sidebar link, pressing Escape or clicking
  the backdrop closes it.
- At desktop widths, the same controls switch between full and mini sidebar;
  the arrow direction follows Hope's native rotation.

## Release and verification

- Version, PWA cache, README and changelog become `4.3.6-hs`.
- Unit contracts first reproduce the duplicate user menu, parallel mobile
  state, missing overlay, header sizing and responsive breakpoints.
- Full PHPUnit, PHPStan and syntax checks run before deployment.
- Production is checked at desktop and mobile widths in light and dark modes,
  including actual menu open/close interactions and overflow measurements.
- The VPS is deployed without a backup as previously authorized. GitHub main
  preserves remote history but takes application content from `4.3.2-hs`, then
  receives tag and signed release `v4.3.6-hs`.
