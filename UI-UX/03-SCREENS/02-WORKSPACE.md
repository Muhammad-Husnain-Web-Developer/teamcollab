# Screens 02 — Workspace

## 02a — Workspace Index (picker)

Where a user lands when they belong to more than one workspace, or after login
on the central domain.

- Heading **"Your Workspaces"**, sub "Select a workspace to continue".
- A card/list per workspace: logo, name, and presumably member count.
- An action to **create a new workspace**.

**Needs an empty state**: a brand-new user with no workspaces. Not designed today.

## 02b — Workspace Create

A **multi-step wizard**.

**Step 1 — Name your workspace**
- Sub: "This is how your team will identify your workspace."
- `Workspace name` (`Acme Corporation`)
- URL slug (`acme-corp`) with helper "Only lowercase letters, numbers, and
  hyphens." — the slug auto-derives from the name and becomes the subdomain.
  Design the `{slug}.teamcollab.app` preview affordance.

**Step 2 — Tell us about your team**
- Sub: "Help us personalise your experience."
- `Timezone` select (placeholder "Select timezone…")
- `Company size` select

**Step 3 — Success**
- "Workspace created!" / "Redirecting to your workspace…"

Design needed: **step indicator / progress**, back navigation, and the
transition between steps.

## 02c — Workspace Settings

Owner/admin only. Sections top to bottom:

**Header** — "Workspace Settings" / "Manage your workspace configuration"

**1. Workspace Identity**
- **Workspace logo** uploader — "PNG, JPG, GIF up to 2MB.",
  "Recommended: 256×256px". Needs: empty state, hover-to-replace, uploading,
  and error states.
- `Workspace name`
- `Timezone`
- `Description (optional)` — "What does your team work on?"

**2. Members**
- A member table/list with an **Invite** button opening a modal.
- Empty state copy exists: "No members found."
- Roles are shown and changeable here.

**3. Danger Zone**
- "Delete this workspace"
- "Once deleted, all data is permanently removed. This action cannot be undone."
- Opens a confirmation modal.

The Danger Zone should read as visually distinct — today it is only red text.
