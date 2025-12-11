<?php
// payments_action.php — aksi ubah status bayar & reset periode
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/auth.php'; require_admin();

function ym_valid($s){ return (bool)preg_match('/^\d{4}-\d{2}$/', $s); }

function go_back($tab, $period, $tech = ''){
  $qs = ['tab'=>$tab ?: 'unpaid','period'=>$period];
  if ($tech !== '') $qs['tech'] = $tech;
  $url = 'payments.php?'.http_build_query($qs);
  header('Location: '.$url, true, 303);
  echo '<!doctype html><meta charset="utf-8"><meta http-equiv="refresh" content="0;url='.$url.'"><script>location.replace("'.$url.'")</script>';
  exit;
}

try {
  verify_csrf();

  $action = $_POST['action'] ?? '';
  $tab    = $_POST['tab'] ?? 'unpaid';
  $tech   = trim($_POST['tech'] ?? '');
  $period = (isset($_POST['period']) && ym_valid($_POST['period'])) ? $_POST['period'] : date('Y-m');

  if ($action === 'mark_paid') {
    $cid     = (int)($_POST['customer_id'] ?? 0);
    $amount  = (int)($_POST['amount'] ?? 0);
    $paid_by = trim($_POST['paid_by'] ?? ($_SESSION['admin_user']['username'] ?? 'admin'));

    if ($cid <= 0) { $_SESSION['flash'] = 'Gagal: pelanggan tidak valid.'; go_back($tab,$period,$tech); }
    if ($paid_by === '') $paid_by = 'admin';

    // upsert: set paid_at=NOW
    $sql = "INSERT INTO payments (customer_id, period, amount, paid_at, paid_by)
            VALUES (?, ?, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE
              amount  = VALUES(amount),
              paid_at = NOW(),
              paid_by = VALUES(paid_by)";
    $st = $pdo->prepare($sql);
    $ok = $st->execute([$cid, $period, $amount, $paid_by]);
    $_SESSION['flash'] = $ok ? 'Status diubah: DITANDAI SUDAH BAYAR.' : 'Gagal menyimpan.';
    go_back('paid', $period, $tech); // setelah mark paid, arahkan ke tab paid

  } elseif ($action === 'mark_unpaid') {
    $cid = (int)($_POST['customer_id'] ?? 0);
    if ($cid <= 0) { $_SESSION['flash'] = 'Gagal: pelanggan tidak valid.'; go_back($tab,$period,$tech); }

    $st = $pdo->prepare("UPDATE payments SET paid_at = NULL, paid_by = NULL WHERE customer_id = ? AND period = ?");
    $st->execute([$cid, $period]);

    $_SESSION['flash'] = 'Status diubah: dikembalikan ke BELUM BAYAR.';
    go_back('unpaid', $period, $tech);

  } elseif ($action === 'reset_period') {
    $st = $pdo->prepare("UPDATE payments SET paid_at = NULL, paid_by = NULL WHERE period = ?");
    $st->execute([$period]);
    $_SESSION['flash'] = "Reset selesai untuk periode $period: ".$st->rowCount()." data diubah.";
    go_back('unpaid', $period, $tech);

  } else {
    $_SESSION['flash'] = 'Aksi tidak dikenal.';
    go_back($tab, $period, $tech);
  }

} catch (Throwable $e) {
  $_SESSION['flash'] = 'Terjadi kesalahan: '.$e->getMessage();
  go_back($_POST['tab'] ?? 'unpaid', $_POST['period'] ?? date('Y-m'), $_POST['tech'] ?? '');
}
