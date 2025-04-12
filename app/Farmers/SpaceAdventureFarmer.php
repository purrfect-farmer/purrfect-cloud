<?php

namespace App\Farmers;

use App\Models\Farmer;
use Closure;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;

class SpaceAdventureFarmer
{
    protected $cookies = [];
    protected $setSignature = true;
    protected $setCookies = true;

    public function __construct(
        protected Farmer $farmer,
        protected Closure $getBaseApi,
    ) {
    }

    /**
     * Fetch CSRF Token
     * @return static
     */
    public function fetchCSRFToken()
    {
        $this->withoutCookies()
            ->withoutSignature()
            ->makeRequest(
                fn(PendingRequest $api) => $api->get('https://space-adventure.online/sanctum/csrf-cookie')
            );
        return $this;
    }

    /**
     * Without Cookies
     * @return static
     */
    public function withoutCookies()
    {
        $this->setCookies = false;
        return $this;
    }

    /**
     * Without Signature
     * @return static
     */
    public function withoutSignature()
    {
        $this->setSignature = false;
        return $this;
    }

    /**
     * Make Request
     * @param Closure $callback
     * @return \Illuminate\Http\Client\Response
     */
    public function makeRequest($callback)
    {
        /** Get Callback */
        $getBaseApi = $this->getBaseApi;

        /** @var \Illuminate\Http\Client\PendingRequest Base API */
        $api = $getBaseApi($this->farmer);

        /** Set Cookie and XSRF */
        if ($this->setCookies) {
            $api->withCookies($this->cookies, '.space-adventure.online')
                ->withHeaders(['x-xsrf-token' => $this->cookies['XSRF-TOKEN'] ?? '']);

        }

        /** Set Signature */
        if ($this->setSignature) {
            $headers = $this->getSignatureHeaders(
                timestamp: strval(time()),
                authId: strval($this->farmer->user_id),
                accessToken: explode(" ", $this->farmer->headers['Authorization'])[1],
                xsrf: $this->cookies['XSRF-TOKEN'] ?? '',
                uuid: Str::uuid(),
            );
            $api->withHeaders($headers);
        }

        /** @var Response Get Response */
        $response = $callback($api);

        /** Update Cookies */
        $this->cookies = $this->extractCookies($response);

        /** Reset */
        $this->setCookies = true;
        $this->setSignature = true;

        return $response;
    }

    /**
     * Make Authenticated Request
     * @param Closure $callback
     * @return \Illuminate\Http\Client\Response
     */
    public function makeAuthRequest($callback)
    {
        return $this->makeRequest(fn(PendingRequest $api) => $callback(
            $api->withHeaders($this->farmer->headers)
        ));
    }

    /**
     * Extract Response
     * @param \Illuminate\Http\Client\Response $response
     * @return array
     */
    protected function extractCookies(Response $response)
    {
        return collect($response->cookies()->toArray())
            ->mapWithKeys(fn($item) => [
                $item['Name'] => urldecode($item['Value']),
            ])
            ->all();
    }

    /**
     * Get Signature Headers
     * @param string $timestamp
     * @param string $authId
     * @param string $accessToken
     * @param string $xsrf
     * @param string $uuid
     * @return array{x-auth-id: string, x-nonce: string, x-signature: string, x-timestamp: string, x-xsrf-sign: string, x-xsrf-token: string}
     */
    protected function getSignatureHeaders(
        $timestamp,
        $authId,
        $accessToken,
        $xsrf,
        $uuid,
    ) {
        $nonce = $uuid . '-' . $timestamp;
        $sign = $this->getXSRFSign($xsrf, $timestamp);

        $data = implode(":", [$timestamp, $accessToken, $nonce, $timestamp, $sign]);
        $signature = hash(
            "sha256",
            $data
        );

        return [
            'x-auth-id' => $authId,
            'x-timestamp' => $timestamp,
            'x-nonce' => $nonce,
            'x-xsrf-sign' => $sign,
            'x-signature' => $signature,
        ];
    }

    /**
     * Get XSRF Sign
     * @param string $xsrf
     * @param string $timestamp
     * @return string
     */
    protected function getXSRFSign($xsrf, $timestamp)
    {
        $half = floor(strlen($xsrf) / 2);
        $first = substr($xsrf, 0, $half);
        $second = substr($xsrf, $half);
        return hash("sha256", $first . $timestamp . $second);
    }
}