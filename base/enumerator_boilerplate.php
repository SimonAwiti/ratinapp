<?php
session_start();

// ============================================================
// API HANDLER - Get enumerator data for editing
// ============================================================
if (isset($_GET['get_enumerator']) && is_numeric($_GET['get_enumerator'])) {
    if (file_exists('includes/config.php')) {
        include 'includes/config.php';
    } elseif (file_exists('../admin/includes/config.php')) {
        include '../admin/includes/config.php';
    }
    header('Content-Type: application/json');
    $get_id = (int)$_GET['get_enumerator'];

    $api_stmt = $con->prepare("SELECT id, name, email, phone, gender, country, county_district, username, tradepoints, latitude, longitude FROM enumerators WHERE id = ?");
    $api_stmt->bind_param("i", $get_id);
    $api_stmt->execute();
    $api_result = $api_stmt->get_result();
    if ($api_row = $api_result->fetch_assoc()) {
        // Decode tradepoints and get actual names
        $tradepoints = json_decode($api_row['tradepoints'], true);
        $normalized = [];
        if (is_array($tradepoints)) {
            foreach ($tradepoints as $key => $value) {
                if (isset($value['id']) && isset($value['type'])) {
                    // Get the actual name from the database
                    $name = getTradepointNameHelper($con, $value['id'], $value['type']);
                    $normalized[] = [
                        'id' => $value['id'], 
                        'type' => $value['type'],
                        'name' => $name
                    ];
                } elseif (is_numeric($key) && isset($value['id'])) {
                    $name = getTradepointNameHelper($con, $value['id'], $value['type']);
                    $normalized[] = [
                        'id' => $value['id'], 
                        'type' => $value['type'],
                        'name' => $name
                    ];
                }
            }
        }
        $api_row['tradepoints'] = $normalized;
        echo json_encode($api_row);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Enumerator not found']);
    }
    $api_stmt->close();
    $con->close();
    exit;
}

// Helper function for API - Checks BOTH millers and miller_details tables
function getTradepointNameHelper($con, $id, $type) {
    $tableName = '';
    $nameColumn = '';
    
    // Convert to lowercase for comparison
    $typeLower = strtolower(trim($type));

    // Check for market types (case insensitive)
    if ($typeLower == 'market' || $typeLower == 'markets') {
        $tableName = 'markets';
        $nameColumn = 'market_name';
    }
    // Check for border point types (case insensitive)
    elseif ($typeLower == 'border point' || $typeLower == 'border points') {
        $tableName = 'border_points';
        $nameColumn = 'name';
    }
    // Check for miller types (case insensitive) - Try BOTH tables
    elseif ($typeLower == 'miller' || $typeLower == 'millers') {
        // First try 'millers' table
        $stmt = $con->prepare("SELECT miller_name as name FROM millers WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $stmt->close();
                if (!empty($row['name'])) return $row['name'];
            }
            $stmt->close();
        }
        
        // If not found, try 'miller_details' table
        $stmt = $con->prepare("SELECT miller_name as name FROM miller_details WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $stmt->close();
                if (!empty($row['name'])) return $row['name'];
            }
            $stmt->close();
        }
        
        return "ID: " . htmlspecialchars($id) . " (Name Not Found in either millers or miller_details)";
    }
    else {
        return "Unknown Type: " . htmlspecialchars($type);
    }

    if (!empty($tableName) && !empty($nameColumn)) {
        $stmt = $con->prepare("SELECT " . $nameColumn . " FROM " . $tableName . " WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $stmt->close();
                return $row[$nameColumn];
            }
            $stmt->close();
        }
    }
    return "ID: " . htmlspecialchars($id) . " (Name Not Found)";
}

// ============================================================
// API HANDLER - Get tradepoints for dropdown (includes BOTH miller tables)
// ============================================================
if (isset($_GET['get_tradepoints'])) {
    if (file_exists('includes/config.php')) {
        include 'includes/config.php';
    } elseif (file_exists('../admin/includes/config.php')) {
        include '../admin/includes/config.php';
    }
    header('Content-Type: application/json');

    $tradepoints = [];

    // Get markets
    $markets = $con->query("SELECT id, market_name as name, 'Market' as type FROM markets WHERE market_name IS NOT NULL ORDER BY name");
    if ($markets) {
        while ($row = $markets->fetch_assoc()) {
            $tradepoints[] = $row;
        }
    }

    // Get border points
    $borders = $con->query("SELECT id, name, 'Border Point' as type FROM border_points WHERE name IS NOT NULL ORDER BY name");
    if ($borders) {
        while ($row = $borders->fetch_assoc()) {
            $tradepoints[] = $row;
        }
    }

    // Get millers from BOTH tables
    // First from 'millers' table
    $millers1 = $con->query("SELECT id, miller_name as name, 'Miller' as type FROM millers WHERE miller_name IS NOT NULL ORDER BY name");
    if ($millers1) {
        while ($row = $millers1->fetch_assoc()) {
            $tradepoints[] = $row;
        }
    }
    
    // Then from 'miller_details' table
    $millers2 = $con->query("SELECT id, miller_name as name, 'Miller' as type FROM miller_details WHERE miller_name IS NOT NULL ORDER BY name");
    if ($millers2) {
        while ($row = $millers2->fetch_assoc()) {
            $tradepoints[] = $row;
        }
    }

    echo json_encode($tradepoints);
    $con->close();
    exit;
}

// ============================================================
// EXPORT HANDLER
// ============================================================
if (isset($_GET['export_all'])) {
    if (file_exists('includes/config.php')) {
        include 'includes/config.php';
    } elseif (file_exists('../admin/includes/config.php')) {
        include '../admin/includes/config.php';
    }
    while (ob_get_level()) ob_end_clean();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="enumerators_export_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    function getTradepointNameForExport($con, $id, $type) {
        $typeLower = strtolower(trim($type));

        if ($typeLower == 'market' || $typeLower == 'markets') {
            $stmt = $con->prepare("SELECT market_name as name FROM markets WHERE id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $stmt->close();
                    return $row['name'];
                }
                $stmt->close();
            }
        } 
        elseif ($typeLower == 'border point' || $typeLower == 'border points') {
            $stmt = $con->prepare("SELECT name FROM border_points WHERE id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $stmt->close();
                    return $row['name'];
                }
                $stmt->close();
            }
        } 
        elseif ($typeLower == 'miller' || $typeLower == 'millers') {
            // First try 'millers' table
            $stmt = $con->prepare("SELECT miller_name as name FROM millers WHERE id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $stmt->close();
                    return $row['name'];
                }
                $stmt->close();
            }
            
            // If not found, try 'miller_details' table
            $stmt = $con->prepare("SELECT miller_name as name FROM miller_details WHERE id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $stmt->close();
                    return $row['name'];
                }
                $stmt->close();
            }
        }
        
        return '';
    }

    $export_sql = "SELECT id, name, email, phone, gender, country, county_district, username, tradepoints, created_at FROM enumerators ORDER BY id DESC";
    $export_result = $con->query($export_sql);

    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF");
    fputcsv($output, ['ID', 'Name', 'Email', 'Phone', 'Gender', 'Country', 'County/District', 'Username', 'Assigned Tradepoints', 'Date Added']);

    while ($row = $export_result->fetch_assoc()) {
        $tradepoint_names = [];
        $tps = json_decode($row['tradepoints'], true);
        if (is_array($tps)) {
            foreach ($tps as $tp) {
                if (isset($tp['id'], $tp['type'])) {
                    $name = getTradepointNameForExport($con, $tp['id'], $tp['type']);
                    if ($name) $tradepoint_names[] = $name . ' (' . $tp['type'] . ')';
                }
            }
        }
        fputcsv($output, [
            $row['id'], $row['name'], $row['email'], $row['phone'],
            $row['gender'], $row['country'], $row['county_district'],
            $row['username'], implode('; ', $tradepoint_names),
            date('Y-m-d', strtotime($row['created_at']))
        ]);
    }
    fclose($output);
    $con->close();
    exit;
}

