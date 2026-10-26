@extends('admin.outreach.layout')

@section('content')
    <div class="grid grid-2">
        <section class="card stack">
            <h3 style="margin: 0;">Create Campaign</h3>
            <form method="post" action="{{ route('admin.outreach.campaigns.store') }}" class="stack">
                @csrf
                <label>Name
                    <input type="text" name="name" required>
                </label>
                <label>Subject
                    <input type="text" name="subject_template" required>
                </label>
                <label>Body
                    <textarea name="body_template" rows="6" required></textarea>
                </label>
                <label style="display: flex; gap: 8px; align-items: center;">
                    <input type="hidden" name="enforce_renewal_window" value="0">
                    <input type="checkbox" name="enforce_renewal_window" value="1" checked>
                    Enforce renewal timing window for this campaign
                </label>
                <button type="submit">Create campaign</button>
            </form>
        </section>

        <section class="card">
            <h3 style="margin-top: 0;">Queue and Send Policy</h3>
            <p class="muted">When you build queue, the system selects only one winning address per domain. When you send, all eligibility, suppression, and cooldown checks are enforced again.</p>
            <p class="muted">This prevents duplicate outreach per business and keeps enforcement deterministic.</p>
        </section>
    </div>

    <section class="card" style="margin-top: 16px;">
        <h3 style="margin-top: 0;">Campaigns</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Timing Policy</th>
                    <th>Recipients</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($campaigns as $campaign)
                    <tr>
                        <td>{{ $campaign->id }}</td>
                        <td>{{ $campaign->name }}</td>
                        <td>{{ $campaign->status }}</td>
                        <td>{{ $campaign->enforce_renewal_window ? 'renewal-window' : 'any-time' }}</td>
                        <td>{{ $campaign->recipients_count }}</td>
                        <td>
                            <a href="{{ route('admin.outreach.campaigns.show', $campaign) }}">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No campaigns yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div style="margin-top: 12px;">{{ $campaigns->links() }}</div>
    </section>
@endsection
