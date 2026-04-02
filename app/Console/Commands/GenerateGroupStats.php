<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GroupStatisticsService;

class GenerateGroupStats extends Command
{
    protected $signature = 'groupstats:weekly';
    protected $description = 'Generate weekly group statistics report';

    protected $service;

    public function __construct(GroupStatisticsService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function handle()
    {
        $ok = $this->service->generateReport();
        $this->info($ok ? 'Group statistics generated.' : 'No groups to process.');
        return 0;
    }
}
