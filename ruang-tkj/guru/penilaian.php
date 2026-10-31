<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

wajibLogin('guru');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Token keamanan formulir
if (empty($_SESSION['penilaian_token'])) {
    $_SESSION['penilaian_token'] = bin2hex(random_bytes(32));
}

$pesan = '';
$error = '';

// Proses penyimpanan penilaian
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals(
        $_SESSION['penilaian_token'],
        $_POST['penilaian_token'] ?? ''
    )) {
        $error = 'Sesi formulir tidak valid. Muat ulang halaman.';
    } else {

        $attemptId = filter_input(INPUT_POST, 'attempt_id', FILTER_VALIDATE_INT);
        $nilai = filter_input(INPUT_POST, 'nilai', FILTER_VALIDATE_INT);
        $feedback = trim($_POST['feedback'] ?? '');

        if (!$attemptId || $nilai === false || $nilai === null || $nilai < 0 || $nilai > 100) {
            $error = 'Data penilaian tidak valid. Nilai harus 0 sampai 100.';
        } else {

            try {
                $pdo->beginTransaction();

                // Ambil data pengerjaan dan kunci baris
                $stmt = $pdo->prepare("
                    SELECT
                        ga.id,
                        ga.status,
                        g.passing_score
                    FROM game_attempts ga
                    INNER JOIN games g ON g.id = ga.game_id
                    WHERE ga.id = ?
                    FOR UPDATE
                ");
                $stmt->execute([$attemptId]);
                $attempt = $stmt->fetch();

                if (!$attempt) {
                    throw new Exception('Data pengerjaan tidak ditemukan.');
                }

                if ($attempt['status'] !== 'pending') {
                    throw new Exception('Pengerjaan ini sudah dinilai sebelumnya.');
                }

                // Pastikan pengerjaan memiliki jawaban Essay
                $stmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM student_answers sa
                    INNER JOIN questions q ON q.id = sa.question_id
                    WHERE sa.attempt_id = ?
                      AND q.question_type = 'essay'
                ");
                $stmt->execute([$attemptId]);

                if ((int) $stmt->fetchColumn() === 0) {
                    throw new Exception('Jawaban Essay tidak ditemukan.');
                }

                $status = ($nilai >= (int) $attempt['passing_score'])
                    ? 'passed'
                    : 'failed';

                // Simpan nilai akhir dan umpan balik
                $stmt = $pdo->prepare("
                    UPDATE game_attempts
                    SET essay_score = ?,
                        score = ?,
                        teacher_feedback = ?,
                        status = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $nilai,
                    $nilai,
                    $feedback,
                    $status,
                    $attemptId
                ]);

                $pdo->commit();

                unset($_SESSION['penilaian_token']);
                header('Location: penilaian.php?sukses=1');
                exit;

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = $e->getMessage();
            }
        }
    }
}

if (isset($_GET['sukses'])) {
    $pesan = 'Penilaian berhasil disimpan.';
}

