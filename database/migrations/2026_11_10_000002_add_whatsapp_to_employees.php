<?php

use App\Models\Employee;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// رقم واتساب للموظف (لأكواد استعادة كلمة المرور) — بياخد رقم الهاتف كبداية،
// والأرقام اللي بتبلّش بـ 00 بتصير + (00201… → +201…)
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('employees', 'whatsapp')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('whatsapp', 30)->nullable()->after('phone');
            });
        }

        DB::table('employees')->select('id', 'phone', 'whatsapp')->orderBy('id')->get()->each(function ($e) {
            $phone = Employee::cleanPhone($e->phone);
            // رقم الهاتف فريد — ما منغيّره إذا الصيغة الجديدة موجودة لموظف تاني
            if ($phone !== $e->phone && DB::table('employees')->where('phone', $phone)->where('id', '!=', $e->id)->exists()) {
                $phone = $e->phone;
            }
            DB::table('employees')->where('id', $e->id)->update([
                'phone'    => $phone,
                'whatsapp' => Employee::cleanPhone($e->whatsapp) ?: $phone,
            ]);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('employees', 'whatsapp')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('whatsapp');
            });
        }
    }
};
