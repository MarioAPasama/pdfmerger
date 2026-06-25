<?php
header("Content-Type: application/json");

// Muat konfigurasi keamanan dan cleanup secara terpusat di awal
$config_file = __DIR__ . "/config.php";
$config = file_exists($config_file) ? include($config_file) : [];

// Validasi Basic Authentication jika diaktifkan di konfigurasi
if (isset($config['basic_auth']) && $config['basic_auth']['enabled']) {
    $username = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : '';
    $password = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';
    
    if ($username !== $config['basic_auth']['username'] || $password !== $config['basic_auth']['password']) {
        header('WWW-Authenticate: Basic realm="PDF Merger API"');
        http_response_code(401);
        echo json_encode(["error" => "Unauthorized. Kredensial autentikasi salah atau tidak terkirim."]);
        exit;
    }
}

// Helper function to recursively delete a directory
function rmdir_recursive($dir) {
    if (is_dir($dir)) {
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            (is_dir($path)) ? rmdir_recursive($path) : unlink($path);
        }
        return rmdir($dir);
    }
    return false;
}

// Helper function to delete files older than max_age in a directory
function clean_old_files($directory, $max_age_seconds) {
    if (!is_dir($directory)) return;
    $files = glob($directory . DIRECTORY_SEPARATOR . 'hasil_gabungan_*.pdf');
    $now = time();
    foreach ($files as $file) {
        if (is_file($file)) {
            if ($now - filemtime($file) >= $max_age_seconds) {
                unlink($file);
            }
        }
    }
}

