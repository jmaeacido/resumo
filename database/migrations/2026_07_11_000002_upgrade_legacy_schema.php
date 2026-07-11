<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'password_hash')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'password')) {
                    $table->string('password')->nullable()->after('email');
                }
                if (! Schema::hasColumn('users', 'email_verified_at')) {
                    $table->timestamp('email_verified_at')->nullable()->after('email');
                }
                if (! Schema::hasColumn('users', 'remember_token')) {
                    $table->rememberToken();
                }
                if (! Schema::hasColumn('users', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });

            DB::table('users')->whereNotNull('password_hash')->update([
                'password' => DB::raw('password_hash'),
            ]);

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('password_hash');
            });
        }

        if (! Schema::hasTable('resume_reports')) {
            Schema::create('resume_reports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('mode', 20);
                $table->unsignedSmallInteger('score');
                $table->string('job_title')->nullable();
                $table->longText('analysis_json');
                $table->timestamps();

                $table->index(['user_id', 'created_at']);
                $table->index(['mode', 'created_at']);
                $table->index('score');
            });
        } elseif (! Schema::hasColumn('resume_reports', 'updated_at')) {
            Schema::table('resume_reports', function (Blueprint $table) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            });
        }
    }

    public function down(): void
    {
        // Intentionally left empty to avoid destructive rollback on legacy data.
    }
};
