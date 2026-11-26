@props(['name', 'label', 'value' => null, 'hint' => null, 'minHeight' => '320px'])
{{--
    Editor teks kaya seperti Word: gaya paragraf, jenis & ukuran huruf, tebal/
    miring/garis bawah, warna, perataan, daftar, spasi baris & paragraf, tautan,
    dan tampilan kode HTML. Hasilnya tetap HTML di <textarea name="..."> sehingga
    disimpan & ditampilkan seperti sebelumnya.
--}}
@php $id = 'rte-'.$name.'-'.\Illuminate\Support\Str::random(4); @endphp
<div {{ $attributes->only('class')->class(['rte']) }} data-rte>
    <label for="{{ $id }}" class="label">{{ $label }}</label>
    <div class="rte-shell">
        <div class="rte-toolbar" role="toolbar" aria-label="Format teks">
            <div class="rte-group">
                <button type="button" class="rte-btn" data-cmd="undo" title="Urungkan (Ctrl+Z)"><svg viewBox="0 0 24 24"><path d="M9 14 4 9l5-5M4 9h11a5 5 0 0 1 0 10h-3"/></svg></button>
                <button type="button" class="rte-btn" data-cmd="redo" title="Ulangi (Ctrl+Y)"><svg viewBox="0 0 24 24"><path d="m15 14 5-5-5-5m5 5H9a5 5 0 0 0 0 10h3"/></svg></button>
            </div>
            <div class="rte-group">
                <select class="pill rte-select w-[128px]" data-block aria-label="Gaya paragraf">
                    <option value="p">Paragraf</option>
                    <option value="h2">Judul 1</option>
                    <option value="h3">Judul 2</option>
                    <option value="h4">Judul 3</option>
                    <option value="blockquote">Kutipan</option>
                </select>
                <select class="pill rte-select w-[132px]" data-font aria-label="Jenis huruf">
                    <option value="">Huruf bawaan</option>
                    <option value="Poppins, sans-serif">Poppins</option>
                    <option value="Arial, sans-serif">Arial</option>
                    <option value="Helvetica, Arial, sans-serif">Helvetica</option>
                    <option value="Georgia, serif">Georgia</option>
                    <option value="'Times New Roman', serif">Times New Roman</option>
                    <option value="'Courier New', monospace">Courier New</option>
                </select>
                <select class="pill rte-select w-[96px]" data-size aria-label="Ukuran huruf">
                    <option value="">Ukuran</option>
                    @foreach ([10, 12, 14, 16, 18, 20, 24, 28, 32, 40] as $px)<option value="{{ $px }}px">{{ $px }}</option>@endforeach
                </select>
            </div>
            <div class="rte-group">
                <button type="button" class="rte-btn" data-cmd="bold" title="Tebal (Ctrl+B)"><svg viewBox="0 0 24 24"><path d="M7 5h6a3.5 3.5 0 0 1 0 7H7zm0 7h7a3.5 3.5 0 0 1 0 7H7z"/></svg></button>
                <button type="button" class="rte-btn" data-cmd="italic" title="Miring (Ctrl+I)"><svg viewBox="0 0 24 24"><path d="M14 5h-4m4 14h-4m3-14-2 14"/></svg></button>
                <button type="button" class="rte-btn" data-cmd="underline" title="Garis bawah (Ctrl+U)"><svg viewBox="0 0 24 24"><path d="M7 4v7a5 5 0 0 0 10 0V4M5 20h14"/></svg></button>
                <button type="button" class="rte-btn" data-cmd="strikeThrough" title="Coret"><svg viewBox="0 0 24 24"><path d="M5 12h14M16 7.5C15.4 5.5 13.8 4.5 12 4.5c-2.5 0-4 1.3-4 3 0 1.4 1 2.3 3 2.9m5 3.6c.5.6.8 1.3.8 2 0 2-1.7 3.5-4.3 3.5-2.2 0-3.9-1-4.5-3"/></svg></button>
                <label class="rte-btn rte-color" title="Warna huruf">
                    <svg viewBox="0 0 24 24"><path d="m7 16 5-12 5 12M9 12h6"/></svg>
                    <span class="rte-swatch" data-swatch="fore" style="background:#3a4166"></span>
                    <input type="color" value="#3a4166" data-color="foreColor" aria-label="Warna huruf">
                </label>
                <label class="rte-btn rte-color" title="Warna sorot">
                    <svg viewBox="0 0 24 24"><path d="m9 11 6-6 4 4-6 6m-4-4-3 3v3h3l3-3m-3-3 4 4"/></svg>
                    <span class="rte-swatch" data-swatch="hilite" style="background:#fde68a"></span>
                    <input type="color" value="#fde68a" data-color="hiliteColor" aria-label="Warna sorot">
                </label>
            </div>
            <div class="rte-group">
                <button type="button" class="rte-btn" data-cmd="justifyLeft" title="Rata kiri"><svg viewBox="0 0 24 24"><path d="M4 6h16M4 10h10M4 14h16M4 18h10"/></svg></button>
                <button type="button" class="rte-btn" data-cmd="justifyCenter" title="Rata tengah"><svg viewBox="0 0 24 24"><path d="M4 6h16M7 10h10M4 14h16M7 18h10"/></svg></button>
                <button type="button" class="rte-btn" data-cmd="justifyRight" title="Rata kanan"><svg viewBox="0 0 24 24"><path d="M4 6h16M10 10h10M4 14h16M10 18h10"/></svg></button>
                <button type="button" class="rte-btn" data-cmd="justifyFull" title="Rata kiri-kanan"><svg viewBox="0 0 24 24"><path d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg></button>
            </div>
            <div class="rte-group">
                <button type="button" class="rte-btn" data-cmd="insertUnorderedList" title="Daftar berpoin"><svg viewBox="0 0 24 24"><path d="M9 6h11M9 12h11M9 18h11M4.5 6h.01M4.5 12h.01M4.5 18h.01"/></svg></button>
                <button type="button" class="rte-btn" data-cmd="insertOrderedList" title="Daftar bernomor"><svg viewBox="0 0 24 24"><path d="M10 6h10M10 12h10M10 18h10M4 5l1.5-1V9M4 15.5c0-.8.7-1.5 1.5-1.5S7 14.6 7 15.4c0 1-3 2.1-3 3.6h3"/></svg></button>
                <button type="button" class="rte-btn" data-cmd="outdent" title="Kurangi indentasi"><svg viewBox="0 0 24 24"><path d="M4 6h16M11 10h9M11 14h9M4 18h16M8 9l-3 3 3 3"/></svg></button>
                <button type="button" class="rte-btn" data-cmd="indent" title="Tambah indentasi"><svg viewBox="0 0 24 24"><path d="M4 6h16M11 10h9M11 14h9M4 18h16M5 9l3 3-3 3"/></svg></button>
            </div>
            <div class="rte-group">
                <select class="pill rte-select w-[104px]" data-line-height aria-label="Spasi baris" title="Spasi baris">
                    <option value="">Spasi</option>
                    <option value="1">1,0</option>
                    <option value="1.15">1,15</option>
                    <option value="1.5">1,5</option>
                    <option value="1.75">1,75</option>
                    <option value="2">2,0</option>
                    <option value="2.5">2,5</option>
                </select>
                <select class="pill rte-select w-[126px]" data-spacing aria-label="Jarak antar paragraf" title="Jarak antar paragraf">
                    <option value="">Jarak paragraf</option>
                    <option value="0">Rapat (0)</option>
                    <option value="6px">Kecil (6pt)</option>
                    <option value="12px">Sedang (12pt)</option>
                    <option value="20px">Lebar (20pt)</option>
                </select>
            </div>
            <div class="rte-group">
                <button type="button" class="rte-btn" data-link title="Sisipkan tautan"><svg viewBox="0 0 24 24"><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1m2 4a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/></svg></button>
                <button type="button" class="rte-btn" data-cmd="insertHorizontalRule" title="Garis pemisah"><svg viewBox="0 0 24 24"><path d="M4 12h16"/></svg></button>
                <button type="button" class="rte-btn" data-cmd="removeFormat" title="Hapus format"><svg viewBox="0 0 24 24"><path d="M6 5h12M12 5l-3 14m6-6 5 5m0-5-5 5"/></svg></button>
                <button type="button" class="rte-btn" data-source title="Lihat / ubah kode HTML"><svg viewBox="0 0 24 24"><path d="m9 8-4 4 4 4m6-8 4 4-4 4"/></svg></button>
            </div>
        </div>

        <div class="rte-linkbar" data-linkbar hidden>
            <input type="url" class="input h-9 flex-1 text-[13px]" placeholder="https://..." data-link-url aria-label="Alamat tautan">
            <button type="button" class="btn btn-primary btn-sm" data-link-apply>Pasang</button>
            <button type="button" class="btn btn-sm" data-link-remove>Lepas</button>
            <button type="button" class="btn btn-ghost btn-sm" data-link-cancel>Batal</button>
        </div>

        <div class="rte-content" contenteditable="true" spellcheck="true" style="min-height: {{ $minHeight }}" data-editor aria-labelledby="{{ $id }}-label"></div>
        <textarea id="{{ $id }}" name="{{ $name }}" class="rte-source" spellcheck="false" style="min-height: {{ $minHeight }}" data-source-input hidden>{{ old($name, $value) }}</textarea>

        <div class="rte-footer">
            <span data-mode-label>Mode tampilan</span>
            <span data-count>0 kata</span>
        </div>
    </div>
    @if ($hint)<p class="hint">{{ $hint }}</p>@endif
    @error($name)<p class="error">{{ $message }}</p>@enderror
