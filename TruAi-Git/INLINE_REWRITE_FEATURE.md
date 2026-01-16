# TruAi Phase 2: Inline AI Rewrite Feature

## Overview
This document describes the Inline AI Rewrite feature implementation for the TruAi HTML IDE, following the governance constraints specified in the Phase 2 requirements.

## Feature Summary
The Inline AI Rewrite feature enables users to select code in the editor and use AI to rewrite it according to their instructions, with a manual approval workflow via a diff preview.

## Implementation Details

### 1. Forensic ID Generation
Client-side forensic ID generation with format: `TRUAI_<timestamp>_<hash>`

**Location:** `assets/js/dashboard.js`
```javascript
function generateForensicId() {
    const timestamp = Date.now();
    const randomPart = Math.random().toString(36).substring(2, 15);
    const hash = btoa(timestamp + randomPart).substring(0, 16).replace(/[^a-zA-Z0-9]/g, '');
    return `TRUAI_${timestamp}_${hash}`;
}
```

### 2. Selection Detection
Uses `selectionStart` and `selectionEnd` properties of the textarea editor to determine selected text.

**Location:** `assets/js/dashboard.js`
```javascript
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
```

### 3. Trigger Methods

#### a) Toolbar Button
- **Location:** Above the code editor
- **Visual:** Button with AI icon and "AI Rewrite" text
- **Behavior:** Opens inline rewrite modal when clicked

#### b) Keyboard Shortcut
- **Shortcut:** Cmd/Ctrl + Enter
- **Condition:** Only works when editor is focused and text is selected
- **Behavior:** Opens inline rewrite modal

#### c) Context Menu
- **Trigger:** Right-click on selected text in editor
- **Menu Item:** "AI Rewrite Selection" with AI icon
- **Behavior:** Opens inline rewrite modal

### 4. Inline Rewrite Flow

#### Step 1: User Selection
User selects code in the editor using any method (mouse drag, keyboard selection, etc.)

#### Step 2: Trigger Rewrite
User triggers rewrite via:
- Clicking "AI Rewrite" toolbar button
- Pressing Cmd/Ctrl+Enter
- Right-clicking and selecting "AI Rewrite Selection" from context menu

#### Step 3: Instruction Modal
Modal displays:
- Preview of selected code (up to 200 chars)
- Character count
- Text area for entering rewrite instructions
- Cancel and "Generate Rewrite" buttons

#### Step 4: AI Processing
When user clicks "Generate Rewrite":
1. Validates instruction is provided
2. Generates forensic ID
3. Constructs message with selected code and instruction
4. Sends to TruAi Core via `/api/v1/chat/message` with metadata:
   - `intent: "inline_rewrite"`
   - `scope: "selection"`
   - `risk: "SAFE"`
   - `forensic_id: <generated_id>`
   - `selection_length: <character_count>`

#### Step 5: Diff Preview
Upon receiving AI response:
1. Cleans response (removes markdown code blocks if present)
2. Shows side-by-side diff preview modal with:
   - Original code on left (red tint background)
   - Rewritten code on right (green tint background)
   - Rewrite instruction displayed at top
   - Forensic ID displayed for tracking
   - "Reject" and "Apply Changes" buttons

#### Step 6: Manual Approval
User must manually decide:
- **Reject:** Closes modal, no changes made
- **Apply Changes:** Replaces only the selected range in editor with rewritten code

### 5. API Integration

#### Updated API Client
**Location:** `assets/js/api.js`

Extended `sendMessage` method to support metadata:
```javascript
async sendMessage(message, conversationId = null, model = 'auto', metadata = null) {
    const body = { 
        message, 
        conversation_id: conversationId, 
        model 
    };
    
    // Add metadata if provided
    if (metadata) {
        body.metadata = metadata;
    }
    
    return this.request('/chat/message', {
        method: 'POST',
        body: body
    });
}
```

#### Backend Compatibility
The backend router already supports the `/api/v1/chat/message` endpoint. The metadata is sent but not yet explicitly processed by the backend - this is acceptable as it's forward-compatible for future Phase 1 metadata logging enhancements.

### 6. UI Components

#### Editor Toolbar
**Location:** `assets/js/dashboard.js` - `renderEditorContent()`
```html
<div class="editor-toolbar">
    <button class="editor-toolbar-btn" id="aiRewriteBtn" title="AI Rewrite Selection (Cmd/Ctrl+Enter)">
        <svg>...</svg>
        AI Rewrite
    </button>
</div>
```

#### Inline Rewrite Modal
- Dark theme matching IDE
- Semi-transparent backdrop
- Centered positioning
- Code preview with monospace font
- Responsive design

