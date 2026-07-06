<?php
// admin/get_submission.php - Get submission details for view modal
if (session_status() == PHP_SESSION_NONE) session_start();
include '../admin/includes/config.php';

header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

$sql = "SELECT * FROM harvest_submissions WHERE id = ?";
$stmt = $con->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Submission not found']);
    exit;
}

// Build the HTML for the modal
$html = '<div class="hs-detail-grid">';

$fields = [
    'ID' => 'id',
    'UUID' => 'submission_uuid',
    'Farmer ID' => 'farmer_id',
    'Full Name' => 'full_name',
    'Gender' => 'gender',
    'Age Group' => 'age_group',
    'Contact' => 'contact_details',
    'Cooperative' => 'cooperative',
    'County' => 'county',
    'Sub County' => 'sub_county',
    'Ward' => 'ward',
    'Village' => 'village',
    'GPS Coordinates' => 'gps_coordinates',
    'Crop Type' => 'crop_type',
    'Variety' => 'variety',
    'Season' => 'season',
    'Harvest Date' => 'harvest_date',
    'Area Harvested' => 'area_harvested',
    'Area Unit' => 'area_harvested_unit',
    'Total Production' => 'total_production',
    'Production Unit' => 'total_production_unit',
    'Yield Value' => 'yield_value',
    'Harvesting Method' => 'harvesting_method',
    'Stage of Harvest' => 'stage_of_harvest',
    'Harvest Losses' => 'harvest_losses',
    'Losses Unit' => 'harvest_losses_unit',
    'Primary Cause of Loss' => 'primary_cause_of_loss',
    'Drying Method' => 'drying_method',
    'Drying Duration (days)' => 'drying_duration_days',
    'Drying Surface' => 'drying_surface',
    'Turning Frequency' => 'turning_frequency',
    'Moisture Testing' => 'moisture_testing_done',
    'Testing Method' => 'testing_method',
    'Drying Losses' => 'drying_losses',
    'Drying Losses Unit' => 'drying_losses_unit',
    'Storage Start Date' => 'storage_start_date',
    'Storage Type' => 'storage_type',
    'Structure Condition' => 'structure_condition',
    'Storage Duration' => 'storage_duration',
    'Storage Duration Unit' => 'storage_duration_unit',
    'Treatment Applied' => 'treatment_applied',
    'Pests Present' => 'pest_present',
    'Rodents Present' => 'rodent_present',
    'Quantity Stored (kg)' => 'qty_stored_kg',
    'Quantity Lost (kg)' => 'qty_lost',
    'Quantity Sold (kg)' => 'quantity_sold_kg',
    'Quantity Retained (kg)' => 'quantity_retained_kg',
    'Selling Price' => 'selling_price',
    'Market Type' => 'market_type',
    'Date of Sale' => 'date_of_sale',
    'Buyer Type' => 'buyer_type',
    'Posted By' => 'posted_by_name',
    'Posted Email' => 'posted_by_email',
    'Posted Username' => 'posted_by_username',
    'Status' => 'status',
    'Admin Notes' => 'admin_notes',
    'Submission Date' => 'submission_date',
    'Processed At' => 'processed_at'
];

foreach ($fields as $label => $key) {
    $value = $row[$key] ?? '';
    if ($value === '' || $value === null) continue;
    if (is_bool($value)) $value = $value ? 'Yes' : 'No';
    $html .= '<div class="hs-detail-item"><div class="label">' . $label . '</div><div class="value">' . htmlspecialchars($value) . '</div></div>';
}

$html .= '</div>';

echo json_encode(['success' => true, 'html' => $html]);
?>