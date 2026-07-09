<?php
// data/grain_quality_dashboard.php - Grain Quality Testing Dashboard
// ─────────────────────────────────────────────────────────────
// FIXES (this version):
// 1) The "View Details" and "Review" action buttons in each table row
//    live inside <form id="bulkForm"> but had no type attribute, so
//    browsers defaulted them to type="submit". Clicking them opened the
//    modal via onclick AND submitted bulkForm at the same time, reloading
//    the page and killing the modal almost immediately. All non-submitting
//    buttons now explicitly declare type="button".
// 2) Bulk export was opening a new tab (form.target = '_blank') instead of
//    downloading a CSV. Routing a Content-Disposition: attachment response
//    into a brand-new tab is unreliable across browsers — many just render
//    the raw CSV text instead of prompting a download. Removed
//    form.target = '_blank' so the export form submits in the current tab;
//    the browser intercepts the attachment response and downloads the file
//    without ever navigating away from the dashboard.
// ─────────────────────────────────────────────────────────────

if (session_status() == PHP_SESSION_NONE) session_start();
include '../admin/includes/config.php';
include '../admin/includes/admin_header.php';

// ── Handle POST actions ──
$action_result = ['success' => false, 'msg' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Update status
    if ($action === 'update_status' && isset($_POST['submission_id'], $_POST['status'])) {
        $id = (int)$_POST['submission_id'];
        $status = in_array($_POST['status'], ['pending','approved','rejected']) ? $_POST['status'] : 'pending';
        $admin_notes = trim($_POST['admin_notes'] ?? '');
        
        $stmt = $con->prepare("UPDATE grain_quality_submissions SET status = ?, admin_notes = ?, processed_at = NOW() WHERE id = ?");
        $stmt->bind_param('ssi', $status, $admin_notes, $id);
        if ($stmt->execute()) {
            $action_result = ['success' => true, 'msg' => 'Status updated successfully'];
        } else {
            $action_result = ['success' => false, 'msg' => 'Failed to update status'];
        }
        $stmt->close();
    }
    
    // Bulk action
    if ($action === 'bulk_action' && isset($_POST['bulk_ids'], $_POST['bulk_status'])) {
        $ids = array_filter(array_map('intval', explode(',', $_POST['bulk_ids'])));
        $bulk_status = in_array($_POST['bulk_status'], ['pending','approved','rejected']) ? $_POST['bulk_status'] : 'pending';
        
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));
            $stmt = $con->prepare("UPDATE grain_quality_submissions SET status = ?, processed_at = NOW() WHERE id IN ($placeholders)");
            $stmt->bind_param('s' . $types, $bulk_status, ...$ids);
            if ($stmt->execute()) {
                $action_result = ['success' => true, 'msg' => count($ids) . ' items updated successfully'];
            } else {
                $action_result = ['success' => false, 'msg' => 'Bulk update failed'];
            }
            $stmt->close();
        }
    }
    
    // Delete submissions
    if ($action === 'delete_submissions' && isset($_POST['bulk_ids'])) {
        $ids = array_filter(array_map('intval', explode(',', $_POST['bulk_ids'])));
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));
            $stmt = $con->prepare("DELETE FROM grain_quality_submissions WHERE id IN ($placeholders)");
            $stmt->bind_param($types, ...$ids);
            if ($stmt->execute()) {
                $action_result = ['success' => true, 'msg' => count($ids) . ' items deleted successfully'];
            } else {
                $action_result = ['success' => false, 'msg' => 'Delete failed'];
            }
            $stmt->close();
        }
    }
    
    // Export
    if ($action === 'export_quality') {
        $format = $_POST['export_format'] ?? 'csv';
        $filter_status = $_POST['filter_status'] ?? '';
        $filter_grade = $_POST['filter_grade'] ?? '';
        $date_from = $_POST['date_from'] ?? '';
        $date_to = $_POST['date_to'] ?? '';
        $selected_ids = isset($_POST['selected_ids']) ? $_POST['selected_ids'] : '';
        
        $where = ['1=1'];
        $params = [];
        $types = '';
        
        if ($filter_status && $filter_status !== 'all') {
            $where[] = "status = ?";
            $params[] = $filter_status;
            $types .= 's';
        }
        if ($filter_grade && $filter_grade !== 'all') {
            $where[] = "grade_classification = ?";
            $params[] = $filter_grade;
            $types .= 's';
        }
        if ($date_from && $date_to) {
            $where[] = "sampling_date BETWEEN ? AND ?";
            $params[] = $date_from;
            $params[] = $date_to;
            $types .= 'ss';
        } elseif ($date_from) {
            $where[] = "sampling_date >= ?";
            $params[] = $date_from;
            $types .= 's';
        } elseif ($date_to) {
            $where[] = "sampling_date <= ?";
            $params[] = $date_to;
            $types .= 's';
        }
        
        if (!empty($selected_ids)) {
            $id_array = array_filter(array_map('intval', explode(',', $selected_ids)));
            if (!empty($id_array)) {
                $placeholders = implode(',', array_fill(0, count($id_array), '?'));
                $where[] = "id IN ($placeholders)";
                foreach ($id_array as $id) {
                    $params[] = $id;
                    $types .= 'i';
                }
            }
        }
        
        $sql = "SELECT 
            id, submission_uuid, sample_id, sampling_date, location, warehouse,
            moisture, test_weight, uniformity_grade, broken_grains, foreign_matter,
            impurities, insect_damaged, discolored, shrivelled, moldy_grains, rotten_grains,
            pest_infestation_level, live_insects_present, filth_contamination,
            aflatoxin_level, other_mycotoxins,
            odor_assessment, color_assessment, grade_classification, eagc_compliant,
            reason_for_downgrade,
            posted_by_name, posted_by_email, posted_by_username,
            status, submission_date
            FROM grain_quality_submissions 
            WHERE " . implode(' AND ', $where) . " 
            ORDER BY sampling_date DESC";
        
        $stmt = $con->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
        
        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="grain_quality_' . date('Y-m-d') . '.csv"');
            $out = fopen('php://output', 'w');
            fputs($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'ID', 'UUID', 'Sample ID', 'Sampling Date', 'Location', 'Warehouse',
                'Moisture (%)', 'Test Weight (kg/hl)', 'Uniformity Grade',
                'Broken Grains (%)', 'Foreign Matter (%)', 'Impurities (%)',
                'Insect Damaged (%)', 'Discolored (%)', 'Shrivelled (%)',
                'Moldy Grains (%)', 'Rotten Grains (%)',
                'Pest Infestation', 'Live Insects', 'Filth Contamination',
                'Aflatoxin (ppb)', 'Other Mycotoxins',
                'Odor Assessment', 'Color Assessment', 'Grade', 'EAGC Compliant',
                'Reason for Downgrade',
                'Posted By', 'Posted Email', 'Posted Username',
                'Status', 'Submission Date'
            ]);
            foreach ($data as $row) {
                fputcsv($out, [
                    $row['id'], $row['submission_uuid'], $row['sample_id'],
                    $row['sampling_date'], $row['location'], $row['warehouse'],
                    $row['moisture'], $row['test_weight'], $row['uniformity_grade'],
                    $row['broken_grains'], $row['foreign_matter'], $row['impurities'],
                    $row['insect_damaged'], $row['discolored'], $row['shrivelled'],
                    $row['moldy_grains'], $row['rotten_grains'],
                    $row['pest_infestation_level'],
                    $row['live_insects_present'] ? 'Yes' : 'No',
                    $row['filth_contamination'] ? 'Yes' : 'No',
                    $row['aflatoxin_level'], $row['other_mycotoxins'],
                    $row['odor_assessment'], $row['color_assessment'],
                    $row['grade_classification'],
                    $row['eagc_compliant'] ? 'Yes' : 'No',
                    $row['reason_for_downgrade'],
                    $row['posted_by_name'], $row['posted_by_email'],
                    $row['posted_by_username'],
                    $row['status'], $row['submission_date']
                ]);
            }
            fclose($out);
            exit;
        }
    }
}

