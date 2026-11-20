@props([
    'groups' => [],
])

@if (! empty($groups))
    <div class="mt-2 flex flex-wrap items-center gap-2">
        @foreach ($groups as $group)
            <flux:tooltip :content="$group['tooltip']" position="top">
                <flux:dropdown position="top" align="start">
                    <flux:button
                        size="base"
                        variant="ghost"
                        class="relative p-1"
                        :aria-label="$group['label'].': '.$group['tooltip']"
                    >
                        <flux:icon :name="$group['icon']" class="size-10 {{ $group['color'] }}" />

                        @if ($group['files']->count() > 1)
                            <span class="absolute -top-1 -end-1 flex size-4 items-center justify-center rounded-full bg-zinc-800 text-[10px] font-medium text-white dark:bg-zinc-200 dark:text-zinc-900">
                                {{ $group['files']->count() }}
                            </span>
                        @endif
                    </flux:button>

                    <flux:menu>
                        @foreach ($group['files'] as $file)
                            <flux:menu.item
                                :href="route('attachments.download', $file)"
                                icon="arrow-down-tray"
                                target="_blank"
                                rel="noopener"
                            >
                                {{ $file->original_name }}
                            </flux:menu.item>
                        @endforeach
                    </flux:menu>
                </flux:dropdown>
            </flux:tooltip>
        @endforeach
    </div>
@endif
