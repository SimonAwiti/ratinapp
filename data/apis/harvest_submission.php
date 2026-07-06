<?php
/**
 * harvest_submission.php - API endpoint for harvest data collection
 * ─────────────────────────────────────────────────────────────
 */

// ── Configuration ──
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

// ── Database Connection ──
require_once '../../admin/includes/config.php';

if (!isset($con) || !($con instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection not available']);
    exit;
}

// ── Authentication ──
function authenticateEnumerator($con) {
    $headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
    $authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    $token = '';
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = $matches[1];
    }
    
    if (empty($token)) {
        $input = json_decode(file_get_contents('php://input'), true);
        $token = $input['token'] ?? '';
    }
    
    if (empty($token)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required. Please provide a valid token.']);
        exit;
    }
    
    $stmt = $con->prepare("SELECT id, name, email, username, phone, tradepoints, latitude, longitude FROM enumerators WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired token. Please login again.']);
        exit;
    }
    
    $enumerator = $result->fetch_assoc();
    $stmt->close();
    
    return $enumerator;
}

// Authenticate the request
$enumerator = authenticateEnumerator($con);

// ── Get and validate input ──
$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload']);
    exit;
}

if (!isset($data['tradepoint_id']) || !is_numeric($data['tradepoint_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing or invalid tradepoint_id']);
    exit;
}

