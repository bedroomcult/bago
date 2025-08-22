<?php
// Set the content type of the response to JSON
header('Content-Type: application/json');

// Define file and directory paths
$dbFile = 'db.json';
$backupDir = 'database-backup';

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

// --- 1. Backup the original db.json file ---

// Create the backup directory if it doesn't exist
if (!is_dir($backupDir)) {
    if (!mkdir($backupDir, 0777, true)) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to create backup directory.']);
        exit;
    }
}

// Create a timestamped backup file name
$backupFile = $backupDir . '/db-' . date('Y-m-d-H-i-s') . '.json';

// Copy the existing db.json to the backup location if it exists
if (file_exists($dbFile)) {
    if (!copy($dbFile, $backupFile)) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to create database backup.']);
        exit;
    }
}

// --- 2. Update the db.json file ---

// Get the JSON payload from the request body
$jsonPayload = file_get_contents('php://input');
if ($jsonPayload === false) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Failed to read request data.']);
    exit;
}

// Decode the JSON to ensure it's valid
$data = json_decode($jsonPayload);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data received.']);
    exit;
}

// Re-encode the data with pretty printing to keep the file readable
// JSON_UNESCAPED_SLASHES prevents escaping slashes in image paths
$newJsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

// Write the new data to db.json
if (file_put_contents($dbFile, $newJsonData) === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to write to database file. Check permissions.']);
    exit;
}

// --- 3. Send success response ---
echo json_encode(['status' => 'success', 'message' => 'Products updated successfully. Backup created at ' . $backupFile]);

?>