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
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('breed_id')->nullable()->constrained()->nullOnDelete();

            // Basic Information
            $table->string('name');
            $table->string('age'); // Store as string to allow "3 months", "2 years", etc.
            $table->string('gender'); // Backend will handle validation
            $table->string('color');
            $table->decimal('weight', 8, 2)->nullable(); // Support decimal weights
            $table->text('description'); // Use text instead of string for longer descriptions

            // Listing Information
            $table->string('listing_type')->default('adoption'); // Backend will handle validation
            $table->decimal('price', 10, 2)->nullable();
            $table->string('status')->default('available'); // Backend will handle validation

            // Location Information
            $table->string('address')->nullable();
            $table->text('detailed_address')->nullable(); // Use text for potentially long addresses
            $table->string('city');
            $table->string('state');
            $table->string('postal_code')->nullable();
            $table->string('country');
            $table->decimal('latitude', 10, 8)->nullable(); // Proper precision for coordinates
            $table->decimal('longitude', 11, 8)->nullable(); // Proper precision for coordinates

            // Health Information (Basic)
            $table->string('health_status')->default('healthy'); // Backend will handle validation
            $table->boolean('vaccinated')->default(false);
            $table->boolean('spayed_neutered')->default(false);
            $table->text('special_needs')->nullable();
            $table->date('last_vet_visit')->nullable();

            // Healthcare Information (Detailed) - Store as JSON for flexibility
            $table->json('vaccinations')->nullable(); // [{date: '2024-01-01', name: 'Rabies'}]
            $table->json('medications')->nullable(); // [{name: 'Heartgard', usage: 'Monthly heartworm prevention'}]
            $table->json('allergies')->nullable(); // ['Chicken', 'Pollen']
            $table->string('vet_name')->nullable();
            $table->string('vet_phone', 20)->nullable();

            // Personality & Traits
            $table->json('traits')->nullable(); // Array of trait IDs or strings

            // Additional Information - Store as JSON key-value pairs
            $table->json('additional_info')->nullable(); // [{key: 'House Trained', value: 'Yes'}]

            $table->timestamps();
            $table->softDeletes(); // Add soft deletes for safety

            // Indexes for better query performance
            $table->index('user_id');
            $table->index('category_id');
            $table->index('breed_id');
            $table->index('listing_type');
            $table->index('status');
            $table->index(['city', 'state']); // Composite index for location searches
            $table->index('created_at');
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
