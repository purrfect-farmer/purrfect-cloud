<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\FarmerTrait;
use App\Farmers\GoldEagleFarmer;
use App\Helpers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FarmGoldEagle extends Command
{
    use FarmerTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farm:gold-eagle';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Farm Gold Eagle Automatically';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->farm(function () {
            /** Check Script */
            if (!$this->checkScript())
                return false;

            /** Process Farmers */
            $this->getFarmers()->mapConcurrently(fn($farmer) => GoldEagleFarmer::farm($farmer));
        });
    }

    /**
     * Check Script
     * @return boolean|null
     */
    protected function checkScript()
    {
        try {
            $config = Http::throw()->get("https://raw.githubusercontent.com/purrfect-farmer/purrfect-data/main/config.json")->json();
            $index = $config['gold-eagle']['index'];
            $script = Helpers::findDropMainScript('https://telegram.geagle.online', $index);
            $hasNotifiedDev = Cache::has('error-notice:gold-eagle');
            if (!$script) {
                if (!$hasNotifiedDev) {
                    /** Cache */
                    Cache::forever('error-notice:gold-eagle', true);

                    /** Send  */
                    Helpers::sendCloudFarmerMessage(
                        'error-notice:gold-eagle',
                        [
                            "<b>🥇 Gold Eagle Farmer</b>",
                            "<i>❌ Status: Broken</i>",
                            "<b>🗓️ Detected At</b>: " . now(),
                        ],
                        [
                            'message_thread_id' => config('farmer.error_thread_id'),
                            'disable_notification' => false,
                        ]
                    );
                }
                return;
            }

            /** Remove from cache if resolved */
            if ($hasNotifiedDev) {
                Cache::delete('error-notice:gold-eagle');
            }

            return true;
        } catch (\Throwable $e) {
            /** Log Error */
            $this->logError($e);
        }
    }
}