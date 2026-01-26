<?php

namespace App\Console\Commands;

use App\Jobs\ProcessMqttMessage;
use Illuminate\Console\Command;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

class ActiveWattchMqqt extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gls2:aw-mqqt';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listens to ActiveWattch MQQT messages';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $server   = 'a3h328npczvy2s-ats.iot.eu-central-1.amazonaws.com';
        $port     = 8883;
        $clientId = 'datawattch-gls-02';

        $connectionSettings = (new ConnectionSettings)
            ->setUseTls(true)
            ->setTlsCertificateAuthorityFile(base_path('mqqt_certs/aw-test/AmazonRootCA1.pem'))
            ->setTlsClientCertificateFile(base_path('mqqt_certs/aw-test/aw-test.crt'))
            ->setTlsClientCertificateKeyFile(base_path('mqqt_certs/aw-test/aw-test.key'))
            ->setKeepAliveInterval(10);

        $mqtt = new MqttClient($server, $port, $clientId);
        $this->line("Connecting...");
        $mqtt->connect($connectionSettings, true);
        $this->info("Connected to MQTT server at $server:$port with client ID $clientId");
        $mqtt->subscribe('controller/#', function (string $topic, string $message) {
            $this->line("Received message on topic [$topic]: $message");
            ProcessMqttMessage::dispatch($topic, $message);
        });

        $mqtt->loop();
        $mqtt->disconnect();
    }
}
