<?php

namespace Tests\Feature;

use App\Models\AssessmentFormVersion;
use App\Services\Forms\FormRuleTester;
use Tests\TestCase;

class FormRuleTesterTest extends TestCase
{
    public function test_custom_text_minimum_and_maximum_are_enforced(): void
    {
        $version = new AssessmentFormVersion([
            'fields' => [[
                'key' => 'nin',
                'label' => 'NIN',
                'type' => 'text',
                'rules' => ['min:11', 'max:11'],
            ]],
            'scoring_rules' => [],
        ]);
        $tester = app(FormRuleTester::class);

        $this->assertFalse($tester->testVersion($version, ['nin' => '1234567890'])['valid']);
        $this->assertTrue($tester->testVersion($version, ['nin' => '12345678901'])['valid']);
        $this->assertFalse($tester->testVersion($version, ['nin' => '123456789012'])['valid']);
    }

    public function test_custom_numeric_minimum_and_maximum_are_enforced(): void
    {
        $version = new AssessmentFormVersion([
            'fields' => [[
                'key' => 'age',
                'label' => 'Age',
                'type' => 'number',
                'rules' => ['integer', 'min:1', 'max:11'],
            ]],
            'scoring_rules' => [],
        ]);
        $tester = app(FormRuleTester::class);

        $this->assertFalse($tester->testVersion($version, ['age' => 0])['valid']);
        $this->assertTrue($tester->testVersion($version, ['age' => 11])['valid']);
        $this->assertFalse($tester->testVersion($version, ['age' => 12])['valid']);
    }

    public function test_structured_array_answers_are_validated_without_string_conversion_errors(): void
    {
        $version = new AssessmentFormVersion([
            'fields' => [[
                'key' => 'gps_coordinates',
                'label' => 'GPS coordinates',
                'type' => 'gps',
                'rules' => ['array', 'min:2', 'max:3'],
            ]],
            'scoring_rules' => [],
        ]);
        $tester = app(FormRuleTester::class);

        $this->assertTrue($tester->testVersion($version, [
            'gps_coordinates' => ['latitude' => 9.6, 'longitude' => 6.5],
        ])['valid']);
        $this->assertFalse($tester->testVersion($version, [
            'gps_coordinates' => ['latitude' => 9.6],
        ])['valid']);
    }
}
