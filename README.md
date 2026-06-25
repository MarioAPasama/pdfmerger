# PDF, Image, Word, Text, & Excel Merger Service

Layanan microservice API berbasis Web (PHP) dan skrip pengolah dokumen (Python) untuk mengonversi gambar (`.png`, `.jpg`, `.jpeg`), dokumen Word (`.docx`), file teks (`.txt`), serta dokumen Excel (`.xlsx`) dan menggabungkannya bersama berkas PDF menjadi satu file PDF utuh yang terintegrasi.

Proyek ini telah dioptimalkan dengan **Konversi Paralel (Multithreading)** di Python, **Kompresi Gambar Otomatis**, sistem pengamanan **Basic Authentication**, pembatasan tipe/ukuran file, penanganan **HTTP Status Code** yang tepat saat terjadi kegagalan, serta kompatibilitas penuh untuk dijalankan di **Windows (Laragon/XAMPP)** maupun **Linux/Docker**.

---

## 🚀 Fitur Unggulan

1. **Konversi Paralel (Multithreading)**: Memproses konversi berkas input secara bersamaan menggunakan `ThreadPoolExecutor` di Python untuk meningkatkan performa secara signifikan.
2. **Kompresi Gambar Otomatis**: Secara otomatis mendeteksi gambar berukuran besar (dimensi >1200px) dan memperkecil ukurannya dengan resample filter `LANCZOS` sebelum diubah ke PDF demi menghemat kapasitas penyimpanan.
3. **Pengamanan API (Basic Authentication)**: Dapat diaktifkan secara dinamis via konfigurasi atau environment variable untuk melindungi endpoint API dari akses tidak sah.
4. **Respon HTTP Status Code Tepat**:
   * `200 OK`: Penggabungan sukses.
   * `400 Bad Request`: Validasi gagal (ekstensi tidak didukung, ukuran file melebihi batas, atau file upload error).
   * `401 Unauthorized`: Kredensial Basic Auth salah atau tidak dikirimkan.
   * `500 Internal Server Error`: Kegagalan eksekusi skrip pengolah Python atau pembuatan PDF.
5. **Anti Race Condition**: Setiap request diproses dalam folder terisolasi (`uploads/req_<id>/`) menggunakan ID unik agar data antar-pengguna tidak saling menimpa.
6. **Pembersihan Otomatis (Lazy Cleanup)**: Berkas sampah sementara langsung dihapus setelah penggabungan. Berkas PDF hasil akhir dibersihkan otomatis setelah 1 jam untuk menghemat ruang disk.

---

## 🛠️ Persyaratan Sistem & Instalasi

Layanan ini dapat dijalankan dengan dua cara: menggunakan **Docker (Sangat Direkomendasikan)** atau secara **Manual**.

### Opsi A: Menggunakan Docker (Windows & Linux - Direkomendasikan)
Dengan Docker, seluruh dependensi (PHP, Apache, Python, dan library pendukung) sudah diisolasi di dalam container. Anda tidak perlu memasang PHP atau Python secara lokal.

1. **Persyaratan**: Pastikan Docker & Docker Compose sudah terpasang dan berjalan di sistem Anda.
2. **Jalankan Layanan**:
   Buka terminal di folder proyek ini dan jalankan perintah:
   ```bash
   docker compose up -d --build
   ```
3. **Akses API**: API dapat diakses di `http://localhost:8080/`

---

### Opsi B: Instalasi Manual (Windows & Linux)

#### 1. Persyaratan Python & Dependensi
Pastikan Python 3.x telah terpasang. Instal seluruh library yang diperlukan dengan perintah berikut:
* **Windows (cmd/PowerShell)** atau **Linux/macOS (Terminal)**:
  ```bash
  pip install pillow pypdf openpyxl mammoth xhtml2pdf fpdf2 python-docx
  ```

#### 2. Kebutuhan Web Server
* Jalankan server lokal seperti **Laragon**, **XAMPP**, atau **Apache/Nginx**.
* Pastikan PHP versi 7.x atau lebih baru telah aktif.

---

## ⚙️ Konfigurasi (`config.php`)

Anda dapat mengatur seluruh perilaku aplikasi secara terpusat di berkas [config.php](config.php). Konfigurasi ini mendukung pembacaan via **Environment Variables** (terutama untuk Docker) dengan fallback otomatis ke nilai lokal (Windows/Laragon):

