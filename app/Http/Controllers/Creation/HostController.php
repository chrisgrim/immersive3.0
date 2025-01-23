<?php

namespace App\Http\Controllers\Creation;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Models\Event;
use Illuminate\Http\Request;

class HostController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    public function show()
    {
        $organizer = auth()->user()->organizer()
            ->withUserRole()
            ->with(['images', 'events' => function ($query) {
                $query->with('images');
            }])
            ->first();
            
        return view('Creation.index', compact('organizer'));
    }

    public function intro()
    {
        return view('Creation.started');
    }
}
