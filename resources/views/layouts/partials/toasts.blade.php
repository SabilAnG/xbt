{{--
    Notifikasi animasi setelah simpan / ubah / hapus (session "status") dan
    saat gagal (session "error"). Sukses hilang sendiri setelah beberapa detik
    (berhenti saat disorot); gagal tetap tampil sampai ditutup.
--}}
@php
    $toasts = [];
    if (session('status')) {
        $message = (string) session('status');
        $kind = match (true) {
            (bool) preg_match('/\b(dihapus|dibatalkan|ditarik)\b/i', $message) => 'deleted',
            (bool) preg_match('/\b(disimpan|diubah|diperbarui|dipindahkan)\b/i', $message) => 'updated',
            default => 'created',
        };
        $toasts[] = ['type' => 'success', 'kind' => $kind, 'message' => $message, 'title' => ['deleted' => 'Berhasil dihapus', 'updated' => 'Perubahan disimpan', 'created' => 'Berhasil disimpan'][$kind]];
    }
    if (session('error')) {
        $toasts[] = ['type' => 'error', 'kind' => 'error', 'message' => (string) session('error'), 'title' => 'Gagal disimpan'];
    }
@endphp
@if ($toasts)
    <div class="pointer-events-none fixed top-24 right-3 left-3 z-[80] flex flex-col items-end gap-3 sm:top-28 sm:right-8 sm:left-auto" aria-live="polite">
        @foreach ($toasts as $toast)
            @php $success = $toast['type'] === 'success'; @endphp
            <div class="toast pointer-events-auto relative w-full overflow-hidden rounded-[16px] border-[1.5px] border-white bg-white/90 py-3.5 pr-11 pl-3.5 shadow-(--shadow-pop) backdrop-blur-xl sm:w-[360px]"
                 role="{{ $success ? 'status' : 'alert' }}" data-toast data-autoclose="{{ $success ? 4500 : 0 }}">
                <div class="flex items-start gap-3">
                    <span @class(['toast-badge relative grid size-11 shrink-0 place-items-center rounded-[13px] text-white',
                        'bg-linear-to-br from-[#7fdb8f] to-[#1fae5b] shadow-[0_10px_20px_-10px_#1fae5b]' => $toast['kind'] === 'created' || $toast['kind'] === 'updated',
                        'bg-linear-to-br from-[#fbbf94] to-[#e9643a] shadow-[0_10px_20px_-10px_#e9643a]' => $toast['kind'] === 'deleted',
                        'bg-linear-to-br from-[#fdb7c1] to-[#e5465c] shadow-[0_10px_20px_-10px_#e5465c]' => ! $success])>
                        @if ($toast['kind'] === 'deleted')
                            <svg class="size-[22px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path class="toast-draw" pathLength="1" d="M4 7h16m-10 4v6m4-6v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/></svg>
                        @elseif ($success)
                            <svg class="size-[22px]" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path class="toast-draw" pathLength="1" d="m5 12.5 4.5 4.5L19 7.5"/></svg>
                        @else
                            <svg class="size-[22px]" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" viewBox="0 0 24 24"><path class="toast-draw" pathLength="1" d="M12 6.5v7m0 4h.01"/></svg>
                        @endif
                    </span>
                    <div class="min-w-0 pt-0.5">
                        <p class="text-[14px] font-semibold text-ink">{{ $toast['title'] }}</p>
                        <p class="mt-0.5 text-[12.5px] leading-snug text-ink-soft">{{ $toast['message'] }}</p>
                    </div>
                </div>
                <button type="button" class="absolute top-3 right-3 grid size-7 place-items-center rounded-lg text-muted transition hover:bg-hover hover:text-ink" data-toast-close aria-label="Tutup">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
                @if ($success)
                    <span class="absolute inset-x-0 bottom-0 h-[3px] bg-[#eef0f5]"><span class="toast-progress block h-full origin-left bg-linear-to-r {{ $toast['kind'] === 'deleted' ? 'from-[#fbbf94] to-[#e9643a]' : 'from-[#7fdb8f] to-[#1fae5b]' }}"></span></span>
                @endif
            </div>
        @endforeach
    </div>
    <script>
        (() => {
            document.querySelectorAll('[data-toast]').forEach(toast => {
                const close = () => {
                    if (toast.classList.contains('is-leaving')) return;
                    toast.classList.add('is-leaving');
                    toast.addEventListener('animationend', () => toast.remove(), { once: true });
                };
                toast.querySelector('[data-toast-close]').addEventListener('click', close);
                const delay = parseInt(toast.dataset.autoclose, 10);
                if (!delay) return;
                const bar = toast.querySelector('.toast-progress');
                bar.style.animationDuration = delay + 'ms';
                bar.addEventListener('animationend', close);
                toast.addEventListener('mouseenter', () => { bar.style.animationPlayState = 'paused'; });
                toast.addEventListener('mouseleave', () => { bar.style.animationPlayState = 'running'; });
            });
        })();
    </script>
@endif
