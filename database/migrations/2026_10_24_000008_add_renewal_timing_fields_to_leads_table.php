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
        Schema::table('leads', function (Blueprint $table): void {
            $table->timestamp('next_business_renewal_at')->nullable()->after('last_sent_at')->index();
            $table->timestamp('next_domain_renewal_at')->nullable()->after('next_business_renewal_at')->index();
            $table->string('renewal_signal_source')->nullable()->after('next_domain_renewal_at');
            $table->unsignedTinyInteger('renewal_confidence_score')->default(0)->after('renewal_signal_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropColumn([
                'next_business_renewal_at',
                'next_domain_renewal_at',
                'renewal_signal_source',
                'renewal_confidence_score',
            ]);
        });
    }
};
