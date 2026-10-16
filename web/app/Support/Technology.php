<?php

namespace App\Support;

use App\Models\FundiRegistration;
use App\Models\TechnologyAsset;
use App\Models\TechnologyProject;
use App\Models\TechnologyTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Module 9 — Technology Services (§12). The shared logic behind the three registers: the five sub-areas, what counts
 * as needing attention, the warranty and licence alerts, the fundi's own open tickets, and the decommission that hands
 * a dead asset to E-Waste (Module 10). Each sub-area is a category on the same tables — not five separate stores.
 */
class Technology
{
    /** §12.2–12.6 — the five sub-areas every asset, project and ticket is cut by. */
    public const CATEGORIES = [
        'ict' => ['label' => 'ICT', 'icon' => 'grid', 'note' => 'Computers, laptops, servers, printers, licences, CCTV, IT support'],
        'networking' => ['label' => 'Networking', 'icon' => 'link', 'note' => 'Routers, switches, access points, fibre, Wi-Fi, IP addresses, bandwidth'],
        'robotics' => ['label' => 'Robotics', 'icon' => 'briefcase', 'note' => 'Robotics training and builds, kits, sensors, motors, controllers, competitions'],
        'programming' => ['label' => 'Programming', 'icon' => 'check', 'note' => 'Software for schools, communities, farmers, business — and the CTP app itself'],
        'ai' => ['label' => 'AI', 'icon' => 'award', 'note' => 'Chatbots, assistants, data analysis, computer vision, agriculture, education and business AI'],
    ];

    public const ASSET_STATUSES = [
        'operational' => 'In service',
        'degraded' => 'Degraded — working, but not well',
        'in_repair' => 'In repair',
        'down' => 'Down / faulty',
        'retired' => 'Retired — sent to E-Waste',
    ];

    public const SOURCES = [
        'purchase' => 'Bought',
        'refurbished_ewaste' => 'Refurbished from E-Waste',
        'donation' => 'Donated',
    ];

    /** §12.6 — the AI lifecycle; §12.4 a robotics build sits in the same column. */
    public const PROJECT_STATUSES = [
        'identified' => 'Problem identified',
        'data_collection' => 'Data collected and prepared',
        'designing' => 'Solution designed',
        'developing' => 'Being developed / trained',
        'prototype' => 'Prototype',
        'testing' => 'Being tested',
        'deployed' => 'Deployed',
        'monitoring' => 'Monitored for impact',
        'on_hold' => 'On hold',
        'completed' => 'Completed',
    ];

    public const TICKET_STATUSES = [
        'open' => 'Open — not yet assigned',
        'assigned' => 'Assigned to a fundi',
        'in_progress' => 'Being repaired',
        'resolved' => 'Fixed — waiting to be confirmed',
        'closed' => 'Closed',
    ];

