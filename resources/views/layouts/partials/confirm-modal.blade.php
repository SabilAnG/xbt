{{--
    Modal konfirmasi bergaya panel, pengganti dialog bawaan browser.
    - Form dengan atribut data-confirm="pesan" otomatis memakai modal ini
      (data-confirm-title, data-confirm-ok, data-confirm-tone="danger" opsional).
    - Script lain: window.appConfirm({ title, message, ok, tone }).then(yes => …)
--}}
<div class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-message" hidden data-confirm-modal>
    <div class="confirm-backdrop" data-confirm-cancel></div>
    <div class="confirm-card">
        <span class="confirm-icon" data-confirm-icon></span>
        <h2 id="confirm-title" class="mt-4 text-[17px] font-semibold text-ink" data-confirm-title></h2>
        <p id="confirm-message" class="mt-1.5 text-[13.5px] leading-relaxed text-ink-soft" data-confirm-message></p>
        <div class="mt-6 grid grid-cols-2 gap-2.5">
            <button type="button" class="btn h-11" data-confirm-cancel>Batal</button>
            <button type="button" class="btn h-11" data-confirm-ok></button>
        </div>
    </div>
</div>
<script>
    (() => {
        const modal = document.querySelector('[data-confirm-modal]');
        const okButton = modal.querySelector('[data-confirm-ok]');
        const icons = {
            danger: '<svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4 7h16m-10 4v6m4-6v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/></svg>',
            default: '<svg fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 8v5m0 3.5h.01M10.3 3.9 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>',
        };
        let resolver = null;
        let lastFocus = null;

        function close(result) {
            if (!resolver) return;
            modal.classList.remove('is-open');
            setTimeout(() => { modal.hidden = true; }, 180);
            const done = resolver;
            resolver = null;
            lastFocus?.focus?.();
            done(result);
        }

        window.appConfirm = ({ title, message, ok, tone } = {}) => new Promise(resolve => {
            const danger = tone === 'danger';
            modal.querySelector('[data-confirm-title]').textContent = title || (danger ? 'Hapus data ini?' : 'Lanjutkan?');
            modal.querySelector('[data-confirm-message]').textContent = message || '';
            modal.querySelector('[data-confirm-icon]').innerHTML = danger ? icons.danger : icons.default;
            modal.querySelector('[data-confirm-icon]').dataset.tone = danger ? 'danger' : 'default';
            okButton.textContent = ok || (danger ? 'Ya, hapus' : 'Ya, lanjutkan');
            okButton.className = 'btn h-11 ' + (danger ? 'confirm-danger' : 'btn-primary');
            lastFocus = document.activeElement;
            resolver = resolve;
            modal.hidden = false;
            requestAnimationFrame(() => modal.classList.add('is-open'));
            setTimeout(() => okButton.focus(), 30);
        });

        okButton.addEventListener('click', () => close(true));
        modal.querySelectorAll('[data-confirm-cancel]').forEach(el => el.addEventListener('click', () => close(false)));
        document.addEventListener('keydown', e => {
            if (!resolver) return;
            if (e.key === 'Escape') { e.preventDefault(); close(false); }
            if (e.key === 'Tab') {
                const focusable = [...modal.querySelectorAll('button')];
                const i = focusable.indexOf(document.activeElement);
                e.preventDefault();
                focusable[(i + (e.shiftKey ? -1 : 1) + focusable.length) % focusable.length].focus();
            }
        });

        // Form yang perlu konfirmasi: tahan dulu, kirim setelah dijawab "ya".
        document.addEventListener('submit', e => {
            const form = e.target.closest('form[data-confirm]');
            if (!form || form.dataset.confirmed === '1') return;
            e.preventDefault();
            const submitter = e.submitter;
            window.appConfirm({
                title: form.dataset.confirmTitle,
                message: form.dataset.confirm,
                ok: form.dataset.confirmOk,
                tone: form.dataset.confirmTone,
            }).then(yes => {
                if (!yes) return;
                form.dataset.confirmed = '1';
                submitter ? form.requestSubmit(submitter) : form.requestSubmit();
                setTimeout(() => { delete form.dataset.confirmed; }, 1000);
            });
        }, true);
    })();
</script>
