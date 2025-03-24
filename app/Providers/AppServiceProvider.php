<?php

namespace App\Providers;

use App\Libraries\Madeline;
use App\Libraries\Proxy;
use App\Payment\Paystack;
use Illuminate\Auth\Notifications\ResetPassword;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url') . "/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });
    }
}
