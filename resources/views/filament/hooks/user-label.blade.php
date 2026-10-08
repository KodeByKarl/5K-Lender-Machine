{{-- Plain text beside the avatar: who is signed in and which Area they see. --}}
@auth
    @php($user = auth()->user())
    <div class="lms-user-label" style="text-align: right; line-height: 1.2;">
        <div class="text-gray-950 dark:text-white" style="font-size: .875rem; font-weight: 600;">{{ $user->name }}</div>
        <div class="text-gray-500 dark:text-gray-400" style="font-size: .75rem;">
            {{ $user->isAdmin() ? 'Administrator · All areas' : $user->area?->name }}
        </div>
    </div>
@endauth
