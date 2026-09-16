<?php

namespace Tests\Feature\Admin;

use App\Models\Table;

class TableQrTest extends AdminDatabaseTestCase
{
    public function test_qr_uses_configured_url_and_existing_token_and_download_matches_preview(): void
    {
        config(['app.url' => 'https://menu.example.test']);
        $table = Table::factory()->create(['table_no' => 5]);
        $token = $table->qr_token;
        $url = 'https://menu.example.test/menu/'.$token;
        $page = $this->get(route('admin.tables.qr', $table))->assertOk()->assertSee('Meja 05')->assertViewHas('menuUrl', $url);
        $download = $this->get(route('admin.tables.qr.download', $table))->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertDownload('meja-05-qr.svg');
        $this->assertSame('data:image/svg+xml;base64,'.base64_encode($download->getContent()), $page->viewData('qrImage'));
        $this->assertStringContainsString('<svg', $download->getContent());
        $this->get(route('admin.tables.qr', $table))->assertViewHas('menuUrl', $url);
        $this->assertSame($token, $table->fresh()->qr_token);
        $this->get(route('customer.menu', $token))->assertOk()->assertSee('Meja 05');
    }

    public function test_different_tables_have_different_qr_and_missing_tables_return_404(): void
    {
        $first = Table::factory()->create();
        $second = Table::factory()->create();
        $one = $this->get(route('admin.tables.qr', $first))->assertOk();
        $two = $this->get(route('admin.tables.qr', $second))->assertOk();
        $this->assertNotSame($one->viewData('menuUrl'), $two->viewData('menuUrl'));
        $this->assertNotSame($one->viewData('qrImage'), $two->viewData('qrImage'));
        $this->get('/admin/tables/999999/qr')->assertNotFound();
        $this->get('/admin/tables/999999/qr/download')->assertNotFound();
        $second->update(['is_available' => false]);
        $this->get(route('admin.tables.qr', $second))->assertOk();
        $this->get(route('customer.menu', $second->qr_token))->assertNotFound();
    }
}
