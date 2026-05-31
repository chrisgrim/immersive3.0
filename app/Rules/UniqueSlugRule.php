<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Str;

class UniqueSlugRule implements Rule
{
    protected string $name;

    protected ?int $id;

    protected string $modelClass;

    protected string $slugColumn;

    public function __construct(?string $name, string $modelClass, string $slugColumn = 'slug', ?int $id = null)
    {
        // Tolerate a null/empty name (e.g. an empty-string field coerced to null by
        // ConvertEmptyStringsToNull) so building this rule never throws — the
        // 'required' rule on the field produces the validation error instead.
        $this->name = $name ?? '';
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

        return ! $query->exists();
    }

    public function message(): string
    {
        return 'This name is already taken.';
    }
}
