# TruAi HTML Server - Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                        TruAi HTML Server                             │
│                     (Phantom.ai Style Login)                         │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│                          CLIENT LAYER                                │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  ┌────────────────┐              ┌──────────────────┐              │
│  │  Login Page    │              │   Dashboard      │              │
│  │                │              │                  │              │
│  │  • Logo/Brand  │──Login──>   │  3-Column Layout │              │
│  │  • Legal Terms │              │  (Cursor Style)  │              │
│  │  • Encryption  │              │                  │              │
│  └────────────────┘              └──────────────────┘              │
│         │                                  │                         │
│         │                                  │                         │
│         ▼                                  ▼                         │
│  ┌──────────────────────────────────────────────────────┐          │
│  │           JavaScript Frontend Layer                   │          │
│  ├──────────────────────────────────────────────────────┤          │
│  │  crypto.js    │  api.js      │  dashboard.js         │          │
│  │  • AES-256    │  • REST      │  • UI Logic           │          │
│  │  • SHA-256    │  • Fetch API │  • Task Management    │          │
│  │  • Web Crypto │  • Error     │  • File Handling      │          │
│  │  • Key Gen    │    Handling  │  • Event Listeners    │          │
│  └──────────────────────────────────────────────────────┘          │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
                             │
                             │  HTTPS (Encrypted)
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         SERVER LAYER (PHP)                           │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  ┌────────────────────────────────────────────────────────────┐    │
│  │                    index.php (Entry Point)                  │    │
│  │  • Route Detection                                          │    │
│  │  • Localhost Enforcement                                    │    │
│  │  • Session Management                                       │    │
│  └────────────────────────────────────────────────────────────┘    │
│                             │                                        │
│                             ▼                                        │
│  ┌────────────────────────────────────────────────────────────┐    │
│  │                      router.php (API Router)                │    │
│  │  • Route Matching                                           │    │
│  │  • CORS Handling                                            │    │
│  │  • Auth Middleware                                          │    │
│  └────────────────────────────────────────────────────────────┘    │
│           │                    │                    │                │
│           ▼                    ▼                    ▼                │
│  ┌────────────────┐  ┌─────────────────┐  ┌─────────────────┐    │
│  │  auth.php      │  │ truai_service   │  │  chat_service   │    │
│  │                │  │                 │  │                 │    │
│  │  • Login       │  │  • Task Create  │  │  • Messages     │    │
│  │  • Session     │  │  • Risk Eval    │  │  • Conversations│    │
│  │  • CSRF        │  │  • Tier Route   │  │  • History      │    │
│  │  • Audit       │  │  • Execute      │  │                 │    │
│  └────────────────┘  └─────────────────┘  └─────────────────┘    │
│           │                    │                    │                │
│           └────────────────────┴────────────────────┘                │
│                             │                                        │
│                             ▼                                        │
│  ┌────────────────────────────────────────────────────────────┐    │
│  │                    encryption.php                           │    │
│  │  • RSA-2048 Key Generation                                  │    │
│  │  • AES-256-GCM Decryption                                   │    │
│  │  • Session Key Management                                   │    │
│  │  • PBKDF2 Password Hashing                                  │    │
│  └────────────────────────────────────────────────────────────┘    │
│                             │                                        │
│                             ▼                                        │
│  ┌────────────────────────────────────────────────────────────┐    │
│  │                    database.php (SQLite)                    │    │
│  │  • Schema Auto-Init                                         │    │
│  │  • Prepared Statements                                      │    │
│  │  • Connection Pool                                          │    │
│  └────────────────────────────────────────────────────────────┘    │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         DATA LAYER                                   │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  ┌─────────────────────────────────────────────────────────┐       │
│  │                    SQLite Database                       │       │
│  │                    (truai.db)                            │       │
│  ├─────────────────────────────────────────────────────────┤       │
│  │  Tables:                                                 │       │
│  │  • users            - Authentication                     │       │
│  │  • conversations    - Chat history                       │       │
│  │  • messages         - Chat messages                      │       │
│  │  • tasks            - TruAi tasks                        │       │
│  │  • executions       - Task executions                    │       │
│  │  • artifacts        - Generated code                     │       │
│  │  • audit_logs       - Immutable audit trail             │       │
│  └─────────────────────────────────────────────────────────┘       │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘


