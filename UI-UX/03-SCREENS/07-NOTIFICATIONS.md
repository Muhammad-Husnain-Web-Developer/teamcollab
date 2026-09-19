# Screen 07 — Notifications

Two surfaces for the same data.

## A. Notification bell (dropdown, in the shell)

- Bell icon with an **unread count badge**
- Dropdown titled "Notifications"
- Empty state: "No notifications yet"
- Rows are clickable and navigate to the source message
- A "mark all read" action exists in the API

## B. Notifications page (`/notifications`)

- Heading **"Notifications"**
- Full list, read/unread distinguished
- Empty state: "No notifications" /
  "Mentions, messages and missed calls will appear here"

## Notification types to design

| Type | Copy pattern |
|---|---|
| `mention` | "{sender} mentioned you in #{channel}" |
| `channel_message` | "{sender} posted in #{channel}" |
| `dm_message` | "{sender} sent you a message" |
| `missed_call` | "Missed audio/video call from {sender}" |

Each carries a **preview snippet** and the sender's avatar.

Read/unread states, grouping (e.g. "3 new messages in #general"), and a
timestamp treatment all need design decisions.
