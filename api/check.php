<?php
header('Content-Type: text/plain; charset=utf-8');

echo "=== 底辺AI音楽フェス サーバー診断 ===\n\n";

// PHP バージョン
echo "PHP Version: " . phpversion() . "\n";

// SQLite3 拡張
echo "SQLite3 extension: " . (class_exists('SQLite3') ? '✅ OK' : '❌ NOT AVAILABLE') . "\n";

// PDO SQLite
echo "PDO SQLite: " . (in_array('sqlite', PDO::getAvailableDrivers()) ? '✅ OK' : '❌ NOT AVAILABLE') . "\n";

// JSON
echo "JSON extension: " . (function_exists('json_encode') ? '✅ OK' : '❌ NOT AVAILABLE') . "\n";

// api/ ディレクトリの書き込み権限
$dir = __DIR__;
echo "\nDirectory: $dir\n";
echo "Dir writable: " . (is_writable($dir) ? '✅ YES' : '❌ NO') . "\n";
echo "Dir permissions: " . substr(sprintf('%o', fileperms($dir)), -4) . "\n";

// テスト書き込み
$testFile = $dir . '/test_write.tmp';
$writeOk = @file_put_contents($testFile, 'test');
echo "File write test: " . ($writeOk !== false ? '✅ OK' : '❌ FAILED') . "\n";
if ($writeOk !== false) @unlink($testFile);

// SQLite3 テスト
if (class_exists('SQLite3')) {
    try {
        $testDb = $dir . '/test.sqlite3';
        $db = new SQLite3($testDb);
        $db->exec('CREATE TABLE IF NOT EXISTS t (id INTEGER)');
        $db->close();
        @unlink($testDb);
        echo "SQLite3 create DB: ✅ OK\n";
    } catch (Exception $e) {
        echo "SQLite3 create DB: ❌ " . $e->getMessage() . "\n";
    }
}

// PDO SQLite テスト
if (in_array('sqlite', PDO::getAvailableDrivers())) {
    try {
        $testDb = $dir . '/test_pdo.sqlite3';
        $pdo = new PDO('sqlite:' . $testDb);
        $pdo->exec('CREATE TABLE IF NOT EXISTS t (id INTEGER)');
        $pdo = null;
        @unlink($testDb);
        echo "PDO SQLite create DB: ✅ OK\n";
    } catch (Exception $e) {
        echo "PDO SQLite create DB: ❌ " . $e->getMessage() . "\n";
    }
}

// PHP エラー表示設定
echo "\ndisplay_errors: " . ini_get('display_errors') . "\n";
echo "error_reporting: " . ini_get('error_reporting') . "\n";

echo "\n=== 診断完了 ===\n";
