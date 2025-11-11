<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Check if user is logged in and is admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../admin/index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ratin Website Management System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.6/quill.snow.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f8f8;
            margin: 0;
            padding: 20px;
        }
        
        .header {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .header h1 {
            margin: 0;
            color: #333;
            font-size: 24px;
        }
        
        .header-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-primary {
            background-color: rgba(180, 80, 50, 1);
            border-color: rgba(180, 80, 50, 1);
            color: white;
            font-weight: bold;
        }
        
        .btn-primary:hover {
            background-color: rgba(160, 60, 30, 1);
            border-color: rgba(160, 60, 30, 1);
        }
        
        .container-main {
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .nav-tabs .nav-link {
            color: #666;
            font-weight: 500;
            border: none;
            padding: 15px 25px;
        }
        
        .nav-tabs .nav-link.active {
            color: rgba(180, 80, 50, 1);
            background-color: white;
            border-bottom: 3px solid rgba(180, 80, 50, 1);
            font-weight: bold;
        }
        
        .tab-content {
            padding: 30px;
        }
        
        /* Form Styles */
        .form-section {
            margin-bottom: 25px;
        }
        
        .form-section h5 {
            color: #333;
            margin-bottom: 15px;
            font-weight: bold;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            font-weight: bold;
            color: #333;
            margin-bottom: 8px;
            display: block;
        }
        
        .required::after {
            content: " *";
            color: #dc3545;
        }
        
        input[type="text"],
        input[type="url"],
        input[type="file"],
        select,
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: rgba(180, 80, 50, 0.5);
            box-shadow: 0 0 5px rgba(180, 80, 50, 0.3);
        }
        
        /* Rich Text Editor */
        .editor-container {
            border: 1px solid #ccc;
            border-radius: 5px;
            background: white;
        }
        
        .ql-editor {
            min-height: 200px;
        }
        
        /* Tables */
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #333;
            border-top: none;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-draft {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-published {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-unpublished {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 4px 8px;
            font-size: 12px;
        }
        
        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }
        
        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
        }
        
        .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
        }
        
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        
        /* Filter Section */
        .filter-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        /* Image Preview */
        .image-preview {
            max-width: 200px;
            max-height: 150px;
            border-radius: 5px;
            margin-top: 10px;
            border: 1px solid #ddd;
        }
        
        /* Document Preview */
        .document-preview {
            max-width: 200px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            margin-top: 10px;
            border: 1px solid #ddd;
        }
        
        .document-preview i {
            font-size: 24px;
            color: #dc3545;
            margin-right: 8px;
        }
        
        /* Loading and Empty States */
        .loading {
            text-align: center;
            padding: 40px;
        }
        
        .spinner-border {
            color: rgba(180, 80, 50, 1);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 20px;
            color: #ccc;
        }
        
        /* Toast notification */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }
            
            .header-buttons {
                margin-top: 15px;
                justify-content: center;
            }
            
            .tab-content {
                padding: 15px;
            }
            
            .filter-row {
                flex-direction: column;
            }
            
            .filter-group {
                min-width: 100%;
            }
            
            .action-buttons {
                justify-content: center;
            }
            
            .table-responsive {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <!-- Toast container -->
    <div id="toastContainer" class="toast-container"></div>
    
    <!-- Header -->
    <div class="header">
        <h1><i class="fas fa-newspaper"></i> Ratin Website Management System</h1>
        <div class="header-buttons">
            <select id="categoryFilter" class="form-select" style="width: auto;">
                <option value="">All Categories</option>
                <option value="Market Policy">Market Policy</option>
                <option value="Export">Export</option>
                <option value="Import">Import</option>
                <option value="Agriculture">Agriculture</option>
                <option value="Trade">Trade</option>
            </select>
            <button class="btn btn-primary" onclick="showCreateModal()">
                <i class="fas fa-plus"></i> Create Article
            </button>
            <button class="btn btn-primary" onclick="showCreateInsightModal()">
                <i class="fas fa-lightbulb"></i> Create Insight
            </button>
            <button class="btn btn-primary" onclick="showCreateGrainWatchModal()">
                <i class="fas fa-binoculars"></i> Create GrainWatch
            </button>
            <button class="btn btn-primary" onclick="window.location.href='https://beta.ratin.net/ratinapp/admin'">
                Admin Portal
            </button>
        </div>
    </div>

    <!-- Main Container -->
    <div class="container-main">
        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs" id="mainTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="articles-tab" data-bs-toggle="tab" data-bs-target="#articles" type="button" role="tab">
                    <i class="fas fa-list"></i> All Articles
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="published-tab" data-bs-toggle="tab" data-bs-target="#published" type="button" role="tab">
                    <i class="fas fa-check-circle"></i> Published
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="drafts-tab" data-bs-toggle="tab" data-bs-target="#drafts" type="button" role="tab">
                    <i class="fas fa-edit"></i> Drafts
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="insights-tab" data-bs-toggle="tab" data-bs-target="#insights" type="button" role="tab">
                    <i class="fas fa-lightbulb"></i> Insights
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="grainwatch-tab" data-bs-toggle="tab" data-bs-target="#grainwatch" type="button" role="tab">
                    <i class="fas fa-binoculars"></i> GrainWatch
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="mainTabContent">
            <!-- All Articles Tab -->
            <div class="tab-pane fade show active" id="articles" role="tabpanel">
                <div class="filter-section">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>Search</label>
                            <input type="text" id="searchInput" class="form-control" placeholder="Search articles...">
                        </div>
                        <div class="filter-group">
                            <label>Category</label>
                            <select id="categoryFilterTab" class="form-select">
                                <option value="">All Categories</option>
                                <option value="Market Policy">Market Policy</option>
                                <option value="Export">Export</option>
                                <option value="Import">Import</option>
                                <option value="Agriculture">Agriculture</option>
                                <option value="Trade">Trade</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Status</label>
                            <select id="statusFilter" class="form-select">
                                <option value="">All Status</option>
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                                <option value="unpublished">Unpublished</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <button class="btn btn-primary" onclick="applyFilters()">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>
                    </div>
                </div>

                <div id="articlesTableContainer">
                    <!-- Articles table will be loaded here -->
                </div>
            </div>

            <!-- Published Articles Tab -->
            <div class="tab-pane fade" id="published" role="tabpanel">
                <div id="publishedArticlesContainer">
                    <!-- Published articles will be loaded here -->
                </div>
            </div>

            <!-- Draft Articles Tab -->
            <div class="tab-pane fade" id="drafts" role="tabpanel">
                <div id="draftArticlesContainer">
                    <!-- Draft articles will be loaded here -->
                </div>
            </div>

            <!-- Insights Tab -->
            <div class="tab-pane fade" id="insights" role="tabpanel">
                <div class="filter-section">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>Search Insights</label>
                            <input type="text" id="insightSearchInput" class="form-control" placeholder="Search insights...">
                        </div>
                        <div class="filter-group">
                            <button class="btn btn-primary" onclick="applyInsightFilters()">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>
                    </div>
                </div>

                <div id="insightsTableContainer">
                    <!-- Insights table will be loaded here -->
                </div>
            </div>

            <!-- GrainWatch Tab -->
            <div class="tab-pane fade" id="grainwatch" role="tabpanel">
                <div class="filter-section">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>Search GrainWatch</label>
                            <input type="text" id="grainwatchSearchInput" class="form-control" placeholder="Search grainwatch entries...">
                        </div>
                        <div class="filter-group">
                            <label>Category</label>
                            <select id="grainwatchCategoryFilter" class="form-select">
                                <option value="">All Categories</option>
                                <option value="grain watch">Grain Watch</option>
                                <option value="grain standards">Grain Standards</option>
                                <option value="policy briefs">Policy Briefs</option>
                                <option value="reports">Reports</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <button class="btn btn-primary" onclick="applyGrainWatchFilters()">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>
                    </div>
                </div>

                <div id="grainwatchTableContainer">
                    <!-- GrainWatch table will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Create/Edit Article Modal -->
    <div class="modal fade" id="articleModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="articleModalTitle">Create New Article</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="articleForm">
                        <input type="hidden" id="articleId" name="id">
                        
                        <!-- Basic Information -->
                        <div class="form-section">
                            <h5><i class="fas fa-info-circle"></i> Basic Information</h5>
                            
                            <div class="form-group">
                                <label for="title" class="required">Title</label>
                                <input type="text" id="title" name="title" required maxlength="255">
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="Category">Category</label>
                                        <input type="text" id="category" name="category" placeholder="e.g. Agriculture">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="location">Location</label>
                                        <input type="text" id="location" name="location" placeholder="e.g. Kenya, Uganda">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="source">Source</label>
                                        <input type="text" id="source" name="source" placeholder="e.g. Reuters, BBC">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="image">Cover Image URL</label>
                                        <input type="url" id="image" name="image" onchange="previewImage()">
                                        <img id="imagePreview" class="image-preview" style="display: none;">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Content Section -->
                        <div class="form-section">
                            <h5><i class="fas fa-file-alt"></i> Content</h5>
                            
                            <div class="form-group">
                                <label for="body" class="required">Article Content</label>
                                <div id="editor" class="editor-container">
                                    <!-- Rich text editor will be initialized here -->
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-outline-primary" onclick="saveArticle('draft')">Save as Draft</button>
                    <button type="button" class="btn btn-primary" onclick="saveArticle('published')">Publish Article</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Create/Edit Insight Modal -->
    <div class="modal fade" id="insightModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="insightModalTitle">Create New Insight</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="insightForm">
                        <input type="hidden" id="insightId" name="id">
                        
                        <!-- Basic Information -->
                        <div class="form-section">
                            <h5><i class="fas fa-lightbulb"></i> Insight Information</h5>
                            
                            <div class="form-group">
                                <label for="insightTitle" class="required">Title</label>
                                <input type="text" id="insightTitle" name="title" required maxlength="255" placeholder="Enter insight title...">
                            </div>
                        </div>

                        <!-- Content Section -->
                        <div class="form-section">
                            <h5><i class="fas fa-file-alt"></i> Content</h5>
                            
                            <div class="form-group">
                                <label for="insightBody" class="required">Insight Content</label>
                                <div id="insightEditor" class="editor-container">
                                    <!-- Rich text editor will be initialized here -->
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveInsight()">Save Insight</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Create/Edit GrainWatch Modal -->
    <div class="modal fade" id="grainwatchModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="grainwatchModalTitle">Create New GrainWatch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="grainwatchForm" enctype="multipart/form-data">
                        <input type="hidden" id="grainwatchId" name="id">
                        
                        <!-- Basic Information -->
                        <div class="form-section">
                            <h5><i class="fas fa-info-circle"></i> GrainWatch Information</h5>
                            
                            <div class="form-group">
                                <label for="grainwatchHeading" class="required">Heading</label>
                                <input type="text" id="grainwatchHeading" name="heading" required maxlength="255" placeholder="Enter grainwatch heading...">
                            </div>

                            <div class="form-group">
                                <label for="grainwatchCategory" class="required">Category</label>
                                <select id="grainwatchCategory" name="category" required class="form-select">
                                    <option value="">Select Category</option>
                                    <option value="grain watch">Grain Watch</option>
                                    <option value="grain standards">Grain Standards</option>
                                    <option value="policy briefs">Policy Briefs</option>
                                    <option value="reports">Reports</option>
                                </select>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="grainwatchImage">Cover Image URL</label>
                                        <input type="url" id="grainwatchImage" name="image" onchange="previewGrainWatchImage()" placeholder="Enter image URL for cover...">
                                        <img id="grainwatchImagePreview" class="image-preview" style="display: none;">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="grainwatchDocument">Document (PDF)</label>
                                        <input type="file" id="grainwatchDocument" name="document" accept=".pdf" onchange="previewDocument()">
                                        <small class="text-muted">Only PDF files are allowed. Maximum file size: 10MB</small>
                                        <div id="documentPreview" class="document-preview" style="display: none;">
                                            <i class="fas fa-file-pdf"></i>
                                            <span id="documentName"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="grainwatchDescription" class="required">Description</label>
                                <textarea id="grainwatchDescription" name="description" required rows="4" placeholder="Enter grainwatch description..."></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveGrainWatch()">Save GrainWatch</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">
                        <i class="fas fa-exclamation-triangle"></i> <span id="deleteModalTitle">Delete Article</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="deleteModalText">Are you sure you want to delete this article? This action cannot be undone.</p>
                    <div id="deleteItemInfo"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.6/quill.min.js"></script>
    
    <script>
        // Global variables
        let articles = [];
        let insights = [];
        let grainwatch = [];
        let quill;
        let insightQuill;
        let currentArticleId = null;
        let currentInsightId = null;
        let currentGrainWatchId = null;
        let currentDeleteType = null;
        const API_BASE = 'api/'; // Adjust this path as needed

        // Initialize the application
        document.addEventListener('DOMContentLoaded', function() {
            initializeEditor();
            initializeInsightEditor();
            loadArticles();
            
            // Tab change handlers
            document.addEventListener('shown.bs.tab', function(e) {
                if (e.target.id === 'published-tab') {
                    loadPublishedArticles();
                } else if (e.target.id === 'drafts-tab') {
                    loadDraftArticles();
                } else if (e.target.id === 'insights-tab') {
                    loadInsights();
                } else if (e.target.id === 'grainwatch-tab') {
                    loadGrainWatch();
                }
            });
        });

        // Initialize Quill editor for articles
        function initializeEditor() {
            quill = new Quill('#editor', {
                theme: 'snow',
                modules: {
                    toolbar: [
                        [{ 'header': [1, 2, 3, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ 'color': [] }, { 'background': [] }],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        [{ 'indent': '-1'}, { 'indent': '+1' }],
                        [{ 'align': [] }],
                        ['blockquote', 'code-block'],
                        ['link', 'image'],
                        ['clean']
                    ]
                },
                placeholder: 'Write your article content here...'
            });
        }

        // Initialize Quill editor for insights
        function initializeInsightEditor() {
            insightQuill = new Quill('#insightEditor', {
                theme: 'snow',
                modules: {
                    toolbar: [
                        [{ 'header': [1, 2, 3, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ 'color': [] }, { 'background': [] }],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        [{ 'indent': '-1'}, { 'indent': '+1' }],
                        [{ 'align': [] }],
                        ['blockquote', 'code-block'],
                        ['link'],
                        ['clean']
                    ]
                },
                placeholder: 'Write your insight content here...'
            });
        }

        // API Helper Functions
        async function apiRequest(endpoint, options = {}) {
            try {
                const response = await fetch(API_BASE + endpoint, {
                    headers: {
                        'Content-Type': 'application/json',
                        ...options.headers
                    },
                    ...options
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                return await response.json();
            } catch (error) {
                console.error('API request failed:', error);
                showToast('An error occurred. Please try again.', 'danger');
                throw error;
            }
        }

        // File upload helper function
        async function uploadFile(formData) {
            try {
                const response = await fetch(API_BASE + 'upload.php', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                return await response.json();
            } catch (error) {
                console.error('File upload failed:', error);
                showToast('Failed to upload file. Please try again.', 'danger');
                throw error;
            }
        }

        // Load articles from API
        async function loadArticles() {
            showLoading('articlesTableContainer');
            
            try {
                articles = await apiRequest('articles.php');
                renderArticlesTable(articles, 'articlesTableContainer');
            } catch (error) {
                document.getElementById('articlesTableContainer').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        Failed to load articles. Please check your connection and try again.
                    </div>
                `;
            }
        }

        // Load published articles
        async function loadPublishedArticles() {
            showLoading('publishedArticlesContainer');
            
            try {
                const publishedArticles = await apiRequest('articles.php?status=published');
                renderArticlesTable(publishedArticles, 'publishedArticlesContainer');
            } catch (error) {
                document.getElementById('publishedArticlesContainer').innerHTML = `
                    <div class="alert alert-danger">Failed to load published articles.</div>
                `;
            }
        }

        // Load draft articles
        async function loadDraftArticles() {
            showLoading('draftArticlesContainer');
            
            try {
                const draftArticles = await apiRequest('articles.php?status=draft');
                renderArticlesTable(draftArticles, 'draftArticlesContainer');
            } catch (error) {
                document.getElementById('draftArticlesContainer').innerHTML = `
                    <div class="alert alert-danger">Failed to load draft articles.</div>
                `;
            }
        }

        // Load insights from API
        async function loadInsights() {
            showLoading('insightsTableContainer');
            
            try {
                insights = await apiRequest('insights.php');
                renderInsightsTable(insights, 'insightsTableContainer');
            } catch (error) {
                document.getElementById('insightsTableContainer').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        Failed to load insights. Please check your connection and try again.
                    </div>
                `;
            }
        }

        // Load grainwatch from API
        async function loadGrainWatch() {
            showLoading('grainwatchTableContainer');
            
            try {
                grainwatch = await apiRequest('grainwatch.php');
                renderGrainWatchTable(grainwatch, 'grainwatchTableContainer');
            } catch (error) {
                document.getElementById('grainwatchTableContainer').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        Failed to load grainwatch entries. Please check your connection and try again.
                    </div>
                `;
            }
        }

        // Show loading spinner
        function showLoading(containerId) {
            document.getElementById(containerId).innerHTML = `
                <div class="loading">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3">Loading...</p>
                </div>
            `;
        }

        // Render articles table
        function renderArticlesTable(articlesData, containerId) {
            const container = document.getElementById(containerId);
            
            if (!articlesData || articlesData.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-newspaper"></i>
                        <h4>No articles found</h4>
                        <p>Start by creating your first article.</p>
                    </div>
                `;
                return;
            }

            const tableHTML = `
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="40%">Title</th>
                                <th width="12%">Category</th>
                                <th width="10%">Location</th>
                                <th width="10%">Status</th>
                                <th width="13%">Created</th>
                                <th width="15%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${articlesData.map(article => `
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            ${article.image ? `<img src="${article.image}" alt="" style="width: 40px; height: 30px; object-fit: cover; border-radius: 3px; margin-right: 10px;">` : ''}
                                            <div>
                                                <strong>${escapeHtml(article.title)}</strong>
                                                ${article.source ? `<br><small class="text-muted">Source: ${escapeHtml(article.source)}</small>` : ''}
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-info">${escapeHtml(article.category)}</span></td>
                                    <td>${escapeHtml(article.location) || '-'}</td>
                                    <td>
                                        <span class="status-badge status-${article.status}">
                                            ${article.status.charAt(0).toUpperCase() + article.status.slice(1)}
                                        </span>
                                    </td>
                                    <td>${formatDate(article.created_at)}</td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-info btn-sm" onclick="editArticle(${article.id})" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            ${article.status === 'draft' || article.status === 'unpublished' ? 
                                                `<button class="btn btn-success btn-sm" onclick="publishArticle(${article.id})" title="Publish">
                                                    <i class="fas fa-check"></i>
                                                </button>` : ''
                                            }
                                            ${article.status === 'published' ? 
                                                `<button class="btn btn-warning btn-sm" onclick="unpublishArticle(${article.id})" title="Unpublish">
                                                    <i class="fas fa-times"></i>
                                                </button>` : ''
                                            }
                                            <button class="btn btn-danger btn-sm" onclick="deleteArticle(${article.id})" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
            
            container.innerHTML = tableHTML;
        }

        // Render insights table
        function renderInsightsTable(insightsData, containerId) {
            const container = document.getElementById(containerId);
            
            if (!insightsData || insightsData.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-lightbulb"></i>
                        <h4>No insights found</h4>
                        <p>Start by creating your first insight.</p>
                    </div>
                `;
                return;
            }

            const tableHTML = `
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="60%">Title</th>
                                <th width="20%">Created</th>
                                <th width="20%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${insightsData.map(insight => `
                                <tr>
                                    <td>
                                        <div>
                                            <strong>${escapeHtml(insight.title)}</strong>
                                            <br>
                                            <small class="text-muted">
                                                ${stripHtmlTags(insight.body).substring(0, 100)}${stripHtmlTags(insight.body).length > 100 ? '...' : ''}
                                            </small>
                                        </div>
                                    </td>
                                    <td>${formatDate(insight.created_at)}</td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-info btn-sm" onclick="editInsight(${insight.id})" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="deleteInsight(${insight.id})" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
            
            container.innerHTML = tableHTML;
        }

        // Render grainwatch table
        function renderGrainWatchTable(grainwatchData, containerId) {
            const container = document.getElementById(containerId);
            
            if (!grainwatchData || grainwatchData.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-binoculars"></i>
                        <h4>No grainwatch entries found</h4>
                        <p>Start by creating your first grainwatch entry.</p>
                    </div>
                `;
                return;
            }

            const tableHTML = `
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="25%">Heading</th>
                                <th width="15%">Category</th>
                                <th width="25%">Description</th>
                                <th width="10%">Cover Image</th>
                                <th width="10%">Document</th>
                                <th width="5%">Posted</th>
                                <th width="10%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${grainwatchData.map(gw => `
                                <tr>
                                    <td>
                                        <strong>${escapeHtml(gw.heading)}</strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">${escapeHtml(gw.category)}</span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            ${escapeHtml(gw.description).substring(0, 100)}${escapeHtml(gw.description).length > 100 ? '...' : ''}
                                        </small>
                                    </td>
                                    <td>
                                        ${gw.image ? `
                                            <img src="${gw.image}" alt="Cover" style="width: 40px; height: 30px; object-fit: cover; border-radius: 3px;">
                                        ` : '<span class="text-muted">No image</span>'}
                                    </td>
                                    <td>
                                        ${gw.document_path ? `
                                            <a href="${gw.document_path}" target="_blank" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-file-pdf"></i> PDF
                                            </a>
                                        ` : '<span class="text-muted">No document</span>'}
                                    </td>
                                    <td>${formatDate(gw.created_at)}</td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-info btn-sm" onclick="editGrainWatch(${gw.id})" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="deleteGrainWatch(${gw.id})" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
            
            container.innerHTML = tableHTML;
        }

        // Utility functions
        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        function stripHtmlTags(html) {
            if (!html) return '';
            const div = document.createElement('div');
            div.innerHTML = html;
            return div.textContent || div.innerText || '';
        }

        function formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: '2-digit'
            });
        }

        // Show create modal
        function showCreateModal() {
            document.getElementById('articleModalTitle').textContent = 'Create New Article';
            document.getElementById('articleForm').reset();
            document.getElementById('articleId').value = '';
            quill.setText('');
            document.getElementById('imagePreview').style.display = 'none';
            
            const modal = new bootstrap.Modal(document.getElementById('articleModal'));
            modal.show();
        }

        // Show create insight modal
        function showCreateInsightModal() {
            document.getElementById('insightModalTitle').textContent = 'Create New Insight';
            document.getElementById('insightForm').reset();
            document.getElementById('insightId').value = '';
            insightQuill.setText('');
            
            const modal = new bootstrap.Modal(document.getElementById('insightModal'));
            modal.show();
        }

        // Show create grainwatch modal
        function showCreateGrainWatchModal() {
            document.getElementById('grainwatchModalTitle').textContent = 'Create New GrainWatch';
            document.getElementById('grainwatchForm').reset();
            document.getElementById('grainwatchId').value = '';
            document.getElementById('grainwatchCategory').value = '';
            document.getElementById('grainwatchImage').value = '';
            document.getElementById('grainwatchImagePreview').style.display = 'none';
            document.getElementById('documentPreview').style.display = 'none';
            
            const modal = new bootstrap.Modal(document.getElementById('grainwatchModal'));
            modal.show();
        }

        // Edit article
        async function editArticle(articleId) {
            try {
                const article = await apiRequest(`articles.php/${articleId}`);
                
                document.getElementById('articleModalTitle').textContent = 'Edit Article';
                document.getElementById('articleId').value = article.id;
                document.getElementById('title').value = article.title;
                document.getElementById('category').value = article.category;
                document.getElementById('location').value = article.location || '';
                document.getElementById('source').value = article.source || '';
                document.getElementById('image').value = article.image || '';
                
                if (article.image) {
                    document.getElementById('imagePreview').src = article.image;
                    document.getElementById('imagePreview').style.display = 'block';
                }
                
                quill.root.innerHTML = article.body;
                
                const modal = new bootstrap.Modal(document.getElementById('articleModal'));
                modal.show();
                
            } catch (error) {
                showToast('Failed to load article for editing.', 'danger');
            }
        }

        // Edit insight
        async function editInsight(insightId) {
            try {
                const insight = await apiRequest(`insights.php/${insightId}`);
                
                document.getElementById('insightModalTitle').textContent = 'Edit Insight';
                document.getElementById('insightId').value = insight.id;
                document.getElementById('insightTitle').value = insight.title;
                insightQuill.root.innerHTML = insight.body;
                
                const modal = new bootstrap.Modal(document.getElementById('insightModal'));
                modal.show();
                
            } catch (error) {
                showToast('Failed to load insight for editing.', 'danger');
            }
        }

        // Edit grainwatch
        async function editGrainWatch(grainwatchId) {
            try {
                const gw = await apiRequest(`grainwatch.php/${grainwatchId}`);
                
                document.getElementById('grainwatchModalTitle').textContent = 'Edit GrainWatch';
                document.getElementById('grainwatchId').value = gw.id;
                document.getElementById('grainwatchHeading').value = gw.heading;
                document.getElementById('grainwatchCategory').value = gw.category;
                document.getElementById('grainwatchImage').value = gw.image || '';
                document.getElementById('grainwatchDescription').value = gw.description;
                
                if (gw.image) {
                    document.getElementById('grainwatchImagePreview').src = gw.image;
                    document.getElementById('grainwatchImagePreview').style.display = 'block';
                } else {
                    document.getElementById('grainwatchImagePreview').style.display = 'none';
                }
                
                if (gw.document_path) {
                    document.getElementById('documentName').textContent = gw.document_path.split('/').pop();
                    document.getElementById('documentPreview').style.display = 'flex';
                } else {
                    document.getElementById('documentPreview').style.display = 'none';
                }
                
                const modal = new bootstrap.Modal(document.getElementById('grainwatchModal'));
                modal.show();
                
            } catch (error) {
                showToast('Failed to load grainwatch for editing.', 'danger');
            }
        }

        // Preview image
        function previewImage() {
            const imageUrl = document.getElementById('image').value;
            const preview = document.getElementById('imagePreview');
            
            if (imageUrl) {
                preview.src = imageUrl;
                preview.style.display = 'block';
                preview.onerror = function() {
                    this.style.display = 'none';
                    showToast('Invalid image URL', 'warning');
                };
            } else {
                preview.style.display = 'none';
            }
        }

        // Preview grainwatch image
        function previewGrainWatchImage() {
            const imageUrl = document.getElementById('grainwatchImage').value;
            const preview = document.getElementById('grainwatchImagePreview');
            
            if (imageUrl) {
                preview.src = imageUrl;
                preview.style.display = 'block';
                preview.onerror = function() {
                    this.style.display = 'none';
                    showToast('Invalid image URL', 'warning');
                };
            } else {
                preview.style.display = 'none';
            }
        }

        // Preview document
        function previewDocument() {
            const fileInput = document.getElementById('grainwatchDocument');
            const preview = document.getElementById('documentPreview');
            const documentName = document.getElementById('documentName');
            
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                if (file.type !== 'application/pdf') {
                    showToast('Please select a PDF file.', 'warning');
                    fileInput.value = '';
                    preview.style.display = 'none';
                    return;
                }
                
                if (file.size > 10 * 1024 * 1024) { // 10MB limit
                    showToast('File size must be less than 10MB.', 'warning');
                    fileInput.value = '';
                    preview.style.display = 'none';
                    return;
                }
                
                documentName.textContent = file.name;
                preview.style.display = 'flex';
            } else {
                preview.style.display = 'none';
            }
        }

        // Save article
        async function saveArticle(status) {
            const form = document.getElementById('articleForm');
            const formData = new FormData(form);
            const articleId = formData.get('id');
            
            const articleData = {
                title: formData.get('title'),
                body: quill.root.innerHTML,
                image: formData.get('image') || null,
                category: formData.get('category'),
                source: formData.get('source') || null,
                location: formData.get('location') || null,
                status: status
            };

            // Validate required fields
            if (!articleData.title || !articleData.category) {
                showToast('Please fill in all required fields.', 'warning');
                return;
            }

            try {
                if (articleId) {
                    // Update existing article
                    await apiRequest(`articles.php/${articleId}`, {
                        method: 'PUT',
                        body: JSON.stringify(articleData)
                    });
                    showToast('Article updated successfully!', 'success');
                } else {
                    // Create new article
                    await apiRequest('articles.php', {
                        method: 'POST',
                        body: JSON.stringify(articleData)
                    });
                    showToast('Article created successfully!', 'success');
                }

                // Close modal and refresh table
                const modal = bootstrap.Modal.getInstance(document.getElementById('articleModal'));
                modal.hide();
                
                loadArticles();
                
            } catch (error) {
                showToast('Failed to save article. Please try again.', 'danger');
            }
        }

        // Save insight
        async function saveInsight() {
            const form = document.getElementById('insightForm');
            const formData = new FormData(form);
            const insightId = formData.get('id');
            
            const insightData = {
                title: formData.get('title'),
                body: insightQuill.root.innerHTML
            };

            // Validate required fields
            if (!insightData.title || !insightData.body || insightData.body === '<p><br></p>') {
                showToast('Please fill in all required fields.', 'warning');
                return;
            }

            try {
                if (insightId) {
                    // Update existing insight
                    await apiRequest(`insights.php/${insightId}`, {
                        method: 'PUT',
                        body: JSON.stringify(insightData)
                    });
                    showToast('Insight updated successfully!', 'success');
                } else {
                    // Create new insight
                    await apiRequest('insights.php', {
                        method: 'POST',
                        body: JSON.stringify(insightData)
                    });
                    showToast('Insight created successfully!', 'success');
                }

                // Close modal and refresh table
                const modal = bootstrap.Modal.getInstance(document.getElementById('insightModal'));
                modal.hide();
                
                loadInsights();
                
            } catch (error) {
                showToast('Failed to save insight. Please try again.', 'danger');
            }
        }

        // Save grainwatch
        async function saveGrainWatch() {
            const form = document.getElementById('grainwatchForm');
            const formData = new FormData(form);
            const grainwatchId = formData.get('id');
            
            const heading = formData.get('heading');
            const category = formData.get('category');
            const image = formData.get('image');
            const description = formData.get('description');
            const documentFile = document.getElementById('grainwatchDocument').files[0];

            // Validate required fields
            if (!heading || !category || !description) {
                showToast('Please fill in all required fields.', 'warning');
                return;
            }

            try {
                let documentPath = null;

                // Upload document if provided
                if (documentFile) {
                    const uploadFormData = new FormData();
                    uploadFormData.append('document', documentFile);
                    
                    const uploadResult = await uploadFile(uploadFormData);
                    if (uploadResult.success) {
                        documentPath = uploadResult.filePath;
                    } else {
                        throw new Error('Failed to upload document');
                    }
                }

                const grainwatchData = {
                    heading: heading,
                    category: category,
                    image: image || null,
                    description: description,
                    document_path: documentPath
                };

                if (grainwatchId) {
                    // Update existing grainwatch
                    await apiRequest(`grainwatch.php/${grainwatchId}`, {
                        method: 'PUT',
                        body: JSON.stringify(grainwatchData)
                    });
                    showToast('GrainWatch updated successfully!', 'success');
                } else {
                    // Create new grainwatch
                    await apiRequest('grainwatch.php', {
                        method: 'POST',
                        body: JSON.stringify(grainwatchData)
                    });
                    showToast('GrainWatch created successfully!', 'success');
                }

                // Close modal and refresh table
                const modal = bootstrap.Modal.getInstance(document.getElementById('grainwatchModal'));
                modal.hide();
                
                loadGrainWatch();
                
            } catch (error) {
                showToast('Failed to save grainwatch. Please try again.', 'danger');
            }
        }

        // Publish article
        async function publishArticle(articleId) {
            try {
                await apiRequest('publish.php', {
                    method: 'PUT',
                    body: JSON.stringify({
                        id: articleId,
                        status: 'published'
                    })
                });
                
                loadArticles();
                showToast('Article published successfully!', 'success');
            } catch (error) {
                showToast('Failed to publish article.', 'danger');
            }
        }

        // Unpublish article
        async function unpublishArticle(articleId) {
            try {
                await apiRequest('publish.php', {
                    method: 'PUT',
                    body: JSON.stringify({
                        id: articleId,
                        status: 'unpublished'
                    })
                });
                
                loadArticles();
                showToast('Article unpublished successfully!', 'warning');
            } catch (error) {
                showToast('Failed to unpublish article.', 'danger');
            }
        }

        // Delete article
        function deleteArticle(articleId) {
            currentArticleId = articleId;
            currentDeleteType = 'article';
            const article = articles.find(a => a.id == articleId);
            if (article) {
                document.getElementById('deleteModalTitle').textContent = 'Delete Article';
                document.getElementById('deleteModalText').textContent = 'Are you sure you want to delete this article? This action cannot be undone.';
                document.getElementById('deleteItemInfo').innerHTML = `
                    <div class="alert alert-danger">
                        <strong>Title:</strong> ${escapeHtml(article.title)}<br>
                        <strong>Category:</strong> ${escapeHtml(article.category)}<br>
                        <strong>Status:</strong> ${article.status}
                    </div>
                `;
                
                const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
                modal.show();
            }
        }

        // Delete insight
        function deleteInsight(insightId) {
            currentInsightId = insightId;
            currentDeleteType = 'insight';
            const insight = insights.find(i => i.id == insightId);
            if (insight) {
                document.getElementById('deleteModalTitle').textContent = 'Delete Insight';
                document.getElementById('deleteModalText').textContent = 'Are you sure you want to delete this insight? This action cannot be undone.';
                document.getElementById('deleteItemInfo').innerHTML = `
                    <div class="alert alert-danger">
                        <strong>Title:</strong> ${escapeHtml(insight.title)}<br>
                        <strong>Created:</strong> ${formatDate(insight.created_at)}
                    </div>
                `;
                
                const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
                modal.show();
            }
        }

        // Delete grainwatch
        function deleteGrainWatch(grainwatchId) {
            currentGrainWatchId = grainwatchId;
            currentDeleteType = 'grainwatch';
            const gw = grainwatch.find(g => g.id == grainwatchId);
            if (gw) {
                document.getElementById('deleteModalTitle').textContent = 'Delete GrainWatch';
                document.getElementById('deleteModalText').textContent = 'Are you sure you want to delete this grainwatch entry? This action cannot be undone.';
                document.getElementById('deleteItemInfo').innerHTML = `
                    <div class="alert alert-danger">
                        <strong>Heading:</strong> ${escapeHtml(gw.heading)}<br>
                        <strong>Category:</strong> ${escapeHtml(gw.category)}<br>
                        <strong>Created:</strong> ${formatDate(gw.created_at)}
                    </div>
                `;
                
                const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
                modal.show();
            }
        }

        // Confirm delete
        async function confirmDelete() {
            try {
                if (currentDeleteType === 'article' && currentArticleId) {
                    await apiRequest(`articles.php/${currentArticleId}`, {
                        method: 'DELETE'
                    });
                    loadArticles();
                    showToast('Article deleted successfully!', 'success');
                } else if (currentDeleteType === 'insight' && currentInsightId) {
                    await apiRequest(`insights.php/${currentInsightId}`, {
                        method: 'DELETE'
                    });
                    loadInsights();
                    showToast('Insight deleted successfully!', 'success');
                } else if (currentDeleteType === 'grainwatch' && currentGrainWatchId) {
                    await apiRequest(`grainwatch.php/${currentGrainWatchId}`, {
                        method: 'DELETE'
                    });
                    loadGrainWatch();
                    showToast('GrainWatch deleted successfully!', 'success');
                }
                
                const modal = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
                modal.hide();
                
            } catch (error) {
                showToast(`Failed to delete ${currentDeleteType}.`, 'danger');
            }
            
            currentArticleId = null;
            currentInsightId = null;
            currentGrainWatchId = null;
            currentDeleteType = null;
        }

        // Apply filters for articles
        async function applyFilters() {
            const searchTerm = document.getElementById('searchInput').value.trim();
            const categoryFilter = document.getElementById('categoryFilterTab').value;
            const statusFilter = document.getElementById('statusFilter').value;

            showLoading('articlesTableContainer');

            try {
                const params = new URLSearchParams();
                if (searchTerm) params.append('search', searchTerm);
                if (categoryFilter) params.append('category', categoryFilter);
                if (statusFilter) params.append('status', statusFilter);

                const queryString = params.toString();
                const endpoint = queryString ? `articles.php?${queryString}` : 'articles.php';
                
                const filteredArticles = await apiRequest(endpoint);
                renderArticlesTable(filteredArticles, 'articlesTableContainer');
            } catch (error) {
                document.getElementById('articlesTableContainer').innerHTML = `
                    <div class="alert alert-danger">Failed to filter articles.</div>
                `;
            }
        }

        // Apply filters for insights
        async function applyInsightFilters() {
            const searchTerm = document.getElementById('insightSearchInput').value.trim();

            showLoading('insightsTableContainer');

            try {
                const params = new URLSearchParams();
                if (searchTerm) params.append('search', searchTerm);

                const queryString = params.toString();
                const endpoint = queryString ? `insights.php?${queryString}` : 'insights.php';
                
                const filteredInsights = await apiRequest(endpoint);
                renderInsightsTable(filteredInsights, 'insightsTableContainer');
            } catch (error) {
                document.getElementById('insightsTableContainer').innerHTML = `
                    <div class="alert alert-danger">Failed to filter insights.</div>
                `;
            }
        }

        // Apply filters for grainwatch
        async function applyGrainWatchFilters() {
            const searchTerm = document.getElementById('grainwatchSearchInput').value.trim();
            const categoryFilter = document.getElementById('grainwatchCategoryFilter').value;

            showLoading('grainwatchTableContainer');

            try {
                const params = new URLSearchParams();
                if (searchTerm) params.append('search', searchTerm);
                if (categoryFilter) params.append('category', categoryFilter);

                const queryString = params.toString();
                const endpoint = queryString ? `grainwatch.php?${queryString}` : 'grainwatch.php';
                
                const filteredGrainWatch = await apiRequest(endpoint);
                renderGrainWatchTable(filteredGrainWatch, 'grainwatchTableContainer');
            } catch (error) {
                document.getElementById('grainwatchTableContainer').innerHTML = `
                    <div class="alert alert-danger">Failed to filter grainwatch entries.</div>
                `;
            }
        }

        // Show toast notification
        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = `alert alert-${type} alert-dismissible fade show`;
            toast.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.getElementById('toastContainer').appendChild(toast);
            
            // Auto remove after 3 seconds
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 3000);
        }

        // Event listeners
        document.getElementById('categoryFilter').addEventListener('change', function() {
            document.getElementById('categoryFilterTab').value = this.value;
            applyFilters();
        });

        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(applyFilters, 300);
        });

        document.getElementById('insightSearchInput').addEventListener('input', function() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(applyInsightFilters, 300);
        });

        document.getElementById('grainwatchSearchInput').addEventListener('input', function() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(applyGrainWatchFilters, 300);
        });
    </script>
</body>
</html>