<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

wajibLogin('guru');

$namaGuru = $_SESSION['full_name'] ?? 'Guru';

$stmt = $pdo->query("
    SELECT
        COUNT(*) AS total_game,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS game_aktif
    FROM games
");

$game = $stmt->fetch();

$stmt = $pdo->query("
    SELECT COUNT(*) 
    FROM game_attempts
    WHERE status = 'pending'
");

$menungguPenilaian = (int) $stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT COUNT(*) 
    FROM users
    WHERE role = 'siswa' AND is_active = 1
");

$totalSiswa = (int) $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Guru | RUANG TKJ</title>

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
            margin: 35px auto;
            padding: 0 20px;
        }

        .welcome {
            margin-bottom: 25px;
        }

        .welcome h2 {
            margin-bottom: 8px;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 20px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,.05);
        }

        .card h3 {
            font-size: 15px;
            color: #64748b;
            margin-top: 0;
        }

        .number {
            font-size: 32px;
            font-weight: bold;
            color: #12345a;
        }

        .menu {
            margin-top: 30px;
            background: white;
            padding: 25px;
            border-radius: 12px;
        }

        .menu a {
            display: block;
            padding: 15px;
            margin-top: 12px;
            background: #eff6ff;
            color: #12345a;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
        }

        .menu a:hover {
            background: #dbeafe;
        }

        .logout {
            color: #b91c1c !important;
            background: #fee2e2 !important;
        }

        footer {
            text-align: center;
            padding: 25px;
            color: #64748b;
        }
    </style>
</head>

<body>

<header>
    <h1>RUANG TKJ</h1>
    <p>Dashboard Guru | Dasar-Dasar Kejuruan TKJ</p>
</header>

<main>

    <section class="welcome">
        <h2>Selamat Datang, <?= htmlspecialchars($namaGuru) ?>!</h2>
        <p>Kelola pembelajaran, game edukasi, dan penilaian siswa melalui halaman ini.</p>
    </section>

    <section class="cards">

        <div class="card">
            <h3>Total Game</h3>
            <div class="number"><?= (int) ($game['total_game'] ?? 0) ?></div>
        </div>

        <div class="card">
            <h3>Game Aktif</h3>
            <div class="number"><?= (int) ($game['game_aktif'] ?? 0) ?></div>
        </div>

        <div class="card">
            <h3>Total Siswa Aktif</h3>
            <div class="number"><?= $totalSiswa ?></div>
        </div>

        <div class="card">
            <h3>Essay Menunggu Penilaian</h3>
            <div class="number"><?= $menungguPenilaian ?></div>
        </div>

    </section>

    <section class="menu">
        <h2>Menu Guru</h2>

        <a href="penilaian.php">
            Penilaian Essay Siswa
        </a>

        <a href="../admin/game.php">
            Kelola Game Pembelajaran
        </a>

        <a href="../admin/soal.php">
            Kelola Soal Pembelajaran
        </a>

        <a href="../logout.php" class="logout">
            Keluar
        </a>
    </section>

</main>

<footer>
    RUANG TKJ &copy; <?= date('Y') ?> | Media Pembelajaran Dasar-Dasar Kejuruan
</footer>

</body>
</html>
