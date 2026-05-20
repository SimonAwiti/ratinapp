<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../admin/login.php");
    exit;
}

require_once '../admin/includes/admin_header.php';

// Include config
if (file_exists('includes/config.php')) {
    include 'includes/config.php';
} elseif (file_exists('../admin/includes/config.php')) {
    include '../admin/includes/config.php';
}

// Get quick counts with simple queries (fast)
$total_articles = 0;
$published_count = 0;
$draft_count = 0;
$archived_count = 0;
$total_insights = 0;
$total_grainwatch = 0;

$result = $con->query("SELECT COUNT(*) as count FROM news_articles");
if ($result) $total_articles = $result->fetch_assoc()['count'];

$result = $con->query("SELECT COUNT(*) as count FROM news_articles WHERE status = 'published'");
if ($result) $published_count = $result->fetch_assoc()['count'];

$result = $con->query("SELECT COUNT(*) as count FROM news_articles WHERE status = 'draft'");
if ($result) $draft_count = $result->fetch_assoc()['count'];

$result = $con->query("SELECT COUNT(*) as count FROM news_articles WHERE status = 'archived'");
if ($result) $archived_count = $result->fetch_assoc()['count'];

$result = $con->query("SELECT COUNT(*) as count FROM insights");
if ($result) $total_insights = $result->fetch_assoc()['count'];

$result = $con->query("SELECT COUNT(*) as count FROM grainwatch");
if ($result) $total_grainwatch = $result->fetch_assoc()['count'];

// Handle Bulk Delete
if (isset($_POST['bulk_delete']) && isset($_POST['selected_items']) && !empty($_POST['selected_items'])) {
    $selected_ids = explode(',', $_POST['selected_items']);
    $delete_type = $_POST['delete_type'];
    $deleted_count = 0;
    
    foreach ($selected_ids as $item_id) {
        if ($delete_type === 'article') {
            $delete_stmt = $con->prepare("DELETE FROM news_articles WHERE id = ?");
            $delete_stmt->bind_param("i", $item_id);
            if ($delete_stmt->execute()) $deleted_count++;
            $delete_stmt->close();
        } elseif ($delete_type === 'insight') {
            $delete_stmt = $con->prepare("DELETE FROM insights WHERE id = ?");
            $delete_stmt->bind_param("i", $item_id);
            if ($delete_stmt->execute()) $deleted_count++;
            $delete_stmt->close();
        } elseif ($delete_type === 'grainwatch') {
            $delete_stmt = $con->prepare("DELETE FROM grainwatch WHERE id = ?");
            $delete_stmt->bind_param("i", $item_id);
            if ($delete_stmt->execute()) $deleted_count++;
            $delete_stmt->close();
        }
    }
    
    $message = "$deleted_count item(s) deleted successfully!";
    $message_type = "success";
}
?>

