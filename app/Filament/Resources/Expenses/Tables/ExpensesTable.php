<?php

namespace App\Filament\Resources\Expenses\Tables;

use App\Models\Expense;
use App\Services\PostingService;
use App\Support\TableActions;
use App\Support\TableExport;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExpensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('spent_at', 'desc')
            ->columns([
                TextColumn::make('reference_number')->label('No. Bukti')->searchable()->sortable(),
                TextColumn::make('spent_at')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('category.name')->label('Kategori')->searchable()
                    ->description(fn (Expense $r) => $r->category?->type?->name),
                TextColumn::make('paid_to')->label('Kepada')->placeholder('—')->toggleable(),
                TextColumn::make('amount')->label('Jumlah')->money('IDR')->sortable()->alignRight(),
                TextColumn::make('wallet.name')->label('Dompet')->placeholder('—')->toggleable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => Expense::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => $state === 'posted' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')->options(Expense::STATUSES),
                SelectFilter::make('expense_category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name')
                    ->searchable()->preload(),
                Filter::make('periode')
                    ->schema([
                        DatePicker::make('dari')->label('Dari tanggal'),
                        DatePicker::make('sampai')->label('Sampai tanggal'),
                    ])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['dari'] ?? null, fn ($q, $d) => $q->whereDate('spent_at', '>=', $d))
                        ->when($data['sampai'] ?? null, fn ($q, $d) => $q->whereDate('spent_at', '<=', $d))),
            ])
            ->recordActions([
                Action::make('post')
                    ->label('Bukukan')
                    ->icon('heroicon-o-check-circle')->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Saldo dompet berkurang sebesar jumlah pengeluaran.')
                    ->visible(fn (Expense $record) => ! $record->isPosted())
                    ->action(function (Expense $record, PostingService $posting) {
                        try {
                            $posting->postExpense($record);
                            Notification::make()->success()->title('Pengeluaran dibukukan')->send();
                        } catch (\Throwable $e) {
                            Notification::make()->danger()->title('Gagal membukukan')->body($e->getMessage())->send();
                        }
                    }),

                Action::make('unpost')
                    ->label('Batalkan')
                    ->icon('heroicon-o-arrow-uturn-left')->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Expense $record) => $record->isPosted())
                    ->action(function (Expense $record, PostingService $posting) {
                        $posting->unpostExpense($record);
                        Notification::make()->success()->title('Dikembalikan ke draft')->send();
                    }),

                EditAction::make()->label('Ubah'),

                TableActions::delete(TableActions::onlyDraft()),
            ])
            ->headerActions([
                TableExport::action('pengeluaran', [
                    'No. Bukti' => fn (Expense $r) => $r->reference_number,
                    'Tanggal' => fn (Expense $r) => $r->spent_at,
                    'Kategori' => fn (Expense $r) => $r->category?->name,
                    'Jenis' => fn (Expense $r) => $r->category?->type?->name,
                    'Kepada' => fn (Expense $r) => $r->paid_to,
                    'Keterangan' => fn (Expense $r) => $r->description,
                    'Dompet' => fn (Expense $r) => $r->wallet?->name,
                    'Jumlah' => fn (Expense $r) => (float) $r->amount,
                    'Status' => fn (Expense $r) => Expense::STATUSES[$r->status] ?? $r->status,
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    TableActions::deleteBulk(TableActions::onlyDraft()),
                ]),
            ])
            ->emptyStateHeading('Belum ada pengeluaran')
            ->emptyStateDescription('Catat biaya seperti bayar las, listrik, atau gaji di sini.')
            ->emptyStateIcon('heroicon-o-banknotes');
    }
}
