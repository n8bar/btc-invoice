<?php

namespace App\Http\Controllers;

use App\Services\DashboardSnapshot;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardSnapshot $snapshot)
    {
        $user = $request->user();
        $data = $snapshot->forUser($user);

        return view('dashboard', [
            'snapshot' => $data,
            'hasClients' => $user->clients()->exists(),
        ]);
    }
}
