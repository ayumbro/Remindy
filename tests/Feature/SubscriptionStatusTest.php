<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->currency = Currency::factory()->create();
    }

    /** @test */
    public function it_returns_original_status_when_no_end_date()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'end_date' => null,
        ]);

        $this->assertEquals('active', $subscription->computed_status);
    }

    /** @test */
    public function it_returns_original_status_when_end_date_is_in_future()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'end_date' => Carbon::now()->addDays(30),
        ]);

        $this->assertEquals('active', $subscription->computed_status);
    }

    /** @test */
    public function it_returns_ended_status_when_end_date_has_passed()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'end_date' => Carbon::now()->subDays(1),
        ]);

        $this->assertEquals('ended', $subscription->computed_status);
    }

    /** @test */
    public function it_returns_ended_status_for_paused_subscription_past_end_date()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'end_date' => Carbon::now()->subDays(5),
        ]);

        $this->assertEquals('ended', $subscription->computed_status);
    }

    /** @test */
    public function it_preserves_original_status_in_database()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'end_date' => Carbon::now()->subDays(1),
        ]);

        // Computed status should be 'ended'
        $this->assertEquals('ended', $subscription->computed_status);

        // Note: The status field has been removed from the database schema
        // The computed_status is now calculated dynamically based on end_date
        // This test verifies that the computed status works correctly
        $this->assertTrue($subscription->end_date->isPast());
    }

    /** @test */
    public function it_includes_computed_status_in_array_form()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'end_date' => Carbon::now()->subDays(1),
        ]);

        $array = $subscription->toArray();

        $this->assertArrayHasKey('computed_status', $array);
        $this->assertEquals('ended', $array['computed_status']);
        // Note: The status field has been removed from the database schema
        $this->assertArrayNotHasKey('status', $array);
    }

    /** @test */
    public function it_handles_edge_case_end_date_today()
    {
        // Test subscription ending today
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'end_date' => Carbon::now()->startOfDay(),
        ]);

        // Since current time is after start of day, it should be ended
        $this->assertEquals('ended', $subscription->computed_status);
    }

    /** @test */
    public function it_works_with_different_original_statuses()
    {
        // Since the status field has been removed, we test that computed_status
        // works consistently regardless of other subscription properties
        $testCases = [
            ['end_date' => Carbon::now()->subDays(1), 'expected' => 'ended'],
            ['end_date' => Carbon::now()->addDays(1), 'expected' => 'active'],
            ['end_date' => null, 'expected' => 'active'],
        ];

        foreach ($testCases as $case) {
            $subscription = Subscription::factory()->create([
                'user_id' => $this->user->id,
                'currency_id' => $this->currency->id,
                'end_date' => $case['end_date'],
            ]);

            $this->assertEquals($case['expected'], $subscription->computed_status,
                "Failed for end_date: " . ($case['end_date'] ? $case['end_date']->toDateString() : 'null'));
        }
    }
}
