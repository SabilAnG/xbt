<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // Field names mirror what the tracking page's JavaScript reads.
            $table->string('provider')->nullable();
            $table->string('tracking_number');
            $table->string('status')->default('in_transit');
            $table->string('status_description')->nullable();
            $table->timestamp('status_updated_at')->nullable();
            $table->timestamp('last_checked')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['order_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
