@php
  $quickUser = auth()->user();
  $quickCanCustomer = \App\Http\Controllers\Sales\SalesRules::canCreate($quickUser);
  $quickCanSales = \App\Http\Controllers\Sales\SalesRules::canCreateSales($quickUser);
  $quickCanEvent = \App\Models\CsaEvent::canCreate($quickUser);
  $quickCanPartner = \App\Http\Controllers\Ecosystem\Access::editsEcosystem($quickUser);
  $quickCanCase = in_array($quickUser->role, ['CC Manager', 'CC Team Lead', 'CC Executive', 'HOD', 'Superadmin'], true) && $quickUser->canManageCases();
  $quickTypes = array_filter([
    'customer' => $quickCanCustomer,
    'contact' => $quickCanCustomer,
    'opportunity' => $quickCanSales,
    'activity' => true,
    'task' => true,
    'tender' => $quickCanSales,
    'event' => $quickCanEvent,
    'partner' => $quickCanPartner,
    'case' => $quickCanCase,
  ]);
  $quickCustomers = \App\Models\Customer::active()->orderBy('name')->get(['id', 'name']);
  $quickOpportunities = \App\Models\Opportunity::active()->orderBy('code')->get(['id', 'code', 'name', 'customer_id']);
  $quickTenders = \App\Models\Tender::orderBy('code')->get(['id', 'code', 'title', 'customer_id']);
  $quickOwners = \App\Models\User::whereIn('role', \App\Http\Controllers\Sales\SalesRules::OWNER_ROLES)->orderBy('name')->get(['id', 'name']);
  $quickPartnerOwners = \App\Models\User::whereIn('role', ['HOD', 'AHOD', 'BD Manager', 'BD Executive', 'Sales Admin', 'Sales Admin / Inside Sales'])
      ->orderBy('name')->get(['id', 'name']);
  $quickAssignees = in_array($quickUser->role, ['Management', 'HOD', 'AHOD', 'Superadmin', 'BD Manager', 'CC Manager', 'CC Team Lead'], true)
      ? \App\Models\User::where('active', true)->orderBy('name')->get(['id', 'name'])
      : collect([$quickUser]);
