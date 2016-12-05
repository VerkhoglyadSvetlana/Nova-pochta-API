<?php
if (!defined('_PS_VERSION_'))
	exit;
require_once(dirname(__FILE__).'/classes/NovaPoshta.php');
class NovaPoshtaApi extends CarrierModule {
// class NovaPoshtaApi extends Module {
	public $id_carrier;
	public $NP;

	public function __construct() {
		$this->name = 'novaposhtaapi';
		$this->tab = 'shipping_logistics';
		$this->version = '0.1.0';
		$this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
		$this->author = 'WebKingStudio';
		$this->need_instance = 0;
		$this->bootstrap = true;

		parent::__construct();

		$this->displayName = $this->l('Nova Poshta Delivery');
		$this->description = $this->l('Добавляет выбор новой почты как службы доставки.');

		$this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
		$this->sender_city = Configuration::get('SENDER_CITY');
		$this->overcost = Configuration::get('OVERCOST');
		$this->api_key = Configuration::get('API_KEY');
		if(isset($this->api_key) && !empty($this->api_key)){
			$this->NP = new NovaPoshtaApi2($this->api_key);
		}

		if (self::isInstalled($this->name))
		{
			// Getting carrier list
			global $cookie;
			$carriers = Carrier::getCarriers($cookie->id_lang, true, false, false, NULL, PS_CARRIERS_AND_CARRIER_MODULES_NEED_RANGE);

			// Saving id carrier list
			$id_carrier_list = array();
			foreach($carriers as $carrier)
				$id_carrier_list[] .= $carrier['id_carrier'];

			// Testing if Carrier Id exists
			$warning = array();
			if (!in_array((int)(Configuration::get('MYCARRIER1_CARRIER_ID')), $id_carrier_list))
				$warning[] .= $this->l('"Нова Пошта"').' ';
			// if (!in_array((int)(Configuration::get('MYCARRIER2_CARRIER_ID')), $id_carrier_list))
			// 	$warning[] .= $this->l('"Carrier 2"').' ';
			if (!Configuration::get('OVERCOST'))
				$warning[] .= $this->l('"Нова Пошта Overcost"').' ';
			// if (!Configuration::get('MYCARRIER2_OVERCOST'))
			// 	$warning[] .= $this->l('"Carrier 2 Overcost"').' ';
			if (count($warning))
				$this->warning .= implode(' , ',$warning).$this->l('must be configured to use this module correctly').' ';
		}

	}

	public function install(){

		$carrierConfig = array(
			'name' => 'Нова Пошта',
			'id_tax_rules_group' => 0,
			'active' => true,
			'deleted' => 0,
			'shipping_handling' => false,
			'range_behavior' => 0,
			'delay' => array('ua' => 'Доставка майбутнього', 'ru' => 'Доставка будущего', Language::getIsoById(Configuration::get('PS_LANG_DEFAULT')) => 'Доставка майбутнього'),
			'id_zone' => 1,
			'is_module' => true,
			'shipping_external' => true,
			'external_module_name' => 'novaposhtaapi',
			'need_range' => true
		);

		$id_carrier1 = $this->installExternalCarrier($carrierConfig);
		Configuration::updateValue('MYCARRIER1_CARRIER_ID', (int)$id_carrier1);
		if (!parent::install()
			|| !Configuration::updateValue('OVERCOST', '')
			|| !Configuration::updateValue('API_KEY', '')
			|| !$this->registerhook('displaydepartmentbyref')
			|| !$this->registerhook('citySelect')
			|| !$this->registerhook('warehouseSelect')
			|| !$this->registerHook('updateCarrier'))
			return false;
		return true;
		if(Shop::isFeatureActive()){
			Shop::setContext(Shop::CONTEXT_ALL);
		}
	}

