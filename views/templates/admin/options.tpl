<div class="panel">
    <div class="panel-heading">
        <i class="icon-folder-close"></i> Catégories
    </div>
    <div class="panel-body">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 80px;">ID</th>
                    <th>Nom de la catégorie</th>
                    <th style="width: 80px;"></th>
                </tr>
            </thead>
            <tbody>
                {if $ld_categories}
                    {foreach from=$ld_categories item=cat}
                    <tr>
                        <td>{$cat.id_category}</td>
                        <td>
                            <span class="ld-cat-name" id="ldCatName{$cat.id_category}">{$cat.name}</span>
                            <form class="ld-cat-edit-form" id="ldCatEditForm{$cat.id_category}" style="display:none;"
                                  action="{$ld_current_index}&token={$ld_token}" method="post">
                                <div class="input-group" style="max-width: 320px;">
                                    <input type="hidden" name="id_category" value="{$cat.id_category}">
                                    <input type="text" name="ld_category_name_edit" class="form-control"
                                           value="{$cat.name|escape:'html':'UTF-8'}">
                                    <span class="input-group-btn">
                                        <button type="submit" name="submitLdEditCategory" class="btn btn-primary btn-sm" title="Enregistrer">
                                            <i class="icon-check"></i>
                                        </button>
                                        <button type="button" class="btn btn-default btn-sm ld-cat-cancel" data-id="{$cat.id_category}" title="Annuler">
                                            <i class="icon-remove"></i>
                                        </button>
                                    </span>
                                </div>
                            </form>
                        </td>
                        <td class="text-right" style="white-space: nowrap;">
                            <div class="btn-group" role="group" style="display: flex !important; gap: 5px; justify-content: flex-end;">
                                <button type="button" class="btn btn-default btn-sm ld-cat-edit-toggle" data-id="{$cat.id_category}" title="Modifier" style="margin: 0 !important;">
                                    <i class="icon-pencil"></i>
                                </button>
                                <a href="{$ld_current_index}&deleteLdCategory&id_category={$cat.id_category}&token={$ld_token}"
                                   class="btn btn-default btn-sm"
                                   onclick="return confirm('Supprimer cette catégorie ?');" title="Supprimer" style="margin: 0 !important;">
                                    <i class="icon-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    {/foreach}
                {else}
                    <tr>
                        <td colspan="3">Aucune catégorie pour le moment.</td>
                    </tr>
                {/if}
            </tbody>
        </table>

        <form action="{$ld_current_index}&token={$ld_token}" method="post" class="form-inline" style="margin-top: 15px;">
            <div class="form-group">
                <input type="text" name="ld_category_name" class="form-control" placeholder="Nom de la catégorie (ex: Optimiser / Améliorer)">
            </div>
            <button type="submit" name="submitLdAddCategory" class="btn btn-primary">
                <i class="icon-plus"></i> Ajouter une catégorie
            </button>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-heading">
        <i class="icon-list"></i> Options
    </div>
    <div class="panel-body">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 80px;">ID</th>
                    <th>Catégorie</th>
                    <th>Nom de l'option</th>
                    <th style="width: 120px;">Valeur</th>
                    <th>Description</th>
                    <th style="width: 80px;"></th>
                </tr>
            </thead>
            <tbody>
                {if $ld_options}
                    {foreach from=$ld_options item=opt}
                    <tr>
                        <td>{$opt.id_option}</td>
                        <td>{$opt.category_name}</td>
                        <td>{$opt.name}</td>
                        <td>{$opt.value|string_format:"%.2f"} €</td>
                        <td>{if $opt.description}{$opt.description}{else}<span class="text-muted">—</span>{/if}</td>
                        <td class="text-right" style="white-space: nowrap;">
                            <div class="btn-group" role="group" style="display: flex !important; gap: 5px; justify-content: flex-end;">
                                <button type="button" class="btn btn-default btn-sm ld-opt-edit-btn"
                                    data-toggle="modal" data-target="#ldEditOptionModal"
                                    data-id="{$opt.id_option}"
                                    data-category="{$opt.id_category}"
                                    data-name="{$opt.name|escape:'html':'UTF-8'}"
                                    data-value="{$opt.value}"
                                    data-description="{$opt.description|escape:'html':'UTF-8'}"
                                    title="Modifier" style="margin: 0 !important;">
                                    <i class="icon-pencil"></i>
                                </button>
                                <a href="{$ld_current_index}&deleteLdOption&id_option={$opt.id_option}&token={$ld_token}"
                                   class="btn btn-default btn-sm"
                                   onclick="return confirm('Supprimer cette option ?');" title="Supprimer" style="margin: 0 !important;">
                                    <i class="icon-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    {/foreach}
                {else}
                    <tr>
                        <td colspan="6">Aucune option pour le moment.</td>
                    </tr>
                {/if}
            </tbody>
        </table>

        {if $ld_categories}
        <form action="{$ld_current_index}&token={$ld_token}" method="post" class="form-inline" style="margin-top: 15px;">
            <div class="form-group">
                <select name="ld_option_category" class="form-control">
                    {foreach from=$ld_categories item=cat}
                        <option value="{$cat.id_category}">{$cat.name}</option>
                    {/foreach}
                </select>
            </div>
            <div class="form-group">
                <input type="text" name="ld_option_name" class="form-control" placeholder="Nom de l'option (ex: Bagues de guidage)">
            </div>
            <div class="form-group">
                <input type="text" name="ld_option_value" class="form-control" placeholder="Valeur (ex: 39.00)" style="width: 120px;">
            </div>
            <div class="form-group">
                <input type="text" name="ld_option_description" class="form-control" placeholder="Description (optionnel, pour plus tard)" style="width: 260px;">
            </div>
            <button type="submit" name="submitLdAddOption" class="btn btn-primary">
                <i class="icon-plus"></i> Ajouter une option
            </button>
        </form>
        {else}
        <p class="alert alert-warning">Créez d'abord une catégorie ci-dessus avant de pouvoir ajouter une option.</p>
        {/if}
    </div>
