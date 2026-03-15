# Phantom.ai App (scaffold)

Copy this to `DemeWebsolutions/Phantom.ai` repo.

- Uses `@truai/memory-client` with `strictFailClosed: false`, `retries: 3`
- Token in Keychain: `com.trinity.truai.token.phantom`
- Gateway URL default: `http://127.0.0.1:8010`
- Collections: read `phantom_episodes`, `shared_knowledge`; write `phantom_episodes` only

## Setup

```bash
npm install
npm start
```

## Build

```bash
npm run build
```