if (!isset($data['submissions']) || !is_array($data['submissions']) || empty($data['submissions'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing or empty submissions array']);
    exit;
}

$tradepoint_id = (int)$data['tradepoint_id'];
$submissions = $data['submissions'];
$processed = 0;
$failed = 0;
$errors = [];

// ── Process each submission ──
foreach ($submissions as $index => $submission) {
    try {
        $result = processHarvestSubmission($con, $tradepoint_id, $submission, $enumerator);
        if ($result['success']) {
            $processed++;
        } else {
            $failed++;
            $errors[] = "Submission #" . ($index + 1) . ": " . $result['message'];
        }
    } catch (Exception $e) {
        $failed++;
        $errors[] = "Submission #" . ($index + 1) . ": " . $e->getMessage();
    }
}

// ── Response ──
$response = [
    'success' => ($failed === 0),
    'message' => $failed === 0 ? 'All submissions processed successfully' : 'Some submissions failed',
    'data' => [
        'processed' => $processed,
        'failed' => $failed,
        'total' => count($submissions)
    ]
];

if (!empty($errors)) {
    $response['errors'] = $errors;
}

$response['submitted_by'] = [
    'id' => $enumerator['id'],
    'name' => $enumerator['name'],
    'email' => $enumerator['email'],
    'username' => $enumerator['username']
];

http_response_code($failed === 0 ? 200 : 207);
echo json_encode($response);

// ─────────────────────────────────────────────────────────────
// FUNCTION: Process a single harvest submission
// ─────────────────────────────────────────────────────────────
function processHarvestSubmission($con, $tradepoint_id, $submission, $enumerator) {
    // Generate a unique UUID for this submission
    $submission_uuid = generateUUID();
    
    // ── Extract and sanitize data ──
    $farmer_id = sanitize($submission['farmer_id'] ?? null);
    $full_name = sanitize($submission['full_name'] ?? '');
    $gender = sanitize($submission['gender'] ?? null);
    $age_group = sanitize($submission['age_group'] ?? null);
    $contact_details = sanitize($submission['contact_details'] ?? null);
    $cooperative = sanitize($submission['cooperative'] ?? null);
    $county = sanitize($submission['county'] ?? null);
    $sub_county = sanitize($submission['sub_county'] ?? null);
    $ward = sanitize($submission['ward'] ?? null);
    $village = sanitize($submission['village'] ?? null);
    $gps_coordinates = sanitize($submission['gps_coordinates'] ?? null);
    
    $crop_type = sanitize($submission['crop_type'] ?? '');
    $variety = sanitize($submission['variety'] ?? null);
    $season = sanitize($submission['season'] ?? null);
    $harvest_date = sanitizeDate($submission['harvest_date'] ?? null);
    
    $area_harvested = sanitizeFloat($submission['area_harvested'] ?? null);
    $area_harvested_unit = sanitize($submission['area_harvested_unit'] ?? null);
    $total_production = sanitizeFloat($submission['total_production'] ?? null);
    $total_production_unit = sanitize($submission['total_production_unit'] ?? null);
    $yield_value = sanitizeFloat($submission['yield_value'] ?? null);
    $harvesting_method = sanitize($submission['harvesting_method'] ?? null);
    $stage_of_harvest = sanitize($submission['stage_of_harvest'] ?? null);
    $harvest_losses = sanitizeFloat($submission['harvest_losses'] ?? null);
    $harvest_losses_unit = sanitize($submission['harvest_losses_unit'] ?? null);
    $primary_cause_of_loss = sanitize($submission['primary_cause_of_loss'] ?? null);
    
    $drying_method = sanitize($submission['drying_method'] ?? null);
    $drying_duration_days = sanitizeInt($submission['drying_duration_days'] ?? null);
    $drying_surface = sanitize($submission['drying_surface'] ?? null);
    $turning_frequency = sanitize($submission['turning_frequency'] ?? null);
    $moisture_testing_done = isset($submission['moisture_testing_done']) ? (bool)$submission['moisture_testing_done'] : false;
    $testing_method = sanitize($submission['testing_method'] ?? null);
    $drying_losses = sanitizeFloat($submission['drying_losses'] ?? null);
    $drying_losses_unit = sanitize($submission['drying_losses_unit'] ?? null);
    
    $storage_start_date = sanitizeDate($submission['storage_start_date'] ?? null);
    $storage_type = sanitize($submission['storage_type'] ?? null);
    $structure_condition = sanitize($submission['structure_condition'] ?? null);
    $storage_duration = sanitizeInt($submission['storage_duration'] ?? null);
    $storage_duration_unit = sanitize($submission['storage_duration_unit'] ?? null);
    $treatment_applied = sanitize($submission['treatment_applied'] ?? null);
    $pest_present = isset($submission['pest_present']) ? (bool)$submission['pest_present'] : false;
    $rodent_present = isset($submission['rodent_present']) ? (bool)$submission['rodent_present'] : false;
    $qty_stored_kg = sanitizeFloat($submission['qty_stored_kg'] ?? null);
    $qty_lost = sanitizeFloat($submission['qty_lost'] ?? null);
    
    $quantity_sold_kg = sanitizeFloat($submission['quantity_sold_kg'] ?? null);
    $quantity_retained_kg = sanitizeFloat($submission['quantity_retained_kg'] ?? null);
    $selling_price = sanitizeFloat($submission['selling_price'] ?? null);
    $market_type = sanitize($submission['market_type'] ?? null);
    $date_of_sale = sanitizeDate($submission['date_of_sale'] ?? null);
    $buyer_type = sanitize($submission['buyer_type'] ?? null);
    
    // Posted by fields from enumerator
    $posted_by_id = $enumerator['id'];
    $posted_by_name = $enumerator['name'];
    $posted_by_email = $enumerator['email'];
    $posted_by_username = $enumerator['username'];
    $posted_by_phone = $enumerator['phone'] ?? null;
    $posted_by_latitude = $enumerator['latitude'] ?? null;
    $posted_by_longitude = $enumerator['longitude'] ?? null;
    $posted_by_tradepoints = $enumerator['tradepoints'] ?? null;
    $posted_by_ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $posted_by_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // Submission date
    $submission_date = date('Y-m-d H:i:s');
    
    // Validate required fields
    if (empty($farmer_id)) {
        return ['success' => false, 'message' => 'farmer_id is required'];
    }
    if (empty($full_name)) {
        return ['success' => false, 'message' => 'full_name is required'];
    }
    if (empty($crop_type)) {
        return ['success' => false, 'message' => 'crop_type is required'];
    }
    
    // Convert boolean to int for database
    $moisture_testing_done_int = $moisture_testing_done ? 1 : 0;
    $pest_present_int = $pest_present ? 1 : 0;
    $rodent_present_int = $rodent_present ? 1 : 0;
    
    // ── Build INSERT using a simpler array approach ──
    $data = [
        'submission_uuid' => $submission_uuid,
        'tradepoint_id' => $tradepoint_id,
        'farmer_id' => $farmer_id,
        'full_name' => $full_name,
        'gender' => $gender,
        'age_group' => $age_group,
        'contact_details' => $contact_details,
        'cooperative' => $cooperative,
        'county' => $county,
        'sub_county' => $sub_county,
        'ward' => $ward,
        'village' => $village,
        'gps_coordinates' => $gps_coordinates,
        'crop_type' => $crop_type,
        'variety' => $variety,
        'season' => $season,
        'harvest_date' => $harvest_date,
        'area_harvested' => $area_harvested,
        'area_harvested_unit' => $area_harvested_unit,
        'total_production' => $total_production,
        'total_production_unit' => $total_production_unit,
        'yield_value' => $yield_value,
        'harvesting_method' => $harvesting_method,
        'stage_of_harvest' => $stage_of_harvest,
        'harvest_losses' => $harvest_losses,
        'harvest_losses_unit' => $harvest_losses_unit,
        'primary_cause_of_loss' => $primary_cause_of_loss,
        'drying_method' => $drying_method,
        'drying_duration_days' => $drying_duration_days,
        'drying_surface' => $drying_surface,
        'turning_frequency' => $turning_frequency,
        'moisture_testing_done' => $moisture_testing_done_int,
        'testing_method' => $testing_method,
        'drying_losses' => $drying_losses,
        'drying_losses_unit' => $drying_losses_unit,
        'storage_start_date' => $storage_start_date,
        'storage_type' => $storage_type,
        'structure_condition' => $structure_condition,
        'storage_duration' => $storage_duration,
        'storage_duration_unit' => $storage_duration_unit,
        'treatment_applied' => $treatment_applied,
        'pest_present' => $pest_present_int,
        'rodent_present' => $rodent_present_int,
        'qty_stored_kg' => $qty_stored_kg,
        'qty_lost' => $qty_lost,
        'quantity_sold_kg' => $quantity_sold_kg,
        'quantity_retained_kg' => $quantity_retained_kg,
        'selling_price' => $selling_price,
        'market_type' => $market_type,
        'date_of_sale' => $date_of_sale,
        'buyer_type' => $buyer_type,
        'posted_by_id' => $posted_by_id,
        'posted_by_name' => $posted_by_name,
        'posted_by_email' => $posted_by_email,
        'posted_by_username' => $posted_by_username,
        'posted_by_phone' => $posted_by_phone,
        'posted_by_latitude' => $posted_by_latitude,
        'posted_by_longitude' => $posted_by_longitude,
        'posted_by_tradepoints' => $posted_by_tradepoints,
        'posted_by_ip' => $posted_by_ip,
        'posted_by_user_agent' => $posted_by_user_agent,
        'submission_date' => $submission_date
    ];
    
    // Build the SET clause and values arrays
    $set_parts = [];
    $values = [];
    $types = '';
    
    foreach ($data as $column => $value) {
        $set_parts[] = "$column = ?";
        $values[] = $value;
        
        // Determine the type for bind_param
        if ($value === null) {
            $types .= 's';
        } elseif (is_int($value)) {
            $types .= 'i';
        } elseif (is_float($value)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }
    
    $sql = "INSERT INTO harvest_submissions SET " . implode(', ', $set_parts);
    
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        return ['success' => false, 'message' => 'Database prepare error: ' . $con->error];
    }
    
    // Debug: Log the number of values and types
    error_log("Number of values: " . count($values));
    error_log("Types string length: " . strlen($types));
    error_log("Types string: " . $types);
    
    // Bind parameters dynamically
    $bind_names = array_merge([$types], $values);
    $bind_params = [];
    foreach ($bind_names as $key => $value) {
        $bind_params[$key] = &$bind_names[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_params);
    
    if ($stmt->execute()) {
        $submission_id = $stmt->insert_id;
        $stmt->close();
        
        // Log the submission
        logAudit($con, $submission_id, 'submitted', $posted_by_name, "Submission via API by enumerator: $posted_by_name ($posted_by_email)");
        
        return ['success' => true, 'message' => 'Submission saved', 'id' => $submission_id];
    } else {
        $error = $stmt->error;
        $stmt->close();
        return ['success' => false, 'message' => 'Database error: ' . $error];
    }
}

// ── Helper Functions ──
function sanitize($value) {
    if ($value === null || $value === '') return null;
    return trim($value);
}

function sanitizeFloat($value) {
    if ($value === null || $value === '') return null;
    return (float)$value;
}

function sanitizeInt($value) {
    if ($value === null || $value === '') return null;
    return (int)$value;
}

function sanitizeDate($value) {
    if ($value === null || $value === '') return null;
    $timestamp = strtotime($value);
    return $timestamp ? date('Y-m-d', $timestamp) : null;
}

function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function logAudit($con, $submission_id, $action, $action_by, $notes = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $sql = "INSERT INTO harvest_submission_audit (submission_id, action, action_by, action_notes, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $con->prepare($sql);
    $stmt->bind_param('isssss', $submission_id, $action, $action_by, $notes, $ip, $user_agent);
    $stmt->execute();
    $stmt->close();
}
?>