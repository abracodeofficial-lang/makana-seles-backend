<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('leads', 'direction')) {
            Schema::table('leads', function (Blueprint $table) {
                // اتجاه نوع العقار يلي المهتم داور عليه — نفس قيم اتجاه العقارات
                $table->enum('direction', [
                    'شمالية', 'جنوبية', 'شرقية', 'غربية',
                    'شمالية شرقية', 'شمالية غربية', 'جنوبية شرقية', 'جنوبية غربية',
                ])->nullable()->after('property_type_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('direction');
        });
    }
};
