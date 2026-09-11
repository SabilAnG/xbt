<?php

namespace App\Filament\Resources\Partners\Tables;

use App\Models\Tenant;
use App\Services\PartnerProvisioning;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PartnersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Toko')->searchable()->sortable()->weight('medium')
                    ->description(fn (Tenant $r) => $r->status === Tenant::AKTIF ? $r->alamatRingkas() : '—'),

                TextColumn::make('owner_name')
                    ->label('Pemilik')->searchable()
                    ->description(fn (Tenant $r) => $r->owner_email),

                TextColumn::make('status')
                    ->label('Status')->badge()
                    ->formatStateUsing(fn (Tenant $r) => $r->displayStatus())
                    ->color(fn (Tenant $r) => match (true) {
                        $r->status === Tenant::MENUNGGU => 'warning',
                        $r->status === Tenant::DITOLAK => 'danger',
                        $r->kedaluwarsa() => 'danger',
                        default => 'success',
                    }),

                TextColumn::make('features')
                    ->label('Hak Akses')->wrap()
                    ->getStateUsing(fn (Tenant $r) => collect($r->features ?? [])
                        ->map(fn ($f) => Tenant::FEATURES[$f] ?? $f)
                        ->implode(', '))
                    ->placeholder('—'),

                TextColumn::make('expires_at')
                    ->label('Masa Pakai')->sortable()
                    ->getStateUsing(fn (Tenant $r) => $r->status === Tenant::AKTIF ? $r->sisaMasa() : '—')
                    ->color(fn (Tenant $r) => $r->kedaluwarsa() ? 'danger' : null),

                TextColumn::make('created_at')
                    ->label('Mendaftar')->dateTime('d M Y')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(Tenant::STATUSES),
            ])
            ->recordActions([
                // Persetujuan: di sinilah database partner lahir, jadi tombolnya
                // sengaja meminta dua jawaban dulu — boleh buka apa, dan sampai kapan.
                Action::make('setujui')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->modalWidth(Width::TwoExtraLarge)
                    ->modalHeading(fn (Tenant $record) => 'Setujui '.$record->name)
                    ->modalDescription('Database toko partner dibuat sekarang, berikut akun admin dari email dan sandi pendaftarannya.')
                    ->modalSubmitActionLabel('Setujui & Buatkan Toko')
                    ->visible(fn (Tenant $record) => $record->status === Tenant::MENUNGGU)
                    ->schema([
                        CheckboxList::make('features')
                            ->label('Partner ini boleh membuka')
                            ->options(Tenant::FEATURES)
                            ->default(['landing', 'toko'])
                            ->required()
                            ->columns(1)
                            ->helperText('Menu yang tidak dicentang tidak muncul di panel partner.'),

                        Select::make('hari')
                            ->label('Masa Pakai')
                            ->options(Tenant::DURATIONS + [0 => 'Tanpa batas'])
                            ->default(7)
                            ->required()
                            ->helperText('Dihitung sejak sekarang. Bisa diperpanjang kapan saja.'),
                    ])
                    ->action(function (Tenant $record, array $data, PartnerProvisioning $siapkan) {
                        try {
                            $siapkan->setujui(
                                $record,
                                $data['features'],
                                (int) $data['hari'] ?: null,
                            );

                            Notification::make()->success()
                                ->title('Toko partner dibuatkan')
                                ->body($record->fresh()->alamat())
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()->danger()
                                ->title('Gagal menyiapkan toko')
                                ->body($e->getMessage())
                                ->persistent()
                                ->send();
                        }
                    }),

                // Email butuh SMTP yang belum tentu terpasang; WhatsApp selalu
                // ada, dan nomornya sudah diisi partner saat mendaftar. Pesannya
                // ditulis lengkap supaya admin tidak perlu mengarangnya tiap kali.
                Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->url(fn (Tenant $record) => $record->tautanWhatsapp(), shouldOpenInNewTab: true)
                    ->visible(fn (Tenant $record) => filled($record->owner_phone)),

                Action::make('perpanjang')
                    ->label('Perpanjang')
                    ->icon('heroicon-o-clock')
                    ->color('primary')
                    ->visible(fn (Tenant $record) => $record->status === Tenant::AKTIF)
                    ->schema([
                        Select::make('hari')
                            ->label('Tambah masa pakai')
                            ->options(Tenant::DURATIONS)
                            ->default(30)->required()
                            ->helperText('Kalau masa pakainya belum habis, tambahan dihitung dari sisa yang ada.'),
                    ])
                    ->action(function (Tenant $record, array $data, PartnerProvisioning $siapkan) {
                        $siapkan->perpanjang($record, (int) $data['hari']);

                        Notification::make()->success()
                            ->title('Masa pakai diperpanjang')
                            ->body('Sampai '.$record->fresh()->expires_at?->translatedFormat('d F Y'))
                            ->send();
                    }),

                Action::make('hak')
                    ->label('Ubah Hak')
                    ->icon('heroicon-o-key')
                    ->color('gray')
                    ->visible(fn (Tenant $record) => $record->status === Tenant::AKTIF)
                    ->schema([
                        CheckboxList::make('features')
                            ->label('Partner ini boleh membuka')
                            ->options(Tenant::FEATURES)
                            ->required()->columns(1),
                    ])
                    ->fillForm(fn (Tenant $record) => ['features' => $record->features ?? []])
                    ->action(function (Tenant $record, array $data) {
                        $record->forceFill(['features' => array_values($data['features'])])->save();

                        Notification::make()->success()->title('Hak akses diperbarui')->send();
                    }),

                Action::make('tolak')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Tenant $record) => $record->status === Tenant::MENUNGGU)
                    ->schema([
                        Textarea::make('alasan')->label('Alasan')->rows(2)
                            ->helperText('Disimpan sebagai catatan, tidak dikirim ke pendaftar.'),
                    ])
                    ->action(function (Tenant $record, array $data, PartnerProvisioning $siapkan) {
                        $siapkan->tolak($record, $data['alasan'] ?? null);

                        Notification::make()->success()->title('Pendaftaran ditolak')->send();
                    }),

                // Menghapus partner ikut membuang databasenya. Tidak bisa
                // dibatalkan, jadi konfirmasinya meminta mengetik nama tokonya.
                Action::make('hapus')
                    ->label('Hapus')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Tenant $record) => 'Hapus '.$record->name.'?')
                    ->modalDescription('Database toko partner ini ikut dibuang berikut seluruh isinya. Tidak ada jalan kembali.')
                    ->action(function (Tenant $record, PartnerProvisioning $siapkan) {
                        $siapkan->hapus($record);

                        Notification::make()->success()->title('Partner dan databasenya dihapus')->send();
                    }),
            ])
            ->emptyStateHeading('Belum ada pendaftar')
            ->emptyStateDescription('Pendaftaran masuk lewat tombol "Jadi Partner" di situs.')
            ->emptyStateIcon('heroicon-o-building-storefront');
    }
}
