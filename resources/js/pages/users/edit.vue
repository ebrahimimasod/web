<script setup lang="ts">
import {Head, useForm} from '@inertiajs/vue3';
import {Input} from '@/components/ui/input'
import {Label} from "@/components/ui/label";
import {type BreadcrumbItem} from '@/types';
import {Button} from "@/components/ui/button";
import {Switch} from "@/components/ui/switch";
import AppLayout from '@/layouts/AppLayout.vue';
import AppPageTitle from "@/components/AppPageTitle.vue";
import {Badge} from "@/components/ui/badge";
import InputError from "@/components/InputError.vue";


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
        title: 'ویرایش کاربر',
        href: '/users/edit',
    },
];

const props = defineProps(['data']);


const user = useForm({
    id: props.data?.id,
    first_name: props.data?.first_name,
    last_name: props.data?.last_name,
    email: props.data?.email,
    phone_number: props.data?.phone_number,
    password: props.data?.password,
    status: !!props.data?.status,
    is_admin: !!props.data?.is_admin,
});

function onSubmit() {
    user.post(route('admin.users.update', {id: user.id}), {
        onSuccess: () => user.reset()
    })
}


</script>

<template>
    <Head title="ویرایش کاربر"/>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">

            <AppPageTitle
                icon="userPen"
                title="ویرایش کاربر"
                subtitle="در این فرم می توانید اطلاعات کاربر را ویرایش کنید.">
                <template #actions>
                </template>
            </AppPageTitle>


            <div class="grid w-full max-w-sm items-center gap-1.5 mt-6">
                <div>
                    <Label for="first_name" class="mb-2">نام*</Label>
                    <Input
                        v-model="user.first_name"
                        id="first_name"
                        placeholder="نام کاربر را وارد کنید"/>
                    <InputError :message="user.errors.first_name"/>
                </div>

                <div class="mt-4">
                    <Label for="last_name" class="mb-2">نام خانوادگی*</Label>
                    <Input
                        v-model="user.last_name"
                        id="last_name"
                        placeholder="نام خانوادگی کاربر را وارد کنید"/>
                    <InputError :message="user.errors.last_name"/>
                </div>

                <div class="mt-4">
                    <Label for="email" class="mb-2">ایمیل*</Label>
                    <Input
                        v-model="user.email"
                        class="text-left"
                        id="email"
                        placeholder="ایمیل کاربر را وارد کنید"/>
                    <InputError :message="user.errors.email"/>
                </div>


                <div class="mt-4">
                    <Label for="password" class="mb-2">رمز عبور</Label>
                    <Input
                        v-model="user.password"
                        class="text-left"
                        id="password"
                        placeholder="رمز عبور حداقل باید 6 کارکتر باشد."/>
                    <InputError :message="user.errors.password"/>
                </div>


                <div class="mt-4">
                    <Label for="phone_number" class="mb-2">شماره موبایل</Label>
                    <Input
                        v-model="user.phone_number"
                        class="text-left"
                        id="phone_number"
                        placeholder="مثال : 09123456789"/>
                    <InputError :message="user.errors.phone_number"/>
                </div>

                <div class="mt-4 flex items-center justify-start">
                    <Switch v-model="user.status" id="status"/>
                    <Label for="status" class="mr-2">
                        <span class="ml-1">وضعیت کاربر</span>
                        <Badge v-if="user.status" variant="secondary">
                            فعال
                        </Badge>
                        <Badge v-else variant="destructive">
                            غیرفعال
                        </Badge>
                    </Label>
                </div>

                <div class="mt-4 flex items-center justify-start">
                    <Switch v-model="user.is_admin" id="is_admin"/>
                    <Label for="is_admin" class="mr-2">
                        <span>  به عنوان کاربر ادمین</span>
                    </Label>
                </div>


                <Button
                    class="mt-4"
                    @click="onSubmit"
                    :loading="user.processing">
                    ذخیره
                </Button>

            </div>


        </div>
    </AppLayout>
</template>
