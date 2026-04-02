<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ReportService;

class GenerateWeeklyReports extends Command
{
    protected $signature = 'reports:generate-weekly';
    protected $description = 'Генерирует недельные отчеты по студентам для каждого преподавателя';

    public function handle()
    {
        $service = new ReportService();
        $service->generateWeeklyReports();
        $this->info('Недельные отчеты успешно созданы.');
    }
}
