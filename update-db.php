<?php
// Simple logging function to write to a local debug.log file.
function write_log($message) {
    // Prepend a timestamp to the message.
    $log_message = date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL;
    // Append the message to the log file.
    file_put_contents('debug.log', $log_message, FILE_APPEND);
}

// This function will run when the script is about to terminate.
register_shutdown_function('handleFatalError');

function handleFatalError() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR]) && !headers_sent()) {
        write_log("Fatal Error: " . json_encode($error));
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'A fatal server error occurred. Check server error logs or debug.log for details.',
            'php_error' => $error
        ]);
    }
}

// Set the content type of the response to JSON
header('Content-Type: application/json');

// The script now primarily handles DB updates, which may include an image upload.
handleDbUpdate();

/**
 * Processes and saves an uploaded image.
 *
 * @param array $file The file array from $_FILES.
 * @param string $productName The name of the product to associate the image with.
 * @param string &$errorMsg A variable to hold any error message.
 * @param bool $isTempUpload Whether this is a temporary upload for editing.
 * @return string|false The path to the saved image on success, false on failure.
 */
function processAndSaveImage($file, $productName, &$errorMsg, $isTempUpload = false)
{
    write_log("--- Starting Image Upload ---");

    // Attempt to increase memory limit for larger images.
    @ini_set('memory_limit', '256M');
    write_log("Memory limit set to 256M.");

    if (!function_exists('exif_imagetype') || !function_exists('imagecreatefromjpeg')) {
        $errorMsg = 'Server Configuration Error: Required PHP extensions (exif, GD) are not enabled.';
        write_log("Error: " . $errorMsg);
        return false;
    }
    write_log("Server extensions checked successfully.");

    $uploadDir = 'uploaded/';

    if (!is_dir($uploadDir)) {
        write_log("Upload directory does not exist. Attempting to create.");
        if (!mkdir($uploadDir, 0777, true) || !is_writable($uploadDir)) {
            $errorMsg = 'Failed to create or write to upload directory. Check folder permissions.';
            write_log("Error: " . $errorMsg);
            return false;
        }
    }
    write_log("Upload directory is writable.");

    $detectedType = @exif_imagetype($file['tmp_name']);
    $allowedImageTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];

    if (!in_array($detectedType, $allowedImageTypes)) {
        $errorMsg = 'Invalid file type. Server rejected file content.';
        write_log("Error: " . $errorMsg);
        return false;
    }
    write_log("File type detected as: " . image_type_to_mime_type($detectedType));

    $sourceImage = null;
    switch ($detectedType) {
        case IMAGETYPE_JPEG: $sourceImage = @imagecreatefromjpeg($file['tmp_name']); break;
        case IMAGETYPE_PNG: $sourceImage = @imagecreatefrompng($file['tmp_name']); break;
        case IMAGETYPE_GIF: $sourceImage = @imagecreatefromgif($file['tmp_name']); break;
        case IMAGETYPE_WEBP: $sourceImage = @imagecreatefromwebp($file['tmp_name']); break;
    }

    if (!$sourceImage) {
        $errorMsg = 'Failed to process image. It may be corrupt or too large for server memory.';
        write_log("Error: " . $errorMsg);
        return false;
    }
    write_log("Image resource created successfully.");

    $maxWidth = 800;
    $quality = 75;
    $width = imagesx($sourceImage);
    $height = imagesy($sourceImage);
    write_log("Original image dimensions: {$width}x{$height}.");

    $newWidth = $width > $maxWidth ? $maxWidth : $width;
    $newHeight = $width > $maxWidth ? floor($height * ($maxWidth / $width)) : $height;

    $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
    write_log("Resized canvas created with dimensions: {$newWidth}x{$newHeight}.");

    // Handle transparency for formats that support it by filling with a white background
    if (in_array($detectedType, [IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP])) {
        $white = imagecolorallocate($resizedImage, 255, 255, 255);
        imagefill($resizedImage, 0, 0, $white);
        write_log("Filled transparent background with white.");
    }

    imagecopyresampled($resizedImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    write_log("Image resampled.");

    // For temp uploads, generate a unique temp filename
    if ($isTempUpload) {
        $timestamp = time();
        $random = substr(md5(uniqid(mt_rand(), true)), 0, 8);
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '_', $productName));
        $fileName = "temp_{$timestamp}_{$random}_{$safeName}.jpg";
    } else {
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '_', $productName));
        $fileName = $safeName . '.jpg';
    }

    $filePath = $uploadDir . $fileName;

    if (imagejpeg($resizedImage, $filePath, $quality)) {
        write_log("Image saved successfully to: " . $filePath);
        imagedestroy($sourceImage);
        imagedestroy($resizedImage);
        return $filePath;
    } else {
        $errorMsg = 'Failed to save the processed image.';
        write_log("Error: " . $errorMsg);
        imagedestroy($sourceImage);
        imagedestroy($resizedImage);
        return false;
    }
}

/**
 * Handles backing up and updating the db.json file.
 * This function now also handles an optional image upload.
 */
