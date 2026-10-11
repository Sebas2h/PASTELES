<?php
namespace App\Helpers;

final class WheelConfig
{
    private const DEFAULTS = [
        'wheel_first_spin_cost' => '150',
        'wheel_respin_cost' => '10',
        'wheel_prize_probabilities' => '{"muffin":62.5,"five":30,"ten":5,"twenty":2.5}',
    ];

    public static function current(): array
    {
        foreach (self::DEFAULTS as $key => $value) {
            if (Settings::get($key) === null) Settings::set($key, $value);
        }

        $probabilities = json_decode((string)Settings::get('wheel_prize_probabilities', self::DEFAULTS['wheel_prize_probabilities']), true);
        if (!is_array($probabilities)) $probabilities = json_decode(self::DEFAULTS['wheel_prize_probabilities'], true);
        $defaults = ['muffin' => 62.5, 'five' => 30.0, 'ten' => 5.0, 'twenty' => 2.5];
        $probabilities = array_replace($defaults, array_intersect_key($probabilities, $defaults));

        return [
            'first_cost' => max(1, (int)Settings::get('wheel_first_spin_cost', '150')),
            'respin_cost' => max(1, (int)Settings::get('wheel_respin_cost', '10')),
            'probabilities' => $probabilities,
        ];
    }

    public static function save(int $firstCost, int $respinCost, array $probabilities): void
    {
        if ($firstCost < 1 || $respinCost < 1) throw new \InvalidArgumentException('Los costos deben ser de al menos 1 punto.');
        $keys = ['muffin', 'five', 'ten', 'twenty'];
        $values = [];
        foreach ($keys as $key) {
            $raw = $probabilities[$key] ?? null;
            if (!is_numeric($raw)) throw new \InvalidArgumentException('Completa todas las probabilidades.');
            $value = round((float)$raw, 2);
            if ($value <= 0 || $value > 100) throw new \InvalidArgumentException('Cada premio debe tener una probabilidad mayor que 0% y no superior a 100%.');
            $values[$key] = $value;
        }
        $sum = array_sum(array_map(static fn($value) => (int)round($value * 100), $values));
        if ($sum !== 10000) throw new \InvalidArgumentException('Las probabilidades deben sumar exactamente 100%.');

        Settings::set('wheel_first_spin_cost', (string)$firstCost);
        Settings::set('wheel_respin_cost', (string)$respinCost);
        Settings::set('wheel_prize_probabilities', json_encode($values, JSON_THROW_ON_ERROR));
    }

    public static function prizes(array $config): array
    {
        $definitions = [
            'muffin' => ['name' => '1 muffin gratis', 'type' => 'BENEFIT', 'discount' => null, 'color' => '#f3b787'],
            'five' => ['name' => 'Cupón de 5% OFF', 'type' => 'COUPON', 'discount' => 5, 'color' => '#f8e6a0'],
            'ten' => ['name' => 'Cupón de 10% OFF', 'type' => 'COUPON', 'discount' => 10, 'color' => '#e8d3b5'],
            'twenty' => ['name' => 'Cupón de 20% OFF', 'type' => 'COUPON', 'discount' => 20, 'color' => '#f07832'],
        ];
        $prizes = [];
        foreach ($definitions as $key => $definition) {
            $definition['key'] = $key;
            $definition['probability'] = (float)$config['probabilities'][$key];
            $prizes[] = $definition;
        }
        return $prizes;
    }

    public static function displaySegments(array $prizes): array
    {
        $keys = ['muffin', 'five', 'ten', 'muffin', 'five', 'ten', 'twenty'];
        $probabilities = [];
        $definitions = [];
        foreach ($prizes as $prize) {
            $key = (string)$prize['key'];
            $probabilities[$key] = max(0.0, (float)$prize['probability']);
            $definitions[$key] = $prize;
        }
        $counts = array_count_values($keys);
        $uniformSize = 100 / count($keys);
        $blend = 0.2;
        $segments = [];
        $total = 0.0;
        foreach ($keys as $key) {
            $configuredShare = ($probabilities[$key] ?? 0.0) / max(1, $counts[$key]);
            $size = (1 - $blend) * $uniformSize + $blend * $configuredShare;
            if ($key === 'twenty') $size *= 0.9;
            $segments[] = [
                'key' => $key,
                'size' => $size,
                'color' => $definitions[$key]['color'] ?? '#f8e6a0',
                'name' => $definitions[$key]['name'] ?? $key,
            ];
            $total += $size;
        }
        foreach ($segments as &$segment) $segment['size'] = $total > 0 ? $segment['size'] * 100 / $total : $uniformSize;
        unset($segment);
        return $segments;
    }

    public static function displayStopAngle(string $key, array $prizes): float
    {
        $matchingSegments = [];
        $segments = self::displaySegments($prizes);
        foreach ($segments as $index => $segment) {
            if ($segment['key'] === $key) $matchingSegments[] = $index;
        }
        if (!$matchingSegments) return 0.0;
        $index = $matchingSegments[random_int(0, count($matchingSegments) - 1)];
        $start = 0.0;
        foreach (array_slice($segments, 0, $index) as $segment) $start += (float)$segment['size'] * 3.6;
        return 360 - ($start + (float)$segments[$index]['size'] * 1.8);
    }

    public static function gradient(array $prizes): string
    {
        $segments = self::displaySegments($prizes);
        $stops = [];
        $start = 0.0;
        foreach ($segments as $segment) {
            $key = $segment['key'];
            $end = $start + (float)$segment['size'] * 3.6;
            $colorEnd = $end - 1.2;
            $stops[] = $segment['color'].' '.number_format($start, 2, '.', '').'deg '.number_format($colorEnd, 2, '.', '').'deg';
            $stops[] = '#fffaf3 '.number_format($colorEnd, 2, '.', '').'deg '.number_format($end, 2, '.', '').'deg';
            $start = $end;
        }
        return 'conic-gradient('.implode(', ', $stops).')';
    }
}
