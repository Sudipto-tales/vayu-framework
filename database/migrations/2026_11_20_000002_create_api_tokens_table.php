<?php

use Vayu\Database\Migrations\Migration;
use Vayu\Database\Schema\Blueprint;
use Vayu\Database\Schema\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table): void {
            $table->id();
            $table->bigInteger('user_id')->index();
            $table->string('name')->default('api');
            $table->string('token')->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
