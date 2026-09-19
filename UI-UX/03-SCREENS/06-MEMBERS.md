# Screen 06 — Members Directory

Workspace-wide people list at `/members`.

## Layout

- Heading **"Members"**
- **Filter bar**
  - Search input: "Search members…"
  - Role select: `All roles` · Owner · Admin · Member
  - Status select: `All statuses` · Online · Away · Offline
- **Member list/grid** — avatar, name, presence, role, and:
  - `Title`
  - `Timezone`
  - `Member since`
- **Empty state**: "No members found" / "Try adjusting your search or filters."

## Member profile (`/members/{id}`)

Reached by clicking a member or "View Profile" from a DM menu. Shows the
person's public profile and should offer a "Message" action.

## Design notes

- Decide **list vs card grid** — the data (title, timezone, member since)
  suits cards, but scanning suits a table.
- **Timezone is shown** — a "local time right now" affordance would be a cheap,
  high-value addition for distributed teams.
- Presence needs a consistent dot treatment shared with the sidebar and DM header.
