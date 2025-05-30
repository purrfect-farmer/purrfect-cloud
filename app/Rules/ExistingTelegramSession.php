<?php

namespace App\Rules;

use App\Libraries\TelegramClient;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ExistingTelegramSession implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!TelegramClient::sessionExists($value)) {
            $fail('Invalid Session');
        }
    }
}
