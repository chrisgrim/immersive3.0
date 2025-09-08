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
        $docks = Dock::where('location', 'home')
            ->with([
                'posts' => function($query) {
                    $query->where('status', 'p')
                          ->where('is_hidden', false)
                          ->with([
                              'community:id,name,slug',
                              'featuredEventImage',
                              'images',
                              'limitedCards'
                          ])
                          ->orderBy('order')
                          ->limit(4);
                },
                'cards' => function($query) {
                    $query->with([
                        'post:id,name,slug,community_id',
                        'post.community:id,name,slug',
                        'event:id,name,slug,thumbImagePath,largeImagePath',
                        'images'
                    ])
                    ->orderBy('order')
                    ->limit(4);
                },
                'shelves' => function($query) {
                    $query->where('is_hidden', false);
                },
                'shelves.publishedPosts' => function($query) {
                    $query->where('is_hidden', false)
                          ->with([
                              'community:id,name,slug',
                              'featuredEventImage',
                              'images',
                              'limitedCards'
                          ]);
                },
                'communities'
            ])
            ->orderBy('order', 'ASC')
            ->get();
            
        return view('index', compact('docks'));
    }
}