<style>
.auth-bg-gradient {
    background: radial-gradient(circle at top left, rgba(0, 69, 13, 0.03), transparent),
                radial-gradient(circle at bottom right, rgba(128, 0, 0, 0.03), transparent);
}
.header-accent-gradient {
    background: linear-gradient(90deg, #00450d 0%, #800000 50%, #00450d 100%);
}
.table-row-hover:hover {
    background-color: #fefaf5;
}
.stat-card {
    transition: all 0.2s ease;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.pagination-btn {
    min-width: 34px;
    height: 34px;
    transition: all 0.2s ease;
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
}
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.7rem;
    border-radius: 9999px;
    font-size: 0.7rem;
    font-weight: 500;
}
.status-published { background-color: #d1fae5; color: #065f46; }
.status-draft { background-color: #fef3c7; color: #92400e; }
.status-archived { background-color: #f3f4f6; color: #374151; }
.category-badge {
    display: inline-flex;
    padding: 0.25rem 0.7rem;
    border-radius: 9999px;
    font-size: 0.7rem;
    font-weight: 500;
    background-color: #f3f4f6;
    color: #4b5563;
}
.loading-spinner {
    display: inline-block;
    width: 2rem;
    height: 2rem;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #800000;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
.checkbox-col {
    width: 32px;
}
</style>

<div class="auth-bg-gradient -m-4 -mt-20 p-4 pt-24 min-h-screen">
    <div class="max-w-7xl mx-auto">

        <!-- Messages -->
        <?php if (isset($message) && !empty($message)): ?>
            <div class="mb-4 p-3 rounded-lg flex items-center gap-2 text-sm <?= $message_type == 'success' ? 'bg-green-50 text-green-700 border-l-4 border-green-500' : 'bg-red-50 text-red-700 border-l-4 border-red-500' ?>">
                <span class="material-symbols-outlined text-base"><?= $message_type == 'success' ? 'check_circle' : 'error' ?></span>
                <span><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <!-- Header Section -->
        <div class="mb-6">
            <div class="flex justify-between items-center flex-wrap gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-800">Website Content Management</h1>
                    <p class="text-sm text-gray-500 mt-0.5">Manage articles, insights, and grainwatch publications</p>
                </div>
                <div class="flex gap-2">
                    <button onclick="showCreateModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-maroon text-white text-sm rounded-lg hover:bg-[#660000] transition-all shadow-sm">
                        <span class="material-symbols-outlined text-base">article</span>
                        New Article
                    </button>
                    <button onclick="showCreateInsightModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-maroon text-white text-sm rounded-lg hover:bg-[#660000] transition-all shadow-sm">
                        <span class="material-symbols-outlined text-base">lightbulb</span>
                        New Insight
                    </button>
                    <button onclick="showCreateGrainWatchModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-maroon text-white text-sm rounded-lg hover:bg-[#660000] transition-all shadow-sm">
                        <i class="fas fa-seedling text-base"></i>
                        New GrainWatch
                    </button>
                </div>
            </div>
            <div class="h-0.5 w-full header-accent-gradient mt-4 rounded-full"></div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
            <div class="stat-card bg-white rounded-xl p-4 shadow-sm border-l-4 border-maroon">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider">Total Articles</p>
                        <p class="text-2xl font-semibold text-gray-700 mt-1"><?= number_format($total_articles) ?></p>
                    </div>
                    <span class="material-symbols-outlined text-3xl text-maroon/40">article</span>
                </div>
            </div>
            <div class="stat-card bg-white rounded-xl p-4 shadow-sm border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider">Published</p>
                        <p class="text-2xl font-semibold text-gray-700 mt-1"><?= number_format($published_count) ?></p>
                    </div>
                    <span class="material-symbols-outlined text-3xl text-green-500/40">check_circle</span>
                </div>
            </div>
            <div class="stat-card bg-white rounded-xl p-4 shadow-sm border-l-4 border-amber-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider">Drafts</p>
                        <p class="text-2xl font-semibold text-gray-700 mt-1"><?= number_format($draft_count) ?></p>
                    </div>
                    <span class="material-symbols-outlined text-3xl text-amber-500/40">edit_note</span>
                </div>
            </div>
            <div class="stat-card bg-white rounded-xl p-4 shadow-sm border-l-4 border-gray-400">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider">Archived</p>
                        <p class="text-2xl font-semibold text-gray-700 mt-1"><?= number_format($archived_count) ?></p>
                    </div>
                    <span class="material-symbols-outlined text-3xl text-gray-400/40">archive</span>
                </div>
            </div>
            <div class="stat-card bg-white rounded-xl p-4 shadow-sm border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider">Insights</p>
                        <p class="text-2xl font-semibold text-gray-700 mt-1"><?= number_format($total_insights) ?></p>
                    </div>
                    <span class="material-symbols-outlined text-3xl text-blue-500/40">lightbulb</span>
                </div>
            </div>
            <div class="stat-card bg-white rounded-xl p-4 shadow-sm border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider">GrainWatch</p>
                        <p class="text-2xl font-semibold text-gray-700 mt-1"><?= number_format($total_grainwatch) ?></p>
                    </div>
                    <i class="fas fa-seedling text-2xl text-purple-500/40"></i>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="border-b border-gray-200 mb-5">
            <nav class="flex flex-wrap gap-1">
                <button onclick="switchTab('articles')" id="tab-articles" class="tab-btn px-4 py-2 text-sm font-medium rounded-t-lg transition-all border-b-2 border-maroon text-maroon">
                    <span class="material-symbols-outlined text-base align-middle mr-1">list_alt</span>
                    All Articles
                </button>
                <button onclick="switchTab('published')" id="tab-published" class="tab-btn px-4 py-2 text-sm font-medium rounded-t-lg transition-all border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                    <span class="material-symbols-outlined text-base align-middle mr-1">check_circle</span>
                    Published
                </button>
                <button onclick="switchTab('drafts')" id="tab-drafts" class="tab-btn px-4 py-2 text-sm font-medium rounded-t-lg transition-all border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                    <span class="material-symbols-outlined text-base align-middle mr-1">edit_note</span>
                    Drafts
                </button>
                <button onclick="switchTab('archived')" id="tab-archived" class="tab-btn px-4 py-2 text-sm font-medium rounded-t-lg transition-all border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                    <span class="material-symbols-outlined text-base align-middle mr-1">archive</span>
                    Archived
                </button>
                <button onclick="switchTab('insights')" id="tab-insights" class="tab-btn px-4 py-2 text-sm font-medium rounded-t-lg transition-all border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                    <span class="material-symbols-outlined text-base align-middle mr-1">lightbulb</span>
                    Insights
                </button>
                <button onclick="switchTab('grainwatch')" id="tab-grainwatch" class="tab-btn px-4 py-2 text-sm font-medium rounded-t-lg transition-all border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                    <i class="fas fa-seedling text-base mr-1"></i>
                    GrainWatch
                </button>
            </nav>
        </div>

        <!-- Filter Bar -->
        <div class="bg-white rounded-xl shadow-sm mb-5 p-3">
            <div class="flex flex-wrap gap-3 items-center">
                <div class="flex-1 min-w-[200px]">
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">search</span>
                        <input type="text" id="searchInput" placeholder="Search by title, category, or location..." 
                               class="search-input w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:border-maroon">
                    </div>
                </div>
                <select id="categoryFilter" class="px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:border-maroon bg-white">
                    <option value="">All Categories</option>
                    <option value="Market Policy">Market Policy</option>
                    <option value="Export">Export</option>
                    <option value="Import">Import</option>
                    <option value="Agriculture">Agriculture</option>
                    <option value="Trade">Trade</option>
                </select>
                <select id="statusFilter" class="px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:border-maroon bg-white">
                    <option value="">All Status</option>
                    <option value="published">Published</option>
                    <option value="draft">Draft</option>
                    <option value="archived">Archived</option>
                </select>
                <button onclick="applyFilters()" class="px-3 py-1.5 bg-maroon text-white text-sm rounded-lg hover:bg-[#660000] transition-all">
                    <span class="material-symbols-outlined text-base align-middle">filter_list</span>
                    Filter
                </button>
                <button id="bulkDeleteBtn" class="px-3 py-1.5 bg-red-500 text-white text-sm rounded-lg hover:bg-red-600 transition-all disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                    <span class="material-symbols-outlined text-base align-middle">delete</span>
                    Delete Selected
                </button>
            </div>
        </div>

        <!-- Content Container -->
        <div id="contentContainer" class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="text-center py-12">
                <div class="loading-spinner"></div>
                <p class="text-gray-500 text-sm mt-2">Loading articles...</p>
            </div>
        </div>

    </div>
</div>

<!-- Article Modal -->
<div id="articleModal" class="fixed inset-0 bg-black/50 hidden z-50 overflow-y-auto">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-xl w-full max-w-5xl max-h-[90vh] overflow-y-auto shadow-xl">
            <div class="sticky top-0 bg-white border-b border-gray-100 px-5 py-3 flex justify-between items-center">
                <h3 id="articleModalTitle" class="text-lg font-semibold text-gray-800">Create New Article</h3>
                <button onclick="closeModal('articleModal')" class="text-gray-400 hover:text-gray-600">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="p-5">
                <form id="articleForm">
                    <input type="hidden" id="articleId" name="id">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                        <div><label class="block text-sm text-gray-600 mb-1">Title <span class="text-red-500">*</span></label><input type="text" id="articleTitle" name="title" required class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:border-maroon"></div>
                        <div><label class="block text-sm text-gray-600 mb-1">Category</label><input type="text" id="articleCategory" name="category" class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:border-maroon" placeholder="e.g., Agriculture"></div>
                        <div><label class="block text-sm text-gray-600 mb-1">Location</label><input type="text" id="articleLocation" name="location" class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:border-maroon" placeholder="e.g., Kenya"></div>
                        <div><label class="block text-sm text-gray-600 mb-1">Source</label><input type="text" id="articleSource" name="source" class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:border-maroon" placeholder="e.g., Reuters"></div>
                        <div class="md:col-span-2"><label class="block text-sm text-gray-600 mb-1">Cover Image URL</label><input type="url" id="articleImage" name="image" class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:border-maroon" onchange="previewImage('articleImage', 'articleImagePreview')"><img id="articleImagePreview" class="image-preview hidden mt-2 max-h-12 rounded"></div>
                    </div>
                    <div class="mb-5"><label class="block text-sm text-gray-600 mb-1">Content <span class="text-red-500">*</span></label><div id="articleEditor" class="border border-gray-200 rounded-lg" style="height: 350px;"></div></div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                        <button type="button" onclick="closeModal('articleModal')" class="px-4 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="button" onclick="saveArticle('draft')" class="px-4 py-1.5 text-sm bg-amber-500 text-white rounded-lg hover:bg-amber-600">Save Draft</button>
                        <button type="button" onclick="saveArticle('published')" class="px-4 py-1.5 text-sm bg-maroon text-white rounded-lg hover:bg-[#660000]">Publish</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Insight Modal -->
<div id="insightModal" class="fixed inset-0 bg-black/50 hidden z-50 overflow-y-auto">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto shadow-xl">
            <div class="sticky top-0 bg-white border-b border-gray-100 px-5 py-3 flex justify-between items-center">
                <h3 id="insightModalTitle" class="text-lg font-semibold text-gray-800">Create New Insight</h3>
                <button onclick="closeModal('insightModal')" class="text-gray-400 hover:text-gray-600"><span class="material-symbols-outlined">close</span></button>
            </div>
            <div class="p-5">
                <form id="insightForm">
                    <input type="hidden" id="insightId" name="id">
                    <div class="mb-5"><label class="block text-sm text-gray-600 mb-1">Title <span class="text-red-500">*</span></label><input type="text" id="insightTitle" name="title" required class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:border-maroon"></div>
                    <div class="mb-5"><label class="block text-sm text-gray-600 mb-1">Content <span class="text-red-500">*</span></label><div id="insightEditor" class="border border-gray-200 rounded-lg" style="height: 350px;"></div></div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                        <button type="button" onclick="closeModal('insightModal')" class="px-4 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="button" onclick="saveInsight()" class="px-4 py-1.5 text-sm bg-maroon text-white rounded-lg hover:bg-[#660000]">Save Insight</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- GrainWatch Modal -->
<div id="grainwatchModal" class="fixed inset-0 bg-black/50 hidden z-50 overflow-y-auto">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto shadow-xl">
            <div class="sticky top-0 bg-white border-b border-gray-100 px-5 py-3 flex justify-between items-center">
                <h3 id="grainwatchModalTitle" class="text-lg font-semibold text-gray-800">Create New GrainWatch</h3>
                <button onclick="closeModal('grainwatchModal')" class="text-gray-400 hover:text-gray-600"><span class="material-symbols-outlined">close</span></button>
            </div>
            <div class="p-5">
                <form id="grainwatchForm" enctype="multipart/form-data">
                    <input type="hidden" id="grainwatchId" name="id">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                        <div><label class="block text-sm text-gray-600 mb-1">Heading <span class="text-red-500">*</span></label><input type="text" id="grainwatchHeading" name="heading" required class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:border-maroon"></div>
                        <div><label class="block text-sm text-gray-600 mb-1">Category <span class="text-red-500">*</span></label><select id="grainwatchCategory" name="category" required class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:border-maroon bg-white"><option value="">Select Category</option><option value="grain watch">Grain Watch</option><option value="grain standards">Grain Standards</option><option value="policy briefs">Policy Briefs</option><option value="reports">Reports</option></select></div>
                        <div><label class="block text-sm text-gray-600 mb-1">Cover Image URL</label><input type="url" id="grainwatchImage" name="image" class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:border-maroon" onchange="previewImage('grainwatchImage', 'grainwatchImagePreview')"><img id="grainwatchImagePreview" class="image-preview hidden mt-2 max-h-12 rounded"></div>
                        <div><label class="block text-sm text-gray-600 mb-1">PDF Document</label><input type="file" id="grainwatchDocument" name="document" accept=".pdf" class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:border-maroon" onchange="previewDocument()"><div id="documentPreview" class="document-preview hidden mt-2 text-sm text-gray-500"><span class="material-symbols-outlined text-red-500 text-base align-middle">picture_as_pdf</span><span id="documentName" class="ml-1"></span></div></div>
                    </div>
                    <div class="mb-5"><label class="block text-sm text-gray-600 mb-1">Description <span class="text-red-500">*</span></label><textarea id="grainwatchDescription" name="description" rows="4" required class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:border-maroon"></textarea></div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                        <button type="button" onclick="closeModal('grainwatchModal')" class="px-4 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="button" onclick="saveGrainWatch()" class="px-4 py-1.5 text-sm bg-maroon text-white rounded-lg hover:bg-[#660000]">Save GrainWatch</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl w-full max-w-md shadow-xl">
        <div class="p-5">
            <div class="flex items-center gap-2 mb-3"><span class="material-symbols-outlined text-red-500">warning</span><h3 id="deleteModalTitle" class="text-lg font-semibold text-gray-800">Delete Item</h3></div>
            <p id="deleteModalText" class="text-sm text-gray-500 mb-3">Are you sure you want to delete this item? This action cannot be undone.</p>
            <div class="flex justify-end gap-2">
                <button onclick="closeModal('deleteModal')" class="px-4 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                <button onclick="confirmDelete()" class="px-4 py-1.5 text-sm bg-red-500 text-white rounded-lg hover:bg-red-600">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Delete Form -->
<form id="bulkDeleteForm" method="POST" action="">
    <input type="hidden" name="delete_type" id="bulkDeleteType">
    <input type="hidden" name="selected_items" id="bulkDeleteItems">
</form>

<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://kit.fontawesome.com/a5f8b2f8f7.js" crossorigin="anonymous"></script>

<script>
// Global variables
let currentTab = 'articles';
let currentPage = 1;
let itemsPerPage = 20;
let totalPages = 1;
let totalItems = 0;
let articleQuill, insightQuill;
let selectedItems = new Set();
let currentDeleteId = null, currentDeleteType = null;

// Initialize Quill editors
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('articleEditor')) {
        articleQuill = new Quill('#articleEditor', {
            theme: 'snow',
            placeholder: 'Write your article content here...',
            modules: { toolbar: [[{ 'header': [1, 2, 3, false] }], ['bold', 'italic', 'underline', 'strike'], [{ 'color': [] }, { 'background': [] }], [{ 'list': 'ordered'}, { 'list': 'bullet' }], [{ 'indent': '-1'}, { 'indent': '+1' }], [{ 'align': [] }], ['blockquote', 'code-block'], ['link', 'image'], ['clean']] }
        });
    }
    
    if (document.getElementById('insightEditor')) {
        insightQuill = new Quill('#insightEditor', {
            theme: 'snow',
            placeholder: 'Write your insight content here...',
            modules: { toolbar: [[{ 'header': [1, 2, 3, false] }], ['bold', 'italic', 'underline', 'strike'], [{ 'color': [] }, { 'background': [] }], [{ 'list': 'ordered'}, { 'list': 'bullet' }], [{ 'indent': '-1'}, { 'indent': '+1' }], [{ 'align': [] }], ['blockquote', 'code-block'], ['link'], ['clean']] }
        });
    }
    
    loadArticles();
});

async function loadArticles() {
    showLoading();
    const searchTerm = document.getElementById('searchInput')?.value || '';
    const category = document.getElementById('categoryFilter')?.value || '';
    let status = '';
    
    if (currentTab === 'articles') status = document.getElementById('statusFilter')?.value || '';
    else if (currentTab === 'published') status = 'published';
    else if (currentTab === 'drafts') status = 'draft';
    else if (currentTab === 'archived') status = 'archived';
    
    let url = `api/articles_optimized.php?page=${currentPage}&limit=${itemsPerPage}`;
    if (searchTerm) url += `&search=${encodeURIComponent(searchTerm)}`;
    if (category) url += `&category=${encodeURIComponent(category)}`;
    if (status) url += `&status=${encodeURIComponent(status)}`;
    
    try {
        const response = await fetch(url);
        const result = await response.json();
        
        if (result.data) {
            totalItems = result.total;
            totalPages = result.total_pages;
            renderArticlesOptimized(result.data);
        } else {
            renderArticlesOptimized([]);
        }
    } catch (error) {
        console.error('Error loading articles:', error);
        // Fallback to original API if optimized fails
        try {
            const fallbackUrl = `api/articles.php`;
            const response = await fetch(fallbackUrl);
            const articles = await response.json();
            totalItems = articles.length;
            totalPages = Math.ceil(totalItems / itemsPerPage);
            renderArticlesOptimized(articles.slice(0, itemsPerPage));
        } catch (fallbackError) {
            showError('Failed to load articles');
        }
    }
}

async function loadInsights() {
    showLoading();
    const searchTerm = document.getElementById('searchInput')?.value || '';
    let url = `api/insights.php`;
    if (searchTerm) url += `?search=${encodeURIComponent(searchTerm)}`;
    
    try {
        const response = await fetch(url);
        const insights = await response.json();
        totalItems = insights.length;
        totalPages = Math.ceil(totalItems / itemsPerPage);
        const start = (currentPage - 1) * itemsPerPage;
        const paginatedData = insights.slice(start, start + itemsPerPage);
        renderInsightsOptimized(paginatedData);
    } catch (error) {
        console.error('Error loading insights:', error);
        showError('Failed to load insights');
    }
}

async function loadGrainWatch() {
    showLoading();
    const searchTerm = document.getElementById('searchInput')?.value || '';
    const category = document.getElementById('categoryFilter')?.value || '';
    let url = `api/grainwatch.php`;
    let params = [];
    if (searchTerm) params.push(`search=${encodeURIComponent(searchTerm)}`);
    if (category) params.push(`category=${encodeURIComponent(category)}`);
    if (params.length) url += `?${params.join('&')}`;
    
    try {
        const response = await fetch(url);
        const grainwatch = await response.json();
        totalItems = grainwatch.length;
        totalPages = Math.ceil(totalItems / itemsPerPage);
        const start = (currentPage - 1) * itemsPerPage;
        const paginatedData = grainwatch.slice(start, start + itemsPerPage);
        renderGrainWatchOptimized(paginatedData);
    } catch (error) {
        console.error('Error loading grainwatch:', error);
        showError('Failed to load grainwatch');
    }
}

function renderArticlesOptimized(articles) {
    if (!articles || articles.length === 0) {
        document.getElementById('contentContainer').innerHTML = `<div class="text-center py-12"><span class="material-symbols-outlined text-4xl text-gray-300">inbox</span><p class="text-gray-400 text-sm mt-2">No articles found</p></div>`;
        return;
    }
    
    let html = `<div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-gray-50 border-b border-gray-100"><tr>
        <th class="checkbox-col px-3 py-3 text-left"><input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)" class="rounded border-gray-300"></th>
        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-24">Actions</th>
    <tr></thead><tbody class="divide-y divide-gray-50">`;
    
    for (const article of articles) {
        const statusClass = article.status === 'published' ? 'status-published' : (article.status === 'draft' ? 'status-draft' : 'status-archived');
        const statusText = article.status === 'published' ? 'Published' : (article.status === 'draft' ? 'Draft' : 'Archived');
        
        html += `<tr class="table-row-hover">
            <td class="px-3 py-3"><input type="checkbox" class="row-checkbox rounded border-gray-300" value="${article.id}" onchange="toggleSelectItem(this, ${article.id})"></td>
            <td class="px-4 py-3"><div class="flex items-center gap-2">${article.image ? `<img src="${article.image}" class="w-6 h-6 object-cover rounded">` : ''}<span class="text-gray-800">${escapeHtml(article.title)}</span></div></td>
            <td class="px-4 py-3"><span class="category-badge">${escapeHtml(article.category) || '-'}</span></td>
            <td class="px-4 py-3 text-gray-500">${escapeHtml(article.location) || '-'}</td>
            <td class="px-4 py-3"><span class="status-badge ${statusClass}">${statusText}</span></td>
            <td class="px-4 py-3 text-gray-500 text-xs">${formatDate(article.created_at)}</span></div></td>
            <td class="px-4 py-3"><div class="flex items-center justify-center gap-1">
                <button onclick="editArticle(${article.id})" class="p-1 rounded hover:bg-blue-50" title="Edit"><span class="material-symbols-outlined text-blue-500 text-sm">edit</span></button>
                <button onclick="archiveArticle(${article.id})" class="p-1 rounded hover:bg-yellow-50" title="Archive"><span class="material-symbols-outlined text-yellow-500 text-sm">archive</span></button>
                <button onclick="deleteArticle(${article.id})" class="p-1 rounded hover:bg-red-50" title="Delete"><span class="material-symbols-outlined text-red-400 text-sm">delete</span></button>
            </div></td>
        </tr>`;
    }
    
    html += `</tbody></table></div>`;
    
    if (totalPages > 1) {
        html += renderPaginationHTML();
    }
    
    document.getElementById('contentContainer').innerHTML = html;
    selectedItems.clear();
    updateBulkDeleteButton();
}

function renderInsightsOptimized(insights) {
    if (!insights || insights.length === 0) {
        document.getElementById('contentContainer').innerHTML = `<div class="text-center py-12"><span class="material-symbols-outlined text-4xl text-gray-300">lightbulb</span><p class="text-gray-400 text-sm mt-2">No insights found</p></div>`;
        return;
    }
    
    let html = `<div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-gray-50 border-b border-gray-100"><tr>
        <th class="checkbox-col px-3 py-3 text-left"><input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)" class="rounded border-gray-300"></th>
        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-24">Actions</th>
    </tr></thead><tbody class="divide-y divide-gray-50">`;
    
    for (const insight of insights) {
        html += `<tr class="table-row-hover">
            <td class="px-3 py-3"><input type="checkbox" class="row-checkbox rounded border-gray-300" value="${insight.id}" onchange="toggleSelectItem(this, ${insight.id})"></td>
            <td class="px-4 py-3"><span class="text-gray-800">${escapeHtml(insight.title)}</span><br><span class="text-gray-400 text-xs">${stripHtml(insight.body).substring(0, 80)}...</span></td>
            <td class="px-4 py-3 text-gray-500 text-xs">${formatDate(insight.created_at)}</span></div></td>
            <td class="px-4 py-3"><div class="flex items-center justify-center gap-1">
                <button onclick="editInsight(${insight.id})" class="p-1 rounded hover:bg-blue-50"><span class="material-symbols-outlined text-blue-500 text-sm">edit</span></button>
                <button onclick="deleteInsight(${insight.id})" class="p-1 rounded hover:bg-red-50"><span class="material-symbols-outlined text-red-400 text-sm">delete</span></button>
            </div></td>
        </tr>`;
    }
    
    html += `</tbody></table></div>`;
    
    if (totalPages > 1) {
        html += renderPaginationHTML();
    }
    
    document.getElementById('contentContainer').innerHTML = html;
    selectedItems.clear();
    updateBulkDeleteButton();
}

function renderGrainWatchOptimized(grainwatch) {
    if (!grainwatch || grainwatch.length === 0) {
        document.getElementById('contentContainer').innerHTML = `<div class="text-center py-12"><i class="fas fa-seedling text-4xl text-gray-300"></i><p class="text-gray-400 text-sm mt-2">No grainwatch entries found</p></div>`;
        return;
    }
    
    let html = `<div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-gray-50 border-b border-gray-100"><tr>
        <th class="checkbox-col px-3 py-3 text-left"><input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)" class="rounded border-gray-300"></th>
        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Heading</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-24">Actions</th>
    </tr></thead><tbody class="divide-y divide-gray-50">`;
    
    for (const gw of grainwatch) {
        html += `<tr class="table-row-hover">
            <td class="px-3 py-3"><input type="checkbox" class="row-checkbox rounded border-gray-300" value="${gw.id}" onchange="toggleSelectItem(this, ${gw.id})"></td>
            <td class="px-4 py-3"><span class="text-gray-800">${escapeHtml(gw.heading)}</span></td>
            <td class="px-4 py-3"><span class="category-badge">${escapeHtml(gw.category)}</span></td>
            <td class="px-4 py-3 text-gray-500 text-xs">${escapeHtml(gw.description).substring(0, 100)}...</td>
            <td class="px-4 py-3"><div class="flex items-center justify-center gap-1">
                <button onclick="editGrainWatch(${gw.id})" class="p-1 rounded hover:bg-blue-50"><span class="material-symbols-outlined text-blue-500 text-sm">edit</span></button>
                <button onclick="deleteGrainWatch(${gw.id})" class="p-1 rounded hover:bg-red-50"><span class="material-symbols-outlined text-red-400 text-sm">delete</span></button>
            </div></td>
        </tr>`;
    }
    
    html += `</tbody></table></div>`;
    
    if (totalPages > 1) {
        html += renderPaginationHTML();
    }
    
    document.getElementById('contentContainer').innerHTML = html;
    selectedItems.clear();
    updateBulkDeleteButton();
}

function renderPaginationHTML() {
    let pages = [];
    if (totalPages <= 7) {
        for (let i = 1; i <= totalPages; i++) pages.push(i);
    } else {
        pages.push(1);
        if (currentPage > 3) pages.push('...');
        let start = Math.max(2, currentPage - 1);
        let end = Math.min(totalPages - 1, currentPage + 1);
        if (currentPage <= 3) end = 4;
        if (currentPage >= totalPages - 2) start = totalPages - 3;
        for (let i = start; i <= end; i++) pages.push(i);
        if (currentPage < totalPages - 2) pages.push('...');
        pages.push(totalPages);
    }
    
    let html = `<div class="px-4 py-3 border-t border-gray-100 flex justify-between items-center flex-wrap gap-3">
        <div class="flex items-center gap-2">
            <select id="itemsPerPageSelect" onchange="changeItemsPerPage()" class="px-2 py-1 text-sm border border-gray-200 rounded bg-white">
                <option value="20" ${itemsPerPage === 20 ? 'selected' : ''}>20</option>
                <option value="50" ${itemsPerPage === 50 ? 'selected' : ''}>50</option>
                <option value="100" ${itemsPerPage === 100 ? 'selected' : ''}>100</option>
            </select>
            <span class="text-xs text-gray-400">per page</span>
        </div>
        <div class="flex items-center gap-1">
            <button onclick="goToPage(${currentPage - 1})" class="pagination-btn px-2 py-1 rounded border border-gray-200 ${currentPage === 1 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-50'}" ${currentPage === 1 ? 'disabled' : ''}>
                <span class="material-symbols-outlined text-sm">chevron_left</span>
            </button>`;
    
    for (let page of pages) {
        if (page === '...') {
            html += `<span class="pagination-ellipsis text-sm">...</span>`;
        } else {
            html += `<button onclick="goToPage(${page})" class="pagination-btn w-8 h-8 rounded text-sm ${currentPage === page ? 'active-page bg-maroon text-white' : 'border border-gray-200 hover:bg-gray-50'}">${page}</button>`;
        }
    }
    
    html += `<button onclick="goToPage(${currentPage + 1})" class="pagination-btn px-2 py-1 rounded border border-gray-200 ${currentPage === totalPages ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-50'}" ${currentPage === totalPages ? 'disabled' : ''}>
                <span class="material-symbols-outlined text-sm">chevron_right</span>
            </button>
        </div>
        <span class="text-xs text-gray-400">${totalItems} total items</span>
    </div>`;
    
    return html;
}

async function goToPage(page) {
    if (page < 1 || page > totalPages) return;
    currentPage = page;
    if (currentTab === 'articles') await loadArticles();
    else if (currentTab === 'insights') await loadInsights();
    else if (currentTab === 'grainwatch') await loadGrainWatch();
}

async function changeItemsPerPage() {
    const select = document.getElementById('itemsPerPageSelect');
    if (select) {
        itemsPerPage = parseInt(select.value);
        currentPage = 1;
        if (currentTab === 'articles') await loadArticles();
        else if (currentTab === 'insights') await loadInsights();
        else if (currentTab === 'grainwatch') await loadGrainWatch();
    }
}

async function applyFilters() {
    currentPage = 1;
    if (currentTab === 'articles') await loadArticles();
    else if (currentTab === 'insights') await loadInsights();
    else if (currentTab === 'grainwatch') await loadGrainWatch();
    else await loadArticles();
}

function switchTab(tab) {
    currentTab = tab;
    currentPage = 1;
    document.querySelectorAll('.tab-btn').forEach(t => {
        t.classList.remove('border-maroon', 'text-maroon');
        t.classList.add('border-transparent', 'text-gray-500');
    });
    document.getElementById(`tab-${tab}`).classList.add('border-maroon', 'text-maroon');
    document.getElementById(`tab-${tab}`).classList.remove('border-transparent', 'text-gray-500');
    
    if (tab === 'articles') loadArticles();
    else if (tab === 'published') loadArticles();
    else if (tab === 'drafts') loadArticles();
    else if (tab === 'archived') loadArticles();
    else if (tab === 'insights') loadInsights();
    else if (tab === 'grainwatch') loadGrainWatch();
}

// CRUD Operations
async function saveArticle(status) {
    const data = {
        title: document.getElementById('articleTitle').value,
        body: articleQuill.root.innerHTML,
        image: document.getElementById('articleImage').value || null,
        category: document.getElementById('articleCategory').value,
        source: document.getElementById('articleSource').value || null,
        location: document.getElementById('articleLocation').value || null,
        status: status
    };
    const id = document.getElementById('articleId').value;
    if (!data.title || !data.category) { alert('Please fill in required fields'); return; }
    
    try {
        let response;
        if (id) {
            response = await fetch(`api/articles.php/${id}`, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        } else {
            response = await fetch('api/articles.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        }
        if (response.ok) {
            alert('Article saved successfully!');
            closeModal('articleModal');
            loadArticles();
        } else {
            alert('Failed to save article');
        }
    } catch (error) { alert('Failed to save article'); }
}

async function editArticle(id) {
    try {
        const response = await fetch(`api/articles.php/${id}`);
        const article = await response.json();
        document.getElementById('articleModalTitle').innerHTML = 'Edit Article';
        document.getElementById('articleId').value = article.id;
        document.getElementById('articleTitle').value = article.title;
        document.getElementById('articleCategory').value = article.category;
        document.getElementById('articleLocation').value = article.location || '';
        document.getElementById('articleSource').value = article.source || '';
        document.getElementById('articleImage').value = article.image || '';
        articleQuill.root.innerHTML = article.body;
        if (article.image) previewImage('articleImage', 'articleImagePreview');
        openModal('articleModal');
    } catch (error) { alert('Failed to load article'); }
}

async function archiveArticle(id) {
    if (!confirm('Archive this article?')) return;
    try {
        await fetch(`api/articles.php/${id}`, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ status: 'archived' }) });
        loadArticles();
        alert('Article archived');
    } catch (error) { alert('Failed to archive'); }
}

async function restoreArticle(id) {
    if (!confirm('Restore this article?')) return;
    try {
        await fetch(`api/articles.php/${id}`, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ status: 'draft' }) });
        loadArticles();
        alert('Article restored');
    } catch (error) { alert('Failed to restore'); }
}

