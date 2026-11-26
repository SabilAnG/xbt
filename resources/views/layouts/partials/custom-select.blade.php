{{--
    Dropdown bergaya dashboard untuk semua <select class="input|pill">.

    <select> asli tetap ada (disembunyikan secara visual) dan tetap jadi sumber
    nilai: validasi form, old(), dan script halaman (mis. menyembunyikan opsi
    gudang, membaca selectedOptions) bekerja seperti biasa. Tombol di sebelahnya
    hanya tampilan; memilih opsi menulis ke <select> lalu memicu "change".
    Baris yang digandakan (repeat-rows) ikut tertangani lewat event delegation.
--}}
<script>
    (() => {
        const SELECTOR = 'select.input:not([multiple]):not([size]), select.pill';
        const caret = '<svg class="cselect-caret" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5 7h10l-5 6z"/></svg>';
        const check = '<svg class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m5 12 5 5L20 7"/></svg>';
        let panel = null;
        let current = null;
        let active = -1;

        const buttonFor = select => select.nextElementSibling?.classList.contains('cselect-button') ? select.nextElementSibling : null;
        const selectFor = button => button.previousElementSibling;
        const labelOf = select => {
            const option = select.selectedOptions[0];
            return option ? option.textContent.trim() : '';
        };

        function enhance(select) {
            if (select.dataset.cselect === '1' && buttonFor(select)) return;
            let button = buttonFor(select);
            if (!button) {
                button = document.createElement('button');
                button.type = 'button';
                select.after(button);
            }
            button.className = select.className.replace(/\binput\b/, 'input cselect-button').replace(/\bpill\b/, 'pill cselect-button') + (select.classList.contains('pill') ? '' : ' cselect-field');
            button.classList.remove('appearance-none');
            button.setAttribute('aria-haspopup', 'listbox');
            button.setAttribute('aria-expanded', 'false');
            button.innerHTML = '<span class="cselect-label"></span>' + caret;
            select.dataset.cselect = '1';
            select.classList.add('cselect-native');
            select.tabIndex = -1;
            sync(select);
        }

        function sync(select) {
            const button = buttonFor(select);
            if (!button) return;
            const option = select.selectedOptions[0];
            const label = button.querySelector('.cselect-label');
            label.textContent = labelOf(select);
            label.classList.toggle('is-placeholder', !option || option.value === '');
            button.disabled = select.disabled;
            button.classList.toggle('hidden', select.classList.contains('hidden'));
        }

        const syncAll = () => document.querySelectorAll('select[data-cselect="1"]').forEach(sync);
        const enhanceAll = (root = document) => root.querySelectorAll(SELECTOR).forEach(enhance);

        function close(focus = true) {
            if (!panel) return;
            panel.remove();
            panel = null;
            if (current) {
                const button = buttonFor(current);
                button?.setAttribute('aria-expanded', 'false');
                if (focus) button?.focus();
            }
            current = null;
        }

        function choose(option) {
            const select = current;
            if (!option || option.disabled) return;
            select.value = option.value;
            close();
            sync(select);
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function items() {
            return panel ? [...panel.querySelectorAll('[role="option"]:not([hidden])')] : [];
        }

        function highlight(index) {
            const list = items();
            if (!list.length) return;
            active = (index + list.length) % list.length;
            list.forEach((el, i) => el.classList.toggle('is-active', i === active));
            list[active].scrollIntoView({ block: 'nearest' });
        }

        function render(select) {
            const box = document.createElement('div');
            box.className = 'cselect-panel';
            box.setAttribute('role', 'listbox');
            const options = [...select.options].filter(o => !o.hidden);
            const searchable = options.length > 8;
            if (searchable) {
                box.insertAdjacentHTML('beforeend', '<div class="cselect-search"><input type="text" placeholder="Cari..." aria-label="Cari pilihan"></div>');
            }
            const list = document.createElement('div');
            list.className = 'cselect-list';
            let group = null;
            options.forEach(option => {
                const parent = option.parentElement.tagName === 'OPTGROUP' ? option.parentElement : null;
                if (parent && parent !== group) {
                    group = parent;
                    const head = document.createElement('p');
                    head.className = 'cselect-group';
                    head.textContent = parent.label;
                    list.appendChild(head);
                }
                const item = document.createElement('div');
                item.setAttribute('role', 'option');
                item.className = 'cselect-option' + (option.selected ? ' is-selected' : '') + (option.value === '' ? ' is-placeholder' : '') + (option.disabled ? ' is-disabled' : '');
                item.setAttribute('aria-selected', option.selected ? 'true' : 'false');
                item.dataset.index = option.index;
                item.innerHTML = '<span class="min-w-0 flex-1 truncate"></span>' + (option.selected ? check : '');
                item.firstChild.textContent = option.textContent.trim();
                list.appendChild(item);
            });
            if (!options.length) list.innerHTML = '<p class="px-3 py-3 text-center text-xs text-muted">Tidak ada pilihan.</p>';
            box.appendChild(list);

            box.addEventListener('mousedown', e => e.preventDefault());
            box.addEventListener('click', e => {
                const item = e.target.closest('[role="option"]');
                if (item) choose(select.options[item.dataset.index]);
            });
            box.addEventListener('mousemove', e => {
                const item = e.target.closest('[role="option"]');
                if (item) highlight(items().indexOf(item));
            });
            const search = box.querySelector('.cselect-search input');
            if (search) {
                search.addEventListener('mousedown', e => e.stopPropagation());
                search.addEventListener('input', () => {
                    const q = search.value.trim().toLowerCase();
                    list.querySelectorAll('[role="option"]').forEach(el => { el.hidden = q !== '' && !el.textContent.toLowerCase().includes(q); });
                    list.querySelectorAll('.cselect-group').forEach(head => {
                        let next = head.nextElementSibling, any = false;
                        while (next && !next.classList.contains('cselect-group')) { any = any || !next.hidden; next = next.nextElementSibling; }
                        head.hidden = !any;
                    });
                    highlight(0);
                });
                search.addEventListener('keydown', keys);
            }
            return box;
        }

        function place(button) {
            const rect = button.getBoundingClientRect();
            const width = Math.max(rect.width, 180);
            panel.style.minWidth = width + 'px';
            panel.style.maxWidth = Math.max(width, 320) + 'px';
            const below = window.innerHeight - rect.bottom;
            const height = Math.min(panel.scrollHeight, 320);
            const up = below < height + 16 && rect.top > below;
            panel.style.left = Math.min(rect.left, window.innerWidth - panel.offsetWidth - 12) + 'px';
            panel.style.top = (up ? rect.top - Math.min(panel.offsetHeight, 330) - 8 : rect.bottom + 8) + 'px';
            panel.classList.toggle('is-up', up);
        }

        function open(button) {
            const select = selectFor(button);
            if (!select || select.disabled) return;
            if (current === select) { close(); return; }
            close(false);
            current = select;
            panel = render(select);
            document.body.appendChild(panel);
            button.setAttribute('aria-expanded', 'true');
            place(button);
            const list = items();
            highlight(Math.max(list.findIndex(el => el.classList.contains('is-selected')), 0));
            panel.querySelector('.cselect-search input')?.focus();
        }

        function keys(e) {
            if (!panel) return;
            if (e.key === 'ArrowDown') { e.preventDefault(); highlight(active + 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(active - 1); }
            else if (e.key === 'Enter') { e.preventDefault(); const item = items()[active]; if (item) choose(current.options[item.dataset.index]); }
            else if (e.key === 'Escape') { e.preventDefault(); close(); }
            else if (e.key === 'Tab') { close(false); }
        }

        document.addEventListener('click', e => {
            const button = e.target.closest('.cselect-button');
            if (button) {
                const select = selectFor(button);
                if (select && select.dataset.cselect !== '1') enhance(select);
                open(button);
                return;
            }
            // Klik label → buka dropdown-nya.
            const label = e.target.closest('label[for]');
            const target = label && document.getElementById(label.htmlFor);
            if (target && target.dataset.cselect === '1') {
                e.preventDefault();
                buttonFor(target)?.focus();
                return;
            }
            if (panel && !panel.contains(e.target)) close(false);
        });

        document.addEventListener('keydown', e => {
            const button = e.target.closest?.('.cselect-button');
            if (button && !panel && ['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(e.key)) { e.preventDefault(); open(button); return; }
            if (panel && (button || e.target === document.body)) keys(e);
        });

        window.addEventListener('resize', () => close(false));
        document.addEventListener('scroll', e => { if (panel && !panel.contains(e.target)) close(false); }, true);

        // Nilai yang diubah script halaman (tanpa event) tetap tampil benar.
        ['change', 'input', 'click', 'reset'].forEach(type => document.addEventListener(type, () => setTimeout(syncAll), true));

        // Baris baru (repeat-rows) & elemen yang ditambahkan belakangan.
        new MutationObserver(records => {
            records.forEach(record => record.addedNodes.forEach(node => {
                if (node.nodeType !== 1 || node.closest?.('.cselect-panel')) return;
                if (node.matches(SELECTOR)) enhance(node);
                else enhanceAll(node);
            }));
        }).observe(document.body, { childList: true, subtree: true });

        // Isian wajib yang kosong: arahkan fokus ke tombol dropdown-nya.
        document.addEventListener('invalid', e => {
            if (e.target.dataset?.cselect === '1') buttonFor(e.target)?.classList.add('is-invalid');
        }, true);

        enhanceAll();
        window.addEventListener('load', syncAll);
    })();
</script>
