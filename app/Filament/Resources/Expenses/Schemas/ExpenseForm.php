<?php

namespace App\Filament\Resources\Expenses\Schemas;

use App\Models\Expense;
use App\Services\DocumentNumber;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Pengeluaran')
                    ->columns(3)
                    ->schema([
                        TextInput::make('reference_number')
                            ->label('No. Bukti')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(fn () => DocumentNumber::next('expenses'))
                            ->disabled(fn (?Expense $record) => $record?->isPosted())
                            ->dehydrated(),

                        DatePicker::make('spent_at')
                            ->label('Tanggal')
                            ->required()
                            ->default(now())
                            ->disabled(fn (?Expense $record) => $record?->isPosted()),

                        TextInput::make('amount')
                            ->label('Jumlah')
                            ->numeric()->required()->minValue(0)->prefix('Rp')
                            ->disabled(fn (?Expense $record) => $record?->isPosted()),

                        Select::make('expense_category_id')
                            ->label('Kategori')
                            ->relationship('category', 'name')
                            ->searchable()->preload()->required()
                            ->helperText('Jenis pengeluaran mengikuti kategori yang dipilih.')
                            ->disabled(fn (?Expense $record) => $record?->isPosted()),

                        Select::make('wallet_id')
                            ->label('Dibayar dari dompet')
                            ->relationship('wallet', 'name')
                            ->searchable()->preload()
                            ->disabled(fn (?Expense $record) => $record?->isPosted()),

                        TextInput::make('paid_to')
                            ->label('Dibayarkan ke')
                            ->maxLength(255)
                            ->disabled(fn (?Expense $record) => $record?->isPosted()),

                        Textarea::make('description')
                            ->label('Keterangan')
                            ->rows(2)
                            ->columnSpanFull()
                            ->disabled(fn (?Expense $record) => $record?->isPosted()),
                    ]),
            ]);
    }
}
