<div class="mb-3">
    <label for="name" class="form-label">Nama</label>
    <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $user->name) }}" maxlength="255" required autocomplete="name">
</div>
<div class="mb-3">
    <label for="email" class="form-label">Email</label>
    <input type="email" id="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" maxlength="255" required autocomplete="email">
</div>
<div class="mb-3">
    <label for="role" class="form-label">Role</label>
    <select id="role" name="role" class="form-select" required>
        @foreach (['kasir' => 'Kasir', 'admin' => 'Admin', 'superAdmin' => 'Super Admin'] as $value => $label)
            <option value="{{ $value }}" @selected(old('role', $user->role) === $value)>{{ $label }}</option>
        @endforeach
    </select>
    @if ($user->is(auth()->user()))<p class="form-text">Role akun yang sedang digunakan tidak dapat diubah.</p>@endif
</div>
<div class="mb-3">
    <label for="password" class="form-label">Password</label>
    <input type="password" id="password" name="password" class="form-control" minlength="8" maxlength="255" autocomplete="new-password" @required(! $user->exists)>
    <p class="form-text">Minimal 8 karakter. @if ($user->exists)Kosongkan untuk mempertahankan password lama.@endif</p>
</div>
<div class="mb-4">
    <label for="password_confirmation" class="form-label">Konfirmasi Password</label>
    <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" maxlength="255" autocomplete="new-password" @required(! $user->exists)>
</div>
<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">Simpan User</button>
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Batal</a>
</div>
