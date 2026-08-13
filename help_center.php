<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
require_once '../db_connect.php';

// Helper function to format Tab Names to Title Case (e.g. "Account Settings & Policy")
function formatTabName($name) {
    return ucwords(strtolower(trim($name)));
}

// 1. Advanced Content Parser (Theme-Adaptive Version)
function parseArticleContent($text) {
    // Escape HTML for security
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    
    // --- BLOCK ELEMENTS (Must be parsed FIRST to prevent overlap) ---
    // Sub-headings (###)
    $text = preg_replace('/^###\s+(.*?)\r?$/m', '<h3 class="md-h3">$1</h3>', $text);
    
    // Main headings (##)
    $text = preg_replace('/^##\s+(.*?)\r?$/m', '<h2 class="md-h2">$1</h2>', $text);
    
    // Bullet Points (Extracts lists before inline styling can accidentally trigger)
    $text = preg_replace('/^\s*\*\s+(.*?)\r?$/m', '<div class="md-bullet">&bull; $1</div>', $text);
    
    // Blockquotes (>) 
    $text = preg_replace('/^&gt;\s+(.*?)\r?$/m', '<blockquote class="md-quote">$1</blockquote>', $text);
    
    // Horizontal Line (---)
    $text = preg_replace('/^---\s*\r?$/m', '<hr class="md-hr">', $text);
    
    // --- INLINE ELEMENTS ---
    // Code blocks / Highlights
    $text = preg_replace('/`(.*?)`/s', '<code class="md-code">$1</code>', $text);
    
    // Double Asterisk Bold (Sky-Blue Bold)
    $text = preg_replace('/\*\*(.*?)\*\*/s', '<strong class="md-bold-accent">$1</strong>', $text);
    
    // Single Asterisk Bold (Normal Bold adapting to theme)
    $text = preg_replace('/(?<!\*)\*([^\n\*]+)\*(?!\*)/s', '<strong class="md-bold-normal">$1</strong>', $text);
    
    // Underline
    $text = preg_replace('/__(.*?)__/s', '<u>$1</u>', $text);
    
    // Strikethrough
    $text = preg_replace('/~~(.*?)~~/s', '<del class="md-del">$1</del>', $text);
    
    // Links (Auto-detect HTTP/HTTPS)
    $url_pattern = '/(?<!href=")(https?:\/\/[a-zA-Z0-9\-\.\/\?\&\=\+\_\:\#\%]+)/i';
    $text = preg_replace($url_pattern, '<a href="$1" target="_blank" class="article-link">$1 <i class="fas fa-external-link-alt" style="font-size: 10px;"></i></a>', $text);
    
    // Add standard Line breaks
    $text = nl2br($text);
    
    // MASTER GAP FIXER: Destroys all extra <br> tags around block elements!
    $text = preg_replace('/(?:<br\s*\/?>\s*)*(<\/?(?:div|hr|h1|h2|h3|h4|blockquote)[^>]*>)(?:<br\s*\/?>\s*)*/i', '$1', $text);
    
    return $text;
}

// 2. AJAX Handler for loading tabs without changing the URL
if (isset($_GET['ajax']) && $_GET['ajax'] == '1' && isset($_GET['token'])) {
    header('Content-Type: application/json');
    $token = $_GET['token'];
    
    $stmt = $conn->prepare("SELECT * FROM help_articles WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        $formatted_date = "RECENTLY UPDATED";
        if (isset($row['updated_at']) && $row['updated_at']) {
            $formatted_date = strtoupper(date('d M Y', strtotime($row['updated_at']))) . date(' , h:i A', strtotime($row['updated_at']));
        }
        
        echo json_encode([
            'status' => 'success',
            'title' => htmlspecialchars(formatTabName($row['title'])),
            'token' => htmlspecialchars($row['token']),
            'content' => parseArticleContent($row['content']),
            'updated_at' => $formatted_date
        ]);
    } else {
        echo json_encode(['status' => 'error']);
    }
    
    $stmt->close();
    exit();
}

// 3. Get the requested article token
$active_token = isset($_GET['token']) ? $_GET['token'] : '';

// 4. Fetch Categories
$cat_query = "SELECT * FROM help_categories ORDER BY id ASC";
$cat_result = $conn->query($cat_query);
$categories = [];

if ($cat_result && $cat_result->num_rows > 0) {
    while($row = $cat_result->fetch_assoc()) {
        $categories[$row['id']] = $row;
        $categories[$row['id']]['articles'] = [];
    }
}

