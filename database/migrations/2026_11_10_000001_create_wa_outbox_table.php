<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// رسائل واتساب فورية (مثل كود استعادة كلمة المرور) — بتنبعت قبل الحملات وبدون حدودها
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wa_outbox')) return;

        Schema::create('wa_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 30);
            $table->text('message');
            $table->string('purpose', 30)->default('general');
            $table->enum('status', ['pending', 'sending', 'sent', 'failed'])->default('pending');
            $table->string('error')->nullable();
            $table->timestamp('expires_at')->nullable(); // ما بتنبعت بعد هالوقت
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_outbox');
    }
};
