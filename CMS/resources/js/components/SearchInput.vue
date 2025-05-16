<script setup lang="ts">

import {defineEmits, defineProps, ref, watch} from "vue"
import {Input} from '@/components/ui/input'
import {Search} from 'lucide-vue-next'
import {useDebounceFn} from "@vueuse/core";

const props = defineProps({
    value: {
        type: String
    }
})
const emits = defineEmits(['change'])
const keyword = ref(props.value);


function onSearch() {
    emits('change', keyword.value);
}

const debouncedSearch = useDebounceFn(onSearch, 600)

watch(() => keyword.value, () => {
    debouncedSearch();
})


</script>

<template>
    <div class="relative w-full min-w-[350px] items-center">
        <Input id="search" type="text" v-model="keyword" placeholder="جستجو..." class="pr-10"/>
        <span class="absolute start-0 inset-y-0 flex items-center justify-center px-2">
        <Search class="size-6 text-muted-foreground"/>
    </span>
    </div>
</template>
