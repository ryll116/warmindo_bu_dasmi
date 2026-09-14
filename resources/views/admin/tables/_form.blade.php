<div class="row g-4">
    <div class="col-md-6">
        <label for="table_no" class="form-label">Nomor Meja</label>
        <input type="number" id="table_no" name="table_no" class="form-control @error('table_no') is-invalid @enderror" value="{{ old('table_no', $table->table_no) }}" min="1" max="2147483647" step="1" required @error('table_no') aria-invalid="true" aria-describedby="table_no-error" @enderror>
        @error('table_no') <div class="invalid-feedback" id="table_no-error">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label for="is_available" class="form-label">Status</label>
        <select id="is_available" name="is_available" class="form-select @error('is_available') is-invalid @enderror" required @error('is_available') aria-invalid="true" aria-describedby="is_available-error" @enderror>
            <option value="1" @selected((string) old('is_available', (int) $table->is_available) === '1')>Active</option>
            <option value="0" @selected((string) old('is_available', (int) $table->is_available) === '0')>Inactive</option>
        </select>
        @error('is_available') <div class="invalid-feedback" id="is_available-error">{{ $message }}</div> @enderror
    </div>
</div>
<div class="d-flex flex-wrap gap-2 border-top mt-4 pt-4">
    <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
    <a href="{{ route('admin.tables.index') }}" class="btn btn-outline-secondary">Batal</a>
</div>
