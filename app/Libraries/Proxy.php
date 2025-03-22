<?php

namespace App\Libraries;

use App\Helpers;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Proxy
{
    public function __construct() {}

    /**
     * Get List
     */
    public function list()
    {
        return Cache::remember('proxies', now()->addHour(), function () {
            return $this->fetchList();
        });
    }

    /**
     * Get Unique Proxy
     * @param int $seed
     * @return array|null
     */
    public function getUnique(int $seed)
    {
        return Helpers::getUniqueItem(
            $this->list(),
            $seed
        );
    }

    /** Fetch List */
    public function fetchList()
    {
        try {
            $response = Http::throw()
                ->withHeader('Authorization', 'Token ' . config('farmer.proxy.api_key'))
                ->get('https://proxy.webshare.io/api/v2/proxy/list', [
                    'mode' => 'direct',
                    'valid' => true,
                    'page' => 1,
                    'page_size' => 100
                ])
                ->json();

            $proxies = collect($response['results'])->map(
                fn($item) => $item['username'] . ':' . $item['password'] . '@' . $item['proxy_address'] . ':' . $item['port']
            )->all();

            return $proxies;
        } catch (\Throwable $e) {
            Log::error('Fetching Proxies', [
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }


    /**
     * Update List
     * @return void
     */
    public function updateList()
    {
        Cache::put('proxies', $this->fetchList(), now()->addHour());
    }
}
