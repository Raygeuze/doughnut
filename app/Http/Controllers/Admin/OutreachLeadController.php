<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\RegistrySeedBusiness;
use App\Services\Outreach\AuditLogger;
use App\Services\Outreach\EligibilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OutreachLeadController extends Controller
{
    public function index(): View
    {
        $leads = Lead::query()->latest('id')->paginate(25);

        return view('admin.outreach.leads', [
            'leads' => $leads,
        ]);
    }

    public function store(Request $request, EligibilityService $eligibility): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'target_business_name' => ['nullable', 'string', 'max:255'],
            'target_business_domain' => ['required', 'string', 'max:255'],
            'source_url' => ['required', 'url', 'max:2048'],
            'visibility' => ['required', 'in:public,authenticated,paywalled,blocked,unknown'],
            'http_status' => ['required', 'integer', 'min:100', 'max:599'],
            'snippet' => ['required', 'string', 'max:2000'],
            'context' => ['nullable', 'string', 'max:2000'],
        ]);

        $lead = Lead::query()->firstOrCreate(
            ['email' => strtolower($validated['email'])],
            [
                'target_business_name' => $validated['target_business_name'] ?? null,
                'target_business_domain' => strtolower($validated['target_business_domain']),
                'first_seen_at' => CarbonImmutable::now(),
            ],
        );

        $lead->update([
            'target_business_name' => $validated['target_business_name'] ?? $lead->target_business_name,
            'target_business_domain' => strtolower($validated['target_business_domain']),
        ]);

        $host = parse_url($validated['source_url'], PHP_URL_HOST);
        $sourceDomain = is_string($host) ? strtolower($host) : '';

        $evidencePayload = [
            $validated['source_url'],
            $sourceDomain,
            (string) $validated['http_status'],
            (string) $validated['visibility'],
            (string) $validated['snippet'],
            (string) ($validated['context'] ?? ''),
        ];

        LeadSource::query()->create([
            'lead_id' => $lead->id,
            'source_url' => $validated['source_url'],
            'source_domain' => $sourceDomain,
            'source_retrieved_at' => CarbonImmutable::now(),
            'http_status' => (int) $validated['http_status'],
            'crawl_visibility' => $validated['visibility'],
            'page_title' => null,
            'page_text_snippet' => $validated['snippet'],
            'email_found_context' => $validated['context'] ?? null,
            'evidence_hash' => hash('sha256', json_encode($evidencePayload)),
        ]);

        $eligibility->evaluateLead($lead->fresh());

        return redirect()
            ->route('admin.outreach.leads.index')
            ->with('status', 'Lead evidence saved and eligibility recalculated.');
    }

    public function recalculate(Lead $lead, EligibilityService $eligibility): RedirectResponse
    {
        $eligibility->evaluateLead($lead);

        return redirect()
            ->route('admin.outreach.leads.index')
            ->with('status', "Recalculated eligibility for {$lead->email}.");
    }

    public function purgeWithdrawnRecord(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'abn' => ['nullable', 'string', 'max:64'],
            'source_record_id' => ['nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $abn = isset($validated['abn']) ? preg_replace('/\D+/', '', (string) $validated['abn']) : null;
        $sourceRecordId = trim((string) ($validated['source_record_id'] ?? ''));

        if (($abn === null || $abn === '') && $sourceRecordId === '') {
            return redirect()
                ->route('admin.outreach.leads.index')
                ->with('status', 'Purge skipped. Provide an ABN or source record ID.');
        }

        $query = RegistrySeedBusiness::query()->where('source_system', 'abr_webservice');

        if ($abn !== null && $abn !== '') {
            $query->where('abn', $abn);
        }

        if ($sourceRecordId !== '') {
            $query->orWhere(function ($inner) use ($sourceRecordId): void {
                $inner
                    ->where('source_system', 'abr_webservice')
                    ->where('source_record_id', $sourceRecordId);
            });
        }

        $records = $query->get();
        $count = $records->count();

        foreach ($records as $record) {
            $audit->log(
                entityType: 'registry_seed_business',
                entityId: $record->id,
                action: 'withdrawn_data_purged',
                sourceSystem: $record->source_system,
                sourceIdentifier: $record->source_record_id,
                payload: [
                    'abn' => $record->abn,
                    'business_name' => $record->business_name,
                    'reason' => $validated['reason'],
                ],
            );
        }

        if ($count > 0) {
            RegistrySeedBusiness::query()->whereIn('id', $records->pluck('id'))->delete();
        }

        $audit->log(
            entityType: 'registry_withdrawal_purge',
            entityId: null,
            action: 'withdrawn_data_purge_requested',
            sourceSystem: 'abr_webservice',
            sourceIdentifier: $sourceRecordId !== '' ? $sourceRecordId : ($abn ?: null),
            payload: [
                'abn' => $abn,
                'source_record_id' => $sourceRecordId !== '' ? $sourceRecordId : null,
                'reason' => $validated['reason'],
                'records_deleted' => $count,
            ],
        );

        return redirect()
            ->route('admin.outreach.leads.index')
            ->with('status', "Withdrawal purge complete. Deleted {$count} record(s).");
    }
}
