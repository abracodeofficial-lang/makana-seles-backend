<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// المهتمين: "تاريخ الإضافة التجريبي" صار تاريخ حقيقي (تقويم) بدل نص حر
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('leads', 'trial_added_date')) return;

        // تحويل أي قيم نصية موجودة لصيغة Y-m-d، واللي ما بتنفهم بتصير فاضية
        DB::table('leads')->whereNotNull('trial_added_date')->orderBy('id')
            ->each(function ($row) {
                $raw = trim((string) $row->trial_added_date);
                $date = null;
                foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'j/n/Y'] as $fmt) {
                    try {
                        $c = Carbon::createFromFormat('!' . $fmt, $raw);
                        if ($c && $c->format($fmt) === $raw) { $date = $c->format('Y-m-d'); break; }
                    } catch (\Throwable) {}
                }
                DB::table('leads')->where('id', $row->id)->update(['trial_added_date' => $date]);
            });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE leads MODIFY trial_added_date DATE NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('leads', 'trial_added_date') && DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE leads MODIFY trial_added_date VARCHAR(100) NULL');
        }
    }
};
