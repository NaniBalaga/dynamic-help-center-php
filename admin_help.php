<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
require_once '../db_connect.php';

// Helper function to format Tab Names to Title Case
function formatTabName($name) {
    return ucwords(strtolower(trim($name)));
}

// Check for toast messages
$message = '';
$msg_type = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $msg_type = $_SESSION['msg_type'];
    unset($_SESSION['message']);
    unset($_SESSION['msg_type']);
}

// Fetch categories
$cat_query = "SELECT * FROM help_categories ORDER BY name ASC";
$cat_result = $conn->query($cat_query);
$categories = [];
if ($cat_result && $cat_result->num_rows > 0) {
    while($row = $cat_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Fetch all articles GROUPED BY Category, ordering by latest updated inside each group
$grouped_articles = [];
$art_query = "SELECT a.*, c.name as cat_name FROM help_articles a JOIN help_categories c ON a.category_id = c.id ORDER BY c.name ASC, a.updated_at DESC";
$art_result = $conn->query($art_query);
if ($art_result && $art_result->num_rows > 0) {
    while($row = $art_result->fetch_assoc()) {
        $cat_formatted = formatTabName($row['cat_name']);
        $grouped_articles[$cat_formatted][] = $row;
    }
}
?>



<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Help Center Admin Editor</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* === THEME VARIABLES === */
        :root {
            /* Light Theme */
            --bg-color: #f4f6f9;
            --sidebar-bg: #ffffff;
            --border-color: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --accent-color: #0d97ff;
            --danger-color: #ef4444;
            --input-bg: #f8fafc;
            --modal-bg: #ffffff;
            --table-header: #f1f5f9;
            --table-hover: #f8fafc;
            --nav-hover-bg: rgba(13, 151, 255, 0.05);
            --font-family: 'Poppins', sans-serif;
        }

        [data-theme="dark"] {
            /* Dark Theme */
            --bg-color: #000000;
            --sidebar-bg: #080808;
            --border-color: #1a1a1a;
            --text-primary: #eaeaea;
            --text-secondary: #777777;
            --accent-color: #0d97ff;
            --danger-color: #ff4d4d;
            --input-bg: #0f0f0f;
            --modal-bg: #0a0a0a;
            --table-header: #050505;
            --table-hover: #111111;
            --nav-hover-bg: rgba(255, 255, 255, 0.05);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background-color: var(--bg-color);
            color: var(--text-primary);
            font-family: var(--font-family);
            font-size: 13px;
            display: flex;
            height: 100vh;
            overflow: hidden;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        /* Smooth Transitions */
        .sidebar, .modal-content, .input-field, .side-input, .side-select, .editor-toolbar, .editor-header, .mobile-header {
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }

        .toast {
            position: fixed; top: 20px; right: 20px;
            padding: 12px 20px; border-radius: 6px;
            font-size: 13px; font-weight: 500;
            z-index: 9999; box-shadow: 0 5px 15px rgba(0,0,0,0.5);
            animation: slideIn 0.4s ease-out;
        }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .toast.success { background: rgba(13, 151, 255, 0.9); border: 1px solid var(--accent-color); color: #fff; }
        .toast.error { background: rgba(255, 77, 77, 0.9); border: 1px solid var(--danger-color); color: #fff; }

        .mobile-header {
            display: none; padding: 12px 18px;
            background: var(--sidebar-bg);
            border-bottom: 1px solid var(--border-color);
            align-items: center; justify-content: space-between;
            position: fixed; top: 0; width: 100%;
            z-index: 999;
        }

        .mobile-brand { font-size: 16px; font-weight: 600; color: var(--accent-color); display: flex; align-items: center; gap: 10px; }
        .icon-btn { background: transparent; border: none; color: var(--text-primary); font-size: 20px; cursor: pointer; padding: 5px; }

        .mobile-overlay {
            display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(3px); z-index: 998;
        }
        .mobile-overlay.active { display: block; }

        .sidebar {
            width: 280px; min-width: 280px;
            background-color: var(--sidebar-bg); border-right: 1px solid var(--border-color);
            display: flex; flex-direction: column; overflow-y: auto;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 1000;
        }

        .sidebar-brand-desktop {
            padding: 20px 15px; border-bottom: 1px solid var(--border-color);
            display: flex; justify-content: space-between; align-items: center;
        }
        .sidebar-brand-desktop h2 { font-size: 16px; color: var(--accent-color); display: flex; align-items: center; gap: 8px; font-weight: 600; }

        .mobile-sidebar-header {
            display: none; padding: 15px; border-bottom: 1px solid var(--border-color);
            justify-content: space-between; align-items: center; background: var(--sidebar-bg);
        }
        .mobile-sidebar-header h2 { font-size: 15px; color: var(--accent-color); margin: 0; display: flex; align-items: center; gap: 8px; font-weight: 600;}

        .sidebar-section { padding: 18px 15px; border-bottom: 1px solid var(--border-color); }
        .sidebar h2 { font-size: 12px; font-weight: 600; margin-bottom: 14px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 8px; }

        .form-group { margin-bottom: 12px; }
        label { display: block; margin-bottom: 6px; color: var(--text-secondary); font-size: 12px; font-weight: 500; }

        input[type="text"].side-input, select.side-select {
            width: 100%; background: var(--input-bg); border: 1px solid var(--border-color); color: var(--text-primary);
            padding: 10px; border-radius: 6px; font-family: var(--font-family); font-size: 12px; transition: border-color 0.3s;
        }
        input.side-input:focus, select.side-select:focus { outline: none; border-color: var(--accent-color); }

        button.btn-primary {
            background: var(--accent-color); color: #ffffff; border: none; padding: 12px;
            border-radius: 6px; cursor: pointer; font-weight: 500; font-size: 13px; width: 100%; transition: opacity 0.3s;
        }
        button.btn-primary:hover { opacity: 0.9; }
        
        button.btn-outline {
            background: transparent; color: var(--accent-color); border: 1px solid var(--accent-color);
            padding: 10px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 500; width: 100%; transition: all 0.3s;
        }
        button.btn-outline:hover { background: var(--nav-hover-bg); }

        button.btn-secondary {
            background: var(--input-bg); color: var(--text-primary); border: 1px solid var(--border-color); padding: 12px;
            border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 500; width: 100%; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        button.btn-secondary:hover { border-color: var(--accent-color); color: var(--accent-color); }

        .main-wrapper { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .editor-area { display: flex; flex-direction: column; min-height: 100vh; }
        
        .editor-header { padding: 15px 25px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; background: var(--sidebar-bg); }
        .title-container { display: flex; align-items: center; flex: 1; }
        .title-label { font-size: 14px; font-weight: 500; color: var(--text-secondary); margin-right: 12px; }
        .title-input { flex: 1; background: transparent; border: none; color: var(--text-primary); font-size: 16px; font-family: var(--font-family); font-weight: 500; outline: none; }

        .editor-toolbar {
            padding: 10px 25px; border-bottom: 1px solid var(--border-color);
            display: flex; flex-wrap: wrap; gap: 8px; background-color: var(--input-bg);
        }

        .format-btn {
            background: var(--sidebar-bg); border: 1px solid var(--border-color); color: var(--text-secondary);
            padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; transition: all 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 5px;
        }
        .format-btn:hover { border-color: var(--accent-color); color: var(--accent-color); }

        .editor-content {
            flex: 1; padding: 25px; background: transparent; border: none; color: var(--text-primary);
            font-size: 14px; line-height: 1.8; font-family: var(--font-family); resize: none; outline: none;
        }

        #preview_area {
            display: none; flex: 1; padding: 25px; overflow-y: auto; color: var(--text-primary); line-height: 1.8; font-size: 14px; background: var(--bg-color);
        }

        /* FULL PAGE MODAL */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.7); z-index: 2000; backdrop-filter: blur(5px); align-items: center; justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-content {
            background: var(--modal-bg); border: 1px solid var(--border-color); border-radius: 12px; 
            width: 95vw; height: 95vh; max-width: 1400px;
            display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        .modal-header { padding: 20px 25px; background: var(--sidebar-bg); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
        .modal-header h2 { font-size: 18px; color: var(--text-primary); margin: 0; display: flex; align-items: center; gap: 10px; font-weight: 600;}
        .close-modal-btn { background: none; border: none; color: var(--text-secondary); font-size: 24px; cursor: pointer; transition: color 0.3s; }
        .close-modal-btn:hover { color: var(--danger-color); }
        
        .modal-body { padding: 25px; overflow-y: auto; flex: 1; background: var(--bg-color); }
        
        /* Category Groups inside Modal */
        .category-group {
            background: var(--sidebar-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 25px;
            overflow: hidden;
        }
        .category-group-header {
            padding: 15px 20px;
            background: var(--table-header);
            border-bottom: 1px solid var(--border-color);
            color: var(--accent-color);
            font-size: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-responsive { width: 100%; overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; font-size: 13px; text-align: left; }
        .data-table th, .data-table td { padding: 15px 20px; border-bottom: 1px solid var(--border-color); }
        .data-table th { color: var(--text-secondary); font-weight: 500; text-transform: uppercase; font-size: 11px; letter-spacing: 1px; }
        .data-table td { color: var(--text-primary); font-weight: 500; }
        .data-table tr:last-child td { border-bottom: none; }
        .data-table tr:hover { background: var(--table-hover); }
        
        .action-btn { background: none; border: none; cursor: pointer; padding: 6px 10px; font-size: 13px; margin-right: 5px; transition: all 0.2s; border-radius: 4px; font-weight: 500;}
        .btn-edit { color: var(--accent-color); background: rgba(13, 151, 255, 0.1); } .btn-edit:hover { background: var(--accent-color); color: #fff; }
        .btn-del { color: var(--danger-color); background: rgba(239, 68, 68, 0.1); } .btn-del:hover { background: var(--danger-color); color: #fff; }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-secondary); }

        @media (max-width: 768px) {
            body { flex-direction: column; overflow: hidden; }
            .mobile-header { display: flex; }
            .sidebar-brand-desktop { display: none; }
            .main-wrapper { padding-top: 55px; height: 100vh; }
            .sidebar { position: fixed; top: 0; left: 0; height: 100vh; width: 85%; max-width: 320px; transform: translateX(-100%); box-shadow: 5px 0 20px rgba(0,0,0,0.5); }
            .sidebar.active { transform: translateX(0); }
            .mobile-sidebar-header { display: flex; }
            .editor-area { min-height: auto; }
            .title-container { flex-direction: column; align-items: flex-start; gap: 8px;}
            .title-input { width: 100%; }
            .editor-header, .editor-toolbar, .editor-content { padding: 15px; }
            .modal-content { width: 100vw; height: 100vh; max-height: 100vh; border-radius: 0; border: none; }
            .modal-body { padding: 15px; }
        }
    </style>
</head>
<body>

    <?php if ($message): ?>
        <div class="toast <?= $msg_type ?>" id="toastMsg"><?= $message ?></div>
        <script>setTimeout(() => document.getElementById('toastMsg').style.display = 'none', 4000);</script>
    <?php endif; ?>

    <div class="mobile-header">
        <div class="mobile-brand"><i class="fas fa-shield-alt"></i> Admin Panel</div>
        <div style="display:flex; gap: 10px; align-items:center;">
            <button class="icon-btn theme-toggle-btn" onclick="toggleTheme()"><i class="fas fa-moon"></i></button>
            <button class="icon-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
        </div>
    </div>

    <div class="mobile-overlay" onclick="toggleSidebar()"></div>

    <div class="sidebar" id="sidebar">
        
        <div class="sidebar-brand-desktop">
            <h2><i class="fas fa-shield-alt"></i> Admin Panel</h2>
            <button class="icon-btn theme-toggle-btn" onclick="toggleTheme()" title="Toggle Theme"><i class="fas fa-moon"></i></button>
        </div>

        <div class="mobile-sidebar-header">
            <h2><i class="fas fa-tools"></i> Menu</h2>
            <button class="icon-btn" onclick="toggleSidebar()"><i class="fas fa-times"></i></button>
        </div>

        <div class="sidebar-section" style="border-bottom: 2px solid var(--border-color);">
            <button type="button" class="btn-secondary" onclick="openArticlesModal()">
                <i class="fas fa-layer-group"></i> Manage Articles
            </button>
        </div>

        <div class="sidebar-section">
            <h2><i class="fas fa-folder-plus"></i> Create Tab</h2>
            <form method="POST" action="handle_submissions.php">
                <div class="form-group">
                    <input type="text" name="cat_name" class="side-input" required placeholder="Tab Name">
                </div>
                <div class="form-group">
                    <input type="text" name="cat_icon" class="side-input" placeholder="Icon (e.g., fas fa-user)">
                </div>
                <button type="submit" name="add_category" class="btn-outline">Add Tab</button>
            </form>
        </div>

        <div class="sidebar-section">
            <h2><i class="fas fa-cog"></i> Article Settings</h2>
            <form method="POST" action="handle_submissions.php" id="article_form">
                <input type="hidden" name="article_id" id="form_article_id" value="">
                
                <div class="form-group">
                    <label>Assign to Tab</label>
                    <select name="category_id" id="form_category_id" class="side-select" required>
                        <option value="">-- Choose Tab --</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars(formatTabName($cat['name'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" name="save_article" id="form_submit_btn" class="btn-primary">
                    <i class="fas fa-paper-plane"></i> Publish Article
                </button>
                <button type="button" id="form_cancel_btn" class="btn-outline" style="display:none; margin-top:8px; border-color:var(--border-color); color:var(--text-secondary);" onclick="cancelEdit()">
                    Cancel Edit
                </button>
            </form>
        </div>
        
        <div class="sidebar-section">
            <h2><i class="fas fa-trash-alt"></i> Delete Tab</h2>
            <form method="POST" action="handle_submissions.php" onsubmit="return confirm('WARNING: This deletes the tab AND all its articles. Continue?');">
                <div class="form-group">
                    <select name="category_id" class="side-select" required>
                        <option value="">-- Select Tab --</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars(formatTabName($cat['name'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="delete_category" class="btn-outline" style="border-color:var(--danger-color); color:var(--danger-color);">Delete Tab</button>
            </form>
        </div>
    </div>

    <div class="main-wrapper">
        <div class="editor-area">
            <div class="editor-header">
                <div class="title-container">
                    <span class="title-label">Article Title:</span>
                    <input type="text" name="title" id="form_title" form="article_form" class="title-input" required placeholder="Enter an engaging title...">
                </div>
                <button type="button" class="btn-outline" style="width:auto; padding: 8px 16px; font-size: 12px; margin-left: 10px;" onclick="togglePreview()" id="previewToggleBtn">
                    <i class="fas fa-eye"></i> Preview
                </button>
            </div>

            <div class="editor-toolbar" id="editor_toolbar">
                <button class="format-btn" onclick="insertTag('**', '**')" title="Bold"><i class="fas fa-bold"></i></button>
                <button class="format-btn" onclick="insertTag('*', '*')" title="Italic"><i class="fas fa-italic"></i></button>
                <button class="format-btn" onclick="insertTag('__', '__')" title="Underline"><i class="fas fa-underline"></i></button>
                <button class="format-btn" onclick="insertTag('~~', '~~')" title="Strikethrough"><i class="fas fa-strikethrough"></i></button>
                <button class="format-btn" onclick="insertTag('`', '`')" title="Code"><i class="fas fa-code"></i></button>
                <button class="format-btn" onclick="insertTag('## ', '')" title="Heading"><i class="fas fa-heading"></i></button>
                <button class="format-btn" onclick="insertTag('> ', '')" title="Quote"><i class="fas fa-quote-right"></i></button>
                <button class="format-btn" onclick="insertTag('https://', '')" title="Insert Link"><i class="fas fa-link"></i></button>
                <button class="format-btn" onclick="insertTag('\n\n---\n\n', '')" title="Divider"><i class="fas fa-minus"></i></button>
            </div>

            <textarea name="content" id="form_content" form="article_form" class="editor-content" required placeholder="Write your message here... use the formatting buttons above!"></textarea>
            
            <div id="preview_area"></div>
        </div>
    </div>

    <div id="articlesModal" class="modal-overlay" onclick="if(event.target==this) closeArticlesModal()">
        <div class="modal-content">
            
            <div class="modal-header">
                <h2><i class="fas fa-database" style="color:var(--accent-color);"></i> Manage Articles by Tab</h2>
                <button class="close-modal-btn" onclick="closeArticlesModal()"><i class="fas fa-times"></i></button>
            </div>
            
            <div class="modal-body">
                <?php if(!empty($grouped_articles)): ?>
                    <?php foreach($grouped_articles as $category_name => $articles): ?>
                        <div class="category-group">
                            <div class="category-group-header">
                                <i class="fas fa-folder-open"></i> <?= htmlspecialchars($category_name) ?>
                            </div>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 70%;">Article Title</th>
                                            <th style="width: 30%; text-align: right;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($articles as $row): ?>
                                            <tr>
                                                <td><?= htmlspecialchars(formatTabName($row['title'])) ?></td>
                                                <td style="text-align: right;">
                                                    <button type="button" class="action-btn btn-edit" onclick="editArticle(
                                                        <?= (int)$row['id'] ?>, 
                                                        <?= (int)$row['category_id'] ?>, 
                                                        <?= htmlspecialchars(json_encode($row['title']), ENT_QUOTES, 'UTF-8') ?>, 
                                                        <?= htmlspecialchars(json_encode($row['content']), ENT_QUOTES, 'UTF-8') ?>
                                                    )" title="Edit">
                                                        <i class="fas fa-pen"></i> Edit
                                                    </button>
                                                    
                                                    <form method="POST" action="handle_submissions.php" style="display:inline;" onsubmit="return confirm('Permanently delete this article?');">
                                                        <input type="hidden" name="article_id" value="<?= $row['id'] ?>">
                                                        <button type="submit" name="delete_article" class="action-btn btn-del" title="Delete">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center; padding: 60px 20px; color:var(--text-secondary); font-size: 16px;">
                        <i class="fas fa-file-alt" style="font-size: 40px; margin-bottom: 15px; opacity: 0.5;"></i><br>
                        No articles found. Start publishing!
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <script>
        // --- THEME MANAGEMENT ---
        function initTheme() {
            const savedTheme = localStorage.getItem('admin_editor_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
            updateThemeIcons(savedTheme);
        }

        function toggleTheme() {
            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('admin_editor_theme', newTheme);
            
            // Animate icons
            document.querySelectorAll('.theme-toggle-btn i').forEach(icon => {
                icon.classList.add('rotate-anim');
                setTimeout(() => icon.classList.remove('rotate-anim'), 500);
            });
            
            setTimeout(() => updateThemeIcons(newTheme), 150);
        }

        function updateThemeIcons(theme) {
            document.querySelectorAll('.theme-toggle-btn i').forEach(icon => {
                icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            });
        }
        
        initTheme(); // Run on load

        // --- SIDEBAR & MODAL LOGIC ---
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.querySelector('.mobile-overlay').classList.toggle('active');
        }

        function openArticlesModal() {
            document.getElementById('articlesModal').classList.add('active');
            document.getElementById('sidebar').classList.remove('active');
            document.querySelector('.mobile-overlay').classList.remove('active');
        }

        function closeArticlesModal() {
            document.getElementById('articlesModal').classList.remove('active');
        }

        // --- EDITOR LOGIC ---
        function insertTag(openTag, closeTag) {
            const textarea = document.getElementById('form_content');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = textarea.value;
            const selectedText = text.substring(start, end);
            const replacement = openTag + selectedText + closeTag;
            
            textarea.value = text.substring(0, start) + replacement + text.substring(end);
            textarea.selectionStart = start + openTag.length;
            textarea.selectionEnd = end + openTag.length;
            textarea.focus();
        }

        function editArticle(id, catId, title, content) {
            document.getElementById('form_article_id').value = id;
            document.getElementById('form_category_id').value = catId;
            document.getElementById('form_title').value = title;
            document.getElementById('form_content').value = content;
            
            document.getElementById('form_submit_btn').innerHTML = '<i class="fas fa-save"></i> Update Article';
            document.getElementById('form_cancel_btn').style.display = 'block';
            
            if(document.getElementById('preview_area').style.display === 'block') {
                togglePreview(); 
            }
            
            closeArticlesModal();
        }

        function cancelEdit() {
            document.getElementById('form_article_id').value = '';
            document.getElementById('form_category_id').value = '';
            document.getElementById('form_title').value = '';
            document.getElementById('form_content').value = '';
            
            document.getElementById('form_submit_btn').innerHTML = '<i class="fas fa-paper-plane"></i> Publish Article';
            document.getElementById('form_cancel_btn').style.display = 'none';
        }

        // --- MARKDOWN PREVIEWER (Theme Adaptive) ---
        function togglePreview() {
            const editor = document.getElementById('form_content');
            const preview = document.getElementById('preview_area');
            const btn = document.getElementById('previewToggleBtn');
            const toolbar = document.getElementById('editor_toolbar');

            if (preview.style.display === 'none' || preview.style.display === '') {
                preview.innerHTML = parseCustomMarkdown(editor.value);
                editor.style.display = 'none';
                toolbar.style.display = 'none';
                preview.style.display = 'block';
                btn.innerHTML = '<i class="fas fa-pen"></i> Edit Mode';
            } else {
                editor.style.display = 'block';
                toolbar.style.display = 'flex';
                preview.style.display = 'none';
                btn.innerHTML = '<i class="fas fa-eye"></i> Preview';
            }
        }

        function parseCustomMarkdown(text) {
            // Escape HTML tags to prevent breaking
            let html = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            
            // Core Formatting
            html = html.replace(/\*\*(.*?)\*\*/g, '<strong style="color: var(--accent-color); font-weight: 600;">$1</strong>');
            html = html.replace(/\*(.*?)\*/g, '<i>$1</i>');
            html = html.replace(/__(.*?)__/g, '<u>$1</u>');
            html = html.replace(/~~(.*?)~~/g, '<del style="color: var(--text-secondary);">$1</del>');
            html = html.replace(/`(.*?)`/g, '<code style="background: var(--input-bg); padding: 2px 6px; border-radius: 4px; color: #d97706; font-family: monospace; border: 1px solid var(--border-color); font-size: 13px;">$1</code>');
            
            // Block Elements
            html = html.replace(/^### (.*$)/gim, '<h3 style="color: var(--accent-color); margin: 25px 0 10px 0; font-size: 15px; text-transform: uppercase; letter-spacing: 0.5px;">$1</h3>');
            html = html.replace(/^## (.*$)/gim, '<h2 style="color: var(--text-primary); margin: 30px 0 15px 0; font-size: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">$1</h2>');
            html = html.replace(/^\* (.*$)/gim, '<div style="margin-bottom: 8px; padding-left: 15px; text-indent: -12px; color: var(--text-secondary);">&bull; $1</div>');
            html = html.replace(/^&gt; (.*$)/gim, '<blockquote style="border-left: 3px solid var(--accent-color); margin: 18px 0; color: var(--text-secondary); background: var(--nav-hover-bg); padding: 14px 18px; border-radius: 0 6px 6px 0; font-size: 14px; font-style: italic;">$1</blockquote>');
            
            // Horizontal Line
            html = html.replace(/^---$/gim, '<hr style="border: none; border-top: 1px dashed var(--border-color); margin: 30px 0;">');
            
            // Line breaks
            html = html.replace(/\n/g, '<br>');
            
            // --- GAP FIXER: Clean up extra breaks around block elements ---
            html = html.replace(/(<\/div>)<br>/g, '$1'); 
            html = html.replace(/(?:<br\s*\/?>\s*)*<hr([^>]*)>(?:<br\s*\/?>\s*)*/g, '<hr$1>');
            html = html.replace(/(?:<br\s*\/?>\s*)*<h2([^>]*)>(?:<br\s*\/?>\s*)*/g, '<h2$1>');
            html = html.replace(/(?:<br\s*\/?>\s*)*<h3([^>]*)>(?:<br\s*\/?>\s*)*/g, '<h3$1>');
            html = html.replace(/(?:<br\s*\/?>\s*)*<blockquote([^>]*)>(?:<br\s*\/?>\s*)*/g, '<blockquote$1>');
            
            return html || '<span style="color:var(--text-secondary); font-style:italic;">Nothing to preview yet...</span>';
        }
    </script>
</body>
</html>
