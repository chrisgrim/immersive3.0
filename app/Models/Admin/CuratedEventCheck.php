<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CuratedEventCheck extends Model
{
    use HasFactory;

    /**
    * What protected variables are allowed to be passed to the database
    *
    * @var array
    */
    protected $fillable = ['curated', 'social', 'newsletter'];

    protected $casts = [
        'curated' => 'boolean', 
        'newsletter' => 'boolean',
        'social' => 'boolean'
    ];

    // Constants representing the three possible states
    const STATE_NONE = null;
    const STATE_FALSE = false;
    const STATE_TRUE = true;
    
    /**
     * Cycle through the three states for any check field
     * 
     * @param string $field
     * @return mixed
     */
    public function cycleState($field)
    {
        if (!in_array($field, ['curated', 'social', 'newsletter'])) {
            return $this->$field;
        }
        
        // Cycle through: null -> false -> true -> null
        if ($this->$field === self::STATE_NONE) {
            $this->$field = self::STATE_FALSE;
        } elseif ($this->$field === self::STATE_FALSE) {
            $this->$field = self::STATE_TRUE;
        } else {
            $this->$field = self::STATE_NONE;
        }
        
        return $this->$field;
    }

    /**
    * Each Curated Check belongs to One Event
    *
    * @return \Illuminate\Database\Eloquent\Relations\belongsTo
    */
    public function event() 
    {
        return $this->belongsTo(Event::class);
    }
}
