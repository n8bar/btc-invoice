<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class SupportAccessSettingsController extends Controller
{
    public function grant(Request $request): RedirectResponse
    {
        abort_if($request->user()->isSupportAgent(), 403);

        $request->user()->grantSupportAccess();

        return Redirect::route('profile.edit')->with('status', 'support-access-granted');
    }

    public function revoke(Request $request): RedirectResponse
    {
        abort_if($request->user()->isSupportAgent(), 403);

        $request->user()->revokeSupportAccess();

        return Redirect::route('profile.edit')->with('status', 'support-access-revoked');
    }
}