┌─────────────────────────────────────────────────────────────────────┐
│                      ENCRYPTION FLOW                                 │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  CLIENT                                    SERVER                    │
│  ──────                                    ──────                    │
│                                                                       │
│  1. Request Public Key          ────────>  Generate RSA-2048        │
│                                                                       │
│  2. Generate Session Key                                             │
│     • Random 32-byte key                                             │
│     • Used for AES-256-GCM                                           │
│                                                                       │
│  3. Hash Password                                                    │
│     • SHA-256(password)                                              │
│     • No plaintext sent                                              │
│                                                                       │
│  4. Encrypt Credentials                                              │
│     • AES-256-GCM with session key                                   │
│     • Includes timestamp                                             │
│                                                                       │
│  5. Send Encrypted Data         ────────>  Decrypt with Session Key │
│                                                                       │
│  6.                                        Verify Timestamp          │
│                                            (Prevent Replay)          │
│                                                                       │
│  7.                                        Hash & Verify Password    │
│                                            (bcrypt + salt)           │
│                                                                       │
│  8.                                        Create Secure Session     │
│                                            (HTTP-only cookie)        │
│                                                                       │
│  9. <────────  Success Response            Generate CSRF Token       │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘


┌─────────────────────────────────────────────────────────────────────┐
│                      TRUAI CORE WORKFLOW                             │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  User Input                                                          │
│      │                                                                │
│      ▼                                                                │
│  ┌────────────────┐                                                  │
│  │  Task Creation │                                                  │
│  │  • Prompt      │                                                  │
│  │  • Context     │                                                  │
│  │  • Files       │                                                  │
│  └────────────────┘                                                  │
│      │                                                                │
│      ▼                                                                │
│  ┌────────────────┐                                                  │
│  │  Risk Engine   │                                                  │
│  │  Evaluation    │────────> LOW / MEDIUM / HIGH                    │
│  └────────────────┘                                                  │
│      │                                                                │
│      ▼                                                                │
│  ┌────────────────┐                                                  │
│  │  Tier Router   │────────> Cheap / Mid / High                     │
│  │  Assignment    │                                                  │
│  └────────────────┘                                                  │
│      │                                                                │
│      ▼                                                                │
│  ┌────────────────┐                                                  │
│  │  AI Execution  │────────> Generate Output                        │
│  │  (Simulated)   │          (Placeholder for real AI)              │
│  └────────────────┘                                                  │
│      │                                                                │
│      ▼                                                                │
│  ┌────────────────┐                                                  │
│  │  Review Phase  │                                                  │
│  │  (Human Gate)  │────────> Accept / Reject / Save                 │
│  └────────────────┘                                                  │
│      │                                                                │
│      ▼                                                                │
│  ┌────────────────┐                                                  │
│  │  Approval      │────────> Production / Staging                   │
│  │  & Deployment  │                                                  │
│  └────────────────┘                                                  │
│      │                                                                │
│      ▼                                                                │
│  ┌────────────────┐                                                  │
│  │  Audit Log     │────────> Immutable Record                       │
│  └────────────────┘                                                  │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘


┌─────────────────────────────────────────────────────────────────────┐
│                      SECURITY LAYERS                                 │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  Layer 1: Transport Security                                         │
│  • AES-256-GCM encryption                                            │
│  • HTTPS/TLS ready                                                   │
│  • No plaintext transmission                                         │
│                                                                       │
│  Layer 2: Authentication                                             │
│  • SHA-256 client hashing                                            │
│  • bcrypt server hashing                                             │
│  • PBKDF2 with 100K iterations                                       │
│  • Salt-based password storage                                       │
│                                                                       │
│  Layer 3: Session Security                                           │
│  • HTTP-only cookies                                                 │
│  • Secure session tokens                                             │
│  • 1-hour expiration                                                 │
│  • Automatic cleanup                                                 │
│                                                                       │
│  Layer 4: API Security                                               │
│  • CSRF token protection                                             │
│  • Rate limiting ready                                               │
│  • Input validation                                                  │
│  • Output sanitization                                               │
│                                                                       │
│  Layer 5: Database Security                                          │
│  • Prepared statements                                               │
│  • SQL injection prevention                                          │
│  • Encrypted storage support                                         │
│  • Read-only permissions where applicable                            │
│                                                                       │
│  Layer 6: Access Control                                             │
│  • Localhost-only enforcement                                        │
│  • Single admin authorization                                        │
│  • Comprehensive audit logging                                       │
│  • Immutable audit trail                                             │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘


