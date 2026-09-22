<section class="border rounded p-4 text-center my-4" aria-labelledby="qris-demo-title">
    <h2 id="qris-demo-title" class="h5">QR Pembayaran Demo</h2>
    <p class="alert alert-warning">QRIS belum terhubung</p>
    <img src="{{ asset('images/qris-demo.svg') }}" alt="Placeholder QR demo, tidak dapat digunakan untuk pembayaran" width="240" height="240" class="img-fluid">
    <p class="small text-secondary mt-3">Ini hanya tampilan demo dan tidak dapat digunakan untuk membayar. Silakan hubungi kasir untuk pembayaran.</p>
    <p>Total pembayaran: <strong>Rp{{ number_format((float) $order->total, str_ends_with($order->total, '.00') ? 0 : 2, ',', '.') }}</strong></p>
    <p class="mb-0" role="status">Status: <strong>Menunggu Pembayaran</strong></p>
</section>
