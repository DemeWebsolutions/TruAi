# TruAi Development Testing Guide

## Overview

This document describes manual test procedures for validating TruAi's governed AI API integration. These tests ensure that the unified TruAi Core integration behaves correctly with metadata and that governance constraints are properly enforced.

## Prerequisites

- TruAi application running locally
- Valid API keys configured in settings
- Admin access to the TruAi dashboard

## Dev Test Harness

A lightweight, non-framework test harness is available for development testing:

**Location:** `TruAi-Git/dev/ai-api-harness.html`

### Access the Test Harness

1. Start the TruAi application:
   ```bash
   cd TruAi-Git
   ./start.sh
   ```

2. Open the test harness in your browser:
   ```
   http://localhost:8000/dev/ai-api-harness.html
   ```

3. The page displays a prominent "DEV ONLY" warning banner.

### Test Harness Features

- **Message Input:** Send test messages to the `/chat/message` API
- **Conversation ID:** Optional conversation tracking
- **Model Selection:** Internal model routing (NOT exposed in production UI)
- **Metadata Fields:** Test governed metadata passthrough:
  - `intent` - Purpose of the request (e.g., "inline_rewrite", "general_chat")
  - `risk` - Risk level (SAFE, LOW, MEDIUM, HIGH)
  - `forensic_id` - Tracking identifier
  - `scope` - Operation scope (e.g., "selection", "document")
  - `selection_length` - Character count for inline rewrite scenarios
- **Payload Preview:** View the exact JSON payload before sending
- **Response Display:** See the API response and timing information

## Manual Test Procedures

### Test 1: Basic Message Send (No Metadata)

**Purpose:** Verify backward compatibility for existing callers.

**Steps:**
1. Open the test harness
2. Enter a simple message: "What is the capital of France?"
3. Leave all metadata fields empty
4. Click "Send Message"

**Expected Results:**
- Payload preview shows only: `message`, `conversation_id`, and `model`
- Request succeeds (200 OK)
- Response contains AI-generated answer
- No metadata fields in payload

### Test 2: Metadata Passthrough (Inline Rewrite)

**Purpose:** Validate that metadata is correctly passed through to the API.

**Steps:**
1. Open the test harness
2. Enter message: "Rewrite this code to add error handling"
3. Set metadata:
   - Intent: `inline_rewrite`
   - Risk: `SAFE`
   - Scope: `selection`
   - Selection Length: `150`
   - Forensic ID: Leave empty or use format `TRUAI_1234567890_abc123`
4. Click "Send Message"

**Expected Results:**
- Payload preview shows message + all metadata fields
- Request succeeds
- Response contains AI-generated rewrite
- Timing information displayed

### Test 3: Conversation Continuity

**Purpose:** Verify conversation tracking works with metadata.

**Steps:**
1. Send initial message: "Hello, what is JavaScript?"
2. Note the `conversation_id` from the response
3. Clear the form
4. Enter new message: "Tell me more about its history"
5. Paste the conversation_id from step 2
6. Add metadata:
   - Intent: `general_chat`
   - Risk: `SAFE`
7. Click "Send Message"

**Expected Results:**
- Second message uses the same conversation_id
- Response is contextually aware of the first message
- Conversation history maintained

### Test 4: Model Routing Internal Use Only

**Purpose:** Confirm model identifiers remain internal and are not exposed in production UI.

**Steps:**
1. Open the test harness (dev environment only)
2. Notice model selection dropdown is visible
3. Open main TruAi dashboard (`index.php`)
4. Open browser dev tools and search for "gpt-4", "gpt-3.5-turbo", or "claude"

**Expected Results:**
- Model selector visible in dev harness
- Model identifiers NOT visible in production dashboard HTML/JavaScript
- Settings may contain model selection but labeled generically (e.g., "Advanced Model", "Standard Model")

### Test 5: Error Handling and Logging Safety

**Purpose:** Ensure error messages do not leak PII or secrets.

**Steps:**
1. Open browser console (F12)
2. Open test harness
3. Send message with invalid/empty content
4. Check console logs
5. Send message with very long text (>10,000 characters)
6. Check console logs

