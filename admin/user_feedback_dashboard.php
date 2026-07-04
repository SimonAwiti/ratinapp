<?php
// user_feedback_dashboard.php - Admin dashboard for managing user feedback
// ─────────────────────────────────────────────────────────────
// Features:
//   - View all feedback with filtering (status, category, date range)
//   - Update feedback status (new → reviewed → archived)
//   - Add admin notes to feedback entries
//   - Delete spam/irrelevant feedback
//   - Export feedback to CSV/Excel
//   - Real-time stats dashboard
// ─────────────────────────────────────────────────────────────
// FIX (this version): the "Add note" and "Delete" buttons inside the
// bulk-actions <form id="bulkForm"> had no type="button" attribute, so
// browsers treated them as type="submit" by default. Clicking them opened
// the modal via onclick AND submitted bulkForm at the same time, which
// reloaded the page and made the modal disappear ~instantly. Both buttons
// now explicitly declare type="button" so they only run their onclick
// handler and never submit the surrounding form.
// ─────────────────────────────────────────────────────────────

if (session_status() == PHP_SESSION_NONE) session_start();
include 'includes/config.php';
include 'includes/admin_header.php';

// ── Handle POST actions ──────────────────────────────────────
$action_result = ['success' => false, 'msg' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Update status
    if ($action === 'update_status' && isset($_POST['feedback_id'], $_POST['status'])) {
        $id = (int)$_POST['feedback_id'];
        $status = in_array($_POST['status'], ['new','reviewed','archived']) ? $_POST['status'] : 'new';
        
        $stmt = $con->prepare("UPDATE site_feedback SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $status, $id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $action_result = ['success' => true, 'msg' => 'Status updated successfully'];
        } else {
            $action_result = ['success' => false, 'msg' => 'Failed to update status'];
        }
        $stmt->close();
    }
    
    // Add admin note
    if ($action === 'add_note' && isset($_POST['feedback_id'], $_POST['admin_notes'])) {
        $id = (int)$_POST['feedback_id'];
        $notes = trim($_POST['admin_notes']);
        
        $stmt = $con->prepare("UPDATE site_feedback SET admin_notes = ? WHERE id = ?");
        $stmt->bind_param('si', $notes, $id);
        if ($stmt->execute()) {
            $action_result = ['success' => true, 'msg' => 'Note added successfully'];
        } else {
            $action_result = ['success' => false, 'msg' => 'Failed to add note'];
        }
        $stmt->close();
    }
    
    // Delete feedback
    if ($action === 'delete_feedback' && isset($_POST['feedback_id'])) {
        $id = (int)$_POST['feedback_id'];
        $stmt = $con->prepare("DELETE FROM site_feedback WHERE id = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $action_result = ['success' => true, 'msg' => 'Feedback deleted successfully'];
        } else {
            $action_result = ['success' => false, 'msg' => 'Failed to delete feedback'];
        }
        $stmt->close();
    }
    
    // Bulk action
    if ($action === 'bulk_action' && isset($_POST['bulk_ids'], $_POST['bulk_status'])) {
        $ids = array_filter(array_map('intval', explode(',', $_POST['bulk_ids'])));
        $bulk_status = in_array($_POST['bulk_status'], ['new','reviewed','archived']) ? $_POST['bulk_status'] : 'new';
        
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));
            $stmt = $con->prepare("UPDATE site_feedback SET status = ? WHERE id IN ($placeholders)");
            $stmt->bind_param('s' . $types, $bulk_status, ...$ids);
            if ($stmt->execute()) {
                $action_result = ['success' => true, 'msg' => count($ids) . ' items updated successfully'];
            } else {
                $action_result = ['success' => false, 'msg' => 'Bulk update failed'];
            }
            $stmt->close();
        }
    }
    
    // Export
    if ($action === 'export_feedback') {
        $format = $_POST['export_format'] ?? 'csv';
        $filter_status = $_POST['filter_status'] ?? '';
        $filter_category = $_POST['filter_category'] ?? '';
        $date_from = $_POST['date_from'] ?? '';
        $date_to = $_POST['date_to'] ?? '';
        
        $where = ['1=1'];
        $params = [];
        $types = '';
        
        if ($filter_status && $filter_status !== 'all') {
            $where[] = "status = ?";
            $params[] = $filter_status;
            $types .= 's';
        }
        if ($filter_category && $filter_category !== 'all') {
            $where[] = "category = ?";
            $params[] = $filter_category;
            $types .= 's';
        }
        if ($date_from && $date_to) {
            $where[] = "submitted_at BETWEEN ? AND ?";
            $params[] = $date_from . ' 00:00:00';
            $params[] = $date_to . ' 23:59:59';
            $types .= 'ss';
        } elseif ($date_from) {
            $where[] = "submitted_at >= ?";
            $params[] = $date_from . ' 00:00:00';
            $types .= 's';
        } elseif ($date_to) {
            $where[] = "submitted_at <= ?";
            $params[] = $date_to . ' 23:59:59';
            $types .= 's';
        }
        
        $sql = "SELECT id, category, name, email, message, rating, page_url, status, submitted_at, admin_notes 
                FROM site_feedback WHERE " . implode(' AND ', $where) . " ORDER BY submitted_at DESC";
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
            header('Content-Disposition: attachment; filename="user_feedback_' . date('Y-m-d') . '.csv"');
            $out = fopen('php://output', 'w');
            fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM
            fputcsv($out, ['ID','Category','Name','Email','Message','Rating','Page URL','Status','Submitted At','Admin Notes']);
            foreach ($data as $row) {
                fputcsv($out, [
                    $row['id'],
                    $row['category'],
                    $row['name'],
                    $row['email'],
                    $row['message'],
                    $row['rating'],
                    $row['page_url'],
                    $row['status'],
                    $row['submitted_at'],
                    $row['admin_notes']
                ]);
            }
            fclose($out);
            exit;
        }
    }
}

