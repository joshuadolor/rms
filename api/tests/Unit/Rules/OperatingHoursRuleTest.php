<?php

namespace Tests\Unit\Rules;

use App\Rules\OperatingHoursRule;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('availability')]
class OperatingHoursRuleTest extends TestCase
{
    private function runRule(mixed $value): ?string
    {
        $failMessage = null;
        $rule = new OperatingHoursRule();
        $rule->validate('operating_hours', $value, function (string $message) use (&$failMessage) {
            $failMessage = $message;
        });

        return $failMessage;
    }

    private function validOperatingHours(): array
    {
        return [
            'sunday' => ['open' => false, 'slots' => []],
            'monday' => ['open' => true, 'slots' => [['from' => '09:00', 'to' => '17:00']]],
            'tuesday' => ['open' => true, 'slots' => [['from' => '09:00', 'to' => '12:00'], ['from' => '14:00', 'to' => '18:00']]],
            'wednesday' => ['open' => false, 'slots' => []],
            'thursday' => ['open' => true, 'slots' => [['from' => '10:00', 'to' => '22:00']]],
            'friday' => ['open' => true, 'slots' => [['from' => '10:00', 'to' => '23:00']]],
            'saturday' => ['open' => false, 'slots' => []],
        ];
    }

    public function test_rule_passes_for_valid_operating_hours(): void
    {
        $this->assertNull($this->runRule($this->validOperatingHours()));
    }

    public function test_rule_fails_when_value_is_not_array(): void
    {
        $msg = $this->runRule('not-an-array');
        $this->assertNotNull($msg);
        $this->assertStringContainsString('must be an object keyed by day', $msg);
    }

    public function test_rule_fails_for_invalid_day_keys(): void
    {
        $value = [
            'monday' => ['open' => true, 'slots' => []],
            'invalid_day' => ['open' => false, 'slots' => []],
        ];
        $msg = $this->runRule($value);
        $this->assertNotNull($msg);
        $this->assertStringContainsString('sunday, monday, tuesday', $msg);
    }

    public function test_rule_fails_when_day_value_is_not_array(): void
    {
        $value = $this->validOperatingHours();
        $value['monday'] = 'not-an-array';
        $msg = $this->runRule($value);
        $this->assertNotNull($msg);
        $this->assertStringContainsString('must be an object with "open" and "slots"', $msg);
    }

    public function test_rule_fails_when_open_is_missing(): void
    {
        $value = $this->validOperatingHours();
        $value['monday'] = ['slots' => []];
        $msg = $this->runRule($value);
        $this->assertNotNull($msg);
        $this->assertStringContainsString('"open"', $msg);
    }

    public function test_rule_fails_when_open_is_not_boolean(): void
    {
        $value = $this->validOperatingHours();
        $value['monday'] = ['open' => 'yes', 'slots' => []];
        $msg = $this->runRule($value);
        $this->assertNotNull($msg);
        $this->assertStringContainsString('"open" must be true or false', $msg);
    }

    public function test_rule_fails_when_slots_is_missing(): void
    {
        $value = $this->validOperatingHours();
        $value['monday'] = ['open' => true];
        $msg = $this->runRule($value);
        $this->assertNotNull($msg);
        $this->assertStringContainsString('"slots"', $msg);
    }

    public function test_rule_fails_when_slots_is_not_array(): void
    {
        $value = $this->validOperatingHours();
        $value['monday'] = ['open' => true, 'slots' => 'not-array'];
        $msg = $this->runRule($value);
        $this->assertNotNull($msg);
        $this->assertStringContainsString('"slots" must be an array', $msg);
    }

    public function test_rule_fails_when_slots_are_invalid(): void
    {
        $value = $this->validOperatingHours();
        $value['monday'] = ['open' => true, 'slots' => [['from' => '25:00', 'to' => '17:00']]];
        $msg = $this->runRule($value);
        $this->assertNotNull($msg);
        $this->assertStringContainsString('Invalid time format', $msg);
    }

    public function test_rule_fails_when_slots_overlap(): void
    {
        $value = $this->validOperatingHours();
        $value['monday'] = [
            'open' => true,
            'slots' => [
                ['from' => '09:00', 'to' => '14:00'],
                ['from' => '12:00', 'to' => '18:00'],
            ],
        ];
        $msg = $this->runRule($value);
        $this->assertNotNull($msg);
        $this->assertStringContainsString('overlap', $msg);
    }

    public function test_rule_passes_when_open_false_with_empty_slots(): void
    {
        $value = [
            'sunday' => ['open' => false, 'slots' => []],
            'monday' => ['open' => false, 'slots' => []],
            'tuesday' => ['open' => false, 'slots' => []],
            'wednesday' => ['open' => false, 'slots' => []],
            'thursday' => ['open' => false, 'slots' => []],
            'friday' => ['open' => false, 'slots' => []],
            'saturday' => ['open' => false, 'slots' => []],
        ];
        $this->assertNull($this->runRule($value));
    }
}
