<?php

// Nightly via ScheduleServiceProvider. Passport 13 purges device codes along
// with tokens, so that table has to exist even though EI never offers the
// device flow: the install published the other four Passport migrations
// without it, and the first scheduled run on prod failed (EI-LARAVEL-17/18).
test('passport:purge runs against every table it touches', function () {
    $this->artisan('passport:purge')->assertSuccessful();
});
