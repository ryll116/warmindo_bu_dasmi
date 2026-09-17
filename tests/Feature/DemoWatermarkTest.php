<?php

namespace Tests\Feature;

use App\Models\Table;
use App\Models\User;
use Tests\Feature\Admin\AdminDatabaseTestCase;

class DemoWatermarkTest extends AdminDatabaseTestCase
{
    public function test_watermark_can_be_enabled_and_disabled_across_application_layouts(): void
    {
        $table = Table::factory()->create();
        $this->actingAs(User::factory()->admin()->create());
        foreach ([true, false] as $enabled) {
            config(['app.demo_mode' => $enabled]);
            foreach (['/', route('customer.menu', $table->qr_token), '/menu/invalid-token', route('admin.tables.index'), route('admin.tables.qr', $table)] as $url) {
                $response = $this->get($url);
                if ($enabled) {
                    $response->assertSee('DEMO VERSION')->assertSee('css/demo-watermark.css');
                    $this->assertSame(1, substr_count($response->getContent(), 'class="demo-watermark"'));
                } else {
                    $response->assertDontSee('DEMO VERSION')->assertDontSee('css/demo-watermark.css');
                }
            }
        }

    }
}
