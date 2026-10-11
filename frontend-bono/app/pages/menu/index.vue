<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'
import type { MenuCategory, MenuItem } from '~/types/domain'

definePageMeta({ permissions: [PERMISSIONS.menuView, PERMISSIONS.menuManage] })

const api = useRestaurantApi()
const auth = useAuthStore()
const toast = useToast()

const canManage = computed(() => auth.can(PERMISSIONS.menuManage))
const canRecipes = computed(() => auth.can(PERMISSIONS.recipesManage) || canManage.value)

const categories = ref<MenuCategory[]>([])
const items = ref<MenuItem[]>([])
const inventory = ref<any[]>([])
const loading = ref(true)

const showCategory = ref(false)
const editingCategory = ref<MenuCategory | null>(null)
const categoryForm = reactive({ name: '', description: '', sort_order: '0', active: true })

const showItem = ref(false)
const editingItem = ref<MenuItem | null>(null)
const itemForm = reactive({
  category_id: '',
  name: '',
  description: '',
  base_price: '',
  available: true,
  active: true,
  requires_kitchen: false,
})

const recipes = ref<any[]>([])
const recipeForm = reactive({
  inventory_item_id: '',
  quantity_required: '1',
  unit: 'piece',
})

async function load() {
  loading.value = true
  try {
    const requests: Promise<any>[] = [
      api.categories({ include_inactive: canManage.value ? 1 : undefined }),
      api.items({ per_page: 100 }),
    ]
    if (canRecipes.value) requests.push(api.inventory({ per_page: 100 }))

    const [cats, itemPage, inv] = await Promise.all(requests)
    categories.value = (cats as any).data ?? []
    items.value = (itemPage as any).data ?? []
    if (inv) inventory.value = (inv as any).data ?? []
    if (!itemForm.category_id && categories.value[0]) {
      itemForm.category_id = String(categories.value[0].id)
    }
  }
  catch (error: any) {
    toast.error(error.message)
  }
  finally {
    loading.value = false
  }
}

function openNewCategory() {
  editingCategory.value = null
  Object.assign(categoryForm, { name: '', description: '', sort_order: '0', active: true })
  showCategory.value = true
}

function openEditCategory(row: MenuCategory) {
  editingCategory.value = row
  Object.assign(categoryForm, {
    name: row.name,
    description: row.description ?? '',
    sort_order: String(row.sort_order ?? 0),
    active: row.active,
  })
  showCategory.value = true
}

async function saveCategory() {
  try {
    const payload = {
      name: categoryForm.name,
      description: categoryForm.description || null,
      sort_order: Number(categoryForm.sort_order) || 0,
      active: categoryForm.active,
    }
    if (editingCategory.value) await api.updateCategory(editingCategory.value.id, payload)
    else await api.createCategory(payload)
    toast.success(editingCategory.value ? 'Category updated' : 'Category added')
    showCategory.value = false
    await load()
  }
  catch (error: any) {
    toast.error(error.message)
  }
}

function openNewItem() {
  editingItem.value = null
  recipes.value = []
  Object.assign(itemForm, {
    category_id: categories.value[0] ? String(categories.value[0].id) : '',
    name: '',
    description: '',
    base_price: '',
    available: true,
    active: true,
    requires_kitchen: false,
  })
  showItem.value = true
}

async function openEditItem(row: MenuItem) {
  editingItem.value = row
  Object.assign(itemForm, {
    category_id: String(row.category_id),
    name: row.name,
    description: row.description ?? '',
    base_price: String(row.base_price),
    available: row.available,
    active: row.active,
    requires_kitchen: row.requires_kitchen,
  })
  recipes.value = []
  showItem.value = true
  if (canRecipes.value) {
    try {
      recipes.value = (await api.itemRecipes(row.id)).data
    }
    catch {
      recipes.value = []
    }
  }
}

async function saveItem() {
  try {
    const payload = {
      category_id: Number(itemForm.category_id),
      name: itemForm.name,
      description: itemForm.description || null,
      base_price: itemForm.base_price,
      available: itemForm.available,
      active: itemForm.active,
      requires_kitchen: itemForm.requires_kitchen,
    }
    if (editingItem.value) {
      await api.updateItem(editingItem.value.id, payload)
      toast.success('Menu item updated')
    }
    else {
      const created = await api.createItem(payload) as any
      editingItem.value = created.data
      toast.success('Menu item added')
    }
    await load()
    if (editingItem.value && canRecipes.value) {
      recipes.value = (await api.itemRecipes(editingItem.value.id)).data
    }
    else {
      showItem.value = false
    }
  }
  catch (error: any) {
    toast.error(error.message)
  }
}

