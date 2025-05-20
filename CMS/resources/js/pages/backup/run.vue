<script setup lang="ts">
import { Head,router  } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import AppPageTitle from '@/components/AppPageTitle.vue';
import { type BreadcrumbItem } from '@/types';
import {onMounted,onUnmounted} from "vue"
import { useIntervalFn } from '@vueuse/core'



const breadcrumbs: BreadcrumbItem[] = [
    { title: 'پنل مدیریت', href: '/dashboard' },
    { title: 'پشتیبان‌گیری', href: '/backup' },
    { title: 'فرایند پشتیبان‌گیری', href: '/backup/run' },
];

const { pause, resume, isActive } = useIntervalFn(() => {
    router.post(route('admin.backup.run'))
}, 5000)

onUnmounted(pause)



</script>

<template>
    <Head title="فرایند پشتیبان‌گیری" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <AppPageTitle title="فرایند پشتیبان‌گیری" subtitle="وضعیت پیشرفت فرایند پشتیبان‌گیری را در این صفحه دنبال کنید." />

            <div class="mx-auto mt-6 max-w-xl text-center"></div>
        </div>
    </AppLayout>
</template>
