<?php

// Зареждане на валутите от базата
function cs_get_supported_currencies() {
    $currencies = get_option('cs_custom_currencies');

    if (!$currencies || !is_array($currencies)) {
        // Начални стойности при първо активиране
        $currencies = [
            'BGN' => ['rate' => 1,    'symbol' => 'лв'],
            'EUR' => ['rate' => 0.51, 'symbol' => '€'],
            'USD' => ['rate' => 0.55, 'symbol' => '$'],
        ];
        update_option('cs_custom_currencies', $currencies);
    }

    return $currencies;
}

// Определяне на текущата валута от GET или COOKIE
function cs_get_current_currency() {
    $currencies = cs_get_supported_currencies();

    if (isset($_GET['currency']) && array_key_exists($_GET['currency'], $currencies)) {
        setcookie('cs_currency', $_GET['currency'], time() + 3600 * 24 * 30, COOKIEPATH, COOKIE_DOMAIN);
        return $_GET['currency'];
    } elseif (isset($_COOKIE['cs_currency']) && array_key_exists($_COOKIE['cs_currency'], $currencies)) {
        return $_COOKIE['cs_currency'];
    }

    return 'BGN';
}

// Смяна на валутния символ
add_filter('woocommerce_currency_symbol', function($currency_symbol, $currency) {
    $curr = cs_get_current_currency();
    $currencies = cs_get_supported_currencies();
    return $currencies[$curr]['symbol'] ?? $currency_symbol;
}, 20, 2);

// Промяна на WooCommerce цените според валутния курс
add_filter('woocommerce_product_get_price', 'cs_convert_price', 20, 2);
add_filter('woocommerce_product_get_regular_price', 'cs_convert_price', 20, 2);
add_filter('woocommerce_product_get_sale_price', 'cs_convert_price', 20, 2);

function cs_convert_price($price, $product) {
    // Ако няма цена или тя не е число – не преобразувай
    if (!is_numeric($price)) {
        return $price;
    }

    $current = cs_get_current_currency();
    $currencies = cs_get_supported_currencies();
    $rate = isset($currencies[$current]['rate']) ? floatval($currencies[$current]['rate']) : 1;

    return floatval($price) * $rate;
}

// Шорткод за избор на валута
add_shortcode('currency_switcher', function () {
    $currencies = cs_get_supported_currencies();
    $current = cs_get_current_currency();

    $output = '<form method="get" class="currency-switcher"><select name="currency" onchange="this.form.submit()">';

    foreach ($currencies as $code => $data) {
        $selected = ($current === $code) ? 'selected' : '';
        $output .= "<option value='{$code}' {$selected}>{$code}</option>";
    }

    $output .= '</select></form>';
    return $output;
});

// Добавяне на падащо меню точно след цената
add_action('woocommerce_single_product_summary', 'cs_add_currency_switcher_after_price', 11);

function cs_add_currency_switcher_after_price() {
    echo '<div class="cs-switcher-after-price" style="margin-top: 10px;">' . do_shortcode('[currency_switcher]') . '</div>';
}
