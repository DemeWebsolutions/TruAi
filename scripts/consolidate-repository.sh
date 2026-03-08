#!/bin/bash
#
# TruAi Repository Consolidation Script
#
# Automates file archiving and relocation for cleaner repo structure
#
# Usage: ./scripts/consolidate-repository.sh
#

set -e

echo "======================================"
echo "TruAi Repository Consolidation"
echo "======================================"
echo ""

# Safety check: ensure we're in TruAi root
if [ ! -f "README.md" ] || [ ! -d "backend" ]; then
    echo "❌ Error: Run this script from TruAi project root"
    exit 1
fi

# Safety check: ensure git status is clean
if [ -n "$(git status --porcelain)" ]; then
    echo "⚠️  Warning: You have uncommitted changes"
    echo ""
    git status --short
    echo ""
    read -p "Continue anyway? (y/N): " confirm
    if [ "$confirm" != "y" ] && [ "$confirm" != "Y" ]; then
        echo "Aborted. Commit or stash changes first."
        exit 1
    fi
fi

# Create archive directories
echo ""
echo "📁 Creating archive directories..."
mkdir -p archive/milestones
mkdir -p archive/updates
mkdir -p archive/fixes
mkdir -p archive/superseded
mkdir -p archive/backups
mkdir -p design/
mkdir -p dev/

echo "✓ Archive directories created"

# Phase 1: Archive milestone documents
echo ""
echo "📦 Phase 1: Archiving milestone documents..."

FILES_MILESTONES=(
    "PHASE1_FINAL_SUMMARY.md"
    "PHASE1_FIXES_IMPLEMENTATION.md"
    "PHASE4_IMPLEMENTATION_SUMMARY.md"
    "PROJECT_CONFIRMATION.md"
    "PROJECT_REVIEW.md"
    "DELIVERABLES_SUMMARY.md"
    "IMPLEMENTATION_SUMMARY.md"
)

for file in "${FILES_MILESTONES[@]}"; do
    if [ -f "$file" ]; then
        git mv "$file" "archive/milestones/" && echo "  ✓ $file"
    fi
done

# Phase 2: Archive update/working notes
echo ""
echo "📦 Phase 2: Archiving update notes..."

FILES_UPDATES=(
    "TruAi Update - Biometric and Bug Fixes.md"
    "TruAi Update - Biometric and Bug Fixes Refinements.md"
    "TruAi Update - Biometric and Bug Fixes.rtf"
    "TruAi Update - Critical Updates.md"
    "TruAi Update - Revied Updates.md"
    "TruAi Update - original html refernces.md"
    "TruAi Update - Template for missing html.md"
)

for file in "${FILES_UPDATES[@]}"; do
    if [ -f "$file" ]; then
        git mv "$file" "archive/updates/" && echo "  ✓ $file"
    fi
done

# Phase 3: Archive fix documentation
echo ""
echo "📦 Phase 3: Archiving fix documentation..."

FILES_FIXES=(
    "INVALID_CREDENTIALS_FIX.md"
    "PATH_FIX_SUMMARY.md"
    "ROUTING_SOLUTION.md"
    "LOGIN_TROUBLESHOOTING.md"
    "CURSOR_STYLE_UPDATES.md"
)

for file in "${FILES_FIXES[@]}"; do
    if [ -f "$file" ]; then
        git mv "$file" "archive/fixes/" && echo "  ✓ $file"
    fi
done

# Phase 4: Archive superseded docs
echo ""
echo "📦 Phase 4: Archiving superseded documentation..."

FILES_SUPERSEDED=(
    "DATABASE_INITIALIZATION.md"
    "FILES_REQUIRED.txt"
    "GITHUB_PUSH_INSTRUCTIONS.md"
    "UPLOAD_TO_GITHUB.md"
    "PASSWORD_CHANGE.md"
    "AI_RESPONSE_INVESTIGATION.md"
    "SETTINGS_WIRING_CONFIRMED.md"
    "SELECTION_TOOLS.md"
)

for file in "${FILES_SUPERSEDED[@]}"; do
    if [ -f "$file" ]; then
        git mv "$file" "archive/superseded/" && echo "  ✓ $file"
    fi
