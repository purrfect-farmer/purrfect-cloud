<?php

namespace App\Libraries;

use App\Helpers;
use App\Models\Account;
use Illuminate\Support\Collection;
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

    /**
     *  Get Random Unused
     * @return string|null
     */
    public function getRandomUnused()
    {
        /** Get Proxies */
        $proxies = Account::subscribed()->pluck('proxy');

        /** Available Proxies */
        $available = $this->getAvailable($proxies);

        return $available->isNotEmpty() ? $available->random() : null;
    }

    /** Get Available Proxies */
    public function getAvailable(Collection $proxies)
    {
        /** Get List */
        $list = collect($this->list());

        /** Available Proxies */
        $available = $list->filter(fn($proxy) => $proxies->doesntContain($proxy));

        return $available;
    }

    /** Fetch List */
    public function fetchList()
    {
        try {
            $response = Http::throw()
                ->withHeader(
                    'Authorization',
                    'Token ' . config('farmer.proxy.api_key')
                )
                ->get('https://proxy.webshare.io/api/v2/proxy/list', [
                    'mode' => 'direct',
                    'valid' => true,
                    'page' => config('farmer.proxy.page'),
                    'page_size' => config('farmer.proxy.page_size')
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
