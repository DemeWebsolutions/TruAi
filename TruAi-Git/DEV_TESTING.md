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
4. Click "AI Rewrite" button or press Ctrl+Shift+R
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
