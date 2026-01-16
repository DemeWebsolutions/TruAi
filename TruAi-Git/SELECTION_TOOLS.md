# TruAi Selection-Scoped AI Tools

## Overview

TruAi provides three manual, selection-scoped AI tools for code editing and understanding. All tools require explicit user action, send only selected text to the AI, and follow strict governance constraints.

## Available Tools

### 1. AI Rewrite (Phase 5)
**Purpose:** Transform selected code according to user instructions with manual approval.

**Trigger Methods:**
- **Toolbar Button:** Click "AI Rewrite" button above editor
- **Keyboard Shortcut:** Cmd/Ctrl+Enter (when editor is focused)
- **Context Menu:** Right-click selected text → "AI Rewrite Selection"

**Flow:**
1. Select code in editor (up to 4000 characters)
2. Trigger the tool via button, keyboard, or context menu
3. Enter rewrite instructions (optional prompt chips available)
4. Review side-by-side diff preview
5. Manually Apply or Reject changes

**Metadata Sent:**
```json
{
  "intent": "inline_rewrite",
  "scope": "selection",
  "risk": "SAFE",
  "forensic_id": "TRUAI_<timestamp>_<hash>",
  "selection_length": <character_count>
}
```

**Features:**
- Diff preview with original vs. rewritten code
- Forensic ID tracking and copy functionality
- Line wrapping toggle
- Copy rewritten text and diff patch
- Session-only instruction preservation
- Stale detection (warns if editor content changed)

---

### 2. Explain Selection (Phase 7)
**Purpose:** Get a clear, read-only explanation of selected code without modifying it.

**Trigger Methods:**
- **Toolbar Dropdown:** Click dropdown arrow → "Explain Selection"
- **Keyboard Shortcut:** Cmd/Ctrl+Shift+Enter (when editor is focused)
- **Context Menu:** Right-click selected text → "Explain Selection"

**Flow:**
1. Select code in editor (up to 4000 characters)
2. Trigger the tool via dropdown, keyboard, or context menu
3. Optionally add specific questions or context
4. View explanation in read-only modal
5. Copy explanation to clipboard if needed

**Metadata Sent:**
```json
{
  "intent": "explain_selection",
  "scope": "selection",
  "risk": "SAFE",
  "forensic_id": "TRUAI_<timestamp>_<hash>",
  "selection_length": <character_count>
}
```

**Features:**
- Read-only explanation display
- No code modifications
- Forensic ID tracking and copy functionality
- Copy explanation button
- Optional additional questions/context

---

### 3. Add Comments (Phase 7)
**Purpose:** Generate commented/documented version of selected code with manual approval.

**Trigger Methods:**
- **Toolbar Dropdown:** Click dropdown arrow → "Add Comments"
- **Context Menu:** Right-click selected text → "Add Comments"

**Flow:**
1. Select code in editor (up to 4000 characters)
2. Trigger the tool via dropdown or context menu
3. Optionally specify comment style (e.g., "JSDoc style")
4. Review side-by-side diff preview
5. Manually Apply or Reject changes

**Metadata Sent:**
```json
{
  "intent": "add_comments",
  "scope": "selection",
  "risk": "SAFE",
  "forensic_id": "TRUAI_<timestamp>_<hash>",
  "selection_length": <character_count>
}
```

**Features:**
- Same diff preview as AI Rewrite
- Preserves all code functionality
- Only adds comments/docstrings
- Manual apply/reject workflow
- Copy commented code and diff patch

---

## UI Components

### Toolbar Layout
```
┌────────────────────┬────┐
│   AI Rewrite       │ ▼  │
└────────────────────┴────┘
        │
        └─── Dropdown Menu:
             ├─ Explain Selection
             └─ Add Comments
```

### Context Menu
When text is selected and right-clicked:
```
┌───────────────────────┐
│ ⚡ AI Rewrite Selection│
│ ❓ Explain Selection   │
│ 💬 Add Comments        │
└───────────────────────┘
```

---

## Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| Cmd/Ctrl+Enter | AI Rewrite |
| Cmd/Ctrl+Shift+Enter | Explain Selection |

---

## Guardrails & Safety

### Selection Requirements
- **All tools require an active selection**
- Empty selection: Button disabled, toast shown
- Maximum size: 4000 characters
- Oversized selection: Blocked with helpful message

### Manual-Only Triggers
- ✅ No automatic suggestions
- ✅ No background polling
- ✅ No silent execution
- ✅ User must explicitly trigger each action

### Selection-Scoped Requests
- ✅ Only selected text is sent
- ✅ No whole-file analysis
- ✅ No project-wide ingestion
- ✅ Cost-efficient, targeted requests

