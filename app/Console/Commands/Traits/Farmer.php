<?php

namespace App\Console\Commands\Traits;

use App\Helpers;
use App\Models\Account;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait Farmer
{
    /**
     *  Execute farming
     * @param callable $callback
     * @return void
     */
    public function farm($callback)
    {
        Cache::lock($this->signature)->get(function () use ($callback) {
            /** Start Date */
            $startDate = now();

            /** Run Callback */
            $result = call_user_func($callback);

            /** End Date */
            $endDate = now();

            if ($result !== false) {
                /** Send Message */
                Helpers::sendFarmingCompletedMessage(
                    $this->getKey(),
                    $startDate,
                    $endDate
                );
            }
        });
    }

    /** Get Base Headers */
    protected function getBaseHeaders()
    {
        return [
            'Origin' => $this->origin,
            'Referer' => $this->origin . '/',
            'X-Requested-With' => 'org.telegram.messenger'
        ];
    }

    /**
     * Get Account API
     * @param \App\Models\Account $account
     * @return \Illuminate\Http\Client\PendingRequest
     */
    protected function getApi(Account $account)
    {
        return Http::withHeaders($account->headers)
            ->withHeaders(
                $this->getBaseHeaders()
            )
            ->withUserAgent(
                $account->getUserAgent()
            );
    }

    /**
     * Get Base Account API
     * @param \App\Models\Account $account
     * @return \Illuminate\Http\Client\PendingRequest
     */
    protected function getBaseApi(Account $account)
    {
        return Http::withHeaders(
            $this->getBaseHeaders()
        )
            ->withUserAgent(
                $account->getUserAgent()
            );
    }


    /**
     * Log Farmer Error
     * @param \Throwable $e
     * @return void
     */
    protected function logError(\Throwable $e)
    {
        /** Farmer Title */
        $title = config('farmer.drops')[$this->getKey()]['title'];

        /** Log Error */
        Log::error($title . ' Error', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }

    /**
     * Get Farmer Key
     * @return string
     */
    protected function getKey()
    {
        return explode(':', $this->signature)[1];
    }
}
