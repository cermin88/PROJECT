<?php

namespace App\Services\Governance;

use App\Models\Contact;
use App\Models\Customer;
use App\Models\GovernanceDuplicateDecision;
use Illuminate\Support\Collection;

/**
 * Finds likely duplicate customers / contacts (UX-061 / UX-062).
 * Pairs already decided (Keep Separate / Merged) are skipped.
 */
class DuplicateDetector
{
    public static function normalizeName(?string $name): string
    {
        $n = strtolower((string) $name);
        $n = preg_replace('/\(.*?\)/', ' ', $n);              // drop "(JUPEM)"
        $n = str_replace('&', ' dan ', $n);                     // "Ukur & Pemetaan" == "Ukur dan Pemetaan"
        $n = preg_replace('/\b(sdn|bhd|berhad|the|and)\b/', ' ', $n);
        return trim(preg_replace('/[^a-z0-9]+/', ' ', $n));
    }

    public static function acronym(?string $name): ?string
    {
        return preg_match('/\(([A-Za-z0-9]{2,})\)/', (string) $name, $m) ? strtoupper($m[1]) : null;
    }

    public static function normalizeMobile(?string $m): string
    {
        return preg_replace('/\D+/', '', (string) $m);
    }

    // Why two customers look alike, or null when they don't
    public static function customerReason(Customer $a, Customer $b): ?string
    {
        $na = self::normalizeName($a->name);
        $nb = self::normalizeName($b->name);
        if ($na !== '' && $na === $nb) {
            return 'Same name';
        }
        if (($x = self::acronym($a->name)) && $x === self::acronym($b->name)) {
            return "Same acronym ({$x})";
        }
        similar_text($na, $nb, $pct);
        return $pct >= 88 ? 'Similar name ('.round($pct).'%)' : null;
    }

    public static function contactReason(Contact $a, Contact $b): ?string
    {
        if ($a->email && strcasecmp(trim($a->email), trim($b->email ?? '')) === 0) {
            return 'Same email';
        }
        $ma = self::normalizeMobile($a->mobile);
        if (strlen($ma) >= 8 && $ma === self::normalizeMobile($b->mobile)) {
            return 'Same mobile';
        }
        if ($a->customer_id === $b->customer_id && self::normalizeName($a->name) === self::normalizeName($b->name)) {
            return 'Same name at same customer';
        }
        return null;
    }

    public function customers(): Collection
    {
        return $this->pairs('customer', Customer::whereNull('archived_at')->where('archived', false)->orderBy('id')->get(), [self::class, 'customerReason']);
    }

    public function contacts(): Collection
    {
        return $this->pairs('contact', Contact::with('customer')->whereNull('archived_at')->orderBy('id')->get(), [self::class, 'contactReason']);
    }

    private function pairs(string $type, Collection $rows, callable $reason): Collection
    {
        $decided = GovernanceDuplicateDecision::where('entity_type', $type)->get()
            ->map(fn ($d) => min($d->keep_id, $d->other_id).'-'.max($d->keep_id, $d->other_id))->flip();
        $out = collect();
        $list = $rows->values();
        for ($i = 0; $i < $list->count(); $i++) {
            for ($j = $i + 1; $j < $list->count(); $j++) {
                [$a, $b] = [$list[$i], $list[$j]];
                if (isset($decided[$a->id.'-'.$b->id])) {
                    continue;
                }
                if ($r = $reason($a, $b)) {
                    $out->push(['a' => $a, 'b' => $b, 'reason' => $r]);
                }
            }
        }
        return $out;
    }
}
