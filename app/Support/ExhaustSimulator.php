<?php

namespace App\Support;

/**
 * Simulasi teoretis knalpot racing 4-tak (satu silinder, motor matic).
 *
 * Dipakai rumus tuning yang umum di dunia knalpot:
 * - Panjang leheran menentukan rpm tempat gelombang balik membantu
 *   pembilasan: L(inci) = 850 × ED / rpm − 3, dengan ED = 180° + bukaan
 *   klep buang sebelum TMB (≈ 55° untuk mesin matic standar).
 * - Diameter dalam ideal leheran: D(inci) = √(cc / ((L + 3) × 25)) × 2,1.
 * - Inti (core) silincer yang lebih kecil dari leheran menahan aliran;
 *   volume silincer kecil = bising & turbulen.
 *
 * Hasilnya estimasi, bukan hasil dyno: CVT, injeksi/karburator, filter udara
 * dan kondisi mesin ikut menentukan.
 */
class ExhaustSimulator
{
    /** Data motor standar (tenaga pabrik, PS @ rpm). */
    public const MOTORS = [
        'mio' => ['label' => 'Yamaha Mio (Sporty/Smile) 113 cc', 'cc' => 113.7, 'hp' => 8.9, 'rpm' => 8000],
        'mio-m3' => ['label' => 'Yamaha Mio M3 / S 125 cc', 'cc' => 124.9, 'hp' => 9.5, 'rpm' => 8000],
        'beat' => ['label' => 'Honda Beat / Scoopy 110 cc', 'cc' => 109.5, 'hp' => 9.0, 'rpm' => 7500],
        'vario-125' => ['label' => 'Honda Vario 125', 'cc' => 124.8, 'hp' => 11.1, 'rpm' => 8500],
        'vario-160' => ['label' => 'Honda Vario 160', 'cc' => 156.9, 'hp' => 15.4, 'rpm' => 8500],
        'pcx-160' => ['label' => 'Honda PCX 160', 'cc' => 156.9, 'hp' => 16.0, 'rpm' => 8500],
        'nmax' => ['label' => 'Yamaha NMAX 155', 'cc' => 155.1, 'hp' => 15.4, 'rpm' => 8000],
        'aerox' => ['label' => 'Yamaha Aerox 155', 'cc' => 155.1, 'hp' => 15.4, 'rpm' => 8000],
        'custom' => ['label' => 'Motor lain (isi sendiri)', 'cc' => 125.0, 'hp' => 10.0, 'rpm' => 8000],
    ];

    public const STYLES = [
        'gp' => 'Racing GP (silincer trapesium)',
        'round' => 'Bulat klasik',
        'oval' => 'Oval',
        'hexagon' => 'Hexagon',
        'megaphone' => 'Megaphone / drag',
    ];

    public const TIPS = ['straight' => 'Lurus', 'slash' => 'Potong miring', 'double' => 'Dobel', 'cap' => 'Tutup karbon'];

    public const FINISHES = ['satin' => 'Titanium satin (doff)', 'stainless' => 'Stainless poles', 'burnt' => 'Titanium bakar (biru-ungu)', 'black' => 'Hitam doff', 'carbon' => 'Karbon'];

    /** Bukaan klep buang sebelum TMB (derajat) — asumsi mesin matic standar. */
    private const EXHAUST_OPEN = 55;

    /** Batas kebisingan motor 80–175 cc (Permen LHK P.56/2019). */
    public const NOISE_LIMIT = 80;

    /** @return array<string, mixed> */
    public static function defaults(string $motor = 'mio'): array
    {
        return self::autoDesign($motor, 'gp');
    }

