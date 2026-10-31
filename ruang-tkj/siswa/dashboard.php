
<?php
require_once __DIR__ . '/../includes/auth.php';

wajibLogin('siswa');

header('Location: game.php');
exit;
