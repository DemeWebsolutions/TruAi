# TruAi V1 Operator Guide

## Overview

TruAi provides three manual, selection-scoped AI tools for code editing and understanding. All operations require explicit user action, send only selected text to the AI, and follow strict governance constraints with no automatic suggestions or background processes.

**Version:** 1.0.0  
**Last Updated:** 2026-01-16

---

## Table of Contents

1. [Selection-Scoped AI Tools](#selection-scoped-ai-tools)
2. [Accessibility & Keyboard Navigation](#accessibility--keyboard-navigation)
3. [Guardrails & Safety](#guardrails--safety)
4. [Privacy Controls](#privacy-controls)
5. [Development Testing](#development-testing)
6. [Governance Compliance](#governance-compliance)

---

## Selection-Scoped AI Tools

### 1. AI Rewrite

**Purpose:** Transform selected code according to user instructions with manual approval before applying changes.

**How to Use:**
- **Toolbar Button:** Click "AI Rewrite" button above editor
- **Keyboard Shortcut:** `Cmd/Ctrl+Enter` (when editor is focused)
- **Context Menu:** Right-click selected text → "AI Rewrite Selection"

**Workflow:**
1. Select code in editor (up to 4000 characters)
2. Trigger the tool via button, keyboard shortcut, or context menu
3. Enter rewrite instructions (optional prompt chips available)
4. Review side-by-side diff preview showing original vs. rewritten code
5. **Manually** click "Apply" or "Reject" - no automatic application

**Features:**
- **Diff Preview:** Side-by-side comparison with original and rewritten code
- **Forensic ID:** Unique tracking identifier (format: `TRUAI_<timestamp>_<hash>`)
- **Line Wrapping Toggle:** Toggle line wrap in diff view
- **Copy Actions:** Copy rewritten text or unified diff patch to clipboard
- **Session Instruction Memory:** Last instruction preserved within session only
- **Stale Detection:** Warns if editor content changed since rewrite was generated

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

---

### 2. Explain Selection

**Purpose:** Get a clear, read-only explanation of selected code without modifying it.

**How to Use:**
- **Toolbar Dropdown:** Click dropdown arrow next to "AI Rewrite" → "Explain Selection"
- **Keyboard Shortcut:** `Cmd/Ctrl+Shift+Enter` (when editor is focused)
- **Context Menu:** Right-click selected text → "Explain Selection"

**Workflow:**
1. Select code in editor (up to 4000 characters)
2. Trigger the tool via dropdown, keyboard shortcut, or context menu
3. Optionally add specific questions or context
4. View explanation in read-only modal
5. Copy explanation to clipboard if needed

**Features:**
- **Read-only Display:** No code modifications possible
- **Forensic ID:** Unique tracking identifier displayed and copyable
- **Optional Context:** Add specific questions or focus areas
- **Copy Function:** Copy full explanation to clipboard

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

---

### 3. Add Comments

**Purpose:** Generate commented/documented version of selected code with manual approval before applying.

**How to Use:**
- **Toolbar Dropdown:** Click dropdown arrow next to "AI Rewrite" → "Add Comments"
- **Context Menu:** Right-click selected text → "Add Comments"

**Workflow:**
1. Select code in editor (up to 4000 characters)
2. Trigger the tool via dropdown or context menu
3. Optionally specify comment style (e.g., "JSDoc style", "focus on complex logic")
4. Review side-by-side diff preview showing original vs. commented code
5. **Manually** click "Apply" or "Reject"

**Features:**
- **Same Diff Preview** as AI Rewrite
- **Preserves Functionality:** Only adds comments/docstrings, no code changes
- **Manual Approval Required:** Apply/Reject workflow
- **Copy Actions:** Copy commented code or diff patch

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

---

## Accessibility & Keyboard Navigation

TruAi is designed to be fully accessible via keyboard navigation.

### Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `Cmd/Ctrl+Enter` | AI Rewrite (when editor focused) |
| `Cmd/Ctrl+Shift+Enter` | Explain Selection (when editor focused) |
| `Escape` | Close any open modal or dropdown |
| `Tab` / `Shift+Tab` | Navigate within modal (focus trap active) |
| `Enter` | Activate focused button or menu item |

### Modal Accessibility

All modals (rewrite prompt, diff preview, explanation, add comments) include:

- **ARIA Attributes:** `role="dialog"`, `aria-modal="true"`, `aria-labelledby`
- **Focus Trap:** Tab key cycles within modal, cannot escape to background
- **Escape Key:** Closes modal and returns focus to triggering element
- **Focus Restoration:** Focus returns to the element that opened the modal
- **Keyboard-Only Navigation:** All actions accessible without mouse

### Dropdown Menu Navigation

**AI Tools Dropdown** (next to AI Rewrite button):
- Click dropdown arrow or press `Enter` when focused
- `Arrow Up/Down`: Navigate menu options
- `Enter`: Select option
- `Escape`: Close dropdown

### Context Menu Navigation

**Editor Context Menu** (right-click on selection):
- Right-click or keyboard shortcut to open
- `Arrow Up/Down`: Navigate menu items
- `Enter`: Select menu item
- `Escape`: Close menu

### Focus-Visible Indicators

All interactive elements display visible focus indicators when navigated via keyboard:
- **Blue outline** (2px solid) with 2px offset
- Applies to: buttons, inputs, checkboxes, menu items, close buttons

---

## Guardrails & Safety

### Selection Requirements

- **All tools require an active selection** - buttons disabled when no text selected
- **Maximum Size:** 4000 characters
- **Oversized Selection:** Tool shows notification with character count and limit
- **Empty Selection:** Shows toast notification: "Select text to [action]"

### Manual-Only Triggers

✅ **No automatic suggestions**  
✅ **No background polling**  
✅ **No silent execution**  
✅ **User must explicitly trigger each action**

### Selection-Scoped Requests

✅ **Only selected text is sent** - no whole-file analysis  
✅ **No project-wide ingestion**  
✅ **Cost-efficient, targeted requests**  
✅ **Maximum 4000 characters enforced**

### Forensic Tracking

Every AI operation generates a unique forensic ID:
- **Format:** `TRUAI_<timestamp>_<hash>`
- **Display:** Shown prominently in all result modals
- **Copyable:** Click-to-copy functionality with success notification
- **Immutable:** Never stripped from responses

### No Auto-Apply

- **AI Rewrite:** Manual Apply/Reject required
- **Add Comments:** Manual Apply/Reject required
- **Explain Selection:** Read-only, no changes possible
- **Enter Key Safety:** Pressing Enter does not apply unless Apply button is explicitly focused

### Stale Detection (AI Rewrite & Add Comments)

Before applying changes, the system checks:
- **Content Changed:** Warns if editor content modified since rewrite generated
- **Selection Changed:** Warns if selected text itself was modified
- **Character Count Check:** Compares original and current editor length
- **User Choice:** Option to proceed or cancel with clear warnings

### Request Management

- **Timeout:** Requests timeout after 30 seconds
- **Cancellation:** User can cancel in-progress requests
- **Duplicate Prevention:** Blocks multiple simultaneous rewrite requests
- **Error Handling:** Graceful handling of network failures and malformed responses

---

## Privacy Controls

TruAi provides a privacy toggle in Settings:

**Location:** Settings → Privacy → Data Sharing

**Options:**
- **ON:** Enable analytics and telemetry (default)
- **OFF:** Disable all non-essential data collection

**Persistence:** Settings saved to database and persist across sessions

**Privacy Defaults:**
- No PII in logs
- No secrets in logs
- API keys never logged
- Model identifiers not exposed in production UI
- Metadata allowlist enforced (only governed keys forwarded)

---

## Development Testing

### Dev Test Harness

A lightweight test harness is available for development:

**Location:** `TruAi-Git/dev/ai-api-harness.html`

**Access:**
```bash
cd TruAi-Git
./start.sh
# Open in browser: http://localhost:8000/dev/ai-api-harness.html
```

**Features:**
- Message input with conversation ID tracking
- Model selection (internal use only)
- Metadata field testing
- Payload preview before sending
- Response display with timing

**⚠️ DEV ONLY:** This harness is marked "DEV ONLY" and should NOT be deployed to production.

### Manual Test Procedures

For comprehensive testing procedures, see: [`DEV_TESTING.md`](./DEV_TESTING.md)

**Key Test Areas:**
- Empty selection handling
- Oversized selection blocking
- Prompt chips functionality
- Forensic ID display and copy
- Line wrapping toggle
- Keyboard accessibility (Escape, Enter, Tab)
- Diff preview Apply/Reject workflow
- Stale detection warnings
- Privacy toggle persistence
- Dropdown and context menu keyboard navigation

---

## Governance Compliance

### ✅ Constraints Satisfied

#### 1. No New Endpoints
- All tools use existing `/api/v1/chat/message` endpoint
- No additional API routes created
- Unified TruAi Core integration

#### 2. No Frameworks/Libraries
- Pure JavaScript implementation
- No external dependencies added
- Vanilla JS for all functionality

#### 3. Manual-Only Triggers
- No automation or background execution
- Explicit user action required for every operation
- No predictive typing or auto-completion

#### 4. Selection-Scoped Only
- Maximum 4000 character limit enforced
- No whole-file or project-wide analysis
- No LSP integration, AST parsing, or symbol graphs

#### 5. Model Routing Hidden
- Internal model selection not exposed to users
- Routing handled by TruAi Core backend
- Dev harness only for internal testing

#### 6. Never Auto-Apply
- All code changes require manual approval
- Explain tool is read-only by design
- Enter key does not accidentally apply changes

### Allowlisted Metadata Keys

Only the following keys are forwarded to the API:

- `intent` - Request intent/purpose
- `risk` - Risk level classification
- `forensic_id` - Tracking identifier
- `scope` - Operation scope
- `selection_length` - Selection character count
- `conversation_id` - Conversation override (if needed)
- `model` - Model routing (internal/dev use only)

**Any other keys are ignored and not forwarded.**

---

## Backward Compatibility

All existing API callers remain compatible:

```javascript
// Old usage - still works
api.sendMessage('Hello', null, 'auto');

// New usage with metadata
api.sendMessage('Hello', null, 'auto', {
  intent: 'general_chat',
  risk: 'SAFE',
  forensic_id: 'TRUAI_123_abc'
});
```

---

## Security Considerations

- ✅ **No PII in Logs:** Error handling sanitized
- ✅ **No Secrets in Logs:** API keys, tokens never logged
- ✅ **Model Identifiers:** Not exposed in production UI
- ✅ **Metadata Allowlist:** Only governed keys forwarded
- ✅ **Dev Harness:** Clearly marked "DEV ONLY"
- ✅ **XSS Protection:** All user input escaped with `escapeHtml()`
- ✅ **CSRF Protection:** Session-based authentication
- ✅ **No SQL Injection:** Prepared statements used throughout

---

## Troubleshooting

### Modal Won't Open
- **Check:** Is text selected in editor?
- **Check:** Is selection under 4000 characters?
- **Solution:** Select valid text and try again

### Keyboard Shortcuts Not Working
- **Check:** Is editor focused?
- **Solution:** Click in editor to focus, then use shortcut

### Changes Not Applying
- **Check:** Did you click "Apply Changes" button?
- **Check:** Did you see stale detection warning?
- **Solution:** Review diff carefully and click Apply, or re-run rewrite

### Forensic ID Copy Failed
- **Check:** Browser clipboard permissions
- **Solution:** Use fallback method or manually select and copy

### Dropdown Not Opening
- **Check:** Browser console for errors
- **Solution:** Refresh page and try again

---

## Future Enhancements (Not in Scope)

The following are explicitly **NOT** implemented per governance constraints:

1. ❌ Auto-apply without user approval
2. ❌ Background suggestion generation
3. ❌ Predictive typing/completion
4. ❌ Automatic whole-file analysis
5. ❌ LSP integration
6. ❌ AST parsing
7. ❌ Symbol graph

---

## Support & Feedback

For issues, questions, or feedback:

1. Review this operator guide and [`DEV_TESTING.md`](./DEV_TESTING.md)
2. Check browser console for errors (sanitized)
3. Document steps to reproduce
4. Capture network requests/responses (sanitized)
5. Report to development team with forensic ID if available

---

## Version History

- **v1.0.0** (2026-01-16): Initial operator guide consolidation
  - Phase 8: Accessibility, CSS, and documentation cleanup
  - Consolidated from SELECTION_TOOLS.md, INLINE_REWRITE_FEATURE.md, and DEV_TESTING.md
  - Added comprehensive keyboard navigation documentation
  - Added accessibility features documentation
  - Added guardrails and safety documentation

---

**End of Operator Guide**
