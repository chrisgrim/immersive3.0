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
                'posts' => function ($query) {
                    // Don't filter by is_hidden - hidden posts should still appear in docks
                    $query->where('status', 'p')
                        ->with([
                            'community:id,name,slug',
                            'featuredEventImage',
                            'images',
                            'limitedCards',
                            'limitedCards.event.currentUserFavorite',
                            // The hero dock (curated.hero) maps each post's full
                            // cards and reads each card's post, community, event
                            // and images. Eager-load them to avoid an N+1 per
                            // card (EI-LARAVEL-F).
                            'cards' => function ($cardQuery) {
                                $cardQuery->with([
                                    'post:id,name,slug,community_id',
                                    'post.community:id,name,slug',
                                    'event:id,name,slug,thumbImagePath,largeImagePath',
                                    'event.currentUserFavorite',
                                    'images',
                                ]);
                            },
                        ])
                        ->orderBy('order')
                        ->limit(4);
                },
                'cards' => function ($query) {
                    $query->with([
                        'post:id,name,slug,community_id',
                        'post.community:id,name,slug',
                        'event:id,name,slug,thumbImagePath,largeImagePath',
                        'event.currentUserFavorite',
                        'images',
                    ])
                        ->orderBy('order')
                        ->limit(4);
                },
                'shelves' => function ($query) {
                    $query->where('is_hidden', false);
                },
                'shelves.dockPosts' => function ($query) {
                    // Use dockPosts relationship which includes hidden posts
                    $query->with([
                        'community:id,name,slug',
                        'featuredEventImage',
                        'images',
                        'limitedCards',
                        'limitedCards.event.currentUserFavorite',
                        // Hero maps these posts' cards through the same path
                        // as the posts branch; eager-load to match (EI-LARAVEL-F).
                        'cards' => function ($cardQuery) {
                            $cardQuery->with([
                                'post:id,name,slug,community_id',
                                'post.community:id,name,slug',
                                'event:id,name,slug,thumbImagePath,largeImagePath',
                                'event.currentUserFavorite',
                                'images',
                            ]);
                        },
                    ]);
                },
                'communities',
            ])
            ->orderBy('order', 'ASC')
            ->get();

        return view('index', compact('docks'));
    }
}
