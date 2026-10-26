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
        Schema::create('registry_dataset_resources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('registry_dataset_id')->constrained()->cascadeOnDelete();
            $table->string('resource_id')->index();
            $table->string('name')->nullable();
            $table->string('format')->nullable()->index();
            $table->string('state')->nullable();
            $table->string('url', 2048);
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamp('last_modified')->nullable();
            $table->json('raw_payload');
            $table->timestamp('discovered_at');
            $table->timestamps();
            $table->unique(['registry_dataset_id', 'resource_id'], 'registry_dataset_resource_unique');
        });

        Schema::create('registry_parser_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('profile_key')->unique();
            $table->string('source_system')->index();
            $table->string('format')->index();
            $table->boolean('enabled')->default(true);
            $table->json('mapping');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('registry_ingest_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('registry_dataset_resource_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('registry_parser_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_system')->index();
            $table->string('source_identifier')->index();
            $table->string('source_url', 2048);
            $table->string('format')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('rows_read')->default(0);
            $table->unsignedInteger('rows_created')->default(0);
            $table->unsignedInteger('rows_updated')->default(0);
            $table->unsignedInteger('rows_rejected')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
            $table->unique(['source_system', 'source_identifier', 'registry_parser_profile_id'], 'registry_ingest_job_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registry_ingest_jobs');
        Schema::dropIfExists('registry_parser_profiles');
        Schema::dropIfExists('registry_dataset_resources');
    }
};
