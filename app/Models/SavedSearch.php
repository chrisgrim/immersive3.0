<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavedSearch extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'criteria', 'fingerprint', 'pinned',
    ];

    protected $casts = [
        'criteria' => 'array',
        'pinned' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
