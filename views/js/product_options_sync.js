document.addEventListener('DOMContentLoaded', function () {
    // État suivi indépendamment du DOM : les checkboxes elles-mêmes sont recréées
    // à chaque mise à jour AJAX du thème (changement de déclinaison), donc on ne
    // peut pas se fier à leurs éléments pour se souvenir de ce qui est coché.
    var checkedOptionIds = new Set();

    // Marqueur posé sur .product__price / .product__regular-price à chaque fois qu'ON
    // écrit dedans. S'il est absent, l'élément vient d'être fraîchement remplacé par le
    // thème (changement de déclinaison) : son texte est alors un prix "brut", sans nos
    // options, qu'on peut directement utiliser comme nouvelle base sans rien soustraire.
    var SYNC_MARK = 'data-ld-synced';

    function getBlock() {
        var blocks = document.querySelectorAll('.ld-front-options');
        if (!blocks.length) {
            return null;
        }
        var kept = blocks[blocks.length - 1];
        for (var i = 0; i < blocks.length - 1; i++) {
            blocks[i].remove();
        }
        return kept;
    }

    function reposition(block) {
        var variantsBlock = document.querySelector('.product__variants.js-product-variants');
        if (!block || !variantsBlock) {
            return;
        }
        if (block.previousElementSibling !== variantsBlock) {
            variantsBlock.insertAdjacentElement('afterend', block);
        }
    }

    function restoreCheckedState(block) {
        if (!block) {
            return;
        }
        block.querySelectorAll('.js-ld-front-option').forEach(function (cb) {
            cb.checked = checkedOptionIds.has(cb.value);
        });
    }

    function getOptionsTotal(block) {
        var total = 0;
        if (!block) {
            return total;
        }
        block.querySelectorAll('.js-ld-front-option').forEach(function (cb) {
            if (cb.checked) {
                total += parseFloat(cb.getAttribute('data-price')) || 0;
            }
        });
        return total;
    }

    function findPriceTextNode(el) {
        if (!el) {
            return null;
        }
        var node = null;
        el.childNodes.forEach(function (n) {
            if (n.nodeType === Node.TEXT_NODE && n.textContent.replace(/\s/g, '') !== '') {
                node = n;
            }
        });
        return node;
    }

    function parsePrice(str) {
        var cleaned = str.replace(/[^\d,.-]/g, '').replace(',', '.');
        return parseFloat(cleaned) || 0;
    }

    function formatPrice(value) {
        return ' ' + value.toFixed(2).replace('.', ',') + '\u00A0€ ';
    }

    // Mémorise la base "brute" de chaque prix (élément DOM -> valeur), pour ne pas avoir
    // à la déduire par soustraction (ce qui était le bug : soustraire puis rajouter les
    // mêmes options est une opération neutre, le prix ne pouvait donc jamais changer).
    var basesByElement = new WeakMap();

    function syncPriceElement(el, optionsTotal) {
        if (!el) {
            return;
        }

        var node = findPriceTextNode(el);
        if (!node) {
            return;
        }

        if (!el.hasAttribute(SYNC_MARK)) {
            // Élément neuf (remplacé par le thème) : son texte actuel est un prix brut,
            // sans nos options, qu'on prend tel quel comme nouvelle base.
            basesByElement.set(el, parsePrice(node.textContent));
        }

        var base = basesByElement.get(el) || 0;
        node.textContent = formatPrice(base + optionsTotal);
        el.setAttribute(SYNC_MARK, '1');
    }

    function updatePrices(block) {
        var optionsTotal = getOptionsTotal(block);
        syncPriceElement(document.querySelector('.product__price'), optionsTotal);
        syncPriceElement(document.querySelector('.product__regular-price'), optionsTotal);
    }

    function fullSync() {
        var block = getBlock();
        reposition(block);
        restoreCheckedState(block);
        updatePrices(block);
    }

    // Délégation sur document : fonctionne même si les checkboxes sont recréées
    // par une mise à jour AJAX, puisqu'on écoute au niveau du document entier.
    document.addEventListener('change', function (e) {
        if (!e.target.classList || !e.target.classList.contains('js-ld-front-option')) {
            return;
        }

        if (e.target.checked) {
            checkedOptionIds.add(e.target.value);
        } else {
            checkedOptionIds.delete(e.target.value);
        }

        updatePrices(getBlock());
    });

    fullSync();

    // Toute mise à jour AJAX du thème (changement de déclinaison...) remplace des éléments
    // (childList), pas seulement du texte : on n'observe donc que childList/subtree, ce qui
    // évite aussi de se déclencher sur nos propres écritures de texte (characterData).
    var syncTimeout = null;

    var observer = new MutationObserver(function () {
        if (syncTimeout) {
            clearTimeout(syncTimeout);
        }
        syncTimeout = setTimeout(fullSync, 150);
    });

    observer.observe(document.body, { childList: true, subtree: true });

    // Bulle d'info au clic/tap (le "title" natif ne s'affiche jamais sur mobile,
    // qui n'a pas de notion de survol souris).
    document.addEventListener('click', function (e) {
        var tip = e.target.closest('.js-ld-tooltip');

        // Clic ailleurs : ferme toute bulle ouverte
        if (!tip) {
            document.querySelectorAll('.ld-tooltip-bubble').forEach(function (b) { b.remove(); });
            return;
        }

        // Empêche le clic sur "(?)" de cocher la case (le tooltip est dans le <label>)
        e.preventDefault();
        e.stopPropagation();

        var already = tip.querySelector('.ld-tooltip-bubble');
        document.querySelectorAll('.ld-tooltip-bubble').forEach(function (b) { b.remove(); });

        if (already) {
            return; // un second clic referme simplement la bulle
        }

        var description = tip.getAttribute('data-description');
        if (!description) {
            return;
        }

        var bubble = document.createElement('span');
        bubble.className = 'ld-tooltip-bubble';
        bubble.textContent = description;
        tip.appendChild(bubble);
    });
});
