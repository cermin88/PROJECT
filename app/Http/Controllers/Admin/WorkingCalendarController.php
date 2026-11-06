<?php

namespace App\Http\Controllers\Admin;

use App\Models\AdminSetting;
use App\Models\AuditLog;
use App\Models\WorkingCalendarDay;
use Carbon\Carbon;
use Illuminate\Http\Request;

class WorkingCalendarController extends AdminController
{
    public const TYPES = ['Federal', 'State', 'Company'];

    public function index(Request $request)
    {
        $this->gate(self::VIEWERS);
        $year = (int) $request->query('year', now()->year);
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now()->endOfMonth();
        $q = WorkingCalendarDay::whereYear('date', $year);
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('state', 'like', "%{$s}%"));
        }
        if (in_array($v = $request->query('type'), self::TYPES, true)) {
            $q->where('type', $v);
        }
        return view('admin.working-calendar', [
            'year' => $year,
            'holidays' => $q->orderBy('date')->paginate(config('csa.per_page'))->withQueryString(),
            'types' => self::TYPES,
            'weekend' => WorkingCalendarDay::weekend(),
            'from' => $from, 'to' => $to,
            'workingDays' => WorkingCalendarDay::workingDaysBetween(Carbon::parse($from), Carbon::parse($to)),
            'canEdit' => $this->canEdit(),
            'days' => ConfigurationController::DAYS,
        ]);
    }

    public function store(Request $request)
    {
        $this->gate(self::EDITORS);
        $d = $request->validate([
            'date' => 'required|date',
            'name' => 'required|string|max:120',
            'type' => 'required|in:Federal,State,Company',
            'state' => 'required_if:type,State|nullable|string|max:60',
        ]);
        if ($d['type'] !== 'State') {
            $d['state'] = null;
        }
        if (WorkingCalendarDay::whereDate('date', $d['date'])->where('name', $d['name'])->exists()) {
            return back()->with('err', 'That holiday is already in the calendar.');
        }
        $h = WorkingCalendarDay::create($d);
        AuditLog::record('HOLIDAY_ADDED', 'holiday', $h->id, $d);
        return redirect()->route('working-calendar', ['year' => $h->date->year])->with('ok', "{$h->name} added.");
    }

    public function destroy(WorkingCalendarDay $holiday)
    {
        $this->gate(self::EDITORS);
        AuditLog::record('HOLIDAY_REMOVED', 'holiday', $holiday->id, ['date' => $holiday->date->toDateString(), 'name' => $holiday->name]);
        $holiday->delete();
        return back()->with('ok', 'Holiday removed.');
    }

    public function weekend(Request $request)
    {
        $this->gate(self::EDITORS);
        $d = $request->validate(['weekend' => 'required|array|min:1|max:3', 'weekend.*' => 'in:' . implode(',', ConfigurationController::DAYS)]);
        $value = implode(',', array_values(array_intersect(ConfigurationController::DAYS, $d['weekend'])));
        $s = AdminSetting::updateOrCreate(['key' => 'weekend_days'], ['value' => $value, 'type' => 'list', 'group' => 'Working Calendar', 'label' => 'Weekend days', 'updated_by' => auth()->id()]);
        AuditLog::record('CONFIG_CHANGE', 'config', $s->id, ['weekend_days' => $value]);
        return back()->with('ok', "Weekend set to {$value}.");
    }
}
