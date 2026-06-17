<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');

/**
 * 底辺AI音楽フェス — 期待投票API（JSONファイルベース）
 *
 * ── 一般用 ──
 * GET /api/vote.php                                            → 全投票数を返す
 * GET /api/vote.php?action=vote&artist=001                     → 投票
 *
 * ── 管理用（要パスワード）──
 * GET ?admin=1&pass=XXXX                                       → 集計確認
 * GET ?admin=1&pass=XXXX&format=csv                            → CSV DL
 * GET ?admin=1&pass=XXXX&action=reset                          → 実票リセット
 * GET ?admin=1&pass=XXXX&action=reset_all                      → 全リセット
 * GET ?admin=1&pass=XXXX&action=boost&artist=001&count=5       → ゲタ個別設定
 * GET ?admin=1&pass=XXXX&action=boost_bulk&data=001:5,002:3    → ゲタ一括設定
 * GET ?admin=1&pass=XXXX&action=boost_list                     → ゲタ一覧
 * GET ?admin=1&pass=XXXX&action=reset_all_boost                → ゲタ全消し
 *
 * ── デバッグ ──
 * GET ?action=vote&artist=001&debug=teihen                     → 重複なし投票
 */

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

define('DATA_FILE',   __DIR__ . '/votes_data.json');
define('BOOST_FILE',  __DIR__ . '/votes_boost.json');
define('ADMIN_PASS',  'teihen2026admin');
define('SALT',        'teihen_ai_fes_2026_salt');
define('DEBUG_KEY',   'teihen');

function jsonOut($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonErr($msg, $code = 500) {
    jsonOut(array('error' => $msg), $code);
}

function isDebug() {
    if (DEBUG_KEY === false) return false;
    return (isset($_GET['debug']) && $_GET['debug'] === DEBUG_KEY);
}

function getIPHash() {
    $ip = isset($_SERVER['HTTP_X_FORWARDED_FOR'])
        ? $_SERVER['HTTP_X_FORWARDED_FOR']
        : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown');
    $ip = explode(',', $ip);
    return hash('sha256', trim($ip[0]) . SALT);
}

function getUAHash() {
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown';
    return hash('sha256', $ua . SALT);
}

function getOrCreateToken() {
    if (isset($_COOKIE['vote_token']) && strlen($_COOKIE['vote_token']) === 64) {
        return $_COOKIE['vote_token'];
    }
    $token = bin2hex(random_bytes(32));
    setcookie('vote_token', $token, time() + 86400 * 90, '/', '', true, false);
    return $token;
}

function loadData() {
    if (!file_exists(DATA_FILE)) return array('votes' => array());
    $raw = file_get_contents(DATA_FILE);
    if ($raw === false || $raw === '') return array('votes' => array());
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['votes'])) return array('votes' => array());
    return $data;
}

function loadBoost() {
    if (!file_exists(BOOST_FILE)) return array();
    $raw = file_get_contents(BOOST_FILE);
    if ($raw === false || $raw === '') return array();
    $data = json_decode($raw, true);
    if (!is_array($data)) return array();
    return $data;
}

function saveBoost($boost) {
    // 値が0以下のものは除去
    $clean = array();
    foreach ($boost as $k => $v) {
        if ((int)$v > 0) $clean[$k] = (int)$v;
    }
    file_put_contents(BOOST_FILE, json_encode($clean, JSON_UNESCAPED_UNICODE));
}

function withLock($callback) {
    $fp = fopen(DATA_FILE, 'c+');
    if (!$fp) jsonErr('Cannot open data file');
    if (!flock($fp, LOCK_EX)) { fclose($fp); jsonErr('Cannot lock data file'); }
    $raw = stream_get_contents($fp);
    $data = ($raw !== false && $raw !== '') ? json_decode($raw, true) : null;
    if (!is_array($data) || !isset($data['votes'])) $data = array('votes' => array());
    $result = $callback($data);
    if ($result['save']) {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($result['data'], JSON_UNESCAPED_UNICODE));
        fflush($fp);
    }
    flock($fp, LOCK_UN);
    fclose($fp);
    return $result;
}

