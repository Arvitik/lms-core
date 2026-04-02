<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\GroupStatisticsService;
use App\GroupReport;
use PDF;

class GroupStatisticsController extends Controller
{
    /**
     * @var GroupStatisticsService
     */
    protected $service;

    public function __construct(GroupStatisticsService $service)
    {
        $this->service = $service;
    }

    /**
     * Показ списка ранее сгенерированных отчётов.
     */
    public function index()
    {
        $reports = GroupReport::orderBy('created_at', 'desc')->get();

        return view('statistics.group_index', [
            'reports' => $reports
        ]);
    }

    /**
     * Генерация нового отчёта: вызывает сервис.
     */
    public function generate(Request $request)
    {
        $report = $this->service->generateReport();

        if ($report) {
            return redirect()->back()->with('success', 'Отчёт успешно сгенерирован.');
        }

        return redirect()->back()->with('error', 'Не удалось собрать статистику (нет групп в базе).');
    }

    /**
     * Скачивание PDF ранее сгенерированного отчёта.
     *
     * @param int $id — ID записи в group_reports
     */
    public function download($id)
    {
        $report = GroupReport::findOrFail($id);

        // rawJson может быть строкой или уже массивом
        $rawJson = $report->report_data;
        if (is_string($rawJson)) {
            $allStats = json_decode($rawJson, true);
        } else {
            $allStats = $rawJson;
        }

        // Вместо $allStats['period'] ?? [...] пишем через isset()
        if (isset($allStats['period'])) {
            $period = $allStats['period'];
        } else {
            $period = ['from' => '', 'to' => ''];
        }

        if (isset($allStats['years'])) {
            $years = $allStats['years'];
        } else {
            $years = ['current_year' => '', 'previous_year' => ''];
        }

        if (isset($allStats['comparison'])) {
            $comparison = $allStats['comparison'];
        } else {
            $comparison = [];
        }

        $pdf = PDF::loadView('pdf.group_statistics', [
            'period'       => $period,
            'years'        => $years,
            'comparison'   => $comparison,
            'generated_at' => $report->created_at,
        ]);

        return $pdf->download("group_statistics_report_{$id}.pdf");
    }
}
