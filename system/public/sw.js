self.addEventListener('push', (event) => {
    let data = {
        title: 'NEXIAM Alert',
        body: 'You have a new update in your NEXIAM portal.',
        url: '/notifications',
        tag: 'nexiam-alert'
    };

    if (event.data) {
        try {
            data = Object.assign(data, event.data.json());
        } catch {
            data.body = event.data.text() || data.body;
        }
    }

    const options = {
        body: data.body,
        icon: '/assets/branding/nexiam/nexiam-xiam-icon-512.png',
        badge: '/assets/branding/nexiam/nexiam-xiam-icon-512.png',
        tag: data.tag,
        data: {
            url: data.url
        }
    };

    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const destinationUrl = event.notification.data?.url || '/notifications';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            for (const client of windowClients) {
                if (client.url.includes(self.location.origin) && 'focus' in client) {
                    client.navigate(destinationUrl);
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(destinationUrl);
            }
        })
    );
});
