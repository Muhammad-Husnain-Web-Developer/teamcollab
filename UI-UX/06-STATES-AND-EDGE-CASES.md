# 06 — States & Edge Cases

The parts that get skipped in mockups and then get invented badly in code.

## Empty states — every one that exists

| Where | Current copy |
|---|---|
| Message list | "No messages yet" / "Be the first to say something!" |
| Thread panel | "No replies yet — start the conversation." |
| Members page | "No members found" / "Try adjusting your search or filters." |
| Notifications page | "No notifications" / "Mentions, messages and missed calls will appear here" |
| Notification bell | "No notifications yet" |
| Dashboard activity | "No recent activity." |
| Workspace settings members | "No members found." |
| Channel members — nobody to add | "Everyone in this workspace is already in the channel." |
| Channel members — no search match | "No one matches "{term}"." |
| DM list | "No other members yet." |
| Right panel | "Select a channel to see details." |

**All of them are a single line of grey text.** No illustration, no icon, no
call to action. This is a large, cheap win for the redesign.

### Empty states that do NOT exist and should
- A workspace with **no channels at all**
- A **brand-new user** with no workspaces
- A **new DM** with no history (deserves a warmer intro than the generic one)
- **Search with no results**
- **Offline / connection lost**

## Loading states

- Skeleton loaders for messages, threads, member lists
- "Loading older messages…" spinner pinned at the top of the list while
  paginating upward
- Buttons with in-flight text ("Adding…")
- **Missing:** a full-page/route-transition loading treatment

## Error states

- Inline field errors: red text under the input + red input border
- Toasts for action failures — now including **all server flash messages**
- **Missing:** a 403 "no access" screen, a 404 screen, a 500 screen, and an
  offline banner. All need designing.

## Overflow & long content

Cases the design must survive:

- **Very long channel names** in a 240px sidebar (truncation today)
- **Long display names** in DM rows and message headers
- **A 10,000-character message** (the enforced maximum)
- **A message with 10 attachments** (the enforced maximum)
- **Unbroken strings / long URLs** — links use `break-all` today
- **Wide code blocks** — must scroll horizontally inside the bubble
- **Many reactions** on one message — pills need to wrap
- **99+ unread** — badge caps at "99+"
- **Deep channel lists** — no collapsing or grouping exists

## Presence states

Three, used in the sidebar, DM header, and members page:
**online** (green) · **away** (yellow) · **offline** (grey)

Plus a per-user custom status (emoji + text) surfaced via "Set a status".

## Real-time states to visualise

Everything below happens live over WebSockets and needs a considered treatment:

- A new message arriving while you are scrolled up
- Someone typing
- A reaction appearing on a message you are looking at
- Unread badges incrementing
- A member being added or leaving a channel
- Presence flipping online/offline
- **Connection dropped / reconnecting** ← not handled at all today

## Responsive

Single breakpoint: **768px**, driven by JavaScript.

| Above 768px | Below 768px |
|---|---|
| Sidebar is a fixed 240px column | Sidebar is an overlay, starts closed, opened by a hamburger |
| Right panel available | Right panel hidden entirely |
| Thread panel is a 400px column | Thread panel is a full-screen overlay |

**Not designed for tablet.** Between roughly 768px and 1024px the layout is
desktop-shaped and cramped. The auth marketing panel disappears at 1024px.

Honest assessment: the phone experience is functional but thin. If mobile
matters to the business, it needs designing properly rather than adapting.

## Accessibility gaps to fix in the redesign

- **Text contrast fails WCAG AA** in several places (see `02-DESIGN-SYSTEM.md`)
- Focus-visible styling is largely absent
- Icon-only buttons rely on `title` tooltips rather than accessible labels
- Modals do not trap focus, and Escape does not reliably close them
- No reduced-motion handling despite GSAP animations on every message
