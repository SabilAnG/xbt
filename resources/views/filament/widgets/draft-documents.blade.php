@php
    $groups = $this->getGroups();
    $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
    $kosong = collect($groups)->sum('total') === 0;
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        heading="Nota Draft"
        description="Belum dibukukan — stok dan kas belum bergerak."
        icon="heroicon-o-document-text"
    >
        @if ($kosong)
            <div class="flex flex-col items-center gap-2 py-6 text-center">
                <x-filament::icon icon="heroicon-o-check-badge" class="h-8 w-8 text-success-500" />
                <p class="text-sm font-medium">Semua nota sudah dibukukan</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Tidak ada draft yang menggantung.</p>
            </div>
        @else
            <div class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($groups as $group)
                    @continue($group['total'] === 0)
                    <div class="py-3 first:pt-0 last:pb-0">
                        <div class="mb-2 flex items-center gap-2">
                            <x-filament::icon :icon="$group['icon']" class="h-4 w-4 text-{{ $group['color'] }}-500" />
                            <span class="text-sm font-semibold">{{ $group['label'] }}</span>
                            <x-filament::badge :color="$group['color']" size="sm">{{ $group['total'] }}</x-filament::badge>
                        </div>

                        <ul class="space-y-1">
                            @foreach ($group['rows'] as $row)
                                <li>
                                    <a href="{{ $row['url'] }}"
                                       class="flex items-center justify-between gap-3 rounded-lg px-2 py-1.5 text-sm hover:bg-gray-50 dark:hover:bg-white/5">
                                        <span class="font-mono text-xs">{{ $row['nomor'] }}</span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $row['tanggal'] }}</span>
                                        <span class="ms-auto font-medium tabular-nums">{{ $rp($row['nilai']) }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>

                        @if ($group['total'] > count($group['rows']))
                            <p class="mt-1 px-2 text-xs text-gray-500 dark:text-gray-400">
                                dan {{ $group['total'] - count($group['rows']) }} lainnya
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
