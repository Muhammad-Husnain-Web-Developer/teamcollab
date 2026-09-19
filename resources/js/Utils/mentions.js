/**
 * @mention autocomplete over the workspace member list Inertia already
 * shares on every page — no per-keystroke request, no extra endpoint.
 *
 * Ranking: prefix matches on name/display name first (what people expect
 * while typing), then substring matches anywhere in name/display name/email.
 * An empty query lists everyone, alphabetically.
 */
export function filterMentionCandidates(members, query, limit = 6) {
  const list = Array.isArray(members) ? members : [];
  const q = (query ?? '').trim().toLowerCase();

  const fields = (m) => [m.display_name, m.name, m.email]
    .filter(Boolean)
    .map(v => String(v).toLowerCase());

  const byName = (a, b) => (a.display_name || a.name || '').localeCompare(b.display_name || b.name || '');

  if (!q) return [...list].sort(byName).slice(0, limit);

  const prefix    = [];
  const substring = [];
  for (const m of list) {
    const values = fields(m);
    if (values.some(v => v.startsWith(q) || v.split(/\s+/).some(word => word.startsWith(q)))) {
      prefix.push(m);
    } else if (values.some(v => v.includes(q))) {
      substring.push(m);
    }
  }

  return [...prefix.sort(byName), ...substring.sort(byName)].slice(0, limit);
}
