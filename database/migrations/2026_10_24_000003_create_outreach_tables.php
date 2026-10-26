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
        Schema::create('leads', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->string('email_domain')->index();
            $table->string('target_business_name')->nullable();
            $table->string('target_business_domain')->nullable();
            $table->string('status')->default('unknown')->index();
            $table->json('status_reason_codes')->nullable();
            $table->string('policy_version')->default('v1');
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('source_url', 2048);
            $table->string('source_domain')->index();
            $table->timestamp('source_retrieved_at');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('crawl_visibility')->default('unknown');
            $table->string('page_title')->nullable();
            $table->text('page_text_snippet')->nullable();
            $table->text('email_found_context')->nullable();
            $table->string('evidence_hash', 64)->index();
            $table->timestamps();
            $table->unique(['lead_id', 'source_url', 'evidence_hash'], 'lead_source_evidence_unique');
        });

        Schema::create('suppression_list', function (Blueprint $table): void {
            $table->id();
            $table->string('scope')->default('email');
            $table->string('email')->nullable()->index();
            $table->string('email_domain')->nullable()->index();
            $table->string('reason');
            $table->string('source');
            $table->timestamp('noted_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('domain_mappings', function (Blueprint $table): void {
            $table->id();
            $table->string('business_domain')->index();
            $table->string('approved_source_domain')->index();
            $table->string('mapping_type')->default('exact');
            $table->timestamps();
            $table->unique(['business_domain', 'approved_source_domain'], 'domain_mapping_unique');
        });

        Schema::create('outreach_settings', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->text('value');
            $table->timestamps();
        });

        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('purpose')->default('fraud_warning');
            $table->string('subject_template');
            $table->longText('body_template');
            $table->string('status')->default('draft')->index();
            $table->timestamps();
        });

        Schema::create('campaign_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('recipient_email_snapshot');
            $table->string('email_domain')->index();
            $table->string('eligibility_snapshot')->default('unknown');
            $table->boolean('is_domain_winner')->default(false);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->string('skip_reason')->nullable();
            $table->string('message_id')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'lead_id']);
        });

        Schema::create('send_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type')->index();
            $table->string('reason_or_error')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('send_events');
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('outreach_settings');
        Schema::dropIfExists('domain_mappings');
        Schema::dropIfExists('suppression_list');
        Schema::dropIfExists('lead_sources');
        Schema::dropIfExists('leads');
    }
};
