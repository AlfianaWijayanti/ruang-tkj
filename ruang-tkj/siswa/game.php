
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

wajibLogin('siswa');

$stmt = $pdo->query("
    SELECT
        g.id,
        g.title,
        g.description,
        g.question_mode,
        g.passing_score,
        g.duration_minutes,
        COUNT(DISTINCT q.id) AS total_soal
    FROM games g
    LEFT JOIN questions q ON q.game_id = g.id
    WHERE g.status = 'active'
    GROUP BY
        g.id,
        g.title,
        g.description,
        g.question_mode,
        g.passing_score,
        g.duration_minutes
    ORDER BY g.created_at DESC
");

$games = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sesuaikan nama kolom ID siswa jika struktur tabel users berbeda.
$studentId = $_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? null;

$attemptStmt = $pdo->prepare("
    SELECT id, game_id, status, score
    FROM game_attempts
    WHERE student_id = ?
    ORDER BY id DESC
");

$attemptStmt->execute([$studentId]);
$attempts = [];

foreach ($attemptStmt->fetchAll(PDO::FETCH_ASSOC) as $attempt) {
    if (!isset($attempts[$attempt['game_id']])) {
        $attempts[$attempt['game_id']] = $attempt;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Game Pembelajaran | RUANG TKJ</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f3f6fb;
    color: #1e293b;
}

header {
    background: #123b67;
    color: white;
    padding: 22px 7%;
}

header h1 {
    margin: 0;
    font-size: 25px;
}

header p {
    margin: 7px 0 0;
    color: #dbeafe;
}

main {
    max-width: 1100px;
    margin: 35px auto;
    padding: 0 20px;
}

.intro {
    margin-bottom: 25px;
}

.intro h2 {
    color: #123b67;
    margin-bottom: 8px;
}

.intro p {
    color: #64748b;
    line-height: 1.6;
}

.game-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(270px, 1fr));
    gap: 20px;
}

.game-card {
    background: white;
    padding: 23px;
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 15px rgba(0,0,0,.04);
}

.game-card h3 {
    color: #123b67;
    margin-top: 0;
}

.description {
    color: #64748b;
    line-height: 1.6;
    min-height: 45px;
}

.info {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 16px 0;
}

.info span {
    background: #eff6ff;
    color: #1d4ed8;
    padding: 7px 10px;
    border-radius: 7px;
    font-size: 12px;
}

.btn {
    display: block;
    text-align: center;
    text-decoration: none;
    background: #1769aa;
    color: white;
    padding: 12px;
    border-radius: 8px;
    font-weight: bold;
}

.btn:hover {
    background: #124f82;
}

.done {
    background: #e2e8f0;
    color: #475569;
    padding: 12px;
    text-align: center;
    border-radius: 8px;
}

.empty {
    background: white;
    padding: 30px;
    border-radius: 12px;
    text-align: center;
    color: #64748b;
}
</style>
</head>

<body>

<header>
    <h1>RUANG TKJ</h1>
    <p>Ruang Belajar Dasar-Dasar Kejuruan</p>
</header>

<main>
    <section class="intro">
        <h2>Game Pembelajaran</h2>
        <p>
            Pilih game yang tersedia, pahami setiap pertanyaan,
            dan kerjakan dengan sungguh-sungguh.
            Gunakan kesempatan belajar ini untuk mengembangkan
            pemahaman dan kemampuan berpikir kritis.
        </p>
    </section>

    <section class="game-list">

    <?php if (empty($games)): ?>

        <div class="empty">
            <h3>Belum Ada Game Aktif</h3>
            <p>Game pembelajaran akan ditampilkan setelah guru mengaktifkannya.</p>
        </div>

    <?php else: ?>

        <?php foreach ($games as $game): ?>
            <?php
            $sudahDikerjakan = isset($attempts[$game['id']]);
            ?>

            <article class="game-card">
                <h3>
                    <?= htmlspecialchars($game['title']) ?>
                </h3>

                <p class="description">
                    <?= htmlspecialchars($game['description'] ?? 'Game pembelajaran TKJ') ?>
                </p>

                <div class="info">
                    <span>
                        <?= $game['question_mode'] === 'essay'
                            ? 'Essay'
                            : 'Pilihan Ganda' ?>
                    </span>

                    <span>
                        <?= (int)$game['total_soal'] ?> Soal
                    </span>

                    <span>
                        <?= (int)$game['duration_minutes'] ?> Menit
                    </span>

                    <span>
                        KKM <?= (int)$game['passing_score'] ?>
                    </span>
                </div>

            <?php if ($sudahDikerjakan): ?>

    <div class="done">
        Sudah dikerjakan
    </div>

    <a class="btn"
       href="hasil.php?id=<?= (int)$attempts[$game['id']]['id'] ?>">
        Lihat Hasil
    </a>

<?php else: ?>

                    <a class="btn"
                       href="kerjakan.php?id=<?= (int)$game['id'] ?>">
                        Mulai Mengerjakan
                    </a>

                <?php endif; ?>
            </article>

        <?php endforeach; ?>

    <?php endif; ?>

    </section>
</main>

</body>
</html>
