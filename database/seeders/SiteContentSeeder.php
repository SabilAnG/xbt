<?php

namespace Database\Seeders;

use App\Models\ContentBlock;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class SiteContentSeeder extends Seeder
{
    /**
     * Seeds the editable copy/imagery captured from the pages
     * (database/data/content-blocks.json) plus the global site settings.
     *
     * Idempotent, and deliberately non-destructive: a block that already exists
     * keeps whatever the admin has since typed into it. Only its label, page
     * and type are refreshed.
     */
    public function run(): void
    {
        $this->seedSettings();
        $this->seedBlocks();
    }

    private function seedSettings(): void
    {
        $defaults = [
            // key, value, type, group
            ['site.name', 'Hypersonic Speed Tech', 'text', 'identity'],
            ['site.tagline', 'Official Hypersonic Speed Tech', 'text', 'identity'],
            ['site.logo', 'assets/images/logo-white.png', 'image', 'identity'],
            ['site.favicon', 'favicon.ico', 'image', 'identity'],

            ['contact.email', 'hypersonicspeedtech@gmail.com', 'email', 'contact'],
            ['contact.phone', '62895337161221', 'phone', 'contact'],
            ['contact.address', '', 'text', 'contact'],

            ['whatsapp.primary', '62895337161221', 'phone', 'whatsapp'],
            ['whatsapp.sales1', '6281234567890', 'phone', 'whatsapp'],
            ['whatsapp.sales2', '6282345678901', 'phone', 'whatsapp'],
            ['whatsapp.sales3', '6283456789012', 'phone', 'whatsapp'],

            ['social.instagram', 'https://www.instagram.com/hypersonic_speedtech?igsh=ZGwyZDh0azVpcm00', 'url', 'social'],
            ['social.facebook', 'https://www.facebook.com/share/17kUNcDAyq/', 'url', 'social'],
            ['social.tiktok', '', 'url', 'social'],
            ['social.youtube', '', 'url', 'social'],
        ];

        $created = 0;
        foreach ($defaults as [$key, $value, $type, $group]) {
            $setting = Setting::firstOrNew(['key' => $key]);
            if (! $setting->exists) {
                $setting->value = $value;
                $created++;
            }
            $setting->type = $type;
            $setting->group = $group;
            $setting->save();
        }

        $this->command?->info("Settings: {$created} created, ".(count($defaults) - $created).' left untouched.');
    }

    private function seedBlocks(): void
    {
        $file = database_path('data/content-blocks.json');

        if (! is_file($file)) {
            $this->command?->warn("Missing {$file}, no content blocks seeded.");

            return;
        }

        $rows = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        $created = 0;
        foreach ($rows as $row) {
            $block = ContentBlock::firstOrNew(['key' => $row['key']]);
            if (! $block->exists) {
                $block->value = $row['value'];
                $created++;
            }
            $block->page = $row['page'];
            $block->label = $row['label'];
            $block->type = $row['type'];
            $block->sort_order = $row['sort_order'];
            $block->save();
        }

        $this->command?->info(
            'Content blocks: '.$created.' created, '.(count($rows) - $created).' left untouched.'
        );
    }
}
