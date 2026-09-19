# 01 — Product Overview

## What it is

TeamCollab is a team chat application — the same category as Slack, Microsoft
Teams and Discord. Teams talk in **channels** (topic rooms) and in **direct
messages** (one-to-one), share files, react with emoji, reply in threads, and
call each other with audio or video.

## The multi-workspace model — important for design

This is **not** a single-company app. It is multi-tenant: each customer gets
their own isolated workspace with its own database, its own members, and its
own subdomain.

```
hamid.localhost      →  Hamid's workspace
proxify.localhost    →  a completely separate workspace
```

Design consequences:

- A person can belong to **several workspaces** with one login, and switches
  between them (see the Workspace Switcher in the sidebar).
- Nothing is shared across workspaces — not channels, not messages, not files.
- Branding is per-workspace: each has its own **name and logo**.
- There is a workspace-picker screen before you land in any workspace.

## Who uses it

| Role | Can do |
|---|---|
| **Workspace owner** | Everything, including workspace settings and deleting the workspace |
| **Workspace admin** | Manage members and workspace settings |
| **Member** | Chat, create channels, join public channels |
| **Channel admin** | Additionally: add and remove people from that channel |

Roles exist at two levels — workspace and channel — and they are independent.
Someone can be a plain workspace member but an admin of a channel they created.

## Core objects

- **Workspace** — the company/team container. Has a name, logo, timezone.
- **Channel** — a topic room. **Public** (anyone in the workspace can join) or
  **Private** (invite only, added by a channel admin). Has a name, topic,
  member list, and can be archived.
- **Direct message** — a private one-to-one conversation. Always exactly two
  people; there are no group DMs.
- **Message** — text (Markdown), file attachments, emoji reactions, and replies.
- **Thread** — the set of replies hanging off one message, shown in a side panel.
- **Call** — audio or video, one-to-one, started from a DM.

## The screen the user lives in

Roughly 90% of usage is one screen: the three-column chat view.

```
┌──────────┬───────────────────────────────┬────────────┐
│          │  Channel header               │            │
│ Sidebar  ├───────────────────────────────┤  Thread    │
│          │                               │  panel     │
│ workspace│  Message list                 │  (opens on │
│ channels │  (scrolls)                    │   demand)  │
│ DMs      │                               │            │
│          ├───────────────────────────────┤            │
│ user     │  Composer                     │            │
└──────────┴───────────────────────────────┴────────────┘
```

Everything else — dashboard, members, settings, profile — is visited
occasionally. Weight the redesign effort accordingly.

## What makes the current design feel dated

Honest notes, to save the designer discovery time:

1. **Very dark, very flat.** Near-black greys with one blue accent. Little
   depth, few surfaces, low contrast between layers.
2. **Dense, uniform type.** Almost everything is 12–14px. Little hierarchy
   between a channel name, a message, and metadata.
3. **Icon-only actions with no labels**, relying on tooltips.
4. **No empty-state illustration** anywhere — empty states are one line of grey text.
5. **The right panel is an unfinished shell** — it renders a heading and
   "Select a channel to see details." and nothing else.
6. **Mobile is an afterthought** — see `08-KNOWN-GAPS.md`.
