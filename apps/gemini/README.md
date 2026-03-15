# Gemini.ai App (scaffold)

Copy this to `DemeWebsolutions/Gemini.ai` repo.

- Uses `@truai/memory-client` with `strictFailClosed: true`, `retries: 0`
- Token in Keychain: `com.trinity.truai.token.gemini`
- Gateway URL: WireGuard IP (e.g. `http://10.100.0.1:8010`)
- Requires WireGuard connected; zone must be `wg`
- Collections: read `gemini_episodes`, `shared_knowledge`; write `gemini_episodes` only

## Setup

```bash
npm install
npm start
```

## Build

```bash
npm run build
```
