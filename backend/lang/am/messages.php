<?php

return [

    'auth' => [
        'credentials' => 'እነዚህ መግቢያ መረጃዎች ከመዝገባችን ጋር አይዛመዱም።',
        'inactive' => 'ይህ መለያ እንቅስቃሴ የለውም።',
        'inactive_contact' => 'ይህ መለያ እንቅስቃሴ የለውም። አስተዳዳሪን ያነጋግሩ።',
        'signed_out' => 'ወጥተዋል።',
        'password_updated' => 'የይለፍ ቃል ተቀይሯል።',
        'branch_inactive' => 'ያ ቅርንጫፍ እንቅስቃሴ የለውም።',
        'not_allowed' => 'ይህን ለማድረግ ፈቃድ የለዎትም።',
        'staff_deactivated' => 'የሰራተኛ መለያ ቦዝኗል።',
    ],

    'telegram' => [
        'bot_not_configured' => 'ቴሌግራም ቦት አልተዋቀረም።',
        'invalid_login_data' => 'ልክ ያልሆነ የቴሌግራም መግቢያ መረጃ።',
        'invalid_signature' => 'የቴሌግራም መግቢያ ፊርማ ልክ አይደለም።',
        'login_expired' => 'የቴሌግራም መግቢያ መረጃ ጊዜው አልፏል። ሚኒ አፕን እንደገና ይክፈቱ።',
        'user_missing' => 'ከመግቢያ መረጃው ላይ የቴሌግራም ተጠቃሚ የለም።',
        'not_linked' => 'ይህ የቴሌግራም መለያ አልተገናኘም። አንድ ጊዜ በመግባት ያገናኙት።',
        'already_linked' => 'ያ የቴሌግራም መለያ ከሌላ ሰራተኛ ጋር ተገናኝቷል።',
        'linked' => 'የቴሌግራም መለያ ተገናኝቷል።',
        'unlinked' => 'የቴሌግራም መለያ ተቋርጧል።',
    ],

    'bono' => [
        'entitlement_inactive' => 'ለዚህ ሬስቶራንት ቦኖ ቁጥጥር አልተነቃም።',
        'approve_admin_only' => 'የካሽየር ቦኖ ጥያቄን ማጽደቅ የሚችሉት አድሚን ወይም ባለቤት ብቻ ናቸው።',
        'cannot_approve' => 'ይህን የቦኖ ጥያቄ ማጽደቅ አይችሉም።',
        'approve_cashier_or_admin' => 'ይህን ጥያቄ ማጽደቅ የሚችሉት ካሽየር፣ አድሚን ወይም ባለቤት ብቻ ናቸው።',
        'cannot_return_to_self' => 'ቦኖዎችን ወደ ራስዎ መመለስ አይችሉም።',
        'return_to_cashier' => 'ያልተጠቀሙ ቦኖዎች ወደ ካሽየር መመለስ አለባቸው።',
        'cashier_not_on_branch' => 'ያ ካሽየር በዚህ ቅርንጫፍ አይደለም።',
        'approved_exceeds_request' => 'የጸደቀው ብዛት ከጥያቄው መብለጥ አይችልም።',
        'issued_exceeds_approved' => 'የተሰጠው ብዛት ከጸደቀው መብለጥ አይችልም።',
        'receiver_must_confirm' => 'ይህን ማስረከብ ማረጋገጥ የሚችለው ተቀባዩ ብቻ ነው።',
        'received_exceeds_issued' => 'የተቀበለው ብዛት ከተሰጠው መብለጥ አይችልም።',
        'handover_locked' => 'ይህ ማስረከብ ተቆልፏል እና መቀየር አይቻልም።',
        'handover_invalid_step' => 'ይህ ማስረከብ ከ :status ሁኔታ ወደዚያ እርምጃ መሄድ አይችልም።',
        'type_not_at_branch' => 'ያ የቦኖ አይነት በዚህ ቅርንጫፍ አይገኝም።',
        'type_not_available' => 'ያ የቦኖ አይነት አይገኝም።',
        'choose_menu_item' => 'ይህ ቦኖ የሚቀይረውን የምናሌ ንጥል ይምረጡ።',
        'menu_item_not_covered' => 'ያ የምናሌ ንጥል በዚህ የቦኖ አይነት አይሸፈንም።',
        'quantity_gt_zero' => 'ብዛቱ ከዜሮ በላይ መሆን አለበት።',
        'balance_too_low' => 'ለመስጠት በቂ :name የለም (ያስፈልጋል :needed፣ በእጅዎ :available፣ ማዕከላዊ :central)።',
        'menu_items_branch' => 'እያንዳንዱ የምናሌ ንጥል የዚህ ቅርንጫፍ መሆን አለበት።',
        'opening_balance_recorded' => 'የመክፈቻ ቀሪ ሂሳብ ተመዝግቧል።',
    ],

    'stock' => [
        'approved_exceeds_request' => 'የጸደቀው ብዛት ከጥያቄው መብለጥ አይችልም።',
        'issued_exceeds_approved' => 'የተሰጠው ብዛት ከጸደቀው መብለጥ አይችልም።',
        'insufficient' => 'መደብሩ በቂ :name የለውም።',
        'receiver_must_confirm' => 'ይህን እቃ ማረጋገጥ የሚችለው ተቀባዩ ክፍል ብቻ ነው።',
        'received_exceeds_issued' => 'የተቀበለው ብዛት ከተሰጠው መብለጥ አይችልም።',
        'request_locked' => 'ይህ የእቃ ጥያቄ ተቆልፏል እና መቀየር አይቻልም።',
        'request_invalid_step' => 'ይህ የእቃ ጥያቄ ከ :status ሁኔታ ወደዚያ እርምጃ መሄድ አይችልም።',
        'item_not_at_branch' => 'ያ የእቃ ንጥል በዚህ ቅርንጫፍ አይደለም።',
        'waste_recorded' => 'ብክነት ተመዝግቧል።',
        'kitchen_sales_recorded' => 'የኩሽና ሽያጭ ማጠቃለያ ተመዝግቧል። ወደ POS ገቢ አይታከልም።',
    ],

    'reconciliation' => [
        'shift_in_progress' => 'ይህ ፈረቃ አስቀድሞ በሂደት ላይ ያለ ማስተካከያ አለው።',
        'variance_flag_instead' => 'ይህ መዝጋት ልዩነት አለው። ከመቀበል ይልቅ ምልክት ያድርጉ።',
        'already_closed' => 'ይህ ማስተካከያ አስቀድሞ ተዘግቷል።',
        'discrepancy_only_close' => 'በዚህ መንገድ መዝጋት የሚቻለው የልዩነት ግምገማ ብቻ ነው።',
        'resolve_discrepancies' => 'ከመዝጋት በፊት ክፍት ልዩነቶችን ይፍቱ።',
        'discrepancy_already_resolved' => 'ይህ ልዩነት አስቀድሞ ተፈትቷል።',
        'no_longer_editable' => 'ይህ ማስተካከያ ከእንግዲህ አይስተካከልም።',
        'return_exceeds_on_hand' => 'የሚመለሰው ብዛት በእጅዎ ካለው መብለጥ አይችልም።',
        'receipt_not_found' => 'አንድ ወይም ከዚያ በላይ የዝውውር ደረሰኞች አልተገኙም።',
        'receipt_wrong_branch' => 'የዝውውር ደረሰኝ የሌላ ቅርንጫፍ ነው።',
        'receipt_not_yours' => 'ማያያዝ የሚችሉት እርስዎ የሰቀሉትን ደረሰኞች ብቻ ነው።',
        'receipt_already_linked' => 'የዝውውር ደረሰኝ አስቀድሞ ከሌላ መዝጋት ጋር ተያይዟል።',
    ],

    'purchase' => [
        'already_received' => 'ይህ ግዢ አስቀድሞ ተቀብሏል።',
        'not_approved' => 'ይህ ግዢ አልጸደቀም።',
        'accepted_exceeds_ordered' => 'የተቀበለው ብዛት ከታዘዘው መብለጥ አይችልም።',
        'cannot_reapprove_received' => 'የተቀበለ ግዢ እንደገና ማጽደቅ አይቻልም።',
    ],

    'order' => [
        'cannot_transition' => 'ትዕዛዝ ከ :from ወደ :to መሄድ አይችልም።',
        'table_has_open' => 'ይህ ጠረጴዛ አስቀድሞ ክፍት ትዕዛዝ አለው።',
        'dine_in_needs_table' => 'የውስጥ ትዕዛዝ ጠረጴዛ ያስፈልገዋል።',
        'cannot_cancel' => 'ይህ ትዕዛዝ ከእንግዲህ መሰረዝ አይችልም።',
        'cannot_change' => 'ይህ ትዕዛዝ ከእንግዲህ መቀየር አይችልም።',
        'cannot_submit' => 'ይህ ትዕዛዝ ከእንግዲህ መላክ አይችልም።',
        'add_item_before_kitchen' => 'ወደ ኩሽና ከመላክ በፊት ቢያንስ አንድ ንጥል ያክሉ።',
        'serve_before_complete' => 'ከማጠናቀቅ በፊት ትዕዛዙን ያቅርቡ።',
        'cancelled_cannot_complete' => 'የተሰረዘ ትዕዛዝ ማጠናቀቅ አይቻልም።',
        'outstanding_balance' => 'ትዕዛዙ አሁንም ያልተከፈለ ቀሪ አለው።',
        'only_ready_served' => 'እንደ ቀረበ ምልክት ማድረግ የሚቻለው ዝግጁ ትዕዛዞች ብቻ ናቸው።',
        'item_not_on_order' => 'ንጥሉ የዚህ ትዕዛዝ አይደለም።',
        'quantity_at_least_one' => 'ብዛቱ ቢያንስ 1 መሆን አለበት። ይልቁን ንጥሉን ያስወግዱ።',
        'menu_item_unavailable' => 'ያ የምናሌ ንጥል አይገኝም።',
        'menu_item_wrong_branch' => 'ያ የምናሌ ንጥል የዚህ ቅርንጫፍ አይደለም።',
        'variant_unavailable' => 'ያ ልዩነት አይገኝም።',
        'addon_invalid' => 'ተጨማሪው ለዚህ ንጥል ልክ አይደለም።',
        'discount_closed' => 'ቅናሽ በተዘጋ ትዕዛዝ ላይ ማስገባት አይቻልም።',
        'discount_negative' => 'ቅናሽ አሉታዊ መሆን አይችልም።',
        'discount_exceeds_subtotal' => 'ቅናሽ ከንዑስ ድምር መብለጥ አይችልም።',
        'item_removed' => 'ንጥሉ ተወግዷል።',
        'table_open_order_delete' => 'ክፍት ትዕዛዝ ያለውን ጠረጴዛ መሰረዝ አይቻልም።',
        'table_deleted' => 'ጠረጴዛ ተሰርዟል።',
        'category_deactivated' => 'ምድቡ ቦዝኗል።',
    ],

    'payment' => [
        'cannot_record' => 'ክፍያ በዚህ ትዕዛዝ ላይ መመዝገብ አይቻልም።',
        'amount_gt_zero' => 'የክፍያ መጠን ከዜሮ በላይ መሆን አለበት።',
        'exceeds_balance' => 'ክፍያው ከቀሪው :balance ይበልጣል።',
        'only_completed_reverse' => 'መመለስ የሚቻለው የተጠናቀቀ ክፍያ ብቻ ነው።',
    ],

    'kitchen' => [
        'not_sent' => 'ይህ ንጥል ገና ወደ ኩሽና አልተላከም።',
        'cannot_transition' => 'የኩሽና ሁኔታ ከ :from ወደ :to መሄድ አይችልም።',
    ],

    'operations' => [
        'expense_deleted' => 'ወጪ ተሰርዟል።',
        'shift_already_open' => 'አስቀድሞ ክፍት ፈረቃ አለዎት።',
        'no_open_shift' => 'ለመውጣት ክፍት ፈረቃ የለም።',
        'receipt_already_uploaded' => 'ይህ ደረሰኝ ለዚህ ቅርንጫፍ አስቀድሞ ተሰቅሏል።',
        'ft_already_uploaded' => 'ይህ የFT / የግብይት ማጣቀሻ ለዚህ ቅርንጫፍ አስቀድሞ ተሰቅሏል።',
    ],

    'billing' => [
        'invoice_already_settled' => 'ያ ደረሰኝ አስቀድሞ ተከፍሏል።',
    ],

    'common' => [
        // Reserved for shared / cross-cutting copy.
    ],

];
