<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            // Dotted key, e.g. "site.name", "contact.email", "social.instagram".
            $table->string('key')->unique();
            $table->text('value')->nullable();
            // text | image | url | email | phone — drives which Filament field
            // is rendered on the settings page.
            $table->string('type')->default('text');
            $table->string('group')->default('general');
            $table->timestamps();

            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