* **`python_path`**: Jalur (path) ke executable Python.
  * *Docker/Linux*: `python3` (disetel otomatis via env)
  * *Windows/Manual*: Sesuaikan dengan path instalasi Python Anda (contoh: `C:\\Users\\Administrator\\AppData\\Local\\Programs\\Python\\Python312\\python.exe`).
* **`allowed_extensions`**: Daftar ekstensi file yang diizinkan (default: `pdf`, `png`, `jpg`, `jpeg`, `docx`, `txt`, `xlsx`).
* **`max_file_size`**: Ukuran maksimal per file dalam bytes (default: `20 MB`).
* **`max_output_lifetime`**: Masa simpan PDF hasil gabungan di folder `output/` sebelum dihapus otomatis (default: `3600` detik / 1 jam).
* **`max_log_lifetime_days`**: Retensi file log harian dalam hitungan hari (default: `90` hari).
* **`basic_auth`**:
  * `enabled`: Mengaktifkan/menonaktifkan Basic Auth (`true` / `false`).
  * `username`: Username untuk autentikasi (default: `admin`).
  * `password`: Password untuk autentikasi (default: `admin_secret_123`).

---

## 📂 Struktur Direktori Proyek

```text
pdfmerger/
├── config.php          # Konfigurasi terpusat (mendukung env vars & fallback lokal)
├── index.php           # API Endpoint PHP (upload, validasi, auth, cleanup, HTTP status)
├── main.py             # Skrip Python utama (konversi paralel & image compression & merge PDF)
├── Dockerfile          # Spesifikasi image Docker (PHP 8.1 Apache + Python 3 + pip)
├── docker-compose.yml  # Konfigurasi container, port mapping (8080), dan env variables
├── README.md           # Dokumentasi proyek (file ini)
├── logs/               # [Otomatis] Folder catatan riwayat log aktivitas harian
├── output/             # [Otomatis] Folder penyimpanan berkas PDF hasil penggabungan
└── uploads/            # [Otomatis] Folder unggahan berkas sementara terisolasi per request
    └── .htaccess       # Keamanan Apache untuk memblokir eksekusi skrip berbahaya
```

---

## 🚀 Cara Penggunaan (API Endpoint)

Kirimkan HTTP POST Request ke endpoint API dengan tipe konten `multipart/form-data` menggunakan form field bernama `files[]`.

### 📌 Penting: Pengaturan Urutan File (Ordering)
Agar urutan penggabungan dokumen tepat seperti yang diharapkan, **sangat disarankan** untuk mengirimkan field `files` dengan indeks array secara eksplisit (seperti `files[0]`, `files[1]`, `files[2]`, dst.). PHP akan melakukan `ksort` untuk mengurutkan file berdasarkan indeks tersebut sebelum diproses oleh Python.

### Contoh Request Menggunakan cURL:

#### Tanpa Basic Authentication:
```bash
curl -X POST \
  -F "files[0]=@/path/to/document.pdf" \
  -F "files[1]=@/path/to/image.jpg" \
  -F "files[2]=@/path/to/report.xlsx" \
  http://localhost:8080/
```

#### Dengan Basic Authentication (jika diaktifkan):
```bash
curl -X POST -u admin:admin_secret_123 \
  -F "files[0]=@/path/to/document.pdf" \
  -F "files[1]=@/path/to/report.docx" \
  http://localhost:8080/
```

### Contoh Respons Sukses (`200 OK`):
```json
{
  "message": "Berhasil digabung",
  "output_file": "output/hasil_gabungan_req_6a3ca1d277cd90.66611041.pdf",
  "url_download": "http://localhost:8080/output/hasil_gabungan_req_6a3ca1d277cd90.66611041.pdf"
}
```

### Contoh Respons Gagal (`400 Bad Request` - Validasi Gagal):
```json
{
  "error": "Format file tidak didukung: 'document.exe'. Hanya file pdf, png, jpg, jpeg, docx, txt, xlsx yang diizinkan."
}
```

### Contoh Respons Gagal (`401 Unauthorized` - Auth Salah/Tidak Ada):
```json
{
  "error": "Unauthorized. Kredensial autentikasi salah atau tidak terkirim."
}
```

### Contoh Respons Gagal (`500 Internal Server Error` - Kesalahan Pemrosesan):
```json
{
  "error": "Gagal membuat file PDF.",
  "debug": "Traceback... ModuleNotFoundError..."
}
```