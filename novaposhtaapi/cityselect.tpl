<datalist id="cities_list">
	{foreach from=$cities['data'] item=item}
		<option>{$item['DescriptionRu']}</option>
	{/foreach}
</datalist>