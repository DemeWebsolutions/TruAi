/**
 * TruAi Dashboard - IDE Layout
 * 
 * Cursor-like IDE interface matching Electron version
 * Activity Bar → Sidebar → Editor → Terminal → Status Bar
 * 
 * @package TruAi
 * @version 1.0.0
 */

// Check if legal notice has been acknowledged
function shouldRenderDashboard() {
    if (document.getElementById('legal-notice-overlay')) {
        return false;
    }
    return sessionStorage.getItem('truai_legal_acknowledged') === 'true' || 
           !document.getElementById('legal-notice-overlay');
}

// State
let activePanel = 'explorer'; // explorer, search, git, debug, extensions, ai, settings
let showSidebar = true;
let showTerminal = false;
let sidebarWidth = 280;
let openTabs = [];
let activeTab = null;
let fileTree = [];
let terminalHistory = [];
let terminalInput = '';
let settings = null;
let showSettingsModal = false;
let currentConversationId = null;
let chatMessages = [];
let activeSettingsTab = 'general'; // general, models, features, beta

// Inline AI Rewrite state
let showDiffPreview = false;
let diffPreviewData = null; // { original, rewritten, selectionStart, selectionEnd }

/**
 * Generate forensic ID for tracking AI operations
 * Format: TRUAI_<timestamp>_<hash>
 */
function generateForensicId() {
    const timestamp = Date.now();
    const randomPart = Math.random().toString(36).substring(2, 15);
    const hash = btoa(timestamp + randomPart).substring(0, 16).replace(/[^a-zA-Z0-9]/g, '');
    return `TRUAI_${timestamp}_${hash}`;
}

/**
 * Get selected text from code editor
 * Returns null if no selection or editor not available
 */
function getEditorSelection() {
    const editor = document.getElementById('codeEditor');
    if (!editor) return null;
    
    const start = editor.selectionStart;
    const end = editor.selectionEnd;
    
    if (start === end) return null; // No selection
    
    return {
        text: editor.value.substring(start, end),
        start: start,
        end: end
    };
}

/**
 * Show inline rewrite prompt modal
 */
function showInlineRewritePrompt() {
    // Check if modal already exists
    if (document.getElementById('inline-rewrite-modal')) {
        return; // Prevent duplicate modals
    }
    
    const selection = getEditorSelection();
    
    if (!selection) {
        showNotification('Please select some code to rewrite', 'info');
        return;
    }
    
    // Create prompt modal
    const modal = document.createElement('div');
    modal.id = 'inline-rewrite-modal';
    modal.className = 'inline-rewrite-modal';
    modal.innerHTML = `
        <div class="inline-rewrite-content">
            <div class="inline-rewrite-header">
                <h3>AI Rewrite - Selected Text</h3>
                <button class="inline-rewrite-close" onclick="closeInlineRewriteModal()">×</button>
            </div>
            <div class="inline-rewrite-body">
                <div class="selected-code-preview">
                    <div class="preview-label">Selected Code (${selection.text.length} chars):</div>
                    <pre class="code-preview">${escapeHtml(selection.text.substring(0, 200))}${selection.text.length > 200 ? '...' : ''}</pre>
                </div>
                <textarea 
                    id="rewriteInstruction" 
                    class="rewrite-instruction" 
                    placeholder="Enter instructions for how to rewrite this code (e.g., 'Add error handling', 'Optimize for performance', 'Add comments')"
                    rows="3"
                ></textarea>
                <div class="inline-rewrite-actions">
                    <button class="btn-secondary" onclick="closeInlineRewriteModal()">Cancel</button>
                    <button class="btn-primary" onclick="executeInlineRewrite()">Generate Rewrite</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Focus the instruction input
    setTimeout(() => {
        document.getElementById('rewriteInstruction')?.focus();
    }, 100);
}

/**
 * Close inline rewrite modal
 */
function closeInlineRewriteModal() {
    const modal = document.getElementById('inline-rewrite-modal');
    if (modal) {
        modal.remove();
    }
}

/**
 * Execute inline AI rewrite
 */
async function executeInlineRewrite() {
    const instruction = document.getElementById('rewriteInstruction')?.value.trim();
    const selection = getEditorSelection();
    
    if (!instruction) {
        showNotification('Please enter rewrite instructions', 'warning');
        return;
    }
    
    if (!selection) {
        showNotification('Selection lost. Please try again.', 'error');
        closeInlineRewriteModal();
        return;
    }
    
    // Show loading state
    const executeBtn = document.querySelector('#inline-rewrite-modal .btn-primary');
    if (executeBtn) {
        executeBtn.disabled = true;
        executeBtn.textContent = 'Generating...';
    }
    
    try {
        const api = new TruAiAPI();
        const forensicId = generateForensicId();
        
        // Sanitize instruction to prevent prompt injection
        const sanitizedInstruction = instruction.replace(/["'`\\]/g, '').substring(0, 500);
        
        // Prepare the message with context
        const message = `Please rewrite the following code according to these instructions: ${sanitizedInstruction}\n\nCode to rewrite:\n\`\`\`\n${selection.text}\n\`\`\`\n\nProvide ONLY the rewritten code without explanations or markdown formatting.`;
        
        // Get model from settings
        const model = (settings && settings.ai && settings.ai.model) ? settings.ai.model : 'gpt-4';
        
        // Send request with metadata
        const response = await api.sendMessage(message, null, model, {
            intent: 'inline_rewrite',
            scope: 'selection',
            risk: 'SAFE',
            forensic_id: forensicId,
            selection_length: selection.text.length
        });
        
        if (response.message && response.message.content) {
            // Clean up the response - remove markdown code blocks if present
            let rewrittenCode = response.message.content.trim();
            // Remove opening code fence (```language or ```)
            rewrittenCode = rewrittenCode.replace(/^```[\w]*\n?/, '');
            // Remove closing code fence
            rewrittenCode = rewrittenCode.replace(/\n?```$/, '');
            rewrittenCode = rewrittenCode.trim();
            
            // Store diff preview data
            diffPreviewData = {
                original: selection.text,
                rewritten: rewrittenCode,
                selectionStart: selection.start,
                selectionEnd: selection.end,
                forensicId: forensicId,
                instruction: instruction
            };
            
            // Close prompt modal
            closeInlineRewriteModal();
            
            // Show diff preview
            showDiffPreviewModal();
        } else {
            throw new Error('Invalid response from AI');
        }
        
    } catch (error) {
        console.error('Inline rewrite error:', error);
        showNotification(`Error: ${error.message}`, 'error');
        
        // Reset button
        if (executeBtn) {
            executeBtn.disabled = false;
            executeBtn.textContent = 'Generate Rewrite';
        }
    }
}

/**
 * Show diff preview modal
 */
