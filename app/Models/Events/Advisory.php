<?php

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Model;

class Advisory extends Model
{
    /**
     * What protected variables are allowed to be passed to the database
     *
     * @var array
     */
    protected $fillable = [
        'wheelchairReady', 'wheelchairAccess', 'wheelchairDescription', 'sexual', 'sexualDescription', 'mobilityAdvisories', 'contactAdvisories', 'event_id', 'ageRestriction', 'audience',
    ];

    /**
     * Wheelchair answers. Anything short of full access needs a written
     * explanation (wheelchairDescription), like the sexual content question.
     * The old yes/no column wheelchairReady is still written: true only for
     * full access.
     */
    public const WHEELCHAIR_FULL = 'full';

    public const WHEELCHAIR_PARTIAL = 'partial';

    public const WHEELCHAIR_NONE = 'none';

    public const WHEELCHAIR_LEVELS = [self::WHEELCHAIR_FULL, self::WHEELCHAIR_PARTIAL, self::WHEELCHAIR_NONE];

    /** The automatic mobility chip each answer adds (name => slug via Str::slug). */
    public const WHEELCHAIR_CHIPS = [
        self::WHEELCHAIR_FULL => 'Wheelchair Accessible',
        self::WHEELCHAIR_PARTIAL => 'Partially Wheelchair Accessible',
        self::WHEELCHAIR_NONE => 'Not Wheelchair Accessible',
    ];

    /**
     * The explanation given to every "no" answered before explanations
     * existed. The event page does not repeat it under its own "not
     * wheelchair accessible" line.
     */
    public const WHEELCHAIR_DEFAULT_EXPLANATION = 'Not wheelchair accessible.';

    public const WHEELCHAIR_CHIP_SLUGS = ['wheelchair-accessible', 'partially-wheelchair-accessible', 'not-wheelchair-accessible'];

    /**
     * The three-way answer, falling back to the old yes/no column for a row
     * written only through it. Null means never answered. When the two
     * disagree the yes/no wins: only code from before this change writes it
     * alone (a rollback), so it is the newer answer.
     */
    public function wheelchairLevel(): ?string
    {
        $ready = $this->wheelchairReady === null ? null : (bool) $this->wheelchairReady;
        $access = in_array($this->wheelchairAccess, self::WHEELCHAIR_LEVELS, true) ? $this->wheelchairAccess : null;

        if ($ready === null) {
            return $access;
        }

        if ($access !== null && ($access === self::WHEELCHAIR_FULL) === $ready) {
            return $access;
        }

        return $ready ? self::WHEELCHAIR_FULL : self::WHEELCHAIR_NONE;
    }

    /**
     * The answer a save request gives, if any: wheelchairAccess, or the old
     * yes/no wheelchairReady (a browser tab opened before this change, or an
     * older assistant) read as full / none. An old yes/no that agrees with
     * the current answer keeps it, so a "no" from an old tab leaves a
     * partial answer partial.
     */
    public static function levelFromInput(array $input, ?string $current = null): ?string
    {
        if (isset($input['wheelchairAccess'])) {
            return $input['wheelchairAccess'];
        }

        if (isset($input['wheelchairReady'])) {
            $ready = filter_var($input['wheelchairReady'], FILTER_VALIDATE_BOOLEAN);

            if ($current !== null && ($current === self::WHEELCHAIR_FULL) === $ready) {
                return $current;
            }

            return $ready ? self::WHEELCHAIR_FULL : self::WHEELCHAIR_NONE;
        }

        return null;
    }

    /**
     * The columns to write for an answer: the new pair plus the old yes/no.
     * A full answer clears any old explanation.
     */
    public static function wheelchairColumns(string $level, ?string $description): array
    {
        return [
            'wheelchairAccess' => $level,
            'wheelchairDescription' => $level === self::WHEELCHAIR_FULL ? null : $description,
            'wheelchairReady' => $level === self::WHEELCHAIR_FULL,
        ];
    }

    /**
     * Expect Model hasOne Event
     *
     * @return \Illuminate\Database\Eloquent\Relations\hasOne
     */
    public function event()
    {
        return $this->hasOne(Event::class);
    }
}
