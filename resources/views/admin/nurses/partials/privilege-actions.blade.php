{{--
    Edit and Remove for one privilege record. Expects $nurse, $privilege,
    $formId (the record's own form) and the tab's button classes.
--}}
<button type="button" @click="toggle('{{ $formId }}')" class="{{ $linkButton }}"
    :aria-expanded="open === '{{ $formId }}'">Edit</button>
<form method="POST" action="{{ route('nurses.privileges.destroy', [$nurse, $privilege]) }}">
    @csrf
    @method('DELETE')
    <input type="hidden" name="active_tab" value="credentialing">
    <button type="button" class="{{ $removeButton }}"
        @click="discardChanges() && confirmDelete($event, @js('Remove "' . $privilege->name . '" from this nurse\'s privileges? To stop the nurse using it but keep the record, untick it or set it to Suspended or Withdrawn instead.'))">
        Remove
    </button>
</form>
