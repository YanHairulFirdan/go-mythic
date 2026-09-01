<?php

namespace Tests\Unit;

use App\Support\ColorPalette;
use PHPUnit\Framework\TestCase;

class ColorPaletteTest extends TestCase
{
    public function test_resolves_a_preset_key_to_its_base_hex(): void
    {
        $palette = ColorPalette::resolve('blue');

        $this->assertSame('blue', $palette['name']);
        $this->assertSame('#2563eb', $palette['hex']);
        $this->assertFalse($palette['custom']);
    }

    public function test_resolves_a_custom_hex_and_normalises_case(): void
    {
        $palette = ColorPalette::resolve('#FF5733');

        $this->assertSame('custom', $palette['name']);
        $this->assertSame('#ff5733', $palette['hex']);
        $this->assertTrue($palette['custom']);
    }

    public function test_unrecognised_values_fall_back_to_the_default(): void
    {
        foreach (['not-a-color', '#12345', '#zzzzzz', 'rgb(0,0,0)', ''] as $value) {
            $this->assertSame(ColorPalette::DEFAULT, ColorPalette::resolve($value)['name']);
        }

        $this->assertSame(ColorPalette::DEFAULT, ColorPalette::resolve(null)['name']);
    }

    public function test_ramp_has_ten_steps_and_the_600_step_equals_the_input(): void
    {
        $ramp = ColorPalette::ramp('#2563eb');

        $this->assertSame([50, 100, 200, 300, 400, 500, 600, 700, 800, 900], array_keys($ramp));
        $this->assertSame('37 99 235', $ramp[600]);
        $this->assertSame('255 255 255', ColorPalette::ramp('#ffffff')[600]);
        $this->assertSame('0 0 0', ColorPalette::ramp('#000000')[600]);
    }

    public function test_is_valid_accepts_presets_and_six_digit_hex_only(): void
    {
        $this->assertTrue(ColorPalette::isValid('violet'));
        $this->assertTrue(ColorPalette::isValid('#0d9488'));
        $this->assertFalse(ColorPalette::isValid('#fff'));
        $this->assertFalse(ColorPalette::isValid('purple'));
    }

    public function test_css_variables_are_empty_for_the_default_and_present_otherwise(): void
    {
        $this->assertSame('', ColorPalette::cssVariables(ColorPalette::resolve(null)));
        $this->assertSame('', ColorPalette::cssVariables(ColorPalette::resolve('indigo')));

        $css = ColorPalette::cssVariables(ColorPalette::resolve('#ff5733'));
        $this->assertStringStartsWith(':root{', $css);
        $this->assertStringContainsString('--c-primary-600:255 87 51 !important;', $css);
    }
}
