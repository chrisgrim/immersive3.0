<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NameChangeRequest;
use Illuminate\Http\Request;
use App\Services\NameChangeRequestService;
use Illuminate\Support\Facades\Mail;
use App\Mail\NameChangeNotification;

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
            })
        ]);
    }

    public function approve(NameChangeRequest $request)
    {
        $result = $this->nameChangeService->processAdminDirectChange(
            $request->requestable, 
            $request->requested_name
        );

        if ($result['success']) {
            $request->update(['status' => 'approved']);
            return response()->json([
                'message' => 'Name change request approved successfully',
                'requiresRefresh' => $result['requiresRefresh']
            ]);
        }

        return response()->json([
            'message' => 'Failed to approve name change request'
        ], 422);
    }

    public function reject(Request $httpRequest, NameChangeRequest $request)
    {
        \Log::info('Form Request Data:', [
            'reason' => $httpRequest->reason
        ]);
        
        $request->update([
            'status' => 'rejected',
            'reason' => $httpRequest->reason
        ]);
        
        try {
            Mail::to($request->user)->send(new NameChangeNotification($request, false));
        } catch (\Exception $e) {
            \Log::error('Failed to send rejection notification:', [
                'error' => $e->getMessage()
            ]);
        }
        
        return response()->json([
            'message' => 'Name change request rejected successfully'
        ]);
    }
}