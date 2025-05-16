<script setup lang="ts">
import {computed, type HTMLAttributes} from 'vue'
import {Info} from "lucide-vue-next"



const props = withDefaults(defineProps<{
    class?: HTMLAttributes['class']
    colspan?: number
}>(), {
    colspan: 1,
})

const delegatedProps = computed(() => {
    const {class: _, ...delegated} = props

    return delegated
})
</script>

<template>
    <div class="flex items-center justify-center flex-col py-10 w-full">
        <Info class="size-12 opacity-70"/>
      <div class="opacity-70 mt-4">  موردی برای نمایش وجود ندارد.</div>
    </div>
</template>
