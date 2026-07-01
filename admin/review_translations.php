<?php
// admin/review_translations.php
if (session_status() == PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_logged_in'])) { header("Location: login.php"); exit; }

require_once 'includes/config.php';
require_once '../subscribers/includes/TranslationManager.php';

$translator = TranslationManager::getInstance($con);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'approve') {
        $con->query("UPDATE translations SET status='approved' WHERE id=$id");
    } elseif ($action === 'update_text') {
        $stmt = $con->prepare("UPDATE translations SET translation=?, status='approved' WHERE id=?");
        $stmt->bind_param('si', $_POST['translation'], $id);
        $stmt->execute();
    } elseif ($action === 'delete') {
        $con->query("DELETE FROM translations WHERE id=$id");
    } elseif ($action === 'approve_all_pending') {
        $con->query("UPDATE translations SET status='approved' WHERE status='pending'");
    }
    $translator->rebuildAllCaches();
    header('Location: review_translations.php');
    exit;
}

$rows = [];
$result = $con->query("SELECT * FROM translations ORDER BY status='pending' DESC, language_code, source_text");
while ($r = $result->fetch_assoc()) $rows[] = $r;
?>
<!DOCTYPE html>
<html><head><title>Review Translations</title>
<style>
body{font-family:'Inter',sans-serif;padding:20px;background:#f9f9f9;}
table{width:100%;border-collapse:collapse;font-size:13px;background:#fff;}
th,td{padding:8px 10px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top;}
th{background:#f3f4f6;}
.badge{padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;}
.badge-pending{background:#fef3c7;color:#92400e;}
.badge-approved{background:#d1fae5;color:#065f46;}
input[type=text]{width:100%;padding:4px;border:1px solid #d1d5db;border-radius:4px;}
button{padding:4px 10px;border:none;border-radius:4px;cursor:pointer;font-size:12px;margin-right:4px;}
.btn-approve{background:#16a34a;color:#fff;}
.btn-save{background:#3b82f6;color:#fff;}
.btn-delete{background:#dc2626;color:#fff;}
</style></head>
<body>
<h1>Review Translations</h1>
<form method="POST" style="margin-bottom:14px;">
  <input type="hidden" name="action" value="approve_all_pending">
  <button class="btn-approve" type="submit">Approve All Pending</button>
</form>
<table>
<thead><tr><th>Lang</th><th>Source (English)</th><th>Translation</th><th>Status</th><th>Origin</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
  <td><strong><?= strtoupper($r['language_code']) ?></strong></td>
  <td><?= htmlspecialchars($r['source_text']) ?></td>
  <td>
    <form method="POST" style="display:flex;gap:4px;">
      <input type="hidden" name="action" value="update_text">
      <input type="hidden" name="id" value="<?= $r['id'] ?>">
      <input type="text" name="translation" value="<?= htmlspecialchars($r['translation']) ?>">
      <button class="btn-save" type="submit">Save</button>
    </form>
  </td>
  <td><span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
  <td><?= htmlspecialchars($r['source']) ?></td>
  <td>
    <?php if ($r['status']==='pending'): ?>
    <form method="POST" style="display:inline;">
      <input type="hidden" name="action" value="approve">
      <input type="hidden" name="id" value="<?= $r['id'] ?>">
      <button class="btn-approve" type="submit">Approve</button>
    </form>
    <?php endif; ?>
    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete?')">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?= $r['id'] ?>">
      <button class="btn-delete" type="submit">Delete</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</body></html>