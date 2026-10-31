
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

wajibLogin('siswa');

$studentId = (int) $_SESSION['user_id'];
$attemptId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$attemptId || $attemptId < 1) {
    exit('Hasil pengerjaan tidak ditemukan.');
}

// Ambil hasil hanya milik siswa yang sedang login.
$stmt = $pdo->prepare("
    SELECT
        ga.*,
        g.title AS game_title,
        g.question_mode,
        g.passing_score,
        c.category_name AS category_name
    FROM game_attempts ga
    INNER JOIN games g ON g.id = ga.game_id
    LEFT JOIN categories c ON c.id = g.category_id
    WHERE ga.id = ? AND ga.student_id = ?
    LIMIT 1
");

$stmt->execute([$attemptId, $studentId]);
$result = $stmt->fetch();

if (!$result) {
    exit('Hasil tidak ditemukan atau bukan milik Anda.');
}

// Ambil jawaban siswa.
$stmt = $pdo->prepare("
    SELECT
        sa.*,
        q.question_text,
        q.question_type,
        q.points,
        o.option_text
    FROM student_answers sa
    INNER JOIN questions q ON q.id = sa.question_id
    LEFT JOIN options o ON o.id = sa.selected_option_id
    WHERE sa.attempt_id = ?
    ORDER BY q.id ASC
");

$stmt->execute([$attemptId]);
$answers = $stmt->fetchAll();

$isEssay = $result['question_mode'] === 'essay';

if ($isEssay && $result['status'] === 'pending') {
    $statusText = 'Menunggu Penilaian Guru';
    $statusClass = 'pending';
} elseif ($result['status'] === 'passed') {
    $statusText = 'LULUS';
    $statusClass = 'passed';
} else {
    $statusText = 'Belum Lulus';
    $statusClass = 'failed';
}

function e($value) {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Hasil Pengerjaan | RUANG TKJ</title>

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
    background: #123b67;
    color: white;
    padding: 22px 7%;
}

header h1 {
    margin: 0;
}

main {
    max-width: 900px;
    margin: 35px auto;
    padding: 0 18px;
}

.card {
    background: white;
    padding: 25px;
    border-radius: 14px;
    margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,.04);
}

.result {
    text-align: center;
    padding: 35px 20px;
}

.score {
    font-size: 48px;
    font-weight: bold;
    color: #123b67;
    margin: 15px 0;
}

.badge {
    display: inline-block;
    padding: 10px 18px;
    border-radius: 30px;
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

.question {
    border-bottom: 1px solid #e2e8f0;
    padding: 18px 0;
}

.question:last-child {
    border-bottom: none;
}

.answer {
    background: #f8fafc;
    padding: 14px;
    border-radius: 8px;
    line-height: 1.7;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

.feedback {
    background: #eff6ff;
    border-left: 4px solid #1769aa;
    padding: 16px;
    border-radius: 8px;
    line-height: 1.7;
}

.notice {
    color: #64748b;
    line-height: 1.7;
}

button {
    padding: 13px 22px;
    background: #1769aa;
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
}

button:hover {
    background: #124f82;
}

a {
    text-decoration: none;
}
</style>
</head>

<body>

<header>
    <h1>RUANG TKJ</h1>
    <p>Hasil Pembelajaran Dasar-Dasar Kejuruan</p>
</header>

<main>

<div class="card result">
    <h2><?= e($result['game_title']) ?></h2>

    <p class="notice">
        Kategori: <?= e($result['category_name'] ?? 'Umum') ?>
    </p>

    <?php if ($isEssay && $result['status'] === 'pending'): ?>

        <div class="badge pending">
            <?= e($statusText) ?>
        </div>

        <p class="notice">
            Jawaban Essay Anda sudah tersimpan.
            Guru akan memeriksa jawaban dan memberikan nilai serta umpan balik.
        </p>

    <?php else: ?>

    <div class="score">
    <?= rtrim(rtrim(number_format((float) $result['score'], 2, ',', '.'), '0'), ',') ?>
    </div>

        <div class="badge <?= e($statusClass) ?>">
            <?= e($statusText) ?>
        </div>

        <p class="notice">
            Nilai ketuntasan: <?= (int) $result['passing_score'] ?>
        </p>

    <?php endif; ?>
</div>

<div class="card">
    <h2>Jawaban Anda</h2>

    <?php foreach ($answers as $index => $answer): ?>

        <div class="question">
            <h3>
                <?= $index + 1 ?>.
                <?= e($answer['question_text']) ?>
            </h3>

            <?php if ($answer['question_type'] === 'essay'): ?>

                <p><strong>Jawaban Essay:</strong></p>

                <div class="answer"><?= e($answer['essay_answer']) ?></div>

                <?php if ($answer['essay_score'] !== null): ?>
                    <p>
                        <strong>Nilai:</strong>
                        <?= number_format((float) $answer['essay_score'], 2) ?>
                        / <?= (int) $answer['points'] ?>
                    </p>
                <?php endif; ?>

                <?php if (!empty($answer['teacher_feedback'])): ?>
                    <div class="feedback">
                        <strong>Umpan Balik Guru:</strong><br>
                        <?= nl2br(e($answer['teacher_feedback'])) ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>

                <p><strong>Jawaban yang dipilih:</strong></p>

                <div class="answer">
                    <?= e($answer['option_text'] ?? 'Tidak tersedia') ?>
                </div>

                <p>
                    <strong>Hasil:</strong>
                    <?= (int) $answer['is_correct'] === 1
                        ? 'Benar'
                        : 'Belum tepat' ?>
                </p>

            <?php endif; ?>

        </div>

    <?php endforeach; ?>
</div>

<?php if ($isEssay && $result['status'] !== 'pending'): ?>
<div class="card">
    <h2>Hasil Penilaian Guru</h2>

    <?php if ($result['essay_score'] !== null): ?>
        <p class="score">
        <?= rtrim(rtrim(number_format((float) $result['essay_score'], 2, ',', '.'), '0'), ',') ?>
        </p>
    <?php endif; ?>

    <?php if (!empty($result['teacher_feedback'])): ?>
        <div class="feedback">
            <?= nl2br(e($result['teacher_feedback'])) ?>
        </div>
    <?php else: ?>
        <p class="notice">Belum ada komentar dari guru.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
    <a href="game.php">
        <button type="button">KEMBALI KE DAFTAR GAME</button>
    </a>
</div>

</main>

</body>
</html>
