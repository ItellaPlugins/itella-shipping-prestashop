<div class="panel">
	{foreach from=$moduleMenu item=menuItem}
		<a class="btn btn-{if $menuItem.active}primary{else}default{/if}" href="{$menuItem.url|escape:'htmlall':'UTF-8'}">
			{$menuItem.label|escape:'htmlall':'UTF-8'}
		</a>
	{/foreach}
</div>
