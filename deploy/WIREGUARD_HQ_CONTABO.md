# WireGuard VPN: HQ ↔ Contabo VPS

Secure tunnel between Local TruAi HQ and Contabo VPS for ROMA + ITC traffic.

---

## Architecture

```
Local HQ (Trusted)                    Contabo VPS (Cloud)
┌─────────────────────┐              ┌─────────────────────┐
│ TruAi Core          │              │ Gemini.ai           │
│ Phantom.ai          │   WireGuard  │ Plesk               │
│ ROMA                │◄───────────►│ 154.53.54.169       │
└─────────────────────┘  10.0.0.0/24 └─────────────────────┘
```

---

## 1. Install WireGuard (Both Sides)

### On Contabo VPS (Ubuntu/Debian)

```bash
sudo apt update
sudo apt install -y wireguard
```

### On Local HQ (macOS)

```bash
# Via Homebrew
brew install wireguard-tools
# Or download from App Store: WireGuard
```

---

## 2. Generate Keys (Both Sides)

```bash
# Create directory
mkdir -p /etc/wireguard
cd /etc/wireguard

# Generate server key pair (Contabo)
wg genkey | tee server_private.key | wg pubkey > server_public.key
chmod 600 server_private.key

# Generate client key pair (HQ)
wg genkey | tee hq_private.key | wg pubkey > hq_public.key
chmod 600 hq_private.key
```

---

## 3. Contabo Server Config

**File: `/etc/wireguard/wg0.conf`** (on Contabo)

```ini
[Interface]
Address = 10.0.0.1/24
ListenPort = 51820
PrivateKey = <SERVER_PRIVATE_KEY>
PostUp = iptables -A FORWARD -i wg0 -j ACCEPT; iptables -t nat -A POSTROUTING -o eth0 -j MASQUERADE
PostDown = iptables -D FORWARD -i wg0 -j ACCEPT; iptables -t nat -D POSTROUTING -o eth0 -j MASQUERADE

[Peer]
PublicKey = <HQ_PUBLIC_KEY>
AllowedIPs = 10.0.0.2/32
```

Replace:
- `<SERVER_PRIVATE_KEY>` = content of `server_private.key`
- `<HQ_PUBLIC_KEY>` = content of `hq_public.key`
- `eth0` = your main interface (check with `ip a`)

---

## 4. HQ Client Config

**File: `hq-wg0.conf`** (on local machine)

```ini
[Interface]
Address = 10.0.0.2/24
PrivateKey = <HQ_PRIVATE_KEY>
DNS = 1.1.1.1

[Peer]
PublicKey = <SERVER_PUBLIC_KEY>
Endpoint = 154.53.54.169:51820
AllowedIPs = 10.0.0.0/24
PersistentKeepalive = 25
```

Replace:
- `<HQ_PRIVATE_KEY>` = content of `hq_private.key`
- `<SERVER_PUBLIC_KEY>` = content of `server_public.key`

---

## 5. Start WireGuard

### On Contabo

```bash
sudo wg-quick up wg0
# Enable on boot
sudo systemctl enable wg-quick@wg0
```

### On HQ (macOS)

```bash
# Import config in WireGuard app, or:
sudo wg-quick up hq-wg0
```

---

## 6. Open Firewall on Contabo

```bash
# UFW
sudo ufw allow 51820/udp
sudo ufw allow from 10.0.0.0/24
sudo ufw reload
```

---

## 7. Restrict Gemini ITC to VPN Only

Update firewall so ITC (port 8443) only accepts from VPN:

```bash
sudo ufw allow from 10.0.0.0/24 to any port 8443
sudo ufw deny 8443
sudo ufw reload
```

---

## 8. Verify

```bash
# On Contabo
sudo wg show

# On HQ - ping Contabo VPN IP
ping 10.0.0.1
```

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| No handshake | Check UDP 51820 open; verify keys |
| Ping fails | Check AllowedIPs; enable IP forwarding on server |
| Interface not found | `eth0` may be `ens3` or `eth1` — adjust PostUp/PostDown |

---

*My Deme, LLC © 2026 — TruAi ROMA Security*
