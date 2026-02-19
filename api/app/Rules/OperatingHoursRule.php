<?php

namespace App\Rules;

use App\Support\OperatingHoursSlotValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates operating_hours (and reusable for menu item availability).
 * Structure: object keyed by day (sunday..saturday), each value: { open: bool, slots: [ { from, to }, ... ] }.
 * Times HH:MM or HH:MM:SS; from < to; no overlapping slots per day.
 */
final class OperatingHoursRule implements ValidationRule
{
    private const ALLOWED_DAYS = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('Operating hours must be an object keyed by day (sunday through saturday).');

            return;
        }

        $invalidKeys = array_diff(array_keys($value), self::ALLOWED_DAYS);
        if ($invalidKeys !== []) {
            $fail('Operating hours may only contain day keys: sunday, monday, tuesday, wednesday, thursday, friday, saturday.');

            return;
        }

        foreach ($value as $day => $dayValue) {
            if (! is_array($dayValue)) {
                $fail("Operating hours for {$day} must be an object with \"open\" and \"slots\".");
                return;
            }

            if (! array_key_exists('open', $dayValue)) {
                $fail("Operating hours for {$day} must have an \"open\" (boolean) field.");
                return;
            }

            if (! is_bool($dayValue['open'])) {
                $fail("Operating hours for {$day}: \"open\" must be true or false.");
                return;
            }

            if (! array_key_exists('slots', $dayValue)) {
                $fail("Operating hours for {$day} must have a \"slots\" (array) field.");
                return;
            }

            if (! is_array($dayValue['slots'])) {
                $fail("Operating hours for {$day}: \"slots\" must be an array.");
                return;
            }

            if ($dayValue['open'] && count($dayValue['slots']) > 0) {
                $slotErrors = OperatingHoursSlotValidator::validateSlots($dayValue['slots']);
                if ($slotErrors !== []) {
                    $fail(implode(' ', $slotErrors));
                    return;
                }
            }
        }
    }
}
