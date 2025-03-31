<script setup lang="ts">
import {defineProps} from "vue"
const props = defineProps(['name','value']);
</script>


<template>
<div class="flex items-center justify-start w-full mb-4">
    <div class="flex-shrink-0">
        <div class="text-[13px] opacity-70 font-semibold pl-2">{{name}}</div>
    </div>

    <div class="w-full">
        <div class="border border-[0px] border-b border-gray-200 "></div>
    </div>

    <div class="flex-shrink-0">
        <div class="text-[14px] font-semibold pr-2">{{value}}</div>
    </div>
    </div>
</template>
