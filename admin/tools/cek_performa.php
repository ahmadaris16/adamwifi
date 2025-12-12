<?php
// cek_performa.php ? Diagnosa singkat performa hosting (DB, query ringan, disk I/O, DNS).
// Jalankan untuk memeriksa apakah lambat karena DB/disk/dns.
ini_set('display_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/config/persiapan_admin.php'; // butuh koneksi DB & session

// Timer sederhana untuk ukur jeda antar blok
function t(){ static $t=null; $now=microtime(true); $d=$t?($now-$t):0; $t=$now; return $d; }
// Cetak key/value rata kiri
function pr($k,$v){ printf("%-28s : %s
",$k,$v); }

header('Content-Type:text/plain; charset=utf-8');
echo "AdamWifi Admin ? Cek Performa
";
echo "-------------------------------------------
";

// 0) Info PHP/OPcache
pr('PHP version', PHP_VERSION);
pr('SAPI', php_sapi_name());
pr('memory_limit', ini_get('memory_limit'));
pr('max_execution_time', ini_get('max_execution_time'));
pr('OPcache enabled', function_exists('opcache_get_status') ? ((opcache_get_status(false)['opcache_enabled']??false)?'YES':'NO') : 'NO EXT');
echo "
"; t(); // reset timer

// 1) Tes koneksi DB (pakai $pdo dari persiapan_admin.php)
try{
  $pdo->query('SELECT 1'); $db_ok='OK';
}catch(Throwable $e){ $db_ok='ERR: '.$e->getMessage(); }
pr('DB connect/test', $db_ok);
pr('DB connect time (approx)', number_format(t()*1000,1).' ms');

// 2) Query ringan (COUNT) untuk tabel utama
function quickCount($pdo,$sql){ $st=$pdo->query($sql); return (int)$st->fetchColumn(); }
$has_pppoe=true; try{ $c1=quickCount($pdo,"SELECT COUNT(*) FROM pppoe_status"); }catch(Throwable $e){ $has_pppoe=false; $c1=0; }
$has_cust=true;  try{ $c2=quickCount($pdo,"SELECT COUNT(*) FROM customers"); }catch(Throwable $e){ $has_cust=false; $c2=0; }
pr('pppoe_status rows', $has_pppoe?$c1:'(table missing)');
pr('customers rows', $has_cust?$c2:'(table missing)');
pr('Query count time', number_format(t()*1000,1).' ms');

// 3) Query dengan ORDER BY + LIMIT (simulasi query tabel)
if($has_pppoe){
  $st=$pdo->query("SELECT username FROM pppoe_status ORDER BY username DESC LIMIT 200");
  $st->fetchAll();
  pr('pppoe_status ORDER 200', number_format(t()*1000,1).' ms');
}
if($has_cust){
  $st=$pdo->query("SELECT id,name FROM customers ORDER BY id DESC LIMIT 200");
  $st->fetchAll();
  pr('customers ORDER 200', number_format(t()*1000,1).' ms');
}

// 4) Tes disk I/O kecil (tulis/baca 256KB di /tmp)
$fn=sys_get_temp_dir().'/io_test_'.uniqid().'.bin';
$bytes=1024*256; // 256 KB
$t0=microtime(true);
file_put_contents($fn, random_bytes($bytes));
$w = microtime(true)-$t0;
$t0=microtime(true);
$d = file_get_contents($fn);
$R = microtime(true)-$t0;
@unlink($fn);
pr('Disk write 256KB', number_format($w*1000,1).' ms');
pr('Disk read 256KB', number_format($R*1000,1).' ms');

// 5) DNS lookup host sendiri (cek resolver)
$t0=microtime(true);
$ip = gethostbyname($_SERVER['HTTP_HOST'] ?? 'localhost');
$dns = microtime(true)-$t0;
pr('DNS lookup self', number_format($dns*1000,1).' ms ('.$ip.')');

// Catatan interpretasi
echo "
Catatan:
";
echo "- DB > 300ms berulang: kemungkinan MySQL padat/kena limit.
";
echo "- Disk > 100ms: I/O hosting sedang lambat.
";
echo "- DNS > 100ms: resolver lambat (jarang, tapi bisa bikin delay awal).
";
echo "- Jika semua <100ms tapi tetap lambat, cek cPanel > Resource Usage (CPU/EP/IO).
";
