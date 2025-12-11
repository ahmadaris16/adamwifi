<?php
require_once __DIR__ . '/admin_config.php';

function q(PDO $pdo,$sql,$p=[]){ $st=$pdo->prepare($sql); $st->execute($p); return $st; }
function verify_mysql_old_hash($plain,$stored){
  if (!is_string($stored) || strlen($stored)!==41 || $stored[0]!=='*') return false;
  $s1 = sha1($plain, true); $s2 = strtoupper(sha1($s1)); return hash_equals($stored, '*'.$s2);
}
function is_php_hash($h){ return is_string($h) && preg_match('/^\$(2[aby]|argon2(id|i)?)\$/i',$h); }
function go_dashboard(){
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http';
  $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
  header('Location: '.$scheme.'://'.$_SERVER['HTTP_HOST'].$base.'/index.php'); exit;
}

$already = (
  (!empty($_SESSION['is_admin']) && !empty($_SESSION['admin_id'])) ||
  (!empty($_SESSION['admin']) && is_array($_SESSION['admin'])) ||
  (!empty($_SESSION['logged_in']) && !empty($_SESSION['user_id']))
);
if ($already) { go_dashboard(); }

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $u = trim($_POST['username'] ?? ''); $p = (string)($_POST['password'] ?? '');
  if ($u==='' || $p===''){ header('Location: login.php?error=empty'); exit; }

  try{
    $user = q($pdo,"SELECT * FROM admin_users WHERE username=? LIMIT 1",[$u])->fetch(PDO::FETCH_ASSOC);
    $ok=false; $err='';
    if ($user){
      if (array_key_exists('is_active',$user) && (string)$user['is_active']==='0'){ $err='Akun nonaktif'; }
      else {
        foreach (['password_hash','password'] as $col){
          if ($ok) break;
          if (array_key_exists($col,$user) && $user[$col]!==null){
            $h=(string)$user[$col];
            if (is_php_hash($h)) $ok = password_verify($p,$h);
            if (!$ok && strlen($h)===41 && $h[0]==='*') $ok = verify_mysql_old_hash($p,$h);
            if (!$ok && strlen($h)===32 && ctype_xdigit($h)) $ok = hash_equals(strtolower($h), md5($p));
            if (!$ok && $h===$p) $ok = true;
          }
        }
        if (!$ok){
          try{ $ok=(bool)q($pdo,"SELECT 1 FROM admin_users WHERE username=? AND password=PASSWORD(?) LIMIT 1",[$u,$p])->fetchColumn(); }catch(Throwable $e){}
          if(!$ok){ try{ $ok=(bool)q($pdo,"SELECT 1 FROM admin_users WHERE username=? AND password_hash=PASSWORD(?) LIMIT 1",[$u,$p])->fetchColumn(); }catch(Throwable $e){} }
        }
      }
    }

    if ($ok){
      session_regenerate_id(true);
      $id=$user['id']??null; $name=$user['username']??$u; $role=$user['role']??'Owner';

      // KUNCI sesi yang dipakai auth.php
      $_SESSION['admin_user'] = [
        'id'       => $id,
        'username' => $name,
        'role'     => $role,
      ];

      // Kompatibilitas lama (opsional)
      $_SESSION['is_admin']=true; $_SESSION['admin_id']=$id; $_SESSION['admin_username']=$name; $_SESSION['role']=$role;
      $_SESSION['logged_in']=true; $_SESSION['user_id']=$id; $_SESSION['username']=$name;
      $_SESSION['admin']=['id'=>$id,'username'=>$name,'role'=>$role]; $_SESSION['user']=$_SESSION['admin'];

      go_dashboard();
    } else {
      header('Location: login.php?error='.urlencode($err?:'invalid')); exit;
    }
  }catch(Throwable $e){ header('Location: login.php?error=system'); exit; }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login &mdash; Adam Wifi</title>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');