// ============================================================
// CSV TEMPLATE DOWNLOAD
// ============================================================
if (isset($_GET['download_template'])) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="enumerators_import_template.csv"');
    header('Pragma: no-cache');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Name', 'Email', 'Phone', 'Gender', 'Country', 'County/District', 'Username', 'Password', 'Latitude', 'Longitude', 'Tradepoints']);
    fputcsv($out, ['John Doe', 'john@example.com', '+254712345678', 'Male', 'Kenya', 'Nairobi', 'john.doe', 'password123', '-1.286389', '36.817223', 'Market:1,Border Point:2']);
    fclose($out);
    exit;
}

// ============================================================
// NORMAL PAGE LOAD
// ============================================================
require_once '../admin/includes/admin_header.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../admin/login.php");
    exit;
}

if (file_exists('includes/config.php')) {
    include 'includes/config.php';
} elseif (file_exists('../admin/includes/config.php')) {
    include '../admin/includes/config.php';
}

$message      = '';
$message_type = '';

if (!isset($_SESSION['selected_enumerators'])) {
    $_SESSION['selected_enumerators'] = [];
}

// Handle selection updates via AJAX
if (isset($_POST['action']) && $_POST['action'] === 'update_selection') {
    $id         = (int)$_POST['id'];
    $isSelected = $_POST['selected'] === 'true';

    if ($isSelected) {
        if (!in_array($id, $_SESSION['selected_enumerators'])) {
            $_SESSION['selected_enumerators'][] = $id;
        }
    } else {
        $key = array_search($id, $_SESSION['selected_enumerators']);
        if ($key !== false) {
            unset($_SESSION['selected_enumerators'][$key]);
            $_SESSION['selected_enumerators'] = array_values($_SESSION['selected_enumerators']);
        }
    }
    if (isset($_POST['clear_all']) && $_POST['clear_all'] === 'true') {
        $_SESSION['selected_enumerators'] = [];
    }
    echo json_encode(['success' => true, 'count' => count($_SESSION['selected_enumerators'])]);
    exit;
}

// ============================================================
// FUNCTION: Get Tradepoint Name - FOR DISPLAY IN TABLE (Checks BOTH miller tables)
// ============================================================
function getTradepointName($con, $id, $type) {
    $tableName = '';
    $nameColumn = '';
    
    // Convert to lowercase for comparison
    $typeLower = strtolower(trim($type));

    // Check for market types (case insensitive)
    if ($typeLower == 'market' || $typeLower == 'markets') {
        $tableName = 'markets';
        $nameColumn = 'market_name';
    }
    // Check for border point types (case insensitive)
    elseif ($typeLower == 'border point' || $typeLower == 'border points') {
        $tableName = 'border_points';
        $nameColumn = 'name';
    }
    // Check for miller types (case insensitive) - Try BOTH tables
    elseif ($typeLower == 'miller' || $typeLower == 'millers') {
        // First try 'millers' table
        $stmt = $con->prepare("SELECT miller_name as name FROM millers WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $stmt->close();
                if (!empty($row['name'])) return $row['name'];
            }
            $stmt->close();
        }
        
        // If not found, try 'miller_details' table
        $stmt = $con->prepare("SELECT miller_name as name FROM miller_details WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $stmt->close();
                if (!empty($row['name'])) return $row['name'];
            }
            $stmt->close();
        }
        
        return "ID: " . htmlspecialchars($id) . " (Name Not Found in either millers or miller_details)";
    }
    else {
        return "Unknown Type: " . htmlspecialchars($type);
    }

    if (!empty($tableName) && !empty($nameColumn)) {
        $stmt = $con->prepare("SELECT " . $nameColumn . " FROM " . $tableName . " WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $stmt->close();
                return $row[$nameColumn];
            }
            $stmt->close();
        }
    }
    return "ID: " . htmlspecialchars($id) . " (Name Not Found)";
}

// ============================================================
// STATISTICS
// ============================================================
$total_enumerators = (int)($con->query("SELECT COUNT(*) as t FROM enumerators")->fetch_assoc()['t'] ?? 0);
$assigned_enumerators = (int)($con->query("SELECT COUNT(*) as t FROM enumerators WHERE tradepoints IS NOT NULL AND tradepoints != '[]' AND tradepoints != '' AND tradepoints != '{}'")->fetch_assoc()['t'] ?? 0);
$unassigned_enumerators = $total_enumerators - $assigned_enumerators;

// ============================================================
// FORM SUBMISSIONS - ADD ENUMERATOR
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_enumerator'])) {
    $name           = trim($_POST['name']);
    $email          = trim($_POST['email']);
    $phone          = trim($_POST['phone']);
    $gender         = trim($_POST['gender']);
    $country        = trim($_POST['country']);
    $county_district = trim($_POST['county_district']);
    $username       = trim($_POST['username']);
    $password       = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $latitude       = floatval($_POST['latitude'] ?? 0);
    $longitude      = floatval($_POST['longitude'] ?? 0);
    
    // Process tradepoints
    $tradepoints_array = [];
    if (isset($_POST['tradepoints']) && is_array($_POST['tradepoints'])) {
        foreach ($_POST['tradepoints'] as $tp) {
            if (isset($tp['id']) && isset($tp['type'])) {
                $tradepoints_array[] = [
                    'id' => (int)$tp['id'],
                    'type' => $tp['type']
                ];
            }
        }
    }
    $tradepoints_json = json_encode($tradepoints_array);
    $created_at     = date('Y-m-d H:i:s');
    $token          = bin2hex(random_bytes(16));

    if (empty($name) || empty($email) || empty($phone)) {
        $message = "Please fill all required fields."; 
        $message_type = "error";
    } else {
        $check = $con->prepare("SELECT id FROM enumerators WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $message = "Enumerator with this email already exists!"; 
            $message_type = "error";
        } else {
            $stmt = $con->prepare("INSERT INTO enumerators (name, email, phone, gender, country, county_district, username, password, latitude, longitude, tradepoints, token, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssddsss", $name, $email, $phone, $gender, $country, $county_district, $username, $password, $latitude, $longitude, $tradepoints_json, $token, $created_at);
            if ($stmt->execute()) {
                $message = "Enumerator added successfully!"; 
                $message_type = "success";
                echo "<script>setTimeout(function() { window.location.href = window.location.pathname; }, 1500);</script>";
            } else {
                $message = "Error adding enumerator: " . $stmt->error; 
                $message_type = "error";
            }
            $stmt->close();
        }
        $check->close();
    }
}

// ============================================================
// FORM SUBMISSIONS - EDIT ENUMERATOR
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_enumerator'])) {
    $id              = (int)$_POST['enumerator_id'];
    $name            = trim($_POST['name']);
    $email           = trim($_POST['email']);
    $phone           = trim($_POST['phone']);
    $gender          = trim($_POST['gender']);
    $country         = trim($_POST['country']);
    $county_district = trim($_POST['county_district']);
    $username        = trim($_POST['username']);
    $latitude        = floatval($_POST['latitude'] ?? 0);
    $longitude       = floatval($_POST['longitude'] ?? 0);
    
    // Process tradepoints
    $tradepoints_array = [];
    if (isset($_POST['tradepoints']) && is_array($_POST['tradepoints'])) {
        foreach ($_POST['tradepoints'] as $tp) {
            if (isset($tp['id']) && isset($tp['type'])) {
                $tradepoints_array[] = [
                    'id' => (int)$tp['id'],
                    'type' => $tp['type']
                ];
            }
        }
    }
    $tradepoints_json = json_encode($tradepoints_array);

    if (empty($name) || empty($email) || empty($phone)) {
        $message = "Please fill all required fields."; 
        $message_type = "error";
    } else {
        if (!empty($_POST['password'])) {
            $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $con->prepare("UPDATE enumerators SET name=?, email=?, phone=?, gender=?, country=?, county_district=?, username=?, password=?, latitude=?, longitude=?, tradepoints=? WHERE id=?");
            $stmt->bind_param("ssssssssddsi", $name, $email, $phone, $gender, $country, $county_district, $username, $password_hash, $latitude, $longitude, $tradepoints_json, $id);
        } else {
            $stmt = $con->prepare("UPDATE enumerators SET name=?, email=?, phone=?, gender=?, country=?, county_district=?, username=?, latitude=?, longitude=?, tradepoints=? WHERE id=?");
            $stmt->bind_param("sssssssddsi", $name, $email, $phone, $gender, $country, $county_district, $username, $latitude, $longitude, $tradepoints_json, $id);
        }
        if ($stmt->execute()) {
            $message = "Enumerator updated successfully!"; 
            $message_type = "success";
            echo "<script>setTimeout(function() { window.location.href = window.location.pathname; }, 1500);</script>";
        } else {
            $message = "Error updating enumerator: " . $stmt->error; 
            $message_type = "error";
        }
        $stmt->close();
    }
}

