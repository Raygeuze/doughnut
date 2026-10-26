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
        Schema::create('registry_business_domains', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('registry_seed_business_id')->constrained()->cascadeOnDelete();
            $table->string('candidate_domain')->index();
            $table->unsignedTinyInteger('confidence_score')->default(0)->index();
            $table->string('status')->default('candidate')->index();
            $table->string('evidence_url', 2048)->nullable();
            $table->string('evidence_type')->nullable();
            $table->json('signals')->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();
            $table->unique(['registry_seed_business_id', 'candidate_domain'], 'registry_business_domain_unique');
        });

        Schema::create('registry_crawl_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('registry_business_domain_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('crawl_visibility')->default('unknown');
            $table->text('page_title')->nullable();
            $table->longText('page_text_snippet')->nullable();
            $table->unsignedInteger('emails_found')->default(0);
            $table->timestamp('crawled_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registry_crawl_runs');
        Schema::dropIfExists('registry_business_domains');
    }
};
