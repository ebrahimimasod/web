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
        title: 'افزودن کاربر جدید',
        href: '/users/create',
    },
];


const user = useForm({
    first_name: null,
    last_name: null,
    email: null,
    password: null,
    phone_number: null,
    status: true,
    is_admin: false,
});

function onSubmit() {
    user.post(route('admin.users.store'), {
        onSuccess: () => user.reset()
    })
}


</script>

<template>
    <Head title="افزودن کاربر جدید"/>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">

            <AppPageTitle
                icon="userPlus"
                title="افزودن کاربر جدید"
                subtitle="با استفاده از این فرم یک کاربر جدید بسازید.">
                <template #actions>
                </template>
            </AppPageTitle>


            <div class="grid w-full max-w-sm items-center gap-1.5 mt-6">
                <div>
                    <Label for="first_name">نام*</Label>
                    <Input
                        v-model="user.first_name"
                        id="first_name"
                        placeholder="نام کاربر را وارد کنید"/>
                    <InputError :message="user.errors.first_name"/>
                </div>

                <div class="mt-4">
                    <Label for="last_name">نام خانوادگی*</Label>
                    <Input
                        v-model="user.last_name"
                        id="last_name"
                        placeholder="نام خانوادگی کاربر را وارد کنید"/>
                    <InputError :message="user.errors.last_name"/>
                </div>

                <div class="mt-4">
                    <Label for="email">ایمیل*</Label>
                    <Input
                        v-model="user.email"
                        class="text-left"
                        id="email"
                        placeholder="ایمیل کاربر را وارد کنید"/>
                    <InputError :message="user.errors.email"/>
                </div>


                <div class="mt-4">
                    <Label for="password">رمز عبور</Label>
                    <Input
                        v-model="user.password"
                        class="text-left"
                        id="password"
                        placeholder="رمز عبور حداقل باید 6 کارکتر باشد."/>
                    <InputError :message="user.errors.password"/>
                </div>


                <div class="mt-4">
                    <Label for="phone_number">شماره موبایل</Label>
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
                    <Switch v-model="user.is_admin" id="status"/>
                    <Label for="status" class="mr-2">
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
