<?php

namespace App\Http\Controllers;

use App\Exports\ArrayExport;
use App\Models\User;
use App\Services\ComplianceExportService;
use App\Support\SpreadsheetValueSanitizer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComplianceExportWebController extends Controller
{
    public function __construct(
        private readonly ComplianceExportService $complianceExportService,
    ) {
    }

    public function dayCsv(Request $request): StreamedResponse
    {
        $actor = $request->user();
        $this->assertCanExport($actor);

        $date = Carbon::parse((string) $request->input('date', now()->toDateString()))->toDateString();
        $rows = $this->complianceExportService->getDayExportRows($actor, $date);

        return $this->streamCsv("compliance_tag_{$date}.csv", $rows);
    }

    public function dayExcel(Request $request): BinaryFileResponse
    {
        $actor = $request->user();
        $this->assertCanExport($actor);
        $this->assertExcelAvailable();

        $date = Carbon::parse((string) $request->input('date', now()->toDateString()))->toDateString();
        $rows = $this->complianceExportService->getDayExportRows($actor, $date);

        return Excel::download(new ArrayExport($rows), "compliance_tag_{$date}.xlsx");
    }

    public function weekCsv(Request $request): StreamedResponse
    {
        $actor = $request->user();
        $this->assertCanExport($actor);

        $date = Carbon::parse((string) $request->input('date', now()->toDateString()))->toDateString();
        $rows = $this->complianceExportService->getWeekExportRows($actor, $date);

        return $this->streamCsv("compliance_woche_{$date}.csv", $rows);
    }

    public function weekExcel(Request $request): BinaryFileResponse
    {
        $actor = $request->user();
        $this->assertCanExport($actor);
        $this->assertExcelAvailable();

        $date = Carbon::parse((string) $request->input('date', now()->toDateString()))->toDateString();
        $rows = $this->complianceExportService->getWeekExportRows($actor, $date);

        return Excel::download(new ArrayExport($rows), "compliance_woche_{$date}.xlsx");
    }

    public function monthCsv(Request $request): StreamedResponse
    {
        $actor = $request->user();
        $this->assertCanExport($actor);

        $month = (string) $request->input('month', now()->format('Y-m'));
        $rows = $this->complianceExportService->getMonthExportRows($actor, $month);

        return $this->streamCsv("compliance_monat_{$month}.csv", $rows);
    }

    public function monthExcel(Request $request): BinaryFileResponse
    {
        $actor = $request->user();
        $this->assertCanExport($actor);
        $this->assertExcelAvailable();

        $month = (string) $request->input('month', now()->format('Y-m'));
        $rows = $this->complianceExportService->getMonthExportRows($actor, $month);

        return Excel::download(new ArrayExport($rows), "compliance_monat_{$month}.xlsx");
    }

    private function streamCsv(string $filename, array $rows): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = static function () use ($rows): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            if ($rows !== []) {
                fputcsv($handle, array_keys($rows[0]), ';');

                foreach ($rows as $row) {
                    fputcsv($handle, array_values(SpreadsheetValueSanitizer::sanitizeRow($row)), ';');
                }
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function assertCanExport(User $user): void
    {
        abort_unless($user->hasAnyPermission([
            'time.export.own',
            'time.export.team',
            'time.export.all',
        ]), 403);
    }

    private function assertExcelAvailable(): void
    {
        if (! class_exists(Excel::class)) {
            abort(500, 'Excel-Export ist nicht verfuegbar. Bitte Composer-Abhaengigkeiten installieren.');
        }
    }
}
