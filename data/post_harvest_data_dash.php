<?php
// admin/harvest_dashboard.php - Post-Harvest Data Dashboard
// ─────────────────────────────────────────────────────────────
// FIX (this version): the "View Details" and "Review" action buttons in
// each table row live inside <form id="bulkForm"> but had no type
// attribute, so browsers defaulted them to type="submit". Clicking them
// opened the modal via onclick AND submitted bulkForm at the same time,
// reloading the page and killing the modal almost immediately. Both
// buttons now explicitly declare type="button" so they only run their
// onclick handler.
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
        
        $stmt = $con->prepare("UPDATE harvest_submissions SET status = ?, admin_notes = ?, processed_at = NOW() WHERE id = ?");
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
            $stmt = $con->prepare("UPDATE harvest_submissions SET status = ?, processed_at = NOW() WHERE id IN ($placeholders)");
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
            $stmt = $con->prepare("DELETE FROM harvest_submissions WHERE id IN ($placeholders)");
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
    if ($action === 'export_harvest') {
        $format = $_POST['export_format'] ?? 'csv';
        $filter_status = $_POST['filter_status'] ?? '';
        $filter_crop = $_POST['filter_crop'] ?? '';
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
        if ($filter_crop && $filter_crop !== 'all') {
            $where[] = "crop_type = ?";
            $params[] = $filter_crop;
            $types .= 's';
        }
        if ($date_from && $date_to) {
            $where[] = "submission_date BETWEEN ? AND ?";
            $params[] = $date_from . ' 00:00:00';
            $params[] = $date_to . ' 23:59:59';
            $types .= 'ss';
        } elseif ($date_from) {
            $where[] = "submission_date >= ?";
            $params[] = $date_from . ' 00:00:00';
            $types .= 's';
        } elseif ($date_to) {
            $where[] = "submission_date <= ?";
            $params[] = $date_to . ' 23:59:59';
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
            id, submission_uuid, farmer_id, full_name, gender, age_group,
            contact_details, cooperative, county, sub_county, ward, village,
            crop_type, variety, season, harvest_date,
            total_production, total_production_unit, yield_value,
            qty_stored_kg, qty_lost, quantity_sold_kg, quantity_retained_kg,
            selling_price, market_type, date_of_sale,
            posted_by_name, posted_by_email, posted_by_username,
            status, submission_date
            FROM harvest_submissions 
            WHERE " . implode(' AND ', $where) . " 
            ORDER BY submission_date DESC";
        
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
            header('Content-Disposition: attachment; filename="harvest_data_' . date('Y-m-d') . '.csv"');
            $out = fopen('php://output', 'w');
            fputs($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'ID','UUID','Farmer ID','Full Name','Gender','Age Group',
                'Contact','Cooperative','County','Sub County','Ward','Village',
                'Crop Type','Variety','Season','Harvest Date',
                'Total Production','Unit','Yield',
                'Stored (kg)','Lost (kg)','Sold (kg)','Retained (kg)',
                'Selling Price','Market Type','Date of Sale',
                'Posted By','Posted Email','Posted Username',
                'Status','Submission Date'
            ]);
            foreach ($data as $row) {
                fputcsv($out, [
                    $row['id'], $row['submission_uuid'], $row['farmer_id'],
                    $row['full_name'], $row['gender'], $row['age_group'],
                    $row['contact_details'], $row['cooperative'],
                    $row['county'], $row['sub_county'], $row['ward'], $row['village'],
                    $row['crop_type'], $row['variety'], $row['season'],
                    $row['harvest_date'],
                    $row['total_production'], $row['total_production_unit'],
                    $row['yield_value'],
                    $row['qty_stored_kg'], $row['qty_lost'],
                    $row['quantity_sold_kg'], $row['quantity_retained_kg'],
                    $row['selling_price'], $row['market_type'],
                    $row['date_of_sale'],
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
$filter_crop = $_GET['crop'] ?? 'all';
$filter_enumerator = $_GET['enumerator'] ?? 'all';
$search_query = trim($_GET['search'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort_by = $_GET['sort'] ?? 'submission_date';
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
if ($filter_crop !== 'all') {
    $where[] = "crop_type = ?";
    $params[] = $filter_crop;
    $types .= 's';
}
if ($filter_enumerator !== 'all') {
    $where[] = "posted_by_id = ?";
    $params[] = (int)$filter_enumerator;
    $types .= 'i';
}
if ($search_query) {
    $where[] = "(farmer_id LIKE ? OR full_name LIKE ? OR farmer_id LIKE ? OR crop_type LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ssss';
}
if ($date_from && $date_to) {
    $where[] = "submission_date BETWEEN ? AND ?";
    $params[] = $date_from . ' 00:00:00';
    $params[] = $date_to . ' 23:59:59';
    $types .= 'ss';
} elseif ($date_from) {
    $where[] = "submission_date >= ?";
    $params[] = $date_from . ' 00:00:00';
    $types .= 's';
} elseif ($date_to) {
    $where[] = "submission_date <= ?";
    $params[] = $date_to . ' 23:59:59';
    $types .= 's';
}

// ── Get total count ──
$count_sql = "SELECT COUNT(*) as total FROM harvest_submissions WHERE " . implode(' AND ', $where);
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
$allowed_sort = ['id','farmer_id','full_name','crop_type','total_production','status','submission_date'];
$sort_col = in_array($sort_by, $allowed_sort) ? $sort_by : 'submission_date';
$dir = $sort_dir === 'ASC' ? 'ASC' : 'DESC';

$sql = "SELECT * FROM harvest_submissions 
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
    SUM(total_production) as total_production_kg,
    SUM(qty_stored_kg) as total_stored,
    SUM(qty_lost) as total_lost,
    SUM(quantity_sold_kg) as total_sold,
    AVG(yield_value) as avg_yield,
    COUNT(DISTINCT farmer_id) as unique_farmers,
    COUNT(DISTINCT crop_type) as unique_crops,
    COUNT(DISTINCT posted_by_id) as unique_enumerators
FROM harvest_submissions";
$stats_result = $con->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// ── Get distinct values for filters ──
$crops = $con->query("SELECT DISTINCT crop_type FROM harvest_submissions ORDER BY crop_type")->fetch_all(MYSQLI_ASSOC);
$enumerators = $con->query("SELECT DISTINCT posted_by_id, posted_by_name, posted_by_username FROM harvest_submissions WHERE posted_by_id IS NOT NULL ORDER BY posted_by_name")->fetch_all(MYSQLI_ASSOC);

// ── Helper functions ──
function getStatusBadge($status) {
    $map = [
        'pending' => ['class' => 'pending', 'label' => 'Pending'],
        'approved' => ['class' => 'approved', 'label' => 'Approved'],
        'rejected' => ['class' => 'rejected', 'label' => 'Rejected']
    ];
    $info = $map[$status] ?? ['class' => 'unknown', 'label' => $status];
    return '<span class="hs-badge hs-badge-' . $info['class'] . '">' . $info['label'] . '</span>';
}

function getCropIcon($crop) {
    $icons = [
        'Maize' => '🌽',
        'Beans' => '🫘',
        'Rice' => '🌾',
        'Wheat' => '🌾',
        'Sorghum' => '🌾',
        'Millet' => '🌾',
        'Cassava' => '🌱',
        'Sweet Potatoes' => '🍠',
        'Irish Potatoes' => '🥔',
        'Tomatoes' => '🍅',
        'Onions' => '🧅',
        'Cabbage' => '🥬',
        'Kale' => '🥬',
        'Spinach' => '🥬',
        'Coffee' => '☕',
        'Tea' => '🍵',
        'Cotton' => '🌿',
        'Sugarcane' => '🌿',
        'Sunflower' => '🌻'
    ];
    return $icons[$crop] ?? '🌱';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Post-Harvest Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/>
<style>
/* ── Root variables ── */
:root {
    --hs-primary: #800000;
    --hs-primary-dk: #660000;
    --hs-green: #00450d;
    --hs-bg: #f9fafb;
    --hs-card: #ffffff;
    --hs-border: #e5e7eb;
    --hs-text: #1f2937;
    --hs-muted: #6b7280;
    --hs-radius: .625rem;
    --hs-warning: #d97706;
    --hs-success: #16a34a;
    --hs-danger: #dc2626;
    --hs-info: #0891b2;
}

/* ── Page background ── */
.hs-wrap {
    background: radial-gradient(circle at top left, rgba(0,69,13,.04), transparent 50%),
                radial-gradient(circle at bottom right, rgba(128,0,0,.04), transparent 50%);
    min-height: 100vh;
    padding: 0 0 40px;
    font-family: 'Segoe UI', system-ui, sans-serif;
    color: var(--hs-text);
}

/* ── Header ── */
.hs-page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 4px;
}
.hs-page-header h1 { font-size: 1.5rem; font-weight: 700; color: var(--hs-primary); margin: 0; }
.hs-page-header p  { font-size: .875rem; color: var(--hs-muted); margin: 4px 0 0; }
.hs-accent-bar { height: 3px; background: linear-gradient(90deg, var(--hs-green) 0%, var(--hs-primary) 50%, var(--hs-green) 100%); border-radius: 99px; margin: 10px 0 20px; }

/* ── Stat cards ── */
.hs-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 20px; }
.hs-stat-card {
    background: var(--hs-card);
    border-radius: var(--hs-radius);
    padding: 14px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
    border-left: 4px solid var(--hs-primary);
    transition: transform .2s, box-shadow .2s;
}
.hs-stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.1); }
.hs-stat-card.stat-pending   { border-left-color: var(--hs-warning); }
.hs-stat-card.stat-approved { border-left-color: var(--hs-success); }
.hs-stat-card.stat-rejected { border-left-color: var(--hs-danger); }
.hs-stat-card.stat-farmers  { border-left-color: var(--hs-info); }
.hs-stat-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .06em; color: var(--hs-muted); margin-bottom: 4px; }
.hs-stat-value { font-size: 1.4rem; font-weight: 700; color: var(--hs-text); }
.hs-stat-icon  { font-size: 2rem; opacity: .25; }

/* ── Alert ── */
.hs-alert {
    padding: 10px 14px;
    border-radius: var(--hs-radius);
    font-size: .875rem;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 14px;
    border-left: 4px solid transparent;
}
.hs-alert.success { background: #f0fdf4; color: #15803d; border-left-color: var(--hs-success); }
.hs-alert.danger  { background: #fef2f2; color: #dc2626; border-left-color: var(--hs-danger); }
.hs-alert.info    { background: #eff6ff; color: #1d4ed8; border-left-color: var(--hs-info); }

/* ── Toolbar ── */
.hs-toolbar {
    background: var(--hs-card);
    border-radius: var(--hs-radius);
    padding: 12px 16px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
    margin-bottom: 14px;
}
.hs-toolbar-left  { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.hs-toolbar-right { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }

/* ── Buttons ── */
.hs-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 14px; border-radius: 6px; font-size: .8125rem; font-weight: 500;
    border: 1px solid var(--hs-border); background: white; color: var(--hs-text);
    cursor: pointer; transition: all .2s; white-space: nowrap;
}
.hs-btn:hover { background: #f3f4f6; }
.hs-btn.primary  { background: var(--hs-primary); color: white; border-color: var(--hs-primary); }
.hs-btn.primary:hover { background: var(--hs-primary-dk); }
.hs-btn.success  { background: var(--hs-success); color: white; border-color: var(--hs-success); }
.hs-btn.success:hover { background: #15803d; }
.hs-btn.info     { background: var(--hs-info); color: white; border-color: var(--hs-info); }
.hs-btn.info:hover { background: #0e7490; }
.hs-btn.warning  { background: var(--hs-warning); color: white; border-color: var(--hs-warning); }
.hs-btn.warning:hover { background: #b45309; }
.hs-btn.danger   { background: var(--hs-danger); color: white; border-color: var(--hs-danger); }
.hs-btn.danger:hover { background: #b91c1c; }
.hs-btn.ghost    { background: transparent; border-color: var(--hs-border); color: var(--hs-muted); }
.hs-btn.ghost:hover { background: #f9fafb; color: var(--hs-text); }
.hs-btn:disabled { opacity: .45; cursor: not-allowed; pointer-events: none; }

/* ── Search bar ── */
.hs-search-bar {
    background: var(--hs-card);
    border-radius: var(--hs-radius);
    padding: 10px 14px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
    margin-bottom: 14px;
}
.hs-search-field { position: relative; flex: 1; min-width: 150px; }
.hs-search-field input, .hs-search-field select {
    width: 100%; padding: 6px 10px 6px 32px;
    border: 1px solid var(--hs-border); border-radius: 6px;
    font-size: .8125rem; color: var(--hs-text);
    transition: border-color .2s, box-shadow .2s;
    box-sizing: border-box;
    background: white;
}
.hs-search-field input:focus, .hs-search-field select:focus {
    outline: none; border-color: var(--hs-primary); box-shadow: 0 0 0 3px rgba(128,0,0,.1);
}
.hs-search-icon { position: absolute; left: 8px; top: 50%; transform: translateY(-50%); color: var(--hs-muted); font-size: 1rem; pointer-events: none; }
.hs-search-field select { padding-left: 32px; }

/* ── Table card ── */
.hs-table-card {
    background: var(--hs-card);
    border-radius: var(--hs-radius);
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
    overflow: hidden;
}
.hs-table-wrap { overflow-x: auto; }
.hs-table { width: 100%; border-collapse: collapse; font-size: .8125rem; }
.hs-table thead tr { background: #f8f9fa; }
.hs-table th {
    padding: 10px 12px; text-align: left;
    font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em;
    color: var(--hs-muted); border-bottom: 2px solid var(--hs-border);
    white-space: nowrap;
}
.hs-table td { padding: 10px 12px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
.hs-table tbody tr:hover { background: #fefaf5; }
.hs-table tbody tr.hs-pending-row { background: #fffbeb; }
.hs-table tbody tr.hs-pending-row:hover { background: #fef3c7 !important; }

/* ── Status badges ── */
.hs-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 2px 9px; border-radius: 99px; font-size: .7rem; font-weight: 600;
}
.hs-badge::before { content: ''; width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
.hs-badge-pending    { background: #fef3c7; color: #92400e; }
.hs-badge-pending::before { background: var(--hs-warning); }
.hs-badge-approved  { background: #dcfce7; color: #166534; }
.hs-badge-approved::before { background: var(--hs-success); }
.hs-badge-rejected  { background: #fee2e2; color: #991b1b; }
.hs-badge-rejected::before { background: var(--hs-danger); }
.hs-badge-unknown   { background: #f3f4f6; color: var(--hs-muted); }
.hs-badge-unknown::before { background: var(--hs-muted); }

/* ── Action buttons ── */
.hs-action-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 6px; border: none; cursor: pointer;
    transition: all .2s; background: #f3f4f6; color: var(--hs-muted);
}
.hs-action-btn:hover { background: #e0f2fe; color: var(--hs-info); }
.hs-action-btn.success:hover { background: #dcfce7; color: var(--hs-success); }

/* ── Pagination ── */
.hs-pagination-bar {
    display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center;
    gap: 12px; padding: 12px 16px; border-top: 1px solid var(--hs-border);
    background: var(--hs-card);
}
.hs-pagination-info { font-size: .8125rem; color: var(--hs-muted); }
.hs-pagination-nav  { display: flex; align-items: center; gap: 4px; }
.hs-pg-btn {
    min-width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;
    border-radius: 6px; font-size: .75rem; border: 1px solid var(--hs-border);
    background: white; color: var(--hs-text); cursor: pointer; transition: all .2s; padding: 0 4px;
}
.hs-pg-btn:hover:not(:disabled):not(.active) { background: #fef3e7; border-color: var(--hs-primary); color: var(--hs-primary); }
.hs-pg-btn.active { background: var(--hs-primary); border-color: var(--hs-primary); color: white; font-weight: 700; }
.hs-pg-btn:disabled { opacity: .35; cursor: not-allowed; }

/* ── Modal ── */
.hs-modal-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,.5);
    z-index: 500; display: none; overflow-y: auto;
}
.hs-modal-backdrop.open { display: block; }
.hs-modal-center { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
.hs-modal-box {
    background: white; border-radius: var(--hs-radius);
    width: 100%; max-width: 600px;
    box-shadow: 0 20px 60px rgba(0,0,0,.2);
}
.hs-modal-box.wide { max-width: 700px; }
.hs-modal-header {
    background: linear-gradient(135deg, var(--hs-primary) 0%, var(--hs-green) 100%);
    padding: 14px 18px; border-radius: var(--hs-radius) var(--hs-radius) 0 0;
    display: flex; align-items: center; justify-content: space-between;
    color: white;
}
.hs-modal-header h3 { margin: 0; font-size: 1rem; font-weight: 600; display: flex; align-items: center; gap: 6px; }
.hs-modal-header button { background: none; border: none; color: rgba(255,255,255,.8); cursor: pointer; font-size: 1.25rem; line-height: 1; padding: 0 8px; }
.hs-modal-header button:hover { color: white; }
.hs-modal-body  { padding: 18px; max-height: 60vh; overflow-y: auto; }
.hs-modal-footer { padding: 14px 18px; border-top: 1px solid var(--hs-border); display: flex; justify-content: flex-end; gap: 8px; }

/* ── Detail view ── */
.hs-detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px 24px;
}
.hs-detail-item {
    display: flex;
    flex-direction: column;
    padding: 6px 0;
    border-bottom: 1px solid #f3f4f6;
}
.hs-detail-item .label {
    font-size: .7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--hs-muted);
}
.hs-detail-item .value {
    font-size: .875rem;
    color: var(--hs-text);
    font-weight: 500;
    margin-top: 2px;
}

/* ── Sortable headers ── */
.hs-th-sort { cursor: pointer; user-select: none; white-space: nowrap; }
.hs-th-sort:hover { color: var(--hs-primary); }
.hs-sort-icon { font-size: .65rem; margin-left: 3px; opacity: .5; vertical-align: middle; }
.hs-th-sort.active-sort { color: var(--hs-primary); }
.hs-th-sort.active-sort .hs-sort-icon { opacity: 1; }

/* ── Responsive ── */
@media (max-width: 768px) {
    .hs-stats { grid-template-columns: repeat(2, 1fr); }
    .hs-detail-grid { grid-template-columns: 1fr; }
    .hs-search-field { min-width: 100%; }
}
</style>
</head>
<body>

<div class="hs-wrap" style="max-width:1400px; margin:0 auto; padding:24px 20px;">

    <!-- ── Page Header ── -->
    <div class="hs-page-header">
        <div>
            <h1><span class="material-symbols-outlined" style="font-size:1.6rem;vertical-align:middle;margin-right:6px;">grass</span> Post-Harvest Dashboard</h1>
            <p>Monitor and manage harvest data submissions from field enumerators</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button type="button" class="hs-btn primary" onclick="location.reload()">
                <span class="material-symbols-outlined">refresh</span> Refresh
            </button>
        </div>
    </div>
    <div class="hs-accent-bar"></div>

    <!-- ── Alert ── -->
    <?php if ($action_result['msg']): ?>
    <div class="hs-alert <?= $action_result['success'] ? 'success' : 'danger' ?>">
        <span class="material-symbols-outlined"><?= $action_result['success'] ? 'check_circle' : 'error' ?></span>
        <?= htmlspecialchars($action_result['msg']) ?>
    </div>
    <?php endif; ?>

    <!-- ── Stat Cards ── -->
    <div class="hs-stats">
        <div class="hs-stat-card">
            <div>
                <div class="hs-stat-label">Total Submissions</div>
                <div class="hs-stat-value"><?= number_format($stats['total'] ?? 0) ?></div>
            </div>
            <span class="hs-stat-icon material-symbols-outlined" style="color:var(--hs-primary);">inbox</span>
        </div>
        <div class="hs-stat-card stat-pending">
            <div>
                <div class="hs-stat-label">Pending Review</div>
                <div class="hs-stat-value" style="color:var(--hs-warning);"><?= number_format($stats['pending'] ?? 0) ?></div>
            </div>
            <span class="hs-stat-icon material-symbols-outlined" style="color:var(--hs-warning);">schedule</span>
        </div>
        <div class="hs-stat-card stat-approved">
            <div>
                <div class="hs-stat-label">Approved</div>
                <div class="hs-stat-value" style="color:var(--hs-success);"><?= number_format($stats['approved'] ?? 0) ?></div>
            </div>
            <span class="hs-stat-icon material-symbols-outlined" style="color:var(--hs-success);">check_circle</span>
        </div>
        <div class="hs-stat-card stat-rejected">
            <div>
                <div class="hs-stat-label">Rejected</div>
                <div class="hs-stat-value" style="color:var(--hs-danger);"><?= number_format($stats['rejected'] ?? 0) ?></div>
            </div>
            <span class="hs-stat-icon material-symbols-outlined" style="color:var(--hs-danger);">cancel</span>
        </div>
        <div class="hs-stat-card stat-farmers">
            <div>
                <div class="hs-stat-label">Unique Farmers</div>
                <div class="hs-stat-value" style="color:var(--hs-info);"><?= number_format($stats['unique_farmers'] ?? 0) ?></div>
            </div>
            <span class="hs-stat-icon material-symbols-outlined" style="color:var(--hs-info);">people</span>
        </div>
        <div class="hs-stat-card" style="border-left-color:#7c3aed;">
            <div>
                <div class="hs-stat-label">Total Production</div>
                <div class="hs-stat-value"><?= number_format($stats['total_production_kg'] ?? 0) ?> kg</div>
            </div>
            <span class="hs-stat-icon material-symbols-outlined" style="color:#7c3aed;">production_quantity_limits</span>
        </div>
    </div>



    <!-- ── Toolbar ── -->
    <div class="hs-toolbar">
        <div class="hs-toolbar-left">
            <button type="button" class="hs-btn danger" id="bulkDeleteBtn" disabled onclick="deleteSelected()">
                <span class="material-symbols-outlined">delete</span> Delete
                <span class="hs-badge-count" id="selectedCount" style="background:rgba(0,0,0,.1);color:inherit;">0</span>
            </button>
            <button type="button" class="hs-btn ghost" onclick="clearAllSelections()">
                <span class="material-symbols-outlined">clear</span> Clear Selected
            </button>
            <button type="button" class="hs-btn success" id="approveBtn" disabled onclick="approveSelected()">
                <span class="material-symbols-outlined">check_circle</span> Approve
            </button>
            <button type="button" class="hs-btn warning" id="rejectBtn" disabled onclick="rejectSelected()">
                <span class="material-symbols-outlined">cancel</span> Reject
            </button>
        </div>
        <div class="hs-toolbar-right">
            <button type="button" class="hs-btn" onclick="exportAll('csv')">
                <span class="material-symbols-outlined">download</span> Export All
            </button>
        </div>
    </div>

    <!-- ── Search ── -->
    <form method="GET" action="" class="hs-search-bar" id="filterForm">
        <div class="hs-search-field">
            <span class="hs-search-icon material-symbols-outlined">search</span>
            <input type="text" name="search" placeholder="Search farmer, crop, ID…" value="<?= htmlspecialchars($search_query) ?>">
        </div>
        <div class="hs-search-field">
            <span class="hs-search-icon material-symbols-outlined">filter_alt</span>
            <select name="status" onchange="this.form.submit()">
                <option value="all">All Status</option>
                <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="approved" <?= $filter_status === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="rejected" <?= $filter_status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
        </div>
        <div class="hs-search-field">
            <span class="hs-search-icon material-symbols-outlined">grass</span>
            <select name="crop" onchange="this.form.submit()">
                <option value="all">All Crops</option>
                <?php foreach ($crops as $c): ?>
                <option value="<?= $c['crop_type'] ?>" <?= $filter_crop === $c['crop_type'] ? 'selected' : '' ?>><?= htmlspecialchars($c['crop_type']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="hs-search-field">
            <span class="hs-search-icon material-symbols-outlined">person</span>
            <select name="enumerator" onchange="this.form.submit()">
                <option value="all">All Enumerators</option>
                <?php foreach ($enumerators as $e): ?>
                <option value="<?= $e['posted_by_id'] ?>" <?= $filter_enumerator == $e['posted_by_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($e['posted_by_name'] ?? $e['posted_by_username']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="hs-search-field" style="min-width:120px;">
            <span class="hs-search-icon material-symbols-outlined">calendar_today</span>
            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" onchange="this.form.submit()">
        </div>
        <div class="hs-search-field" style="min-width:120px;">
            <span class="hs-search-icon material-symbols-outlined">calendar_today</span>
            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" onchange="this.form.submit()">
        </div>
        <button type="submit" class="hs-btn primary">
            <span class="material-symbols-outlined">search</span> Filter
        </button>
        <a href="?" class="hs-btn ghost">
            <span class="material-symbols-outlined">close</span>
        </a>
    </form>

    <!-- ── Table ── -->
    <div class="hs-table-card">
        <form method="POST" action="" id="bulkForm">
            <input type="hidden" name="action" value="bulk_action">
            <input type="hidden" name="bulk_ids" id="bulkIds" value="">
            <input type="hidden" name="bulk_status" id="bulkStatus" value="">

            <div class="hs-table-wrap">
                <table class="hs-table" id="submissionsTable">
                    <thead>
                        <tr>
                            <th style="width:36px;">
                                <input type="checkbox" class="hs-check" id="selectAll" onchange="toggleAllCheckboxes(this)">
                            </th>
                            <?php
                            $sort_cols = [
                                'id' => 'ID',
                                'farmer_id' => 'Farmer ID',
                                'full_name' => 'Farmer',
                                'crop_type' => 'Crop',
                                'total_production' => 'Production',
                                'status' => 'Status',
                                'submission_date' => 'Date'
                            ];
                            foreach ($sort_cols as $col => $label):
                                $is_active = ($sort_by === $col);
                                $next_dir = ($is_active && $sort_dir === 'DESC') ? 'asc' : 'desc';
                                $icon = $is_active ? ($sort_dir === 'ASC' ? '↑' : '↓') : '↕';
                            ?>
                            <th class="hs-th-sort <?= $is_active ? 'active-sort' : '' ?>"
                                onclick="hsSortTable('<?= $col ?>', '<?= $next_dir ?>')">
                                <?= $label ?><span class="hs-sort-icon"><?= $icon ?></span>
                            </th>
                            <?php endforeach; ?>
                            <th>Posted By</th>
                            <th style="width:100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($submissions)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-8 text-gray-500">
                                <span class="material-symbols-outlined text-4xl block mb-2 opacity-30">inbox</span>
                                No harvest submissions found
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($submissions as $row): ?>
                        <tr class="<?= $row['status'] === 'pending' ? 'hs-pending-row' : '' ?>" data-id="<?= $row['id'] ?>">
                            <td><input type="checkbox" class="hs-check row-checkbox" value="<?= $row['id'] ?>" onchange="updateBulkCount()"></td>
                            <td><span class="font-mono text-sm">#<?= $row['id'] ?></span></td>
                            <td><span class="font-mono text-xs"><?= htmlspecialchars($row['farmer_id']) ?></span></td>
                            <td>
                                <div class="font-medium text-sm"><?= htmlspecialchars($row['full_name']) ?></div>
                                <div class="text-xs text-gray-400"><?= htmlspecialchars($row['village'] ?? '') ?></div>
                            </td>
                            <td>
                                <span style="font-size:1.1rem;"><?= getCropIcon($row['crop_type']) ?></span>
                                <?= htmlspecialchars($row['crop_type']) ?>
                                <?php if ($row['variety']): ?>
                                <div class="text-xs text-gray-400"><?= htmlspecialchars($row['variety']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?= number_format($row['total_production'] ?? 0) ?> <?= $row['total_production_unit'] ?? 'kg' ?></div>
                                <div class="text-xs text-gray-400">Yield: <?= number_format($row['yield_value'] ?? 0) ?></div>
                            </td>
                            <td><?= getStatusBadge($row['status']) ?></td>
                            <td>
                                <div class="text-sm"><?= date('d M Y', strtotime($row['submission_date'])) ?></div>
                                <div class="text-xs text-gray-400"><?= date('H:i', strtotime($row['submission_date'])) ?></div>
                            </td>
                            <td>
                                <div class="text-sm"><?= htmlspecialchars($row['posted_by_name'] ?? 'Unknown') ?></div>
                                <div class="text-xs text-gray-400"><?= htmlspecialchars($row['posted_by_username'] ?? '') ?></div>
                            </td>
                            <td>
                                <div class="flex items-center gap-1">
                                    <!-- FIX: type="button" prevents this from also submitting bulkForm -->
                                    <button type="button" onclick="viewSubmission(<?= $row['id'] ?>)" class="hs-action-btn" title="View Details">
                                        <span class="material-symbols-outlined text-sm">visibility</span>
                                    </button>
                                    <?php if ($row['status'] === 'pending'): ?>
                                    <!-- FIX: type="button" prevents this from also submitting bulkForm -->
                                    <button type="button" onclick="reviewSubmission(<?= $row['id'] ?>)" class="hs-action-btn success" title="Review">
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
        <div class="hs-pagination-bar">
            <div class="hs-pagination-info">
                Showing <?= $offset + 1 ?> – <?= min($offset + $limit, $total_records) ?> of <?= number_format($total_records) ?> submissions
            </div>
            <div class="hs-pagination-nav">
                <button type="button" class="hs-pg-btn" onclick="hsGoToPage(1)" <?= $page <= 1 ? 'disabled' : '' ?>>
                    <span class="material-symbols-outlined text-sm">first_page</span>
                </button>
                <button type="button" class="hs-pg-btn" onclick="hsGoToPage(<?= $page - 1 ?>)" <?= $page <= 1 ? 'disabled' : '' ?>>
                    <span class="material-symbols-outlined text-sm">chevron_left</span>
                </button>
                <?php
                $window = 2;
                $start = max(1, $page - $window);
                $end = min($total_pages, $page + $window);
                if ($start > 1) {
                    echo '<button type="button" class="hs-pg-btn" onclick="hsGoToPage(1)">1</button>';
                    if ($start > 2) echo '<span class="text-gray-400 text-sm px-1">…</span>';
                }
                for ($i = $start; $i <= $end; $i++) {
                    echo '<button type="button" class="hs-pg-btn ' . ($i == $page ? 'active' : '') . '" onclick="hsGoToPage(' . $i . ')">' . $i . '</button>';
                }
                if ($end < $total_pages) {
                    if ($end < $total_pages - 1) echo '<span class="text-gray-400 text-sm px-1">…</span>';
                    echo '<button type="button" class="hs-pg-btn" onclick="hsGoToPage(' . $total_pages . ')">' . $total_pages . '</button>';
                }
                ?>
                <button type="button" class="hs-pg-btn" onclick="hsGoToPage(<?= $page + 1 ?>)" <?= $page >= $total_pages ? 'disabled' : '' ?>>
                    <span class="material-symbols-outlined text-sm">chevron_right</span>
                </button>
                <button type="button" class="hs-pg-btn" onclick="hsGoToPage(<?= $total_pages ?>)" <?= $page >= $total_pages ? 'disabled' : '' ?>>
                    <span class="material-symbols-outlined text-sm">last_page</span>
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ─── MODALS ─── -->

<!-- View Modal -->
<div class="hs-modal-backdrop" id="viewModal">
    <div class="hs-modal-center">
        <div class="hs-modal-box wide" onclick="event.stopPropagation();">
            <div class="hs-modal-header">
                <h3><span class="material-symbols-outlined">description</span> Submission Details</h3>
                <button type="button" onclick="closeViewModal()">✕</button>
            </div>
            <div class="hs-modal-body" id="viewContent">
                <div style="text-align:center;padding:30px;">
                    <span class="material-symbols-outlined" style="font-size:2.5rem;color:var(--hs-muted);animation:spin 1s linear infinite;">hourglass_empty</span>
                    <p style="color:var(--hs-muted);margin-top:8px;">Loading...</p>
                </div>
            </div>
            <div class="hs-modal-footer">
                <button type="button" class="hs-btn ghost" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Review Modal -->
<div class="hs-modal-backdrop" id="reviewModal">
    <div class="hs-modal-center">
        <div class="hs-modal-box" onclick="event.stopPropagation();">
            <div class="hs-modal-header" style="background:linear-gradient(135deg,var(--hs-warning),#b45309);">
                <h3><span class="material-symbols-outlined">rate_review</span> Review Submission</h3>
                <button type="button" onclick="closeReviewModal()">✕</button>
            </div>
            <form method="POST" action="" id="reviewForm">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="submission_id" id="reviewId" value="">
                <div class="hs-modal-body">
                    <div style="margin-bottom:16px;">
                        <label style="font-weight:600;display:block;margin-bottom:4px;">Status</label>
                        <select name="status" style="width:100%;padding:7px 10px;border:1px solid var(--hs-border);border-radius:6px;">
                            <option value="approved">✅ Approve</option>
                            <option value="rejected">❌ Reject</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-weight:600;display:block;margin-bottom:4px;">Admin Notes</label>
                        <textarea name="admin_notes" rows="4" style="width:100%;padding:7px 10px;border:1px solid var(--hs-border);border-radius:6px;font-family:inherit;resize:vertical;" placeholder="Add notes about this submission..."></textarea>
                    </div>
                </div>
                <div class="hs-modal-footer">
                    <button type="button" class="hs-btn ghost" onclick="closeReviewModal()">Cancel</button>
                    <button type="submit" class="hs-btn primary">
                        <span class="material-symbols-outlined">save</span> Submit Review
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="hs-modal-backdrop" id="deleteModal">
    <div class="hs-modal-center">
        <div class="hs-modal-box" onclick="event.stopPropagation();">
            <div class="hs-modal-header" style="background:linear-gradient(135deg,var(--hs-danger),#991b1b);">
                <h3><span class="material-symbols-outlined">warning</span> Confirm Deletion</h3>
                <button type="button" onclick="closeDeleteModal()">✕</button>
            </div>
            <div class="hs-modal-body">
                <p id="deleteModalText" style="font-size:.9rem;color:var(--hs-text);margin-bottom:12px;"></p>
                <div style="background:#fef2f2;border-left:4px solid var(--hs-danger);border-radius:0 6px 6px 0;padding:10px 12px;font-size:.8rem;color:#991b1b;">
                    <span class="material-symbols-outlined" style="font-size:.9rem;vertical-align:middle;">info</span>
                    This action is irreversible.
                </div>
            </div>
            <div class="hs-modal-footer">
                <button type="button" class="hs-btn ghost" onclick="closeDeleteModal()">Cancel</button>
                <button type="button" class="hs-btn danger" id="confirmDeleteBtn">
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
function exportAll(format) {
    if (!confirm('Export ALL submissions? This may take a moment.')) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.target = '_blank';
    form.innerHTML = `
        <input type="hidden" name="action" value="export_harvest">
        <input type="hidden" name="export_format" value="${format}">
        <input type="hidden" name="export_all" value="true">
        <input type="hidden" name="filter_status" value="${document.querySelector('select[name="status"]')?.value || 'all'}">
        <input type="hidden" name="filter_crop" value="${document.querySelector('select[name="crop"]')?.value || 'all'}">
        <input type="hidden" name="date_from" value="${document.querySelector('input[name="date_from"]')?.value || ''}">
        <input type="hidden" name="date_to" value="${document.querySelector('input[name="date_to"]')?.value || ''}">
    `;
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

// ── View Submission ──
function viewSubmission(id) {
    // Prevent event bubbling
    if (window.event) {
        window.event.stopPropagation();
    }
    
    // Show loading state
    const viewContent = document.getElementById('viewContent');
    viewContent.innerHTML = `
        <div style="text-align:center;padding:30px;">
            <span class="material-symbols-outlined" style="font-size:2.5rem;color:var(--hs-muted);animation:spin 1s linear infinite;">hourglass_empty</span>
            <p style="color:var(--hs-muted);margin-top:8px;">Loading submission details...</p>
        </div>
    `;
    
    // Open modal
    openViewModal();
    
    // Fetch data
    fetch(`get_submission.php?id=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('viewContent').innerHTML = data.html;
            } else {
                document.getElementById('viewContent').innerHTML = `
                    <div style="text-align:center;padding:30px;color:var(--hs-danger);">
                        <span class="material-symbols-outlined" style="font-size:2.5rem;">error</span>
                        <p>${data.message || 'Failed to load submission details'}</p>
                    </div>
                `;
            }
        })
        .catch(err => {
            document.getElementById('viewContent').innerHTML = `
                <div style="text-align:center;padding:30px;color:var(--hs-danger);">
                    <span class="material-symbols-outlined" style="font-size:2.5rem;">error</span>
                    <p>Error loading submission: ${err.message}</p>
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
function hsGoToPage(page) {
    const url = new URL(window.location);
    url.searchParams.set('page', page);
    window.location.href = url.toString();
}

function hsSortTable(col, dir) {
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