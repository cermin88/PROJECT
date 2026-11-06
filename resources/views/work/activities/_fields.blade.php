<select name="type">@foreach($types as $t)<option @selected(old('type',$a->type ?? null)===$t)>{{ $t }}</option>@endforeach</select>
<input name="subject" placeholder="Subject" value="{{ old('subject',$a->subject ?? '') }}" required>
<select name="customer_id" required><option value="">Customer…</option>@foreach($customers as $c)<option value="{{ $c->id }}" @selected((int)old('customer_id',$a->customer_id ?? ($prefill['customer_id'] ?? 0))===$c->id)>{{ $c->name }}</option>@endforeach</select>
<select name="opportunity_id"><option value="">Opportunity (optional)</option>@foreach($opps as $o)<option value="{{ $o->id }}" @selected((int)old('opportunity_id',$a->opportunity_id ?? ($prefill['opportunity_id'] ?? 0))===$o->id)>{{ $o->code }} — {{ \Illuminate\Support\Str::limit($o->name,40) }}</option>@endforeach</select>
<select name="tender_id"><option value="">Tender (optional)</option>@foreach($tenders as $t)<option value="{{ $t->id }}" @selected((int)old('tender_id',$a->tender_id ?? ($prefill['tender_id'] ?? 0))===$t->id)>{{ $t->code }}</option>@endforeach</select>
<input type="datetime-local" name="activity_at" value="{{ old('activity_at', isset($a) ? $a->activity_at->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i')) }}" required>
<input name="next_action" placeholder="Next action" value="{{ old('next_action',$a->next_action ?? '') }}">
<input type="date" name="next_action_date" title="Next action date" value="{{ old('next_action_date', isset($a) ? $a->next_action_date?->toDateString() : '') }}">
<input name="outcome" placeholder="Outcome / notes" value="{{ old('outcome',$a->outcome ?? '') }}" style="min-width:240px">
