<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\NameChangeNotification;
use App\Models\NameChangeRequest;
use App\Services\NameChangeRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AdminRequestsController extends Controller
{
    protected $nameChangeService;

    public function __construct(NameChangeRequestService $nameChangeService)
    {
        $this->nameChangeService = $nameChangeService;
    }

    public function index()
    {
        $requests = NameChangeRequest::with(['user', 'requestable'])
            ->where('status', 'pending')
            ->latest()
            ->get();

        return response()->json([
            'requests' => $requests->map(function ($request) {
                return [
                    'id' => $request->id,
                    'current_name' => $request->current_name,
                    'requested_name' => $request->requested_name,
                    'reason' => $request->reason,
                    'status' => $request->status,
                    'created_at' => $request->created_at,
                    'type' => class_basename($request->requestable_type),
                    'user' => [
                        'id' => $request->user->id,
                        'name' => $request->user->name,
                        'email' => $request->user->email,
                    ],
                ];
            }),
        ]);
    }

    public function approve(NameChangeRequest $request)
    {
        // A stale screen: already decided, withdrawn, or what it renames is
        // gone. Claimed under a row lock so two clicks never both apply it.
        $claimed = \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
            $locked = NameChangeRequest::whereKey($request->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== 'pending' || ! $locked->requestable) {
                return false;
            }
            $locked->update(['status' => 'approving']);

            return true;
        });
        if (! $claimed) {
            return response()->json(['message' => 'This request is no longer waiting.'], 422);
        }

        try {
            $result = $this->nameChangeService->processAdminDirectChange(
                $request->requestable,
                $request->requested_name,
                $request->user
            );
        } catch (\Throwable $e) {
            $request->update(['status' => 'pending']);
            throw $e;
        }

        // Refused (the name was taken meanwhile): it goes back to waiting.
        if (! $result['success']) {
            $request->update(['status' => 'pending']);
        }

        if ($result['success']) {
            $request->update(['status' => 'approved']);

            return response()->json([
                'message' => 'Name change request approved successfully',
                'requiresRefresh' => $result['requiresRefresh'],
            ]);
        }

        return response()->json([
            'message' => $result['message'] ?? 'Failed to approve name change request',
        ], 422);
    }

    public function reject(Request $httpRequest, NameChangeRequest $request)
    {
        if ($request->status !== 'pending') {
            return response()->json(['message' => 'This request is no longer waiting.'], 422);
        }

        $request->update([
            'status' => 'rejected',
            'reason' => $httpRequest->reason,
        ]);

        try {
            Mail::to($request->user)->queue(new NameChangeNotification($request, false));
        } catch (\Exception $e) {
            report($e);
            \Log::error('Failed to send rejection notification:', [
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Name change request rejected successfully',
        ]);
    }
}
