<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class PaymentBCAController extends Controller
{
   

    /**
     * Validate signature from BCA API request
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateSignature(Request $request)
    {
        try {
            // Ambil header dari request
            $clientId = $request->header('X-CLIENT-KEY');
            $signature = $request->header('X-SIGNATURE');
            $timeStamp = $request->header('X-TIMESTAMP');
            

            if (!$clientId || !$timeStamp || !$signature) {
                return response()->json(['message' => 'Missing required headers'], 400);
            }

            // Public key (sebaiknya diambil dari .env)
            // $publicKey = "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAroD08oxYa+AQjhik/VguGFfiUf7Ga3Nxf/jOlNHy5qhyIKr56j9lTVD8yKx9Qm0sceMfp7lyC6PNyQnyp3jZj14F59FsIC4Sm1dtETZ21ODV9/No+YzVj0eo/1zMtSim9HW6ukVUSPoXa2618XBbtwFN4qqOkjdcZLGsn5KYdr7SGnjZKe/KDyZsGHPQSZXATXKDUpcqU56zx2ku+Adlv/vdOrem60mWg6PSy8i3FOI1NXgoNJodD9hVfHZu8SwkepBAfKCqDfkHL2CuVKvzOVSHjpD6HbACwZ/lmavEF89S/nhdxN7sVL122jzlssbEx+6/Id/DR2QS66z8c6QWWwIDAQAB";
            $publicKey = env('BCA_PUBLIC_KEY');
            // Validasi signature
            $isValid = $this->validateOauthSignature($publicKey, $clientId, $timeStamp, $signature);

            if ($isValid) {
                return response()->json(['message' => 'Valid signature'], 200);
            }

            return response()->json(['message' => 'Invalid signature'], 401);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Validasi OAuth Signature
     *
     * @param string $publicKey Public key untuk validasi
     * @param string $clientId Client ID dari header
     * @param string $timeStamp x-time dari header
     * @param string $signature Signature dari header
     * @return bool True jika valid, false jika tidak
     * @throws Exception
     */
    public function validateOauthSignature($public_key_str, $client_id, $iso_time, $signature)
    {
        $is_valid = false;
        $public_key = <<<EOF
-----BEGIN PUBLIC KEY-----
$public_key_str
-----END PUBLIC KEY-----
EOF;
        $algo = "SHA256";
        $dataToSign = $client_id . "|" . $iso_time;
        $is_valid = openssl_verify($dataToSign, base64_decode($signature), $public_key, $algo);
        //$is_valid = openssl_verify($dataToSign, hex2bin($signature), $public_key, $algo);
        if($is_valid == 1){
            $is_valid =  true;
        }
        return $is_valid;
    }
   
    

}
