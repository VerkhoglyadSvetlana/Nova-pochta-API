<select name="region" id="">
	<option selected disabled>{l s='Select region'}</option>
	{foreach from=$areas['data'] item=item}
		<option value="{$item['Ref']}">{$item['DescriptionRu']}</option>
	{/foreach}
</select>
<select name="city" id="">
	<option selected disabled>{l s='Select city'}</option>
	{foreach from=$areas['data'] item=item}
		<option value="{$item['Ref']}">{$item['DescriptionRu']}</option>
	{/foreach}
</select>
<select name="department" id="">
	<option selected disabled>{l s='Select department'}</option>
	{foreach from=$areas['data'] item=item}
		<option value="{$item['Ref']}">{$item['DescriptionRu']}</option>
	{/foreach}
</select>