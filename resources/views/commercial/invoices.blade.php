@extends('layouts.app')
@section('title', 'Invoices')
@section('content')
<h2 style="margin-bottom:14px">Invoices & Collection</h2>
<div class="grid"><div class="card kpi"><span>Invoiced</span><b>RM {{ number_format($totalInvoiced) }}</b></div>
<div class="card kpi"><span>Collected</span><b>RM {{ number_format($totalPaid) }}</b></div>
<div class="card kpi"><span>Outstanding</span><b>RM {{ number_format($totalInvoiced-$totalPaid) }}</b></div></div>
<x-filters search="Search invoice / award / customer" :selects="['state' => ['All invoices', $states]]" dates />
<div class="card wrap"><table><tr><th>Invoice</th><th>Customer</th><th>Amount</th><th>Paid</th><th>Outstanding</th><th>Due</th></tr>
@foreach($invoices as $i)<tr><td>{{ $i->invoice_no }}</td><td>{{ $i->award->customer->name }}</td><td>RM {{ number_format($i->amount) }}</td><td>RM {{ number_format($i->paid) }}</td>
<td>RM {{ number_format($i->outstanding()) }}</td><td class="{{ $i->isOverdue()?'bad':'' }}">{{ $i->due_date->format('d M Y') }}{{ $i->isOverdue()?' · overdue':'' }}</td></tr>@endforeach
@if($invoices->isEmpty())<tr><td colspan="6" class="mute">No invoices match.</td></tr>@endif</table>{{ $invoices->links() }}</div>
@endsection
