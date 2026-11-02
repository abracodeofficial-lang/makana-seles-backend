<?php

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Database\Migrations\Migration;

// طلب الإدارة: انصراف السجلات المضافة بوقت الشيفت اللي ضلت مفتوحة (يوم 2 نوفمبر 2026) — بنهاية الشيفت
return new class extends Migration
{
    public function up(): void
    {
        $records = Attendance::where('notes', 'إضافة من الإدارة بوقت الشيفت')->whereNull('check_out')->get();

        foreach ($records as $record) {
            $end = Employee::find($record->employee_id)?->shift?->end_time;
            if ($end) $record->update(['check_out' => $end]);
        }
    }

    public function down(): void {}
};
