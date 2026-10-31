
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
            throw new Exception('Permintaan tidak valid. Muat ulang halaman.');
        }

        $aksi = $_POST['aksi'] ?? '';

        if ($aksi === 'tambah' || $aksi === 'edit') {
            $judul = trim($_POST['title'] ?? '');
            $isi = trim($_POST['content'] ?? '');
            $kategori = (int)($_POST['category_id'] ?? 0);
            $status = $_POST['status'] ?? 'draft';

            if ($judul === '' || $isi === '' || $kategori <= 0) {
                throw new Exception('Judul, isi, dan kategori wajib diisi.');
            }

            if (!in_array($status, ['draft', 'published'], true)) {
                throw new Exception('Status materi tidak valid.');
            }

            $cek = $pdo->prepare("SELECT id FROM categories WHERE id=?");
            $cek->execute([$kategori]);

            if (!$cek->fetch()) {
                throw new Exception('Kategori tidak ditemukan.');
            }

            if ($aksi === 'tambah') {
                $stmt = $pdo->prepare("
                    INSERT INTO materials
                    (category_id, title, content, status)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$kategori, $judul, $isi, $status]);

                $pesan = 'Materi berhasil ditambahkan.';
            } else {
                $id = (int)($_POST['id'] ?? 0);

                $stmt = $pdo->prepare("
                    UPDATE materials
                    SET category_id=?, title=?, content=?, status=?
                    WHERE id=?
                ");
                $stmt->execute([$kategori, $judul, $isi, $status, $id]);

                $pesan = 'Materi berhasil diperbarui.';
            }
        } elseif ($aksi === 'hapus') {
            $id = (int)($_POST['id'] ?? 0);

            $stmt = $pdo->prepare("DELETE FROM materials WHERE id=?");
            $stmt->execute([$id]);

            $pesan = 'Materi berhasil dihapus.';
        } else {
            throw new Exception('Aksi tidak dikenal.');
        }

    } catch (PDOException $e) {
        $error = 'Kesalahan database: ' . $e->getMessage();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$kategori = $pdo->query("
    SELECT id, category_name
    FROM categories
    ORDER BY category_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$materi = $pdo->query("
    SELECT m.*, c.category_name
    FROM materials m
    LEFT JOIN categories c ON m.category_id = c.id
    ORDER BY m.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manajemen Materi - RUANG TKJ</title>

<style>
*{box-sizing:border-box}
body{
    margin:0;
    font-family:Arial,sans-serif;
    background:#f1f5f9;
    color:#1e293b;
}
header{
    background:#12345a;
    color:white;
    padding:22px 6%;
}
header h2{margin:0 0 6px}
header p{margin:0;color:#cbd5e1}
main{
    max-width:1150px;
    margin:30px auto;
    padding:0 18px;
}
.panel{
    background:white;
    padding:24px;
    border-radius:14px;
    margin-bottom:22px;
    box-shadow:0 4px 15px #0f172a10;
}
h3{margin-top:0;color:#12345a}
input,select,textarea{
    width:100%;
    padding:12px;
    margin:7px 0 14px;
    border:1px solid #cbd5e1;
    border-radius:8px;
    font-family:inherit;
}
textarea{
    min-height:180px;
    resize:vertical;
}
button,.btn{
    border:0;
    border-radius:8px;
    padding:11px 16px;
    background:#087e8b;
    color:white;
    cursor:pointer;
    text-decoration:none;
    display:inline-block;
}
.danger{background:#dc2626}
.back{background:#475569}
.alert{padding:12px;border-radius:8px;margin-bottom:15px}
.success{background:#dcfce7;color:#166534}
.fail{background:#fee2e2;color:#991b1b}
.table-wrap{overflow-x:auto}
table{
    width:100%;
    border-collapse:collapse;
    min-width:750px;
}
th,td{
    padding:13px;
    text-align:left;
    border-bottom:1px solid #e2e8f0;
    vertical-align:top;
}
th{background:#f8fafc}
.actions{display:flex;gap:7px;flex-wrap:wrap}
.status{
    padding:5px 9px;
    border-radius:20px;
    background:#dcfce7;
    color:#166534;
    font-size:12px;
}
.draft{background:#fef3c7;color:#92400e}
</style>
</head>

<body>

<header>
<h2>RUANG TKJ — Manajemen Materi</h2>
<p>Kelola materi pembelajaran Dasar-Dasar Kejuruan TKJ</p>
</header>

<main>

<p><a href="dashboard.php" class="btn back">← Kembali ke Dashboard</a></p>

<?php if ($pesan): ?>
<div class="alert success"><?= htmlspecialchars($pesan) ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert fail"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<section class="panel">
<h3 id="judulForm">Tambah Materi Pembelajaran</h3>

<form method="POST" id="formMateri">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<input type="hidden" name="aksi" id="aksi" value="tambah">
<input type="hidden" name="id" id="materi_id">

<label>Judul Materi</label>
<input type="text" name="title" id="title" maxlength="200"
placeholder="Contoh: Pengenalan Perangkat Keras Komputer" required>

<label>Kategori Pembelajaran</label>
<select name="category_id" id="category_id" required>
<option value="">-- Pilih Kategori --</option>
<?php foreach ($kategori as $k): ?>
<option value="<?= (int)$k['id'] ?>">
<?= htmlspecialchars($k['category_name']) ?>
</option>
<?php endforeach; ?>
</select>

<label>Isi Materi</label>
<textarea name="content" id="content"
placeholder="Tuliskan materi pembelajaran di sini..." required></textarea>

<label>Status Publikasi</label>
<select name="status" id="status">
<option value="draft">Draft (Belum ditampilkan kepada siswa)</option>
<option value="published">Published (Siap dipelajari siswa)</option>
</select>

<button type="submit">Simpan Materi</button>
<button type="button" class="back" onclick="resetForm()">Batal Edit</button>
</form>
</section>

<section class="panel">
<h3>Daftar Materi Pembelajaran</h3>

<div class="table-wrap">
<table>
<thead>
<tr>
<th>No.</th>
<th>Judul Materi</th>
<th>Kategori</th>
<th>Status</th>
<th>Tanggal Dibuat</th>
<th>Aksi</th>
</tr>
</thead>

<tbody>
<?php if (!$materi): ?>
<tr><td colspan="6">Belum ada materi pembelajaran.</td></tr>
<?php endif; ?>

<?php foreach ($materi as $i => $m): ?>
<tr>
<td><?= $i+1 ?></td>
<td><?= htmlspecialchars($m['title']) ?></td>
<td><?= htmlspecialchars($m['category_name'] ?? '-') ?></td>
<td>
<span class="status <?= $m['status'] === 'draft' ? 'draft' : '' ?>">
<?= htmlspecialchars($m['status']) ?>
</span>
</td>
<td><?= htmlspecialchars($m['created_at']) ?></td>
<td>
<div class="actions">

<button type="button" onclick='editMateri(<?= json_encode([
    "id" => $m["id"],
    "title" => $m["title"],
    "category_id" => $m["category_id"],
    "content" => $m["content"],
    "status" => $m["status"]
], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>
Edit
</button>

<form method="POST"
onsubmit="return confirm('Yakin ingin menghapus materi ini?')">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<input type="hidden" name="aksi" value="hapus">
<input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
<button type="submit" class="danger">Hapus</button>
</form>

</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</section>

</main>

<script>
function editMateri(m) {
    document.getElementById('judulForm').textContent = 'Edit Materi Pembelajaran';
    document.getElementById('aksi').value = 'edit';
    document.getElementById('materi_id').value = m.id;
    document.getElementById('title').value = m.title;
    document.getElementById('category_id').value = m.category_id;
    document.getElementById('content').value = m.content;
    document.getElementById('status').value = m.status;

    document.getElementById('formMateri').scrollIntoView({
        behavior:'smooth'
    });
}

function resetForm() {
    document.getElementById('formMateri').reset();
    document.getElementById('judulForm').textContent = 'Tambah Materi Pembelajaran';
    document.getElementById('aksi').value = 'tambah';
    document.getElementById('materi_id').value = '';
}
</script>

</body>
</html>
