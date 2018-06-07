<?php declare(strict_types=1);

namespace PN\River;

require_lib('http');

const HTTPC_DEFAULT_HEADERS = [
  'User-Agent' => 'pn.httpc/v0.1 (+https://pn.id.lv)',
];

function httpc_request($method, $url, $headers = [ ], $data = null) {
  $ch = curl_init();

  $out_headers = [ ];

  $_headers = [ ];
  $headers += HTTPC_DEFAULT_HEADERS;
  foreach ($headers as $key => $value) {
    $_headers[] = "{$key}: {$value}";
  }

  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_URL => $url,
    CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_HTTPHEADER => $_headers,

    CURLOPT_HEADERFUNCTION =>
    function ($ch, $_header) use (&$out_headers) {
      $header = explode(':', $_header, 2);
      if (count($header) < 2) { goto done; }

      [ $name, $value ] = $header;
      $name = trim($name);
      $value = trim($value);

      $out_headers[$name] = $value;

    done:
      return strlen($_header);
    },
  ]);

  if (is_resource($data)) {
    curl_setopt($ch, CURLOPT_INFILE, $data);
  } else if (is_string($data) || is_array($data)) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
  }

  $resp = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

  $out_headers = new HeaderBag($out_headers);
  return [ $status, $out_headers, $resp ];
}

