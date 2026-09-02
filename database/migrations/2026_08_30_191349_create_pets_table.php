<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `pets_status_deleted_at_created_at_index` is the home feed's index, and
     * it replaces the single-column `status` index rather than joining it.
     * The feed issues `where status = ? and deleted_at is null order by
     * created_at desc limit 12`; `status = 'available'` matches ~94% of rows,
     * so a `status`-only index narrows nothing and the whole matching set gets
     * sorted to return one page. The composite spans the two equality columns
     * and the ordering column, so the same page is a range scan with no sort.
     * `status` is the leading column of it, so anything that filtered on
     * `status` alone is still covered — see .ai/rules/migrations.md.
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

            $table->index('user_id');
            $table->index('category_id');
            $table->index('breed_id');
            $table->index('listing_type');
            $table->index(['status', 'deleted_at', 'created_at'], 'pets_status_deleted_at_created_at_index');
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
