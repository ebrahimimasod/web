<script setup lang="ts">
import {defineProps, onMounted, reactive} from "vue";
import AppLayout from '@/layouts/AppLayout.vue';
import {type BreadcrumbItem} from '@/types';
import {Head, useForm} from '@inertiajs/vue3';
import AppPageTitle from "@/components/AppPageTitle.vue";
import {Progress} from '@/components/ui/progress'


const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'پنل مدیریت',
        href: '/dashboard',
    },
    {
        title: 'به‌روز‌رسانی',
        href: '/update',
    },
];
const props = defineProps(['currentVersion', 'lastVersion'])
const updateForm = useForm({});
const updateProgress = reactive({
    isUpdating: false,
    next_step: null,
    percentage: 0,
    message: 'در حال بررسی وضعیت به‌روزرسانی...',
});

function sendUpdateRequest() {
    updateForm.post(route('admin.update.run'),
        {
            showProgress: false,
            onStart: () => {
                updateProgress.isUpdating = true;
            },

            onSuccess: (page) => {
                const {message,step, next_step, success, percentage} = page.props.back_response;


                updateProgress.message = message;
                updateProgress.next_step = next_step;
                updateProgress.percentage = percentage;
                updateProgress.step = step;

                /*
                 message : "در حال پاک سازی فایل های موقت ..."
                 next_step : "finished"
                 success : true
                 */


                if (step === 'end') {
                    window.location.href = route("admin.update.versions")
                    return;
                }

                if (next_step && success) {
                    sendUpdateRequest();
                }
            },
            onError: (errors) => {

            }
        });
}

onMounted(() => {
    sendUpdateRequest()
})


</script>

<template>
    <Head title="به‌روز‌رسانی"/>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">

            <AppPageTitle
                icon="refreshCw"
                title="به‌روز‌رسانی"
                subtitle="در این صفحه آخرین نسخه نرم‌افزار روی سایت شما نصب خواهد شد.">

            </AppPageTitle>


            <div class="mt-4">
                <Progress :model-value="updateProgress.percentage"/>
                <div class="flex items-center justify-center mt-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                           stroke-width="2">
                            <path stroke-dasharray="16" stroke-dashoffset="16" d="M12 3c4.97 0 9 4.03 9 9">
                                <animate fill="freeze" attributeName="stroke-dashoffset" dur="0.3s" values="16;0"/>
                                <animateTransform attributeName="transform" dur="1.5s" repeatCount="indefinite"
                                                  type="rotate" values="0 12 12;360 12 12"/>
                            </path>
                            <path stroke-dasharray="64" stroke-dashoffset="64" stroke-opacity="0.3"
                                  d="M12 3c4.97 0 9 4.03 9 9c0 4.97 -4.03 9 -9 9c-4.97 0 -9 -4.03 -9 -9c0 -4.97 4.03 -9 9 -9Z">
                                <animate fill="freeze" attributeName="stroke-dashoffset" dur="1.2s" values="64;0"/>
                            </path>
                        </g>
                    </svg>
                    <span class="mx-2 text-sm">{{ updateProgress.message }}</span>
                    <span class="mr-2 text-sm">{{ updateProgress.percentage }}%</span>
                </div>
            </div>


        </div>
    </AppLayout>

</template>
