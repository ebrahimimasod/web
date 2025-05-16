<script setup lang="ts">
import {Head, router} from '@inertiajs/vue3';
import {type BreadcrumbItem} from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import AppPageTitle from "@/components/AppPageTitle.vue";
import DetailItem from "@/components/DetailItem.vue";
import {toPersianDate} from "@/lib/utils";
import {Button} from "@/components/ui/button";
import Icon from "@/components/Icon.vue";
import DeleteResource from "@/components/DeleteResource.vue";
import {reactive} from "vue";


const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'پنل مدیریت',
        href: '/dashboard',
    },
    {
        title: 'کاربران',
        href: '/users',
    },
    {
        title: 'نمایش کاربر',
        href: '/users/show',
    },
];

const {user} = defineProps(['user']);

const deleteUser = reactive({
    dialog: false,
    url: null,
});



function openDialogForDelete(id) {
    deleteUser.url = route('admin.users.destroy', {id: id});
    deleteUser.dialog = true;
}



</script>

<template>
    <Head title="نمایش اطلاعات کاربر"/>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">

            <AppPageTitle
                icon="user"
                title="نمایش اطلاعات کاربر"
                subtitle="در این صفحه می توانید اطلاعات کاربر را مشاهده و مدیریت کنید.">
                <template #actions>
                    <div class="flex items-center justify-end">
                        <Button @click="router.visit((route('admin.users.edit',{id:user.id})))">
                            <Icon name="userPen"/>
                            ویرایش
                        </Button>
                        <Button @click="openDialogForDelete(user.id)" variant="destructive" class="mr-2">
                            <Icon name="trash"/>
                            حذف
                        </Button>
                    </div>
                </template>
            </AppPageTitle>


            <div class="grid w-full max-w-md items-center gap-1.5 mt-6">
                <DetailItem name="نام" :value="user.first_name"/>
                <DetailItem name="نام خانوادگی" :value="user.last_name"/>
                <DetailItem name="ایمیل" :value="user.email"/>
                <DetailItem name="شماره موبایل" :value="user.phone_number"/>
                <DetailItem name="وضعیت" :value="user.status ? 'فعال' : 'غیرفعال'"/>
                <DetailItem name="ادمین" :value="user.is_admin ? 'بله' : 'خیر'"/>
                <DetailItem name="تاریخ ثبت نام" :value="toPersianDate(user.created_at)"/>
                <DetailItem name="تاریخ اخرین ویرایش" :value="toPersianDate(user.updated_at)"/>
                <DetailItem name="تاریخ اخرین فعالیت" :value="' - '"/>

            </div>


        </div>

        <DeleteResource v-model="deleteUser.dialog" label="کاربر " :url="deleteUser.url"/>
    </AppLayout>
</template>
