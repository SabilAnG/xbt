{{--
    Pemilih tanggal bergaya aplikasi untuk semua <input type="date|month|datetime-local" class="input">.

    Input asli tetap ada (disembunyikan secara visual) dan tetap jadi sumber
    nilai: validasi wajib isi, min/max, old(), dan onchange halaman bekerja
    seperti biasa. Tombol di sebelahnya hanya tampilan ("27 Nov 2026");
    memilih tanggal menulis ke input asli lalu memicu "input" + "change".
    Panelnya memakai gaya dropdown (di HP menjadi bottom sheet).
--}}
<script>
    (() => {
        const SELECTOR = 'input.input[type="date"], input.input[type="month"], input.input[type="datetime-local"]';
        const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        const SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        const DAYS = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
        const icon = '<svg class="cdate-icon" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 3v3m8-3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/></svg>';
        const arrow = d => `<svg fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="${d}"/></svg>`;

        const pad = n => String(n).padStart(2, '0');
        const ymd = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
        const today = () => { const d = new Date(); d.setHours(0, 0, 0, 0); return d; };
        const parse = (value, kind) => {
            if (!value) return null;
            const m = value.match(/^(\d{4})-(\d{2})(?:-(\d{2}))?(?:T(\d{2}):(\d{2}))?/);
            if (!m) return null;
            const d = new Date(+m[1], +m[2] - 1, kind === 'month' ? 1 : +(m[3] || 1), +(m[4] || 0), +(m[5] || 0));
            return isNaN(d) ? null : d;
        };
        const kindOf = input => input.type === 'month' ? 'month' : (input.type === 'datetime-local' ? 'datetime' : 'date');
        const labelOf = input => {
            const kind = kindOf(input);
            const d = parse(input.value, kind);
            if (!d) return '';
            if (kind === 'month') return `${MONTHS[d.getMonth()]} ${d.getFullYear()}`;
            const day = `${d.getDate()} ${SHORT[d.getMonth()]} ${d.getFullYear()}`;
            return kind === 'datetime' ? `${day}, ${pad(d.getHours())}:${pad(d.getMinutes())}` : day;
        };
        const placeholderOf = input => input.getAttribute('placeholder') || ({ month: 'Pilih bulan', datetime: 'Pilih tanggal & jam', date: 'Pilih tanggal' })[kindOf(input)];

        const buttonFor = input => input.nextElementSibling?.classList.contains('cdate-button') ? input.nextElementSibling : null;
        const inputFor = button => button.previousElementSibling;

        let panel = null;
        let current = null;
        let view = null;   // bulan (atau tahun, untuk pilih bulan) yang sedang ditampilkan
        let time = '00:00';

        function enhance(input) {
            if (input.dataset.cdate === '1' && buttonFor(input)) return;
            let button = buttonFor(input);
            if (!button) {
                button = document.createElement('button');
                button.type = 'button';
                input.after(button);
            }
            button.className = input.className.replace(/\binput\b/, 'input cdate-button').replace(/\bpl-\S+/g, '');
            button.setAttribute('aria-haspopup', 'dialog');
            button.setAttribute('aria-expanded', 'false');
            if (input.getAttribute('aria-label')) button.setAttribute('aria-label', input.getAttribute('aria-label'));
            button.innerHTML = icon + '<span class="cdate-label"></span><svg class="cselect-caret" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5 7h10l-5 6z"/></svg>';
            input.dataset.cdate = '1';
            input.classList.add('cdate-native');
            input.tabIndex = -1;
            sync(input);
        }

        function sync(input) {
            const button = buttonFor(input);
            if (!button) return;
            const label = button.querySelector('.cdate-label');
            const text = labelOf(input);
            label.textContent = text || placeholderOf(input);
            label.classList.toggle('is-placeholder', !text);
            button.disabled = input.disabled || input.readOnly;
            button.classList.toggle('hidden', input.classList.contains('hidden'));
            if (text) button.classList.remove('is-invalid');
        }
        const syncAll = () => document.querySelectorAll('input[data-cdate="1"]').forEach(sync);
        const enhanceAll = (root = document) => root.querySelectorAll(SELECTOR).forEach(enhance);

        const limits = input => ({ min: parse(input.min, kindOf(input)), max: parse(input.max, kindOf(input)) });
        const outside = (d, { min, max }, kind) => {
            const key = kind === 'month' ? d.getFullYear() * 12 + d.getMonth() : +new Date(d.getFullYear(), d.getMonth(), d.getDate());
            const k = v => kind === 'month' ? v.getFullYear() * 12 + v.getMonth() : +new Date(v.getFullYear(), v.getMonth(), v.getDate());
            return (min && key < k(min)) || (max && key > k(max));
        };

        function commit(value) {
            const input = current;
            input.value = value;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            sync(input);
            close();
        }

        function draw() {
            const input = current;
            const kind = kindOf(input);
            const bounds = limits(input);
            const selected = parse(input.value, kind);
            const now = today();
            let html = '<div class="cdate-head">'
                + `<button type="button" class="cdate-nav" data-step="-1" aria-label="Sebelumnya">${arrow('M15 18 9 12l6-6')}</button>`
                + `<p class="cdate-title">${kind === 'month' ? view.getFullYear() : `${MONTHS[view.getMonth()]} ${view.getFullYear()}`}</p>`
                + `<button type="button" class="cdate-nav" data-step="1" aria-label="Berikutnya">${arrow('m9 6 6 6-6 6')}</button></div>`;

            if (kind === 'month') {
                html += '<div class="cdate-months">' + SHORT.map((name, m) => {
                    const d = new Date(view.getFullYear(), m, 1);
                    const cls = ['cdate-cell', selected && selected.getFullYear() === d.getFullYear() && selected.getMonth() === m ? 'is-selected' : '',
                        now.getFullYear() === d.getFullYear() && now.getMonth() === m ? 'is-today' : ''].join(' ');
                    return `<button type="button" class="${cls}" data-value="${d.getFullYear()}-${pad(m + 1)}" ${outside(d, bounds, kind) ? 'disabled' : ''}>${name}</button>`;
                }).join('') + '</div>';
            } else {
                html += '<div class="cdate-week">' + DAYS.map(d => `<span>${d}</span>`).join('') + '</div><div class="cdate-days">';
                const first = new Date(view.getFullYear(), view.getMonth(), 1);
                const start = new Date(first);
                start.setDate(1 - ((first.getDay() + 6) % 7));   // minggu dimulai Senin
                for (let i = 0; i < 42; i++) {
                    const d = new Date(start);
                    d.setDate(start.getDate() + i);
                    const cls = ['cdate-cell', d.getMonth() !== view.getMonth() ? 'is-muted' : '', +d === +now ? 'is-today' : '',
                        selected && ymd(selected) === ymd(d) ? 'is-selected' : '', (d.getDay() === 0) ? 'is-sunday' : ''].join(' ');
                    html += `<button type="button" class="${cls}" data-value="${ymd(d)}" ${outside(d, bounds, kind) ? 'disabled' : ''}>${d.getDate()}</button>`;
                }
                html += '</div>';
            }

            // Pintasan & aksi.
            const quick = kind === 'month'
                ? [['Bulan ini', `${now.getFullYear()}-${pad(now.getMonth() + 1)}`]]
                : [['Hari ini', ymd(now)], ['Kemarin', ymd(new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1))]];
            html += '<div class="cdate-foot">';
            if (kind === 'datetime') {
                // Jam 24 jam (bukan AM/PM bawaan browser), menit per 5.
                const [h, m] = time.split(':').map(Number);
                const opts = (max, step, selectedValue) => Array.from({ length: Math.ceil(max / step) }, (_, i) => i * step)
                    .map(v => `<option value="${pad(v)}" ${v === selectedValue ? 'selected' : ''}>${pad(v)}</option>`).join('');
                html += `<label class="cdate-time">Jam <select class="cdate-select" data-hour aria-label="Jam">${opts(24, 1, h)}</select>`
                    + `<span>:</span><select class="cdate-select" data-minute aria-label="Menit">${opts(60, 5, m - (m % 5))}</select></label>`;
            }
            html += '<div class="cdate-quick">' + quick.map(([label, value]) => {
                const d = parse(value, kind);
                return `<button type="button" class="cdate-chip" data-value="${value}" ${outside(d, bounds, kind) ? 'disabled' : ''}>${label}</button>`;
            }).join('');
            if (!input.required && input.value) html += '<button type="button" class="cdate-chip is-clear" data-clear>Hapus</button>';
            html += '</div></div>';
            panel.innerHTML = html;
        }

        function place(button) {
            if (matchMedia('(max-width: 1023.98px)').matches) return;   // HP: bottom sheet lewat CSS
            const rect = button.getBoundingClientRect();
            const height = panel.offsetHeight;
            const up = window.innerHeight - rect.bottom < height + 16 && rect.top > height + 16;
            panel.style.left = Math.max(12, Math.min(rect.left, window.innerWidth - panel.offsetWidth - 12)) + 'px';
            panel.style.top = (up ? rect.top - height - 8 : rect.bottom + 8) + 'px';
            panel.classList.toggle('is-up', up);
        }

        function open(button) {
            const input = inputFor(button);
            if (!input || input.disabled || input.readOnly || !button.isConnected) return;
            if (current === input) { close(); return; }
            close(false);
            current = input;
            const kind = kindOf(input);
            const selected = parse(input.value, kind);
            // Tanpa nilai: buka di bulan ini, dijepit ke batas min/max bila ada.
            const { min, max } = limits(input);
            let base = selected || today();
            if (!selected && max && base > max) base = max;
            if (!selected && min && base < min) base = min;
            view = new Date(base.getFullYear(), kind === 'month' ? 0 : base.getMonth(), 1);
            time = selected && kind === 'datetime' ? `${pad(selected.getHours())}:${pad(selected.getMinutes())}` : (kind === 'datetime' ? `${pad(new Date().getHours())}:00` : '00:00');
            panel = document.createElement('div');
            panel.className = 'cselect-panel cdate-panel';
            panel.setAttribute('role', 'dialog');
            panel.setAttribute('aria-label', placeholderOf(input));
            document.body.appendChild(panel);
            draw();
            button.setAttribute('aria-expanded', 'true');
            place(button);
            (panel.querySelector('.is-selected') || panel.querySelector('.is-today') || panel.querySelector('.cdate-cell:not([disabled])'))?.focus({ preventScroll: true });
        }

        function close(focus = true) {
            if (!panel) return;
            const button = current && buttonFor(current);
            panel.remove();
            panel = null;
            button?.setAttribute('aria-expanded', 'false');
            if (focus) button?.focus({ preventScroll: true });
            current = null;
        }

        document.addEventListener('click', e => {
            if (panel && panel.contains(e.target)) {
                const kind = kindOf(current);
                const step = e.target.closest('[data-step]');
                if (step) {
                    const n = +step.dataset.step;
                    view = kind === 'month' ? new Date(view.getFullYear() + n, 0, 1) : new Date(view.getFullYear(), view.getMonth() + n, 1);
                    draw();
                    return;
                }
                if (e.target.closest('[data-clear]')) { commit(''); return; }
                const pick = e.target.closest('[data-value]:not([disabled])');
                if (pick) {
                    const t = readTime();
                    commit(kind === 'datetime' ? `${pick.dataset.value}T${t}` : pick.dataset.value);
                }
                return;
            }
            const button = e.target.closest('.cdate-button');
            if (button) { open(button); return; }
            // Klik label → buka pemilihnya.
            const label = e.target.closest('label[for]');
            const target = label && document.getElementById(label.htmlFor);
            if (target?.dataset.cdate === '1') { e.preventDefault(); open(buttonFor(target)); return; }
            if (panel) close(false);
        });

        const readTime = () => {
            const h = panel?.querySelector('[data-hour]');
            const m = panel?.querySelector('[data-minute]');
            return h && m ? `${h.value}:${m.value}` : time;
        };

        // Jam diubah setelah tanggal dipilih → langsung tersimpan.
        document.addEventListener('change', e => {
            if (!panel || !e.target.matches('[data-hour], [data-minute]')) return;
            time = readTime();
            const d = parse(current.value, 'datetime');
            if (d) {
                current.value = `${ymd(d)}T${time}`;
                current.dispatchEvent(new Event('change', { bubbles: true }));
                sync(current);
            }
        });

        // Keyboard: panah memindah hari, Enter memilih, Esc menutup.
        document.addEventListener('keydown', e => {
            const button = e.target.closest?.('.cdate-button');
            if (button && !panel && ['ArrowDown', 'Enter', ' '].includes(e.key)) { e.preventDefault(); open(button); return; }
            if (!panel) return;
            if (e.key === 'Escape') { e.preventDefault(); close(); return; }
            const cell = e.target.closest?.('.cdate-cell');
            if (!cell) return;
            const moves = kindOf(current) === 'month'
                ? { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -3, ArrowDown: 3 }
                : { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
            if (moves[e.key]) {
                e.preventDefault();
                const cells = [...panel.querySelectorAll('.cdate-cell')];
                cells[Math.max(0, Math.min(cells.length - 1, cells.indexOf(cell) + moves[e.key]))]?.focus();
            }
        });

        window.addEventListener('resize', () => close(false));
        document.addEventListener('scroll', e => { if (panel && !panel.contains(e.target)) close(false); }, true);

        // Nilai yang diubah script halaman (tanpa event) tetap tampil benar.
        ['change', 'input', 'reset'].forEach(type => document.addEventListener(type, () => setTimeout(syncAll), true));

        // Baris baru & elemen yang ditambahkan belakangan.
        new MutationObserver(records => {
            records.forEach(record => record.addedNodes.forEach(node => {
                if (node.nodeType !== 1 || node.closest?.('.cdate-panel')) return;
                if (node.matches(SELECTOR)) enhance(node); else enhanceAll(node);
            }));
        }).observe(document.body, { childList: true, subtree: true });

        // Wajib diisi tapi kosong: tandai tombolnya.
        document.addEventListener('invalid', e => {
            if (e.target.dataset?.cdate === '1') buttonFor(e.target)?.classList.add('is-invalid');
        }, true);

        enhanceAll();
        window.addEventListener('load', syncAll);
    })();
</script>
