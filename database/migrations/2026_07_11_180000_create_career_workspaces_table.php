<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('career_workspaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->longText('base_resume')->nullable();
            $table->json('resume_versions')->nullable();
            $table->json('applications')->nullable();
            $table->json('cover_letters')->nullable();
            $table->json('suggestions')->nullable();
            $table->json('interview_kits')->nullable();
            $table->json('linkedin_reviews')->nullable();
            $table->json('usage')->nullable();
            $table->timestamp('last_exported_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('career_workspaces');
    }
};