#### Diff Preview Modal
- Side-by-side column layout
- Color-coded backgrounds (red for original, green for rewritten)
- Scrollable code areas
- Maximum 90vh height to fit viewport

#### Context Menu
- Dark theme
- Positioned at mouse cursor
- Auto-closes on click outside
- Icon + text for menu item

#### CSS Styles
**Location:** `assets/css/inline-rewrite.css`
- Complete styling for all UI components
- Responsive breakpoints for mobile
- Animation for notifications
- Hover states and transitions

### 7. Governance Compliance

✅ **All AI actions route through TruAi Core via existing unified endpoints**
- Uses `/api/v1/chat/message` - no new endpoints created

✅ **Manual-only AI triggers**
- No background polling
- No silent execution
- User must explicitly trigger via button, keyboard shortcut, or context menu

✅ **Cost efficient: selection-scoped requests**
- Only selected text is sent
- No whole-project ingestion
- User provides specific scope via selection

✅ **Forbidden features NOT implemented**
- No LSP
- No AST engines
- No symbol graph
- No background token prediction
- No frameworks added

✅ **Copilot is subordinate executor**
- Feature is part of TruAi IDE
- No external brand exposure
- Routing logic abstracted from user

✅ **Forensic watermark markers**
- Forensic ID generated and sent with each request
- Displayed in diff preview for tracking
- Never stripped from responses

## Files Changed

### New Files
1. `TruAi-Git/assets/css/inline-rewrite.css` - Complete styling for feature
2. `TruAi-Git/INLINE_REWRITE_FEATURE.md` - This documentation

### Modified Files
1. `TruAi-Git/assets/js/dashboard.js`
   - Added forensic ID generation
   - Added selection detection
   - Added inline rewrite modal logic
   - Added diff preview modal logic
   - Added keyboard shortcut handler
   - Added context menu support
   - Added notification system
   - Modified `renderEditorContent()` to include toolbar

2. `TruAi-Git/assets/js/api.js`
   - Extended `sendMessage()` to support metadata parameter

3. `TruAi-Git/index.php`
   - Added `inline-rewrite.css` stylesheet link

## Testing

### Manual Testing Completed
✅ Toolbar button displays correctly
✅ Selection detection works
✅ No-selection notification appears
✅ Inline rewrite modal opens with code preview
✅ Instruction input works
✅ Cancel closes modal without changes
✅ Keyboard shortcut (Cmd/Ctrl+Enter) triggers modal
✅ Context menu appears on right-click with selection
✅ Modal prevents duplicates

### End-to-End Flow
1. User creates/opens file with code
2. User selects code snippet
3. User triggers rewrite (button/keyboard/context menu)
4. Modal shows with code preview
5. User enters instruction
6. API call sends to TruAi Core with metadata
7. Diff preview shows results
8. User can reject or apply changes
9. Only selected range is replaced on apply

### Edge Cases Handled
- No selection: Shows notification
- Empty instruction: Shows warning
- Selection lost between trigger and execution: Shows error
- Duplicate modal prevention
- Modal cleanup on close

## Future Enhancements (Not in Scope)

The following are explicitly NOT implemented per governance constraints:

1. ❌ Auto-apply without user approval
2. ❌ Background suggestion generation
3. ❌ LSP integration for context awareness
4. ❌ AST parsing for code understanding
5. ❌ Symbol graph for project-wide changes
6. ❌ Predictive typing/completion
7. ❌ Automatic whole-file analysis

## Backend Enhancement (Optional)

While the current implementation is complete and functional, the backend could be enhanced to:

1. Log metadata to database for audit trails
2. Use forensic_id for tracking request lineage
3. Apply risk-based rate limiting based on metadata.risk
4. Filter or validate based on metadata.intent

These enhancements are NOT required for Phase 2 functionality but would provide better observability and governance.

## Screenshots

1. **Dashboard with AI Rewrite Button**
   ![Dashboard](https://github.com/user-attachments/assets/b95ea538-9a6f-4505-b3a1-895332dd2983)

2. **Inline Rewrite Modal**
   ![Modal](https://github.com/user-attachments/assets/4748073d-67bf-4572-8528-9b4518a63f36)

3. **Modal with Instruction**
   ![Instruction](https://github.com/user-attachments/assets/0c1facb7-1368-4fd9-9e73-706bcfe38203)

4. **Context Menu**
   ![Context Menu](https://github.com/user-attachments/assets/7ff57939-f7e6-4524-bae2-aee2ac8c5e2c)

## Conclusion

The Phase 2 Inline AI Rewrite feature has been successfully implemented following all governance constraints. The feature provides a user-friendly, manual-approval workflow for AI-assisted code rewriting with proper forensic tracking and cost-efficient selection-scoped requests.
