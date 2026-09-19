# Screen 00 — The App Shell

The persistent frame around every logged-in screen. Redesigning this changes
the feel of the whole product more than any other single decision.

## Layout

```
┌────────────┬────────────────────────────────┬──────────────┐
│  SIDEBAR   │  PAGE CONTENT (slot)           │ RIGHT PANEL  │
│  240px     │  flex-1                        │ 256px        │
│  dark-750  │  dark-800                      │ dark-750     │
│            │                                │ (toggleable, │
│            │                                │  hidden on   │
│            │                                │  mobile)     │
└────────────┴────────────────────────────────┴──────────────┘
```

Full height, `overflow-hidden` — the page never scrolls; only inner regions do.

## Sidebar — top to bottom

1. **Workspace switcher** — current workspace logo + name, opens a menu of the
   user's other workspaces.
2. **Search bar**.
3. **Channels section**
   - Header row: "Channels" + a `+` button (`title="Add channel"`).
   - Channel rows: `#` for public, a padlock icon for private, then the name.
   - **Active** channel: `bg-brand-500/15`, white text.
   - **Unread** channel: bold + a pill badge with the count (`99+` above 99).
     The badge is hidden on the channel you are currently viewing.
4. **Direct Messages section**
   - Header row: "Direct Messages" + a `+` button.
   - Rows: avatar with a **presence dot** (green online / yellow away / grey
     offline), display name, and an unread badge.
   - Members with no conversation yet are also listed; clicking starts one.
5. **Current user footer** — avatar, name, "Set a status", "Profile & Settings",
   "Sign Out", and a settings gear.

### Notes for redesign
- Sections are not collapsible today. Long channel lists just scroll.
- There is no visual grouping beyond the two section headers — no favourites,
  no starred, no folders.
- The unread badge and the bold state are the only unread signals.

## Right panel

Currently a **shell only**: a "Details" heading and the placeholder text
"Select a channel to see details." It is toggled by the ⓘ button in the chat
header. **This needs designing from scratch** — see `08-KNOWN-GAPS.md`.

## Global overlays mounted in the shell

Always present regardless of page:

- **Toast container** — fixed overlay, top-level.
- **Incoming call toast** — slides in when someone calls.
- **Call screen** — full-screen when a call is active.

## Mobile behaviour

Below 768px the shell switches to a phone layout:

- The sidebar becomes a **fixed overlay** (82vw, max 300px) with a
  dark scrim behind it, and starts **closed**.
- A **hamburger button** appears at the left of the chat header to open it.
- Selecting a channel or DM auto-closes the sidebar.
- The right panel is **hidden entirely**.

The breakpoint is driven by JavaScript (`window.innerWidth < 768`), not CSS
media queries — worth knowing if the redesign introduces new breakpoints.
