// Served from the site root so its scope covers the whole tenant-subdomain
// origin — this is what lets push notifications reach the user when no
// TeamCollab tab is open at all (the in-app Notification API in AppLayout.vue
// only works while a tab's JS is still running).

self.addEventListener('push', (event) => {
  if (!event.data) return;

  let payload;
  try {
    payload = event.data.json();
  } catch {
    payload = { title: 'TeamCollab', body: event.data.text() };
  }

  const title = payload.title || 'TeamCollab';
  const options = {
    body: payload.body || '',
    icon: payload.icon || '/favicon.ico',
    badge: payload.badge,
    tag: payload.tag,
    data: payload.data || {},
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const url = event.notification.data?.url;
  if (!url) return;

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
      for (const client of windowClients) {
        try {
          if (new URL(client.url).pathname === url && 'focus' in client) {
            return client.focus();
          }
        } catch {
          // Ignore a malformed client URL and keep checking the rest.
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(url);
      }
    }),
  );
});
