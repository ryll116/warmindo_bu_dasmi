<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Table;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Response;
use Illuminate\View\View;

class TableQrController extends Controller
{
    public function show(Table $table): View
    {
        $menuUrl = $this->menuUrl($table);
        $qrImage = 'data:image/svg+xml;base64,'.base64_encode($this->svg($table));

        return view('admin.tables.qr', compact('table', 'menuUrl', 'qrImage'));
    }

    public function download(Table $table): Response
    {
        $number = str_pad((string) $table->table_no, 2, '0', STR_PAD_LEFT);

        return response($this->svg($table), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="meja-'.$number.'-qr.svg"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function menuUrl(Table $table): string
    {
        abort_if(empty($table->qr_token), 404);

        return rtrim(config('app.url'), '/').route('customer.menu', ['qr_token' => $table->qr_token], false);
    }

    private function svg(Table $table): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(1024, 128), new SvgImageBackEnd));

        return $writer->writeString($this->menuUrl($table), 'UTF-8', ErrorCorrectionLevel::M());
    }
}
