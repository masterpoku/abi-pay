<?php
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, 'https://devapi.klikbca.com/api/bca/v1.0/access-token/b2b');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, "{\"grantType\":\"client_credentials\"}");

$headers = array();
$headers[] = 'Host: devapi.klikbca.com';
$headers[] = 'Max-Forwards: 19';
$headers[] = 'Via: 1.1  (), 1.1  ()';
$headers[] = 'Accept: text/html, image/gif, image/jpeg, */*; q=.2';
$headers[] = 'Ecid-Context: 1.006AUQ8XZmrBt1_5pRWByY005luu001Iep;kXhgv0XIVVNLCKIHVKHM5VML5JGLFINN1ODDs9DEn4DDt2C';
$headers[] = 'User-Agent: Jersey/2.22.4 (HttpUrlConnection 1.8.0_411)';
$headers[] = 'X-Api-Hashcode: Id-22c768670a0816039c446e3d';
$headers[] = 'X-Client-Key: 03697a86-9ce0-4b17-ad93-1b89ccace372';
$headers[] = 'X-Signature: hMVm3KutZg0rWJNhBzfW7gg7BjNITmG6twQdDgbSBaQ2MpxM6HHhnxNMjuohu0wBgmzI0M0Y/vHwD4Vpn/T7poX3hHyBjJUd2zHnI+Env7BRNU00lff5nz82aOqwWHInluybfnAsxQPy19D+INJ4AdEUbkAQ1Icf0sPiPJqPf/fw+kihrTeuJ+yeVpkccU0BWGYsWcnU8xqNpQi8nBgLtTaQFsMvkBO/bAQFlKp9L0w+tKUIPfaLEJZ3MGp9Hd4IkwQt2X9Oy+YyZpSeg775sE/vFSEPNo9FR7LTbXEVBIXyacHLtPGMTyDL/UgJtvtLD7EaJX0fzmkAI/CcgUerJQ==';
$headers[] = 'X-Timestamp: 2024-12-23T09:12:49+07:00';
$headers[] = 'Content-Length: 34';
$headers[] = 'Connection: close';
$headers[] = 'X-Correlationid: Id-22c76867a7434852a49f2b50 1';
$headers[] = 'Content-Type: application/json';
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$result = curl_exec($ch);
if (curl_errno($ch)) {
    echo 'Error:' . curl_error($ch);
}
curl_close($ch);

print_r($result);
