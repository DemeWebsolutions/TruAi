#!/bin/bash
# TruAi API Key Update Script
# Securely updates OpenAI and/or Anthropic API keys via terminal

set -e

API_BASE="http://localhost:8001/TruAi/api/v1"
LOGIN_URL="${API_BASE}/auth/login"
SETTINGS_URL="${API_BASE}/settings"

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🔐 TruAi API Key Update${NC}"
echo ""

# Step 1: Login and get session cookie + CSRF token
echo -e "${YELLOW}Step 1: Authenticating...${NC}"
LOGIN_RESPONSE=$(curl -s -c /tmp/truai_session.txt -X POST "${LOGIN_URL}" \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}')

SUCCESS=$(echo "$LOGIN_RESPONSE" | grep -o '"success":true' || echo "")
CSRF_TOKEN=$(echo "$LOGIN_RESPONSE" | grep -o '"csrf_token":"[^"]*"' | cut -d'"' -f4 || echo "")

if [ -z "$SUCCESS" ]; then
  echo -e "${RED}❌ Login failed!${NC}"
  echo "Response: $LOGIN_RESPONSE"
  exit 1
fi

echo -e "${GREEN}✅ Login successful${NC}"

if [ -z "$CSRF_TOKEN" ]; then
  echo -e "${YELLOW}⚠️  Warning: No CSRF token received${NC}"
fi

