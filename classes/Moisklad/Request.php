<?php

namespace Classes\Moisklad;

class Request extends ExportController
{
    const username = '';
    const password = '';
    
    public static function request($method, $get = [], $payload = [], $type = 'POST')
    {
        $method = str_replace('https://api.moysklad.ru/api/remap/1.2/entity/', '', $method);
        $url = 'https://api.moysklad.ru/api/remap/1.2/entity/' . $method;
        
        if ( ! empty($get)) {
            $url .= '?' . implode('&', $get);
        }
        
        $curl = curl_init();
        
        $headers = [
            'Content-Type: application/json',
            "cache-control: no-cache",
            "Accept-Encoding: gzip"
        ];
        
        curl_setopt_array($curl, array(
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERPWD        => self::username . ":" . self::password,
            CURLOPT_ENCODING => 'gzip'
        ));
        
        if ( ! empty($payload)) {
            $payload = json_encode($payload);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $type);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        }
        
        $response = curl_exec($curl);
        if ($response) {
            curl_close($curl);
            return json_decode($response, true);
        } else {
            return curl_error($curl);
        }
    }
}