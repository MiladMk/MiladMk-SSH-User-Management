<p align="center">
<img width="160" height="160" alt="MiladMk Panel" src="https://raw.githubusercontent.com/MiladMk/MiladMk-SSH-User-Management/main/xlogo.png">
</p>
<h1 align="center">MiladMk Panel</h1>
<h6 align="center">SSH + Sing-box User Management Panel</h6>

## Introduction
**MiladMk Panel** is a lightweight web app for managing SSH and Sing-box accounts. Create users, apply traffic/expiry limits, monitor usage, and hand out connection links.

## Protocols
`SSH-DIRECT` `SSH-TLS` `SSH-DROPBEAR` `SSH-DROPBEAR-TLS` `SSH-WEBSOCKET` `SSH-WEBSOCKET-TLS` `VMess ws` `VLess Reality` `Hysteria2` `Tuic` `Shadowsocks`

## Features
- Unlimited users, traffic & expiry limits, concurrent-session limits
- Online users, backup/restore, Telegram bot, API
- Custom panel port, fake address, IP blacklist
- **Independent Cloudflare IP rotation (self-hosted)**
- **Custom host for connection links**
- **NPV Tunnel**
- Sing-box kernel

## Install
Recommended OS: **Ubuntu 24.04 / 26.04** (Debian supported).
```
bash <(curl -Ls https://raw.githubusercontent.com/MiladMk/MiladMk-SSH-User-Management/main/install.sh --ipv4)
```

## Manage
```
miladmk
```

## License
Released under the [Unlicense](./LICENSE).
