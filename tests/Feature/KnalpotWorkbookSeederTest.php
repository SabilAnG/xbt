<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Material;
use App\Models\WorkerPayroll;
use App\Services\DashboardSummary;
use Database\Seeders\KnalpotWorkbookSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Impor buku kerja harus menghasilkan angka yang sama dengan sheet
 * "Ringkasan" di Excel — itulah acuan pemilik usaha saat membandingkan.
 */
class KnalpotWorkbookSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_headline_matches_the_workbook_summary(): void
    {
        $this->seed(KnalpotWorkbookSeeder::class);

        $headline = app(DashboardSummary::class)->headline();

        $this->assertEquals(53, $headline['total_stock']);
        $this->assertEquals(24_859_000, $headline['stock_value']);
        $this->assertSame(40, $headline['products_out']);
        $this->assertSame(10, $headline['products_low']);
        $this->assertSame(10, $headline['materials_alert']);
        $this->assertEquals(1_457_500, $headline['revenue']);
        $this->assertEquals(575_500, $headline['gross_profit']);
        $this->assertEquals(500, $headline['receivable']);
        $this->assertEquals(104_015_888, $headline['expenses']);
        $this->assertSame(12, $headline['needs_detail']);
        $this->assertEquals(0, $headline['host_unpaid']);
        $this->assertEquals(0, $headline['worker_unpaid']);
    }

    public function test_every_material_and_product_matches_the_workbook_stock_sheets(): void
    {
        $this->seed(KnalpotWorkbookSeeder::class);

        $expectedMaterials = [
            'Leheran / Bendingan Pipa' => 68, 'Tutup Silincer' => 39, 'Kerucut Silincer' => 0, 'Plat Stainless 1mm' => 10.3,
            'Plat Sarangan / Strimin' => 4.76, 'Tameng FD' => 0, 'Tameng XBT' => 0, 'PE Foam' => -0.08, 'Gas Argon' => 5.3,
            'Batu Potong / Flap Disk' => 0.2, 'Paku Rivet' => 0.92, 'Tameng FD/XBT (campur, belum dipilah)' => 37,
        ];

        foreach ($expectedMaterials as $name => $stock) {
            $this->assertEqualsWithDelta($stock, (float) Material::where('name', $name)->value('stock'), 0.001, $name);
        }

        $this->assertEquals(15, (float) Item::where('name', 'Mio Standar Racing Stainless Sarful 28/35 XBT')->value('stock'));
        $this->assertEquals(0, (float) Item::where('name', 'PCX Stainless Data Lama')->value('stock'));
    }

    public function test_monthly_summary_and_categories_follow_the_workbook_layout(): void
    {
        $this->seed(KnalpotWorkbookSeeder::class);
        $summary = app(DashboardSummary::class);

        $months = $summary->monthly();
        $this->assertCount(12, $months);
        $this->assertSame('2026-05', $months->first()['month']->format('Y-m'));
        $this->assertEquals(30_338_350, $months->first()['expenses']);
        $this->assertEquals(55, $months->firstWhere(fn ($m) => $m['month']->format('Y-m') === '2026-10')['produced']);

        $categories = $summary->expensesByCategory();
        $this->assertSame('Bahan & Perlengkapan', $categories->first()['name']);
        $this->assertEquals(58_219_588, $categories->first()['total']);
        $this->assertEquals(0, $categories->firstWhere('name', 'Aset')['total']);
    }

    public function test_hr_sheets_and_archive_are_imported(): void
    {
        $this->seed(KnalpotWorkbookSeeder::class);

        $this->assertDatabaseCount('hosts', 4);
        $this->assertDatabaseCount('workers', 2);
        $this->assertDatabaseCount('attendance_days', 104);
        $this->assertDatabaseCount('worker_payrolls', 38);
        $this->assertDatabaseCount('host_payrolls', 3);
        $this->assertDatabaseCount('procurement_histories', 237);
        $this->assertDatabaseHas('list_options', ['list' => 'model_motor', 'value' => 'Scoopy']);

        // Baris pertama Gaji Tukang: SIDIK 18–23 Mei = 2 hari × 150.000.
        $first = WorkerPayroll::with('worker')->orderBy('id')->first();
        $this->assertEquals(2, $first->daysPresent());
        $this->assertEquals(300_000, $first->amountDue());
    }

    public function test_material_stock_follows_purchases_minus_recipe_usage(): void
    {
        $this->seed(KnalpotWorkbookSeeder::class);

        // 122 set dibeli, 54 knalpot bermerek diproduksi × 1 set. "PCX Data Lama"
        // tanpa merek tidak memotong bahan — sama dengan wildcard di Excel.
        $this->assertEquals(68, (float) Material::where('name', 'Leheran / Bendingan Pipa')->value('stock'));
        $this->assertEquals(0, (float) Material::where('name', 'Baut L Stainless')->value('stock'));
        // Tameng FD: 25 dibeli + 1 dari penyesuaian − 26 knalpot merek FD.
        $this->assertEquals(0, (float) Material::where('name', 'Tameng FD')->value('stock'));
        $this->assertEquals(56, Item::count());
    }

    public function test_seeding_twice_does_not_duplicate_transactions(): void
    {
        $this->seed(KnalpotWorkbookSeeder::class);
        $this->seed(KnalpotWorkbookSeeder::class);

        $this->assertEquals(53, (float) Item::sum('stock'));
        $this->assertDatabaseCount('expenses', 193);
    }
}