┌─────────────────────────────────────────────────────────────────────┐
│                      FILE STRUCTURE                                  │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  TruAi/                                                              │
│  ├── index.php              (Entry point)                           │
│  ├── README.md              (Full documentation)                    │
│  ├── SETUP.md               (Quick start guide)                     │
│  ├── IMPLEMENTATION_SUMMARY.md  (This document)                     │
│  ├── .gitignore             (Ignore patterns)                       │
│  │                                                                    │
│  ├── backend/               (PHP Backend)                           │
│  │   ├── config.php         (Configuration)                         │
│  │   ├── database.php       (DB layer)                              │
│  │   ├── auth.php           (Authentication)                        │
│  │   ├── encryption.php     (Encryption service)                    │
│  │   ├── router.php         (API router)                            │
│  │   ├── truai_service.php  (Core logic)                            │
│  │   └── chat_service.php   (Chat functionality)                    │
│  │                                                                    │
│  ├── assets/                (Frontend Assets)                       │
│  │   ├── css/                                                        │
│  │   │   └── main.css       (Complete styling)                      │
│  │   ├── js/                                                         │
│  │   │   ├── crypto.js      (Encryption utils)                      │
│  │   │   ├── api.js         (API client)                            │
│  │   │   ├── app.js         (Core app)                              │
│  │   │   ├── login.js       (Login page)                            │
│  │   │   └── dashboard.js   (Dashboard)                             │
│  │   └── images/            (Logos)                                 │
│  │       ├── TruAi-Logo.png                                          │
│  │       ├── TruAi-icon.png                                          │
│  │       ├── TruAi-transparent-bg.png                                │
│  │       └── Tru.png                                                 │
│  │                                                                    │
│  ├── database/              (Auto-created)                          │
│  │   └── truai.db           (SQLite)                                │
│  │                                                                    │
│  └── logs/                  (Auto-created)                          │
│      └── (application logs)                                         │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘

## Persistent Learning System Architecture

### Overview

The persistent learning system enables TruAi to learn from user interactions and improve over time. It's designed with privacy, performance, and maintainability in mind.

### Components

```
┌─────────────────────────────────────────────────────────────────────┐
│                    Learning System Architecture                      │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  ┌──────────────┐       ┌──────────────┐       ┌──────────────┐   │
│  │   Frontend   │──────▶│   Backend    │──────▶│   Database   │   │
│  │              │       │   Service    │       │              │   │
│  └──────────────┘       └──────────────┘       └──────────────┘   │
│         │                      │                        │           │
│         │                      │                        │           │
│  learning-client.js    learning_service.php    learning_events     │
│  - Feedback UI         - Event recording       learning_patterns   │
│  - Insights panel      - Pattern extraction                        │
│  - Suggestions         - Confidence scoring                        │
│                        - Analytics                                 │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
```

### Database Schema

#### learning_events Table

Stores all learning events from user interactions:

| Column | Type | Description |
|--------|------|-------------|
| id | INTEGER | Primary key |
| user_id | INTEGER | Foreign key to users |
| event_type | TEXT | correction, preference, success, failure, feedback |
| context | TEXT | JSON context (prompt, model, tier) |
| original_prompt | TEXT | User's original prompt |
| original_response | TEXT | AI's original response |
| corrected_response | TEXT | User's corrected version |
| feedback_score | INTEGER | -1 (negative), 0 (neutral), 1 (positive) |
| model_used | TEXT | Model name (gpt-4, claude-sonnet, etc.) |
| risk_level | TEXT | LOW, MEDIUM, HIGH |
| tier | TEXT | CHEAP, MID, HIGH |
| created_at | DATETIME | Event timestamp |

**Indexes:**
- `idx_learning_user` - Fast user-specific queries
- `idx_learning_type` - Filter by event type
- `idx_learning_date` - Chronological ordering
- `idx_learning_user_date` - Combined user + date queries

#### learned_patterns Table

Stores extracted patterns with confidence scores:

| Column | Type | Description |
|--------|------|-------------|
| id | INTEGER | Primary key |
| user_id | INTEGER | Foreign key to users |
| pattern_type | TEXT | prompt_template, context_preference, model_preference, correction_pattern |
| pattern_key | TEXT | Unique identifier (hash) |
| pattern_value | TEXT | JSON pattern data |
| confidence_score | REAL | 0.0 to 1.0 |
| usage_count | INTEGER | How many times used |
| success_rate | REAL | 0.0 to 1.0 |
| last_used | DATETIME | Last usage timestamp |
| created_at | DATETIME | Pattern creation time |
| updated_at | DATETIME | Last update time |

