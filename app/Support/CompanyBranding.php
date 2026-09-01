<?php

namespace App\Support;

use App\Models\Company;
use Illuminate\Support\Facades\Storage;

class CompanyBranding
{
    /**
     * The branding data shared with every Inertia page: the current colour
     * selection (for the settings form) and the resolved logo URL.
     *
     * @return array{primary: array{name: string, hex: string, custom: bool, scale: array<int, string>}, logoUrl: string|null}
     */
    public static function payload(?Company $company): array
    {
        $palette = ColorPalette::resolve($company?->primary_color);

        return [
            'primary' => [
                'name' => $palette['name'],
                'hex' => $palette['hex'],
                'custom' => $palette['custom'],
                // Lets the client re-apply the theme on SPA navigation, when the
                // <head> <style> block is not re-rendered.
                'scale' => $palette['scale'],
            ],
            'logoUrl' => self::logoUrl($company),
        ];
    }

    /**
     * The `:root { --c-primary-* }` block injected into the page <head> so the
     * theme is correct on first paint. Empty when the company uses the default.
     */
    public static function css(?Company $company): string
    {
        return ColorPalette::cssVariables(ColorPalette::resolve($company?->primary_color));
    }

    public static function logoUrl(?Company $company): ?string
    {
        if ($company === null || $company->logo_path === null) {
            return null;
        }

        return Storage::disk('public')->url($company->logo_path);
    }
}
