<?php
header('Content-Type: application/json');

// --- Database Configuration - REPLACE WITH YOUR ACTUAL CREDENTIALS ---
define('DB_HOST', 'localhost'); // Or your MariaDB/MySQL host
define('DB_NAME', 'push_notifications_db');
define('DB_USER', 'your_db_user');
define('DB_PASSWORD', 'your_db_password');
// --- End Database Configuration ---

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

// Get the raw POST data
$rawData = file_get_contents('php://input');
$subscriptionData = json_decode($rawData, true);

if (!$subscriptionData || empty($subscriptionData['endpoint'])) {
    $response['message'] = 'Error: Invalid or missing subscription data.';
    echo json_encode($response);
    exit;
}

$endpoint = $subscriptionData['endpoint'];
$p256dh = $subscriptionData['keys']['p256dh'] ?? null;
$auth = $subscriptionData['keys']['auth'] ?? null;

if (!$p256dh || !$auth) {
    $response['message'] = 'Error: Missing p256dh or auth key in subscription data.';
    echo json_encode($response);
    exit;
}

// Connect to MariaDB/MySQL
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

// Check connection
if ($mysqli->connect_error) {
    $response['message'] = 'Database connection failed: ' . $mysqli->connect_error;
    // In a real app, log this error, don't expose details to the client
    error_log('Database connection failed: ' . $mysqli->connect_error);
    echo json_encode($response);
    exit;
}

// Attempt to create the table if it doesn't exist
$createTableQuery = "
CREATE TABLE IF NOT EXISTS `subscriptions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `endpoint` VARCHAR(512) NOT NULL UNIQUE,
    `p256dh` VARCHAR(255) NOT NULL,
    `auth` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if (!$mysqli->query($createTableQuery)) {
    $response['message'] = 'Error creating subscriptions table: ' . $mysqli->error;
    error_log('Error creating subscriptions table: ' . $mysqli->error);
    echo json_encode($response);
    exit;
}

// Check if the endpoint already exists to prevent duplicates
$stmtCheck = $mysqli->prepare("SELECT id FROM `subscriptions` WHERE `endpoint` = ?");
if (!$stmtCheck) {
    $response['message'] = 'Error preparing select statement: ' . $mysqli->error;
    error_log('Error preparing select statement: ' . $mysqli->error);
    echo json_encode($response);
    exit;
}
$stmtCheck->bind_param('s', $endpoint);
$stmtCheck->execute();
$stmtCheck->store_result();

if ($stmtCheck->num_rows > 0) {
    $response['success'] = true; // Or false, if you want to indicate it's a duplicate
    $response['message'] = 'Subscription already exists.';
    $stmtCheck->close();
    $mysqli->close();
    echo json_encode($response);
    exit;
}
$stmtCheck->close();

// Insert the new subscription
$stmtInsert = $mysqli->prepare("INSERT INTO `subscriptions` (endpoint, p256dh, auth) VALUES (?, ?, ?)");
if (!$stmtInsert) {
    $response['message'] = 'Error preparing insert statement: ' . $mysqli->error;
    error_log('Error preparing insert statement: ' . $mysqli->error);
    echo json_encode($response);
    exit;
}
$stmtInsert->bind_param('sss', $endpoint, $p256dh, $auth);

if ($stmtInsert->execute()) {
    $response['success'] = true;
    $response['message'] = 'Subscription saved successfully.';
} else {
    $response['message'] = 'Error saving subscription: ' . $stmtInsert->error;
    // Potentially, the endpoint might have been inserted between the SELECT and INSERT (race condition)
    // if multiple requests for the same endpoint arrive simultaneously.
    // The UNIQUE constraint on `endpoint` should prevent duplicates.
    if ($mysqli->errno == 1062) { // Error code for duplicate entry
        $response['success'] = true; // Or false, depending on desired behavior for duplicates
        $response['message'] = 'Subscription already exists (caught by insert).';
    } else {
        error_log('Error saving subscription: ' . $stmtInsert->error);
    }
}

$stmtInsert->close();
$mysqli->close();

echo json_encode($response);
?>
