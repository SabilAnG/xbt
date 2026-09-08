<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Loads the catalogue captured from the current live site
     * (database/data/products.json).
     *
     * Idempotent: re-running updates the existing rows by slug rather than
     * duplicating them.
     */
    public function run(): void
    {
        $file = database_path('data/products.json');

        if (! is_file($file)) {
            $this->command?->warn("Missing {$file}, nothing seeded.");

            return;
        }

        $rows = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        foreach ($rows as $row) {
            // Names and fitment lines are stored decoded; Blade escapes them on
            // output. The description keeps its markup and is rendered raw.
            $product = Product::updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'name' => html_entity_decode($row['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    'fitment' => html_entity_decode($row['fitment'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    'price' => $row['price'],
                    'description' => $row['description'] ?? null,
                    'is_active' => true,
                    'sort_order' => $row['sort'],
                ]
            );

            $product->images()->delete();

            foreach ($row['images'] as $i => $image) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'path' => $image['single'],
                    'thumb_path' => $image['thumb'] ?? null,
                    'sort_order' => $i,
                ]);
            }
        }

        $this->command?->info('Seeded '.count($rows).' products.');
    }
}
