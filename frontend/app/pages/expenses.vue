<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'
definePageMeta({ permissions: [PERMISSIONS.expensesView] })
const api = useRestaurantApi()
const auth = useAuthStore()
const toast = useToast()
const rows = ref<any[]>([])
const show = ref(false)
const form = reactive({ category: 'miscellaneous', description: '', amount: '', expense_date: formatDateForInput() })
async function load() { rows.value = (await api.expenses()).data }
async function create() {
  await api.createExpense(form)
  show.value = false
  toast.success('Expense recorded')
  await load()
}
onMounted(load)
</script>
<template>
  <div>
    <PageHeader title="Expenses">
      <template #actions>
        <button v-if="auth.can(PERMISSIONS.expensesManage)" class="btn-primary" type="button" @click="show = true">Add</button>
      </template>
    </PageHeader>
    <div class="card">
      <AppTable :rows="rows" :columns="[
        { key: 'expense_date', label: 'Date' },
        { key: 'category', label: 'Category' },
        { key: 'description', label: 'Description' },
        { key: 'amount', label: 'Amount', align: 'right' },
      ]">
        <template #cell:category="{ row }">{{ humanize(row.category) }}</template>
        <template #cell:amount="{ row }">{{ formatAmount(row.amount) }}</template>
      </AppTable>
    </div>
    <AppModal v-model="show" title="New expense">
      <form class="space-y-3" @submit.prevent="create">
        <select v-model="form.category" class="input">
          <option v-for="c in ['rent','utilities','salary','gas','transport','maintenance','cleaning','supplies','miscellaneous']" :key="c" :value="c">{{ humanize(c) }}</option>
        </select>
        <input v-model="form.description" class="input" placeholder="Description" required>
        <input v-model="form.amount" class="input" type="number" step="0.01" required>
        <input v-model="form.expense_date" class="input" type="date" required>
        <button class="btn-primary" type="submit">Save</button>
      </form>
    </AppModal>
  </div>
</template>
