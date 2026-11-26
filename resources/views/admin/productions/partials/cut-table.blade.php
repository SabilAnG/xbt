{{-- $assignments: hasil CuttingPlanner; $material: bahan pipanya. --}}
<div class="mt-2 overflow-x-auto">
    <table class="table text-xs">
        <thead><tr><th>Potongan untuk</th><th class="num">Panjang</th><th>Diambil dari</th><th class="num">Sebelum</th><th class="num">Serbuk</th><th class="num">Sisa</th></tr></thead>
        <tbody>
        @foreach ($assignments as $cut)
            <tr>
                <td>{{ $cut['label'] }}</td>
                <td class="num font-semibold">{{ qty($cut['length'], 2) }} cm</td>
                <td class="font-mono">{{ $cut['code'] }}</td>
                <td class="num text-slate-500">{{ qty($cut['before'], 2) }} cm</td>
                <td class="num text-slate-400">{{ qty($cut['kerf'], 2) }}</td>
                <td class="num">{{ qty($cut['after'], 2) }} cm</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
