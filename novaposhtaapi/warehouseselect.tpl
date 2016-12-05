<div class="delivery_options">
	<div class="delivery_method">Отделение</div>
	<div class="order_select">
		<select name="department" id="department" class="form-control">
			{foreach from=$warehouses['data'] item=item}
				<option {if isset($department) && $department == $item['DescriptionRu']} selected="selected"{/if} value="{$item['DescriptionRu']}">{$item['DescriptionRu']}</option>
			{/foreach}
		</select>
	</div>
</div>