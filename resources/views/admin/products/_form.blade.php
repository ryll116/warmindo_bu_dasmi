<p class="text-secondary mb-4">Lengkapi informasi produk. Semua kolom wajib diisi.</p>
@if ($categories->isEmpty())
    <div class="alert alert-warning" role="alert">Belum ada kategori. Siapkan data kategori terlebih dahulu sebelum menyimpan produk.</div>
@endif
<div class="row g-4">
    <div class="col-md-6">
        <label for="product_code" class="form-label">Product Code</label>
        <input type="text" id="product_code" name="product_code" class="form-control @error('product_code') is-invalid @enderror" value="{{ old('product_code', $product->product_code) }}" maxlength="255" required aria-describedby="product_code-error">
        @error('product_code') <div class="invalid-feedback" id="product_code-error">{{ $message }}</div> @enderror
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
    <div class="col-12">
        <label for="product_name" class="form-label">Product Name</label>
        <input type="text" id="product_name" name="product_name" class="form-control @error('product_name') is-invalid @enderror" value="{{ old('product_name', $product->product_name) }}" maxlength="255" required aria-describedby="product_name-error">
        @error('product_name') <div class="invalid-feedback" id="product_name-error">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label for="price" class="form-label">Price (Rp)</label>
        <input type="number" id="price" name="price" class="form-control @error('price') is-invalid @enderror" value="{{ old('price', $product->price) }}" min="0" max="9999999999.99" step="0.01" required aria-describedby="price-help price-error">
        <div class="form-text" id="price-help">Harga dalam rupiah, maksimal 2 angka desimal.</div>
        @error('price') <div class="invalid-feedback" id="price-error">{{ $message }}</div> @enderror
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
    <button type="submit" class="btn btn-primary" @disabled($categories->isEmpty())>{{ $submitLabel }}</button>
    <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">Batal</a>
</div>
