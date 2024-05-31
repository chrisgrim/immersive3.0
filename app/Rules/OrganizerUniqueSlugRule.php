<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Str;
use App\Models\Organizer;

class OrganizerUniqueSlugRule implements Rule
{
    protected $name;
    protected $id;

    public function __construct($name, $id = null)
    {
        $this->name = $name;
        $this->id = $id;
    }

    public function passes($attribute, $value)
    {
        $slug = Str::slug($this->name);

        $query = Organizer::where('slug', $slug);

        if ($this->id) {
            // Exclude the current record if an ID is provided (for updates)
            $query->where('id', '!=', $this->id);
        }

        return !$query->exists();
    }

    public function message()
    {
        return 'A Team with this name already exists';
    }
}