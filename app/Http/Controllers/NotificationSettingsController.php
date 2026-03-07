<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotificationSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class NotificationSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.notifications', [
            'user' => $request->user(),
        ]);
    }

    public function update(NotificationSettingsRequest $request): RedirectResponse
    {
        $request->user()->forceFill([
            'auto_receipt_emails' => $request->boolean('auto_receipt_emails'),
        ])->save();

        return Redirect::route('settings.notifications.edit')
            ->with('status', 'notification-settings-updated');
    }
}
