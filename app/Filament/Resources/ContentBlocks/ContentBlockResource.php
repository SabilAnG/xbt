<?php

namespace App\Filament\Resources\ContentBlocks;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\ContentBlocks\Pages\EditContentBlock;
use App\Filament\Resources\ContentBlocks\Pages\ListContentBlocks;
use App\Filament\Resources\ContentBlocks\Schemas\ContentBlockForm;
use App\Filament\Resources\ContentBlocks\Tables\ContentBlocksTable;
use App\Models\ContentBlock;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ContentBlockResource extends BaseResource
{
    protected static ?string $model = ContentBlock::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?string $navigationLabel = 'Page content';

    protected static ?string $modelLabel = 'content block';

    protected static ?string $recordTitleAttribute = 'label';

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): ?string
    {
        return 'Website';
    }

    public static function form(Schema $schema): Schema
    {
        return ContentBlockForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContentBlocksTable::configure($table);
    }

    /**
     * Blocks are created by the page templates, not by hand — a row with no
     * matching content() call in a Blade file would never be rendered.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContentBlocks::route('/'),
            'edit' => EditContentBlock::route('/{record}/edit'),
        ];
    }
}
