<?php
// config.php（PHP用設定ファイル）

// データベース接続設定
define('DB_HOST', 'localhost');
define('DB_NAME', 'news_app');
define('DB_USER', 'root');
define('DB_PASS', 'root');

// セッション設定
ini_set('session.cookie_lifetime', 60 * 60 * 24 * 30); // 30日間
ini_set('session.gc_maxlifetime', 60 * 60 * 24 * 30);

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

/// エラー表示設定（デバッグ時のみ有効にする）
ini_set('display_errors', 0);
error_reporting(E_ALL);

// ログファイル
define('LOG_FILE', __DIR__ . '/logs/app.log');

// 簡易ログ関数
function app_log($message)
{
    $log_message = date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL;
    file_put_contents(LOG_FILE, $log_message, FILE_APPEND);
}

// セキュリティ設定
define('CSRF_TOKEN_SECRET', 'your_random_secret_key');
