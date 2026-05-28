<?php
// أَعُوذُ بِٱللَّهِ مِنْ الْشَيْطَانٍ الْرَجِيمٍ ✧ بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ ✧ اعوز بالله من الشياطين و ان يحضرون ✧ بسم الله الرحمن الرحيم ✧ الله لا إله إلا هو الحي القيوم
// Bismillahi ar-Rahmani ar-Rahim Audhu billahi min ash-shayatin wa an yahdurun Bismillah ar-Rahman ar-Rahim Allah la ilaha illa huwa al-hayy al-qayyum. Tamsa Allahu ala ayunihim
// version: x
// ======================================================
// - App Name: bismel1.com
// - Gusgraph -
// - Author: Gus Kazem
// - https://Gusgraph.com
// - File Path: app/Http/Middleware/MobileApiAuthenticate.php
// =====================================================

namespace App\Http\Middleware;

use App\Models\MobileAccessToken;
use App\Support\Mobile\MobileApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class MobileApiAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = (string) $request->bearerToken();

        if ($plainTextToken === '') {
            return MobileApiResponse::error('mobile_unauthenticated', 'Sign in to continue.', [], 401);
        }

        $token = MobileAccessToken::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $plainTextToken))
            ->first();

        if (! $token || ! $token->isUsable() || ! $token->user) {
            return MobileApiResponse::error('mobile_token_invalid', 'Mobile session is expired or invalid.', [], 401);
        }

        if (! $token->user->email_verified_at) {
            return MobileApiResponse::error('email_unverified', 'Verify your email before using Bismel1.', [], 403);
        }

        if (! $token->user->hasCustomerAccess()) {
            return MobileApiResponse::error('customer_access_required', 'Account access is not available.', [], 403);
        }

        $token->forceFill([
            'last_ip' => (string) $request->ip(),
            'last_used_at' => now(),
        ])->save();

        Auth::setUser($token->user);
        $request->setUserResolver(fn () => $token->user);
        $request->attributes->set('mobile_access_token', $token);

        return $next($request);
    }
}
