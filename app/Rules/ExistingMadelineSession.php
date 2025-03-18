<?php

namespace App\Rules;

use App\Facades\Madeline;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ExistingMadelineSession implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!Madeline::sessionExists($value)) {
            $fail('Invalid Session');
        }
    }
}