done

# Phase 5: Move test files to dev/
echo ""
echo "📦 Phase 5: Relocating test files..."

TEST_FILES=(
    "test-init.html"
    "test-new-designs.html"
    "test-popup-direct.html"
    "preview-new-design.html"
)

for file in "${TEST_FILES[@]}"; do
    if [ -f "$file" ]; then
        git mv "$file" "dev/" && echo "  ✓ $file → dev/"
    fi
done

# Phase 6: Move backend scripts
echo ""
echo "📦 Phase 6: Relocating backend scripts..."

BACKEND_SCRIPTS=(
    "reset-admin-password.php"
    "change-password.php"
    "init-database.php"
)

for file in "${BACKEND_SCRIPTS[@]}"; do
    if [ -f "$file" ]; then
        git mv "$file" "scripts/" && echo "  ✓ $file → scripts/"
    fi
done

# Phase 7: Move test shell scripts to tests/integration/
echo ""
echo "📦 Phase 7: Relocating integration test scripts..."
mkdir -p tests/integration

INTEGRATION_TESTS=(
    "test-login-flow.sh"
    "test-settings-api.sh"
    "test-settings-wiring.php"
)

for file in "${INTEGRATION_TESTS[@]}"; do
    if [ -f "$file" ]; then
        git mv "$file" "tests/integration/" && echo "  ✓ $file → tests/integration/"
    fi
done

# Phase 8: Check for duplicate HTML files in root
echo ""
echo "📦 Phase 8: Checking for duplicate HTML files in root..."

HTML_FILES=(
    "access-denied.html"
    "access-granted.html"
    "loading.html"
    "login-portal.html"
    "welcome.html"
)

for file in "${HTML_FILES[@]}"; do
    if [ -f "$file" ] && [ -f "public/TruAi/$file" ]; then
        echo "  ⚠️  Duplicate found: $file (exists in public/TruAi/)"
        echo "     Removing root-level duplicate..."
        git rm "$file" && echo "  ✓ Removed duplicate: $file"
    elif [ -f "$file" ]; then
        echo "  ℹ️  $file exists only in root (moving to public/TruAi/)"
        git mv "$file" "public/TruAi/" && echo "  ✓ Moved to public/TruAi/"
    fi
done

# Phase 9: Move large design files
echo ""
echo "📦 Phase 9: Relocating large design files..."

DESIGN_FILES=(
    "Home.svg"
    "TruAi Login Portal.svg"
    "TruAi Login Portal.pdf"
    "TruAi.Gateway.svg"
    "TruAi.Gatewaycopy.html"
)

for file in "${DESIGN_FILES[@]}"; do
    if [ -f "$file" ]; then
        git mv "$file" "design/" && echo "  ✓ $file → design/"
    fi
done

# Phase 10: Handle backup directories
echo ""
echo "📦 Phase 10: Evaluating backup/staging directories..."

if [ -d "TruAi-Git" ]; then
    echo "  ⚠️  Found TruAi-Git/ directory"
    echo "     This appears to be a backup. Moving to archive/backups/"
    git mv "TruAi-Git" "archive/backups/TruAi-Git" && echo "  ✓ Archived TruAi-Git/"
fi

if [ -d "TruAi-Update" ]; then
    echo "  ⚠️  Found TruAi-Update/ directory"
    echo "     This appears to be a staging area."
    echo "     Please manually review and merge changes, then delete."
    echo "     Skipping automated move."
fi

# Summary
echo ""
echo "======================================"
echo "✅ Repository Consolidation Complete"
echo "======================================"
echo ""
echo "Changes staged. Review with:"
echo "  git status"
echo "  git diff --cached --stat"
echo ""
echo "If satisfied, commit:"
echo "  git commit -m 'chore: consolidate repository structure"
echo ""
echo "  - Archive milestone, update, fix, and superseded docs"
echo "  - Move test files to dev/ directory"
echo "  - Relocate backend scripts to scripts/"
echo "  - Remove duplicate HTML files from root"
echo "  - Move large design files to design/"
echo "  - Archive TruAi-Git backup directory"
echo "  '"
echo ""
echo "Then push:"
echo "  git push origin HEAD"
echo ""
