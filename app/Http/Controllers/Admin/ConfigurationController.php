<?php

namespace App\Http\Controllers\Admin;

use App\Models\AdminSetting;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class ConfigurationController extends AdminController
{
    public const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    public function index()
    {
        $this->gate(self::VIEWERS);
        return view('admin.configuration', [
            'groups' => AdminSetting::with('editor')->orderBy('group')->orderBy('id')->get()->groupBy('group'),
            'canEdit' => $this->canEdit(),
        ]);
    }

    public function update(Request $request)
    {
        $this->gate(self::EDITORS);
        $settings = AdminSetting::all()->keyBy('key');
        $rules = [];
        foreach ($settings as $key => $s) {
            $rules["s.$key"] = match ($s->type) {
                'int' => 'required|integer|min:0|max:100000000',
                'list' => ['required', 'string', 'max:200', $key === 'weekend_days'
                    ? 'regex:/^\s*(' . implode('|', self::DAYS) . ')(\s*,\s*(' . implode('|', self::DAYS) . '))*\s*$/'
                    : 'regex:/^\s*\d+(\s*,\s*\d+)*\s*$/'],
                default => 'nullable|string|max:500',
            };
        }
        $d = $request->validate($rules, ['regex' => 'The :attribute format is invalid (use a comma-separated list).']);
        $changes = [];
        foreach ($d['s'] as $key => $value) {
            $s = $settings[$key];
            $value = $s->type === 'list' ? implode(',', array_map('trim', explode(',', $value))) : (string) $value;
            if ($s->value !== $value) {
                $changes[$key] = [$s->value, $value];
                $s->update(['value' => $value, 'updated_by' => auth()->id()]);
            }
        }
        if (! $changes) {
            return back()->with('ok', 'No changes.');
        }
        AuditLog::record('CONFIG_CHANGE', 'config', null, $changes);
        return back()->with('ok', 'Configuration saved and audit logged (' . count($changes) . ' change(s)).');
    }
}
