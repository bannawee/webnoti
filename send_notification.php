<?php
// send_notification.php

// IMPORTANT: Ensure you have minishlink/web-push installed via Composer
// Run: composer require minishlink/web-push
// And ensure vendor/autoload.php is correctly included.
require_once __DIR__ . '/vendor/autoload.php'; // Adjust path if your vendor dir is elsewhere

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

// --- VAPID Keys - REPLACE WITH YOUR ACTUAL KEYS ---
// You can generate these once and keep them safe.
// See: https://github.com/minishlink/web-push-php-example/blob/master/src/generate_vapid_keys.php
// Or use: VAPID::createVapidKeys()
define('VAPID_SUBJECT', 'mailto:your-email@example.com'); // Replace with your email or website
define('VAPID_PUBLIC_KEY', 'YOUR_VAPID_PUBLIC_KEY');     // <--- REPLACE THIS
define('VAPID_PRIVATE_KEY', 'YOUR_VAPID_PRIVATE_KEY');   // <--- REPLACE THIS
// --- End VAPID Keys ---

// --- Database Configuration - REPLACE WITH YOUR ACTUAL CREDENTIALS ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'push_notifications_db');
define('DB_USER', 'your_db_user');
define('DB_PASSWORD', 'your_db_password');
// --- End Database Configuration ---

// Notification Payload
$notificationPayload = [
    'title' => 'Hello from Server!',
    'body' => 'This is a test push notification sent at ' . date('H:i:s'),
    'icon' => 'images/icon-192x192.png', // Relative to the domain root
    'badge' => 'images/icon-192x192.png', // For Android status bar (optional)
    'url' => '/index.html', // URL to open when notification is clicked (relative to domain)
    // 'image' => 'images/some_promo_image.jpg', // Optional large image
];
$payloadString = json_encode($notificationPayload);

if (VAPID_PUBLIC_KEY === 'YOUR_VAPID_PUBLIC_KEY' || VAPID_PRIVATE_KEY === 'YOUR_VAPID_PRIVATE_KEY') {
    die("Error: VAPID keys are not set in send_notification.php. Please generate them and update the script.\n");
}

// Connect to MariaDB/MySQL
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($mysqli->connect_error) {
    error_log('Database connection failed: ' . $mysqli->connect_error);
    die('Database connection failed. Check error logs.');
}

// Fetch subscriptions
$result = $mysqli->query("SELECT id, endpoint, p256dh, auth FROM `subscriptions`");
if (!$result) {
    error_log('Error fetching subscriptions: ' . $mysqli->error);
    die('Error fetching subscriptions. Check error logs.');
}

$subscriptionsFromDb = [];
while ($row = $result->fetch_assoc()) {
    $subscriptionsFromDb[] = $row;
}
$mysqli->close();

if (empty($subscriptionsFromDb)) {
    echo "No subscriptions found in the database to send notifications to.\n";
    exit;
}

// Prepare WebPush object
$auth = [
    'VAPID' => [
        'subject' => VAPID_SUBJECT,
        'publicKey' => VAPID_PUBLIC_KEY,
        'privateKey' => VAPID_PRIVATE_KEY,
    ],
];
$webPush = new WebPush($auth);
// $webPush->setReuseVAPIDHeaders(true); // Optional: can improve performance for many notifications

echo "Attempting to send notifications to " . count($subscriptionsFromDb) . " subscription(s)...\n";

foreach ($subscriptionsFromDb as $sub) {
    $subscription = Subscription::create([
        'endpoint' => $sub['endpoint'],
        'publicKey' => $sub['p256dh'],
        'authToken' => $sub['auth'],
        // 'contentEncoding' => 'aesgcm', // aesgcm is default, aes128gcm is new spec
    ]);
    $webPush->queueNotification($subscription, $payloadString);
}

// Flush notifications (send them)
$failedSubscriptions = [];
foreach ($webPush->flush() as $report) {
    $endpoint = $report->getRequest()->getUri()->__toString();
    if ($report->isSuccess()) {
        echo "[v] Message sent successfully for endpoint: {$endpoint}.\n";
    } else {
        echo "[x] Message failed to send for endpoint: {$endpoint}: {$report->getReason()}\n";
        // Add to list of failed subscriptions for potential cleanup
        $failedSubscriptions[] = [
            'endpoint' => $endpoint,
            'reason' => $report->getReason(),
            'isGone' => $report->isSubscriptionExpired() // 404 or 410 status code
        ];
    }
}

// Optional: Handle failed subscriptions (e.g., remove them from DB)
if (!empty($failedSubscriptions)) {
    echo "\n--- Failed Subscriptions ---\n";
    // Re-connect to DB for cleanup
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    if ($mysqli->connect_error) {
        error_log('Database connection failed (for cleanup): ' . $mysqli->connect_error);
        echo "Could not connect to DB for cleanup.\n";
    } else {
        $stmtDelete = $mysqli->prepare("DELETE FROM `subscriptions` WHERE `endpoint` = ?");
        if (!$stmtDelete) {
            error_log('Error preparing delete statement: ' . $mysqli->error);
            echo "Error preparing delete statement for cleanup.\n";
        } else {
            foreach ($failedSubscriptions as $failure) {
                if ($failure['isGone']) { // Only remove if subscription is confirmed gone
                    $stmtDelete->bind_param('s', $failure['endpoint']);
                    if ($stmtDelete->execute()) {
                        echo "Removed expired/invalid subscription: {$failure['endpoint']}\n";
                    } else {
                        error_log("Error removing subscription {$failure['endpoint']}: " . $stmtDelete->error);
                        echo "Failed to remove subscription: {$failure['endpoint']}\n";
                    }
                }
            }
            $stmtDelete->close();
        }
        $mysqli->close();
    }
    echo "--------------------------\n";
}

echo "\nNotification sending process complete.\n";

?>
