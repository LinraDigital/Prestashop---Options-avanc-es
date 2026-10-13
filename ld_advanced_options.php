<?php
/**
 * ld_advanced_options - Linra Digital
 * - Catalogue > Options avancées : catégories et options (nom + valeur + description).
 * - Fiche produit (onglet injecté) : sélection des options proposées, avec prix propre au produit.
 * - Fiche produit (front) : choix des options, ajoutées au panier via un prix spécifique limité au panier.
 * - Panier, commande (admin) et e-mail de confirmation : rappel des options choisies.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Ld_Advanced_Options extends Module
{
    const ADMIN_CONTROLLER = 'AdminLdAdvancedOptions';

    public function __construct()
    {
        $this->name = 'ld_advanced_options';
        $this->tab = 'administration';
        $this->version = '2.10.0';
        $this->author = 'Linra Digital';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('LD - Options avancées');
        $this->description = $this->l('Catégories et options supplémentaires (nom + valeur), sélectionnables sur une fiche produit.');

        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        if (!parent::install()
            || !$this->installTables()
            || !$this->installTab()
        ) {
            return false;
        }

        // Désenregistrement explicite d'un hook obsolète (au cas où il aurait été
        // enregistré par une ancienne version du module) - il ne doit plus jamais être appelé.
        $this->unregisterHook('displayAdminProductsExtra');

        return $this->registerHook('actionAdminControllerSetMedia')
            && $this->registerHook('displayProductAdditionalInfo')
            && $this->registerHook('actionFrontControllerSetMedia')
            && $this->registerHook('actionValidateOrder')
            && $this->registerHook('displayAdminOrder')
            && $this->registerHook('displayCartExtraProductInfo')
            && $this->registerHook('actionEmailSendBefore')
            && $this->registerHook('actionEmailAddAfterContent');
    }

    public function uninstall()
    {
        $id_tab = (int) Tab::getIdFromClassName(self::ADMIN_CONTROLLER);
        if ($id_tab) {
            $tab = new Tab($id_tab);
            $tab->delete();
        }

        // Les tables sont volontairement conservées (catégories/options déjà configurées) :
        // à supprimer manuellement en SQL si vous êtes sûr de ne plus en avoir besoin.
        return parent::uninstall();
    }

    private function installTables()
    {
        $sql = [];

        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'ld_advanced_options_category` (
            `id_category` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(128) NOT NULL,
            PRIMARY KEY (`id_category`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4';

        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'ld_advanced_options_option` (
            `id_option` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_category` INT UNSIGNED NOT NULL,
            `name` VARCHAR(128) NOT NULL,
            `value` DECIMAL(20,6) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id_option`),
            KEY `id_category` (`id_category`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4';

        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'ld_advanced_options_product` (
            `id_product` INT UNSIGNED NOT NULL,
            `id_option` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`id_product`, `id_option`),
            KEY `id_option` (`id_option`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4';

        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'ld_advanced_options_cart_product` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_cart` INT UNSIGNED NOT NULL,
            `id_product` INT UNSIGNED NOT NULL,
            `id_product_attribute` INT UNSIGNED NOT NULL DEFAULT 0,
            `id_option` INT UNSIGNED NOT NULL,
            `option_name` VARCHAR(128) NOT NULL DEFAULT \'\',
            `price` DECIMAL(20,6) NOT NULL DEFAULT 0,
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `cart_product` (`id_cart`, `id_product`, `id_product_attribute`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4';

        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'ld_advanced_options_order_product` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_order` INT UNSIGNED NOT NULL,
            `id_order_detail` INT UNSIGNED NOT NULL,
            `id_option` INT UNSIGNED NOT NULL,
            `option_name` VARCHAR(128) NOT NULL DEFAULT \'\',
            `price` DECIMAL(20,6) NOT NULL DEFAULT 0,
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `order_detail` (`id_order`, `id_order_detail`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4';

        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return $this->migrateOptionDescriptionColumn()
            && $this->migrateValueOverrideColumn();
    }

    /**
     * La table peut déjà exister (installation précédente) : CREATE TABLE IF NOT EXISTS
     * ne modifie pas une table déjà en place, donc on ajoute la colonne manuellement si absente.
     */
    private function migrateOptionDescriptionColumn()
    {
        $columns = Db::getInstance()->executeS('DESCRIBE `' . _DB_PREFIX_ . 'ld_advanced_options_option`');
        $names = array_column($columns ?: [], 'Field');

        if (!in_array('description', $names, true)) {
            return (bool) Db::getInstance()->execute(
                'ALTER TABLE `' . _DB_PREFIX_ . 'ld_advanced_options_option`
                 ADD COLUMN `description` TEXT NULL AFTER `value`'
            );
        }

        return true;
    }

    /**
     * Colonne d'override du prix d'une option, propre à un produit donné
     * (vide = on utilise la valeur globale de l'option).
     */
    private function migrateValueOverrideColumn()
    {
        $columns = Db::getInstance()->executeS('DESCRIBE `' . _DB_PREFIX_ . 'ld_advanced_options_product`');
        $names = array_column($columns ?: [], 'Field');

        if (!in_array('value_override', $names, true)) {
            return (bool) Db::getInstance()->execute(
                'ALTER TABLE `' . _DB_PREFIX_ . 'ld_advanced_options_product`
                 ADD COLUMN `value_override` DECIMAL(20,6) NULL AFTER `id_option`'
            );
        }

        return true;
    }

    private function installTab()
    {
        $id_tab = (int) Tab::getIdFromClassName(self::ADMIN_CONTROLLER);
        $tab = $id_tab ? new Tab($id_tab) : new Tab();

        $tab->class_name = self::ADMIN_CONTROLLER;
        $tab->module = $this->name;
        $tab->icon = 'tune';
        $tab->active = 1;

        // Le menu « Catalogue » porte un nom de classe différent selon les versions de PrestaShop :
        // on essaie les noms connus, jamais un identifiant en dur (il varie d'une boutique à l'autre).
        $resolved_parent = 0;
        foreach (['AdminParentCatalog', 'AdminCatalog'] as $parent_class) {
            $resolved_parent = (int) Tab::getIdFromClassName($parent_class);
            if ($resolved_parent) {
                break;
            }
        }

        if ($resolved_parent) {
            $tab->id_parent = $resolved_parent;
        } elseif (!$tab->id_parent) {
            // Menu Catalogue introuvable et aucun rattachement existant : l'onglet est créé au premier niveau
            // du menu (toujours visible) plutôt que sous un parent deviné au hasard.
            $tab->id_parent = 0;
        }
        // Sinon (résolution échouée mais un id_parent était déjà en place) : on NE TOUCHE
        // PAS à $tab->id_parent, pour ne jamais écraser un rattachement déjà correct.

        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[$lang['id_lang']] = 'Options avancées';
        }

        return $id_tab ? $tab->update() : $tab->add();
    }

    /**
     * ------------------------------------------------------------------
     * Point d'entrée "Configurer" (Modules > ... ou onglet "Modules" de la fiche produit)
     * ------------------------------------------------------------------
     */
    public function getContent()
    {
        $id_product = (int) Tools::getValue('id_product');

        // Arrivée depuis la fiche produit (onglet "Modules" > Configurer) : on affiche
        // directement le panneau de sélection des options pour CE produit.
        if ($id_product) {
            if (Tools::isSubmit('submitLdOptionsProduct') && (int) Tools::getValue('id_product') === $id_product) {
                $this->saveProductOptions($id_product);
            }

            return $this->renderProductOptionsPanel($id_product);
        }

        // Arrivée depuis Modules > Configurer (sans contexte produit) : on renvoie
        // vers la vraie page de gestion, Catalogue > Options avancées.
        Tools::redirectAdmin(
            $this->context->link->getAdminLink(self::ADMIN_CONTROLLER)
        );
    }

    /**
     * ------------------------------------------------------------------
     * Fiche produit : sélection des options avancées
     * ------------------------------------------------------------------
     */
    /**
     * ------------------------------------------------------------------
     * Injection de l'onglet "Options avancées" sur la fiche produit
     * ------------------------------------------------------------------
     */
    public function hookActionAdminControllerSetMedia($params)
    {
        if (!$this->active) {
            return;
        }

        $controller = $this->context->controller;

        if (!isset($controller->controller_name)) {
            return;
        }

        if ($controller->controller_name === 'AdminProducts') {
            Media::addJsDef([
                'ldAdvancedOptionsAjaxUrl' => $this->context->link->getAdminLink(self::ADMIN_CONTROLLER)
                    . '&token=' . Tools::getAdminTokenLite(self::ADMIN_CONTROLLER),
            ]);

            $this->context->controller->addJS(
                _PS_MODULE_DIR_ . $this->name . '/views/js/product_tab_inject.js'
            );
        }

        if ($controller->controller_name === 'AdminOrders') {
            Media::addJsDef([
                'ldAdvancedOptionsOrderAjaxUrl' => $this->context->link->getAdminLink(self::ADMIN_CONTROLLER)
                    . '&token=' . Tools::getAdminTokenLite(self::ADMIN_CONTROLLER),
            ]);

            $this->context->controller->addJS(
                _PS_MODULE_DIR_ . $this->name . '/views/js/order_options_inject.js'
            );
        }
    }

    /**
     * Hook obsolète, issu d'une toute première tentative d'intégration (abandonnée
     * au profit de l'onglet injecté en JS, notre mécanisme actif). Neutralisé
     * volontairement : son ancien comportement générait un <form> imbriqué dans
     * celui, natif, de la fiche produit - source probable d'un conflit de jeton CSRF.
     */
    public function hookDisplayAdminProductsExtra($params)
    {
        return '';
    }

    private function saveProductOptions($id_product)
    {
        Db::getInstance()->delete('ld_advanced_options_product', 'id_product = ' . (int) $id_product);

        $option_ids = Tools::getValue('ld_product_options', []);
        $option_ids = is_array($option_ids) ? array_map('intval', $option_ids) : [];

        foreach ($option_ids as $id_option) {
            if ($id_option > 0) {
                Db::getInstance()->insert(
                    'ld_advanced_options_product',
                    [
                        'id_product' => (int) $id_product,
                        'id_option' => $id_option,
                    ],
                    true, // les valeurs nulles seraient écrites NULL (et non '')
                    true,
                    Db::INSERT_IGNORE
                );
            }
        }
    }

    private function renderProductOptionsPanel($id_product)
    {
        $categories = Db::getInstance()->executeS(
            'SELECT id_category, name FROM `' . _DB_PREFIX_ . 'ld_advanced_options_category` ORDER BY name ASC'
        );

        $options = Db::getInstance()->executeS(
            'SELECT id_option, id_category, name, value
             FROM `' . _DB_PREFIX_ . 'ld_advanced_options_option`
             ORDER BY name ASC'
        );

        $selected = Db::getInstance()->executeS(
            'SELECT id_option FROM `' . _DB_PREFIX_ . 'ld_advanced_options_product`
             WHERE id_product = ' . (int) $id_product
        );
        $selected_ids = array_map('intval', array_column($selected ?: [], 'id_option'));

        $options_by_category = [];
        foreach ($options ?: [] as $opt) {
            $options_by_category[(int) $opt['id_category']][] = $opt;
        }

        $this->context->smarty->assign([
            'ld_categories' => $categories ?: [],
            'ld_options_by_category' => $options_by_category,
            'ld_selected_ids' => $selected_ids,
            'ld_id_product' => $id_product,
        ]);

        return $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'ld_advanced_options/views/templates/admin/product_options.tpl'
        );
    }

    /**
     * ------------------------------------------------------------------
     * Front : chargement du CSS, uniquement sur la fiche produit
     * ------------------------------------------------------------------
     */
    public function hookActionFrontControllerSetMedia($params)
    {
        try {
            if (!isset($this->context->controller->php_self) || $this->context->controller->php_self !== 'product') {
                return;
            }

            $this->context->controller->registerStylesheet(
                'ld-advanced-options-front-css',
                'modules/' . $this->name . '/views/css/ld_advanced_options_front.css',
                ['media' => 'all', 'priority' => 100]
            );

            $this->context->controller->registerJavascript(
                'ld-advanced-options-sync-js',
                'modules/' . $this->name . '/views/js/product_options_sync.js',
                ['position' => 'bottom', 'priority' => 100]
            );

            $this->context->controller->registerJavascript(
                'ld-advanced-options-add-to-cart-js',
                'modules/' . $this->name . '/views/js/product_add_to_cart_options.js',
                ['position' => 'bottom', 'priority' => 101]
            );
        } catch (\Throwable $e) {
            // On ne doit jamais casser une page front : en cas de souci, on journalise (Paramètres avancés >
            // Logs) et on se tait. Aucun fichier de debug n'est écrit à la racine du site.
            PrestaShopLogger::addLog(
                'ld_advanced_options (setMedia) : ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(),
                3
            );
        }
    }

    /**
     * ------------------------------------------------------------------
     * Front : affichage des options sur la fiche produit (sous les déclinaisons)
     * ------------------------------------------------------------------
     */
    public function hookDisplayProductAdditionalInfo($params)
    {
        try {
            $id_product = $this->extractIdProductFromHookParams($params);

            if (!$id_product) {
                return '';
            }

            $groups = $this->getProductOptionsGroupedForFront($id_product);

            if (empty($groups)) {
                return '';
            }

            $this->context->smarty->assign([
                'ld_front_option_groups' => $groups,
                'ld_register_url' => $this->context->link->getModuleLink($this->name, 'registeroptions'),
            ]);

            return $this->context->smarty->fetch(
                _PS_MODULE_DIR_ . 'ld_advanced_options/views/templates/hook/product_options_front.tpl'
            );
        } catch (\Throwable $e) {
            // On garde le try/catch en filet de sécurité : jamais de fiche produit
            // cassée à cause de ce module, même si un bug imprévu survient plus tard.
            PrestaShopLogger::addLog('ld_advanced_options (displayProductAdditionalInfo) : ' . $e->getMessage(), 3);

            return '';
        }
    }

    private function extractIdProductFromHookParams($params)
    {
        if (!isset($params['product'])) {
            return 0;
        }

        $product = $params['product'];

        // $product peut être un tableau, un ProductLazyArray (ArrayAccess) ou un objet Product classique
        if (is_array($product) || $product instanceof ArrayAccess) {
            if (isset($product['id_product'])) {
                return (int) $product['id_product'];
            }
            if (isset($product['id'])) {
                return (int) $product['id'];
            }
        } elseif (is_object($product)) {
            if (isset($product->id_product)) {
                return (int) $product->id_product;
            }
            if (isset($product->id)) {
                return (int) $product->id;
            }
        }

        return 0;
    }

    private function getProductOptionsGroupedForFront($id_product)
    {
        $categories = Db::getInstance()->executeS(
            'SELECT id_category, name FROM `' . _DB_PREFIX_ . 'ld_advanced_options_category` ORDER BY id_category ASC'
        );

        $rows = Db::getInstance()->executeS(
            'SELECT o.id_option, o.id_category, o.name, o.description,
                    IFNULL(pop.value_override, o.value) AS value
             FROM `' . _DB_PREFIX_ . 'ld_advanced_options_product` pop
             INNER JOIN `' . _DB_PREFIX_ . 'ld_advanced_options_option` o ON o.id_option = pop.id_option
             WHERE pop.id_product = ' . (int) $id_product . '
             ORDER BY o.id_category ASC, o.name ASC'
        );

        if (!$rows) {
            return [];
        }

        $by_category = [];
        foreach ($rows as $row) {
            $by_category[(int) $row['id_category']][] = [
                'id_option' => (int) $row['id_option'],
                'name' => $row['name'],
                'description' => $row['description'],
                'price' => (float) $row['value'],
                'price_formatted' => $this->ldFormatPrice((float) $row['value']),
            ];
        }

        $groups = [];
        foreach ($categories ?: [] as $cat) {
            $id_category = (int) $cat['id_category'];
            if (isset($by_category[$id_category])) {
                $groups[] = [
                    'name' => $cat['name'],
                    'options' => $by_category[$id_category],
                ];
            }
        }

        return $groups;
    }

    /**
     * Formate un prix de façon robuste, peu importe l'API réellement disponible
     * sur cette version de PrestaShop (Tools::displayPrice() n'existe pas ici) :
     * 1. Tools::displayPrice() si disponible (versions classiques)
     * 2. Locale::formatPrice() via le contexte (versions plus récentes)
     * 3. En dernier recours, un formatage manuel simple
     */
    private function ldFormatPrice($price)
    {
        $price = (float) $price;

        if (is_callable(['Tools', 'displayPrice'])) {
            try {
                return Tools::displayPrice($price);
            } catch (\Throwable $e) {
                // on tente le repli ci-dessous
            }
        }

        try {
            $context = Context::getContext();
            if ($context && method_exists($context, 'getCurrentLocale')) {
                $locale = $context->getCurrentLocale();
                $currency = $context->currency;
                if ($locale && $currency && method_exists($locale, 'formatPrice')) {
                    return $locale->formatPrice($price, $currency->iso_code);
                }
            }
        } catch (\Throwable $e) {
            // on tente le dernier repli ci-dessous
        }

        return number_format($price, 2, ',', ' ') . ' €';
    }

    /**
     * ------------------------------------------------------------------
     * Recopie les options du panier vers la commande, au moment de la validation.
     * Nécessaire car le panier peut être vidé/modifié après achat : on garde une
     * trace permanente, indépendante, de ce qui a été réellement commandé.
     * ------------------------------------------------------------------
     */
    public function hookActionValidateOrder($params)
    {
        try {
            if (!isset($params['order']) || !isset($params['cart'])) {
                return;
            }

            /** @var Order $order */
            $order = $params['order'];
            /** @var Cart $cart */
            $cart = $params['cart'];

            $cart_rows = Db::getInstance()->executeS(
                'SELECT id_product, id_product_attribute, id_option, option_name, price
                 FROM `' . _DB_PREFIX_ . 'ld_advanced_options_cart_product`
                 WHERE id_cart = ' . (int) $cart->id
            );

            if (!$cart_rows) {
                return;
            }

            $order_details = Db::getInstance()->executeS(
                'SELECT id_order_detail, product_id, product_attribute_id
                 FROM `' . _DB_PREFIX_ . 'order_detail`
                 WHERE id_order = ' . (int) $order->id
            );

            if (!$order_details) {
                return;
            }

            // Associe chaque ligne panier (produit + déclinaison) à la bonne ligne de commande
            $detail_by_product = [];
            foreach ($order_details as $detail) {
                $key = (int) $detail['product_id'] . '_' . (int) $detail['product_attribute_id'];
                $detail_by_product[$key] = (int) $detail['id_order_detail'];
            }

            foreach ($cart_rows as $row) {
                $key = (int) $row['id_product'] . '_' . (int) $row['id_product_attribute'];

                if (!isset($detail_by_product[$key])) {
                    continue;
                }

                Db::getInstance()->insert('ld_advanced_options_order_product', [
                    'id_order' => (int) $order->id,
                    'id_order_detail' => $detail_by_product[$key],
                    'id_option' => (int) $row['id_option'],
                    'option_name' => pSQL($row['option_name']),
                    'price' => (float) $row['price'],
                    'date_add' => date('Y-m-d H:i:s'),
                ]);
            }
        } catch (\Throwable $e) {
            // Ne doit jamais empêcher la validation d'une commande : on logue et on continue.
            PrestaShopLogger::addLog('ld_advanced_options (actionValidateOrder) : ' . $e->getMessage(), 3);
        }
    }

    /**
     * ------------------------------------------------------------------
     * Affiche les options choisies sur la fiche commande en admin
     * (préparation / facturation).
     * ------------------------------------------------------------------
     */
    public function hookDisplayAdminOrder($params)
    {
        try {
            if (!isset($params['id_order'])) {
                return '';
            }

            $id_order = (int) $params['id_order'];

            $rows = Db::getInstance()->executeS(
                'SELECT lop.id_order_detail, lop.option_name, lop.price,
                        od.product_name, od.product_attribute_id
                 FROM `' . _DB_PREFIX_ . 'ld_advanced_options_order_product` lop
                 INNER JOIN `' . _DB_PREFIX_ . 'order_detail` od ON od.id_order_detail = lop.id_order_detail
                 WHERE lop.id_order = ' . $id_order . '
                 ORDER BY od.id_order_detail ASC, lop.option_name ASC'
            );

            if (!$rows) {
                return '';
            }

            $grouped = [];
            foreach ($rows as $row) {
                $grouped[(int) $row['id_order_detail']]['product_name'] = $row['product_name'];
                $grouped[(int) $row['id_order_detail']]['options'][] = [
                    'name' => $row['option_name'],
                    'price' => $this->ldFormatPrice((float) $row['price']),
                ];
            }

            $this->context->smarty->assign(['ld_order_options' => $grouped]);

            return $this->context->smarty->fetch(
                _PS_MODULE_DIR_ . $this->name . '/views/templates/admin/order_options.tpl'
            );
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('ld_advanced_options (displayAdminOrder) : ' . $e->getMessage(), 3);

            return '';
        }
    }

    /**
     * ------------------------------------------------------------------
     * Affiche les options choisies sous chaque ligne produit du panier
     * ------------------------------------------------------------------
     */
    public function hookDisplayCartExtraProductInfo($params)
    {
        try {
            if (!isset($params['product'])) {
                return '';
            }

            $product = $params['product'];
            $id_product = 0;
            $id_product_attribute = 0;

            if (is_array($product) || $product instanceof ArrayAccess) {
                $id_product = isset($product['id_product']) ? (int) $product['id_product'] : 0;
                $id_product_attribute = isset($product['id_product_attribute']) ? (int) $product['id_product_attribute'] : 0;
            } elseif (is_object($product)) {
                $id_product = isset($product->id_product) ? (int) $product->id_product : 0;
                $id_product_attribute = isset($product->id_product_attribute) ? (int) $product->id_product_attribute : 0;
            }

            if (!$id_product || !$this->context->cart || !$this->context->cart->id) {
                return '';
            }

            $rows = Db::getInstance()->executeS(
                'SELECT option_name, price
                 FROM `' . _DB_PREFIX_ . 'ld_advanced_options_cart_product`
                 WHERE id_cart = ' . (int) $this->context->cart->id . '
                 AND id_product = ' . $id_product . '
                 AND id_product_attribute = ' . $id_product_attribute . '
                 ORDER BY option_name ASC'
            );

            if (!$rows) {
                return '';
            }

            $options = [];
            foreach ($rows as $row) {
                $options[] = [
                    'name' => $row['option_name'],
                    'price' => $this->ldFormatPrice((float) $row['price']),
                ];
            }

            $this->context->smarty->assign(['ld_cart_options' => $options]);

            return $this->context->smarty->fetch(
                _PS_MODULE_DIR_ . $this->name . '/views/templates/hook/cart_options.tpl'
            );
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('ld_advanced_options (displayCartExtraProductInfo) : ' . $e->getMessage(), 3);

            return '';
        }
    }


    /**
     * ------------------------------------------------------------------
     * E-mail de confirmation de commande : ajout du détail des options choisies.
     *
     * PrestaShop ne donne pas le contenu HTML de l'e-mail au hook actionEmailSendBefore (il n'est
     * construit qu'après) : on mémorise donc ici la commande concernée, puis on complète le contenu
     * dans actionEmailAddAfterContent, déclenché une fois le corps de l'e-mail généré.
     * ------------------------------------------------------------------
     */
    private $ld_mail_order_id = 0;

    public function hookActionEmailSendBefore(&$params)
    {
        $this->ld_mail_order_id = 0;

        try {
            $template = isset($params['template']) ? (string) $params['template'] : '';

            // On ne touche qu'à l'email de confirmation de commande, pas aux autres
            // (facture, réexpédition mot de passe, etc.)
            if (strpos($template, 'order_conf') === false) {
                return true;
            }

            // Selon la version de PrestaShop, les variables s'appellent templateVars ou template_vars.
            $vars = [];
            foreach (['templateVars', 'template_vars'] as $key) {
                if (isset($params[$key]) && is_array($params[$key])) {
                    $vars = $params[$key];
                    break;
                }
            }

            $this->ld_mail_order_id = $this->guessIdOrderFromTemplateVars($vars);
        } catch (\Throwable $e) {
            // Ne doit jamais empêcher l'envoi d'un email.
            PrestaShopLogger::addLog('ld_advanced_options (actionEmailSendBefore) : ' . $e->getMessage(), 3);
        }

        return true;
    }

    public function hookActionEmailAddAfterContent(&$params)
    {
        $id_order = (int) $this->ld_mail_order_id;
        $this->ld_mail_order_id = 0;

        if (!$id_order) {
            return true;
        }

        try {
            $rows = Db::getInstance()->executeS(
                'SELECT lop.option_name, lop.price, od.product_name
                 FROM `' . _DB_PREFIX_ . 'ld_advanced_options_order_product` lop
                 INNER JOIN `' . _DB_PREFIX_ . 'order_detail` od ON od.id_order_detail = lop.id_order_detail
                 WHERE lop.id_order = ' . $id_order . '
                 ORDER BY od.id_order_detail ASC, lop.option_name ASC'
            );

            if (!$rows) {
                return true;
            }

            $grouped = [];
            foreach ($rows as $row) {
                $grouped[$row['product_name']][] = $row['option_name'] . ' (' . $this->ldFormatPrice((float) $row['price']) . ')';
            }

            // Bloc HTML autonome, inséré juste avant la fermeture du corps de l'email :
            // technique volontairement simple (pas besoin de connaître la structure exacte
            // du template mail, qui varie selon le thème / la langue).
            $html_block = '<div style="margin:20px 0;padding:15px;background:#f6f7f4;border-radius:6px;font-family:Arial,sans-serif;font-size:13px;">';
            $html_block .= '<strong>Options choisies :</strong><ul style="margin:8px 0 0;padding-left:18px;">';
            foreach ($grouped as $product_name => $options) {
                $html_block .= '<li><strong>' . htmlspecialchars($product_name, ENT_QUOTES, 'UTF-8') . '</strong><ul>';
                foreach ($options as $opt) {
                    $html_block .= '<li>' . htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') . '</li>';
                }
                $html_block .= '</ul></li>';
            }
            $html_block .= '</ul></div>';

            if (isset($params['template_html']) && is_string($params['template_html'])) {
                if (strpos($params['template_html'], '</body>') !== false) {
                    $params['template_html'] = str_replace('</body>', $html_block . '</body>', $params['template_html']);
                } else {
                    $params['template_html'] .= $html_block;
                }
            }

            if (isset($params['template_txt']) && is_string($params['template_txt'])) {
                $txt_block = "\n\nOptions choisies :\n";
                foreach ($grouped as $product_name => $options) {
                    $txt_block .= '- ' . $product_name . "\n";
                    foreach ($options as $opt) {
                        $txt_block .= '    * ' . $opt . "\n";
                    }
                }
                $params['template_txt'] .= $txt_block;
            }
        } catch (\Throwable $e) {
            // Ne doit jamais empêcher l'envoi d'un email : on logue et on laisse l'email
            // partir normalement, sans notre ajout.
            PrestaShopLogger::addLog('ld_advanced_options (actionEmailAddAfterContent) : ' . $e->getMessage(), 3);
        }

        return true;
    }

    private function guessIdOrderFromTemplateVars(array $vars)
    {
        foreach (['{id_order}', '{order_id}'] as $key) {
            if (!empty($vars[$key])) {
                return (int) $vars[$key];
            }
        }

        if (!empty($vars['{order_name}'])) {
            // La référence d'une commande scindée peut être suffixée (« ABCDEFGHI#2 ») : on retire le suffixe.
            $reference = preg_replace('/#\d+$/', '', (string) $vars['{order_name}']);
            $id_order = (int) Order::getIdByReference($reference);
            if ($id_order) {
                return $id_order;
            }
        }

        return 0;
    }
}
