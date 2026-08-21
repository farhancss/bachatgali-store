<?php

declare(strict_types=1);

/*
| Scheduled work. Each of these arrives with the phase that needs it —
| listed now so the operational shape of the system is visible from day one.
*/

// Schedule::command('courier:sync-tracking')->everyFifteenMinutes();
// Schedule::command('cod:import-remittances')->dailyAt('06:00');
// Schedule::command('cod:report-cash-in-transit')->dailyAt('09:00');
// Schedule::command('catalog:regenerate-sitemap')->dailyAt('03:00');
// Schedule::command('flash-sale:roll-back-expired')->everyMinute();

use Illuminate\Support\Facades\Schedule;

// Rebuilt nightly rather than on every catalog write: crawlers re-read it on
// their own cadence, and regenerating per save would rewrite the file
// hundreds of times during a bulk import for no benefit.
Schedule::command('sitemap:generate')->dailyAt('03:30')->withoutOverlapping();
