<?php

namespace App\Jobs;

use App\Helpers;
use App\Models\Sensor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProcessMqttMessage implements ShouldQueue
{
    use Queueable;

    public string $topic;
    public string $message;

    static array $monitored = [
        '52-46629-386', // Veerstraat10
        '75-92886-667', // Molenhoek28
        '57-52439-867', // Vattenfall demo1
        '57-80596-296', // Molenhoek16
        '18-36063-221', // Kerkweg57
        '75-92886-667', // Molenhoek25

        '62-30039-471', //  leigraaf38
        '70-27576-655', //  markt70
        '10-26050-617', //  middelveld1
        '59-90414-895', //  markt62

        '61-47685-975', // olijfwilgstraat 18
        '63-99295-496',
        '66-54422-956',

        // Vattenfall IDs
        '82-39914-255', '46-29703-477', '86-11855-361', '80-53300-950', '31-10262-436', '55-13862-738', '46-61892-621',
        '60-49222-642', '41-81155-122', '86-15507-145', '31-61896-425', '11-89073-780', '30-86149-946', '59-66131-868',
        '26-52637-672', '87-86419-160', '93-12741-210', '94-97835-802', '85-46996-743', '46-60263-483', '10-64949-752',
        '86-64941-628', '67-85308-504', '55-52149-385', '48-77058-992', '39-28648-309', '19-22018-523', '27-27905-457',
        '96-59758-165', '52-27709-616', '40-95501-141', '19-67806-950', '59-87220-266', '29-32368-395', '98-34177-126',
    ];

    static array $translationTable = [
        "SOC" => "SOC",
        "SOH" => "SOH",
        "PBatW" => "PBA",
        "PpvW" => "PPV",
        "PoutW" => "POU",
        "PmeterW" => "PMT",
        "PboilerW" => "PBL",
        "PboilerWInst" => "PBL",
        "PbufferW" => "PBF",
        "EtoLoadWhCum" => "ELC",
        "EtoGridT1WhCum" => "EG1",
        "EtoGridT2WhCum" => "EG2",
        "EfromGridT1WhCum" => "FG1",
        "EfromGridT2WhCum" => "FG2",
        "EfromPVWhCum" => "FPV",
        "EtoBattWhCum" => "EBW",
        "EfromBattWhCum" => "FBW",
        "EtoBoilerWhCum" => "EBL",
        "EtoBuffer" => "EBF",
        "EtoBufferWhCum" => "EBF",
        "EfromGasm3Cum" => "FGC",
        "TinvC" => "TIC",
        "TbattC" => "TBC",
        "SinvState" => "SIS",
        "SbattState" => "SBS",
        "Sprio" => "SPR",
        "RemoteMode" => "REM",
        "GridFeed" => "GRF",
        "Battery" => "BAT",
        "Boiler" => "BOL",
        "Buffer" => "BUF"
    ];

    static array $conversions = [ // Mostly to convert Wh to kWh
        "EtoLoadWhCum" => 0.001,
        // "EtoGridT1WhCum" => 0.001,
        // "EtoGridT2WhCum" => 0.001,
        // "EfromGridT1WhCum" => 0.001,
        // "EfromGridT2WhCum" => 0.001, // 2025-08-13 Based on Database, these are already in Watthours not KiloWattHours
        "EfromPVWhCum" => 0.001,
        "EtoBattWhCum" => 0.001,
        "EfromBattWhCum" => 0.001,
        "EtoBoilerWhCum" => 0.001,
    ];

    /**
     * Create a new job instance.
     */
    public function __construct(string $topic, string $message)
    {
        $this->topic = $topic;
        $this->message = $message;
    }

    /**
     * Get 5-minute time bucket start in Europe/Amsterdam.
     *
     * @param int $unixTimestamp Seconds since epoch (UTC)
     * @return Carbon Bucket start (Europe/Amsterdam timezone)
     */
    function timeBucketAmsterdam(int $unixTimestamp): Carbon
    {
        return Carbon::createFromTimestampUTC($unixTimestamp)
            ->setTimezone('Europe/Amsterdam')
            ->floorMinutes(5)
            ->second(0);
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        // Filter out messages not meant for us:
        $admin_id = Str::after($this->topic, 'controller/');
        if (!in_array($admin_id, self::$monitored)) return;

        // Append the message to mqqt-raw.txt
        $filePath = base_path('mqqt-raw.txt');
        file_put_contents($filePath, "{$this->topic}\t{$this->message}\n", FILE_APPEND);

        // Decode JSON message
        $data = json_decode($this->message, true);

        // Check if Sensor Exists, create if not
        $sensor = Sensor::firstOrCreate(
            [
                'sn' => $admin_id,
                'deviceType' => 'ActiveWattch',
            ],
            [
                'registrationIP' => '127.0.0.1',
                'etc' => null,
            ]
        );

        // Prepare log rows
        $now = \Carbon\Carbon::now();
        $logRows = [];
        $logRows2 = [];

        // Override $now if $message['timestamp'] exists
        if (isset($data['timestamp'])) $now = \Carbon\Carbon::createFromTimestamp($data['timestamp']);

        // Log every 5 mins to file for Vattenfall demo
        // 2026-01-26 <DEPRECATION NOTICE> This is no longer needed, as we have proper DB storage and reporting now.
        if ($this->topic === 'controller/57-52439-867') {
            // Logs every request
            $filePath = public_path("vf/$admin_id.txt");
            $columns = @json_decode($this->message, true);
            $dt = Carbon::createFromTimestamp($columns['timestamp'], 'Europe/Amsterdam')->format('d-m-Y H:i:s');
            $logLine = "{$this->topic},{$dt},{$columns['PboilerW']},{$columns['EtoBoilerWhCum']}";
            file_put_contents(
                $filePath,
                "$logLine\n",
                FILE_APPEND
            );
        }
        // </DEPRECATION NOTICE>

        // Write a JSON entry to {$admin_id}.txt in public/mqtt
        $filePath = public_path("mqtt/{$admin_id}.txt");
        $columns = @json_decode($this->message, true);
        $dt = Carbon::createFromTimestamp($columns['timestamp'], 'Europe/Amsterdam')->format('Y-m-d H:i:s');
        $logLine = json_encode([
            'topic' => $this->topic,
            'timestamp' => $dt,
            'data' => $columns,
        ]);
        file_put_contents(
            $filePath,
            "$logLine\n",
            FILE_APPEND
        );


        // 2026-01-26 We're now dual logging it (LogEvents can be phased out actually)
        $saveToLogEvents = true;
        $saveToLogEvents2 = true;

        // Only save to DB if no data saved within past 14 minutes
        if ($sensor->last_package) {
            $lastPackageTime = strtotime($sensor->last_package);
            $nowTime = time();
            $diffInMinutes = ($nowTime - $lastPackageTime) / 60;
            if ($diffInMinutes < 14) {
                $saveToLogEvents = false;
            }
        }

        $smartMeterData_isComplete = (!empty($data['EtoGridT1WhCum']) && !empty($data['EtoGridT2WhCum']) && !empty($data['EfromGridT1WhCum']) && !empty($data['EfromGridT2WhCum']));
        foreach ($data as $metricKey => $metricValue) {

            // Skip unknown keys
            if (!isset(self::$translationTable[$metricKey])) continue;
            $type = self::$translationTable[$metricKey];

            // Skip some metrics if smart meter data is incomplete
            if (!$smartMeterData_isComplete) {
                if (in_array($type, ['EG1', 'EG2', 'FG1', 'FG2'])) {
                    continue;
                }
            }

            $conversion_rate = self::$conversions[$metricKey] ?? 1;
            $logRows[] = [
                'sensor' => $sensor->id,
                'sn' => $sensor->sn,
                'type' => $type,
                'time' => $now,
                'value' => $metricValue * $conversion_rate,
            ];

            $logRows2[] = [
                'sensor_id' => $sensor->id,
                'type' => $type,
                'value' => $metricValue * $conversion_rate,
                'time_bucket' => $this->timeBucketAmsterdam($data['timestamp']),
                'time_true' => $now,
            ];
        }

        // Add virtual 'NET' value that's EG1+EG2-FG1-FG2 !empty
        if ($smartMeterData_isComplete) {
            $netValue = ($data['EfromGridT1WhCum'] + $data['EfromGridT2WhCum']) - ($data['EtoGridT1WhCum'] + $data['EtoGridT2WhCum']);
            $logRows[] = [
                'sensor' => $sensor->id,
                'sn' => $sensor->sn,
                'type' => 'NET',
                'time' => $now,
                'value' => $netValue,
            ];

            $logRows2[] = [
                'sensor_id' => $sensor->id,
                'type' => 'NET',
                'value' => $netValue,
                'time_bucket' => $this->timeBucketAmsterdam($data['timestamp']),
                'time_true' => $now,
            ];
        }

        // Bulk insert
        if ($saveToLogEvents) DB::table('log_events')->insert($logRows);
        if ($saveToLogEvents2) DB::table('log_events2')->insert($logRows2);

        // Update last package time
        $sensor->last_package = $now;
        $sensor->save();
    }

    public function tags(): array
    {
        return ['mqtt', 'aw:' . $this->topic];
    }
}
