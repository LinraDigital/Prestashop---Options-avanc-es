<div class="panel">
    <div class="panel-heading">
        <i class="icon-link"></i> Attribuer des options à un produit
    </div>
    <div class="panel-body">
        <form action="{$ld_current_index}&token={$ld_token}" method="get" class="form-inline">
            <input type="hidden" name="controller" value="AdminLdAdvancedOptions">
            <input type="hidden" name="token" value="{$ld_token}">
            <div class="form-group">
                <label style="margin-right: 10px;">ID du produit</label>
                <input type="number" name="id_product" class="form-control" style="width: 120px;"
                       value="{if $ld_assign_id_product}{$ld_assign_id_product}{/if}">
            </div>
            <button type="submit" class="btn btn-default">
                <i class="icon-search"></i> Charger
            </button>
        </form>

        {if $ld_assign_id_product && !$ld_assign_product}
            <p class="alert alert-warning" style="margin-top: 15px;">Aucun produit trouvé avec cet ID.</p>
        {/if}

        {if $ld_assign_product}
        <hr>
        <h4>{$ld_assign_product->name} <small class="text-muted">(ID {$ld_assign_id_product})</small></h4>

        <form method="post" action="{$ld_current_index}&token={$ld_token}">
            {if $ld_assign_categories}
                {foreach from=$ld_assign_categories item=cat}
                    {if isset($ld_assign_options_by_category[$cat.id_category])}
                    <h5>{$cat.name}</h5>
                    <div class="row" style="margin-bottom: 15px;">
                        {foreach from=$ld_assign_options_by_category[$cat.id_category] item=opt}
                        <div class="col-md-4" style="margin-bottom: 8px;">
                            <label class="checkbox" style="font-weight: normal;">
                                <input type="checkbox" name="ld_product_options[]" value="{$opt.id_option}"
                                    {if in_array($opt.id_option, $ld_assign_selected_ids)}checked{/if}>
                                {$opt.name}
                                <span class="text-muted">({$opt.value|string_format:"%.2f"} €)</span>
                            </label>
                        </div>
                        {/foreach}
                    </div>
                    {/if}
                {/foreach}
            {else}
                <p class="alert alert-info">Aucune option configurée pour le moment (voir le panneau ci-dessus).</p>
            {/if}

            <input type="hidden" name="id_product" value="{$ld_assign_id_product}">
            <button type="submit" name="submitLdAssignOptions" class="btn btn-primary">
                <i class="icon-save"></i> Enregistrer les options de ce produit
            </button>
        </form>
        {/if}
    </div>
</div>
