<!DOCTYPE html>
<html lang="id" data-theme="dark" data-intro="off">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifikasi · IHC Racing</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=oxanium:500,600,700|manrope:400,500,600,700|racing-sans-one:400&display=swap">
    @vite('resources/css/admin.css')
</head>
<body class="site-login">
@php $useRecovery = $errors->has('recovery_code'); @endphp
<div class="sl-page">
    <div class="sl-hero" style="background-image: url('{{ asset('assets/images/header1.png') }}')"></div>

    <header class="sl-top">
        <a href="{{ route('home') }}" class="sl-brand" aria-label="IHC Racing">
            <span class="racing-stripes" aria-hidden="true"><i></i><i></i><i></i></span>IHC <span>Racing</span>
        </a>
        <a href="{{ route('login') }}" class="sl-back">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 18 9 12l6-6"/></svg>
            Kembali ke login
        </a>
    </header>

    <main class="sl-main">
        <section class="sl-copy">
            <div class="sl-subtitle">Verifikasi 2 langkah</div>
            <h1>Satu langkah <span>lagi</span></h1>
            <p>Password sudah benar. Buka aplikasi authenticator di HP (Google Authenticator, Microsoft Authenticator, Authy) lalu masukkan kode 6 digit untuk IHC Racing.</p>
        </section>

        <section class="sl-card">
            <h2>Masukkan kode</h2>
            <p class="sl-lead" data-mode-lead>{{ $useRecovery ? 'Pakai salah satu kode cadangan yang disimpan saat mengaktifkan.' : 'Kode berganti setiap 30 detik.' }}</p>

            <form method="POST" action="{{ route('login.two-factor.verify') }}">
                @csrf
                <div data-mode="code" @if ($useRecovery) hidden @endif>
                    <label for="code">Kode authenticator</label>
                    <div class="sl-field">
                        <svg fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M7 3h10v18H7z"/><path d="M11 18h2"/></svg>
                        <input id="code" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code" placeholder="123456" @unless ($useRecovery) autofocus @endunless>
                    </div>
                    @error('code')<p class="sl-error">{{ $message }}</p>@enderror
                </div>

                <div data-mode="recovery" @unless ($useRecovery) hidden @endunless>
                    <label for="recovery_code">Kode cadangan</label>
                    <div class="sl-field">
                        <svg fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M15 7a4 4 0 1 1-3.9 5H7v3H4v-3H3"/></svg>
                        <input id="recovery_code" name="recovery_code" autocomplete="off" placeholder="XXXXX-XXXXX" @if ($useRecovery) autofocus @endif>
                    </div>
                    @error('recovery_code')<p class="sl-error">{{ $message }}</p>@enderror
                </div>

                <button class="sl-btn"><span>Verifikasi &amp; masuk</span>
                    <svg fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </button>
                <button type="button" class="sl-check" style="margin: 14px auto 0; background: none; border: 0; cursor: pointer; text-decoration: underline;" data-toggle-mode>
                    {{ $useRecovery ? 'Pakai kode authenticator' : 'HP hilang? Pakai kode cadangan' }}
                </button>
            </form>
        </section>
    </main>

    <footer class="sl-foot">© {{ now()->year }} IHC Racing · Admin panel</footer>
</div>

<script>
    (() => {
        const code = document.querySelector('[data-mode="code"]');
        const recovery = document.querySelector('[data-mode="recovery"]');
        const toggle = document.querySelector('[data-toggle-mode]');
        const lead = document.querySelector('[data-mode-lead]');

        toggle.addEventListener('click', () => {
            const toRecovery = recovery.hidden;
            recovery.hidden = !toRecovery;
            code.hidden = toRecovery;
            // Kosongkan isian yang disembunyikan supaya server tahu mode mana yang dipakai.
            (toRecovery ? code : recovery).querySelector('input').value = '';
            toggle.textContent = toRecovery ? 'Pakai kode authenticator' : 'HP hilang? Pakai kode cadangan';
            lead.textContent = toRecovery ? 'Pakai salah satu kode cadangan yang disimpan saat mengaktifkan.' : 'Kode berganti setiap 30 detik.';
            (toRecovery ? recovery : code).querySelector('input').focus();
        });

        // Isi 6 digit → langsung kirim.
        document.getElementById('code').addEventListener('input', e => {
            if (e.target.value.replace(/\D/g, '').length === 6) e.target.form.requestSubmit();
        });
    })();
</script>
</body>
</html>
