<?php

namespace App\Support;

class ColorPalette
{
    /**
     * Curated presets an owner can pick without thinking about contrast.
     * Each value is the base ("600"-weight) colour; the 50-900 ramp is derived.
     */
    public const PRESETS = [
        'indigo' => '#4f46e5',
        'blue' => '#2563eb',
        'teal' => '#0d9488',
        'emerald' => '#059669',
        'rose' => '#e11d48',
        'violet' => '#7c3aed',
    ];

    public const DEFAULT = 'indigo';

    /**
     * Resolve a stored `companies.primary_color` value (preset key, "#rrggbb", or
     * null) into a structure the settings UI and the CSS-variable injector share.
     * Anything unrecognised falls back to the app default.
     *
     * @return array{name: string, hex: string, custom: bool, scale: array<int, string>}
     */
    public static function resolve(?string $value): array
    {
        $value = is_string($value) ? trim($value) : '';

        if (array_key_exists($value, self::PRESETS)) {
            return self::make($value, self::PRESETS[$value], false);
        }

        if (self::isHex($value)) {
            return self::make('custom', strtolower($value), true);
        }

        return self::make(self::DEFAULT, self::PRESETS[self::DEFAULT], false);
    }

    public static function isHex(string $value): bool
    {
        return (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $value);
    }

    /**
     * Whether a candidate value is something we are willing to store.
     */
    public static function isValid(string $value): bool
    {
        return array_key_exists($value, self::PRESETS) || self::isHex($value);
    }

    /**
     * The CSS custom-property block for a resolved palette, or an empty string
     * when the palette is the app default (the stylesheet already covers it).
     *
     * @param  array{name: string, hex: string, custom: bool, scale: array<int, string>}  $palette
     */
    public static function cssVariables(array $palette): string
    {
        if ($palette['name'] === self::DEFAULT) {
            return '';
        }

        $lines = [];
        foreach ($palette['scale'] as $weight => $rgb) {
            // !important so the per-company override always beats the compiled
            // stylesheet's default :root, regardless of <head> tag order (Vite
            // injects the app stylesheet after this block in dev mode).
            $lines[] = sprintf('--c-primary-%d:%s !important;', $weight, $rgb);
        }

        return ':root{'.implode('', $lines).'}';
    }

    /**
     * @return array{name: string, hex: string, custom: bool, scale: array<int, string>}
     */
    private static function make(string $name, string $hex, bool $custom): array
    {
        return [
            'name' => $name,
            'hex' => $hex,
            'custom' => $custom,
            'scale' => self::ramp($hex),
        ];
    }

    /**
     * Build a 50-900 ramp from a single base colour by mixing towards white
     * (lighter steps) and black (darker steps). Values are "r g b" strings so
     * they drop straight into `rgb(var(--c-primary-600) / <alpha-value>)`.
     *
     * @return array<int, string>
     */
    public static function ramp(string $hex): array
    {
        $base = self::toRgb($hex);
        $white = [255, 255, 255];
        $black = [0, 0, 0];

        $steps = [
            50 => [$white, 0.92],
            100 => [$white, 0.84],
            200 => [$white, 0.68],
            300 => [$white, 0.48],
            400 => [$white, 0.24],
            500 => [$white, 0.10],
            600 => [$base, 0.0],
            700 => [$black, 0.18],
            800 => [$black, 0.34],
            900 => [$black, 0.48],
        ];

        $ramp = [];
        foreach ($steps as $weight => [$target, $amount]) {
            $ramp[$weight] = self::mix($base, $target, $amount);
        }

        return $ramp;
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $from
     * @param  array{0: int, 1: int, 2: int}  $to
     */
    private static function mix(array $from, array $to, float $amount): string
    {
        $channel = fn (int $a, int $b): int => (int) round($a + ($b - $a) * $amount);

        return sprintf(
            '%d %d %d',
            $channel($from[0], $to[0]),
            $channel($from[1], $to[1]),
            $channel($from[2], $to[2]),
        );
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function toRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }
}
