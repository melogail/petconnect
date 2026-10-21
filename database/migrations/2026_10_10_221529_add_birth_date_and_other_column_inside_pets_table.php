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
        Schema::table('pets', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            $table->string('color')->nullable()->change();
            $table->dropColumn('age');

            $table->date('birth_date')->nullable()->after('name');
            $table->unsignedTinyInteger('quantity')->default(1)->after('gender');
            $table->unsignedTinyInteger('male_count')->nullable()->after('quantity');
            $table->unsignedTinyInteger('female_count')->nullable()->after('male_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
            $table->string('color')->nullable(false)->change();
            $table->string('age')->nullable()->after('name');

            $table->dropColumn(['quantity', 'male_count', 'female_count']);
        });
    }
};
