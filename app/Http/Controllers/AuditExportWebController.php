<?php

namespace App\Http\Controllers;

use App\Exports\ArrayExport;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditExportService;
use App\Support\SpreadsheetValueSanitizer;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditExportWebController extends Controller
{
    public function __construct(
        private readonly AuditExportService $auditExportService,
    ) {
    }

    public function csv(Request $request): StreamedResponse
    {
        $actor = $request->user();
        $this->assertCanExport($actor);

        $filters = $this->resolveFilters($request);
        $rows = $this->auditExportService->getExportRows($filters);

        return $this->streamCsv('audit_logs.csv', $rows);
    }

    public function excel(Request $request): BinaryFileResponse
    {
        $actor = $request->user();
        $this->assertCanExport($actor);
        $this->assertExcelAvailable();

        $filters = $this->resolveFilters($request);
        $rows = $this->auditExportService->getExportRows($filters);

        return Excel::download(new ArrayExport($rows), 'audit_logs.xlsx');
    }

    private function resolveFilters(Request $request): array
    {
        $validated = $request->validate([
            'actor_id' => ['nullable', 'integer', 'exists:users,id'],
            'event' => ['nullable', 'string', 'max:120'],
        ]);

        return array_filter($validated, fn ($value) => $value !== null && $value !== '');
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
        abort_unless($user->can('viewAny', AuditLog::class), 403);
    }

    private function assertExcelAvailable(): void
    {
        if (! class_exists(Excel::class)) {
            abort(500, 'Excel-Export ist nicht verfügbar. Bitte Composer-Abhängigkeiten installieren.');
        }
    }
}
