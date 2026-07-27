<?php

namespace App\Services\Themes;

final class ThemeValidator
{
    public function validate(array $configuration): array
    {
        $theme = ThemeSchema::normalize($configuration);
        $checks = [];
        $errors = [];

        foreach (['light', 'dark'] as $mode) {
            $pairs = [
                'page text' => [$theme['colors'][$mode]['text'], $theme['colors'][$mode]['background'], 4.5],
                'surface text' => [$theme['colors'][$mode]['text'], $theme['colors'][$mode]['surface'], 4.5],
                'muted text' => [$theme['colors'][$mode]['muted'], $theme['colors'][$mode]['surface'], 4.5],
                'link text' => [$theme['colors'][$mode]['brand'], $theme['colors'][$mode]['background'], 4.5],
            ];

            foreach ($pairs as $label => [$foreground, $background, $minimum]) {
                $ratio = round($this->contrastRatio($foreground, $background), 2);
                $passed = $ratio >= $minimum;
                $checks[] = compact('mode', 'label', 'ratio', 'minimum', 'passed');

                if (! $passed) {
                    $errors[] = ucfirst($mode)." {$label} needs at least {$minimum}:1 contrast; current ratio is {$ratio}:1.";
                }
            }

            $buttonForeground = $this->bestForeground($theme['colors'][$mode]['brand']);
            $buttonRatio = round($this->contrastRatio($buttonForeground, $theme['colors'][$mode]['brand']), 2);
            $checks[] = [
                'mode' => $mode,
                'label' => 'primary button',
                'ratio' => $buttonRatio,
                'minimum' => 4.5,
                'passed' => $buttonRatio >= 4.5,
            ];
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'checks' => $checks,
            'validatedAt' => now()->toIso8601String(),
        ];
    }

    public function bestForeground(string $background): string
    {
        return $this->contrastRatio('#151A16', $background) >= $this->contrastRatio('#FFFFFF', $background)
            ? '#151A16'
            : '#FFFFFF';
    }

    public function contrastRatio(string $first, string $second): float
    {
        $lighter = max($this->luminance($first), $this->luminance($second));
        $darker = min($this->luminance($first), $this->luminance($second));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function luminance(string $hex): float
    {
        $channels = [
            hexdec(substr($hex, 1, 2)) / 255,
            hexdec(substr($hex, 3, 2)) / 255,
            hexdec(substr($hex, 5, 2)) / 255,
        ];

        $channels = array_map(
            fn (float $channel): float => $channel <= 0.03928
                ? $channel / 12.92
                : (($channel + 0.055) / 1.055) ** 2.4,
            $channels,
        );

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