    /**
     * Desain awal yang langsung pas untuk motor & gaya ini: leheran disetel ke
     * rpm tenaga puncak, silincer ±11× isi silinder.
     *
     * @return array<string, mixed>
     */
    public static function autoDesign(string $motor, string $style, ?int $targetRpm = null, array $custom = []): array
    {
        $engine = self::engine(['motor' => $motor] + $custom);
        $target = $targetRpm ?: $engine['rpm'];
        $ideal = self::ideal($engine['cc'], $target);
        $headerId = round($ideal['diameter'] + 2);       // sedikit lebih besar dari teori untuk knalpot racing
        $volumeCc = $engine['cc'] * 11;

        [$length, $width, $height] = match ($style) {
            'megaphone' => [300, 100, 100],
            'oval' => [320, 100, 72],
            'hexagon' => [300, 90, 90],
            'round' => [320, 86, 86],
            default => [300, 104, 78],
        };
        // Sesuaikan panjang supaya volume ≈ target.
        $area = self::crossSection($style, $width, $height);
        $length = (int) round(max(280, min(460, $volumeCc * 1000 / max($area, 1))) / 5) * 5;   // minimal 280 mm: proporsi knalpot racing

        return [
            'motor' => array_key_exists($motor, self::MOTORS) ? $motor : 'custom',
            'custom_cc' => $engine['cc'],
            'custom_hp' => $engine['hp'],
            'custom_rpm' => $engine['rpm'],
            'target_rpm' => $target,
            'header' => [
                'diameter' => (int) $headerId,
                'length' => (int) (round($ideal['length'] / 5) * 5),
                'wall' => 1.2,
                'bend' => 'racing',
                'finish' => $style === 'megaphone' ? 'burnt' : 'stainless',
            ],
            'silencer' => [
                'shape' => array_key_exists($style, self::STYLES) ? $style : 'gp',
                'length' => $length,
                'width' => $width,
                'height' => in_array($style, ['round', 'hexagon', 'megaphone'], true) ? $width : $height,
                'core' => (int) max($headerId + 2, 26),
                'inlet_cone' => 60,
                'outlet_cone' => $style === 'megaphone' ? 70 : 35,
                'packing' => true,
                'tip' => $style === 'megaphone' ? 'straight' : ($style === 'gp' ? 'slash' : 'cap'),
                'tip_diameter' => (int) max($headerId + 10, 35),
                'angle' => $style === 'gp' ? 14 : 8,
                'finish' => match ($style) {
                    'megaphone' => 'black', 'gp', 'hexagon' => 'satin', default => 'stainless'
                },
            ],
            'mockup' => self::MOCKUP_DEFAULT,
        ];
    }

    /**
     * Posisi knalpot di foto motor (mode "Di motor"): x/y = titik tengah
     * knalpot relatif terhadap foto (0–1), scale = lebar knalpot ÷ lebar foto.
     */
    public const MOCKUP_DEFAULT = ['photo' => 0, 'x' => 0.62, 'y' => 0.72, 'scale' => 0.42, 'rotation' => 0, 'flip' => false, 'brightness' => 1.0, 'body_color' => '#f2c200'];

