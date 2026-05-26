<x-filament-panels::page>
    <form id="new-sale-form" wire:submit="save">
        {{ $this->form }}
    </form>

    <div style="margin-top: 0.0rem; display: flex; flex-wrap: wrap; align-items: center; column-gap: 1.25rem; row-gap: 0.75rem;">
        <x-filament::button
            type="button"
            wire:click="save"
            wire:loading.attr="disabled"
            wire:target="save"
            color="success"
            size="md"
        >
            Complete sale
        </x-filament::button>

        <x-filament::button
            type="button"
            wire:click="cancel"
            color="gray"
            outlined
            size="md"
        >
            Cancel
        </x-filament::button>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
