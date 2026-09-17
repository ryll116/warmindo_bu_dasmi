@extends('layouts.admin')

@section('title', 'User Management')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <h1 class="h3 fw-bold mb-0">User Management</h1>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">Tambah User</a>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th>Nama</th><th>Email</th><th>Role</th><th>Tanggal dibuat</th><th>Action</th></tr></thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td class="text-break">{{ $user->name }}</td>
                            <td class="text-break">{{ $user->email }}</td>
                            <td><span class="badge text-bg-light border">{{ strtoupper($user->role) }}</span></td>
                            <td class="text-nowrap">{{ $user->created_at?->format('d/m/Y H:i') }}</td>
                            <td><div class="d-flex gap-2">
                                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-outline-primary btn-sm">Edit</a>
                                @unless ($user->is(auth()->user()))
                                    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#delete-record" data-delete-url="{{ route('admin.users.destroy', $user) }}" data-record-name="{{ $user->name }} ({{ $user->email }})">Hapus</button>
                                @endunless
                            </div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())<div class="card-body">{{ $users->links('pagination::bootstrap-5') }}</div>@endif
    </div>
    @include('admin.partials.delete-modal', ['recordLabel' => 'User'])
@endsection
