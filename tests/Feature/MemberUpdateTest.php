<?php

namespace Tests\Feature;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_be_updated_with_a_partial_patch(): void
    {
        $member = Member::factory()->create(['email' => 'old@example.test']);

        $this->patchJson("/api/members/{$member->id}", [
            'email' => 'new@example.test',
        ])
            ->assertOk()
            ->assertJsonPath('data.email', 'new@example.test');

        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'email' => 'new@example.test',
        ]);
    }

    public function test_member_update_rejects_an_empty_payload(): void
    {
        $member = Member::factory()->create(['email' => 'old@example.test']);

        $this->patchJson("/api/members/{$member->id}", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'type']);

        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'email' => 'old@example.test',
        ]);
    }
}
