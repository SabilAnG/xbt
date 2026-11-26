{{-- Baris berulang sederhana: [data-rows-form] berisi [data-rows] > [data-row], tombol [data-add-row] & [data-remove-row]. --}}
<script>
    (function () {
        document.querySelectorAll('[data-rows-form]').forEach(function (form) {
            var rows = form.querySelector('[data-rows]');
            var next = rows.children.length;

            form.querySelector('[data-add-row]').addEventListener('click', function () {
                var row = rows.querySelector('[data-row]').cloneNode(true);
                row.querySelectorAll('select, input').forEach(function (el) {
                    el.name = el.name.replace(/\[\d+\]/, '[' + next + ']');
                    el.value = '';
                });
                row.querySelectorAll('.error, [data-row-hint]').forEach(function (el) { el.remove(); });
                rows.appendChild(row);
                next++;
                row.dispatchEvent(new CustomEvent('row:added', { bubbles: true }));
                var first = row.querySelector('select, input');
                if (first) first.focus();
            });

            rows.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-remove-row]');
                if (!btn) return;
                var row = btn.closest('[data-row]');
                if (rows.children.length > 1) {
                    row.remove();
                } else {
                    row.querySelectorAll('select, input').forEach(function (el) { el.value = ''; });
                }
                rows.dispatchEvent(new CustomEvent('row:removed', { bubbles: true }));
            });
        });
    })();
</script>
