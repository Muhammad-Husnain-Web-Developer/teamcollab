# TeamCollab — UI/UX Redesign Brief

Everything a designer needs to redesign this product: what it is, every screen,
every overlay, every state, the current design tokens, and the gaps where UI was
never built.

## How to read this pack

| File | What it covers |
|---|---|
| `01-PRODUCT-OVERVIEW.md` | What the product does, who uses it, the mental model |
| `02-DESIGN-SYSTEM.md` | Current colours, type, spacing, motion, iconography |
| `03-SCREENS/` | One file per screen — layout, regions, copy, behaviour |
| `04-MODALS-AND-OVERLAYS.md` | Every dialog, panel, popover, toast, lightbox |
| `05-COMPONENT-LIBRARY.md` | Reusable components and their variants |
| `06-STATES-AND-EDGE-CASES.md` | Empty, loading, error, offline, overflow, long text |
| `07-USER-FLOWS.md` | Step-by-step journeys through the product |
| `08-KNOWN-GAPS.md` | UI that does not exist yet and needs designing |
| `09-SCREENSHOT-CHECKLIST.md` | Exact shot list, with filenames |
| `10-FILE-MAP.md` | Every Vue file → where it is documented |
| `screenshots/` | Where captured images go (see checklist for naming) |

## Status of screenshots

**The `screenshots/` folders are empty.** The folder tree and the naming
convention are set up, but no images have been captured yet — see
`09-SCREENSHOT-CHECKLIST.md` for the complete shot list and how to capture them.

## Scope note for the designer

This is a **dark-mode-only** product today. There is no light theme anywhere in
the codebase. If a light theme is in scope for the redesign, say so early — it
affects every token decision.

The product is **desktop-first**. A phone layout exists but is thin; see
`06-STATES-AND-EDGE-CASES.md` → Responsive.
