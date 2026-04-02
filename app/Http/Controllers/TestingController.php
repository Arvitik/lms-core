<?php

namespace App\Http\Controllers;

use App\Report;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Mpdf\Mpdf;

class TestingController extends Controller
{
    public function reports()
    {
        $reportId = request()->get('report_id');
        if ($reportId) {
            return $this->download($reportId);
        }

        $reports = Report::with('teacher')->orderBy('created_at', 'desc')->get();
        foreach ($reports as $report) {
            $parsed = $this->parseReportData($report);
            $report->period = $parsed['period'];
            $report->grouped = $parsed['grouped'];
            $report->problem_count = $parsed['problem_count'];
            $report->inactive_count = $parsed['inactive_count'];
            $report->students_count = $parsed['students_count'];
        }

        return view('testing.reports', ['reports' => $reports]);
    }

    public function generate(Request $request, ReportService $reportService)
    {
        $this->validate($request, [
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $from = $request->input('from');
        $to = $request->input('to');

        if ((empty($from) xor empty($to))) {
            return redirect()->back()->with('error', 'Specify both dates: from and to.');
        }

        $count = $reportService->generateWeeklyReports($from, $to);

        if ($count > 0) {
            return redirect()->back()->with('success', 'Reports generated: ' . $count);
        }

        return redirect()->back()->with('error', 'No reports were generated (no teachers/groups/students for selected period).');
    }

    public function download($id)
    {
        $report = Report::with('teacher')->findOrFail($id);
        $parsed = $this->parseReportData($report);

        $html = view('pdf.report', [
            'report' => $report,
            'grouped' => $parsed['grouped'],
            'period' => $parsed['period'],
            'problemCount' => $parsed['problem_count'],
            'inactiveCount' => $parsed['inactive_count'],
            'studentsCount' => $parsed['students_count'],
        ])->render();

        $mpdf = $this->makePdfEngine();
        $mpdf->WriteHTML($html);
        $content = $mpdf->Output('', 'S');

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="weekly_report_' . $report->id . '.pdf"',
        ]);
    }

    private function parseReportData(Report $report)
    {
        $raw = json_decode($report->data, true);
        if (!is_array($raw)) {
            $raw = [];
        }

        if (isset($raw['students']) && is_array($raw['students'])) {
            $students = $raw['students'];
            $period = isset($raw['period']) && is_array($raw['period']) ? $raw['period'] : ['from' => null, 'to' => null];
            $summary = isset($raw['summary']) && is_array($raw['summary']) ? $raw['summary'] : [];
        } else {
            $students = $raw;
            $period = ['from' => null, 'to' => null];
            $summary = [];
        }

        $grouped = collect($students)->groupBy(function ($row) {
            return isset($row['group']) ? $row['group'] : 'No group';
        });

        $hasProblemInSummary = array_key_exists('problem_students_total', $summary);
        $hasInactiveInSummary = array_key_exists('inactive_students_total', $summary);

        $problemCount = $hasProblemInSummary ? (int) $summary['problem_students_total'] : 0;
        $inactiveCount = $hasInactiveInSummary ? (int) $summary['inactive_students_total'] : 0;
        $studentsCount = isset($summary['students_total']) ? (int) $summary['students_total'] : count($students);

        if (!$hasProblemInSummary || !$hasInactiveInSummary) {
            foreach ($students as $student) {
                $danger = !empty($student['danger']);
                $inactive = isset($student['inactive']) ? (bool) $student['inactive'] : (isset($student['attempts']) && (int) $student['attempts'] === 0);
                if ($danger) {
                    $problemCount++;
                }
                if ($inactive) {
                    $inactiveCount++;
                }
            }
        }

        return [
            'period' => $period,
            'grouped' => $grouped,
            'problem_count' => $problemCount,
            'inactive_count' => $inactiveCount,
            'students_count' => $studentsCount,
        ];
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
