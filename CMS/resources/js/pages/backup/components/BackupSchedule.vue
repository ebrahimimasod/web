<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { DialogFooter } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Switch } from '@/components/ui/switch';
import { useForm, usePage } from '@inertiajs/vue3';
import { AlertCircle } from 'lucide-vue-next';

const emit = defineEmits(['onClose']);
const { backup_schedule_setting } = usePage().props;
const scheduleOptions = [
    {
        label: 'هر 12 ساعت',
        value: '12_hours',
    },
    {
        label: 'روزانه',
        value: 'daily',
    },
    {
        label: 'هفتگی',
        value: 'weekly',
    },
    {
        label: 'هر دو هفته',
        value: 'fortnightly',
    },
    {
        label: 'هر ماه',
        value: 'monthly',
    },
];

const form = useForm({
    enabled: backup_schedule_setting?.enabled ?? false,
    schedule: backup_schedule_setting?.schedule ?? scheduleOptions[1]?.value,
});

function submit() {
    form.post(route('admin.backup.setting.schedule'), {
        onSuccess: () => {
            emit('onClose');
        },
    });
}
</script>

<template>
    <div>
        <div>
            <div class="flex items-center">
                <Switch v-model="form.enabled" id="backup_auto_schedule" />
                <Label v-if="form.enabled" for="backup_auto_schedule" class="mr-3"> پشتیبان‌گیری خودکار فعال است. </Label>
                <Label v-else for="backup_auto_schedule" class="mr-3"> پشتیبان‌گیری خودکار غیر فعال است. </Label>
            </div>

            <div v-if="form.enabled" class="mt-6">
                <RadioGroup v-model="form.schedule">
                    <div v-for="item in scheduleOptions" :key="item.value" class="mb-2 flex items-center">
                        <RadioGroupItem :id="item.value" :value="item.value" />
                        <Label :for="item.value" class="mr-2 cursor-pointer">
                            {{ item.label }}
                        </Label>
                    </div>
                </RadioGroup>
            </div>

            <Alert variant="warning" class="mt-4">
                <div class="mb-1 flex items-center justify-start">
                    <AlertCircle class="ml-2 h-4 w-4" />
                    <AlertTitle>توجه!</AlertTitle>
                </div>
                <AlertDescription>
                    <div class="text-[13px] font-bold">برای انجام پشتیبان‌گیری خودکار، حتما باید روی سرور خودتون کرون جاب زیر را تنظیم کنید :</div>
                    <code dir="ltr" class="mt-2 mx-auto block max-w-max  text-center text-white bg-gray-500 rounded-sm py-0.5 px-4"> php /home/user/web-builder/artisan schedule:run </code>
                    <div class="mt-2 text-[13px]">
                        برای کسب اطلاعات بیشتر در مورد تنظیم کرون جاب در سرور به این لینک مراجعه کنید.
                        <a href="#" class="text-blue-400 font-bold"> راهنمایی تنظیم کرون جاب </a>
                    </div>
                </AlertDescription>
            </Alert>
        </div>

        <DialogFooter class="mt-6">
            <Button variant="default" @click="submit" :loading="form.processing"> ذخیره کردن</Button>
        </DialogFooter>
    </div>
</template>
