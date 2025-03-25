<script setup lang="ts">
import {Button} from '@/components/ui/button'
import {defineProps} from "vue";
import {
    Pagination,
    PaginationEllipsis,
    PaginationFirst,
    PaginationLast,
    PaginationList,
    PaginationListItem,
    PaginationNext,
    PaginationPrev,
} from '@/components/ui/pagination'
import {router} from "@inertiajs/vue3";

const {meta} = defineProps(['meta']);


function handlePageChange(newPage: number) {
    console.log({newPage})
    router.get(meta.path, { page: newPage }, { preserveState: true, replace: true })
}


</script>

<template>
    <div class="flex items-center justify-center">
        <Pagination
            v-slot="{ page }"
            :items-per-page="meta.per_page"
            :total="meta.total"
            :sibling-count="1"
            show-edges
            :default-page="meta.current_page"
            @update:page="handlePageChange"
        >
            <PaginationList v-slot="{ items }" class="flex items-center gap-1">
                <PaginationFirst/>
                <PaginationPrev/>

                <template v-for="(item, index) in items">
                    <PaginationListItem v-if="item.type === 'page'" :key="index" :value="item.value" as-child>
                        <Button class="w-10 h-10 p-0" :variant="item.value === page ? 'default' : 'outline'">
                            {{ item.value }}
                        </Button>
                    </PaginationListItem>
                    <PaginationEllipsis v-else :key="item.type" :index="index"/>
                </template>

                <PaginationNext/>
                <PaginationLast/>
            </PaginationList>
        </Pagination>
    </div>
    <!--    <Pagination-->
    <!--        v-slot="{ page }"-->
    <!--        :items-per-page="pageProps.items.per_page"-->
    <!--        :total="totalItems"-->
    <!--        :sibling-count="1"-->
    <!--        show-edges-->
    <!--        :default-page="currentPage"-->
    <!--        @change="handlePageChange"-->
    <!--    >-->
    <!--        <PaginationList v-slot="{ items }" class="flex items-center gap-1">-->
    <!--            <PaginationFirst />-->
    <!--            <PaginationPrev />-->

    <!--            <template v-for="(item, index) in items">-->
    <!--                <PaginationListItem v-if="item.type === 'page'" :key="index" :value="item.value" as-child>-->
    <!--                    <Button class="w-10 h-10 p-0" :variant="item.value === page ? 'default' : 'outline'">-->
    <!--                        {{ item.value }}-->
    <!--                    </Button>-->
    <!--                </PaginationListItem>-->
    <!--                <PaginationEllipsis v-else :key="item.type" :index="index" />-->
    <!--            </template>-->

    <!--            <PaginationNext />-->
    <!--            <PaginationLast />-->
    <!--        </PaginationList>-->
    <!--    </Pagination>-->
</template>
