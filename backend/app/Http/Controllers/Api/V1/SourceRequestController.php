<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Notifications\SourceRequestNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class SourceRequestController extends Controller
{
    /** "Bron aanvragen" in de app: we mailen het naar ons eigen adres, met de gebruiker als reply-to. */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'url' => 'nullable|url:http,https|max:300',
            'note' => 'nullable|string|max:1000',
        ]);
        $user = $request->user();

        Notification::route('mail', config('veiligbuiten.source_requests.mail_to'))
            ->notify(new SourceRequestNotification($user->id, $user->email, $data));

        return response()->json(['message' => __('sources.requested')], 202);
    }
}
