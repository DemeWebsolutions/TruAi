# TruAi AI Integration Guide

## Overview

TruAi HTML Server includes **full AI functionality** with real-time code generation and intelligent chat capabilities. This guide explains how to configure and use the AI features.

## Supported AI Providers

### OpenAI (Recommended)
- **Models:** GPT-3.5 Turbo, GPT-4, GPT-4 Turbo
- **Best for:** Code generation, general tasks, fast responses
- **Cost:** Moderate
- **API Key:** https://platform.openai.com/api-keys

### Anthropic Claude
- **Models:** Claude 3 Sonnet, Claude 3 Opus
- **Best for:** Complex reasoning, long-context analysis
- **Cost:** Varies by model
- **API Key:** https://console.anthropic.com/

## Configuration

### Step 1: Get API Keys

#### OpenAI
1. Visit https://platform.openai.com/api-keys
2. Create an account or sign in
3. Click "Create new secret key"
4. Copy the key (starts with `sk-`)
5. Store it securely

#### Anthropic (Optional)
1. Visit https://console.anthropic.com/
2. Create an account or sign in
3. Navigate to API Keys section
4. Generate a new key (starts with `sk-ant-`)
5. Copy and store securely

### Step 2: Set Environment Variables

**Linux/macOS:**
```bash
export OPENAI_API_KEY="sk-your-actual-openai-key-here"
export ANTHROPIC_API_KEY="sk-ant-your-actual-anthropic-key-here"
```

**Windows (PowerShell):**
```powershell
$env:OPENAI_API_KEY="sk-your-actual-openai-key-here"
$env:ANTHROPIC_API_KEY="sk-ant-your-actual-anthropic-key-here"
```

**Permanent Configuration (Linux/macOS):**
```bash
# Add to ~/.bashrc or ~/.zshrc
echo 'export OPENAI_API_KEY="sk-your-key"' >> ~/.bashrc
source ~/.bashrc
```

### Step 3: Verify Configuration

```bash
# Check if keys are set
echo $OPENAI_API_KEY
echo $ANTHROPIC_API_KEY

# Start server
cd TruAi
php -S localhost:8080 index.php

# Test AI connection (in another terminal)
curl http://localhost:8080/api/v1/ai/test
```

## AI Model Tiers

TruAi Core automatically routes tasks to appropriate AI models based on complexity and risk level.

### Tier System

| Tier | Models | Use Case | Cost |
|------|--------|----------|------|
| **Cheap** | GPT-3.5 Turbo | Simple tasks, formatting, quick responses | $ |
| **Mid** | GPT-4 | Standard code generation, refactoring | $$ |
| **High** | GPT-4 Turbo, Claude Opus | Complex reasoning, large codebases | $$$ |

### Automatic Tier Selection

TruAi Core evaluates each task and assigns the appropriate tier:

```
LOW RISK → Cheap Tier → GPT-3.5 Turbo
MEDIUM RISK → Mid Tier → GPT-4
HIGH RISK → High Tier → GPT-4 Turbo
```

### Manual Tier Override

You can manually select a tier in the dashboard:
1. Navigate to the right column
2. Select tier: **Auto** | **Cheap** | **Mid** | **High**
3. Submit your task

## Using AI Features

### 1. Code Generation

**Example Task:**
```
Create a Python function that validates email addresses using regex
```

**TruAi Response:**
```python
import re

def validate_email(email):
    """
    Validate email address using regex pattern.
    
    Args:
        email (str): Email address to validate
        
    Returns:
        bool: True if valid, False otherwise
    """
    pattern = r'^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$'
    return re.match(pattern, email) is not None
```

### 2. Chat Interface

Ask questions, get explanations, or request help:

**You:** "Explain how async/await works in JavaScript"

**TruAi:** "Async/await is syntactic sugar built on top of Promises in JavaScript..."

### 3. Code Review & Refactoring

**Task:** "Refactor this code to use modern ES6 syntax"

TruAi will analyze and provide improved code with explanations.

### 4. Context-Aware Generation

Upload files to provide context:
1. Click the file upload area
2. Select relevant code files
3. Enter your task prompt
4. TruAi will consider the file context

## API Integration Details

### Backend Implementation

The `AIClient` class (`backend/ai_client.php`) handles all AI API calls:

```php
$aiClient = new AIClient();

// Generate code
$code = $aiClient->generateCode("Create a REST API endpoint", "gpt-4");

// Chat
$response = $aiClient->chat("Explain closures", "gpt-3.5-turbo");

// Test connection
$status = $aiClient->testConnection();
```

### Error Handling

The system includes comprehensive error handling:

1. **API Key Missing:** Clear error message with configuration instructions
2. **API Error:** Displays specific error from provider
3. **Network Issues:** Retries and provides fallback responses
4. **Rate Limiting:** Handles gracefully with user notification

### Response Caching

To optimize costs and performance:
- Similar prompts may return cached responses
- Cache duration: Configurable (default: 1 hour)
- Cache storage: SQLite database

## Cost Optimization

### Automatic Cost Optimization

TruAi Core implements intelligent cost optimization:

1. **Task Classification:** Analyzes complexity before sending to AI
2. **Tier Selection:** Uses cheapest model capable of handling task
3. **Token Management:** Optimizes prompt length and response tokens
4. **Context Trimming:** Includes only relevant context

### Manual Cost Control

You can control costs by:
1. Using **Auto** tier for best balance
2. Selecting **Cheap** tier for simple tasks
3. Setting maximum token limits in config
4. Reviewing usage in audit logs

### Estimated Costs

Based on OpenAI pricing (as of 2024):

| Task Type | Tier | Tokens | Cost |
|-----------|------|--------|------|
| Simple formatting | Cheap | ~200 | $0.0004 |
| Function generation | Mid | ~500 | $0.015 |
| Full module | High | ~2000 | $0.08 |

*Actual costs vary based on prompt and response length*

## Troubleshooting

### Problem: "API key not configured"

**Solution:**
```bash
# Verify environment variable is set
echo $OPENAI_API_KEY

# If empty, set it:
export OPENAI_API_KEY="sk-your-key"

# Restart server
php -S localhost:8080 index.php
```

### Problem: "API request failed"

**Possible causes:**
1. Invalid API key - Check key is correct
2. No internet connection - Verify connectivity
3. API service down - Check status.openai.com
4. Rate limit exceeded - Wait and retry

**Check logs:**
```bash
tail -f logs/error.log
```

### Problem: "Invalid response from API"

**Solution:**
- Check API key has credits/quota
- Verify account is in good standing
- Try different model (switch to Anthropic)

### Problem: Slow responses

**Causes:**
- Using GPT-4 (slower but more accurate)
- Large context/prompts
- High API load

**Solutions:**
- Use GPT-3.5 for faster responses
- Reduce context size
- Enable response streaming (future feature)

## Advanced Configuration

### Custom Model Selection

Edit `backend/truai_service.php`:

```php
private function getModelForTier($tier) {
    return match($tier) {
        AI_TIER_CHEAP => 'gpt-3.5-turbo',
        AI_TIER_MID => 'gpt-4',
        AI_TIER_HIGH => 'gpt-4-turbo-preview',
        default => 'gpt-3.5-turbo'
    };
}
```

### Timeout Configuration

Adjust timeout in `backend/ai_client.php`:

```php
curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 2 minutes
```

### Temperature Settings

Control creativity/randomness:

```php
$data = [
    'temperature' => 0.7, // 0.0 = deterministic, 1.0 = creative
    // ...
];
```

## Security Best Practices

### 1. Protect API Keys

- Never commit API keys to version control
- Use environment variables only
- Rotate keys regularly
- Monitor usage for anomalies

### 2. Rate Limiting

Implement rate limiting to prevent abuse:
- Limit requests per user
- Implement cooldown periods
- Track usage in audit logs

### 3. Input Validation

- Sanitize all user inputs
- Limit prompt length
- Block malicious patterns
- Validate file uploads

### 4. Cost Controls

- Set spending limits in OpenAI dashboard
- Monitor usage daily
- Alert on unusual patterns
- Implement budget caps

## Monitoring & Analytics

### Usage Tracking

All AI requests are logged in `audit_logs` table:

```sql
SELECT 
    event,
    details,
    timestamp
FROM audit_logs
WHERE event LIKE '%AI%'
ORDER BY timestamp DESC;
```

### Cost Analysis

Track costs by:
- Model used
- Tokens consumed
- User/session
- Time period

### Performance Metrics

Monitor:
- Response time
- Success rate
- Error types
- User satisfaction

## Future Enhancements

Planned features:
- [ ] Streaming responses
- [ ] Custom model fine-tuning
- [ ] Multi-language support
- [ ] Voice input/output
- [ ] Image generation (DALL-E)
- [ ] Code execution sandbox
- [ ] Collaborative AI sessions

## Support

For AI integration issues:
1. Check this guide
2. Review logs: `tail -f logs/error.log`
3. Test connection: `curl http://localhost:8080/api/v1/ai/test`
4. Consult provider documentation:
   - OpenAI: https://platform.openai.com/docs
   - Anthropic: https://docs.anthropic.com

---

**TruAi HTML Server - Full AI Integration**  
Copyright My Deme, LLC © 2026  
Developed by DemeWebsolutions.com
