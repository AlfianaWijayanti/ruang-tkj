
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
$jenisPesan = 'success';

// PROSES TAMBAH, EDIT, DAN NONAKTIFKAN SISWA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals(
        $_SESSION['csrf_token'],
        $_POST['csrf_token'] ?? ''
    )) {
        $pesan = 'Permintaan tidak valid.';
        $jenisPesan = 'error';

    } else {

        $aksi = $_POST['aksi'] ?? '';

        try {

            if ($aksi === 'tambah') {

                $nama = trim($_POST['nama'] ?? '');
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                $email = trim($_POST['email'] ?? '');
                $kelas = $_POST['kelas'] ?? '';

                if ($nama === '' || $username === '' || strlen($password) < 10) {
                    throw new Exception('Lengkapi data. Password minimal 10 karakter.');
                }

                if (!preg_match('/^[a-zA-Z0-9_]{4,50}$/', $username)) {
                    throw new Exception('Username harus 4-50 karakter.');
                }

                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Format email tidak valid.');
                }

                $hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare(
                    "INSERT INTO users
                    (class_id, full_name, username, email, password, role)
                    VALUES (?, ?, ?, ?, ?, 'siswa')"
                );

                $stmt->execute([
                    $kelas !== '' ? $kelas : null,
                    $nama,
                    $username,
                    $email !== '' ? $email : null,
                    $hash
                ]);

                $pesan = 'Siswa berhasil ditambahkan!';

            } elseif ($aksi === 'edit') {

                $id = (int)($_POST['id'] ?? 0);
                $nama = trim($_POST['nama'] ?? '');
                $username = trim($_POST['username'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $kelas = $_POST['kelas'] ?? '';

                if ($nama === '' || !preg_match('/^[a-zA-Z0-9_]{4,50}$/', $username)) {
                    throw new Exception('Nama atau username tidak valid.');
                }

                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Format email tidak valid.');
                }

                $stmt = $pdo->prepare(
                    "UPDATE users SET
                    full_name=?, username=?, email=?, class_id=?
                    WHERE id=? AND role='siswa'"
                );

                $stmt->execute([
                    $nama,
                    $username,
                    $email !== '' ? $email : null,
                    $kelas !== '' ? $kelas : null,
                    $id
                ]);

                if (!empty($_POST['password'])) {
                    if (strlen($_POST['password']) < 10) {
                        throw new Exception('Password minimal 10 karakter.');
                    }

                    $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);

                    $stmt = $pdo->prepare(
                        "UPDATE users SET password=?
                         WHERE id=? AND role='siswa'"
                    );

                    $stmt->execute([$hash, $id]);
                }

                $pesan = 'Data siswa berhasil diperbarui!';

            } elseif ($aksi === 'hapus') {

                $id = (int)($_POST['id'] ?? 0);

                // Nonaktifkan akun, pertahankan riwayat nilai.
                $stmt = $pdo->prepare(
                    "UPDATE users SET is_active=0
                     WHERE id=? AND role='siswa'"
                );

                $stmt->execute([$id]);

                $pesan = 'Akun siswa berhasil dinonaktifkan.';
            }

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $pesan = 'Operasi gagal. Username atau email mungkin sudah digunakan.';
            $jenisPesan = 'error';

        } catch (Exception $e) {
            $pesan = $e->getMessage();
            $jenisPesan = 'error';
        }
    }
}

// AMBIL DATA KELAS
$kelasList = $pdo->query(
    "SELECT * FROM classes ORDER BY class_name"
)->fetchAll();

// AMBIL DAFTAR SISWA
$siswaList = $pdo->query(
    "SELECT u.*, c.class_name
     FROM users u
     LEFT JOIN classes c ON u.class_id=c.id
     WHERE u.role='siswa'
     ORDER BY u.id DESC"
)->fetchAll();

$totalSiswa = count($siswaList);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Data Siswa | RUANG TKJ</title>

<style>
* { box-sizing:border-box; margin:0; padding:0; }

body {
    font-family:Arial,sans-serif;
    background:#f1f5f9;
    color:#1e293b;
}

.sidebar {
    position:fixed;
    width:235px;
    height:100vh;
    background:#10243e;
    color:white;
    padding:25px 16px;
}

