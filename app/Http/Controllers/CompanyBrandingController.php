<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCompanyBrandingRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CompanyBrandingController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user()->role === 'owner', 403);

        return Inertia::render('Settings/Branding');
    }

    public function update(UpdateCompanyBrandingRequest $request): RedirectResponse
    {
        $company = $request->user()->company;
        $data = $request->validated();
        $changes = [];

        if (array_key_exists('primary_color', $data)) {
            $changes['primary_color'] = $data['primary_color'] !== '' ? $data['primary_color'] : null;
        }

        if ($request->boolean('remove_logo')) {
            $this->deleteLogo($company->logo_path);
            $changes['logo_path'] = null;
        } elseif ($request->hasFile('logo')) {
            $this->deleteLogo($company->logo_path);
            $changes['logo_path'] = $request->file('logo')->store('company-logos', 'public');
        }

        $company->update($changes);

        return Redirect::route('settings.branding.edit');
    }

    private function deleteLogo(?string $path): void
    {
        if ($path !== null && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
