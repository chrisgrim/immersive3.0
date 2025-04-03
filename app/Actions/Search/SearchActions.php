<?php

namespace App\Actions\Search;

use Illuminate\Http\Request;
use App\Models\Curated\Shelf;
use App\Models\Curated\Community;
use Illuminate\Support\Str;
use Elastic\ScoutDriverPlus\Support\Query;

class SearchActions
{

    /**
     * Create helper for name search.
     */
    public function nameSearch(Request $request)
    {
        if ($request->keywords) {
            return Query::multiMatch()
                ->fields(['name', 'name._2gram','name._3gram'])
                ->type('bool_prefix')
                ->query($request->keywords);
        } else {
            return Query::matchAll();
        }
    }

    public function eventSearch(Request $request)
    {
        if (!$request->keywords) {
            return Query::matchAll();
        }

        return Query::bool()
            ->should(
                Query::match()
                    ->field('name.raw')
                    ->query($request->keywords)
                    ->boost(10)
            )
            ->should(
                Query::match()
                    ->field('name')
                    ->query($request->keywords)
                    ->boost(5)
            )
            ->should(
                Query::multiMatch()
                    ->fields(['name._2gram', 'name._3gram'])
                    ->type('bool_prefix')
                    ->query($request->keywords)
            )
            ->minimumShouldMatch(1);
    }
    
}