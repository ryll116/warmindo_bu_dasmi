<div class="modal fade" id="delete-record" tabindex="-1" aria-labelledby="delete-record-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="delete-record-title">Hapus {{ $recordLabel }}?</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                {{ $recordLabel }} <strong id="delete-record-name"></strong> akan dihapus. Tindakan ini tidak dapat dibatalkan.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <form method="POST" id="delete-record-form">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger" disabled>Ya, hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        document.getElementById('delete-record').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) {
                return;
            }
            const form = document.getElementById('delete-record-form');
            form.action = button.dataset.deleteUrl;
            form.querySelector('button').disabled = false;
            document.getElementById('delete-record-name').textContent = button.dataset.recordName;
        });
    </script>
@endpush
