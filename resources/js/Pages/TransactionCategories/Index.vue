<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, InfiniteScroll, router, useForm } from '@inertiajs/vue3';
import { Check, Pencil, Plus, Search, Trash2, X } from '@lucide/vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';

type CategoryType = 'income' | 'expense';

interface Category {
    id: number;
    name: string;
    type: CategoryType;
    is_default: boolean;
    transactions_count: number;
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Props {
    categories: Paginated<Category>;
    filters: { type: CategoryType; search: string };
}

const props = defineProps<Props>();

const tabs: Array<{ type: CategoryType; label: string }> = [
    { type: 'income', label: 'Pemasukan' },
    { type: 'expense', label: 'Pengeluaran' },
];

const activeType = ref<CategoryType>(props.filters.type);
const search = ref<string>(props.filters.search);

const reload = (): void => {
    router.get(route('transaction-categories.index'), {
        type: activeType.value,
        search: search.value.trim() || undefined,
    }, {
        only: ['categories', 'filters'],
        reset: ['categories'],
        preserveState: true,
        replace: true,
    });
};

const selectTab = (type: CategoryType): void => {
    if (type === activeType.value) {
        return;
    }
    activeType.value = type;
    reload();
};

let searchTimer: ReturnType<typeof setTimeout> | undefined;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(reload, 300);
});

const addForm = useForm({ name: '' });

const submitAdd = (): void => {
    addForm
        .transform((data) => ({ ...data, type: activeType.value }))
        .post(route('transaction-categories.store'), {
            preserveScroll: true,
            onSuccess: () => addForm.reset(),
        });
};

const editingId = ref<number | null>(null);
const editName = ref('');
const editError = ref('');
const savingEdit = ref(false);

const startEdit = (category: Category): void => {
    editingId.value = category.id;
    editName.value = category.name;
    editError.value = '';
};

const cancelEdit = (): void => {
    editingId.value = null;
    editError.value = '';
};

const saveEdit = (category: Category): void => {
    savingEdit.value = true;
    router.put(route('transaction-categories.update', category.id), {
        name: editName.value,
        type: category.type,
    }, {
        preserveScroll: true,
        onSuccess: () => cancelEdit(),
        onError: (errors) => { editError.value = errors.name ?? 'Gagal menyimpan.'; },
        onFinish: () => { savingEdit.value = false; },
    });
};

const pendingDelete = ref<Category | null>(null);
const deleting = ref(false);

const confirmDelete = (): void => {
    if (pendingDelete.value === null) {
        return;
    }
    deleting.value = true;
    router.delete(route('transaction-categories.destroy', pendingDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => { pendingDelete.value = null; },
        onFinish: () => { deleting.value = false; },
    });
};

const isEmpty = computed((): boolean => props.categories.data.length === 0);
</script>

