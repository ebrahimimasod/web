<script setup lang="ts">

import Icon from "@/components/Icon.vue";

const props = defineProps(['title', 'subtitle','icon']);

</script>

<template>
    <div class="w-full flex items-center justify-between">
        <template v-if="title">
            <div>
                <div class="flex items-center justify-start">
                    <Icon v-if="icon" :name="icon" class="size-6 ml-2"/>
                    <h1 class="text-lg font-semibold">{{ title }}</h1>
                </div>
                <p class="text-[13px] text-gray-500 font-light" v-if="subtitle">{{ subtitle }}</p>
            </div>
        </template>

        <template v-else>
            <slot name="title"/>
        </template>


        <slot name="actions"/>
    </div>
</template>