function showDiffPreviewModal() {
    if (!diffPreviewData) return;
    
    const modal = document.createElement('div');
    modal.id = 'diff-preview-modal';
    modal.className = 'diff-preview-modal';
    modal.innerHTML = `
        <div class="diff-preview-content">
            <div class="diff-preview-header">
                <h3>Code Rewrite Preview</h3>
                <button class="diff-preview-close" onclick="closeDiffPreview()">×</button>
            </div>
            <div class="diff-preview-body">
                <div class="diff-instruction">
                    <strong>Instruction:</strong> ${escapeHtml(diffPreviewData.instruction)}
                </div>
                <div class="diff-forensic">
                    <small>Forensic ID: ${escapeHtml(diffPreviewData.forensicId)}</small>
                </div>
                <div class="diff-view">
                    <div class="diff-column">
                        <div class="diff-column-header">Original</div>
                        <pre class="diff-code original-code">${escapeHtml(diffPreviewData.original)}</pre>
                    </div>
                    <div class="diff-column">
                        <div class="diff-column-header">Rewritten</div>
                        <pre class="diff-code rewritten-code">${escapeHtml(diffPreviewData.rewritten)}</pre>
                    </div>
                </div>
                <div class="diff-preview-actions">
                    <button class="btn-secondary" onclick="rejectDiff()">Reject</button>
                    <button class="btn-primary" onclick="applyDiff()">Apply Changes</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

/**
 * Close diff preview modal
 */
function closeDiffPreview() {
    const modal = document.getElementById('diff-preview-modal');
    if (modal) {
        modal.remove();
    }
    diffPreviewData = null;
}

/**
 * Reject the diff - just close the preview
 */
function rejectDiff() {
    closeDiffPreview();
    showNotification('Changes rejected', 'info');
}

/**
 * Apply the diff - replace selected text with rewritten code
 */
function applyDiff() {
    if (!diffPreviewData) return;
    
    const editor = document.getElementById('codeEditor');
    if (!editor || !activeTab) {
        showNotification('Editor not available', 'error');
        closeDiffPreview();
        return;
    }
    
    // Replace the selected range with rewritten code
    const before = editor.value.substring(0, diffPreviewData.selectionStart);
    const after = editor.value.substring(diffPreviewData.selectionEnd);
    const newContent = before + diffPreviewData.rewritten + after;
    
    // Update editor and tab
    editor.value = newContent;
    activeTab.content = newContent;
    activeTab.modified = true;
    
    // Mark tab as modified
    const tabIndex = openTabs.indexOf(activeTab);
    const tabElement = document.querySelector(`.editor-tab[data-tab="${tabIndex}"]`);
    if (tabElement && !tabElement.querySelector('.tab-modified')) {
        const modifiedIndicator = document.createElement('span');
        modifiedIndicator.className = 'tab-modified';
        modifiedIndicator.textContent = '•';
        tabElement.appendChild(modifiedIndicator);
    }
    
    closeDiffPreview();
    showNotification('Changes applied successfully', 'success');
}

/**
 * Show notification toast
 */
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 20px;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 4px;
        color: var(--text-primary);
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        z-index: 10000;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transition = 'opacity 0.3s';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Make functions globally accessible for onclick handlers
window.closeInlineRewriteModal = closeInlineRewriteModal;
window.executeInlineRewrite = executeInlineRewrite;
window.closeDiffPreview = closeDiffPreview;
window.rejectDiff = rejectDiff;
window.applyDiff = applyDiff;

/**
 * Show context menu for editor
 */
function showEditorContextMenu(x, y) {
    // Remove existing context menu if any
    const existingMenu = document.getElementById('editor-context-menu');
    if (existingMenu) {
        existingMenu.remove();
    }
    
    const menu = document.createElement('div');
    menu.id = 'editor-context-menu';
    menu.className = 'editor-context-menu';
    menu.style.cssText = `
        position: fixed;
        left: ${x}px;
        top: ${y}px;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 4px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        z-index: 10000;
        min-width: 200px;
    `;
    
    menu.innerHTML = `
        <div class="context-menu-item" onclick="showInlineRewritePrompt(); closeEditorContextMenu();">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1.41 16.09V20h-2.67v-1.93c-1.71-.36-3.16-1.46-3.27-3.4h1.96c.1 1.05.82 1.87 2.65 1.87 1.96 0 2.4-.98 2.4-1.59 0-.83-.44-1.61-2.67-2.14-2.48-.6-4.18-1.62-4.18-3.67 0-1.72 1.39-2.84 3.11-3.21V4h2.67v1.95c1.86.45 2.79 1.86 2.85 3.39H14.3c-.05-1.11-.64-1.87-2.22-1.87-1.5 0-2.4.68-2.4 1.64 0 .84.65 1.39 2.67 1.91s4.18 1.39 4.18 3.91c-.01 1.83-1.38 2.83-3.12 3.16z"/>
            </svg>
            AI Rewrite Selection
        </div>
    `;
    
    document.body.appendChild(menu);
    
    // Close menu when clicking outside
    setTimeout(() => {
        document.addEventListener('click', closeEditorContextMenu);
    }, 0);
}

/**
 * Close editor context menu
 */
function closeEditorContextMenu() {
    const menu = document.getElementById('editor-context-menu');
    if (menu) {
        menu.remove();
    }
    document.removeEventListener('click', closeEditorContextMenu);
}

// Make context menu functions globally accessible
window.closeEditorContextMenu = closeEditorContextMenu;

function renderDashboard() {
    const app = document.getElementById('app');
    app.innerHTML = `
        <div class="ide-container">
            <!-- Activity Bar (leftmost) -->
            <div class="activity-bar">
                <div class="activity-icons">
                    <button class="activity-icon ${activePanel === 'explorer' ? 'active' : ''}" 
                            data-panel="explorer" title="Explorer">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M10 4H4c-1.11 0-2 .89-2 2v12c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2h-8l-2-2z"/>
                        </svg>
                    </button>
                    <button class="activity-icon ${activePanel === 'search' ? 'active' : ''}" 
                            data-panel="search" title="Search">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                        </svg>
                    </button>
                    <button class="activity-icon ${activePanel === 'git' ? 'active' : ''}" 
                            data-panel="git" title="Source Control">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                    </button>
                    <button class="activity-icon ${activePanel === 'debug' ? 'active' : ''}" 
                            data-panel="debug" title="Run and Debug">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                    </button>
                    <button class="activity-icon ${activePanel === 'extensions' ? 'active' : ''}" 
                            data-panel="extensions" title="Extensions">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M20.5 11H19V7c0-1.1-.9-2-2-2h-4V3.5C13 2.12 11.88 1 10.5 1S8 2.12 8 3.5V5H4c-1.1 0-1.99.9-1.99 2v3.8H3.5c1.49 0 2.7 1.21 2.7 2.7s-1.21 2.7-2.7 2.7H2V20c0 1.1.9 2 2 2h3.8v-1.5H7c-1.49 0-2.7-1.21-2.7-2.7 0-1.49 1.21-2.7 2.7-2.7h1.5V11H20.5z"/>
                        </svg>
                    </button>
                    <button class="activity-icon ${activePanel === 'ai' ? 'active' : ''}" 
                            data-panel="ai" title="Tru.ai Assistant">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1.41 16.09V20h-2.67v-1.93c-1.71-.36-3.16-1.46-3.27-3.4h1.96c.1 1.05.82 1.87 2.65 1.87 1.96 0 2.4-.98 2.4-1.59 0-.83-.44-1.61-2.67-2.14-2.48-.6-4.18-1.62-4.18-3.67 0-1.72 1.39-2.84 3.11-3.21V4h2.67v1.95c1.86.45 2.79 1.86 2.85 3.39H14.3c-.05-1.11-.64-1.87-2.22-1.87-1.5 0-2.4.68-2.4 1.64 0 .84.65 1.39 2.67 1.91s4.18 1.39 4.18 3.91c-.01 1.83-1.38 2.83-3.12 3.16z"/>
                        </svg>
                    </button>
                </div>
                <div class="activity-icons-bottom">
                    <button class="activity-icon" id="settingsBtn" title="Settings">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94L14.4 2.81c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.12.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.07.62-.07.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.12-.57 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Sidebar -->
            ${showSidebar ? `
            <div class="sidebar" style="width: ${sidebarWidth}px;">
                <div class="sidebar-header">
                    <span id="sidebarTitle">${getSidebarTitle()}</span>
                    <button class="sidebar-action" id="sidebarToggle" title="Toggle Sidebar">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                        </svg>
                    </button>
                </div>
                <div class="sidebar-content">
                    ${renderSidebarContent()}
                </div>
            </div>
            ` : ''}

            <!-- Main Area (Editor + Terminal) -->
            <div class="main-area">
                <!-- Editor Area -->
                <div class="editor-area">
                    <div class="editor-tabs" id="editorTabs">
                        ${openTabs.length === 0 ? '<div class="no-tabs">No files open</div>' : ''}
                        ${openTabs.map((tab, index) => `
                            <div class="editor-tab ${tab === activeTab ? 'active' : ''}" data-tab="${index}">
                                <span class="tab-name">${tab.name}</span>
                                ${tab.modified ? '<span class="tab-modified">•</span>' : ''}
                                <button class="tab-close" data-close="${index}">×</button>
                            </div>
                        `).join('')}
                    </div>
                    <div class="editor-content" id="editorContent">
                        ${renderEditorContent()}
                    </div>
                </div>

                <!-- Terminal Panel -->
                ${showTerminal ? `
                <div class="terminal-panel">
                    <div class="terminal-header">
                        <div class="terminal-tabs">
                            <div class="terminal-tab active">Terminal</div>
                        </div>
                        <button class="terminal-close" id="terminalToggle" title="Hide Terminal">×</button>
                    </div>
                    <div class="terminal-content" id="terminalContent">
                        ${terminalHistory.map(line => `<div class="terminal-line">${escapeHtml(line)}</div>`).join('')}
                        <div class="terminal-input-line">
                            <span class="terminal-prompt">$</span>
                            <input type="text" class="terminal-input" id="terminalInput" placeholder="Type command..." autocomplete="off">
                        </div>
                    </div>
                </div>
                ` : ''}
            </div>

            <!-- Status Bar -->
            <div class="status-bar">
                <div class="status-left">
                    <div class="status-item">
                        <span id="statusFile">${activeTab ? activeTab.name : 'No file'}</span>
                        ${activeTab && activeTab.modified ? '<span class="status-modified">•</span>' : ''}
                    </div>
                    <div class="status-item">
                        <span id="statusBranch">main</span>
                    </div>
                </div>
                <div class="status-right">
                    <div class="status-item">
                        <span id="statusPosition">Ln 1, Col 1</span>
                    </div>
                    <div class="status-item">
                        <span>UTF-8</span>
                    </div>
                    <div class="status-item">
                        <span>LF</span>
                    </div>
                    <div class="status-item">
                        <span>👤 ${window.TRUAI_CONFIG.USERNAME}</span>
                    </div>
                    <button class="status-item" id="logoutBtn" style="cursor: pointer; background: transparent; border: none; color: var(--text-secondary);">Logout</button>
                </div>
            </div>
        </div>
    `;

    setupDashboardListeners();
    loadFileTree();
}

function getSidebarTitle() {
    const titles = {
        explorer: 'EXPLORER',
        search: 'SEARCH',
        git: 'SOURCE CONTROL',
        debug: 'RUN AND DEBUG',
        extensions: 'EXTENSIONS',
        ai: 'TRU.AI ASSISTANT',
        settings: 'SETTINGS'
    };
    return titles[activePanel] || 'EXPLORER';
}

function renderSidebarContent() {
    switch(activePanel) {
        case 'explorer':
            return renderFileExplorer();
        case 'search':
            return renderSearchPanel();
        case 'git':
            return renderGitPanel();
        case 'debug':
            return renderDebugPanel();
        case 'extensions':
            return renderExtensionsPanel();
        case 'ai':
            return renderAIPanel();
        case 'settings':
            return renderSettingsPanel();
        default:
            return renderFileExplorer();
    }
}

// Load chat history when AI panel is opened
async function loadChatHistory() {
    if (!currentConversationId) return;
    
    try {
        const api = new TruAiAPI();
        const conversation = await api.getConversation(currentConversationId);
        
        if (conversation && conversation.messages) {
            chatMessages = conversation.messages.map(msg => ({
                role: msg.role,
                content: msg.content,
                model: msg.model_used,
                timestamp: new Date(msg.created_at)
            }));
            renderChatContainer();
        }
    } catch (error) {
        console.error('Error loading chat history:', error);
    }
}

function renderFileExplorer() {
    return `
        <div class="panel-content">
            <div class="section-title">OPEN EDITORS</div>
            <div id="openEditors" style="font-size: 12px; color: var(--text-tertiary); padding: 8px;">
                ${openTabs.length === 0 ? 'No open files' : openTabs.map(tab => tab.name).join(', ')}
            </div>
            <div class="section-title">FILES</div>
            <div class="file-tree" id="fileTree">
                ${fileTree.length === 0 ? '<div style="padding: 20px; text-align: center; color: var(--text-tertiary);">Loading files...</div>' : ''}
            </div>
        </div>
    `;
}

function renderSearchPanel() {
    return `
        <div class="panel-content">
            <input type="text" class="search-input" id="searchInput" placeholder="Search">
            <div class="search-results" id="searchResults">
                <div class="search-empty">No search results</div>
            </div>
        </div>
    `;
}

function renderGitPanel() {
    return `
        <div class="panel-content">
            <div class="git-section">
                <div class="section-title">SOURCE CONTROL</div>
                <div class="git-status" id="gitStatus">
                    <div class="git-item">No changes</div>
                </div>
            </div>
        </div>
    `;
}

function renderDebugPanel() {
    return `
        <div class="panel-content">
            <div style="padding: 20px; text-align: center; color: var(--text-tertiary);">
                <p>Run and Debug</p>
                <p style="font-size: 12px; margin-top: 10px;">Configure launch.json to enable debugging</p>
            </div>
        </div>
    `;
}

function renderExtensionsPanel() {
    return `
        <div class="panel-content">
            <div style="padding: 20px; text-align: center; color: var(--text-tertiary);">
                <p>Extensions</p>
                <p style="font-size: 12px; margin-top: 10px;">Extension marketplace coming soon</p>
            </div>
        </div>
    `;
}

function renderAIPanel() {
    // Get model from settings or default
    const defaultModel = (settings && settings.ai && settings.ai.model) ? settings.ai.model : 'gpt-4';
    
    return `
        <div class="ai-panel">
            <div class="ai-header">
                <div class="ai-header-tabs">
                    <button class="ai-tab active" data-tab="new-chat">New Chat</button>
                    <button class="ai-tab" data-tab="sent-to-chat">Sent to Chat</button>
                </div>
                <div class="ai-header-actions">
                    <button class="ai-header-btn" id="newChatBtn" title="New Chat">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                        </svg>
                    </button>
                    <button class="ai-header-btn" id="refreshChatBtn" title="Refresh">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="ai-chat" id="aiChat">
                ${renderChatMessages()}
            </div>
            <div class="ai-input-area">
                <button class="btn-add-context" id="addContextBtn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                    </svg>
                    @ Add Context
                </button>
                <div class="ai-input-wrapper" id="aiInputWrapper">
                    <div class="ai-input-container">
                        <div class="context-tags" id="contextTags"></div>
                        <textarea class="ai-input" id="aiInput" placeholder="Plan, search, build anything" rows="3"></textarea>
                        <div class="context-menu" id="contextMenu" style="display: none;">
                            <div class="context-menu-search">
                                <input type="text" placeholder="Q Search models..." id="contextSearch">
                            </div>
                            <div class="context-menu-items">
                                <div class="context-menu-item" data-context="files">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>
                                    </svg>
                                    <span>Files & Folders</span>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" class="context-arrow">
                                        <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
                                    </svg>
                                </div>
                                <div class="context-menu-item" data-context="code">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0L19.2 12l-4.6-4.6L16 6l6 6-6 6-1.4-1.4z"/>
                                    </svg>
                                    <span>Code</span>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" class="context-arrow">
                                        <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
                                    </svg>
                                </div>
                                <div class="context-menu-item" data-context="docs">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>
                                    </svg>
                                    <span>Docs</span>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" class="context-arrow">
                                        <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
                                    </svg>
                                </div>
                                <div class="context-menu-item" data-context="git">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                    </svg>
                                    <span>Git</span>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" class="context-arrow">
                                        <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
                                    </svg>
                                </div>
                                <div class="context-menu-item" data-context="past-chats">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
                                    </svg>
                                    <span>Past Chats</span>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" class="context-arrow">
                                        <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
                                    </svg>
                                </div>
                                <div class="context-menu-item" data-context="web">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM18.92 8h-2.95c-.32-1.25-.78-2.45-1.38-3.56 1.84.63 3.37 1.91 4.33 3.56zM12 4.04c.83 1.2 1.48 2.53 1.91 3.96h-3.82c.43-1.43 1.08-2.76 1.91-3.96zM4.26 14C4.1 13.36 4 12.69 4 12s.1-1.36.26-2h3.38c-.08.66-.14 1.32-.14 2 0 .68.06 1.34.14 2H4.26zm.82 2h2.95c.32 1.25.78 2.45 1.38 3.56-1.84-.63-3.37-1.9-4.33-3.56zM8.03 8H5.08c.96-1.66 2.49-2.93 4.33-3.56C8.81 5.55 8.35 6.75 8.03 8zM12 19.96c-.83-1.2-1.48-2.53-1.91-3.96h3.82c-.43 1.43-1.08 2.76-1.91 3.96zM14.34 14H9.66c-.09-.66-.16-1.32-.16-2 0-.68.07-1.35.16-2h4.68c.09.65.16 1.32.16 2 0 .68-.07 1.34-.16 2zm.25 5.56c.6-1.11 1.06-2.31 1.38-3.56h2.95c-.96 1.65-2.49 2.93-4.33 3.56zM16.36 14c.08-.66.14-1.32.14-2 0-.68-.06-1.34-.14-2h3.38c.16.64.26 1.31.26 2s-.1 1.36-.26 2h-3.38z"/>
                                    </svg>
                                    <span>Web</span>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" class="context-arrow">
                                        <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="ai-input-actions">
                        <button class="ai-image-btn" id="aiImageBtn" title="Upload Image">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
                            </svg>
                        </button>
                        <div class="ai-model-selector">
                            <select class="model-select" id="aiModelSelect">
                                <optgroup label="OpenAI">
                                    <option value="gpt-4" ${defaultModel === 'gpt-4' ? 'selected' : ''}>GPT-4</option>
                                    <option value="gpt-4-turbo" ${defaultModel === 'gpt-4-turbo' ? 'selected' : ''}>GPT-4 Turbo</option>
                                    <option value="gpt-3.5-turbo" ${defaultModel === 'gpt-3.5-turbo' ? 'selected' : ''}>GPT-3.5 Turbo</option>
                                </optgroup>
                                <optgroup label="Anthropic">
                                    <option value="claude-3-opus" ${defaultModel === 'claude-3-opus' ? 'selected' : ''}>Claude 3 Opus</option>
                                    <option value="claude-3-sonnet" ${defaultModel === 'claude-3-sonnet' ? 'selected' : ''}>Claude 3 Sonnet</option>
                                    <option value="claude-3-haiku" ${defaultModel === 'claude-3-haiku' ? 'selected' : ''}>Claude 3 Haiku</option>
                                </optgroup>
                            </select>
                        </div>
                        <button class="btn-send" id="aiSendBtn">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function renderChatMessages() {
    if (chatMessages.length === 0) {
        return `
            <div class="ai-message assistant">
                <div class="message-content">
                    Hello! I'm Tru.ai Assistant. How can I help you today?
                </div>
            </div>
        `;
    }
    
    return chatMessages.map(msg => `
        <div class="ai-message ${msg.role}">
            <div class="message-content">${formatMessageContent(msg.content)}</div>
            ${msg.model ? `<div style="font-size: 10px; color: var(--text-tertiary); margin-top: 4px;">Model: ${msg.model}</div>` : ''}
        </div>
    `).join('');
}

function formatMessageContent(content) {
    if (typeof content === 'object') {
        content = JSON.stringify(content, null, 2);
    }
    
    // Escape HTML
    content = escapeHtml(content);
    
    // Format code blocks
    content = content.replace(/```(\w+)?\n([\s\S]*?)```/g, (match, lang, code) => {
        return `<pre style="background: var(--bg-primary); padding: 12px; border-radius: 4px; overflow-x: auto; margin: 8px 0;"><code>${escapeHtml(code.trim())}</code></pre>`;
    });
    
    // Format inline code
    content = content.replace(/`([^`]+)`/g, '<code style="background: var(--bg-primary); padding: 2px 6px; border-radius: 3px; font-family: monospace;">$1</code>');
    
    // Format line breaks
    content = content.replace(/\n/g, '<br>');
    
    return content;
}

function renderSettingsPanel() {
    // Use defaults if settings not loaded yet
    if (!settings) {
        settings = {
            editor: {
                fontSize: 14,
                fontFamily: 'Monaco',
                tabSize: 4,
                wordWrap: true,
                minimapEnabled: true
            },
            ai: {
                openaiApiKey: '',
                anthropicApiKey: '',
                model: 'gpt-4',
                temperature: 0.7
            },
            appearance: {
                theme: 'dark'
            },
            git: {
                autoFetch: false,
                confirmSync: true
            },
            terminal: {
                shell: 'zsh'
            }
        };
    }

    const editor = settings.editor || {};
    const ai = settings.ai || {};
    const appearance = settings.appearance || {};
    const git = settings.git || {};
    const terminal = settings.terminal || {};

    return `
        <div class="settings-container">
            <div class="settings-sidebar">
                <div class="settings-sidebar-title">Cursor Settings</div>
                <div class="settings-nav">
                    <button class="settings-nav-item ${activeSettingsTab === 'general' ? 'active' : ''}" data-tab="general">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94L14.4 2.81c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.12.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.07.62-.07.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.12-.57 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/>
                        </svg>
                        General
                    </button>
                    <button class="settings-nav-item ${activeSettingsTab === 'models' ? 'active' : ''}" data-tab="models">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/>
                        </svg>
                        Models
                    </button>
                    <button class="settings-nav-item ${activeSettingsTab === 'features' ? 'active' : ''}" data-tab="features">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M22.7 19l-9.1-9.1c.9-2.3.4-5-1.5-6.9-2-2-5-2.4-7.4-1.3L9 6 6 9 1.6 4.7C.4 7.1.9 10.1 2.9 12.1c1.9 1.9 4.6 2.4 6.9 1.5l9.1 9.1c.4.4 1 .4 1.4 0l2.3-2.3c.5-.4.5-1.1.1-1.4z"/>
                        </svg>
                        Features
                    </button>
                    <button class="settings-nav-item ${activeSettingsTab === 'beta' ? 'active' : ''}" data-tab="beta">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19.3 8.2l-5.4-5.4C13.8 2.4 13.4 2 13 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V9c0-.4-.4-.8-.7-.8zM14 3.5L18.5 8H14V3.5zM19 20H6V4h6v5h7v11z"/>
                        </svg>
                        Beta
                    </button>
                </div>
            </div>
            <div class="settings-content">
                ${renderSettingsContent()}
            </div>
        </div>
    `;
}

function renderSettingsContent() {
    const editor = settings?.editor || {};
    const ai = settings?.ai || {};
    const appearance = settings?.appearance || {};
    const git = settings?.git || {};
    const terminal = settings?.terminal || {};

    switch(activeSettingsTab) {
        case 'general':
            return renderGeneralSettings(editor, appearance, git, terminal);
        case 'models':
            return renderModelsSettings(ai);
        case 'features':
            return renderFeaturesSettings();
        case 'beta':
            return renderBetaSettings();
        default:
            return renderGeneralSettings(editor, appearance, git, terminal);
    }
}

function renderGeneralSettings(editor, appearance, git, terminal) {
    return `
        <div class="settings-panel">
            <!-- Account Section -->
            <div class="settings-section">
                <div class="settings-section-header">
                    <h2 class="settings-section-title">Account</h2>
                    <span class="settings-badge">Pro Trial</span>
                </div>
                <div class="settings-section-content">
                    <p style="color: var(--text-secondary); margin-bottom: 16px;">
                        You are currently signed in with ${window.TRUAI_CONFIG?.USERNAME || 'admin'}@tru.ai
                    </p>
                    <div class="settings-actions">
                        <button class="btn-upgrade">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M2.81 14.12L5.64 11.3l8.49 8.48-2.83 2.83-8.49-8.49zm14.12-8.49l-2.83-2.83-8.48 8.49 2.83 2.83 8.48-8.49zm-5.66 5.65l-1.41-1.41 4.24-4.24 1.41 1.41-4.24 4.24z"/>
                            </svg>
                            Upgrade to Pro
                        </button>
                        <button class="btn-secondary">Manage</button>
                        <button class="btn-secondary">Log out</button>
                    </div>
                </div>
            </div>

            <!-- VS Code Import -->
            <div class="settings-section">
                <div class="settings-section-header">
                    <h2 class="settings-section-title">VS Code Import</h2>
                </div>
                <div class="settings-section-content">
                    <p style="color: var(--text-secondary); margin-bottom: 12px;">
                        Instantly use all of your extensions, settings and keybindings.
                    </p>
                    <a href="#" class="link-import">+ Import</a>
                </div>
            </div>

            <!-- Rules for AI -->
            <div class="settings-section">
                <div class="settings-section-header">
                    <h2 class="settings-section-title">Rules for AI</h2>
                </div>
                <div class="settings-section-content">
                    <p style="color: var(--text-secondary); margin-bottom: 12px;">
                        These rules get shown to the AI on all chats and Command-K sessions.
                    </p>
                    <textarea class="rules-textarea" id="rulesForAI" placeholder="e.g., 'always use functional React, never use unwrap in rust, always output your answers in Portuguese'">${settings?.rulesForAI || ''}</textarea>
                    <div class="rules-status">Saved ✓</div>
                    <div class="settings-toggle">
                        <label class="toggle-label">
                            <span>Include .cursorrules file</span>
                            <span class="toggle-description">If off, we will not include any .cursorrules files in your Rules for AI.</span>
                        </label>
                        <label class="toggle-switch">
                            <input type="checkbox" id="includeCursorRules" ${settings?.includeCursorRules !== false ? 'checked' : ''}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Editor Settings -->
            <div class="settings-section">
                <div class="settings-section-header">
                    <h2 class="settings-section-title">Editor</h2>
                </div>
                <div class="settings-section-content">
            <div style="padding: 8px;">
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 4px; font-size: 12px; color: var(--text-secondary);">Font Size</label>
                    <input type="range" id="setting-fontSize" min="10" max="24" value="${editor.fontSize || 14}" 
                           style="width: 100%;" oninput="document.getElementById('fontSizeValue').textContent = this.value">
                    <div style="display: flex; justify-content: space-between; font-size: 11px; color: var(--text-tertiary);">
                        <span>10</span>
                        <span id="fontSizeValue">${editor.fontSize || 14}</span>
                        <span>24</span>
                    </div>
                </div>
                
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 4px; font-size: 12px; color: var(--text-secondary);">Font Family</label>
                    <select id="setting-fontFamily" style="width: 100%; padding: 6px; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 4px; color: var(--text-primary); font-size: 12px;">
                        <option value="Monaco" ${editor.fontFamily === 'Monaco' ? 'selected' : ''}>Monaco</option>
                        <option value="Menlo" ${editor.fontFamily === 'Menlo' ? 'selected' : ''}>Menlo</option>
                        <option value="SF Mono" ${editor.fontFamily === 'SF Mono' ? 'selected' : ''}>SF Mono</option>
                        <option value="Courier" ${editor.fontFamily === 'Courier' ? 'selected' : ''}>Courier</option>
                    </select>
                </div>
                
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 4px; font-size: 12px; color: var(--text-secondary);">Tab Size</label>
                    <input type="range" id="setting-tabSize" min="2" max="8" value="${editor.tabSize || 4}" 
                           style="width: 100%;" oninput="document.getElementById('tabSizeValue').textContent = this.value">
                    <div style="display: flex; justify-content: space-between; font-size: 11px; color: var(--text-tertiary);">
                        <span>2</span>
                        <span id="tabSizeValue">${editor.tabSize || 4}</span>
                        <span>8</span>
                    </div>
                </div>
                
                <div style="margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
                    <label style="font-size: 12px; color: var(--text-secondary);">Word Wrap</label>
                    <input type="checkbox" id="setting-wordWrap" ${editor.wordWrap ? 'checked' : ''} 
                           style="cursor: pointer;">
                </div>
                
                <div style="margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
                    <label style="font-size: 12px; color: var(--text-secondary);">Minimap</label>
                    <input type="checkbox" id="setting-minimapEnabled" ${editor.minimapEnabled ? 'checked' : ''} 
                           style="cursor: pointer;">
                </div>
            </div>

            <!-- AI Configuration -->
            <div class="section-title">AI Configuration</div>
            <div style="padding: 8px;">
                <div style="margin-bottom: 20px; padding: 12px; background: var(--bg-primary); border-radius: 6px; border: 1px solid var(--border-color);">
                    <div style="font-size: 11px; font-weight: 600; color: var(--text-secondary); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;">OpenAI / ChatGPT</div>
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; margin-bottom: 4px; font-size: 12px; color: var(--text-secondary);">OpenAI API Key</label>
                        <input type="password" id="setting-openaiApiKey" value="${ai.openaiApiKey || ''}" 
                               placeholder="sk-..." 
                               style="width: 100%; padding: 6px; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 4px; color: var(--text-primary); font-size: 12px; font-family: monospace;">
                        <div style="font-size: 10px; color: var(--text-tertiary); margin-top: 4px;">
                            Get key: <a href="https://platform.openai.com/api-keys" target="_blank" style="color: var(--accent-blue);">platform.openai.com</a>
                        </div>
                    </div>
                </div>
                
                <div style="margin-bottom: 20px; padding: 12px; background: var(--bg-primary); border-radius: 6px; border: 1px solid var(--border-color);">
                    <div style="font-size: 11px; font-weight: 600; color: var(--text-secondary); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Anthropic / Claude</div>
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; margin-bottom: 4px; font-size: 12px; color: var(--text-secondary);">Anthropic API Key</label>
                        <input type="password" id="setting-anthropicApiKey" value="${ai.anthropicApiKey || ''}" 
                               placeholder="sk-ant-..." 
                               style="width: 100%; padding: 6px; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 4px; color: var(--text-primary); font-size: 12px; font-family: monospace;">
                        <div style="font-size: 10px; color: var(--text-tertiary); margin-top: 4px;">
                            Get key: <a href="https://console.anthropic.com/" target="_blank" style="color: var(--accent-blue);">console.anthropic.com</a>
                        </div>
                    </div>
                </div>
                
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 4px; font-size: 12px; color: var(--text-secondary);">Default Model</label>
                    <select id="setting-model" style="width: 100%; padding: 6px; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 4px; color: var(--text-primary); font-size: 12px;">
                        <optgroup label="OpenAI">
                            <option value="gpt-4" ${ai.model === 'gpt-4' ? 'selected' : ''}>GPT-4</option>
                            <option value="gpt-4-turbo" ${ai.model === 'gpt-4-turbo' ? 'selected' : ''}>GPT-4 Turbo</option>
                            <option value="gpt-3.5-turbo" ${ai.model === 'gpt-3.5-turbo' ? 'selected' : ''}>GPT-3.5 Turbo</option>
                        </optgroup>
                        <optgroup label="Anthropic">
                            <option value="claude-3-opus" ${ai.model === 'claude-3-opus' ? 'selected' : ''}>Claude 3 Opus</option>
                            <option value="claude-3-sonnet" ${ai.model === 'claude-3-sonnet' ? 'selected' : ''}>Claude 3 Sonnet</option>
                            <option value="claude-3-haiku" ${ai.model === 'claude-3-haiku' ? 'selected' : ''}>Claude 3 Haiku</option>
                        </optgroup>
                    </select>
                </div>
                
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 4px; font-size: 12px; color: var(--text-secondary);">Temperature</label>
                    <input type="range" id="setting-temperature" min="0" max="1" step="0.1" value="${ai.temperature || 0.7}" 
                           style="width: 100%;" oninput="document.getElementById('temperatureValue').textContent = this.value">
                    <div style="display: flex; justify-content: space-between; font-size: 11px; color: var(--text-tertiary);">
                        <span>0.0</span>
                        <span id="temperatureValue">${ai.temperature || 0.7}</span>
                        <span>1.0</span>
                    </div>
                </div>
            </div>

            <!-- Appearance / Theme Customization -->
            <div class="settings-section">
                <div class="settings-section-header">
                    <h2 class="settings-section-title">Appearance</h2>
                </div>
                <div class="settings-section-content">
                    <label style="display: block; margin-bottom: 12px; font-size: 13px; color: var(--text-secondary);">Customize Theme</label>
                    <p style="font-size: 12px; color: var(--text-tertiary); margin-bottom: 16px;">
                        TruAi works for all coders. Pick your vibe.
                    </p>
                    <div class="theme-preview-grid">
                        <div class="theme-preview-card ${appearance.theme === 'dark' ? 'selected' : ''}" data-theme="dark">
                            <div class="theme-preview-header">
                                <h3 class="theme-preview-title">Cursor Dark</h3>
                            </div>
                            <div class="theme-preview-code">
                                <div class="theme-code-line"><span class="code-keyword">export</span> <span class="code-keyword">const</span> <span class="code-function">PaintCanvas</span></div>
                                <div class="theme-code-line"><span class="code-keyword">const</span> <span class="code-variable">getCanvasPoint</span> = <span class="code-function">useEffect</span>()</div>
                                <div class="theme-code-line"><span class="code-keyword">return</span> <span class="code-string">'Point'</span></div>
                            </div>
                        </div>
                        <div class="theme-preview-card ${appearance.theme === 'light' ? 'selected' : ''}" data-theme="light">
                            <div class="theme-preview-header">
                                <h3 class="theme-preview-title">Cursor Light</h3>
                            </div>
                            <div class="theme-preview-code light-theme">
                                <div class="theme-code-line"><span class="code-keyword">export</span> <span class="code-keyword">const</span> <span class="code-function">PaintCanvas</span></div>
                                <div class="theme-code-line"><span class="code-keyword">const</span> <span class="code-variable">getCanvasPoint</span> = <span class="code-function">useEffect</span>()</div>
                                <div class="theme-code-line"><span class="code-keyword">return</span> <span class="code-string">'Point'</span></div>
                            </div>
                        </div>
                        <div class="theme-preview-card ${appearance.theme === 'auto' ? 'selected' : ''}" data-theme="auto">
                            <div class="theme-preview-header">
                                <h3 class="theme-preview-title">Auto (System)</h3>
                            </div>
                            <div class="theme-preview-code">
                                <div class="theme-code-line"><span class="code-keyword">export</span> <span class="code-keyword">const</span> <span class="code-function">PaintCanvas</span></div>
                                <div class="theme-code-line"><span class="code-keyword">const</span> <span class="code-variable">getCanvasPoint</span> = <span class="code-function">useEffect</span>()</div>
                                <div class="theme-code-line"><span class="code-keyword">return</span> <span class="code-string">'Point'</span></div>
                            </div>
                        </div>
                    </div>
                    <div style="margin-top: 16px; text-align: center;">
                        <a href="#" class="link-explore-themes">Explore other themes</a>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function renderModelsSettings(ai) {
    const models = [
        { name: 'gpt-4', enabled: ai.model === 'gpt-4' },
        { name: 'gpt-4o', enabled: ai.model === 'gpt-4o' },
        { name: 'claude-3-opus', enabled: ai.model === 'claude-3-opus' },
        { name: 'cursor-small', enabled: false },
        { name: 'gpt-3.5-turbo', enabled: ai.model === 'gpt-3.5-turbo' },
        { name: 'gpt-4-turbo-2024-04-09', enabled: ai.model === 'gpt-4-turbo' },
        { name: 'claude-3.5-sonnet', enabled: ai.model === 'claude-3-sonnet' },
        { name: 'gpt-4o-mini', enabled: false }
    ];

    return `
        <div class="settings-panel">
            <div class="settings-section">
                <div class="settings-section-header">
                    <h2 class="settings-section-title">Model Names</h2>
                </div>
                <div class="settings-section-content">
                    <p style="color: var(--text-secondary); margin-bottom: 16px;">
                        Add new model names to TruAi. Often used to configure the latest OpenAI models or OpenRouter models.
                    </p>
                    <div class="model-list">
                        ${models.map(model => `
                            <div class="model-item">
                                <span class="model-name">${model.name}</span>
                                <label class="toggle-switch-small">
                                    <input type="checkbox" ${model.enabled ? 'checked' : ''}>
                                    <span class="toggle-slider-small"></span>
                                </label>
                            </div>
                        `).join('')}
                    </div>
                    <button class="btn-add-model">+ Add model</button>
                </div>
            </div>

            <div class="settings-section">
                <div class="settings-section-header">
                    <h2 class="settings-section-title">OpenAI API Key</h2>
                </div>
                <div class="settings-section-content">
                    <p style="color: var(--text-secondary); margin-bottom: 12px;">
                        You can put in your <a href="#" style="color: var(--accent-blue);">OpenAI key</a> to use TruAi at public API costs. Note: this can cost more than pro and won't work for custom model features.
                    </p>
                    <div class="api-key-input-group">
                        <input type="password" class="api-key-input" id="setting-openaiApiKey" value="${ai.openaiApiKey || ''}" placeholder="Enter your OpenAI API Key">
                        <button class="btn-verify">Verify →</button>
                    </div>
                    <details class="api-key-details">
                        <summary style="cursor: pointer; color: var(--text-secondary); font-size: 12px; margin-top: 8px;">
                            Override OpenAI Base URL (when using key)
                        </summary>
                    </details>
                </div>
            </div>
        </div>
    `;
}

function renderFeaturesSettings() {
    return `
        <div class="settings-panel">
            <div class="settings-section">
                <div class="settings-section-header">
                    <h2 class="settings-section-title">Features</h2>
                </div>
                <div class="settings-section-content">
                    <p style="color: var(--text-secondary);">
                        Feature settings coming soon.
                    </p>
                </div>
            </div>
        </div>
    `;
}

function renderBetaSettings() {
    return `
        <div class="settings-panel">
            <div class="settings-section">
                <div class="settings-section-header">
                    <h2 class="settings-section-title">Beta Features</h2>
                </div>
                <div class="settings-section-content">
                    <p style="color: var(--text-secondary);">
                        Beta features coming soon.
                    </p>
                </div>
            </div>
        </div>
    `;
}

function renderEditorContent() {
    if (!activeTab) {
        return `
            <div class="editor-welcome">
                <h1>Tru.ai</h1>
                <p>Super Admin AI Platform</p>
                <div class="welcome-actions">
                    <button class="welcome-btn" id="openFileBtn">Open File</button>
                    <button class="welcome-btn" id="newFileBtn">New File</button>
                    <button class="welcome-btn" id="openFolderBtn">Open Folder</button>
                </div>
            </div>
        `;
    }
    
    return `
        <div class="editor-with-toolbar">
            <div class="editor-toolbar">
                <button class="editor-toolbar-btn" id="aiRewriteBtn" title="AI Rewrite Selection (Cmd/Ctrl+Enter)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1.41 16.09V20h-2.67v-1.93c-1.71-.36-3.16-1.46-3.27-3.4h1.96c.1 1.05.82 1.87 2.65 1.87 1.96 0 2.4-.98 2.4-1.59 0-.83-.44-1.61-2.67-2.14-2.48-.6-4.18-1.62-4.18-3.67 0-1.72 1.39-2.84 3.11-3.21V4h2.67v1.95c1.86.45 2.79 1.86 2.85 3.39H14.3c-.05-1.11-.64-1.87-2.22-1.87-1.5 0-2.4.68-2.4 1.64 0 .84.65 1.39 2.67 1.91s4.18 1.39 4.18 3.91c-.01 1.83-1.38 2.83-3.12 3.16z"/>
                    </svg>
                    AI Rewrite
                </button>
            </div>
            <textarea class="code-textarea" id="codeEditor" spellcheck="false">${activeTab.content || ''}</textarea>
        </div>
    `;
}

function setupDashboardListeners() {
    const api = new TruAiAPI();

    // Activity bar panel switching
    document.querySelectorAll('.activity-icon[data-panel]').forEach(btn => {
        btn.addEventListener('click', async function() {
            activePanel = this.dataset.panel;
            showSidebar = true;
            renderDashboard();
            
            // Load chat history if AI panel is opened
            if (activePanel === 'ai') {
                await loadChatHistory();
            }
        });
    });

    // Sidebar toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            showSidebar = !showSidebar;
            renderDashboard();
        });
    }

    // Terminal toggle (from status bar or keyboard)
    const terminalToggle = document.getElementById('terminalToggle');
    if (terminalToggle) {
        terminalToggle.addEventListener('click', function() {
            showTerminal = false;
            renderDashboard();
        });
    }

    // Terminal input
    const terminalInput = document.getElementById('terminalInput');
    if (terminalInput) {
        terminalInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const command = this.value.trim();
                if (command) {
                    terminalHistory.push(`$ ${command}`);
                    terminalHistory.push('Command executed');
                    terminalInput.value = '';
                    renderDashboard();
                    // Scroll terminal to bottom
                    setTimeout(() => {
                        const terminalContent = document.getElementById('terminalContent');
                        if (terminalContent) {
                            terminalContent.scrollTop = terminalContent.scrollHeight;
                        }
                    }, 100);
                }
            }
        });
    }

    // AI Chat
    const aiSendBtn = document.getElementById('aiSendBtn');
    const aiInput = document.getElementById('aiInput');
    if (aiSendBtn && aiInput) {
        aiSendBtn.addEventListener('click', async function() {
            await handleAIMessage();
        });

        aiInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                aiSendBtn.click();
            }
            // Handle @ for context menu
            if (e.key === '@') {
                showContextMenu();
            }
        });
        
        // Add context button
        const addContextBtn = document.getElementById('addContextBtn');
        if (addContextBtn) {
            addContextBtn.addEventListener('click', function() {
                showContextMenu();
            });
        }
        
        // Context menu handling
        let selectedContexts = [];
        
        function showContextMenu() {
            const menu = document.getElementById('contextMenu');
            if (menu) {
                menu.style.display = 'block';
                const search = document.getElementById('contextSearch');
                if (search) search.focus();
            }
        }
        
        function hideContextMenu() {
            const menu = document.getElementById('contextMenu');
            if (menu) menu.style.display = 'none';
        }
        
        // Context menu item clicks
        document.addEventListener('click', function(e) {
            const menu = document.getElementById('contextMenu');
            if (menu && !menu.contains(e.target) && e.target.id !== 'addContextBtn') {
                hideContextMenu();
            }
        });
        
        document.addEventListener('click', function(e) {
            const item = e.target.closest('.context-menu-item');
            if (item) {
                const contextType = item.dataset.context;
                addContext(contextType);
                hideContextMenu();
            }
        });
        
        function addContext(type) {
            if (!selectedContexts.includes(type)) {
                selectedContexts.push(type);
                updateContextTags();
            }
        }
        
        function removeContext(type) {
            selectedContexts = selectedContexts.filter(c => c !== type);
            updateContextTags();
        }
        
        function updateContextTags() {
            const tagsContainer = document.getElementById('contextTags');
            if (!tagsContainer) return;
            
            tagsContainer.innerHTML = selectedContexts.map(type => `
                <span class="context-tag">
                    @${type}
                    <button class="context-tag-remove" onclick="removeContext('${type}')">×</button>
                </span>
            `).join('');
        }
        
        // Make removeContext globally accessible
        window.removeContext = removeContext;
    }
    
    async function handleAIMessage() {
        const message = aiInput.value.trim();
        if (!message) return;

        const chatContainer = document.getElementById('aiChat');
        const modelSelect = document.getElementById('aiModelSelect');
        const model = modelSelect?.value || (settings && settings.ai && settings.ai.model) || 'gpt-4';
        
        // Disable input while processing
        aiInput.disabled = true;
        aiSendBtn.disabled = true;
        aiSendBtn.textContent = 'Sending...';

        // Add user message to chat
        chatMessages.push({
            role: 'user',
            content: message,
            timestamp: new Date()
        });
        renderChatContainer();
        aiInput.value = '';

        // Show loading message
        const loadingId = 'loading-' + Date.now();
        chatMessages.push({
            role: 'assistant',
            content: 'Thinking...',
            loading: true,
            id: loadingId
        });
        renderChatContainer();

        try {
            // Call chat API endpoint
            const response = await api.sendMessage(message, currentConversationId, model);
            
            // Remove loading message
            chatMessages = chatMessages.filter(msg => msg.id !== loadingId);
            
            // Add AI response
            if (response.message) {
                chatMessages.push({
                    role: 'assistant',
                    content: response.message.content || response.message,
                    model: response.message.model || model,
                    timestamp: new Date()
                });
                
                // Update conversation ID if new
                if (response.conversation_id) {
                    currentConversationId = response.conversation_id;
                }
            } else {
                throw new Error('Invalid response format');
            }
            
            renderChatContainer();
        } catch (error) {
            console.error('AI chat error:', error);
            
            // Remove loading message
            chatMessages = chatMessages.filter(msg => msg.id !== loadingId);
            
            // Add error message
            chatMessages.push({
                role: 'assistant',
                content: `Error: ${error.message}\n\nPlease check:\n1. API keys are configured in Settings\n2. API keys are valid\n3. Network connection is working`,
                error: true,
                timestamp: new Date()
            });
            
            renderChatContainer();
        } finally {
            // Re-enable input
            aiInput.disabled = false;
            aiSendBtn.disabled = false;
            aiSendBtn.textContent = 'Send';
            aiInput.focus();
        }
    }
    
    function renderChatContainer() {
        const chatContainer = document.getElementById('aiChat');
        if (!chatContainer) return;
        
        chatContainer.innerHTML = renderChatMessages();
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }

    // Editor tabs
    document.querySelectorAll('.editor-tab[data-tab]').forEach(tab => {
        tab.addEventListener('click', function() {
            const index = parseInt(this.dataset.tab);
            activeTab = openTabs[index];
            renderDashboard();
        });
    });

    document.querySelectorAll('.tab-close[data-close]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const index = parseInt(this.dataset.close);
            openTabs.splice(index, 1);
            if (activeTab === openTabs[index]) {
                activeTab = openTabs.length > 0 ? openTabs[0] : null;
            }
            renderDashboard();
        });
    });

    // Code editor changes
    const codeEditor = document.getElementById('codeEditor');
    if (codeEditor) {
        // Apply editor settings
        applyEditorSettings(codeEditor);
        
        codeEditor.addEventListener('input', function() {
            if (activeTab) {
                activeTab.content = this.value;
                activeTab.modified = true;
                // Update tab indicator
                const tabElement = document.querySelector(`.editor-tab[data-tab="${openTabs.indexOf(activeTab)}"]`);
                if (tabElement && !tabElement.querySelector('.tab-modified')) {
                    tabElement.innerHTML += '<span class="tab-modified">•</span>';
                }
            }
        });
    }

    // Welcome screen actions
    const openFileBtn = document.getElementById('openFileBtn');
    const newFileBtn = document.getElementById('newFileBtn');
    const openFolderBtn = document.getElementById('openFolderBtn');

    if (openFileBtn) {
        openFileBtn.addEventListener('click', function() {
            // Create file input
            const input = document.createElement('input');
            input.type = 'file';
            input.multiple = true;
            input.addEventListener('change', function(e) {
                Array.from(e.target.files).forEach(file => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        openTabs.push({
                            name: file.name,
                            content: e.target.result,
                            modified: false
                        });
                        activeTab = openTabs[openTabs.length - 1];
                        renderDashboard();
                    };
                    reader.readAsText(file);
                });
            });
            input.click();
        });
    }

    if (newFileBtn) {
        newFileBtn.addEventListener('click', function() {
            const name = prompt('Enter file name:');
            if (name) {
                openTabs.push({
                    name: name,
                    content: '',
                    modified: false
                });
                activeTab = openTabs[openTabs.length - 1];
                renderDashboard();
            }
        });
    }

    // Settings button
    const settingsBtn = document.getElementById('settingsBtn');
    if (settingsBtn) {
        settingsBtn.addEventListener('click', async function() {
            activePanel = 'settings';
            showSidebar = true;
            // Load settings if not already loaded
            if (!settings) {
                await loadSettings();
            }
            renderDashboard();
            
            // Setup settings tab navigation
            setupSettingsNavigation();
        });
    }
    
    function setupSettingsNavigation() {
        document.querySelectorAll('.settings-nav-item').forEach(btn => {
            btn.addEventListener('click', function() {
                activeSettingsTab = this.dataset.tab;
                renderDashboard();
                setupSettingsNavigation();
            });
        });
    }

    // Settings handlers
    const saveSettingsBtn = document.getElementById('saveSettingsBtn');
    if (saveSettingsBtn) {
        saveSettingsBtn.addEventListener('click', async function() {
            await saveSettings();
        });
    }

    const resetSettingsBtn = document.getElementById('resetSettingsBtn');
    if (resetSettingsBtn) {
        resetSettingsBtn.addEventListener('click', async function() {
            if (confirm('Reset all settings to defaults?')) {
                try {
                    await api.resetSettings();
                    await loadSettings();
                    alert('Settings reset to defaults');
                } catch (error) {
                    alert('Error resetting settings: ' + error.message);
                }
            }
        });
    }

    const clearConversationsBtn = document.getElementById('clearConversationsBtn');
    if (clearConversationsBtn) {
        clearConversationsBtn.addEventListener('click', async function() {
            if (confirm('Clear all conversations? This cannot be undone.')) {
                try {
                    await api.clearConversations();
                    alert('All conversations cleared');
                } catch (error) {
                    alert('Error clearing conversations: ' + error.message);
                }
            }
        });
    }

    // Theme selector - apply immediately (dropdown)
    const themeSelect = document.getElementById('setting-theme');
    if (themeSelect) {
        themeSelect.addEventListener('change', function() {
            const theme = this.value;
            if (settings && settings.appearance) {
                settings.appearance.theme = theme;
            }
            applyTheme();
            updateThemePreviewSelection(theme);
            console.log('Theme changed to:', theme);
        });
    }
    
    // Theme preview cards
    document.querySelectorAll('.theme-preview-card').forEach(card => {
        card.addEventListener('click', function() {
            const theme = this.dataset.theme;
            if (settings && settings.appearance) {
                settings.appearance.theme = theme;
            }
            applyTheme();
            updateThemePreviewSelection(theme);
            
            // Update dropdown if it exists
            const themeSelect = document.getElementById('setting-theme');
            if (themeSelect) {
                themeSelect.value = theme;
            }
        });
    });
    
    function updateThemePreviewSelection(selectedTheme) {
        document.querySelectorAll('.theme-preview-card').forEach(card => {
            if (card.dataset.theme === selectedTheme) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }
        });
    }

    // Logout
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function() {
            sessionStorage.removeItem('truai_legal_acknowledged');
            window.location.reload();
        });
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Cmd/Ctrl + Enter for AI inline rewrite
        if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
            const editor = document.getElementById('codeEditor');
            if (editor && document.activeElement === editor) {
                e.preventDefault();
                showInlineRewritePrompt();
            }
        }
        // Cmd/Ctrl + ` to toggle terminal
        if ((e.metaKey || e.ctrlKey) && e.key === '`') {
            e.preventDefault();
            showTerminal = !showTerminal;
            renderDashboard();
        }
        // Cmd/Ctrl + B to toggle sidebar
        if ((e.metaKey || e.ctrlKey) && e.key === 'b') {
            e.preventDefault();
            showSidebar = !showSidebar;
            renderDashboard();
        }
        // Cmd/Ctrl + Shift + P for command palette (future)
        if ((e.metaKey || e.ctrlKey) && e.shiftKey && e.key === 'P') {
            e.preventDefault();
            // Command palette (to be implemented)
        }
    });
    
    // AI Rewrite button
    const aiRewriteBtn = document.getElementById('aiRewriteBtn');
    if (aiRewriteBtn) {
        aiRewriteBtn.addEventListener('click', function() {
            showInlineRewritePrompt();
        });
    }
    
    // Context menu for code editor
    const editorElement = document.getElementById('codeEditor');
    if (editorElement) {
        editorElement.addEventListener('contextmenu', function(e) {
            const selection = getEditorSelection();
            if (selection) {
                e.preventDefault();
                showEditorContextMenu(e.pageX, e.pageY);
            }
        });
    }
}

