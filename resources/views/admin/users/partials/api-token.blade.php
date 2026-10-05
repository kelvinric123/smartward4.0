{{-- API token of an Integration User: shown once when generated, otherwise only its status. --}}
<div id="api-token" class="mt-6 bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-blue-100">
    <div class="p-8 text-gray-900">
        <h3 class="text-lg font-semibold">API token</h3>
        <p class="mt-1 text-sm text-gray-600">
            A system such as the C+ Bed Management RPA sends this token as
            <span class="font-mono">Authorization: Bearer &hellip;</span>. SmartWard keeps only a fingerprint of it,
            so the token is shown once, right after it is generated.
        </p>

        @if(session('api_token'))
            <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4" x-data="{ copied: false }">
                <p class="text-sm font-semibold text-amber-900">Copy this token now. It will not be shown again.</p>
                <div class="mt-2 flex flex-col sm:flex-row gap-2">
                    <input type="text" readonly value="{{ session('api_token') }}" x-ref="token"
                        class="flex-1 min-w-0 font-mono text-sm rounded-md border-gray-300 bg-white"
                        x-on:focus="$event.target.select()">
                    <button type="button"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm hover:bg-blue-700"
                        x-on:click="$refs.token.select();
                            (navigator.clipboard ? navigator.clipboard.writeText($refs.token.value) : Promise.resolve(document.execCommand('copy')))
                                .then(() => copied = true)"
                        x-text="copied ? 'Copied' : 'Copy'">Copy</button>
                </div>
                <p class="mt-2 text-xs text-amber-800">
                    For the C+ RPA, put it in <span class="font-mono">rpa_cplus_smartward\.env</span> as
                    <span class="font-mono">SMARTWARD_TOKEN=&hellip;</span>
                </p>
            </div>
        @endif

        <dl class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
            <div>
                <dt class="text-gray-500">Status</dt>
                <dd class="mt-1 font-medium">
                    @if(! $user->hasApiToken())
                        <span class="inline-flex px-2 text-xs font-semibold leading-5 rounded-full bg-gray-100 text-gray-700">No token</span>
                    @elseif($user->isDeactivated())
                        <span class="inline-flex px-2 text-xs font-semibold leading-5 rounded-full bg-red-100 text-red-800">Refused: user deactivated</span>
                    @else
                        <span class="inline-flex px-2 text-xs font-semibold leading-5 rounded-full bg-green-100 text-green-800">Active</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-gray-500">Generated</dt>
                <dd class="mt-1 font-medium">{{ $user->api_token_created_at?->format('d/m/Y H:i') ?? '-' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Last used</dt>
                <dd class="mt-1 font-medium">{{ $user->api_token_last_used_at?->format('d/m/Y H:i') ?? 'Never' }}</dd>
            </div>
        </dl>

        <div class="mt-6 flex flex-wrap gap-3">
            <form method="POST" action="{{ route('users.api-token.generate', $user) }}"
                @if($user->hasApiToken()) onsubmit="return confirm('The current token stops working at once. Generate a new one?')" @endif>
                @csrf
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    {{ $user->hasApiToken() ? 'Regenerate token' : 'Generate token' }}
                </button>
            </form>
            @if($user->hasApiToken())
                <form method="POST" action="{{ route('users.api-token.revoke', $user) }}"
                    onsubmit="return confirm('Revoke this token? Calls made with it will be refused.')">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-white border border-red-300 text-red-700 rounded-lg hover:bg-red-50">
                        Revoke token
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>