**Expected Results:**
- Console logs show sanitized error messages
- No request/response bodies logged in console
- No API keys or sensitive data in logs
- User-friendly error messages displayed in UI
- Timing information still available

## Phase-Specific Tests

### Phase 2: Selection Rewrite Flow

**Purpose:** Validate inline AI rewrite functionality.

**Steps:**
1. Open TruAi dashboard and log in
2. Open a code file in the editor
3. Select a block of code (at least 50 characters)
4. Click "AI Rewrite" button or press Cmd/Ctrl+Enter
5. Enter instruction: "Add comments explaining each line"
6. Click "Generate Rewrite"
7. Review diff preview showing original vs. rewritten
8. Check for forensic_id in the preview
9. Click "Accept" or "Reject"

**Expected Results:**
- Selection detected correctly
- Forensic ID auto-generated
- Metadata includes: `intent=inline_rewrite`, `scope=selection`, `risk=SAFE`, `selection_length=<count>`
- Diff preview displays clearly
- Accept applies changes, Reject discards

### Phase 5: Polish & UX Tightening Tests

#### Test 5.1: Empty Selection Handling

**Purpose:** Verify that empty selection is properly handled with UI feedback.

**Steps:**
1. Open TruAi dashboard and log in
2. Open a code file in the editor
3. Click anywhere in the editor WITHOUT selecting text
4. Observe the "AI Rewrite" button state
5. Try clicking the "AI Rewrite" button
6. Press Cmd/Ctrl+Enter

**Expected Results:**
- "AI Rewrite" button is visually disabled (grayed out)
- Button cannot be clicked when disabled
- Keyboard shortcut shows toast: "Select text to rewrite"
- No modal opens for empty selection

#### Test 5.2: Maximum Selection Size Guard

**Purpose:** Verify that oversized selections are blocked with helpful feedback.

**Steps:**
1. Open TruAi dashboard and log in
2. Open or create a file with > 4000 characters of code
3. Select all text (Cmd/Ctrl+A)
4. Click "AI Rewrite" button or press Cmd/Ctrl+Enter
5. Observe the notification message

**Expected Results:**
- Toast notification appears: "Selection too large (X chars). Please reduce to 4000 chars or less."
- Modal does NOT open
- No API request sent
- User can select smaller portion and try again

#### Test 5.3: Suggested Prompt Chips

**Purpose:** Verify that prompt chips appear and function correctly.

**Steps:**
1. Open TruAi dashboard and log in
2. Open a code file and select some text
3. Click "AI Rewrite" button
4. Observe the prompt chips above the instruction textarea
5. Click on "Refactor" chip
6. Observe the instruction field
7. Click on "Add comments" chip
8. Observe the instruction field

**Expected Results:**
- Six prompt chips displayed: "Refactor", "Fix bug", "Improve readability", "Add comments", "Optimize performance", "Add error handling"
- Clicking first chip inserts text into instruction field
- Clicking second chip appends with comma separator: "Refactor, Add comments"
- Chips are clickable and responsive
- Manual text can still be entered

#### Test 5.4: Session Instruction Preservation

**Purpose:** Verify that last instruction is preserved within session.

**Steps:**
1. Open TruAi dashboard and log in
2. Open a code file and select text
3. Click "AI Rewrite" button
4. Enter instruction: "Test instruction 123"
5. Click "Cancel" to close modal
6. Select different text
7. Click "AI Rewrite" button again
8. Observe the instruction field

**Expected Results:**
- Instruction field is pre-filled with "Test instruction 123"
- Cursor positioned at end of text
- User can edit or replace text
- Instruction persists across modal opens in same session
- Instruction is NOT saved to localStorage (refresh clears it)

#### Test 5.5: Forensic ID Display and Copy

**Purpose:** Verify forensic ID is displayed and copyable in diff preview.

**Steps:**
1. Complete a rewrite operation to reach diff preview
2. Locate the "Forensic ID" section
3. Observe the styling and display
4. Click the copy button next to forensic ID
5. Paste into a text editor (Cmd/Ctrl+V)

