document.addEventListener('DOMContentLoaded', function () {
    var url = window.ldAdvancedOptionsOrderAjaxUrl;
    if (!url) {
        return; // pas sur la bonne page (le hook ne charge ce script que sur AdminOrders)
    }

    // Deux formats d'URL possibles selon la page : moderne (/sell/orders/6/view)
    // ou classique (?id_order=6&...) - on gère les deux.
    var idOrder = new URLSearchParams(window.location.search).get('id_order');

    if (!idOrder) {
        var pathMatch = window.location.pathname.match(/\/orders\/(\d+)(\/|$)/);
        idOrder = pathMatch ? pathMatch[1] : null;
    }

    if (!idOrder) {
        return;
    }

    fetch(url + '&ajax=1&action=get_order_options&id_order=' + encodeURIComponent(idOrder), {
        credentials: 'same-origin'
    })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (!res || !res.success || !res.lines || !res.lines.length) {
                return;
            }

            res.lines.forEach(function (line) {
                // La ligne <tr id="orderProduct_8"> contient directement id_order_detail :
                // identifiant fiable, plus besoin de comparer des textes de nom de produit.
                var row = document.getElementById('orderProduct_' + line.id_order_detail);
                if (!row) {
                    return;
                }

                var cell = row.querySelector('td.cellProductName');
                if (!cell || cell.querySelector('.ld-order-options')) {
                    return;
                }

                var wrapper = document.createElement('div');
                wrapper.className = 'ld-order-options';
                wrapper.style.marginTop = '4px';

                line.options.forEach(function (optionText) {
                    var p = document.createElement('p');
                    p.className = 'mb-0';
                    p.style.fontSize = '0.85em';
                    p.style.color = '#6c757d';
                    p.textContent = 'Option : ' + optionText;
                    wrapper.appendChild(p);
                });

                cell.appendChild(wrapper);
            });
        })
        .catch(function () {
            // Silencieux : au pire, l'info n'apparaît pas, mais la page de commande
            // ne doit jamais planter à cause de ce script.
        });
});