// ============================================================
// DELETE SELECTED ENUMERATORS
// ============================================================
if (isset($_POST['delete_selected']) && !empty($_POST['selected_ids'])) {
    $selected_ids = array_map('intval', (array)$_POST['selected_ids']);
    $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
    $stmt = $con->prepare("DELETE FROM enumerators WHERE id IN ($placeholders)");
    if ($stmt) {
        $stmt->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);
        if ($stmt->execute()) {
            $deleted = $stmt->affected_rows;
            $message = "Successfully deleted $deleted enumerator(ies)."; 
            $message_type = "success";
            $_SESSION['selected_enumerators'] = array_values(array_diff($_SESSION['selected_enumerators'], $selected_ids));
            echo "<script>setTimeout(function() { window.location.href = window.location.pathname; }, 1500);</script>";
        } else {
            $message = "Error deleting: " . $stmt->error; 
            $message_type = "error";
        }
        $stmt->close();
    }
}

// ============================================================
// CSV BULK IMPORT
// ============================================================
$import_message = '';
$import_type    = '';
$import_stats   = [];

if (isset($_POST['import_csv']) && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
    $handle      = fopen($_FILES['csv_file']['tmp_name'], 'r');
    fgetcsv($handle); // skip header

    $successCount = 0;
    $errorCount   = 0;
    $errors       = [];
    $rowNumber    = 1;

    $con->begin_transaction();
    try {
        while (($data = fgetcsv($handle, 2000, ',')) !== false) {
            $rowNumber++;
            if (empty($data) || (count($data) === 1 && trim($data[0]) === '')) continue;

            $name            = trim($data[0] ?? '');
            $email           = trim($data[1] ?? '');
            $phone           = trim($data[2] ?? '');
            $gender          = trim($data[3] ?? '');
            $country         = trim($data[4] ?? '');
            $county_district = trim($data[5] ?? '');
            $username        = trim($data[6] ?? '');
            $password        = trim($data[7] ?? '');
            $latitude        = floatval($data[8] ?? 0);
            $longitude       = floatval($data[9] ?? 0);
            $tradepoints_raw = trim($data[10] ?? '');

            if ($name  === '') { $errors[] = "Row $rowNumber: Name is required.";  $errorCount++; continue; }
            if ($email === '') { $errors[] = "Row $rowNumber: Email is required."; $errorCount++; continue; }
            if ($phone === '') { $errors[] = "Row $rowNumber: Phone is required."; $errorCount++; continue; }

            if ($username === '') {
                $username = strtolower(preg_replace('/[^a-z0-9]/', '', str_replace(' ', '.', $name))) . rand(100, 999);
            }
            if ($password === '') {
                $password = substr(bin2hex(random_bytes(8)), 0, 12);
            }
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Parse tradepoints
            $tradepoints_array = [];
            if ($tradepoints_raw !== '') {
                foreach (array_map('trim', explode(',', $tradepoints_raw)) as $pair) {
                    if (strpos($pair, ':') !== false) {
                        [$tp_type, $tp_id] = array_map('trim', explode(':', $pair, 2));
                        $tp_id = intval($tp_id);
                        // Normalize plurals
                        if ($tp_type === 'Markets')      $tp_type = 'Market';
                        if ($tp_type === 'Border Points') $tp_type = 'Border Point';
                        if ($tp_type === 'Millers')      $tp_type = 'Miller';

                        if (in_array($tp_type, ['Market', 'Border Point', 'Miller']) && $tp_id > 0) {
                            $tradepoints_array[] = ['id' => $tp_id, 'type' => $tp_type];
                        } else {
                            $errors[] = "Row $rowNumber: Warning - Invalid tradepoint format: '$pair'";
                        }
                    }
                }
            }
            $tradepoints_json = json_encode($tradepoints_array);

            $check = $con->prepare("SELECT id FROM enumerators WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            $check->store_result();
            $exists = $check->num_rows > 0;
            $check->close();

            if ($exists) {
                if (isset($_POST['overwrite_existing'])) {
                    $upd = $con->prepare("UPDATE enumerators SET name=?, phone=?, gender=?, country=?, county_district=?, username=?, password=?, latitude=?, longitude=?, tradepoints=? WHERE email=?");
                    $upd->bind_param("sssssssddss", $name, $phone, $gender, $country, $county_district, $username, $hashed_password, $latitude, $longitude, $tradepoints_json, $email);
                    if ($upd->execute()) $successCount++;
                    else { $errors[] = "Row $rowNumber: Update failed — " . $upd->error; $errorCount++; }
                    $upd->close();
                } else {
                    $errors[] = "Row $rowNumber: Enumerator with email '$email' already exists";
                    $errorCount++;
                }
                continue;
            }

            $token      = bin2hex(random_bytes(16));
            $created_at = date('Y-m-d H:i:s');
            $ins = $con->prepare("INSERT INTO enumerators (name, email, phone, gender, country, county_district, username, password, latitude, longitude, tradepoints, token, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->bind_param("ssssssssddsss", $name, $email, $phone, $gender, $country, $county_district, $username, $hashed_password, $latitude, $longitude, $tradepoints_json, $token, $created_at);
            if ($ins->execute()) $successCount++;
            else { $errors[] = "Row $rowNumber: Insert failed — " . $ins->error; $errorCount++; }
            $ins->close();
        }

        $criticalErrors = count(array_filter($errors, fn($e) => strpos($e, 'Warning') === false));
        if ($criticalErrors === 0) {
            $con->commit();
            $import_type    = count($errors) > 0 ? 'warning' : 'success';
            $import_message = "Successfully imported <strong>$successCount</strong> enumerator(s).";
            $import_stats   = $errors;
            echo "<script>setTimeout(function() { window.location.href = window.location.pathname; }, 2000);</script>";
        } else {
            $con->rollback();
            $import_type    = 'error';
            $import_message = "Import rolled back — <strong>$criticalErrors</strong> critical error(s).";
            $import_stats   = $errors;
        }
    } catch (Exception $e) {
        $con->rollback();
        $import_type    = 'error';
        $import_message = "Import failed: " . htmlspecialchars($e->getMessage());
    }
    fclose($handle);
}

// ============================================================
// FETCH ENUMERATORS
// ============================================================
$search_name    = $_GET['search_name']    ?? '';
$search_email   = $_GET['search_email']   ?? '';
$search_country = $_GET['search_country'] ?? '';
$search_gender  = $_GET['search_gender']  ?? '';

$filterConditions = [];
$params           = [];
$types            = '';

if (!empty($search_name)) {
    $filterConditions[] = "name LIKE ?";
    $params[] = '%' . $search_name . '%';
    $types   .= 's';
}
if (!empty($search_email)) {
    $filterConditions[] = "email LIKE ?";
    $params[] = '%' . $search_email . '%';
    $types   .= 's';
}
if (!empty($search_country)) {
    $filterConditions[] = "country LIKE ?";
    $params[] = '%' . $search_country . '%';
    $types   .= 's';
}
if (!empty($search_gender)) {
    $filterConditions[] = "gender = ?";
    $params[] = $search_gender;
    $types   .= 's';
}

$where_clause = !empty($filterConditions) ? " AND " . implode(" AND ", $filterConditions) : "";

// Sorting
$allowed_sort   = ['id', 'name', 'email', 'country', 'gender', 'created_at'];
$sort_column    = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sort) ? $_GET['sort'] : 'created_at';
$sort_direction = (isset($_GET['dir']) && strtoupper($_GET['dir']) === 'ASC') ? 'ASC' : 'DESC';

// Pagination
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
if (!in_array($limit, [10, 20, 50, 100])) $limit = 10;

$count_stmt = $con->prepare("SELECT COUNT(*) as total FROM enumerators WHERE 1=1" . $where_clause);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$filtered_records = (int)$count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = max(1, (int)ceil($filtered_records / $limit));
$page        = isset($_GET['page']) ? max(1, min((int)$_GET['page'], $total_pages)) : 1;
$offset      = ($page - 1) * $limit;

$main_query   = "SELECT id, name, email, phone, gender, country, county_district, tradepoints, latitude, longitude, username, created_at FROM enumerators WHERE 1=1"
              . $where_clause
              . " ORDER BY $sort_column $sort_direction LIMIT ? OFFSET ?";

$main_params  = $params;
$main_params[] = $limit;
$main_params[] = $offset;
$main_types   = $types . 'ii';

$main_stmt = $con->prepare($main_query);
$main_stmt->bind_param($main_types, ...$main_params);
$main_stmt->execute();
$enumerators_raw = $main_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$main_stmt->close();

// Process tradepoints for each enumerator
foreach ($enumerators_raw as &$enum) {
    $tp_list = [];
    if (!empty($enum['tradepoints'])) {
        $decoded = json_decode($enum['tradepoints'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $tp_data) {
                if (isset($tp_data['id']) && isset($tp_data['type'])) {
                    $name = getTradepointName($con, $tp_data['id'], $tp_data['type']);
                    if ($name && strpos($name, 'Name Not Found') === false && strpos($name, 'Unknown') === false) {
                        $tp_list[] = htmlspecialchars($name) . " (" . htmlspecialchars($tp_data['type']) . ")";
                    } else {
                        $tp_list[] = "ID: " . $tp_data['id'] . " (" . htmlspecialchars($tp_data['type']) . ")";
                    }
                }
            }
        }
    }
    $enum['tradepoints_list'] = $tp_list;
}
unset($enum);

$showing_from = $filtered_records > 0 ? $offset + 1 : 0;
$showing_to   = min($offset + $limit, $filtered_records);

// Countries for datalist
$countries   = [];
$ctry_result = $con->query("SELECT DISTINCT country FROM enumerators WHERE country IS NOT NULL AND country != '' ORDER BY country");
if ($ctry_result) {
    while ($row = $ctry_result->fetch_assoc()) {
        $countries[] = $row['country'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enumerators Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0,1" />
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        .auth-bg-gradient {
            background: radial-gradient(circle at top left, rgba(0,69,13,.03), transparent), 
                        radial-gradient(circle at bottom right, rgba(128,0,0,.03), transparent);
        }
        .header-accent-gradient {
            background: linear-gradient(90deg, #00450d 0%, #800000 50%, #00450d 100%);
        }
        .table-row-hover:hover {
            background-color: #fefaf5;
            transition: all .2s ease;
        }
        .stat-card {
            transition: all .2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,.05);
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,.1);
        }
        .search-input:focus {
            border-color: #800000;
            outline: none;
        }
        .action-btn {
            padding: .2rem .4rem;
            border-radius: .375rem;
            font-size: .7rem;
            font-weight: 500;
            transition: all .2s;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
        }
        .pagination-btn {
            min-width: 28px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: .375rem;
            font-size: .75rem;
            transition: all .2s ease;
            cursor: pointer;
            border: 1px solid #e5e7eb;
            background: white;
            color: #374151;
        }
        .pagination-btn:hover:not(:disabled):not(.active-page) {
            background-color: #fef3e7;
            border-color: #800000;
            color: #800000;
        }
        .pagination-btn.active-page {
            background-color: #800000;
            border-color: #800000;
            color: white;
            font-weight: 600;
        }
        .pagination-btn:disabled {
            opacity: .35;
            cursor: not-allowed;
        }
        .page-size-select {
            font-size: .75rem;
            padding: .25rem .5rem;
            border-radius: .375rem;
            border: 1px solid #e5e7eb;
            background: white;
            cursor: pointer;
        }
        .sortable {
            cursor: pointer;
            user-select: none;
        }
        .sortable:hover {
            color: #800000;
        }
        .sort-icon {
            font-size: .7rem;
            margin-left: .2rem;
            vertical-align: middle;
        }
        .modal-gradient-header {
            background: linear-gradient(135deg, #800000 0%, #00450d 100%);
        }
        .tradepoint-tag {
            background: #e9ecef;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            display: inline-block;
            margin: 2px;
        }
        .tradepoint-tag.market {
            background: #ffeaea;
            color: #721c24;
        }
        .tradepoint-tag.border-point {
            background: #fff3cd;
            color: #856404;
        }
        .tradepoint-tag.miller {
            background: #d1ecf1;
            color: #0c5460;
        }
        .material-symbols-outlined {
            font-family: 'Material Symbols Outlined' !important;
            font-style: normal;
            font-weight: normal;
            line-height: 1;
            letter-spacing: normal;
            text-transform: none;
            display: inline-block;
            white-space: nowrap;
            word-wrap: normal;
            direction: ltr;
            -webkit-font-feature-settings: 'liga';
            font-feature-settings: 'liga';
            -webkit-font-smoothing: antialiased;
        }
        .tradepoint-dropdown {
            max-height: 300px;
            overflow-y: auto;
        }
        .tradepoint-dropdown-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #e5e7eb;
            transition: background-color 0.2s;
        }
        .tradepoint-dropdown-item:hover {
            background-color: #f3f4f6;
        }
    </style>
</head>
<body class="bg-gray-50">
<div class="auth-bg-gradient -m-4 -mt-20 p-4 pt-24 min-h-screen">
<div class="max-w-7xl mx-auto">

    <!-- Header -->
    <div class="mb-6">
        <div class="flex justify-between items-center flex-wrap gap-4">
            <div>
                <h1 class="text-2xl font-bold" style="color: #800000;">Enumerators Management</h1>
                <p class="text-gray-600 text-sm mt-1">Manage field enumerators and their assignments</p>
            </div>
            <div class="flex gap-2">
                <button onclick="openImportModal()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-all shadow-sm">
                    <span class="material-symbols-outlined text-base">upload_file</span>Import CSV
                </button>
                <a href="?export_all=1" class="inline-flex items-center gap-1.5 px-3 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition-all shadow-sm">
                    <span class="material-symbols-outlined text-base">download</span>Export All CSV
                </a>
                <button onclick="openAddModal()" class="inline-flex items-center gap-1.5 px-4 py-2 text-white text-sm rounded-lg transition-all shadow-sm" style="background-color: #800000;">
                    <span class="material-symbols-outlined text-base">add_circle</span>Add Enumerator
                </button>
            </div>
        </div>
        <div class="h-0.5 w-full header-accent-gradient mt-3 rounded-full"></div>
    </div>

    <!-- Operation Messages -->
    <?php if (!empty($message)): ?>
    <div class="mb-4 p-3 rounded-lg flex items-center gap-2 text-sm <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border-l-4 border-green-600' : 'bg-red-100 text-red-700 border-l-4 border-red-600' ?>">
        <span class="material-symbols-outlined text-base"><?= $message_type === 'success' ? 'check_circle' : 'error' ?></span>
        <span class="font-medium"><?= htmlspecialchars($message) ?></span>
    </div>
    <?php endif; ?>

    <!-- Import Messages -->
    <?php if (!empty($import_message)): ?>
    <div class="mb-4 rounded-lg border-l-4 text-sm overflow-hidden <?= $import_type === 'success' ? 'bg-green-100 text-green-800 border-green-500' : ($import_type === 'warning' ? 'bg-amber-50 text-amber-800 border-amber-500' : 'bg-red-100 text-red-800 border-red-500') ?>">
        <div class="flex items-center gap-2 p-3">
            <span class="material-symbols-outlined text-base"><?= $import_type === 'success' ? 'check_circle' : ($import_type === 'warning' ? 'warning' : 'error') ?></span>
            <span class="font-medium"><?= $import_message ?></span>
        </div>
        <?php if (!empty($import_stats)): ?>
        <details class="px-4 pb-3">
            <summary class="cursor-pointer text-xs font-medium opacity-70 hover:opacity-100">Show <?= count($import_stats) ?> detail(s)</summary>
            <ul class="mt-2 space-y-0.5 text-xs opacity-80 list-disc list-inside max-h-40 overflow-y-auto">
                <?php foreach ($import_stats as $detail): ?>
                    <li><?= htmlspecialchars($detail) ?></li>
                <?php endforeach; ?>
            </ul>
        </details>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4" style="border-left-color: #800000;">
            <div class="flex items-center justify-between">
                <div><p class="text-xs text-gray-400 uppercase tracking-wide">Total Enumerators</p><p class="text-xl font-bold text-gray-800"><?= number_format($total_enumerators) ?></p></div>
                <span class="material-symbols-outlined text-3xl opacity-40" style="color: #800000;">people</span>
            </div>
        </div>
        <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-green-600">
            <div class="flex items-center justify-between">
                <div><p class="text-xs text-gray-400 uppercase tracking-wide">Assigned</p><p class="text-xl font-bold text-gray-800"><?= number_format($assigned_enumerators) ?></p></div>
                <span class="material-symbols-outlined text-3xl text-green-500/50">assignment_ind</span>
            </div>
        </div>
        <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-gray-500">
            <div class="flex items-center justify-between">
                <div><p class="text-xs text-gray-400 uppercase tracking-wide">Unassigned</p><p class="text-xl font-bold text-gray-800"><?= number_format($unassigned_enumerators) ?></p></div>
                <span class="material-symbols-outlined text-3xl text-gray-400">person_off</span>
            </div>
        </div>
        <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-amber-500">
            <div class="flex items-center justify-between">
                <div><p class="text-xs text-gray-400 uppercase tracking-wide">Active</p><p class="text-xl font-bold text-gray-800"><?= number_format($total_enumerators) ?></p></div>
                <span class="material-symbols-outlined text-3xl text-amber-400/60">badge</span>
            </div>
        </div>
    </div>

    <!-- Search & Bulk Actions -->
    <div class="bg-white rounded-lg shadow-sm mb-5 p-3">
        <div class="flex flex-wrap gap-3 items-center justify-between">
            <div class="flex-1 min-w-[150px]">
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">search</span>
                    <input type="text" id="searchName" placeholder="Search by name..."
                           class="search-input w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-opacity-20"
                           value="<?= htmlspecialchars($search_name) ?>">
                </div>
            </div>
            <div class="flex-1 min-w-[150px]">
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">email</span>
                    <input type="text" id="searchEmail" placeholder="Search by email..."
                           class="search-input w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none"
                           value="<?= htmlspecialchars($search_email) ?>">
                </div>
            </div>
            <div class="flex-1 min-w-[150px]">
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">location_on</span>
                    <input type="text" id="searchCountry" placeholder="Search by country..."
                           class="search-input w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none"
                           value="<?= htmlspecialchars($search_country) ?>">
                </div>
            </div>
            <div class="flex gap-2 flex-wrap">
                <select id="searchGender" class="search-input px-3 py-1.5 text-sm border border-gray-200 rounded-lg">
                    <option value="">All Genders</option>
                    <option value="Male"   <?= $search_gender === 'Male'   ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= $search_gender === 'Female' ? 'selected' : '' ?>>Female</option>
                </select>
                <button onclick="applyFilters()" class="px-3 py-1.5 text-white text-sm rounded-lg hover:opacity-90 transition-all inline-flex items-center gap-1" style="background-color: #800000;">
                    <span class="material-symbols-outlined text-base">filter_list</span>Filter
                </button>
                <button id="clearSelectionsBtn" class="px-3 py-1.5 bg-yellow-500 text-white text-sm rounded-lg hover:bg-yellow-600 transition-all inline-flex items-center gap-1">
                    <span class="material-symbols-outlined text-base">clear</span>Clear Selected
                </button>
                <button id="bulkDeleteBtn" disabled class="px-3 py-1.5 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition-all disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center gap-1">
                    <span class="material-symbols-outlined text-base">delete</span>Delete (<span id="selectedCount">0</span>)
                </button>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="w-8 px-3 py-2 text-left"><input type="checkbox" id="selectAllCheckbox" class="rounded border-gray-300"></th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="id">
                            ID<?php if ($sort_column === 'id') echo '<span class="sort-icon">'.($sort_direction === 'ASC' ? '↑' : '↓').'</span>'; ?>
                        </th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="name">
                            Name<?php if ($sort_column === 'name') echo '<span class="sort-icon">'.($sort_direction === 'ASC' ? '↑' : '↓').'</span>'; ?>
                        </th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="email">
                            Email<?php if ($sort_column === 'email') echo '<span class="sort-icon">'.($sort_direction === 'ASC' ? '↑' : '↓').'</span>'; ?>
                        </th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Phone</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="gender">
                            Gender<?php if ($sort_column === 'gender') echo '<span class="sort-icon">'.($sort_direction === 'ASC' ? '↑' : '↓').'</span>'; ?>
                        </th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="country">
                            Country<?php if ($sort_column === 'country') echo '<span class="sort-icon">'.($sort_direction === 'ASC' ? '↑' : '↓').'</span>'; ?>
                        </th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Assigned Tradepoints</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="created_at">
                            Date Added<?php if ($sort_column === 'created_at') echo '<span class="sort-icon">'.($sort_direction === 'ASC' ? '↑' : '↓').'</span>'; ?>
                        </th>
                        <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase w-24">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                <?php if (empty($enumerators_raw)): ?>
                    <tr>
                        <td colspan="10" class="px-3 py-8 text-center text-gray-400">
                            <span class="material-symbols-outlined text-5xl text-gray-300 block">people</span>
                            <p class="text-sm mt-1">No enumerators found</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($enumerators_raw as $e): ?>
                    <tr class="table-row-hover" data-id="<?= $e['id'] ?>">
                        <td class="px-3 py-2">
                            <input type="checkbox" class="row-checkbox rounded border-gray-300"
                                   value="<?= $e['id'] ?>"
                                   <?= in_array($e['id'], $_SESSION['selected_enumerators']) ? 'checked' : '' ?>
                                   onchange="updateSelection(this, <?= $e['id'] ?>)">
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-600"><?= $e['id'] ?></td>
                        <td class="px-3 py-2 text-xs font-medium text-gray-800"><?= htmlspecialchars($e['name']) ?></td>
                        <td class="px-3 py-2 text-xs text-gray-600"><?= htmlspecialchars($e['email']) ?></td>
                        <td class="px-3 py-2 text-xs text-gray-600"><?= htmlspecialchars($e['phone']) ?></td>
                        <td class="px-3 py-2"><span class="bg-gray-100 px-2 py-0.5 rounded text-xs"><?= htmlspecialchars($e['gender'] ?: '—') ?></span></td>
                        <td class="px-3 py-2 text-xs text-gray-600"><?= htmlspecialchars($e['country'] ?: '—') ?></td>
                        <td class="px-3 py-2">
                            <div class="flex flex-wrap gap-1">
                                <?php if (!empty($e['tradepoints_list'])): ?>
                                    <?php foreach (array_slice($e['tradepoints_list'], 0, 2) as $tp): ?>
                                        <?php
                                        $tp_class = '';
                                        if (stripos($tp, 'Market') !== false)      $tp_class = 'market';
                                        elseif (stripos($tp, 'Border') !== false)  $tp_class = 'border-point';
                                        elseif (stripos($tp, 'Miller') !== false)  $tp_class = 'miller';
                                        ?>
                                        <span class="tradepoint-tag <?= $tp_class ?>" title="<?= htmlspecialchars($tp) ?>">
                                            <?= htmlspecialchars(strlen($tp) > 28 ? substr($tp, 0, 27) . "…" : $tp) ?>
                                        </span>
                                    <?php endforeach; ?>
                                    <?php if (count($e['tradepoints_list']) > 2): ?>
                                        <span class="tradepoint-tag" title="<?= htmlspecialchars(implode(', ', array_slice($e['tradepoints_list'], 2))) ?>">
                                            +<?= count($e['tradepoints_list']) - 2 ?> more
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-gray-400 text-xs">—</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-500"><?= date('M d, Y', strtotime($e['created_at'])) ?></td>
                        <td class="px-3 py-2">
                            <div class="flex items-center justify-center gap-1">
                                <button onclick="editEnumerator(<?= $e['id'] ?>)"
                                        class="action-btn bg-blue-100 text-blue-700 hover:bg-blue-200" title="Edit">
                                    <span class="material-symbols-outlined text-sm">edit</span>
                                </button>
                                <button onclick="deleteSingle(<?= $e['id'] ?>,'<?= htmlspecialchars(addslashes($e['name'])) ?>')"
                                        class="action-btn bg-red-100 text-red-700 hover:bg-red-200" title="Delete">
                                    <span class="material-symbols-outlined text-sm">delete</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="border-t border-gray-200 px-4 py-3 bg-white">
            <div class="flex flex-wrap justify-between items-center gap-3">
                <div class="text-xs text-gray-500">
                    Showing <strong><?= $showing_from ?></strong> – <strong><?= $showing_to ?></strong>
                    of <strong><?= number_format($filtered_records) ?></strong> enumerators
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2">
                        <label class="text-xs text-gray-500" for="rowsPerPage">Rows:</label>
                        <select id="rowsPerPage" class="page-size-select" onchange="changeRowsPerPage(this.value)">
                            <?php foreach ([10, 20, 50, 100] as $opt): ?>
                                <option value="<?= $opt ?>" <?= $limit === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($total_pages > 1): ?>
                    <nav class="flex items-center gap-1">
                        <button class="pagination-btn" onclick="goToPage(1)" <?= $page <= 1 ? 'disabled' : '' ?>>
                            <span class="material-symbols-outlined text-sm">first_page</span>
                        </button>
                        <button class="pagination-btn" onclick="goToPage(<?= $page - 1 ?>)" <?= $page <= 1 ? 'disabled' : '' ?>>
                            <span class="material-symbols-outlined text-sm">chevron_left</span>
                        </button>
                        <?php
                        $sp = max(1, $page - 2);
                        $ep = min($total_pages, $page + 2);
                        if ($sp > 1): ?>
                            <button class="pagination-btn" onclick="goToPage(1)">1</button>
                            <?php if ($sp > 2): ?><span class="text-gray-400 text-xs px-1">…</span><?php endif;
                        endif;
                        for ($i = $sp; $i <= $ep; $i++): ?>
                            <button class="pagination-btn <?= $i === $page ? 'active-page' : '' ?>"
                                    <?= $i === $page ? '' : "onclick=\"goToPage($i)\"" ?>><?= $i ?></button>
                        <?php endfor;
                        if ($ep < $total_pages):
                            if ($ep < $total_pages - 1): ?><span class="text-gray-400 text-xs px-1">…</span><?php endif; ?>
                            <button class="pagination-btn" onclick="goToPage(<?= $total_pages ?>)"><?= $total_pages ?></button>
                        <?php endif; ?>
                        <button class="pagination-btn" onclick="goToPage(<?= $page + 1 ?>)" <?= $page >= $total_pages ? 'disabled' : '' ?>>
                            <span class="material-symbols-outlined text-sm">chevron_right</span>
                        </button>
                        <button class="pagination-btn" onclick="goToPage(<?= $total_pages ?>)" <?= $page >= $total_pages ? 'disabled' : '' ?>>
                            <span class="material-symbols-outlined text-sm">last_page</span>
                        </button>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div><!-- /max-w-7xl -->
</div><!-- /auth-bg-gradient -->

<!-- ===================== ADD/EDIT MODAL ===================== -->
<div id="enumeratorModal" class="fixed inset-0 bg-black/50 hidden z-50 overflow-y-auto">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-xl w-full max-w-3xl max-h-[90vh] overflow-y-auto shadow-xl">
            <div class="modal-gradient-header px-5 py-3 flex justify-between items-center sticky top-0 z-10">
                <h3 id="modalTitle" class="text-base font-semibold text-white">Add New Enumerator</h3>
                <button onclick="closeModal('enumeratorModal')" class="text-white/80 hover:text-white">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>
            <div class="p-5">
                <form method="POST" action="" id="enumeratorForm">
                    <input type="hidden" name="enumerator_id" id="enumeratorId">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Full Name <span class="text-red-500">*</span></label>
                            <input type="text" name="name" id="name" required class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Email <span class="text-red-500">*</span></label>
                            <input type="email" name="email" id="email" required class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Phone <span class="text-red-500">*</span></label>
                            <input type="text" name="phone" id="phone" required class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Gender <span class="text-red-500">*</span></label>
                            <select name="gender" id="gender" required class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20">
                                <option value="">Select</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Country <span class="text-red-500">*</span></label>
                            <input type="text" name="country" id="country" required list="countryList" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20">
                            <datalist id="countryList">
                                <?php foreach ($countries as $c): ?>
                                    <option value="<?= htmlspecialchars($c) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">County/District <span class="text-red-500">*</span></label>
                            <input type="text" name="county_district" id="county_district" required class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Username <span class="text-red-500">*</span></label>
                            <input type="text" name="username" id="username" required class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">
                                Password <span class="text-red-500" id="passwordRequired">*</span>
                                <span id="passwordHint" class="text-gray-400 hidden">(leave blank to keep current)</span>
                            </label>
                            <input type="password" name="password" id="password" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Latitude</label>
                            <input type="number" step="any" name="latitude" id="latitude" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Longitude</label>
                            <input type="number" step="any" name="longitude" id="longitude" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20">
                        </div>
                    </div>

                    <!-- Tradepoints picker -->
                    <div class="mb-4">
                        <label class="block text-xs text-gray-600 mb-1 font-medium">Assigned Tradepoints</label>
                        <div class="relative">
                            <input type="text" id="tradepointSearch" placeholder="Search and add tradepoints (type at least 2 characters)..." 
                                   class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20">
                            <div id="tradepointDropdown" class="absolute z-10 w-full bg-white border border-gray-200 rounded-lg shadow-lg tradepoint-dropdown hidden"></div>
                        </div>
                        <div id="selectedTradepointsContainer" class="mt-3 flex flex-wrap gap-2 min-h-[40px]"></div>
                        <div id="tradepointsHidden"></div>
                        <p class="text-xs text-gray-400 mt-1">Search by name or type (Market, Border Point, Miller)</p>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                        <button type="button" onclick="closeModal('enumeratorModal')" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit" name="add_enumerator" id="submitBtn" class="px-3 py-1.5 text-sm text-white rounded-lg hover:opacity-90" style="background-color: #800000;">Add Enumerator</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ===================== DELETE MODAL ===================== -->
<div id="deleteModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg w-full max-w-md shadow-xl p-4">
        <div class="flex items-center gap-2 mb-3">
            <span class="material-symbols-outlined text-red-500">warning</span>
            <h3 class="text-base font-semibold text-gray-800">Confirm Deletion</h3>
        </div>
        <p id="deleteModalText" class="text-sm text-gray-500 mb-3">Are you sure you want to delete this enumerator?</p>
        <div class="bg-red-50 border-l-4 border-red-500 p-2 mb-3 text-xs text-red-700">
            <span class="material-symbols-outlined text-xs align-middle">info</span> This action cannot be undone.
        </div>
        <form method="POST" action="" id="deleteForm">
            <input type="hidden" name="delete_selected" value="1">
            <div id="deleteIdsContainer"></div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeModal('deleteModal')" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-3 py-1.5 text-sm bg-red-500 text-white rounded-lg hover:bg-red-600">Delete</button>
            </div>
        </form>
    </div>
</div>

<!-- ===================== IMPORT MODAL ===================== -->
<div id="importModal" class="fixed inset-0 bg-black/50 hidden z-50 overflow-y-auto">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-xl w-full max-w-2xl shadow-xl">
            <div class="modal-gradient-header px-5 py-3 flex justify-between items-center rounded-t-xl">
                <h3 class="text-base font-semibold text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-base">upload_file</span>Bulk Import Enumerators (CSV)
                </h3>
                <button onclick="closeModal('importModal')" class="text-white/80 hover:text-white">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>
            <div class="p-5">
                <div class="bg-blue-50 border-l-4 border-blue-500 rounded-r-lg p-4 mb-5 text-sm">
                    <p class="font-semibold text-blue-800 mb-2">CSV Column Order</p>
                    <ol class="list-decimal list-inside text-blue-700 space-y-0.5 text-xs">
                        <li><strong>Name</strong> — required</li>
                        <li><strong>Email</strong> — required</li>
                        <li><strong>Phone</strong> — required</li>
                        <li><strong>Gender</strong> — required</li>
                        <li><strong>Country</strong> — required</li>
                        <li><strong>County/District</strong> — required</li>
                        <li><strong>Username</strong> — optional (auto-generated if blank)</li>
                        <li><strong>Password</strong> — optional (auto-generated if blank)</li>
                        <li><strong>Latitude</strong> — optional</li>
                        <li><strong>Longitude</strong> — optional</li>
                        <li><strong>Tradepoints</strong> — optional (e.g. "Market:1,Border Point:2")</li>
                    </ol>
                    <a href="?download_template=1" class="inline-flex items-center gap-1 mt-3 text-xs text-blue-700 font-medium hover:underline">
                        <span class="material-symbols-outlined text-sm">download</span>Download template CSV
                    </a>
                </div>
                <form method="POST" enctype="multipart/form-data" id="importForm">
                    <div class="mb-4">
                        <label class="block text-xs text-gray-600 mb-1 font-medium">
                            Select CSV File <span class="text-red-500">*</span>
                        </label>
                        <input type="file" name="csv_file" id="importCsvFile" accept=".csv" required class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-blue-500 focus:outline-none">
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none mb-5">
                        <input type="checkbox" name="overwrite_existing" id="overwriteExisting" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span>Overwrite existing enumerators with matching emails</span>
                    </label>
                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                        <button type="button" onclick="closeModal('importModal')" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit" name="import_csv" class="px-4 py-1.5 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 inline-flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">upload</span>Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let allTradepoints = [];
let selectedTradepoints = [];

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

function openModal(id) { 
    document.getElementById(id).classList.remove('hidden'); 
    document.body.style.overflow = 'hidden';
}

function closeModal(id) { 
    document.getElementById(id).classList.add('hidden'); 
    document.body.style.overflow = 'auto';
}

// Load tradepoints from API
async function loadTradepoints() {
    try {
        const response = await fetch(`${window.location.pathname}?get_tradepoints=1`);
        allTradepoints = await response.json();
        console.log('Loaded tradepoints:', allTradepoints.length);
    } catch (e) { 
        console.error('Failed to load tradepoints:', e); 
    }
}

// Open add modal
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New Enumerator';
    document.getElementById('enumeratorId').value = '';
    
    const fields = ['name', 'email', 'phone', 'country', 'county_district', 'username', 'password', 'latitude', 'longitude'];
    fields.forEach(f => {
        const el = document.getElementById(f);
        if (el) el.value = '';
    });
    
    const genderEl = document.getElementById('gender');
    if (genderEl) genderEl.value = '';
    
    document.getElementById('passwordRequired').style.display = 'inline';
    document.getElementById('passwordHint').classList.add('hidden');
    document.getElementById('password').required = true;
    document.getElementById('password').value = '';
    
    selectedTradepoints = [];
    updateTradepointsDisplay();
    updateTradepointsHidden();
    
    document.getElementById('submitBtn').name = 'add_enumerator';
    document.getElementById('submitBtn').textContent = 'Add Enumerator';
    openModal('enumeratorModal');
}

// Edit enumerator
async function editEnumerator(id) {
    try {
        const response = await fetch(`${window.location.pathname}?get_enumerator=${id}`);
        const data = await response.json();
        
        document.getElementById('modalTitle').textContent = 'Edit Enumerator';
        document.getElementById('enumeratorId').value = data.id;
        document.getElementById('name').value = data.name || '';
        document.getElementById('email').value = data.email || '';
        document.getElementById('phone').value = data.phone || '';
        document.getElementById('gender').value = data.gender || '';
        document.getElementById('country').value = data.country || '';
        document.getElementById('county_district').value = data.county_district || '';
        document.getElementById('username').value = data.username || '';
        document.getElementById('latitude').value = data.latitude || '';
        document.getElementById('longitude').value = data.longitude || '';
        document.getElementById('password').value = '';
        document.getElementById('passwordRequired').style.display = 'none';
        document.getElementById('passwordHint').classList.remove('hidden');
        document.getElementById('password').required = false;
        
        selectedTradepoints = [];
        if (data.tradepoints && Array.isArray(data.tradepoints)) {
            for (const tp of data.tradepoints) {
                if (tp.id && tp.type) {
                    selectedTradepoints.push({ 
                        id: tp.id, 
                        type: tp.type, 
                        name: tp.name || `ID: ${tp.id}`
                    });
                }
            }
        }
        updateTradepointsDisplay();
        updateTradepointsHidden();
        
        document.getElementById('submitBtn').name = 'edit_enumerator';
        document.getElementById('submitBtn').textContent = 'Update Enumerator';
        openModal('enumeratorModal');
    } catch (e) { 
        console.error('Failed to load enumerator data:', e);
        alert('Failed to load enumerator data.'); 
    }
}

// Setup tradepoint search
function setupTradepointSearch() {
    const searchInput = document.getElementById('tradepointSearch');
    const dropdown = document.getElementById('tradepointDropdown');
    if (!searchInput) return;

    searchInput.addEventListener('input', function() {
        const term = this.value.toLowerCase().trim();
        if (term.length < 2) {
            dropdown.classList.add('hidden');
            return;
        }
        
        const filtered = allTradepoints.filter(tp =>
            tp.name.toLowerCase().includes(term) || tp.type.toLowerCase().includes(term)
        );
        
        renderDropdown(filtered.slice(0, 15));
    });
    
    searchInput.addEventListener('focus', function() {
        if (allTradepoints.length > 0) {
            renderDropdown(allTradepoints.slice(0, 15));
        }
    });
    
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });

    function renderDropdown(items) {
        dropdown.innerHTML = '';
        if (!items.length) {
            const div = document.createElement('div');
            div.className = 'px-3 py-2 text-sm text-gray-500';
            div.textContent = 'No tradepoints found. Try a different search term.';
            dropdown.appendChild(div);
        } else {
            items.forEach(tp => {
                const div = document.createElement('div');
                div.className = 'tradepoint-dropdown-item';
                div.innerHTML = `<span class="font-medium">${escapeHtml(tp.name)}</span> <span class="text-xs text-gray-500 ml-2">(${tp.type})</span>`;
                div.onclick = () => {
                    const exists = selectedTradepoints.some(t => String(t.id) === String(tp.id) && t.type === tp.type);
                    if (!exists) {
                        selectedTradepoints.push({ id: tp.id, type: tp.type, name: tp.name });
                        updateTradepointsDisplay();
                        updateTradepointsHidden();
                    }
                    searchInput.value = '';
                    dropdown.classList.add('hidden');
                };
                dropdown.appendChild(div);
            });
        }
        dropdown.classList.remove('hidden');
    }
}

