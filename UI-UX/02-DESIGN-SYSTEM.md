# 02 — Current Design System

Everything here is what exists **today**, pulled from `tailwind.config.js` and
the components. Treat it as the starting point to replace, not a constraint.

## Colour

### Brand (the single accent — indigo/periwinkle)

| Token | Hex | Used for |
|---|---|---|
| `brand-50`  | `#f0f4ff` | — |
| `brand-100` | `#dbe4ff` | — |
| `brand-200` | `#bac8ff` | — |
| `brand-300` | `#91a7ff` | Hover text on links, admin badge text |
| `brand-400` | `#748ffc` | Links, mentions, thread "N replies", active icons |
| `brand-500` | `#5c7cfa` | **Primary** — buttons, active channel, unread badge, focus ring |
| `brand-600` | `#4c6ef5` | Primary hover |
| `brand-700` | `#4263eb` | — |
| `brand-800` | `#3b5bdb` | — |
| `brand-900` | `#364fc7` | — |
| `brand-950` | `#2c3e9e` | — |

### Dark neutrals (the entire surface system)

| Token | Hex | Used for |
|---|---|---|
| `dark-900` | `#0f0f10` | Deepest — code block backgrounds |
| `dark-800` | `#161618` | **App background**, chat area |
| `dark-750` | `#1a1a1d` | **Sidebar**, right panel, modal surface, dropdowns |
| `dark-700` | `#1e1e21` | Inputs, hover-action bar, raised chips |
| `dark-650` | `#222226` | — |
| `dark-600` | `#27272b` | **Borders/dividers** (usually at 50–70% opacity) |
| `dark-550` | `#2d2d32` | — |
| `dark-500` | `#323237` | Muted icons, offline status dot |
| `dark-400` | `#3a3a40` | Secondary icons, placeholder-ish text |
| `dark-300` | `#48484f` | Body text on dark, sub-labels |
| `dark-200` | `#6b6b72` | Form labels, secondary text |
| `dark-100` | `#8e8e96` | Message body text |

> **Contrast warning.** `dark-300` (`#48484f`) is used as body/secondary text on
> `dark-800` (`#161618`). That is roughly **2.3:1** — well under the WCAG AA
> minimum of 4.5:1. Several greys in this scale fail. Fixing text contrast
> should be an explicit goal of the redesign.

### Semantic colours

These are used as raw Tailwind palette colours, not tokens:

| Meaning | Class used |
|---|---|
| Success / online | `green-500` |
| Warning / away | `yellow-500` |
| Error / destructive / offline dot | `red-400`, `red-500` |
| Info | `blue-*` |
| Accent gradient (marketing side) | `brand-400` → `purple-400` |

There is **no semantic token layer** — components reach for `red-400` directly.
Introducing `--color-danger` etc. would be a real improvement.

## Typography

| | |
|---|---|
| **Sans** | `Inter`, then `system-ui`, `-apple-system`, `sans-serif` |
| **Mono** | `JetBrains Mono`, `Fira Code`, `monospace` — used in code blocks |

Observed scale (Tailwind classes actually in use):

| Class | Size | Where |
|---|---|---|
| `text-[9px]` / `text-[10px]` | 9–10px | Message timestamps, badges, role chips |
| `text-xs` | 12px | Metadata, labels, sub-text, most secondary UI |
| `text-sm` | 14px | **Message body**, buttons, most primary UI |
| `text-base` | 16px | Modal titles |
| `text-lg` | 18px | — |
| `text-2xl` | 24px | Page headings ("Welcome back", "Account Settings") |
| `text-4xl` / `text-5xl` | 36/48px | Auth marketing panel headline only |

The whole app essentially lives between 12px and 14px. **Hierarchy is the
weakest part of the current type system.**

Weights in use: `font-medium` (500), `font-semibold` (600), `font-bold` (700).

## Shape and depth

| | |
|---|---|
| Radius — small controls | `rounded-md` (6px), `rounded-lg` (8px) |
| Radius — bubbles, inputs, cards | `rounded-xl` (12px) |
| Radius — modals | `rounded-2xl` (16px) |
| Radius — avatars, pills | `rounded-full` |

Custom shadows:

| Token | Value |
|---|---|
| `shadow-glow-brand` | `0 0 20px rgba(92, 124, 250, 0.3)` |
| `shadow-dark-lg` | `0 10px 40px rgba(0, 0, 0, 0.5)` |
| `shadow-dark-xl` | `0 20px 60px rgba(0, 0, 0, 0.6)` |

Borders are almost always **semi-transparent**: `border-dark-600/50`,
`/60`, `/70`. Overlays use `bg-black/50`–`/60` with `backdrop-blur-sm`.

## Motion

Defined in `tailwind.config.js`:

| Animation | Timing |
|---|---|
| `fade-in` | opacity, 0.2s ease-out |
| `fade-up` | opacity + 10px rise, 0.3s ease-out |
| `slide-in` | opacity + 10px from left, 0.25s ease-out |
| `pulse-slow` | 3s infinite |

Component-level transitions are 0.15s–0.25s ease. **GSAP** is also used for the
sidebar entrance and for each message appearing (fade + 8px rise, 0.25s).

## Iconography

**Inline SVG, stroke-based**, 24×24 viewBox, `stroke-width="2"`, round caps and
joins — visually the Heroicons *outline* set. Rendered at `w-3.5` (14px) in
dense action bars, `w-4` (16px) standard, `w-5` (20px) for headers.

`@heroicons/vue` is installed as a dependency.

## Spacing

Standard Tailwind 4px scale. Common rhythms:

- Sidebar rows: `px-2 py-1.5`, `gap-2`
- Chat header: `px-4 py-2.5`, `gap-3`
- Modal body: `px-6 py-4`, sections `space-y-5`
- Message rows: `space-y-0.5` within a group

## Fixed dimensions worth knowing

| Element | Size |
|---|---|
| Sidebar | 240px desktop; 82vw (max 300px) as a phone overlay |
| Right panel | 256px |
| Thread panel | 400px, 440px on xl |
| Composer max height | 192px before scrolling |
| Message list page size | 50 messages |
