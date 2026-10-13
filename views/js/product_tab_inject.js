document.addEventListener('DOMContentLoaded', function () {
    // Recherche un <li> d'onglet par le texte visible de son lien (ex: "Modules"),
    // plus fiable que de dépendre d'un ID interne qui peut varier.
    function findTabLiByLabel(formNav, label) {
        if (!formNav) {
            return null;
        }

        var links = formNav.querySelectorAll('a.nav-link');
        for (var i = 0; i < links.length; i++) {
            if (links[i].textContent.trim() === label) {
                return links[i].closest('li');
            }
        }

        return null;
    }

    var attempts = 0;
    var maxAttempts = 100; // 100 x 100ms = 10 secondes max

    var poll = setInterval(function () {
        attempts++;

        var formNav = document.getElementById('form-nav');
        var tabsContent = document.getElementById('product-tabs-content');
        var productForm = document.querySelector('form[name="product"]');
        var nativeModulesTab = findTabLiByLabel(formNav, 'Modules');
        var nativeOptionsTab = findTabLiByLabel(formNav, 'Options');

        // Seuls ces trois éléments sont vraiment indispensables. "Options" et "Modules"
        // ne servent qu'au positionnement (best effort) : leur absence ne doit jamais
        // empêcher l'onglet d'apparaître.
        var essentialsReady = formNav && tabsContent && productForm;

        if (!essentialsReady && attempts < maxAttempts) {
            return; // on retente au prochain tick
        }

        clearInterval(poll);

        if (!essentialsReady) {
            console.warn('[ld_advanced_options] Structure de la fiche produit introuvable après ' + (attempts * 100) + 'ms.', {
                formNav: !!formNav,
                tabsContent: !!tabsContent,
                productForm: !!productForm
            });

            return;
        }

        try {
            injectTab(formNav, tabsContent, productForm, nativeOptionsTab, nativeModulesTab);
        } catch (e) {
            console.error('[ld_advanced_options] Erreur lors de l\'injection de l\'onglet :', e);
        }
    }, 100);

    function injectTab(formNav, tabsContent, productForm, nativeOptionsTab, nativeModulesTab) {
        var idProduct = productForm.getAttribute('data-product-id');
        if (!idProduct) {
            return;
        }

        // Évite un double ajout si le script tourne deux fois
        if (document.getElementById('ld_advanced_options_tab-nav')) {
            return;
        }

        /* --- Création de l'onglet (Bootstrap standard, la bascule est automatique) --- */
        var li = document.createElement('li');
        li.id = 'ld_advanced_options_tab-nav';
        li.className = 'nav-item';

        var link = document.createElement('a');
        link.href = '#ld_advanced_options_tab';
        link.setAttribute('role', 'tab');
        link.setAttribute('data-toggle', 'tab');
        link.className = 'nav-link';
        link.textContent = 'Options avancées';

        li.appendChild(link);

        if (nativeModulesTab) {
            // On s'insère juste AVANT "Modules" (donc juste après "Options", si trouvé)
            formNav.insertBefore(li, nativeModulesTab);
        } else if (nativeOptionsTab && nativeOptionsTab.nextElementSibling) {
            formNav.insertBefore(li, nativeOptionsTab.nextElementSibling);
        } else {
            formNav.appendChild(li);
        }

    /* --- Création du panneau de contenu (vide pour l'instant) --- */
    var pane = document.createElement('div');
    pane.id = 'ld_advanced_options_tab';
    pane.className = 'tab-pane';
    pane.setAttribute('role', 'tabpanel');
    pane.innerHTML = '<style>@keyframes ldAdvancedOptionsSpin { to { transform: rotate(360deg); } }</style>'
        + '<div style="display: flex; justify-content: center; align-items: center; padding: 60px 0;">'
        + '<div style="width: 40px; height: 40px; border: 4px solid #eee; border-top-color: #333; '
        + 'border-radius: 50%; animation: ldAdvancedOptionsSpin 0.8s linear infinite;"></div>'
        + '</div>';
    tabsContent.appendChild(pane);

    /* --- Chargement paresseux : uniquement au premier clic sur l'onglet, jamais
       automatiquement au chargement de la page (pour ne solliciter le serveur
       que si le vendeur ouvre vraiment cet onglet). --- */
    var loaded = false;

    link.addEventListener('click', function () {
        if (loaded) {
            return;
        }
        loaded = true;

        var ajaxUrl = window.ldAdvancedOptionsAjaxUrl
            + '&ajax=1&action=get_product_panel&id_product=' + encodeURIComponent(idProduct);

        fetch(ajaxUrl, { credentials: 'same-origin' })
            .then(function (response) { return response.text(); })
            .then(function (html) {
                pane.innerHTML = html;
                bindSaveButton(pane, idProduct);
            })
            .catch(function () {
                pane.innerHTML = '<p class="alert alert-danger" style="margin: 20px;">Erreur de chargement du panneau.</p>';
            });
    });

    function bindSaveButton(pane, idProduct) {
        var btn = pane.querySelector('.js-ld-save-options');
        var msg = pane.querySelector('.js-ld-save-msg');

        if (!btn) {
            return;
        }

        btn.addEventListener('click', function () {
            btn.disabled = true;

            var checkboxes = pane.querySelectorAll('.js-ld-opt-checkbox:checked');
            var body = 'ajax=1&action=save_product_options&id_product=' + encodeURIComponent(idProduct);

            checkboxes.forEach(function (cb, i) {
                var idOption = cb.value;
                var valueInput = pane.querySelector('.js-ld-opt-value[data-id-option="' + idOption + '"]');
                var value = valueInput ? valueInput.value : '';

                body += '&ld_product_options%5B' + i + '%5D%5Bid%5D=' + encodeURIComponent(idOption);
                body += '&ld_product_options%5B' + i + '%5D%5Bvalue%5D=' + encodeURIComponent(value);
            });

            fetch(window.ldAdvancedOptionsAjaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            })
                .then(function (response) { return response.json(); })
                .then(function (res) {
                    btn.disabled = false;
                    if (!msg) {
                        return;
                    }
                    msg.style.display = 'block';
                    if (res.success) {
                        msg.className = 'js-ld-save-msg alert alert-success';
                        msg.textContent = 'Options enregistrées.';
                    } else {
                        msg.className = 'js-ld-save-msg alert alert-danger';
                        msg.textContent = res.error || 'Une erreur est survenue.';
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    if (msg) {
                        msg.style.display = 'block';
                        msg.className = 'js-ld-save-msg alert alert-danger';
                        msg.textContent = 'Erreur réseau, merci de réessayer.';
                    }
                });
        });
    }
    }
});
