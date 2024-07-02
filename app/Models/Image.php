<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    use HasFactory;

    protected $fillable = ['imageable_id', 'imageable_type', 'large_image_path', 'thumb_image_path', 'rank'];

    public function imageable()
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
