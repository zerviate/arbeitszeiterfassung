<?php

namespace App\Services;

class TrafficLightService
{
    public function evaluateDay(
        int $targetMinutes,
        int $actualMinutes,
        int $vacationMinutes,
        int $sickLeaveMinutes,
        bool $isScheduledWorkday,
        bool $isHoliday = false,
        array $flags = [],
    ): array {
        $targetMinutes = max(0, $targetMinutes);
        $actualMinutes = max(0, $actualMinutes);
        $vacationMinutes = max(0, $vacationMinutes);
        $sickLeaveMinutes = max(0, $sickLeaveMinutes);

        $creditedMinutes = $actualMinutes + $vacationMinutes + $sickLeaveMinutes;
        $balanceMinutes = $creditedMinutes - $targetMinutes;

        if ($isHoliday) {
            if ($actualMinutes > 0) {
                return [
                    'day_status' => 'holiday_work',
                    'traffic_light' => 'green',
                    'balance_minutes' => $actualMinutes,
                ];
            }

            return [
                'day_status' => 'holiday',
                'traffic_light' => 'grey',
                'balance_minutes' => 0,
            ];
        }

        if (in_array('missing_contract', $flags, true)) {
            return [
                'day_status' => $actualMinutes > 0 ? 'worked_without_contract' : 'missing_contract',
                'traffic_light' => 'yellow',
                'balance_minutes' => $actualMinutes > 0 ? $actualMinutes : 0,
            ];
        }

        if (! $isScheduledWorkday && $creditedMinutes === 0) {
            return [
                'day_status' => 'off_day',
                'traffic_light' => 'grey',
                'balance_minutes' => 0,
            ];
        }

        if ($vacationMinutes > 0) {
            return [
                'day_status' => 'vacation',
                'traffic_light' => 'green',
                'balance_minutes' => 0,
            ];
        }

        if ($sickLeaveMinutes > 0) {
            return [
                'day_status' => 'sick_leave',
                'traffic_light' => 'green',
                'balance_minutes' => 0,
            ];
        }

        if (in_array('open_session', $flags, true)) {
            return [
                'day_status' => 'incomplete',
                'traffic_light' => 'yellow',
                'balance_minutes' => $balanceMinutes,
            ];
        }

        if ($targetMinutes === 0 && $actualMinutes > 0) {
            return [
                'day_status' => 'extra_work',
                'traffic_light' => 'green',
                'balance_minutes' => $actualMinutes,
            ];
        }

        if ($balanceMinutes >= 0) {
            return [
                'day_status' => 'fulfilled',
                'traffic_light' => 'green',
                'balance_minutes' => $balanceMinutes,
            ];
        }

        if ($balanceMinutes >= -30) {
            return [
                'day_status' => 'minor_under_target',
                'traffic_light' => 'yellow',
                'balance_minutes' => $balanceMinutes,
            ];
        }

        return [
            'day_status' => 'under_target',
            'traffic_light' => 'red',
            'balance_minutes' => $balanceMinutes,
        ];
    }
}
