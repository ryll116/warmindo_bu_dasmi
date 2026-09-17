@if (config('app.demo_mode'))
    <link rel="stylesheet" href="{{ asset('css/demo-watermark.css') }}">
    <div class="demo-watermark" aria-hidden="true"><span>DEMO VERSION</span></div>
@endif
