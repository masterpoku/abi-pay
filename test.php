<?php

// Variabel utama

use function PHPUnit\Framework\returnSelf;

$privateKey = "
-----BEGIN PRIVATE KEY-----
MIIEvwIBADANBgkqhkiG9w0BAQEFAASCBKkwggSlAgEAAoIBAQC1q0icxg2vgljuXjpIPpUnWAL9NsRWtFMV4FFHrS2iC/1S7D8M1n1b9di7C5TYvNqcolItLhA06XMgm7VF61pZHi8CCiymWza9PvU79DfC9WNFdBpgSxai4dFCVxYAM8du3VcflT4qYPbQvHVYdyzCAEl2/6fecVskp3Jypvs
ZvmaOs53PFdIpoEQIcF3YK+Xg4WGikNEugGnOFSgo60Wd96Js6Ro4QNdgmUPl7uvrHJ6bSncN/Ilwf1eLGL6bU04lgMFmHwKdIAaEQuZYLjMN1pNoCLDFFdJ/YAUoy03604V/IXNiKRgrR9uGSI8KYF7wJNn0y76c6X6Dpb8fa+/DAgMBAAECggEBAJf8hdJLS/XS2m4KPT5lxUlWM6H+qMJVON
GrirSpqOzSlQxEA/fMlrJR+xF5ffzZ+xdiIdgUmpB54sycGEs3vK2kN/W/510CIMixHGAdUG11+KiJmuuGxphczkJvM0PWDfqtiQ8uQAUafENj99ScV8CylsPM3XeXZIZE5NYQ5zDAFVgY5a8DuhHaIJI19jdmP4nb1S7CFylRIQ8PGA+kU/hB5TUZXhtr6Iv2nRNcM01wev6AB8gz0qI9pOkLA
gSIPnfBDl9gIpVWtt4tWedWoXR3W59KIgG1p6YIb/lDAeJSS+nYV4dbs+1zh5Lhl5WyanA1bqQoU1T+VEEx9xb8v/ECgYEA6nPkl7ezmjdrpin/vLQ8E9yyqI2sqw7kIv4AqLKiaKIS5pkZ/3jv9fLXNDlOz2MPPThefHWxv4Il/Cbf6JkCuJ71yajNCLw0OLiYJSYGC1Nuj4buG6oWOdI/xYAL
eEfdvnu1atkgGMHx1xhreuspl3gGGIp85I6qf89/o8aMetsCgYEAxl2E5OUMnGORxshF/7l8FEw/bIxaQNLt2RkrOfDiDpSm3dBCASSnN2MDh9eTKTpy5KSnGX0uRVt/FRJ8XUTtVEQeSkD8QvraCFA9RPtNXlsQn7hhpOk1iVEHQB9xESdCya3B7ab42vzGBMXSwf8XQMcioa1JLmaYfFYc6E4
hTzkCgYEAqV9aB+TVIhbhdOQodTm7oRmyE6Rt1hHm7ASVk0mhnHdhsiduqanDqOlrYLX54kaM7sw3LjCUXWZ3bIblARLw7VEg/TMuFB5ql4N7nnKusSXv3E48281vSwxBt7s+DgHVBtQ2Bl+fGWObA6oHk4ApxtwVg0sg2LjcIYNUkYtRVzsCgYAkVI55aaX0opvZX2bKnksmYIyhMdd51efwAh
cTppWQfBNPvsvH79GcaEsGPypZu7W9QJbGKVInK8nLrzYN0wjwjQVLLjnFfrIeIawHDUuvQ1h5GEjx7jB69NcyHFAWBy3JSESjZRhg6zjNOPoPw8ubdp1WJSmpEOtOomrq9RxOqQKBgQDS2Z1PvHLaAzkWkKTwRMprbvYm/EYchvQRiY6OKWBVUEzuFfw2n/oI+yDr9PVwC2jjMMrsHXC86afxB
y8n6LJ6Wf4DaZvigpbmDOxPGzBHExriuQKm6aQEOqKL4afiY7Ex1HdnlE3HYm9qp/MRgWaNtBb0P3d6BpRtDXeT0Flx+A==
-----END PRIVATE KEY-----
"; // Ganti dengan private key Anda yang valid
$clientId = "b66925de-d8ec-476e-a170-6cf06c863b78"; // Client ID Anda
$timestamp = gmdate("Y-m-d\TH:i:s\Z"); // Timestamp ISO-8601

// StringToSign
$stringToSign = $clientId . "|" . $timestamp;

// Generate Signature menggunakan SHA256withRSA
$privateKeyResource = openssl_pkey_get_private($privateKey);
if (!$privateKeyResource) {
    die("Invalid private key");
}

openssl_sign($stringToSign, $signature, $privateKeyResource, OPENSSL_ALGO_SHA256);
openssl_free_key($privateKeyResource);

// Encode signature ke Base64
$base64Signature = base64_encode($signature);

// Headers untuk request
$headers = [
    "X-TIMESTAMP: $timestamp",
    "X-CLIENT-KEY: $clientId",
    "X-SIGNATURE: $base64Signature",
    "Content-Type: application/json"
];




print_r($base64Signature);

// Body request
$requestBody = json_encode([
    "grantType" => "client_credentials"
]);

// URL endpoint
$url = "https://devapi.klikbca.com/openapi/v1.0/access-token/b2b";

// CURL request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);

// Eksekusi request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Output response
echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";