async function saveRecipeLine() {
  if (!editingItem.value) {
    toast.error('Save the menu item first, then add a recipe.')
    return
  }
  try {
    await api.saveRecipe({
      menu_item_id: editingItem.value.id,
      inventory_item_id: Number(recipeForm.inventory_item_id),
      quantity_required: recipeForm.quantity_required,
      unit: recipeForm.unit,
    })
    toast.success('Recipe line saved')
    recipes.value = (await api.itemRecipes(editingItem.value.id)).data
    Object.assign(recipeForm, { inventory_item_id: '', quantity_required: '1', unit: 'piece' })
  }
  catch (error: any) {
    toast.error(error.message)
  }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader title="Menu" description="Add and edit categories, item names, prices, and recipes for bono redemption.">
      <template v-if="canManage" #actions>
        <button type="button" class="btn-secondary" @click="openNewCategory">Add category</button>
        <button type="button" class="btn-primary" @click="openNewItem">Add item</button>
      </template>
    </PageHeader>

    <div class="mb-4 grid gap-4 lg:grid-cols-3">
      <div class="card p-4 lg:col-span-1">
        <h2 class="font-semibold">Categories</h2>
        <ul class="mt-3 space-y-2 text-sm">
          <li v-for="cat in categories" :key="cat.id" class="flex items-center justify-between gap-2 border-b border-slate-100 pb-2 last:border-0">
            <span>{{ cat.name }}</span>
            <button v-if="canManage" type="button" class="text-brand-700" @click="openEditCategory(cat)">Edit</button>
          </li>
          <li v-if="!categories.length" class="text-slate-500">No categories yet.</li>
        </ul>
      </div>

      <div class="card lg:col-span-2">
        <AppTable
          :columns="[
            { key: 'name', label: 'Item' },
            { key: 'category', label: 'Category' },
            { key: 'base_price', label: 'Price', align: 'right' },
            { key: 'available', label: 'Available' },
            { key: 'actions', label: '' },
          ]"
          :rows="items"
          :loading="loading"
        >
          <template #cell:category="{ row }">{{ row.category?.name }}</template>
          <template #cell:base_price="{ row }">{{ formatAmount(row.base_price) }}</template>
          <template #cell:available="{ row }">
            <StatusBadge :status="row.available ? 'available' : 'inactive'" />
          </template>
          <template #cell:actions="{ row }">
            <button v-if="canManage" type="button" class="btn-ghost text-sm" @click="openEditItem(row)">Edit</button>
          </template>
        </AppTable>
      </div>
    </div>

    <AppModal v-model="showCategory" :title="editingCategory ? 'Edit category' : 'New category'">
      <form class="space-y-3" @submit.prevent="saveCategory">
        <FormField label="Name" required>
          <input v-model="categoryForm.name" class="input" required>
        </FormField>
        <FormField label="Description">
          <input v-model="categoryForm.description" class="input">
        </FormField>
        <FormField label="Sort order">
          <input v-model="categoryForm.sort_order" class="input" type="number" min="0">
        </FormField>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="categoryForm.active" type="checkbox">
          Active
        </label>
        <button type="submit" class="btn-primary">Save category</button>
      </form>
    </AppModal>

    <AppModal v-model="showItem" :title="editingItem ? 'Edit menu item' : 'New menu item'" size="lg">
      <form class="space-y-3" @submit.prevent="saveItem">
        <FormField label="Category" required>
          <select v-model="itemForm.category_id" class="input" required>
            <option disabled value="">Select category</option>
            <option v-for="cat in categories" :key="cat.id" :value="String(cat.id)">{{ cat.name }}</option>
          </select>
        </FormField>
        <FormField label="Name" required>
          <input v-model="itemForm.name" class="input" required placeholder="e.g. St. George">
        </FormField>
        <FormField label="Price" required>
          <input v-model="itemForm.base_price" class="input" type="number" min="0" step="0.01" required>
        </FormField>
        <FormField label="Description">
          <input v-model="itemForm.description" class="input">
        </FormField>
        <div class="flex flex-wrap gap-4 text-sm">
          <label class="flex items-center gap-2"><input v-model="itemForm.available" type="checkbox"> Available</label>
          <label class="flex items-center gap-2"><input v-model="itemForm.active" type="checkbox"> Active</label>
          <label class="flex items-center gap-2"><input v-model="itemForm.requires_kitchen" type="checkbox"> Needs kitchen</label>
        </div>
        <button type="submit" class="btn-primary">{{ editingItem ? 'Update item' : 'Save item' }}</button>
      </form>

      <div v-if="editingItem && canRecipes" class="mt-6 border-t border-slate-200 pt-4">
        <h3 class="font-semibold">Recipe</h3>
        <p class="mt-1 text-sm text-slate-600">Link stock ingredients consumed when this item is sold.</p>
        <ul class="mt-3 space-y-1 text-sm">
          <li v-for="line in recipes" :key="line.id">
            {{ line.inventory_item?.name ?? line.inventory_item_id }} · {{ line.quantity_required }} {{ line.unit }}
          </li>
          <li v-if="!recipes.length" class="text-slate-500">No recipe lines yet.</li>
        </ul>
        <form class="mt-3 grid gap-2 sm:grid-cols-4" @submit.prevent="saveRecipeLine">
          <select v-model="recipeForm.inventory_item_id" class="input sm:col-span-2" required>
            <option disabled value="">Inventory item</option>
            <option v-for="row in inventory" :key="row.id" :value="String(row.id)">{{ row.name }}</option>
          </select>
          <input v-model="recipeForm.quantity_required" class="input" type="number" min="0.001" step="0.001" required>
          <select v-model="recipeForm.unit" class="input">
            <option v-for="u in ['kg', 'gram', 'liter', 'ml', 'piece', 'pack']" :key="u" :value="u">{{ u }}</option>
          </select>
          <button type="submit" class="btn-secondary sm:col-span-4">Add / update recipe line</button>
        </form>
      </div>
    </AppModal>
  </div>
</template>
