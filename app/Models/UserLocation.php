<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'home', 'street', 'city', 'region', 'country', 'postal_code', 'longitude', 'latitude',
    ];

    protected $casts = [
        'longitude' => 'float',
        'latitude' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