    /**
     * Rapikan & batasi parameter dari form/JSON supaya hitungan selalu wajar.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function normalize(array $params): array
    {
        $base = self::defaults((string) ($params['motor'] ?? 'mio'));
        $num = fn ($value, $default, $min, $max) => is_numeric($value) ? max($min, min($max, (float) $value)) : $default;
        $pick = fn ($value, array $allowed, $default) => array_key_exists((string) $value, $allowed) ? (string) $value : $default;
        $h = (array) ($params['header'] ?? []);
        $s = (array) ($params['silencer'] ?? []);
        $m = (array) ($params['mockup'] ?? []);
        $mock = self::MOCKUP_DEFAULT;

        return [
            'motor' => $pick($params['motor'] ?? null, self::MOTORS, 'mio'),
            'custom_cc' => $num($params['custom_cc'] ?? null, $base['custom_cc'], 50, 1000),
            'custom_hp' => $num($params['custom_hp'] ?? null, $base['custom_hp'], 2, 200),
            'custom_rpm' => (int) $num($params['custom_rpm'] ?? null, $base['custom_rpm'], 4000, 14000),
            'target_rpm' => (int) $num($params['target_rpm'] ?? null, $base['target_rpm'], 4000, 14000),
            'header' => [
                'diameter' => $num($h['diameter'] ?? null, $base['header']['diameter'], 16, 60),
                'length' => $num($h['length'] ?? null, $base['header']['length'], 250, 1200),
                'wall' => $num($h['wall'] ?? null, 1.2, 0.8, 3),
                'bend' => $pick($h['bend'] ?? null, ['racing' => 1, 'low' => 1, 'upswept' => 1], 'racing'),
                'finish' => $pick($h['finish'] ?? null, self::FINISHES, $base['header']['finish']),
            ],
            'silencer' => [
                'shape' => $pick($s['shape'] ?? null, self::STYLES, $base['silencer']['shape']),
                'length' => $num($s['length'] ?? null, $base['silencer']['length'], 150, 600),
                'width' => $num($s['width'] ?? null, $base['silencer']['width'], 50, 180),
                'height' => $num($s['height'] ?? null, $base['silencer']['height'], 40, 180),
                'core' => $num($s['core'] ?? null, $base['silencer']['core'], 16, 70),
                'inlet_cone' => $num($s['inlet_cone'] ?? null, 60, 0, 200),
                'outlet_cone' => $num($s['outlet_cone'] ?? null, 35, 0, 200),
                'packing' => filter_var($s['packing'] ?? true, FILTER_VALIDATE_BOOL),
                'tip' => $pick($s['tip'] ?? null, self::TIPS, $base['silencer']['tip']),
                'tip_diameter' => $num($s['tip_diameter'] ?? null, $base['silencer']['tip_diameter'], 20, 90),
                'angle' => $num($s['angle'] ?? null, $base['silencer']['angle'], -10, 35),
                'finish' => $pick($s['finish'] ?? null, self::FINISHES, $base['silencer']['finish']),
            ],
            'mockup' => [
                'photo' => (int) $num($m['photo'] ?? null, $mock['photo'], 0, 20),
                'x' => $num($m['x'] ?? null, $mock['x'], -0.5, 1.5),
                'y' => $num($m['y'] ?? null, $mock['y'], -0.5, 1.5),
                'scale' => $num($m['scale'] ?? null, $mock['scale'], 0.05, 3),
                'rotation' => $num($m['rotation'] ?? null, $mock['rotation'], -60, 60),
                'flip' => filter_var($m['flip'] ?? $mock['flip'], FILTER_VALIDATE_BOOL),
                'brightness' => $num($m['brightness'] ?? null, $mock['brightness'], 0.3, 2),
                'body_color' => preg_match('/^#[0-9a-f]{6}$/i', (string) ($m['body_color'] ?? '')) ? strtolower($m['body_color']) : $mock['body_color'],
            ],
            // Desain yang ditampilkan: model parametrik, atau knalpot dari foto referensi (urutan ke-index).
            'source' => ($params['source']['type'] ?? null) === 'reference'
                ? ['type' => 'reference', 'index' => (int) $num($params['source']['index'] ?? null, 0, 0, 20), 'share' => $num($params['source']['share'] ?? null, 0.6, 0.1, 1)]
                : ['type' => 'parametric'],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function simulate(array $params): array
    {
        $p = self::normalize($params);
        $engine = self::engine($p);
        $target = $p['target_rpm'];
        $ideal = self::ideal($engine['cc'], $target);
        $ed = 180 + self::EXHAUST_OPEN;

        $lengthIn = $p['header']['length'] / 25.4;
        $tunedRpm = (int) round(850 * $ed / ($lengthIn + 3));
        $diameter = $p['header']['diameter'];

        // Kecocokan tiap bagian (0–1).
        $fitLength = exp(-(($tunedRpm - $target) / 1800) ** 2);
        $fitDiameter = exp(-((($diameter - $ideal['diameter']) / $ideal['diameter']) / 0.22) ** 2);
        $flowRatio = ($p['silencer']['core'] / $diameter) ** 2;
        $fitFlow = $flowRatio >= 1 ? 1.0 : max(0.0, $flowRatio) ** 0.8;
        $volumeCc = self::crossSection($p['silencer']['shape'], $p['silencer']['width'], $p['silencer']['height']) * $p['silencer']['length'] / 1000;
        $volumeRatio = $volumeCc / $engine['cc'];
        $fitVolume = min(1.0, 0.72 + 0.28 * min($volumeRatio / 10, 1));
        $quality = $fitLength * $fitDiameter * $fitFlow * $fitVolume;

        // Kurva tenaga: standar vs dengan knalpot ini.
        $gainAtTune = (-4 + 14 * $quality) / 100;   // −4% (lebih buruk dari standar) s/d +10%
        $curve = [];
        $peakHp = 0;
        $peakRpm = $engine['rpm'];
        for ($rpm = 3000; $rpm <= max(10000, $engine['rpm'] + 2000); $rpm += 250) {
            $stock = self::stockPower($engine, $rpm);
            $boost = $gainAtTune * exp(-(($rpm - $tunedRpm) / 2600) ** 2);
            $modified = $stock * (1 + $boost);
            $curve[] = ['rpm' => $rpm, 'stock' => round($stock, 2), 'modified' => round($modified, 2)];
            if ($modified > $peakHp) {
                [$peakHp, $peakRpm] = [$modified, $rpm];
            }
        }
        $gainHp = $peakHp - $engine['hp'];
        $gainPct = $gainHp / $engine['hp'] * 100;

        $noise = 98 - 10 * log10(max($volumeRatio, 1)) - ($p['silencer']['packing'] ? 4 : 0) + 6 * (min($flowRatio, 2) - 1);

        return [
            'params' => $p,
            'engine' => $engine,
            'ideal' => [
                'header_length' => (int) round($ideal['length']),
                'header_diameter' => round($ideal['diameter'], 1),
                'silencer_volume' => round($engine['cc'] * 11 / 1000, 2),
                'core' => (int) ceil($diameter),
            ],
            'tuned_rpm' => $tunedRpm,
            'band' => match (true) {
                $tunedRpm < $engine['rpm'] - 700 => 'Bawah–tengah (akselerasi)',
                $tunedRpm > $engine['rpm'] + 700 => 'Atas (top speed)',
                default => 'Tengah–atas (seimbang)',
            },
            'fit' => [
                'length' => (int) round($fitLength * 100),
                'diameter' => (int) round($fitDiameter * 100),
                'flow' => (int) round($fitFlow * 100),
                'volume' => (int) round($fitVolume * 100),
                'overall' => (int) round($quality * 100),
            ],
            'silencer_volume' => round($volumeCc / 1000, 2),
            'volume_ratio' => round($volumeRatio, 1),
            'stock_hp' => $engine['hp'],
            'peak_hp' => round($peakHp, 2),
            'peak_rpm' => $peakRpm,
            'gain_hp' => round($gainHp, 2),
            'gain_pct' => round($gainPct, 1),
            'gain_range' => [round($gainHp - $engine['hp'] * 0.015, 2), round($gainHp + $engine['hp'] * 0.015, 2)],
            'noise_db' => (int) round($noise),
            'noise_legal' => $noise <= self::NOISE_LIMIT,
            'advice' => self::advice($p, $ideal, $tunedRpm, $target, $flowRatio, $volumeRatio, $noise),
            'curve' => $curve,
        ];
    }

    /** @return array{cc: float, hp: float, rpm: int, label: string} */
    private static function engine(array $p): array
    {
        $key = array_key_exists((string) ($p['motor'] ?? ''), self::MOTORS) ? $p['motor'] : 'mio';
        $motor = self::MOTORS[$key];

        if ($key === 'custom') {
            $motor['cc'] = (float) ($p['custom_cc'] ?? $motor['cc']);
            $motor['hp'] = (float) ($p['custom_hp'] ?? $motor['hp']);
            $motor['rpm'] = (int) ($p['custom_rpm'] ?? $motor['rpm']);
        }

        return ['cc' => (float) $motor['cc'], 'hp' => (float) $motor['hp'], 'rpm' => (int) $motor['rpm'], 'label' => $motor['label']];
    }

