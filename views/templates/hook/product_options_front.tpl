<div class="ld-front-options" data-register-url="{$ld_register_url|escape:'html':'UTF-8'}">
    {foreach from=$ld_front_option_groups item=group}
    <div class="ld-front-options__group">
        <p class="ld-front-options__group-title">{$group.name}</p>

        <div class="ld-front-options__list">
            {foreach from=$group.options item=opt}
            <label class="ld-front-options__item">
                <span class="ld-front-options__item-left">
                    <input type="checkbox"
                        class="ld-front-options__checkbox js-ld-front-option"
                        name="ld_selected_options[]"
                        value="{$opt.id_option}"
                        data-price="{$opt.price}"
                        data-price-formatted="{$opt.price_formatted}">
                    <span class="ld-front-options__item-name">
                        {$opt.name}
                        {if $opt.description}
                            <span class="ld-front-options__item-tooltip js-ld-tooltip" tabindex="0" role="button"
                                  aria-label="{l s='Plus d\'informations' mod='ld_advanced_options'}"
                                  data-description="{$opt.description|escape:'html':'UTF-8'}">(?)</span>
                        {/if}
                    </span>
                </span>
                <span class="ld-front-options__item-price">{$opt.price_formatted}</span>
            </label>
            {/foreach}
        </div>
    </div>
    {/foreach}
</div>
