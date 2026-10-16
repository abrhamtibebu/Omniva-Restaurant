<?php

return [

    'auth' => [
        'credentials' => 'These credentials do not match our records.',
        'inactive' => 'This account is inactive.',
        'inactive_contact' => 'This account is inactive. Contact a manager.',
        'signed_out' => 'Signed out.',
        'password_updated' => 'Password updated.',
        'branch_inactive' => 'That branch is inactive.',
        'not_allowed' => 'You are not allowed to do that.',
        'staff_deactivated' => 'Staff account deactivated.',
    ],

    'telegram' => [
        'bot_not_configured' => 'Telegram bot is not configured.',
        'invalid_login_data' => 'Invalid Telegram login data.',
        'invalid_signature' => 'Telegram login signature is invalid.',
        'login_expired' => 'Telegram login data has expired. Re-open the Mini App.',
        'user_missing' => 'Telegram user is missing from login data.',
        'not_linked' => 'This Telegram account is not linked. Sign in once to link it.',
        'already_linked' => 'That Telegram account is already linked to another staff user.',
        'linked' => 'Telegram account linked.',
        'unlinked' => 'Telegram account unlinked.',
    ],

    'bono' => [
        'entitlement_inactive' => 'Bono control is not active for this restaurant.',
        'approve_admin_only' => 'Only an admin or owner can approve cashier bono requests.',
        'cannot_approve' => 'You cannot approve this bono request.',
        'approve_cashier_or_admin' => 'Only a cashier, admin, or owner can approve this request.',
        'cannot_return_to_self' => 'You cannot return bonos to yourself.',
        'return_to_cashier' => 'Unused bonos must be returned to a cashier.',
        'cashier_not_on_branch' => 'That cashier is not on this branch.',
        'approved_exceeds_request' => 'Approved quantity cannot exceed the request.',
        'issued_exceeds_approved' => 'Issued quantity cannot exceed the approved quantity.',
        'receiver_must_confirm' => 'Only the receiving person can confirm this handover.',
        'received_exceeds_issued' => 'Received quantity cannot exceed the issued quantity.',
        'handover_locked' => 'This handover is locked and cannot be changed.',
        'handover_invalid_step' => 'This handover cannot take that step from :status.',
        'type_not_at_branch' => 'That bono type is not available at this branch.',
        'type_not_available' => 'That bono type is not available.',
        'choose_menu_item' => 'Choose which menu item this bono redeems.',
        'menu_item_not_covered' => 'That menu item is not covered by this bono type.',
        'quantity_gt_zero' => 'Quantity must be greater than zero.',
        'balance_too_low' => 'Not enough :name to issue (need :needed, you have :available on hand, central has :central).',
        'menu_items_branch' => 'Every menu item must belong to this branch.',
        'opening_balance_recorded' => 'Opening balance recorded.',
    ],

    'stock' => [
        'approved_exceeds_request' => 'Approved quantity cannot exceed the request.',
        'issued_exceeds_approved' => 'Issued quantity cannot exceed the approved quantity.',
        'insufficient' => 'Store does not have enough :name.',
        'receiver_must_confirm' => 'Only the receiving department can confirm this stock.',
        'received_exceeds_issued' => 'Received quantity cannot exceed the issued quantity.',
        'request_locked' => 'This stock request is locked and cannot be changed.',
        'request_invalid_step' => 'This stock request cannot take that step from :status.',
        'item_not_at_branch' => 'That stock item is not at this branch.',
        'waste_recorded' => 'Waste recorded.',
        'kitchen_sales_recorded' => 'Kitchen sales summary recorded. It is not added to POS revenue.',
    ],

    'reconciliation' => [
        'shift_in_progress' => 'This shift already has a reconciliation in progress.',
        'variance_flag_instead' => 'This close has a variance. Flag it instead of accepting it.',
        'already_closed' => 'This reconciliation is already closed.',
        'discrepancy_only_close' => 'Only a discrepancy review can be closed this way.',
        'resolve_discrepancies' => 'Resolve the open discrepancies before closing.',
        'discrepancy_already_resolved' => 'This discrepancy is already resolved.',
        'no_longer_editable' => 'This reconciliation can no longer be edited.',
    ],

    'purchase' => [
        'already_received' => 'This purchase has already been received.',
        'not_approved' => 'This purchase has not been approved.',
        'accepted_exceeds_ordered' => 'Accepted quantity cannot exceed the ordered quantity.',
        'cannot_reapprove_received' => 'A received purchase cannot be re-approved.',
    ],

    'order' => [
        'cannot_transition' => 'Cannot move an order from :from to :to.',
        'table_has_open' => 'This table already has an open order.',
        'dine_in_needs_table' => 'A dine-in order needs a table.',
        'cannot_cancel' => 'This order can no longer be cancelled.',
        'cannot_change' => 'This order can no longer be changed.',
        'cannot_submit' => 'This order can no longer be submitted.',
        'add_item_before_kitchen' => 'Add at least one item before sending to the kitchen.',
        'serve_before_complete' => 'Serve the order before completing it.',
        'cancelled_cannot_complete' => 'A cancelled order cannot be completed.',
        'outstanding_balance' => 'The order still has an outstanding balance.',
        'only_ready_served' => 'Only ready orders can be marked served.',
        'item_not_on_order' => 'Item does not belong to this order.',
        'quantity_at_least_one' => 'Quantity must be at least 1. Remove the item instead.',
        'menu_item_unavailable' => 'That menu item is not available.',
        'menu_item_wrong_branch' => 'That menu item does not belong to this branch.',
        'variant_unavailable' => 'That variant is not available.',
        'addon_invalid' => 'An add-on is not valid for this item.',
        'discount_closed' => 'Discounts cannot be applied to a closed order.',
        'discount_negative' => 'Discount cannot be negative.',
        'discount_exceeds_subtotal' => 'Discount cannot exceed the subtotal.',
        'item_removed' => 'Item removed.',
        'table_open_order_delete' => 'Cannot delete a table with an open order.',
        'table_deleted' => 'Table deleted.',
        'category_deactivated' => 'Category deactivated.',
    ],

    'payment' => [
        'cannot_record' => 'Payments cannot be recorded against this order.',
        'amount_gt_zero' => 'Payment amount must be greater than zero.',
        'exceeds_balance' => 'Payment exceeds the remaining balance of :balance.',
        'only_completed_reverse' => 'Only a completed payment can be reversed.',
    ],

    'kitchen' => [
        'not_sent' => 'This item has not been sent to the kitchen yet.',
        'cannot_transition' => 'Cannot move kitchen status from :from to :to.',
    ],

    'operations' => [
        'expense_deleted' => 'Expense deleted.',
        'shift_already_open' => 'You already have an open shift.',
        'no_open_shift' => 'No open shift to clock out.',
        'receipt_already_uploaded' => 'This receipt was already uploaded for this branch.',
        'ft_already_uploaded' => 'This FT / transaction reference was already uploaded for this branch.',
    ],

    'billing' => [
        'invoice_already_settled' => 'That invoice is already settled.',
    ],

    'common' => [
        // Reserved for shared / cross-cutting copy.
    ],

];
