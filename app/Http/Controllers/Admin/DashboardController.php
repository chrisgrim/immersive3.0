<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        // $this->middleware(['auth', 'admin']);
    }

    /**
     * Display admin dashboard
     */
    public function index()
    {
        return view('Admin.index', [
            'user' => auth()->user()
        ]);
    }
}