function countVotesRaw($votes) {
    $counts = array();
    foreach ($votes as $v) {
        $no = $v['artist_no'];
        if (!isset($counts[$no])) $counts[$no] = 0;
        $counts[$no]++;
    }
    return $counts;
}

function countVotesPublic($votes, $boost) {
    $counts = countVotesRaw($votes);
    foreach ($boost as $no => $add) {
        if ($add <= 0) continue;
        if (!isset($counts[$no])) $counts[$no] = 0;
        $counts[$no] += (int)$add;
    }
    return $counts;
}

function findMyVote($votes, $token, $ipHash, $uaHash) {
    foreach ($votes as $v) {
        if ($v['token'] === $token) return $v['artist_no'];
    }
    foreach ($votes as $v) {
        if ($v['ip_hash'] === $ipHash && $v['ua_hash'] === $uaHash) return $v['artist_no'];
    }
    return null;
}

try {

    $action = isset($_GET['action']) ? $_GET['action'] : '';

    /* ══════════════════════════════════════
       管理用
       ══════════════════════════════════════ */
    if (isset($_GET['admin'])) {
        $pass = isset($_GET['pass']) ? $_GET['pass'] : '';
        if ($pass !== ADMIN_PASS) jsonErr('Unauthorized', 401);

        // 実票リセット
        if ($action === 'reset') {
            file_put_contents(DATA_FILE, json_encode(array('votes' => array())));
            setcookie('vote_token', '', time() - 3600, '/', '', true, false);
            jsonOut(array('ok' => true, 'message' => '実投票データをリセットしました（ゲタは保持）'));
        }

        // 全リセット
        if ($action === 'reset_all') {
            file_put_contents(DATA_FILE, json_encode(array('votes' => array())));
            file_put_contents(BOOST_FILE, json_encode(array()));
            setcookie('vote_token', '', time() - 3600, '/', '', true, false);
            jsonOut(array('ok' => true, 'message' => '全データ（実票＋ゲタ）をリセットしました'));
        }

        // ゲタ全消し
        if ($action === 'reset_all_boost') {
            file_put_contents(BOOST_FILE, json_encode(array()));
            jsonOut(array('ok' => true, 'message' => 'ゲタを全て解除しました'));
        }

        // ゲタ個別
        if ($action === 'boost') {
            $artistNo = isset($_GET['artist']) ? $_GET['artist'] : '';
            $count    = isset($_GET['count']) ? (int)$_GET['count'] : 0;
            if (!preg_match('/^\d{3}$/', $artistNo)) jsonErr('Invalid artist number', 400);
            $boost = loadBoost();
            if ($count <= 0) {
                unset($boost[$artistNo]);
            } else {
                $boost[$artistNo] = $count;
            }
            saveBoost($boost);
            jsonOut(array('ok' => true, 'artist' => $artistNo, 'boost' => $count, 'all_boosts' => $boost));
        }

        // ゲタ一括
        if ($action === 'boost_bulk') {
            $raw = isset($_GET['data']) ? $_GET['data'] : '';
            if ($raw === '') jsonErr('data parameter required', 400);
            $boost = loadBoost();
            $pairs = explode(',', $raw);
            $updated = array();
            foreach ($pairs as $pair) {
                $parts = explode(':', trim($pair));
                if (count($parts) !== 2) continue;
                $no  = trim($parts[0]);
                $cnt = (int)trim($parts[1]);
                if (!preg_match('/^\d{3}$/', $no)) continue;
                if ($cnt <= 0) {
                    unset($boost[$no]);
                } else {
                    $boost[$no] = $cnt;
                }
                $updated[$no] = $cnt;
            }
            saveBoost($boost);
            jsonOut(array('ok' => true, 'updated' => $updated, 'all_boosts' => $boost));
        }

        // ゲタ一覧
        if ($action === 'boost_list') {
            jsonOut(array('boosts' => loadBoost()));
        }

        // 集計表示
        $data  = loadData();
        $boost = loadBoost();
        $rawCounts    = countVotesRaw($data['votes']);
        $publicCounts = countVotesPublic($data['votes'], $boost);
        arsort($publicCounts);

        if (isset($_GET['format']) && $_GET['format'] === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="votes_' . date('Ymd_His') . '.csv"');
            echo "artist_no,real_votes,boost,total\n";
            foreach ($publicCounts as $no => $total) {
                $real = isset($rawCounts[$no]) ? $rawCounts[$no] : 0;
                $bst  = isset($boost[$no]) ? $boost[$no] : 0;
                echo $no . ',' . $real . ',' . $bst . ',' . $total . "\n";
            }
            exit;
        }

        $tokens = array();
        foreach ($data['votes'] as $v) { $tokens[$v['token']] = true; }
        jsonOut(array(
            'total_real_votes' => count($data['votes']),
            'unique_voters'    => count($tokens),
            'boosts'           => $boost,
            'by_artist'        => $publicCounts,
            'raw_counts'       => $rawCounts
        ));
    }

    /* ══════════════════════════════════════
       投票
       ══════════════════════════════════════ */
    if ($action === 'vote') {
        $artistNo = isset($_GET['artist']) ? $_GET['artist'] : '';
        if (!preg_match('/^\d{3}$/', $artistNo)) jsonErr('Invalid artist number', 400);

        $debug  = isDebug();
        $token  = $debug ? bin2hex(random_bytes(32)) : getOrCreateToken();
        $ipHash = $debug ? hash('sha256', 'debug_' . microtime(true)) : getIPHash();
        $uaHash = $debug ? hash('sha256', 'debug_' . mt_rand()) : getUAHash();
        $boost  = loadBoost();

        $result = withLock(function($data) use ($artistNo, $token, $ipHash, $uaHash, $debug, $boost) {
            if (!$debug) {
                $existing = findMyVote($data['votes'], $token, $ipHash, $uaHash);
                if ($existing) {
                    return array(
                        'save' => false,
                        'data' => $data,
                        'response' => array('error' => 'already_voted', 'message' => '既に投票済みです', 'myVote' => $existing),
                        'code' => 409
                    );
                }
            }

            $data['votes'][] = array(
                'artist_no' => $artistNo,
                'token'     => $token,
                'ip_hash'   => $ipHash,
                'ua_hash'   => $uaHash,
                'voted_at'  => date('Y-m-d H:i:s')
            );

            $cnt = 0;
            foreach ($data['votes'] as $v) {
                if ($v['artist_no'] === $artistNo) $cnt++;
            }
            $cnt += isset($boost[$artistNo]) ? (int)$boost[$artistNo] : 0;

            $resp = array('ok' => true, 'artist' => $artistNo, 'count' => $cnt, 'myVote' => $artistNo);
            if ($debug) $resp['debug'] = true;

            return array(
                'save' => true,
                'data' => $data,
                'response' => $resp,
                'code' => 200
            );
        });

        jsonOut($result['response'], $result['code']);
    }

    /* ══════════════════════════════════════
       通常取得（実票 + ゲタ合計）
       ══════════════════════════════════════ */
    $data   = loadData();
    $boost  = loadBoost();
    $token  = getOrCreateToken();
    $ipHash = getIPHash();
    $uaHash = getUAHash();

    $counts = countVotesPublic($data['votes'], $boost);
    $myVote = findMyVote($data['votes'], $token, $ipHash, $uaHash);

    jsonOut(array('counts' => $counts, 'myVote' => $myVote));

} catch (Exception $e) {
    jsonErr('Server error: ' . $e->getMessage());
}