@endphp
<dialog id="quick-create-dialog" aria-labelledby="quick-create-title">
  <div class="quick-create-modal">
    <div class="quick-create-heading">
      <div>
        <h2 id="quick-create-title">Quick Create</h2>
        <p class="mute">Add a record. It will be saved and appear in your dashboard.</p>
      </div>
      <button class="alt" type="button" data-quick-create-close aria-label="Close quick create">Close</button>
    </div>
    <label class="quick-create-type">Record type
      <select id="quick-create-type">
        @foreach($quickTypes as $type => $allowed)
          <option value="{{ $type }}" @selected(old('quick_create') === $type)>{{ ucfirst($type) }}</option>
        @endforeach
      </select>
    </label>

    @if($quickCanCustomer)
    <form class="quick-create-form" data-quick-create-form="customer" method="POST" action="{{ route('customers.store') }}">
      @csrf<input type="hidden" name="quick_create" value="customer"><input type="hidden" name="quick_create_page" value="{{ old('quick_create_page') }}">
      <label>Organisation name <input name="name" value="{{ old('name') }}" required maxlength="255"></label>
      <label>Type <select name="type" required>@foreach(\App\Http\Controllers\Sales\SalesRules::CUSTOMER_TYPES as $type)<option @selected(old('type') === $type)>{{ $type }}</option>@endforeach</select></label>
      <label>Sector <input name="sector" value="{{ old('sector') }}" maxlength="100"></label>
      <label>State <input name="state" value="{{ old('state', 'Federal') }}" maxlength="100"></label>
      <div class="quick-create-actions"><button type="submit">Create customer</button></div>
    </form>

    <form class="quick-create-form" data-quick-create-form="contact" method="POST" action="{{ route('contacts.store') }}">
      @csrf<input type="hidden" name="quick_create" value="contact"><input type="hidden" name="quick_create_page" value="{{ old('quick_create_page') }}">
      <label>Organisation <select name="customer_id" required>@foreach($quickCustomers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->name }}</option>@endforeach</select></label>
      <label>Contact name <input name="name" value="{{ old('name') }}" required maxlength="255"></label>
      <label>Email <input type="email" name="email" value="{{ old('email') }}" maxlength="255"></label>
      <label>Mobile <input name="mobile" value="{{ old('mobile') }}" maxlength="30"></label>
      <label>Position <input name="position" value="{{ old('position') }}" maxlength="120"></label>
      <label>Division <input name="division" value="{{ old('division') }}" maxlength="120"></label>
      <label>Buying role <select name="buying_role"><option value="">Select role</option>@foreach(\App\Http\Controllers\Sales\SalesRules::BUYING_ROLES as $role)<option @selected(old('buying_role') === $role)>{{ $role }}</option>@endforeach</select></label>
      <label>Relationship <select name="relationship">@foreach(\App\Http\Controllers\Sales\SalesRules::RELATIONSHIPS as $relationship)<option @selected(old('relationship', 'Warm') === $relationship)>{{ $relationship }}</option>@endforeach</select></label>
      <div class="quick-create-actions"><button type="submit">Create contact</button></div>
    </form>
    @endif

    @if($quickCanSales)
    <form class="quick-create-form" data-quick-create-form="opportunity" method="POST" action="{{ route('opportunities.store') }}">
      @csrf<input type="hidden" name="quick_create" value="opportunity"><input type="hidden" name="quick_create_page" value="{{ old('quick_create_page') }}">
      <label>Opportunity name <input name="name" value="{{ old('name') }}" required maxlength="255"></label>
      <label>Customer <select name="customer_id" required>@foreach($quickCustomers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->name }}</option>@endforeach</select></label>
      <label>Owner <select name="owner_id"><option value="">Select owner</option>@foreach($quickOwners as $owner)<option value="{{ $owner->id }}" @selected(old('owner_id') == $owner->id)>{{ $owner->name }}</option>@endforeach</select></label>
      <label>Stage <select name="stage" required>@foreach(\App\Models\Opportunity::STAGES as $stage)<option @selected(old('stage', \App\Models\Opportunity::STAGES[0]) === $stage)>{{ $stage }}</option>@endforeach</select></label>
      <label>Next action <input name="next_action" value="{{ old('next_action') }}" required maxlength="255"></label>
      <label>Next action date <input type="date" name="next_action_date" value="{{ old('next_action_date') }}" required></label>
      <label>Estimated value (RM) <input type="number" step="0.01" min="0" name="value" value="{{ old('value') }}"></label>
      <div class="quick-create-actions"><button type="submit">Create opportunity</button></div>
    </form>
    @endif

    <form class="quick-create-form" data-quick-create-form="activity" method="POST" action="{{ route('activities.store') }}">
      @csrf<input type="hidden" name="quick_create" value="activity"><input type="hidden" name="quick_create_page" value="{{ old('quick_create_page') }}">
      <label>Activity type <select name="type" required>@foreach(\App\Models\Activity::TYPES as $type)<option @selected(old('type') === $type)>{{ $type }}</option>@endforeach</select></label>
      <label>Subject <input name="subject" value="{{ old('subject') }}" required maxlength="255"></label>
      <label>Customer <select name="customer_id" required>@foreach($quickCustomers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->name }}</option>@endforeach</select></label>
      <label>Opportunity (optional) <select name="opportunity_id"><option value="">None</option>@foreach($quickOpportunities as $opportunity)<option value="{{ $opportunity->id }}" @selected(old('opportunity_id') == $opportunity->id)>{{ $opportunity->code }} · {{ $opportunity->name }}</option>@endforeach</select></label>
      <label>Tender (optional) <select name="tender_id"><option value="">None</option>@foreach($quickTenders as $tender)<option value="{{ $tender->id }}" @selected(old('tender_id') == $tender->id)>{{ $tender->code }}</option>@endforeach</select></label>
      <label>Date and time <input type="datetime-local" name="activity_at" value="{{ old('activity_at', now()->format('Y-m-d\TH:i')) }}" required></label>
      <label>Status <select name="status"><option value="PLANNED" @selected(old('status', 'PLANNED') === 'PLANNED')>PLANNED</option><option value="COMPLETED" @selected(old('status') === 'COMPLETED')>COMPLETED</option></select></label>
      <label>Outcome / notes <input name="outcome" value="{{ old('outcome') }}" maxlength="2000"></label>
      <label>Next action (optional) <input name="next_action" value="{{ old('next_action') }}" maxlength="255"></label>
      <label>Next action date <input type="date" name="next_action_date" value="{{ old('next_action_date') }}"></label>
      <div class="quick-create-actions"><button type="submit">Save activity</button></div>
    </form>

    <form class="quick-create-form" data-quick-create-form="task" method="POST" action="{{ route('tasks.store') }}">
      @csrf<input type="hidden" name="quick_create" value="task"><input type="hidden" name="quick_create_page" value="{{ old('quick_create_page') }}">
      <label>Task title <input name="title" value="{{ old('title') }}" required maxlength="255"></label>
      <label>Assignee <select name="assignee_id">@foreach($quickAssignees as $assignee)<option value="{{ $assignee->id }}" @selected(old('assignee_id', $quickUser->id) == $assignee->id)>{{ $assignee->name }}</option>@endforeach</select></label>
      <label>Due date <input type="date" name="due_date" value="{{ old('due_date', today()->addDays(3)->toDateString()) }}" required></label>
      <label>Customer (optional) <select name="customer_id"><option value="">None</option>@foreach($quickCustomers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->name }}</option>@endforeach</select></label>
      <label>Opportunity (optional) <select name="opportunity_id"><option value="">None</option>@foreach($quickOpportunities as $opportunity)<option value="{{ $opportunity->id }}" @selected(old('opportunity_id') == $opportunity->id)>{{ $opportunity->code }}</option>@endforeach</select></label>
      <label>Tender (optional) <select name="tender_id"><option value="">None</option>@foreach($quickTenders as $tender)<option value="{{ $tender->id }}" @selected(old('tender_id') == $tender->id)>{{ $tender->code }}</option>@endforeach</select></label>
      <label>Notes <input name="notes" value="{{ old('notes') }}" maxlength="2000"></label>
      <div class="quick-create-actions"><button type="submit">Create task</button></div>
    </form>

    @if($quickCanSales)
    <form class="quick-create-form" data-quick-create-form="tender" method="POST" action="{{ route('tenders.store') }}">
      @csrf<input type="hidden" name="quick_create" value="tender"><input type="hidden" name="quick_create_page" value="{{ old('quick_create_page') }}">
      <label>Opportunity <select name="opportunity_id" required>@foreach($quickOpportunities as $opportunity)<option value="{{ $opportunity->id }}" @selected(old('opportunity_id') == $opportunity->id)>{{ $opportunity->code }} · {{ $opportunity->name }}</option>@endforeach</select></label>
      <label>Tender title <input name="title" value="{{ old('title') }}" required maxlength="255"></label>
      <label>Closing date and time <input type="datetime-local" name="closing_at" value="{{ old('closing_at') }}" required></label>
      <div class="quick-create-actions"><button type="submit">Create draft tender</button></div>
    </form>
    @endif

    @if($quickCanEvent)
    <form class="quick-create-form" data-quick-create-form="event" method="POST" action="{{ route('events.store') }}">
      @csrf<input type="hidden" name="quick_create" value="event"><input type="hidden" name="quick_create_page" value="{{ old('quick_create_page') }}">
      <label>Event name <input name="name" value="{{ old('name') }}" required maxlength="200"></label>
      <label>Event date <input type="date" name="event_date" value="{{ old('event_date') }}" required></label>
      <label>Venue <input name="venue" value="{{ old('venue') }}" maxlength="200"></label>
      <label>Budget (RM) <input type="number" name="budget" value="{{ old('budget') }}" min="0" max="100000000" step="0.01"></label>
      <label>Objective <textarea name="objective" maxlength="2000">{{ old('objective') }}</textarea></label>
      <div class="quick-create-actions"><button type="submit">Create draft event</button></div>
    </form>
    @endif

    @if($quickCanPartner)
    <form class="quick-create-form" data-quick-create-form="partner" method="POST" action="{{ route('partners.store') }}">
      @csrf<input type="hidden" name="quick_create" value="partner"><input type="hidden" name="quick_create_page" value="{{ old('quick_create_page') }}">
      <label>Partner name <input name="name" value="{{ old('name') }}" required maxlength="255"></label>
      <label>Registration number <input name="reg_no" value="{{ old('reg_no') }}" maxlength="60"></label>
      <label>Owner <select name="owner_id" required>@foreach($quickPartnerOwners as $owner)<option value="{{ $owner->id }}" @selected(old('owner_id', $quickPartnerOwners->contains('id', $quickUser->id) ? $quickUser->id : $quickPartnerOwners->first()?->id) == $owner->id)>{{ $owner->name }}</option>@endforeach</select></label>
      <label>Status <select name="status"><option @selected(old('status', 'Active') === 'Active')>Active</option><option @selected(old('status') === 'Inactive')>Inactive</option></select></label>
      <label>Website <input type="url" name="website" value="{{ old('website') }}" maxlength="255"></label>
      <label>Rating <select name="rating" required>@foreach(range(1, 5) as $rating)<option value="{{ $rating }}" @selected(old('rating', 3) == $rating)>{{ $rating }}</option>@endforeach</select></label>
      <fieldset><legend>Classifications (select at least one)</legend>@foreach(\App\Models\Partner::CLASSIFICATIONS as $classification)<label class="quick-create-check"><input type="checkbox" name="classifications[]" value="{{ $classification }}" @checked(in_array($classification, (array) old('classifications', []), true))>{{ $classification }}</label>@endforeach</fieldset>
      <label>Notes <textarea name="notes" maxlength="2000">{{ old('notes') }}</textarea></label>
      <label class="quick-create-check"><input type="checkbox" name="preferred" value="1" @checked(old('preferred'))> Preferred partner</label>
      <label class="quick-create-check"><input type="checkbox" name="confirm_duplicate" value="1" @checked(old('confirm_duplicate'))> Save anyway if a possible duplicate is found</label>
      <div class="quick-create-actions"><button type="submit">Create partner</button></div>
    </form>
    @endif

    @if($quickCanCase)
    <form class="quick-create-form" data-quick-create-form="case" method="POST" action="{{ route('cases.store') }}">
      @csrf<input type="hidden" name="quick_create" value="case"><input type="hidden" name="quick_create_page" value="{{ old('quick_create_page') }}">
      <label>Case title <input name="title" value="{{ old('title') }}" required maxlength="255"></label>
      <label>Customer <select name="customer_id" required>@foreach($quickCustomers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->name }}</option>@endforeach</select></label>
      <label>Channel <select name="channel" required>@foreach(\App\Models\SupportCase::CHANNELS as $channel)<option @selected(old('channel') === $channel)>{{ $channel }}</option>@endforeach</select></label>
      <label>Priority <select name="priority" required>@foreach(\App\Models\SupportCase::PRIORITIES as $priority)<option @selected(old('priority', 'Medium') === $priority)>{{ $priority }}</option>@endforeach</select></label>
      <div class="quick-create-actions"><button type="submit">Open case</button></div>
    </form>
    @endif
  </div>