    /** @return array{length: float, diameter: float} panjang & diameter dalam ideal (mm) */
    private static function ideal(float $cc, int $rpm): array
    {
        $lengthIn = 850 * (180 + self::EXHAUST_OPEN) / $rpm - 3;
        $diameterIn = sqrt($cc / (($lengthIn + 3) * 25)) * 2.1;

        return ['length' => $lengthIn * 25.4, 'diameter' => $diameterIn * 25.4];
    }

    /** Luas penampang silincer (mm²). */
    private static function crossSection(string $shape, float $width, float $height): float
    {
        return match ($shape) {
            'round', 'megaphone' => M_PI * ($width / 2) ** 2,
            'hexagon' => 3 * sqrt(3) / 2 * ($width / 2) ** 2,
            'oval' => M_PI * ($width / 2) * ($height / 2),
            default => ($width + $width * 0.72) / 2 * $height,   // trapesium GP
        };
    }

    /** Kurva tenaga standar (PS) berpuncak di rpm pabrik. */
    private static function stockPower(array $engine, int $rpm): float
    {
        $x = $rpm / $engine['rpm'];
        $a = 2.2;

        return $engine['hp'] * $x ** $a * exp($a * (1 - $x));
    }

    /** @return list<string> */
    private static function advice(array $p, array $ideal, int $tunedRpm, int $target, float $flowRatio, float $volumeRatio, float $noise): array
    {
        $tips = [];
        $deltaLength = $ideal['length'] - $p['header']['length'];
        if (abs($deltaLength) > 30) {
            $tips[] = ($deltaLength > 0 ? 'Perpanjang' : 'Perpendek').' leheran ±'.(int) round(abs($deltaLength) / 5) * 5
                .' mm supaya dorongan tenaga jatuh di '.number_format($target, 0, ',', '.').' rpm (sekarang '.number_format($tunedRpm, 0, ',', '.').' rpm).';
        }
        $deltaDiameter = $p['header']['diameter'] - $ideal['diameter'];
        if ($deltaDiameter > 5) {
            $tips[] = 'Diameter leheran terlalu besar untuk mesin ini: tenaga bawah bisa hilang. Coba '.(int) round($ideal['diameter'] + 2).' mm.';
        } elseif ($deltaDiameter < -2) {
            $tips[] = 'Diameter leheran terlalu kecil: putaran atas tertahan. Coba '.(int) round($ideal['diameter'] + 2).' mm.';
        }
        if ($flowRatio < 1) {
            $tips[] = 'Core silincer lebih kecil dari leheran (menahan aliran). Naikkan core ke ≥ '.(int) ceil($p['header']['diameter']).' mm.';
        }
        if ($volumeRatio < 8) {
            $tips[] = 'Silincer kecil ('.round($volumeRatio, 1).'× isi silinder): bising & tenaga atas kurang. Idealnya 10–14×.';
        }
        if ($noise > self::NOISE_LIMIT) {
            $tips[] = 'Perkiraan suara '.(int) round($noise).' dB melewati batas '.self::NOISE_LIMIT.' dB: tambah peredam (glasswool), perpanjang silincer, atau pakai db-killer.';
        }

        return $tips ?: ['Ukuran sudah seimbang untuk target rpm ini.'];
    }
}
