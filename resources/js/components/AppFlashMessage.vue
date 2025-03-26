<script setup>

import {usePage} from "@inertiajs/vue3";
import {computed, onMounted, ref, toRaw, watch} from "vue";
import { useToast } from '@/components/ui/toast/use-toast'
import { Toaster } from '@/components/ui/toast'

const { toast } = useToast()
const {success = "", error = "", warning = ""} = usePage().props?.flash;
const data = computed(() => usePage().props?.flash);
const errors = computed(() => usePage().props?.errors);


watch(data, (value) => {
    const {success, error, warning} = value;

    if (success) {
        toast({
            title: success,
        });
    }

    if (error) {
        toast({
            title: error,
            variant:'destructive'
        });
    }

    if (warning) {
        toast({
            title: warning,
            variant:'warning'
        });
    }
}, {
    deep: true,
    immediate: true
})

watch(errors, (value) => {
    Object.entries(value).forEach((item) => {
        toast({
            title: item[1],
            variant:'destructive'
        });
    })
}, {
    deep: true,
    immediate: true
})


if (success) {
    toast({
        title:success,
        variant:'default'
    });}

if (error) {
    toast({
        title: error,
        variant:'destructive'
    });}

if (warning) {
    toast({
        title: warning,
        variant:'warning'
    });}


</script>


<template>
    <Toaster/>
</template>


