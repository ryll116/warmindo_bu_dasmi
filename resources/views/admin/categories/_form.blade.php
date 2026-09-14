<div class="mb-4">
    <label for="category_name" class="form-label">Category Name</label>
    <input type="text" id="category_name" name="category_name" class="form-control @error('category_name') is-invalid @enderror" value="{{ old('category_name', $category->category_name) }}" maxlength="255" required @error('category_name') aria-invalid="true" aria-describedby="category_name-error" @enderror>
    @error('category_name') <div class="invalid-feedback" id="category_name-error">{{ $message }}</div> @enderror
</div>
<div class="mb-4">
    <label for="status" class="form-label">Status</label>
    <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required @error('status') aria-invalid="true" aria-describedby="status-error" @enderror>
        <option value="">Pilih status</option>
        <option value="active" @selected(old('status', $category->status) === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $category->status) === 'inactive')>Inactive</option>
    </select>
    @error('status') <div class="invalid-feedback" id="status-error">{{ $message }}</div> @enderror
</div>
<div class="d-flex flex-wrap gap-2 border-top mt-4 pt-4">
    <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
    <a href="{{ route('admin.categories.index') }}" class="btn btn-outline-secondary">Batal</a>
</div>
