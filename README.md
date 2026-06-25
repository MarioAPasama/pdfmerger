# PDF & Image Merger Service

Layanan API berbasis Web (PHP) dan skrip pengolah dokumen (Python) untuk mengonversi gambar (`.png`, `.jpg`, `.jpeg`) serta menggabungkannya bersama berkas PDF menjadi satu file PDF utuh yang terintegrasi.

Proyek ini telah dioptimalkan untuk menangani **banyak pengguna secara bersamaan (concurrency)**, aman dari celah keamanan unggahan file, memiliki sistem pembersihan sampah otomatis, dan pencatatan log harian yang teratur.

---

## 🛠️ Persyaratan Sistem & Instalasi

### 1. Kebutuhan Python
Proyek ini membutuhkan Python 3.x dan pustaka berikut. Jalankan perintah ini di terminal Anda:
```bash
pip install pillow pypdf
```

### 2. Kebutuhan Web Server
Pastikan Anda menggunakan server lokal (seperti **Laragon**, **XAMPP**, atau **Apache/Nginx**) dengan PHP versi 7.x atau lebih baru.

---

## ⚙️ Konfigurasi (`config.php`)

Anda dapat mengatur seluruh perilaku aplikasi secara terpusat di berkas [config.php](file:///d:/laragon/www/pdfmerger/config.php). Parameter yang tersedia meliputi:

*   **`python_path`**: Path absolut menuju executable Python di sistem Anda. Jika Python terdaftar di *Environment Path* sistem, Anda cukup mengisi `'python'`.
*   **`allowed_extensions`**: Daftar ekstensi file yang diizinkan untuk diunggah (default: `['pdf', 'png', 'jpg', 'jpeg']`).
*   **`max_file_size`**: Ukuran maksimal file per berkas yang boleh diunggah dalam satuan bytes (default: `20 MB`).
*   **`max_output_lifetime`**: Masa simpan berkas PDF hasil gabungan di folder `output/` dalam satuan detik (default: `3600` detik atau 1 jam). Lebih dari itu, file akan otomatis dihapus secara berkala saat ada request baru.
*   **`max_log_lifetime_days`**: Masa simpan berkas log harian dalam satuan hari (default: `90` hari atau 3 bulan).

---

## 📂 Struktur Direktori Proyek

```text
pdfmerger/
├── config.php          # Konfigurasi terpusat (Python path, limit, dll)
├── webservice.php      # API Endpoint PHP (menangani upload, validasi, cleanup)
├── main.py             # Skrip Python utama (konversi gambar & merge PDF)
├── main copy.py        # Salinan cadangan skrip Python
├── README.md           # Dokumentasi proyek (file ini)
├── logs/               # [Otomatis] Folder catatan riwayat log harian (retensi 3 bulan)
├── output/             # [Otomatis] Tempat hasil penggabungan PDF disimpan (retensi 1 jam)
└── uploads/            # [Otomatis] Folder unggahan berkas sementara (isolasi per request)
    └── .htaccess       # Keamanan Apache untuk memblokir eksekusi skrip berbahaya
```

---

## 🚀 Cara Penggunaan (API Endpoint)

Kirimkan HTTP POST Request ke `webservice.php` dengan tipe konten `multipart/form-data` menggunakan form field bernama `files[]`.

### Contoh Request Menggunakan cURL:
```bash
curl -X POST \
  -F "files[]=@C:\path\ke\file1.pdf" \
  -F "files[]=@C:\path\ke\gambar2.jpg" \
  http://localhost/pdfmerger/webservice.php
```

### Contoh Respons Sukses (JSON):
```json
{
  "message": "Berhasil digabung",
  "output_file": "output/hasil_gabungan_req_6a3ca1d277cd90.66611041.pdf",
  "url_download": "http://localhost/pdfmerger/output/hasil_gabungan_req_6a3ca1d277cd90.66611041.pdf"
}
```

---

## 🔒 Fitur Unggulan & Keamanan

1.  **Anti Race Condition (Konkurensi)**: Setiap request diproses dalam subfolder terisolasi (`uploads/req_<id>/`) menggunakan ID unik. File antarpengguna tidak akan saling menimpa meskipun diunggah pada waktu yang bersamaan.
2.  **Keamanan Unggah Berkas**: 
    *   Verifikasi whitelist ekstensi file di sisi PHP sebelum disimpan.
    *   Verifikasi ukuran berkas maksimal.
    *   Sanitasi nama file dari karakter aneh/berbahaya (`preg_replace`).
    *   Proteksi folder `uploads/` menggunakan `.htaccess` untuk mematikan mesin PHP/eksekusi skrip lain jika ada berkas mencurigakan yang lolos.
3.  **Pembersihan Otomatis (Cleanup)**: Berkas sementara dan file JSON perantara langsung dihapus seketika setelah proses penggabungan selesai. Berkas hasil akhir juga akan terhapus otomatis setelah 1 jam untuk menghemat kapasitas disk server.
4.  **Log Harian Terstruktur**: Log harian disimpan di folder [logs/](file:///d:/laragon/www/pdfmerger/logs) dengan penamaan harian (misal: `log_YYYY-MM-DD.log`) dan fitur rotasi log otomatis yang hanya menyimpan riwayat selama 3 bulan terakhir.