**Expected Results:**
- Forensic ID displayed in dedicated banner with label "Forensic ID:"
- ID shown in monospace font within styled code element
- Copy button visible with icon
- Clicking copy button shows success toast: "Forensic ID copied to clipboard"
- Pasted value matches displayed forensic ID exactly
- Format: TRUAI_<timestamp>_<hash>

#### Test 5.6: Line Wrapping Toggle

**Purpose:** Verify line wrapping can be toggled in diff preview.

**Steps:**
1. Complete a rewrite with code containing long lines (>100 chars)
2. In diff preview, locate "Wrap lines" checkbox
3. Observe initial state (should be checked/wrapped by default)
4. Uncheck the "Wrap lines" checkbox
5. Observe the diff code display
6. Check the "Wrap lines" checkbox again
7. Observe the diff code display

**Expected Results:**
- "Wrap lines" checkbox displayed above diff columns
- Default state: checked, lines wrap within column width
- Unchecked: lines extend horizontally, horizontal scrollbar appears
- Checked again: lines wrap, scrollbar disappears
- Toggle works smoothly without page reload
- Both original and rewritten columns update together

#### Test 5.7: Keyboard Accessibility - Escape

**Purpose:** Verify Escape key closes diff preview modal.

**Steps:**
1. Complete a rewrite to open diff preview
2. Press Escape key
3. Observe the modal behavior

**Expected Results:**
- Diff preview modal closes immediately
- No changes applied to editor
- No error messages
- Editor remains in previous state

#### Test 5.8: Keyboard Accessibility - Enter

**Purpose:** Verify Enter key behavior in diff preview.

**Steps:**
1. Complete a rewrite to open diff preview
2. Press Enter key without focusing any button
3. Observe behavior
4. Close and reopen diff preview
5. Tab to "Apply Changes" button (or click to focus it)
6. Observe focus indicator on button
7. Press Enter key
8. Observe behavior

**Expected Results:**
- Pressing Enter without focus on Apply button: No action (safe)
- Pressing Enter with Apply button focused: Changes applied
- Changes applied successfully to editor
- Notification shows: "Changes applied successfully"
- Modal closes after application

#### Test 5.9: Button State Dynamic Update

**Purpose:** Verify button state updates as selection changes.

**Steps:**
1. Open TruAi dashboard with a code file
2. Observe "AI Rewrite" button (should be disabled)
3. Select some text with mouse
4. Observe button state change
5. Click elsewhere to deselect
6. Observe button state change
7. Select text with keyboard (Shift+Arrow keys)
8. Observe button state change

**Expected Results:**
- Initial state: button disabled
- After selection: button enabled immediately
- After deselection: button disabled immediately
- Keyboard selection: button enabled
- State updates are instant and responsive
- Visual disabled state (grayed out) matches functional state

#### Test 5.10: Dark Theme Compatibility

**Purpose:** Verify all Phase 5 elements work correctly in dark theme.

**Steps:**
1. Ensure dark theme is active (default)
2. Complete a full rewrite flow
3. Observe all UI elements:
   - Disabled button state
   - Prompt chips
   - Forensic ID banner
   - Copy button
   - Line wrap toggle
   - Diff code backgrounds

**Expected Results:**
- All text readable with proper contrast
- Disabled button visible but clearly non-interactive
- Prompt chips have appropriate hover states
- Forensic ID banner distinguishable from surrounding UI
- Copy button visible and styled appropriately
- No white boxes or harsh contrasts
- Color-coded diffs (red/green tints) subtle and readable
- All interactive elements have visible hover states

### Phase 3: Privacy Toggle Persistence

**Purpose:** Verify privacy settings are saved and respected.

**Steps:**
1. Open TruAi dashboard
2. Navigate to Settings → Privacy
3. Toggle "Data Sharing" to OFF
4. Click "Save Settings"
5. Refresh the page
6. Navigate back to Settings → Privacy

**Expected Results:**
- Privacy toggle state persists after refresh
- Settings saved notification displayed
- No telemetry or analytics sent when disabled

