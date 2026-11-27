@extends('layouts.admin', ['title' => 'Keamanan akun'])

@section('content')
    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
        {{-- ------------------------------------------------ Verifikasi 2 langkah --}}
        <section class="card space-y-4 p-5" id="dua-langkah">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="card-title">Verifikasi 2 langkah</h2>
                    <p class="text-[12.5px] text-muted">Setelah password, login meminta kode 6 digit dari aplikasi authenticator di HP. Password yang bocor saja tidak cukup untuk masuk.</p>
                </div>
                @if ($user->hasTwoFactor())
                    <span class="shrink-0 rounded-full bg-emerald-50 px-2.5 py-1 text-[11.5px] font-semibold text-emerald-700">Aktif</span>
                @else
                    <span class="shrink-0 rounded-full bg-amber-50 px-2.5 py-1 text-[11.5px] font-semibold text-amber-700">Belum aktif</span>
                @endif
            </div>

            @if ($recoveryCodes)
                <div class="rounded-[12px] border border-amber-200 bg-amber-50 p-4">
                    <p class="text-[13px] font-semibold text-amber-800">Kode cadangan — simpan sekarang, hanya ditampilkan sekali</p>
                    <p class="mt-1 text-[12px] text-amber-700">Dipakai bila HP hilang. Tiap kode hanya bisa dipakai sekali. Catat di kertas atau simpan di pengelola password.</p>
                    <div class="mt-3 grid grid-cols-2 gap-2 font-mono text-[13px] sm:grid-cols-4" data-codes>
                        @foreach ($recoveryCodes as $code)
                            <span class="rounded-[8px] bg-white px-2 py-1.5 text-center text-ink">{{ $code }}</span>
                        @endforeach
                    </div>
                    <button type="button" class="btn btn-sm mt-3" data-copy-codes>Salin semua kode</button>
                </div>
            @endif

            @if ($user->hasTwoFactor())
                <p class="text-[13px] text-ink-soft">Aktif sejak {{ $user->two_factor_confirmed_at->translatedFormat('d M Y H:i') }} · sisa {{ count($user->two_factor_recovery_codes ?? []) }} kode cadangan.</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <form method="POST" action="{{ route('admin.account.recovery-codes') }}" class="space-y-2 rounded-[12px] border border-line p-3.5">
                        @csrf
                        <p class="text-[12.5px] font-semibold text-ink">Buat kode cadangan baru</p>
                        <x-admin.field name="current_password" label="Password saat ini" type="password" required autocomplete="current-password" />
                        <button class="btn btn-sm">Buat ulang kode</button>
                    </form>
                    <form method="POST" action="{{ route('admin.account.two-factor.disable') }}" class="space-y-2 rounded-[12px] border border-rose-200 p-3.5"
                          data-confirm="Matikan verifikasi 2 langkah? Akun hanya dilindungi password." data-confirm-tone="danger">
                        @csrf
                        @method('DELETE')
                        <p class="text-[12.5px] font-semibold text-rose-700">Matikan verifikasi 2 langkah</p>
                        <x-admin.field name="current_password" label="Password saat ini" type="password" required autocomplete="current-password" />
                        <button class="btn btn-sm text-rose-600">Matikan</button>
                    </form>
                </div>
            @elseif ($setupSecret)
                <ol class="space-y-4 text-[13px] text-ink-soft">
                    <li>
                        <strong class="text-ink">1. Pasang aplikasi authenticator</strong> di HP: Google Authenticator, Microsoft Authenticator, atau Authy.
                    </li>
                    <li>
                        <strong class="text-ink">2. Tambahkan akun</strong> → pilih <em>Masukkan kunci penyiapan</em>, lalu ketik kunci ini (jenis: berbasis waktu):
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <code class="rounded-[10px] bg-chip px-3 py-2 font-mono text-[14px] tracking-[0.12em] text-ink" data-secret>{{ trim(chunk_split($setupSecret, 4, ' ')) }}</code>
                            <button type="button" class="btn btn-sm" data-copy-secret="{{ $setupSecret }}">Salin</button>
                            <a href="{{ $setupUri }}" class="btn btn-sm">Buka di aplikasi (dari HP)</a>
                        </div>
                        <p class="mt-1 text-[12px] text-muted">Nama akun: {{ $user->email }} · {{ \App\Http\Controllers\Admin\AccountSecurityController::ISSUER }}</p>
                    </li>
                    <li>
                        <strong class="text-ink">3. Masukkan kode 6 digit</strong> yang muncul di aplikasi:
                        <form method="POST" action="{{ route('admin.account.two-factor.confirm') }}" class="mt-2 flex flex-wrap items-start gap-2">
                            @csrf
                            <div class="w-40">
                                <input name="code" inputmode="numeric" maxlength="7" autocomplete="one-time-code" placeholder="123456" class="input font-mono tracking-[0.2em]" required autofocus>
                                @error('code')<p class="error">{{ $message }}</p>@enderror
                            </div>
                            <button class="btn btn-primary">Aktifkan</button>
                        </form>
                    </li>
                </ol>
                <form method="POST" action="{{ route('admin.account.two-factor.cancel') }}">
                    @csrf
                    <button class="text-[12.5px] text-muted hover:underline">Batal</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.account.two-factor.start') }}">
                    @csrf
                    <button class="btn btn-primary">Aktifkan verifikasi 2 langkah</button>
                </form>
            @endif
        </section>

        {{-- ------------------------------------------------ Password --}}
        <form method="POST" action="{{ route('admin.account.password') }}" class="card space-y-4 self-start p-5">
            @csrf
            @method('PUT')
            <div>
                <h2 class="card-title">Ganti password</h2>
                <p class="text-[12.5px] text-muted">Minimal 8 karakter, campuran huruf dan angka. Sesi di perangkat lain otomatis keluar.</p>
            </div>
            <x-admin.field name="current_password" label="Password saat ini" type="password" required autocomplete="current-password" />
            <x-admin.field name="password" label="Password baru" type="password" required autocomplete="new-password" />
            <x-admin.field name="password_confirmation" label="Ulangi password baru" type="password" required autocomplete="new-password" />
            <button class="btn btn-primary w-full">Simpan password</button>
        </form>
    </div>
@endsection

@push('scripts')
<script>
    (() => {
        const copy = (text, button) => navigator.clipboard?.writeText(text).then(() => {
            const label = button.textContent;
            button.textContent = 'Tersalin';
            setTimeout(() => button.textContent = label, 1500);
        });
        document.querySelector('[data-copy-secret]')?.addEventListener('click', e => copy(e.currentTarget.dataset.copySecret, e.currentTarget));
        document.querySelector('[data-copy-codes]')?.addEventListener('click', e => {
            copy([...document.querySelectorAll('[data-codes] span')].map(s => s.textContent.trim()).join('\n'), e.currentTarget);
        });
    })();
</script>
@endpush
