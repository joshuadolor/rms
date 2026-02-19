<?php

namespace Tests\Unit\Support;

use App\Support\OperatingHoursSlotValidator;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('availability')]
class OperatingHoursSlotValidatorTest extends TestCase
{
    // --- isValidTimeFormat ---

    public function test_is_valid_time_format_accepts_hh_mm(): void
    {
        $this->assertTrue(OperatingHoursSlotValidator::isValidTimeFormat('00:00'));
        $this->assertTrue(OperatingHoursSlotValidator::isValidTimeFormat('09:30'));
        $this->assertTrue(OperatingHoursSlotValidator::isValidTimeFormat('12:00'));
        $this->assertTrue(OperatingHoursSlotValidator::isValidTimeFormat('23:59'));
    }

    public function test_is_valid_time_format_accepts_hh_mm_ss(): void
    {
        $this->assertTrue(OperatingHoursSlotValidator::isValidTimeFormat('00:00:00'));
        $this->assertTrue(OperatingHoursSlotValidator::isValidTimeFormat('12:30:45'));
        $this->assertTrue(OperatingHoursSlotValidator::isValidTimeFormat('23:59:59'));
    }

    public function test_is_valid_time_format_rejects_invalid(): void
    {
        $this->assertFalse(OperatingHoursSlotValidator::isValidTimeFormat(''));
        $this->assertFalse(OperatingHoursSlotValidator::isValidTimeFormat('24:00'));
        $this->assertFalse(OperatingHoursSlotValidator::isValidTimeFormat('12:60'));
        $this->assertFalse(OperatingHoursSlotValidator::isValidTimeFormat('12:30:60'));
        $this->assertFalse(OperatingHoursSlotValidator::isValidTimeFormat('25:00'));
        $this->assertFalse(OperatingHoursSlotValidator::isValidTimeFormat('12:99'));
        $this->assertFalse(OperatingHoursSlotValidator::isValidTimeFormat('12:00 am'));
        $this->assertFalse(OperatingHoursSlotValidator::isValidTimeFormat('not-a-time'));
    }

    // --- isSlotValid ---

    public function test_is_slot_valid_accepts_valid_slot(): void
    {
        $this->assertTrue(OperatingHoursSlotValidator::isSlotValid(['from' => '09:00', 'to' => '17:00']));
        $this->assertTrue(OperatingHoursSlotValidator::isSlotValid(['from' => '12:00', 'to' => '14:30']));
    }

    public function test_is_slot_valid_rejects_missing_from_or_to(): void
    {
        $this->assertFalse(OperatingHoursSlotValidator::isSlotValid([]));
        $this->assertFalse(OperatingHoursSlotValidator::isSlotValid(['from' => '09:00']));
        $this->assertFalse(OperatingHoursSlotValidator::isSlotValid(['to' => '17:00']));
    }

    public function test_is_slot_valid_rejects_non_string_from_or_to(): void
    {
        $this->assertFalse(OperatingHoursSlotValidator::isSlotValid(['from' => 9, 'to' => '17:00']));
        $this->assertFalse(OperatingHoursSlotValidator::isSlotValid(['from' => '09:00', 'to' => null]));
    }

    public function test_is_slot_valid_rejects_invalid_time_format(): void
    {
        $this->assertFalse(OperatingHoursSlotValidator::isSlotValid(['from' => '25:00', 'to' => '17:00']));
        $this->assertFalse(OperatingHoursSlotValidator::isSlotValid(['from' => '09:00', 'to' => 'invalid']));
    }

    public function test_is_slot_valid_rejects_from_equals_or_after_to(): void
    {
        $this->assertFalse(OperatingHoursSlotValidator::isSlotValid(['from' => '17:00', 'to' => '17:00']));
        $this->assertFalse(OperatingHoursSlotValidator::isSlotValid(['from' => '18:00', 'to' => '12:00']));
    }

    // --- slotsOverlap ---

    public function test_slots_overlap_returns_false_for_empty_or_single_slot(): void
    {
        $this->assertFalse(OperatingHoursSlotValidator::slotsOverlap([]));
        $this->assertFalse(OperatingHoursSlotValidator::slotsOverlap([['from' => '09:00', 'to' => '17:00']]));
    }

    public function test_slots_overlap_returns_false_for_non_overlapping_slots(): void
    {
        $slots = [
            ['from' => '09:00', 'to' => '12:00'],
            ['from' => '14:00', 'to' => '18:00'],
        ];
        $this->assertFalse(OperatingHoursSlotValidator::slotsOverlap($slots));
    }

