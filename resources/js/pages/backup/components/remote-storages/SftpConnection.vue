<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea'
import { defineModel } from 'vue';

const model = defineModel();
</script>

<template>
    <div>
        <div class="flex items-center">
            <Switch id="local-storage" v-model="model.enabled" />
            <Label for="local-storage" v-if="model.enabled" class="mr-2">فعال</Label>
            <Label for="local-storage" v-else class="mr-2">غیرفعال</Label>
        </div>
        <div class="mt-2" v-if="model.enabled">
            <Input placeholder="هاست (Host)" class="mb-2 h-9 text-left" v-model="model.config.host" />
            <Input placeholder="پورت (Port)" class="mb-2 h-9 text-left" v-model="model.config.port" />
            <Input placeholder="کاربر (Username)" class="mb-2 h-9 text-left" v-model="model.config.username" />
            <Select v-model="model.config.auth_by" >
                <SelectTrigger>
                    <SelectValue placeholder="نوع احرازهویت" />
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup>
                        <SelectItem value="ssh"> احرازهویت با کلید SSH</SelectItem>
                        <SelectItem value="password"> احرازهویت با رمزعبور</SelectItem>
                    </SelectGroup>
                </SelectContent>
            </Select>
            <Input
                type="password"
                v-if="model.config.auth_by === 'password'"
                placeholder="رمزعبور (Password)"
                class="h-9 mt-2 text-left"
                v-model="model.config.password"
            />
            <Textarea
                v-else
                placeholder="کلید ssh  را وارد کنید"
                class="mt-2 text-left"
                v-model="model.config.ssh_key"
            />
        </div>
    </div>
</template>
