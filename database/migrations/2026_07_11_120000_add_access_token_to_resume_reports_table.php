<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resume_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('resume_reports', 'access_token')) {
                $table->string('access_token', 64)->nullable()->unique()->after('user_id');
            }
        });

        $reports = DB::table('resume_reports')->whereNull('access_token')->get(['id']);

        foreach ($reports as $report) {
            DB::table('resume_reports')
                ->where('id', $report->id)
                ->update(['access_token' => Str::random(48)]);
        }
    }

    public function down(): void
    {
        Schema::table('resume_reports', function (Blueprint $table) {
            if (Schema::hasColumn('resume_reports', 'access_token')) {
                $table->dropColumn('access_token');
            }
        });
    }
};
