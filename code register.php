
/**
 * Register Guest Users @ WooCommerce Checkout By Billing Phone (Digits)
 * @author arman-ario (based on ThemeFars)
 * @testedwith WooCommerce 6
 * @updated 2025-03-14 12:47:49
 */

add_action('woocommerce_thankyou', 'register_guests_by_phone', 9999);

function register_guests_by_phone($order_id) {
    // اگر کاربر لاگین کرده باشد، خارج می‌شویم
    if (is_user_logged_in()) {
        return;
    }

    $country_code = "+98"; // کد کشور پیش‌فرض

    // دریافت اطلاعات سفارش
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    $email = $order->get_billing_email();
    
    // استانداردسازی شماره موبایل
    $raw_phone = $order->get_billing_phone();
    $phone_no = substr(preg_replace('/[^0-9]/', '', $raw_phone), -10);
    
    // بررسی اعتبار شماره موبایل
    if (strlen($phone_no) !== 10 || substr($phone_no, 0, 1) !== '9') {
        return;
    }

    $phone = $country_code . $phone_no;

    // بررسی وجود کاربر
    $existing_user = get_user_by_phone($phone_no);
    
    if ($existing_user) {
        // اگر کاربر وجود دارد
        wc_update_new_customer_past_orders($existing_user->ID);
        update_post_meta($order_id, '_customer_user', $existing_user->ID);
    } else {
        // ایجاد کاربر جدید
        try {
            $customer_id = wp_insert_user([
                'user_login' => $phone,
                'user_pass' => wp_generate_password(),
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                'user_email' => (!empty($email)) ? $email : '',
                'role' => 'customer'
            ]);

            if (is_wp_error($customer_id)) {
                throw new Exception($customer_id->get_error_message());
            }

            // ذخیره متادیتای Digits - دقیقاً مثل کد اصلی
            update_user_meta($customer_id, 'digits_phone_no', $phone_no);
            update_user_meta($customer_id, 'digits_phone', $phone);
            update_user_meta($customer_id, 'digt_countrycode', $country_code);

            // تنظیم کوکی‌ها برای کاربر جدید
            wc_update_new_customer_past_orders($customer_id);
            wc_set_customer_auth_cookie($customer_id);
            
            // اتصال سفارش به کاربر
            update_post_meta($order_id, '_customer_user', $customer_id);

        } catch (Exception $e) {
            error_log(sprintf(
                '[%s] خطا در ثبت کاربر مهمان: %s',
                date('Y-m-d H:i:s'),
                $e->getMessage()
            ));
            return;
        }
    }
}

/**
 * جستجوی کاربر با شماره موبایل در متادیتای Digits
 */
function get_user_by_phone($phone) {
    // اول جستجو در متادیتای digits_phone_no
    $users = get_users([
        'meta_key' => 'digits_phone_no',
        'meta_value' => $phone,
        'number' => 1
    ]);

    if (!empty($users)) {
        return $users[0];
    }

    // اگر پیدا نشد، در username جستجو کنیم
    return get_user_by('login', '+98' . $phone);
}