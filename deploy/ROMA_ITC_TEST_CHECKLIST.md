# ROMA + ITC Connection Test Checklist

Verify Gemini.ai trust channel and ITC connectivity.

---

## Prerequisites

- [ ] TruAi Core running locally
- [ ] Gemini.ai deployed on Contabo (https://gemini-ai.demewebsolutions.com)
- [ ] WireGuard tunnel established (if using VPN)
- [ ] ROMA keys in `/opt/roma/keys/` (VPS) and local

---

## 1. Network Reachability

| Test | Command | Expected |
|------|---------|----------|
| HTTPS | `curl -sI https://gemini-ai.demewebsolutions.com/` | 200 or 302 |
| ITC Port | `nc -zv 154.53.54.169 8443` | Open (if ITC enabled) |
| VPN | `ping 10.0.0.1` from HQ | Reply |

---

## 2. ROMA Key Verification

```bash
# On VPS
ls -la /opt/roma/keys/
# Should show: truai_public.pem, gemini_private.pem (no world read)

# Verify key format
openssl rsa -in /opt/roma/keys/gemini_private.pem -check -noout
```

---

## 3. ITC Handshake (Manual)

If ITC endpoint is live:

```bash
# From HQ (or via curl)
curl -k -X POST https://gemini-ai.demewebsolutions.com:8443/itc \
  -H "Content-Type: application/json" \
  -d '{"action":"handshake","client_id":"truai"}'
```

Expected: JSON with `public_key` and `system_state_hash` (or equivalent).

---

## 4. Trust State Logs

```bash
# On VPS
tail -f /var/log/gemini/trust.log
tail -f /var/log/gemini/itc.log
```

Monitor for:
- Failed handshakes
- Repeated commands
- Unusual file writes

---

## 5. Escalation Path

If any step fails:

1. **Gemini state → SUSPENDED**
2. **Execution → blocked**
3. **Escalation → TruAi Core**

---

## 6. Quick Health Check Script

```bash
#!/bin/bash
URL="https://gemini-ai.demewebsolutions.com"
echo "1. HTTPS reachability..."
curl -sI "$URL/" | head -1

echo "2. Login page..."
curl -sI "$URL/TruAi/login-portal.html" | head -1

echo "3. API health (if enabled)..."
curl -s "$URL/api/v1/health" 2>/dev/null || echo "N/A"
```

---

*My Deme, LLC © 2026 — TruAi ROMA Security*
