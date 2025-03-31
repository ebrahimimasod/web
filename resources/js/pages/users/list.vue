<script setup lang="ts">
import {defineProps, reactive} from "vue";
import AppLayout from '@/layouts/AppLayout.vue';
import {type BreadcrumbItem} from '@/types';
import {Head, router, usePage} from '@inertiajs/vue3';
import {Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow} from '@/components/ui/table'
import ResourcePagination from "@/components/ResourcePagination.vue";
import {toPersianDate} from "@/lib/utils";
import {Badge} from '@/components/ui/badge'
import {Button} from '@/components/ui/button'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {Ellipsis, Eye, Trash, UserPlus, UserRoundPen} from "lucide-vue-next";
import SearchInput from "@/components/SearchInput.vue";
import DeleteResource from "@/components/DeleteResource.vue";
import AppPageTitle from "@/components/AppPageTitle.vue";


const deleteUser = reactive({
    dialog: false,
    url: null,
});

const {users, keyword} = defineProps(['users', 'keyword'])

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'پنل مدیریت',
        href: '/dashboard',
    },
    {
        title: 'کاربران',
        href: '/users',
    },
];

function searchUsers(keyword) {
    router.get(usePage().url, {page: 1, keyword}, {preserveState: true, replace: true})
}


function openDialogForDelete(id) {
    deleteUser.url = route('admin.users.destroy', {id: id});
    deleteUser.dialog = true;
}


</script>

<template>
    <Head title="مدیرت کاربران"/>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">

            <AppPageTitle
                icon="users"
                title="کاربران"
                subtitle="در این جدول می توانید لیست همه کاربران را ببینید و آنها را مدیریت کنید.">
                <template #actions>

                    <div class="flex items-center justify-end">
                        <SearchInput class="ml-2" @change="searchUsers" :value="keyword"/>

                        <Button variant="outline" @click="router.visit('/users/create')">
                            <UserPlus/>
                            افزودن کاربر جدید
                        </Button>

                    </div>

                </template>
            </AppPageTitle>

            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>نام</TableHead>
                        <TableHead>ایمیل</TableHead>
                        <TableHead>موبایل</TableHead>
                        <TableHead>وضعیت</TableHead>
                        <TableHead>تاریخ ثبت نام</TableHead>
                        <TableHead>عملیات</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="user in users.data">
                        <TableCell>{{ user.first_name }} {{ user.last_name }}</TableCell>
                        <TableCell>{{ user.email }}</TableCell>
                        <TableCell>{{ user.phone_number }}</TableCell>
                        <TableCell>

                            <Badge v-if="user.status" variant="secondary">
                                فعال
                            </Badge>
                            <Badge v-else variant="destructive">
                                غیرفعال
                            </Badge>

                            <Badge class="mr-1" v-if="user.is_admin" variant="default">
                                ادمین
                            </Badge>

                        </TableCell>
                        <TableCell>{{ toPersianDate(user.created_at) }}</TableCell>
                        <TableCell>
                            <DropdownMenu>
                                <DropdownMenuTrigger>
                                    <Button variant="ghost" size="icon">
                                        <Ellipsis class="w-4 h-4"/>
                                    </Button>

                                </DropdownMenuTrigger>
                                <DropdownMenuContent>
                                    <DropdownMenuItem @click="openDialogForDelete(user.id)" class="cursor-pointer">
                                        <Trash class="text-red-500"/>
                                        <span class="text-red-500">حذف</span>
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator/>
                                    <DropdownMenuItem @click="router.visit(route('admin.users.edit',{id:user.id}))" class="cursor-pointer">
                                        <UserRoundPen/>
                                        <span>ویرایش</span>
                                    </DropdownMenuItem >
                                    <DropdownMenuSeparator/>
                                    <DropdownMenuItem  @click="router.visit(route('admin.users.show',{id:user.id}))"  class="cursor-pointer">
                                        <Eye/>
                                        <span>نمایش</span>
                                    </DropdownMenuItem>

                                </DropdownMenuContent>
                            </DropdownMenu>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <TableEmpty v-if="!users.data.length"/>

            <ResourcePagination :meta="users.meta"/>

            <DeleteResource v-model="deleteUser.dialog" label="کاربر " :url="deleteUser.url"/>
        </div>
    </AppLayout>
</template>
