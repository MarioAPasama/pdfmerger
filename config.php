<?php
// Konfigurasi aplikasi PDF Merger

return [
    // Jalur (path) ke executable Python.
    // Biarkan 'python' jika Python terdaftar di Environment Path sistem Anda.
    // Jika tidak, Anda dapat mengisinya dengan path absolut ke python.exe Anda.
    // Contoh Windows: 'C:\\Users\\NamaUser\\AppData\\Local\\Programs\\Python\\Python312\\python.exe'
    // Contoh Linux: '/usr/bin/python3'
    'python_path' => 'C:\\Users\\Administrator\\AppData\\Local\\Programs\\Python\\Python312\\python.exe',

    // Ekstensi file yang diizinkan untuk diunggah
    'allowed_extensions' => ['pdf', 'png', 'jpg', 'jpeg', 'docx', 'txt', 'xlsx'],

    // Batas maksimal ukuran file (dalam bytes). Default: 20 MB
    'max_file_size' => 20 * 1024 * 1024,

    // Usia maksimal file output disimpan (dalam detik). Default: 1 jam (3600 detik)
    'max_output_lifetime' => 3600,

    // Masa simpan file log (dalam hari). Default: 90 hari (3 bulan)
    'max_log_lifetime_days' => 90,

    // Konfigurasi Basic Authentication untuk pengamanan API
    'basic_auth' => [
        'enabled' => false, // Set ke true untuk mengaktifkan pengamanan Basic Auth
        'username' => 'admin',
        'password' => 'admin_secret_123',
    ],
];
?>
