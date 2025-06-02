<?php
namespace App\Contracts;

interface TelegramClientInterface
{
    public function phoneLogin(string $phone);
    public function completePhoneLogin(string $code);
    public function complete2faLogin(string $password);
    public function logout();

    public function getSelf();
    public function getWebview(string $url);
    public function joinTelegramLink(string $url);
    public static function getSessions();
}