<div style="padding: 20px;">
    {if $ld_pt_categories}
        {foreach from=$ld_pt_categories item=cat}
            {if isset($ld_pt_options_by_category[$cat.id_category])}
            <h4 style="text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; color: #666; margin-top: 25px; margin-bottom: 10px;">
                {$cat.name}
            </h4>
            <div class="ld-pt-option-list">
                {foreach from=$ld_pt_options_by_category[$cat.id_category] item=opt}
                <div class="ld-pt-option-row" style="display: flex; align-items: center; gap: 12px; padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
                    <input type="checkbox" value="{$opt.id_option}"
                        {if $opt.is_selected}checked{/if} class="js-ld-opt-checkbox" style="margin: 0;">
                    <span style="flex: 1;">{$opt.name}</span>
                    <div class="input-group" style="width: 130px;">
                        <input type="text" class="form-control js-ld-opt-value"
                            data-id-option="{$opt.id_option}"
                            value="{$opt.display_value|string_format:"%.2f"}">
                    </div>
                </div>
                {/foreach}
            </div>
            {/if}
        {/foreach}
    {else}
        <p class="alert alert-info">
            Aucune option configurée. Rendez-vous dans <strong>Catalogue &gt; Options avancées</strong> pour en créer.
        </p>
    {/if}

    <div class="js-ld-save-msg alert" style="display: none; margin-top: 15px;"></div>

    <button type="button" class="btn btn-primary js-ld-save-options" style="margin-top: 15px;">
        <i class="icon-save"></i> Enregistrer les options
    </button>
</div>