// 5. Fetch Articles
$art_query = "SELECT * FROM help_articles ORDER BY id ASC";
$art_result = $conn->query($art_query);
$active_article = null;
$all_articles_flat = []; // To store all articles for JS search and suggestions

if ($art_result && $art_result->num_rows > 0) {
    while($row = $art_result->fetch_assoc()) {
        if (isset($categories[$row['category_id']])) {
            $categories[$row['category_id']]['articles'][] = $row;
        }
        if ($active_token && $row['token'] === $active_token) {
            $active_article = $row;
        }
        // Store in flat array for search capability
        $all_articles_flat[] = [
            'token' => $row['token'],
            'title' => formatTabName($row['title'])
        ];
    }
}

// Helper function to get random articles for empty categories
function getRandomArticles($articles, $count = 5) {
    if (empty($articles)) return [];
    $max = min($count, count($articles));
    $keys = array_rand($articles, $max);
    if (!is_array($keys)) $keys = [$keys];
    $random_arts = [];
    foreach ($keys as $k) {
        $random_arts[] = $articles[$k];
    }
    return $random_arts;
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Help Center | CONNECT SRMAP</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        /* === THEME VARIABLES (GLASSMORPHISM) === */
        :root {
            /* Light Mode Glassmorphism */
            --bg-color: #f4f6f9;
            --bg-gradient: linear-gradient(135deg, #fdfbfb 0%, #ebedee 100%);
            --glass-bg: rgba(255, 255, 255, 0.7);
            --glass-border: rgba(255, 255, 255, 0.4);
            --glass-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
            --text-primary: #1a1a1a;
            --text-secondary: #555555;
            --accent-color: #0d97ff;
            --accent-hover: rgba(13, 151, 255, 0.1);
            --danger-color: #e53e3e;
            --link-color: #0056b3; 
            
            /* Markdown Elements */
            --code-bg: rgba(240, 243, 246, 0.7);
            --code-color: #d97706;
            --quote-bg: rgba(248, 250, 252, 0.6);
            --hr-color: rgba(226, 232, 240, 0.8);
            --nav-hover-bg: rgba(0, 0, 0, 0.04);
            --nav-active-bg: rgba(13, 151, 255, 0.08);
            
            /* Skeleton Loaders */
            --skel-bg-1: rgba(226, 232, 240, 0.8);
            --skel-bg-2: rgba(203, 213, 225, 0.8);
            
            --font-family: 'Poppins', sans-serif;
        }

        [data-theme="dark"] {
            /* Dark Mode Glassmorphism */
            --bg-color: #000000;
            --bg-gradient: radial-gradient(circle at 10% 20%, rgba(13, 151, 255, 0.08) 0%, transparent 40%), radial-gradient(circle at 90% 80%, rgba(13, 151, 255, 0.05) 0%, transparent 40%), #000000;
            --glass-bg: rgba(20, 20, 20, 0.6);
            --glass-border: rgba(255, 255, 255, 0.06);
            --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
            --text-primary: #ffffff;
            --text-secondary: #a3a3a3;
            --accent-color: #0d97ff;
            --accent-hover: rgba(13, 151, 255, 0.15);
            --danger-color: #ff4d4d;
            --link-color: #33b5e5; 
            
            /* Markdown Elements */
            --code-bg: rgba(21, 21, 21, 0.7);
            --code-color: #ffcc00;
            --quote-bg: rgba(17, 17, 17, 0.6);
            --hr-color: rgba(34, 34, 34, 0.8);
            --nav-hover-bg: rgba(255, 255, 255, 0.06);
            --nav-active-bg: rgba(13, 151, 255, 0.12);
            
            /* Skeleton Loaders */
            --skel-bg-1: rgba(26, 26, 26, 0.8);
            --skel-bg-2: rgba(42, 42, 42, 0.8);
        }

        /* === GLOBAL STYLES & SMOOTH TRANSITIONS === */
        * { 
            box-sizing: border-box; 
            margin: 0; 
            padding: 0; 
        }

        html, body {
            max-width: 100vw;
            overflow-x: hidden; 
        }

        body {
            background: var(--bg-gradient);
            background-attachment: fixed;
            background-color: var(--bg-color);
            color: var(--text-primary);
            font-family: var(--font-family);
            font-size: 14px; 
            display: flex;
            height: 100vh;
            overflow: hidden; 
            -webkit-font-smoothing: antialiased;
            transition: background-color 0.4s ease, color 0.4s ease;
        }

        .glass-panel {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            box-shadow: var(--glass-shadow);
        }

        .sidebar, .main-content, .mobile-header, .subtab-link, .article-body, .developer-profile, summary, .md-quote, .md-code {
            transition: background-color 0.4s ease, background 0.4s ease, color 0.4s ease, border-color 0.4s ease, box-shadow 0.4s ease;
        }

        .sidebar, .main-content {
            -ms-overflow-style: none;
            scrollbar-width: none; 
        }
        .sidebar::-webkit-scrollbar, .main-content::-webkit-scrollbar { display: none; }

        /* === MOBILE HEADER === */
        .mobile-header {
            display: none;
            padding: 10px 15px;
            align-items: center;
            justify-content: space-between;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            z-index: 1001; 
            border-bottom: 1px solid var(--glass-border);
        }
        
        .mobile-brand {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 16px; 
            font-weight: 600;
            color: var(--text-primary);
        }

        .mobile-actions {
            display: flex;
            align-items: center;
            gap: 5px; 
        }

        .icon-btn {
            background: transparent;
            border: none;
            color: var(--text-primary);
            font-size: 18px; 
            cursor: pointer;
            width: 38px; 
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .icon-btn:hover, .icon-btn:active { 
            background: var(--nav-hover-bg); 
            color: var(--accent-color); 
        }

        .theme-icon { transition: transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1); }
        .theme-icon.rotate-anim { transform: rotate(360deg) scale(1.1); }

        /* === SIDEBAR === */
        .sidebar {
            width: 280px; 
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), background-color 0.4s ease;
            z-index: 1000;
            border-right: 1px solid var(--glass-border);
        }

        .sidebar-header {
            padding: 20px;
            font-size: 16px; 
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: var(--accent-color);
            border-bottom: 1px solid var(--glass-border);
        }
        
        .sidebar-header-title { display: flex; align-items: center; }
        .sidebar-header-actions { display: flex; gap: 5px; }

        details { border-bottom: 1px solid var(--glass-border); }

        summary {
            padding: 15px 20px;
            cursor: pointer;
            font-size: 14px; 
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            list-style: none;
            color: var(--text-primary);
        }
        summary::-webkit-details-marker { display: none; }
        summary:hover { background: var(--nav-hover-bg); }
        summary i:first-child {
            color: var(--text-secondary);
            width: 20px;
            text-align: center;
            font-size: 15px;
            transition: color 0.3s;
        }
        details[open] summary i:first-child { color: var(--accent-color); }
        details[open] summary { 
            background: var(--nav-active-bg); 
            border-left: 3px solid var(--accent-color); 
            padding-left: 17px; 
        }

        /* Sub-tabs */
        .subtabs { padding: 8px 0; background: rgba(0,0,0,0.01); }
        [data-theme="dark"] .subtabs { background: rgba(0,0,0,0.1); }

        .subtab-link {
            display: block;
            padding: 10px 20px 10px 50px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 13px; 
            font-weight: 400;
            position: relative;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }
        .subtab-link:hover { color: var(--text-primary); background: var(--nav-hover-bg); }
        
        .subtab-link.active { 
            color: var(--accent-color); 
            font-weight: 600; 
            background: linear-gradient(90deg, var(--nav-active-bg) 0%, transparent 100%);
            border-left: 3px solid var(--accent-color);
            padding-left: 47px; 
        }
        .subtab-link.active::before {
            content: '';
            position: absolute;
            left: 32px;
            top: 50%;
            transform: translateY(-50%);
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--accent-color);
            box-shadow: 0 0 6px var(--accent-color);
        }

        /* Empty Tab Messaging */
        .empty-tab-msg {
            padding: 20px 20px 10px 20px;
            text-align: center;
            color: var(--text-secondary);
            font-size: 13px;
        }
        .empty-tab-msg i {
            font-size: 24px;
            margin-bottom: 8px;
            opacity: 0.5;
            color: var(--accent-color);
        }
        .suggested-title {
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--accent-color);
            margin: 10px 0 5px 20px;
            letter-spacing: 0.5px;
        }

        /* === MAIN CONTENT === */
        .main-content {
            flex: 1;
            padding: 40px 60px; 
            overflow-y: auto;
            overflow-x: hidden;
            position: relative;
            border-radius: 12px;
            margin: 12px 12px 12px 0;
            width: 100%;
        }

        .article-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--glass-border);
            gap: 15px;
        }

        .article-title {
            font-size: 24px; 
            font-weight: 600;
            color: var(--text-primary);
            line-height: 1.3;
            flex: 1;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .copy-link-btn {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            color: var(--text-secondary);
            padding: 8px 14px;
            border-radius: 50px;
            cursor: pointer;
            font-size: 12.5px; 
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            font-family: var(--font-family);
            white-space: nowrap;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        }
        .copy-link-btn i { font-size: 12.5px; }
        .copy-link-btn:hover {
            background: var(--accent-hover);
            border-color: var(--accent-color);
            color: var(--accent-color);
            transform: translateY(-2px);
        }
        .copy-link-btn.copied {
            background: #10b981;
            border-color: #10b981;
            color: white;
        }

        /* === ARTICLE BODY (MARKDOWN) === */
        .article-body {
            font-size: 14px; 
            line-height: 1.7;
            color: var(--text-secondary);
            width: 100%;
            padding-bottom: 60px;
            max-width: 800px;
            word-wrap: break-word;
            overflow-wrap: break-word; 
        }

        .article-body p { margin-bottom: 14px; }
        .md-h2 { color: var(--text-primary); margin: 25px 0 12px 0; font-size: 18px; border-bottom: 1px solid var(--glass-border); padding-bottom: 6px; font-weight: 600; }
        .md-h3 { color: var(--accent-color); margin: 20px 0 10px 0; font-size: 14.5px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
        .md-bullet { margin-bottom: 8px; padding-left: 15px; text-indent: -12px; color: var(--text-secondary); }
        .md-quote { border-left: 3px solid var(--accent-color); margin: 15px 0; padding: 12px 16px; background: var(--quote-bg); color: var(--text-secondary); border-radius: 0 6px 6px 0; font-size: 13.5px; font-style: italic; }
        .md-hr { border: none; border-top: 1px dashed var(--hr-color); margin: 30px 0; }
        .md-code { background: var(--code-bg); padding: 2px 6px; border-radius: 4px; color: var(--code-color); font-family: monospace; border: 1px solid var(--glass-border); font-size: 13px; word-break: break-all; }
        .md-bold-accent { color: var(--accent-color); font-weight: 600; }
        .md-bold-normal { color: var(--text-primary); font-weight: 600; }
        .md-del { color: var(--text-secondary); text-decoration: line-through; opacity: 0.7; }
        
        .article-link {
            color: var(--link-color);
            text-decoration: underline; 
            text-underline-offset: 3px; 
            transition: all 0.2s;
            font-weight: 500;
            word-break: break-all; 
        }
        .article-link:hover { opacity: 0.8; }

        .article-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px dashed var(--hr-color);
            color: #888;
            font-size: 12.5px;
            font-style: italic;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .developer-profile {
            margin: 25px 0;
            padding: 20px;
            background: var(--nav-active-bg);
            border: 1px solid var(--glass-border);
            border-left: 4px solid var(--accent-color);
        }
        .developer-profile h3 { font-size: 15px; color: var(--accent-color); margin-bottom: 10px; }
        .developer-profile p.dev-name { font-size: 14px; color: var(--text-primary); margin-bottom: 8px; font-weight: 500; }
        .developer-profile p.dev-desc { font-size: 13.5px; color: var(--text-secondary); line-height: 1.6; margin-bottom: 20px; }

        /* === SEARCH FULL PAGE POPUP === */
        .search-overlay {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            z-index: 9999;
            display: none;
            flex-direction: column;
            align-items: center;
            padding-top: 12vh;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .search-overlay.active {
            display: flex;
            opacity: 1;
        }
        .search-wrapper {
            width: 90%;
            max-width: 650px;
            background: transparent;
        }
        .search-input-box {
            display: flex;
            align-items: center;
            border-bottom: 3px solid var(--accent-color);
            padding-bottom: 15px;
            gap: 15px;
        }
        .search-input-box i {
            font-size: 24px;
            color: var(--accent-color);
        }
        .search-input-box input {
            flex: 1;
            background: transparent;
            border: none;
            font-size: 24px;
            color: var(--text-primary);
            outline: none;
            font-family: var(--font-family);
        }
        .search-input-box input::placeholder {
            color: var(--text-secondary);
            opacity: 0.6;
        }
        .close-search-btn {
            background: var(--nav-hover-bg);
            border: none;
            border-radius: 50%;
            width: 40px; height: 40px;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-primary);
            font-size: 20px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .close-search-btn:hover { background: var(--danger-color); color: white; }
        
        .search-results-container {
            margin-top: 30px;
            max-height: 60vh;
            overflow-y: auto;
            padding-right: 10px;
        }
        /* Custom Scrollbar for Search */
        .search-results-container::-webkit-scrollbar { width: 6px; }
        .search-results-container::-webkit-scrollbar-track { background: transparent; }
        .search-results-container::-webkit-scrollbar-thumb { background: var(--glass-border); border-radius: 10px; }
        
        .search-item {
            padding: 18px 20px;
            border-radius: 12px;
            margin-bottom: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 15px;
            border: 1px solid transparent;
            transition: all 0.2s;
            color: var(--text-primary);
        }
        .search-item:hover {
            background: var(--nav-active-bg);
            border-color: var(--glass-border);
            transform: translateX(5px);
        }
        .search-item i {
            color: var(--accent-color);
            font-size: 18px;
            opacity: 0.8;
        }
        .search-highlight {
            background: rgba(13, 151, 255, 0.2);
            color: var(--accent-color);
            font-weight: 700;
            padding: 0 4px;
            border-radius: 4px;
        }
        .no-search-results {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
            font-size: 16px;
        }

        /* === SKELETON LOADER === */
        .skeleton {
            background: var(--skel-bg-1);
            background: linear-gradient(90deg, var(--skel-bg-1) 25%, var(--skel-bg-2) 50%, var(--skel-bg-1) 75%);
            background-size: 200% 100%;
            animation: skeletonLoading 1.5s infinite;
            border-radius: 4px;
        }
        @keyframes skeletonLoading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        .skel-title { height: 32px; width: 60%; margin-bottom: 10px; }
        .skel-btn { height: 30px; width: 100px; border-radius: 50px; }
        .skel-text { height: 16px; width: 100%; margin-bottom: 12px; }
        .skel-text.short { width: 75%; }
        .skel-text.medium { width: 90%; }
        .skel-space { margin-top: 30px; }

        /* === MOBILE OVERLAY === */
        .mobile-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 998;
            backdrop-filter: blur(4px);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        /* === RESPONSIVE LAYOUT === */
        @media (max-width: 900px) {
            body { flex-direction: column; overflow-x: hidden; }
            
            .main-content { margin: 0; border-radius: 0; padding: 80px 20px 40px 20px; border: none; border-top: 1px solid var(--glass-border); width: 100vw; box-sizing: border-box; }
            
            .mobile-header { display: flex; }
            .sidebar-header { display: none; } 
            
            .article-header { flex-direction: column; align-items: flex-start; gap: 12px; margin-bottom: 25px; width: 100%; }
            .article-title { font-size: 22px; width: 100%; }
            
            .sidebar {
                position: fixed;
                top: 0; left: 0;
                height: 100vh; 
                width: 100vw; 
                max-width: 100vw;
                transform: translateX(-100%);
                padding-top: 65px; 
                z-index: 1000; 
                border-right: none;
            }
            .sidebar.active { transform: translateX(0); }
            .mobile-overlay.active { display: block; opacity: 1; }
            
            .search-input-box input { font-size: 18px; }
        }
    </style>
</head>
<body>

    <div class="search-overlay" id="search-modal">
        <div class="search-wrapper">
            <div class="search-input-box">
                <i class="fas fa-search"></i>
                <input type="text" id="global-search-input" placeholder="Type to search articles..." autocomplete="off">
                <button class="close-search-btn" onclick="closeSearch()"><i class="fas fa-times"></i></button>
            </div>
            <div class="search-results-container" id="search-results-list">
                </div>
        </div>
    </div>

    <div class="mobile-header glass-panel">
        <div class="mobile-brand">
            <i class="fas fa-headset" style="color: var(--accent-color);"></i> Help Center
        </div>
        <div class="mobile-actions">
            <button class="icon-btn" onclick="openSearch()" title="Search Articles">
                <i class="fas fa-search"></i>
            </button>
            <button class="icon-btn" onclick="toggleTheme()" title="Toggle Theme">
                <i class="fas fa-moon theme-icon"></i>
            </button>
            <button class="icon-btn" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </div>

    <div class="mobile-overlay" onclick="toggleSidebar()"></div>

    <div class="sidebar glass-panel" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-header-title">
                <i class="fas fa-headset" style="margin-right: 10px;"></i> Support
            </div>
            <div class="sidebar-header-actions">
                <button class="icon-btn" onclick="openSearch()" title="Search">
                    <i class="fas fa-search" style="font-size: 16px;"></i>
                </button>
                <button class="icon-btn" onclick="toggleTheme()" title="Toggle Theme">
                    <i class="fas fa-moon theme-icon" style="font-size: 16px;"></i>
                </button>
            </div>
        </div>
        
        <?php foreach ($categories as $cat): ?>
            <details class="acc-details" <?php 
                $isOpen = false;
                if ($active_token) {
                    foreach($cat['articles'] as $art) {
                        if($art['token'] === $active_token) $isOpen = true;
                    }
                }
                echo $isOpen ? 'open' : ''; 
            ?>>
                <summary>
                    <i class="<?= htmlspecialchars($cat['icon_class'] ?? 'fas fa-folder') ?>"></i> 
                    <?= htmlspecialchars(formatTabName($cat['name'])) ?>
                    <i class="fas fa-chevron-down" style="margin-left:auto; font-size:11px; opacity: 0.5;"></i>
                </summary>
                
                <?php if (empty($cat['articles'])): ?>
                    <div class="empty-tab-msg">
                        <i class="fas fa-folder-open"></i><br>
                        No content inside this tab yet.
                    </div>
                    <?php $suggested = getRandomArticles($all_articles_flat, 5); ?>
                    <?php if(!empty($suggested)): ?>
                        <div class="suggested-title">Suggested For You</div>
                        <div class="subtabs">
                            <?php foreach ($suggested as $s_art): ?>
                                <a href="javascript:void(0);" onclick="loadArticle('<?= htmlspecialchars($s_art['token']) ?>', this)" class="subtab-link" data-token="<?= htmlspecialchars($s_art['token']) ?>">
                                    <i class="far fa-file-alt" style="margin-right: 6px; opacity: 0.6;"></i> <?= htmlspecialchars($s_art['title']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="subtabs">
                        <?php foreach ($cat['articles'] as $art): ?>
                            <a href="javascript:void(0);" onclick="loadArticle('<?= htmlspecialchars($art['token']) ?>', this)" class="subtab-link <?= ($art['token'] === $active_token) ? 'active' : '' ?>" data-token="<?= htmlspecialchars($art['token']) ?>">
                                <?= htmlspecialchars(formatTabName($art['title'])) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </details>
        <?php endforeach; ?>
    </div>

    <div class="main-content glass-panel" id="dynamic-content">
        
        <?php if ($active_article): ?>
            <div class="article-header">
                <h1 class="article-title"><?= htmlspecialchars(formatTabName($active_article['title'])) ?></h1>
                <button class="copy-link-btn" onclick="copyToClipboard('<?= htmlspecialchars($active_article['token']) ?>', this)">
                    <i class="fas fa-link"></i> <span>Copy Link</span>
                </button>
            </div>
            
            <div class="article-body">
                <?= parseArticleContent($active_article['content']) ?>
                <div class="article-footer">
                    <i class="fas fa-clock"></i> 
                    <?php
                        if (isset($active_article['updated_at']) && $active_article['updated_at']) {
                            echo strtoupper(date('d M Y', strtotime($active_article['updated_at']))) . date(' , h:i A', strtotime($active_article['updated_at']));
                        } else {
                            echo "RECENTLY UPDATED";
                        }
                    ?>
                </div>
            </div>

        <?php else: ?>
            <div class="article-header">
                <h1 class="article-title" style="color: var(--accent-color);">Welcome to CONNECT SRMAP</h1>
            </div>
            
            <div class="article-body">
                <p>
                    Welcome to CONNECT SRMAP! We are dedicated to providing a secure and enriching platform exclusively for our registered users. To access this page, please log in with your credentials. At CONNECT SRMAP, we value your privacy and strive to ensure a seamless and personalized experience for all users. Join us and explore the features crafted to connect and engage our vibrant community!
                </p>

                <p>CONNECT SRMAP is the official platform for SRM AP students. By using our services, you agree to these terms:</p>

                <h3 class="md-h3"><i class="fas fa-exclamation-triangle"></i> Important Notice About This Platform</h3>
                <div class="md-bullet">&bull; This is a personal project created to help SRM AP students</div>
                <div class="md-bullet">&bull; Designed to showcase development skills while providing useful campus information</div>
                <div class="md-bullet">&bull; Not an official SRM University product - but made with genuine care for the student community</div>

                <div class="developer-profile">
                    <h3><i class="fas fa-laptop-code" style="margin-right: 6px;"></i> About the Developer</h3>
                    <p class="dev-name">Name: Balaga Nagamani Sankar (Nani)</p>
                    <p class="dev-desc">
                        I am a Passionate Full Stack Developer with hands-on experience in building 30+ professional websites and scalable real-time applications. I am highly skilled in the MERN stack, PHP, and MySQL.
                    </p>
                    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <a href="https://myresume.purlyedit.in/new" target="_blank" class="copy-link-btn" style="text-decoration: none;">
                            <i class="fas fa-globe"></i> <span>Portfolio</span>
                        </a>
                        <a href="https://www.linkedin.com/in/nanibalaga/" target="_blank" class="copy-link-btn" style="text-decoration: none;">
                            <i class="fab fa-linkedin"></i> <span>LinkedIn</span>
                        </a>
                    </div>
                </div>

                <h3 class="md-h3"><i class="fas fa-life-ring"></i> Need Help?</h3>
                <p>Contact admin immediately for Account security concerns, reporting issues, or technical help.</p>
                <p>
                    <strong style="color: var(--text-primary);"><i class="fas fa-envelope" style="color: var(--accent-color);"></i> Email Support:</strong> <a href="mailto:connectsrmap@purlyedit.in" class="article-link">connectsrmap@purlyedit.in</a>
                </p>
                
                <div style="margin-top: 50px; padding-top: 20px; border-top: 1px dashed var(--hr-color); text-align: center;">
                    <p style="font-size: 12.5px; color: var(--text-secondary);">
                        <em>Project Purpose: This platform was created by SRM AP students to help fellow students access campus resources more easily while demonstrating technical capabilities. We appreciate your support!</em>
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // --- GLOBAL ARTICLES DATA FOR SEARCH ---
        const allArticlesData = <?= json_encode($all_articles_flat) ?>;

        // --- THEME MANAGEMENT ---
        const body = document.documentElement;
        
        function initTheme() {
            const savedTheme = localStorage.getItem('srmap_help_theme') || 'light';
            body.setAttribute('data-theme', savedTheme);
            updateThemeIcons(savedTheme);
        }

        function toggleTheme() {
            const currentTheme = body.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            document.querySelectorAll('.theme-icon').forEach(icon => {
                icon.classList.add('rotate-anim');
                setTimeout(() => {
                    icon.classList.remove('rotate-anim');
                }, 500);
            });
            
            body.setAttribute('data-theme', newTheme);
            localStorage.setItem('srmap_help_theme', newTheme);
            
            setTimeout(() => {
                updateThemeIcons(newTheme);
            }, 150);
        }

        function updateThemeIcons(theme) {
            document.querySelectorAll('.theme-icon').forEach(icon => {
                icon.className = theme === 'light' ? 'fas fa-moon theme-icon' : 'fas fa-sun theme-icon';
            });
        }
        
        initTheme();

        // --- SEARCH FUNCTIONALITY ---
        const searchModal = document.getElementById('search-modal');
        const searchInput = document.getElementById('global-search-input');
        const searchResultsList = document.getElementById('search-results-list');

        function openSearch() {
            searchInput.value = '';
            searchResultsList.innerHTML = '';
            searchModal.style.display = 'flex';
            setTimeout(() => {
                searchModal.classList.add('active');
                searchInput.focus();
            }, 10);
            
            // Auto close sidebar if open on mobile
            if (window.innerWidth <= 900) {
                document.getElementById('sidebar').classList.remove('active');
                document.querySelector('.mobile-overlay').classList.remove('active');
                const toggleIcon = document.querySelector('.mobile-actions .fa-times');
                if(toggleIcon) toggleIcon.className = 'fas fa-bars';
            }
        }

        function closeSearch() {
            searchModal.classList.remove('active');
            setTimeout(() => { 
                searchModal.style.display = 'none'; 
            }, 300);
        }

        // Close search on escape key or clicking outside
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && searchModal.classList.contains('active')) {
                closeSearch();
            }
        });
        
        searchModal.addEventListener('click', (e) => {
            if (e.target === searchModal) closeSearch();
        });

        // Real-time Search Processing
        searchInput.addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase().trim();
            searchResultsList.innerHTML = '';

            if (query.length === 0) return;

            const matches = allArticlesData.filter(art => art.title.toLowerCase().includes(query));

            if (matches.length === 0) {
                searchResultsList.innerHTML = `
                    <div class="no-search-results">
                        <i class="fas fa-search" style="font-size: 30px; opacity:0.3; margin-bottom: 10px;"></i>
                        <p>No articles found for "<strong>${escapeHtml(query)}</strong>"</p>
                    </div>`;
                return;
            }

            matches.forEach(art => {
                // Highlight text using Regex
                const regex = new RegExp(`(${escapeRegExp(query)})`, 'gi');
                const highlightedTitle = art.title.replace(regex, '<span class="search-highlight">$1</span>');

                const itemDiv = document.createElement('div');
                itemDiv.className = 'search-item';
                itemDiv.innerHTML = `<i class="far fa-file-alt"></i> <div style="flex:1;">${highlightedTitle}</div>`;
                
                itemDiv.onclick = () => {
                    closeSearch();
                    
                    // Reset all active classes
                    document.querySelectorAll('.subtab-link').forEach(el => el.classList.remove('active'));
                    
                    // Find the sidebar link corresponding to this token and make it active
                    const sidebarLink = document.querySelector(`.subtab-link[data-token="${art.token}"]`);
                    if(sidebarLink) {
                        sidebarLink.classList.add('active');
                        const parentDetails = sidebarLink.closest('details');
                        if (parentDetails) parentDetails.setAttribute('open', '');
                    }
                    
                    // Load the article (pass the DOM element if found, else null)
                    loadArticle(art.token, sidebarLink);
                };
                
                searchResultsList.appendChild(itemDiv);
            });
        });

        // Utilities for safe Regex and HTML
        function escapeRegExp(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }
        function escapeHtml(unsafe) {
            return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }


        // --- NAVIGATION & AJAX ---
        function loadArticle(token, clickedElement) {
            const container = document.getElementById('dynamic-content');
            
            if (clickedElement) {
                document.querySelectorAll('.subtab-link').forEach(el => el.classList.remove('active'));
                clickedElement.classList.add('active');
            }

            // Responsive Skeleton Loader
            container.innerHTML = `
                <div class="article-header" style="border-bottom: 1px solid var(--glass-border);">
                    <div class="skeleton skel-title"></div>
                    <div class="skeleton skel-btn"></div>
                </div>
                <div class="article-body">
                    <div class="skeleton skel-text"></div>
                    <div class="skeleton skel-text medium"></div>
                    <div class="skeleton skel-text short"></div>
                    <div class="skel-space"></div>
                    <div class="skeleton skel-text"></div>
                    <div class="skeleton skel-text medium"></div>
                    <div class="skeleton skel-text short"></div>
                </div>
            `;

            // Auto-close sidebar on mobile
            if (window.innerWidth <= 900) {
                document.getElementById('sidebar').classList.remove('active');
                document.querySelector('.mobile-overlay').classList.remove('active');
                const toggleIcon = document.querySelector('.mobile-actions .fa-times');
                if(toggleIcon) toggleIcon.className = 'fas fa-bars';
            }

            // Promise.all enforces a strict minimum wait time for the skeleton animation to show beautifully
            Promise.all([
                fetch(`help_center.php?ajax=1&token=${token}`).then(response => response.json()),
                new Promise(resolve => setTimeout(resolve, 800)) // Adjusted down to 0.8s for better UX flow
            ])
            .then(([data]) => {
                if(data.status === 'success') {
                    container.innerHTML = `
                        <div class="article-header">
                            <h1 class="article-title">${data.title}</h1>
                            <button class="copy-link-btn" onclick="copyToClipboard('${data.token}', this)">
                                <i class="fas fa-link"></i> <span>Copy Link</span>
                            </button>
                        </div>
                        <div class="article-body">
                            ${data.content}
                            <div class="article-footer">
                                <i class="fas fa-clock"></i> ${data.updated_at}
                            </div>
                        </div>
                    `;
                } else {
                    container.innerHTML = `<div class="article-header"><h1 class="article-title" style="color:var(--danger-color);">Error: Article not found.</h1></div>`;
                }
            })
            .catch(() => {
                container.innerHTML = `<div class="article-header"><h1 class="article-title" style="color:var(--danger-color);">Network Error. Please try again.</h1></div>`;
            });
        }

        // --- SIDEBAR TOGGLE (MOBILE) ---
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.mobile-overlay');
            const menuBtn = document.querySelector('.mobile-actions button:last-child i');
            
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            
            if (sidebar.classList.contains('active')) {
                menuBtn.className = 'fas fa-times';
            } else {
                menuBtn.className = 'fas fa-bars';
            }
        }

        // --- ACCORDION LOGIC ---
        const allDetails = document.querySelectorAll('.acc-details');
        allDetails.forEach((targetDetail) => {
            targetDetail.addEventListener('click', (e) => {
                // Prevent auto-closing if clicking on an inside link
                if(e.target.tagName.toLowerCase() === 'a') return;
                
                allDetails.forEach((detail) => {
                    if (detail !== targetDetail) detail.removeAttribute('open');
                });
            });
        });

        // --- COPY LINK UTILITY ---
        function copyToClipboard(token, btnElement) {
            const url = window.location.origin + window.location.pathname + '?token=' + token;
            navigator.clipboard.writeText(url).then(() => {
                const icon = btnElement.querySelector('i');
                const text = btnElement.querySelector('span');
                
                btnElement.classList.add('copied');
                icon.className = 'fas fa-check';
                text.innerText = 'Copied!';
                
                setTimeout(() => {
                    btnElement.classList.remove('copied');
                    icon.className = 'fas fa-link';
                    text.innerText = 'Copy Link';
                }, 2000);
            });
        }
    </script>
</body>
</html>
