# MiladMk Panel — API Documentation (v4.1)

A complete reference for the MiladMk Panel HTTP API. This document is written to be
copy-pasted to an AI assistant or a developer so they can integrate with the panel.

---

## 1. Base URL & Authentication

**Base URL**
```
http(s)://<PANEL_HOST>:<PANEL_PORT>/api
```
- `<PANEL_HOST>` — your panel domain or server IP.
- `<PANEL_PORT>` — the panel port chosen at install (shown after installation).

**Authentication**
Every request is authenticated with an **API token** you create in the panel under
**Settings → API**. Each token may be restricted to a specific caller IP (or `0.0.0.0/0`
for any IP).

- For **GET** endpoints the token is part of the URL path: `/api/{token}/...`
- For **POST** endpoints the token is sent as a form field named `token`.

If the token is missing/invalid, the API returns the panel's access-denied view.

**Content type for POST**: `application/x-www-form-urlencoded` (standard form fields).

---

## 2. Data model notes

- **Traffic unit**: internally stored in **MB**. When creating/editing a user you send
  `traffic` plus `type_traffic` = `gb` or `mb`. If `gb`, the panel multiplies by 1024.
- **Unlimited traffic**: send `traffic` = `0`.
- **Dates**: `expdate` uses `YYYY-MM-DD` and must be after today. Omit for no expiry.
- **multiuser**: max number of simultaneous logins (integer). `0` or a number.
- **connection_start**: if set (non-empty), the expiry countdown starts on first
  connection instead of immediately.

---

## 3. Endpoints

### 3.1 List all users
```
GET /api/{token}/listuser
```
Returns an array of users with their traffic objects.

### 3.2 List users by status
```
GET /api/{token}/listuser/{sort}
```
`{sort}` = `active` | `deactive` (or any status value).

### 3.3 Show one user
```
GET /api/{token}/user/{username}
```
Returns the user plus a trailing object with `port_direct`, `port_tls`,
`port_dropbear`, and `message: success`. If not found: `{"message":"Not Exist User"}`.

### 3.4 Create user
```
POST /api/adduser
```
Form fields:
| field | required | notes |
|-------|----------|-------|
| token | yes | API token |
| username | yes | unique |
| password | yes | |
| multiuser | yes | numeric, max concurrent logins |
| traffic | yes | numeric; `0` = unlimited |
| type_traffic | yes | `gb` or `mb` |
| expdate | no | `YYYY-MM-DD`, after today |
| connection_start | no | numeric; if set, expiry starts on first connect |
| email | no | |
| mobile | no | |
| desc | no | free text |

Response: `{"message":"success"}` or `{"message":"User Exist"}`.

### 3.5 Edit user
```
POST /api/edituser
```
Same fields as create (token, username, password, multiuser, traffic, type_traffic,
expdate?, email?, mobile?, desc?). Username identifies the record.

### 3.6 Delete user
```
POST /api/delete
```
Fields: `token`, `username`.

### 3.7 Activate / Deactivate user
```
POST /api/active
POST /api/deactive
```
Fields: `token`, `username`.

### 3.8 Reset user traffic
```
POST /api/retraffic
```
Fields: `token`, `username`. Resets used traffic counters to zero.

### 3.9 Renew user
```
POST /api/renewal
```
Fields: `token`, `username` (plus any renewal fields the panel expects, e.g. new
`expdate`/`traffic` if your build requires them).

### 3.10 Get user traffic
```
POST /api/traffic
```
Fields: `token`, `username`. Returns usage figures for the user.

### 3.11 Online users
```
GET /api/{token}/online
```
Returns currently connected users.

### 3.12 Kill a session
```
GET /api/{token}/kill/{method}/{param}
```
`{method}` / `{param}` identify what to kill (e.g. by username).

### 3.13 Backup
```
GET /api/{token}/backup
GET /api/{token}/backup/dl/{name}
```
First creates a backup (`{"message":"Backup Maked","link":"..."}`), second downloads it.

### 3.14 Filtering status
```
GET /api/{token}/filtering
```

---

## 4. NEW in v4.1 — Connection-link generators

These return ready-to-use client links for a given username. The host used in the
links respects the **Custom Host** setting if configured, otherwise the request host.

### 4.1 SSH link
```
GET /api/{token}/link/ssh/{type}/{username}
```
`{type}` = `direct` | `tls` | `dropbear`

Example response:
```json
{
  "message": "success",
  "username": "ali",
  "type": "direct",
  "link": "ssh://ali:secret@mc1.example.com:8080"
}
```

### 4.2 NPV Tunnel link
```
GET /api/{token}/link/npv/{type}/{username}
```
`{type}` = `direct` | `tls` | `dropbear`

Example response:
```json
{
  "message": "success",
  "username": "ali",
  "type": "direct",
  "link": "npvt-ssh://eyJzc2hDb25maWdUeXBlIjoiU1NILURpcmVjdCIs...=="
}
```
The link is the `npvt-ssh://` scheme followed by base64-encoded JSON describing the
SSH host/port/credentials and UDPGW settings — importable directly into NPV Tunnel.

### 4.3 All links at once
```
GET /api/{token}/links/{username}
```
Example response:
```json
{
  "message": "success",
  "username": "ali",
  "host": "mc1.example.com",
  "ssh": {
    "direct":   "ssh://ali:secret@mc1.example.com:8080",
    "dropbear": "ssh://ali:secret@mc1.example.com:2083"
  },
  "npv": {
    "direct":   "npvt-ssh://....",
    "dropbear": "npvt-ssh://...."
  }
}
```

---

## 5. Examples

### cURL — create a user
```bash
curl -X POST "http://PANEL:PORT/api/adduser" \
  -d "token=YOUR_TOKEN" \
  -d "username=ali" \
  -d "password=Secret123" \
  -d "multiuser=1" \
  -d "traffic=0" \
  -d "type_traffic=gb"
```

### cURL — get SSH direct link
```bash
curl "http://PANEL:PORT/api/YOUR_TOKEN/link/ssh/direct/ali"
```

### cURL — get all links
```bash
curl "http://PANEL:PORT/api/YOUR_TOKEN/links/ali"
```

### Python
```python
import requests
BASE = "http://PANEL:PORT/api"
TOKEN = "YOUR_TOKEN"

# create
requests.post(f"{BASE}/adduser", data={
    "token": TOKEN, "username": "ali", "password": "Secret123",
    "multiuser": 1, "traffic": 0, "type_traffic": "gb",
})

# get npv link
r = requests.get(f"{BASE}/{TOKEN}/link/npv/direct/ali")
print(r.json()["link"])
```

---

## 6. Error handling
- Invalid token → access-denied page (HTML), not JSON. Check the token first.
- Validation errors → HTTP 422 with Laravel's JSON error body.
- Not-found user on link endpoints → `{"message":"Not Exist User"}` with HTTP 404.

---

## 7. Notes for AI assistants
- Always send `token` in the body for POST and in the path for GET.
- Traffic `0` means unlimited.
- To hand a customer their connection string, prefer `GET /api/{token}/links/{username}`
  which returns SSH + NPV links in one call.
- The `npvt-ssh://` value is base64(JSON); do not URL-encode it further when displaying.
