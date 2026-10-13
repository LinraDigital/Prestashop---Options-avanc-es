<div class="panel">
    <div class="panel-heading">
        <i class="icon-cogs"></i> Options avancées
    </div>
    <div class="panel-body">
        <form method="post" action="">
            {if $ld_categories}
                {foreach from=$ld_categories item=cat}
                    {if isset($ld_options_by_category[$cat.id_category])}
                    <h4>{$cat.name}</h4>
                    <div class="row" style="margin-bottom: 20px;">
                        {foreach from=$ld_options_by_category[$cat.id_category] item=opt}
                        <div class="col-md-4" style="margin-bottom: 8px;">
                            <label class="checkbox" style="font-weight: normal;">
                                <input type="checkbox" name="ld_product_options[]" value="{$opt.id_option}"
                                    {if in_array($opt.id_option, $ld_selected_ids)}checked{/if}>
                                {$opt.name}
                                <span class="text-muted">({$opt.value|string_format:"%.2f"} €)</span>
                            </label>
                        </div>
                        {/foreach}
                    </div>
                    {/if}
                {/foreach}
            {else}
                <p class="alert alert-info">
                    Aucune option configurée pour le moment. Rendez-vous dans
                    <strong>Catalogue &gt; Options avancées</strong> pour en créer.
                </p>
            {/if}

            <input type="hidden" name="id_product" value="{$ld_id_product}">
            <button type="submit" name="submitLdOptionsProduct" class="btn btn-primary">
                <i class="icon-save"></i> Enregistrer les options
            </button>
        </form>
    </div>
</div>
