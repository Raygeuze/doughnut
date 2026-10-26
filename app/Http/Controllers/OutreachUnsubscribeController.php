<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\Outreach\SuppressionService;
use Illuminate\Http\Request;

class OutreachUnsubscribeController extends Controller
{
    public function __invoke(Request $request, Lead $lead, SuppressionService $suppressionService)
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        $suppressionService->suppressEmail(
            email: $lead->email,
            reason: 'unsubscribe',
            source: 'unsubscribe_link',
            metadata: [
                'campaign_id' => (int) $request->query('campaign'),
            ],
        );

        return response()->view('outreach.unsubscribed');
    }
}
