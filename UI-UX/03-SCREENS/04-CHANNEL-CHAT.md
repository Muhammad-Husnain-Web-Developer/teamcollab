# Screen 04 — Channel Chat  ⭐ THE MAIN SCREEN

Where users spend ~90% of their time. Give this the most design attention.

## Regions

```
┌───────────────────────────────────────────┬─────────────┐
│ CHAT HEADER                               │             │
├───────────────────────────────────────────┤  THREAD     │
│                                           │  PANEL      │
│  MESSAGE LIST  (scrolls, loads older      │  400px      │
│                 when scrolled to top)     │  (on demand)│
│                                           │             │
├───────────────────────────────────────────┤             │
│ TYPING INDICATOR                          │             │
├───────────────────────────────────────────┤             │
│ COMPOSER                                  │             │
└───────────────────────────────────────────┴─────────────┘
```

## 1. Chat header

Left → right:
- **Hamburger** (mobile only) — opens the sidebar.
- Channel glyph: `#` for public, **padlock icon** for private.
- Channel **name** (bold, 14px) and **topic** beneath (12px, muted) if set.
- Right cluster of icon buttons, each with a hover tooltip:
  - **Members** — people icon + the live member count
  - **Search** — opens an inline search bar that slides down
  - **Pinned** — pinned messages *(not implemented — see gaps)*
  - **Info** (ⓘ) — toggles the right panel
  - **⋮ More** — dropdown: Members · Mute/Unmute Notifications · **Leave Channel** (red)

## 2. Message list

### Date grouping
Messages are grouped under a **date separator**, labelled `Today`,
`Yesterday`, or a formatted date.

### Author grouping
Consecutive messages from the same person within a short window **hide the
avatar and name** and render as a tight continuation row (`space-y-0.5`).

### Two completely different message styles

> **Important:** channels and DMs render messages differently. Both are
> currently in one component. Decide whether the redesign unifies them.

**A. Channel style** — classic Slack-like row
```
[avatar]  Name  10:42 AM
          Message body text…
          [reactions]  [3 replies]
```

**B. DM style** — chat-bubble, WhatsApp-like
- Own messages: right-aligned, **brand-coloured bubble**, white text
- Their messages: left-aligned, dark bubble, with avatar
- Max bubble width **72%**
- Timestamp sits **inline at the end of the text**, tiny (9px) and translucent,
  with `· edited` appended when edited

### Message anatomy — every part to design

| Part | Detail |
|---|---|
| **Avatar** | Circular. Hidden on grouped continuation rows. Own avatar shows on DM bubbles too. |
| **Author name + time** | Baseline-aligned row, only on the first message of a group |
| **Reply quote** | A quoted block *inside* the message: author name + truncated original (max 220px), with a coloured left border |
| **Body** | **Markdown**: bold, italic, strikethrough, inline code, fenced code blocks, lists, blockquotes, links, horizontal rules |
| **@mentions** | Highlighted `brand-400`, medium weight, clickable. On own DM bubbles they flip to white + underline for contrast |
| **Image attachments** | Thumbnail grid, wraps, opens a full-screen lightbox |
| **File attachments** | A card: type-coloured icon badge, filename, human-readable size, download button |
| **Reactions** | Pills under the message — emoji + count; the user's own reaction is highlighted; a `+` opens the picker |
| **Thread link** | "N replies" with up to 3 stacked participant avatars and an arrow on hover |
| **Edited** | `· edited` appended to the timestamp |
| **Deleted** | Replaced by a muted placeholder box (`dark-700/40`, dashed-feeling border) — the row stays in place |

### Hover action bar
Floats **above** the message (absolutely positioned, so it takes no layout
space), `dark-700` pill with border and shadow:

`👍` `❤️` `😊 react` `↩ reply` `💬 reply in thread` `✏️ edit*` `🗑 delete*` `📌 pin`

`*` own messages only. Appears on row hover at `right-4 top-1` in channels;
in DMs it flips side based on whose message it is.

### Emoji picker
A popover anchored to the message: a **24-emoji grid**, closes on outside click.

## 3. Typing indicator
Sits between list and composer: "X is typing…" with animated dots.

## 4. Composer

- **Reply banner** (when replying) — above the input: "Replying to {name}", a
  truncated preview, brand left-border, and an ✕ to cancel.
- **File preview strip** — pending uploads with remove buttons and progress.
- **Drag-and-drop overlay** — "Drop files to upload" covers the composer.
- **Formatting toolbar**: Bold · Italic · Code · Emoji · Attach file.
  These insert Markdown markers around the selection.
- **Textarea** — placeholder `Message #channel-name`, auto-grows to 192px.
- **Send button**.
- Enter sends; Shift+Enter makes a new line.

Limits to respect in the design: **10 files per message**, **10,000 characters**.

## 5. Thread panel

Opens on the right (full-screen overlay below 1024px).

- Header: "Thread" + "N replies" + close ✕
- The **root message**, then a divider labelled "N replies", then the replies
- Empty state: "No replies yet — start the conversation."
- Its own mini-composer: "Reply in thread…"

Threads are **one level deep** — replying to a reply attaches to the same root.
