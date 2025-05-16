<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { DialogFooter } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const emit = defineEmits(['onClose']);
const { backup_file_setting, backup_storage_setting } = usePage().props;
const backupTypeOptions = [
    {
        label: 'همه (شامل فایل‌ها + دیتابیس)',
        value: 'all',
    },
    {
        label: 'فقط فایل ها',
        value: 'files',
    },
    {
        label: 'فقط دیتابیس',
        value: 'database',
    },
];
const form = useForm({
    storage: backup_file_setting?.storage ?? null,
    type: backup_file_setting?.type ?? null,
});

const isFormValid = computed(() => {
    return !!(form.storage && form.type);
});
const storageOptions = computed(() => {
    return [...(backup_storage_setting ?? [])]
        .filter((i) => i.enabled)
        .map((i) => {
            return {
                label: i.title,
                value: i.key,
            };
        });
});

function submit() {
    form.post(route('admin.backup.setting.file'), {
        onSuccess: () => {
            emit('onClose');
        },
    });
}

</script>

<template>
    <div>
        <div class="mb-6">
            <Label class="mb-2">انتخاب محل ذخیره</Label>
            <Select v-model="form.storage">
                <SelectTrigger>
                    <SelectValue placeholder="انتخاب محل ذخیره" />
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup>
                        <SelectItem v-for="item in storageOptions" :value="item.value" :key="item.value">
                            {{ item.label }}
                        </SelectItem>
                    </SelectGroup>
                </SelectContent>
            </Select>
        </div>

        <div>
            <Label class="mb-2">محتوای فایل پشتیبان</Label>
            <Select v-model="form.type">
                <SelectTrigger>
                    <SelectValue placeholder="محتوای فایل پشتیبان" />
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup>
                        <SelectItem v-for="item in backupTypeOptions" :value="item.value" :key="item.value">
                            {{ item.label }}
                        </SelectItem>
                    </SelectGroup>
                </SelectContent>
            </Select>
        </div>

        <DialogFooter class="mt-6">
            <Button variant="default" :disabled="!isFormValid" @click="submit" :loading="form.processing"> ذخیره کردن </Button>
        </DialogFooter>
    </div>
</template>