<template>
    <Head title="Kelola Kategori" />

    <PrototypeLayout>
        <section class="pb-3 pt-4">
            <h1 class="text-xl font-bold tracking-tight">Kelola Kategori</h1>
            <p class="mt-1 text-xs text-slate-500">
                Kategori bawaan tidak bisa diubah. Tambahkan kategori sendiri sesuai kebutuhan usaha.
            </p>
        </section>

        <div class="flex gap-1 rounded-xl bg-slate-100 p-1" role="tablist" aria-label="Jenis kategori">
            <button
                v-for="tab in tabs"
                :key="tab.type"
                type="button"
                role="tab"
                :aria-selected="activeType === tab.type"
                :class="[
                    'flex-1 rounded-lg px-3 py-1.5 text-xs font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500',
                    activeType === tab.type ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700',
                ]"
                @click="selectTab(tab.type)"
            >
                {{ tab.label }}
            </button>
        </div>

        <div class="relative mt-3">
            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
            <input
                v-model="search"
                type="search"
                placeholder="Cari kategori"
                class="block w-full rounded-lg border-slate-300 pl-9 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                aria-label="Cari kategori"
            />
        </div>

        <form class="mt-3 flex items-start gap-2" @submit.prevent="submitAdd">
            <div class="min-w-0 flex-1">
                <input
                    v-model="addForm.name"
                    type="text"
                    :placeholder="`Tambah kategori ${activeType === 'income' ? 'pemasukan' : 'pengeluaran'}`"
                    class="block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                <p v-if="addForm.errors.name" class="mt-1.5 text-xs font-semibold text-rose-600">{{ addForm.errors.name }}</p>
            </div>
            <button
                type="submit"
                :disabled="addForm.processing || addForm.name.trim() === ''"
                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-indigo-600 text-white transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-40"
                aria-label="Tambah kategori"
            >
                <Plus class="size-4" />
            </button>
        </form>

        <p class="mt-3 text-[11px] text-slate-400">Jumlah transaksi per kategori menyusul modul transaksi.</p>

        <div class="mt-1 rounded-2xl border border-slate-200 bg-white">
            <InfiniteScroll data="categories" as="div" class="divide-y divide-slate-100">
                <div
                    v-for="category in props.categories.data"
                    :key="category.id"
                    class="flex items-center gap-2 px-4 py-3"
                >
                    <template v-if="editingId === category.id">
                        <input
                            v-model="editName"
                            type="text"
                            class="min-w-0 flex-1 rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            @keyup.enter="saveEdit(category)"
                            @keyup.esc="cancelEdit"
                        />
                        <button
                            type="button"
                            :disabled="savingEdit || editName.trim() === ''"
                            class="flex size-8 items-center justify-center rounded-lg bg-indigo-600 text-white transition hover:bg-indigo-700 disabled:opacity-40"
                            aria-label="Simpan"
                            @click="saveEdit(category)"
                        >
                            <Check class="size-4" />
                        </button>
                        <button
                            type="button"
                            class="flex size-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:bg-slate-50"
                            aria-label="Batal"
                            @click="cancelEdit"
                        >
                            <X class="size-4" />
                        </button>
                    </template>

                    <template v-else>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm text-slate-800">{{ category.name }}</span>
                            <span class="mt-0.5 block text-[10px] text-slate-400">{{ category.transactions_count }} transaksi</span>
                        </span>
                        <span
                            v-if="category.is_default"
                            class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500"
                        >
                            Bawaan
                        </span>
                        <template v-else>
                            <button
                                type="button"
                                class="flex size-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:bg-indigo-50 hover:text-indigo-700"
                                aria-label="Ubah"
                                @click="startEdit(category)"
                            >
                                <Pencil class="size-3.5" />
                            </button>
                            <button
                                type="button"
                                class="flex size-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:bg-rose-50 hover:text-rose-600"
                                aria-label="Hapus"
                                @click="pendingDelete = category"
                            >
                                <Trash2 class="size-3.5" />
                            </button>
                        </template>
                    </template>
                </div>

                <template #next="{ loading }">
                    <p v-if="loading" class="py-3 text-center text-xs text-slate-400">Memuat…</p>
                </template>
            </InfiniteScroll>

            <p v-if="isEmpty" class="px-4 py-8 text-center text-xs text-slate-400">
                {{ search.trim() ? 'Tidak ada kategori yang cocok.' : 'Belum ada kategori.' }}
            </p>
        </div>

        <p v-if="editError" class="mt-1.5 px-1 text-xs font-semibold text-rose-600">{{ editError }}</p>

        <div
            v-if="pendingDelete"
            class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-4 sm:items-center"
            role="presentation"
            @click.self="pendingDelete = null"
        >
            <section
                class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl"
                role="dialog"
                aria-modal="true"
                aria-labelledby="delete-category-title"
            >
                <h2 id="delete-category-title" class="text-sm font-bold text-slate-900">Hapus kategori?</h2>
                <p class="mt-1.5 text-xs text-slate-500">
                    Kategori <strong class="text-slate-700">{{ pendingDelete.name }}</strong> akan dihapus.
                </p>
                <div class="mt-4 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                        @click="pendingDelete = null"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        :disabled="deleting"
                        class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-rose-700 disabled:opacity-40"
                        @click="confirmDelete"
                    >
                        Hapus
                    </button>
                </div>
            </section>
        </div>
    </PrototypeLayout>
</template>
