<?php

namespace App\Filament\Resources\Wallets\Schemas;

use App\Models\Wallet;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WalletForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dompet')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->label('Nama')->required()->maxLength(255),

                        Select::make('type')->label('Tipe')
                            ->options(Wallet::TYPES)->default('cash')->required(),

                        TextInput::make('account_number')->label('No. Rekening')->maxLength(64),
                        TextInput::make('holder_name')->label('Atas Nama')->maxLength(255),

                        TextInput::make('opening_balance')
                            ->label('Saldo Awal')
                            ->numeric()->default(0)->prefix('Rp')
                            ->helperText('Saldo sebelum transaksi apa pun dicatat di sistem ini.'),

                        TextInput::make('current_balance')
                            ->label('Saldo Berjalan')
                            ->numeric()->prefix('Rp')
                            ->disabled()->dehydrated(false)
                            ->helperText('Dihitung dari mutasi — tidak bisa diketik manual.'),

                        Toggle::make('is_active')->label('Aktif')->default(true),
                    ]),
            ]);
    }
}
