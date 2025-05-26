<?php

namespace App\Console\Commands\Traits;

use App\Helpers;
use App\Models\Farmer;
use Illuminate\Support\Facades\Log;

trait FarmerTrait
{
    /**
     *  Execute farming
     * @param callable $callback
     * @return void
     */
    public function farm($callback)
    {
        try {
            /** Start Date */
            $startDate = now();

            /** Run Callback */
            $result = call_user_func($callback);

            if ($result !== false) {
                /** End Date */
                $endDate = now();

                /** Send Message */
                Helpers::sendFarmingCompletedMessage(
                    $this->getKey(),
                    $startDate,
                    $endDate
                );
            }
        } catch (\Throwable $e) {
            $this->logError($e);
        }
    }


    /**
     * Log Farmer Error
     * @param \Throwable $e
     * @return void
     */
    protected function logError(\Throwable $e)
    {
        /** Log Error */
        Log::error($this->getTitle() . ' Error', [
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

    /** Get Title */
    protected function getTitle()
    {
        return $this->getConfig()['title'];
    }

    /** Get Config */
    protected function getConfig()
    {
        return config('farmer.drops')[$this->getKey()];
    }


    /**
     * Get Base Farmers
     * @return \Illuminate\Database\Eloquent\Builder<Farmer>
     */
    protected function getBaseFarmers()
    {
        return Farmer::farmer($this->getKey())
            ->subscribed()
            ->connected();
    }

    /**
     * Get Farmers
     */
    protected function getFarmers()
    {
        return $this->getBaseFarmers()
            ->get();
    }
}