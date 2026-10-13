<?php
/**
 * Reçoit, juste après un ajout au panier réussi, les options sélectionnées pour
 * un produit précis. Recalcule le prix côté serveur (jamais confiance au client),
 * pose une SpecificPrice limitée à CE panier (id_cart) pour que le total du panier
 * reflète automatiquement les options, sans créer de faux produit.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Ld_Advanced_OptionsRegisteroptionsModuleFrontController extends ModuleFrontController
{
    public $auth = false;
    public $ssl = true;

    public function initContent()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $result = $this->handle();
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('ld_advanced_options (registeroptions) : ' . $e->getMessage(), 3);
            $result = ['success' => false, 'error' => 'internal_error'];
        }

        echo json_encode($result);
        exit;
    }

    private function handle()
    {
        $id_product = (int) Tools::getValue('id_product');
        $id_product_attribute = (int) Tools::getValue('id_product_attribute');
        $option_ids = Tools::getValue('option_ids', []);
        $option_ids = is_array($option_ids) ? array_map('intval', $option_ids) : [];

        if (!$id_product) {
            return ['success' => false, 'error' => 'missing_id_product'];
        }

        $product = new Product($id_product, false, (int) $this->context->language->id);
        if (!Validate::isLoadedObject($product)) {
            return ['success' => false, 'error' => 'product_not_found'];
        }

        $cart = $this->context->cart;
        if (!$cart || !$cart->id) {
            return ['success' => false, 'error' => 'no_cart'];
        }

        // On ne fait confiance qu'aux options réellement configurées pour CE produit
        // (jamais aux prix envoyés par le client) : on relit tout depuis notre table.
        $selected = [];
        if ($option_ids) {
            $placeholders = implode(',', $option_ids);
            $rows = Db::getInstance()->executeS(
                'SELECT o.id_option, o.name, IFNULL(pop.value_override, o.value) AS price
                 FROM `' . _DB_PREFIX_ . 'ld_advanced_options_product` pop
                 INNER JOIN `' . _DB_PREFIX_ . 'ld_advanced_options_option` o ON o.id_option = pop.id_option
                 WHERE pop.id_product = ' . $id_product . '
                 AND pop.id_option IN (' . $placeholders . ')'
            );
            $selected = $rows ?: [];
        }

        // Supprime toute sélection précédente pour cette ligne (nouvel ajout = état actuel)
        Db::getInstance()->delete(
            'ld_advanced_options_cart_product',
            'id_cart = ' . (int) $cart->id . ' AND id_product = ' . $id_product . ' AND id_product_attribute = ' . $id_product_attribute
        );

        $options_total_tax_incl = 0;
        foreach ($selected as $row) {
            $options_total_tax_incl += (float) $row['price'];

            Db::getInstance()->insert('ld_advanced_options_cart_product', [
                'id_cart' => (int) $cart->id,
                'id_product' => $id_product,
                'id_product_attribute' => $id_product_attribute,
                'id_option' => (int) $row['id_option'],
                'option_name' => pSQL($row['name']),
                'price' => (float) $row['price'],
                'date_add' => date('Y-m-d H:i:s'),
            ]);
        }

        if ($options_total_tax_incl <= 0) {
            // Aucune option (valide) sélectionnée : rien à faire, le produit garde son prix normal.
            return ['success' => true, 'options_total' => 0];
        }

        $this->applySpecificPrice($product, $id_product_attribute, $cart, $options_total_tax_incl);

        return ['success' => true, 'options_total' => $options_total_tax_incl];
    }

    /**
     * Pose (ou remplace) une SpecificPrice limitée à ce panier précis, pour que le prix
     * de cette ligne panier reflète le produit + les options, sans toucher au prix catalogue.
     *
     * Important : PrestaShop ne combine pas plusieurs règles de prix simultanément (une seule
     * "gagne", la plus spécifique - la nôtre, scopée au panier, l'emporterait sur une promo
     * générale). Pour ne pas faire disparaître une promo déjà active sur le produit, on reporte
     * sa remise EXACTE (montant, indépendamment de si elle est en % ou en valeur fixe à
     * l'origine) sur notre propre règle, appliquée au nouveau total (produit + options).
     */
    private function applySpecificPrice(Product $product, $id_product_attribute, Cart $cart, $options_total_tax_incl)
    {
        // Nos options sont saisies en admin comme des montants TTC (affichés tels quels en
        // front) ; specific_price.price s'interprète HT, donc on convertit via le taux de TVA
        // réel du produit plutôt qu'un taux en dur.
        $tax_rate = (float) $product->getTaxesRate();
        $options_total_tax_excl = $tax_rate > 0
            ? $options_total_tax_incl / (1 + $tax_rate / 100)
            : $options_total_tax_incl;

        // Prix HT plein (sans aucune réduction) et prix HT actuellement affiché (avec la/les
        // réduction(s) déjà actives) : sert à calculer le nouveau "prix plein" (HT) incluant
        // les options - specific_price.price s'interprète toujours en HT.
        $price_without_reduction_tax_excl = Product::getPriceStatic(
            (int) $product->id,
            false,
            $id_product_attribute ?: null,
            6,
            null,
            false,
            false // usereduc = false : prix plein, sans réduction
        );

        // Le montant de la remise, lui, doit être calculé en TTC : le champ "reduction" avec
        // reduction_tax=1 attend un montant déjà taxes incluses (pas besoin de le convertir
        // nous-même en HT, sous peine de diviser deux fois par le taux de TVA).
        $price_without_reduction_tax_incl = Product::getPriceStatic(
            (int) $product->id,
            true,
            $id_product_attribute ?: null,
            6,
            null,
            false,
            false
        );

        $price_with_reduction_tax_incl = Product::getPriceStatic(
            (int) $product->id,
            true,
            $id_product_attribute ?: null,
            6,
            null,
            false,
            true
        );

        $existing_reduction_tax_incl = round($price_without_reduction_tax_incl - $price_with_reduction_tax_incl, 6);

        // Nouveau prix plein (sans réduction) = prix plein d'origine (HT) + options (HT)
        $new_price_without_reduction = round($price_without_reduction_tax_excl + $options_total_tax_excl, 6);

        // Supprime une éventuelle SpecificPrice qu'on aurait déjà posée pour cette ligne,
        // avant d'en reposer une à jour (évite d'empiler des règles obsolètes).
        $existing = Db::getInstance()->executeS(
            'SELECT id_specific_price FROM `' . _DB_PREFIX_ . 'specific_price`
             WHERE id_cart = ' . (int) $cart->id . '
             AND id_product = ' . (int) $product->id . '
             AND id_product_attribute = ' . (int) $id_product_attribute
        );
        foreach ($existing ?: [] as $row) {
            $old = new SpecificPrice((int) $row['id_specific_price']);
            if (Validate::isLoadedObject($old)) {
                $old->delete();
            }
        }

        $specific_price = new SpecificPrice();
        $specific_price->id_shop = 0;
        $specific_price->id_shop_group = 0;
        $specific_price->id_currency = 0;
        $specific_price->id_country = 0;
        $specific_price->id_group = 0;
        $specific_price->id_customer = 0;
        $specific_price->id_product = (int) $product->id;
        $specific_price->id_product_attribute = (int) $id_product_attribute;
        $specific_price->id_cart = (int) $cart->id;
        $specific_price->price = $new_price_without_reduction;
        $specific_price->from_quantity = 1;
        $specific_price->reduction = $existing_reduction_tax_incl;
        $specific_price->reduction_tax = 1;
        $specific_price->reduction_type = 'amount';
        $specific_price->from = '0000-00-00 00:00:00';
        $specific_price->to = '0000-00-00 00:00:00';
        $specific_price->add();
    }
}
