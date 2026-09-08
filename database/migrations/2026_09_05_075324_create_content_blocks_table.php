<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_blocks', function (Blueprint $table) {
            $table->id();
            // Dotted key scoped by page, e.g. "home.hero.title", "about.image.1".
            $table->string('key')->unique();
            // Which page the block belongs to — the admin list groups on this.
            $table->string('page')->index();
            $table->string('label');
            // text | html | image
            $table->string('type')->default('text');
            $table->longText('value')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['page', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_blocks');
    }
};
