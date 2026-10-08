<x-filament-panels::page>
    {{ $this->form }}

    <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(min(100%, 18rem), 1fr));">
        @foreach ($this->getReports() as $report)
            <x-filament::section :icon="$report['icon']" icon-color="primary" compact>
                <x-slot name="heading">{{ $report['title'] }}</x-slot>
                <x-slot name="description">Uses: {{ $report['uses'] }}</x-slot>

                <p style="margin: 0 0 1rem; font-size: .875rem; line-height: 1.4;" class="text-gray-600 dark:text-gray-400">
                    {{ $report['description'] }}
                </p>

                <x-filament::button
                    tag="a"
                    :href="$report['url']"
                    target="_blank"
                    icon="heroicon-m-arrow-top-right-on-square"
                    icon-position="after"
                    size="sm"
                >
                    Open report
                </x-filament::button>
            </x-filament::section>
        @endforeach

        <x-filament::section icon="heroicon-o-user" icon-color="primary" compact>
            <x-slot name="heading">Borrower loan history</x-slot>
            <x-slot name="description">Uses: borrower</x-slot>

            <p style="margin: 0 0 .75rem; font-size: .875rem; line-height: 1.4;" class="text-gray-600 dark:text-gray-400">
                All loans of one borrower with their payments and savings balance.
            </p>

            {{ $this->borrowerHistoryForm }}

            <div style="margin-top: .75rem;">
                @if ($url = $this->getBorrowerHistoryUrl())
                    <x-filament::button tag="a" :href="$url" target="_blank" icon="heroicon-m-arrow-top-right-on-square" icon-position="after" size="sm">
                        Open report
                    </x-filament::button>
                @else
                    <x-filament::button size="sm" disabled color="gray">Choose a borrower</x-filament::button>
                @endif
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
