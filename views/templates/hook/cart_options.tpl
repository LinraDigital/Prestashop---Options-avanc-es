{if $ld_cart_options}
    {foreach from=$ld_cart_options item=opt}
    <div class="product-line__item product-line__item--info ld-cart-option">
        <span class="product-line__item-label">Option :</span>
        <span class="product-line__item-value">{$opt.name} ({$opt.price})</span>
    </div>
    {/foreach}
{/if}
