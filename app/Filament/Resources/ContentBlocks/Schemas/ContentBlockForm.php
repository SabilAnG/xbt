<?php

namespace App\Filament\Resources\ContentBlocks\Schemas;

use App\Models\ContentBlock;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContentBlockForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Where this appears')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextInput::make('key')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Identifier used by the page template — not editable.'),

                        Select::make('page')
                            ->options(ContentBlock::PAGES)
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('label')
                            ->label('Description')
                            ->columnSpanFull(),
                    ]),

                Section::make('Content')
                    ->schema([
                        Textarea::make('value')
                            ->label('Text')
                            ->rows(4)
                            ->columnSpanFull()
                            ->visible(fn ($get) => $get('type') === 'text'),

                        Textarea::make('value')
                            ->label('Text (HTML allowed)')
                            ->rows(4)
                            ->columnSpanFull()
                            ->helperText('This block is printed without escaping, so & and quotes survive as typed. HTML tags here will render as markup.')
                            ->visible(fn ($get) => $get('type') === 'html'),

                        FileUpload::make('value')
                            ->label('Image')
                            ->image()
                            ->disk('site')
                            ->directory('uploads/content')
                            ->visibility('public')
                            ->columnSpanFull()
                            ->visible(fn ($get) => $get('type') === 'image'),
                    ]),
            ]);
    }
}
