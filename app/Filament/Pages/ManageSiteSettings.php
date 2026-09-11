<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\HakPartner;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageSiteSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Site settings';

    protected static ?string $title = 'Site settings';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.manage-site-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    /** Disaring sama seperti resource: hak partner menentukan. */
    public static function canAccess(): bool
    {
        return HakPartner::boleh(static::class, 'Website');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Website';
    }

    public function mount(): void
    {
        // Keys are stored dotted; Filament would read "site.name" as a nested
        // path, so swap dots for underscores while the form holds them.
        $values = [];
        foreach (Setting::query()->get() as $setting) {
            $values[str_replace('.', '_', $setting->key)] = $setting->value;
        }

        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identity')
                    ->columns(2)
                    ->schema([
                        TextInput::make('site_name')
                            ->label('Website name')
                            ->required(),

                        TextInput::make('site_tagline')
                            ->label('Tagline'),

                        FileUpload::make('site_logo')
                            ->label('Logo (latar gelap)')
                            ->image()
                            ->disk('site')
                            ->directory('uploads/site')
                            ->visibility('public')
                            ->helperText('Dipakai header situs dan panel admin mode gelap. Tulisannya putih.'),

                        // Logo dengan tulisan putih hilang begitu latarnya
                        // terang, jadi mode terang butuh berkasnya sendiri.
                        FileUpload::make('site_logo_light')
                            ->label('Logo (latar terang)')
                            ->image()
                            ->disk('site')
                            ->directory('uploads/site')
                            ->visibility('public')
                            ->helperText('Dipakai panel admin mode terang. Tulisannya gelap.'),

                        FileUpload::make('site_favicon')
                            ->label('Favicon')
                            ->disk('site')
                            ->directory('uploads/site')
                            ->visibility('public'),
                    ]),

                Section::make('Contact')
                    ->columns(2)
                    ->schema([
                        TextInput::make('contact_email')->label('Email')->email(),
                        TextInput::make('contact_phone')->label('Phone')->tel(),
                        TextInput::make('contact_address')->label('Address')->columnSpanFull(),
                    ]),

                Section::make('WhatsApp')
                    ->columns(2)
                    ->description('Numbers in international format without "+", e.g. 62895337161221.')
                    ->schema([
                        TextInput::make('whatsapp_primary')->label('Main number')->tel(),
                        TextInput::make('whatsapp_sales1')->label('Sales desk 1 (Admin 1)')->tel(),
                        TextInput::make('whatsapp_sales2')->label('Sales desk 2 (Admin 2)')->tel(),
                        TextInput::make('whatsapp_sales3')->label('Sales desk 3')->tel(),
                    ]),

                Section::make('Social links')
                    ->columns(2)
                    ->schema([
                        TextInput::make('social_instagram')->label('Instagram')->url(),
                        TextInput::make('social_facebook')->label('Facebook')->url(),
                        TextInput::make('social_tiktok')->label('TikTok')->url(),
                        TextInput::make('social_youtube')->label('YouTube')->url(),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save changes')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $field => $value) {
            Setting::put($this->unmangle($field), $value);
        }

        Notification::make()
            ->success()
            ->title('Settings saved')
            ->send();
    }

    /**
     * "whatsapp_sales1" is a single key, not "whatsapp.sales1" split further —
     * only the first underscore separates group from name.
     */
    private function unmangle(string $field): string
    {
        return preg_replace('/_/', '.', $field, 1);
    }
}