async function loadFileTree() {
    // Load file tree from API or file system
    // For now, show a sample structure
    fileTree = [
        { name: 'src', type: 'folder', children: [
            { name: 'index.js', type: 'file' },
            { name: 'app.js', type: 'file' }
        ]},
        { name: 'package.json', type: 'file' },
        { name: 'README.md', type: 'file' }
    ];
    
    renderFileTree();
}

function renderFileTree() {
    const treeContainer = document.getElementById('fileTree');
    if (!treeContainer) return;
    
    function renderItems(items, level = 0) {
        return items.map(item => {
            const icon = item.type === 'folder' ? '📁' : '📄';
            const indent = level * 16;
            return `
                <div class="file-item" style="padding-left: ${indent + 8}px;" data-path="${item.name}">
                    <span class="file-icon">${icon}</span>
                    <span>${item.name}</span>
                </div>
                ${item.children ? renderItems(item.children, level + 1) : ''}
            `;
        }).join('');
    }
    
    treeContainer.innerHTML = renderItems(fileTree);
    
    // Add click handlers
    treeContainer.querySelectorAll('.file-item').forEach(item => {
        item.addEventListener('click', function() {
            const path = this.dataset.path;
            // Open file in editor
            const tab = {
                name: path,
                content: `// Content of ${path}`,
                modified: false
            };
            openTabs.push(tab);
            activeTab = tab;
            renderDashboard();
        });
    });
}

