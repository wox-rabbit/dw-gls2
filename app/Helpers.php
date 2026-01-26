<?php

namespace App;

use GuzzleHttp\Client;

class Helpers
{
    static function pushToUhm(string $message)
    {
        $title = "DW GLS2";
        $client = new Client();
        $client->post('https://api.pushbullet.com/v2/pushes', [
            'headers' => [
                'Access-Token' => 'o.JsA3vxQq1jaTIrZi2W1m7egrjdfT62fp'
            ],
            'json' => [
                'type' => 'note',
                'title' => $title,
                'body' => $message,
                'direction' => 'self'
            ]
        ]);
    }
}
