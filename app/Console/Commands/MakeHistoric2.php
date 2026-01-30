<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MakeHistoric2 extends Command
{
    protected $signature = 'gls2:make-historic {--days=1 : How many days back to process}';
    protected $description = 'Takes data from log_events2 and turns them into historic records (last value per day).';

    public function handle()
    {
        $days = (int) $this->option('days');
        if ($days < 1) {
            $this->error('--days must be >= 1');
            return 1;
        }

        // Pick the timezone you want the "day" boundaries to follow.
        // You said time_bucket is already LOCAL TIME; pick your actual local TZ here.
        $tz = 'Europe/Amsterdam';

        $endDay = Carbon::now($tz)->startOfDay(); // today 00:00
        $startDay = (clone $endDay)->subDays($days); // N days back

        $this->info("Processing days from {$startDay->toDateString()} (inclusive) to {$endDay->toDateString()} (exclusive)");

        // Optional: restrict which types you want to cook (prevents deleting/inserting other historic types)
        // If you want "all types", set to null and remove whereIn clauses below.
        $typesToCook = null; // e.g. ['EBL','EBW','KWH'] or null for all

        $totalInserted = 0;

        // Iterate day by day (safe & predictable)
        for ($day = $startDay->copy(); $day->lt($endDay); $day->addDay()) {
            $dayStart = $day->copy()->startOfDay();
            $dayEnd   = $dayStart->copy()->addDay();

            $this->line("Day: {$dayStart->toDateString()}");

            DB::beginTransaction();
            try {
                // 1) Find which sensor_id + type combos exist for this day (we'll use this list to delete precisely)
                $pairsQuery = DB::table('log_events2')
                    ->select('sensor_id', 'type')
                    ->where('time_bucket', '>=', $dayStart->format('Y-m-d H:i:s'))
                    ->where('time_bucket', '<',  $dayEnd->format('Y-m-d H:i:s'))
                    ->groupBy('sensor_id', 'type');

                if (is_array($typesToCook)) {
                    $pairsQuery->whereIn('type', $typesToCook);
                }

                $pairs = $pairsQuery->get();

                if ($pairs->isEmpty()) {
                    DB::commit();
                    $this->line("  - no log_events2 data, skipped");
                    continue;
                }

                // 2) Delete existing historic rows for that day (avoid duplicates)
                // We delete per pair to keep it simple and safe.
                // (You could also delete broader by date + type list.)
                foreach ($pairs as $p) {
                    $del = DB::table('historic_events')
                        ->where('sensor', $p->sensor_id)
                        ->where('type', $p->type)
                        ->where('date', $dayStart->toDateString())
                        ->delete();
                }

                // 3) Build a subquery that finds the *last time_bucket* per sensor_id+type that day
                // Then join back to log_events2 to get value at that last time.
                // Also join sensors to get sn.
                //
                // Note: use MAX(time_bucket) because time_bucket is the primary key piece and represents chronological buckets.
                $lastTimes = DB::table('log_events2')
                    ->selectRaw('sensor_id, type, MAX(time_bucket) AS last_time_bucket')
                    ->where('time_bucket', '>=', $dayStart->format('Y-m-d H:i:s'))
                    ->where('time_bucket', '<',  $dayEnd->format('Y-m-d H:i:s'))
                    ->groupBy('sensor_id', 'type');

                if (is_array($typesToCook)) {
                    $lastTimes->whereIn('type', $typesToCook);
                }

                // 4) Insert into historic_events (one row per sensor_id+type)
                // We use insertUsing for efficiency.
                $insertQuery = DB::table(DB::raw('(' . $lastTimes->toSql() . ') AS lt'))
                    ->mergeBindings($lastTimes)
                    ->join('log_events2 as le', function ($join) {
                        $join->on('le.sensor_id', '=', 'lt.sensor_id')
                            ->on('le.type', '=', 'lt.type')
                            ->on('le.time_bucket', '=', 'lt.last_time_bucket');
                    })
                    ->join('sensors as s', 's.id', '=', 'le.sensor_id')
                    ->selectRaw(
                        'le.sensor_id as sensor, s.sn as sn, le.type as type, ? as date, le.value as value',
                        [$dayStart->toDateString()]
                    );

                // Do the insert.
                // If you want upsert instead (in case of race), you can later switch to upsert logic.
                $inserted = DB::table('historic_events')
                    ->insertUsing(['sensor', 'sn', 'type', 'date', 'value'], $insertQuery);

                // insertUsing returns bool, not count. We'll count via expected rows:
                $expectedCount = DB::table(DB::raw('(' . $lastTimes->toSql() . ') AS x'))
                    ->mergeBindings($lastTimes)
                    ->count();

                $totalInserted += $expectedCount;

                DB::commit();
                $this->line("  - inserted approx {$expectedCount} rows");
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("  - failed: " . $e->getMessage());
                throw $e;
            }
        }

        $this->info("Done. Inserted approx {$totalInserted} rows.");
        return 0;
    }
}
