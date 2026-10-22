<?php

namespace App\Support;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Reads cells as text with the app's own converter (config braille.reader):
 * the same braille_text code and liblouis tables the phone uses.
 */
class BrailleReader
{
    public static function installed(): bool
    {
        return is_executable(config('braille.reader.bin'));
    }

    /**
     * @param  list<list<float>>  $cells  [x1, y1, x2, y2, pattern, ...] in photo pixels
     * @return array{text: string, braille: string}
     *
     * @throws RuntimeException when the reader fails
     */
    public static function read(array $cells, int $width, int $height, string $lang): array
    {
        $reader = config('braille.reader');
        $result = Process::timeout(15)
            ->input(json_encode(['cells' => $cells, 'width' => $width, 'height' => $height, 'langs' => [$lang]])."\n")
            ->run([$reader['bin'], '--louis', $reader['louis'], '--tables', $reader['tables']]);

        $output = json_decode($result->output(), true);
        $read = $output[$lang] ?? null;

        if (! $result->successful() || ! is_array($read) || isset($read['error'])) {
            throw new RuntimeException('braille reader failed: '.($output['error'] ?? $read['error'] ?? $result->errorOutput()));
        }

        return ['text' => (string) $read['text'], 'braille' => (string) $read['braille']];
    }
}
