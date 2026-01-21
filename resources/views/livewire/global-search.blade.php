<div>
    {{-- Global Search Component --}}
    <div x-data="{
        search: @entangle('searchQuery'),
        results: [],
        filterResults(items, query) {
            const trimmed = query.toLowerCase().trim();
            return items.filter(item => {
                return item.name.toLowerCase().includes(trimmed) ||
                       item.type.toLowerCase().includes(trimmed) ||
                       (item.quickcommand && item.quickcommand.toLowerCase().includes(trimmed));
            });
        }
    }">
        <input type="text" wire:model.live="searchQuery" placeholder="Search..." />
    </div>
</div>
