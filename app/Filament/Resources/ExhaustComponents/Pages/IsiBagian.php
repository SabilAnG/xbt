<?php

namespace App\Filament\Resources\ExhaustComponents\Pages;

use App\Filament\Resources\ExhaustComponents\ExhaustComponentResource;
use App\Models\ExhaustComponent;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Isi satu bagian knalpot.
 *
 * Dibuka dengan mengklik kartunya. Di sinilah komponen ditambah dan
 * dipasangkan ke bahan bakunya — pekerjaan yang selalu dilakukan per bagian,
 * bukan menyisir satu daftar panjang.
 */
class IsiBagian extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = ExhaustComponentResource::class;

    protected string $view = 'filament.resources.exhaust-components.isi-bagian';

    public ExhaustComponent $record;

    public function mount(ExhaustComponent $record): void
    {
        $this->record = $record;
    }

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function getSubheading(): ?string
    {
        $jumlah = $this->record->children()->count();

        return $jumlah === 0
            ? 'Bagian ini belum berisi komponen.'
            : $jumlah.' komponen · bahan dan ukurannya diisi nanti di formula';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => ExhaustComponent::query()
                ->where('parent_id', $this->record->id))
            ->defaultSort('sort_order')
            ->paginated(false)
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label('Komponen')->searchable()->sortable()->weight('medium')
                    ->description(fn (ExhaustComponent $r) => $r->code),

                TextColumn::make('notes')
                    ->label('Catatan')->wrap()->color('gray')
                    ->placeholder('—'),

                TextColumn::make('sort_order')
                    ->label('Urutan')->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')->label('Aktif')->boolean()->toggleable(),
            ])
            ->recordUrl(fn (ExhaustComponent $record) => ExhaustComponentResource::getUrl('edit', ['record' => $record]))
            ->emptyStateHeading('Belum ada komponen di bagian ini')
            ->emptyStateDescription('Daftarkan bagian-bagiannya; bahan dan ukurannya menyusul di formula.')
            ->emptyStateIcon('heroicon-o-puzzle-piece');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('tambah')
                ->label('Tambah Komponen')
                ->icon('heroicon-o-plus')
                ->color('primary')
                // Bagiannya sudah pasti; jangan minta orang memilihnya lagi.
                ->url(fn () => ExhaustComponentResource::getUrl('create', ['bagian' => $this->record->id])),

            Action::make('kembali')
                ->label('Semua Bagian')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->url(fn () => ExhaustComponentResource::getUrl('index')),
        ];
    }
}