# Step 2: Get current settings to preserve other values
echo ""
echo -e "${YELLOW}Step 2: Loading current settings...${NC}"
CURRENT_SETTINGS=$(curl -s -b /tmp/truai_session.txt -X GET "${SETTINGS_URL}" \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: ${CSRF_TOKEN}")

if echo "$CURRENT_SETTINGS" | grep -q '"error"'; then
  echo -e "${RED}❌ Failed to load current settings${NC}"
  echo "Response: $CURRENT_SETTINGS"
  exit 1
fi

echo -e "${GREEN}✅ Current settings loaded${NC}"

# Step 3: Prompt for API keys
echo ""
echo -e "${BLUE}Enter API Keys (press Enter to skip):${NC}"
echo ""

read -sp "OpenAI API Key (sk-...): " OPENAI_KEY
echo ""
[ -n "$OPENAI_KEY" ] && echo -e "${GREEN}✓ OpenAI key provided${NC}"

read -sp "Anthropic/Sonnet API Key (sk-ant-...): " ANTHROPIC_KEY
echo ""
[ -n "$ANTHROPIC_KEY" ] && echo -e "${GREEN}✓ Anthropic key provided${NC}"

if [ -z "$OPENAI_KEY" ] && [ -z "$ANTHROPIC_KEY" ]; then
  echo -e "${RED}❌ No API keys provided. Exiting.${NC}"
  exit 1
fi

# Step 4: Extract current model settings (with defaults)
CURRENT_OPENAI_MODEL="gpt-4o"
CURRENT_SONNET_MODEL="sonnet-1"
CURRENT_DEFAULT_PROVIDER="openai"

# Try to extract from current settings if available
if echo "$CURRENT_SETTINGS" | grep -q "openaiModel"; then
  CURRENT_OPENAI_MODEL=$(echo "$CURRENT_SETTINGS" | grep -o '"openaiModel":"[^"]*"' | cut -d'"' -f4 || echo "gpt-4o")
fi

if echo "$CURRENT_SETTINGS" | grep -q "anthropicModel"; then
  CURRENT_SONNET_MODEL=$(echo "$CURRENT_SETTINGS" | grep -o '"anthropicModel":"[^"]*"' | cut -d'"' -f4 || echo "sonnet-1")
fi

if echo "$CURRENT_SETTINGS" | grep -q '"provider"'; then
  CURRENT_DEFAULT_PROVIDER=$(echo "$CURRENT_SETTINGS" | grep -o '"provider":"[^"]*"' | cut -d'"' -f4 || echo "openai")
fi

# Step 5: Build settings payload
echo ""
echo -e "${YELLOW}Step 3: Preparing settings update...${NC}"

# Build JSON payload - only include keys that were provided
if [ -n "$OPENAI_KEY" ] && [ -n "$ANTHROPIC_KEY" ]; then
  # Both keys provided
  SETTINGS_JSON=$(cat <<EOF
{
  "settings": {
    "providers": {
      "openai": {
        "api_key": "${OPENAI_KEY}",
        "default_model": "${CURRENT_OPENAI_MODEL}"
      },
      "sonnet": {
        "api_key": "${ANTHROPIC_KEY}",
        "default_model": "${CURRENT_SONNET_MODEL}"
      }
    },
    "default_provider": "${CURRENT_DEFAULT_PROVIDER}",
    "enable_streaming": true
  }
}
EOF
)
elif [ -n "$OPENAI_KEY" ]; then
  # Only OpenAI provided - need to get current Anthropic key
  CURRENT_ANTHROPIC_KEY=$(echo "$CURRENT_SETTINGS" | grep -o '"anthropicApiKey":"[^"]*"' | cut -d'"' -f4 || echo "")
  SETTINGS_JSON=$(cat <<EOF
{
  "settings": {
    "providers": {
      "openai": {
        "api_key": "${OPENAI_KEY}",
        "default_model": "${CURRENT_OPENAI_MODEL}"
      },
      "sonnet": {
        "api_key": "${CURRENT_ANTHROPIC_KEY}",
        "default_model": "${CURRENT_SONNET_MODEL}"
      }
    },
    "default_provider": "${CURRENT_DEFAULT_PROVIDER}",
    "enable_streaming": true
  }
}
EOF
)
else
  # Only Anthropic provided - need to get current OpenAI key
  CURRENT_OPENAI_KEY=$(echo "$CURRENT_SETTINGS" | grep -o '"openaiApiKey":"[^"]*"' | cut -d'"' -f4 || echo "")
  SETTINGS_JSON=$(cat <<EOF
{
  "settings": {
    "providers": {
      "openai": {
        "api_key": "${CURRENT_OPENAI_KEY}",
        "default_model": "${CURRENT_OPENAI_MODEL}"
      },
      "sonnet": {
        "api_key": "${ANTHROPIC_KEY}",
        "default_model": "${CURRENT_SONNET_MODEL}"
      }
    },
    "default_provider": "${CURRENT_DEFAULT_PROVIDER}",
    "enable_streaming": true
  }
}
EOF
)
fi

# Step 6: Save settings
echo -e "${YELLOW}Step 4: Saving settings...${NC}"
SAVE_RESPONSE=$(curl -s -b /tmp/truai_session.txt -X POST "${SETTINGS_URL}" \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: ${CSRF_TOKEN}" \
  -d "$SETTINGS_JSON")

if echo "$SAVE_RESPONSE" | grep -q '"success":true'; then
  echo -e "${GREEN}✅ Settings saved successfully!${NC}"
  echo ""
  echo "Updated API Keys:"
  [ -n "$OPENAI_KEY" ] && echo -e "  ${GREEN}✓${NC} OpenAI: ${OPENAI_KEY:0:10}...${OPENAI_KEY: -4}"
  [ -n "$ANTHROPIC_KEY" ] && echo -e "  ${GREEN}✓${NC} Anthropic: ${ANTHROPIC_KEY:0:10}...${ANTHROPIC_KEY: -4}"
else
  echo -e "${RED}❌ Failed to save settings${NC}"
  echo "Response: $SAVE_RESPONSE"
  rm -f /tmp/truai_session.txt
  exit 1
fi

# Cleanup
rm -f /tmp/truai_session.txt

echo ""
echo -e "${GREEN}🎉 API keys updated successfully!${NC}"
echo ""
echo "You can now test the API keys by using the TruAi dashboard."
