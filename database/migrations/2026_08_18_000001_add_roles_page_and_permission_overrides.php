<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        // صفحة إدارة "الأدوار والصلاحيات" الجديدة — تُمنح تلقائياً لمجموعة مدير النظام
        $adminGroupId = DB::table('permission_groups')->where('name', 'مدير النظام')->value('id');

        if ($adminGroupId && !DB::table('permission_group_pages')
                ->where('permission_group_id', $adminGroupId)
                ->where('page_key', 'roles')
                ->exists()) {
            DB::table('permission_group_pages')->insert([
                'permission_group_id' => $adminGroupId,
                'page_key'            => 'roles',
                'page_name'           => 'الأدوار والصلاحيات',
                'can_view'            => true,
                'can_add'             => true,
                'can_edit'            => true,
                'can_delete'          => true,
                'can_export'          => true,
                'can_approve'         => true,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        }

        // استثناءات صلاحيات فردية لموظف معين (تتجاوز صلاحيات مجموعته)
        Schema::create('employee_permission_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('page_key');
            $table->boolean('can_view')->nullable();
            $table->boolean('can_add')->nullable();
            $table->boolean('can_edit')->nullable();
            $table->boolean('can_delete')->nullable();
            $table->boolean('can_export')->nullable();
            $table->boolean('can_approve')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'page_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_permission_overrides');

        DB::table('permission_group_pages')
            ->whereIn('permission_group_id', DB::table('permission_groups')->where('name', 'مدير النظام')->pluck('id'))
            ->where('page_key', 'roles')
            ->delete();
    }
};
