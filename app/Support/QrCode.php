<?php

namespace App\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode as Generator;
use chillerlan\QRCode\QROptions;

/**
 * QR SVG generat server-side (fără GD/Imagick).
 */
class QrCode
{
    public static function svg(string $data, string $color = '#133E23'): string
    {
        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'eccLevel' => EccLevel::M,
            'outputBase64' => false,
            'addQuietzone' => true,
            'quietzoneSize' => 2,
            'svgUseFillAttributes' => true,
            'drawLightModules' => false,
            'markupDark' => $color,
            'connectPaths' => true,
        ]);

        return (new Generator($options))->render($data);
    }
}
