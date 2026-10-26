@extends('admin.outreach.layout')

@section('content')
    <div class="grid grid-4">
        <section class="card">
            <div class="muted">Leads Total</div>
            <div class="kpi">{{ $leadCounts['total'] }}</div>
        </section>
        <section class="card">
            <div class="muted">Eligible</div>
            <div class="kpi">{{ $leadCounts['eligible'] }}</div>
        </section>
        <section class="card">
            <div class="muted">Ineligible</div>
            <div class="kpi">{{ $leadCounts['ineligible'] }}</div>
        </section>
        <section class="card">
            <div class="muted">Unknown</div>
            <div class="kpi">{{ $leadCounts['unknown'] }}</div>
        </section>
    </div>

    <div class="grid grid-4" style="margin-top: 16px;">
        <section class="card">
            <div class="muted">Campaigns Total</div>
            <div class="kpi">{{ $campaignCounts['total'] }}</div>
        </section>
        <section class="card">
            <div class="muted">Draft</div>
            <div class="kpi">{{ $campaignCounts['draft'] }}</div>
        </section>
        <section class="card">
            <div class="muted">Queued</div>
            <div class="kpi">{{ $campaignCounts['queued'] }}</div>
        </section>
        <section class="card">
            <div class="muted">Completed</div>
            <div class="kpi">{{ $campaignCounts['completed'] }}</div>
        </section>
    </div>

    <div class="grid grid-2" style="margin-top: 16px;">
        <section class="card">
            <h3 style="margin-top: 0;">Recent Send Events</h3>
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Type</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentEvents as $event)
                        <tr>
                            <td>{{ $event->occurred_at }}</td>
                            <td>{{ $event->event_type }}</td>
                            <td>{{ $event->reason_or_error ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="muted">No events yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="card">
            <h3 style="margin-top: 0;">Recent Suppressions</h3>
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Scope</th>
                        <th>Target</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentSuppressions as $entry)
                        <tr>
                            <td>{{ $entry->noted_at }}</td>
                            <td>{{ $entry->scope }}</td>
                            <td>{{ $entry->email ?? $entry->email_domain }}</td>
                            <td>{{ $entry->reason }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">No suppression entries yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>

    <section class="card" style="margin-top: 16px;">
        <h3 style="margin-top: 0;">Immutable Audit Log</h3>
        <table>
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Entity</th>
                    <th>Action</th>
                    <th>Source</th>
                    <th>Identifier</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentAuditLogs as $log)
                    <tr>
                        <td>{{ $log->occurred_at }}</td>
                        <td>{{ $log->entity_type }}#{{ $log->entity_id ?? '-' }}</td>
                        <td>{{ $log->action }}</td>
                        <td>{{ $log->source_system ?? '-' }}</td>
                        <td>{{ $log->source_identifier ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No audit entries yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
@endsection
