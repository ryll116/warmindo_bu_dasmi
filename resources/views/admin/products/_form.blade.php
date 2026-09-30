<p class="text-secondary mb-4">Lengkapi informasi produk. Isi diskon 0 untuk produk tanpa promo.</p>
@if ($categories->isEmpty())
    <div class="alert alert-warning" role="alert">Belum ada kategori. Siapkan data kategori terlebih dahulu sebelum menyimpan produk.</div>
@endif
@if ($restos->isEmpty())
    <div class="alert alert-warning" role="alert">Belum ada resto. Siapkan data master resto terlebih dahulu sebelum menyimpan produk.</div>
@endif
<div class="row g-4">
    <div class="col-12">
        <label for="product-image" class="form-label">{{ $product->exists ? 'Ganti Foto Produk' : 'Foto Produk' }}</label>
        <input type="file" id="product-image" name="image" accept="image/jpeg,image/png,image/webp" class="form-control @error('image') is-invalid @enderror" aria-describedby="product-image-help product-image-error">
        <div class="form-text" id="product-image-help">Opsional. JPG, JPEG, PNG, atau WebP, maksimal 2 MB. Setelah validasi gagal, pilih ulang file foto.</div>
        @error('image') <div class="invalid-feedback" id="product-image-error">{{ $message }}</div> @enderror
        <img id="product-image-preview" src="{{ $product->imageUrl() }}" data-existing-src="{{ $product->imageUrl() }}" data-placeholder="{{ asset('images/product-placeholder.svg') }}" alt="Preview foto produk" width="160" height="160" class="rounded border mt-3" style="object-fit: cover">
        <p id="product-image-feedback" class="small text-danger mt-2" role="status"></p>
        @if ($product->exists && $product->img)
            <div class="form-check mt-2"><input type="checkbox" id="remove-image" name="remove_image" value="1" class="form-check-input" @checked(old('remove_image'))><label for="remove-image" class="form-check-label">Hapus Foto</label></div>
        @endif
    </div>
    <div class="col-md-6">
        <label for="resto_id" class="form-label">Resto / Penyedia</label>
        <select id="resto_id" name="resto_id" class="form-select @error('resto_id') is-invalid @enderror" required aria-describedby="resto_id-error">
            <option value="">Pilih Resto</option>
            @foreach ($restos as $resto)
                <option value="{{ $resto->id }}" @selected((string) old('resto_id', $product->resto_id) === (string) $resto->id)>{{ $resto->resto_name }}</option>
            @endforeach
        </select>
        @error('resto_id') <div class="invalid-feedback" id="resto_id-error">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label for="category_id" class="form-label">Category</label>
        <select id="category_id" name="category_id" class="form-select @error('category_id') is-invalid @enderror" required aria-describedby="category_id-error">
            <option value="">Pilih kategori</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((string) old('category_id', $product->category_id) === (string) $category->id)>{{ $category->category_name }}</option>
            @endforeach
        </select>
        @error('category_id') <div class="invalid-feedback" id="category_id-error">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label for="product_code" class="form-label">Product Code</label>
        <input type="text" id="product_code" name="product_code" class="form-control" value="{{ $product->exists ? $product->product_code : '' }}" readonly placeholder="Pilih kategori terlebih dahulu" aria-describedby="product-code-help product-code-status" @if (! $product->exists) data-preview-url="{{ route('admin.products.next-code') }}" @endif>
        <div class="small mt-1" id="product-code-status" role="status" aria-live="polite"></div>
    </div>
    <div class="col-12">
        <label for="product_name" class="form-label">Product Name</label>
        <input type="text" id="product_name" name="product_name" class="form-control @error('product_name') is-invalid @enderror" value="{{ old('product_name', $product->product_name) }}" maxlength="255" required aria-describedby="product_name-error">
        @error('product_name') <div class="invalid-feedback" id="product_name-error">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label for="price" class="form-label">Harga Dasar (Rp)</label>
        <input type="number" id="price" name="price" class="form-control @error('price') is-invalid @enderror" value="{{ old('price', $product->price) }}" min="0" max="9999999999.99" step="0.01" required aria-describedby="price-help price-error">
        <div class="form-text" id="price-help">Harga sebelum diskon dalam rupiah, maksimal 2 angka desimal.</div>
        @error('price') <div class="invalid-feedback" id="price-error">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label for="disc" class="form-label">Diskon (%)</label>
        <input type="number" id="disc" name="disc" class="form-control @error('disc') is-invalid @enderror" value="{{ old('disc', $product->disc ?? '0.00') }}" min="0" max="100" step="0.01" required aria-describedby="disc-help disc-error">
        <div class="form-text" id="disc-help">Persentase potongan dari harga dasar. Isi 0 untuk menghapus promo.</div>
        @error('disc') <div class="invalid-feedback" id="disc-error">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label for="is_available" class="form-label">Is Available</label>
        <select id="is_available" name="is_available" class="form-select @error('is_available') is-invalid @enderror" required aria-describedby="is_available-error">
            <option value="1" @selected((string) old('is_available', (int) $product->is_available) === '1')>Available</option>
            <option value="0" @selected((string) old('is_available', (int) $product->is_available) === '0')>Unavailable</option>
        </select>
        @error('is_available') <div class="invalid-feedback" id="is_available-error">{{ $message }}</div> @enderror
    </div>
</div>
<div class="d-flex flex-wrap gap-2 border-top mt-4 pt-4">
    <button type="submit" class="btn btn-primary" @disabled($categories->isEmpty() || $restos->isEmpty())>{{ $submitLabel }}</button>
    <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">Batal</a>
</div>
