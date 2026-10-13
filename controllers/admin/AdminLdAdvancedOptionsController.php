<?php
/**
 * Page "Catalogue > Options avancées".
 * On n'utilise volontairement pas le système HelperList/ObjectModel standard
 * (deux tables simples, CRUD basique) : renderList() est entièrement
 * réécrit pour afficher deux panneaux (Catégories / Options) en HTML direct.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminLdAdvancedOptionsController extends AdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'ld_advanced_options_option';
        $this->identifier = 'id_option';
        $this->lang = false;

        parent::__construct();

        $this->meta_title = $this->trans('Options avancées', [], 'Modules.Ldadvancedoptions.Admin');
    }

    /**
     * ------------------------------------------------------------------
     * Requêtes AJAX (appelées depuis l'onglet injecté sur la fiche produit)
     * Passe par le pipeline d'authentification standard de AdminController :
     * pas d'accès sans être connecté en tant qu'employé.
     * ------------------------------------------------------------------
     */
    public function initContent()
    {
        if (Tools::getValue('ajax') && Tools::getValue('action')) {
            $this->handleLdAjax();
            exit;
        }

        parent::initContent();
    }

    private function handleLdAjax()
    {
        $action = Tools::getValue('action');

        if ($action === 'get_product_panel') {
            $id_product = (int) Tools::getValue('id_product');
            header('Content-Type: text/html; charset=utf-8');
            echo $this->renderProductPanelAjax($id_product);

            return;
        }

        if ($action === 'save_product_options') {
            $id_product = (int) Tools::getValue('id_product');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($this->saveProductOptionsAjax($id_product));

            return;
        }

        if ($action === 'get_order_options') {
            $id_order = (int) Tools::getValue('id_order');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($this->getOrderOptionsAjax($id_order));

            return;
        }

        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Action inconnue.']);
    }

    /**
     * Renvoie, pour une commande donnée, la liste des options choisies groupées par
     * nom de produit - utilisé pour l'injection JS sur la fiche commande (page Twig,
     * pas de hook Smarty disponible à cet endroit précis).
     */
    private function getOrderOptionsAjax($id_order)
    {
        if (!$id_order) {
            return ['success' => false, 'error' => 'id_order manquant.'];
        }

        $rows = Db::getInstance()->executeS(
            'SELECT lop.id_order_detail, lop.option_name, lop.price
             FROM `' . _DB_PREFIX_ . 'ld_advanced_options_order_product` lop
             WHERE lop.id_order = ' . (int) $id_order . '
             ORDER BY lop.id_order_detail ASC, lop.option_name ASC'
        );

        if (!$rows) {
            return ['success' => true, 'lines' => []];
        }

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['id_order_detail']][] = $row['option_name']
                . ' (' . number_format((float) $row['price'], 2, ',', ' ') . ' €)';
        }

        $lines = [];
        foreach ($grouped as $id_order_detail => $options) {
            $lines[] = ['id_order_detail' => $id_order_detail, 'options' => $options];
        }

        return ['success' => true, 'lines' => $lines];
    }

    private function saveProductOptionsAjax($id_product)
    {
        if (!$id_product || !Validate::isLoadedObject(new Product($id_product))) {
            return ['success' => false, 'error' => 'Produit introuvable.'];
        }

        Db::getInstance()->delete('ld_advanced_options_product', 'id_product = ' . $id_product);

        // Chaque entrée reçue est désormais {id: X, value: "39.00"} et non plus un simple ID
        $entries = Tools::getValue('ld_product_options', []);
        $entries = is_array($entries) ? $entries : [];

        foreach ($entries as $entry) {
            $id_option = isset($entry['id']) ? (int) $entry['id'] : 0;

            if ($id_option <= 0) {
                continue;
            }

            $value_override = null;
            if (isset($entry['value']) && $entry['value'] !== '') {
                $value_override = (float) str_replace(',', '.', (string) $entry['value']);
            }

            Db::getInstance()->insert(
                'ld_advanced_options_product',
                [
                    'id_product' => $id_product,
                    'id_option' => $id_option,
                    'value_override' => $value_override,
                ],
                true, // null écrit NULL (pas de prix propre au produit)
                true,
                Db::INSERT_IGNORE
            );
        }

        return ['success' => true];
    }

    private function renderProductPanelAjax($id_product)
    {
        $product = null;

        if ($id_product) {
            $candidate = new Product($id_product, false, (int) $this->context->language->id);
            if (Validate::isLoadedObject($candidate)) {
                $product = $candidate;
            }
        }

        if (!$product) {
            return '<p class="alert alert-danger" style="margin: 20px;">Produit introuvable.</p>';
        }

        $categories = Db::getInstance()->executeS(
            'SELECT id_category, name FROM `' . _DB_PREFIX_ . 'ld_advanced_options_category` ORDER BY id_category ASC'
        );

        $options = Db::getInstance()->executeS(
            'SELECT id_option, id_category, name, value
             FROM `' . _DB_PREFIX_ . 'ld_advanced_options_option`
             ORDER BY name ASC'
        );

        $selected_rows = Db::getInstance()->executeS(
            'SELECT id_option, value_override FROM `' . _DB_PREFIX_ . 'ld_advanced_options_product`
             WHERE id_product = ' . $id_product
        );

        $override_by_option = [];
        foreach ($selected_rows ?: [] as $row) {
            // La clé existe = l'option est cochée pour ce produit ; la valeur peut être NULL
            // (pas d'override -> on affichera la valeur globale de l'option).
            $override_by_option[(int) $row['id_option']] = $row['value_override'];
        }

        // Toute la logique (coché ou non, quelle valeur afficher) est calculée ici,
        // pour garder le template le plus simple possible.
        $options_by_category = [];
        foreach ($options ?: [] as $opt) {
            $id_option = (int) $opt['id_option'];
            $is_selected = array_key_exists($id_option, $override_by_option);
            $override = $is_selected ? $override_by_option[$id_option] : null;
            $display_value = ($override !== null) ? (float) $override : (float) $opt['value'];

            $options_by_category[(int) $opt['id_category']][] = [
                'id_option' => $id_option,
                'name' => $opt['name'],
                'is_selected' => $is_selected,
                'display_value' => $display_value,
            ];
        }

        $this->context->smarty->assign([
            'ld_pt_categories' => $categories ?: [],
            'ld_pt_options_by_category' => $options_by_category,
        ]);

        return $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'ld_advanced_options/views/templates/admin/product_tab_panel.tpl'
        );
    }

    public function renderList()
    {
        $this->processLdActions();

        return $this->renderLdPanels();
    }

    /**
     * ------------------------------------------------------------------
     * Traitement des formulaires (ajout / suppression)
     * ------------------------------------------------------------------
     */
    private function processLdActions()
    {
        // --- Ajout d'une catégorie ---
        if (Tools::isSubmit('submitLdAddCategory')) {
            $name = trim(Tools::getValue('ld_category_name'));

            if ($name === '') {
                $this->errors[] = $this->trans('Le nom de la catégorie est obligatoire.', [], 'Modules.Ldadvancedoptions.Admin');
            } else {
                Db::getInstance()->insert('ld_advanced_options_category', [
                    'name' => pSQL($name),
                ]);

                if (empty($this->errors)) {
                    $this->redirectLd();
                }
            }
        }

        // --- Édition rapide du nom d'une catégorie ---
        if (Tools::isSubmit('submitLdEditCategory')) {
            $id_category = (int) Tools::getValue('id_category');
            $name = trim(Tools::getValue('ld_category_name_edit'));

            if ($name === '') {
                $this->errors[] = $this->trans('Le nom de la catégorie est obligatoire.', [], 'Modules.Ldadvancedoptions.Admin');
            } else {
                Db::getInstance()->update(
                    'ld_advanced_options_category',
                    ['name' => pSQL($name)],
                    'id_category = ' . $id_category
                );

                if (empty($this->errors)) {
                    $this->redirectLd();
                }
            }
        }

        // --- Suppression d'une catégorie ---
        if (Tools::isSubmit('deleteLdCategory')) {
            $id_category = (int) Tools::getValue('id_category');

            $nb_options = (int) Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'ld_advanced_options_option`
                 WHERE id_category = ' . $id_category
            );

            if ($nb_options > 0) {
                $this->errors[] = $this->trans('Impossible de supprimer cette catégorie : des options y sont encore rattachées.', [], 'Modules.Ldadvancedoptions.Admin');
            } else {
                Db::getInstance()->delete('ld_advanced_options_category', 'id_category = ' . $id_category);
                $this->redirectLd();
            }
        }

        // --- Ajout d'une option ---
        if (Tools::isSubmit('submitLdAddOption')) {
            $name = trim(Tools::getValue('ld_option_name'));
            $id_category = (int) Tools::getValue('ld_option_category');
            $value = (float) str_replace(',', '.', (string) Tools::getValue('ld_option_value'));
            $description = trim(Tools::getValue('ld_option_description'));

            if ($name === '') {
                $this->errors[] = $this->trans('Le nom de l\'option est obligatoire.', [], 'Modules.Ldadvancedoptions.Admin');
            } elseif (!$id_category) {
                $this->errors[] = $this->trans('Veuillez choisir une catégorie.', [], 'Modules.Ldadvancedoptions.Admin');
            } else {
                Db::getInstance()->insert('ld_advanced_options_option', [
                    'id_category' => $id_category,
                    'name' => pSQL($name),
                    'value' => $value,
                    'description' => pSQL($description),
                ]);

                if (empty($this->errors)) {
                    $this->redirectLd();
                }
            }
        }

        // --- Édition d'une option ---
        if (Tools::isSubmit('submitLdEditOption')) {
            $id_option = (int) Tools::getValue('id_option');
            $name = trim(Tools::getValue('ld_option_name_edit'));
            $id_category = (int) Tools::getValue('ld_option_category_edit');
            $value = (float) str_replace(',', '.', (string) Tools::getValue('ld_option_value_edit'));
            $description = trim(Tools::getValue('ld_option_description_edit'));

            if ($name === '') {
                $this->errors[] = $this->trans('Le nom de l\'option est obligatoire.', [], 'Modules.Ldadvancedoptions.Admin');
            } elseif (!$id_category) {
                $this->errors[] = $this->trans('Veuillez choisir une catégorie.', [], 'Modules.Ldadvancedoptions.Admin');
            } else {
                Db::getInstance()->update(
                    'ld_advanced_options_option',
                    [
                        'id_category' => $id_category,
                        'name' => pSQL($name),
                        'value' => $value,
                        'description' => pSQL($description),
                    ],
                    'id_option = ' . $id_option
                );

                if (empty($this->errors)) {
                    $this->redirectLd();
                }
            }
        }

        // --- Suppression d'une option ---
        if (Tools::isSubmit('deleteLdOption')) {
            $id_option = (int) Tools::getValue('id_option');
            Db::getInstance()->delete('ld_advanced_options_option', 'id_option = ' . $id_option);
            $this->redirectLd();
        }
    }

    private function redirectLd()
    {
        Tools::redirectAdmin(self::$currentIndex . '&token=' . $this->token);
    }

    /**
     * ------------------------------------------------------------------
     * Rendu des deux panneaux (Catégories / Options)
     * ------------------------------------------------------------------
     */
    private function renderLdPanels()
    {
        $categories = Db::getInstance()->executeS(
            'SELECT id_category, name
             FROM `' . _DB_PREFIX_ . 'ld_advanced_options_category`
             ORDER BY id_category ASC'
        );

        $options = Db::getInstance()->executeS(
            'SELECT o.id_option, o.id_category, o.name, o.value, o.description, c.name AS category_name
             FROM `' . _DB_PREFIX_ . 'ld_advanced_options_option` o
             LEFT JOIN `' . _DB_PREFIX_ . 'ld_advanced_options_category` c ON c.id_category = o.id_category
             ORDER BY o.id_category ASC, o.name ASC'
        );

        $this->context->smarty->assign([
            'ld_categories' => $categories ?: [],
            'ld_options' => $options ?: [],
            'ld_current_index' => self::$currentIndex,
            'ld_token' => $this->token,
        ]);

        return $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'ld_advanced_options/views/templates/admin/options.tpl'
        );
    }
}