async function loadSettings() {
    try {
        const api = new TruAiAPI();
        const response = await api.getSettings();
        settings = response.settings;
        
        // Re-render if settings panel is active
        if (activePanel === 'settings') {
            renderDashboard();
        }
        
        // Apply settings to editor and theme
        applySettingsToEditor();
    } catch (error) {
        console.error('Error loading settings:', error);
        // Use defaults if API fails
        settings = {
            editor: {
                fontSize: 14,
                fontFamily: 'Monaco',
                tabSize: 4,
                wordWrap: true,
                minimapEnabled: true
            },
            ai: {
                openaiApiKey: '',
                anthropicApiKey: '',
                model: 'gpt-4',
                temperature: 0.7
            },
            appearance: {
                theme: 'dark'
            },
            git: {
                autoFetch: false,
                confirmSync: true
            },
            terminal: {
                shell: 'zsh'
            }
        };
        
        // Re-render if settings panel is active
        if (activePanel === 'settings') {
            renderDashboard();
        }
        
        // Apply defaults to editor and theme
        applySettingsToEditor();
    }
}

async function saveSettings() {
    try {
        const api = new TruAiAPI();
        
        // Collect all settings
        const settingsToSave = {
            editor: {
                fontSize: parseInt(document.getElementById('setting-fontSize')?.value || 14),
                fontFamily: document.getElementById('setting-fontFamily')?.value || 'Monaco',
                tabSize: parseInt(document.getElementById('setting-tabSize')?.value || 4),
                wordWrap: document.getElementById('setting-wordWrap')?.checked || false,
                minimapEnabled: document.getElementById('setting-minimapEnabled')?.checked || false
            },
            ai: {
                openaiApiKey: document.getElementById('setting-openaiApiKey')?.value || '',
                anthropicApiKey: document.getElementById('setting-anthropicApiKey')?.value || '',
                model: document.getElementById('setting-model')?.value || 'gpt-4',
                temperature: parseFloat(document.getElementById('setting-temperature')?.value || 0.7)
            },
            appearance: {
                theme: document.getElementById('setting-theme')?.value || 'dark'
            },
            git: {
                autoFetch: document.getElementById('setting-autoFetch')?.checked || false,
                confirmSync: document.getElementById('setting-confirmSync')?.checked || true
            },
            terminal: {
                shell: document.getElementById('setting-shell')?.value || 'zsh'
            }
        };
        
        await api.saveSettings(settingsToSave);
        settings = settingsToSave;
        
        // Apply settings (including theme)
        applySettingsToEditor();
        
        alert('Settings saved successfully!');
    } catch (error) {
        alert('Error saving settings: ' + error.message);
        console.error('Error saving settings:', error);
    }
}

