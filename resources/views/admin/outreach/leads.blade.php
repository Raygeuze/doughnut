@extends('admin.outreach.layout')

@section('content')
    <div class="grid grid-2">
        <section class="card stack">
            <h3 style="margin: 0;">Add Lead Evidence</h3>
            <form method="post" action="{{ route('admin.outreach.leads.store') }}" class="stack">
                @csrf
                <label>Email
                    <input type="email" name="email" required>
                </label>
                <label>Target business domain
                    <input type="text" name="target_business_domain" placeholder="example.com" required>
                </label>
                <label>Target business name
                    <input type="text" name="target_business_name" placeholder="Optional">
                </label>
                <label>Source URL
                    <input type="url" name="source_url" placeholder="https://example.com/contact" required>
                </label>
                <div class="grid grid-2">
                    <label>Visibility
                        <select name="visibility" required>
                            <option value="public">public</option>
                            <option value="authenticated">authenticated</option>
                            <option value="paywalled">paywalled</option>
                            <option value="blocked">blocked</option>
                            <option value="unknown">unknown</option>
                        </select>
                    </label>
                    <label>HTTP status
                        <input type="number" name="http_status" value="200" min="100" max="599" required>
                    </label>
                </div>
                <label>Snippet
                    <textarea name="snippet" rows="4" required></textarea>
                </label>
                <label>Context
                    <textarea name="context" rows="3"></textarea>
                </label>
                <button type="submit">Save and Recalculate</button>
            </form>
        </section>

        <section class="card">
            <h3 style="margin-top: 0;">Eligibility Rules In Effect</h3>
            <p class="muted">This panel classifies leads automatically and allows role and name mailboxes. Ownership relevance, public source, suppression, and evidence completeness are enforced.</p>
            <table>
                <thead>
                    <tr>
                        <th>Setting</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Cooldown days default</td>
                        <td>{{ config('outreach.cooldown_days') }}</td>
                    </tr>
                    <tr>
                        <td>Policy version</td>
                        <td>{{ config('outreach.policy_version') }}</td>
                    </tr>
                    <tr>
                        <td>Domain suppression propagation</td>
                        <td>{{ config('outreach.domain_suppression_propagation_enabled') ? 'enabled' : 'disabled' }}</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>

    <section class="card" style="margin-top: 16px;">
        <h3 style="margin-top: 0;">ABR Withdrawal Purge</h3>
        <p class="muted">Use this when ABR instructs removal of withdrawn records. This will delete matching ABR seed records and write immutable audit entries.</p>
        <form method="post" action="{{ route('admin.outreach.leads.purge_withdrawn') }}" class="grid grid-2" style="margin-bottom: 16px;">
            @csrf
            <label>ABN (optional)
                <input type="text" name="abn" placeholder="e.g. 51835430479">
            </label>
            <label>Source Record ID (optional)
                <input type="text" name="source_record_id" placeholder="ABR source record id">
            </label>
            <label style="grid-column: 1 / -1;">Reason for purge
                <textarea name="reason" rows="2" placeholder="ABR withdrawal notice reference" required></textarea>
            </label>
            <div>
                <button type="submit" class="warn">Purge withdrawn record(s)</button>
            </div>
        </form>

        <h3 style="margin-top: 0;">Leads</h3>
        <table>
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Domain</th>
                    <th>Status</th>
                    <th>Reasons</th>
                    <th>Last verified</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($leads as $lead)
                    <tr>
                        <td>{{ $lead->email }}</td>
                        <td>{{ $lead->target_business_domain }}</td>
                        <td><span class="tag {{ $lead->status }}">{{ $lead->status }}</span></td>
                        <td>{{ is_array($lead->status_reason_codes) ? implode(', ', $lead->status_reason_codes) : '-' }}</td>
                        <td>{{ $lead->last_verified_at ?? '-' }}</td>
                        <td>
                            <form method="post" action="{{ route('admin.outreach.leads.recalculate', $lead) }}">
                                @csrf
                                <button type="submit" class="secondary">Recalculate</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No leads yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div style="margin-top: 12px;">{{ $leads->links() }}</div>
    </section>
@endsection
