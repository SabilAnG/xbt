@props(['action', 'label' => 'Hapus', 'confirm' => 'Yakin ingin menghapus data ini?'])
<form method="POST" action="{{ $action }}" class="inline" data-confirm="{{ $confirm }}" data-confirm-tone="danger" data-confirm-ok="Ya, hapus">
    @csrf
    @method('DELETE')
    <button type="submit" {{ $attributes->class(['btn btn-danger btn-sm']) }}>{{ $label }}</button>
</form>