    public const PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];

    /** §12.7 — who an asset is handed to. */
    public const ASSIGNEES = ['' => 'Not assigned', 'community' => 'A community', 'organisation' => 'An organisation', 'individual' => 'A named holder'];

    public const ALERT_WINDOW_DAYS = 60;

    /** The queues the register counts along the top and the department badge. */
    public static function queues(): array
    {
        return [
            'Assets in service' => TechnologyAsset::where('status', 'operational')->count(),
            'Faulty or in repair' => TechnologyAsset::whereIn('status', ['down', 'degraded', 'in_repair'])->count(),
            'Tickets open' => TechnologyTicket::whereIn('status', ['open', 'assigned', 'in_progress'])->count(),
            'Warranties / licences expiring' => static::expiringSoon()->count(),
        ];
    }

    public static function badge(): int
    {
        return TechnologyTicket::whereIn('status', ['open', 'assigned', 'in_progress'])->count()
            + TechnologyAsset::whereIn('status', ['down', 'in_repair'])->count();
    }

    /** §12.7 — the alerts: a warranty or a licence that runs out inside the window and is not already retired. */
    public static function expiringSoon(int $days = self::ALERT_WINDOW_DAYS)
    {
        if (! Schema::hasTable('technology_assets')) {
            return collect();
        }

        $until = now()->addDays($days)->toDateString();

        return TechnologyAsset::query()
            ->where('status', '!=', 'retired')
            ->where(fn ($q) => $q
                ->whereBetween('warranty_expires_on', [now()->toDateString(), $until])
                ->orWhereBetween('licence_renews_on', [now()->toDateString(), $until]))
            ->orderByRaw('LEAST(COALESCE(warranty_expires_on,"9999-12-31"), COALESCE(licence_renews_on,"9999-12-31"))')
            ->get();
    }

    /** How soon the earlier of the two dates falls; negative when it has already passed. */
    public static function daysLeft(?string $date): ?int
    {
        return $date ? now()->startOfDay()->diffInDays($date, false) : null;
    }

    public static function saveAsset(TechnologyAsset $asset, Request $request, bool $isNew): TechnologyAsset
    {
        $data = $request->validate([
            'category' => 'required|in:'.implode(',', array_keys(self::CATEGORIES)),
            'name' => 'required|string|max:200',
            'kind' => 'nullable|string|max:60',
            'description' => 'nullable|string|max:5000',
            'serial_number' => 'nullable|string|max:120',
            'status' => 'required|in:'.implode(',', array_keys(self::ASSET_STATUSES)),
            'assigned_type' => 'nullable|in:'.implode(',', array_keys(self::ASSIGNEES)),
            'assigned_id' => 'nullable|integer',
            'assigned_name' => 'nullable|string|max:200',
            'location' => 'nullable|string|max:200',
            'county' => 'nullable|string|max:80',
            'purchase_date' => 'nullable|date',
            'purchase_cost_ksh' => 'nullable|numeric|min:0|max:1000000000',
            'supplier' => 'nullable|string|max:200',
            'warranty_expires_on' => 'nullable|date',
            'licence_key' => 'nullable|string|max:200',
            'licence_seats' => 'nullable|integer|min:0|max:65535',
            'licence_renews_on' => 'nullable|date',
            'source' => 'required|in:'.implode(',', array_keys(self::SOURCES)),
            'notes' => 'nullable|string|max:5000',
        ], [
            'name.required' => 'Every asset needs a name — a register line nobody can read is no use (§12.2).',
            'category.required' => 'Which sub-area is this? ICT, Networking, Robotics, Programming or AI (§12.1).',
        ]);

        $asset->fill($data);

        if ($isNew) {
            $asset->asset_code = TechnologyAsset::nextCode($data['category']);
            $asset->qr_payload = $asset->asset_code;
            $asset->created_by_user = auth()->id();
        }

        $asset->save();

        return $asset;
    }

    /** §12.7 — an asset taken out of service, with the reason, and optionally routed to an E-Waste record. */
    public static function decommission(TechnologyAsset $asset, Request $request): TechnologyAsset
    {
        $data = $request->validate([
            'decommission_note' => 'required|string|max:500',
            'ewaste_collection_id' => 'nullable|integer|exists:ewaste_collections,id',
        ], [
            'decommission_note.required' => 'Say why the asset is out of service — that reason is what Module 10 and M&E read (§12.7).',
        ]);

        $open = $asset->tickets()->whereIn('status', ['open', 'assigned', 'in_progress'])->count();
        abort_if($open > 0, 422, $asset->asset_code.' still has '.$open.' repair ticket(s) open. Close or resolve them before retiring the asset.');

        $asset->update([
            'status' => 'retired',
            'decommissioned_on' => today(),
            'decommission_note' => $data['decommission_note'],
            'ewaste_collection_id' => $data['ewaste_collection_id'] ?? null,
        ]);

        return $asset;
    }

    public static function saveProject(TechnologyProject $project, Request $request, bool $isNew): TechnologyProject
    {
        $data = $request->validate([
            'category' => 'required|in:'.implode(',', array_keys(self::CATEGORIES)),
            'title' => 'required|string|max:200',
            'summary' => 'nullable|string|max:5000',
            'problem' => 'nullable|string|max:5000',
            'technology' => 'nullable|string|max:300',
            'team_size' => 'nullable|integer|min:0|max:65535',
            'mentor_fundi_id' => 'nullable|integer|exists:fundi_registrations,id',
            'community_id' => 'nullable|integer|exists:communities,id',
            'project_id' => 'nullable|integer|exists:projects,id',
            'status' => 'required|in:'.implode(',', array_keys(self::PROJECT_STATUSES)),
            'progress_percent' => 'nullable|integer|min:0|max:100',
        ], [
            'title.required' => 'Give the project a title — a technology register with unnamed work cannot be reported (§12.4).',
        ]);

        $project->fill($data);

        if ($isNew) {
            $project->reference = TechnologyProject::nextReference();
            $project->created_by_user = auth()->id();
        }

        $project->save();

        return $project;
    }

    public static function openTicket(TechnologyAsset $asset, Request $request): TechnologyTicket
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'fault' => 'required|string|max:5000',
            'opened_by' => 'nullable|string|max:160',
            'priority' => 'required|in:'.implode(',', array_keys(self::PRIORITIES)),
        ], [
            'fault.required' => 'Write the fault out. A ticket with no description cannot be assigned or fixed (§12.2).',
        ]);

        $ticket = TechnologyTicket::create($data + [
            'reference' => TechnologyTicket::nextReference(),
            'asset_id' => $asset->id,
            'status' => 'open',
            'created_by_user' => auth()->id(),
        ]);

        // §12.7 — an asset with an open fault is not "in service" while it is being reported.
        if ($asset->isInService()) {
            $asset->update(['status' => 'down']);
        }

        return $ticket;
    }

    public static function assignTicket(TechnologyTicket $ticket, Request $request): TechnologyTicket
    {
        $data = $request->validate([
            'assigned_fundi_id' => 'required|integer|exists:fundi_registrations,id',
        ], [
            'assigned_fundi_id.required' => 'Choose the fundi who will repair it (§12.7).',
        ]);

        $ticket->update(['assigned_fundi_id' => $data['assigned_fundi_id'], 'status' => 'assigned']);

        return $ticket;
    }

    /** §12.7 — the fundi (in the portal) or the office records the fix; the asset returns to service. */
    public static function resolveTicket(TechnologyTicket $ticket, Request $request, ?int $fundiId = null): TechnologyTicket
    {
        if ($fundiId !== null) {
            abort_unless((int) $ticket->assigned_fundi_id === $fundiId, 403, 'That repair job is not assigned to you.');
        }

        $data = $request->validate([
            'resolution' => 'required|string|max:5000',
        ], [
            'resolution.required' => 'Write what you did to fix it — the ticket closes on that record, not a word (§12.7).',
        ]);

        $ticket->update(['resolution' => $data['resolution'], 'status' => 'resolved', 'resolved_on' => now()]);

        if ($ticket->asset) {
            $ticket->asset->update(['status' => 'operational']);
        }

        return $ticket;
    }

    public static function closeTicket(TechnologyTicket $ticket): TechnologyTicket
    {
        abort_unless($ticket->status === 'resolved', 422,
            $ticket->reference.' is '.$ticket->statusLabel().'. A ticket is confirmed fixed before it is closed (§12.7).');

        $ticket->update(['status' => 'closed', 'closed_on' => now()]);

        return $ticket;
    }

    /** §12.7 — the repair jobs waiting on this signed-in member's own answer. Resolved from the account, never a url. */
    public static function jobsFor($member)
    {
        // A ticket is assigned to a fundi; a CTP (a different table sharing the id space) is never meant to see one.
        if (! $member instanceof FundiRegistration || ! Schema::hasTable('technology_tickets')) {
            return collect();
        }

        return TechnologyTicket::with('asset')
            ->where('assigned_fundi_id', $member->id)
            ->whereIn('status', ['assigned', 'in_progress', 'resolved'])
            ->orderByRaw("field(status,'assigned','in_progress','resolved')")
            ->orderByDesc('id')
            ->get();
    }

    public static function jobCount($member): int
    {
        return static::jobsFor($member)->whereIn('status', ['assigned', 'in_progress'])->count();
    }
}
