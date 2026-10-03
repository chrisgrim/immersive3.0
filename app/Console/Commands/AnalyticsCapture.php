<?php

namespace App\Console\Commands;

use App\Support\Analytics\Analytics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Switch a phase 2 capture on or off without a deploy (a cache override
 * that wins over config/analytics.php 'capture'), or show them all.
 * `default` removes the override so config decides again.
 */
class AnalyticsCapture extends Command
{
    protected $signature = 'ei:analytics-capture {name? : One of the config analytics.capture keys} {state? : on, off or default}';

    protected $description = 'Show or switch the analytics captures (page views, device, utm, city, duration, live, nav_search).';

    public function handle(): int
    {
        $names = array_keys(config('analytics.capture'));
        $overrides = (array) Cache::get(Analytics::CAPTURE_OVERRIDES, []);
        $name = $this->argument('name');

        if ($name !== null) {
            if (! in_array($name, $names, true) || ! in_array($this->argument('state'), ['on', 'off', 'default'], true)) {
                $this->error('Usage: ei:analytics-capture <'.implode('|', $names).'> <on|off|default>');

                return self::FAILURE;
            }

            if ($this->argument('state') === 'default') {
                unset($overrides[$name]);
            } else {
                $overrides[$name] = $this->argument('state') === 'on';
            }
            Cache::forever(Analytics::CAPTURE_OVERRIDES, $overrides);
            app(Analytics::class)->forgetOverrides();
        }

        $this->table(['capture', 'on?', 'set by'], array_map(fn ($capture) => [
            $capture,
            Analytics::captures($capture) ? 'on' : 'off',
            array_key_exists($capture, $overrides) ? 'override' : 'config',
        ], $names));

        return self::SUCCESS;
    }
}
