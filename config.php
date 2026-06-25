<?php
// Konfigurasi aplikasi PDF Merger

return [
    // Jalur (path) ke executable Python.
    // Secara default menggunakan path Windows Anda. Jika di-deploy ke Docker/Linux,
    // di-override menggunakan environment variable PYTHON_PATH=python3.
    'python_path' => getenv('PYTHON_PATH') ?: 'C:\\Users\\Administrator\\AppData\\Local\\Programs\\Python\\Python312\\python.exe',

    // Ekstensi file yang diizinkan untuk diunggah
    'allowed_extensions' => getenv('ALLOWED_EXTENSIONS') ? explode(',', getenv('ALLOWED_EXTENSIONS')) : ['pdf', 'png', 'jpg', 'jpeg', 'docx', 'txt', 'xlsx'],

    // Batas maksimal ukuran file (dalam bytes). Default: 20 MB
    'max_file_size' => getenv('MAX_FILE_SIZE') !== false ? (int)getenv('MAX_FILE_SIZE') : 20 * 1024 * 1024,

    // Usia maksimal file output disimpan (dalam detik). Default: 1 jam (3600 detik)
    'max_output_lifetime' => getenv('MAX_OUTPUT_LIFETIME') !== false ? (int)getenv('MAX_OUTPUT_LIFETIME') : 3600,

    // Masa simpan file log (dalam hari). Default: 90 hari (3 bulan)
    'max_log_lifetime_days' => getenv('MAX_LOG_LIFETIME_DAYS') !== false ? (int)getenv('MAX_LOG_LIFETIME_DAYS') : 90,

    // Konfigurasi Basic Authentication untuk pengamanan API
    'basic_auth' => [
        'enabled' => getenv('BASIC_AUTH_ENABLED') !== false ? (getenv('BASIC_AUTH_ENABLED') === 'true') : false,
        'username' => getenv('BASIC_AUTH_USER') ?: 'admin',
        'password' => getenv('BASIC_AUTH_PASS') ?: 'admin_secret_123',
    ],
];
?>
