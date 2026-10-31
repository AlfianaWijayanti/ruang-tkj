
<?php
session_start();
require_once __DIR__ . '/config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    if (!in_array($role, ['admin', 'guru', 'siswa'], true)) {
        $error = 'Silakan pilih jenis akun.';
    } else {

        $stmt = $pdo->prepare(
            "SELECT * FROM users WHERE username = ? LIMIT 1"
        );

        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (
            $user &&
            $user['role'] === $role &&
            (int)$user['is_active'] === 1 &&
            password_verify($password, $user['password'])
        ) {

            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];

            header("Location: " . $user['role'] . "/dashboard.php");
            exit;

        } else {
            $error = 'Username atau password tidak sesuai.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | RUANG TKJ</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    font-family: Arial, sans-serif;
    background: linear-gradient(135deg,#101c35,#1769aa);
}

.login-card {
    width: 100%;
    max-width: 430px;
    background: white;
    padding: 35px;
    border-radius: 20px;
    box-shadow: 0 15px 45px rgba(0,0,0,.25);
}

.logo {
    text-align: center;
    font-size: 48px;
}

h1 {
    text-align: center;
    color: #153b62;
    margin-bottom: 8px;
}

.subtitle {
    text-align: center;
    color: #64748b;
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 28px;
}

label {
    display: block;
    margin: 16px 0 8px;
    font-weight: bold;
    color: #334155;
}

input, select {
    width: 100%;
    padding: 13px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    font-size: 15px;
}

input:focus, select:focus {
    outline: 2px solid #38bdf8;
    border-color: transparent;
}

button {
    width: 100%;
    padding: 14px;
    margin-top: 25px;
    border: none;
    border-radius: 9px;
    background: #1769aa;
    color: white;
    font-size: 15px;
    font-weight: bold;
    cursor: pointer;
}

button:hover {
    background: #104c80;
}

.error {
    background: #fee2e2;
    color: #991b1b;
    padding: 12px;
    border-radius: 8px;
    font-size: 14px;
}

.footer {
    text-align: center;
    margin-top: 25px;
    font-size: 12px;
    color: #94a3b8;
}
</style>
</head>

<body>

<div class="login-card">

    <div class="logo">🌐</div>

    <h1>RUANG TKJ</h1>

    <div class="subtitle">
        GAME LEARNING PLATFORM<br>
        Belajar Teknologi, Raih Prestasi
    </div>

    <?php if ($error): ?>
        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <label>Masuk Sebagai</label>

        <select name="role" required>
            <option value="">Pilih jenis pengguna</option>
            <option value="siswa">Siswa</option>
            <option value="guru">Guru</option>
            <option value="admin">Administrator</option>
        </select>

        <label>Username</label>

        <input type="text"
               name="username"
               placeholder="Masukkan username"
               required>

        <label>Password</label>

        <input type="password"
               name="password"
               placeholder="Masukkan password"
               required>

        <button type="submit">
            MASUK KE APLIKASI
        </button>

    </form>

    <div class="footer">
        © 2026 RUANG TKJ - GAME LEARNING
        <br>Teknik Komputer dan Jaringan | by: alfiana wijayanti
    </div>

</div>

</body>
</html>
