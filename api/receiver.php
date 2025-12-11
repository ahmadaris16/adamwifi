<?php
// receiver.php — kirim notifikasi saat status BERUBAH (online <-> offline)

require_once __DIR__ . '/../config/config.php'; // pakai $koneksi (PDO) & boleh berisi $secret_key

/* ====== KONFIGURASI FCM ====== */
$service_account_key_file = '/home/adah1658/kunci_firebase/adamwifi-notifikasi-firebase-adminsdk-fbsvc-c1227b1733.json';
$project_id = 'adamwifi-notifikasi';

/* ====== KUNCI KEAMANAN ====== */
if (!isset($secret_key)) { $secret_key = 'adamwifi2024secret'; } // fallback kalau belum ada di config.php

/* ====== UTIL ====== */
function b64url($s){ return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }

function getGoogleAccessToken($key_file){
  if (!file_exists($key_file)) return false;
  $key = json_decode(file_get_contents($key_file), true);
  if (!$key) return false;

  $header  = b64url(json_encode(['alg'=>'RS256','typ'=>'JWT']));
  $payload = b64url(json_encode([
    'iss'   => $key['client_email'],
    'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
    'aud'   => 'https://oauth2.googleapis.com/token',
    'exp'   => time()+3600,
    'iat'   => time()
  ]));
  openssl_sign($header.'.'.$payload, $sig, $key['private_key'], 'SHA256');
  $jwt = $header.'.'.$payload.'.'.b64url($sig);

  $ch = curl_init('https://oauth2.googleapis.com/token');
  curl_setopt_array($ch, [
    CURLOPT_POST=>true,
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_POSTFIELDS=>http_build_query([
      'grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer',
      'assertion'=>$jwt,
    ]),
  ]);
  $res = curl_exec($ch);
  curl_close($ch);
  $data = json_decode($res,true);
  return $data['access_token'] ?? false;
}

function sendFCMv1($access_token, $project_id, $tokens, $title, $body){
  if (!$tokens) return;
  $url = "https://fcm.googleapis.com/v1/projects/{$project_id}/messages:send";
  $hdr = [
    'Authorization: Bearer '.$access_token,
    'Content-Type: application/json'
  ];
  foreach($tokens as $t){
    $msg = [
      'message'=>[
        'token'=>$t,
        'notification'=>['title'=>$title,'body'=>$body],
        'android'=>['notification'=>['channel_id'=>'pppoe_status_channel']]
      ]
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_POST=>true,
      CURLOPT_HTTPHEADER=>$hdr,
      CURLOPT_RETURNTRANSFER=>true,
      CURLOPT_SSL_VERIFYPEER=>false,
      CURLOPT_POSTFIELDS=>json_encode($msg),
    ]);
    curl_exec($ch);
    curl_close($ch);
  }
}

/* ====== AUTH ====== */
$key = $_GET['key'] ?? $_POST['key'] ?? '';
if ($key !== $secret_key) { http_response_code(401); echo 'Unauthorized'; exit; }

/* ====== AMBIL TOKEN SEKALI ====== */
$tokens = $koneksi->query("SELECT token FROM fcm_tokens")->fetchAll(PDO::FETCH_COLUMN);

/* ====== QUICK PATH: ?user=&status=&ip= ====== */
if (isset($_GET['user'], $_GET['status'])) {
  $u = trim($_GET['user']);
  $st = strtolower(trim($_GET['status'])); // online|offline
  $ip = trim($_GET['ip'] ?? '');

  if ($u === '' || !in_array($st, ['online','offline'], true)) {
    http_response_code(400); echo 'bad params'; exit;
  }

  $koneksi->beginTransaction();
  try {
    $old = $koneksi->prepare("SELECT status,outage_since FROM pppoe_status WHERE username=? LIMIT 1");
    $old->execute([$u]);
    $row = $old->fetch(PDO::FETCH_ASSOC);
    $oldStatus = $row['status'] ?? null;
    $oldOutage = $row['outage_since'] ?? null;

    if ($row) {
      if ($st === 'online') {
        $sql = "UPDATE pppoe_status SET status='online', ip=?, last_update=NOW(), last_up=NOW(), outage_since=NULL WHERE username=? LIMIT 1";
        $koneksi->prepare($sql)->execute([$ip, $u]);
      } else {
        $sql = "UPDATE pppoe_status SET status='offline', ip=?, last_update=NOW(), last_down=NOW(), outage_since=COALESCE(outage_since,NOW()) WHERE username=? LIMIT 1";
        $koneksi->prepare($sql)->execute([$ip, $u]);
      }
    } else {
      if ($st === 'online') {
        $sql = "INSERT INTO pppoe_status (username,ip,status,last_update,last_up) VALUES (?,?, 'online', NOW(), NOW())";
        $koneksi->prepare($sql)->execute([$u,$ip]);
      } else {
        $sql = "INSERT INTO pppoe_status (username,ip,status,last_update,last_down,outage_since) VALUES (?,?, 'offline', NOW(), NOW(), NOW())";
        $koneksi->prepare($sql)->execute([$u,$ip]);
      }
      $oldStatus = null;
    }

    $koneksi->commit();

    // === KIRIM NOTIF JIKA BERUBAH ===
    if ($oldStatus === null || strtolower($oldStatus) !== $st) {
      $access = getGoogleAccessToken($service_account_key_file);
      if ($access && $tokens) {
        if ($st === 'offline') {
          $title = "Pelanggan Offline";
          $body  = "Pengguna '$u' baru saja offline.";
        } else {
          // jika ada outage_since lama, bisa hitung durasi
          $title = "Pelanggan Online";
          $body  = "Pengguna '$u' kembali online.";
        }
        sendFCMv1($access, $project_id, $tokens, $title, $body);
        // simpan sejarah singkat
        $koneksi->prepare("INSERT INTO notification_history (message) VALUES (?)")->execute([$body]);
      }
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>true,'user'=>$u,'status'=>$st,'ip'=>$ip]);
    exit;

  } catch(Throwable $e){
    $koneksi->rollBack();
    http_response_code(500);
    echo "error: ".$e->getMessage();
    exit;
  }
}

