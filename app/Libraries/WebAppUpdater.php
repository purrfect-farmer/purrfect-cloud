<?php
namespace App\Libraries;

use App\Models\Account;
use App\Models\Farmer;
use Illuminate\Support\Facades\Log;

class WebAppUpdater
{
    public function __construct(protected Account $account)
    {
    }

    public function process()
    {
        try {
            $api = TelegramClient::session($this->account->session_id);
            try {
                /** Update Farmers */
                $this->account->farmers->each(function (Farmer $farmer) use ($api) {
                    /** Get Config */
                    $config = config('farmer.drops')[$farmer->farmer] ?? null;

                    if (!$config) {
                        return;
                    }

                    /** Get Web App Data */
                    $data = $api->getTelegramData($config['telegram_link']);

                    try {
                        /** Update TelegramWebApp */
                        $farmer->update([
                            'is_connected' => true,
                            'telegram_web_app' => [
                                'initData' => $data['initData']
                            ]
                        ]);
                    } catch (\Throwable $e) {
                        /** Log Error */
                        $this->logError(
                            title: 'SAVING WEB_APP_DATA',
                            config: $config,
                            error: $e
                        );
                    }
                });

                /** Get User Details */
                $data = $api->getTelegramData(
                    config('farmer.farmer_bot_link')
                );

                /** Save User Details */
                try {
                    $this->account->update([
                        'data' => array_merge(
                            $this->account->data ?? [],
                            ['user' => $data['initDataUnsafe']['user']]
                        )
                    ]);
                } catch (\Throwable $e) {
                    /** Log Error */
                    $this->logError(
                        title: 'SAVING ACCOUNT USER',
                        error: $e
                    );
                }


            } catch (\Throwable $e) {
                /** Logout */
                try {
                    $api->logout();
                } catch (\Throwable $e) {
                    /** Log Error */
                    $this->logError(
                        title: 'TELEGRAM SESSION LOGOUT',
                        error: $e
                    );
                }

                throw $e;
            }
        } catch (\Throwable $e) {
            /** Log Error */
            $this->logError(
                title: 'TELEGRAM WEBAPP DATA',
                error: $e
            );

            /** Update Session */
            try {
                $this->account->update(['session_id' => null]);
            } catch (\Throwable $e) {
                /** Log Error */
                $this->logError(
                    title: 'REMOVING SESSION',
                    error: $e
                );
            }
        }
    }

    /**
     * Log Error
     * @param string $title
     * @param \Throwable $error
     * @param array|null $config
     * @return void
     */
    protected function logError($title, $error, $config = null)
    {
        /** Log Error */
        Log::error(($config ? $config['title'] . ' ' : '') . 'Error (' . $title . ')', [
            'title' => $this->account->getFarmerTitle(),
            'user_id' => $this->account->user_id ?? null,
            'username' => $this->account->getUsername(),
            'message' => $error->getMessage(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
        ]);
    }

    public static function update(Account $account)
    {
        return (new static($account))->process();
    }
}