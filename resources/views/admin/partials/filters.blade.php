<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route($indexRoute) }}" class="row g-3 align-items-end" role="search">
            <div class="col-12 col-md">
                <label for="search" class="form-label">Search</label>
                <input type="search" class="form-control" id="search" name="search" value="{{ request('search') }}" maxlength="255" placeholder="{{ $searchPlaceholder }}">
            </div>
            @isset($categoryOptions)
                <div class="col-12 col-md-3">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">Semua kategori</option>
                        @foreach ($categoryOptions as $option)
                            <option value="{{ $option->id }}" @selected((string) request('category') === (string) $option->id)>{{ $option->category_name }}</option>
                        @endforeach
                    </select>
                </div>
            @endisset
            @if ($showStatus ?? true)
                <div class="col-12 col-md-3">
                    <label for="status" class="form-label">{{ $statusLabel ?? 'Status' }}</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Semua status</option>
                        <option value="active" @selected(request('status') === 'active')>{{ $activeLabel ?? 'Active' }}</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>{{ $inactiveLabel ?? 'Inactive' }}</option>
                    </select>
                </div>
            @endif
            <div class="col-12 col-md-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary">Search/Filter</button>
                <a href="{{ route($indexRoute) }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>
