<?php

namespace App\Filament\Resources\ExpenseCategories\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ExpenseCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Select::make('expense_type_id')
                        ->label('Jenis Pengeluaran')
                        ->relationship('type', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText('Mis. kategori "Bayar Las" jenisnya "Jasa".')
                        ->createOptionForm([
                            TextInput::make('name')->label('Nama Jenis')->required(),
                            TextInput::make('slug')->required()
                                ->default(fn (callable $get) => Str::slug((string) $get('name'))),
                        ]),

                    TextInput::make('name')
                        ->label('Kategori Pengeluaran')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Contoh: Bayar Las, Listrik, Gaji Karyawan.')
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, callable $get, callable $set) {
                            if (blank($get('slug')) && filled($state)) {
                                $set('slug', Str::slug($state));
                            }
                        }),

                    TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    Toggle::make('is_active')->label('Aktif')->default(true),

                    Textarea::make('description')->label('Keterangan')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }
}
