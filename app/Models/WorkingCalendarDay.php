<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Model;

// Public / company holidays (UX-072). Weekend days come from the 'weekend_days' setting.
class WorkingCalendarDay extends Model
{
    protected $guarded = [];
    protected $casts = ['date' => 'date'];

    public static function weekend(): array
    {
        return AdminSetting::get('weekend_days', ['Saturday', 'Sunday']);
    }

    /** Working days from $from to $to inclusive (excludes weekend + non-State holidays). */
    public static function workingDaysBetween(Carbon $from, Carbon $to): int
    {
        if ($to->lt($from)) {
            return 0;
        }
        $weekend = static::weekend();
        $holidays = static::whereNull('state')->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()->map(fn ($h) => $h->date->toDateString())->all();
        $n = 0;
        foreach (CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()) as $d) {
            if (! in_array($d->format('l'), $weekend, true) && ! in_array($d->toDateString(), $holidays, true)) {
                $n++;
            }
        }
        return $n;
    }
}
