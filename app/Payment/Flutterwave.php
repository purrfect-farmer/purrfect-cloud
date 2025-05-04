<?php

namespace App\Payment;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Flutterwave
{

    public function __construct(
        protected string $publicKey = '',
        protected string $secretKey = '',
        protected string $encryptionKey = '',
    ) {
    }

    /** Get http client */
    protected function getClient()
    {
        return Http::throw()->baseUrl('https://api.flutterwave.com/v3')->replaceHeaders(
            ['Authorization' => 'Bearer ' . $this->secretKey]
        );
    }

    /**
     * Initialize Transaction
     * @param array $data
     * @return array
     */
    public function initialize($data)
    {
        return $this->getClient()->post('payments', array_merge($data, [
            'tx_ref' => Str::random(),
        ]))->json('data');
    }


    /**
     * Verify Transaction
     * @param string $reference
     * @return array
     */
    public function verify($reference)
    {
        return $this->getClient()
            ->get('transactions/verify_by_reference', ['tx_ref' => $reference])
            ->json('data');
    }




    /** Compute Hash */
    public function computeHash(string $data = '', string $key = '')
    {
        return hash_hmac('sha512', $data, $key ?: $this->secretKey);
    }
}
