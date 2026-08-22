<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoginHistory extends Model
{
    use HasFactory;

    protected $table = 'login_history';

    protected $fillable = [
        'user_id', 'session_id', 'device_id', 'ip_address', 'browser', 'platform', 'device_type',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
