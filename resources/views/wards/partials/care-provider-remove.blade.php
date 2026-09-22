{{--
    Remove a manually added care provider (Consultant and Anaesthetist tabs).
    Every delete needs the passphrase; the field only exists once Remove is
    clicked, so browsers never treat the page as a login form.
    Expects $provider (PatientCareProvider) and $tab (the tab to come back to).
--}}
<div x-data="{ removing: false }" class="flex items-center">
    <button type="button" x-show="!removing" @click="removing = true"
        class="text-xs font-medium text-red-600 hover:text-red-800 hover:underline"
        title="Remove {{ $provider->display_name }} from this patient's care team">
        Remove
    </button>
    <template x-if="removing">
        <form method="POST" action="{{ route('ward.care-providers.destroy', $provider->id) }}"
            class="flex items-center gap-1">
            @csrf
            @method('DELETE')
            <input type="hidden" name="active_tab" value="{{ $tab }}">
            <input type="password" name="delete_passphrase" required autocomplete="new-password"
                data-lpignore="true" data-1p-ignore data-form-type="other" placeholder="Passphrase"
                class="w-28 rounded border-gray-300 text-xs py-1 focus:border-red-500 focus:ring-red-500">
            <button type="submit"
                class="px-2 py-1 rounded bg-red-600 text-white text-xs font-semibold hover:bg-red-700">
                Remove
            </button>
            <button type="button" @click="removing = false" class="px-2 py-1 text-xs text-gray-600 hover:underline">
                Cancel
            </button>
        </form>
    </template>
</div>
