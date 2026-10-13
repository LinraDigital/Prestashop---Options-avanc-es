document.addEventListener('DOMContentLoaded', function () {
    var lastHandled = 0;

    // Présente dans le HTML généré par le serveur dès le premier chargement (contrairement
    // à l'attribut data-product-id du formulaire, qui n'était pas toujours disponible à temps) :
    // <body class="... product-id-10 product-id-category-12 product-id-manufacturer-2 ...">
    // On isole bien "product-id-10" et pas les autres "product-id-xxx-N" (catégorie, fabricant...).
    function getCurrentIdProduct() {
        var match = document.body.className.match(/\bproduct-id-(\d+)\b/);
        return match ? match[1] : null;
    }

    function getSelectedOptionIds() {
        var ids = [];
        document.querySelectorAll('.js-ld-front-option:checked').forEach(function (cb) {
            ids.push(cb.value);
        });
        return ids;
    }

    function getCurrentIdProductAttribute() {
        // Champ maintenu à jour par le thème (module Stripe) avec la combinaison
        // actuellement sélectionnée : le plus fiable qu'on ait trouvé pour cette info.
        var el = document.getElementById('stripe_product_attribute_info');
        return el ? el.value : '0';
    }

    function getRegisterUrl() {
        var block = document.querySelector('.ld-front-options');
        return block ? block.getAttribute('data-register-url') : null;
    }

    function registerOptions() {
        var idProduct = getCurrentIdProduct();
        var optionIds = getSelectedOptionIds();
        var registerUrl = getRegisterUrl();

        if (!idProduct || !optionIds.length || !registerUrl) {
            return;
        }

        var idProductAttribute = getCurrentIdProductAttribute();

        var body = 'ajax=1&id_product=' + encodeURIComponent(idProduct)
            + '&id_product_attribute=' + encodeURIComponent(idProductAttribute);

        optionIds.forEach(function (id, i) {
            body += '&option_ids%5B' + i + '%5D=' + encodeURIComponent(id);
        });

        fetch(registerUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        }).catch(function () {
            // Silencieux côté client : en cas d'échec, le panier garde le prix de base
            // du produit (pas d'erreur bloquante pour le client), et l'échec est loggé
            // côté serveur pour qu'on puisse le corriger.
        });
    }

    function handleCartSuccess() {
        var now = Date.now();
        if (now - lastHandled < 300) {
            return;
        }
        lastHandled = now;

        registerOptions();
    }

    if (window.jQuery) {
        window.jQuery(document).on('show.bs.modal', '#blockcart-modal', handleCartSuccess);
    } else {
        document.addEventListener('shown.bs.modal', function (e) {
            if (e.target && e.target.id === 'blockcart-modal') {
                handleCartSuccess();
            }
        });
    }

    // Repli léger (sans "attributes: true", la partie la plus coûteuse en perf) :
    // détecte aussi l'insertion de la modale directement dans le DOM.
    var fallbackObserver = new MutationObserver(function (mutations) {
        for (var i = 0; i < mutations.length; i++) {
            var addedNodes = mutations[i].addedNodes;
            for (var j = 0; j < addedNodes.length; j++) {
                var node = addedNodes[j];
                if (node.nodeType === 1 && (node.id === 'blockcart-modal' || (node.querySelector && node.querySelector('#blockcart-modal')))) {
                    handleCartSuccess();
                }
            }
        }
    });

    fallbackObserver.observe(document.body, { childList: true, subtree: true });
});
