<?php

namespace Tests\Unit;

use App\Services\CuttingPlanner;
use PHPUnit\Framework\TestCase;

class CuttingPlannerTest extends TestCase
{
    public function test_longest_cut_goes_first_into_the_shortest_piece_that_fits(): void
    {
        $plan = (new CuttingPlanner)->plan(
            cuts: [['length' => 10, 'label' => 'P2'], ['length' => 30, 'label' => 'P1']],
            pieces: [
                ['key' => 1, 'code' => 'BATANG', 'length' => 600, 'offcut' => false],
                ['key' => 2, 'code' => 'SISA-35', 'length' => 35, 'offcut' => true],
                ['key' => 3, 'code' => 'SISA-12', 'length' => 12, 'offcut' => true],
            ],
            kerf: 0.3,
        );

        $this->assertSame(['P1', 'P2'], array_column($plan['assignments'], 'label'));
        // P1 30 cm muat di sisa 35 cm; P2 10 cm muat di sisa 12 cm — batang utuh tidak disentuh.
        $this->assertSame([2, 3], array_column($plan['assignments'], 'key'));
        $this->assertEqualsWithDelta(4.7, $plan['assignments'][0]['after'], 0.001);
        $this->assertEqualsWithDelta(1.7, $plan['assignments'][1]['after'], 0.001);
        $this->assertSame(0, $plan['new_bars']);
    }

    public function test_an_exact_fit_uses_the_whole_piece_without_kerf(): void
    {
        $plan = (new CuttingPlanner)->plan([['length' => 20, 'label' => 'P1']], [['key' => 1, 'code' => 'A', 'length' => 20, 'offcut' => true]], kerf: 0.3);

        $this->assertSame(0.0, $plan['assignments'][0]['after']);
        $this->assertSame(0.0, $plan['assignments'][0]['kerf']);
    }

    public function test_new_bars_are_opened_only_when_allowed_and_overlong_cuts_are_reported(): void
    {
        $cuts = [['length' => 400, 'label' => 'A'], ['length' => 400, 'label' => 'B'], ['length' => 700, 'label' => 'TERLALU PANJANG']];

        $withoutBars = (new CuttingPlanner)->plan($cuts, [], kerf: 0);
        $this->assertCount(3, $withoutBars['unplaced']);

        $withBars = (new CuttingPlanner)->plan($cuts, [], kerf: 0, barLength: 600);
        $this->assertSame(2, $withBars['new_bars']);
        $this->assertSame(['TERLALU PANJANG'], array_column($withBars['unplaced'], 'label'));
    }
}
