<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Billing;
use App\Services\Ops;
use App\Models\IncidentReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ]);

        $emailChanged = isset($validated['email']) && $validated['email'] !== $user->email;
        // Een nieuw e-mailadres vraagt om het wachtwoord: anders is een gestolen token genoeg om
        // het adres te wijzigen en daarna via 'wachtwoord vergeten' het account over te nemen.
        if ($emailChanged) {
            $request->validate(['current_password' => ['required', 'current_password']]);
        }

        $user->fill($validated);
        if ($emailChanged) {
            // email_verified_at staat niet in $fillable; daarom expliciet.
            $user->forceFill(['email_verified_at' => null]);
        }
        $user->save();

        // Send new verification email if needed
        if (isset($validated['email']) && !$user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json([
            'message' => 'Profiel bijgewerkt',
            'user' => $user->fresh(),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);
        // Andere toestellen uitloggen; dit toestel blijft ingelogd.
        $request->user()->tokens()->whereKeyNot($request->user()->currentAccessToken()->id)->delete();

        return response()->json([
            'message' => 'Wachtwoord bijgewerkt',
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // Eerst het abonnement stoppen: lukt dat niet, dan blijft het account bestaan in plaats van dat iemand doorbetaalt.
        try {
            app(Billing::class)->forget($user);
        } catch (\Throwable $exception) {
            Ops::record('payment.forget_failed', Ops::ERROR, 'Abonnement niet gestopt bij verwijderen account', "Account #{$user->id}: ".mb_substr($exception->getMessage(), 0, 200), ['account' => $user->id], (string) $user->id);

            return response()->json(['message' => 'Je abonnement kon nu niet worden stopgezet. Probeer het over een paar minuten opnieuw.'], 503);
        }

        // Revoke all tokens
        $user->tokens()->delete();

        // Delete user
        $user->delete();

        return response()->json([
            'message' => 'Account verwijderd',
        ]);
    }

    public function reports(Request $request): JsonResponse
    {
        $reports = IncidentReport::query()
            ->where('user_id', $request->user()->id)
            ->with('incident:id,category,subtype,status,latitude,longitude,created_at')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($reports);
    }
}
