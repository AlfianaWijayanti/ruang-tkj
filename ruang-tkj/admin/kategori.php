
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
            $nama = trim($_POST['category_name'] ?? '');
            $deskripsi = trim($_POST['description'] ?? '');

            if ($nama === '') {
                throw new Exception('Nama kategori wajib diisi.');
            }

            if ($aksi === 'tambah') {
                $stmt = $pdo->prepare("
                    INSERT INTO categories (category_name, description)
                    VALUES (?, ?)
                ");
                $stmt->execute([$nama, $deskripsi ?: null]);

                $pesan = 'Kategori berhasil ditambahkan.';
            } else {
                $id = (int)($_POST['id'] ?? 0);

                $stmt = $pdo->prepare("
                    UPDATE categories
                    SET category_name=?, description=?
                    WHERE id=?
                ");
                $stmt->execute([$nama, $deskripsi ?: null, $id]);

                $pesan = 'Kategori berhasil diperbarui.';
            }
        } elseif ($aksi === 'hapus') {
            $id = (int)($_POST['id'] ?? 0);

            $cek = $pdo->prepare("
                SELECT COUNT(*) FROM materials WHERE category_id=?
            ");
            $cek->execute([$id]);

            if ((int)$cek->fetchColumn() > 0) {
                throw new Exception(
                    'Kategori tidak dapat dihapus karena masih digunakan oleh materi.'
                );
            }

            $stmt = $pdo->prepare("DELETE FROM categories WHERE id=?");
            $stmt->execute([$id]);

            $pesan = 'Kategori berhasil dihapus.';
        } else {
            throw new Exception('Aksi tidak dikenal.');
        }

    } catch (PDOException $e) {
        $error = 'Kesalahan database: ' . $e->getMessage();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$stmt = $pdo->query("
    SELECT id, category_name, description, created_at
    FROM categories
    ORDER BY id DESC
");
$kategori = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kategori TKJ - RUANG TKJ</title>

<style>
*{box-sizing:border-box}
body{margin:0;font-family:Arial,sans-serif;background:#f1f5f9;color:#1e293b}
header{background:#12345a;color:white;padding:22px 6%}
header h2{margin:0 0 6px}
header p{margin:0;color:#cbd5e1}
main{max-width:1100px;margin:30px auto;padding:0 18px}
.panel{background:white;padding:24px;border-radius:14px;margin-bottom:22px;box-shadow:0 4px 15px #0f172a10}
h3{margin-top:0;color:#12345a}
input,textarea{width:100%;padding:12px;margin:7px 0 14px;border:1px solid #cbd5e1;border-radius:8px;font-family:inherit}
textarea{min-height:90px;resize:vertical}
button,.btn{border:0;border-radius:8px;padding:11px 16px;background:#087e8b;color:white;cursor:pointer;text-decoration:none;display:inline-block}
.danger{background:#dc2626}
.back{background:#475569}
.alert{padding:12px;border-radius:8px;margin-bottom:15px}
.success{background:#dcfce7;color:#166534}
.fail{background:#fee2e2;color:#991b1b}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:600px}
th,td{padding:13px;text-align:left;border-bottom:1px solid #e2e8f0}
th{background:#f8fafc}
.actions{display:flex;gap:7px;flex-wrap:wrap}
</style>
</head>

<body>

<header>
<h2>RUANG TKJ — Kategori Pembelajaran</h2>
<p>Kelola kelompok materi Teknik Komputer dan Jaringan</p>
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
<h3>Tambah Kategori</h3>

<form method="POST">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<input type="hidden" name="aksi" value="tambah">

<label>Nama Kategori</label>
<input type="text" name="category_name" required placeholder="Contoh: Dasar-Dasar Jaringan Komputer">

<label>Deskripsi</label>
<textarea name="description" placeholder="Jelaskan isi kategori pembelajaran..."></textarea>

<button type="submit">+ Tambahkan Kategori</button>
</form>
</section>

<section class="panel">
<h3>Daftar Kategori Pembelajaran</h3>

<div class="table-wrap">
<table>
<thead>
<tr>
<th>No.</th>
<th>Nama Kategori</th>
<th>Deskripsi</th>
<th>Aksi</th>
</tr>
</thead>

<tbody>
<?php if (!$kategori): ?>
<tr><td colspan="4">Belum ada kategori pembelajaran.</td></tr>
<?php endif; ?>

<?php foreach ($kategori as $i => $item): ?>
<tr>
<td><?= $i+1 ?></td>
<td><?= htmlspecialchars($item['category_name']) ?></td>
<td><?= htmlspecialchars($item['description'] ?? '-') ?></td>
<td>
<div class="actions">

<button type="button" onclick='editKategori(<?= json_encode([
    "id" => $item["id"],
    "category_name" => $item["category_name"],
    "description" => $item["description"]
], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>
Edit
</button>

<form method="POST" onsubmit="return confirm('Yakin ingin menghapus kategori ini?')">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<input type="hidden" name="aksi" value="hapus">
<input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
<button class="danger" type="submit">Hapus</button>
</form>

</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</section>

<section class="panel" id="formEdit" style="display:none">
<h3>Edit Kategori</h3>

<form method="POST">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<input type="hidden" name="aksi" value="edit">
<input type="hidden" name="id" id="edit_id">

<label>Nama Kategori</label>
<input type="text" name="category_name" id="edit_nama" required>

<label>Deskripsi</label>
<textarea name="description" id="edit_deskripsi"></textarea>

<button type="submit">Simpan Perubahan</button>
<button type="button" class="back" onclick="document.getElementById('formEdit').style.display='none'">Batal</button>
</form>
</section>

</main>

<script>
function editKategori(item) {
    document.getElementById('edit_id').value = item.id;
    document.getElementById('edit_nama').value = item.category_name;
    document.getElementById('edit_deskripsi').value = item.description || '';

    const form = document.getElementById('formEdit');
    form.style.display = 'block';
    form.scrollIntoView({behavior:'smooth'});
}
</script>

</body>
</html>
