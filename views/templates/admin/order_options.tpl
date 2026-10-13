<div class="panel">
    <div class="panel-heading">
        <i class="icon-list"></i> Options avancées commandées
    </div>
    <div class="panel-body">
        <table class="table">
            <thead>
                <tr>
                    <th>Produit</th>
                    <th>Options choisies</th>
                </tr>
            </thead>
            <tbody>
                {foreach from=$ld_order_options item=line}
                <tr>
                    <td>{$line.product_name}</td>
                    <td>
                        <ul style="margin: 0; padding-left: 18px;">
                            {foreach from=$line.options item=opt}
                            <li>{$opt.name} <span class="text-muted">({$opt.price})</span></li>
                            {/foreach}
                        </ul>
                    </td>
                </tr>
                {/foreach}
            </tbody>
        </table>
    </div>
</div>
