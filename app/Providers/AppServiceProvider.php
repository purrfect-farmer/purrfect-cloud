<?php

namespace App\Providers;

use App\Libraries\Madeline;
use App\Libraries\Proxy;
use App\Payment\Flutterwave;
use App\Payment\Paystack;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('madeline', Madeline::class);
        $this->app->singleton('proxy', Proxy::class);
        $this->app->singleton('paystack', function () {
            $config = config('services.paystack');

            return new Paystack(
                publicKey: $config['public_key'],
                secretKey: $config['secret_key'],
            );
        });

        $this->app->singleton('flutterwave', function () {
            $config = config('services.flutterwave');

            return new Flutterwave(
                publicKey: $config['public_key'],
                secretKey: $config['secret_key'],
                encryptionKey: $config['encryption_key'],
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Collection::macro(
            'mapForConcurrency',
            /**
             * @param callable $callback
             */
            function ($callback) {
                /** @var Collection $this */
                return $this->map(fn($value, $key) => fn() => $callback($value, $key))->all();
            }
        );

        Collection::macro(
            'mapConcurrently',
            /**
             * @param callable $callback
             */
            function ($callback) {
                /** @var Collection $this */
                if (!config('farmer.enable_concurrency')) {
                    return $this->map($callback);
                }

                return collect(
                    $this->chunk(10)->map(
                        function ($chunk) use ($callback) {
                            return Concurrency::run(
                                $chunk->mapForConcurrency($callback)
                            );
                        }
                    )->flatten()
                );
            }
        );

        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url') . "/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });
    }
}