<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // جدول مدد الإذونات
        Schema::create('permission_durations', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // ساعة واحدة، ساعتين، نصف يوم...
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // بيانات افتراضية
        DB::table('permission_durations')->insert([
            ['name' => 'ساعة واحدة', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'ساعتين',     'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'نصف يوم',   'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // تحويل duration من ENUM إلى VARCHAR حتى تصبح المدد مرنة
        Schema::table('permission_requests', function (Blueprint $table) {
            $table->string('duration', 100)->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_durations');

        DB::statement("ALTER TABLE permission_requests MODIFY COLUMN duration ENUM('ساعة واحدة','ساعتين','نصف يوم') NOT NULL");
    }
};
