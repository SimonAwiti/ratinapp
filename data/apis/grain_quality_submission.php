<?php
/**
 * grain_quality_submission.php - API endpoint for grain quality testing data
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
        $result = processGrainQualitySubmission($con, $tradepoint_id, $submission, $enumerator);
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
    'message' => $failed === 0 ? 'All grain quality submissions processed successfully' : 'Some submissions failed',
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
// FUNCTION: Process a single grain quality submission
// ─────────────────────────────────────────────────────────────
function processGrainQualitySubmission($con, $tradepoint_id, $submission, $enumerator) {
    $submission_uuid = generateUUID();
    
    // ── Extract and sanitize data ──
    $sample_id = sanitize($submission['sample_id'] ?? null);
    $sampling_date = sanitizeDate($submission['sampling_date'] ?? null);
    $location = sanitize($submission['location'] ?? null);
    $warehouse = sanitize($submission['warehouse'] ?? null);
    $enumerator_id = sanitize($submission['enumerator_id'] ?? null);
    
    // Physical parameters
    $moisture = sanitizeFloat($submission['moisture'] ?? null);
    $test_weight = sanitizeFloat($submission['test_weight'] ?? null);
    $uniformity_grade = sanitize($submission['uniformity_grade'] ?? null);
    $broken_grains = sanitizeFloat($submission['broken_grains'] ?? null);
    $foreign_matter = sanitizeFloat($submission['foreign_matter'] ?? null);
    $impurities = sanitizeFloat($submission['impurities'] ?? null);
    $insect_damaged = sanitizeFloat($submission['insect_damaged'] ?? null);
    $discolored = sanitizeFloat($submission['discolored'] ?? null);
    $shrivelled = sanitizeFloat($submission['shrivelled'] ?? null);
    $moldy_grains = sanitizeFloat($submission['moldy_grains'] ?? null);
    $rotten_grains = sanitizeFloat($submission['rotten_grains'] ?? null);
    
    // Pest and contamination - FIXED ENUM HANDLING
    $valid_pest_levels = ['None', 'Low', 'Moderate', 'High'];
    $pest_infestation_level = null;
    if (!empty($submission['pest_infestation_level'])) {
        $level = trim($submission['pest_infestation_level']);
        // Check if it matches any valid level (case insensitive)
        foreach ($valid_pest_levels as $valid) {
            if (strcasecmp($level, $valid) === 0) {
                $pest_infestation_level = $valid;
                break;
            }
        }
        // If no match found, default to null
        if ($pest_infestation_level === null) {
            error_log("Invalid pest_infestation_level: " . $level);
        }
    }
    
    $live_insects_present = isset($submission['live_insects_present']) ? (bool)$submission['live_insects_present'] : false;
    $filth_contamination = isset($submission['filth_contamination']) ? (bool)$submission['filth_contamination'] : false;
    
    // Mycotoxins
    $aflatoxin_level = sanitizeFloat($submission['aflatoxin_level'] ?? null);
    $other_mycotoxins = sanitize($submission['other_mycotoxins'] ?? null);
    
    // Quality assessment
    $odor_assessment = sanitize($submission['odor_assessment'] ?? null);
    $color_assessment = sanitize($submission['color_assessment'] ?? null);
    $grade_classification = sanitize($submission['grade_classification'] ?? null);
    $eagc_compliant = isset($submission['eagc_compliant']) ? (bool)$submission['eagc_compliant'] : false;
    $reason_for_downgrade = sanitize($submission['reason_for_downgrade'] ?? null);
    
    // Posted by fields
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
    
    $submission_date = date('Y-m-d H:i:s');
    
    // Validate required fields
    if (empty($sample_id)) {
        return ['success' => false, 'message' => 'sample_id is required'];
    }
    if (empty($sampling_date)) {
        return ['success' => false, 'message' => 'sampling_date is required'];
    }
    
    // Convert boolean to int
    $live_insects_int = $live_insects_present ? 1 : 0;
    $filth_contamination_int = $filth_contamination ? 1 : 0;
    $eagc_compliant_int = $eagc_compliant ? 1 : 0;
    
    // ── Build the INSERT query dynamically ──
    $data = [
        'submission_uuid' => $submission_uuid,
        'tradepoint_id' => $tradepoint_id,
        'sample_id' => $sample_id,
        'sampling_date' => $sampling_date,
        'location' => $location,
        'warehouse' => $warehouse,
        'enumerator_id' => $enumerator_id,
        'moisture' => $moisture,
        'test_weight' => $test_weight,
        'uniformity_grade' => $uniformity_grade,
        'broken_grains' => $broken_grains,
        'foreign_matter' => $foreign_matter,
        'impurities' => $impurities,
        'insect_damaged' => $insect_damaged,
        'discolored' => $discolored,
        'shrivelled' => $shrivelled,
        'moldy_grains' => $moldy_grains,
        'rotten_grains' => $rotten_grains,
        'pest_infestation_level' => $pest_infestation_level,
        'live_insects_present' => $live_insects_int,
        'filth_contamination' => $filth_contamination_int,
        'aflatoxin_level' => $aflatoxin_level,
        'other_mycotoxins' => $other_mycotoxins,
        'odor_assessment' => $odor_assessment,
        'color_assessment' => $color_assessment,
        'grade_classification' => $grade_classification,
        'eagc_compliant' => $eagc_compliant_int,
        'reason_for_downgrade' => $reason_for_downgrade,
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
    
    // Build SET clause
    $set_parts = [];
    $values = [];
    $types = '';
    
    foreach ($data as $column => $value) {
        $set_parts[] = "$column = ?";
        $values[] = $value;
        
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
    
    $sql = "INSERT INTO grain_quality_submissions SET " . implode(', ', $set_parts);
    
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        return ['success' => false, 'message' => 'Database prepare error: ' . $con->error];
    }
    
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
        
        logAudit($con, $submission_id, 'submitted', $posted_by_name, "Grain quality submission via API by enumerator: $posted_by_name ($posted_by_email)");
        
        return ['success' => true, 'message' => 'Grain quality submission saved', 'id' => $submission_id];
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
    
    $sql = "INSERT INTO grain_quality_audit (submission_id, action, action_by, action_notes, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $con->prepare($sql);
    $stmt->bind_param('isssss', $submission_id, $action, $action_by, $notes, $ip, $user_agent);
    $stmt->execute();
    $stmt->close();
}
?>