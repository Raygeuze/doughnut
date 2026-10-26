<?php

namespace App\Mail;

use App\Models\Campaign;
use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class FraudWarningMailable extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Campaign $campaign,
        public Lead $lead,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->campaign->subject_template);
    }

    public function content(): Content
    {
        $unsubscribeUrl = URL::temporarySignedRoute(
            'outreach.unsubscribe',
            now()->addDays(365),
            ['lead' => $this->lead->id, 'campaign' => $this->campaign->id],
        );

        return new Content(
            view: 'emails.fraud-warning',
            with: [
                'bodyTemplate' => $this->campaign->body_template,
                'lead' => $this->lead,
                'unsubscribeUrl' => $unsubscribeUrl,
            ],
        );
    }
}
