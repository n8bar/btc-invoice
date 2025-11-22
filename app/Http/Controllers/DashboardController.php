<?php

namespace App\Http\Controllers;

use App\Services\DashboardSnapshot;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardSnapshot $snapshot)
    {
        $data = $snapshot->forUser($request->user());

        return view('dashboard', [
            'snapshot' => $data,
        ]);
    }
}
