<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=lms_db;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sql = file_get_contents('C:/wamp64/www/lmsproject/LMS-Project/database/migrations/035_admin_google_account.sql');
$pdo->exec($sql);
echo "MIGRATION_OK\n";
