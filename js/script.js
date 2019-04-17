function get_timestamps() {
    let timestamps = [];
    $.ajax({
        url: '/scripts/admin_controller.php?action=get_timestamps',
        dataType: 'json',
        async: false,
        success: function (response) {
            response.timestamps.forEach(function (item, i, arr) {
                timestamps.push(item);
            });
        }
    });
    return timestamps
}

function get_available_base_currencies() {
    let available_base_currencies = [];
    $.ajax({
        url: '/scripts/admin_controller.php?action=get_prices',
        dataType: 'json',
        async: false,
        success: function (response) {
            let quote_currency = 'USDT';
            response.forEach(function (item, i, arr) {
                if (item.symbol.substring(item.symbol.length - 4) === quote_currency) {
                    let base_currency = item.symbol.substring(0, item.symbol.length - 4);
                    available_base_currencies.push(base_currency);
                }
            });
        }
    });
    return available_base_currencies;
}

function check_user() {
    $.ajax({
        url: '/scripts/guest_controller.php?action=check_user',
        async: false,
        dataType: 'json',
        success: function (response) {
            if (parseInt(response.is_logged) === 0)
                window.location.replace('login.html');
        }
    });
}