### Phase 4: Metadata Allowlist Enforcement

**Purpose:** Ensure only allowlisted metadata keys are forwarded.

**Steps:**
1. Open browser dev tools → Network tab
2. Open test harness
3. In browser console, manually call:
   ```javascript
   const api = new TruAiAPI();
   api.sendMessage('test', null, 'auto', {
     intent: 'test',
     risk: 'SAFE',
     malicious_key: 'should_be_filtered',
     secret: 'should_not_appear'
   });
   ```
4. Inspect the network request payload

**Expected Results:**
- Request payload contains: `intent`, `risk`
- Request payload does NOT contain: `malicious_key`, `secret`
- Only allowlisted keys forwarded to API

## Allowlisted Metadata Keys

The following metadata keys are allowed and will be forwarded to the TruAi Core API:

- `intent` - Request intent/purpose
- `risk` - Risk level classification
- `forensic_id` - Tracking identifier
- `scope` - Operation scope
- `selection_length` - Selection character count
- `conversation_id` - Conversation override (if needed)
- `model` - Model routing (internal/dev use only)

Any other keys in the metadata object will be ignored.

## Backward Compatibility

All existing callers of `sendMessage()` remain compatible:

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

## Excluding Dev Files from Production

The dev test harness should NOT be deployed to production. Options:

1. **Build Process:** Exclude `dev/` directory in production build
2. **Web Server Config:** Deny access to `/dev/` path in production
3. **Git:** Add to `.gitignore` if needed for private dev versions

## Security Considerations

- **No PII in Logs:** Error handling sanitized to prevent logging sensitive data
- **No Secrets in Logs:** API keys, tokens never logged to console
- **Model Identifiers:** Not exposed in production UI
- **Metadata Allowlist:** Only governed keys forwarded to API
- **Dev Harness:** Clearly marked "DEV ONLY" and not for production use

## Reporting Issues

If any test fails or unexpected behavior occurs:

1. Note the test case number and description
2. Capture browser console logs (sanitized)
3. Capture network request/response (sanitized)
4. Document expected vs. actual behavior
5. Report to development team

---

**Last Updated:** 2026-01-16
**Version:** 1.0.0

## Phase 6: Inline Rewrite Reliability & Hardening Tests

### Test 6.1: Timeout Handling

**Purpose:** Verify that long-running rewrite requests timeout gracefully.

**Steps:**
1. Open TruAi dashboard and log in
2. Open a code file and select text
3. Click "AI Rewrite" button
4. Enter instruction and click "Generate Rewrite"
5. If the request takes longer than 30 seconds, observe behavior

**Expected Results:**
- Request times out after exactly 30 seconds
- Toast notification shows: "Request canceled or timed out after 30 seconds"
- Modal closes automatically
- No error in console
- User can retry with a new request

**Note:** To simulate timeout, you may need to temporarily set timeout to 5 seconds for testing, or use a slow/unresponsive API endpoint.

### Test 6.2: Cancel Request

**Purpose:** Verify that users can cancel in-progress rewrite requests.

**Steps:**
1. Open TruAi dashboard and log in
2. Open a code file and select text
3. Click "AI Rewrite" button
4. Enter instruction and click "Generate Rewrite"
5. While request is pending, click "Cancel Request" button
6. Observe behavior

**Expected Results:**
- "Cancel" button changes to "Cancel Request" when request starts
- Clicking "Cancel Request" immediately aborts the fetch
- Toast notification shows: "Rewrite request canceled"
- Modal closes
- No diff preview displayed
- User can start a new request immediately

### Test 6.3: Malformed Response Handling

**Purpose:** Verify graceful handling of unexpected API response formats.

**Steps:**
1. Use dev harness or browser console to test various response shapes:
   ```javascript
   // In browser console after opening dashboard
   const api = new TruAiAPI();
   
   // Test 1: Missing content field
   // Test 2: Empty response
   // Test 3: Response with { reply: "code" }
   // Test 4: Response with { content: "code" }
   // Test 5: Response with { data: { reply: "code" } }
   ```
2. Observe how the system handles each case

