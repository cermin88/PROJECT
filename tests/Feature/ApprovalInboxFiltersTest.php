<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Filter bar + pagination on the Action & Approval Inbox
class ApprovalInboxFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_inbox_filters_and_pagination(): void
    {
        $hod = User::where('email', 'fauzi@csa.test')->firstOrFail();
        $requester = User::where('id', '!=', $hod->id)->orderBy('id')->firstOrFail();
        foreach (range(1, 22) as $i) {
            Approval::create(['code' => sprintf('APR-F%03d', $i), 'type' => 'Quotation', 'requester_id' => $requester->id, 'approver_id' => $hod->id]);
        }
        Approval::create(['code' => 'APR-ZEBRA', 'type' => 'MoM', 'requester_id' => $requester->id, 'approver_id' => $hod->id, 'status' => 'Returned']);

        $this->actingAs($hod)->get(route('approvals.index', ['q' => 'ZEBRA']))
            ->assertOk()->assertSee('APR-ZEBRA')->assertDontSee('APR-F001');
        $this->get(route('approvals.index', ['status' => 'Returned', 'type' => 'MoM']))
            ->assertOk()->assertSee('APR-ZEBRA')->assertDontSee('APR-F001');
        // Unknown values are ignored
        $this->get(route('approvals.index', ['status' => 'Bogus']))->assertOk();
        $this->get(route('approvals.index', ['type' => 'Quotation', 'page' => 2]))->assertOk()->assertSee('page=1', false);
    }

    public function test_approval_snapshot_is_shown_as_readable_details_instead_of_json(): void
    {
        $hod = User::where('email', 'fauzi@csa.test')->firstOrFail();
        $requester = User::where('id', '!=', $hod->id)->orderBy('id')->firstOrFail();
        Approval::create([
            'code' => 'APR-READABLE',
            'type' => 'Tender Pricing/Submission',
            'requester_id' => $requester->id,
            'approver_id' => $hod->id,
            'snapshot' => ['summary' => 'Controlled submission', 'amount' => 500000],
        ]);

        $this->actingAs($hod)->get(route('approvals.index'))
            ->assertOk()
            ->assertSee('Summary')
            ->assertSee('Controlled submission')
            ->assertSee('Amount')
            ->assertSee('RM 500,000.00')
            ->assertDontSee('<pre', false)
            ->assertDontSee('"summary"', false);
    }

    public function test_approval_statuses_have_distinct_colors(): void
    {
        $hod = User::where('email', 'fauzi@csa.test')->firstOrFail();
        $requester = User::where('id', '!=', $hod->id)->orderBy('id')->firstOrFail();
        foreach (['Pending', 'Approved', 'Rejected', 'Returned'] as $status) {
            Approval::create([
                'code' => 'APR-COLOR-' . $status,
                'type' => 'Request',
                'requester_id' => $requester->id,
                'approver_id' => $hod->id,
                'status' => $status,
            ]);
        }

        $this->actingAs($hod)->get(route('approvals.index'))
            ->assertOk()
            ->assertSee('grade-chip grade-medium">Pending', false)
            ->assertSee('grade-chip grade-positive">Approved', false)
            ->assertSee('grade-chip grade-negative">Rejected', false)
            ->assertSee('grade-chip grade-progress">Returned', false);
    }
}
