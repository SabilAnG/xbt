<?php

namespace App\Filament\Resources\Wallets\Tables;

use App\Models\Wallet;
use App\Support\TableActions;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WalletsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->label('Dompet')->searchable()->sortable()->weight('bold')
                    ->description(fn (Wallet $r) => collect([$r->account_number, $r->holder_name])->filter()->implode(' • ') ?: null),

                TextColumn::make('type')->label('Tipe')->badge()
                    ->formatStateUsing(fn (string $state) => Wallet::TYPES[$state] ?? $state),

                TextColumn::make('opening_balance')->label('Saldo Awal')->money('IDR')->alignRight()->toggleable(),

                TextColumn::make('current_balance')
                    ->label('Saldo Berjalan')
                    ->money('IDR')
                    ->alignRight()
                    ->sortable()
                    ->weight('bold')
                    ->color(fn (Wallet $r) => (float) $r->current_balance < 0 ? 'danger' : 'success')
                    ->summarize(Sum::make()->label('Total kas')->money('IDR')),

                TextColumn::make('transactions_count')->counts('transactions')->label('Mutasi')->alignCenter(),

                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')->options(Wallet::TYPES),
            ])
            ->recordActions([
                Action::make('recalculate')
                    ->label('Hitung ulang saldo')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Saldo dihitung ulang dari saldo awal ditambah seluruh mutasi.')
                    ->action(function (Wallet $record) {
                        $record->recalculateBalance();
                        Notification::make()->success()->title('Saldo dihitung ulang')->send();
                    }),

                EditAction::make()->label('Ubah'),

                TableActions::delete(fn (Wallet $w) => $w->transactions()->exists()
                    ? 'Dompet ini sudah punya mutasi. Nonaktifkan saja agar riwayat kas tetap utuh.'
                    : null),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    TableActions::deleteBulk(fn (Wallet $w) => $w->transactions()->exists()
                        ? 'punya mutasi'
                        : null),
                ]),
            ])
            ->emptyStateHeading('Belum ada dompet')
            ->emptyStateDescription('Tambahkan kas laci atau rekening bank sebagai sumber dana transaksi.')
            ->emptyStateIcon('heroicon-o-wallet');
    }
}