*{margin:0;padding:0;box-sizing:border-box}
:root{--primary:#fbbf24;--primary-light:#fde047;--primary-dark:#f59e0b;--secondary:#06b6d4;--success:#10b981;--danger:#ef4444;--dark:#0f172a;--dark-light:#1e293b;--gray:#64748b;--light:#f8fafc;--white:#ffffff;--gradient:linear-gradient(135deg,#fbbf24 0%,#f59e0b 50%,#dc2626 100%);--gradient-soft:linear-gradient(135deg,rgba(251,191,36,.1) 0%,rgba(245,158,11,.05) 100%)}
body{font-family:'Plus Jakarta Sans',system-ui,-apple-system,sans-serif;background:var(--dark);color:var(--light);min-height:100vh;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden}
.bg-wrapper{position:fixed;inset:0;z-index:0}
.orb{position:absolute;border-radius:50%;filter:blur(100px);opacity:.4;animation:float 20s ease-in-out infinite}
.orb1{width:600px;height:600px;background:radial-gradient(circle,rgba(251,191,36,.8) 0%,transparent 70%);top:-300px;left:-200px}
.orb2{width:500px;height:500px;background:radial-gradient(circle,rgba(245,158,11,.6) 0%,transparent 70%);bottom:-250px;right:-250px;animation-delay:-10s}
.orb3{width:400px;height:400px;background:radial-gradient(circle,rgba(6,182,212,.4) 0%,transparent 70%);top:50%;left:50%;transform:translate(-50%,-50%);animation-delay:-5s}
@keyframes float{0%,100%{transform:translate(0,0) scale(1)}33%{transform:translate(30px,-30px) scale(1.1)}66%{transform:translate(-20px,20px) scale(.9)}}
.grid-bg{position:absolute;inset:0;background-image:linear-gradient(rgba(251,191,36,.03) 1px,transparent 1px),linear-gradient(90deg,rgba(251,191,36,.03) 1px,transparent 1px);background-size:50px 50px;animation:gridMove 20s linear infinite}
@keyframes gridMove{0%{transform:translate(0,0)}100%{transform:translate(50px,50px)}}
.particles{position:absolute;inset:0;overflow:hidden}.particle{position:absolute;width:4px;height:4px;background:var(--primary);border-radius:50%;opacity:0;animation:particle-float 10s linear infinite}
@keyframes particle-float{0%{opacity:0;transform:translateY(100vh) scale(0)}10%{opacity:1;transform:translateY(90vh) scale(1)}90%{opacity:1;transform:translateY(10vh) scale(1)}100%{opacity:0;transform:translateY(0) scale(0)}
}
.login-container{position:relative;z-index:10;width:100%;max-width:480px;padding:20px;animation:slideUp .8s ease-out}
@keyframes slideUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
.login-card{background:rgba(15,23,42,.9);backdrop-filter:blur(20px);border:1px solid rgba(251,191,36,.2);border-radius:24px;padding:40px;box-shadow:0 20px 60px rgba(0,0,0,.5),0 0 100px rgba(251,191,36,.1),inset 0 0 60px rgba(251,191,36,.05);position:relative;overflow:hidden}
.login-card::before{content:"";position:absolute;top:-2px;left:-2px;right:-2px;bottom:-2px;background:var(--gradient);border-radius:24px;opacity:0;z-index:-1;transition:opacity .3s;animation:glow-pulse 3s ease-in-out infinite}
@keyframes glow-pulse{0%,100%{opacity:.3}50%{opacity:.1}}
.logo-section{text-align:center;margin-bottom:32px}
/* paksa ukuran & benar-benar center */
.logo-section .logo-icon{
  width:80px !important;
  height:80px !important;
  background:var(--gradient) !important;
  border-radius:20px;
  display:grid !important;
  place-items:center !important;
  margin:0 auto 20px !important;
  box-shadow:0 10px 40px rgba(251,191,36,.3);
  position:relative;
  overflow:hidden;
}

/* kunci ukuran ikon Wi-Fi di dalam kotaknya */
.logo-section .logo-icon svg{
  width:36px !important;
  height:36px !important;
  color:var(--dark) !important; /* biar kontras di atas kotak kuning */
  display:block;
  line-height:0;
}


.logo-icon::after{content:"";position:absolute;width:100px;height:100px;background:radial-gradient(circle,rgba(251,191,36,.3) 0%,transparent 70%);border-radius:50%;animation:pulse-glow 3s ease-in-out infinite;z-index:-1}
@keyframes pulse-glow{0%,100%{transform:scale(.8);opacity:.5}50%{transform:scale(1.3);opacity:.8}}
.brand-name{font-size:28px;font-weight:800;background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:8px;letter-spacing:-.5px}
.brand-tagline{color:var(--gray);font-size:14px}
.form-title{font-size:20px;font-weight:700;color:var(--light);margin:24px 0;text-align:center}
.input-group{margin-bottom:20px}
.input-label{display:block;margin-bottom:8px;font-size:13px;font-weight:600;color:var(--gray);text-transform:uppercase;letter-spacing:.5px}
.input-wrapper{position:relative}
.input-icon{position:absolute;left:16px;top:50%;transform:translateY(-50%);color:var(--gray)}
.form-input{width:100%;padding:14px 16px 14px 48px;background:rgba(30,41,59,.5);border:2px solid rgba(100,116,139,.3);border-radius:12px;color:var(--light);font-size:15px;font-weight:500;transition:all .3s}
.form-input:focus{outline:none;background:rgba(30,41,59,.8);border-color:var(--primary);box-shadow:0 0 0 4px rgba(251,191,36,.1),0 0 20px rgba(251,191,36,.2)}
.password-toggle{position:absolute;right:16px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--gray);cursor:pointer;padding:4px}
.submit-btn{width:100%;padding:16px;background:var(--gradient);border:none;border-radius:12px;color:var(--dark);font-size:16px;font-weight:700;cursor:pointer;box-shadow:0 10px 30px rgba(251,191,36,.3);position:relative;overflow:hidden}
.submit-btn::before{content:"";position:absolute;top:50%;left:50%;width:0;height:0;background:rgba(255,255,255,.3);border-radius:50%;transform:translate(-50%,-50%);transition:width .6s,height .6s}
.submit-btn:hover::before{width:400px;height:400px}
.error-message,.success-message{display:none;align-items:center;gap:12px;border-radius:12px;padding:12px 16px;margin-bottom:20px}
.error-message{background:linear-gradient(135deg,rgba(239,68,68,.1),rgba(220,38,38,.05));border:1px solid rgba(239,68,68,.3)}
.success-message{background:linear-gradient(135deg,rgba(16,185,129,.1),rgba(34,197,94,.05));border:1px solid rgba(16,185,129,.3)}
.copyright{text-align:center;margin-top:24px;color:rgba(100,116,139,.6);font-size:12px}
</style>
</head>
<body>
  <div class="bg-wrapper">
    <div class="orb orb1"></div><div class="orb orb2"></div><div class="orb orb3"></div>
    <div class="grid-bg"></div><div class="particles" id="particles"></div>
  </div>

  <div class="login-container">
    <div class="login-card">
      <div class="logo-section">
        <div class="logo-icon">
  <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
    <!-- bar tambahan (paling luar) -->
    <path d="M2.5 9.5a16 16 0 0119 0" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    <!-- bar besar (lama) -->
    <path d="M5 12.55a11.8 11.8 0 0114 0" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    <!-- bar kecil (lama) -->
    <path d="M8.5 16.05a7 7 0 017 0" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    <!-- titik -->
    <path d="M12 20h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
  </svg>
