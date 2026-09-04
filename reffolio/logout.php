<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
logout_user();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
flash('success', '已退出登录。');
redirect('/login.php');
