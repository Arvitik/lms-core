<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ReportService;

class GenerateWeeklyReports extends Command
{
    // Для Laravel 5.x ok
    protected $signature = 'reports:weekly';
    protected $description = 'Generate weekly teacher reports';

    protected $service;

    public function __construct(ReportService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function handle()
    {
        $this->service->generateWeeklyReports();
        $this->info('Weekly teacher reports generated.');
        return 0;
    }
}
