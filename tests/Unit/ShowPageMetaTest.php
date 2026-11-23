<?php

use App\Models\Project;
use App\Support\Seo\PageMeta;

uses(Tests\TestCase::class);

// A show page's runs as ProjectPage hands them over: only the city counts.
function showPage(string ...$places): array
{
    return ['now' => array_map(fn ($place) => ['place' => $place, 'in_person' => true], $places)];
}

function show(): Project
{
    return (new Project)->forceFill(['name' => 'Prison Break', 'description' => 'Escape the cell.']);
}

test('two or three places are named with "and"', function (array $places, string $title, string $where) {
    $page = showPage(...$places);

    expect(PageMeta::projectTitle(show(), $page))->toBe("Prison Break: {$title} | Everything Immersive")
        ->and(PageMeta::projectDescription(show(), $page))->toStartWith("On now or coming up in {$where}.");
})->with([
    'two' => [['New York, NY', 'Brooklyn, NY'], 'New York and Brooklyn', 'New York and Brooklyn'],
    'three' => [['London', 'Paris', 'Berlin'], 'London, Paris and Berlin', 'London, Paris and Berlin'],
]);

test('one place, and four or more, read as before', function () {
    expect(PageMeta::projectTitle(show(), showPage('London')))->toBe('Prison Break: London | Everything Immersive')
        ->and(PageMeta::projectTitle(show(), showPage('A', 'B', 'C', 'D')))->toBe('Prison Break: 4 cities incl. A, B | Everything Immersive')
        ->and(PageMeta::projectDescription(show(), showPage('A', 'B', 'C', 'D')))->toStartWith('On now or coming up in A, B, C and D.')
        ->and(PageMeta::projectDescription(show(), showPage('A', 'B', 'C', 'D', 'E', 'F')))->toStartWith('On now or coming up in A, B, C, D and 2 more.');
});
