# 08 — Known Gaps: UI That Does Not Exist

Read this before quoting the work. These are places where a button, a route or
a backend feature exists but **there is no designed UI** — so the redesign is
partly *design from scratch*, not just restyling.

## Buttons that are visible but do nothing

| Where | Button | Status |
|---|---|---|
| Chat header | **Pinned messages** | Backend pin/unpin endpoints exist; no panel to show pinned messages |
| Chat header | **Search** (channel) | Opens an input; results presentation undesigned |
| Message hover bar | **Pin** | No click handler at all |
| Call screen | **Speaker** | Explicitly disabled, "coming soon" |

## Panels that are empty shells

- **Right panel ("Details")** — renders a heading and
  "Select a channel to see details." That is the entire component.
  Needs a full design: channel about/topic, members, files, pinned, settings.
- **DM Info / Files / Pinned sections** — section headers with thin content.

## Modals referenced in code but never built

The UI store can open modals by name, but **nothing renders them**:

- `editMessage` — editing currently happens inline instead
- `newDM` — the "New direct message" `+` in the sidebar opens nothing

Both need designing (or the entry points removing).

## Features with backend support but no UI

| Feature | Status |
|---|---|
| **Promote a channel member to admin** | The API accepts a role; the members modal always sends "member". This is why a channel creator can end up unable to leave. |
| **Read receipts** | A `MessageRead` model, table and broadcast event exist, unused. If "seen by" is wanted, it needs designing. |
| **Message pinning** | Endpoints exist; no surface. |
| **Workspace analytics** | An analytics service exists beyond what the dashboard shows. |

## Missing screens

- 403 / 404 / 500 error pages
- Offline / reconnecting state
- Search results page
- Onboarding or first-run guidance
- Keyboard shortcut help

## Product-level gaps worth raising with the client

These are not bugs — they are scope decisions the designer will hit:

1. **No light theme.** Dark only, everywhere.
2. **No group DMs.** Conversations are strictly two-person.
3. **No message search results UI**, though a search backend exists.
4. **Mobile is thin.** One JS breakpoint at 768px; no tablet design.
5. **Two competing reply models** (inline quote vs thread) — pick one, or make
   the difference obvious.
6. **The dashboard is low-value** — decide whether to invest or remove it.
