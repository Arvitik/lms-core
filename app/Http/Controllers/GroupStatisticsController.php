<?php

namespace App\Http\Controllers;

use App\GroupReport;
use App\Services\GroupStatisticsService;
use Illuminate\Http\Request;
use Mpdf\Mpdf;

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

    public function index()
    {
        $reportsRaw = GroupReport::orderBy('created_at', 'desc')->get();
        $reports = [];

        foreach ($reportsRaw as $report) {
            $raw = is_array($report->report_data) ? $report->report_data : json_decode($report->report_data, true);
            if (!is_array($raw)) {
                continue;
            }

            $report->period_from = isset($raw['period']['from']) ? $raw['period']['from'] : '—';
            $report->period_to = isset($raw['period']['to']) ? $raw['period']['to'] : '—';
            $report->groups_count = isset($raw['summary']['groups_count']) ? $raw['summary']['groups_count'] : 0;
            $report->students_total = isset($raw['summary']['students_total']) ? $raw['summary']['students_total'] : 0;
            $report->problem_students_total = isset($raw['summary']['problem_students_total']) ? $raw['summary']['problem_students_total'] : 0;
            $reports[] = $report;
        }

        return view('statistics.group_index', ['reports' => $reports]);
    }

    public function generate(Request $request)
    {
        $this->validate($request, [
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $from = $request->input('from');
        $to = $request->input('to');

        if ((empty($from) xor empty($to))) {
            return redirect()->back()->with('error', 'Укажите обе даты периода: "с" и "по".');
        }

        $report = $this->service->generateReport($from, $to);
        if ($report) {
            return redirect()->back()->with('success', 'Отчет успешно сформирован.');
        }

        return redirect()->back()->with('error', 'Не удалось собрать статистику (нет групп или данных).');
    }

    public function download($id)
    {
        $report = GroupReport::findOrFail($id);
        $allStats = is_array($report->report_data) ? $report->report_data : json_decode($report->report_data, true);
        if (!is_array($allStats)) {
            $allStats = [];
        }

        $period = isset($allStats['period']) ? $allStats['period'] : ['from' => '', 'to' => ''];
        $previousPeriod = isset($allStats['previous_period']) ? $allStats['previous_period'] : ['from' => '', 'to' => ''];
        $comparison = isset($allStats['comparison']) ? $allStats['comparison'] : [];
        $summary = isset($allStats['summary']) ? $allStats['summary'] : [];

        $html = view('pdf.group_statistics', [
            'period' => $period,
            'previous_period' => $previousPeriod,
            'comparison' => $comparison,
            'summary' => $summary,
            'generated_at' => $report->created_at,
        ])->render();

        $mpdf = $this->makePdfEngine();
        $mpdf->WriteHTML($html);
        $content = $mpdf->Output('', 'S');

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="group_statistics_report_' . $id . '.pdf"',
        ]);
    }

    private function makePdfEngine()
    {
        $tempDir = storage_path('app/mpdf-temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        return new Mpdf([
            'tempDir' => $tempDir,
            'mode' => 'utf-8',
            'format' => 'A4',
        ]);
    }
}