</dialog>
<script>
(() => {
  const dialog = document.getElementById('quick-create-dialog');
  if (!dialog) return;
  const typeSelect = document.getElementById('quick-create-type');
  const typeControl = typeSelect.closest('.quick-create-type');
  const title = document.getElementById('quick-create-title');
  const description = dialog.querySelector('.quick-create-heading p');
  const forms = [...dialog.querySelectorAll('[data-quick-create-form]')];
  const pageTitles = {
    customer: 'Add New Customer',
    contact: 'Create Contact',
    opportunity: 'Create Opportunity',
    activity: 'Create Activity',
    task: 'Create Task',
    tender: 'Create Tender',
    event: 'Create Event',
    partner: 'Create Partner',
    case: 'Create Case',
  };
  const setType = (type) => {
    typeSelect.value = type;
    forms.forEach((form) => {
      form.hidden = form.dataset.quickCreateForm !== type;
    });
  };
  const setPageMode = (type) => {
    const pageSpecific = Boolean(type);
    typeControl.hidden = pageSpecific;
    title.textContent = pageSpecific ? pageTitles[type] : 'Quick Create';
    description.textContent = pageSpecific
      ? 'Fill in the details below to create this record.'
      : 'Add a record. It will be saved and appear in your dashboard.';
    forms.forEach((form) => {
      const marker = form.querySelector('[name="quick_create_page"]');
      if (marker) marker.value = pageSpecific && form.dataset.quickCreateForm === type ? '1' : '';
    });
  };
  typeSelect.addEventListener('change', () => setType(typeSelect.value));
  document.addEventListener('click', (event) => {
    const button = event.target instanceof Element
      ? event.target.closest('[data-quick-create-open]')
      : null;
    if (!button) return;
    const pageType = button.dataset.quickCreateOpen || null;
    setType(pageType || typeSelect.value);
    setPageMode(pageType);
    dialog.showModal();
  });
  dialog.querySelector('[data-quick-create-close]').addEventListener('click', () => dialog.close());
  dialog.addEventListener('click', (event) => {
    if (event.target === dialog) dialog.close();
  });
  const initialType = @json(old('quick_create', array_key_first($quickTypes)));
  const initialPageType = @json(old('quick_create_page') ? old('quick_create') : null);
  setType(initialType);
  setPageMode(initialPageType);
  @if(($errors->any() || session('err')) && old('quick_create'))
    dialog.showModal();
  @endif
})();
</script>
