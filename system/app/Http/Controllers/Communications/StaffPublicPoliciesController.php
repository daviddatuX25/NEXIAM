<?php

declare(strict_types=1);

namespace App\Http\Controllers\Communications;

use App\Communications\Actions\DesignateIntakePublicPolicy;
use App\Communications\Actions\PublishPublicPolicy;
use App\Communications\Actions\SavePublicPolicy;
use App\Communications\Actions\WithdrawPublicPolicy;
use App\Communications\Queries\PublicPolicyQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Communications\DesignateIntakePublicPolicyRequest;
use App\Http\Requests\Communications\PolicyCommandRequest;
use App\Http\Requests\Communications\SavePublicPolicyRequest;
use App\Http\Requests\Communications\WithdrawPublicPolicyRequest;
use App\Models\Communications\PublicPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class StaffPublicPoliciesController extends Controller
{
    public function index(Request $request, PublicPolicyQuery $query): Response
    {
        $filters = $request->validate([
            'status' => ['sometimes', Rule::in(['all', 'draft', 'published', 'withdrawn'])],
            'search' => ['nullable', 'string', 'max:120'],
            'placement' => ['sometimes', Rule::in(['all', 'general', 'program'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        return Inertia::render('Communications/Policies/Index', $query->staffIndex($request->user(), [
            'status' => (string) ($filters['status'] ?? 'all'),
            'search' => (string) ($filters['search'] ?? ''),
            'placement' => (string) ($filters['placement'] ?? 'all'),
            'page' => (int) ($filters['page'] ?? 1),
        ]));
    }

    public function create(Request $request, PublicPolicyQuery $query): Response
    {
        return Inertia::render('Communications/Policies/Create', $query->staffForm($request->user()));
    }

    public function edit(Request $request, PublicPolicy $policy, PublicPolicyQuery $query): Response
    {
        return Inertia::render('Communications/Policies/Edit', $query->staffForm($request->user(), $policy));
    }

    public function store(SavePublicPolicyRequest $request, SavePublicPolicy $save): RedirectResponse
    {
        $save->handle($request->user(), $request->validated());

        return Redirect::route('communications.policies.index')->with('status', 'Public policy draft saved.');
    }

    public function update(SavePublicPolicyRequest $request, PublicPolicy $policy, SavePublicPolicy $save): RedirectResponse
    {
        $save->handle($request->user(), [...$request->validated(), 'policy_id' => (int) $policy->getKey()]);

        return Redirect::route('communications.policies.index')->with('status', 'Public policy saved.');
    }

    public function publish(PolicyCommandRequest $request, PublicPolicy $policy, PublishPublicPolicy $publish): RedirectResponse
    {
        $publish->handle($request->user(), $policy, $request->validated());

        return Redirect::route('communications.policies.index')->with('status', 'Public policy published.');
    }

    public function withdraw(WithdrawPublicPolicyRequest $request, PublicPolicy $policy, WithdrawPublicPolicy $withdraw): RedirectResponse
    {
        $withdraw->handle($request->user(), $policy, $request->validated());

        return Redirect::route('communications.policies.index')->with('status', 'Public policy withdrawn.');
    }

    public function designateIntake(DesignateIntakePublicPolicyRequest $request, PublicPolicy $policy, DesignateIntakePublicPolicy $designate): RedirectResponse
    {
        $designate->handle($request->user(), $policy, $request->validated());

        return Redirect::route('communications.policies.index')->with('status', 'Application Privacy Policy updated.');
    }
}
