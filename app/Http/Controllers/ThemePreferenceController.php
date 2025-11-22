<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ThemePreferenceController extends Controller
{
    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'theme' => ['required', Rule::in(['light', 'dark', 'system'])],
        ]);

        $user = $request->user();
        $user->theme = $validated['theme'];
        $user->save();

        if ($request->wantsJson()) {
            return response()->json(['theme' => $user->theme]);
        }

        return back()->with('status', 'theme-updated');
    }
}
