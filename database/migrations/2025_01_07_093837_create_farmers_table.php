<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('farmers', function (Blueprint $table) {
            $table->id();
            $table->string('farmer');
            $table->unsignedBigInteger('user_id');
            $table->json('telegram_web_app');
            $table->json('headers');
            $table->boolean('is_connected')->default(true);
            $table->timestamps();

            $table->foreign('user_id')
                ->references('user_id')
                ->on('accounts')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->unique(['farmer', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('farmers');
    }
};
