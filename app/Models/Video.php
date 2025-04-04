<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    use HasFactory;

    protected $fillable = ['videoable_id', 'videoable_type', 'url', 'platform', 'thumbnail_url', 'title', 'description', 'rank'];

    public function videoable()
    {
        return $this->morphTo();
    }

    public function scopeOrderedByRank($query)
    {
        return $query->orderBy('rank', 'asc');
    }

    public function newQuery($excludeDeleted = true)
    {
        return parent::newQuery($excludeDeleted)->orderedByRank();
    }
} 