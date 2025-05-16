<script setup lang="ts">
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
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
    </div>
</template>
