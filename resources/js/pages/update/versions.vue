<script setup lang="ts">
import {defineProps, ref} from "vue";
import AppLayout from '@/layouts/AppLayout.vue';
import {type BreadcrumbItem} from '@/types';
import {Head, router, useForm} from '@inertiajs/vue3';
import AppPageTitle from "@/components/AppPageTitle.vue";
import Icon from "@/components/Icon.vue";
import {Button} from "@/components/ui/button";
import {toPersianDate} from "@/lib/utils";
import {Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle} from "@/components/ui/dialog";
import {Badge} from "@/components/ui/badge";
import {Alert, AlertDescription, AlertTitle} from "@/components/ui/alert";


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
const props = defineProps(['versions', 'currentVersion', 'isUpdated'])
const updateVersions = ref(
    props.versions.map(i => ({
        ...i,
        showLog: false,
    }))
)

const checkIsUpdated = useForm({
    isUpdated: false,
});

function updateToLastVersion() {
    checkIsUpdate.post(route('admin.update.check'), {
        onFinished() {
            console.log('ok')
        }
    })
}


</script>

<template>
    <Head title="به‌روز‌رسانی"/>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">

            <AppPageTitle
                icon="refreshCw"
                title="به‌روز‌رسانی"
                subtitle="در این صفحه می‌توانید نسخه مختلف سایت را ببنید و همچنین به اخرین نسخه به‌روزرسانی کنید">

            </AppPageTitle>


            <div class="mt-4">
                <Alert class="mb-6" v-if="isUpdated">
                    <AlertTitle class="flex items-center justify-start">
                        <Icon name="circleCheck" class="text-green-600 ml-2 size-5 stroke-2"/>
                        <span>  تبریک!
                        سایت شما به‌روز است.</span>
                    </AlertTitle>

                    <AlertDescription class="pr-6">
                        شما از آخرین نسخه سایت استفاده می کنید.
                    </AlertDescription>
                </Alert>
                <Alert class="mb-6" v-else variant="warning">
                    <AlertTitle class="flex items-center justify-between">
                        <span>
                           توجه!
                        سایت شما نیاز به به‌روزرسانی دارد.
                        </span>

                        <div class="flex items-center justify-end">
                            <Button variant="outline" @click="router.visit(route('admin.update.run'))" >
                                <Icon name="refreshCw"/>
                                به‎‌روزرسانی به اخرین نسخه
                            </Button>

                        </div>
                    </AlertTitle>

                    <AlertDescription >
                            برای به‌روزرسانی روی دکمه مقابل کلیک کنید.
                    </AlertDescription>
                </Alert>

                <div v-for="(version,i) in updateVersions"
                     class=" flex items-start justify-between p-2 dark:bg-gray-950 bg-gray-100 rounded-md mb-6">
                    <div class="flex items-start justify-start flex-col ">
                        <div class="flex items-center justify-start">
                            <div class="flex items-center justify-start">
                                <Icon name="package" class="ml-2"/>
                                <span> {{ version.title }}</span>
                                <small class="text-sm mx-1 font-bold">(نسخه {{ version.version }})</small>
                                <div class="mr-3 text-blue-500  cursor-pointer flex items-center justify-center">
                                    <span class="text-[12px]" @click="version.showLog = !version.showLog">
                                        تغییرات این نسخه
                                    </span>

                                    <Icon v-if="!version.showLog" name="chevronDown" class="size-3"/>
                                    <Icon v-else name="chevronUp" class="size-3"/>
                                </div>
                            </div>
                        </div>


                        <div class="flex items-center justify-start opacity-70 text-[13px] mt-1 pr-4">
                            <Icon name="clock3" class="size-3"/>
                            <span class="mr-1">منتشر شده در : {{ toPersianDate(version.released_at) }}</span>
                        </div>


                        <div class="mt-4 mr-6  " v-if="version.showLog">
                            <div class="text-[13px]">تغییرات این نسخه:</div>
                            <ul
                                class="list-disc  p-2">
                                <li v-for="log in version.logs " class="text-black dark:text-white/70 text-[13px]"
                                    v-html="log"></li>
                            </ul>
                        </div>

                    </div>

                    <div>

                        <Badge v-if="version.version === props.currentVersion ">
                            نسخه فعلی شما
                        </Badge>

                        <Badge class="mr-2" variant="info" v-if="i === 0">
                            آخرین نسخه
                        </Badge>

                    </div>
                </div>
            </div>


        </div>
    </AppLayout>


    <Dialog :open="model">
        <DialogContent @close="model=false">
            <div class="space-y-6">
                <DialogHeader class="space-y-3">
                    <DialogTitle>
                        به‌روز‌رسانی به آخرین نسخه نرم‎‌افزار
                    </DialogTitle>

                </DialogHeader>


                <div>
                    <div v-if="checkIsUpdated.processing" class="flex items-center justify-center flex-col">
                        <span>
                           در حال بررسی آخرین نسخه...
                       </span>
                    </div>
                    <template v-else>
                            <span v-if="checkIsUpdated.isUpdated">
                                شما در حال استفاده از آخرین نسخه سایت هستید.
                            </span>
                    </template>
                </div>


                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" @click="model = false"> لغو</Button>
                    </DialogClose>

                    <Button variant="destructive" @click="onDelete" :disabled="form.processing"
                            :loading="form.processing">
                        حذف کردن
                    </Button>
                </DialogFooter>
            </div>
        </DialogContent>
    </Dialog>
</template>
