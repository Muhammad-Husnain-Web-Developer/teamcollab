# Screen 03 — Dashboard

The landing page inside a workspace (route `/`).

## Content

- **Greeting header** — a personalised line plus
  "Here's what's happening in your workspace today."
- **Stats cards** row — `StatsCard` component, repeated. Each shows a metric
  with a label and (per the component) an icon and a trend indicator.
- **Message Activity** chart
  - Sub-label "Last 14 days", series label "Messages sent".
  - Rendered by `MessageChart` as a **hand-rolled SVG line/area chart** — no
    charting library. Whatever you design here has to be buildable in raw SVG,
    or the team adds a chart library.
- **Top Channels** — a ranked list with a "total" figure per row.
- **Recent Activity** — a feed. Empty state: "No recent activity."

## Design opportunities

This screen is generic and currently the weakest-value screen in the product —
people go straight to their channels. Two honest options worth raising with the
client:

1. Make it genuinely useful — unread digest, mentions waiting, threads the user
   is in, today's activity.
2. Drop it and land users in their last channel instead.

Do not spend heavy design effort here before that decision is made.
