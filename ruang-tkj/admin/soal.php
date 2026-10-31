
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

wajibLogin('admin');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_soal'])) {
    $_SESSION['csrf_soal'] = bin2hex(random_bytes(32));
}

$pesan = '';
$error = '';

function h($nilai) {
    return htmlspecialchars((string)$nilai, ENT_QUOTES, 'UTF-8');
}

try {
    // Ambil daftar game
    $stmt = $pdo->query("
        SELECT g.id, g.title, g.question_mode,
               (SELECT COUNT(*) FROM questions q WHERE q.game_id = g.id) AS total_soal
        FROM games g
        ORDER BY g.created_at DESC
    ");
    $games = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $gameId = (int)($_GET['game_id'] ?? $_POST['game_id'] ?? 0);
    $game = null;

    if ($gameId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM games WHERE id = ?");
        $stmt->execute([$gameId]);
        $game = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Proses simpan dan hapus
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($_SESSION['csrf_soal'], $_POST['csrf_token'] ?? '')) {
            throw new Exception('Token keamanan tidak valid. Muat ulang halaman.');
        }

        $aksi = $_POST['aksi'] ?? '';

        if ($aksi === 'simpan') {
            $questionId = (int)($_POST['question_id'] ?? 0);
            $gameId = (int)($_POST['game_id'] ?? 0);
            $teksSoal = trim($_POST['question_text'] ?? '');
            $jenisSoal = $_POST['question_type'] ?? '';
            $poin = (int)($_POST['points'] ?? 10);

            if ($gameId < 1 || $teksSoal === '') {
                throw new Exception('Game dan teks soal wajib diisi.');
            }

            if (!in_array($jenisSoal, ['multiple_choice', 'essay'], true)) {
                throw new Exception('Jenis soal tidak valid.');
            }

            if ($poin < 1 || $poin > 100) {
                throw new Exception('Poin harus antara 1 sampai 100.');
            }

            $pdo->beginTransaction();

            // Kunci baris game agar jenis soal tidak berubah bersamaan
            $stmt = $pdo->prepare("SELECT * FROM games WHERE id = ? FOR UPDATE");
            $stmt->execute([$gameId]);
            $gameTerkunci = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$gameTerkunci) {
                throw new Exception('Game tidak ditemukan.');
            }

            if ($questionId > 0) {
                $stmt = $pdo->prepare("
                    SELECT * FROM questions
                    WHERE id = ? AND game_id = ?
                ");
                $stmt->execute([$questionId, $gameId]);
                $soalLama = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$soalLama) {
                    throw new Exception('Soal yang akan diedit tidak ditemukan.');
                }

                if ($gameTerkunci['question_mode'] !== $jenisSoal) {
                    throw new Exception('Jenis soal harus sama dengan jenis game.');
                }
            } else {
                $modeGame = $gameTerkunci['question_mode'];

                if ($modeGame !== null && $modeGame !== $jenisSoal) {
                    throw new Exception('Game ini sudah ditetapkan sebagai jenis ' .
                        ($modeGame === 'essay' ? 'Essay' : 'Pilihan Ganda') . '.');
                }

                // Tetapkan jenis game saat soal pertama dibuat
                if ($modeGame === null) {
                    $stmt = $pdo->prepare("
                        UPDATE games SET question_mode = ? WHERE id = ?
                    ");
                    $stmt->execute([$jenisSoal, $gameId]);
                }
            }

            if ($jenisSoal === 'multiple_choice') {
                $opsi = $_POST['options'] ?? [];
                $jawabanBenar = $_POST['correct_option'] ?? '';

                foreach (['A', 'B', 'C', 'D', 'E'] as $huruf) {
                    if (empty(trim($opsi[$huruf] ?? ''))) {
                        throw new Exception("Pilihan jawaban $huruf wajib diisi.");
                    }
                }

                if (!in_array($jawabanBenar, ['A', 'B', 'C', 'D', 'E'], true)) {
                    throw new Exception('Tentukan satu jawaban yang benar.');
                }
            }

            if ($questionId > 0) {
                $stmt = $pdo->prepare("
                    UPDATE questions
                    SET question_text = ?, question_type = ?, points = ?
                    WHERE id = ? AND game_id = ?
                ");
                $stmt->execute([
                    $teksSoal, $jenisSoal, $poin, $questionId, $gameId
                ]);

                $stmt = $pdo->prepare("DELETE FROM options WHERE question_id = ?");
                $stmt->execute([$questionId]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO questions
                    (game_id, question_text, question_type, points)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$gameId, $teksSoal, $jenisSoal, $poin]);
                $questionId = (int)$pdo->lastInsertId();
            }

            if ($jenisSoal === 'multiple_choice') {
                $stmt = $pdo->prepare("
                    INSERT INTO options (question_id, option_text, is_correct)
                    VALUES (?, ?, ?)
                ");

                foreach (['A', 'B', 'C', 'D', 'E'] as $huruf) {
                    $stmt->execute([
                        $questionId,
                        trim($opsi[$huruf]),
                        $jawabanBenar === $huruf ? 1 : 0
                    ]);
                }
            }

            $pdo->commit();
            header("Location: soal.php?game_id=" . $gameId . "&status=sukses");
            exit;
        }

        if ($aksi === 'hapus') {
            $questionId = (int)($_POST['question_id'] ?? 0);
            $gameId = (int)($_POST['game_id'] ?? 0);

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT id FROM questions WHERE id = ? AND game_id = ?
            ");
            $stmt->execute([$questionId, $gameId]);

            if (!$stmt->fetch()) {
                throw new Exception('Soal tidak ditemukan.');
            }

            $stmt = $pdo->prepare("DELETE FROM options WHERE question_id = ?");
            $stmt->execute([$questionId]);

            $stmt = $pdo->prepare("
                DELETE FROM questions WHERE id = ? AND game_id = ?
            ");
            $stmt->execute([$questionId, $gameId]);

            $pdo->commit();
            header("Location: soal.php?game_id=" . $gameId . "&status=hapus");
            exit;
        }
    }

    if (isset($_GET['status'])) {
        if ($_GET['status'] === 'sukses') {
            $pesan = 'Soal berhasil disimpan.';
        } elseif ($_GET['status'] === 'hapus') {
            $pesan = 'Soal berhasil dihapus.';
        }
    }

    // Ambil game terbaru setelah proses penyimpanan
    if ($gameId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM games WHERE id = ?");
        $stmt->execute([$gameId]);
        $game = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Ambil soal dalam game terpilih
    $daftarSoal = [];

    if ($game) {
        $stmt = $pdo->prepare("
            SELECT * FROM questions
            WHERE game_id = ?
            ORDER BY id ASC
        ");
        $stmt->execute([$gameId]);
        $daftarSoal = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($daftarSoal as &$item) {
            $stmtOpsi = $pdo->prepare("
                SELECT * FROM options
                WHERE question_id = ?
                ORDER BY id ASC
            ");
            $stmtOpsi->execute([$item['id']]);
            $item['options'] = $stmtOpsi->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($item);
    }

    // Ambil soal untuk diedit
    $editSoal = null;

    if (isset($_GET['edit']) && $game) {
        $stmt = $pdo->prepare("
            SELECT * FROM questions WHERE id = ? AND game_id = ?
        ");
        $stmt->execute([(int)$_GET['edit'], $gameId]);
        $editSoal = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($editSoal) {
            $stmt = $pdo->prepare("
                SELECT * FROM options WHERE question_id = ? ORDER BY id ASC
            ");
            $stmt->execute([$editSoal['id']]);
            $editSoal['options'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manajemen Soal - RUANG TKJ</title>
<style>
*{box-sizing:border-box}
body{margin:0;background:#f1f5f9;color:#1e293b;font-family:Arial,sans-serif}
header{background:#102b50;color:white;padding:22px 30px}
header h1{margin:0;font-size:23px}
header p{margin:7px 0 0;color:#cbd5e1}
.container{max-width:1150px;margin:25px auto;padding:0 18px}
.card{background:white;border-radius:13px;padding:22px;margin-bottom:20px;box-shadow:0 3px 14px #0f172a0c}
h2{font-size:19px;margin:0 0 18px;color:#15365d}
label{display:block;font-weight:bold;margin:14px 0 7px}
input,select,textarea{width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:7px;font:inherit}
textarea{min-height:100px;resize:vertical}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:15px}
.options{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.option{background:#f8fafc;padding:12px;border-radius:8px}
.option label{margin-top:0}
button,.btn{border:0;border-radius:7px;padding:11px 17px;background:#087e8b;color:white;font-weight:bold;cursor:pointer;text-decoration:none;display:inline-block}
button:hover,.btn:hover{opacity:.88}
.btn-secondary{background:#64748b}
.btn-danger{background:#dc5252}
.actions{display:flex;gap:8px;flex-wrap:wrap}
.notice{padding:13px;border-radius:8px;margin-bottom:15px;background:#dcfce7;color:#166534}
.error{background:#fee2e2;color:#991b1b}
table{width:100%;border-collapse:collapse}
th,td{padding:12px;text-align:left;border-bottom:1px solid #e2e8f0;vertical-align:top}
th{background:#f8fafc}
.small{color:#64748b;font-size:13px}
.badge{background:#e0f2fe;color:#075985;padding:5px 9px;border-radius:20px;font-size:12px}
@media(max-width:700px){.grid,.options{grid-template-columns:1fr}.card{padding:16px}table{font-size:13px}}
</style>
</head>
<body>
<header>
    <h1>RUANG TKJ — Manajemen Soal</h1>
    <p>Kelola soal pilihan ganda dan essay untuk game pembelajaran</p>
</header>

<div class="container">

<?php if ($pesan): ?>
<div class="notice"><?= h($pesan) ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="notice error"><?= h($error) ?></div>
<?php endif; ?>

<section class="card">
    <h2>1. Pilih Game</h2>
    <form method="GET">
        <label>Game pembelajaran</label>
        <select name="game_id" onchange="this.form.submit()" required>
            <option value="">-- Pilih game --</option>
            <?php foreach ($games as $g): ?>
                <option value="<?= (int)$g['id'] ?>"
                    <?= $gameId === (int)$g['id'] ? 'selected' : '' ?>>
                    <?= h($g['title']) ?>
                    — <?= $g['question_mode'] === 'essay' ? 'Essay' :
                        ($g['question_mode'] === 'multiple_choice' ? 'Pilihan Ganda' : 'Belum ditentukan') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if (!$games): ?>
        <p class="small">Belum ada game. Buat game terlebih dahulu melalui menu Manajemen Game.</p>
    <?php endif; ?>
</section>

<?php if ($game): ?>
<section class="card">
    <h2>2. <?= $editSoal ? 'Edit Soal' : 'Tambah Soal Baru' ?></h2>

    <p><strong>Game:</strong> <?= h($game['title']) ?></p>
    <p class="small">
        Jenis game:
        <span class="badge">
        <?= $game['question_mode'] === 'essay' ? 'Essay' :
            ($game['question_mode'] === 'multiple_choice' ? 'Pilihan Ganda' : 'Belum ditentukan') ?>
        </span>
    </p>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_soal']) ?>">
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="game_id" value="<?= (int)$gameId ?>">
        <input type="hidden" name="question_id" value="<?= (int)($editSoal['id'] ?? 0) ?>">

        <?php
        $modeForm = $editSoal['question_type'] ?? $game['question_mode'] ?? '';
        ?>

        <?php if (!$game['question_mode'] && !$editSoal): ?>
            <label>Jenis soal untuk game ini</label>
            <select name="question_type" id="question_type" required>
                <option value="multiple_choice">Pilihan Ganda A–E</option>
                <option value="essay">Essay</option>
            </select>
        <?php else: ?>
            <input type="hidden" name="question_type" value="<?= h($modeForm) ?>">
            <label>Jenis soal</label>
            <input value="<?= $modeForm === 'essay' ? 'Essay' : 'Pilihan Ganda A–E' ?>" disabled>
        <?php endif; ?>

        <label>Pertanyaan</label>
        <textarea name="question_text" required
            placeholder="Tuliskan pertanyaan di sini..."><?= h($editSoal['question_text'] ?? '') ?></textarea>

        <label>Poin soal</label>
        <input type="number" name="points" min="1" max="100"
            value="<?= h($editSoal['points'] ?? 10) ?>" required>

        <div id="pilihan-ganda" style="<?= $modeForm === 'essay' ? 'display:none' : '' ?>">
            <h3>Pilihan Jawaban A–E</h3>
            <p class="small">Isi kelima pilihan dan tentukan satu jawaban yang benar.</p>

            <div class="options">
            <?php
            $opsiEdit = [];
            $kunciEdit = '';

            if ($editSoal) {
                foreach ($editSoal['options'] as $op) {
                    $huruf = chr(65 + count($opsiEdit));
                    $opsiEdit[$huruf] = $op['option_text'];
                    if ((int)$op['is_correct'] === 1) {
                        $kunciEdit = $huruf;
                    }
                }
            }

            foreach (['A','B','C','D','E'] as $huruf):
            ?>
                <div class="option">
                    <label>Opsi <?= $huruf ?></label>
                    <input type="text" name="options[<?= $huruf ?>]"
                        value="<?= h($opsiEdit[$huruf] ?? '') ?>"
                        placeholder="Jawaban <?= $huruf ?>">
                    <label style="font-weight:normal">
                        <input type="radio" name="correct_option" value="<?= $huruf ?>"
                            style="width:auto"
                            <?= $kunciEdit === $huruf ? 'checked' : '' ?>>
                        Jadikan jawaban benar
                    </label>
                </div>
            <?php endforeach; ?>
            </div>
        </div>

        <div id="info-essay" style="<?= $modeForm === 'essay' ? '' : 'display:none' ?>">
            <p class="notice">
                Soal essay dijawab dengan uraian oleh siswa. Guru akan memeriksa,
                memberikan nilai, dan menulis umpan balik.
            </p>
        </div>

        <div class="actions" style="margin-top:20px">
            <button type="submit"><?= $editSoal ? 'Simpan Perubahan' : 'Simpan Soal' ?></button>
            <?php if ($editSoal): ?>
                <a class="btn btn-secondary" href="soal.php?game_id=<?= (int)$gameId ?>">Batal Edit</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="card">
    <h2>3. Daftar Soal (<?= count($daftarSoal) ?>)</h2>

    <?php if (!$daftarSoal): ?>
        <p class="small">Belum ada soal untuk game ini.</p>
    <?php else: ?>
    <div style="overflow-x:auto">
    <table>
        <thead>
            <tr>
                <th>No.</th>
                <th>Pertanyaan</th>
                <th>Jenis</th>
                <th>Poin</th>
                <th>Opsi / Kunci</th>
                <th>Tindakan</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($daftarSoal as $i => $s): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= nl2br(h($s['question_text'])) ?></td>
                <td><?= $s['question_type'] === 'essay' ? 'Essay' : 'Pilihan Ganda' ?></td>
                <td><?= (int)$s['points'] ?></td>
                <td>
                    <?php if ($s['question_type'] === 'multiple_choice'): ?>
                        <?php foreach ($s['options'] as $j => $op): ?>
                            <div>
                                <?= chr(65 + $j) ?>.
                                <?= h($op['option_text']) ?>
                                <?= (int)$op['is_correct'] === 1 ? ' ✓' : '' ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="small">Dinilai oleh guru</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="actions">
                        <a class="btn" href="soal.php?game_id=<?= (int)$gameId ?>&edit=<?= (int)$s['id'] ?>">Edit</a>
                        <form method="POST" onsubmit="return confirm('Yakin ingin menghapus soal ini?')">
                            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_soal']) ?>">
                            <input type="hidden" name="aksi" value="hapus">
                            <input type="hidden" name="game_id" value="<?= (int)$gameId ?>">
                            <input type="hidden" name="question_id" value="<?= (int)$s['id'] ?>">
                            <button class="btn-danger" type="submit">Hapus</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<a class="btn btn-secondary" href="dashboard.php">Kembali ke Dashboard</a>
</div>

<script>
const jenisSelect = document.getElementById('question_type');

if (jenisSelect) {
    jenisSelect.addEventListener('change', function () {
        const pilihan = document.getElementById('pilihan-ganda');
        const essay = document.getElementById('info-essay');
        const isPG = this.value === 'multiple_choice';

        pilihan.style.display = isPG ? 'block' : 'none';
        essay.style.display = isPG ? 'none' : 'block';

        pilihan.querySelectorAll('input').forEach(function (input) {
            input.required = isPG;
        });
    });

    jenisSelect.dispatchEvent(new Event('change'));
}
</script>
</body>
</html>
