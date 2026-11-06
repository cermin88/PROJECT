<input name="title" placeholder="Task title" value="{{ old('title',$t->title ?? '') }}" required style="min-width:220px">
<select name="assignee_id" title="Assignee">@foreach($assignees as $u)<option value="{{ $u->id }}" @selected((int)old('assignee_id',$t->assignee_id ?? auth()->id())===$u->id)>{{ $u->name }}</option>@endforeach</select>
<input type="date" name="due_date" title="Due date" value="{{ old('due_date', isset($t) ? $t->due_date->toDateString() : today()->addDays(3)->toDateString()) }}" required>
<select name="customer_id"><option value="">Customer (optional)</option>@foreach($customers as $c)<option value="{{ $c->id }}" @selected((int)old('customer_id',$t->customer_id ?? 0)===$c->id)>{{ $c->name }}</option>@endforeach</select>
<select name="opportunity_id"><option value="">Opportunity (optional)</option>@foreach($opps as $o)<option value="{{ $o->id }}" @selected((int)old('opportunity_id',$t->opportunity_id ?? 0)===$o->id)>{{ $o->code }}</option>@endforeach</select>
<select name="tender_id"><option value="">Tender (optional)</option>@foreach($tenders as $x)<option value="{{ $x->id }}" @selected((int)old('tender_id',$t->tender_id ?? 0)===$x->id)>{{ $x->code }}</option>@endforeach</select>
<input name="notes" placeholder="Notes" value="{{ old('notes',$t->notes ?? '') }}">