**Expected Results:**
- Response parser tries multiple field names: `message.content`, `reply`, `content`, `message`, `data.reply`, `data.content`, `data.message`
- If none found, shows toast: "Unable to parse AI response. Please try again."
- No diff preview opens for unparseable responses
- No JavaScript errors in console
- User can retry

### Test 6.4: Empty Response Handling

**Purpose:** Verify handling of empty or whitespace-only responses.

**Steps:**
1. Complete a rewrite operation that returns empty or whitespace-only content
2. Observe behavior

**Expected Results:**
- Empty responses after cleanup are rejected
- Toast shows: "Unable to parse AI response. Please try again."
- No diff preview opens
- User can retry

### Test 6.5: Stale Selection Detection - Content Changed

**Purpose:** Verify that changes to editor content are detected before applying.

**Steps:**
1. Open a code file with at least 100 characters
2. Select 20-30 characters in the middle
3. Click "AI Rewrite" and enter instruction
4. While request is pending or after diff preview opens:
5. Manually add or remove text elsewhere in the editor (before or after the selection)
6. In diff preview, click "Apply Changes"
7. Observe warning dialog

**Expected Results:**
- Warning dialog appears stating: "Editor content has changed since the rewrite was generated"
- Shows character count difference
- Gives option to proceed or cancel
- If canceled, shows toast: "Apply canceled. Please re-run the rewrite with current content."
- Changes NOT applied if canceled
- If proceeded, changes applied at original positions (may be incorrect)

### Test 6.6: Stale Selection Detection - Selection Changed

**Purpose:** Verify that changes to the selected text itself are detected.

**Steps:**
1. Open a code file
2. Select text containing "function myFunction()"
3. Click "AI Rewrite" and generate rewrite
4. Before clicking Apply in diff preview:
5. Close diff preview (Esc)
6. Edit the selected text to "function yourFunction()"
7. Re-open the same diff preview (if possible) or simulate by keeping diffPreviewData
8. Click "Apply Changes"

**Expected Results:**
- Warning dialog appears: "The selected text has changed since the rewrite was generated"
- Recommends rejecting and re-running
- If user proceeds, applies changes (may be incorrect)
- If user cancels, shows toast and does not apply changes

### Test 6.7: Duplicate Request Prevention

**Purpose:** Verify that multiple simultaneous rewrite requests are prevented.

**Steps:**
1. Open a code file and select text
2. Click "AI Rewrite" button
3. Enter instruction and click "Generate Rewrite"
4. While request is pending, try to start another rewrite:
   - Click "AI Rewrite" button again (if possible)
   - Or press Cmd/Ctrl+Enter
5. Observe behavior

**Expected Results:**
- Second request is blocked
- Toast shows: "A rewrite request is already in progress"
- Original request continues unaffected
- After first request completes, new requests can be made

### Test 6.8: Copy Rewritten Text

**Purpose:** Verify copying rewritten text to clipboard works.

**Steps:**
1. Complete a rewrite to open diff preview
2. Locate "Copy Rewritten" button
3. Click "Copy Rewritten" button
4. Paste into external text editor (Cmd/Ctrl+V)

**Expected Results:**
- Toast shows: "Rewritten text copied to clipboard"
- Pasted text exactly matches rewritten code from diff preview
- No extra formatting or characters
- Works in all major browsers

### Test 6.9: Copy Diff Patch

**Purpose:** Verify copying unified diff patch works correctly.

**Steps:**
1. Complete a rewrite to open diff preview
2. Locate "Copy Diff Patch" button
3. Click "Copy Diff Patch" button
4. Paste into external text editor

**Expected Results:**
- Toast shows: "Diff patch copied to clipboard"
- Pasted content is in unified diff format:
  - Header: `--- Original` and `+++ Rewritten`
  - Contains forensic ID in header
  - Contains instruction in header
  - Contains timestamp
  - Shows line changes with `-` and `+` prefixes
  - Unchanged lines prefixed with space
- Format is readable and can be manually applied
- Works in all major browsers

### Test 6.10: Forensic ID in Diff Patch

