<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // قوائم أرقام محفوظة (أرشيف) — لكل قسم: owners أو leads
        if (!Schema::hasTable('contact_lists')) {
            Schema::create('contact_lists', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('audience', 10);           // owners | leads
                $table->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete();
                $table->timestamps();
                $table->index('audience');
            });
        }

        if (!Schema::hasTable('contact_list_members')) {
            Schema::create('contact_list_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contact_list_id')->constrained('contact_lists')->cascadeOnDelete();
                $table->unsignedBigInteger('contact_id');  // owner_id أو lead_id حسب audience القائمة
                $table->timestamps();
                $table->unique(['contact_list_id', 'contact_id']);
            });
        }

        // حملة واتساب = رسالة وحدة لمجموعة مستلمين (لقطة من أسمائهم وأرقامهم وقت الإنشاء)
        if (!Schema::hasTable('wa_campaigns')) {
            Schema::create('wa_campaigns', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('audience', 10);
                $table->text('message');
                $table->unsignedSmallInteger('min_delay')->default(30);   // ثواني بين كل رسالة
                $table->unsignedSmallInteger('max_delay')->default(90);
                $table->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('wa_campaign_recipients')) {
            Schema::create('wa_campaign_recipients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('wa_campaign_id')->constrained('wa_campaigns')->cascadeOnDelete();
                $table->unsignedBigInteger('contact_id');
                $table->string('name');
                $table->string('phone', 30);
                $table->string('status', 10)->default('pending');   // pending | sent | skipped
                $table->timestamp('sent_at')->nullable();
                $table->foreignId('sent_by')->nullable()->constrained('employees')->nullOnDelete();
                $table->timestamps();
                $table->index(['wa_campaign_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_campaign_recipients');
        Schema::dropIfExists('wa_campaigns');
        Schema::dropIfExists('contact_list_members');
        Schema::dropIfExists('contact_lists');
    }
};
