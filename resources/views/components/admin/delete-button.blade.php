@props(['action', 'label' => 'Hapus', 'confirm' => 'Yakin ingin menghapus data ini?'])
<form method="POST" action="{{ $action }}" class="inline" onsubmit="return confirm(@js($confirm))">
    @csrf
    @method('DELETE')
    <button type="submit" {{ $attributes->class(['btn btn-danger btn-sm']) }}>{{ $label }}</button>
</form>
