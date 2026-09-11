{{-- Kalkulator HPP. Tidak menyimpan apa pun — formnya langsung jadi hasilnya. --}}
<x-filament-panels::page>
    {{ $this->form }}

    <style>
        .hpp-tabel {
            width: 100%;
            border-collapse: collapse;
            font-size: var(--text-sm);
            margin-bottom: calc(var(--spacing) * 4);
        }

        .hpp-tabel th,
        .hpp-tabel td {
            padding: calc(var(--spacing) * 2) calc(var(--spacing) * 3);
            border-bottom: 1px solid var(--gray-200);
            text-align: start;
        }

        .hpp-tabel thead th {
            background-color: var(--gray-50);
            font-weight: 500;
            white-space: nowrap;
        }

        .hpp-tabel .hpp-kanan {
            text-align: end;
            white-space: nowrap;
        }

        .hpp-tabel .hpp-tebal td {
            font-weight: 600;
        }

        .hpp-tabel .hpp-catatan td {
            color: var(--gray-500);
            font-style: italic;
        }

        .hpp-tabel.hpp-sempit {
            max-width: 32rem;
        }

        .hpp-kosong {
            color: var(--gray-500);
            font-size: var(--text-sm);
            margin-bottom: calc(var(--spacing) * 4);
        }

        .hpp-total {
            display: flex;
            flex-wrap: wrap;
            gap: calc(var(--spacing) * 4);
            align-items: baseline;
            padding: calc(var(--spacing) * 3);
            border-radius: var(--radius-lg);
            background-color: var(--gray-50);
            font-size: var(--text-sm);
        }

        .hpp-total .hpp-samping {
            color: var(--gray-500);
        }

        .hpp-kartu-baris {
            display: flex;
            flex-wrap: wrap;
            gap: calc(var(--spacing) * 4);
        }

        .hpp-kartu {
            flex: 1 1 16rem;
            padding: calc(var(--spacing) * 4);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
        }

        .hpp-kartu-judul {
            font-size: var(--text-sm);
            color: var(--gray-500);
        }

        .hpp-kartu-angka {
            font-size: var(--text-2xl);
            font-weight: 600;
            margin-block: calc(var(--spacing) * 1);
        }

        .hpp-kartu-kaki {
            font-size: var(--text-xs);
            color: var(--gray-500);
        }

        .dark .hpp-tabel th,
        .dark .hpp-tabel td {
            border-color: var(--gray-700);
        }

        .dark .hpp-tabel thead th,
        .dark .hpp-total {
            background-color: var(--gray-800);
        }

        .dark .hpp-kartu {
            border-color: var(--gray-700);
        }
    </style>
</x-filament-panels::page>
