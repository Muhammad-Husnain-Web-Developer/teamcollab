# 04 — Modals, Panels, Popovers & Overlays

**Every** overlay in the product. None of these are optional for the redesign —
they are where a lot of the perceived quality lives.

## Base modal component

`Components/Common/Modal.vue` — everything below except the popovers uses it.

- Teleported to `<body>`, `z-50`
- Backdrop: `bg-black/60` + `backdrop-blur-sm`, click-to-close
- Dialog: `dark-750` surface, `dark-600/60` border, `rounded-2xl`, heavy shadow
- Header: title (16px semibold) + ✕ close button, bottom border
- Body: scrolls, capped at `80vh`
- Optional footer slot
- Sizes: `sm` · `md` · `lg` · `xl` · `full`
- Animated backdrop fade + dialog scale/slide

**Gap:** closing on `Escape` is bound to the dialog element, which is not
focused — so Escape does not reliably close. Worth fixing in the rebuild.

---

## 1. Create Channel
*Sidebar `+` next to "Channels"* · size `md`

- Title: "Create a channel"
- `Channel name` — placeholder `e.g. marketing`
- `Topic (optional)` — "What's this channel about?"
- **Visibility** — Public / Private choice
- Create + Cancel actions

Needs: a `#` prefix affordance on the name field, name validation feedback, and
a clear explanation of the public/private difference.

## 2. Channel Members
*Chat header → Members, or ⋮ → Members* · size `md`

- Title: "Members of #{channel}"
- **Add a member** section *(admins only)*
  - Search input: "Search workspace members…"
  - Scrollable candidate list — avatar, name, and an "Add" / "Adding…" action
  - Empty: "Everyone in this workspace is already in the channel."
  - No search match: "No one matches "{term}"."
- **In this channel (N)** — the member list
  - Avatar, name, `(you)` marker, **Admin** badge, and a remove ✕ *(admins only)*
  - The channel creator cannot be removed — no ✕ shown
- Non-admin footnote: "Only channel admins can add or remove members."

**Design gap:** there is no way to promote someone to admin. See `08-KNOWN-GAPS.md`.

## 3. Invite Members (workspace)
*Workspace Settings → Invite* · size `md`

- Title: "Invite Members"
- A textarea for `Email addresses`, placeholder shows two example emails on
  separate lines, helper "One email per line."
- Needs: role selection for invitees, a pending-invites list, and a success state.

## 4. Delete Workspace confirmation
*Workspace Settings → Danger Zone* · size `sm`

- Title: "Delete Workspace"
- Warning copy about permanent data loss
- Should require typing the workspace name to confirm — **design that**

## 5. Avatar Crop
*Profile → Edit avatar*

`AvatarCropModal.vue` — the image cropper. Needs a designed treatment for the
crop viewport, zoom control, drag hint, and Save/Cancel.

## 6. Image Lightbox
*Clicking any image attachment*

`ImageLightbox.vue` — full-screen image viewer. Needs: close affordance,
download action, and (if multiple images) prev/next navigation.

## 7. Thread Panel
*Message → "N replies" or ⋮ → Reply in thread*

Not a modal — a **side panel** (400px, 440px on xl). Below 1024px it becomes a
full-screen overlay. Detailed in `03-SCREENS/04-CHANNEL-CHAT.md`.

## 8. Right Panel (Details)
*Chat header → ⓘ*

256px panel. **Currently an empty shell** — heading + placeholder text only.

## 9. Emoji Picker popover
*Message hover bar → 😊, or the reactions `+`*

Anchored popover, 24-emoji grid, closes on outside click. There is a **second**
emoji picker in the composer toolbar. Both need designing — ideally as one
shared component with search and categories.

## 10. Dropdown menus

Consistent style: `dark-750`, `dark-600/60` border, `rounded-xl`, shadow,
`py-1`, items `px-3 py-2.5` with a 16px leading icon, destructive items in red,
with `h-px` dividers.

| Menu | Items |
|---|---|
| **Channel ⋮** | Members · Mute/Unmute Notifications · **Leave Channel** |
| **DM ⋮** | View Profile · Mute Notifications · Search in Chat · **Close Conversation** |
| **Workspace switcher** | The user's workspaces + create new |
| **Sidebar user footer** | Set a status · Profile & Settings · Sign Out |

## 11. Toasts

`ToastContainer` + `Toast`, fixed overlay. Four variants: **success, error,
warning, info**. Auto-dismiss (default 4s). Now also carry all server flash
messages, so they are a primary feedback channel — design them properly.

## 12. Incoming Call toast

Slides in when someone calls: caller avatar and name, call type, and
**Accept / Decline** actions. Needs an urgent, unmissable treatment distinct
from ordinary toasts.

## 13. Call Screen

Full-screen call UI:
- Remote video filling the screen, local video as a small inset
- Duration timer, `MM:SS`
- Controls: **Mute** · **Camera on/off** · **End call** (red) · Speaker
  *(disabled, "coming soon")*
- Needs states for: ringing/connecting, connected, reconnecting, audio-only,
  and call-failed.

## 14. Drag-and-drop overlay

Covers the composer when files are dragged over: "Drop files to upload".

## 15. Search bar (inline)

Slides down from the chat header, placeholder "Search in conversation…".
The results presentation is undesigned.
