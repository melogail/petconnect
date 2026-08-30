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
        Schema::create('pets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('breed_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->string('age');
            $table->string('gender');
            $table->string('color');
            $table->decimal('weight', 8, 2)->nullable();
            $table->text('description');

            $table->string('listing_type')->default('adoption');
            $table->decimal('price', 10, 2)->nullable();
            $table->string('status')->default('available');
            $table->integer('views')->default(0);

            $table->string('address')->nullable();
            $table->text('detailed_address')->nullable();
            $table->string('city');
            $table->string('state');
            $table->string('postal_code')->nullable();
            $table->string('country');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            $table->string('health_status')->default('healthy');
            $table->boolean('vaccinated')->default(false);
            $table->boolean('spayed_neutered')->default(false);
            $table->text('special_needs')->nullable();
            $table->date('last_vet_visit')->nullable();

            $table->json('vaccinations')->nullable();
            $table->json('medications')->nullable();
            $table->json('allergies')->nullable();
            $table->string('vet_name')->nullable();
            $table->string('vet_phone', 20)->nullable();

            $table->json('traits')->nullable();
            $table->json('additional_info')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('listing_type');
            $table->index('status');
            $table->index('created_at');
            $table->index(['city', 'state'], 'pets_city_state_index');
            $table->index(['latitude', 'longitude'], 'pets_latitude_longitude_index');
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
