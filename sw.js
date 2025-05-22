// sw.js

self.addEventListener('push', event => {
    console.log('[Service Worker] Push Received.');
    console.log(`[Service Worker] Push had this data: "${event.data.text()}"`);

    let notificationData = {};
    try {
        notificationData = event.data.json();
    } catch (e) {
        console.warn('[Service Worker] Push event data was not JSON, treating as text.');
        notificationData = {
            title: 'New Notification',
            body: event.data.text(),
            icon: 'images/icon.png', // Default icon
            url: '/' // Default URL to open on click
        };
    }

    const title = notificationData.title || 'New Notification';
    const options = {
        body: notificationData.body || 'Something new happened!',
        icon: notificationData.icon || 'images/icon.png',
        badge: notificationData.badge || 'images/badge.png', // Optional: for Android status bar
        data: {
            url: notificationData.url || self.registration.scope // URL to open on click
        }
        // You can add more options here:
        // vibrate: [200, 100, 200],
        // image: 'images/notification_image.jpg',
        // actions: [
        //   { action: 'explore', title: 'Explore this new world', icon: 'images/checkmark.png' },
        //   { action: 'close', title: 'Close', icon: 'images/xmark.png' },
        // ]
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', event => {
    console.log('[Service Worker] Notification click Received.');

    event.notification.close(); // Close the notification

    // Open the URL associated with the notification
    // If no URL is specified in the data, it opens the client matching the scope
    const urlToOpen = event.notification.data.url || self.registration.scope;
    
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(clientList => {
            // Check if there's already a tab open with the target URL
            for (let i = 0; i < clientList.length; i++) {
                let client = clientList[i];
                // If client is already open and focused, just focus it.
                // If client is open but not focused, focus it.
                // Note: client.url might have a trailing slash and urlToOpen might not, or vice versa.
                if (client.url === urlToOpen && 'focus' in client) {
                    return client.focus();
                }
            }
            // If no such tab is open, open a new one.
            if (clients.openWindow) {
                return clients.openWindow(urlToOpen);
            }
        })
    );
});

self.addEventListener('pushsubscriptionchange', event => {
    console.log('[Service Worker] Subscription expired or about to expire.');
    event.waitUntil(
        self.registration.pushManager.subscribe(event.oldSubscription.options)
        .then(subscription => {
            console.log('[Service Worker] Re-subscribed successfully.');
            // TODO: Send the new subscription to your server
            // This typically involves fetching the VAPID public key again
            // and calling a function similar to the initial subscription process.
            // Example:
            // return fetch('/api/update-subscription', { // Replace with your actual endpoint
            //     method: 'POST',
            //     headers: { 'Content-Type': 'application/json' },
            //     body: JSON.stringify(subscription)
            // });
        })
        .catch(error => {
            console.error('[Service Worker] Failed to re-subscribe:', error);
        })
    );
});

// Optional: Add a skipWaiting and clients.claim() to activate the SW faster during development/updates
self.addEventListener('install', event => {
    console.log('[Service Worker] Install event');
    // event.waitUntil(self.skipWaiting()); // Activate worker immediately
});

self.addEventListener('activate', event => {
    console.log('[Service Worker] Activate event');
    // event.waitUntil(clients.claim()); // Become available to all pages
});
