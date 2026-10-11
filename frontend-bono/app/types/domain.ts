export interface RestaurantTable {
  id: number
  branch_id: number
  name: string
  capacity: number
  status: 'available' | 'occupied' | 'reserved' | 'cleaning'
  active: boolean
  assigned_waiter_id: number | null
  current_order_id: number | null
  waiter?: { id: number; name: string } | null
  current_order?: { id: number; order_number: string; status: string; total: string } | null
}

export interface MenuCategory {
  id: number
  name: string
  description: string | null
  sort_order: number
  active: boolean
}

export interface MenuVariant {
  id: number
  name: string
  price: string
  active: boolean
}

export interface MenuModifier {
  id: number
  name: string
  price: string
  active: boolean
}

export interface MenuItem {
  id: number
  category_id: number
  name: string
  description: string | null
  image: string | null
  base_price: string
  active: boolean
  available: boolean
  requires_kitchen: boolean
  preparation_time_minutes: number | null
  category?: { id: number; name: string }
  variants?: MenuVariant[]
  modifiers?: MenuModifier[]
}

export interface OrderItemModifier {
  id: number
  modifier_id: number | null
  name: string
  price: string
}

export interface OrderItem {
  id: number
  order_id: number
  menu_item_id: number
  menu_item_variant_id: number | null
  item_name_snapshot: string
  price_snapshot: string
  quantity: number
  subtotal: string
  notes: string | null
  kitchen_status: 'new' | 'preparing' | 'ready'
  submitted_to_kitchen: boolean
  modifiers?: OrderItemModifier[]
}

export interface Payment {
  id: number
  order_id: number
  amount: string
  payment_method: string
  status: string
  reference: string | null
  notes: string | null
  paid_at: string | null
  cashier?: { id: number; name: string }
}

export interface Order {
  id: number
  order_number: string
  table_id: number | null
  waiter_id: number
  type: 'dine_in' | 'takeaway'
  status: string
  notes: string | null
  opened_at: string | null
  created_at: string
  subtotal?: string
  discount?: string
  tax?: string
  tax_rate?: string
  total?: string
  amount_paid?: string
  balance_due?: string
  table?: { id: number; name: string } | null
  waiter?: { id: number; name: string }
  customer?: { id: number; name: string; phone: string | null } | null
  items?: OrderItem[]
  payments?: Payment[]
}
