
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

        if (in_array($aksi, ['tambah', 'edit'], true)) {
            $judul = trim($_POST['title'] ?? '');
            $deskripsi = trim($_POST['description'] ?? '');
            $kategori = (int)($_POST['category_id'] ?? 0);
            $nilai = (int)($_POST['passing_score'] ?? 80);
            $durasi = (int)($_POST['duration_minutes'] ?? 30);
            $status = $_POST['status'] ?? 'draft';
            $jenisGame = $_POST['question_mode'] ?? '';

            if ($judul === '' || $kategori <= 0) {
                throw new Exception('Judul dan kategori wajib diisi.');
            }

            if (!in_array($jenisGame, ['multiple_choice', 'essay'], true)) {
                throw new Exception('Pilih jenis game yang valid.');
            }

            if ($nilai < 0 || $nilai > 100) {
                throw new Exception('Nilai kelulusan harus antara 0 sampai 100.');
            }

            if ($durasi < 1 || $durasi > 300) {
                throw new Exception('Durasi harus antara 1 sampai 300 menit.');
            }

            if (!in_array($status, ['draft', 'active', 'inactive'], true)) {
                throw new Exception('Status game tidak valid.');
            }

            $cek = $pdo->prepare("SELECT id FROM categories WHERE id=?");
            $cek->execute([$kategori]);

            if (!$cek->fetch()) {
                throw new Exception('Kategori tidak ditemukan.');
            }

            if ($aksi === 'tambah') {
                $stmt = $pdo->prepare("
                    INSERT INTO games
                    (
                        category_id,
                        title,
                        description,
                        passing_score,
                        duration_minutes,
                        status,
                        question_mode
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $kategori,
                    $judul,
                    $deskripsi ?: null,
                    $nilai,
                    $durasi,
                    $status,
                    $jenisGame
                ]);

                $pesan = 'Game berhasil ditambahkan.';
            } else {
                $id = (int)($_POST['id'] ?? 0);

                if ($id <= 0) {
                    throw new Exception('ID game tidak valid.');
                }

                // Periksa jenis game dan jumlah soal yang sudah dibuat.
                $stmt = $pdo->prepare("
                    SELECT g.question_mode,
                           COUNT(q.id) AS total_soal
                    FROM games g
                    LEFT JOIN questions q ON q.game_id = g.id
                    WHERE g.id = ?
                    GROUP BY g.id, g.question_mode
                ");
                $stmt->execute([$id]);
                $gameLama = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$gameLama) {
                    throw new Exception('Game tidak ditemukan.');
                }

                $modeLama = $gameLama['question_mode'];
                $totalSoal = (int)$gameLama['total_soal'];

                if (
                    $totalSoal > 0 &&
                    $modeLama !== $jenisGame
                ) {
                    throw new Exception(
                        'Jenis game tidak dapat diubah karena game sudah memiliki soal.'
                    );
                }

                $stmt = $pdo->prepare("
                    UPDATE games
                    SET category_id=?,
                        title=?,
                        description=?,
                        passing_score=?,
                        duration_minutes=?,
                        status=?,
                        question_mode=?
                    WHERE id=?
                ");

                $stmt->execute([
                    $kategori,
                    $judul,
                    $deskripsi ?: null,
                    $nilai,
                    $durasi,
                    $status,
                    $jenisGame,
                    $id
                ]);

                $pesan = 'Game berhasil diperbarui.';
            }
        } elseif ($aksi === 'hapus') {
            $id = (int)($_POST['id'] ?? 0);

            // Periksa riwayat pengerjaan siswa.
            $cek = $pdo->prepare("
                SELECT COUNT(*) FROM game_attempts WHERE game_id=?
            ");
            $cek->execute([$id]);

            if ((int)$cek->fetchColumn() > 0) {
                throw new Exception(
                    'Game sudah memiliki riwayat pengerjaan. Ubah status menjadi inactive.'
                );
            }

            // Periksa apakah game sudah mempunyai soal.
            $cek = $pdo->prepare("
                SELECT COUNT(*) FROM questions WHERE game_id=?
            ");
            $cek->execute([$id]);

            if ((int)$cek->fetchColumn() > 0) {
                throw new Exception(
                    'Game masih memiliki soal. Hapus soal terlebih dahulu melalui Manajemen Soal.'
                );
            }

            $stmt = $pdo->prepare("DELETE FROM games WHERE id=?");
            $stmt->execute([$id]);

            $pesan = 'Game berhasil dihapus.';
        } else {
            throw new Exception('Aksi tidak dikenal.');
        }

    } catch (PDOException $e) {
        $error = 'Kesalahan database: ' . $e->getMessage();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Ambil kategori.
$kategori = $pdo->query("
    SELECT id, category_name
    FROM categories
    ORDER BY category_name
")->fetchAll(PDO::FETCH_ASSOC);

// Ambil daftar game.
$games = $pdo->query("
    SELECT
        g.*,
        c.category_name,
        (SELECT COUNT(*) FROM questions q WHERE q.game_id = g.id) AS total_soal
    FROM games g
    LEFT JOIN categories c ON g.category_id = c.id
    ORDER BY g.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manajemen Game - RUANG TKJ</title>

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

textarea{min-height:90px;resize:vertical}

label{
    display:block;
    font-weight:bold;
    margin-top:8px;
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

.alert{
    padding:12px;
    border-radius:8px;
    margin-bottom:15px;
}

.success{background:#dcfce7;color:#166534}
.fail{background:#fee2e2;color:#991b1b}

.table-wrap{overflow-x:auto}

table{
    width:100%;
    border-collapse:collapse;
    min-width:900px;
}

th,td{
    padding:13px;
    text-align:left;
    border-bottom:1px solid #e2e8f0;
    vertical-align:top;
}

th{background:#f8fafc}

.actions{
    display:flex;
    gap:7px;
    flex-wrap:wrap;
}

.status,.jenis{
    padding:5px 9px;
    border-radius:20px;
    display:inline-block;
    font-size:12px;
    font-weight:bold;
}

.status{background:#dcfce7;color:#166534}
.draft{background:#fef3c7;color:#92400e}
.inactive{background:#fee2e2;color:#991b1b}

.jenis-pg{background:#dbeafe;color:#1d4ed8}
.jenis-essay{background:#f3e8ff;color:#7e22ce}

.info{
    background:#eff6ff;
    color:#1e40af;
    padding:12px;
    border-radius:8px;
    font-size:13px;
    line-height:1.6;
}

@media(max-width:600px){
    .panel{padding:16px}
    header{padding:20px}
}
</style>
</head>

<body>

<header>
    <h2>RUANG TKJ — Manajemen Game Learning</h2>
    <p>Kelola permainan edukatif Teknik Komputer dan Jaringan</p>
</header>

<main>

<p>
    <a href="dashboard.php" class="btn back">← Kembali ke Dashboard</a>
</p>

<?php if ($pesan): ?>
<div class="alert success">
    <?= htmlspecialchars($pesan) ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert fail">
    <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<section class="panel">

<h3 id="judulForm">Tambah Game Pembelajaran</h3>

<div class="info">
    <strong>Pilihan jenis game:</strong><br>
    Pilihan Ganda: siswa menjawab menggunakan opsi A, B, C, D, dan E.<br>
    Essay: siswa menjawab dengan uraian dan guru memberikan penilaian.
</div>

<form method="POST" id="formGame">

<input type="hidden" name="csrf_token"
value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

<input type="hidden" name="aksi" id="aksi" value="tambah">

<input type="hidden" name="id" id="game_id">

<label>Judul Game</label>
<input
    type="text"
    name="title"
    id="title"
    maxlength="200"
    placeholder="Contoh: Petualangan Jaringan Komputer"
    required
>

<label>Kategori</label>
<select name="category_id" id="category_id" required>
    <option value="">-- Pilih Kategori --</option>

    <?php foreach ($kategori as $k): ?>
    <option value="<?= (int)$k['id'] ?>">
        <?= htmlspecialchars($k['category_name']) ?>
    </option>
    <?php endforeach; ?>
</select>

<label>Jenis Game</label>
<select name="question_mode" id="question_mode" required>
    <option value="multiple_choice">Pilihan Ganda (A–E)</option>
    <option value="essay">Essay (Uraian)</option>
</select>

<label>Deskripsi Game</label>
<textarea
    name="description"
    id="description"
    placeholder="Jelaskan tujuan dan tantangan permainan..."
></textarea>

<label>Nilai Minimum Kelulusan</label>
<input
    type="number"
    name="passing_score"
    id="passing_score"
    min="0"
    max="100"
    value="80"
    required
>

<label>Durasi (Menit)</label>
<input
    type="number"
    name="duration_minutes"
    id="duration_minutes"
    min="1"
    max="300"
    value="30"
    required
>

<label>Status Game</label>
<select name="status" id="status">
    <option value="draft">Draft</option>
    <option value="active">Active - Dapat dimainkan siswa</option>
    <option value="inactive">Inactive - Tidak aktif</option>
</select>

<button type="submit">Simpan Game</button>

<button
    type="button"
    class="back"
    onclick="resetForm()"
>
    Batal Edit
</button>

</form>
</section>

<section class="panel">

<h3>Daftar Game</h3>

<div class="table-wrap">

<table>

<thead>
<tr>
    <th>No.</th>
    <th>Judul Game</th>
    <th>Kategori</th>
    <th>Jenis Game</th>
    <th>Nilai Lulus</th>
    <th>Durasi</th>
    <th>Status</th>
    <th>Aksi</th>
</tr>
</thead>

<tbody>

<?php if (!$games): ?>
<tr>
    <td colspan="8">Belum ada game pembelajaran.</td>
</tr>
<?php endif; ?>

<?php foreach ($games as $i => $g): ?>

<tr>

<td><?= $i+1 ?></td>

<td><?= htmlspecialchars($g['title']) ?></td>

<td><?= htmlspecialchars($g['category_name'] ?? '-') ?></td>

<td>
    <?php if ($g['question_mode'] === 'essay'): ?>
        <span class="jenis jenis-essay">Essay</span>
    <?php elseif ($g['question_mode'] === 'multiple_choice'): ?>
        <span class="jenis jenis-pg">Pilihan Ganda A–E</span>
    <?php else: ?>
        <span class="jenis">Belum ditentukan</span>
    <?php endif; ?>
</td>

<td><?= (int)$g['passing_score'] ?></td>

<td><?= (int)$g['duration_minutes'] ?> menit</td>

<td>
    <span class="status <?= htmlspecialchars($g['status']) ?>">
        <?= htmlspecialchars($g['status']) ?>
    </span>
</td>

<td>

<div class="actions">

<button
    type="button"
    onclick='editGame(<?= json_encode([
        "id" => $g["id"],
        "title" => $g["title"],
        "category_id" => $g["category_id"],
        "description" => $g["description"],
        "passing_score" => $g["passing_score"],
        "duration_minutes" => $g["duration_minutes"],
        "status" => $g["status"],
        "question_mode" => $g["question_mode"]
    ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'
>
    Edit
</button>

<form
    method="POST"
    onsubmit="return confirm('Yakin ingin menghapus game ini?')"
>
    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
    >

    <input type="hidden" name="aksi" value="hapus">

    <input
        type="hidden"
        name="id"
        value="<?= (int)$g['id'] ?>"
    >

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
function editGame(g) {
    document.getElementById('judulForm').textContent =
        'Edit Game Pembelajaran';

    document.getElementById('aksi').value = 'edit';
    document.getElementById('game_id').value = g.id;
    document.getElementById('title').value = g.title;
    document.getElementById('category_id').value = g.category_id;
    document.getElementById('question_mode').value =
        g.question_mode || 'multiple_choice';

    document.getElementById('description').value =
        g.description || '';

    document.getElementById('passing_score').value =
        g.passing_score;

    document.getElementById('duration_minutes').value =
        g.duration_minutes;

    document.getElementById('status').value = g.status;

    document.getElementById('formGame').scrollIntoView({
        behavior:'smooth'
    });
}

function resetForm() {
    document.getElementById('formGame').reset();

    document.getElementById('judulForm').textContent =
        'Tambah Game Pembelajaran';

    document.getElementById('aksi').value = 'tambah';
    document.getElementById('game_id').value = '';

    document.getElementById('question_mode').value =
        'multiple_choice';
}
</script>

</body>
</html>
