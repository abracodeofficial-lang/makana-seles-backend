<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// العقارات اللي المهتم مهتم فيها (مهتم ← عدة عقارات)
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lead_property')) return;

        Schema::create('lead_property', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['lead_id', 'property_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_property');
    }
};
