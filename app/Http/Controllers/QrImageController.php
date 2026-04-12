<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class QrImageController extends Controller
{
    public function showByToken(Request $request, string $token)
    {
        if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
            abort(404);
        }

        $rec = DB::selectOne("
            SELECT id, status, expires_at
            FROM employee_qr_tokens
            WHERE token = ?
            ORDER BY id DESC
            LIMIT 1
        ", [$token]);

        if (!$rec) {
            abort(404);
        }

        $size = (int) $request->query('size', 512);
        $whitelist = [256,320,384,448,512,640,768,896,1024];
        if (!in_array($size, $whitelist, true)) $size = 512;

        $targetUrl = url('/t/'.$token);
        $baseSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                    ->size($size)->margin(2)->errorCorrection('H')
                    ->generate($targetUrl);

        $logoCandidates = [
            public_path('images/logo-pemda.svg'),
            public_path('images/logo-pemda.png'),
            public_path('anambas-id/images/logo-pemda.svg'),
            public_path('anambas-id/images/logo-pemda.png'),
        ];
        $logoPath = null; $logoMime = null;
        foreach ($logoCandidates as $p) {
            if (file_exists($p)) {
                $logoPath = $p;
                $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
                $logoMime = $ext === 'svg' ? 'image/svg+xml' : 'image/png';
                break;
            }
        }

        $finalSvg = $baseSvg;
        if ($logoPath) {
            try {
                $logoBinary = file_get_contents($logoPath);
                $logoData   = 'data:'.$logoMime.';base64,'.base64_encode($logoBinary);
                $logoSide   = (int) round($size * 0.20);
                $offset     = (int) round(($size - $logoSide) / 2);
                $logoTag    = '<image x="'.$offset.'" y="'.$offset.'" width="'.$logoSide.
                              '" height="'.$logoSide.'" href="'.$logoData.'" />';
                $finalSvg   = preg_replace('/<\/svg>\s*$/', $logoTag.'</svg>', $baseSvg, 1);
            } catch (\Throwable $e) {
                $finalSvg = $baseSvg;
            }
        }

        return new Response($finalSvg, 200, [
            'Content-Type'  => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=86400',
            'ETag'          => '"'.sha1($token.'|'.$size).'"',
        ]);
    }
}
