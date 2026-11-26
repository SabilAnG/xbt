<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · IHC Racing</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=oxanium:500,600,700|manrope:400,500,600,700|racing-sans-one:400&display=swap">
    <script>
        // Splash roda hanya sekali per sesi (tidak saat gerak dikurangi atau setelah gagal masuk).
        (() => {
            let seen = false;
            try { seen = sessionStorage.getItem('ihc.intro') === '1'; } catch (e) {}
            const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
            const failed = {{ $errors->any() ? 'true' : 'false' }};
            document.documentElement.dataset.intro = seen || reduce || failed ? 'off' : 'on';
        })();
    </script>
    @vite('resources/css/admin.css')
</head>
<body class="site-login">

{{-- ---------------------------------------------------------- Splash: roda berapi, gaya website --}}
<div class="intro" data-intro aria-hidden="true">
    <div class="intro-glow"></div>
    <div class="intro-stage">
        <p class="intro-kicker">Professional Exhaust Full System</p>
        <div class="intro-rider">
            <div class="intro-fire">
                <span class="flame f1"></span><span class="flame f2"></span><span class="flame f3"></span>
                <span class="flame f4"></span><span class="flame f5"></span>
                <span class="spark s1"></span><span class="spark s2"></span><span class="spark s3"></span><span class="spark s4"></span><span class="spark s5"></span><span class="spark s6"></span>
            </div>
            <svg class="intro-wheel" viewBox="-64 -64 128 128">
                <defs>
                    <linearGradient id="rim-metal" x1="0" y1="-1" x2="0" y2="1">
                        <stop offset="0" stop-color="#ff8a3d"/><stop offset=".5" stop-color="#e65200"/><stop offset="1" stop-color="#9c3300"/>
                    </linearGradient>
                    <radialGradient id="disc-metal">
                        <stop offset=".55" stop-color="#d9dbe4"/><stop offset="1" stop-color="#7d8296"/>
                    </radialGradient>
                </defs>
                <g class="intro-spin">
                    <circle r="56" fill="#111"/>
                    <circle r="56" fill="none" stroke="#262626" stroke-width="7" stroke-dasharray="6 5"/>
                    <circle r="46" fill="#191919"/>
                    <circle r="43" fill="none" stroke="url(#rim-metal)" stroke-width="4"/>
                    <circle r="26" fill="url(#disc-metal)"/>
                    @foreach (range(0, 7) as $hole)
                        <circle cx="{{ round(18 * cos(deg2rad($hole * 45)), 2) }}" cy="{{ round(18 * sin(deg2rad($hole * 45)), 2) }}" r="2.6" fill="#4b5063"/>
                    @endforeach
                    @foreach (range(0, 4) as $spoke)
                        <path d="M0 -9 C 7 -22, 4 -32, 0 -42" fill="none" stroke="url(#rim-metal)" stroke-width="6" stroke-linecap="round" transform="rotate({{ $spoke * 72 }})"/>
                    @endforeach
                    <circle r="10" fill="#e65200"/>
                    <circle r="4.5" fill="#111"/>
                </g>
            </svg>
            <span class="intro-speed l1"></span><span class="intro-speed l2"></span><span class="intro-speed l3"></span>
        </div>
        <div class="intro-road"></div>
        <p class="intro-brand">IHC <span>Racing</span></p>
        <div class="intro-progress"><span></span></div>
    </div>
    <button type="button" class="intro-skip">Lewati</button>
</div>

{{-- ---------------------------------------------------------- Halaman login --}}
<div class="sl-page">
    <div class="sl-hero" style="background-image: url('{{ asset('assets/images/header1.png') }}')"></div>

    <header class="sl-top">
        <a href="{{ route('home') }}" class="sl-brand" aria-label="IHC Racing">
            <span class="racing-stripes" aria-hidden="true"><i></i><i></i><i></i></span>IHC <span>Racing</span>
        </a>
        <a href="{{ route('home') }}" class="sl-back">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 18 9 12l6-6"/></svg>
            Kembali ke website
        </a>
    </header>

    <main class="sl-main">
        <section class="sl-copy">
            <div class="sl-subtitle">Admin panel</div>
            <h1>Produksi &amp; <span>penjualan</span> dalam satu panel</h1>
            <p>Formula, pembelian bahan, gudang, produksi, gaji tukang, sampai laporan laba — semua angka tersambung otomatis.</p>
            <ul>
                @foreach (['Stok gudang & opname', 'HPP formula otomatis', 'Penjualan & piutang'] as $point)
                    <li><svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 5 5L20 7"/></svg>{{ $point }}</li>
                @endforeach
            </ul>
        </section>

        <section class="sl-card">
            <h2>Masuk panel</h2>
            <p class="sl-lead">Gunakan akun panel untuk melanjutkan.</p>

            <form method="POST" action="{{ route('login.attempt') }}">
                @csrf
                <label for="email">Email</label>
                <div class="sl-field">
                    <svg fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/></svg>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="nama@domain.com">
                </div>
                @error('email')<p class="sl-error">{{ $message }}</p>@enderror

                <label for="password">Password</label>
                <div class="sl-field">
                    <svg fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M7 11V7a5 5 0 0 1 10 0v4M5 11h14v10H5z"/></svg>
                    <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="••••••••" data-password>
                    <button type="button" class="sl-reveal" aria-label="Tampilkan password" data-reveal>
                        <svg fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                @error('password')<p class="sl-error">{{ $message }}</p>@enderror

                <label class="sl-check"><input type="checkbox" name="remember" value="1"> Ingat saya di perangkat ini</label>
                <button class="sl-btn"><span>Masuk</span>
                    <svg fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </button>
            </form>
        </section>
    </main>

    <footer class="sl-foot">© {{ now()->year }} IHC Racing · Admin panel</footer>
</div>

<script>
    (() => {
        const root = document.documentElement;
        const intro = document.querySelector('[data-intro]');

        // Splash selesai → memudar, halaman login masuk. Klik / Enter / Esc untuk melewati.
        function finish() {
            if (root.dataset.intro !== 'on') return;
            root.dataset.intro = 'leaving';
            try { sessionStorage.setItem('ihc.intro', '1'); } catch (e) {}
            setTimeout(() => { root.dataset.intro = 'off'; document.getElementById('email')?.focus(); }, 650);
        }
        if (root.dataset.intro === 'on') {
            setTimeout(finish, 3300);
            intro.addEventListener('click', finish);
            document.addEventListener('keydown', e => { if (e.key === 'Escape' || e.key === 'Enter') finish(); });
        }

        document.querySelector('[data-reveal]').addEventListener('click', e => {
            const input = document.querySelector('[data-password]');
            input.type = input.type === 'password' ? 'text' : 'password';
            e.currentTarget.setAttribute('aria-label', input.type === 'password' ? 'Tampilkan password' : 'Sembunyikan password');
        });
    })();
</script>
</body>
</html>