// ── GET filters ──────────────────────────────────────────────
$filter_status = $_GET['status'] ?? 'all';
$filter_category = $_GET['category'] ?? 'all';
$search_query = trim($_GET['search'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort_by = $_GET['sort'] ?? 'submitted_at';
$sort_dir = ($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

// ── Build WHERE clause ──────────────────────────────────────
$where = ['1=1'];
$params = [];
$types = '';

if ($filter_status !== 'all') {
    $where[] = "status = ?";
    $params[] = $filter_status;
    $types .= 's';
}
if ($filter_category !== 'all') {
    $where[] = "category = ?";
    $params[] = $filter_category;
    $types .= 's';
}
if ($search_query) {
    $where[] = "(name LIKE ? OR email LIKE ? OR message LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}
if ($date_from && $date_to) {
    $where[] = "submitted_at BETWEEN ? AND ?";
    $params[] = $date_from . ' 00:00:00';
    $params[] = $date_to . ' 23:59:59';
    $types .= 'ss';
} elseif ($date_from) {
    $where[] = "submitted_at >= ?";
    $params[] = $date_from . ' 00:00:00';
    $types .= 's';
} elseif ($date_to) {
    $where[] = "submitted_at <= ?";
    $params[] = $date_to . ' 23:59:59';
    $types .= 's';
}

// ── Get total count ─────────────────────────────────────────
$count_sql = "SELECT COUNT(*) as total FROM site_feedback WHERE " . implode(' AND ', $where);
$count_stmt = $con->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'] ?? 0;
$count_stmt->close();
$total_pages = ceil($total_records / $limit);

// ── Get feedback data ──────────────────────────────────────
$allowed_sort = ['id','category','name','email','status','submitted_at','rating'];
$sort_col = in_array($sort_by, $allowed_sort) ? $sort_by : 'submitted_at';
$dir = $sort_dir === 'ASC' ? 'ASC' : 'DESC';

$sql = "SELECT * FROM site_feedback WHERE " . implode(' AND ', $where) . 
       " ORDER BY $sort_col $dir LIMIT ? OFFSET ?";
$stmt = $con->prepare($sql);
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$feedback_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Get stats ───────────────────────────────────────────────
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_count,
    SUM(CASE WHEN status = 'reviewed' THEN 1 ELSE 0 END) as reviewed_count,
    SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived_count,
    SUM(CASE WHEN category = 'bug' THEN 1 ELSE 0 END) as bug_count,
    SUM(CASE WHEN category = 'idea' THEN 1 ELSE 0 END) as idea_count,
    SUM(CASE WHEN category = 'data' THEN 1 ELSE 0 END) as data_count,
    SUM(CASE WHEN category = 'general' THEN 1 ELSE 0 END) as general_count,
    AVG(rating) as avg_rating,
    SUM(CASE WHEN rating IS NOT NULL THEN 1 ELSE 0 END) as rating_count
FROM site_feedback";
$stats_result = $con->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// ── Category labels ─────────────────────────────────────────
$category_labels = [
    'general' => ['label' => 'General', 'icon' => 'chat', 'color' => 'text-blue-600', 'bg' => 'bg-blue-50'],
    'data' => ['label' => 'Data Issue', 'icon' => 'database', 'color' => 'text-amber-600', 'bg' => 'bg-amber-50'],
    'bug' => ['label' => 'Bug Report', 'icon' => 'bug_report', 'color' => 'text-red-600', 'bg' => 'bg-red-50'],
    'idea' => ['label' => 'Feature Idea', 'icon' => 'lightbulb', 'color' => 'text-purple-600', 'bg' => 'bg-purple-50'],
];

$status_labels = [
    'new' => ['label' => 'New', 'color' => 'text-blue-700', 'bg' => 'bg-blue-100'],
    'reviewed' => ['label' => 'Reviewed', 'color' => 'text-amber-700', 'bg' => 'bg-amber-100'],
    'archived' => ['label' => 'Archived', 'color' => 'text-gray-600', 'bg' => 'bg-gray-100'],
];

function getCategoryBadge($category) {
    global $category_labels;
    $info = $category_labels[$category] ?? ['label' => $category, 'color' => 'text-gray-600', 'bg' => 'bg-gray-100'];
    return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium ' . $info['color'] . ' ' . $info['bg'] . '">
        <span class="material-symbols-outlined text-sm">' . $info['icon'] . '</span>
        ' . $info['label'] . '
    </span>';
}

function getStatusBadge($status) {
    global $status_labels;
    $info = $status_labels[$status] ?? ['label' => $status, 'color' => 'text-gray-600', 'bg' => 'bg-gray-100'];
    return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium ' . $info['color'] . ' ' . $info['bg'] . '">
        <span class="w-1.5 h-1.5 rounded-full ' . ($status === 'new' ? 'bg-blue-500' : ($status === 'reviewed' ? 'bg-amber-500' : 'bg-gray-400')) . '"></span>
        ' . $info['label'] . '
    </span>';
}

function getRatingStars($rating) {
    if (!$rating) return '—';
    $full = floor($rating);
    $half = $rating - $full >= 0.5 ? 1 : 0;
    $empty = 5 - $full - $half;
    return str_repeat('★', $full) . str_repeat('½', $half) . str_repeat('☆', $empty);
}

// ── Handle action messages ──────────────────────────────────
if ($action_result['success']) {
    $alert_type = 'success';
    $alert_msg = $action_result['msg'];
} elseif ($action_result['msg']) {
    $alert_type = 'error';
    $alert_msg = $action_result['msg'];
}
?>

<!-- Dashboard Content -->
<div class="container mx-auto px-4 py-6 max-w-7xl">

    <!-- Page Header -->
    <div class="flex flex-wrap justify-between items-start gap-4 mb-2">
        <div>
            <h1 class="text-2xl font-bold text-[#00450d] flex items-center gap-3">
                <span class="material-symbols-outlined text-3xl">feedback</span>
                User Feedback
            </h1>
            <p class="text-sm text-gray-500 mt-1">Monitor and manage feedback submitted from the public website</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <button type="button" onclick="openExportModal()" 
                    class="inline-flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50 transition-all">
                <span class="material-symbols-outlined text-lg">download</span>
                Export
            </button>
            <button type="button" onclick="location.reload()" 
                    class="inline-flex items-center gap-2 px-4 py-2 bg-[#00450d] text-white rounded-lg text-sm font-medium hover:bg-[#00330a] transition-all">
                <span class="material-symbols-outlined text-lg">refresh</span>
                Refresh
            </button>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($alert_msg)): ?>
    <div class="alert-box <?= $alert_type ?>">
        <span class="material-symbols-outlined"><?= $alert_type === 'success' ? 'check_circle' : 'error' ?></span>
        <?= htmlspecialchars($alert_msg) ?>
        <button type="button" class="alert-close" onclick="this.parentElement.remove()">×</button>
    </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="feedback-stats-grid">
        <div class="feedback-stat-card">
            <span class="stat-icon material-symbols-outlined">feedback</span>
            <div class="stat-value"><?= number_format($stats['total'] ?? 0) ?></div>
            <div class="stat-label">Total Feedback</div>
        </div>
        <div class="feedback-stat-card">
            <span class="stat-icon material-symbols-outlined" style="color:#2563eb;">mail</span>
            <div class="stat-value text-blue-600"><?= number_format($stats['new_count'] ?? 0) ?></div>
            <div class="stat-label">New (unread)</div>
        </div>
        <div class="feedback-stat-card bug-stat">
            <span class="stat-icon material-symbols-outlined" style="color:#dc2626;">bug_report</span>
            <div class="stat-value text-red-600"><?= number_format($stats['bug_count'] ?? 0) ?></div>
            <div class="stat-label">Bug Reports</div>
        </div>
        <div class="feedback-stat-card idea-stat">
            <span class="stat-icon material-symbols-outlined" style="color:#7c3aed;">lightbulb</span>
            <div class="stat-value text-purple-600"><?= number_format($stats['idea_count'] ?? 0) ?></div>
            <div class="stat-label">Feature Ideas</div>
        </div>
        <div class="feedback-stat-card data-stat">
            <span class="stat-icon material-symbols-outlined" style="color:#d97706;">database</span>
            <div class="stat-value text-amber-600"><?= number_format($stats['data_count'] ?? 0) ?></div>
            <div class="stat-label">Data Issues</div>
        </div>
        <div class="feedback-stat-card rating-stat">
            <span class="stat-icon material-symbols-outlined" style="color:#f59e0b;">star</span>
            <div class="stat-value text-amber-600"><?= $stats['avg_rating'] ? number_format($stats['avg_rating'], 1) : '—' ?></div>
            <div class="stat-label">Avg Rating (<?= $stats['rating_count'] ?? 0 ?> ratings)</div>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" action="" class="feedback-filters" id="filterForm">
        <div class="filter-group">
            <label>Status</label>
            <select name="status" onchange="this.form.submit()">
                <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All Status</option>
                <option value="new" <?= $filter_status === 'new' ? 'selected' : '' ?>>New</option>
                <option value="reviewed" <?= $filter_status === 'reviewed' ? 'selected' : '' ?>>Reviewed</option>
                <option value="archived" <?= $filter_status === 'archived' ? 'selected' : '' ?>>Archived</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Category</label>
            <select name="category" onchange="this.form.submit()">
                <option value="all" <?= $filter_category === 'all' ? 'selected' : '' ?>>All Categories</option>
                <option value="general" <?= $filter_category === 'general' ? 'selected' : '' ?>>General</option>
                <option value="data" <?= $filter_category === 'data' ? 'selected' : '' ?>>Data Issue</option>
                <option value="bug" <?= $filter_category === 'bug' ? 'selected' : '' ?>>Bug Report</option>
                <option value="idea" <?= $filter_category === 'idea' ? 'selected' : '' ?>>Feature Idea</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Date From</label>
            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" onchange="this.form.submit()">
        </div>
        <div class="filter-group">
            <label>Date To</label>
            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" onchange="this.form.submit()">
        </div>
        <div class="filter-group" style="flex:2;">
            <label>Search</label>
            <input type="text" name="search" placeholder="Search name, email, or message…" 
                   value="<?= htmlspecialchars($search_query) ?>" onchange="this.form.submit()">
        </div>
        <div class="filter-actions">
            <button type="submit" class="px-4 py-2 bg-[#00450d] text-white rounded-lg text-sm font-medium hover:bg-[#00330a] transition-all">
                <span class="material-symbols-outlined text-sm">search</span> Filter
            </button>
            <a href="?<?= http_build_query(array_diff_key($_GET, ['search'=>'', 'page'=>''])) ?>" 
               class="px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50 transition-all">
                Clear
            </a>
        </div>
    </form>

    <!-- Bulk Actions & Table -->
    <div class="feedback-table-wrap">
        <form method="POST" action="" id="bulkForm">
            <input type="hidden" name="action" value="bulk_action">
            <input type="hidden" name="bulk_ids" id="bulkIds" value="">
            <input type="hidden" name="bulk_status" id="bulkStatus" value="">

            <div class="bulk-actions-bar">
                <div class="bulk-info">
                    <span class="material-symbols-outlined text-sm" style="vertical-align:middle;">checklist</span>
                    <span id="bulkCount">0</span> selected
                </div>
                <div class="flex flex-wrap gap-2 items-center">
                    <select id="bulkStatusSelect" class="px-3 py-1.5 border border-gray-200 rounded-lg text-sm">
                        <option value="reviewed">Mark as Reviewed</option>
                        <option value="archived">Mark as Archived</option>
                        <option value="new">Mark as New</option>
                    </select>
                    <button type="button" onclick="submitBulkAction()" 
                            class="px-4 py-1.5 bg-[#00450d] text-white rounded-lg text-sm font-medium hover:bg-[#00330a] transition-all">
                        Apply
                    </button>
                    <button type="button" onclick="clearBulkSelection()" 
                            class="px-3 py-1.5 border border-gray-200 rounded-lg text-sm hover:bg-gray-50 transition-all">
                        Clear
                    </button>
                </div>
                <div style="margin-left:auto;">
                    <span class="text-sm text-gray-500"><?= number_format($total_records) ?> total</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="feedback-table">
                    <thead>
                        <tr>
                            <th style="width:36px;"><input type="checkbox" id="selectAll" onchange="toggleAllCheckboxes(this)"></th>
                            <th>ID</th>
                            <th class="hide-mobile">Category</th>
                            <th>Submitted By</th>
                            <th>Message</th>
                            <th class="hide-mobile">Rating</th>
                            <th>Status</th>
                            <th class="hide-mobile">Date</th>
                            <th style="width:120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($feedback_data)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-8 text-gray-500">
                                <span class="material-symbols-outlined text-4xl block mb-2 opacity-30">inbox</span>
                                No feedback found matching your filters
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($feedback_data as $item): ?>
                        <tr class="<?= $item['status'] === 'new' ? 'feedback-row-new' : '' ?>" data-id="<?= $item['id'] ?>">
                            <td><input type="checkbox" class="row-checkbox" value="<?= $item['id'] ?>" onchange="updateBulkCount()"></td>
                            <td><span class="font-mono text-sm">#<?= $item['id'] ?></span></td>
                            <td class="hide-mobile"><?= getCategoryBadge($item['category']) ?></td>
                            <td>
                                <div class="font-medium text-sm"><?= htmlspecialchars($item['name'] ?? 'Anonymous') ?></div>
                                <div class="text-xs text-gray-400"><?= htmlspecialchars($item['email'] ?? 'No email') ?></div>
                            </td>
                            <td>
                                <div class="msg-preview" id="msg-preview-<?= $item['id'] ?>">
                                    <?= htmlspecialchars(substr($item['message'], 0, 80)) ?>
                                    <?php if (strlen($item['message']) > 80): ?>
                                    <button type="button" class="msg-expand" onclick="toggleMessage(<?= $item['id'] ?>)">more</button>
                                    <?php endif; ?>
                                </div>
                                <div class="msg-full hidden" id="msg-full-<?= $item['id'] ?>">
                                    <?= nl2br(htmlspecialchars($item['message'])) ?>
                                    <button type="button" class="msg-expand" onclick="toggleMessage(<?= $item['id'] ?>)">less</button>
                                </div>
                                <?php if ($item['page_url']): ?>
                                <div class="text-xs text-gray-400 truncate max-w-[200px]">
                                    <span class="material-symbols-outlined text-xs" style="vertical-align:middle;">link</span>
                                    <?= htmlspecialchars($item['page_url']) ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="hide-mobile">
                                <?php if ($item['rating']): ?>
                                <span class="rating-display"><?= getRatingStars($item['rating']) ?></span>
                                <?php else: ?>
                                <span class="text-gray-400 text-sm">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= getStatusBadge($item['status']) ?></td>
                            <td class="hide-mobile">
                                <div class="text-sm"><?= date('d M Y', strtotime($item['submitted_at'])) ?></div>
                                <div class="text-xs text-gray-400"><?= date('H:i', strtotime($item['submitted_at'])) ?></div>
                            </td>
                            <td>
                                <div class="flex items-center gap-1 flex-wrap">
                                    <!-- FIX: type="button" prevents this from also submitting bulkForm -->
                                    <button type="button"
                                            onclick="openNoteModal(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['admin_notes'] ?? '')) ?>')" 
                                            class="action-btn" title="Add note">
                                        <span class="material-symbols-outlined text-sm">comment</span>
                                    </button>
                                    <select onchange="updateStatus(<?= $item['id'] ?>, this.value)" class="text-xs border border-gray-200 rounded px-1.5 py-1 bg-white">
                                        <option value="new" <?= $item['status'] === 'new' ? 'selected' : '' ?>>New</option>
                                        <option value="reviewed" <?= $item['status'] === 'reviewed' ? 'selected' : '' ?>>Reviewed</option>
                                        <option value="archived" <?= $item['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
                                    </select>
                                    <!-- FIX: type="button" prevents this from also submitting bulkForm -->
                                    <button type="button" onclick="deleteFeedback(<?= $item['id'] ?>)" class="action-btn danger" title="Delete">
                                        <span class="material-symbols-outlined text-sm">delete</span>
                                    </button>
                                </div>
                                <?php if (!empty($item['admin_notes'])): ?>
                                <div class="text-xs text-gray-400 mt-1 truncate max-w-[100px]" title="<?= htmlspecialchars($item['admin_notes']) ?>">
                                    <span class="material-symbols-outlined text-xs" style="vertical-align:middle;">sticky_note_2</span>
                                    <?= htmlspecialchars(substr($item['admin_notes'], 0, 20)) ?>…
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="feedback-pagination">
            <div class="pagination-info">
                Showing <?= $offset + 1 ?> – <?= min($offset + $limit, $total_records) ?> of <?= number_format($total_records) ?>
            </div>
            <div class="pagination-nav">
                <button type="button" class="pg-btn" onclick="goToPage(1)" <?= $page <= 1 ? 'disabled' : '' ?>>
                    <span class="material-symbols-outlined text-sm">first_page</span>
                </button>
                <button type="button" class="pg-btn" onclick="goToPage(<?= $page - 1 ?>)" <?= $page <= 1 ? 'disabled' : '' ?>>
                    <span class="material-symbols-outlined text-sm">chevron_left</span>
                </button>
                <?php
                $window = 2;
                $start = max(1, $page - $window);
                $end = min($total_pages, $page + $window);
                if ($start > 1) {
                    echo '<button type="button" class="pg-btn" onclick="goToPage(1)">1</button>';
                    if ($start > 2) echo '<span class="text-gray-400 text-sm px-1">…</span>';
                }
                for ($i = $start; $i <= $end; $i++) {
                    echo '<button type="button" class="pg-btn ' . ($i == $page ? 'active' : '') . '" onclick="goToPage(' . $i . ')">' . $i . '</button>';
                }
                if ($end < $total_pages) {
                    if ($end < $total_pages - 1) echo '<span class="text-gray-400 text-sm px-1">…</span>';
                    echo '<button type="button" class="pg-btn" onclick="goToPage(' . $total_pages . ')">' . $total_pages . '</button>';
                }
                ?>
                <button type="button" class="pg-btn" onclick="goToPage(<?= $page + 1 ?>)" <?= $page >= $total_pages ? 'disabled' : '' ?>>
                    <span class="material-symbols-outlined text-sm">chevron_right</span>
                </button>
                <button type="button" class="pg-btn" onclick="goToPage(<?= $total_pages ?>)" <?= $page >= $total_pages ? 'disabled' : '' ?>>
                    <span class="material-symbols-outlined text-sm">last_page</span>
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ─── MODALS ─── -->

    <!-- Note Modal -->
    <div class="modal-overlay" id="noteModal">
        <div class="modal-content" onclick="event.stopPropagation();">
            <button type="button" class="modal-close" onclick="closeNoteModal()">×</button>
            <div class="modal-title">
                <span class="material-symbols-outlined" style="vertical-align:middle;">comment</span>
                Admin Note
            </div>
            <form method="POST" action="" id="noteForm">
                <input type="hidden" name="action" value="add_note">
                <input type="hidden" name="feedback_id" id="noteFeedbackId" value="">
                <p class="text-sm text-gray-500 mb-3">Add a private note about this feedback. Only admins can see this.</p>
                <textarea name="admin_notes" id="noteText" placeholder="Write your note here…"></textarea>
                <div class="modal-actions">
                    <button type="button" class="px-4 py-2 border border-gray-200 rounded-lg text-sm hover:bg-gray-50" onclick="closeNoteModal()">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-[#00450d] text-white rounded-lg text-sm font-medium hover:bg-[#00330a] transition-all">
                        <span class="material-symbols-outlined text-sm">save</span> Save Note
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Export Modal -->
    <div class="modal-overlay" id="exportModal">
        <div class="modal-content" onclick="event.stopPropagation();">
            <button type="button" class="modal-close" onclick="closeExportModal()">×</button>
            <div class="modal-title">
                <span class="material-symbols-outlined" style="vertical-align:middle;">download</span>
                Export Feedback
            </div>
            <form method="POST" action="" id="exportForm">
                <input type="hidden" name="action" value="export_feedback">
                <input type="hidden" name="filter_status" value="<?= htmlspecialchars($filter_status) ?>">
                <input type="hidden" name="filter_category" value="<?= htmlspecialchars($filter_category) ?>">
                <input type="hidden" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                <input type="hidden" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                
                <p class="text-sm text-gray-600 mb-4">
                    Export all feedback matching the current filters 
                    <span class="font-medium">(<?= number_format($total_records) ?> records)</span>
                </p>
                <div class="flex flex-col gap-3">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="radio" name="export_format" value="csv" checked class="w-4 h-4 text-[#00450d]">
                        <span><span class="material-symbols-outlined text-sm" style="vertical-align:middle;">table_view</span> CSV (Excel compatible)</span>
                    </label>
                </div>
                <div class="modal-actions">
                    <button type="button" class="px-4 py-2 border border-gray-200 rounded-lg text-sm hover:bg-gray-50" onclick="closeExportModal()">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-[#00450d] text-white rounded-lg text-sm font-medium hover:bg-[#00330a] transition-all">
                        <span class="material-symbols-outlined text-sm">download</span> Export
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<!-- Styles moved inline to ensure they work -->
<style>
/* ── Custom styles for feedback dashboard ── */
.feedback-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.feedback-stat-card {
    background: #fff;
    border-radius: 12px;
    padding: 16px 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
    border-left: 4px solid #00450d;
    transition: transform .2s, box-shadow .2s;
}
.feedback-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,.1);
}
.feedback-stat-card .stat-value {
    font-size: 1.75rem;
    font-weight: 700;
    color: #1a1c1c;
    line-height: 1.2;
}
.feedback-stat-card .stat-label {
    font-size: .75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #6b7280;
    margin-top: 4px;
}
.feedback-stat-card .stat-icon {
    font-size: 2rem;
    opacity: .15;
    float: right;
}
.feedback-stat-card.bug-stat { border-left-color: #dc2626; }
.feedback-stat-card.idea-stat { border-left-color: #7c3aed; }
.feedback-stat-card.data-stat { border-left-color: #d97706; }
.feedback-stat-card.rating-stat { border-left-color: #f59e0b; }

.feedback-filters {
    background: #fff;
    border-radius: 12px;
    padding: 16px 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
    margin-bottom: 20px;
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: flex-end;
}
.feedback-filters .filter-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
    min-width: 140px;
}
.feedback-filters .filter-group label {
    font-size: .7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #6b7280;
}
.feedback-filters .filter-group select,
.feedback-filters .filter-group input {
    padding: 7px 10px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    font-size: .8125rem;
    background: #fff;
    width: 100%;
    transition: border-color .2s;
}
.feedback-filters .filter-group select:focus,
.feedback-filters .filter-group input:focus {
    outline: none;
    border-color: #00450d;
    box-shadow: 0 0 0 3px rgba(0,69,13,.1);
}
.feedback-filters .filter-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.feedback-table-wrap {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
    overflow: hidden;
}
.feedback-table {
    width: 100%;
    border-collapse: collapse;
    font-size: .8125rem;
}
.feedback-table thead tr {
    background: #f8f9fa;
}
.feedback-table th {
    padding: 10px 14px;
    text-align: left;
    font-size: .7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #6b7280;
    border-bottom: 2px solid #e5e7eb;
}
.feedback-table td {
    padding: 10px 14px;
    border-bottom: 1px solid #f3f4f6;
    vertical-align: middle;
}
.feedback-table tbody tr:hover {
    background: #fafafa;
}
.feedback-table .msg-preview {
    max-width: 260px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.feedback-table .msg-full {
    max-width: 360px;
    word-wrap: break-word;
}
.feedback-table .msg-expand {
    color: #00450d;
    cursor: pointer;
    font-size: .7rem;
    font-weight: 500;
    background: none;
    border: none;
    padding: 0 4px;
}
.feedback-table .msg-expand:hover {
    text-decoration: underline;
}

.bulk-actions-bar {
    padding: 12px 16px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
}
.bulk-actions-bar .bulk-info {
    font-size: .8125rem;
    color: #6b7280;
}
.bulk-actions-bar .bulk-info strong {
    color: #1a1c1c;
}

.feedback-pagination {
    padding: 12px 16px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}
.feedback-pagination .pagination-info {
    font-size: .8125rem;
    color: #6b7280;
}
.feedback-pagination .pagination-nav {
    display: flex;
    gap: 4px;
    align-items: center;
}
.feedback-pagination .pg-btn {
    min-width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    font-size: .75rem;
    border: 1px solid #e5e7eb;
    background: #fff;
    color: #1a1c1c;
    cursor: pointer;
    transition: all .2s;
    padding: 0 8px;
}
.feedback-pagination .pg-btn:hover:not(:disabled):not(.active) {
    background: #f0f7f0;
    border-color: #00450d;
    color: #00450d;
}
.feedback-pagination .pg-btn.active {
    background: #00450d;
    border-color: #00450d;
    color: #fff;
    font-weight: 700;
}
.feedback-pagination .pg-btn:disabled {
    opacity: .35;
    cursor: not-allowed;
}

.feedback-row-new {
    background: #f8faff;
}
.feedback-row-new:hover {
    background: #f0f4ff !important;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: .75rem;
    font-weight: 500;
    border: none;
    cursor: pointer;
    transition: all .2s;
    background: #f3f4f6;
    color: #374151;
}
.action-btn:hover {
    background: #e5e7eb;
}
.action-btn.primary {
    background: #00450d;
    color: #fff;
}
.action-btn.primary:hover {
    background: #00330a;
}
.action-btn.danger {
    background: #fee2e2;
    color: #dc2626;
}
.action-btn.danger:hover {
    background: #fecaca;
}
.action-btn.success {
    background: #dcfce7;
    color: #16a34a;
}
.action-btn.success:hover {
    background: #bbf7d0;
}

.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.45);
    z-index: 999;
    align-items: center;
    justify-content: center;
}
.modal-overlay.active {
    display: flex;
}
.modal-content {
    background: #fff;
    border-radius: 16px;
    max-width: 520px;
    width: 92%;
    padding: 28px 32px;
    box-shadow: 0 20px 60px rgba(0,0,0,.2);
    max-height: 85vh;
    overflow-y: auto;
    position: relative;
    z-index: 1000;
}
.modal-content .modal-title {
    font-size: 1.125rem;
    font-weight: 700;
    color: #1a1c1c;
    margin-bottom: 16px;
}
.modal-content .modal-close {
    float: right;
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #6b7280;
    padding: 0 8px;
}
.modal-content .modal-close:hover {
    color: #1a1c1c;
}
.modal-content textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    font-size: .875rem;
    resize: vertical;
    min-height: 100px;
    font-family: inherit;
}
.modal-content textarea:focus {
    outline: none;
    border-color: #00450d;
    box-shadow: 0 0 0 3px rgba(0,69,13,.1);
}
.modal-content .modal-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 16px;
}

.rating-display {
    font-size: .95rem;
    color: #f59e0b;
    letter-spacing: 1px;
}

.alert-box {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-box.success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}
.alert-box.error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}
.alert-box .alert-close {
    margin-left: auto;
    background: none;
    border: none;
    font-size: 1.25rem;
    cursor: pointer;
    opacity: .6;
}
.alert-box .alert-close:hover {
    opacity: 1;
}

@media (max-width: 768px) {
    .feedback-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .feedback-filters {
        flex-direction: column;
    }
    .feedback-filters .filter-group {
        min-width: 100%;
    }
    .feedback-table td,
    .feedback-table th {
        padding: 8px 10px;
    }
    .feedback-table .hide-mobile {
        display: none;
    }
}
</style>

<script>
// ── Bulk Selection ──
function updateBulkCount() {
    const checked = document.querySelectorAll('.row-checkbox:checked');
    document.getElementById('bulkCount').textContent = checked.length;
    document.getElementById('selectAll').checked = checked.length > 0 && 
        checked.length === document.querySelectorAll('.row-checkbox').length;
}

function toggleAllCheckboxes(master) {
    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = master.checked);
    updateBulkCount();
}

function clearBulkSelection() {
    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
    updateBulkCount();
}

function submitBulkAction() {
    const checked = document.querySelectorAll('.row-checkbox:checked');
    if (!checked.length) {
        alert('Please select at least one item.');
        return;
    }
    const ids = Array.from(checked).map(cb => cb.value).join(',');
    const status = document.getElementById('bulkStatusSelect').value;
    if (!confirm(`Update ${checked.length} item(s) to "${status}"?`)) return;
    
    document.getElementById('bulkIds').value = ids;
    document.getElementById('bulkStatus').value = status;
    document.getElementById('bulkForm').submit();
}

// ── Status Update ──
function updateStatus(id, status) {
    if (!confirm(`Change status of feedback #${id} to "${status}"?`)) {
        // Reset select to previous value
        const select = document.querySelector(`tr[data-id="${id}"] select`);
        if (select) {
            const current = select.querySelector('option:checked')?.value || 'new';
            select.value = current;
        }
        return;
    }
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="feedback_id" value="${id}">
        <input type="hidden" name="status" value="${status}">
    `;
    document.body.appendChild(form);
    form.submit();
}

// ── Delete ──
function deleteFeedback(id) {
    if (!confirm(`Permanently delete feedback #${id}? This action cannot be undone.`)) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="delete_feedback">
        <input type="hidden" name="feedback_id" value="${id}">
    `;
    document.body.appendChild(form);
    form.submit();
}

// ── Message Expand ──
function toggleMessage(id) {
    const preview = document.getElementById('msg-preview-' + id);
    const full = document.getElementById('msg-full-' + id);
    if (preview && full) {
        preview.classList.toggle('hidden');
        full.classList.toggle('hidden');
    }
}

// ── Note Modal Functions ──
function openNoteModal(id, existingNote) {
    document.getElementById('noteFeedbackId').value = id;
    document.getElementById('noteText').value = existingNote || '';
    document.getElementById('noteModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeNoteModal() {
    document.getElementById('noteModal').classList.remove('active');
    document.body.style.overflow = '';
}

// ── Export Modal Functions ──
function openExportModal() {
    document.getElementById('exportModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeExportModal() {
    document.getElementById('exportModal').classList.remove('active');
    document.body.style.overflow = '';
}

// ── Pagination ──
function goToPage(page) {
    const url = new URL(window.location);
    url.searchParams.set('page', page);
    window.location.href = url.toString();
}

// ── Close modals on overlay click ──
document.addEventListener('DOMContentLoaded', function() {
    // Note modal overlay click
    const noteModal = document.getElementById('noteModal');
    if (noteModal) {
        noteModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeNoteModal();
            }
        });
    }
    
    // Export modal overlay click
    const exportModal = document.getElementById('exportModal');
    if (exportModal) {
        exportModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeExportModal();
            }
        });
    }
});

// ── Keyboard shortcuts ──
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (document.getElementById('noteModal').classList.contains('active')) {
            closeNoteModal();
        }
        if (document.getElementById('exportModal').classList.contains('active')) {
            closeExportModal();
        }
    }
});

// ── Auto-submit filter on Enter ──
document.querySelector('#filterForm input[type="text"]')?.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') this.form.submit();
});
</script>

<?php include 'user_footer.php'; ?>