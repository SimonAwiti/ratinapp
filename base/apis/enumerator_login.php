<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *"); // adjust if needed

include '../../admin/includes/config.php'; // DB connection

$data = json_decode(file_get_contents("php://input"), true);

$username = $data['username'] ?? '';
$password = $data['password'] ?? '';
$latitude = $data['latitude'] ?? '';
$longitude = $data['longitude'] ?? '';

// Validate required fields
if (empty($username) || empty($password) || empty($latitude) || empty($longitude)) {
    echo json_encode(["status" => "error", "message" => "All fields are required"]);
    exit;
}

// Find enumerator by username
$stmt = $con->prepare("SELECT * FROM enumerators WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Invalid credentials"]);
    exit;
}

$user = $result->fetch_assoc();

// Use password_verify() for hashed password checking
if (!password_verify($password, $user['password'])) {
    echo json_encode(["status" => "error", "message" => "Incorrect password"]);
    exit;
}

// Generate a token
$token = bin2hex(random_bytes(16));

// Save token to DB
$saveTokenStmt = $con->prepare("UPDATE enumerators SET token = ? WHERE id = ?");
$saveTokenStmt->bind_param("si", $token, $user['id']);
$saveTokenStmt->execute();

// Update location
$updateStmt = $con->prepare("UPDATE enumerators SET latitude = ?, longitude = ? WHERE id = ?");
$updateStmt->bind_param("ssi", $latitude, $longitude, $user['id']);
$updateStmt->execute();


// Respond with success and token
echo json_encode([
    "status" => "success",
    "message" => "Login successful",
    "token" => $token,
    "user" => [
        "id" => $user['id'],
        "name" => $user['name'],
        "email" => $user['email'],
        "username" => $user['username'],
        "latitude" => $latitude,
        "longitude" => $longitude
    ]
]);
