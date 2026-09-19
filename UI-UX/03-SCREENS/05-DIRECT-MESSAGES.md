# Screen 05 — Direct Messages

Same three-region skeleton as a channel, but a different header and the
**bubble** message style (see `04-CHANNEL-CHAT.md` → Two message styles).

## Header

- **Avatar with a live presence dot** — green online / yellow away / grey offline
- Display name (bold) and the live status text beneath
- Right cluster:
  - **Voice call** — starts a WebRTC audio call
  - **Video call** — starts a WebRTC video call
  - **Search** — inline "Search in conversation…" bar
  - **⋮ More** — View Profile · Mute Notifications · Search in Chat ·
    **Close Conversation** (red)

## Right-hand info column

The DM page has its own collapsible sections (not the shared right panel):
- **Info** (open by default)
- **Files**
- **Pinned**

These need designing — they are section headers with thin content today.

## Behaviour differences from channels

- Always exactly **two people**; no group DMs, no member management.
- **Mute** is per-conversation and suppresses notifications.
- Marked read on open; the sidebar badge clears.
- Calls are only available here, never in channels.

## Empty state

A brand-new conversation with no messages — currently just the generic
"No messages yet / Be the first to say something!". A DM deserves something
warmer, e.g. the other person's avatar and a "This is the start of your
conversation with {name}" line. **Design this.**
