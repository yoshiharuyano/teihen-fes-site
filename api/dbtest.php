<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');

echo "=== 書き込みテスト ===\n\n";

$dbPath = __DIR__ . '/votes.sqlite3';

// 1. DB接続
echo "1. DB open: ";
try {
    $db = new SQLite3($dbPath);
    echo "OK\n";
} catch (Exception $e) {
    echo "FAIL - " . $e->getMessage() . "\n";
    exit;
}

// 2. WALモード
echo "2. WAL mode: ";
try {
    $db->exec('PRAGMA journal_mode=WAL');
    echo "OK\n";
} catch (Exception $e) {
    echo "FAIL - " . $e->getMessage() . "\n";
}

// 3. テーブル確認
echo "3. Table exists: ";
$tbl = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='votes'");
echo ($tbl ? "YES" : "NO") . "\n";

// 4. テーブル作成（なければ）
if (!$tbl) {
    echo "3b. Create table: ";
    try {
        $ok = $db->exec('CREATE TABLE votes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            artist_no TEXT NOT NULL,
            token TEXT NOT NULL,
            ip_hash TEXT NOT NULL,
            ua_hash TEXT NOT NULL,
            voted_at TEXT NOT NULL DEFAULT (datetime("now","localtime")),
            UNIQUE(token, artist_no)
        )');
        echo ($ok ? "OK" : "FAIL - " . $db->lastErrorMsg()) . "\n";
    } catch (Exception $e) {
        echo "FAIL - " . $e->getMessage() . "\n";
    }
}

// 5. INSERT テスト
echo "4. INSERT test: ";
try {
    $stmt = $db->prepare('INSERT INTO votes (artist_no, token, ip_hash, ua_hash) VALUES (:a, :t, :ip, :ua)');
    $stmt->bindValue(':a', '999', SQLITE3_TEXT);
    $stmt->bindValue(':t', 'test_token_' . time(), SQLITE3_TEXT);
    $stmt->bindValue(':ip', 'test_ip', SQLITE3_TEXT);
    $stmt->bindValue(':ua', 'test_ua', SQLITE3_TEXT);
    $result = $stmt->execute();
    echo ($result ? "OK" : "FAIL - " . $db->lastErrorMsg()) . "\n";
} catch (Exception $e) {
    echo "FAIL - " . $e->getMessage() . "\n";
}

// 6. SELECT テスト
echo "5. SELECT test: ";
try {
    $cnt = $db->querySingle('SELECT COUNT(*) FROM votes');
    echo "OK (rows: $cnt)\n";
} catch (Exception $e) {
    echo "FAIL - " . $e->getMessage() . "\n";
}

// 7. テストデータ削除
echo "6. Cleanup: ";
try {
    $db->exec("DELETE FROM votes WHERE artist_no = '999'");
    echo "OK\n";
} catch (Exception $e) {
    echo "FAIL - " . $e->getMessage() . "\n";
}

// 8. ファイル権限
echo "\n=== ファイル情報 ===\n";
echo "DB path: $dbPath\n";
echo "DB size: " . filesize($dbPath) . " bytes\n";
echo "DB perms: " . substr(sprintf('%o', fileperms($dbPath)), -4) . "\n";
echo "DB writable: " . (is_writable($dbPath) ? "YES" : "NO") . "\n";

$walPath = $dbPath . '-wal';
if (file_exists($walPath)) {
    echo "WAL perms: " . substr(sprintf('%o', fileperms($walPath)), -4) . "\n";
    echo "WAL writable: " . (is_writable($walPath) ? "YES" : "NO") . "\n";
}

$shmPath = $dbPath . '-shm';
if (file_exists($shmPath)) {
    echo "SHM perms: " . substr(sprintf('%o', fileperms($shmPath)), -4) . "\n";
    echo "SHM writable: " . (is_writable($shmPath) ? "YES" : "NO") . "\n";
}

echo "\n=== テスト完了 ===\n";