	public function uninstall(){
		// Uninstall
		if (!parent::uninstall()
			|| !Configuration::deleteByName('OVERCOST')
			|| !Configuration::deleteByName('API_KEY')
			|| !$this->unregisterHook('displaydepartmentbyref')
			|| !$this->unregisterHook('citySelect')
			|| !$this->unregisterHook('warehouseSelect')
			|| !$this->unregisterHook('updateCarrier'))
			return false;
		
		// Delete External Carrier
		$Carrier1 = new Carrier((int)(Configuration::get('MYCARRIER1_CARRIER_ID')));
		// $Carrier2 = new Carrier((int)(Configuration::get('MYCARRIER2_CARRIER_ID')));

		// If external carrier is default set other one as default
		if (Configuration::get('PS_CARRIER_DEFAULT') == (int)($Carrier1->id) || Configuration::get('PS_CARRIER_DEFAULT') == (int)($Carrier2->id))
		{
			global $cookie;
			$carriersD = Carrier::getCarriers($cookie->id_lang, true, false, false, NULL, PS_CARRIERS_AND_CARRIER_MODULES_NEED_RANGE);
			foreach($carriersD as $carrierD)
				if ($carrierD['active'] AND !$carrierD['deleted'] AND ($carrierD['name'] != $this->_config['name']))
					Configuration::updateValue('PS_CARRIER_DEFAULT', $carrierD['id_carrier']);
		}

		// Then delete Carrier
		$Carrier1->deleted = 1;
		if (!$Carrier1->update())
			return false;

		return true;
	}

	public static function installExternalCarrier($config)
	{
		$carrier = new Carrier();
		$carrier->name = $config['name'];
		$carrier->id_tax_rules_group = $config['id_tax_rules_group'];
		$carrier->id_zone = $config['id_zone'];
		$carrier->active = $config['active'];
		$carrier->deleted = $config['deleted'];
		$carrier->delay = $config['delay'];
		$carrier->shipping_handling = $config['shipping_handling'];
		$carrier->range_behavior = $config['range_behavior'];
		$carrier->is_module = $config['is_module'];
		$carrier->shipping_external = $config['shipping_external'];
		$carrier->external_module_name = $config['external_module_name'];
		$carrier->need_range = $config['need_range'];

		$languages = Language::getLanguages(true);
		foreach ($languages as $language)
		{
			if ($language['iso_code'] == Language::getIsoById(Configuration::get('PS_LANG_DEFAULT')))
				$carrier->delay[(int)$language['id_lang']] = $config['delay'][$language['iso_code']];
		}

		if ($carrier->add())
		{
			$groups = Group::getGroups(true);
			foreach ($groups as $group)
				Db::getInstance()->autoExecute(_DB_PREFIX_.'carrier_group', array('id_carrier' => (int)($carrier->id), 'id_group' => (int)($group['id_group'])), 'INSERT');

			$rangePrice = new RangePrice();
			$rangePrice->id_carrier = $carrier->id;
			$rangePrice->delimiter1 = '0';
			$rangePrice->delimiter2 = '10000';
			$rangePrice->add();

			$rangeWeight = new RangeWeight();
			$rangeWeight->id_carrier = $carrier->id;
			$rangeWeight->delimiter1 = '0';
			$rangeWeight->delimiter2 = '10000';
			$rangeWeight->add();

			$zones = Zone::getZones(true);
			foreach ($zones as $zone)
			{
				Db::getInstance()->autoExecute(_DB_PREFIX_.'carrier_zone', array('id_carrier' => (int)($carrier->id), 'id_zone' => (int)($zone['id_zone'])), 'INSERT');
				Db::getInstance()->autoExecuteWithNullValues(_DB_PREFIX_.'delivery', array('id_carrier' => (int)($carrier->id), 'id_range_price' => (int)($rangePrice->id), 'id_range_weight' => NULL, 'id_zone' => (int)($zone['id_zone']), 'price' => '0'), 'INSERT');
				Db::getInstance()->autoExecuteWithNullValues(_DB_PREFIX_.'delivery', array('id_carrier' => (int)($carrier->id), 'id_range_price' => NULL, 'id_range_weight' => (int)($rangeWeight->id), 'id_zone' => (int)($zone['id_zone']), 'price' => '0'), 'INSERT');
			}

			// Copy Logo
			if (!copy(dirname(__FILE__).'/carrier.jpg', _PS_SHIP_IMG_DIR_.'/'.(int)$carrier->id.'.jpg'))
				return false;

			// Return ID Carrier
			return (int)($carrier->id);
		}

		return false;
	}

	public function getContent(){
		$output = null;
		if(Tools::isSubmit('submit'.$this->name)){
			Configuration::updateValue('API_KEY', Tools::getValue('API_KEY'));
			Configuration::updateValue('OVERCOST', Tools::getValue('OVERCOST'));
			Configuration::updateValue('SENDER_CITY', Tools::getValue('SENDER_CITY'));
			$output .= $this->displayConfirmation($this->l('Settings updated'));
		}
		$this->api_key = Configuration::get('API_KEY');
		if(empty($this->api_key) || !Validate::isGenericName($this->api_key)){
			$output .= $this->displayError($this->l('Module needs API key to be filled'));
		}
		return $output.$this->displayForm();
	}