function removeTradepoint(index) {
    selectedTradepoints.splice(index, 1);
    updateTradepointsDisplay();
    updateTradepointsHidden();
}

function getTagClass(type) {
    switch (type) {
        case 'Market': return 'bg-rose-100 text-rose-700';
        case 'Border Point': return 'bg-amber-100 text-amber-700';
        case 'Miller': return 'bg-cyan-100 text-cyan-700';
        default: return 'bg-gray-100 text-gray-700';
    }
}

function updateTradepointsDisplay() {
    const container = document.getElementById('selectedTradepointsContainer');
    container.innerHTML = '';
    
    if (selectedTradepoints.length === 0) {
        const placeholder = document.createElement('div');
        placeholder.className = 'text-xs text-gray-400 italic';
        placeholder.textContent = 'No tradepoints assigned. Use the search box above to add tradepoints.';
        container.appendChild(placeholder);
    } else {
        selectedTradepoints.forEach((tp, index) => {
            const tag = document.createElement('span');
            tag.className = `inline-flex items-center gap-1 px-2 py-1 text-xs rounded-full ${getTagClass(tp.type)}`;
            tag.innerHTML = `${escapeHtml(tp.name)} (${tp.type}) <button type="button" onclick="removeTradepoint(${index})" class="ml-1 hover:opacity-70 font-bold">×</button>`;
            container.appendChild(tag);
        });
    }
}