### Forensic Tracking
- Every request generates a unique forensic ID
- Format: `TRUAI_<timestamp>_<hash>`
- Displayed in results with copy functionality
- Never stripped from responses

### No Auto-Apply
- AI Rewrite: Manual Apply/Reject required
- Add Comments: Manual Apply/Reject required
- Explain Selection: Read-only, no changes possible

---

## Governance Compliance

### ✅ Constraints Satisfied

1. **No New Endpoints**
   - All tools use existing `/api/v1/chat/message` endpoint
   - No additional API routes created

2. **No Frameworks/Libraries**
   - Pure JavaScript implementation
   - No external dependencies added

3. **Manual-Only Triggers**
   - No automation or background execution
   - Explicit user action required for every operation

4. **Selection-Scoped Only**
   - Maximum 4000 character limit enforced
   - No whole-file or project-wide analysis

5. **Model Routing Hidden**
   - Internal model selection not exposed to users
   - Routing handled by TruAi Core backend

6. **Never Auto-Apply**
   - All code changes require manual approval
   - Explain tool is read-only by design

---

## Implementation Details

### Files Modified

**JavaScript:**
- `TruAi-Git/assets/js/dashboard.js`
  - Added `showExplainSelectionPrompt()` function
  - Added `executeExplainSelection()` function
  - Added `showExplanationModal()` function
  - Added `showAddCommentsPrompt()` function
  - Added `executeAddComments()` function
  - Updated toolbar rendering with dropdown
  - Added dropdown event listeners
  - Updated context menu with new options
  - Added keyboard shortcut for Explain (Cmd/Ctrl+Shift+Enter)

**CSS:**
- `TruAi-Git/assets/css/inline-rewrite.css`
  - Added `.ai-tools-group` for split-button layout
  - Added `.editor-toolbar-btn-dropdown` for dropdown button
  - Added `.ai-tools-dropdown` for dropdown menu
  - Added `.ai-tool-option` for menu items
  - Added `.explanation-content` for explanation modal
  - Updated `.context-menu-item` styling

### API Integration

All tools use the same unified API endpoint:
```javascript
api.sendMessage(message, conversationId, model, metadata)
```

Endpoint: `POST /api/v1/chat/message`

Metadata structure:
```javascript
{
  intent: 'inline_rewrite' | 'explain_selection' | 'add_comments',
  scope: 'selection',
  risk: 'SAFE',
  forensic_id: 'TRUAI_<timestamp>_<hash>',
  selection_length: <number>
}
```

---

## Testing

### Manual Testing Checklist

**UI Verification:**
- [ ] Toolbar displays split-button with dropdown
- [ ] Dropdown opens on click
- [ ] Dropdown shows Explain and Add Comments options
- [ ] Context menu shows all three tools
- [ ] All buttons have correct icons

**Explain Selection:**
- [ ] Requires selection (button disabled otherwise)
- [ ] Shows prompt modal with optional instruction
- [ ] Sends request with correct metadata
- [ ] Displays explanation in read-only modal
- [ ] Copy button works
- [ ] Forensic ID displayed and copyable
- [ ] Keyboard shortcut (Cmd/Ctrl+Shift+Enter) works

**Add Comments:**
- [ ] Requires selection (shows toast otherwise)
- [ ] Shows prompt modal with optional style input
- [ ] Sends request with correct metadata
- [ ] Displays diff preview with original and commented code
- [ ] Apply/Reject buttons work correctly
- [ ] Forensic ID displayed and copyable

**Guardrails:**
- [ ] Empty selection: Buttons disabled, toast shown
- [ ] Oversized selection (>4000 chars): Blocked with message
- [ ] Selection lost during operation: Shows error

**Keyboard Shortcuts:**
- [ ] Cmd/Ctrl+Enter triggers AI Rewrite
- [ ] Cmd/Ctrl+Shift+Enter triggers Explain Selection
- [ ] Works only when editor is focused

---

## Future Enhancements (Not in Scope)

The following are explicitly NOT implemented per governance constraints:

1. ❌ Auto-apply without user approval
2. ❌ Background suggestion generation
3. ❌ Predictive typing/completion
4. ❌ Automatic whole-file analysis
5. ❌ LSP integration
6. ❌ AST parsing
7. ❌ Symbol graph

---

## Conclusion

Phase 7 successfully adds two new manual, selection-scoped AI tools to TruAi:
1. **Explain Selection** - Read-only code explanation
2. **Add Comments** - Commented code generation with manual approval

Both tools maintain strict governance compliance with no new endpoints, no frameworks, manual-only triggers, and selection-scoped requests. All model routing identifiers remain hidden from users, and no changes are ever auto-applied.

---

**Last Updated:** 2026-01-16  
**Version:** Phase 7  
**Status:** Complete