function handleDbUpdate() {
    write_log("--- Starting DB Update ---");

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        write_log("Error: Invalid request method for DB update.");
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Invalid request method for DB update.']);
        exit;
    }

    // Handle temp file deletion request
    if (isset($_POST['deleteTempFile'])) {
        $tempFilePath = $_POST['deleteTempFile'];
        write_log("Deleting temp file: " . $tempFilePath);
        if (file_exists($tempFilePath)) {
            unlink($tempFilePath);
            write_log("Temp file deleted successfully.");
        } else {
            write_log("Temp file not found: " . $tempFilePath);
        }
        echo json_encode(['status' => 'success']);
        exit;
    }

    // Handle temp file move request
    if (isset($_POST['moveTempFile'])) {
        $fileOp = json_decode($_POST['moveTempFile'], true);
        $tempPath = $fileOp['tempPath'];
        $finalPath = $fileOp['finalPath'];
        $originalImage = $fileOp['originalImage'];

        write_log("Moving temp file: {$tempPath} to {$finalPath}");

        if (file_exists($tempPath)) {
            // Delete original file if it exists and is different from the new one
            if ($originalImage && $originalImage !== $finalPath && file_exists($originalImage)) {
                unlink($originalImage);
                write_log("Deleted original file: {$originalImage}");
            }

            // Force overwrite: Delete finalPath if it exists (fixes rename failure on Windows)
            if (file_exists($finalPath)) {
                if (unlink($finalPath)) {
                    write_log("Deleted existing destination file: {$finalPath}");
                } else {
                    write_log("Warning: Failed to delete existing destination file: {$finalPath}");
                }
            }

            // Move temp file to final location
            if (rename($tempPath, $finalPath)) {
                write_log("Temp file moved successfully to: {$finalPath}");
                echo json_encode(['status' => 'success']);
            } else {
                write_log("Failed to move temp file");
                echo json_encode(['status' => 'error', 'message' => 'Failed to move temp file']);
            }
        } else {
            write_log("Temp file not found: {$tempPath}");
            echo json_encode(['status' => 'error', 'message' => 'Temp file not found']);
        }
        exit;
    }

    // The JSON payload is now expected in a POST field, e.g., 'dbData'
    // because the request is multipart/form-data when an image is included.
    if (!isset($_POST['dbData'])) {
        write_log("Error: dbData payload is missing.");
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Required dbData payload is missing from the request.']);
        exit;
    }

    $jsonPayload = $_POST['dbData'];
    $data = json_decode($jsonPayload);

    if (json_last_error() !== JSON_ERROR_NONE) {
        write_log("Error: Invalid JSON data received. Error: " . json_last_error_msg());
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data received: ' . json_last_error_msg()]);
        exit;
    }

    // Check if an image file is being uploaded
    $uploadedImagePath = null;
    if (isset($_FILES['productImage']) && $_FILES['productImage']['error'] === UPLOAD_ERR_OK) {
        write_log("Image file detected in request: " . $_FILES['productImage']['name']);

        if (!isset($_POST['productName']) || empty($_POST['productName'])) {
            write_log("Error: Product name is missing for image upload.");
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Product name is required when uploading an image.']);
            exit;
        }
        $productName = $_POST['productName'];
        $isTempUpload = isset($_POST['isTempUpload']) && $_POST['isTempUpload'] === 'true';

        write_log("Processing image for product: " . $productName . ($isTempUpload ? " (temp upload)" : ""));

        $errorMsg = '';
        $newImagePath = processAndSaveImage($_FILES['productImage'], $productName, $errorMsg, $isTempUpload);

        if ($newImagePath === false) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $errorMsg]);
            exit;
        }

        // For temp uploads, just return the image path without updating database
        if ($isTempUpload) {
            write_log("Temp image uploaded successfully to: " . $newImagePath);
            echo json_encode(['status' => 'success', 'imagePath' => $newImagePath]);
            exit;
        }

        // Store the image path for the final response
        $uploadedImagePath = $newImagePath;

        // Update the image path in the data object for regular uploads
        $productFound = false;
        foreach ($data as $product) {
            if (isset($product->name) && $product->name === $productName) {
                $product->image = $newImagePath;
                $productFound = true;
                write_log("Updated image path for product '{$productName}' to '{$newImagePath}'.");
                break;
            }
        }

        if (!$productFound) {
            write_log("Warning: Product '{$productName}' not found in dbData to update image path. The product might be new. The image path will be returned to frontend for staging.");
        }
    }

    $dbFile = 'db.json';
    $backupDir = 'database-backup';

    if (!is_dir($backupDir)) {
        if (!mkdir($backupDir, 0777, true)) {
            write_log("Error: Failed to create backup directory.");
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to create backup directory.']);
            exit;
        }
    }

    $backupFile = $backupDir . '/db-' . date('Y-m-d-H-i-s') . '.json';

    if (file_exists($dbFile)) {
        if (!copy($dbFile, $backupFile)) {
            write_log("Error: Failed to create database backup.");
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to create database backup.']);
            exit;
        }
    }

    $newJsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if (file_put_contents($dbFile, $newJsonData) === false) {
        write_log("Error: Failed to write to database file.");
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to write to database file. Check permissions.']);
        exit;
    }

    $response = ['status' => 'success', 'message' => 'Products updated successfully. Backup created at ' . $backupFile];
    if ($uploadedImagePath) {
        $response['imagePath'] = $uploadedImagePath;
    }
    echo json_encode($response);
    exit;
}
?>