async function saveInsight() {
    const data = { title: document.getElementById('insightTitle').value, body: insightQuill.root.innerHTML };
    const id = document.getElementById('insightId').value;
    if (!data.title || !data.body || data.body === '<p><br></p>') { alert('Please fill in all fields'); return; }
    
    try {
        let response;
        if (id) {
            response = await fetch(`api/insights.php/${id}`, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        } else {
            response = await fetch('api/insights.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        }
        if (response.ok) {
            alert('Insight saved successfully!');
            closeModal('insightModal');
            loadInsights();
        } else {
            alert('Failed to save insight');
        }
    } catch (error) { alert('Failed to save insight'); }
}

async function editInsight(id) {
    try {
        const response = await fetch(`api/insights.php/${id}`);
        const insight = await response.json();
        document.getElementById('insightModalTitle').innerHTML = 'Edit Insight';
        document.getElementById('insightId').value = insight.id;
        document.getElementById('insightTitle').value = insight.title;
        insightQuill.root.innerHTML = insight.body;
        openModal('insightModal');
    } catch (error) { alert('Failed to load insight'); }
}

async function saveGrainWatch() {
    const formData = new FormData(document.getElementById('grainwatchForm'));
    const id = formData.get('id');
    const heading = formData.get('heading');
    const category = formData.get('category');
    const description = formData.get('description');
    
    if (!heading || !category || !description) { alert('Please fill in required fields'); return; }
    
    const data = { heading, category, image: formData.get('image') || null, description, document_path: null };
    const file = document.getElementById('grainwatchDocument').files[0];
    
    try {
        if (file) {
            const uploadForm = new FormData();
            uploadForm.append('document', file);
            const uploadRes = await fetch('api/upload.php', { method: 'POST', body: uploadForm });
            const uploadResult = await uploadRes.json();
            if (uploadResult.success) data.document_path = uploadResult.filePath;
        }
        
        let response;
        if (id) {
            response = await fetch(`api/grainwatch.php/${id}`, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        } else {
            response = await fetch('api/grainwatch.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        }
        if (response.ok) {
            alert('GrainWatch saved successfully!');
            closeModal('grainwatchModal');
            loadGrainWatch();
        } else {
            alert('Failed to save grainwatch');
        }
    } catch (error) { alert('Failed to save grainwatch'); }
}

async function editGrainWatch(id) {
    try {
        const response = await fetch(`api/grainwatch.php/${id}`);
        const gw = await response.json();
        document.getElementById('grainwatchModalTitle').innerHTML = 'Edit GrainWatch';
        document.getElementById('grainwatchId').value = gw.id;
        document.getElementById('grainwatchHeading').value = gw.heading;
        document.getElementById('grainwatchCategory').value = gw.category;
        document.getElementById('grainwatchImage').value = gw.image || '';
        document.getElementById('grainwatchDescription').value = gw.description;
        if (gw.image) previewImage('grainwatchImage', 'grainwatchImagePreview');
        openModal('grainwatchModal');
    } catch (error) { alert('Failed to load grainwatch'); }
}

function deleteArticle(id) { currentDeleteId = id; currentDeleteType = 'article'; openModal('deleteModal'); }
function deleteInsight(id) { currentDeleteId = id; currentDeleteType = 'insight'; openModal('deleteModal'); }
function deleteGrainWatch(id) { currentDeleteId = id; currentDeleteType = 'grainwatch'; openModal('deleteModal'); }

async function confirmDelete() {
    try {
        if (currentDeleteType === 'article') await fetch(`api/articles.php/${currentDeleteId}`, { method: 'DELETE' });
        else if (currentDeleteType === 'insight') await fetch(`api/insights.php/${currentDeleteId}`, { method: 'DELETE' });
        else if (currentDeleteType === 'grainwatch') await fetch(`api/grainwatch.php/${currentDeleteId}`, { method: 'DELETE' });
        
        closeModal('deleteModal');
        if (currentDeleteType === 'article') loadArticles();
        else if (currentDeleteType === 'insight') loadInsights();
        else if (currentDeleteType === 'grainwatch') loadGrainWatch();
        alert('Item deleted successfully!');
    } catch (error) { alert('Failed to delete'); }
}

// Bulk Delete Functions
function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('.row-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
        const id = parseInt(cb.value);
        if (checkbox.checked) selectedItems.add(id);
        else selectedItems.delete(id);
    });
    updateBulkDeleteButton();
}

function toggleSelectItem(checkbox, id) {
    if (checkbox.checked) selectedItems.add(id);
    else selectedItems.delete(id);
    updateBulkDeleteButton();
}

function updateBulkDeleteButton() {
    const btn = document.getElementById('bulkDeleteBtn');
    if (btn) btn.disabled = selectedItems.size === 0;
}

function submitBulkDelete() {
    if (selectedItems.size === 0) { alert('Please select items to delete.'); return; }
    if (confirm(`Are you sure you want to delete ${selectedItems.size} selected item(s)? This action cannot be undone.`)) {
        document.getElementById('bulkDeleteType').value = currentTab === 'articles' ? 'article' : (currentTab === 'insights' ? 'insight' : 'grainwatch');
        document.getElementById('bulkDeleteItems').value = Array.from(selectedItems).join(',');
        document.getElementById('bulkDeleteForm').submit();
    }
}

// Utility functions
function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
function showCreateModal() { resetArticleForm(); openModal('articleModal'); }
function showCreateInsightModal() { resetInsightForm(); openModal('insightModal'); }
function showCreateGrainWatchModal() { resetGrainWatchForm(); openModal('grainwatchModal'); }

function resetArticleForm() {
    document.getElementById('articleModalTitle').innerHTML = 'Create New Article';
    document.getElementById('articleId').value = '';
    document.getElementById('articleTitle').value = '';
    document.getElementById('articleCategory').value = '';
    document.getElementById('articleLocation').value = '';
    document.getElementById('articleSource').value = '';
    document.getElementById('articleImage').value = '';
    if (articleQuill) articleQuill.setText('');
    document.getElementById('articleImagePreview').classList.add('hidden');
}

function resetInsightForm() {
    document.getElementById('insightModalTitle').innerHTML = 'Create New Insight';
    document.getElementById('insightId').value = '';
    document.getElementById('insightTitle').value = '';
    if (insightQuill) insightQuill.setText('');
}

function resetGrainWatchForm() {
    document.getElementById('grainwatchModalTitle').innerHTML = 'Create New GrainWatch';
    document.getElementById('grainwatchId').value = '';
    document.getElementById('grainwatchHeading').value = '';
    document.getElementById('grainwatchCategory').value = '';
    document.getElementById('grainwatchImage').value = '';
    document.getElementById('grainwatchDescription').value = '';
    document.getElementById('grainwatchImagePreview').classList.add('hidden');
    document.getElementById('documentPreview').classList.add('hidden');
    document.getElementById('grainwatchDocument').value = '';
}

function previewImage(inputId, previewId) {
    const url = document.getElementById(inputId).value;
    const preview = document.getElementById(previewId);
    if (url) { preview.src = url; preview.classList.remove('hidden'); }
    else preview.classList.add('hidden');
}

function previewDocument() {
    const file = document.getElementById('grainwatchDocument').files[0];
    if (file && file.type === 'application/pdf') {
        document.getElementById('documentName').innerHTML = file.name;
        document.getElementById('documentPreview').classList.remove('hidden');
    }
}

function showLoading() { document.getElementById('contentContainer').innerHTML = `<div class="text-center py-12"><div class="loading-spinner"></div><p class="text-gray-500 text-sm mt-2">Loading...</p></div>`; }
function showError(msg) { document.getElementById('contentContainer').innerHTML = `<div class="text-center py-12 text-red-500 text-sm">${msg}</div>`; }
function escapeHtml(str) { if (!str) return ''; return str.replace(/[&<>]/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[m])); }
function stripHtml(html) { if (!html) return ''; const div = document.createElement('div'); div.innerHTML = html; return div.textContent || div.innerText || ''; }
function formatDate(dateStr) { if (!dateStr) return '-'; return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: '2-digit' }); }

// Event listeners
document.getElementById('searchInput')?.addEventListener('input', () => { currentPage = 1; applyFilters(); });
document.getElementById('categoryFilter')?.addEventListener('change', () => { currentPage = 1; applyFilters(); });
document.getElementById('statusFilter')?.addEventListener('change', () => { currentPage = 1; applyFilters(); });
document.getElementById('bulkDeleteBtn')?.addEventListener('click', submitBulkDelete);
</script>

