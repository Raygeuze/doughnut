@extends('admin.outreach.layout')

@section('content')
    <section class="card">
        <h2 style="margin: 0 0 8px;">Campaign #{{ $campaign->id }}: {{ $campaign->name }}</h2>
        <p class="muted">Status: {{ $campaign->status }}</p>
        <p class="muted">Timing policy: {{ $campaign->enforce_renewal_window ? 'Renewal-window enforced' : 'Any-time outreach allowed' }}</p>
        <div class="actions" style="margin-bottom: 12px;">
            <form method="post" action="{{ route('admin.outreach.campaigns.build_queue', $campaign) }}">
                @csrf
                <button type="submit" class="secondary">Build queue</button>
            </form>
            <form method="post" action="{{ route('admin.outreach.campaigns.send', $campaign) }}">
                @csrf
                <button type="submit" class="warn">Send campaign</button>
            </form>
            <form method="post" action="{{ route('admin.outreach.campaigns.timing_policy', $campaign) }}" style="display: inline-flex; gap: 8px; align-items: center;">
                @csrf
                <label style="display: inline-flex; gap: 8px; align-items: center;">
                    <input type="checkbox" name="enforce_renewal_window" value="1" {{ $campaign->enforce_renewal_window ? 'checked' : '' }}>
                    Enforce renewal window
                </label>
                <button type="submit">Update timing</button>
            </form>
            <a href="{{ route('admin.outreach.campaigns.index') }}">Back to campaigns</a>
        </div>
        <h3>Subject</h3>
        <p>{{ $campaign->subject_template }}</p>
        <h3>Body</h3>
        <p style="white-space: pre-wrap;">{{ $campaign->body_template }}</p>
    </section>

    <section class="card" style="margin-top: 16px;">
        <h3 style="margin-top: 0;">Recipients</h3>
        <table>
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Domain</th>
                    <th>Winner</th>
                    <th>Queued</th>
                    <th>Sent</th>
                    <th>Skipped</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>
                @forelse($campaign->recipients as $recipient)
                    <tr>
                        <td>{{ $recipient->recipient_email_snapshot }}</td>
                        <td>{{ $recipient->email_domain }}</td>
                        <td>{{ $recipient->is_domain_winner ? 'yes' : 'no' }}</td>
                        <td>{{ $recipient->queued_at ?? '-' }}</td>
                        <td>{{ $recipient->sent_at ?? '-' }}</td>
                        <td>{{ $recipient->skipped_at ?? '-' }}</td>
                        <td>{{ $recipient->skip_reason ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No recipients yet. Build queue first.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
@endsection
