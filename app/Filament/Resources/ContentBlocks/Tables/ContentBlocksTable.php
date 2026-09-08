<?php

namespace App\Filament\Resources\ContentBlocks\Tables;

use App\Models\ContentBlock;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContentBlocksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->defaultGroup('page')
            ->paginated([25, 50, 100, 'all'])
            ->columns([
                ImageColumn::make('value')
                    ->label('')
                    ->disk('site')
                    ->square()
                    ->visible(fn () => true)
                    ->getStateUsing(fn (ContentBlock $r) => $r->type === 'image' ? $r->value : null),

                TextColumn::make('label')
                    ->label('Block')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('value')
                    ->label('Current content')
                    ->limit(70)
                    ->wrap()
                    ->searchable()
                    ->getStateUsing(fn (ContentBlock $r) => $r->type === 'image' ? basename((string) $r->value) : $r->value),

                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'image' => 'info',
                        'html' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('page')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ContentBlock::PAGES[$state] ?? $state)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('page')
                    ->options(ContentBlock::PAGES)
                    ->label('Page'),

                SelectFilter::make('type')
                    ->options([
                        'text' => 'Text',
                        'html' => 'Text (raw)',
                        'image' => 'Image',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
