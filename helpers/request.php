<?php

if (!defined('ib_req_helpers')) {
    define('ib_req_helpers', 1);

    function ib_get_json($url, $headers = [])
    {
        return ib_json_req($url, "GET", [], $headers);
    }

    function ib_post_json($url, $data = [], $headers = [])
    {
        return ib_json_req($url, "POST", $data, $headers);
    }

    function ib_json_req($url, $method, array $data = [], array $headers = [])
    {
        $headers = array_merge([
            "cache-control" => "no-cache",
            "content-type" => "application/json",
            "accept" => "application/json"
        ], $headers);

        $compact_headers = [];
        foreach ($headers as $key => $val) {
            $compact_headers[] = $key . ": " . $val;
        }

        $jsonString = $method === "GET" ? "" : json_encode($data);

        return json_decode(ib_req($url, $method, $jsonString, $compact_headers));
    }

    function ib_req($url, $method, $json_string = "", $headers = []) : string
    {

        $curl = curl_init();

        $conf = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $json_string,
            CURLOPT_HTTPHEADER => $headers
        ];

        curl_setopt_array($curl, $conf);

        $response = curl_exec($curl);
        $info = curl_getinfo($curl);
        $err = curl_error($curl);
        curl_close($curl);

        $status = $info["http_code"];

        if ($status >= 200 && $status < 300) {
            return $response;
        }

        throw new \Exception("Request exception: $method '$url'. Status: $status. Error: $err");

    }


}