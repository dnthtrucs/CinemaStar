<?php

namespace App\Services;

use App\Models\Booking;
use Picqer\Barcode\BarcodeGeneratorPNG;

class TicketBarcode
{
    public function png(Booking $booking): string
    {
        $bars = (new BarcodeGeneratorPNG)->getBarcode($booking->barcodeValue(), BarcodeGeneratorPNG::TYPE_CODE_128, 2, 100);
        $source = imagecreatefromstring($bars);
        $image = imagecreatetruecolor(imagesx($source) + 40, 120);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagecopy($image, $source, 20, 10, 0, 0, imagesx($source), imagesy($source));
        ob_start();
        imagepng($image);

        return ob_get_clean();
    }

    public function dataUri(Booking $booking): string
    {
        return 'data:image/png;base64,'.base64_encode($this->png($booking));
    }
}
