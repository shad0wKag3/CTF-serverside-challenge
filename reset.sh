#!/bin/bash
# Reset Script for Vault-Tec Terminal CTF Lab

echo "[*] Resetting Vault-Tec Storage Node CTF Challenge..."

# 1. Clean host uploads directory (remove any uploaded artifacts and status marker)
find uploads/ -type f ! -name ".htaccess" ! -name "backup_manifest.txt" ! -name "node_status.txt" ! -name "vault_memo.txt" -delete 2>/dev/null
rm -f uploads/.node_seal_active 2>/dev/null
rm -f holotape.gif seal.gif 2>/dev/null

echo "[+] Local uploads directory cleaned."

# 2. If docker is running, recreate container to reset in-container storage
if command -v docker &> /dev/null; then
    if docker compose ps 2>/dev/null | grep -q "vaultdrive"; then
        echo "[*] Recreating Docker container..."
        docker compose down
        docker compose up -d
        echo "[+] Docker container reset to fresh state."
    fi
fi

echo "[✓] Challenge successfully reset to initial locked state!"

