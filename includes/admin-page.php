<?php

add_action('admin_menu', 'cs_register_admin_page');

function cs_register_admin_page() {
    add_menu_page(
        'Currency Switcher',
        'Currency Switcher',
        'manage_options',
        'currency-switcher',
        'cs_admin_page_callback',
        'dashicons-money-alt'
    );
}

function cs_admin_page_callback() {
    $currencies = get_option('cs_custom_currencies', []);

    // При запис
    if (isset($_POST['cs_save_currencies'])) {
        $new_data = [];
        if (isset($_POST['currency_code']) && is_array($_POST['currency_code'])) {
            foreach ($_POST['currency_code'] as $i => $code) {
                $code = strtoupper(sanitize_text_field($code));
                $rate = floatval($_POST['currency_rate'][$i]);
                $symbol = sanitize_text_field($_POST['currency_symbol'][$i]);
                if ($code && $rate > 0 && $symbol) {
                    $new_data[$code] = ['rate' => $rate, 'symbol' => $symbol];
                }
            }
        }
        update_option('cs_custom_currencies', $new_data);
        $currencies = $new_data;
        echo '<div class="updated"><p>Настройките са запазени.</p></div>';
    }

    ?>
    <div class="wrap">
        <h1>Currency Switcher настройки</h1>
        <form method="post">
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Код</th>
                        <th>Курс спрямо BGN</th>
                        <th>Символ</th>
                        <th>Премахване</th>
                    </tr>
                </thead>
                <tbody id="cs-currency-rows">
                    <?php foreach ($currencies as $code => $data): ?>
                        <tr>
                            <td><input type="text" name="currency_code[]" value="<?php echo esc_attr($code); ?>" required></td>
                            <td><input type="text" name="currency_rate[]" value="<?php echo esc_attr($data['rate']); ?>" required></td>
                            <td><input type="text" name="currency_symbol[]" value="<?php echo esc_attr($data['symbol']); ?>" required></td>
                            <td><button type="button" class="button cs-remove">X</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p><button type="button" class="button" id="cs-add">Добави нова валута</button></p>
            <p><input type="submit" name="cs_save_currencies" class="button button-primary" value="Запази промените"></p>
        </form>

        <h2 style="margin-top:40px;">Курсове от БНБ (днес)</h2>
        <?php echo cs_get_bnb_exchange_rates(); ?>
    </div>

    <script>
    document.getElementById('cs-add').addEventListener('click', function () {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><input type="text" name="currency_code[]" required></td>
            <td><input type="text" name="currency_rate[]" required></td>
            <td><input type="text" name="currency_symbol[]" required></td>
            <td><button type="button" class="button cs-remove">X</button></td>
        `;
        document.getElementById('cs-currency-rows').appendChild(row);
    });

    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('cs-remove')) {
            e.target.closest('tr').remove();
        }
    });
    </script>
<?php
}


// 🔽 Функция за зареждане на БНБ курсовете
function cs_get_bnb_exchange_rates() {
    $url = 'https://www.bnb.bg/Statistics/StExternalSector/StExchangeRates/StERForeignCurrencies/index.htm?download=xml&lang=BG';

    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        return '<p style="color:red;">Неуспешно зареждане на курсовете от БНБ.</p>';
    }

    $body = wp_remote_retrieve_body($response);
    $xml = simplexml_load_string($body);

    if (!$xml) {
        return '<p style="color:red;">Грешка при обработката на XML от БНБ.</p>';
    }

    $output = '<table class="widefat striped" style="max-width:600px;">';
    $output .= '<thead><tr><th>Валута</th><th>Курс</th><th>Единици</th></tr></thead><tbody>';

    foreach ($xml->ROW as $row) {
        $code = (string)$row->CODE;
        $rate = (string)$row->RATE;
        $quantity = (string)$row->AMOUNT;
        $output .= "<tr><td>{$code}</td><td>{$rate}</td><td>{$quantity}</td></tr>";
    }

    $output .= '</tbody></table>';

    return $output;
}
