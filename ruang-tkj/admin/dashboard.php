
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

wajibLogin('admin');

// Mengambil statistik database
$totalSiswa = $pdo->query(
    "SELECT COUNT(*) FROM users WHERE role='siswa'"
)->fetchColumn();

$totalGuru = $pdo->query(
    "SELECT COUNT(*) FROM users WHERE role='guru'"
)->fetchColumn();

$totalGame = $pdo->query(
    "SELECT COUNT(*) FROM games"
)->fetchColumn();

$totalMateri = $pdo->query(
    "SELECT COUNT(*) FROM materials"
)->fetchColumn();

$totalNilai = $pdo->query(
    "SELECT COUNT(*) FROM game_attempts"
)->fetchColumn();

$namaAdmin = htmlspecialchars(
    $_SESSION['full_name'] ?? 'Administrator',
    ENT_QUOTES,
    'UTF-8'
);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Dashboard Admin | RUANG TKJ</title>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #f1f5f9;
    color: #1e293b;
}

/* SIDEBAR */

.sidebar {
    position: fixed;
    width: 250px;
    height: 100vh;
    background: #10243e;
    color: white;
    padding: 25px 18px;
}

.brand {
    font-size: 21px;
    font-weight: bold;
    padding: 10px;
    margin-bottom: 35px;
}

.brand span {
    color: #38bdf8;
}

.menu-title {
    color: #94a3b8;
    font-size: 11px;
    margin: 20px 10px 12px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.menu a {
    display: block;
    padding: 13px;
    color: #cbd5e1;
    text-decoration: none;
    border-radius: 8px;
    margin-bottom: 5px;
    font-size: 14px;
}

.menu a:hover,
.menu a.active {
    background: #1769aa;
    color: white;
}

.logout {
    margin-top: 30px;
    border-top: 1px solid #334155;
    padding-top: 15px;
}

/* MAIN CONTENT */

.main {
    margin-left: 250px;
    padding: 30px;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 30px;
}

.topbar h1 {
    font-size: 25px;
    margin-bottom: 7px;
}

.topbar p {
    color: #64748b;
    font-size: 14px;
}

.profile {
    background: white;
    padding: 12px 18px;
    border-radius: 10px;
    font-weight: bold;
    box-shadow: 0 2px 10px rgba(0,0,0,.04);
}

/* WELCOME */

.welcome {
    background: linear-gradient(120deg,#1769aa,#153b62);
    color: white;
    padding: 28px;
    border-radius: 15px;
    margin-bottom: 28px;
}

.welcome h2 {
    margin-bottom: 10px;
    font-size: 23px;
}

.welcome p {
    color: #dbeafe;
    line-height: 1.6;
}

/* STATISTICS */

.section-title {
    margin-bottom: 18px;
    font-size: 19px;
}

.stats {
    display: grid;
    grid-template-columns: repeat(4,1fr);
    gap: 18px;
}

.card {
    background: white;
    padding: 22px;
    border-radius: 13px;
    box-shadow: 0 3px 12px rgba(0,0,0,.04);
    border: 1px solid #e2e8f0;
}

.card .icon {
    font-size: 28px;
    margin-bottom: 15px;
}

.card h3 {
    color: #64748b;
    font-size: 13px;
    font-weight: normal;
    margin-bottom: 10px;
}

.card .number {
    font-size: 30px;
    font-weight: bold;
    color: #153b62;
}

/* INFO */

.info {
    margin-top: 28px;
    background: white;
    border-radius: 13px;
    padding: 25px;
    border: 1px solid #e2e8f0;
}

.info p {
    color: #64748b;
    line-height: 1.8;
    margin-top: 10px;
    font-size: 14px;
}

/* RESPONSIVE */

@media(max-width:1000px) {
    .stats {
        grid-template-columns: repeat(2,1fr);
    }
}

@media(max-width:650px) {
    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }

    .main {
        margin-left: 0;
        padding: 18px;
    }

    .stats {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .topbar {
        align-items: flex-start;
        flex-direction: column;
    }

    .card {
        padding: 15px;
    }
}
</style>
</head>

<body>

<!-- SIDEBAR -->

<aside class="sidebar">

    <div class="brand">
        🌐 RUANG <span>TKJ</span>
    </div>

    <div class="menu-title">Menu Utama</div>

    <nav class="menu">
        <a href="dashboard.php" class="active">
            🏠 Dashboard
        </a>

        <div class="menu-title">Manajemen Data</div>

        <a href="siswa.php">👨‍🎓 Data Siswa</a>
        <a href="guru.php">👨‍🏫 Data Guru</a>
        <a href="kategori.php">📚 Kategori TKJ</a>
        <a href="materi.php">📖 Materi Pembelajaran</a>
        <a href="game.php">🎮 Manajemen Game</a>
        <a href="soal.php">📝 Bank Soal</a>
        <a href="nilai.php">📊 Nilai Siswa</a>
    </nav>

    <div class="logout">
        <a href="../logout.php"
           style="color:#fca5a5;text-decoration:none;padding:10px;">
            🚪 Logout
        </a>
    </div>

</aside>

<!-- MAIN -->

<main class="main">

    <div class="topbar">

        <div>
            <h1>Dashboard Administrator</h1>
            <p>Panel pengelolaan RUANG TKJ - GAME LEARNING</p>
        </div>

        <div class="profile">
            👤 <?= $namaAdmin ?>
        </div>

    </div>

    <section class="welcome">

        <h2>Selamat Datang, <?= $namaAdmin ?>!</h2>

        <p>
            Selamat bekerja di RUANG TKJ - GAME LEARNING.
            Kelola materi pembelajaran, game edukasi, pengguna,
            dan evaluasi hasil belajar siswa dalam satu platform.
        </p>

    </section>

    <h2 class="section-title">Statistik Aplikasi</h2>

    <section class="stats">

        <div class="card">
            <div class="icon">👨‍🎓</div>
            <h3>Total Siswa</h3>
            <div class="number"><?= $totalSiswa ?></div>
        </div>

        <div class="card">
            <div class="icon">👨‍🏫</div>
            <h3>Total Guru</h3>
            <div class="number"><?= $totalGuru ?></div>
        </div>

        <div class="card">
            <div class="icon">🎮</div>
            <h3>Total Game</h3>
            <div class="number"><?= $totalGame ?></div>
        </div>

        <div class="card">
            <div class="icon">📚</div>
            <h3>Total Materi</h3>
            <div class="number"><?= $totalMateri ?></div>
        </div>

    </section>

    <section class="info">

        <h2>Informasi Aktivitas</h2>

        <p>
            Total pengerjaan game:
            <strong><?= $totalNilai ?></strong>
        </p>

        <p>
            Sistem RUANG TKJ siap digunakan untuk
            pengelolaan pembelajaran berbasis game.
        </p>

    </section>

</main>

</body>
</html>
