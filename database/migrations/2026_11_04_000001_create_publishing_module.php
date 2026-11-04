<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// موديول النشر: حالة العقار، معرض الصور، منشورات كل منصة وسجلها، إعدادات الربط، والطابور
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            if (!Schema::hasColumn('properties', 'uuid'))               $table->char('uuid', 36)->nullable();
            if (!Schema::hasColumn('properties', 'workflow_status'))    $table->string('workflow_status', 30)->default('draft')->index();
            if (!Schema::hasColumn('properties', 'return_note'))        $table->text('return_note')->nullable();
            if (!Schema::hasColumn('properties', 'ready_at'))           $table->timestamp('ready_at')->nullable();
            if (!Schema::hasColumn('properties', 'ready_by'))           $table->unsignedBigInteger('ready_by')->nullable();
            if (!Schema::hasColumn('properties', 'ad_license_expiry'))  $table->date('ad_license_expiry')->nullable();
            if (!Schema::hasColumn('properties', 'fal_license_number')) $table->string('fal_license_number', 50)->nullable();
            if (!Schema::hasColumn('properties', 'listing_purpose'))    $table->string('listing_purpose', 10)->nullable();
        });

        DB::table('properties')->whereNull('uuid')->orderBy('id')->select('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) DB::table('properties')->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]);
        });

        if (!Schema::hasTable('property_images')) {
            Schema::create('property_images', function (Blueprint $table) {
                $table->id();
                $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
                $table->char('uuid', 36)->unique();      // الرابط العام للصورة بالـ uuid مش بالرقم
                $table->string('file_path');
                $table->string('file_name');
                $table->unsignedInteger('sort_order')->default(0);
                $table->softDeletes();
                $table->unsignedBigInteger('deleted_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('property_publications')) {
            Schema::create('property_publications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
                $table->string('platform', 30);           // string مش enum: منصة جديدة = كلاس جديد بس
                $table->string('status', 20)->default('pending')->index();
                $table->string('external_id')->nullable();
                $table->string('external_url', 500)->nullable();
                $table->string('payload_hash', 64)->nullable();
                $table->text('error_message')->nullable();
                $table->unsignedBigInteger('published_by')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();
                $table->unique(['property_id', 'platform']);
            });
        }

        if (!Schema::hasTable('publication_logs')) {
            Schema::create('publication_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('publication_id')->constrained('property_publications')->cascadeOnDelete();
                $table->string('action', 20);
                $table->string('status', 20);
                $table->text('request_summary')->nullable();
                $table->text('response_summary')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('publishing_settings')) {
            Schema::create('publishing_settings', function (Blueprint $table) {
                $table->id();
                $table->string('public_base_url')->nullable();
                $table->string('wordpress_base_url')->nullable();
                $table->string('wordpress_username')->nullable();
                $table->text('wordpress_app_password')->nullable();   // مشفّر
                $table->boolean('wordpress_mock')->default(true);
                $table->text('bayut_token')->nullable();              // مشفّر
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        $this->seedPermissions();
    }

    private function seedPermissions(): void
    {
        $page = fn(int $groupId, string $key, string $name, array $can) => DB::table('permission_group_pages')->updateOrInsert(
            ['permission_group_id' => $groupId, 'page_key' => $key],
            ['page_name' => $name, 'created_at' => now(), 'updated_at' => now()] + array_merge(
                ['can_view' => 0, 'can_add' => 0, 'can_edit' => 0, 'can_delete' => 0, 'can_export' => 0, 'can_approve' => 0],
                array_fill_keys($can, 1)
            )
        );
        $all = ['can_view', 'can_add', 'can_edit', 'can_delete', 'can_export', 'can_approve'];

        // كل مجموعة عندها تحكم كامل بالأدوار بتاخد صفحة النشر (حتى يضل الأدمن أدمن)
        $admins = DB::table('permission_group_pages')
            ->where('page_key', 'roles')->where('can_view', 1)->where('can_edit', 1)->where('can_delete', 1)
            ->pluck('permission_group_id')
            ->merge(DB::table('permission_groups')->where('name', 'مدير النظام')->pluck('id'))
            ->unique();
        foreach ($admins as $id) $page($id, 'publishing', 'النشر', $all);

        $group = function (string $name, string $description) {
            $existing = DB::table('permission_groups')->where('name', $name)->whereNull('deleted_at')->value('id');
            return $existing ?: DB::table('permission_groups')->insertGetId([
                'name' => $name, 'description' => $description, 'created_at' => now(), 'updated_at' => now(),
            ]);
        };

        $publisher = $group('ناشر', 'ينشر العقارات الجاهزة على المنصات، وما بيعدّل بيانات العقار');
        $page($publisher, 'publishing', 'النشر', ['can_view', 'can_edit']);
        $page($publisher, 'properties', 'العقارات', ['can_view']);

        $dataEntry = $group('مدخل بيانات', 'يضيف ويعدّل العقارات ويحوّلها لجاهزة للنشر');
        $page($dataEntry, 'properties', 'العقارات', ['can_view', 'can_add', 'can_edit']);
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_logs');
        Schema::dropIfExists('property_publications');
        Schema::dropIfExists('property_images');
        Schema::dropIfExists('publishing_settings');
    }
};
