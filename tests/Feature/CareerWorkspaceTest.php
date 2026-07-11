<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CareerWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_can_use_every_workspace_tool(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get('/workspace')->assertOk();

        $actions = [
            ['save_base', ['content' => "Jane Doe\nSummary\nExperience\nImproved delivery by 25%\nSkills\nPHP Laravel\nEducation"]],
            ['create_version', ['name' => 'Laravel role', 'job_title' => 'Laravel Developer', 'job_description' => 'Laravel PHP APIs testing', 'content' => 'Jane Doe Laravel PHP APIs testing improved 25% Summary Experience Skills Education']],
            ['save_application', ['company' => 'Acme', 'job_title' => 'Developer', 'description' => 'Build APIs']],
            ['generate_cover_letter', ['company' => 'Acme', 'job_title' => 'Developer', 'job_description' => 'PHP Laravel APIs']],
            ['create_suggestion', ['text' => 'Responsible for building APIs', 'target_role' => 'Developer']],
            ['generate_interview', ['job_title' => 'Developer', 'job_description' => 'PHP Laravel APIs']],
            ['review_linkedin', ['target_role' => 'Developer', 'profile' => 'Developer building web products and improving delivery by 25%.']],
        ];

        foreach ($actions as [$action, $payload]) {
            $this->actingAs($user)->postJson('/workspace', compact('action', 'payload'))->assertOk();
        }

        $workspace = $user->fresh()->careerWorkspace;
        $this->assertCount(1, $workspace->resume_versions);
        $this->assertCount(1, $workspace->applications);
        $this->assertCount(1, $workspace->cover_letters);
        $this->assertCount(1, $workspace->suggestions);
        $this->assertCount(1, $workspace->interview_kits);
        $this->assertCount(1, $workspace->linkedin_reviews);

        $this->actingAs($user)->get('/workspace/export')->assertOk();
    }
}
