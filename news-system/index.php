<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News Management System</title>
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
        
        /* Articles Table */
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
    <!-- Header -->
    <div class="header">
        <h1><i class="fas fa-newspaper"></i> News Management System</h1>
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
                                        <label for="category" class="required">Category</label>
                                        <select id="category" name="category" required>
                                            <option value="">Select Category</option>
                                            <option value="Market Policy">Market Policy</option>
                                            <option value="Export">Export</option>
                                            <option value="Import">Import</option>
                                            <option value="Agriculture">Agriculture</option>
                                            <option value="Trade">Trade</option>
                                        </select>
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

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">
                        <i class="fas fa-exclamation-triangle"></i> Delete Article
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this article? This action cannot be undone.</p>
                    <div id="deleteArticleInfo"></div>
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
        let quill;
        let currentArticleId = null;
        const API_BASE = 'api/'; // Adjust this path as needed

        // Initialize the application
        document.addEventListener('DOMContentLoaded', function() {
            initializeEditor();
            loadArticles();
            
            // Tab change handlers
            document.addEventListener('shown.bs.tab', function(e) {
                if (e.target.id === 'published-tab') {
                    loadPublishedArticles();
                } else if (e.target.id === 'drafts-tab') {
                    loadDraftArticles();
                }
            });
        });

        // Initialize Quill editor
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

        // Show loading spinner
        function showLoading(containerId) {
            document.getElementById(containerId).innerHTML = `
                <div class="loading">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3">Loading articles...</p>
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

        function formatDate(dateString) {
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
            const article = articles.find(a => a.id == articleId);
            if (article) {
                document.getElementById('deleteArticleInfo').innerHTML = `
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

        // Confirm delete
        async function confirmDelete() {
            if (currentArticleId) {
                try {
                    await apiRequest(`articles.php/${currentArticleId}`, {
                        method: 'DELETE'
                    });
                    
                    loadArticles();
                    
                    const modal = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
                    modal.hide();
                    
                    showToast('Article deleted successfully!', 'success');
                } catch (error) {
                    showToast('Failed to delete article.', 'danger');
                }
                
                currentArticleId = null;
            }
        }

        // Apply filters
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

        // Show toast notification
        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            toast.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(toast);
            
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
    </script>
</body>
</html>