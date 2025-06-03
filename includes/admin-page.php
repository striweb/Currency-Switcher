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

    $bnb_rates = [];
    $url = 'https://www.bnb.bg/Statistics/StExternalSector/StExchangeRates/StERForeignCurrencies/index.htm?download=xml&lang=BG';
    $response = wp_remote_get($url);
    if (!is_wp_error($response)) {
        $body = wp_remote_retrieve_body($response);
        $xml = simplexml_load_string($body);
        if ($xml) {
            foreach ($xml->ROW as $row) {
                $code = (string)$row->CODE;
                $rate = floatval(str_replace(',', '.', (string)$row->RATE));
                $amount = intval((string)$row->AMOUNT);
                if ($amount > 0) {
                    $rate = $rate / $amount;
                }
                if ($rate > 0) {
                    $bnb_rates[$code] = round(1 / $rate, 6);
                }

                if (!isset($bnb_rates['EUR'])) {
                  $bnb_rates['EUR'] = round(1 / 1.95583, 6);
                }

            }
            
        }
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
                    <?php foreach ($currencies as $code => $data):
                        $code = strtoupper($code);
                        $current_rate = floatval($data['rate']);
                        $bnb_rate = isset($bnb_rates[$code]) ? $bnb_rates[$code] : null;
                        $rate_diff = $bnb_rate !== null && abs($current_rate - $bnb_rate) > 0.0001;
                    ?>
                        <tr>
                            <td><input type="text" name="currency_code[]" value="<?php echo esc_attr($code); ?>" required></td>
                            <td>
                                <input type="text"
                                      name="currency_rate[]"
                                      value="<?php echo esc_attr($current_rate); ?>"
                                      required
                                      class="cs-rate-input"
                                      data-code="<?php echo esc_attr($code); ?>">
                                <?php if ($rate_diff): ?>
                                    <div class="cs-rate-update"
                                        data-code="<?php echo esc_attr($code); ?>"
                                        data-bnb-rate="<?php echo esc_attr($bnb_rate); ?>">
                                        → <strong><?php echo esc_html($bnb_rate); ?></strong>
                                        <button type="button" class="button small cs-rate-apply">Обнови</button>
                                    </div>
                                <?php endif; ?>
                            </td>

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

    <style>
    .cs-rate-warning {
        margin-left: 6px;
        color: #d98400;
        font-size: 16px;
        cursor: help;
    }
    .cs-rate-update {
    display: inline-block;
    margin-left: 8px;
    color: #d98400;
    font-size: 13px;
}

.cs-rate-update button.cs-rate-apply {
    margin-left: 5px;
    padding: 2px 6px;
    font-size: 11px;
    height: auto;
    line-height: 1.2;
}

    </style>

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

        if (e.target.classList.contains('cs-copy-rate')) {
    const code = e.target.getAttribute('data-code');
    let rate = parseFloat(e.target.getAttribute('data-rate'));

    if (!isNaN(rate) && rate > 0) {
        rate = (1 / rate).toFixed(6);
    }

    const rows = document.querySelectorAll('#cs-currency-rows tr');

    let found = false;
    rows.forEach(row => {
        const codeInput = row.querySelector('input[name="currency_code[]"]');
        const rateInput = row.querySelector('input[name="currency_rate[]"]');

        if (codeInput && rateInput && codeInput.value.toUpperCase() === code) {
            rateInput.value = rate;
            rateInput.focus();
            rateInput.style.backgroundColor = '#d4ffd4';
            setTimeout(() => rateInput.style.backgroundColor = '', 1000);
            found = true;
        }
    });

    if (!found) {
        alert('Валутата ' + code + ' не е добавена. Моля, добавете я първо ръчно.');
    }
}

    });

    document.addEventListener('click', function (e) {
    if (e.target.classList.contains('cs-rate-apply')) {
        const wrapper = e.target.closest('.cs-rate-update');
        const code = wrapper.getAttribute('data-code');
        const rate = wrapper.getAttribute('data-bnb-rate');

        const rows = document.querySelectorAll('#cs-currency-rows tr');
        rows.forEach(row => {
            const codeInput = row.querySelector('input[name="currency_code[]"]');
            const rateInput = row.querySelector('input[name="currency_rate[]"]');

            if (codeInput && rateInput && codeInput.value.toUpperCase() === code) {
                rateInput.value = rate;
                rateInput.focus();
                rateInput.style.backgroundColor = '#d4ffd4';
                setTimeout(() => rateInput.style.backgroundColor = '', 1000);
            }
        });
    }
});

    </script>
<?php
}

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
    $output .= '<thead><tr><th>Валута</th><th>Курс</th><th>Действие</th></tr></thead><tbody>';

    foreach ($xml->ROW as $row) {
        $code = (string)$row->CODE;
        $rate = str_replace(',', '.', (string)$row->RATE);
        $quantity = (string)$row->AMOUNT;

        $output .= "<tr data-currency-code='{$code}' data-currency-rate='{$rate}' data-currency-amount='{$quantity}'>";
        $output .= "<td>{$code}</td><td>{$rate}</td>";
        $output .= "<td><button type='button' class='button cs-copy-rate' data-code='{$code}' data-rate='{$rate}'>Копирай</button></td>";
        $output .= "</tr>";
    }

    $eur_rate = '1.95583';
    $output .= "<tr data-currency-code='EUR' data-currency-rate='{$eur_rate}' data-currency-amount='1'>";
    $output .= "<td>EUR</td><td>{$eur_rate}</td>";
    $output .= "<td><button type='button' class='button cs-copy-rate' data-code='EUR' data-rate='{$eur_rate}'>Копирай</button></td>";
    $output .= "</tr>";
    $output .= '</tbody></table>';

    return $output;
}
