// Autocomplete-only list — the actual execution is server-side
// (App\Services\SlashCommandService) so there is a single source of truth
// for what a command does. This just drives the "/" suggestion dropdown.
export const SLASH_COMMANDS = [
  { command: 'invite', usage: '/invite @name', description: 'Add a member to this channel' },
  { command: 'mute', usage: '/mute', description: 'Toggle mute for this channel' },
  { command: 'remind', usage: '/remind me "text" in 10m', description: 'Schedule a reminder message' },
  { command: 'giphy', usage: '/giphy cats', description: 'Search and send a GIF' },
];

export function matchSlashCommands(query) {
  const q = query.toLowerCase();
  return SLASH_COMMANDS.filter(c => c.command.startsWith(q));
}

export function isKnownSlashCommand(text) {
  const match = text.match(/^\/(\w+)/);
  if (!match) return false;
  return SLASH_COMMANDS.some(c => c.command === match[1].toLowerCase());
}
