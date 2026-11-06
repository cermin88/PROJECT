<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public const STATUSES = ['Draft', 'Under Review', 'Approved', 'Final', 'Superseded'];
    public const CATEGORIES = ['Proposal', 'Tender', 'Award Evidence', 'Project', 'KPI', 'MoM', 'Agreement'];
    // Allowed manual moves; Superseded is only set by creating a new version
    private const FLOW = ['Draft' => ['Under Review'], 'Under Review' => ['Approved', 'Draft'], 'Approved' => ['Final', 'Draft'], 'Final' => [], 'Superseded' => []];
    private const REVIEWERS = ['HOD', 'AHOD', 'Management', 'Superadmin'];
    private const MIMES = 'pdf,doc,docx,xls,xlsx,ppt,pptx,png,jpg,jpeg,txt,csv,zip';

    public function index(Request $request)
    {
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date']);
        $q = Document::whereNull('archived_at');
        // Restricted metadata is not listed for roles that cannot open it (prototype rule)
        $hidden = 0;
        if (! auth()->user()->canSeeRestricted()) {
            $hidden = (clone $q)->where('restricted', true)->count();
            $q->where('restricted', false);
        }
        foreach (['status' => self::STATUSES, 'category' => self::CATEGORIES] as $f => $allowed) {
            if (in_array($v = $request->query($f), $allowed, true)) {
                $q->where($f, $v);
            }
        }
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('title', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%")->orWhere('context_code', 'like', "%{$s}%"));
        }
        if (in_array($v = $request->query('access'), ['restricted', 'standard'], true)) {
            $q->where('restricted', $v === 'restricted');
        }
        if ($d = $request->date('from')) {
            $q->whereDate('created_at', '>=', $d);
        }
        if ($d = $request->date('to')) {
            $q->whereDate('created_at', '<=', $d);
        }
        return view('documents.index', [
            'documents' => $q->orderBy('code')->paginate(config('csa.per_page'))->withQueryString(), 'hidden' => $hidden,
            'statuses' => self::STATUSES, 'categories' => self::CATEGORIES,
        ]);
    }

    public function show(Document $document)
    {
        $this->guardRestricted($document, 'view');
        AuditLog::record('DOCUMENT_VIEWED', 'document', $document->id);

        // Version chain: walk back through supersedes_id, and forward to newer versions
        $chain = collect([$document]);
        for ($d = $document; $d->supersedes_id && ($p = Document::find($d->supersedes_id)); $d = $p) {
            $chain->push($p);
        }
        for ($d = $document; $n = Document::where('supersedes_id', $d->id)->first(); $d = $n) {
            $chain->prepend($n);
        }

        return view('documents.show', [
            'document' => $document, 'versions' => $chain,
            'next' => self::FLOW[$document->status] ?? [],
            'canReview' => in_array(auth()->user()->role, self::REVIEWERS, true),
            'uploader' => $document->uploaded_by ? User::find($document->uploaded_by) : null,
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'title' => 'required|string|max:255', 'context_code' => 'required|string|max:40',
            'category' => 'required|in:'.implode(',', self::CATEGORIES), 'restricted' => 'nullable|boolean',
            'file' => 'nullable|file|max:10240|mimes:'.self::MIMES,
        ]);
        $restricted = $request->boolean('restricted');
        // Only roles that can see Restricted documents may create them
        abort_if($restricted && ! auth()->user()->canSeeRestricted(), 403, 'Only HOD / Management / Superadmin may create Restricted documents.');

        $doc = Document::create([
            'code' => WorkflowService::nextCode('DOC', 'documents'), 'title' => $d['title'], 'context_code' => $d['context_code'],
            'category' => $d['category'], 'version' => '1.0', 'status' => 'Draft', 'restricted' => $restricted,
            'uploaded_by' => auth()->id(),
        ]);
        $this->attachFile($request, $doc);
        AuditLog::record('DOCUMENT_UPLOAD', 'document', $doc->id, ['context' => $d['context_code'], 'file' => $doc->file_name]);
        return redirect()->route('documents.show', $doc)->with('ok', "{$doc->code} added as Draft.");
    }

    // New version: old one becomes Superseded, the new copy starts as Draft
    public function version(Request $request, Document $document)
    {
        $this->guardRestricted($document, 'version');
        $request->validate(['file' => 'nullable|file|max:10240|mimes:'.self::MIMES]);
        if ($document->status === 'Superseded' || Document::where('supersedes_id', $document->id)->exists()) {
            return back()->with('err', 'Only the latest version can be versioned.');
        }
        $new = $document->replicate(['code', 'status', 'version', 'supersedes_id', 'file_path', 'file_name', 'file_size', 'mime', 'uploaded_by', 'created_at', 'updated_at']);
        $new->fill([
            'code' => WorkflowService::nextCode('DOC', 'documents'), 'status' => 'Draft',
            'version' => number_format((float) $document->version + 1, 1, '.', ''),
            'supersedes_id' => $document->id, 'uploaded_by' => auth()->id(),
        ]);
        // Carry the previous file forward unless a new one is uploaded
        $new->forceFill($document->only(['file_path', 'file_name', 'file_size', 'mime']))->save();
        $this->attachFile($request, $new);
        $document->update(['status' => 'Superseded']);
        AuditLog::record('DOCUMENT_NEW_VERSION', 'document', $new->id, ['supersedes' => $document->code, 'version' => $new->version]);
        return redirect()->route('documents.show', $new)->with('ok', "Version {$new->version} created; {$document->code} superseded.");
    }

    public function status(Request $request, Document $document)
    {
        $this->guardRestricted($document, 'status');
        $d = $request->validate(['status' => 'required|in:'.implode(',', self::STATUSES)]);
        if (! in_array($d['status'], self::FLOW[$document->status] ?? [], true)) {
            return back()->with('err', "Cannot move from {$document->status} to {$d['status']}.");
        }
        // Approving / finalising is a reviewer action; submitting for review is open to all
        if (in_array($d['status'], ['Approved', 'Final'], true) || $document->status !== 'Draft') {
            abort_unless(in_array(auth()->user()->role, self::REVIEWERS, true), 403, 'Only HOD / AHOD / Management / Superadmin may review documents.');
        }
        $old = $document->status;
        $document->update(['status' => $d['status']]);
        AuditLog::record('DOCUMENT_STATUS', 'document', $document->id, ['from' => $old, 'to' => $d['status']]);
        return back()->with('ok', "Status changed to {$d['status']}.");
    }

    public function download(Document $document)
    {
        $this->guardRestricted($document, 'download');
        abort_unless($document->file_path && Storage::disk('local')->exists($document->file_path), 404, 'No file stored for this document.');
        AuditLog::record('DOCUMENT_DOWNLOAD', 'document', $document->id, ['version' => $document->version]);
        return Storage::disk('local')->download($document->file_path, $document->file_name);
    }

    // Restricted documents: server-side check, and the denial itself is audited
    private function guardRestricted(Document $document, string $what): void
    {
        if ($document->restricted && ! auth()->user()->canSeeRestricted()) {
            AuditLog::record('ACCESS_DENIED', 'document', $document->id, ['reason' => 'restricted', 'attempt' => $what]);
            abort(403, 'This document is restricted to HOD / Management.');
        }
    }

    // Files live in storage/app/private/documents/<code>/ (never publicly served)
    private function attachFile(Request $request, Document $doc): void
    {
        if (! $request->hasFile('file')) {
            return;
        }
        $f = $request->file('file');
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $f->getClientOriginalName());
        $doc->update([
            'file_path' => $f->storeAs('documents/'.$doc->code, $name, 'local'),
            'file_name' => $f->getClientOriginalName(), 'file_size' => $f->getSize(), 'mime' => $f->getClientMimeType(),
        ]);
    }
}
