# Trinity macOS Apps — Project Completion Checklist

**Date:** 2026-03-15  
**Status:** Complete

---

## Completed deliverables

| # | Item | Location |
|---|------|----------|
| 1 | Plan v3.3 | `docs/TRINITY_MACOS_APPS_PLAN.md` |
| 2 | Runtime contract | `electron/runtimeContract.js` |
| 3 | Supervisor | `electron/supervisor.js` |
| 4 | Dashboard (optional) | `electron/dashboard.html` — File → Platform Dashboard |
| 5 | Config template | `electron/config.template.json` |
| 6 | Runtime manifest | `electron/runtime/manifest.json` |
| 7 | Memory client | `packages/memory-client/` |
| 8 | Phantom scaffold | `apps/phantom/` |
| 9 | Gemini scaffold | `apps/gemini/` |
| 10 | Gateway PyInstaller | `memory-gateway/run_gateway.py`, `gateway.spec` |
| 11 | Build scripts | `scripts/build_macos.sh`, `scripts/build-suite.sh` |

---

## UX/UI preservation

- **TruAi:** Main window loads login portal (unchanged). Dashboard via File → Platform Dashboard.
- **Phantom.ai (existing):** Untouched — scaffolds in TruAi/apps/ are reference only.
- **Gemini.ai (existing):** Untouched — scaffolds in TruAi/apps/ are reference only.

---

## Run commands

```bash
# TruAi (from TruAi root)
cd /Users/mydemellc./Desktop/TruAi/electron
npm start

# Phantom scaffold
cd /Users/mydemellc./Desktop/TruAi/apps/phantom
npm start

# Gemini scaffold
cd /Users/mydemellc./Desktop/TruAi/apps/gemini
npm start

# Build Gateway
cd /Users/mydemellc./Desktop/TruAi/memory-gateway
./scripts/build_macos.sh

# Build all
cd /Users/mydemellc./Desktop/TruAi
./scripts/build-suite.sh
```

---

## Remaining (optional)

- [ ] Add Qdrant binary to `electron/bin/` or implement download fallback
- [ ] Keychain integration (keytar) for token storage
- [ ] Sign and notarize DMGs
- [ ] Copy Phantom/Gemini scaffolds to their repos when integrating
