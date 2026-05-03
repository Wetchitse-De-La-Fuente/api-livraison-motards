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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_id')->constrained('utilisateurs')->cascadeOnDelete();
            $table->foreignId('motard_id')->nullable()->constrained('utilisateurs')->nullOnDelete();

            $table->string('pickup_address');
            $table->string('delivery_address');

            $table->decimal('pickup_latitude', 10, 7);
            $table->decimal('pickup_longitude', 10, 7);
            $table->decimal('delivery_latitude', 10, 7);
            $table->decimal('delivery_longitude', 10, 7);

            $table->text('description')->nullable();

            $table->decimal('distance_km', 10, 2)->default(0);
            $table->decimal('duration_min', 10, 2)->default(0);
            $table->integer('waiting_minutes')->default(0);
            $table->decimal('pickup_fee', 10, 2)->default(0);
            $table->decimal('estimated_price', 10, 2)->default(0);
            $table->decimal('final_price', 10, 2)->default(0);

            $table->enum('status', ['en_attente','acceptee','recuperation_en_cours','en_livraison','livree','annulee'])->default('en_attente');

            // =========================
            // PARTAGE POSITION CLIENT
            // =========================
            $table->boolean('client_location_shared')->default(false);
            $table->decimal('client_current_latitude', 10, 7)->nullable();
            $table->decimal('client_current_longitude', 10, 7)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
