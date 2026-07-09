<?php
// data/get_grain_quality.php - Get grain quality submission details
if (session_status() == PHP_SESSION_NONE) session_start();
include '../admin/includes/config.php';

header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

$sql = "SELECT * FROM grain_quality_submissions WHERE id = ?";
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

$html = '<div class="gq-detail-grid">';

$fields = [
    'Sample ID' => 'sample_id',
    'Sampling Date' => 'sampling_date',
    'Location' => 'location',
    'Warehouse' => 'warehouse',
    'Enumerator ID' => 'enumerator_id',
    'Moisture (%)' => 'moisture',
    'Test Weight (kg/hl)' => 'test_weight',
    'Uniformity Grade' => 'uniformity_grade',
    'Broken Grains (%)' => 'broken_grains',
    'Foreign Matter (%)' => 'foreign_matter',
    'Impurities (%)' => 'impurities',
    'Insect Damaged (%)' => 'insect_damaged',
    'Discolored (%)' => 'discolored',
    'Shrivelled (%)' => 'shrivelled',
    'Moldy Grains (%)' => 'moldy_grains',
    'Rotten Grains (%)' => 'rotten_grains',
    'Pest Infestation' => 'pest_infestation_level',
    'Live Insects Present' => 'live_insects_present',
    'Filth Contamination' => 'filth_contamination',
    'Aflatoxin (ppb)' => 'aflatoxin_level',
    'Other Mycotoxins' => 'other_mycotoxins',
    'Odor Assessment' => 'odor_assessment',
    'Color Assessment' => 'color_assessment',
    'Grade Classification' => 'grade_classification',
    'EAGC Compliant' => 'eagc_compliant',
    'Reason for Downgrade' => 'reason_for_downgrade',
    'Posted By' => 'posted_by_name',
    'Posted Email' => 'posted_by_email',
    'Posted Username' => 'posted_by_username',
    'Status' => 'status',
    'Admin Notes' => 'admin_notes',
    'Submission Date' => 'submission_date'
];

foreach ($fields as $label => $key) {
    $value = $row[$key] ?? '';
    if ($value === '' || $value === null) continue;
    if (is_bool($value)) $value = $value ? 'Yes' : 'No';
    $html .= '<div class="gq-detail-item"><div class="label">' . $label . '</div><div class="value">' . htmlspecialchars($value) . '</div></div>';
}

$html .= '</div>';

echo json_encode(['success' => true, 'html' => $html]);
?>