    public function test_slots_overlap_returns_true_for_overlapping_slots(): void
    {
        $slots = [
            ['from' => '09:00', 'to' => '14:00'],
            ['from' => '12:00', 'to' => '18:00'],
        ];
        $this->assertTrue(OperatingHoursSlotValidator::slotsOverlap($slots));
    }

    public function test_slots_overlap_returns_false_for_adjacent_slots_touching(): void
    {
        // 09:00-12:00 and 12:00-18:00 share boundary; a < d and c < b => 9*60 < 18*60 and 12*60 < 12*60 => 540 < 1080 and 720 < 720 => false. So adjacent do NOT overlap (correct).
        $slots = [
            ['from' => '09:00', 'to' => '12:00'],
            ['from' => '12:00', 'to' => '18:00'],
        ];
        $this->assertFalse(OperatingHoursSlotValidator::slotsOverlap($slots));
    }

    public function test_slots_overlap_returns_true_for_contained_slot(): void
    {
        $slots = [
            ['from' => '08:00', 'to' => '20:00'],
            ['from' => '10:00', 'to' => '12:00'],
        ];
        $this->assertTrue(OperatingHoursSlotValidator::slotsOverlap($slots));
    }

    public function test_slots_overlap_treats_invalid_times_as_overlap(): void
    {
        $slots = [
            ['from' => '09:00', 'to' => '17:00'],
            ['from' => 'invalid', 'to' => '18:00'],
        ];
        $this->assertTrue(OperatingHoursSlotValidator::slotsOverlap($slots));
    }

    // --- validateSlots ---

    public function test_validate_slots_returns_empty_for_valid_non_overlapping_slots(): void
    {
        $slots = [
            ['from' => '09:00', 'to' => '12:00'],
            ['from' => '14:00', 'to' => '18:00'],
        ];
        $this->assertSame([], OperatingHoursSlotValidator::validateSlots($slots));
    }

    public function test_validate_slots_returns_empty_for_single_valid_slot(): void
    {
        $this->assertSame([], OperatingHoursSlotValidator::validateSlots([['from' => '09:00', 'to' => '17:00']]));
    }

    public function test_validate_slots_returns_error_when_slot_is_not_array(): void
    {
        $slots = [['from' => '09:00', 'to' => '12:00'], 'not-an-array'];
        $errors = OperatingHoursSlotValidator::validateSlots($slots);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Each slot must be an object', $errors[0]);
    }

    public function test_validate_slots_returns_error_when_from_or_to_missing(): void
    {
        $slots = [['from' => '09:00']];
        $errors = OperatingHoursSlotValidator::validateSlots($slots);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('from', $errors[0]);
        $this->assertStringContainsString('to', $errors[0]);
    }

    public function test_validate_slots_returns_error_when_from_or_to_not_string(): void
    {
        $slots = [['from' => 900, 'to' => '17:00']];
        $errors = OperatingHoursSlotValidator::validateSlots($slots);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('must be strings', $errors[0]);
    }

    public function test_validate_slots_returns_errors_for_invalid_time_format(): void
    {
        $slots = [['from' => '25:00', 'to' => '17:00']];
        $errors = OperatingHoursSlotValidator::validateSlots($slots);
        $this->assertNotEmpty($errors);
        $this->assertTrue(
            collect($errors)->contains(fn (string $e) => str_contains($e, 'Invalid time format')),
            'Expected an error about invalid time format'
        );
    }

    public function test_validate_slots_returns_error_when_from_not_before_to(): void
    {
        $slots = [['from' => '17:00', 'to' => '09:00']];
        $errors = OperatingHoursSlotValidator::validateSlots($slots);
        $this->assertNotEmpty($errors);
        $this->assertTrue(
            collect($errors)->contains(fn (string $e) => str_contains($e, 'from') && str_contains($e, 'to')),
            'Expected an error about from before to'
        );
    }

    public function test_validate_slots_returns_error_for_overlapping_slots(): void
    {
        $slots = [
            ['from' => '09:00', 'to' => '14:00'],
            ['from' => '12:00', 'to' => '18:00'],
        ];
        $errors = OperatingHoursSlotValidator::validateSlots($slots);
        $this->assertNotEmpty($errors);
        $this->assertTrue(
            collect($errors)->contains(fn (string $e) => str_contains($e, 'overlap')),
            'Expected an error about overlapping slots'
        );
    }
}
