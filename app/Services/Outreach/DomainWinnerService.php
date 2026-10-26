<?php

namespace App\Services\Outreach;

use App\Models\Lead;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DomainWinnerService
{
    /**
     * @param Collection<int, Lead> $leads
     * @return array{winner:Lead|null, scores:array<string,int>}
     */
    public function pickWinner(Collection $leads): array
    {
        if ($leads->isEmpty()) {
            return ['winner' => null, 'scores' => []];
        }

        $scores = [];

        foreach ($leads as $lead) {
            $scores[$lead->email] = $this->scoreEmail($lead->email);
        }

        $winner = $leads
            ->sort(function (Lead $a, Lead $b) use ($scores): int {
                $scoreCompare = $scores[$b->email] <=> $scores[$a->email];
                if ($scoreCompare !== 0) {
                    return $scoreCompare;
                }

                $aVerified = $a->last_verified_at?->getTimestamp() ?? 0;
                $bVerified = $b->last_verified_at?->getTimestamp() ?? 0;
                $verifiedCompare = $bVerified <=> $aVerified;
                if ($verifiedCompare !== 0) {
                    return $verifiedCompare;
                }

                return strcmp($a->email, $b->email);
            })
            ->first();

        return [
            'winner' => $winner,
            'scores' => $scores,
        ];
    }

    public function scoreEmail(string $email): int
    {
        $localPart = Str::before($email, '@');
        $normalized = strtolower(preg_replace('/[^a-z]/', '', $localPart) ?? $localPart);

        /** @var array<string,int> $roleScores */
        $roleScores = config('outreach.domain_winner.role_scores', []);

        foreach ($roleScores as $prefix => $score) {
            if (str_starts_with($normalized, $prefix)) {
                return (int) $score;
            }
        }

        return (int) config('outreach.domain_winner.named_mailbox_score', 40);
    }
}
