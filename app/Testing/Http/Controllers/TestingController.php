<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PDF;
use App\Report;

class TestingController extends Controller
{
    public function reports()
    {
        $reportId = request()->get('report_id');

        if ($reportId) {
            $report = Report::findOrFail($reportId);
            $data = json_decode($report->data, true);
            $grouped = collect($data)->groupBy('group');
            $allGroupedData = [$report->teacher_id => $grouped];

            $pdf = PDF::loadView('pdf.report', [
                'allGroupedData' => $allGroupedData,
                'reports' => [$report]
            ]);

            return $pdf->download('weekly_report_' . $report->id . '.pdf');
        }

        $reports = Report::with('teacher')->get();
        $allGroupedData = [];

        foreach ($reports as $report) {
            $data = json_decode($report->data, true);
            $grouped = collect($data)->groupBy('group');
            $allGroupedData[$report->teacher_id] = $grouped;
        }

        return view('testing.reports', [
            'reports' => $reports,
            'allGroupedData' => $allGroupedData,
        ]);
    }

    public function download($id)
    {
        $report = Report::with('teacher')->findOrFail($id);
        $data = json_decode($report->data, true);
        $grouped = collect($data)->groupBy('group');

        $pdf = PDF::loadView('pdf.report', [
            'allGroupedData' => [$report->teacher_id => $grouped],
            'reports' => [$report]
        ]);

        return $pdf->download('weekly_report_' . $report->id . '.pdf');
    }

}
