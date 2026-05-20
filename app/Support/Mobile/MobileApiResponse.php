<?php
// أَعُوذُ بِٱللَّهِ مِنْ الْشَيْطَانٍ الْرَجِيمٍ ✧ بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ ✧ اعوز بالله من الشياطين و ان يحضرون ✧ بسم الله الرحمن الرحيم ✧ الله لا إله إلا هو الحي القيوم
// Bismillahi ar-Rahmani ar-Rahim Audhu billahi min ash-shayatin wa an yahdurun Bismillah ar-Rahman ar-Rahim Allah la ilaha illa huwa al-hayy al-qayyum. Tamsa Allahu ala ayunihim
// version: x
// ======================================================
// - App Name: bismel1.com
// - Gusgraph -
// - Author: Gus Kazem
// - https://Gusgraph.com
// - File Path: app/Support/Mobile/MobileApiResponse.php
// =====================================================

namespace App\Support\Mobile;

use Illuminate\Http\JsonResponse;

class MobileApiResponse
{
    public static function success(array $data = [], array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => (object) $data,
            'meta' => (object) $meta,
        ], $status);
    }

    public static function error(string $code, string $message, array $details = [], int $status = 400): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => (object) $details,
            ],
        ], $status);
    }
}