// ── GET filters ──
$filter_status = $_GET['status'] ?? 'all';
$filter_grade = $_GET['grade'] ?? 'all';
$filter_enumerator = $_GET['enumerator'] ?? 'all';
$search_query = trim($_GET['search'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort_by = $_GET['sort'] ?? 'sampling_date';
$sort_dir = ($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// ── Build WHERE clause ──
$where = ['1=1'];
$params = [];
$types = '';

if ($filter_status !== 'all') {
    $where[] = "status = ?";
    $params[] = $filter_status;
    $types .= 's';
}
if ($filter_grade !== 'all') {
    $where[] = "grade_classification = ?";
    $params[] = $filter_grade;
    $types .= 's';
}
if ($filter_enumerator !== 'all') {
    $where[] = "posted_by_id = ?";
    $params[] = (int)$filter_enumerator;
    $types .= 'i';
}
if ($search_query) {
    $where[] = "(sample_id LIKE ? OR location LIKE ? OR warehouse LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}
if ($date_from && $date_to) {
    $where[] = "sampling_date BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
    $types .= 'ss';
} elseif ($date_from) {
    $where[] = "sampling_date >= ?";
    $params[] = $date_from;
    $types .= 's';
} elseif ($date_to) {
    $where[] = "sampling_date <= ?";
    $params[] = $date_to;
    $types .= 's';
}

// ── Get total count ──
$count_sql = "SELECT COUNT(*) as total FROM grain_quality_submissions WHERE " . implode(' AND ', $where);
$count_stmt = $con->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'] ?? 0;
$count_stmt->close();
$total_pages = ceil($total_records / $limit);

// ── Get submissions ──
$allowed_sort = ['id','sample_id','location','moisture','test_weight','grade_classification','status','sampling_date'];
$sort_col = in_array($sort_by, $allowed_sort) ? $sort_by : 'sampling_date';
$dir = $sort_dir === 'ASC' ? 'ASC' : 'DESC';

$sql = "SELECT * FROM grain_quality_submissions 
        WHERE " . implode(' AND ', $where) . " 
        ORDER BY $sort_col $dir 
        LIMIT ? OFFSET ?";
$stmt = $con->prepare($sql);
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$submissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Get stats ──
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    AVG(moisture) as avg_moisture,
    AVG(test_weight) as avg_test_weight,
    AVG(broken_grains) as avg_broken_grains,
    AVG(foreign_matter) as avg_foreign_matter,
    AVG(aflatoxin_level) as avg_aflatoxin,
    SUM(CASE WHEN eagc_compliant = 1 THEN 1 ELSE 0 END) as compliant_count,
    COUNT(DISTINCT location) as unique_locations,
    COUNT(DISTINCT grade_classification) as unique_grades,
    COUNT(DISTINCT posted_by_id) as unique_enumerators
FROM grain_quality_submissions";
$stats_result = $con->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// ── Get distinct values for filters ──
$grades = $con->query("SELECT DISTINCT grade_classification FROM grain_quality_submissions WHERE grade_classification IS NOT NULL ORDER BY grade_classification")->fetch_all(MYSQLI_ASSOC);
$enumerators = $con->query("SELECT DISTINCT posted_by_id, posted_by_name, posted_by_username FROM grain_quality_submissions WHERE posted_by_id IS NOT NULL ORDER BY posted_by_name")->fetch_all(MYSQLI_ASSOC);

// ── Helper functions ──
function getStatusBadge($status) {
    $map = [
        'pending' => ['class' => 'pending', 'label' => 'Pending'],
        'approved' => ['class' => 'approved', 'label' => 'Approved'],
        'rejected' => ['class' => 'rejected', 'label' => 'Rejected']
    ];
    $info = $map[$status] ?? ['class' => 'unknown', 'label' => $status];
    return '<span class="gq-badge gq-badge-' . $info['class'] . '">' . $info['label'] . '</span>';
}

function getMoistureBadge($moisture) {
    if ($moisture === null) return '<span class="gq-badge gq-badge-unknown">—</span>';
    if ($moisture >= 12 && $moisture <= 14) {
        return '<span class="gq-badge gq-badge-good">✓ ' . $moisture . '%</span>';
    } elseif ($moisture >= 10 && $moisture <= 16) {
        return '<span class="gq-badge gq-badge-warning">' . $moisture . '%</span>';
    } else {
        return '<span class="gq-badge gq-badge-danger">' . $moisture . '%</span>';
    }
}

function getComplianceBadge($compliant) {
    if ($compliant) {
        return '<span class="gq-badge gq-badge-compliant">✓ Compliant</span>';
    } else {
        return '<span class="gq-badge gq-badge-noncompliant">✗ Non-Compliant</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Grain Quality Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/>
<style>
/* ── Root variables ── */
:root {
    --gq-primary: #800000;
    --gq-primary-dk: #660000;
    --gq-green: #00450d;
    --gq-bg: #f9fafb;
    --gq-card: #ffffff;
    --gq-border: #e5e7eb;
    --gq-text: #1f2937;
    --gq-muted: #6b7280;
    --gq-radius: .625rem;
    --gq-warning: #d97706;
    --gq-success: #16a34a;
    --gq-danger: #dc2626;
    --gq-info: #0891b2;
}

/* ── Page background ── */
.gq-wrap {
    background: radial-gradient(circle at top left, rgba(0,69,13,.04), transparent 50%),
                radial-gradient(circle at bottom right, rgba(128,0,0,.04), transparent 50%);
    min-height: 100vh;
    padding: 0 0 40px;
    font-family: 'Segoe UI', system-ui, sans-serif;
    color: var(--gq-text);
}

/* ── Header ── */
.gq-page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 4px;
}
.gq-page-header h1 { font-size: 1.5rem; font-weight: 700; color: var(--gq-primary); margin: 0; }
.gq-page-header p  { font-size: .875rem; color: var(--gq-muted); margin: 4px 0 0; }
.gq-accent-bar { height: 3px; background: linear-gradient(90deg, var(--gq-green) 0%, var(--gq-primary) 50%, var(--gq-green) 100%); border-radius: 99px; margin: 10px 0 20px; }

/* ── Stat cards ── */
.gq-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 20px; }
.gq-stat-card {
    background: var(--gq-card);
    border-radius: var(--gq-radius);
    padding: 14px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
    border-left: 4px solid var(--gq-primary);
    transition: transform .2s, box-shadow .2s;
}
.gq-stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.1); }
.gq-stat-card.stat-pending   { border-left-color: var(--gq-warning); }
.gq-stat-card.stat-approved { border-left-color: var(--gq-success); }
.gq-stat-card.stat-rejected { border-left-color: var(--gq-danger); }
.gq-stat-card.stat-compliant { border-left-color: var(--gq-info); }
.gq-stat-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .06em; color: var(--gq-muted); margin-bottom: 4px; }
.gq-stat-value { font-size: 1.4rem; font-weight: 700; color: var(--gq-text); }
.gq-stat-icon  { font-size: 2rem; opacity: .25; }

/* ── Alert ── */
.gq-alert {
    padding: 10px 14px;
    border-radius: var(--gq-radius);
    font-size: .875rem;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 14px;
    border-left: 4px solid transparent;
}
.gq-alert.success { background: #f0fdf4; color: #15803d; border-left-color: var(--gq-success); }
.gq-alert.danger  { background: #fef2f2; color: #dc2626; border-left-color: var(--gq-danger); }

/* ── Toolbar ── */
.gq-toolbar {
    background: var(--gq-card);
    border-radius: var(--gq-radius);
    padding: 12px 16px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
    margin-bottom: 14px;
}
.gq-toolbar-left  { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.gq-toolbar-right { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }

/* ── Buttons ── */
.gq-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 14px; border-radius: 6px; font-size: .8125rem; font-weight: 500;
    border: 1px solid var(--gq-border); background: white; color: var(--gq-text);
    cursor: pointer; transition: all .2s; white-space: nowrap;
}
.gq-btn:hover { background: #f3f4f6; }
.gq-btn.primary  { background: var(--gq-primary); color: white; border-color: var(--gq-primary); }
.gq-btn.primary:hover { background: var(--gq-primary-dk); }
.gq-btn.success  { background: var(--gq-success); color: white; border-color: var(--gq-success); }
.gq-btn.success:hover { background: #15803d; }
.gq-btn.warning  { background: var(--gq-warning); color: white; border-color: var(--gq-warning); }
.gq-btn.warning:hover { background: #b45309; }
.gq-btn.danger   { background: var(--gq-danger); color: white; border-color: var(--gq-danger); }
.gq-btn.danger:hover { background: #b91c1c; }
.gq-btn.ghost    { background: transparent; border-color: var(--gq-border); color: var(--gq-muted); }
.gq-btn.ghost:hover { background: #f9fafb; color: var(--gq-text); }
.gq-btn:disabled { opacity: .45; cursor: not-allowed; pointer-events: none; }

/* ── Search bar ── */
.gq-search-bar {
    background: var(--gq-card);
    border-radius: var(--gq-radius);
    padding: 10px 14px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
    margin-bottom: 14px;
}
.gq-search-field { position: relative; flex: 1; min-width: 150px; }
.gq-search-field input, .gq-search-field select {
    width: 100%; padding: 6px 10px 6px 32px;
    border: 1px solid var(--gq-border); border-radius: 6px;
    font-size: .8125rem; color: var(--gq-text);
    transition: border-color .2s, box-shadow .2s;
    box-sizing: border-box;
    background: white;
}
.gq-search-field input:focus, .gq-search-field select:focus {
    outline: none; border-color: var(--gq-primary); box-shadow: 0 0 0 3px rgba(128,0,0,.1);
}
.gq-search-icon { position: absolute; left: 8px; top: 50%; transform: translateY(-50%); color: var(--gq-muted); font-size: 1rem; pointer-events: none; }

/* ── Table card ── */
.gq-table-card {
    background: var(--gq-card);
    border-radius: var(--gq-radius);
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
    overflow: hidden;
}
.gq-table-wrap { overflow-x: auto; }
.gq-table { width: 100%; border-collapse: collapse; font-size: .8125rem; }
.gq-table thead tr { background: #f8f9fa; }
.gq-table th {
    padding: 10px 12px; text-align: left;
    font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em;
    color: var(--gq-muted); border-bottom: 2px solid var(--gq-border);
    white-space: nowrap;
}
.gq-table td { padding: 10px 12px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
.gq-table tbody tr:hover { background: #fefaf5; }
.gq-table tbody tr.gq-pending-row { background: #fffbeb; }
.gq-table tbody tr.gq-pending-row:hover { background: #fef3c7 !important; }

/* ── Badges ── */
.gq-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 2px 9px; border-radius: 99px; font-size: .7rem; font-weight: 600;
}
.gq-badge::before { content: ''; width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
.gq-badge-pending    { background: #fef3c7; color: #92400e; }
.gq-badge-pending::before { background: var(--gq-warning); }
.gq-badge-approved  { background: #dcfce7; color: #166534; }
.gq-badge-approved::before { background: var(--gq-success); }
.gq-badge-rejected  { background: #fee2e2; color: #991b1b; }
.gq-badge-rejected::before { background: var(--gq-danger); }
.gq-badge-good      { background: #dcfce7; color: #166534; }
.gq-badge-good::before { background: var(--gq-success); }
.gq-badge-warning   { background: #fef3c7; color: #92400e; }
.gq-badge-warning::before { background: var(--gq-warning); }
.gq-badge-danger    { background: #fee2e2; color: #991b1b; }
.gq-badge-danger::before { background: var(--gq-danger); }
.gq-badge-compliant { background: #dbeafe; color: #1e40af; }
.gq-badge-compliant::before { background: var(--gq-info); }
.gq-badge-noncompliant { background: #fee2e2; color: #991b1b; }
.gq-badge-noncompliant::before { background: var(--gq-danger); }
.gq-badge-unknown   { background: #f3f4f6; color: var(--gq-muted); }
.gq-badge-unknown::before { background: var(--gq-muted); }

/* ── Action buttons ── */
.gq-action-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 6px; border: none; cursor: pointer;
    transition: all .2s; background: #f3f4f6; color: var(--gq-muted);
}
.gq-action-btn:hover { background: #e0f2fe; color: var(--gq-info); }
.gq-action-btn.success:hover { background: #dcfce7; color: var(--gq-success); }

/* ── Pagination ── */
.gq-pagination-bar {
    display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center;
    gap: 12px; padding: 12px 16px; border-top: 1px solid var(--gq-border);
    background: var(--gq-card);
}
.gq-pagination-info { font-size: .8125rem; color: var(--gq-muted); }
.gq-pagination-nav  { display: flex; align-items: center; gap: 4px; }
.gq-pg-btn {
    min-width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;
    border-radius: 6px; font-size: .75rem; border: 1px solid var(--gq-border);
    background: white; color: var(--gq-text); cursor: pointer; transition: all .2s; padding: 0 4px;
}
.gq-pg-btn:hover:not(:disabled):not(.active) { background: #fef3e7; border-color: var(--gq-primary); color: var(--gq-primary); }
.gq-pg-btn.active { background: var(--gq-primary); border-color: var(--gq-primary); color: white; font-weight: 700; }
.gq-pg-btn:disabled { opacity: .35; cursor: not-allowed; }

/* ── Modal ── */
.gq-modal-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,.5);
    z-index: 500; display: none; overflow-y: auto;
}
.gq-modal-backdrop.open { display: block; }
.gq-modal-center { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
.gq-modal-box {
    background: white; border-radius: var(--gq-radius);
    width: 100%; max-width: 600px;
    box-shadow: 0 20px 60px rgba(0,0,0,.2);
}
.gq-modal-box.wide { max-width: 700px; }
.gq-modal-header {
    background: linear-gradient(135deg, var(--gq-primary) 0%, var(--gq-green) 100%);
    padding: 14px 18px; border-radius: var(--gq-radius) var(--gq-radius) 0 0;
    display: flex; align-items: center; justify-content: space-between;
    color: white;
}
.gq-modal-header h3 { margin: 0; font-size: 1rem; font-weight: 600; display: flex; align-items: center; gap: 6px; }
.gq-modal-header button { background: none; border: none; color: rgba(255,255,255,.8); cursor: pointer; font-size: 1.25rem; line-height: 1; padding: 0 8px; }
.gq-modal-header button:hover { color: white; }
.gq-modal-body  { padding: 18px; max-height: 60vh; overflow-y: auto; }
.gq-modal-footer { padding: 14px 18px; border-top: 1px solid var(--gq-border); display: flex; justify-content: flex-end; gap: 8px; }

/* ── Detail view ── */
.gq-detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px 24px;
}
.gq-detail-item {
    display: flex;
    flex-direction: column;
    padding: 6px 0;
    border-bottom: 1px solid #f3f4f6;
}
.gq-detail-item .label {
    font-size: .7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--gq-muted);
}
.gq-detail-item .value {
    font-size: .875rem;
    color: var(--gq-text);
    font-weight: 500;
    margin-top: 2px;
}

/* ── Sortable headers ── */
.gq-th-sort { cursor: pointer; user-select: none; white-space: nowrap; }
.gq-th-sort:hover { color: var(--gq-primary); }
.gq-sort-icon { font-size: .65rem; margin-left: 3px; opacity: .5; vertical-align: middle; }
.gq-th-sort.active-sort { color: var(--gq-primary); }
.gq-th-sort.active-sort .gq-sort-icon { opacity: 1; }

/* ── Responsive ── */
@media (max-width: 768px) {
    .gq-stats { grid-template-columns: repeat(2, 1fr); }
    .gq-detail-grid { grid-template-columns: 1fr; }
    .gq-search-field { min-width: 100%; }
}
</style>
</head>
<body>

<div class="gq-wrap" style="max-width:1400px; margin:0 auto; padding:24px 20px;">

    <!-- ── Page Header ── -->
    <div class="gq-page-header">
        <div>
            <h1><span class="material-symbols-outlined" style="font-size:1.6rem;vertical-align:middle;margin-right:6px;">science</span> Grain Testing & Quality</h1>
            <p>Monitor and manage grain quality testing data from field enumerators</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button type="button" class="gq-btn primary" onclick="location.reload()">
                <span class="material-symbols-outlined">refresh</span> Refresh
            </button>
        </div>
    </div>
    <div class="gq-accent-bar"></div>

    <!-- ── Alert ── -->
    <?php if ($action_result['msg']): ?>
    <div class="gq-alert <?= $action_result['success'] ? 'success' : 'danger' ?>">
        <span class="material-symbols-outlined"><?= $action_result['success'] ? 'check_circle' : 'error' ?></span>
        <?= htmlspecialchars($action_result['msg']) ?>
    </div>
    <?php endif; ?>

    <!-- ── Stat Cards ── -->
    <div class="gq-stats">
        <div class="gq-stat-card">
            <div>
                <div class="gq-stat-label">Total Tests</div>
                <div class="gq-stat-value"><?= number_format($stats['total'] ?? 0) ?></div>
            </div>
            <span class="gq-stat-icon material-symbols-outlined" style="color:var(--gq-primary);">science</span>
        </div>
        <div class="gq-stat-card stat-pending">
            <div>
                <div class="gq-stat-label">Pending Review</div>
                <div class="gq-stat-value" style="color:var(--gq-warning);"><?= number_format($stats['pending'] ?? 0) ?></div>
            </div>
            <span class="gq-stat-icon material-symbols-outlined" style="color:var(--gq-warning);">schedule</span>
        </div>
        <div class="gq-stat-card stat-approved">
            <div>
                <div class="gq-stat-label">Approved</div>
                <div class="gq-stat-value" style="color:var(--gq-success);"><?= number_format($stats['approved'] ?? 0) ?></div>
            </div>
            <span class="gq-stat-icon material-symbols-outlined" style="color:var(--gq-success);">check_circle</span>
        </div>
        <div class="gq-stat-card stat-rejected">
            <div>
                <div class="gq-stat-label">Rejected</div>
                <div class="gq-stat-value" style="color:var(--gq-danger);"><?= number_format($stats['rejected'] ?? 0) ?></div>
            </div>
            <span class="gq-stat-icon material-symbols-outlined" style="color:var(--gq-danger);">cancel</span>
        </div>
        <div class="gq-stat-card stat-compliant">
            <div>
                <div class="gq-stat-label">EAGC Compliant</div>
                <div class="gq-stat-value" style="color:var(--gq-info);"><?= number_format($stats['compliant_count'] ?? 0) ?></div>
            </div>
            <span class="gq-stat-icon material-symbols-outlined" style="color:var(--gq-info);">verified</span>
        </div>
        <div class="gq-stat-card" style="border-left-color:#7c3aed;">
            <div>
                <div class="gq-stat-label">Avg Moisture</div>
                <div class="gq-stat-value"><?= number_format($stats['avg_moisture'] ?? 0, 1) ?>%</div>
            </div>
            <span class="gq-stat-icon material-symbols-outlined" style="color:#7c3aed;">water_drop</span>
        </div>
    </div>


    <!-- ── Toolbar ── -->
    <div class="gq-toolbar">
        <div class="gq-toolbar-left">
            <button type="button" class="gq-btn danger" id="bulkDeleteBtn" disabled onclick="deleteSelected()">
                <span class="material-symbols-outlined">delete</span> Delete
                <span class="gq-badge-count" id="selectedCount" style="background:rgba(0,0,0,.1);color:inherit;padding:0 6px;border-radius:99px;font-size:.7rem;">0</span>
            </button>
            <button type="button" class="gq-btn ghost" onclick="clearAllSelections()">
                <span class="material-symbols-outlined">clear</span> Clear Selected
            </button>
            <button type="button" class="gq-btn success" id="approveBtn" disabled onclick="approveSelected()">
                <span class="material-symbols-outlined">check_circle</span> Approve
            </button>
            <button type="button" class="gq-btn warning" id="rejectBtn" disabled onclick="rejectSelected()">
                <span class="material-symbols-outlined">cancel</span> Reject
            </button>
        </div>
        <div class="gq-toolbar-right">
            <button type="button" class="gq-btn" onclick="exportAll('csv')">
                <span class="material-symbols-outlined">download</span> Export All
            </button>
        </div>
    </div>

    <!-- ── Search ── -->
    <form method="GET" action="" class="gq-search-bar" id="filterForm">
        <div class="gq-search-field">
            <span class="gq-search-icon material-symbols-outlined">search</span>
            <input type="text" name="search" placeholder="Search sample ID, location…" value="<?= htmlspecialchars($search_query) ?>">
        </div>
        <div class="gq-search-field">
            <span class="gq-search-icon material-symbols-outlined">filter_alt</span>
            <select name="status" onchange="this.form.submit()">
                <option value="all">All Status</option>
                <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="approved" <?= $filter_status === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="rejected" <?= $filter_status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
        </div>
        <div class="gq-search-field">
            <span class="gq-search-icon material-symbols-outlined">verified</span>
            <select name="grade" onchange="this.form.submit()">
                <option value="all">All Grades</option>
                <?php foreach ($grades as $g): ?>
                <option value="<?= $g['grade_classification'] ?>" <?= $filter_grade === $g['grade_classification'] ? 'selected' : '' ?>><?= htmlspecialchars($g['grade_classification']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="gq-search-field">
            <span class="gq-search-icon material-symbols-outlined">person</span>
            <select name="enumerator" onchange="this.form.submit()">
                <option value="all">All Enumerators</option>
                <?php foreach ($enumerators as $e): ?>
                <option value="<?= $e['posted_by_id'] ?>" <?= $filter_enumerator == $e['posted_by_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($e['posted_by_name'] ?? $e['posted_by_username']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="gq-search-field" style="min-width:120px;">
            <span class="gq-search-icon material-symbols-outlined">calendar_today</span>
            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" onchange="this.form.submit()">
        </div>
        <div class="gq-search-field" style="min-width:120px;">
            <span class="gq-search-icon material-symbols-outlined">calendar_today</span>
            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" onchange="this.form.submit()">
        </div>
        <button type="submit" class="gq-btn primary">
            <span class="material-symbols-outlined">search</span> Filter
        </button>
        <a href="?" class="gq-btn ghost">
            <span class="material-symbols-outlined">close</span>
        </a>
    </form>

    <!-- ── Table ── -->
    <div class="gq-table-card">
        <form method="POST" action="" id="bulkForm">
            <input type="hidden" name="action" value="bulk_action">
            <input type="hidden" name="bulk_ids" id="bulkIds" value="">
            <input type="hidden" name="bulk_status" id="bulkStatus" value="">

            <div class="gq-table-wrap">
                <table class="gq-table">
                    <thead>
                        <tr>
                            <th style="width:36px;">
                                <input type="checkbox" class="gq-check" id="selectAll" onchange="toggleAllCheckboxes(this)">
                            </th>
                            <?php
                            $sort_cols = [
                                'id' => 'ID',
                                'sample_id' => 'Sample',
                                'location' => 'Location',
                                'moisture' => 'Moisture',
                                'test_weight' => 'Test Wt',
                                'grade_classification' => 'Grade',
                                'status' => 'Status',
                                'sampling_date' => 'Date'
                            ];
                            foreach ($sort_cols as $col => $label):
                                $is_active = ($sort_by === $col);
                                $next_dir = ($is_active && $sort_dir === 'DESC') ? 'asc' : 'desc';
                                $icon = $is_active ? ($sort_dir === 'ASC' ? '↑' : '↓') : '↕';
                            ?>
                            <th class="gq-th-sort <?= $is_active ? 'active-sort' : '' ?>"
                                onclick="gqSortTable('<?= $col ?>', '<?= $next_dir ?>')">
                                <?= $label ?><span class="gq-sort-icon"><?= $icon ?></span>
                            </th>
                            <?php endforeach; ?>
                            <th>Posted By</th>
                            <th style="width:100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($submissions)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-8 text-gray-500">
                                <span class="material-symbols-outlined text-4xl block mb-2 opacity-30">science</span>
                                No grain quality submissions found
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($submissions as $row): ?>
                        <tr class="<?= $row['status'] === 'pending' ? 'gq-pending-row' : '' ?>" data-id="<?= $row['id'] ?>">
                            <td><input type="checkbox" class="gq-check row-checkbox" value="<?= $row['id'] ?>" onchange="updateBulkCount()"></td>
                            <td><span class="font-mono text-sm">#<?= $row['id'] ?></span></td>
                            <td><span class="font-mono text-xs"><?= htmlspecialchars($row['sample_id']) ?></span></td>
                            <td><?= htmlspecialchars($row['location'] ?? '—') ?></td>
                            <td><?= getMoistureBadge($row['moisture']) ?></td>
                            <td><span class="font-mono text-sm"><?= number_format($row['test_weight'] ?? 0, 1) ?></span></td>
                            <td>
                                <span class="gq-badge gq-badge-approved" style="background:#ede9fe;color:#5b21b6;">
                                    <?= htmlspecialchars($row['grade_classification'] ?? '—') ?>
                                </span>
                            </td>
                            <td><?= getStatusBadge($row['status']) ?></td>
                            <td>
                                <div class="text-sm"><?= date('d M Y', strtotime($row['sampling_date'])) ?></div>
                                <div class="text-xs text-gray-400"><?= htmlspecialchars($row['warehouse'] ?? '') ?></div>
                            </td>
                            <td>
                                <div class="text-sm"><?= htmlspecialchars($row['posted_by_name'] ?? 'Unknown') ?></div>
                                <div class="text-xs text-gray-400"><?= htmlspecialchars($row['posted_by_username'] ?? '') ?></div>
                            </td>
                            <td>
                                <div class="flex items-center gap-1">
                                    <!-- FIX: type="button" prevents this from also submitting bulkForm -->
                                    <button type="button" onclick="viewSubmission(<?= $row['id'] ?>)" class="gq-action-btn" title="View Details">
                                        <span class="material-symbols-outlined text-sm">visibility</span>
                                    </button>
                                    <?php if ($row['status'] === 'pending'): ?>
                                    <!-- FIX: type="button" prevents this from also submitting bulkForm -->
                                    <button type="button" onclick="reviewSubmission(<?= $row['id'] ?>)" class="gq-action-btn success" title="Review">
                                        <span class="material-symbols-outlined text-sm">rate_review</span>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <!-- ── Pagination ── -->
        <?php if ($total_pages > 1): ?>
        <div class="gq-pagination-bar">
            <div class="gq-pagination-info">
                Showing <?= $offset + 1 ?> – <?= min($offset + $limit, $total_records) ?> of <?= number_format($total_records) ?> tests
            </div>
            <div class="gq-pagination-nav">
                <button type="button" class="gq-pg-btn" onclick="gqGoToPage(1)" <?= $page <= 1 ? 'disabled' : '' ?>>
                    <span class="material-symbols-outlined text-sm">first_page</span>
                </button>
                <button type="button" class="gq-pg-btn" onclick="gqGoToPage(<?= $page - 1 ?>)" <?= $page <= 1 ? 'disabled' : '' ?>>
                    <span class="material-symbols-outlined text-sm">chevron_left</span>
                </button>
                <?php
                $window = 2;
                $start = max(1, $page - $window);
                $end = min($total_pages, $page + $window);
                if ($start > 1) {
                    echo '<button type="button" class="gq-pg-btn" onclick="gqGoToPage(1)">1</button>';
                    if ($start > 2) echo '<span class="text-gray-400 text-sm px-1">…</span>';
                }
                for ($i = $start; $i <= $end; $i++) {
                    echo '<button type="button" class="gq-pg-btn ' . ($i == $page ? 'active' : '') . '" onclick="gqGoToPage(' . $i . ')">' . $i . '</button>';
                }
                if ($end < $total_pages) {
                    if ($end < $total_pages - 1) echo '<span class="text-gray-400 text-sm px-1">…</span>';
                    echo '<button type="button" class="gq-pg-btn" onclick="gqGoToPage(' . $total_pages . ')">' . $total_pages . '</button>';
                }
                ?>
                <button type="button" class="gq-pg-btn" onclick="gqGoToPage(<?= $page + 1 ?>)" <?= $page >= $total_pages ? 'disabled' : '' ?>>
                    <span class="material-symbols-outlined text-sm">chevron_right</span>
                </button>
                <button type="button" class="gq-pg-btn" onclick="gqGoToPage(<?= $total_pages ?>)" <?= $page >= $total_pages ? 'disabled' : '' ?>>
                    <span class="material-symbols-outlined text-sm">last_page</span>
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ─── MODALS ─── -->

<!-- View Modal -->
<div class="gq-modal-backdrop" id="viewModal">
    <div class="gq-modal-center">
        <div class="gq-modal-box wide" onclick="event.stopPropagation();">
            <div class="gq-modal-header">
                <h3><span class="material-symbols-outlined">description</span> Grain Quality Details</h3>
                <button type="button" onclick="closeViewModal()">✕</button>
            </div>
            <div class="gq-modal-body" id="viewContent">
                <div style="text-align:center;padding:30px;">
                    <span class="material-symbols-outlined" style="font-size:2.5rem;color:var(--gq-muted);animation:spin 1s linear infinite;">hourglass_empty</span>
                    <p style="color:var(--gq-muted);margin-top:8px;">Loading...</p>
                </div>
            </div>
            <div class="gq-modal-footer">
                <button type="button" class="gq-btn ghost" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Review Modal -->
<div class="gq-modal-backdrop" id="reviewModal">
    <div class="gq-modal-center">
        <div class="gq-modal-box" onclick="event.stopPropagation();">
            <div class="gq-modal-header" style="background:linear-gradient(135deg,var(--gq-warning),#b45309);">
                <h3><span class="material-symbols-outlined">rate_review</span> Review Submission</h3>
                <button type="button" onclick="closeReviewModal()">✕</button>
            </div>
            <form method="POST" action="" id="reviewForm">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="submission_id" id="reviewId" value="">
                <div class="gq-modal-body">
                    <div style="margin-bottom:16px;">
                        <label style="font-weight:600;display:block;margin-bottom:4px;">Status</label>
                        <select name="status" style="width:100%;padding:7px 10px;border:1px solid var(--gq-border);border-radius:6px;">
                            <option value="approved">✅ Approve</option>
                            <option value="rejected">❌ Reject</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-weight:600;display:block;margin-bottom:4px;">Admin Notes</label>
                        <textarea name="admin_notes" rows="4" style="width:100%;padding:7px 10px;border:1px solid var(--gq-border);border-radius:6px;font-family:inherit;resize:vertical;" placeholder="Add notes about this submission..."></textarea>
                    </div>
                </div>
                <div class="gq-modal-footer">
                    <button type="button" class="gq-btn ghost" onclick="closeReviewModal()">Cancel</button>
                    <button type="submit" class="gq-btn primary">
                        <span class="material-symbols-outlined">save</span> Submit Review
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="gq-modal-backdrop" id="deleteModal">
    <div class="gq-modal-center">
        <div class="gq-modal-box" onclick="event.stopPropagation();">
            <div class="gq-modal-header" style="background:linear-gradient(135deg,var(--gq-danger),#991b1b);">
                <h3><span class="material-symbols-outlined">warning</span> Confirm Deletion</h3>
                <button type="button" onclick="closeDeleteModal()">✕</button>
            </div>
            <div class="gq-modal-body">
                <p id="deleteModalText" style="font-size:.9rem;color:var(--gq-text);margin-bottom:12px;"></p>
                <div style="background:#fef2f2;border-left:4px solid var(--gq-danger);border-radius:0 6px 6px 0;padding:10px 12px;font-size:.8rem;color:#991b1b;">
                    <span class="material-symbols-outlined" style="font-size:.9rem;vertical-align:middle;">info</span>
                    This action is irreversible.
                </div>
            </div>
            <div class="gq-modal-footer">
                <button type="button" class="gq-btn ghost" onclick="closeDeleteModal()">Cancel</button>
                <button type="button" class="gq-btn danger" id="confirmDeleteBtn">
                    <span class="material-symbols-outlined">delete</span> Delete
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// ── Bulk Selection ──
function updateBulkCount() {
    const checked = document.querySelectorAll('.row-checkbox:checked');
    const count = checked.length;
    document.getElementById('selectedCount').textContent = count;
    document.getElementById('selectAll').checked = count > 0 && 
        count === document.querySelectorAll('.row-checkbox').length;
    
    const isAny = count > 0;
    ['bulkDeleteBtn','approveBtn','rejectBtn'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.disabled = !isAny;
    });
}

function toggleAllCheckboxes(master) {
    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = master.checked);
    updateBulkCount();
}

function clearAllSelections() {
    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
    updateBulkCount();
}

function submitBulkAction(status) {
    const checked = document.querySelectorAll('.row-checkbox:checked');
    if (!checked.length) {
        alert('Please select at least one item.');
        return;
    }
    const ids = Array.from(checked).map(cb => cb.value).join(',');
    if (!confirm(`Update ${checked.length} item(s) to "${status}"?`)) return;
    
    document.getElementById('bulkIds').value = ids;
    document.getElementById('bulkStatus').value = status;
    document.getElementById('bulkForm').submit();
}

function approveSelected() { submitBulkAction('approved'); }
function rejectSelected() { submitBulkAction('rejected'); }

function deleteSelected() {
    const checked = document.querySelectorAll('.row-checkbox:checked');
    if (!checked.length) {
        alert('Select at least one item to delete.');
        return;
    }
    const ids = Array.from(checked).map(cb => cb.value);
    document.getElementById('deleteModalText').innerHTML = 
        `Are you sure you want to delete <strong>${checked.length}</strong> submission(s)?`;
    document.getElementById('confirmDeleteBtn').onclick = function() {
        closeDeleteModal();
        if (!confirm(`Permanently delete ${checked.length} submission(s)?`)) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_submissions">
            <input type="hidden" name="bulk_ids" value="${ids.join(',')}">
        `;
        document.body.appendChild(form);
        form.submit();
    };
    openDeleteModal();
}

// ── Export ──
// FIX: removed form.target = '_blank'. Routing a
// Content-Disposition: attachment response into a brand-new tab is
// unreliable across browsers (some render the raw CSV instead of
// downloading it). Submitting in the current tab lets the browser
// correctly intercept the attachment response and download the file
// without navigating away from the dashboard.
function exportAll(format) {
    if (!confirm('Export ALL grain quality submissions? This may take a moment.')) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="export_quality">
        <input type="hidden" name="export_format" value="${format}">
        <input type="hidden" name="export_all" value="true">
        <input type="hidden" name="filter_status" value="${document.querySelector('select[name="status"]')?.value || 'all'}">
        <input type="hidden" name="filter_grade" value="${document.querySelector('select[name="grade"]')?.value || 'all'}">
        <input type="hidden" name="date_from" value="${document.querySelector('input[name="date_from"]')?.value || ''}">
        <input type="hidden" name="date_to" value="${document.querySelector('input[name="date_to"]')?.value || ''}">
    `;
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

// ── View Submission ──
function viewSubmission(id) {
    if (window.event) {
        window.event.stopPropagation();
    }
    
    const viewContent = document.getElementById('viewContent');
    viewContent.innerHTML = `
        <div style="text-align:center;padding:30px;">
            <span class="material-symbols-outlined" style="font-size:2.5rem;color:var(--gq-muted);animation:spin 1s linear infinite;">hourglass_empty</span>
            <p style="color:var(--gq-muted);margin-top:8px;">Loading grain quality details...</p>
        </div>
    `;
    
    openViewModal();
    
    fetch(`get_grain_quality.php?id=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('viewContent').innerHTML = data.html;
            } else {
                document.getElementById('viewContent').innerHTML = `
                    <div style="text-align:center;padding:30px;color:var(--gq-danger);">
                        <span class="material-symbols-outlined" style="font-size:2.5rem;">error</span>
                        <p>${data.message || 'Failed to load details'}</p>
                    </div>
                `;
            }
        })
        .catch(err => {
            document.getElementById('viewContent').innerHTML = `
                <div style="text-align:center;padding:30px;color:var(--gq-danger);">
                    <span class="material-symbols-outlined" style="font-size:2.5rem;">error</span>
                    <p>Error loading: ${err.message}</p>
                </div>
            `;
        });
}

function reviewSubmission(id) {
    if (window.event) {
        window.event.stopPropagation();
    }
    document.getElementById('reviewId').value = id;
    openReviewModal();
}

// ── Modal Open/Close Functions ──
function openViewModal() {
    document.getElementById('viewModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeViewModal() {
    document.getElementById('viewModal').classList.remove('open');
    document.body.style.overflow = '';
}

function openReviewModal() {
    document.getElementById('reviewModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeReviewModal() {
    document.getElementById('reviewModal').classList.remove('open');
    document.body.style.overflow = '';
}

function openDeleteModal() {
    document.getElementById('deleteModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('open');
    document.body.style.overflow = '';
}

// ── Close modals on overlay click ──
document.addEventListener('DOMContentLoaded', function() {
    const viewModal = document.getElementById('viewModal');
    if (viewModal) {
        viewModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeViewModal();
            }
        });
    }
    
    const reviewModal = document.getElementById('reviewModal');
    if (reviewModal) {
        reviewModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeReviewModal();
            }
        });
    }
    
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });
    }
});

// ── Keyboard shortcuts ──
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (document.getElementById('viewModal').classList.contains('open')) {
            closeViewModal();
        }
        if (document.getElementById('reviewModal').classList.contains('open')) {
            closeReviewModal();
        }
        if (document.getElementById('deleteModal').classList.contains('open')) {
            closeDeleteModal();
        }
    }
});

// ── Pagination ──
function gqGoToPage(page) {
    const url = new URL(window.location);
    url.searchParams.set('page', page);
    window.location.href = url.toString();
}

function gqSortTable(col, dir) {
    const url = new URL(window.location);
    url.searchParams.set('sort', col);
    url.searchParams.set('dir', dir);
    url.searchParams.set('page', 1);
    window.location.href = url.toString();
}

// ── Auto-submit filter on Enter ──
document.querySelector('#filterForm input[type="text"]')?.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') this.form.submit();
});

// ── Spin animation ──
const spinStyle = document.createElement('style');
spinStyle.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
document.head.appendChild(spinStyle);
</script>

<?php include 'user_footer.php'; ?>