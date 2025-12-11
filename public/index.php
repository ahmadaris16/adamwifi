<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Adam WiFi - Internet Cepat, Monggo Merapat!</title>
    <meta name="description" content="Layanan internet cepat dan stabil tanpa FUP di Desa Pagerwesi. Harga terjangkau, teknisi lokal, dan layanan satset. Daftar sekarang!">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

        :root {
            --primary: #fbbf24;
            --primary-dark: #f59e0b;
            --dark: #0f172a;
            --dark-light: #1e293b;
            --gray: #94a3b8;
            --light: #f1f5f9;
            --white: #ffffff;
            --gradient: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            --shadow-glow: 0 0 40px rgba(251, 191, 36, 0.3);
            scroll-behavior: smooth;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background-color: var(--dark);
            color: var(--light);
            line-height: 1.7;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: 
                radial-gradient(circle at 20% 20%, rgba(251, 191, 36, 0.25) 0%, transparent 40%),
                radial-gradient(circle at 80% 70%, rgba(245, 158, 11, 0.2) 0%, transparent 40%);
            z-index: 0;
            filter: blur(100px);
            transform: scale(1.2);
            animation: bgMove 25s ease-in-out infinite;
        }

        @keyframes bgMove {
            0%   { transform: translate(0, 0) rotate(0deg) scale(1.2); }
            50%  { transform: translate(30px, -40px) rotate(180deg) scale(1.3); }
            100% { transform: translate(0, 0) rotate(360deg) scale(1.2); }
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
            position: relative;
            z-index: 2;
        }

        .header {
            position: sticky;
            top: 0;
            z-index: 1000;
            padding: 20px 0;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(251, 191, 36, 0.2);
            animation: slideDown 0.7s ease-out;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 24px;
            font-weight: 800;
            color: var(--primary);
        }

        .logo::before {
            content: '⚡';
            margin-right: 8px;
            display: inline-block;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
          0%, 100% { transform: scale(1); }
          50% { transform: scale(1.1); }
        }

        .nav-links a {
            color: var(--light);
            text-decoration: none;
            margin-left: 24px;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .nav-links a:hover {
            color: var(--primary);
        }
        
        .btn {
            padding: 12px 28px;
            background: var(--gradient);
            color: var(--dark);
            text-decoration: none;
            border-radius: 12px;
            font-weight: 700;
            transition: all 0.3s ease;
            display: inline-block;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(251, 191, 36, 0.25);
        }

        .btn:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: var(--shadow-glow);
        }
        
        section {
            padding: 100px 0;
        }

        .section-title {
            font-size: 36px;
            font-weight: 800;
            text-align: center;
            margin-bottom: 16px;
        }
        
        .section-subtitle {
            text-align: center;
            color: var(--gray);
            max-width: 600px;
            margin: 0 auto 60px auto;
            font-size: 18px;
        }
        
        #hero {
            padding: 120px 0 100px 0;
            text-align: center;
        }

        #hero h1 {
            font-size: 60px;
            font-weight: 800;
            line-height: 1.2;
            background: linear-gradient(135deg, var(--white), #fde047);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 24px;
        }

        #hero p {
            font-size: 20px;
            color: var(--gray);
            margin-bottom: 40px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .card {
            background: rgba(30, 41, 59, 0.7);
            border: 1px solid rgba(251, 191, 36, 0.1);
            border-radius: 20px;
            padding: 32px;
            transition: all 0.3s ease-in-out; /* Animasi lebih halus */
            backdrop-filter: blur(10px);
        }

        /* === EFEK HOVER MEMBESAR (REVISI) === */
        .card:hover {
            transform: translateY(-8px) scale(1.05); /* Sedikit terangkat DAN membesar */
            border-color: rgba(251, 191, 36, 0.3);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .keunggulan-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
        }

        .card-icon {
            font-size: 40px;
            margin-bottom: 20px;
        }
        
        .card h3 {
            font-size: 22px;
            font-weight: 700;
            color: var(--light);
            margin-bottom: 12px;
        }
        
        .card p {
            color: var(--gray);
        }

        #paket {
            padding-bottom: 120px;
        }
        
        .paket-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            align-items: center;
        }

        .paket-card {
            text-align: center;
        }
        
        .paket-card .paket-nama {
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--primary);
            margin-bottom: 16px;
        }
        
        .paket-card .paket-harga {
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 8px;
        }
        
        .paket-card .paket-harga span {
            font-size: 16px;
            font-weight: 500;
            color: var(--gray);
        }
        
        .paket-card .paket-kecepatan {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 24px;
        }

        .paket-card ul {
            list-style: none;
            margin-bottom: 32px;
            text-align: left;
            padding-left: 20px;
        }
        
        .paket-card li {
            margin-bottom: 12px;
            position: relative;
        }
        
        .paket-card li::before {
            content: '✓';
            color: var(--primary);
            position: absolute;
            left: -20px;
        }

        #area {
            background: rgba(30, 41, 59, 0.7);
            border-radius: 20px;
            text-align: center;
            padding: 80px 40px;
            border: 1px solid rgba(251, 191, 36, 0.1);
            backdrop-filter: blur(10px);
        }
        
        .testimoni-card p {
            font-size: 18px;
            font-style: italic;
            margin-bottom: 24px;
            position: relative;
            padding-left: 20px;
        }
        
        .testimoni-card p::before {
            content: '“';
            font-size: 40px;
            color: var(--primary);
            position: absolute;
            left: -10px;
            top: -10px;
        }
        
        .testimoni-author {
            font-weight: 700;
            color: var(--light);
        }
        
        .testimoni-author span {
            display: block;
            font-weight: 400;
            color: var(--gray);
            font-size: 14px;
        }

        .footer {
            border-top: 1px solid rgba(251, 191, 36, 0.2);
            padding: 40px 0;
            text-align: center;
            color: var(--gray);
        }
        
        .reveal {
            opacity: 0;
            transform: translateY(40px);
            transition: opacity 0.8s ease-out, transform 0.8s ease-out;
        }
        
        .reveal.is-visible {
            opacity: 1;
            transform: translateY(0);
        }

        @keyframes slideDown {
            from { transform: translateY(-100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @media (max-width: 768px) {
            .nav-links { display: none; }
            #hero h1 { font-size: 40px; }
            #hero p { font-size: 16px; }
            .section-title { font-size: 28px; }
            .section-subtitle { font-size: 16px; }
            section { padding: 60px 0; }
            .paket-card.highlight { transform: scale(1.05) translateY(0) !important; }
        }
    </style>
</head>
<body>

    <header class="header">
        <div class="container navbar">
            <div class="logo">Adam WiFi</div>
            <nav class="nav-links">
                <a href="#keunggulan">Keunggulan</a>
                <a href="#paket">Paket</a>
                <a href="#area">Area</a>
                <a href="#kontak">Kontak</a>
            </nav>
            <a href="https://api.whatsapp.com/send?phone=6285726999047&text=Halo%20Adam%20WiFi,%20saya%20tertarik%20untuk%20mendaftar%20layanan%20internet.%20Mohon%20infonya." class="btn" target="_blank">Daftar Sekarang</a>
        </div>
    </header>

    <main>
        <section id="hero" class="container">
            <div class="reveal">
                <h1>Internet Cepat, Monggo Merapat!</h1>
                <p>Nikmati koneksi internet super cepat dan stabil tanpa batas kuota (FUP) untuk semua kebutuhan digital Anda. Streaming, gaming, dan kerja dari rumah lancar jaya bersama Adam WiFi.</p>
                <a href="#paket" class="btn">Lihat Pilihan Paket</a>
            </div>
        </section>

        <section id="keunggulan" class="container">
            <h2 class="section-title reveal">Kenapa Pilih Adam WiFi?</h2>
            <p class="section-subtitle reveal">Kami bukan sekadar penyedia internet, kami adalah tetangga Anda yang siap memberikan layanan terbaik.</p>
            <div class="keunggulan-grid">
                <div class="card reveal" style="transition-delay: 0.1s;">
                    <div class="card-icon">💰</div>
                    <h3>Harga Terjangkau</h3>
                    <p>Internet ngebut tanpa bikin kantong bolong. Biaya bulanan yang ramah di saku.</p>
                </div>
                <div class="card reveal" style="transition-delay: 0.2s;">
                    <div class="card-icon">♾️</div>
                    <h3>Tanpa FUP (Unlimited)</h3>
                    <p>Bebas streaming, download, dan main game sepuasnya tanpa khawatir kecepatan turun.</p>
                </div>
                <div class="card reveal" style="transition-delay: 0.3s;">
                    <div class="card-icon">🛠️</div>
                    <h3>Maintenance Satset</h3>
                    <p>Ada kendala? Tim kami langsung gerak cepat. Gak pakai lama, gak pakai ribet.</p>
                </div>
                <div class="card reveal" style="transition-delay: 0.4s;">
                    <div class="card-icon">🤝</div>
                    <h3>Teknisi Tonggo Dewe</h3>
                    <p>Dilayani oleh teknisi lokal yang Anda kenal, ramah, dan siap membantu kapan saja.</p>
                </div>
            </div>
        </section>

        <section id="paket" class="container">
            <h2 class="section-title reveal">Pilihan Paket Sesuai Kebutuhan</h2>
            <p class="section-subtitle reveal">Transparan, tanpa biaya tersembunyi. Anda hanya bayar sesuai paket yang dipilih.</p>
            <div class="paket-grid">
                <div class="card paket-card reveal" style="transition-delay: 0.1s;">
                    <div class="paket-nama">Paket SMALL</div>
                    <div class="paket-harga">Rp 150.000<span>/bulan</span></div>
                    <div class="paket-kecepatan">Up to 10 Mbps</div>
                    <ul>
                        <li>Gratis Biaya Pemasangan</li>
                        <li>Perangkat Dipinjamkan</li>
                        <li>Unlimited Tanpa FUP</li>
                        <li>Dukungan 24 Jam</li>
                    </ul>
                    <a href="https://api.whatsapp.com/send?phone=6285726999047&text=Halo,%20saya%20tertarik%20dengan%20Paket%20SMALL." class="btn" target="_blank">Pilih Paket Ini</a>
                </div>
                <div class="card paket-card highlight reveal" style="transition-delay: 0.2s;">
                    <div class="paket-nama">Paket MEDIUM</div>
                    <div class="paket-harga">Rp 230.000<span>/bulan</span></div>
                    <div class="paket-kecepatan">Up to 20 Mbps</div>
                    <ul>
                        <li>Gratis Biaya Pemasangan</li>
                        <li>Perangkat Dipinjamkan</li>
                        <li>Unlimited Tanpa FUP</li>
                        <li>Dukungan 24 Jam</li>
                    </ul>
                    <a href="https://api.whatsapp.com/send?phone=6285726999047&text=Halo,%20saya%20tertarik%20dengan%20Paket%20MEDIUM." class="btn" target="_blank">Pilih Paket Ini</a>
                </div>
                <div class="card paket-card card-custom reveal" style="transition-delay: 0.3s;">
                    <div class="card-icon">📞</div>
                    <h3>Butuh Paket Lain?</h3>
                    <p>Punya kebutuhan kecepatan khusus untuk bisnis atau keperluan lainnya? Hubungi kami untuk penawaran spesial.</p>
                    <br>
                    <a href="https://api.whatsapp.com/send?phone=6285726999047&text=Halo,%20saya%20mau%20bertanya%20tentang%20paket%20custom." class="btn" target="_blank">Hubungi Kami</a>
                </div>
            </div>
        </section>

        <section id="area" class="container reveal">
            <h2 class="section-title">Jangkauan Kami</h2>
            <p class="section-subtitle" style="margin-bottom:0;">Saat ini layanan Adam WiFi dengan bangga melayani seluruh area <strong>Desa Pagerwesi</strong>. Kami berkomitmen untuk terus memperluas jangkauan kami.</p>
        </section>

        <section id="testimoni" class="container">
            <h2 class="section-title reveal">Kata Tonggo</h2>
            <p class="section-subtitle reveal">Jangan cuma kata kami, ini kata mereka yang sudah merasakan kencangnya Adam WiFi.</p>
            <div class="keunggulan-grid">
                <div class="card testimoni-card reveal" style="transition-delay: 0.1s;">
                    <p>Mantap, Mas! Buat main Mobile Legends lancar jaya, ping ijo terus. Gak nyesel pasang.</p>
                    <div class="testimoni-author">Ahmad S. <span>- Warga Pagerwesi</span></div>
                </div>
                <div class="card testimoni-card reveal" style="transition-delay: 0.2s;">
                    <p>Alhamdulillah sangat membantu buat anak sekolah daring dan saya jualan online. Kalau ada masalah, teknisinya cepat datang.</p>
                    <div class="testimoni-author">Ibu Siti Z. <span>- Pemilik Toko Kelontong</span></div>
                </div>
            </div>
        </section>

        <section id="kontak" class="container reveal">
            <h2 class="section-title">Siap Punya Internet Cepat?</h2>
            <p class="section-subtitle">Tunggu apa lagi? Hubungi kami sekarang dan tim kami akan segera menjadwalkan pemasangan di rumah Anda. Proses cepat, tinggal pakai!</p>
            <a href="https://api.whatsapp.com/send?phone=6285726999047&text=Halo%20Adam%20WiFi,%20saya%20siap%20berlangganan!" class="btn" target="_blank" style="padding: 20px 40px; font-size: 18px;">Daftar Sekarang via WhatsApp</a>
            <p style="margin-top:20px; color: var(--gray); font-size: 14px;">Dukungan Pelanggan 24 Jam: 0857-2699-9047</p>
        </section>
    </main>

    <footer class="footer">
        <div class="container">
            <p>© <?php echo date("Y"); ?> Adam WiFi. Semua Hak Cipta Dilindungi.</p>
        </div>
    </footer>

    <script>
        // Perintah untuk memastikan halaman berada di posisi paling atas saat dimuat
        if ('scrollRestoration' in history) {
            history.scrollRestoration = 'manual';
        }
        window.scrollTo(0, 0);

        // Animasi elemen saat scroll
        const revealElements = document.querySelectorAll(".reveal");

        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add("is-visible");
                }
            });
        }, {
            threshold: 0.1
        });

        revealElements.forEach(element => {
            revealObserver.observe(element);
        });
    </script>
</body>
</html>