	public function displayForm(){
		// Get default language
		$default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
		// Init Fields form array
		$fields_form[0]['form'] = array(
			'legend' => array(
				'title' => $this->l('Settings'),
			),
			'input' => array(
				array(
					'type' => 'text',
					'label' => $this->l('Key'),
					'name' => 'API_KEY',
					'desc' => 'Insert API key'
				),
				array(
					'type' => 'text',
					'label' => $this->l('Over cost'),
					'name' => 'OVERCOST',
					'desc' => 'Basic delivery price'
				),
				array(
					'type' => 'text',
					'label' => $this->l('Sender city'),
					'name' => 'SENDER_CITY',
					// 'desc' => 'sender city'
				)
			),
			'submit' => array(
				'title' => $this->l('Save'),
				'class' => 'button'
			)
		);
		 
		$helper = new HelperForm();
		 
		// Module, token and currentIndex
		$helper->module = $this;
		$helper->name_controller = $this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
		 
		// Language
		$helper->default_form_language = $default_lang;
		$helper->allow_employee_form_lang = $default_lang;
		 
		// Title and toolbar
		$helper->title = $this->displayName;
		$helper->show_toolbar = true;        // false -> remove toolbar
		$helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
		$helper->submit_action = 'submit'.$this->name;
		$helper->toolbar_btn = array(
			'save' =>
			array(
				'desc' => $this->l('Save'),
				'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
				'&token='.Tools::getAdminTokenLite('AdminModules'),
			),
			'back' => array(
				'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
				'desc' => $this->l('Back to list')
			)
		);
		 
		// Load current value
		$helper->fields_value['API_KEY'] = Configuration::get('API_KEY');
		$helper->fields_value['OVERCOST'] = Configuration::get('OVERCOST');
		$helper->fields_value['SENDER_CITY'] = Configuration::get('SENDER_CITY');
		
		return $helper->generateForm($fields_form);
	}

	public function hookCitySelect(){
		$this->smarty->assign(array(
			'cities' => $this->NP->getCities()
		));
		return $this->display(__FILE__, 'cityselect.tpl');
	}

	public function hookWarehouseSelect($params){
		global $smarty;
 		$address = new Address(intval($params['cart']->id_address_delivery));
		// print_r($params['delivery']);
		// var_dump(Tools::getValue('city'));
		// print_r(get_object_vars(Context::getContext()->smarty));
		$city = $this->NP->getCity($address->city);
		$department = $this->context->cart->department;
		$this->smarty->assign(array(
			'delivery' => $params['delivery'],
			'department' => $department,
			'warehouses' => $this->NP->getWarehouses($city['data'][0]['Ref'])
		));
		return $this->display(__FILE__, 'warehouseselect.tpl');
	}

	// public function hookupdateCarrier($params){
	// 	if ((int)($params['id_carrier']) == (int)(Configuration::get('MYCARRIER1_CARRIER_ID')))
	// 		Configuration::updateValue('MYCARRIER1_CARRIER_ID', (int)($params['carrier']->id));
	// }

	public function getOrderShippingCost($params, $shipping_cost){
		// This example returns shipping cost with overcost set in the back-office, but you can call a webservice or calculate what you want before returning the final value to the Cart
		if ($this->id_carrier == (int)(Configuration::get('MYCARRIER1_CARRIER_ID')) && Configuration::get('OVERCOST') > 1)
			return (float)(Configuration::get('OVERCOST'));
		// $this->NP->getDocumentPrice(Configuration::get('SENDER_CITY'));
		// If the carrier is not known, you can return false, the carrier won't appear in the order process
		return false;
	}
	
	public function getOrderShippingCostExternal($params){
		// This example returns the overcost directly, but you can call a webservice or calculate what you want before returning the final value to the Cart
		if ($this->id_carrier == (int)(Configuration::get('MYCARRIER1_CARRIER_ID')) && Configuration::get('OVERCOST') > 1)
			return (float)(Configuration::get('OVERCOST'));

		// If the carrier is not known, you can return false, the carrier won't appear in the order process
		return false;
	}
}