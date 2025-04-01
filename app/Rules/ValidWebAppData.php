<?php

namespace App\Rules;

use App\Helpers;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidWebAppData implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!Helpers::isValidEd25519WebAppData($value)) {
            $fail("Invalid Data");
        }
    }
}
