<?php
session_start();

// ==========================================
// 1. KONFIGURASI & AUTO-SETUP DATABASE
// ==========================================
$host = 'localhost';
$user = 'root';
$pass = '';

try {
    // Konek ke MySQL
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Auto-create database & gunakan
    $pdo->exec("CREATE DATABASE IF NOT EXISTS siakad_mini");
    $pdo->exec("USE siakad_mini");

    // Auto-create tabel users
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(20) DEFAULT 'operator'
    )");

    // Auto-create tabel dosen
    $pdo->exec("CREATE TABLE IF NOT EXISTS dosen (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nidn VARCHAR(20) NOT NULL,
        nama VARCHAR(100) NOT NULL,
        program_studi VARCHAR(50) NOT NULL,
        status VARCHAR(20) DEFAULT 'aktif',
        deleted_at TIMESTAMP NULL
    )");

    // Auto-insert akun Admin default jika belum ada
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    if ($stmt->fetchColumn() == 0) {
        $hash = password_hash('rahasia123', PASSWORD_BCRYPT);
        $pdo->exec("INSERT INTO users (username, password_hash, role) VALUES ('admin', '$hash', 'admin')");
    }

} catch (PDOException $e) {
    die("Koneksi / Setup Database Gagal: " . $e->getMessage());
}

// ==========================================
// 2. LOGIC ROUTING & AUTENTIKASI
// ==========================================
$error = '';

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Handle Login Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        header('Location: index.php');
        exit;
    } else {
        $error = 'Username atau password salah!';
    }
}

// Status Login
$is_logged_in = isset($_SESSION['user_id']);

// Jika sudah login, ambil data dosen
$dosen_list = [];
if ($is_logged_in) {
    $dosen_list = $pdo->query("SELECT * FROM dosen WHERE deleted_at IS NULL ORDER BY id DESC")->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIAKAD Mini - Single File</title>
    <style>
        /* CSS Terpadu */
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --bg: #f3f4f6;
            --surface: #ffffff;
            --text: #1f2937;
            --border: #e5e7eb;
            --danger: #ef4444;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', system-ui, sans-serif; }
        body { background-color: var(--bg); color: var(--text); line-height: 1.6; display: flex; flex-direction: column; min-height: 100vh; }
        
        .container { max-width: 1000px; margin: 2rem auto; padding: 0 1rem; width: 100%; }
        .login-container { max-width: 400px; margin: auto; padding: 2rem; }
        
        .card { background: var(--surface); padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        h2 { margin-bottom: 1.5rem; color: var(--text); }
        
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        input { width: 100%; padding: 0.75rem; border: 1px solid var(--border); border-radius: 4px; }
        
        button { background: var(--primary); color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; width: 100%; }
        button:hover { background: var(--primary-hover); }
        button.btn-inline { width: auto; margin-bottom: 1rem; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border); }
        th { background-color: var(--bg); font-weight: 600; }
        
        .badge { padding: 0.25rem 0.5rem; border-radius: 999px; font-size: 0.875rem; background: #dcfce3; color: #166534; }
        .alert { padding: 1rem; background: #fee2e2; color: var(--danger); border-radius: 4px; margin-bottom: 1rem; }
        .flex-between { display: flex; justify-content: space-between; align-items: center; }
        .text-danger { color: var(--danger); text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

    <?php if (!$is_logged_in): ?>
    <div class="login-container">
        <div class="card">
            <h2>Login SIAKAD</h2>
            <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" placeholder="Masukkan username" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Masukkan password" required>
                </div>
                <button type="submit" name="login">Masuk</button>
            </form>
            <p style="margin-top: 1rem; font-size: 0.85rem; color: #6b7280; text-align: center;">
                Default login: <b>admin</b> / <b>rahasia123</b>
            </p>
        </div>
    </div>

    <?php else: ?>
    <div class="container">
        <div class="card">
            <div class="flex-between">
                <h2>Data Dosen SIAKAD</h2>
                <a href="?action=logout" class="text-danger">Logout</a>
            </div>
            
            <button class="btn-inline" onclick="alert('Fitur tambah dosen bisa ditambahkan dengan form POST ke file ini.')">+ Tambah Dosen</button>
            
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>NIDN</th>
                            <th>Nama Lengkap</th>
                            <th>Program Studi</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($dosen_list)): ?>
                            <tr><td colspan="5" style="text-align: center; color: #6b7280;">Belum ada data dosen.</td></tr>
                        <?php else: ?>
                            <?php foreach($dosen_list as $dosen): ?>
                            <tr>
                                <td><?= htmlspecialchars($dosen['nidn']) ?></td>
                                <td><?= htmlspecialchars($dosen['nama']) ?></td>
                                <td><?= htmlspecialchars($dosen['program_studi']) ?></td>
                                <td><span class="badge"><?= htmlspecialchars($dosen['status']) ?></span></td>
                                <td>
                                    <a href="#" style="text-decoration: none; color: var(--primary);">Edit</a> | 
                                    <a href="#" style="text-decoration: none;" class="text-danger">Hapus</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

</body>
</html>