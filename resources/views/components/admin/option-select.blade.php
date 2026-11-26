@props(['name', 'label', 'kind', 'options' => [], 'value' => null, 'required' => false, 'placeholder' => '— pilih —', 'hint' => null])
{{--
    Dropdown master data motor dengan tombol "+" di sampingnya: pilihan baru
    langsung disimpan ke master (tanpa pindah halaman) lalu terpilih.
--}}
@php
    $current = old($name, $value);
    $choices = collect($options);
    if (filled($current) && ! $choices->has($current)) {
        $choices->put($current, $current);
    }
@endphp
<div {{ $attributes->only('class')->class(['relative']) }} data-option-select data-kind="{{ $kind }}">
    <label for="{{ $name }}" class="label">{{ $label }}@if ($required)<span class="text-red-500"> *</span>@endif</label>
    <div class="flex items-stretch gap-2">
        <div class="min-w-0 flex-1">
            <select id="{{ $name }}" name="{{ $name }}" class="input" @required($required) {{ $attributes->except('class') }}>
                <option value="">{{ $placeholder }}</option>
                @foreach ($choices as $optionValue => $optionLabel)
                    <option value="{{ $optionValue }}" @selected((string) $current === (string) $optionValue)>{{ $optionLabel }}</option>
                @endforeach
            </select>
        </div>
        <button type="button" class="tile size-10 shrink-0 text-brand-600" data-option-add title="Tambah {{ strtolower($label) }} baru" aria-label="Tambah {{ strtolower($label) }} baru">
            <svg fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
        </button>
    </div>
    <div class="absolute right-0 left-0 z-30 mt-2 hidden rounded-2xl border border-white bg-white p-3 shadow-(--shadow-pop)" data-option-pop>
        <p class="mb-2 text-[12px] font-semibold text-ink">Tambah {{ strtolower($label) }}</p>
        <div class="flex gap-2">
            <input type="text" class="input h-9 flex-1 text-[13px]" placeholder="Nama baru" data-option-name aria-label="Nama {{ strtolower($label) }} baru">
            <button type="button" class="btn btn-primary btn-sm h-9" data-option-save>Simpan</button>
        </div>
        <p class="mt-1.5 hidden text-[11.5px] text-rose-600" data-option-error></p>
    </div>
    @if ($hint)<p class="hint">{{ $hint }}</p>@endif
    @error($name)<p class="error">{{ $message }}</p>@enderror
</div>

@once
@push('scripts')
<script>
    (() => {
        const url = @json(route('admin.motor-options.store'));
        const token = document.querySelector('meta[name="csrf-token"]')?.content;

        document.querySelectorAll('[data-option-select]').forEach(root => {
            const select = root.querySelector('select');
            const pop = root.querySelector('[data-option-pop]');
            const field = root.querySelector('[data-option-name]');
            const error = root.querySelector('[data-option-error]');
            const save = root.querySelector('[data-option-save]');

            const close = () => { pop.classList.add('hidden'); error.classList.add('hidden'); };
            root.querySelector('[data-option-add]').addEventListener('click', () => {
                const opening = pop.classList.contains('hidden');
                document.querySelectorAll('[data-option-pop]').forEach(el => el.classList.add('hidden'));
                if (opening) { pop.classList.remove('hidden'); field.value = ''; field.focus(); }
            });

            async function submit() {
                const name = field.value.trim();
                if (!name) { field.focus(); return; }
                save.disabled = true;
                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                        body: JSON.stringify({ kind: root.dataset.kind, name }),
                    });
                    const data = await response.json();
                    if (!response.ok) throw new Error(data.errors?.name?.[0] || data.message || 'Gagal menyimpan.');

                    let option = [...select.options].find(o => o.value.toLowerCase() === data.name.toLowerCase());
                    if (!option) {
                        option = new Option(data.name, data.name);
                        select.add(option);
                    }
                    select.value = option.value;
                    select.dispatchEvent(new Event('input', { bubbles: true }));
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    close();
                } catch (e) {
                    error.textContent = e.message;
                    error.classList.remove('hidden');
                } finally {
                    save.disabled = false;
                }
            }

            save.addEventListener('click', submit);
            field.addEventListener('keydown', e => {
                if (e.key === 'Enter') { e.preventDefault(); submit(); }
                if (e.key === 'Escape') close();
            });
            document.addEventListener('click', e => { if (!root.contains(e.target)) close(); });
        });
    })();
</script>
@endpush
@endonce