</div>

        <h1 class="brand-name">Adam Wifi</h1>
        <p class="brand-tagline">Network Management System</p>
      </div>

      <div class="form-section">
        <h2 class="form-title">Selamat Datang Kembali</h2>

        <div class="error-message" id="errorMessage">
          <svg class="error-icon" width="20" height="20" viewBox="0 0 24 24" fill="none">
            <path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          <span class="error-text" id="errorText">Username atau password salah</span>
        </div>

        <div class="success-message" id="successMessage">
          <svg class="success-icon" width="20" height="20" viewBox="0 0 24 24" fill="none">
            <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <span class="success-text">Login berhasil! Mengalihkan...</span>
        </div>

        <form id="loginForm" method="POST" action="">
          <div class="input-group">
            <label class="input-label" for="username">Username</label>
            <div class="input-wrapper">
              <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
              <input type="text" id="username" name="username" class="form-input" placeholder="Masukkan username" required autocomplete="username">
            </div>
          </div>

          <div class="input-group">
            <label class="input-label" for="password">Password</label>
            <div class="input-wrapper">
              <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
              <input type="password" id="password" name="password" class="form-input" placeholder="Masukkan password" required autocomplete="current-password">
              <button type="button" class="password-toggle" id="togglePassword">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" id="eyeIcon">
                  <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" stroke="currentColor" stroke-width="2"/>
                  <path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" stroke="currentColor" stroke-width="2"/>
                </svg>
              </button>
            </div>
          </div>

          <!-- Bagian remember/forgot DIHAPUS -->

          <button type="submit" class="submit-btn" id="submitBtn"><span id="btnText">Masuk</span></button>
        </form>
      </div>
    </div>

    <div class="copyright">© 2024 AdamWifi. All rights reserved.</div>
  </div>

  <script>
    // Particles
    (function(){
      const el=document.getElementById('particles');
      for(let i=0;i<20;i++){
        const p=document.createElement('div');
        p.className='particle';
        p.style.left=(Math.random()*100)+'%';
        p.style.animationDelay=(Math.random()*10)+'s';
        p.style.animationDuration=(10+Math.random()*10)+'s';
        el.appendChild(p);
      }
    })();

    // Toggle password
    const togglePassword=document.getElementById('togglePassword'),
          passwordInput=document.getElementById('password'),
          eyeIcon=document.getElementById('eyeIcon');
    togglePassword.addEventListener('click',()=>{
      const t=passwordInput.type==='password'?'text':'password';
      passwordInput.type=t;
      eyeIcon.innerHTML = t==='text'
        ? `<path d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>`
        : `<path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" stroke="currentColor" stroke-width="2"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" stroke="currentColor" stroke-width="2"/>`;
    });

    // Loading state
    const loginForm=document.getElementById('loginForm'),
          submitBtn=document.getElementById('submitBtn'),
          btnText=document.getElementById('btnText');
    loginForm.addEventListener('submit',()=>{
      submitBtn.disabled=true;
      btnText.innerHTML='<span class="btn-loader"></span> Memproses...';
    });

    // Tampilkan error dari ?error=
    (function(){
      const p=new URLSearchParams(location.search), e=p.get('error');
      const box=document.getElementById('errorMessage'),txt=document.getElementById('errorText');
      if(!e){box.style.display='none';return;}
      box.style.display='flex';
      txt.textContent=(e==='empty')?'Username dan password harus diisi'
        :(e==='invalid')?'Username atau password salah'
        :(e==='Akun nonaktif')?'Akun nonaktif. Hubungi admin.'
        :'Terjadi kesalahan sistem';
    })();

    // Greeting & tahun
    (function(){
      const h=new Date().getHours();
      let g='Selamat Malam'; if(h<12)g='Selamat Pagi'; else if(h<15)g='Selamat Siang'; else if(h<18)g='Selamat Sore';
      document.querySelector('.form-title').textContent=g+', Admin';
      document.querySelector('.copyright').textContent = '\u00A9 ' + new Date().getFullYear() + ' Adam Wifi. All rights reserved.';

    })();
  </script>
</body>
</html>
