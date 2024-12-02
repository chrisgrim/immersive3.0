<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

class UniqueSlugRule implements Rule
{
    protected string $name;
    protected ?int $id;
    protected string $modelClass;
    protected string $slugColumn;

    public function __construct(string $name, string $modelClass, string $slugColumn = 'slug', ?int $id = null)
    {
        $this->name = $name;
        $this->modelClass = $modelClass;
        $this->slugColumn = $slugColumn;
        $this->id = $id;
    }

    public function passes($attribute, $value): bool
    {
        $slug = Str::slug($this->name);

        $query = $this->modelClass::where($this->slugColumn, $slug);

        if ($this->id) {
            $query->where('id', '!=', $this->id);
        }

        return !$query->exists();
    }

    public function message(): string
    {
        return 'The slug for this name is already taken.';
    }
}