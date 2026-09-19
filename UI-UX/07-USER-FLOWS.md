# 07 — User Flows

The journeys a redesign has to keep coherent.

## Flow 1 — New user, brand-new workspace

```
Register → Verify email → Create workspace (3 steps) → Land in workspace
        → Default channel exists → Invite teammates
```
Screens: Register · Verify Email · Workspace Create ×3 · Dashboard ·
Invite modal

## Flow 2 — Returning user

```
Login → (multi-workspace?) Workspace picker → Dashboard → a channel
```
On a workspace subdomain the picker is skipped and login is branded with the
workspace name.

## Flow 3 — Send a message

```
Pick channel → type → (optional: format, emoji, attach) → Enter → sent
```
The message appears **optimistically** before the server confirms. Design a
pending/failed state — today a failure silently restores the text into the
composer and shows an error toast.

## Flow 4 — Reply

Two different mechanisms exist — **the designer should decide whether both stay**:

**A. Inline quoted reply**
```
Hover message → ↩ Reply → reply banner appears above composer
→ send → message shows a quoted block of the original
```

**B. Threaded reply**
```
Hover message → 💬 Reply in thread → thread panel opens
→ type in the panel's composer → reply appears in the thread
→ the parent message gains a "N replies" link
```

Replies are visible **both** inline in the channel and in the thread panel.
This double-exposure is a real UX question worth resolving in the redesign.

## Flow 5 — React

```
Hover message → 👍 / ❤️ quick-react, or 😊 → 24-emoji picker → pick
→ pill appears; clicking your own pill removes it
```

## Flow 6 — Share a file

```
Attach button or drag onto composer → preview strip (up to 10)
→ optionally add text → send
→ images render as a grid (click = lightbox); other files render as cards
```

## Flow 7 — Create a channel and add people

```
Sidebar + → Create channel modal (name, topic, public/private) → created
→ chat header Members → add from workspace list
```
Private channels have **no self-serve join** — an admin must add people.

## Flow 8 — Leave a channel

```
Chat header ⋮ → Leave Channel → confirm → redirected to channel list
```
Blocked with an explanatory toast if you are the **last person who can manage
the channel** and others remain.

## Flow 9 — Direct message

```
Sidebar DM list (or Members → Message) → conversation opens
→ chat in bubble style → optionally start a call
```

## Flow 10 — Call

```
DM header → 📞 or 🎥 → callee sees Incoming Call toast
→ Accept → both enter the full-screen Call Screen
→ Mute / camera / End call
```
Rejected or missed calls produce a **missed call notification**.

## Flow 11 — Catch up on unread

```
Sidebar shows bold channel + count badge → open → badge clears, marked read
→ badges survive refresh (server-persisted read position)
```
Muted channels **never** raise a badge.

## Flow 12 — Switch workspace

```
Workspace switcher → pick another → full page load onto that subdomain
```
Nothing carries over — different data, different members.
