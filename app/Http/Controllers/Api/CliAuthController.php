<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CliAuth\ApproveCliAuthRequest;
use App\Http\Requests\Api\CliAuth\CheckCliAuthRequest;
use App\Http\Requests\Api\CliAuth\DenyCliAuthRequest;
use App\Models\CliAuthSession;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CliAuthController extends Controller
{
    /**
     * Initialize a CLI auth session (public, no auth).
     */
    public function init(Request $request): JsonResponse
    {
        // Generate user-visible code (XXXX-XXXX)
        $code = strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
        $secret = Str::random(40);

        $session = CliAuthSession::create([
            'code' => $code,
            'secret' => $secret,
            'status' => 'pending',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'expires_at' => now()->addMinutes(5),
        ]);

        return response()->json([
            'code' => $session->code,
            'secret' => $session->secret,
            'verification_url' => url('/cli/auth?code='.$session->code),
            'expires_in' => 300,
        ]);
    }

    /**
     * Check CLI auth session status (public, no auth).
     */
    public function check(CheckCliAuthRequest $request): JsonResponse
    {

        $session = CliAuthSession::where('secret', $request->query('secret'))->first();

        if (! $session) {
            return response()->json(['message' => 'Session not found.'], 404);
        }

        // Auto-expire
        if ($session->isExpired() && $session->status === 'pending') {
            $session->update(['status' => 'expired']);
        }

        if ($session->status === 'approved' && $session->token_plain) {
            $token = $session->token_plain;
            $teamName = $session->team->name ?? 'Unknown';
            $userName = $session->user->name ?? 'Unknown';

            // Clear token after one-time retrieval
            $session->update(['token_plain' => null]);

            return response()->json([
                'status' => 'approved',
                'token' => $token,
                'team_name' => $teamName,
                'user_name' => $userName,
            ]);
        }

        return response()->json([
            'status' => $session->status,
        ]);
    }

    /**
     * Show the CLI auth approval page (web, requires auth).
     */
    public function showApprovalPage(Request $request): InertiaResponse
    {
        $code = $request->query('code');
        $session = null;
        $error = null;

        if ($code) {
            $session = CliAuthSession::where('code', $code)
                ->pending()
                ->notExpired()
                ->first();

            if (! $session) {
                $error = 'This authorization request has expired or is invalid.';
            }
        } else {
            $error = 'No authorization code provided.';
        }

        /** @var \App\Models\User $user */
        $user = $request->user();
        $teams = $user->teams()->get()->map(fn (Team $team) => [
            'id' => $team->id,
            'name' => $team->name,
        ]);

        return Inertia::render('Auth/CliAuth', [
            'code' => $session?->code,
            'error' => $error,
            'teams' => $teams,
            'defaultTeamId' => $user->currentTeam()?->id,
        ]);
    }

    /**
     * Approve CLI auth session (web, requires auth + CSRF).
     */
    public function approve(ApproveCliAuthRequest $request): \Illuminate\Http\RedirectResponse
    {

        $session = CliAuthSession::where('code', $request->input('code'))
            ->pending()
            ->notExpired()
            ->first();

        if (! $session) {
            return redirect()->back()->withErrors(['code' => 'Authorization request expired or invalid.']);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        // Verify user belongs to the selected team
        $teamId = (int) $request->input('team_id');
        if (! $user->teams()->where('teams.id', $teamId)->exists()) {
            return redirect()->back()->withErrors(['team_id' => 'You are not a member of this team.']);
        }

        // Create Sanctum token for CLI
        $tokenName = 'Saturn CLI ('.now()->format('Y-m-d H:i').')';
        $newAccessToken = $user->createTokenForCli($tokenName, $teamId);

        // Update session with approval
        $session->update([
            'status' => 'approved',
            'user_id' => $user->id,
            'team_id' => $teamId,
            'token_plain' => $newAccessToken->plainTextToken,
        ]);

        return redirect()->back();
    }

    /**
     * Deny CLI auth session (web, requires auth + CSRF).
     */
    public function deny(DenyCliAuthRequest $request): \Illuminate\Http\RedirectResponse
    {

        $session = CliAuthSession::where('code', $request->input('code'))
            ->pending()
            ->notExpired()
            ->first();

        if (! $session) {
            return redirect()->back()->withErrors(['code' => 'Authorization request expired or invalid.']);
        }

        $session->update(['status' => 'denied']);

        return redirect()->back();
    }
}
