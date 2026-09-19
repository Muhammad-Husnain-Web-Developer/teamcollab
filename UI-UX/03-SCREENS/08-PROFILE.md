# Screen 08 — Profile & Account Settings

At `/profile`. Heading **"Account Settings"** /
"Manage your profile, status and security".

## Sections

**1. Profile header**
- Avatar with an **Edit** affordance → opens the avatar crop modal
- Name and handle

**2. Profile Information**
- `Username` (`username`)
- `Display Name` ("How others see you")
- `Bio (optional)` — "Tell your team a little about yourself…"

**3. Status**
- Presence/status control — status text and emoji

**4. Change Password**
- `Current Password`, `New Password`, `Confirm Password` — all `••••••••`

## Notes

- This is the **only screen that displays flash success messages inline** today
  (everything else was silent until flash messages were wired into toasts).
- The avatar flow is: pick file → **crop modal** → upload. Design the crop
  modal's controls (zoom, drag, aspect lock) — see `04-MODALS-AND-OVERLAYS.md`.
- Consider splitting Profile / Account / Notifications preferences into tabs;
  it is one long column today.