</div>

<div class="modal fade" id="ldEditOptionModal" tabindex="-1" role="dialog" aria-labelledby="ldEditOptionModalLabel">
    <div class="modal-dialog" role="document">
        <form action="{$ld_current_index}&token={$ld_token}" method="post">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="ldEditOptionModalLabel">Modifier l'option</h4>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_option" id="ldEditOptionId" value="">

                    <div class="form-group">
                        <label>Catégorie</label>
                        <select name="ld_option_category_edit" id="ldEditOptionCategory" class="form-control">
                            {foreach from=$ld_categories item=cat}
                                <option value="{$cat.id_category}">{$cat.name}</option>
                            {/foreach}
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nom de l'option</label>
                        <input type="text" name="ld_option_name_edit" id="ldEditOptionName" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Valeur</label>
                        <input type="text" name="ld_option_value_edit" id="ldEditOptionValue" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" name="ld_option_description_edit" id="ldEditOptionDescription" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Annuler</button>
                    <button type="submit" name="submitLdEditOption" class="btn btn-primary">
                        <i class="icon-check"></i> Enregistrer
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.ld-cat-edit-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-id');
            document.getElementById('ldCatName' + id).style.display = 'none';
            document.getElementById('ldCatEditForm' + id).style.display = 'block';
        });
    });

    document.querySelectorAll('.ld-cat-cancel').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-id');
            document.getElementById('ldCatName' + id).style.display = 'inline';
            document.getElementById('ldCatEditForm' + id).style.display = 'none';
        });
    });

    document.querySelectorAll('.ld-opt-edit-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('ldEditOptionId').value = btn.getAttribute('data-id');
            document.getElementById('ldEditOptionCategory').value = btn.getAttribute('data-category');
            document.getElementById('ldEditOptionName').value = btn.getAttribute('data-name');
            document.getElementById('ldEditOptionValue').value = btn.getAttribute('data-value');
            document.getElementById('ldEditOptionDescription').value = btn.getAttribute('data-description');
        });
    });
});
</script>