**Indexes:**
- `idx_patterns_user` - User-specific patterns
- `idx_patterns_type` - Filter by pattern type
- `idx_patterns_score` - Order by confidence
- `idx_patterns_user_type` - Combined queries
- `idx_patterns_success` - Order by success rate

**Unique Constraint:** (user_id, pattern_type, pattern_key)

### Data Flow

#### 1. Feedback Recording

```
User clicks 👍/👎 button
    ↓
learning-client.js sends POST to /api/v1/learning/feedback
    ↓
Router validates authentication
    ↓
LearningService.recordFeedback()
    ↓
Insert into learning_events table
    ↓
Extract patterns (if positive feedback)
    ↓
Update or create learned_patterns
    ↓
Update confidence scores
```

#### 2. Correction Recording

```
User provides corrected response
    ↓
learning-client.js sends POST to /api/v1/learning/correction
    ↓
LearningService.recordCorrection()
    ↓
Insert into learning_events (type='correction')
    ↓
Extract correction patterns
    ↓
Store pattern key and value
    ↓
Initialize confidence at 0.6 (corrections are valuable)
```

#### 3. Pattern Extraction

The system automatically extracts patterns from events:

**Prompt Patterns:**
- Extract keywords (words > 4 chars)
- Create pattern key from top 5 keywords (MD5 hash)
- Store full prompt as template
- Track which prompts led to successful outcomes

**Model Preferences:**
- Track which models users prefer
- Calculate success rate per model
- Adjust confidence based on usage and success

**Correction Patterns:**
- Analyze what was corrected
- Store original → corrected mappings
- High initial confidence (0.6) for corrections

#### 4. Suggestion Generation

```
User types prompt
    ↓
Request suggestions via learning-client.js
    ↓
LearningService.suggestImprovement()
    ↓
Retrieve top patterns by confidence
    ↓
Match keywords in user's prompt
    ↓
Calculate match score × confidence
    ↓
Return top 3 suggestions
```

### API Endpoints

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/api/v1/learning/event` | POST | ✓ | Record generic learning event |
| `/api/v1/learning/feedback` | POST | ✓ | Record feedback (-1, 0, 1) |
| `/api/v1/learning/correction` | POST | ✓ | Record user correction |
| `/api/v1/learning/patterns` | GET | ✓ | Get learned patterns |
| `/api/v1/learning/insights` | GET | ✓ | Get user insights |
| `/api/v1/learning/suggest` | POST | ✓ | Get prompt suggestions |
| `/api/v1/learning/reset` | DELETE | ✓ | Delete all user learning data |

### Privacy & Security

**User Isolation:**
- All queries filter by `user_id`
- No cross-user data access
- No shared learning between users

**Data Retention:**
- Events older than 180 days are pruned
- Patterns with low confidence (<0.3) and age >90 days are removed
- Maximum 1,000 patterns per user (oldest/lowest confidence removed)

**Sensitive Data:**
- No API keys stored in learning data
- No passwords or credentials
- Context is sanitized before storage
- All JSON fields validated

### Performance Optimizations

**Indexes:**
- Strategic indexes on high-query columns
- Composite indexes for common queries
- Covering indexes where appropriate

**Pruning:**
- Automatic cleanup runs periodically
- Removes old events and low-value patterns
- Prevents database bloat

**Caching:**
- Frequently accessed patterns cached
- Insights cached per session
- Pattern matching optimized with indexes

### Confidence Scoring Algorithm

```
confidence = (success_rate × 0.7) + (usage_factor × 0.3)

where:
  success_rate = successful_uses / total_uses
  usage_factor = min(usage_count / 10, 1.0)  // Caps at 10 uses
```

**Example:**
- Pattern used 5 times, 4 successful
- success_rate = 4/5 = 0.8
- usage_factor = 5/10 = 0.5
- confidence = (0.8 × 0.7) + (0.5 × 0.3) = 0.56 + 0.15 = 0.71

### Future Enhancements

- **Collaborative filtering:** Learn from anonymized patterns across users
- **Advanced NLP:** Better keyword extraction and semantic matching
- **Reinforcement learning:** Dynamic confidence adjustment
- **Cross-session learning:** Patterns persist across devices
- **Export/import:** Transfer learning data between accounts

Copyright My Deme, LLC © 2026
Developed by DemeWebsolutions.com
```
