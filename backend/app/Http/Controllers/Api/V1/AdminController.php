<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\IncidentReport;
use App\Models\Installation;
use App\Models\ModerationEvent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function stats(): JsonResponse
    {
        return response()->json([
            'incidents' => [
                'total' => Incident::count(),
                'active' => Incident::whereNull('resolved_at')->count(),
                'today' => Incident::whereDate('created_at', today())->count(),
            ],
            'reports' => [
                'total' => IncidentReport::count(),
                'pending_review' => IncidentReport::where('status', 'review')->count(),
            ],
            'users' => [
                'total' => User::count(),
                'verified' => User::whereNotNull('email_verified_at')->count(),
            ],
            'installations' => [
                'total' => Installation::count(),
                'with_push' => Installation::whereNotNull('push_token')->count(),
                'blocked' => Installation::whereNotNull('blocked_at')->count(),
            ],
        ]);
    }

    public function incidents(Request $request): JsonResponse
    {
        $query = Incident::query()
            ->withCount('votes', 'reports')
            ->orderByDesc('created_at');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('visibility')) {
            $query->where('visibility', $request->visibility);
        }

        if ($request->has('source')) {
            $query->where('source', $request->source);
        }

        return response()->json($query->paginate(50));
    }

    public function updateIncident(Request $request, Incident $incident): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:gemeld,bevestigd,officieel,opgelost,verlopen',
            'visibility' => 'sometimes|in:public,private,review,archived',
        ]);

        $incident->update($validated);

        ModerationEvent::create([
            'incident_id' => $incident->id,
            'user_id' => $request->user()->id,
            'action' => 'manual_update',
            'reason' => 'admin_action',
            'metadata' => $validated,
        ]);

        return response()->json([
            'message' => 'Incident bijgewerkt',
            'incident' => $incident->fresh(),
        ]);
    }

    public function deleteIncident(Request $request, Incident $incident): JsonResponse
    {
        ModerationEvent::create([
            'incident_id' => $incident->id,
            'user_id' => $request->user()->id,
            'action' => 'deleted',
            'reason' => $request->input('reason', 'admin_action'),
        ]);

        $incident->update([
            'status' => 'opgelost',
            'visibility' => 'archived',
            'resolved_at' => now(),
            'archived_at' => now(),
        ]);

        return response()->json(['message' => 'Incident verwijderd']);
    }

    public function users(Request $request): JsonResponse
    {
        $query = User::query()
            ->withCount('reports')
            ->orderByDesc('created_at');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate(50));
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'is_admin' => 'sometimes|boolean',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Gebruiker bijgewerkt',
            'user' => $user->fresh(),
        ]);
    }

    public function installations(Request $request): JsonResponse
    {
        $query = Installation::query()
            ->withCount('votes', 'reports')
            ->orderByDesc('created_at');

        if ($request->boolean('blocked_only')) {
            $query->whereNotNull('blocked_at');
        }

        return response()->json($query->paginate(50));
    }

    public function blockInstallation(Request $request, Installation $installation): JsonResponse
    {
        $installation->update(['blocked_at' => now()]);

        return response()->json(['message' => 'Installatie geblokkeerd']);
    }

    public function unblockInstallation(Installation $installation): JsonResponse
    {
        $installation->update(['blocked_at' => null]);

        return response()->json(['message' => 'Installatie gedeblokkeerd']);
    }

    public function pendingReports(): JsonResponse
    {
        $reports = IncidentReport::query()
            ->where('status', 'review')
            ->with('incident:id,category,subtype,status')
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json($reports);
    }

    public function approveReport(IncidentReport $report): JsonResponse
    {
        $report->update(['status' => 'accepted']);

        if ($report->incident) {
            $report->incident->update(['visibility' => 'public']);
        }

        return response()->json(['message' => 'Melding goedgekeurd']);
    }

    public function rejectReport(Request $request, IncidentReport $report): JsonResponse
    {
        $report->update([
            'status' => 'rejected',
            'moderation_flags' => array_merge(
                $report->moderation_flags ?? [],
                ['rejected_reason' => $request->input('reason', 'spam')]
            ),
        ]);

        return response()->json(['message' => 'Melding afgewezen']);
    }
}
