<?php

namespace App\Console\Commands;

use App\Support\Analytics\Analytics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Switch a phase 2 capture on or off without a deploy (an override in
 * storage/app/analytics-capture.json that wins over config/analytics.php
 * 'capture'), or show them all.
 * `default` removes the override so config decides again.
 */
class AnalyticsCapture extends Command
{
    protected $signature = 'ei:analytics-capture {name? : One of the config analytics.capture keys} {state? : on, off or default}';

    protected $description = 'Show or switch the analytics captures (page views, device, utm, city, duration, live, nav_search).';

    public function handle(): int
    {
        $names = array_keys(config('analytics.capture'));
        $file = @file_get_contents(Analytics::overridesPath());
        $overrides = is_string($file) && is_array($decoded = json_decode($file, true)) ? $decoded : [];
        $name = $this->argument('name');

        if ($name !== null) {
            if (! in_array($name, $names, true) || ! in_array($this->argument('state'), ['on', 'off', 'default'], true)) {
                $this->error('Usage: ei:analytics-capture <'.implode('|', $names).'> <on|off|default>');

                return self::FAILURE;
            }

            $wasOn = Analytics::captures($name);
            if ($this->argument('state') === 'default') {
                unset($overrides[$name]);
            } else {
                $overrides[$name] = $this->argument('state') === 'on';
            }
            // When it goes off, remember when: its records are kept for 13
            // months, and the privacy page names it until they are gone.
            $nowOn = (bool) ($overrides[$name] ?? config("analytics.capture.{$name}", false));
            if ($wasOn && ! $nowOn) {
                $overrides['off_at'][$name] = now()->getTimestamp();
            } elseif ($nowOn) {
                unset($overrides['off_at'][$name]);
            }
            File::put(Analytics::overridesPath(), json_encode($overrides));
            @chmod(Analytics::overridesPath(), 0644);
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
