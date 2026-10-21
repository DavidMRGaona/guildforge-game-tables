<template>
    <div class="animate-pulse overflow-hidden rounded-lg bg-surface shadow-sm shadow-black/50">
        <!-- Header band skeleton -->
        <div class="flex items-center justify-between bg-base-secondary/20 px-4 py-3">
            <div class="h-10 w-10 rounded bg-surface/50" />
            <div class="flex gap-2">
                <div class="h-5 w-16 rounded-full bg-surface/60" />
                <div class="h-5 w-20 rounded-full bg-surface/60" />
            </div>
        </div>

        <!-- Body skeleton -->
        <div class="p-4">
            <!-- Title + system -->
            <div class="mb-3">
                <div class="mb-1.5 h-5 w-3/4 rounded bg-muted" />
                <div class="h-4 w-1/3 rounded bg-muted" />
            </div>

            <!-- Details panel -->
            <div class="mb-3 rounded-lg bg-muted p-3">
                <div class="mb-2 h-4 w-2/3 rounded bg-base-secondary/10" />
                <div class="mb-1 flex justify-between">
                    <div class="h-3 w-16 rounded bg-base-secondary/10" />
                    <div class="h-3 w-12 rounded bg-base-secondary/10" />
                </div>
                <div class="h-2.5 w-full rounded-full bg-base-secondary/10" />
            </div>

            <!-- Meta row -->
            <div class="flex items-center justify-between">
                <div class="h-7 w-28 rounded-full bg-muted" />
                <div class="h-5 w-20 rounded-full bg-muted" />
            </div>
        </div>
    </div>
</template>
