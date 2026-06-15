<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// COUPLE-OWNED. Карточка задачи. CoupleScoped ⚑
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained('couples')->cascadeOnDelete();
            $table->foreignId('board_column_id')->constrained('board_columns')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('assignee')->default('together'); // him|her|together
            $table->timestamp('due_at')->nullable();
            $table->boolean('done')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->index(['couple_id']);
            $table->index(['board_column_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