// Ambil daftar pengerjaan Essay
$stmt = $pdo->query("
    SELECT
        ga.id AS attempt_id,
        ga.score,
        ga.essay_score,
        ga.teacher_feedback,
        ga.status,
        ga.started_at,
        ga.completed_at,
        u.full_name,
        g.title AS game_title,
        g.passing_score,
        c.category_name
    FROM game_attempts ga
    INNER JOIN users u ON u.id = ga.student_id
    INNER JOIN games g ON g.id = ga.game_id
    LEFT JOIN categories c ON c.id = g.category_id
    WHERE g.question_mode = 'essay'
    ORDER BY
        CASE WHEN ga.status = 'pending' THEN 0 ELSE 1 END,
        ga.completed_at DESC
");

$attempts = $stmt->fetchAll();

// Ambil jawaban Essay untuk setiap pengerjaan
$stmtJawaban = $pdo->prepare("
    SELECT
        q.question_text,
        q.points,
        sa.essay_answer
    FROM student_answers sa
    INNER JOIN questions q ON q.id = sa.question_id
    WHERE sa.attempt_id = ?
      AND q.question_type = 'essay'
    ORDER BY q.id ASC
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penilaian Essay | RUANG TKJ</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }

        header {
            background: #12345a;
            color: white;
            padding: 22px 35px;
        }

        header h1 {
            margin: 0;
            font-size: 25px;
        }

        header p {
            margin-bottom: 0;
            color: #dbeafe;
        }

        main {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .top-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #12345a;
            text-decoration: none;
            font-weight: bold;
        }

        .panel {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 22px;
            box-shadow: 0 3px 12px rgba(0,0,0,.05);
        }

        .answer {
            background: #f8fafc;
            border-left: 4px solid #2563eb;
            padding: 16px;
            margin: 15px 0;
            border-radius: 6px;
            white-space: pre-wrap;
            line-height: 1.6;
        }

        .meta {
            color: #64748b;
            font-size: 14px;
            line-height: 1.8;
        }

        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .pending {
            background: #fef3c7;
            color: #92400e;
        }

        .passed {
            background: #dcfce7;
            color: #166534;
        }

        .failed {
            background: #fee2e2;
            color: #991b1b;
        }

        label {
            display: block;
            margin-top: 15px;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font: inherit;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        button {
            margin-top: 15px;
            padding: 12px 20px;
            border: none;
            border-radius: 7px;
            background: #12345a;
            color: white;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #1e40af;
        }

        .message {
            padding: 13px;
            border-radius: 7px;
            margin-bottom: 20px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .empty {
            text-align: center;
            color: #64748b;
            padding: 35px;
        }

        .back {
            color: #12345a;
        }
    </style>
</head>

<body>

<header>
    <h1>RUANG TKJ</h1>
    <p>Penilaian Essay Siswa | Dasar-Dasar Kejuruan TKJ</p>
</header>

<main>

    <a class="top-link" href="dashboard.php">← Kembali ke Dashboard Guru</a>

    <h2>Daftar Penilaian Essay</h2>
    <p>Periksa jawaban siswa dan berikan nilai serta umpan balik.</p>

    <?php if ($pesan): ?>
        <div class="message success"><?= e($pesan) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="message danger"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (empty($attempts)): ?>

        <div class="panel empty">
            Belum ada siswa yang mengumpulkan jawaban Essay.
        </div>

    <?php else: ?>

        <?php foreach ($attempts as $attempt): ?>

            <?php
            $stmtJawaban->execute([$attempt['attempt_id']]);
            $jawabanList = $stmtJawaban->fetchAll();

            $status = $attempt['status'];

            if ($status === 'pending') {
                $statusText = 'Menunggu Penilaian Guru';
                $statusClass = 'pending';
            } elseif ($status === 'passed') {
                $statusText = 'LULUS';
                $statusClass = 'passed';
            } else {
                $statusText = 'BELUM LULUS';
                $statusClass = 'failed';
            }
            ?>

            <section class="panel">

                <h3><?= e($attempt['game_title']) ?></h3>

                <div class="meta">
                    <strong>Nama siswa:</strong> <?= e($attempt['full_name']) ?><br>
                    <strong>Kategori:</strong> <?= e($attempt['category_name'] ?? '-') ?><br>
                    <strong>Nilai ketuntasan:</strong> <?= (int) $attempt['passing_score'] ?><br>
                    <strong>Tanggal pengerjaan:</strong>
                    <?= e($attempt['completed_at'] ?? '-') ?>
                </div>

                <p>
                    <span class="status <?= e($statusClass) ?>">
                        <?= e($statusText) ?>
                    </span>
                </p>

                <h4>Jawaban Siswa</h4>

                <?php if (empty($jawabanList)): ?>

                    <p>Jawaban tidak ditemukan.</p>

                <?php else: ?>

                    <?php foreach ($jawabanList as $index => $jawaban): ?>

                        <p>
                            <strong>
                                Soal <?= $index + 1 ?>:
                                <?= e($jawaban['question_text']) ?>
                            </strong>
                        </p>

                        <div class="answer"><?= e($jawaban['essay_answer'] ?? 'Tidak ada jawaban.') ?></div>

                        <div class="meta">
                            Bobot soal: <?= (int) $jawaban['points'] ?> poin
                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

                <?php if ($status === 'pending'): ?>

                    <h4>Formulir Penilaian Guru</h4>

                    <form method="POST">

                        <input
                            type="hidden"
                            name="penilaian_token"
                            value="<?= e($_SESSION['penilaian_token']) ?>"
                        >

                        <input
                            type="hidden"
                            name="attempt_id"
                            value="<?= (int) $attempt['attempt_id'] ?>"
                        >

                        <label>Nilai Akhir (0–100)</label>

                        <input
                            type="number"
                            name="nilai"
                            min="0"
                            max="100"
                            step="1"
                            required
                            placeholder="Contoh: 90"
                        >

                        <label>Umpan Balik Guru</label>

                        <textarea
                            name="feedback"
                            required
                            placeholder="Tuliskan komentar, apresiasi, atau saran perbaikan untuk siswa..."
                        ></textarea>

                        <button type="submit">
                            Simpan Penilaian
                        </button>

                    </form>

                <?php else: ?>

                    <h4>Hasil Penilaian Guru</h4>

                    <p>
                        <strong>Nilai:</strong>
                        <?= number_format((float) $attempt['essay_score'], 0, ',', '.') ?>
                    </p>

                    <p>
                        <strong>Umpan balik:</strong>
                    </p>

                    <div class="answer"><?= e($attempt['teacher_feedback'] ?? '-') ?></div>

                <?php endif; ?>

            </section>

        <?php endforeach; ?>

    <?php endif; ?>

</main>

</body>
</html>