**Purpose:** Verify forensic ID is included in copied diff patch.

**Steps:**
1. Complete a rewrite and note the forensic ID from diff preview
2. Click "Copy Diff Patch"
3. Paste into text editor
4. Search for the forensic ID

**Expected Results:**
- Forensic ID appears in patch header
- Format: `@@ Forensic ID: TRUAI_<timestamp>_<hash>`
- ID matches what was displayed in diff preview
- ID is on its own line in the header

### Test 6.11: Clipboard Fallback (Non-HTTPS)

**Purpose:** Verify clipboard operations work without modern Clipboard API.

**Steps:**
1. Disable clipboard API (browser dev tools or HTTP context)
2. Complete a rewrite to open diff preview
3. Click "Copy Rewritten" button
4. Attempt to paste

**Expected Results:**
- Fallback method uses execCommand('copy')
- Success toast still appears
- Text is copied successfully
- Works in older browsers or non-HTTPS contexts

### Test 6.12: Response Shape Compatibility - { reply }

**Purpose:** Test backward compatibility with { reply: string } responses.

**Steps:**
1. Mock API response to return `{ reply: "rewritten code" }`
2. Complete rewrite operation
3. Observe handling

**Expected Results:**
- Response parsed successfully
- Rewritten code extracted from `reply` field
- Diff preview displays correctly

### Test 6.13: Response Shape Compatibility - { data: { reply } }

**Purpose:** Test nested response shape support.

**Steps:**
1. Mock API response to return `{ data: { reply: "rewritten code" } }`
2. Complete rewrite operation
3. Observe handling

**Expected Results:**
- Response parsed successfully
- Rewritten code extracted from nested structure
- Diff preview displays correctly

### Test 6.14: AbortController Browser Compatibility

**Purpose:** Verify AbortController works in supported browsers.

**Steps:**
1. Test in Chrome, Firefox, Safari, Edge
2. Complete a rewrite and cancel it
3. Verify cancellation works in each browser

**Expected Results:**
- Cancel functionality works in all modern browsers
- AbortError handled gracefully
- No browser-specific errors
- Older browsers without AbortController may need polyfill (not required by spec)

### Test 6.15: Multiple Diff Operations in Session

**Purpose:** Verify multiple sequential rewrites work correctly.

**Steps:**
1. Complete first rewrite and apply changes
2. Select different text
3. Complete second rewrite and apply changes
4. Select third piece of text
5. Complete third rewrite but reject it
6. Verify state is clean

**Expected Results:**
- Each rewrite works independently
- No state contamination between operations
- Forensic IDs are unique for each operation
- Stale detection works for each operation
- Rejecting one doesn't affect next operation

### Test 6.16: Long-Running Request Visual Feedback

**Purpose:** Verify user has clear feedback during pending requests.

**Steps:**
1. Start a rewrite request
2. Observe the UI during pending state
3. Note all visual indicators

**Expected Results:**
- "Generate Rewrite" button changes to "Generating..." and is disabled
- "Cancel" button changes to "Cancel Request"
- Button states are visually distinct
- User clearly understands request is pending
- No ambiguity about current state

### Test 6.17: Network Error Handling

**Purpose:** Verify graceful handling of network failures.

**Steps:**
1. Disconnect from internet or stop backend server
2. Attempt to start a rewrite
3. Observe error handling

**Expected Results:**
- Error caught gracefully
- Toast shows user-friendly message (not raw network error)
- Modal state resets (button enabled, text restored)
- User can retry once connection restored
- No JavaScript crashes

### Test 6.18: Concurrent Rewrite + Chat

**Purpose:** Verify inline rewrite doesn't interfere with chat functionality.

**Steps:**
1. Start an inline rewrite request
2. While pending, open AI chat panel
3. Send a chat message
4. Verify both operations complete

**Expected Results:**
- Chat request uses separate API call
- Both requests can be in flight simultaneously
- Inline rewrite abort doesn't affect chat
- Chat messages appear correctly
- No cross-contamination of state

---

**Last Updated:** 2026-01-16
**Version:** 1.1.0