function updateTradepointsHidden() {
    const hidden = document.getElementById('tradepointsHidden');
    hidden.innerHTML = '';
    
    selectedTradepoints.forEach((tp, index) => {
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = `tradepoints[${index}][id]`;
        idInput.value = tp.id;
        hidden.appendChild(idInput);
        
        const typeInput = document.createElement('input');
        typeInput.type = 'hidden';
        typeInput.name = `tradepoints[${index}][type]`;
        typeInput.value = tp.type;
        hidden.appendChild(typeInput);
    });
}

function applyFilters() {
    const params = new URLSearchParams();
    const name = document.getElementById('searchName')?.value;
    const email = document.getElementById('searchEmail')?.value;
    const country = document.getElementById('searchCountry')?.value;
    const gender = document.getElementById('searchGender')?.value;
    
    if (name) params.set('search_name', name);
    if (email) params.set('search_email', email);
    if (country) params.set('search_country', country);
    if (gender) params.set('search_gender', gender);
    params.set('page', '1');
    
    window.location.href = window.location.pathname + '?' + params.toString();
}

function sortTable(column) {
    const params = new URLSearchParams(window.location.search);
    const currentSort = params.get('sort');
    const currentDir = params.get('dir');
    
    if (currentSort === column) {
        params.set('dir', currentDir === 'asc' ? 'desc' : 'asc');
    } else {
        params.set('sort', column);
        params.set('dir', 'asc');
    }
    params.set('page', '1');
    
    window.location.href = window.location.pathname + '?' + params.toString();
}

