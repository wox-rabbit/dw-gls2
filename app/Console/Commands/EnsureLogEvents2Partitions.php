<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnsureLogEvents2Partitions extends Command
{
    protected $signature = 'partitions:ensure-log-events2 {--weeks=4 : How many weeks ahead to ensure}';
    protected $description = 'Ensure weekly ISO partitions exist for log_events2 (next N weeks)';

    public function handle(): int
    {
        $table = 'log_events2';
        $weeksAhead = max(1, (int) $this->option('weeks'));

        // Use UTC consistently (your data is UTC).
        $now = CarbonImmutable::now('Europe/Amsterdam');

        // ISO week starts Monday. This gets current week's Monday 00:00:00 UTC.
        $startMonday = $now->startOfWeek(CarbonImmutable::MONDAY)->startOfDay();

        // Fetch existing partitions (names) for this table.
        $existing = DB::table('information_schema.PARTITIONS')
            ->select('PARTITION_NAME')
            ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
            ->where('TABLE_NAME', $table)
            ->whereNotNull('PARTITION_NAME')
            ->pluck('PARTITION_NAME')
            ->map(fn ($n) => (string) $n)
            ->all();

        $existingSet = array_flip($existing);

        // Ensure pmax exists (your migration should create it, but be defensive).
        if (!isset($existingSet['pmax'])) {
            $this->error("Partition pmax is missing on {$table}. Create it first (initial partitioning must exist).");
            return self::FAILURE;
        }

        $added = 0;

        // For each ISO week, create partition for [weekStart, weekEnd) boundary weekEnd.
        for ($i = 0; $i < $weeksAhead; $i++) {
            $weekStart = $startMonday->addWeeks($i);
            $weekEnd   = $startMonday->addWeeks($i + 1);

            $pname = sprintf('p%04dW%02d', (int) $weekStart->format('o'), (int) $weekStart->format('W'));

            if (isset($existingSet[$pname])) {
                continue;
            }

            // Add partition by splitting pmax into (newPartition, pmax) using REORGANIZE.
            // This is the typical way to "append" range partitions safely.
            $sql = sprintf(
                "ALTER TABLE `%s` REORGANIZE PARTITION `pmax` INTO (
                    PARTITION `%s` VALUES LESS THAN ('%s'),
                    PARTITION `pmax` VALUES LESS THAN (MAXVALUE)
                )",
                $table,
                $pname,
                $weekEnd->format('Y-m-d H:i:s')
            );

            DB::statement($sql);

            $this->info("Added partition {$pname} (< {$weekEnd->format('Y-m-d H:i:s')} UTC)");
            $added++;
        }

        $this->info("Done. Added {$added} partition(s).");
        return self::SUCCESS;
    }
}
