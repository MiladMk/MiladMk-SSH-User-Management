#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# MiladMk Panel — automatic backup + Telegram delivery.
# Based on the user's tested script. Reads config from /etc/mk-backup.conf so the
# panel can manage the values without editing this file.
#
# /etc/mk-backup.conf format (KEY=VALUE per line):
#   IP=127.0.0.1:8081
#   API_KEY=xxxxxxxx
#   BACKUP_NAME=myserver
#   BOT_TOKEN=123:abc
#   CHAT_ID=123456

import os
import requests
from datetime import datetime

CONF = "/etc/mk-backup.conf"
BACKUP_DIR = "/tmp"

def load_conf(path):
    cfg = {}
    try:
        with open(path, "r") as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith("#") or "=" not in line:
                    continue
                k, v = line.split("=", 1)
                cfg[k.strip()] = v.strip()
    except FileNotFoundError:
        pass
    return cfg

def main():
    cfg = load_conf(CONF)
    IP = cfg.get("IP", "")
    API_KEY = cfg.get("API_KEY", "")
    BACKUP_NAME = cfg.get("BACKUP_NAME", "backup")
    BOT_TOKEN = cfg.get("BOT_TOKEN", "")
    CHAT_ID = cfg.get("CHAT_ID", "")

    if not all([IP, API_KEY, BOT_TOKEN, CHAT_ID]):
        print("Missing config values in", CONF)
        raise SystemExit(1)

    # 1) create backup via panel API
    create_url = f"http://{IP}/api/{API_KEY}/backup"
    try:
        r = requests.get(create_url, timeout=120)
        r.raise_for_status()
        data = r.json()
        if "link" not in data:
            raise Exception("Download link not found in API response")
        link = data["link"]
        if not link.startswith("http"):
            link = "http://" + link
        print("Download URL:", link)
    except Exception as e:
        print("Failed to create backup:", e)
        raise SystemExit(1)

    # 2) custom filename
    ts = datetime.now().strftime("%Y-%m-%d---%H-%M-%S")
    filename = f"{BACKUP_NAME}-{ts}.sql"
    filepath = os.path.join(BACKUP_DIR, filename)

    # 3) download backup
    try:
        r = requests.get(link, stream=True, timeout=600)
        r.raise_for_status()
        with open(filepath, "wb") as f:
            for chunk in r.iter_content(chunk_size=8192):
                if chunk:
                    f.write(chunk)
        print("Backup downloaded:", filepath)
    except Exception as e:
        print("Download failed:", e)
        raise SystemExit(1)

    # 4) send to Telegram
    try:
        tg = f"https://api.telegram.org/bot{BOT_TOKEN}/sendDocument"
        with open(filepath, "rb") as f:
            requests.post(
                tg,
                data={"chat_id": CHAT_ID, "caption": f"Backup: {filename}"},
                files={"document": (filename, f)},
                timeout=600,
            ).raise_for_status()
        print("Sent to Telegram")
    except Exception as e:
        print("Telegram upload failed:", e)
        raise SystemExit(1)

    # 5) delete local file
    try:
        os.remove(filepath)
        print("Local file deleted")
    except Exception as e:
        print("Delete failed:", e)

if __name__ == "__main__":
    main()
