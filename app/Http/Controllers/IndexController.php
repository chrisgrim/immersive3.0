<?php

namespace App\Http\Controllers;

use App\Models\Admin\Dock;

class IndexController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $docks = Dock::where('location', 'home')->with(['posts.limitedCards', 'shelves.publishedPosts.limitedCards', 'communities'])->orderBy('order', 'ASC')->get();
        // $atHomeCategories=Category::where('remote', true)->get();
        // $inPersonCategories=Category::where('remote', false)->get();
        // $tags = Genre::where('admin', 1)->orderBy('rank', 'desc')->get();
        return view('index', compact('docks'));
        // return view('home.index', compact('staffpicks', 'docks', 'tags', 'atHomeCategories', 'inPersonCategories'));
    }
}
