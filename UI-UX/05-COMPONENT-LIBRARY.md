# 05 — Component Library

Every reusable piece, with the variants that must exist.

## Common

### Avatar
Sizes in use: `xs` (thread facepiles) · `sm` (lists, headers) · `md` · `lg`.
- Falls back to **initials** when there is no image
- Optional **presence dot** overlay, bottom-right, with a ring matching the
  background so it reads as cut out
- Used stacked/overlapping (`-space-x-1`) for thread participants

### Button
Variants exist in the component. The redesign should define, explicitly:
primary · secondary · ghost · **destructive** · icon-only, each with
default / hover / active / focus-visible / disabled / **loading** states.

### Modal
See `04-MODALS-AND-OVERLAYS.md`.

### Toast
Four variants (success / error / warning / info) + dismiss.

### SkeletonLoader
Used while messages, threads and member lists load. One generic shape today —
should probably become shape-specific (message skeleton vs list-row skeleton).

### SearchBar
Sidebar search.

### ImageLightbox / AvatarCropModal
See overlays.

## Sidebar

| Component | Notes |
|---|---|
| `AppSidebar` | The whole left column |
| `WorkspaceSwitcher` | Current workspace + menu |
| `ChannelList` | Section header, rows, active + unread states |
| `DirectMessageList` | Sorted by recent activity |
| `MemberRow` | Avatar + presence + name + unread badge |

## Chat

| Component | Notes |
|---|---|
| `ChatArea` | Composes header + list + typing + composer + thread panel |
| `ChatHeader` | Two variants: **channel** and **DM** |
| `MessageList` | Date separators, author grouping, infinite scroll upward |
| `MessageItem` | Two variants: **channel row** and **DM bubble** — the single most complex component |
| `MessageReactions` | Reaction pills + add button |
| `MessageInput` | Composer, toolbar, reply banner, file previews, drag-drop |
| `ThreadPanel` | Side panel thread view |
| `TypingIndicator` | Animated "X is typing…" |
| `ChannelMembersModal` | Channel membership management |

## Dashboard

| Component | Notes |
|---|---|
| `StatsCard` | Metric + label + icon + trend |
| `MessageChart` | **Hand-rolled SVG chart** — no chart library is installed |

## Calls

| Component | Notes |
|---|---|
| `CallScreen` | Full-screen active call |
| `IncomingCallToast` | Ringing notification with Accept/Decline |

## Notifications

| Component | Notes |
|---|---|
| `NotificationBell` | Icon + unread badge + dropdown |

---

## States every interactive component needs

The current build is inconsistent here. Please specify all of these:

- **default**
- **hover**
- **active / pressed**
- **focus-visible** ← weakest area today; keyboard users get almost no feedback
- **disabled**
- **loading / in-flight** (buttons that fire requests: Add member, Send, Save)
- **error**
