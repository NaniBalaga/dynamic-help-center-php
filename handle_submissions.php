<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
require_once '../db_connect.php';

/* * HELPER FUNCTION FOR YOUR FRONTEND DISPLAY PAGE
 * Use this function on the page where users view the articles.
 * Usage: echo parse_article_markdown($row['content']);
 */
function parse_article_markdown($text) {
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    
    // Core Formatting
    $text = preg_replace('/\*\*(.*?)\*\*/s', '<strong style="color: #0d97ff; font-weight: 600;">$1</strong>', $text);
    $text = preg_replace('/(?<!^)\*(.*?)\*(?!$)/s', '<i>$1</i>', $text);
    $text = preg_replace('/__(.*?)__/s', '<u>$1</u>', $text);
    $text = preg_replace('/~~(.*?)~~/s', '<del style="color: #666;">$1</del>', $text);
    $text = preg_replace('/`(.*?)`/s', '<code style="background: #111; padding: 2px 6px; border-radius: 4px; color: #ffcc00; font-family: monospace; border: 1px solid #222; font-size: 11.5px;">$1</code>', $text);
    
    // Block Elements
    $text = preg_replace('/^### (.*)$/m', '<h3 style="color: #0d97ff; margin: 20px 0 0 0; font-size: 13px; text-align: left; letter-spacing: 0.5px;">$1</h3>', $text);
    $text = preg_replace('/^## (.*)$/m', '<h2 style="color: #fff; margin: 20px 0 10px 0; font-size: 16px; border-bottom: 1px solid #222; padding-bottom: 5px;">$1</h2>', $text);
    $text = preg_replace('/^\* (.*)$/m', '<div style="margin-bottom: 6px; padding-left: 12px; text-indent: -10px; color: #bbb; font-size: 12px;">&bull; $1</div>', $text);
    $text = preg_replace('/^&gt; (.*)$/m', '<blockquote style="border-left: 3px solid #0d97ff; margin: 10px 0; color: #999; background: #0a0a0a; padding: 10px 15px; border-radius: 0 4px 4px 0; font-size: 12px;">$1</blockquote>', $text);
    
    // Horizontal Line (reduced margin)
    $text = preg_replace('/^---$/m', '<hr style="border: none; border-top: 1px solid #222; margin: 15px 0;">', $text);
    
    // Line breaks
    $text = nl2br($text);
    
    // --- GAP FIXER: Clean up extra breaks around block elements ---
    $text = preg_replace('/(<\/div>)<br \/>/i', '$1', $text);
    $text = preg_replace('/(?:<br\s*\/?>\s*)*<hr([^>]*)>(?:<br\s*\/?>\s*)*/i', '<hr$1>', $text);
    $text = preg_replace('/(?:<br\s*\/?>\s*)*<h2([^>]*)>(?:<br\s*\/?>\s*)*/i', '<h2$1>', $text);
    $text = preg_replace('/(?:<br\s*\/?>\s*)*<h3([^>]*)>(?:<br\s*\/?>\s*)*/i', '<h3$1>', $text);
    $text = preg_replace('/(?:<br\s*\/?>\s*)*<blockquote([^>]*)>(?:<br\s*\/?>\s*)*/i', '<blockquote$1>', $text);
    
    return $text;
}


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // --- ADD CATEGORY ---
    if (isset($_POST['add_category'])) {
        $cat_name = trim($_POST['cat_name']);
        $cat_icon = trim($_POST['cat_icon']);
        if (empty($cat_icon)) $cat_icon = 'fas fa-list';

        $stmt = $conn->prepare("INSERT INTO help_categories (name, icon_class) VALUES (?, ?)");
        $stmt->bind_param("ss", $cat_name, $cat_icon);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Category added successfully!";
            $_SESSION['msg_type'] = "success";
        } else {
            $_SESSION['message'] = "Error adding category.";
            $_SESSION['msg_type'] = "error";
        }
        $stmt->close();
    }

    // --- DELETE CATEGORY ---
    if (isset($_POST['delete_category'])) {
        $cat_id = $_POST['category_id'];
        $stmt = $conn->prepare("DELETE FROM help_categories WHERE id = ?");
        $stmt->bind_param("i", $cat_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Category deleted.";
            $_SESSION['msg_type'] = "success";
        }
        $stmt->close();
    }

    // --- ADD OR UPDATE ARTICLE ---
    if (isset($_POST['save_article'])) {
        $article_id = $_POST['article_id']; // Will be empty if new
        $category_id = $_POST['category_id'];
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $current_time = date('Y-m-d H:i:s');

        if (empty($article_id)) {
            // INSERT NEW
            $token = 'ticket_' . uniqid();
            $stmt = $conn->prepare("INSERT INTO help_articles (category_id, title, content, token, updated_at) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $category_id, $title, $content, $token, $current_time);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = "Article published! Link: ?token=" . htmlspecialchars($token);
                $_SESSION['msg_type'] = "success";
            } else {
                $_SESSION['message'] = "Error publishing article.";
                $_SESSION['msg_type'] = "error";
            }
        } else {
            // UPDATE EXISTING
            $stmt = $conn->prepare("UPDATE help_articles SET category_id = ?, title = ?, content = ?, updated_at = ? WHERE id = ?");
            $stmt->bind_param("isssi", $category_id, $title, $content, $current_time, $article_id);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = "Article updated successfully at $current_time (IST).";
                $_SESSION['msg_type'] = "success";
            } else {
                $_SESSION['message'] = "Error updating article.";
                $_SESSION['msg_type'] = "error";
            }
        }
        $stmt->close();
    }

    // --- DELETE ARTICLE ---
    if (isset($_POST['delete_article'])) {
        $article_id = $_POST['article_id'];
        $stmt = $conn->prepare("DELETE FROM help_articles WHERE id = ?");
        $stmt->bind_param("i", $article_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Article deleted successfully.";
            $_SESSION['msg_type'] = "success";
        }
        $stmt->close();
    }

    // Redirect back to the admin page
    header("Location: admin_help.php");
    exit();
}
?>
