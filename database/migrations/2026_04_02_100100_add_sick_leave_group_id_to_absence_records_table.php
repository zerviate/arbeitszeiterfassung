<?php

use App\Models\AbsenceRecord;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absence_records', function (Blueprint $table): void {
            $table->foreignId('sick_leave_group_id')
                ->nullable()
                ->after('reference_group')
                ->constrained('sick_leave_groups')
                ->nullOnDelete();
        });

        $sickLeaveRecords = DB::table('absence_records')
            ->where('type', AbsenceRecord::TYPE_SICK_LEAVE)
            ->orderBy('user_id')
            ->orderBy('absence_date')
            ->orderBy('id')
            ->get();

        if ($sickLeaveRecords->isEmpty()) {
            return;
        }

        $groupBuckets = [];

        foreach ($sickLeaveRecords as $record) {
            $recordGroupKey = $record->reference_group ?: 'legacy-sick-record-'.$record->id;
            $bucketKey = $record->user_id.'|'.$recordGroupKey;

            if (! array_key_exists($bucketKey, $groupBuckets)) {
                $groupBuckets[$bucketKey] = [];
            }

            $groupBuckets[$bucketKey][] = $record;
        }

        $usedGroupKeys = [];

        foreach ($groupBuckets as $records) {
            $first = $records[0];
            $last = $records[count($records) - 1];

            $baseKey = (string) ($first->reference_group ?: 'legacy-sick-group-'.$first->id);
            $groupKey = $this->buildUniqueGroupKey($baseKey, $usedGroupKeys);
            $note = $this->resolveNote($records);

            $groupId = DB::table('sick_leave_groups')->insertGetId([
                'group_key' => $groupKey,
                'user_id' => $first->user_id,
                'start_date' => $first->absence_date,
                'end_date' => $last->absence_date,
                'note' => $note,
                'recorded_by' => $this->resolveRecordedBy($records),
                'meta' => json_encode([
                    'migrated_from_reference_group' => $first->reference_group,
                    'migrated_record_count' => count($records),
                ], JSON_THROW_ON_ERROR),
                'created_at' => $first->created_at ?? now('UTC'),
                'updated_at' => $last->updated_at ?? now('UTC'),
            ]);

            $recordIds = collect($records)
                ->pluck('id')
                ->values()
                ->all();

            DB::table('absence_records')
                ->whereIn('id', $recordIds)
                ->update([
                    'sick_leave_group_id' => $groupId,
                    'reference_group' => $groupKey,
                    'updated_at' => now('UTC'),
                ]);
        }
    }

    public function down(): void
    {
        DB::table('absence_records')
            ->whereNotNull('sick_leave_group_id')
            ->where('type', AbsenceRecord::TYPE_SICK_LEAVE)
            ->update([
                'sick_leave_group_id' => null,
                'updated_at' => now('UTC'),
            ]);

        Schema::table('absence_records', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sick_leave_group_id');
        });
    }

    private function buildUniqueGroupKey(string $baseKey, array &$usedGroupKeys): string
    {
        $trimmedBase = trim($baseKey);
        $candidate = $trimmedBase !== '' ? $trimmedBase : 'sick-'.Str::lower(Str::random(10));
        $candidate = Str::limit($candidate, 120, '');

        if (! array_key_exists($candidate, $usedGroupKeys)) {
            $usedGroupKeys[$candidate] = true;

            return $candidate;
        }

        do {
            $suffix = '-'.Str::lower(Str::random(8));
            $prefix = Str::limit($candidate, 120 - strlen($suffix), '');
            $nextCandidate = $prefix.$suffix;
        } while (array_key_exists($nextCandidate, $usedGroupKeys));

        $usedGroupKeys[$nextCandidate] = true;

        return $nextCandidate;
    }

    private function resolveRecordedBy(array $records): ?int
    {
        foreach ($records as $record) {
            if ($record->recorded_by !== null) {
                return (int) $record->recorded_by;
            }
        }

        return null;
    }

    private function resolveNote(array $records): ?string
    {
        foreach ($records as $record) {
            $note = $record->note;

            if (! is_string($note)) {
                continue;
            }

            $trimmed = trim($note);

            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }
};
