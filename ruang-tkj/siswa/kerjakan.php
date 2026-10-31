
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

wajibLogin('siswa');

$studentId = (int) $_SESSION['user_id'];
$gameId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$gameId || $gameId < 1) {
    exit('Game tidak ditemukan.');
}

// Ambil game aktif.
$stmt = $pdo->prepare("
    SELECT *
    FROM games
    WHERE id = ? AND status = 'active'
    LIMIT 1
");
$stmt->execute([$gameId]);
$game = $stmt->fetch();

if (!$game) {
    exit('Game tidak tersedia atau sudah tidak aktif.');
}

// Periksa apakah siswa sudah pernah mengerjakan.
$stmt = $pdo->prepare("
    SELECT id, status, score, essay_score
    FROM game_attempts
    WHERE student_id = ? AND game_id = ?
    LIMIT 1
");
$stmt->execute([$studentId, $gameId]);
$existingAttempt = $stmt->fetch();

if ($existingAttempt) {
    exit(
        '<h2>Game sudah pernah dikerjakan.</h2>
        <p>Setiap siswa hanya memiliki satu kesempatan untuk game ini.</p>
        <a href="game.php">Kembali ke daftar game</a>'
    );
}

// Ambil soal.
$stmt = $pdo->prepare("
    SELECT id, question_text, question_type, points
    FROM questions
    WHERE game_id = ?
    ORDER BY id ASC
");
$stmt->execute([$gameId]);
$questions = $stmt->fetchAll();

if (!$questions) {
    exit('Game ini belum memiliki soal.');
}
// Pastikan semua soal sesuai dengan jenis game.
foreach ($questions as $question) {
    if ($question['question_type'] !== $game['question_mode']) {
        exit('Jenis soal tidak sesuai dengan jenis game. Hubungi guru.');
    }
}

// Ambil pilihan jawaban tanpa mengirim kunci jawaban ke browser.
$optionsByQuestion = [];

if ($game['question_mode'] === 'multiple_choice') {
    $questionIds = array_column($questions, 'id');

    $placeholders = implode(',', array_fill(0, count($questionIds), '?'));

    $stmt = $pdo->prepare("
        SELECT id, question_id, option_text
        FROM options
        WHERE question_id IN ($placeholders)
        ORDER BY id ASC
    ");
    $stmt->execute($questionIds);

    foreach ($stmt->fetchAll() as $option) {
        $optionsByQuestion[$option['question_id']][] = $option;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['game_token'] ?? '', $_POST['game_token'] ?? '')) {
        $error = 'Sesi formulir tidak valid. Silakan muat ulang halaman.';
    } else {

        try {
            $pdo->beginTransaction();

            // Kunci data game agar dua pengiriman bersamaan tidak membuat dua percobaan.
            $lock = $pdo->prepare("
                SELECT id
                FROM games
                WHERE id = ? AND status = 'active'
                FOR UPDATE
            ");
            $lock->execute([$gameId]);

            if (!$lock->fetch()) {
                throw new Exception('Game tidak lagi tersedia.');
            }

            $check = $pdo->prepare("
                SELECT id
                FROM game_attempts
                WHERE student_id = ? AND game_id = ?
                LIMIT 1
            ");
            $check->execute([$studentId, $gameId]);

            if ($check->fetch()) {
                throw new Exception('Game ini sudah pernah dikerjakan.');
            }

            $totalPoints = 0;
            $earnedPoints = 0;

            foreach ($questions as $question) {
                $totalPoints += (int) $question['points'];
            }

            $attemptStatus = 'failed';
            $score = 0;

            // Essay menunggu penilaian guru.
            $attemptStatus = ($game['question_mode'] === 'essay') ? 'pending' : 'failed';

            $insertAttempt = $pdo->prepare("
                INSERT INTO game_attempts
                (student_id, game_id, score, essay_score, teacher_feedback, status, started_at, completed_at)
                VALUES (?, ?, 0, NULL, NULL, ?, NOW(), NOW())
            ");

            $insertAttempt->execute([
                $studentId,
                $gameId,
                $attemptStatus
            ]);

            $attemptId = (int) $pdo->lastInsertId();

            $insertAnswer = $pdo->prepare("
                INSERT INTO student_answers
                (attempt_id, question_id, selected_option_id, is_correct, essay_answer)
                VALUES (?, ?, ?, ?, ?)
            ");

            foreach ($questions as $question) {

                $questionId = (int) $question['id'];
                $questionType = $question['question_type'];

                if ($questionType === 'multiple_choice') {

                    $selectedOption = filter_var(
                        $_POST['answers'][$questionId] ?? '',
                        FILTER_VALIDATE_INT
                    );

                    if (!$selectedOption) {
                        throw new Exception('Semua soal harus dijawab.');
                    }

                    // Validasi pilihan memang milik soal tersebut.
                    $checkOption = $pdo->prepare("
                        SELECT id, is_correct
                        FROM options
                        WHERE id = ? AND question_id = ?
                        LIMIT 1
                    ");
                    $checkOption->execute([$selectedOption, $questionId]);
                    $option = $checkOption->fetch();

                    if (!$option) {
                        throw new Exception('Pilihan jawaban tidak valid.');
                    }

                    $isCorrect = (int) $option['is_correct'];

                    if ($isCorrect) {
                        $earnedPoints += (int) $question['points'];
                    }

                    $insertAnswer->execute([
                        $attemptId,
                        $questionId,
                        $selectedOption,
                        $isCorrect,
                        null
                    ]);

                } else {

                    $essay = trim($_POST['answers'][$questionId] ?? '');

                    if ($essay === '') {
                        throw new Exception('Semua soal Essay harus dijawab.');
                    }

                    $insertAnswer->execute([
                        $attemptId,
                        $questionId,
                        null,
                        0,
                        $essay
                    ]);
                }
            }

            if ($game['question_mode'] === 'multiple_choice') {

                $score = $totalPoints > 0
                    ? round(($earnedPoints / $totalPoints) * 100, 2)
                    : 0;

                $status = $score >= (int) $game['passing_score']
                    ? 'passed'
                    : 'failed';

                $update = $pdo->prepare("
                    UPDATE game_attempts
                    SET score = ?, status = ?
                    WHERE id = ?
                ");
                $update->execute([$score, $status, $attemptId]);
            }

            $pdo->commit();

            unset($_SESSION['game_token']);

            header('Location: hasil.php?id=' . $attemptId);
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());
            $error = $e instanceof Exception
                ? $e->getMessage()
                : 'Terjadi kesalahan saat menyimpan jawaban.';
        }
    }
}

if (empty($_SESSION['game_token'])) {
    $_SESSION['game_token'] = bin2hex(random_bytes(32));
}

$token = $_SESSION['game_token'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($game['title']) ?> | RUANG TKJ</title>

<style>
* { box-sizing: border-box; }

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f1f5f9;
    color: #1e293b;
}

header {
    background: #123b67;
    color: white;
    padding: 22px 7%;
}

header h1 { margin: 0; }

main {
    max-width: 850px;
    margin: 30px auto;
    padding: 0 18px;
}

.card {
    background: white;
    padding: 25px;
    border-radius: 14px;
    margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,.04);
}

.question {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 18px;
}

.question h3 {
    color: #123b67;
    line-height: 1.6;
}

.option {
    display: block;
    padding: 12px;
    margin: 9px 0;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
}

.option:hover {
    background: #eff6ff;
}

textarea {
    width: 100%;
    min-height: 150px;
    padding: 13px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font: inherit;
    resize: vertical;
}

button {
    width: 100%;
    padding: 15px;
    border: none;
    border-radius: 9px;
    background: #1769aa;
    color: white;
    font-weight: bold;
    font-size: 16px;
    cursor: pointer;
}

button:hover { background: #124f82; }

.error {
    background: #fee2e2;
    color: #991b1b;
    padding: 14px;
    border-radius: 8px;
    margin-bottom: 18px;
}

.notice {
    color: #64748b;
    line-height: 1.6;
}
</style>
</head>

<body>

<header>
    <h1>RUANG TKJ</h1>
    <p>Game Pembelajaran Dasar-Dasar Kejuruan</p>
</header>

<main>

<div class="card">
    <h2><?= htmlspecialchars($game['title']) ?></h2>
    <p class="notice">
        <?= htmlspecialchars($game['description'] ?? '') ?>
    </p>
    <p>
        Jenis:
        <strong>
        <?= $game['question_mode'] === 'essay' ? 'Essay' : 'Pilihan Ganda' ?>
        </strong>
        |
        Durasi: <?= (int) $game['duration_minutes'] ?> menit
    </p>
</div>

<?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">
<input type="hidden" name="game_token" value="<?= htmlspecialchars($token) ?>">

<?php foreach ($questions as $index => $question): ?>

<div class="question">
    <h3>
        <?= $index + 1 ?>.
        <?= nl2br(htmlspecialchars($question['question_text'])) ?>
    </h3>

    <p class="notice">
        Bobot: <?= (int) $question['points'] ?> poin
    </p>

    <?php if ($question['question_type'] === 'multiple_choice'): ?>

        <?php foreach ($optionsByQuestion[$question['id']] ?? [] as $option): ?>
            <label class="option">
                <input
                    type="radio"
                    name="answers[<?= (int) $question['id'] ?>]"
                    value="<?= (int) $option['id'] ?>"
                    required
                >
                <?= htmlspecialchars($option['option_text']) ?>
            </label>
        <?php endforeach; ?>

    <?php else: ?>

        <textarea
            name="answers[<?= (int) $question['id'] ?>]"
            placeholder="Tuliskan jawaban dan alasan teknis Anda..."
            required
        ></textarea>

    <?php endif; ?>
</div>

<?php endforeach; ?>

<div class="card">
    <p class="notice">
        Periksa kembali jawaban sebelum dikirim.
        Setelah dikirim, jawaban tidak dapat diubah.
    </p>

    <button type="submit"
        onclick="return confirm('Yakin ingin mengirim jawaban? Kesempatan hanya satu kali.')">
        KIRIM JAWABAN
    </button>
</div>

</form>

</main>
</body>
</html>
