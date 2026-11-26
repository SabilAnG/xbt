<?php

namespace App\Services;

/**
 * Menentukan dari potongan pipa mana setiap kebutuhan potong diambil.
 *
 * Cara kerjanya seperti tukang di bengkel: potongan terpanjang dikerjakan
 * dulu, lalu dicarikan pipa yang PALING PENDEK tapi masih cukup (best fit).
 * Dengan begitu sisa potongan lama terpakai lebih dulu dan batang utuh baru
 * dibuka hanya bila memang tidak ada sisa yang cukup panjang. Bila panjangnya
 * sama, potongan dari Gudang Stok Sisa didahulukan.
 *
 * Setiap potong memakan lebar mata gergaji (kerf), kecuali bila potongan itu
 * pas habis dipakai.
 */
class CuttingPlanner
{
    /**
     * @param  list<array{length: float, label: string}>  $cuts
     * @param  list<array{key: int|string, code: string, length: float, offcut: bool}>  $pieces
     * @param  float  $barLength  panjang batang baru bila boleh membuka batang baru (0 = tidak boleh)
     * @return array{
     *     assignments: list<array{label: string, length: float, kerf: float, key: int|string, code: string, before: float, after: float, new_bar: bool}>,
     *     pieces: list<array{key: int|string, code: string, length: float, offcut: bool, new_bar?: bool}>,
     *     new_bars: int,
     *     unplaced: list<array{length: float, label: string}>
     * }
     */
    public function plan(array $cuts, array $pieces, float $kerf, float $barLength = 0): array
    {
        usort($cuts, fn (array $a, array $b) => $b['length'] <=> $a['length']);

        $assignments = [];
        $unplaced = [];
        $newBars = 0;

        foreach ($cuts as $cut) {
            $length = round((float) $cut['length'], 2);
            $index = $this->bestFit($pieces, $length);

            if ($index === null && $barLength >= $length && $length > 0) {
                $newBars++;
                $pieces[] = ['key' => 'baru-'.$newBars, 'code' => 'Batang baru #'.$newBars, 'length' => $barLength, 'offcut' => false, 'new_bar' => true];
                $index = array_key_last($pieces);
            }

            if ($index === null) {
                $unplaced[] = $cut;

                continue;
            }

            $before = round((float) $pieces[$index]['length'], 2);
            $used = min($before, $length + $kerf);
            $after = round($before - $used, 2);
            $pieces[$index]['length'] = $after;
            $pieces[$index]['offcut'] = true;

            $assignments[] = [
                'label' => $cut['label'],
                'length' => $length,
                'kerf' => round($used - $length, 2),
                'key' => $pieces[$index]['key'],
                'code' => $pieces[$index]['code'],
                'before' => $before,
                'after' => $after,
                'new_bar' => (bool) ($pieces[$index]['new_bar'] ?? false),
            ];
        }

        return ['assignments' => $assignments, 'pieces' => array_values($pieces), 'new_bars' => $newBars, 'unplaced' => $unplaced];
    }

    /**
     * @param  list<array{length: float, offcut: bool}>  $pieces
     */
    private function bestFit(array $pieces, float $length): ?int
    {
        $best = null;

        foreach ($pieces as $index => $piece) {
            if (round((float) $piece['length'], 2) < $length) {
                continue;
            }

            if ($best === null) {
                $best = $index;

                continue;
            }

            $current = $pieces[$best];
            $shorter = (float) $piece['length'] < (float) $current['length'];
            $sameButOffcut = (float) $piece['length'] === (float) $current['length'] && $piece['offcut'] && ! $current['offcut'];

            if ($shorter || $sameButOffcut) {
                $best = $index;
            }
        }

        return $best;
    }
}
