<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('breed_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color');
            $table->string('age');
            $table->boolean('gender');  // 1 = male, 0 = female
            $table->string('weight')->nullable();
            $table->string('vaccinated')->default(0);
            $table->string('sterilized')->default(0);
            $table->string('description')->nullable();
            $table->string('listing_type')->default('adoption');
            $table->decimal('price', 10, 2)->nullable();
            $table->boolean('status')->default(1);  // 1 = available, 0 = unavailable / sold
            $table->string('image');
            $table->json('attributes')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pets');
    }
};
