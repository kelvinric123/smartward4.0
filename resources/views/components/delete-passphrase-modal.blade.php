{{-- Delete Passphrase Confirmation Modal --}}
{{-- Include this once in the layout. All delete buttons use the global modal via JS. --}}

{{-- Error message display for failed passphrase --}}
@if (session('error'))
    <div class="fixed top-4 right-4 z-[9999] max-w-md bg-gradient-to-r from-red-50 to-pink-50 border-l-4 border-red-500 text-red-800 px-6 py-4 rounded-lg shadow-xl animate-slide-in" 
         id="delete-error-toast" role="alert">
        <div class="flex items-center">
            <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <span class="font-medium">{{ session('error') }}</span>
            <button onclick="this.closest('[role=alert]').remove()" class="ml-3 text-red-600 hover:text-red-800">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>
    <script>
        setTimeout(() => {
            const toast = document.getElementById('delete-error-toast');
            if (toast) toast.style.opacity = '0';
            setTimeout(() => { if (toast) toast.remove(); }, 300);
        }, 5000);
    </script>
@endif

{{-- The Modal --}}
<div id="delete-passphrase-modal" class="fixed inset-0 z-[9998] hidden">
    {{-- Backdrop --}}
    <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" onclick="closeDeleteModal()"></div>
    
    {{-- Modal Content --}}
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full transform transition-all border border-red-100" id="delete-modal-content">
            {{-- Header --}}
            <div class="bg-gradient-to-r from-red-500 to-pink-500 rounded-t-2xl px-6 py-4">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center mr-3">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-white">Confirm Deletion</h3>
                        <p class="text-red-100 text-sm">This action cannot be undone</p>
                    </div>
                </div>
            </div>
            
            {{-- Body --}}
            <div class="px-6 py-5">
                <p class="text-gray-600 text-sm mb-4" id="delete-modal-message">
                    Please enter the passphrase to confirm this deletion.
                </p>
                
                <div class="relative">
                    <label for="delete-passphrase-input" class="block text-sm font-medium text-gray-700 mb-1.5">
                        Passphrase <span class="text-red-500">*</span>
                    </label>
                    {{-- The password field is created only while the modal is open. A password input sitting in
                         every page makes browsers treat the page as a login form, so they autofill the saved
                         username into the first text box on the page - usually a search box. --}}
                    <div id="delete-passphrase-slot"></div>
                    <p class="mt-1.5 text-xs text-gray-400">Contact your administrator if you don't know the passphrase.</p>
                </div>
            </div>
            
            {{-- Footer --}}
            <div class="bg-gray-50 rounded-b-2xl px-6 py-4 flex justify-end gap-3">
                <button type="button" onclick="closeDeleteModal()" 
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button type="button" onclick="submitDeleteForm()" id="delete-confirm-btn"
                        class="px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-red-600 to-pink-600 rounded-lg hover:from-red-700 hover:to-pink-700 transition-all shadow-md hover:shadow-lg">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Delete
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    @keyframes slide-in {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    .animate-slide-in { animation: slide-in 0.3s ease-out; }
</style>

<script>
    let pendingDeleteForm = null;

    /**
     * Builds the passphrase field when the modal opens, and drops it again when it closes,
     * so no password input is present during normal page use (see the note in the markup above).
     */
    function buildPassphraseInput() {
        const slot = document.getElementById('delete-passphrase-slot');
        slot.innerHTML = '';

        const input = document.createElement('input');
        input.type = 'password';
        input.id = 'delete-passphrase-input';
        input.placeholder = 'Enter passphrase to delete';
        input.className = 'w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors';
        // Keep browser and third party password managers away from this field
        input.setAttribute('autocomplete', 'new-password');
        input.setAttribute('data-lpignore', 'true');
        input.setAttribute('data-1p-ignore', '');
        input.setAttribute('data-form-type', 'other');

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                submitDeleteForm();
            }
            // Remove error styling on type
            input.classList.remove('border-red-500', 'ring-2', 'ring-red-200');
        });

        slot.appendChild(input);
        return input;
    }

    /**
     * Called by delete buttons. Shows the passphrase modal instead of browser confirm.
     * Usage: <button onclick="confirmDelete(event, 'Are you sure?')">Delete</button>
     * The button must be inside a <form>.
     */
    function confirmDelete(event, message) {
        event.preventDefault();
        event.stopPropagation();

        // Find the parent form
        const btn = event.currentTarget || event.target;
        pendingDeleteForm = btn.closest('form');

        if (!pendingDeleteForm) {
            console.error('confirmDelete: No parent <form> found');
            return;
        }

        // Set custom message
        const msgEl = document.getElementById('delete-modal-message');
        if (msgEl && message) {
            msgEl.textContent = message;
        }

        // Show modal
        const modal = document.getElementById('delete-passphrase-modal');
        modal.classList.remove('hidden');

        // Build and focus the passphrase field
        const input = buildPassphraseInput();
        setTimeout(() => input.focus(), 100);
    }

    function closeDeleteModal() {
        const modal = document.getElementById('delete-passphrase-modal');
        modal.classList.add('hidden');
        pendingDeleteForm = null;
        document.getElementById('delete-passphrase-slot').innerHTML = '';
    }

    function submitDeleteForm() {
        const input = document.getElementById('delete-passphrase-input');
        const passphrase = input ? input.value : '';

        if (!passphrase) {
            if (input) {
                input.classList.add('border-red-500', 'ring-2', 'ring-red-200');
                input.focus();
            }
            return;
        }

        if (pendingDeleteForm) {
            // Add passphrase as hidden input to the form
            let hiddenInput = pendingDeleteForm.querySelector('input[name="delete_passphrase"]');
            if (!hiddenInput) {
                hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'delete_passphrase';
                pendingDeleteForm.appendChild(hiddenInput);
            }
            hiddenInput.value = passphrase;

            // Submit the form
            pendingDeleteForm.submit();
        }

        closeDeleteModal();
    }

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDeleteModal();
        }
    });
</script>