function goToPage(page) {
    const params = new URLSearchParams(window.location.search);
    params.set('page', page);
    window.location.href = window.location.pathname + '?' + params.toString();
}

function changeRowsPerPage(limit) {
    const params = new URLSearchParams(window.location.search);
    params.set('limit', limit);
    params.set('page', '1');
    window.location.href = window.location.pathname + '?' + params.toString();
}

let selectedEnumeratorIds = new Set();

function updateSelection(checkbox, id) {
    const isSelected = checkbox.checked;
    
    if (isSelected) {
        selectedEnumeratorIds.add(id);
    } else {
        selectedEnumeratorIds.delete(id);
    }
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=update_selection&id=${id}&selected=${isSelected}`
    }).catch(e => console.error(e));
    
    updateSelectedCount();
}

function updateSelectedCount() {
    const count = selectedEnumeratorIds.size;
    const countEl = document.getElementById('selectedCount');
    const deleteBtn = document.getElementById('bulkDeleteBtn');
    if (countEl) countEl.textContent = count;
    if (deleteBtn) deleteBtn.disabled = count === 0;
    
    const checkboxes = document.querySelectorAll('.row-checkbox');
    const selectAll = document.getElementById('selectAllCheckbox');
    if (selectAll && checkboxes.length > 0) {
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        const someChecked = Array.from(checkboxes).some(cb => cb.checked);
        selectAll.checked = allChecked;
        selectAll.indeterminate = !allChecked && someChecked;
    }
}

function deleteSingle(id, name) {
    document.getElementById('deleteModalText').innerHTML = `Are you sure you want to delete <strong>${escapeHtml(name)}</strong>?`;
    document.getElementById('deleteIdsContainer').innerHTML = `<input type="hidden" name="selected_ids[]" value="${id}">`;
    openModal('deleteModal');
}

function openImportModal() {
    document.getElementById('importForm').reset();
    openModal('importModal');
}

document.addEventListener('DOMContentLoaded', async function() {
    await loadTradepoints();
    setupTradepointSearch();
    
    <?php foreach ($_SESSION['selected_enumerators'] as $id): ?>
    selectedEnumeratorIds.add(<?= $id ?>);
    <?php endforeach; ?>
    updateSelectedCount();
    
    document.querySelectorAll('.sortable').forEach(th => {
        th.addEventListener('click', () => sortTable(th.dataset.sort));
    });
    
    const searchInputs = ['searchName', 'searchEmail', 'searchCountry'];
    searchInputs.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') applyFilters();
            });
        }
    });
    
    const genderSelect = document.getElementById('searchGender');
    if (genderSelect) {
        genderSelect.addEventListener('change', applyFilters);
    }
    
    const selectAll = document.getElementById('selectAllCheckbox');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            const isChecked = this.checked;
            document.querySelectorAll('.row-checkbox').forEach(cb => {
                if (cb.checked !== isChecked) {
                    cb.checked = isChecked;
                    updateSelection(cb, parseInt(cb.value));
                }
            });
            updateSelectedCount();
        });
    }
    
    const clearBtn = document.getElementById('clearSelectionsBtn');
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            if (confirm('Clear all selections across all pages?')) {
                document.querySelectorAll('.row-checkbox').forEach(cb => {
                    cb.checked = false;
                    updateSelection(cb, parseInt(cb.value));
                });
                updateSelectedCount();
                
                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=update_selection&clear_all=true'
                }).catch(e => console.error(e));
            }
        });
    }
    
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    if (bulkDeleteBtn) {
        bulkDeleteBtn.addEventListener('click', function() {
            const ids = Array.from(selectedEnumeratorIds);
            if (ids.length === 0) return;
            
            document.getElementById('deleteModalText').innerHTML = `Are you sure you want to delete <strong>${ids.length}</strong> selected enumerator(s)?`;
            document.getElementById('deleteIdsContainer').innerHTML = ids.map(id => `<input type="hidden" name="selected_ids[]" value="${id}">`).join('');
            openModal('deleteModal');
        });
    }
});
</script>


</body>
</html>