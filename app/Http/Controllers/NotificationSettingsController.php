<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotificationSettingsRequest;
use App\Models\User;
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
        $data = $request->validated();
        $data['mail_brand_name'] = $this->normalizeDefaultValue($data['mail_brand_name'] ?? null, User::defaultMailBrandName());
        $data['mail_brand_tagline'] = $this->normalizeDefaultValue($data['mail_brand_tagline'] ?? null, User::defaultMailBrandTagline());
        $data['mail_footer_blurb'] = $this->normalizeDefaultValue($data['mail_footer_blurb'] ?? null, User::defaultMailFooterBlurb());

        $request->user()->fill($data)->save();

        return Redirect::route('settings.notifications.edit')->with('status', 'notification-settings-updated');
    }

    private function normalizeDefaultValue(?string $value, string $default): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value === $default ? null : $value;
    }
}