// Helper function to delete log files older than X days
function clean_old_logs($directory, $lifetime_days) {
    if (!is_dir($directory)) return;
    $files = glob($directory . DIRECTORY_SEPARATOR . 'log_*.log');
    $now = time();
    $max_age_seconds = $lifetime_days * 24 * 60 * 60; // Konversi hari ke detik
    foreach ($files as $file) {
        if (is_file($file)) {
            if ($now - filemtime($file) >= $max_age_seconds) {
                unlink($file);
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['files'])) {
        http_response_code(400);
        echo json_encode(["error" => "Form-data 'files' belum dikirim."]);
        exit;
    }

    $raw_files_names = isset($_FILES['files']['name']) ? $_FILES['files']['name'] : [];
    $uploaded_files = $_FILES['files'];
    
    // Urutkan array $_FILES['files'] berdasarkan key/indeks agar sesuai dengan index array yang dikirim
    // oleh client (seperti files[0], files[1], files[2]...)
    if (is_array($uploaded_files['name'])) {
        ksort($uploaded_files['name']);
        ksort($uploaded_files['tmp_name']);
        ksort($uploaded_files['type']);
        ksort($uploaded_files['error']);
        ksort($uploaded_files['size']);
    }

    $upload_dir = __DIR__ . "/uploads/";
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    $allowed_extensions = isset($config['allowed_extensions']) ? $config['allowed_extensions'] : ['pdf', 'png', 'jpg', 'jpeg'];
    $max_file_size = isset($config['max_file_size']) ? $config['max_file_size'] : 20 * 1024 * 1024;
    $max_output_lifetime = isset($config['max_output_lifetime']) ? $config['max_output_lifetime'] : 3600;
    $max_log_lifetime_days = isset($config['max_log_lifetime_days']) ? $config['max_log_lifetime_days'] : 90;

    // Bersihkan file PDF lama di folder output secara otomatis (Lazy Cleanup)
    clean_old_files(__DIR__ . DIRECTORY_SEPARATOR . "output", $max_output_lifetime);

    // Bersihkan file log lama di folder logs secara otomatis
    $log_dir = __DIR__ . DIRECTORY_SEPARATOR . "logs";
    if (!is_dir($log_dir)) mkdir($log_dir, 0777, true);
    clean_old_logs($log_dir, $max_log_lifetime_days);

    // Validasi semua file sebelum diproses menggunakan key yang sudah diurutkan
    foreach ($uploaded_files['name'] as $key => $name) {
        $orig_filename = basename($name);
        
        // 1. Cek upload error
        if ($uploaded_files['error'][$key] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(["error" => "Gagal mengunggah file '{$orig_filename}' (Kode error: " . $uploaded_files['error'][$key] . ")"]);
            exit;
        }

        // 2. Cek tipe file (ekstensi)
        $ext = strtolower(pathinfo($orig_filename, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_extensions)) {
            http_response_code(400);
            echo json_encode(["error" => "Format file tidak didukung: '{$orig_filename}'. Hanya file " . implode(', ', $allowed_extensions) . " yang diizinkan."]);
            exit;
        }

        // 3. Cek ukuran file
        if ($uploaded_files['size'][$key] > $max_file_size) {
            $max_mb = round($max_file_size / (1024 * 1024), 2);
            http_response_code(400);
            echo json_encode(["error" => "Ukuran file '{$orig_filename}' melebihi batas maksimal {$max_mb} MB."]);
            exit;
        }
    }

    // Generate Request ID unik untuk mencegah tabrakan data (Race Condition)
    $request_id = uniqid('req_', true);
    $request_dir = $upload_dir . $request_id . DIRECTORY_SEPARATOR;
    if (!is_dir($request_dir)) mkdir($request_dir, 0777, true);

    $saved_paths = [];
    $counter = 0;

    // Simpan file ke dalam subfolder unik sesuai urutan index array
    foreach ($uploaded_files['name'] as $key => $name) {
        $orig_filename = basename($name);
        // Sanitasi nama file dan gunakan counter untuk menjaga keunikan dan urutan
        $sanitized_name = preg_replace("/[^a-zA-Z0-9\._-]/", "_", $orig_filename);
        $filename = $counter . "_" . $sanitized_name;
        $target_path = $request_dir . $filename;
        if (move_uploaded_file($uploaded_files['tmp_name'][$key], $target_path)) {
            $saved_paths[] = "uploads/" . $request_id . "/" . $filename;
        }
        $counter++;
    }

    // Simpan input.json dengan nama unik
    $input_json_file = "uploads/input_" . $request_id . ".json";
    file_put_contents($input_json_file, json_encode($saved_paths, JSON_PRETTY_PRINT));

    // Nama output file unik
    $output_filename = "hasil_gabungan_" . $request_id . ".pdf";

    // Tentukan path python secara dinamis dari config atau pencarian otomatis
    $python_path = "python"; // Default fallback

    if (isset($config['python_path']) && !empty($config['python_path'])) {
        // Gunakan path dari config jika file tersebut ada di sistem
        if (file_exists($config['python_path'])) {
            $python_path = $config['python_path'];
        } else {
            // Coba periksa apakah command di config terdaftar di system PATH (misal 'python')
            $test_cmd = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? "where " . escapeshellarg($config['python_path']) : "which " . escapeshellarg($config['python_path']);
            $test_out = @shell_exec($test_cmd);
            if (!empty($test_out)) {
                $python_path = $config['python_path'];
            } else {
                // Jika tidak ditemukan, coba cari alternatif di system PATH
                $possible_commands = ["python", "python3", "py"];
                foreach ($possible_commands as $cmd) {
                    $test_cmd = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? "where " . escapeshellarg($cmd) : "which " . escapeshellarg($cmd);
                    $test_out = @shell_exec($test_cmd);
                    if (!empty($test_out)) {
                        $python_path = $cmd;
                        break;
                    }
                }
            }
        }
    } else {
        // Jika tidak ada konfigurasi, cari default di system PATH
        $possible_commands = ["python", "python3", "py"];
        foreach ($possible_commands as $cmd) {
            $test_cmd = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? "where " . escapeshellarg($cmd) : "which " . escapeshellarg($cmd);
            $test_out = @shell_exec($test_cmd);
            if (!empty($test_out)) {
                $python_path = $cmd;
                break;
            }
        }
    }

    $command = escapeshellcmd("$python_path main.py $input_json_file $output_filename") . " 2>&1";
    $output = shell_exec($command);
    
    // Tentukan file log hari ini
    $log_filename = $log_dir . DIRECTORY_SEPARATOR . "log_" . date("Y-m-d") . ".log";

    // Log debug global (di-append dengan timestamp agar menyimpan seluruh riwayat aktivitas)
    $log_timestamp = date("Y-m-d H:i:s");
    $log_entry = "=================================== [{$log_timestamp}] ===================================\n";
    $log_entry .= "Raw Uploaded Names (Order from client): " . json_encode($raw_files_names) . "\n";
    $log_entry .= "Saved Paths (After ksort): " . json_encode($saved_paths) . "\n";
    $log_entry .= "Python Output:\n" . $output . "\n\n";
    file_put_contents($log_filename, $log_entry, FILE_APPEND);

    // Hapus file debug.txt lama di root directory jika ada
    $old_debug_file = __DIR__ . DIRECTORY_SEPARATOR . "debug.txt";
    if (file_exists($old_debug_file)) {
        @unlink($old_debug_file);
    }

    // Cek apakah output tercipta
    $hasil_file = "output/" . $output_filename;
    $response = [];

    if (file_exists($hasil_file)) {
        $response = [
            "message" => "Berhasil digabung",
            "output_file" => $hasil_file,
            "url_download" => "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/$hasil_file"
        ];
    } else {
        http_response_code(500);
        $response = [
            "error" => "Gagal membuat file PDF.",
            "debug" => $output
        ];
    }

    // Pembersihan file sementara setelah proses selesai
    if (file_exists($input_json_file)) {
        unlink($input_json_file);
    }
    if (is_dir($request_dir)) {
        rmdir_recursive($request_dir);
    }

    echo json_encode($response);
}
?>