.brand {
    font-size:21px;
    font-weight:bold;
    margin-bottom:35px;
}

.brand span { color:#38bdf8; }

.menu a {
    display:block;
    padding:13px;
    margin-bottom:5px;
    border-radius:8px;
    color:#cbd5e1;
    text-decoration:none;
    font-size:14px;
}

.menu a:hover,.menu a.active {
    background:#1769aa;
    color:white;
}

.main {
    margin-left:235px;
    padding:30px;
}

.header {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:15px;
    margin-bottom:25px;
}

.header p {
    color:#64748b;
    margin-top:7px;
    font-size:14px;
}

.panel {
    background:white;
    border-radius:13px;
    padding:23px;
    margin-bottom:22px;
    box-shadow:0 3px 12px rgba(0,0,0,.04);
}

.stat {
    color:#64748b;
    font-size:14px;
}

.stat strong {
    display:block;
    font-size:30px;
    color:#1769aa;
    margin-top:10px;
}

button,.btn {
    border:none;
    border-radius:7px;
    padding:10px 15px;
    cursor:pointer;
    font-weight:bold;
    text-decoration:none;
    display:inline-block;
}

.primary {
    background:#1769aa;
    color:white;
}

.edit {
    background:#dbeafe;
    color:#1d4ed8;
}

.delete {
    background:#fee2e2;
    color:#b91c1c;
}

input,select {
    width:100%;
    padding:11px;
    border:1px solid #cbd5e1;
    border-radius:7px;
    margin-top:7px;
}

.form-grid {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:15px;
    margin-top:15px;
}

.form-group label {
    font-size:13px;
    font-weight:bold;
}

.actions {
    display:flex;
    gap:7px;
    flex-wrap:wrap;
}

.table-wrap {
    overflow-x:auto;
    margin-top:18px;
}

table {
    width:100%;
    border-collapse:collapse;
    min-width:700px;
}

th,td {
    padding:13px;
    border-bottom:1px solid #e2e8f0;
    text-align:left;
    font-size:13px;
}

th {
    background:#f8fafc;
    color:#475569;
}

.badge {
    padding:5px 9px;
    border-radius:20px;
    font-size:11px;
}

.aktif {
    background:#dcfce7;
    color:#166534;
}

.nonaktif {
    background:#fee2e2;
    color:#991b1b;
}

.message {
    padding:13px;
    border-radius:8px;
    margin-bottom:18px;
}

.success {
    background:#dcfce7;
    color:#166534;
}

.error {
    background:#fee2e2;
    color:#991b1b;
}

@media(max-width:750px) {
    .sidebar {
        position:relative;
        width:100%;
        height:auto;
    }

    .main {
        margin-left:0;
        padding:16px;
    }

    .form-grid {
        grid-template-columns:1fr;
    }

    .header {
        align-items:flex-start;
        flex-direction:column;
    }
}
</style>
</head>

<body>

<aside class="sidebar">

    <div class="brand">🌐 RUANG <span>TKJ</span></div>

    <nav class="menu">
        <a href="dashboard.php">🏠 Dashboard</a>
        <a href="siswa.php" class="active">👨‍🎓 Data Siswa</a>
        <a href="guru.php">👨‍🏫 Data Guru</a>
        <a href="kategori.php">📚 Kategori TKJ</a>
        <a href="materi.php">📖 Materi</a>
        <a href="game.php">🎮 Game</a>
        <a href="soal.php">📝 Bank Soal</a>
        <a href="nilai.php">📊 Nilai</a>
        <a href="../logout.php">🚪 Logout</a>
    </nav>

</aside>

<main class="main">

    <div class="header">
        <div>
            <h1>Manajemen Data Siswa</h1>
            <p>Kelola akun dan informasi peserta didik TKJ.</p>
        </div>

        <a href="dashboard.php" class="btn primary">
            ← Dashboard
        </a>
    </div>

    <?php if ($pesan): ?>
        <div class="message <?= $jenisPesan ?>">
            <?= htmlspecialchars($pesan) ?>
        </div>
    <?php endif; ?>

    <div class="panel">
        <div class="stat">Total akun siswa terdaftar</div>
        <strong style="font-size:30px;color:#1769aa;">
            <?= $totalSiswa ?>
        </strong>
    </div>

    <div class="panel">

        <h2 id="formTitle">Tambah Siswa Baru</h2>

        <form method="POST" id="studentForm">

            <input type="hidden" name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <input type="hidden" name="aksi" id="aksi" value="tambah">
            <input type="hidden" name="id" id="id">

            <div class="form-grid">

                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama" id="nama" required>
                </div>

                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" id="username"
                        minlength="4" required>
                </div>

                <div class="form-group">
                    <label>Email (opsional)</label>
                    <input type="email" name="email" id="email">
                </div>

                <div class="form-group">
                    <label>Kelas</label>
                    <select name="kelas" id="kelas">
                        <option value="">Belum ditentukan</option>
                        <?php foreach ($kelasList as $kelas): ?>
                            <option value="<?= $kelas['id'] ?>">
                                <?= htmlspecialchars($kelas['class_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" id="password"
                        minlength="10">
                    <small id="passwordHelp">
                        Minimal 10 karakter.
                    </small>
                </div>

            </div>

            <div class="actions" style="margin-top:20px;">
                <button type="submit" class="primary" id="submitBtn">
                    + Simpan Siswa
                </button>

                <button type="button" class="edit"
                    onclick="resetForm()">
                    Batal / Reset
                </button>
            </div>

        </form>

    </div>

    <div class="panel">

        <h2>Daftar Siswa</h2>

        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Nama Siswa</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Kelas</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (!$siswaList): ?>

                    <tr>
                        <td colspan="7">Belum ada data siswa.</td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($siswaList as $i => $s): ?>

                    <tr>

                        <td><?= $i+1 ?></td>

                        <td><?= htmlspecialchars($s['full_name']) ?></td>

                        <td><?= htmlspecialchars($s['username']) ?></td>

                        <td><?= htmlspecialchars($s['email'] ?? '-') ?></td>

                        <td><?= htmlspecialchars($s['class_name'] ?? '-') ?></td>

                        <td>
                            <span class="badge <?= $s['is_active'] ? 'aktif' : 'nonaktif' ?>">
                                <?= $s['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                            </span>
                        </td>

                        <td>

                            <div class="actions">

                                <button type="button" class="edit"
                                    onclick='editSiswa(
                                        <?= json_encode([
                                            'id' => $s['id'],
                                            'nama' => $s['full_name'],
                                            'username' => $s['username'],
                                            'email' => $s['email'] ?? '',
                                            'kelas' => $s['class_id']
                                        ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>
                                    )'>
                                    Edit
                                </button>

                                <?php if ($s['is_active']): ?>

                                <form method="POST"
                                    onsubmit="return confirm('Nonaktifkan akun siswa ini? Riwayat nilainya tetap disimpan.')">

                                    <input type="hidden" name="csrf_token"
                                        value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                                    <input type="hidden" name="aksi" value="hapus">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">

                                    <button class="delete" type="submit">
                                        Nonaktifkan
                                    </button>

                                </form>

                                <?php endif; ?>

                            </div>

                        </td>

                    </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</main>

<script>

function editSiswa(s) {

    document.getElementById('formTitle').innerText =
        'Edit Data Siswa';

    document.getElementById('aksi').value = 'edit';
    document.getElementById('id').value = s.id;
    document.getElementById('nama').value = s.nama;
    document.getElementById('username').value = s.username;
    document.getElementById('email').value = s.email;
    document.getElementById('kelas').value = s.kelas || '';

    document.getElementById('password').required = false;
    document.getElementById('password').value = '';

    document.getElementById('passwordHelp').innerText =
        'Kosongkan jika tidak ingin mengubah password.';

    document.getElementById('submitBtn').innerText =
        'Simpan Perubahan';

    window.scrollTo({top:0,behavior:'smooth'});
}

function resetForm() {

    document.getElementById('studentForm').reset();

    document.getElementById('formTitle').innerText =
        'Tambah Siswa Baru';

    document.getElementById('aksi').value = 'tambah';
    document.getElementById('id').value = '';

    document.getElementById('password').required = true;

    document.getElementById('passwordHelp').innerText =
        'Minimal 10 karakter.';

    document.getElementById('submitBtn').innerText =
        '+ Simpan Siswa';
}

</script>

</body>
</html>
