/**
 * Shared helpers for rendering tenant notifications.
 * Payload shape: { id, type, data: { sender_name, preview, channel_id,
 * channel_name, conversation_id, call_type, ... }, read_at, created_at }
 */

/** Action phrase without the sender name (for UIs that bold the sender). */
export function notificationAction(n) {
  switch (n.type) {
    case 'mention':         return `mentioned you in #${n.data?.channel_name ?? 'a channel'}`;
    case 'channel_message': return `posted in #${n.data?.channel_name ?? 'a channel'}`;
    case 'dm_message':      return 'sent you a message';
    case 'missed_call':     return `called you (${n.data?.call_type === 'video' ? 'video' : 'audio'}) — missed`;
    default:                return 'sent a notification';
  }
}

/** Full one-line text including the sender name (for toasts). */
export function notificationText(n) {
  const sender = n.data?.sender_name ?? 'Someone';
  if (n.type === 'missed_call') {
    return `Missed ${n.data?.call_type === 'video' ? 'video' : 'audio'} call from ${sender}`;
  }
  return `${sender} ${notificationAction(n)}`;
}

export function notificationRoute(n) {
  if (n.type === 'dm_message' && n.data?.conversation_id) {
    return `/dm/${n.data.conversation_id}`;
  }
  if ((n.type === 'mention' || n.type === 'channel_message') && n.data?.channel_id) {
    return `/channels/${n.data.channel_id}`;
  }
  if (n.type === 'missed_call') {
    return '/dm';
  }
  return null;
}

/**
 * True when the user is already looking at the conversation/channel the
 * notification refers to — no toast needed in that case.
 */
export function isViewingSource(n) {
  const path = window.location.pathname;
  if (n.type === 'dm_message' && n.data?.conversation_id) {
    return path === `/dm/${n.data.conversation_id}`;
  }
  if ((n.type === 'mention' || n.type === 'channel_message') && n.data?.channel_id) {
    return path === `/channels/${n.data.channel_id}`;
  }
  return false;
}
