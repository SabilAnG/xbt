<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, callable $get, callable $set) {
                                // Only fill the slug while it is still empty, so
                                // renaming a live product cannot silently break
                                // its URL.
                                if (blank($get('slug')) && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Used in the URL: /products/<slug>'),

                        TextInput::make('fitment')
                            ->label('Fitment line')
                            ->maxLength(255)
                            ->helperText('Shown under the title, e.g. "fit for DYNA 1986 - 2020+"')
                            ->columnSpanFull(),

                        TextInput::make('price')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->prefix('$'),

                        TextInput::make('sort_order')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower numbers come first on the products page.'),

                        Toggle::make('is_active')
                            ->label('Visible on the site')
                            ->default(true),
                    ]),

                Section::make('Description')
                    ->schema([
                        RichEditor::make('description')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->helperText('Note: saving here rewrites the HTML in the editor\'s own format, so it will no longer match the markup imported from the old site byte for byte.'),
                    ]),

                Section::make('Gallery')
                    ->schema([
                        Repeater::make('images')
                            ->relationship()
                            ->hiddenLabel()
                            ->orderColumn('sort_order')
                            ->reorderable()
                            ->addActionLabel('Add image')
                            ->schema([
                                FileUpload::make('path')
                                    ->label('Image')
                                    ->image()
                                    ->disk('site')
                                    ->directory('uploads/products')
                                    ->visibility('public')
                                    ->required(),
                            ])
                            ->columnSpanFull()
                            ->helperText('The first image is the one used on the products listing.'),
                    ]),
            ]);
    }
}
