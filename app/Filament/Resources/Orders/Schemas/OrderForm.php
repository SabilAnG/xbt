<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order')
                    ->columns(2)
                    ->schema([
                        TextInput::make('order_number')
                            ->required()
                            ->maxLength(64)
                            ->unique(ignoreRecord: true)
                            ->helperText('What the customer types into the tracking form.'),

                        Select::make('status')
                            ->options(Order::STATUSES)
                            ->default('processing')
                            ->required(),

                        TextInput::make('customer_name')->maxLength(255),
                        TextInput::make('customer_email')->email()->maxLength(255),
                        TextInput::make('customer_phone')->tel()->maxLength(64),

                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText('Internal only — never shown on the tracking page.'),
                    ]),

                Section::make('Shipments')
                    ->description('Each shipment becomes a tab on the public tracking page.')
                    ->schema([
                        Repeater::make('shipments')
                            ->relationship()
                            ->hiddenLabel()
                            ->orderColumn('sort_order')
                            ->addActionLabel('Add shipment')
                            ->itemLabel(fn (array $state) => $state['tracking_number'] ?? 'New shipment')
                            ->collapsed()
                            ->columns(2)
                            ->schema([
                                TextInput::make('tracking_number')->required()->maxLength(128),
                                TextInput::make('provider')
                                    ->maxLength(128)
                                    ->helperText('Courier name, e.g. DHL, JNE.'),

                                TextInput::make('status')
                                    ->required()
                                    ->default('in_transit')
                                    ->helperText('Shown lower-cased with underscores turned into spaces.'),

                                TextInput::make('status_description')->maxLength(255),

                                DateTimePicker::make('status_updated_at')->seconds(false),
                                DateTimePicker::make('last_checked')->seconds(false),

                                Repeater::make('events')
                                    ->relationship()
                                    ->label('Tracking history')
                                    ->addActionLabel('Add event')
                                    ->itemLabel(fn (array $state) => $state['status'] ?? 'New event')
                                    ->collapsed()
                                    ->columns(2)
                                    ->columnSpanFull()
                                    ->schema([
                                        TextInput::make('status')->maxLength(128),
                                        DateTimePicker::make('happened_at')->seconds(false),
                                        TextInput::make('location')
                                            ->maxLength(255)
                                            ->helperText('City shown after the bullet in the timeline.'),
                                        TextInput::make('description')->maxLength(255),
                                    ])
                                    // The page's progress bar fills at events/5.
                                    ->helperText('Newest first on the site. Five events fill the progress bar.'),
                            ]),
                    ]),
            ]);
    }
}
