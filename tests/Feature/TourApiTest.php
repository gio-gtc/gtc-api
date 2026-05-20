<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TourApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Department $department;
    protected User $gtcRep;
    protected User $voiceOver;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Seed the departments table so we have real IDs to test with
        $this->seed(DepartmentSeeder::class);
        $this->department = Department::where('name', 'Touring')->first();

        // 2. Create the authenticated user and the related tour users
        $this->user = User::factory()->create();
        $this->gtcRep = User::factory()->create();
        $this->voiceOver = User::factory()->create();
    }

    public function test_can_create_tour_with_valid_data(): void
    {
        $payload = [
            'name' => 'The Blueprint Tour 2026',
            'start_date' => '2026-06-01',
            'expire_on_sale_now_cuts' => '2026-05-25',
            'gtc_rep_id' => $this->gtcRep->id,
            'voice_over_id' => $this->voiceOver->id,
            'department_id' => $this->department->id,
            'hold_all_invoices' => true,
            'live_on_ordering_system' => false,
            'require_client_approval' => true,
            'client_approval_email' => 'approval@example.com',
            'tour_sponsor' => 'Monster Energy',
            'special_instructions' => 'Deliver final cuts in high definition.',
            'tv_first_cut' => 1500.50,
            'tv_second_cut' => 750.00,
            'radio_single_duration' => 250.25,
            'radio_dual_duration' => 400.00,
            'key_art' => 1200.75,
        ];

        $response = $this->actingAs($this->user)->postJson('/api/tours', $payload);

        $response->assertStatus(201)
                 ->assertJsonFragment(['name' => 'The Blueprint Tour 2026'])
                 ->assertJsonStructure([
                     'message',
                     'tour' => [
                         'id',
                         'name',
                         'tv_first_cut',
                         'gtc_rep',
                         'voice_over',
                         'department'
                     ]
                 ]);

        // Verify it was correctly written to the database with decimal accuracy
        $this->assertDatabaseHas('tours', [
            'name' => 'The Blueprint Tour 2026',
            'tv_first_cut' => '1500.50',
            'require_client_approval' => 1,
        ]);
    }

    public function test_client_approval_email_is_required_when_require_client_approval_is_true(): void
    {
        $payload = [
            'name' => 'Validation Fail Tour',
            'start_date' => '2026-06-01',
            'expire_on_sale_now_cuts' => '2026-05-25',
            'gtc_rep_id' => $this->gtcRep->id,
            'voice_over_id' => $this->voiceOver->id,
            'department_id' => $this->department->id,
            'require_client_approval' => true,
            'client_approval_email' => null,
        ];

        $response = $this->actingAs($this->user)->postJson('/api/tours', $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['client_approval_email']);
    }

    public function test_cannot_create_tour_with_non_existent_department(): void
    {
        $payload = [
            'name' => 'Bad Department Tour',
            'start_date' => '2026-06-01',
            'expire_on_sale_now_cuts' => '2026-05-25',
            'gtc_rep_id' => $this->gtcRep->id,
            'voice_over_id' => $this->voiceOver->id,
            'department_id' => 9999,
        ];

        $response = $this->actingAs($this->user)->postJson('/api/tours', $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['department_id']);
    }
}