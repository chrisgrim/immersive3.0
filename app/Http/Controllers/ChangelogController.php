<?php

namespace App\Http\Controllers;

use App\Support\Changelog;

class ChangelogController extends Controller
{
    public function index()
    {
        return view('changelog.index', ['posts' => Changelog::all()]);
    }

    public function show(string $slug)
    {
        $post = Changelog::find($slug) ?? abort(404);
        $more = Changelog::all()->where('slug', '!=', $slug)->take(4)->values();

        return view('changelog.show', compact('post', 'more'));
    }
}