function applySettingsToEditor() {
    if (!settings) return;
    
    const editor = document.getElementById('codeEditor');
    if (editor) {
        applyEditorSettings(editor);
    }
    
    // Apply theme
    applyTheme();
}

function applyTheme() {
    // Get theme from settings or use default
    let theme = 'dark';
    if (settings && settings.appearance && settings.appearance.theme) {
        theme = settings.appearance.theme;
    }
    
    const html = document.documentElement;
    
    // Remove existing theme attributes
    html.removeAttribute('data-theme');
    
    if (theme === 'auto') {
        // Auto theme follows system preference
        html.setAttribute('data-theme', 'auto');
    } else if (theme === 'light') {
        html.setAttribute('data-theme', 'light');
    } else {
        // Dark theme (default)
        html.setAttribute('data-theme', 'dark');
    }
    
    // Also update body class for compatibility
    document.body.className = document.body.className.replace(/\btheme-\w+\b/g, '');
    document.body.classList.add(`theme-${theme}`);
    
    console.log('Theme applied:', theme);
}

// Make applyTheme globally accessible
window.applyTheme = applyTheme;

function applyEditorSettings(editor) {
    if (!settings || !settings.editor) return;
    
    const editorSettings = settings.editor;
    
    editor.style.fontSize = (editorSettings.fontSize || 14) + 'px';
    editor.style.fontFamily = editorSettings.fontFamily || 'Monaco';
    
    // Tab size (convert to spaces)
    const tabSize = editorSettings.tabSize || 4;
    editor.style.tabSize = tabSize;
    editor.setAttribute('data-tab-size', tabSize);
    
    // Word wrap
    if (editorSettings.wordWrap) {
        editor.style.whiteSpace = 'pre-wrap';
        editor.style.wordWrap = 'break-word';
    } else {
        editor.style.whiteSpace = 'pre';
        editor.style.wordWrap = 'normal';
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize dashboard - wait for legal notice if needed
async function initializeDashboardScript() {
    // Check if data sharing consent should be shown
    if (typeof showDataSharingConsent === 'function') {
        const consentShown = showDataSharingConsent();
        if (consentShown) {
            return; // Consent modal will handle continuation
        }
    }
    
    // Check if welcome screen should be shown first
    if (typeof showWelcomeScreen === 'function') {
        const welcomeShown = showWelcomeScreen();
        if (welcomeShown) {
            return; // Welcome screen will handle continuation via completeWelcome()
        }
    }
    
    // Apply default theme immediately (before loading settings)
    if (!settings) {
        applyTheme(); // Will use dark as default
    }
    
    if (shouldRenderDashboard()) {
        await loadSettings();
        renderDashboard();
    } else {
        const checkInterval = setInterval(async function() {
            if (shouldRenderDashboard()) {
                clearInterval(checkInterval);
                await loadSettings();
                renderDashboard();
            }
        }, 100);
        
        document.addEventListener('dashboard-ready', async function() {
            clearInterval(checkInterval);
            await loadSettings();
            renderDashboard();
        });
    }
}

// Wait for DOM and config to be ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(initializeDashboardScript, 200);
    });
} else {
    setTimeout(initializeDashboardScript, 200);
}