/* ====== SNAPSHOT PATH: data="name,status,ip|..." (GET/POST) ====== */
$data = $_GET['data'] ?? $_POST['data'] ?? '';
if ($data === '') { http_response_code(400); echo 'Error: no data'; exit; }

$koneksi->beginTransaction();
try {
  // status DB saat ini
  $db = $koneksi->query("SELECT username,status FROM pppoe_status")->fetchAll(PDO::FETCH_KEY_PAIR);

  $parts = array_filter(explode('|', rtrim($data,'|')));
  $fromRouter = []; // username => [status, ip]
  foreach ($parts as $p){
    $a = explode(',', $p);
    $name = trim($a[0] ?? '');
    $st   = strtolower(trim($a[1] ?? ''));
    $ip   = trim($a[2] ?? '');
    if ($name==='' || !in_array($st, ['online','offline'], true)) continue;
    $fromRouter[$name] = ['status'=>$st,'ip'=>$ip];
  }

  $wentOffline = [];
  $wentOnline  = [];

  foreach ($fromRouter as $u => $d){
    $new = $d['status']; $ip = $d['ip'];
    $cur = $db[$u] ?? null;

    if ($cur === null) {
      // user baru
      if ($new === 'online') {
        $koneksi->prepare("INSERT INTO pppoe_status (username,ip,status,last_update,last_up) VALUES (?,?, 'online', NOW(), NOW())")->execute([$u,$ip]);
        $wentOnline[] = $u;
      } else {
        $koneksi->prepare("INSERT INTO pppoe_status (username,ip,status,last_update,last_down,outage_since) VALUES (?,?, 'offline', NOW(), NOW(), NOW())")->execute([$u,$ip]);
        $wentOffline[] = $u;
      }
    } else {
      if ($cur !== $new) {
        if ($new === 'online') {
          $koneksi->prepare("UPDATE pppoe_status SET status='online', ip=?, last_update=NOW(), last_up=NOW(), outage_since=NULL WHERE username=?")->execute([$ip,$u]);
          $wentOnline[] = $u;
        } else {
          $koneksi->prepare("UPDATE pppoe_status SET status='offline', ip=?, last_update=NOW(), last_down=NOW(), outage_since=COALESCE(outage_since,NOW()) WHERE username=?")->execute([$ip,$u]);
          $wentOffline[] = $u;
        }
      } else {
        // hanya segarkan timestamp & ip
        if ($new === 'online') {
          $koneksi->prepare("UPDATE pppoe_status SET ip=?, last_update=NOW() WHERE username=?")->execute([$ip,$u]);
        } else {
          $koneksi->prepare("UPDATE pppoe_status SET ip=?, last_update=NOW(), outage_since=COALESCE(outage_since,NOW()) WHERE username=?")->execute([$ip,$u]);
        }
      }
    }
  }

  // opsional: hapus user di DB yang tidak ada di snapshot
  // $missing = array_diff_key($db, $fromRouter);
  // if ($missing) {
  //   $in = implode(',', array_fill(0,count($missing),'?'));
  //   $koneksi->prepare("DELETE FROM pppoe_status WHERE username IN ($in)")->execute(array_keys($missing));
  // }

  $koneksi->commit();

  // === KIRIM NOTIF UNTUK PERUBAHAN ===
  if ($tokens) {
    $access = getGoogleAccessToken($service_account_key_file);
    if ($access) {
      foreach ($wentOffline as $u) {
        sendFCMv1($access, $project_id, $tokens, "Pelanggan Offline", "Pengguna '$u' baru saja offline.");
        $koneksi->prepare("INSERT INTO notification_history (message) VALUES (?)")->execute(["Pengguna '$u' baru saja offline."]);
      }
      foreach ($wentOnline as $u) {
        sendFCMv1($access, $project_id, $tokens, "Pelanggan Online", "Pengguna '$u' kembali online.");
        $koneksi->prepare("INSERT INTO notification_history (message) VALUES (?)")->execute(["Pengguna '$u' kembali online."]);
      }
    }
  }

  echo "OK";
} catch(Throwable $e){
  $koneksi->rollBack();
  http_response_code(500);
  echo "error: ".$e->getMessage();
}
