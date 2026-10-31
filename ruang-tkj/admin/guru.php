
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

wajibLogin('admin');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pesan = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
            throw new Exception('Permintaan tidak valid. Silakan muat ulang halaman.');
        }

        $aksi = $_POST['aksi'] ?? '';

        if ($aksi === 'tambah') {
            $nama = trim($_POST['full_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($nama === '' || $username === '' || strlen($password) < 10) {
                throw new Exception('Nama, username, dan password minimal 10 karakter wajib diisi.');
            }

            if (!preg_match('/^[a-zA-Z0-9_]{4,50}$/', $username)) {
                throw new Exception('Username harus 4–50 karakter, hanya huruf, angka, dan underscore.');
            }

            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Format email tidak valid.');
            }

            $cek = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $cek->execute([$username]);

            if ($cek->fetch()) {
                throw new Exception('Username sudah digunakan.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO users (full_name, username, email, password, role, is_active)
                VALUES (?, ?, ?, ?, 'guru', 1)
            ");

            $stmt->execute([
                $nama,
                $username,
                $email ?: null,
                password_hash($password, PASSWORD_DEFAULT)
            ]);

            $pesan = 'Akun guru berhasil ditambahkan.';
        }

        elseif ($aksi === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $nama = trim($_POST['full_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($id <= 0 || $nama === '' || $username === '') {
                throw new Exception('Data guru belum lengkap.');
            }

            if (!preg_match('/^[a-zA-Z0-9_]{4,50}$/', $username)) {
                throw new Exception('Username harus 4–50 karakter, hanya huruf, angka, dan underscore.');
            }

            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Format email tidak valid.');
            }

            $cek = $pdo->prepare("
                SELECT id FROM users
                WHERE username = ? AND id != ?
            ");
            $cek->execute([$username, $id]);

            if ($cek->fetch()) {
                throw new Exception('Username sudah digunakan akun lain.');
            }

            if ($password !== '') {
                if (strlen($password) < 10) {
                    throw new Exception('Password baru minimal 10 karakter.');
                }

                $stmt = $pdo->prepare("
                    UPDATE users
                    SET full_name=?, username=?, email=?, password=?
                    WHERE id=? AND role='guru'
                ");

                $stmt->execute([
                    $nama, $username, $email ?: null,
                    password_hash($password, PASSWORD_DEFAULT), $id
                ]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET full_name=?, username=?, email=?
                    WHERE id=? AND role='guru'
                ");

                $stmt->execute([$nama, $username, $email ?: null, $id]);
            }

            $pesan = 'Data guru berhasil diperbarui.';
        }

        elseif ($aksi === 'nonaktifkan') {
            $id = (int)($_POST['id'] ?? 0);

            $stmt = $pdo->prepare("
                UPDATE users SET is_active=0
                WHERE id=? AND role='guru'
            ");
            $stmt->execute([$id]);

            $pesan = 'Akun guru berhasil dinonaktifkan.';
        }

        else {
            throw new Exception('Aksi tidak dikenal.');
        }

    } catch (PDOException $e) {
        $error = 'Terjadi kesalahan database. Periksa kembali data yang dimasukkan.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$stmt = $pdo->query("
    SELECT id, full_name, username, email, is_active, created_at
    FROM users
    WHERE role='guru'
    ORDER BY id DESC
");
$daftarGuru = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Data Guru - RUANG TKJ</title>
<style>
*{box-sizing:border-box}
body{margin:0;font-family:Arial,sans-serif;background:#f1f5f9;color:#1e293b}
header{background:#12345a;color:white;padding:22px 6%}
header h2{margin:0 0 6px}
header p{margin:0;color:#cbd5e1}
main{max-width:1100px;margin:30px auto;padding:0 18px}
.panel{background:white;padding:24px;border-radius:14px;margin-bottom:22px;box-shadow:0 4px 15px #0f172a10}
h3{margin-top:0;color:#12345a}
input{width:100%;padding:12px;margin:7px 0 14px;border:1px solid #cbd5e1;border-radius:8px}
button,.btn{border:0;border-radius:8px;padding:11px 16px;background:#087e8b;color:white;cursor:pointer;text-decoration:none;display:inline-block}
button:hover,.btn:hover{opacity:.9}
.danger{background:#dc2626}
.back{background:#475569}
table{width:100%;border-collapse:collapse;min-width:650px}
th,td{padding:12px;text-align:left;border-bottom:1px solid #e2e8f0}
th{background:#f8fafc}
.table-wrap{overflow-x:auto}
.status{padding:5px 9px;border-radius:20px;background:#dcfce7;color:#166534;font-size:12px}
.off{background:#fee2e2;color:#991b1b}
.alert{padding:12px;border-radius:8px;margin-bottom:15px}
.success{background:#dcfce7;color:#166534}
.fail{background:#fee2e2;color:#991b1b}
small{color:#64748b}
.actions{display:flex;gap:7px;flex-wrap:wrap}
</style>
</head>
<body>

<header>
<h2>RUANG TKJ — Manajemen Data Guru</h2>
<p>Kelola akun guru dan akses pembelajaran</p>
</header>

<main>
<p><a class="btn back" href="dashboard.php">← Kembali ke Dashboard</a></p>

<?php if ($pesan): ?>
<div class="alert success"><?= htmlspecialchars($pesan) ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert fail"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<section class="panel">
<h3>Tambah Akun Guru</h3>
<form method="POST">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<input type="hidden" name="aksi" value="tambah">

<label>Nama Lengkap</label>
<input name="full_name" required placeholder="Contoh: Budi Santoso">

<label>Username</label>
<input name="username" required minlength="4" placeholder="Contoh: budi_guru">

<label>Email (opsional)</label>
<input type="email" name="email" placeholder="guru@email.com">

<label>Password</label>
<input type="password" name="password" required minlength="10" placeholder="Minimal 10 karakter">

<button type="submit">+ Tambahkan Guru</button>
</form>
</section>

<section class="panel">
<h3>Daftar Guru</h3>
<div class="table-wrap">
<table>
<thead>
<tr>
<th>No.</th><th>Nama</th><th>Username</th><th>Email</th><th>Status</th><th>Aksi</th>
</tr>
</thead>
<tbody>
<?php if (!$daftarGuru): ?>
<tr><td colspan="6">Belum ada data guru.</td></tr>
<?php endif; ?>

<?php foreach ($daftarGuru as $i => $guru): ?>
<tr>
<td><?= $i+1 ?></td>
<td><?= htmlspecialchars($guru['full_name']) ?></td>
<td><?= htmlspecialchars($guru['username']) ?></td>
<td><?= htmlspecialchars($guru['email'] ?? '-') ?></td>
<td>
<span class="status <?= $guru['is_active'] ? '' : 'off' ?>">
<?= $guru['is_active'] ? 'Aktif' : 'Nonaktif' ?>
</span>
</td>
<td>
<div class="actions">
<button type="button" onclick='editGuru(<?= json_encode([
    "id" => $guru["id"],
    "full_name" => $guru["full_name"],
    "username" => $guru["username"],
    "email" => $guru["email"]
], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>Edit</button>

<?php if ($guru['is_active']): ?>
<form method="POST" onsubmit="return confirm('Nonaktifkan akun guru ini?')">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<input type="hidden" name="aksi" value="nonaktifkan">
<input type="hidden" name="id" value="<?= (int)$guru['id'] ?>">
<button class="danger" type="submit">Nonaktifkan</button>
</form>
<?php endif; ?>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</section>

<section class="panel" id="formEdit" style="display:none">
<h3>Edit Data Guru</h3>
<form method="POST">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<input type="hidden" name="aksi" value="edit">
<input type="hidden" name="id" id="edit_id">

<label>Nama Lengkap</label>
<input name="full_name" id="edit_nama" required>

<label>Username</label>
<input name="username" id="edit_username" required minlength="4">

<label>Email</label>
<input type="email" name="email" id="edit_email">

<label>Password Baru (kosongkan jika tidak diubah)</label>
<input type="password" name="password" minlength="10">

<button type="submit">Simpan Perubahan</button>
<button type="button" class="back" onclick="document.getElementById('formEdit').style.display='none'">Batal</button>
</form>
</section>

</main>

<script>
function editGuru(guru) {
    document.getElementById('edit_id').value = guru.id;
    document.getElementById('edit_nama').value = guru.full_name;
    document.getElementById('edit_username').value = guru.username;
    document.getElementById('edit_email').value = guru.email || '';
    const form = document.getElementById('formEdit');
    form.style.display = 'block';
    form.scrollIntoView({behavior:'smooth'});
}
</script>

</body>
</html>
