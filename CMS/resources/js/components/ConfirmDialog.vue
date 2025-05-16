<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { defineModel, defineProps } from 'vue';

const model = defineModel();
const emit = defineEmits(['onConfirm']);
const props = defineProps({
    label: {
        type: String,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    actionLabel:{
        type:String,
        default:'بله مطمئنم'
    },
    actionVariant:{
        type:String,
        default:'default'
    }
});
</script>

<template>
    <Dialog :open="model">
        <DialogContent @close="model = false">
            <div class="space-y-6">
                <DialogHeader class="space-y-3">
                    <DialogTitle>
                        {{ label }}
                    </DialogTitle>
                </DialogHeader>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" @click="model = false"> لغو</Button>
                    </DialogClose>

                    <Button variant="default" @click="emit('onConfirm')" :disabled="loading" :loading="loading">
                      {{ actionLabel}}
                    </Button>
                </DialogFooter>
            </div>
        </DialogContent>
    </Dialog>
</template>
