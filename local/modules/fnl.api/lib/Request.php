<?php

namespace Fnl\Api;

class Request
{
    const url = 'http://api.2fnl.com/v1.2/';
    
    public static function request(string $method, $get = [], ?array $payload = [], string $type = 'GET')
    {
        $url = self::url.$method.'/';
        $curl = curl_init();
        $headers = [
            'Accept: */*',
            "cache-control: no-cache",
            "Accept-Encoding: gzip, deflate, br",
            "Connection: keep-alive"
        ];
        if ( ! empty($get)) {
            $url .= '?' . implode('&', $get);
        }
        if ($payload) {
            $payload = json_encode($payload);
        }
        
        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER         => false,
            CURLOPT_CUSTOMREQUEST  => $type,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_ENCODING       => 'gzip'
        ]);
        
        $response = curl_exec($curl);
        $headers = curl_getinfo($curl);
        
        if ($headers) {
            curl_close($curl);
            return json_decode($response, true);
        } else {
            return curl_error($curl);
        }
    }
}