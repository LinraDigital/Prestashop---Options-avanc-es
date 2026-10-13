<?php
/**
 * Mise à niveau 2.10.0 : enregistre le hook actionEmailAddAfterContent (ajout des options
 * choisies dans l'e-mail de confirmation de commande).
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_10_0($module)
{
    return (bool) $module->registerHook('actionEmailAddAfterContent');
}