</div>

@once
@push('scripts')
<script>
    (() => {
        const ALLOWED = new Set(['P', 'H1', 'H2', 'H3', 'H4', 'STRONG', 'B', 'EM', 'I', 'U', 'S', 'STRIKE', 'UL', 'OL', 'LI', 'A', 'BR', 'SPAN', 'BLOCKQUOTE', 'HR', 'DIV', 'SUB', 'SUP', 'TABLE', 'THEAD', 'TBODY', 'TR', 'TD', 'TH']);
        const STYLES = ['font-size', 'font-family', 'font-weight', 'font-style', 'color', 'background-color', 'text-align', 'line-height', 'margin-bottom', 'text-decoration', 'padding-left', 'margin-left'];

        // Bersihkan HTML tempelan (mis. dari Word): buang tag & atribut asing.
        function clean(html) {
            const doc = new DOMParser().parseFromString('<div>' + html + '</div>', 'text/html');
            const walk = node => {
                [...node.children].forEach(el => {
                    walk(el);
                    if (!ALLOWED.has(el.tagName)) {
                        el.replaceWith(...el.childNodes);
                        return;
                    }
                    [...el.attributes].forEach(attr => {
                        if (el.tagName === 'A' && attr.name === 'href' && !/^\s*javascript:/i.test(attr.value)) return;
                        if (attr.name === 'style') {
                            const kept = STYLES.map(p => el.style.getPropertyValue(p) ? `${p}: ${el.style.getPropertyValue(p)}` : '').filter(Boolean).join('; ');
                            kept ? el.setAttribute('style', kept) : el.removeAttribute('style');
                            return;
                        }
                        el.removeAttribute(attr.name);
                    });
                });
            };
            const root = doc.body.firstChild;
            walk(root);
            return root.innerHTML;
        }

        // Rapikan kode HTML agar enak dibaca di mode kode.
        const pretty = html => html.replace(/>\s*</g, '><').replace(/<(\/?(p|h[1-4]|ul|ol|li|blockquote|div|hr|table|tr|thead|tbody)\b[^>]*)>/gi, '\n<$1>').replace(/\n+/g, '\n').trim();

        document.querySelectorAll('[data-rte]').forEach(root => {
            const editor = root.querySelector('[data-editor]');
            const source = root.querySelector('[data-source-input]');
            const form = root.closest('form');
            let range = null;
            let sourceMode = false;

            editor.innerHTML = clean(source.value.trim());
            try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) {}

            // Teks lepas di level atas dibungkus <p> supaya spasi & gaya paragraf bisa diterapkan.
            function normalize() {
                [...editor.childNodes].forEach(node => {
                    if (node.nodeType === 3 && node.textContent.trim() === '') { node.remove(); return; }
                    if (node.nodeType === 3 || (node.nodeType === 1 && ['SPAN', 'STRONG', 'B', 'EM', 'I', 'U', 'A', 'BR', 'FONT'].includes(node.tagName))) {
                        const p = document.createElement('p');
                        node.replaceWith(p);
                        p.appendChild(node);
                        while (p.nextSibling && (p.nextSibling.nodeType === 3 || ['SPAN', 'STRONG', 'B', 'EM', 'I', 'U', 'A', 'FONT'].includes(p.nextSibling.tagName))) p.appendChild(p.nextSibling);
                    }
                });
                if (!editor.innerHTML.trim()) editor.innerHTML = '<p><br></p>';
            }
            normalize();

            const sync = () => {
                if (!sourceMode) source.value = editor.innerHTML.replace(/<p><br><\/p>$/, '').trim();
                const words = (sourceMode ? source.value.replace(/<[^>]+>/g, ' ') : editor.innerText).trim().split(/\s+/).filter(Boolean).length;
                root.querySelector('[data-count]').textContent = words.toLocaleString('id-ID') + ' kata';
            };

            const saveRange = () => {
                const sel = window.getSelection();
                if (sel.rangeCount && editor.contains(sel.getRangeAt(0).commonAncestorContainer)) range = sel.getRangeAt(0).cloneRange();
            };
            const restore = () => {
                editor.focus({ preventScroll: true });
                if (!range) return;
                const sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(range);
            };
            const exec = (cmd, value = null, css = true) => {
                if (sourceMode) return;
                restore();
                document.execCommand('styleWithCSS', false, css);
                document.execCommand(cmd, false, value);
                normalize();
                saveRange();
                sync();
                refreshState();
            };

            // Gaya huruf ke <span style>: pakai penanda <font> lalu ganti.
            function styleSelection(prop, value) {
                if (sourceMode) return;
                restore();
                if (prop === 'font-size') {
                    document.execCommand('styleWithCSS', false, false);
                    document.execCommand('fontSize', false, '7');
                    editor.querySelectorAll('font[size="7"]').forEach(f => swapFont(f, prop, value));
                } else {
                    document.execCommand('styleWithCSS', false, false);
                    document.execCommand('fontName', false, '__rte__');
                    editor.querySelectorAll('font[face="__rte__"]').forEach(f => swapFont(f, prop, value));
                }
                saveRange();
                sync();
            }
            function swapFont(font, prop, value) {
                const span = document.createElement('span');
                if (value) span.style.setProperty(prop, value);
                font.querySelectorAll('[style]').forEach(el => el.style.removeProperty(prop));
                span.append(...font.childNodes);
                font.replaceWith(value ? span : (span.childNodes.length ? (() => { const frag = document.createDocumentFragment(); frag.append(...span.childNodes); return frag; })() : ''));
            }

            // Spasi baris & jarak paragraf berlaku per paragraf yang terpilih.
            function styleBlocks(prop, value) {
                if (sourceMode) return;
                restore();
                normalize();
                const sel = window.getSelection();
                const current = sel.rangeCount ? sel.getRangeAt(0) : null;
                let blocks = [...editor.querySelectorAll('p, h1, h2, h3, h4, li, blockquote')].filter(el => current && current.intersectsNode(el));
                blocks = blocks.filter(el => !blocks.some(other => other !== el && other.contains(el) && other.tagName !== 'LI'));
                if (!blocks.length) blocks = [...editor.children];
                blocks.forEach(el => value === '' ? el.style.removeProperty(prop) : el.style.setProperty(prop, value));
                saveRange();
                sync();
            }

            function refreshState() {
                if (sourceMode) return;
                root.querySelectorAll('[data-cmd]').forEach(btn => {
                    let on = false;
                    try { on = ['bold', 'italic', 'underline', 'strikeThrough', 'insertUnorderedList', 'insertOrderedList', 'justifyLeft', 'justifyCenter', 'justifyRight', 'justifyFull'].includes(btn.dataset.cmd) && document.queryCommandState(btn.dataset.cmd); } catch (e) {}
                    btn.classList.toggle('is-on', on);
                });
                const block = root.querySelector('[data-block]');
                let tag = '';
                try { tag = (document.queryCommandValue('formatBlock') || '').toLowerCase().replace(/[<>]/g, ''); } catch (e) {}
                if ([...block.options].some(o => o.value === tag) && block.value !== tag) {
                    block.value = tag;
                    block.dispatchEvent(new Event('input', { bubbles: true }));
                }
            }

            document.addEventListener('selectionchange', () => {
                const sel = window.getSelection();
                if (sel.rangeCount && editor.contains(sel.getRangeAt(0).commonAncestorContainer)) { saveRange(); refreshState(); }
            });

            root.querySelectorAll('[data-cmd]').forEach(btn => {
                btn.addEventListener('mousedown', e => e.preventDefault());
                btn.addEventListener('click', () => exec(btn.dataset.cmd));
            });
            root.querySelector('[data-block]').addEventListener('change', e => exec('formatBlock', '<' + e.target.value + '>'));
            root.querySelector('[data-font]').addEventListener('change', e => styleSelection('font-family', e.target.value));
            root.querySelector('[data-size]').addEventListener('change', e => styleSelection('font-size', e.target.value));
            root.querySelector('[data-line-height]').addEventListener('change', e => styleBlocks('line-height', e.target.value));
            root.querySelector('[data-spacing]').addEventListener('change', e => styleBlocks('margin-bottom', e.target.value));
            root.querySelectorAll('[data-color]').forEach(input => {
                input.addEventListener('input', () => {
                    root.querySelector(`[data-swatch="${input.dataset.color === 'foreColor' ? 'fore' : 'hilite'}"]`).style.background = input.value;
                });
                input.addEventListener('change', () => exec(input.dataset.color, input.value));
            });

            // Tautan lewat bilah kecil (tanpa dialog browser).
            const linkbar = root.querySelector('[data-linkbar]');
            const linkUrl = root.querySelector('[data-link-url]');
            root.querySelector('[data-link]').addEventListener('mousedown', e => e.preventDefault());
            root.querySelector('[data-link]').addEventListener('click', () => {
                saveRange();
                const anchor = range && (range.commonAncestorContainer.nodeType === 1 ? range.commonAncestorContainer : range.commonAncestorContainer.parentElement).closest('a');
                linkUrl.value = anchor ? anchor.getAttribute('href') : '';
                linkbar.hidden = false;
                linkUrl.focus();
            });
            const closeLink = () => { linkbar.hidden = true; restore(); };
            root.querySelector('[data-link-apply]').addEventListener('click', () => {
                const url = linkUrl.value.trim();
                linkbar.hidden = true;
                if (url) exec('createLink', /^(https?:|mailto:|tel:|\/|#)/i.test(url) ? url : 'https://' + url, false);
            });
            root.querySelector('[data-link-remove]').addEventListener('click', () => { linkbar.hidden = true; exec('unlink'); });
            root.querySelector('[data-link-cancel]').addEventListener('click', closeLink);
            linkUrl.addEventListener('keydown', e => {
                if (e.key === 'Enter') { e.preventDefault(); root.querySelector('[data-link-apply]').click(); }
                if (e.key === 'Escape') closeLink();
            });

            // Mode kode HTML.
            root.querySelector('[data-source]').addEventListener('click', e => {
                sourceMode = !sourceMode;
                if (sourceMode) {
                    sync();
                    source.value = pretty(source.value);
                } else {
                    editor.innerHTML = clean(source.value);
                    normalize();
                }
                editor.hidden = sourceMode;
                source.hidden = !sourceMode;
                e.currentTarget.classList.toggle('is-on', sourceMode);
                root.querySelectorAll('.rte-toolbar button:not([data-source]), .rte-toolbar select, .rte-toolbar input').forEach(el => { el.disabled = sourceMode; });
                root.querySelectorAll('.rte-toolbar .cselect-button').forEach(el => { el.disabled = sourceMode; });
                root.querySelector('[data-mode-label]').textContent = sourceMode ? 'Mode kode HTML' : 'Mode tampilan';
                (sourceMode ? source : editor).focus();
                sync();
            });

            editor.addEventListener('paste', e => {
                const html = e.clipboardData.getData('text/html');
                const text = e.clipboardData.getData('text/plain');
                e.preventDefault();
                if (html) document.execCommand('insertHTML', false, clean(html));
                else document.execCommand('insertText', false, text);
                normalize();
                sync();
            });
            editor.addEventListener('input', sync);
            source.addEventListener('input', sync);
            form?.addEventListener('submit', () => {
                if (sourceMode) { editor.innerHTML = clean(source.value); }
                sync();
            });
            sync();
        });
    })();
</script>
@endpush
@endonce
