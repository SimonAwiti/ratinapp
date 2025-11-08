<?php
session_start();

// Force all errors to be displayed
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Set header first
header('Content-Type: application/json');

// Log everything for debugging
$debug_log = [];

try {
    $debug_log[] = "Script started";

    // Check if user is logged in and is admin
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        throw new Exception('Unauthorized - Please log in');
    }

    $debug_log[] = "User authenticated";

    // Check if file was uploaded
    if (!isset($_FILES['document'])) {
        throw new Exception('No file uploaded');
    }

    $uploadedFile = $_FILES['document'];
    $debug_log[] = "File received: " . $uploadedFile['name'];

    // Check for upload errors
    if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive in HTML form',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
        ];
        
        $errorMessage = $errorMessages[$uploadedFile['error']] ?? 'Unknown upload error';
        throw new Exception('Upload error: ' . $errorMessage);
    }

    $debug_log[] = "File upload OK";

    // Use absolute path
    $baseDir = '/var/www/html/ratinapp/news-system/api/';
    $uploadDir = $baseDir . 'uploads/grainwatch/';
    
    $debug_log[] = "Upload directory: " . $uploadDir;

    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        $debug_log[] = "Directory doesn't exist, creating...";
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Cannot create upload directory: ' . $uploadDir);
        }
        $debug_log[] = "Directory created successfully";
    } else {
        $debug_log[] = "Directory exists";
    }

    // Check if writable
    if (!is_writable($uploadDir)) {
        $perms = substr(sprintf('%o', fileperms($uploadDir)), -4);
        throw new Exception('Upload directory not writable. Permissions: ' . $perms);
    }

    $debug_log[] = "Directory is writable";

    // Validate file type
    $allowedTypes = ['application/pdf', 'application/x-pdf'];
    $fileType = mime_content_type($uploadedFile['tmp_name']);
    
    $debug_log[] = "File type: " . $fileType;

    if (!in_array($fileType, $allowedTypes)) {
        throw new Exception('Only PDF files are allowed. File type detected: ' . $fileType);
    }

    // Validate file size (10MB limit)
    $maxFileSize = 10 * 1024 * 1024;
    if ($uploadedFile['size'] > $maxFileSize) {
        throw new Exception('File size must be less than 10MB. Current size: ' . round($uploadedFile['size'] / 1024 / 1024, 2) . 'MB');
    }

    $debug_log[] = "File validation passed";

    // Generate filename
    $fileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9-_\.]/', '_', $uploadedFile['name']);
    $filePath = $uploadDir . $fileName;
    
    $debug_log[] = "Target file path: " . $filePath;

    // Move file
    if (move_uploaded_file($uploadedFile['tmp_name'], $filePath)) {
        $debug_log[] = "File moved successfully";
        
        // Verify file exists
        if (!file_exists($filePath)) {
            throw new Exception('File was not saved correctly');
        }
        
        $debug_log[] = "File verified";

        $relativePath = 'uploads/grainwatch/' . $fileName;
        echo json_encode([
            'success' => true, 
            'filePath' => $relativePath,
            'message' => 'File uploaded successfully',
            'debug' => $debug_log // Include debug info
        ]);
        
    } else {
        $lastError = error_get_last();
        throw new Exception('Failed to move uploaded file. Error: ' . ($lastError['message'] ?? 'Unknown'));
    }

} catch (Exception $e) {
    http_response_code(500);
    
    // Add the error to debug log
    $debug_log[] = "ERROR: " . $e->getMessage();
    
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'debug' => $debug_log,
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    // Also log to error log
    error_log("Upload Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
}
?>