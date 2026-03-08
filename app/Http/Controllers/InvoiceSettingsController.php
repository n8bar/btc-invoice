<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class InvoiceSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.invoice', [
            'user' => $request->user(),
        ]);
    }

    public function update(InvoiceSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['show_overpayment_gratuity_note'] = $request->boolean('show_overpayment_gratuity_note');
        $data['show_qr_refresh_reminder'] = $request->boolean('show_qr_refresh_reminder');

        $request->user()->fill($data)->save();

        return Redirect::route('settings.invoice.edit')->with('status', 'invoice-settings-updated');
    }
}
