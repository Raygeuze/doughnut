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
        Schema::create('registry_datasets', function (Blueprint $table): void {
            $table->id();
            $table->string('portal')->default('data.gov.au')->index();
            $table->string('dataset_id')->index();
            $table->string('name')->nullable();
            $table->string('title')->nullable();
            $table->string('organization_name')->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->string('license_id')->nullable();
            $table->boolean('is_open')->default(false);
            $table->unsignedInteger('num_resources')->default(0);
            $table->json('raw_payload');
            $table->timestamp('discovered_at');
            $table->timestamps();
            $table->unique(['portal', 'dataset_id']);
        });

        Schema::create('registry_seed_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('source_system')->index();
            $table->string('query_type');
            $table->string('query_value')->nullable();
            $table->json('query_metadata')->nullable();
            $table->unsignedInteger('results_count')->default(0);
            $table->timestamp('retrieved_at');
            $table->timestamps();
        });

        Schema::create('registry_seed_businesses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('registry_seed_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_system')->index();
            $table->string('source_record_id')->index();
            $table->string('business_name')->nullable();
            $table->string('abn')->nullable()->index();
            $table->string('state')->nullable();
            $table->string('postcode')->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->timestamp('source_retrieved_at');
            $table->string('status')->default('pending')->index();
            $table->json('raw_payload');
            $table->timestamps();
            $table->unique(['source_system', 'source_record_id'], 'registry_seed_unique_source_record');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registry_seed_businesses');
        Schema::dropIfExists('registry_seed_batches');
        Schema::dropIfExists('registry_datasets');
    }
};
