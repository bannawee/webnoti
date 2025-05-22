feat: Implement Web Push Notification App (PHP + MariaDB)

Adds a complete Web Push Notification system.

Features:
- Frontend (`index.html`, `script.js`): Allows you to subscribe to push notifications.
- Service Worker (`sw.js`): Handles incoming push messages, displays notifications, and manages notification clicks.
- PWA Manifest (`manifest.json`): Basic PWA setup.
- Backend (`subscribe.php`): Saves subscription details to a MariaDB/MySQL database, including table auto-creation.
- Backend (`send_notification.php`): Sends push notifications to subscribed users using the minishlink/web-push 
