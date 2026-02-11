<?php
/**
 * TruAi Server - Main Entry Point
 * 
 * HTML Server Version of Tru.ai
 * 
 * @package TruAi
 * @version 1.0.0
 * @copyright My Deme, LLC © 2026
 */

// Load configuration and dependencies
require_once __DIR__ . '/backend/config.php';
require_once __DIR__ . '/backend/database.php';
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/router.php';

// Enforce localhost access
Auth::enforceLocalhost();

// Check if this is a gateway request
$requestUri = $_SERVER['REQUEST_URI'];
if (strpos($requestUri, '/gateway') !== false || strpos($requestUri, '/gateway.php') !== false) {
    // Serve gateway page
    require_once __DIR__ . '/gateway.php';
    exit;
}

// Check if this is an API request
if (strpos($requestUri, '/api/') !== false) {
    // Handle API request
    $router = new Router();
    $router->dispatch();
    exit;
}

// Check if this is a static asset request (CSS, JS, images)
if (preg_match('/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|eot)$/i', $requestUri)) {
    // Let PHP built-in server handle static files
    return false;
}

// Serve frontend
$page = $_GET['page'] ?? 'login';
$auth = new Auth();

// Check authentication - redirect to login portal if not authenticated
if (!$auth->isAuthenticated() && $page !== 'login') {
    // Redirect to login portal
    header('Location: /TruAi/login-portal.html');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TruAi - Start New Project</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Helvetica', 'Arial', sans-serif;
            background: url('/TruAi/assets/images/TruAi-Background.jpg') center center / cover no-repeat fixed;
            background-color: #1a1d23; /* Fallback color */
            color: #ffffff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 0;
            margin: 0;
        }

        .header-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 40px 20px 20px;
        }

        .logo-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 16px;
        }

        .logo-container img {
            width: 64px;
            height: auto;
            display: block;
        }

        .project-title {
            font-size: 18px;
            font-weight: 500;
            color: #e8e9eb;
        }

        /* Full-width AI response area */
        .ai-response-area {
            width: 100%;
            flex: 1;
            padding: 20px 40px;
            overflow-y: auto;
            min-height: 300px;
        }

        .ai-response-content {
            max-width: 1400px;
            margin: 0 auto;
            color: #e8e9eb;
            font-size: 14px;
            line-height: 1.6;
        }

        .ai-response-content.empty {
            color: #8b8d98;
            text-align: center;
            padding: 60px 20px;
        }

        /* Three-box split (same as start.html) for CREATED task details */
        .response-three-box {
            display: flex;
            gap: 16px;
            width: 100%;
            max-width: 100%;
            margin: 0 auto;
            padding: 12px 0;
            text-align: left;
        }
        .response-three-box .response-box {
            flex: 1;
            min-width: 0;
            border: 2px solid rgba(255, 255, 255, 0.27);
            border-radius: 20px;
            padding: 16px;
            background: rgba(0, 0, 0, 0.15);
            transition: border-color 0.3s ease;
        }
        .response-three-box .response-box:hover {
            border-color: rgba(255, 255, 255, 0.5);
        }
        .response-three-box .response-box.selected,
        .response-three-box .response-box:first-child {
            border-color: rgba(0, 142, 214, 0.5);
        }
        .response-three-box .response-box-title {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: #008ed6;
            margin-bottom: 10px;
            letter-spacing: 0.02em;
        }
        .response-three-box .response-box-content {
            font-size: 13px;
            color: #e8e9eb;
            line-height: 1.5;
            overflow-y: auto;
            max-height: 200px;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .response-three-box .response-box-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }
        .response-three-box .response-box-actions button {
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.08);
            color: #e8e9eb;
        }
        .response-three-box .response-box-actions button.primary {
            background: #008ed6;
            border-color: #008ed6;
            color: #fff;
        }
        .response-three-box .response-box-actions button.danger {
            background: rgba(248, 113, 113, 0.15);
            border-color: rgba(248, 113, 113, 0.3);
            color: #f87171;
        }
        .response-three-box .response-box-actions button:hover {
            opacity: 0.9;
        }

        /* Bottom panels container - 100% width */
        .panels-container {
            display: flex;
            gap: 24px;
            width: 100%;
            padding: 20px 40px 40px;
            align-items: flex-start;
        }

        .panel {
            background: none;
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 12px;
            padding: 20px;
            position: relative;
            transition: all 0.25s ease;
        }

        .panel-label {
            font-size: 12px;
            color: #8b8d98;
            margin-bottom: 12px;
            font-weight: 500;
        }

        .panel-toggle-label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            user-select: none;
        }

        .panel-toggle-btn {
            border: none;
            background: transparent;
            color: #6b6d78;
            font-size: 11px;
            cursor: pointer;
            padding: 0 4px;
            border-radius: 4px;
            line-height: 1;
        }

        .panel-toggle-btn:hover {
            background: rgba(255, 255, 255, 0.06);
            color: #e8e9eb;
        }
        .panel-action-btn {
            border: none;
            background: transparent;
            color: #6b6d78;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .panel-action-btn:hover {
            background: rgba(255, 255, 255, 0.06);
            color: #e8e9eb;
        }

        /* Left panel - Mini content view */
        .content-panel {
            flex: 0 0 200px;
            min-height: 200px;
        }

        .file-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .file-item {
            padding: 8px 0;
            font-size: 13px;
            color: #e8e9eb;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .file-item:last-child {
            border-bottom: none;
        }

        .file-icon {
            width: 16px;
            height: 16px;
            color: #6b6d78;
        }

        .content-options {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            padding: 8px 0;
            margin-bottom: 8px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }
        .content-option {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            font-size: 11px;
            border-radius: 6px;
            cursor: pointer;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #8b8d98;
            transition: all 0.2s;
        }
        .content-option:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #e8e9eb;
        }
        .content-option.selected {
            background: rgba(0, 142, 214, 0.2);
            border-color: rgba(0, 142, 214, 0.4);
            color: #e8e9eb;
        }
        .content-option svg {
            width: 12px;
            height: 12px;
            flex-shrink: 0;
        }

        .content-repos {
            padding: 8px 0;
            margin-bottom: 8px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }
        .content-repos-title {
            font-size: 11px;
            color: #8b8d98;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .content-repo-item {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 0;
            font-size: 12px;
            color: #e8e9eb;
            cursor: pointer;
            border-radius: 4px;
        }
        .content-repo-item:hover {
            background: rgba(255, 255, 255, 0.06);
        }
        .content-repo-item svg {
            width: 14px;
            height: 14px;
            flex-shrink: 0;
            color: #8b8d98;
        }

        .content-review {
            padding: 8px 0;
            margin-bottom: 8px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }
        .content-review-title {
            font-size: 11px;
            color: #8b8d98;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .content-review-item {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 0;
            font-size: 12px;
            color: #e8e9eb;
            cursor: pointer;
            border-radius: 4px;
        }
        .content-review-item:hover {
            background: rgba(255, 255, 255, 0.06);
        }
        .content-review-item svg {
            width: 14px;
            height: 14px;
            flex-shrink: 0;
            color: #8b8d98;
        }

        /* Center panel - Text entry */
        .center-panel {
            flex: 1;
            min-width: 400px;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            position: relative;
        }

        .center-panel.expanded {
            align-self: flex-start;
            margin-top: auto;
        }

        .settings-panel {
            display: none;
            width: 100%;
            padding: 20px;
            background: rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            margin-bottom: 16px;
            border: 1px solid rgba(255, 255, 255, 0.06);
        }

        .center-panel.expanded .settings-panel {
            display: block;
        }

        .status-info {
            color: #8b8d98;
        }

        .status-success {
            color: #4ade80;
        }

        .status-error {
            color: #f87171;
        }

        /* Approval / task details modal (same visual language as portal) */
        .approval-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.65);
            align-items: center;
            justify-content: center;
            z-index: 2147483647;
            pointer-events: auto;
            visibility: hidden;
        }
        .approval-modal-overlay.open {
            display: flex !important;
            visibility: visible !important;
        }
        .approval-modal {
            background: rgba(26, 29, 35, 0.98);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 24px;
            max-width: 480px;
            width: 90%;
            color: #e8e9eb;
            font-size: 14px;
        }
        .approval-modal h3 {
            margin: 0 0 16px 0;
            font-size: 16px;
            color: #fff;
        }
        .approval-modal .detail-row {
            margin-bottom: 10px;
            color: #8b8d98;
        }
        .approval-modal .detail-row strong {
            color: #e8e9eb;
            margin-right: 8px;
        }
        .approval-modal .prompt-snippet,
        .approval-modal .approval-prompt-snippet {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 6px;
            padding: 10px;
            margin: 12px 0;
            font-size: 12px;
            color: #8b8d98;
            max-height: 200px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .approval-modal.approval-modal-three-box {
            max-width: 90%;
            width: 900px;
        }
        .approval-modal.approval-modal-three-box .response-three-box .response-box-content {
            max-height: 180px;
        }
        .approval-modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .approval-modal-actions button {
            padding: 10px 18px;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
            border: none;
            background: rgba(255, 255, 255, 0.08);
            color: #e8e9eb;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .approval-modal-actions button.primary {
            background: #008ed6;
            color: white;
            border-color: #008ed6;
        }
        .approval-modal-actions button.danger {
            background: rgba(248, 113, 113, 0.15);
            color: #f87171;
            border-color: rgba(248, 113, 113, 0.3);
        }
        .approval-modal-actions button:hover {
            opacity: 0.9;
        }

        #saveSettings:hover {
            background: #0077b3;
        }

        #resetSettings:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
        }

        #revealApiKey:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
        }


        .text-entry {
            width: 100%;
            min-height: 120px;
            background: none;
            border-radius: 0px;
            padding: 16px;
            font-size: 14px;
            font-family: inherit;
            text-align: center;
            resize: vertical;
            margin-bottom: 16px;
            border: none;
            color: #008ed6;
            outline: none;
        }

        .text-entry:focus {
            width: 100%;
            min-height: 120px;
            background: none;
            border-radius: 8px;
            padding: 16px;
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
            margin-bottom: 16px;
            border: none;
            color: #008ed6;
            outline: none;
        }

        .text-entry::placeholder {
            color: #8b8d98;
        }

        .icon-row {
            display: flex;
            gap: 16px;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
        }

        .icon-group {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .icon {
            width: 24px;
            height: 24px;
            color: #6b6d78;
            opacity: 0.7;
            cursor: pointer;
            transition: opacity 0.2s, color 0.2s;
        }

        .icon:hover {
            opacity: 1;
            color: #8b8d98;
        }

        .icon-button {
            background: none;
            border: none;
            padding: 0;
            cursor: pointer;
            display: flex;
            align-items: center;
        }

        .submit-button {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #008ed6;
            border: none;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 16px auto;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(0, 142, 214, 0.3);
        }

        .submit-button:hover {
            background: #0077b3;
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0, 142, 214, 0.4);
        }

        .submit-button:active {
            transform: scale(0.95);
        }

        .submit-button svg {
            width: 24px;
            height: 24px;
            stroke: white;
        }

        .settings-link {
            color: #6b6d78;
            text-decoration: none;
            font-size: 13px;
            transition: color 0.2s;
            cursor: pointer;
        }

        .settings-link:hover,
        .settings-link.active {
            color: #008ed6;
        }

        .divider {
            width: 1px;
            height: 24px;
            background: rgba(255, 255, 255, 0.1);
        }

        /* Trust State Dashboard (TSD) */
        .trust-state-dashboard {
            width: 100%;
            background: rgba(0, 0, 0, 0.25);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            font-size: 11px;
            position: relative;
        }
        .tsd-toggle {
            position: absolute;
            right: 8px;
            top: 4px;
            background: none;
            border: none;
            color: #8b8d98;
            cursor: pointer;
            padding: 2px 6px;
            font-size: 10px;
        }
        .tsd-toggle:hover { color: #e8e9eb; }
        .trust-state-dashboard.collapsed .tsd-chevron { transform: rotate(-90deg); }
        .trust-state-dashboard.collapsed .tsd-expanded { display: none !important; }
        .tsd-expanded[style*="display: none"] { display: none !important; }
        .tsd-header {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            flex-wrap: wrap;
            padding: 6px 32px 6px 12px;
            gap: 0;
        }
        .tsd-cell {
            display: flex;
            flex-direction: column;
            padding: 2px 12px;
            min-width: 80px;
        }
        .tsd-label {
            color: #6b6d78;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .tsd-value {
            font-weight: 600;
            font-size: 12px;
        }
        .tsd-cell.tsd-trust .tsd-value.verified { color: #4ade80; }
        .tsd-cell.tsd-trust .tsd-value.degraded { color: #fbbf24; }
        .tsd-cell.tsd-trust .tsd-value.unverified { color: #f87171; }
        .tsd-cell.tsd-trust .tsd-value.unknown { color: #8b8d98; }
        .tsd-cell.tsd-escalation .tsd-value.none { color: #4ade80; }
        .tsd-cell.tsd-escalation .tsd-value.watch { color: #fbbf24; }
        .tsd-cell.tsd-escalation .tsd-value.hold { color: #f87171; }
        .tsd-cell.tsd-escalation .tsd-value.admin { color: #f87171; }
        .tsd-divider {
            width: 1px;
            height: 28px;
            background: rgba(255, 255, 255, 0.06);
        }
        .tsd-cell.tsd-roi { cursor: pointer; }
        .tsd-cell.tsd-systems { cursor: pointer; }
        .tsd-expanded {
            padding: 12px 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
        }
        .tsd-panel-title {
            font-size: 10px;
            color: #6b6d78;
            text-transform: uppercase;
            margin-bottom: 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }
        .tsd-roi-rows, .tsd-systems-list, .tsd-events-list {
            font-size: 11px;
            color: #8b8d98;
        }
        .tsd-roi-rows div, .tsd-systems-list div, .tsd-events-list div {
            padding: 2px 0;
        }
        .tsd-roi-final { margin-top: 8px; font-weight: 600; color: #e8e9eb; }
        .tsd-systems-list .verified { color: #4ade80; }
        .tsd-systems-list .degraded { color: #fbbf24; }

        /* Operations panel - status created / Roma (same visual as other panels) */
        .operations-panel {
            flex: 0 0 200px;
            min-height: 200px;
        }

        .operations-content {
            font-size: 12px;
            color: #8b8d98;
            line-height: 1.5;
        }

        .operations-content .status-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .operations-content .status-list li {
            padding: 4px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        }

        .operations-content .status-list li:last-child {
            border-bottom: none;
        }

        .operations-content .roma-line {
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            font-size: 11px;
            color: #6b6d78;
        }

        /* Right panel - Mini code review */
        .code-review-panel {
            flex: 0 0 200px;
            min-height: 200px;
        }

        .code-review-content {
            font-size: 12px;
            color: #8b8d98;
            line-height: 1.5;
        }

        .code-snippet {
            background: rgba(20, 23, 29, 0.6);
            border-radius: 4px;
            padding: 8px;
            margin-top: 8px;
            font-family: 'Monaco', 'Menlo', 'Courier New', monospace;
            font-size: 11px;
            color: #6b6d78;
        }

        .code-review-snippet {
            background: rgba(20, 23, 29, 0.8);
            border-radius: 6px;
            padding: 10px;
            margin: 0;
            font-family: 'Monaco', 'Menlo', 'Courier New', monospace;
            font-size: 11px;
            color: #e8e9eb;
            white-space: pre-wrap;
            word-break: break-all;
            overflow: auto;
            max-height: 300px;
            border: 1px solid rgba(255, 255, 255, 0.06);
        }

        .code-review-placeholder {
            color: #6b6d78;
            font-size: 12px;
        }

        .code-review-toolbar {
            display: none;
            gap: 8px;
            flex-wrap: wrap;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            margin-bottom: 8px;
        }
        .code-review-panel.maximized .code-review-toolbar {
            display: flex;
        }
        .code-review-toolbar-btn {
            padding: 6px 12px;
            background: rgba(255, 255, 255, 0.08);
            color: #e8e9eb;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
        }
        .code-review-toolbar-btn:hover {
            background: rgba(255, 255, 255, 0.12);
        }
        .code-review-target-chip {
            font-size: 11px;
            color: #8b8d98;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .code-review-toolbar-clear {
            background: none;
            border: none;
            color: #6b6d78;
            cursor: pointer;
            padding: 0 2px;
            font-size: 14px;
            line-height: 1;
        }
        .code-review-toolbar-clear:hover {
            color: #e8e9eb;
        }

        /* Chat panel - Cursor-like saved, editable, reviewable */
        .chat-panel {
            flex: 0 0 280px;
            min-height: 200px;
            display: flex;
            flex-direction: column;
        }
        .chat-panel-body {
            display: flex;
            flex: 1;
            min-height: 0;
            font-size: 12px;
        }
        .chat-sidebar {
            flex: 0 0 100px;
            border-right: 1px solid rgba(255, 255, 255, 0.06);
            overflow-y: auto;
        }
        .chat-sidebar-title {
            padding: 8px 10px;
            color: #8b8d98;
            font-size: 11px;
            text-transform: uppercase;
        }
        .chat-conversation-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .chat-conversation-list li {
            padding: 8px 10px;
            cursor: pointer;
            color: #8b8d98;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .chat-conversation-list li:hover,
        .chat-conversation-list li.selected {
            background: rgba(255, 255, 255, 0.06);
            color: #e8e9eb;
        }
        .chat-thread {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .chat-message {
            max-width: 95%;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 12px;
            line-height: 1.4;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .chat-message.user {
            align-self: flex-end;
            background: rgba(0, 142, 214, 0.2);
            border: 1px solid rgba(0, 142, 214, 0.3);
        }
        .chat-message.assistant {
            align-self: flex-start;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .chat-message-actions {
            margin-top: 6px;
        }
        .chat-message-actions button {
            padding: 2px 8px;
            font-size: 11px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            color: #8b8d98;
            cursor: pointer;
        }
        .chat-message-actions button:hover {
            color: #e8e9eb;
        }
        .chat-input-row {
            display: flex;
            gap: 8px;
            padding: 8px 10px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
        }
        .chat-input {
            flex: 1;
            padding: 8px 10px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 6px;
            color: #e8e9eb;
            font-size: 12px;
            resize: none;
            font-family: inherit;
        }
        .chat-send-btn {
            padding: 8px 14px;
            background: #008ed6;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
        }
        .chat-send-btn:hover {
            background: #007ab8;
        }
        .chat-send-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .chat-panel.maximized .chat-panel-body {
            min-height: 400px;
        }

        /* Panel maximize (Content / Code Review) */
        body.panel-max-open .ai-response-area {
            display: none;
        }

        body.panel-max-open .panels-container {
            padding-top: 40px;
        }

        .panel.maximized {
            position: fixed;
            top: 80px;
            left: 40px;
            right: 40px;
            bottom: 40px;
            z-index: 1000;
            background: rgba(3, 6, 12, 0.96);
            border-radius: 16px;
            overflow: auto;
        }

        body.panel-max-open .panels-container .panel:not(.maximized) {
            opacity: 0;
            pointer-events: none;
        }

        @media (max-width: 1024px) {
            .panels-container {
                flex-direction: column;
                align-items: stretch;
            }

            .content-panel,
            .operations-panel,
            .center-panel,
            .code-review-panel {
                flex: 1;
                width: 100%;
                max-width: 100%;
            }

            .ai-response-area {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Header: Logo and Title -->
    <div class="header-section">
        <div class="logo-container">
            <img src="/TruAi/assets/images/TruAi-dashboard-logo.png" alt="TruAi Logo" />
        </div>
        <h1 class="project-title" id="projectTitle">Start New Project</h1>
    </div>

    <!-- Full-width AI Response Area -->
    <div class="ai-response-area">
        <div class="ai-response-content empty" id="aiResponse" role="status" aria-live="polite" aria-atomic="true">
            <!-- AI responses will appear here -->
        </div>
    </div>

    <!-- Trust State Dashboard (TSD) - Execution Header -->
    <div class="trust-state-dashboard" id="trustStateDashboard" role="region" aria-label="Trust state">
        <button type="button" class="tsd-toggle" id="tsdToggle" aria-expanded="false" aria-label="Expand trust state">
            <span class="tsd-chevron">▼</span>
        </button>
        <div class="tsd-header" id="tsdHeader">
            <div class="tsd-cell tsd-trust" id="tsdTrust" title="Global ROMA trust state">
                <span class="tsd-label">TRUST STATE</span>
                <span class="tsd-value" id="tsdTrustValue">—</span>
            </div>
            <div class="tsd-divider"></div>
            <div class="tsd-cell tsd-roi" id="tsdRoi" title="ROI score (click for breakdown)">
                <span class="tsd-label">ROI</span>
                <span class="tsd-value" id="tsdRoiValue">—</span>
            </div>
            <div class="tsd-divider"></div>
            <div class="tsd-cell tsd-systems" id="tsdSystems" title="ITC system trust (hover for details)">
                <span class="tsd-label">SYSTEMS</span>
                <span class="tsd-value" id="tsdSystemsValue">—</span>
            </div>
            <div class="tsd-divider"></div>
            <div class="tsd-cell tsd-escalation" id="tsdEscalation" title="Vital escalation status">
                <span class="tsd-label">ESCALATION</span>
                <span class="tsd-value" id="tsdEscalationValue">—</span>
            </div>
            <div class="tsd-divider"></div>
            <div class="tsd-cell tsd-encryption" id="tsdEncryption" title="Encryption algorithm">
                <span class="tsd-label">ENCRYPTION</span>
                <span class="tsd-value" id="tsdEncryptionValue">—</span>
            </div>
        </div>
        <div class="tsd-expanded" id="tsdExpanded" style="display: none;">
            <div class="tsd-roi-panel" id="tsdRoiPanel">
                <div class="tsd-panel-title">ROI BREAKDOWN</div>
                <div class="tsd-roi-rows" id="tsdRoiRows"></div>
                <div class="tsd-roi-final" id="tsdRoiFinal"></div>
            </div>
            <div class="tsd-systems-panel" id="tsdSystemsPanel">
                <div class="tsd-panel-title">SYSTEM TRUST</div>
                <div class="tsd-systems-list" id="tsdSystemsList"></div>
            </div>
            <div class="tsd-events-panel" id="tsdEventsPanel">
                <div class="tsd-panel-title">RECENT SECURITY EVENTS</div>
                <div class="tsd-events-list" id="tsdEventsList"></div>
            </div>
        </div>
    </div>

    <!-- Bottom Panels Container - Centered -->
    <div class="panels-container">
        <!-- Left Panel: Mini Content View (Files being worked on) -->
        <div class="panel content-panel">
            <div class="panel-label panel-toggle-label">
                <span>Content</span>
                <button type="button" id="truai-content-clear" class="panel-action-btn" title="Clear content section" aria-label="Clear content">Clear</button>
                <button type="button" class="panel-toggle-btn" data-panel-toggle="content" aria-label="Maximize Content panel (popup view)" title="Maximize (popup view)">⤢</button>
            </div>
            <div class="content-options" id="contentOptions">
                <button type="button" class="content-option selected" data-content="file-folder" title="Add File / Folder">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    <span>File / Folder</span>
                </button>
                <button type="button" class="content-option selected" data-content="photos" title="Add Photos">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    <span>Photos</span>
                </button>
                <button type="button" class="content-option" data-content="url" title="Add URL">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    <span>URL</span>
                </button>
                <button type="button" class="content-option" data-content="code" title="Code">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                    <span>Code</span>
                </button>
                <button type="button" class="content-option" data-content="docs" title="Docs">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    <span>Docs</span>
                </button>
                <button type="button" class="content-option" data-content="github" title="Add repo from GitHub">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.17 6.839 9.49.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.603-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.46-1.11-1.46-.909-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.831.092-.646.35-1.086.636-1.336-2.22-.253-4.555-1.11-4.555-4.943 0-1.11.38-2.036 1.03-2.75-.103-.253-.446-1.27.098-2.65 0 0 .84-.27 2.75 1.03A9.578 9.578 0 0112 6.836c.85.004 1.705.114 2.504.336 1.909-1.3 2.747-1.03 2.747-1.03.546 1.38.203 2.397.1 2.65.651.714 1.03 1.632 1.03 2.75 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.855 0 1.336-.012 2.415-.012 2.743 0 .267.18.578.688.48C19.138 20.167 22 16.418 22 12c0-5.523-4.477-10-10-10z"/></svg>
                    <span>GitHub</span>
                </button>
            </div>
            <div class="content-repos" id="contentRepos" style="display:none;"></div>
            <div class="content-review" id="contentReview" style="display:none;"></div>
            <ul class="file-list" id="fileList">
                <li class="file-item">
                    <svg class="file-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                    </svg>
                    <span>No files open</span>
                </li>
            </ul>
        </div>

        <!-- Operations Panel: Status created / Roma (displayed in portal alongside submission) -->
        <div class="panel operations-panel">
            <div class="panel-label panel-toggle-label">
                <span>Operations</span>
                <button type="button" class="panel-toggle-btn" data-panel-toggle="operations" aria-label="Maximize Operations panel">⤢</button>
            </div>
            <div class="operations-content" id="operationsContent">
                <div class="operations-loading">Loading…</div>
                <ul class="status-list" id="operationsStatusList" style="display: none;"></ul>
                <div class="roma-line" id="operationsRoma" style="display: none;"></div>
            </div>
        </div>

        <!-- Center Panel: Text Entry for AI -->
        <div class="panel center-panel" id="centerPanel" style="background: rgba(0, 0, 0, 0.07);">
            <!-- Settings Panel (hidden by default, shown when expanded) -->
            <div class="settings-panel">
                <form id="aiSettingsForm" aria-label="AI Settings Configuration">
                    <div style="margin-bottom: 20px;">
                        <h3 style="color: #e8e9eb; font-size: 14px; font-weight: 500; margin-bottom: 12px;">OpenAI Provider</h3>
                        <div style="margin-bottom: 12px;">
                            <label for="aiApiKeyOpenAI" style="display: block; color: #e8e9eb; font-size: 13px; margin-bottom: 4px;">
                                OpenAI API Key
                            </label>
                            <div style="display: flex; gap: 8px;">
                                <input 
                                    type="password" 
                                    id="aiApiKeyOpenAI" 
                                    name="openai_api_key"
                                    placeholder="sk-..."
                                    style="flex: 1; padding: 8px 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 4px; color: #e8e9eb; font-size: 13px; font-family: 'Monaco', 'Menlo', monospace;"
                                    aria-required="false"
                                />
                                <button 
                                    type="button" 
                                    id="revealApiKeyOpenAI"
                                    style="padding: 8px 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 4px; color: #8b8d98; font-size: 12px; cursor: pointer; white-space: nowrap;"
                                    aria-label="Toggle OpenAI API key visibility"
                                >
                                    Reveal
                                </button>
                            </div>
                        </div>
                        <div>
                            <label for="aiModelOpenAI" style="display: block; color: #e8e9eb; font-size: 13px; margin-bottom: 4px;">
                                OpenAI Model
                            </label>
                            <select 
                                id="aiModelOpenAI" 
                                name="openai_model"
                                style="width: 100%; padding: 8px 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 4px; color: #e8e9eb; font-size: 13px;"
                            >
                                <option value="gpt-4o">gpt-4o</option>
                                <option value="gpt-4">gpt-4</option>
                                <option value="gpt-3.5-turbo">gpt-3.5-turbo</option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 20px; padding-top: 16px; border-top: 1px solid rgba(255, 255, 255, 0.06);">
                        <h3 style="color: #e8e9eb; font-size: 14px; font-weight: 500; margin-bottom: 12px;">Sonnet Provider (Anthropic)</h3>
                        <div style="margin-bottom: 12px;">
                            <label for="aiApiKeySonnet" style="display: block; color: #e8e9eb; font-size: 13px; margin-bottom: 4px;">
                                Sonnet API Key
                            </label>
                            <div style="display: flex; gap: 8px;">
                                <input 
                                    type="password" 
                                    id="aiApiKeySonnet" 
                                    name="sonnet_api_key"
                                    placeholder="sk-ant-..."
                                    style="flex: 1; padding: 8px 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 4px; color: #e8e9eb; font-size: 13px; font-family: 'Monaco', 'Menlo', monospace;"
                                    aria-required="false"
                                />
                                <button 
                                    type="button" 
                                    id="revealApiKeySonnet"
                                    style="padding: 8px 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 4px; color: #8b8d98; font-size: 12px; cursor: pointer; white-space: nowrap;"
                                    aria-label="Toggle Sonnet API key visibility"
                                >
                                    Reveal
                                </button>
                            </div>
                        </div>
                        <div>
                            <label for="aiModelSonnet" style="display: block; color: #e8e9eb; font-size: 13px; margin-bottom: 4px;">
                                Sonnet Model
                            </label>
                            <select 
                                id="aiModelSonnet" 
                                name="sonnet_model"
                                style="width: 100%; padding: 8px 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 4px; color: #e8e9eb; font-size: 13px;"
                            >
                                <option value="sonnet-1">sonnet-1</option>
                                <option value="claude-3-opus">claude-3-opus</option>
                                <option value="claude-3-sonnet">claude-3-sonnet</option>
                                <option value="claude-3-haiku">claude-3-haiku</option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 16px;">
                        <label for="defaultProvider" style="display: block; color: #e8e9eb; font-size: 13px; margin-bottom: 4px;">
                            Default Provider
                        </label>
                        <select 
                            id="defaultProvider" 
                            name="default_provider"
                            style="width: 100%; padding: 8px 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 4px; color: #e8e9eb; font-size: 13px;"
                        >
                            <option value="openai">OpenAI</option>
                            <option value="sonnet">Sonnet (Anthropic)</option>
                        </select>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input 
                                type="checkbox" 
                                id="enableStreaming" 
                                name="enable_streaming"
                                style="width: 18px; height: 18px; cursor: pointer;"
                                aria-label="Enable streaming responses"
                            />
                            <span style="color: #e8e9eb; font-size: 13px;">Enable Streaming</span>
                        </label>
                    </div>
                    
                    <div style="margin-bottom: 20px; padding-top: 8px; border-top: 1px solid rgba(255, 255, 255, 0.06);">
                        <h3 style="color: #e8e9eb; font-size: 14px; font-weight: 500; margin-bottom: 10px;">Tasks &amp; approval</h3>
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input 
                                type="checkbox" 
                                id="truaiAutoExecute" 
                                name="truai_auto_execute"
                                style="width: 18px; height: 18px; cursor: pointer;"
                                aria-label="Auto-execute low and medium risk tasks"
                            />
                            <span style="color: #e8e9eb; font-size: 13px;">Auto-execute low/medium risk tasks</span>
                        </label>
                        <p style="color: #8b8d98; font-size: 12px; margin-top: 6px; margin-left: 0;">When off, all tasks require approval before running.</p>
                    </div>
                    
                    <div style="display: flex; gap: 12px; align-items: center;">
                        <button 
                            type="button" 
                            id="saveSettings"
                            style="padding: 10px 20px; background: #008ed6; border: none; border-radius: 6px; color: white; font-size: 13px; font-weight: 500; cursor: pointer; transition: background 0.2s;"
                            aria-label="Save settings"
                        >
                            Save
                        </button>
                        <button 
                            type="button" 
                            id="resetSettings"
                            style="padding: 10px 20px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 6px; color: #e8e9eb; font-size: 13px; cursor: pointer; transition: all 0.2s;"
                            aria-label="Reset settings to defaults"
                        >
                            Reset
                        </button>
                        <span id="settingsStatus" role="status" aria-live="polite" style="margin-left: 12px; font-size: 12px; color: #8b8d98;"></span>
                    </div>
                </form>
            </div>
            
            <textarea class="text-entry" id="aiTextEntry" placeholder="Standing by for instructions..." rows="4"></textarea>
            
            <!-- Round Submit Button -->
            <button class="submit-button" id="submitButton" type="button" title="Submit" aria-label="Submit prompt">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="22" y1="2" x2="11" y2="13"></line>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                </svg>
            </button>
            
            <div class="icon-row">
                <div class="icon-group">
                    <!-- Add Photos icon -->
                    <button class="icon-button" title="Add Photos">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <polyline points="21 15 16 10 5 21"></polyline>
                        </svg>
                    </button>
                    <!-- Browser / Add URL icon -->
                    <button class="icon-button" title="Add URL">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="2" y1="12" x2="22" y2="12"></line>
                            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                        </svg>
                    </button>
                    <!-- Add File / Folder icon -->
                    <button class="icon-button" title="Add File / Folder">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                        </svg>
                    </button>
                </div>

                <div class="divider"></div>

                <a href="#" class="settings-link" id="pullLastSubmissionLink" title="Pull last submission">Last submission</a>
                <div class="divider"></div>

                <!-- Settings link (center) -->
                <a href="#" class="settings-link" id="settingsLink" title="Settings">Settings</a>

                <div class="divider"></div>

                <div class="icon-group">
                    <!-- Terminal icon -->
                    <button class="icon-button" title="Terminal">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="4 17 10 11 4 5"></polyline>
                            <line x1="12" y1="19" x2="20" y2="19"></line>
                        </svg>
                    </button>
                    <!-- GitHub icon -->
                    <button class="icon-button" title="GitHub">
                        <svg class="icon" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.17 6.839 9.49.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.603-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.46[...]"></path>
                        </svg>
                    </button>
                    <!-- Code View icon -->
                    <button class="icon-button" title="Code View">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="9" y1="3" x2="9" y2="21"></line>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Right Panel: Mini Code Review Section -->
        <div class="panel code-review-panel">
            <div class="panel-label panel-toggle-label">
                <span>Code Review</span>
                <button type="button" class="panel-toggle-btn" data-panel-toggle="code-review" aria-label="Maximize Code Review panel">⤢</button>
            </div>
            <div class="code-review-toolbar" id="codeReviewToolbar">
                <span id="codeReviewTargetChip" class="code-review-target-chip" style="display:none;" title="Selected file for Apply to file / Open in Xcode"><span id="codeReviewTargetPath"></span> <button type="button" id="codeReviewClearTarget" class="code-review-toolbar-clear" aria-label="Clear target file">×</button></span>
                <button type="button" id="codeReviewApplyToFile" class="code-review-toolbar-btn" title="Write Code Review content to Xcode file (Roma protected)">Apply to file</button>
                <button type="button" id="codeReviewViewXcode" class="code-review-toolbar-btn" title="Open in Xcode">View in Xcode</button>
                <button type="button" id="codeReviewXcodeConnection" class="code-review-toolbar-btn" title="Open Xcode">Xcode connection</button>
                <button type="button" id="codeReviewGenerator" class="code-review-toolbar-btn" title="Send code to prompt for generation">Generator</button>
            </div>
            <div class="code-review-content" id="codeReview">
                <div class="code-review-placeholder" id="codeReviewPlaceholder">No code review available</div>
                <pre class="code-review-snippet" id="codeReviewSnippet" style="display:none;"></pre>
            </div>
        </div>

        <!-- Chat Panel: Cursor-like saved, editable, reviewable -->
        <div class="panel chat-panel">
            <div class="panel-label panel-toggle-label">
                <span>Chat</span>
                <button type="button" class="panel-toggle-btn" id="chatNewChatBtn" title="New chat" aria-label="New chat">+</button>
                <button type="button" class="panel-toggle-btn" data-panel-toggle="chat" aria-label="Maximize Chat panel">⤢</button>
            </div>
            <div class="chat-panel-body">
                <div class="chat-sidebar">
                    <div class="chat-sidebar-title">Past chats</div>
                    <ul class="chat-conversation-list" id="chatConversationList"></ul>
                </div>
                <div class="chat-thread">
                    <div class="chat-messages" id="chatMessages"></div>
                    <div class="chat-input-row">
                        <textarea class="chat-input" id="chatInput" placeholder="Message…" rows="2"></textarea>
                        <button type="button" class="chat-send-btn" id="chatSendBtn" aria-label="Send">Send</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Global configuration (preserved for API access if needed)
        window.TRUAI_CONFIG = {
            APP_NAME: '<?= APP_NAME ?>',
            APP_VERSION: '<?= APP_VERSION ?>',
            API_BASE: window.location.origin + '/TruAi/api/v1',
            CSRF_TOKEN: '<?= Auth::generateCsrfToken() ?>',
            IS_AUTHENTICATED: <?= $auth->isAuthenticated() ? 'true' : 'false' ?>,
            USERNAME: '<?= $auth->getUsername() ?? '' ?>'
        };

        // Cursor-like auto-generate project title + disappearing scroll for AI response + panel maximize
        document.addEventListener('DOMContentLoaded', () => {
            // Auto-generate project title (Cursor-like)
            const titleEl = document.getElementById('projectTitle');
            if (titleEl && !titleEl.dataset.userSet) {
                const params = new URLSearchParams(window.location.search);
                const fromUrl =
                    params.get('project') ||
                    params.get('repo') ||
                    params.get('workspace');

                let autoTitle;
                if (fromUrl) {
                    autoTitle = fromUrl;
                } else {
                    const user = window.TRUAI_CONFIG?.USERNAME || 'Guest';
                    const now = new Date();
                    const time = now.toLocaleTimeString([], {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    autoTitle = `New TruAi Project (${user} \u2022 ${time})`;
                }

                titleEl.textContent = autoTitle;
            }

            // Disappearing scroll: show scrollbar only while scrolling / on hover
            const aiResponseArea = document.querySelector('.ai-response-area');
            if (aiResponseArea) {
                let scrollTimeoutId = null;

                aiResponseArea.addEventListener('scroll', () => {
                    aiResponseArea.classList.add('scrolling');
                    if (scrollTimeoutId !== null) {
                        clearTimeout(scrollTimeoutId);
                    }
                    scrollTimeoutId = window.setTimeout(() => {
                        aiResponseArea.classList.remove('scrolling');
                        scrollTimeoutId = null;
                    }, 700);
                });
            }

            // Panel maximize / minimize for Content and Code Review
            function togglePanelMax(panel) {
                if (!panel) return;
                const isMax = panel.classList.contains('maximized');

                // Clear any existing maximized panel
                document.querySelectorAll('.panel.maximized').forEach(p => {
                    p.classList.remove('maximized');
                });

                const contentToggleBtn = document.querySelector('[data-panel-toggle="content"]');
                const codeReviewToggleBtn = document.querySelector('[data-panel-toggle="code-review"]');
                const operationsToggleBtn = document.querySelector('[data-panel-toggle="operations"]');
                const chatToggleBtn = document.querySelector('[data-panel-toggle="chat"]');

                // Helper to reset icons to default "expand"
                function resetToggleIcons() {
                    if (contentToggleBtn) contentToggleBtn.textContent = '⤢';
                    if (codeReviewToggleBtn) codeReviewBtnTextReset();
                    if (operationsToggleBtn) operationsToggleBtn.textContent = '⤢';
                    if (chatToggleBtn) chatToggleBtn.textContent = '⤢';
                }

                // Explicit reset for Code Review button text (kept separate in case of future styling)
                function codeReviewBtnTextReset() {
                    if (codeReviewToggleBtn) codeReviewToggleBtn.textContent = '⤢';
                }

                if (isMax) {
                    // Restore normal layout
                    document.body.classList.remove('panel-max-open');
                    panel.classList.remove('maximized');
                    resetToggleIcons();
                } else {
                    document.body.classList.add('panel-max-open');
                    panel.classList.add('maximized');

                    // Update icons: active panel shows close "×", others show expand
                    resetToggleIcons();
                    if (panel.classList.contains('content-panel') && contentToggleBtn) {
                        contentToggleBtn.textContent = '×';
                    }
                    if (panel.classList.contains('code-review-panel') && codeReviewToggleBtn) {
                        codeReviewToggleBtn.textContent = '×';
                    }
                    if (panel.classList.contains('operations-panel') && operationsToggleBtn) {
                        operationsToggleBtn.textContent = '×';
                    }
                }
            }

            const contentPanel = document.querySelector('.panel.content-panel');
            const codeReviewPanel = document.querySelector('.panel.code-review-panel');
            const operationsPanel = document.querySelector('.panel.operations-panel');

            const contentToggle = document.querySelector('[data-panel-toggle=\"content\"]');
            if (contentPanel && contentToggle) {
                contentToggle.addEventListener('click', () => togglePanelMax(contentPanel));
            }

            const codeReviewToggle = document.querySelector('[data-panel-toggle=\"code-review\"]');
            if (codeReviewPanel && codeReviewToggle) {
                codeReviewToggle.addEventListener('click', () => {
                    togglePanelMax(codeReviewPanel);
                    if (codeReviewPanel.classList.contains('maximized') && window.TruAiUpdateTargetChip) window.TruAiUpdateTargetChip();
                });
            }

            window.TruAiUpdateTargetChip = function() {
                var path = window.__truaiXcodeTargetPath;
                var chip = document.getElementById('codeReviewTargetChip');
                var pathEl = document.getElementById('codeReviewTargetPath');
                if (!chip || !pathEl) return;
                if (path) { chip.style.display = 'inline-flex'; pathEl.textContent = path; } else { chip.style.display = 'none'; pathEl.textContent = ''; }
            };
            var codeReviewClearTarget = document.getElementById('codeReviewClearTarget');
            if (codeReviewClearTarget) codeReviewClearTarget.addEventListener('click', function() {
                window.__truaiXcodeTargetPath = null;
                if (window.TruAiUpdateTargetChip) window.TruAiUpdateTargetChip();
                if (typeof showToast === 'function') showToast('Target file cleared');
            });

            const operationsToggle = document.querySelector('[data-panel-toggle=\"operations\"]');
            if (operationsPanel && operationsToggle) {
                operationsToggle.addEventListener('click', () => togglePanelMax(operationsPanel));
            }

            const chatToggle = document.querySelector('[data-panel-toggle=\"chat\"]');
            if (chatPanel && chatToggle) {
                chatToggle.addEventListener('click', () => togglePanelMax(chatPanel));
            }

            // Code Review toolbar (visible when maximized): View in Xcode, Xcode connection, Generator
            (function() {
                var viewXcodeBtn = document.getElementById('codeReviewViewXcode');
                var xcodeConnBtn = document.getElementById('codeReviewXcodeConnection');
                var generatorBtn = document.getElementById('codeReviewGenerator');
                var apiBase = (window.TRUAI_CONFIG && window.TRUAI_CONFIG.API_BASE) || (window.location.origin + '/TruAi/api/v1');
                var csrf = (window.TRUAI_CONFIG && window.TRUAI_CONFIG.CSRF_TOKEN) || '';
                function openXcode() {
                    var targetPath = window.__truaiXcodeTargetPath;
                    if (targetPath) {
                        fetch(apiBase + '/workspace/xcode/open', {
                            method: 'POST',
                            credentials: 'include',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                            body: JSON.stringify({ path: targetPath })
                        }).then(function(r) { return r.json(); }).then(function(d) {
                            if (d && d.opened) {
                                if (typeof showToast === 'function') showToast('Opening ' + targetPath + ' in Xcode');
                            } else {
                                if (typeof showToast === 'function') showToast(d && d.error ? d.error : 'Open failed');
                            }
                        }).catch(function() {
                            if (typeof showToast === 'function') showToast('Open failed');
                        });
                    } else {
                        fetch(apiBase + '/terminal/execute', {
                            method: 'POST',
                            credentials: 'include',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                            body: JSON.stringify({ command: 'open -a Xcode' })
                        }).then(function(r) { return r.json(); }).then(function(d) {
                            if (d && d.executed) {
                                if (typeof showToast === 'function') showToast('Xcode opening');
                            }
                        }).catch(function() {});
                    }
                }
                if (viewXcodeBtn) viewXcodeBtn.addEventListener('click', openXcode);
                if (xcodeConnBtn) xcodeConnBtn.addEventListener('click', openXcode);

                var applyToFileBtn = document.getElementById('codeReviewApplyToFile');
                if (applyToFileBtn) applyToFileBtn.addEventListener('click', function() {
                    var targetPath = window.__truaiXcodeTargetPath;
                    var snippet = document.getElementById('codeReviewSnippet');
                    var text = snippet && snippet.style.display !== 'none' ? (snippet.textContent || '').trim() : '';
                    if (!targetPath) {
                        if (typeof showToast === 'function') showToast('Select a workspace file first (Content panel or Add File / Folder)');
                        return;
                    }
                    if (!text) {
                        if (typeof showToast === 'function') showToast('No code in review to apply');
                        return;
                    }
                    applyToFileBtn.disabled = true;
                    fetch(apiBase + '/workspace/file/write', {
                        method: 'POST',
                        credentials: 'include',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                        body: JSON.stringify({ path: targetPath, content: text })
                    }).then(function(r) { return r.json(); }).then(function(d) {
                        if (d && d.success && d.roma) {
                            if (typeof showToast === 'function') showToast('Applied to ' + targetPath + ' (Roma protected)');
                        } else {
                            if (typeof showToast === 'function') showToast(d && d.error ? d.error : 'Apply failed');
                        }
                    }).catch(function() {
                        if (typeof showToast === 'function') showToast('Apply failed');
                    }).finally(function() { applyToFileBtn.disabled = false; });
                });

                if (generatorBtn) generatorBtn.addEventListener('click', function() {
                    var snippet = document.getElementById('codeReviewSnippet');
                    var text = snippet && snippet.style.display !== 'none' ? (snippet.textContent || '').trim() : '';
                    var promptEl = document.getElementById('aiTextEntry');
                    if (!promptEl) return;
                    if (text) {
                        promptEl.value = 'Improve or generate based on the following:\n\n' + text + (promptEl.value ? '\n\n' + promptEl.value : '');
                        promptEl.focus();
                        if (typeof showToast === 'function') showToast('Code sent to prompt');
                    } else {
                        if (typeof showToast === 'function') showToast('No code in review to send');
                    }
                });
            })();

            // Chat panel: Cursor-like saved, editable, reviewable
            (function() {
                var apiBase = (window.TRUAI_CONFIG && window.TRUAI_CONFIG.API_BASE) || (window.location.origin + '/TruAi/api/v1');
                var csrf = (window.TRUAI_CONFIG && window.TRUAI_CONFIG.CSRF_TOKEN) || '';
                var chatCurrentConvId = null;
                var chatConversations = [];
                var chatMessagesList = [];

                function chatReq(path, opts) {
                    opts = opts || {};
                    opts.credentials = 'include';
                    opts.headers = opts.headers || {};
                    opts.headers['Content-Type'] = 'application/json';
                    opts.headers['X-CSRF-Token'] = csrf;
                    return fetch(apiBase + path, opts).then(function(r) { return r.json(); });
                }

                function renderConversationList() {
                    var list = document.getElementById('chatConversationList');
                    if (!list) return;
                    list.innerHTML = '';
                    chatConversations.forEach(function(c) {
                        var li = document.createElement('li');
                        li.textContent = (c.title || 'Chat') + (c.message_count ? ' (' + c.message_count + ')' : '');
                        li.dataset.id = c.id;
                        if (chatCurrentConvId === String(c.id)) li.classList.add('selected');
                        li.addEventListener('click', function() {
                            chatCurrentConvId = String(c.id);
                            renderConversationList();
                            loadConversation(chatCurrentConvId);
                        });
                        list.appendChild(li);
                    });
                }

                function renderMessages() {
                    var container = document.getElementById('chatMessages');
                    if (!container) return;
                    if (chatMessagesList.length === 0) {
                        container.innerHTML = '<div style="color:#8b8d98;font-size:12px;padding:12px;">No messages. Start a new chat or select one from Past chats.</div>';
                        return;
                    }
                    container.innerHTML = '';
                    chatMessagesList.forEach(function(m) {
                        var wrap = document.createElement('div');
                        wrap.className = 'chat-message ' + (m.role || 'user');
                        var content = document.createElement('div');
                        content.className = 'chat-message-content';
                        content.textContent = m.content || '';
                        wrap.appendChild(content);
                        if (m.role === 'user' && m.id) {
                            var actions = document.createElement('div');
                            actions.className = 'chat-message-actions';
                            var editBtn = document.createElement('button');
                            editBtn.type = 'button';
                            editBtn.textContent = 'Edit';
                            editBtn.addEventListener('click', function() {
                                var textarea = document.createElement('textarea');
                                textarea.value = m.content || '';
                                textarea.rows = 3;
                                textarea.style.cssText = 'width:100%;padding:8px;background:rgba(0,0,0,0.2);border:1px solid rgba(255,255,255,0.1);border-radius:6px;color:#e8e9eb;font-size:12px;resize:vertical;';
                                content.replaceWith(textarea);
                                textarea.focus();
                                var saveBtn = document.createElement('button');
                                saveBtn.textContent = 'Save';
                                saveBtn.style.marginRight = '6px';
                                var cancelBtn = document.createElement('button');
                                cancelBtn.textContent = 'Cancel';
                                saveBtn.addEventListener('click', function() {
                                    var newContent = textarea.value.trim();
                                    if (!newContent) return;
                                    chatReq('/chat/message/' + m.id, { method: 'PATCH', body: JSON.stringify({ conversation_id: chatCurrentConvId, content: newContent }) })
                                        .then(function(res) {
                                            if (res.error) throw new Error(res.error);
                                            m.content = newContent;
                                            content.textContent = newContent;
                                            textarea.replaceWith(content);
                                            saveBtn.remove();
                                            cancelBtn.remove();
                                            actions.innerHTML = '';
                                            actions.appendChild(editBtn);
                                            if (typeof showToast === 'function') showToast('Message updated');
                                        })
                                        .catch(function(e) {
                                            if (typeof showToast === 'function') showToast('Update failed');
                                        });
                                });
                                cancelBtn.addEventListener('click', function() {
                                    textarea.replaceWith(content);
                                    saveBtn.remove();
                                    cancelBtn.remove();
                                    actions.appendChild(editBtn);
                                });
                                actions.innerHTML = '';
                                actions.appendChild(saveBtn);
                                actions.appendChild(cancelBtn);
                                wrap.appendChild(actions);
                            });
                            actions.appendChild(editBtn);
                            wrap.appendChild(actions);
                        }
                        container.appendChild(wrap);
                    });
                    container.scrollTop = container.scrollHeight;
                }

                function loadConversations() {
                    chatReq('/chat/conversations').then(function(data) {
                        chatConversations = (data && data.conversations) ? data.conversations : [];
                        renderConversationList();
                    }).catch(function() {});
                }

                function loadConversation(id) {
                    chatReq('/chat/conversation/' + id).then(function(conv) {
                        if (!conv || !conv.messages) return;
                        chatMessagesList = conv.messages.map(function(m) {
                            return { id: m.id, role: m.role, content: m.content, model_used: m.model_used };
                        });
                        renderMessages();
                    }).catch(function() {});
                }

                function sendMessage() {
                    var input = document.getElementById('chatInput');
                    if (!input) return;
                    var text = (input.value || '').trim();
                    if (!text) return;
                    input.value = '';
                    input.disabled = true;
                    var sendBtn = document.getElementById('chatSendBtn');
                    if (sendBtn) sendBtn.disabled = true;
                    chatReq('/chat/message', {
                        method: 'POST',
                        body: JSON.stringify({
                            conversation_id: chatCurrentConvId,
                            message: text,
                            model: 'auto'
                        })
                    }).then(function(res) {
                        if (res.error) throw new Error(res.error);
                        chatCurrentConvId = res.conversation_id;
                        loadConversations();
                        loadConversation(chatCurrentConvId);
                    }).catch(function(e) {
                        if (typeof showToast === 'function') showToast('Send failed');
                    }).finally(function() {
                        input.disabled = false;
                        if (sendBtn) sendBtn.disabled = false;
                    });
                }

                var newChatBtn = document.getElementById('chatNewChatBtn');
                if (newChatBtn) {
                    newChatBtn.addEventListener('click', function() {
                        chatCurrentConvId = null;
                        chatMessagesList = [];
                        renderConversationList();
                        renderMessages();
                        var input = document.getElementById('chatInput');
                        if (input) { input.value = ''; input.focus(); }
                    });
                }
                var sendBtn = document.getElementById('chatSendBtn');
                if (sendBtn) sendBtn.addEventListener('click', sendMessage);
                var chatInput = document.getElementById('chatInput');
                if (chatInput) {
                    chatInput.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
                    });
                }
                loadConversations();
            })();

            // Approval / task details modal: three-box split (same as start.html)
            var approvalModal = document.getElementById('approvalDetailsModal');
            var approvalBoxDetails = document.getElementById('approvalBoxDetails');
            var approvalPromptSnippet = document.getElementById('approvalPromptSnippet');
            var approvalApproveBtn = document.getElementById('approvalApproveBtn');
            var approvalRejectBtn = document.getElementById('approvalRejectBtn');
            var approvalViewOutputBtn = document.getElementById('approvalViewOutputBtn');
            var approvalApplyToXcodeBtn = document.getElementById('approvalApplyToXcodeBtn');
            var approvalCloseBtn = document.getElementById('approvalCloseBtn');

            window.openApprovalModal = function(taskResult, promptText) {
                if (!approvalModal || !taskResult) return;
                var taskId = taskResult.task_id || taskResult.id || '—';
                var status = taskResult.status || '—';
                var statusLabel = status === 'CREATED' || status === 'LOCKED' ? status + ' — awaiting approval' : status;
                var risk = taskResult.risk_level || taskResult.risk || '—';
                var fullPrompt = (promptText || taskResult.prompt || '—').toString();
                if (approvalBoxDetails) approvalBoxDetails.textContent = 'Task ID: ' + taskId + '\nStatus: ' + statusLabel + '\nRisk: ' + risk;
                if (approvalPromptSnippet) approvalPromptSnippet.textContent = fullPrompt;
                if (approvalModal) approvalModal.dataset.approvalTaskId = taskId;
                var titleEl = document.getElementById('approvalModalTitle');
                if (titleEl) titleEl.textContent = (status === 'CREATED' || status === 'LOCKED') ? 'Task details (CREATED) — Approve or Reject' : 'Task details';
                var output = taskResult.output || taskResult.results || taskResult.text;
                window.__truaiLastApprovalOutput = output || '';
                approvalViewOutputBtn.style.display = output ? 'inline-block' : 'none';
                if (approvalApplyToXcodeBtn) approvalApplyToXcodeBtn.style.display = output ? 'inline-block' : 'none';
                approvalViewOutputBtn.onclick = function() {
                    var aiResp = document.getElementById('aiResponse');
                    if (aiResp) { aiResp.textContent = output; aiResp.classList.remove('empty'); }
                    approvalModal.classList.remove('open');
                    approvalModal.style.display = '';
                    approvalModal.style.visibility = '';
                };
                approvalModal.classList.add('open');
                approvalModal.style.display = 'flex';
                approvalModal.style.visibility = 'visible';
            };

            if (approvalCloseBtn) approvalCloseBtn.addEventListener('click', function() {
                approvalModal.classList.remove('open');
                approvalModal.style.display = '';
                approvalModal.style.visibility = '';
            });
            if (approvalApplyToXcodeBtn) approvalApplyToXcodeBtn.addEventListener('click', function() {
                var targetPath = window.__truaiXcodeTargetPath;
                var content = window.__truaiLastApprovalOutput || '';
                if (!targetPath || !content) {
                    if (typeof showToast === 'function') showToast('Select a workspace file first and ensure task has output');
                    return;
                }
                approvalApplyToXcodeBtn.disabled = true;
                var apiBase = (window.TRUAI_CONFIG && window.TRUAI_CONFIG.API_BASE) || (window.location.origin + '/TruAi/api/v1');
                var csrf = (window.TRUAI_CONFIG && window.TRUAI_CONFIG.CSRF_TOKEN) || '';
                fetch(apiBase + '/workspace/file/write', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                    body: JSON.stringify({ path: targetPath, content: content })
                }).then(function(r) { return r.json(); }).then(function(d) {
                    if (d && d.success && d.roma) {
                        if (typeof showToast === 'function') showToast('Applied to ' + targetPath + ' (Roma protected)');
                        approvalModal.classList.remove('open');
                    } else {
                        if (typeof showToast === 'function') showToast(d && d.error ? d.error : 'Apply failed');
                    }
                }).catch(function() {
                    if (typeof showToast === 'function') showToast('Apply failed');
                }).finally(function() { approvalApplyToXcodeBtn.disabled = false; });
            });
            if (approvalApproveBtn) approvalApproveBtn.addEventListener('click', function() {
                var taskId = approvalModal && approvalModal.dataset.approvalTaskId ? approvalModal.dataset.approvalTaskId : '';
                if (!taskId || taskId === '—' || !window.TRUAI_CONFIG) return;
                fetch(window.TRUAI_CONFIG.API_BASE + '/task/approve', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.TRUAI_CONFIG.CSRF_TOKEN || '' },
                    body: JSON.stringify({ task_id: taskId, action: 'APPROVE' })
                }).then(function(r) { return r.json(); }).then(function() {
                    approvalModal.classList.remove('open');
                    var aiResp = document.getElementById('aiResponse');
                    if (aiResp) { aiResp.textContent = 'Task approved.'; aiResp.classList.remove('empty'); }
                }).catch(function(e) {
                    var el = document.getElementById('aiResponse');
                    if (el) el.textContent = 'Approve failed: ' + (e && e.message ? e.message : e);
                });
            });
            if (approvalRejectBtn) approvalRejectBtn.addEventListener('click', function() {
                var taskId = approvalModal && approvalModal.dataset.approvalTaskId ? approvalModal.dataset.approvalTaskId : '';
                if (!taskId || taskId === '—' || !window.TRUAI_CONFIG) return;
                fetch(window.TRUAI_CONFIG.API_BASE + '/task/approve', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.TRUAI_CONFIG.CSRF_TOKEN || '' },
                    body: JSON.stringify({ task_id: taskId, action: 'REJECT' })
                }).then(function(r) { return r.json(); }).then(function() {
                    approvalModal.classList.remove('open');
                    var aiResp = document.getElementById('aiResponse');
                    if (aiResp) { aiResp.textContent = 'Task rejected.'; aiResp.classList.remove('empty'); }
                }).catch(function(e) {
                    var el = document.getElementById('aiResponse');
                    if (el) el.textContent = 'Reject failed: ' + (e && e.message ? e.message : e);
                });
            });

            // Load operations: real task list (status created + others) and Roma
            const operationsContent = document.getElementById('operationsContent');
            const operationsStatusList = document.getElementById('operationsStatusList');
            const operationsRoma = document.getElementById('operationsRoma');
            const operationsLoading = operationsContent && operationsContent.querySelector('.operations-loading');
            function loadOperationsList() {
                if (!operationsContent || !operationsStatusList || !operationsRoma) return;
                fetch(window.TRUAI_CONFIG.API_BASE + '/task/list?limit=15', { credentials: 'include' })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (operationsLoading) operationsLoading.style.display = 'none';
                        if (data.tasks && data.tasks.length) {
                            operationsStatusList.style.display = 'block';
                            operationsStatusList.innerHTML = '';
                            data.tasks.forEach(function(t) {
                                var li = document.createElement('li');
                                var shortId = (t.id || '').toString().slice(-12);
                                var status = t.status || '—';
                                var label = document.createElement('span');
                                label.textContent = shortId + ' \u2022 ' + status;
                                label.style.color = status === 'CREATED' || status === 'LOCKED' ? '#008ed6' : '#8b8d98';
                                li.appendChild(label);
                                var viewBtn = document.createElement('button');
                                viewBtn.type = 'button';
                                viewBtn.textContent = 'View details';
                                viewBtn.className = 'operations-view-details';
                                viewBtn.style.cssText = 'margin-left:6px;padding:2px 6px;font-size:11px;background:rgba(0,142,214,0.2);color:#008ed6;border:1px solid rgba(0,142,214,0.3);border-radius:4px;cursor:pointer;';
                                (function(taskId) {
                                    viewBtn.onclick = function() {
                                        fetch(window.TRUAI_CONFIG.API_BASE + '/task/' + encodeURIComponent(taskId), { credentials: 'include' })
                                            .then(function(r) { return r.json(); })
                                            .then(function(full) {
                                                if (full.error) return;
                                                var taskResult = { task_id: full.id, id: full.id, status: full.status, risk_level: full.risk_level, prompt: full.prompt, output: full.output };
                                                if (window.openApprovalModal) window.openApprovalModal(taskResult, full.prompt);
                                            })
                                            .catch(function() {});
                                    };
                                })(t.id);
                                li.appendChild(viewBtn);
                                operationsStatusList.appendChild(li);
                            });
                        } else {
                            operationsStatusList.style.display = 'none';
                        }
                        return fetch(window.TRUAI_CONFIG.API_BASE + '/operations/status', { credentials: 'include' });
                    })
                    .then(function(r) { return r && r.json ? r.json() : {}; })
                    .then(function(data) {
                        if (operationsRoma) {
                            operationsRoma.style.display = 'block';
                            if (data.roma && data.roma.monitor === 'active') {
                                operationsRoma.textContent = 'Roma \u2022 Portal protected \u2022 Monitor active';
                            } else if (data.roma && data.roma.trust_state === 'BLOCKED') {
                                operationsRoma.textContent = 'Roma \u2022 Blocked (suspicion threshold)';
                            } else {
                                operationsRoma.textContent = 'Roma \u2022 Unverified';
                            }
                        }
                    })
                    .catch(function() {
                        if (operationsLoading) operationsLoading.textContent = 'Operations unavailable';
                    });
            }
            window.TruAiRefreshOperationsList = loadOperationsList;
            if (operationsContent && operationsStatusList && operationsRoma) loadOperationsList();
        });

        // Trust State Dashboard (TSD)
        (function() {
            var apiBase = (window.TRUAI_CONFIG && window.TRUAI_CONFIG.API_BASE) || (window.location.origin + '/TruAi/api/v1');
            var tsd = document.getElementById('trustStateDashboard');
            var tsdToggle = document.getElementById('tsdToggle');
            var tsdExpanded = document.getElementById('tsdExpanded');
            var tsdTrustValue = document.getElementById('tsdTrustValue');
            var tsdRoiValue = document.getElementById('tsdRoiValue');
            var tsdSystemsValue = document.getElementById('tsdSystemsValue');
            var tsdEscalationValue = document.getElementById('tsdEscalationValue');
            var tsdEncryptionValue = document.getElementById('tsdEncryptionValue');
            var tsdRoiPanel = document.getElementById('tsdRoiPanel');
            var tsdSystemsPanel = document.getElementById('tsdSystemsPanel');
            var tsdEventsPanel = document.getElementById('tsdEventsPanel');
            var tsdRoiRows = document.getElementById('tsdRoiRows');
            var tsdRoiFinal = document.getElementById('tsdRoiFinal');
            var tsdSystemsList = document.getElementById('tsdSystemsList');
            var tsdEventsList = document.getElementById('tsdEventsList');

            function loadTrustSnapshot() {
                if (!apiBase) return;
                fetch(apiBase + '/trust/snapshot', { credentials: 'include' })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (!tsdTrustValue) return;
                        var g = (data.global || 'UNKNOWN').toUpperCase();
                        tsdTrustValue.textContent = g;
                        tsdTrustValue.className = 'tsd-value ' + (g === 'VERIFIED' ? 'verified' : (g === 'DEGRADED' ? 'degraded' : (g === 'UNVERIFIED' ? 'unverified' : 'unknown')));
                        if (tsdRoiValue) {
                            var roi = data.roi || {};
                            tsdRoiValue.textContent = (roi.securityCost || '—').toUpperCase();
                        }
                        if (tsdSystemsValue) tsdSystemsValue.textContent = data.systems_summary || '—';
                        var esc = (data.escalation || 'NONE').toUpperCase();
                        if (tsdEscalationValue) {
                            tsdEscalationValue.textContent = esc;
                            tsdEscalationValue.className = 'tsd-value ' + (esc === 'NONE' ? 'none' : (esc === 'WATCH' ? 'watch' : (esc === 'HOLD' || esc === 'ADMIN' ? 'hold' : 'none')));
                        }
                        if (tsdEncryptionValue) tsdEncryptionValue.textContent = (data.encryption && data.encryption.algorithm) || 'AES-256-GCM';
                        if (tsdRoiRows && data.roi) {
                            tsdRoiRows.innerHTML = '<div>Model Cost: ' + (data.roi.modelCost || '—') + '</div><div>Security Cost: ' + (data.roi.securityCost || '—') + '</div><div>Execution Scope: ' + (data.roi.executionScope || '—') + '</div><div>Trust Multiplier: ' + (data.roi.trustMultiplier != null ? data.roi.trustMultiplier + 'x' : '—') + '</div>';
                        }
                        if (tsdRoiFinal && data.roi) tsdRoiFinal.textContent = 'Final ROI Score: ' + Math.round((data.roi.score || 0) * 100) + '%';
                        if (tsdSystemsList && data.systems) {
                            var sys = data.systems;
                            tsdSystemsList.innerHTML = '';
                            ['truai', 'phantom', 'gemini'].forEach(function(k) {
                                if (sys[k]) {
                                    var d = document.createElement('div');
                                    d.className = sys[k].toLowerCase();
                                    d.textContent = (k === 'truai' ? 'TruAi' : (k === 'phantom' ? 'Phantom.ai' : 'Gemini.ai')) + ' \u2022 ' + sys[k];
                                    tsdSystemsList.appendChild(d);
                                }
                            });
                        }
                        window.__lastTrustSnapshot = data;
                    })
                    .catch(function() {
                        if (tsdTrustValue) tsdTrustValue.textContent = 'UNKNOWN';
                        if (tsdTrustValue) tsdTrustValue.className = 'tsd-value unknown';
                    });
            }

            function loadTrustEvents() {
                if (!apiBase || !tsdEventsList) return;
                fetch(apiBase + '/trust/events?limit=5', { credentials: 'include' })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        tsdEventsList.innerHTML = '';
                        (data.events || []).forEach(function(ev) {
                            var d = document.createElement('div');
                            var det = ev.details && typeof ev.details === 'object' ? (ev.details.system_id || ev.details.reason || '') : '';
                            d.textContent = (ev.event || '') + (det ? ' — ' + det : '');
                            tsdEventsList.appendChild(d);
                        });
                    })
                    .catch(function() {});
            }

            if (tsdToggle && tsd && tsdExpanded) {
                tsdToggle.addEventListener('click', function() {
                    var wasCollapsed = tsd.classList.contains('collapsed');
                    tsd.classList.toggle('collapsed');
                    tsdExpanded.style.display = tsd.classList.contains('collapsed') ? 'none' : 'grid';
                    tsdToggle.setAttribute('aria-expanded', tsd.classList.contains('collapsed') ? 'false' : 'true');
                    tsdToggle.setAttribute('aria-label', tsd.classList.contains('collapsed') ? 'Expand trust state' : 'Collapse trust state');
                    if (!wasCollapsed && tsd.classList.contains('collapsed')) return;
                    if (wasCollapsed) loadTrustEvents();
                });
            }
            if (document.getElementById('tsdRoi')) {
                document.getElementById('tsdRoi').addEventListener('click', function() {
                    if (tsdExpanded) tsdExpanded.style.display = tsdExpanded.style.display === 'none' ? 'grid' : 'none';
                    if (tsd) tsd.classList.remove('collapsed');
                });
            }
            if (document.getElementById('tsdSystems')) {
                document.getElementById('tsdSystems').addEventListener('click', function() {
                    if (tsdExpanded) tsdExpanded.style.display = tsdExpanded.style.display === 'none' ? 'grid' : 'none';
                    if (tsd) tsd.classList.remove('collapsed');
                    loadTrustEvents();
                });
            }
            window.TruAiRefreshTrustSnapshot = loadTrustSnapshot;
            loadTrustSnapshot();
            setInterval(loadTrustSnapshot, 30000);
            if (window.TruAiRefreshOperationsList) {
                var orig = window.TruAiRefreshOperationsList;
                window.TruAiRefreshOperationsList = function() { orig(); loadTrustSnapshot(); };
            }
        })();

        // Content options: select/unselect and connect to actions
        const contentOptions = document.getElementById('contentOptions');
        if (contentOptions) {
            contentOptions.addEventListener('click', function(e) {
                const opt = e.target.closest('.content-option');
                if (!opt) return;
                const type = opt.dataset.content;
                const wasSelected = opt.classList.contains('selected');
                opt.classList.toggle('selected');
                const nowSelected = opt.classList.contains('selected');
                // When selecting, trigger the corresponding action
                if (nowSelected && !wasSelected) {
                    if (type === 'file-folder' && typeof window.TruAiLoadFileTree === 'function') {
                        window.TruAiLoadFileTree();
                    }
                    if (type === 'file-folder' && typeof window.TruAiShowAddFileFolder === 'function') {
                        window.TruAiShowAddFileFolder();
                    } else if (type === 'photos' && typeof window.TruAiShowAddPhotos === 'function') {
                        window.TruAiShowAddPhotos();
                    } else if (type === 'url' && typeof window.TruAiShowAddUrl === 'function') {
                        window.TruAiShowAddUrl();
                    } else if (type === 'code' && typeof window.TruAiShowCodeView === 'function') {
                        window.TruAiShowCodeView();
                    } else if (type === 'github' && typeof window.TruAiShowGitHub === 'function') {
                        window.TruAiShowGitHub();
                    }
                }
            });
        }

        // Populate content section with recently connected GitHub repos
        window.TruAiPopulateContentFromRepos = function() {
            const container = document.getElementById('contentRepos');
            const getRepos = window.TruAiGetConnectedRepos || (function() {
                try {
                    var raw = localStorage.getItem('truai_connected_repos');
                    return raw ? JSON.parse(raw) : [];
                } catch (e) { return []; }
            });
            var repos = getRepos();
            if (!container) return;
            if (!repos || repos.length === 0) {
                container.style.display = 'none';
                container.innerHTML = '';
                return;
            }
            container.style.display = 'block';
            var html = '<div class="content-repos-title">Recently connected</div>';
            repos.slice(0, 8).forEach(function(r) {
                var slug = r.slug || r.url || '';
                var url = (r.url && r.url.indexOf('http') === 0) ? r.url : 'https://github.com/' + slug;
                html += '<a class="content-repo-item" href="' + url + '" target="_blank" rel="noopener" title="Open ' + slug + '">';
                html += '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.17 6.839 9.49.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.603-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.46-1.11-1.46-.909-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.831.092-.646.35-1.086.636-1.336-2.22-.253-4.555-1.11-4.555-4.943 0-1.11.38-2.036 1.03-2.75-.103-.253-.446-1.27.098-2.65 0 0 .84-.27 2.75 1.03A9.578 9.578 0 0112 6.836c.85.004 1.705.114 2.504.336 1.909-1.3 2.747-1.03 2.747-1.03.546 1.38.203 2.397.1 2.65.651.714 1.03 1.632 1.03 2.75 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.855 0 1.336-.012 2.415-.012 2.743 0 .267.18.578.688.48C19.138 20.167 22 16.418 22 12c0-5.523-4.477-10-10-10z"/></svg>';
                html += '<span>' + (slug || url) + '</span></a>';
            });
            container.innerHTML = html;
        };

        // Populate on load if repos and code review items exist
        document.addEventListener('DOMContentLoaded', function() {
            if (window.TruAiPopulateContentFromRepos) window.TruAiPopulateContentFromRepos();
            if (window.TruAiPopulateContentReview) window.TruAiPopulateContentReview();
        });

        // Code Review: populate with code being generated, modified, or written
        window.updateCodeReview = function(content, label) {
            var placeholder = document.getElementById('codeReviewPlaceholder');
            var snippet = document.getElementById('codeReviewSnippet');
            if (!placeholder || !snippet) return;
            if (!content || (typeof content === 'string' && !content.trim())) {
                placeholder.style.display = 'block';
                placeholder.textContent = label || 'No code review available';
                snippet.style.display = 'none';
                return;
            }
            var text = typeof content === 'string' ? content : (content.code || content.text || JSON.stringify(content));
            var display = (label ? label + '\n\n' : '') + text;
            if (display.length > 8000) display = display.substring(0, 8000) + '\n\n… (truncated)';
            placeholder.style.display = 'none';
            snippet.style.display = 'block';
            snippet.textContent = display;
        };

        // Icon buttons are wired by cursor-parity.js (Add Photos, Add URL, Add File/Folder, etc.)

        // Settings toggle handler
        const settingsLink = document.getElementById('settingsLink');
        const centerPanel = document.getElementById('centerPanel');
        
        settingsLink.addEventListener('click', function(e) {
            e.preventDefault();
            centerPanel.classList.toggle('expanded');
            settingsLink.classList.toggle('active');
        });

        // Pull last submission: restore prompt and response
        const pullLastLink = document.getElementById('pullLastSubmissionLink');
        if (pullLastLink) {
            pullLastLink.addEventListener('click', function(e) {
                e.preventDefault();
                var last = window.__lastSubmission;
                var respEl = document.getElementById('aiResponse');
                var entryEl = document.getElementById('aiTextEntry');
                if (!last) {
                    if (respEl) { respEl.textContent = 'No previous submission.'; respEl.classList.remove('empty'); }
                    return;
                }
                if (entryEl) entryEl.value = last.prompt || '';
                if (respEl) {
                    respEl.textContent = last.output != null ? last.output : '';
                    respEl.classList.remove('empty');
                }
            });
        }

        // Submit button handler - wait for DOM and AI client to be ready
        document.addEventListener('DOMContentLoaded', function() {
            const submitButton = document.getElementById('submitButton');
            const aiTextEntry = document.getElementById('aiTextEntry');
            const aiResponse = document.getElementById('aiResponse');
            
            if (!submitButton || !aiTextEntry || !aiResponse) {
                console.warn('Submit button or required elements not found');
                return;
            }

            // Function to handle submission (same logic as keyboard handler)
            async function handleSubmission() {
                const prompt = aiTextEntry.value.trim();
                if (!prompt) {
                    console.log('No text to submit');
                    return;
                }

                // Wait for AI client to be initialized (with timeout)
                const maxWait = 5000; // 5 seconds
                const startTime = Date.now();
                
                while (!window.truAiClient && (Date.now() - startTime) < maxWait) {
                    await new Promise(resolve => setTimeout(resolve, 100));
                }
                
                if (!window.truAiClient) {
                    console.error('AI client not initialized after timeout');
                    aiResponse.textContent = 'Error: AI client not ready. Please refresh the page.';
                    aiResponse.classList.remove('empty');
                    return;
                }

                try {
                    // Disable UI
                    aiTextEntry.disabled = true;
                    aiTextEntry.classList.add('disabled');
                    submitButton.disabled = true;
                    submitButton.style.opacity = '0.5';
                    submitButton.style.cursor = 'not-allowed';

                    // Show loading state
                    aiResponse.classList.remove('empty');
                    aiResponse.textContent = 'Submitting prompt...';
                    if (window.updateCodeReview) window.updateCodeReview('', 'Generating…');

                    // Ensure CSRF token is up to date before submission
                    if (window.truAiClient && window.truAiClient.updateCsrfToken) {
                      window.truAiClient.updateCsrfToken();
                      console.log('CSRF token updated before submission');
                    }
                    
                    // Submit prompt with context (files/photos/URLs from Content panel) when available
                    const contextFiles = (window.TruAiGetContextFiles && window.TruAiGetContextFiles()) || [];
                    const context = contextFiles.length ? { context_files: contextFiles } : null;
                    const result = await window.truAiClient.submitPrompt(prompt, context, (progress) => {
                        if (!progress || !progress.status) return;
                        if (progress.status === 'CREATED' || progress.status === 'LOCKED') {
                            aiResponse.textContent = 'Task created. Waiting for approval…';
                            if (window.updateCodeReview) window.updateCodeReview('', 'Task created. Waiting for approval…');
                        } else if (progress.status !== 'pending') {
                            var msg = progress.status === 'EXECUTED' || progress.status === 'completed' ? 'Executing…' : (progress.message || progress.status + '…');
                            aiResponse.textContent = msg;
                            if (window.updateCodeReview) window.updateCodeReview('', msg);
                        }
                    });

                    // Display result: avoid raw "Status: CREATED" / JSON; show summary + auto-open approval popup when task needs approval
                    const output = result.output ?? result.results ?? result.text;
                    const needsApproval = result.task_id && (result.status === 'CREATED' || result.status === 'LOCKED' || !output);
                    var displayOutput = '';
                    if (needsApproval) {
                        window.__lastTaskResult = result;
                        window.__lastTaskPrompt = prompt;
                        var taskId = result.task_id || result.id || '';
                        var statusLabel = (result.status === 'CREATED' || result.status === 'LOCKED') ? 'CREATED — awaiting approval' : (result.status || '—');
                        var riskVal = result.risk_level || result.risk || '—';
                        aiResponse.innerHTML = '<div class="response-three-box" role="region" aria-label="Task created - details">' +
                            '<div class="response-box"><div class="response-box-title">Details</div><div class="response-box-content" id="createdBoxDetails">—</div></div>' +
                            '<div class="response-box"><div class="response-box-title">Prompt</div><div class="response-box-content" id="createdBoxPrompt">—</div></div>' +
                            '<div class="response-box"><div class="response-box-title">Actions</div><div class="response-box-actions" id="createdBoxActions"></div></div>' +
                            '</div>';
                        document.getElementById('createdBoxDetails').textContent = 'Task ID: ' + (taskId || '—') + '\nStatus: ' + statusLabel + '\nRisk: ' + riskVal;
                        document.getElementById('createdBoxPrompt').textContent = prompt || '—';
                        var actionsEl = document.getElementById('createdBoxActions');
                        var approveBtn = document.createElement('button'); approveBtn.type = 'button'; approveBtn.className = 'primary'; approveBtn.textContent = 'Approve';
                        approveBtn.onclick = function() {
                            var tid = result.task_id || result.id;
                            if (!tid || !window.TRUAI_CONFIG) return;
                            fetch(window.TRUAI_CONFIG.API_BASE + '/task/approve', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.TRUAI_CONFIG.CSRF_TOKEN || '' }, body: JSON.stringify({ task_id: tid, action: 'APPROVE' }) })
                                .then(function(r) { return r.json(); }).then(function() { aiResponse.textContent = 'Task approved.'; aiResponse.classList.remove('empty'); }).catch(function(e) { aiResponse.textContent = 'Approve failed: ' + (e && e.message ? e.message : e); });
                        };
                        var rejectBtn = document.createElement('button'); rejectBtn.type = 'button'; rejectBtn.className = 'danger'; rejectBtn.textContent = 'Reject';
                        rejectBtn.onclick = function() {
                            var tid = result.task_id || result.id;
                            if (!tid || !window.TRUAI_CONFIG) return;
                            fetch(window.TRUAI_CONFIG.API_BASE + '/task/approve', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.TRUAI_CONFIG.CSRF_TOKEN || '' }, body: JSON.stringify({ task_id: tid, action: 'REJECT' }) })
                                .then(function(r) { return r.json(); }).then(function() { aiResponse.textContent = 'Task rejected.'; aiResponse.classList.remove('empty'); }).catch(function(e) { aiResponse.textContent = 'Reject failed: ' + (e && e.message ? e.message : e); });
                        };
                        var popupBtn = document.createElement('button'); popupBtn.type = 'button'; popupBtn.textContent = 'Show in popup';
                        popupBtn.onclick = function() { if (window.openApprovalModal) window.openApprovalModal(result, prompt); };
                        actionsEl.appendChild(approveBtn); actionsEl.appendChild(rejectBtn); actionsEl.appendChild(popupBtn);
                        if (window.openApprovalModal && result) window.openApprovalModal(result, prompt);
                        if (window.TruAiRefreshOperationsList) setTimeout(window.TruAiRefreshOperationsList, 300);
                        displayOutput = 'Task created. [Three-box details]';
                        if (window.updateCodeReview) window.updateCodeReview(prompt, 'Task created — prompt');
                    } else {
                        displayOutput = output != null && output !== '' ? output : (result.status ? 'Status: ' + result.status : JSON.stringify(result));
                        aiResponse.textContent = displayOutput;
                        if (window.updateCodeReview && displayOutput) {
                            window.updateCodeReview(displayOutput, 'Generated');
                        }
                    }
                    aiResponse.classList.remove('empty');
                    window.__lastSubmission = { prompt: prompt, output: displayOutput, result: result };

                } catch (err) {
                    console.error('Submission error:', err);
                    let errorMessage = err.message;
                    
                    // Check if it's an authentication error
                    if (errorMessage.includes('Unauthorized') || errorMessage.includes('Session expired')) {
                        errorMessage = 'Session expired. Please refresh the page and log in again.';
                        // Optionally redirect after a delay
                        setTimeout(() => {
                            if (confirm('Your session has expired. Would you like to go to the login page?')) {
                                window.location.href = '/TruAi/login-portal.html';
                            }
                        }, 2000);
                    }
                    
                    aiResponse.textContent = `Error: ${errorMessage}`;
                    aiResponse.classList.remove('empty');
                } finally {
                    // Re-enable UI
                    aiTextEntry.disabled = false;
                    aiTextEntry.classList.remove('disabled');
                    submitButton.disabled = false;
                    submitButton.style.opacity = '1';
                    submitButton.style.cursor = 'pointer';
                    
                    // Clear textarea and refocus
                    aiTextEntry.value = '';
                    aiTextEntry.focus();
                }
            }

            // Wire submit button click
            submitButton.addEventListener('click', handleSubmission);

            // Code Review: update when user types code-like content in prompt
            var codeReviewDebounce;
            if (aiTextEntry) {
                aiTextEntry.addEventListener('input', function() {
                    clearTimeout(codeReviewDebounce);
                    var val = (aiTextEntry.value || '').trim();
                    if (val.length < 10) return;
                    codeReviewDebounce = setTimeout(function() {
                        if (window.updateCodeReview && (val.indexOf('```') >= 0 || /(function|const|var|def |class |import |export |<\w+)/.test(val))) {
                            window.updateCodeReview(val, 'Writing…');
                        }
                    }, 400);
                });
            }

            // Also allow Cmd/Ctrl+Enter to trigger submit button
            aiTextEntry.addEventListener('keydown', function(e) {
                const metaPressed = e.metaKey || e.ctrlKey;
                if (e.key === 'Enter' && metaPressed) {
                    e.preventDefault();
                    handleSubmission();
                }
            });

            console.log('✅ Submit button wired to AI client');
        });
    </script>

    <!-- Wire the AI client and enable accessible live region updates -->
    <script src="/TruAi/assets/js/crypto.js"></script>
    <script src="/TruAi/assets/js/ai-client.js"></script>
    <script src="/TruAi/assets/js/settings-client.js"></script>
    <script src="/TruAi/assets/js/cursor-parity.js"></script>
    <script src="/TruAi/assets/js/file-operations.js"></script>
    <script>
      // Initialize AI client immediately (before DOMContentLoaded if possible)
      // This ensures it's available as soon as possible
      (function() {
        try {
          if (window.TRUAI_CONFIG && window.TruAiAIClient) {
            window.truAiClient = new TruAiAIClient(window.TRUAI_CONFIG);
            console.log('✅ AI client created with CSRF:', window.TRUAI_CONFIG.CSRF_TOKEN ? 'present' : 'missing');
          }
        } catch (err) {
          console.error('❌ Failed to create AI client:', err);
        }
      })();

      document.addEventListener('DOMContentLoaded', () => {
        try {
          // Attach AI client to UI elements
          if (window.truAiClient) {
            window.truAiClient.attachToUI({ textareaId: 'aiTextEntry', responseId: 'aiResponse' });
            console.log('✅ AI client attached to UI');
          } else if (window.TRUAI_CONFIG && window.TruAiAIClient) {
            // Fallback: create if not already created
            window.truAiClient = new TruAiAIClient(window.TRUAI_CONFIG);
            window.truAiClient.attachToUI({ textareaId: 'aiTextEntry', responseId: 'aiResponse' });
            console.log('✅ AI client created and attached to UI');
          }
          
          // Initialize settings client with config
          if (window.TruAiSettingsClient && window.TRUAI_CONFIG) {
            window.TruAiSettingsClient.init(window.TRUAI_CONFIG);
            console.log('✅ Settings client initialized with config');
          }
          
          // Log authentication status for debugging
          console.log('Auth status:', {
            isAuthenticated: window.TRUAI_CONFIG?.IS_AUTHENTICATED,
            hasCsrfToken: !!window.TRUAI_CONFIG?.CSRF_TOKEN,
            csrfTokenLength: window.TRUAI_CONFIG?.CSRF_TOKEN?.length || 0
          });
        } catch (err) {
          console.error('❌ Failed to initialize clients:', err);
        }
      });
    </script>

    <!-- Approval modal: three-box split (same as start.html) -->
    <div class="approval-modal-overlay" id="approvalDetailsModal" role="dialog" aria-labelledby="approvalModalTitle" aria-modal="true">
        <div class="approval-modal approval-modal-three-box">
            <h3 id="approvalModalTitle">Task details (CREATED)</h3>
            <div class="response-three-box">
                <div class="response-box">
                    <div class="response-box-title">Details</div>
                    <div class="response-box-content" id="approvalBoxDetails">—</div>
                </div>
                <div class="response-box">
                    <div class="response-box-title">Prompt</div>
                    <div class="response-box-content approval-prompt-snippet" id="approvalPromptSnippet">—</div>
                </div>
                <div class="response-box">
                    <div class="response-box-title">Actions</div>
                    <div class="response-box-actions">
                        <button type="button" class="primary" id="approvalApproveBtn">Approve</button>
                        <button type="button" class="danger" id="approvalRejectBtn">Reject</button>
                        <button type="button" id="approvalViewOutputBtn" style="display: none;">View output</button>
                        <button type="button" id="approvalApplyToXcodeBtn" style="display: none;" title="Write output to Xcode file (Roma protected)">Apply to Xcode file</button>
                        <button type="button" id="approvalCloseBtn">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
