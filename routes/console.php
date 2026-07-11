<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\ResumeReport;
use Symfony\Component\Process\Process;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    ResumeReport::query()
        ->whereNull('user_id')
        ->where('created_at', '<', now()->subDays(90))
        ->delete();
})->dailyAt('02:15')->name('prune-expired-guest-reports')->withoutOverlapping();

Schedule::command('queue:prune-failed --hours=720')->dailyAt('02:30');

Artisan::command('resumo:backup', function () {
    abort_unless(config('database.default') === 'mysql', 1, 'Only MySQL backups are configured.');
    $db = config('database.connections.mysql');
    $directory = storage_path('app/backups');
    if (! is_dir($directory)) mkdir($directory, 0750, true);
    $path = $directory.'/resumo-'.now()->format('Y-m-d-His').'.sql';
    $process = new Process([
        'mysqldump', '--single-transaction', '--skip-lock-tables',
        '--host='.$db['host'], '--port='.(string) $db['port'], '--user='.$db['username'],
        (string) $db['database'],
    ], null, ['MYSQL_PWD' => (string) $db['password']]);
    $process->mustRun();
    file_put_contents($path, $process->getOutput());
    chmod($path, 0640);
    collect(glob($directory.'/resumo-*.sql') ?: [])->filter(fn ($file) => filemtime($file) < now()->subDays(7)->getTimestamp())->each(fn ($file) => unlink($file));
    $this->info('Database backup written to '.$path);
})->purpose('Create a private MySQL backup and retain seven days');

Schedule::command('resumo:backup')->dailyAt('03:00')->withoutOverlapping();
