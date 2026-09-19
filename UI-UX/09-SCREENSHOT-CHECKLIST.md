# 09 — Screenshot Checklist

**Status: no screenshots captured yet.** The folder tree under `screenshots/`
is created and the filenames below are the agreed naming convention. Tick items
off as they are captured.

## How to capture

1. Start the app (four terminals, from the project root):
   ```
   php artisan serve --host=0.0.0.0 --port=8001
   php artisan reverb:start
   php artisan queue:work
   npm run dev
   ```
2. Open a workspace subdomain — e.g. `http://hamid.localhost:8001`
   (plain `localhost:8001` will not show the app; it is multi-tenant).
3. Capture at **1440×900** for desktop and **390×844** (iPhone 14) for mobile.
4. Use two different browsers/profiles logged in as two users for anything
   involving real-time, typing, calls, or presence.
5. Save as PNG into the matching folder using the filename given below.

---

## 01-auth/

- [ ] `login-desktop.png` — full split layout
- [ ] `login-workspace-branded.png` — "Sign in to {Workspace}" variant
- [ ] `login-error.png` — wrong credentials, inline errors visible
- [ ] `login-mobile.png` — marketing panel hidden
- [ ] `register.png`
- [ ] `register-password-mismatch.png` — negative match feedback
- [ ] `register-password-match.png` — positive match feedback
- [ ] `forgot-password.png`
- [ ] `reset-password.png`
- [ ] `verify-email.png`

## 02-workspace/

- [ ] `workspace-picker.png`
- [ ] `create-step1-name.png`
- [ ] `create-step2-details.png`
- [ ] `create-step3-success.png`
- [ ] `settings-identity.png`
- [ ] `settings-members.png`
- [ ] `settings-danger-zone.png`

## 03-dashboard/

- [ ] `dashboard-full.png`
- [ ] `dashboard-stats-cards.png` — close crop
- [ ] `dashboard-message-chart.png` — close crop
- [ ] `dashboard-empty-activity.png`

## 04-channel/

- [ ] `channel-full.png` — the whole three-column view
- [ ] `channel-header-public.png` — `#` glyph
- [ ] `channel-header-private.png` — padlock glyph
- [ ] `channel-header-menu.png` — ⋮ dropdown open
- [ ] `message-single.png` — one message, avatar + name + time
- [ ] `message-grouped.png` — consecutive messages, no repeated avatar
- [ ] `message-date-separator.png` — "Today" / "Yesterday"
- [ ] `message-hover-actions.png` — hover bar visible
- [ ] `message-markdown.png` — bold, italic, code, list, quote in one message
- [ ] `message-code-block.png` — fenced block, ideally wide enough to scroll
- [ ] `message-mention.png` — highlighted @mention
- [ ] `message-reply-quote.png` — inline quoted reply
- [ ] `message-reactions.png` — several reaction pills, one of them yours
- [ ] `message-thread-link.png` — "N replies" with participant avatars
- [ ] `message-edited.png`
- [ ] `message-deleted.png` — the placeholder box
- [ ] `message-images.png` — image attachment grid
- [ ] `message-files.png` — non-image file cards
- [ ] `composer-empty.png`
- [ ] `composer-toolbar.png` — close crop of Bold/Italic/Code/Emoji/Attach
- [ ] `composer-reply-banner.png`
- [ ] `composer-file-preview.png` — pending attachments
- [ ] `composer-drag-drop.png` — "Drop files to upload" overlay
- [ ] `typing-indicator.png`
- [ ] `thread-panel-open.png` — desktop, alongside the channel
- [ ] `thread-panel-empty.png` — "No replies yet"
- [ ] `emoji-picker.png` — the 24-emoji grid popover
- [ ] `search-in-conversation.png` — inline search bar open
- [ ] `sidebar-unread-badges.png` — bold channel + count badge

## 05-dm/

- [ ] `dm-full.png`
- [ ] `dm-header.png` — avatar, presence dot, status text, call buttons
- [ ] `dm-bubbles.png` — own (brand) and their (dark) bubbles together
- [ ] `dm-bubble-with-quote.png` — reply inside a bubble
- [ ] `dm-header-menu.png` — ⋮ dropdown open
- [ ] `dm-empty.png` — brand-new conversation
- [ ] `dm-info-sections.png` — Info / Files / Pinned column

## 06-members/

- [ ] `members-list.png`
- [ ] `members-filters.png` — role + status selects open
- [ ] `members-empty.png` — "No members found"
- [ ] `member-profile.png` — single member page

## 07-notifications/

- [ ] `notification-bell-badge.png` — unread count on the bell
- [ ] `notification-dropdown.png`
- [ ] `notification-dropdown-empty.png`
- [ ] `notifications-page.png`
- [ ] `notifications-page-empty.png`

## 08-profile/

- [ ] `profile-full.png`
- [ ] `profile-information.png`
- [ ] `profile-status.png`
- [ ] `profile-change-password.png`
- [ ] `profile-flash-success.png` — the inline success message

## 09-modals/

- [ ] `modal-create-channel.png`
- [ ] `modal-channel-members-admin.png` — with the "Add a member" section
- [ ] `modal-channel-members-nonadmin.png` — with the admin-only footnote
- [ ] `modal-invite-members.png`
- [ ] `modal-delete-workspace.png`
- [ ] `modal-avatar-crop.png`
- [ ] `lightbox-image.png`
- [ ] `toast-success.png`
- [ ] `toast-error.png`
- [ ] `incoming-call-toast.png`
- [ ] `call-screen-video.png`
- [ ] `call-screen-audio.png`
- [ ] `right-panel-details.png` — the empty shell, for the record

## 10-mobile/ (390×844)

- [ ] `mobile-login.png`
- [ ] `mobile-channel.png` — sidebar closed, hamburger visible
- [ ] `mobile-sidebar-open.png` — overlay + scrim
- [ ] `mobile-dm.png`
- [ ] `mobile-thread-panel.png` — full-screen overlay
- [ ] `mobile-composer.png`
- [ ] `mobile-dashboard.png`
- [ ] `mobile-members.png`

## 11-states/

- [ ] `empty-messages.png`
- [ ] `loading-skeletons.png`
- [ ] `loading-older-messages.png`
- [ ] `long-channel-name-truncated.png`
- [ ] `many-reactions-wrapped.png`
- [ ] `long-message.png` — very long body
- [ ] `presence-online-away-offline.png` — all three dots together
- [ ] `unread-badge-99plus.png`

---

**Total: ~95 shots.** The 04-channel and 09-modals sets matter most — that is
where the product's character lives.
