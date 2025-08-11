<?php
header('Content-Type: application/json');

// Get the JSON data from the POST request body
$json_data = file_get_contents('php://input');

// Check if data was received
if ($json_data) {
    // Write the data to the db.json file
    if (file_put_contents('db.json', $json_data)) {
        echo json_encode(['status' => 'success', 'message' => 'Data saved successfully.']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to write to file. Check file permissions.']);
    }
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No data received.']);
}
?>
