<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotificationSettingsRequest;
use App\Mail\NotificationBrandingPreviewMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\RateLimiter;
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
        $data['show_mail_logo'] = $request->boolean('show_mail_logo');

        $request->user()->fill($data)->save();

        return Redirect::route('settings.notifications.edit')->with('status', 'notification-settings-updated');
    }

    public function sendPreview(Request $request): RedirectResponse
    {
        $user = $request->user();
        $key = 'notification-branding-preview:' . $user->getKey();

        if (RateLimiter::tooManyAttempts($key, 1)) {
            return Redirect::route('settings.notifications.edit')
                ->with('status', 'notification-preview-throttled')
                ->with('preview_retry_after', RateLimiter::availableIn($key));
        }

        Mail::to($user->email)->send(new NotificationBrandingPreviewMail($user));
        RateLimiter::hit($key, 60);

        return Redirect::route('settings.notifications.edit')
            ->with('status', 'notification-preview-sent')
            ->with('preview_email', $user->email);
    }

    private function normalizeDefaultValue(?string $value, string $default): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value === $default ? null : $value;
    }
}
