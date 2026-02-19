<?php

namespace App\Support;

/**
 * Reusable validator for time slots (e.g. operating hours per day, menu item availability).
 * Ensures slots have valid time format (HH:MM or HH:MM:SS), from < to, and no overlapping slots.
 */
final class OperatingHoursSlotValidator
{
    /** 24h time: HH:MM or HH:MM:SS */
    private const TIME_REGEX = '/^([01]?\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/';

    /**
     * Check if a time string is valid (HH:MM or HH:MM:SS, 24h).
     */
    public static function isValidTimeFormat(string $time): bool
    {
        return (bool) preg_match(self::TIME_REGEX, $time);
    }

    /**
     * Check if a single slot has valid from/to and from < to.
     *
     * @param array{from?: string, to?: string} $slot
     */
    public static function isSlotValid(array $slot): bool
    {
        $from = $slot['from'] ?? null;
        $to = $slot['to'] ?? null;

        if (! is_string($from) || ! is_string($to)) {
            return false;
        }

        if (! self::isValidTimeFormat($from) || ! self::isValidTimeFormat($to)) {
            return false;
        }

        return self::timeToMinutes($from) < self::timeToMinutes($to);
    }

    /**
     * Check if slots overlap. Returns true if there is at least one overlap.
     * Slots must already be valid (from < to per slot).
     *
     * @param array<int, array{from: string, to: string}> $slots
     */
    public static function slotsOverlap(array $slots): bool
    {
        $count = count($slots);
        if ($count < 2) {
            return false;
        }

        $ranges = [];
        foreach ($slots as $slot) {
            $from = $slot['from'] ?? '';
            $to = $slot['to'] ?? '';
            if (! self::isValidTimeFormat($from) || ! self::isValidTimeFormat($to)) {
                return true; // treat invalid as "would fail elsewhere"
            }
            $ranges[] = [self::timeToMinutes($from), self::timeToMinutes($to)];
        }

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                // [a, b] and [c, d] overlap iff a < d and c < b
                if ($ranges[$i][0] < $ranges[$j][1] && $ranges[$j][0] < $ranges[$i][1]) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Validate a list of slots for one day: structure, from < to, no overlap.
     * Returns an array of error messages (empty if valid). Reusable for restaurant or menu item availability.
     *
     * @param array<int, array<string, mixed>> $slots
     * @return list<string>
     */
    public static function validateSlots(array $slots): array
    {
        $errors = [];

        foreach ($slots as $index => $slot) {
            if (! is_array($slot)) {
                $errors[] = 'Each slot must be an object with "from" and "to".';

                continue;
            }

            if (! isset($slot['from']) || ! isset($slot['to'])) {
                $errors[] = 'Each slot must have "from" and "to" (time in HH:MM or HH:MM:SS).';

                continue;
            }

            $from = $slot['from'];
            $to = $slot['to'];

            if (! is_string($from) || ! is_string($to)) {
                $errors[] = 'Slot "from" and "to" must be strings.';

                continue;
            }

            if (! self::isValidTimeFormat($from)) {
                $errors[] = "Invalid time format for \"from\" (use HH:MM or HH:MM:SS, 24h): {$from}.";
            }
            if (! self::isValidTimeFormat($to)) {
                $errors[] = "Invalid time format for \"to\" (use HH:MM or HH:MM:SS, 24h): {$to}.";
            }

            if (self::isValidTimeFormat($from) && self::isValidTimeFormat($to) && self::timeToMinutes($from) >= self::timeToMinutes($to)) {
                $errors[] = 'Slot "from" must be before "to".';
            }
        }

        if (empty($errors) && self::slotsOverlap($slots)) {
            $errors[] = 'Time slots must not overlap within the same day.';
        }

        return $errors;
    }

    private static function timeToMinutes(string $time): int
    {
        $parts = explode(':', $time);
        $h = (int) ($parts[0] ?? 0);
        $m = (int) ($parts[1] ?? 0);
        $s = (int) ($parts[2] ?? 0);

        return $h * 60 + $m + (int) round($s / 60);
    }
}
