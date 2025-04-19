<?php

namespace App\Payment;

use Illuminate\Support\Facades\Http;

class Paystack
{

    public function __construct(
        protected string $publicKey = '',
        protected string $secretKey = '',
    ) {
    }

    /** Get http client */
    protected function getClient()
    {
        return Http::throw()->baseUrl('https://api.paystack.co')->replaceHeaders(
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
        return $this->getClient()->post('transaction/initialize', [
            ...$data,
            'amount' => $data['amount'] * 100
        ])->json('data');
    }


    /**
     * Verify Transaction
     * @param string $reference
     * @return array
     */
    public function verify($reference)
    {
        return $this->getClient()->get('transaction/verify/' . $reference)->json('data');
    }




    /** Compute Hash */
    public function computeHash(string $data = '', string $key = '')
    {
        return hash_hmac('sha512', $data, $key ?: $this->secretKey);
    }
}
