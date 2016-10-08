<?php
/**
 * @version		2.0.0
 * @package		Joomla
 * @subpackage	EShop
 * @author  	Giang Dinh Truong
 * @copyright	Copyright (C) 2012 Ossolution Team
 * @license		GNU/GPL, see LICENSE.php
 */
// no direct access
defined('_JEXEC') or die();

class EshopHelper
{
	
	/**
	 *
	 * Function to get configuration object
	 */
	public static function getConfig()
	{
		static $config;
		if (is_null($config))
		{
			$config = new stdClass();
			$db = JFactory::getDbo();
			$query = $db->getQuery(true);
			$query->select('config_key, config_value')
				->from('#__eshop_configs');
			$db->setQuery($query);
			$rows = $db->loadObjectList();
			foreach ($rows as $row)
			{
				$config->{$row->config_key} = $row->config_value;
			}
		}
		return $config;
	}
	
	/**
	 * 
	 * Function to get weight ids
	 * @return array
	 */
	public static function getWeightIds()
	{
		static $weightIds;
		if (is_null($weightIds))
		{
			$db = JFactory::getDbo();
			$query = $db->getQuery(true);
			$query->select('id')
				->from('#__eshop_weights');
			$db->setQuery($query);
			$weightIds = $db->loadColumn();
		}
		return $weightIds;
	}
	
	/**
	 *
	 * Function to get length ids
	 * @return array
	 */
	public static function getLengthIds()
	{
		static $lengthIds;
		if (is_null($lengthIds))
		{
			$db = JFactory::getDbo();
			$query = $db->getQuery(true);
			$query->select('id')
				->from('#__eshop_lengths');
			$db->setQuery($query);
			$lengthIds = $db->loadColumn();
		}
		return $lengthIds;
	}
	
	/**
	 * Function to check if joomla is version 3 or not
	 * @param number $minor
	 * @return boolean
	 */
	public static function isJ3($minor = 0)
	{
		static $status;
		if (!isset($status))
		{
			if (version_compare(JVERSION, '3.'.$minor.'.0', 'ge'))
			{
				$status = true;
			}
			else
			{
				$status = false;
			}
		}
		return $status;
	}

	/**
	 * 
	 * Function to check if is mobile or not
	 * @return boolean
	 */
	public static function isMobile()
	{
		return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
	}
	
	/**
	 *
	 * Function to get value of configuration variable
	 * @param string $configKey
	 * @param string $default
	 * @return string
	 */
	public static function getConfigValue($configKey, $default = null)
	{
		static $configValues;
		if (!isset($configValues["$configKey"]))
		{
			$db = JFactory::getDbo();
			$query = $db->getQuery(true);
			$query->select('config_value')
				->from('#__eshop_configs')
				->where('config_key = "' . $configKey . '"');
			$db->setQuery($query);
			$configValues[$configKey] = $db->loadResult();
		}
		return $configValues[$configKey] ? $configValues[$configKey] : $default;
	}
	
	/**
	 * Get the invoice number for an order
	 */
	public static function getInvoiceNumber()
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('MAX(invoice_number)')
			->from('#__eshop_orders');
		if (self::getConfigValue('reset_invoice_number'))
		{
			$query->where('YEAR(created_date) = YEAR(CURDATE())');
		}
		$db->setQuery($query);
		$invoiceNumber = intval($db->loadResult());
		if (!$invoiceNumber)
		{
			$invoiceNumber = intval(self::getConfigValue('invoice_start_number'));
			if (!$invoiceNumber)
				$invoiceNumber = 1;
		}
		else
		{
			$invoiceNumber++;
		}
		return $invoiceNumber;
	}
	
	/**
	 * Format invoice number
	 * @param string $invoiceNumber
	 * @param Object $config
	 */
	public static function formatInvoiceNumber($invoiceNumber, $createdDate)
	{
		return str_replace('[YEAR]', JHtml::date($createdDate, 'Y'), self::getConfigValue('invoice_prefix')) . str_pad($invoiceNumber, self::getConfigValue('invoice_number_length') ? self::getConfigValue('invoice_number_length') : 5, '0', STR_PAD_LEFT);
	}
	
	/**
	 * Get request data, used for RADList model
	 *
	 */
	public static function getRequestData()
	{
		$request = $_REQUEST;
		//Remove cookie vars from request
		$cookieVars = array_keys($_COOKIE);
		if (count($cookieVars))
		{
			foreach ($cookieVars as $key)
			{
				if (!isset($_POST[$key]) && !isset($_GET[$key]))
				{
					unset($request[$key]);
				}
			}
		}
		if (isset($request['start']) && !isset($request['limitstart']))
		{
			$request['limitstart'] = $request['start'];
		}
		if (!isset($request['limitstart']))
		{
			$request['limitstart'] = 0;
		}
		return $request;
	}
	
	public static function getCategory($categoryId, $processImage = true, $checkPermission = false)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('a.*, b.category_name, b.category_alias, b.category_desc, b.category_page_title, b.category_page_heading, b.meta_key, b.meta_desc')
			->from('#__eshop_categories AS a')
			->innerJoin('#__eshop_categorydetails AS b ON a.id = b.category_id')
			->where('a.id = ' . intval($categoryId))
			->where('a.published = 1')
			->where('b.language = "' . JFactory::getLanguage()->getTag() . '"');
		if ($checkPermission)
		{
			//Check viewable of customer groups
			$user = JFactory::getUser();
			if ($user->get('id'))
			{
				$customer = new EshopCustomer();
				$customerGroupId = $customer->getCustomerGroupId();
			}
			else
			{
				$customerGroupId = EshopHelper::getConfigValue('customergroup_id');
			}
			if (!$customerGroupId)
				$customerGroupId = 0;
			$query->where('((a.category_customergroups = "") OR (a.category_customergroups IS NULL) OR (a.category_customergroups = "' . $customerGroupId . '") OR (a.category_customergroups LIKE "' . $customerGroupId . ',%") OR (a.category_customergroups LIKE "%,' . $customerGroupId . ',%") OR (a.category_customergroups LIKE "%,' . $customerGroupId . '"))');
		}
		$db->setQuery($query);
		$category = $db->loadObject();
		if (is_object($category) && $processImage)
		{
			$imageSizeFunction = EshopHelper::getConfigValue('category_image_size_function', 'resizeImage');
			if ($category->category_image && JFile::exists(JPATH_ROOT.'/media/com_eshop/categories/'.$category->category_image))
			{
				$image = call_user_func_array(array('EshopHelper', $imageSizeFunction), array($category->category_image, JPATH_ROOT.'/media/com_eshop/categories/', EshopHelper::getConfigValue('image_category_width'), EshopHelper::getConfigValue('image_category_height')));
			}
			else
			{
				$image = call_user_func_array(array('EshopHelper', $imageSizeFunction), array('no-image.png', JPATH_ROOT.'/media/com_eshop/categories/', EshopHelper::getConfigValue('image_category_width'), EshopHelper::getConfigValue('image_category_height')));
			}
			$category->image = JUri::base(true) . '/media/com_eshop/categories/resized/' . $image;
		}
		return $category;
	}
	public static function getManufacturer($id, $processImage = true, $checkPermission = false)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('a.*, b.manufacturer_name, b.manufacturer_alias, b.manufacturer_desc, b.manufacturer_page_title, b.manufacturer_page_heading')
			->from('#__eshop_manufacturers AS a')
			->innerJoin('#__eshop_manufacturerdetails AS b ON (a.id = b.manufacturer_id)')
			->where('a.id = ' . (int)$id)
			->where('b.language = ' . $db->quote(JFactory::getLanguage()->getTag()));
		if ($checkPermission)
		{
			//Check viewable of customer groups
			$user = JFactory::getUser();
			if ($user->get('id'))
			{
				$customer = new EshopCustomer();
				$customerGroupId = $customer->getCustomerGroupId();
			}
			else
			{
				$customerGroupId = EshopHelper::getConfigValue('customergroup_id');
			}
			if (!$customerGroupId)
				$customerGroupId = 0;
			$query->where('((a.manufacturer_customergroups = "") OR (a.manufacturer_customergroups IS NULL) OR (a.manufacturer_customergroups = "' . $customerGroupId . '") OR (a.manufacturer_customergroups LIKE "' . $customerGroupId . ',%") OR (a.manufacturer_customergroups LIKE "%,' . $customerGroupId . ',%") OR (a.manufacturer_customergroups LIKE "%,' . $customerGroupId . '"))');
		}
		$db->setQuery($query);
		$manufacturer = $db->loadObject();
		if ($manufacturer && $processImage)
		{
			$imageSizeFunction = EshopHelper::getConfigValue('manufacturer_image_size_function', 'resizeImage');
			if ($manufacturer->manufacturer_image && JFile::exists(JPATH_ROOT.'/media/com_eshop/manufacturers/'.$manufacturer->manufacturer_image))
			{
				$image = call_user_func_array(array('EshopHelper', $imageSizeFunction), array($manufacturer->manufacturer_image, JPATH_ROOT . '/media/com_eshop/manufacturers/', EshopHelper::getConfigValue('image_manufacturer_width'), EshopHelper::getConfigValue('image_manufacturer_height')));
			}
			else
			{
				$image = call_user_func_array(array('EshopHelper', $imageSizeFunction), array('no-image.png', JPATH_ROOT . '/media/com_eshop/manufacturers/', EshopHelper::getConfigValue('image_manufacturer_width'), EshopHelper::getConfigValue('image_manufacturer_height')));
			}
			$manufacturer->image = JUri::base(true) . '/media/com_eshop/manufacturers/resized/' . $image;
		}
		return $manufacturer;
	}
	/**
	 * Get the associations.
	 *
	 */
	public static function getAssociations($id, $view = 'product')
	{
		$langCode = JFactory::getLanguage()->getTag();
		$associations = array();
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select($view . '_id, language')
			->from('#__eshop_' . $view . 'details')
			->where($view . '_id = ' . intval($id))
			->where('language != "' . $langCode . '"');
		$db->setQuery($query);
			
		try
		{
			$items = $db->loadObjectList('language');
		}
		catch (RuntimeException $e)
		{
			throw new Exception($e->getMessage(), 500);
		}
			
		if ($items)
		{
			foreach ($items as $tag => $item)
			{
				$associations[$tag] = $item;
			}
		}
		return $associations;
	}
	
	/**
	 * 
	 * Function to update currencies
	 * @param boolean $force
	 * @param int $timePeriod
	 * @param string $timeUnit
	 */
	public static function updateCurrencies($force = false, $timePeriod = 1, $timeUnit = 'day')
	{
		if (extension_loaded('curl'))
		{
			$db = JFactory::getDbo();
			$query = $db->getQuery(true);
			if ($force)
			{
				$query->select('*')
					->from('#__eshop_currencies')
					->where('currency_code != ' . $db->quote(self::getConfigValue('default_currency_code')));
			}
			else
			{
				$query->select('*')
					->from('#__eshop_currencies')
					->where('currency_code != ' . $db->quote(self::getConfigValue('default_currency_code')))
					->where('modified_date <= ' . $db->quote(date('Y-m-d H:i:s', strtotime('-' . (int)$timePeriod .' ' . $timeUnit))));
			}
			$db->setQuery($query);
			$rows = $db->loadObjectList();
			if (count($rows))
			{
				$data = array();
				foreach ($rows as $row)
				{
					$data[] = self::getConfigValue('default_currency_code') . $row->currency_code . '=X';
				}
				$curl = curl_init();
				curl_setopt($curl, CURLOPT_URL, 'http://download.finance.yahoo.com/d/quotes.csv?s=' . implode(',', $data) . '&f=sl1&e=.csv');
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
				$content = curl_exec($curl);
				curl_close($curl);
				$lines = explode("\n", trim($content));
				foreach ($lines as $line)
				{
					$currency = substr($line, 4, 3);
					$value = substr($line, 11, 6);
					if ((float)$value)
					{
						$query->clear();
						$query->update('#__eshop_currencies')
						->set('exchanged_value = ' . (float)$value)
						->set('modified_date = ' . $db->quote(date('Y-m-d H:i:s')))
						->where('currency_code = ' . $db->quote($currency));
						$db->setQuery($query);
						$db->query();
					}
				}
			}
			$query->clear();
			$query->update('#__eshop_currencies')
				->set('exchanged_value = 1.00000')
				->set('modified_date = ' . $db->quote(date('Y-m-d H:i:s')))
				->where('currency_code = ' . $db->quote(self::getConfigValue('default_currency_code')));
			$db->setQuery($query);
			$db->query();
		}
	}
	
	/**
	 * 
	 * Function to update hits for category/manufacturer/product
	 * @param int $id
	 * @param string $element
	 */
	public static function updateHits($id, $element)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->update('#__eshop_' . $element)
			->set('hits = hits + 1')
			->where('id = ' . intval($id));
		$db->setQuery($query);
		$db->query();
	} 
	
	/**
	 * 
	 * Function to get name of a specific stock status
	 * @param int $stockStatusId
	 * @param string $langCode
	 * @return string
	 */
	public static function getStockStatusName($stockStatusId, $langCode)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('stockstatus_name')
			->from('#__eshop_stockstatusdetails')
			->where('stockstatus_id = ' . intval($stockStatusId))
			->where('language = "' . $langCode . '"');
		$db->setQuery($query);
		return $db->loadResult();		
	}
	
	/**
	 *
	 * Function to get name of a specific order status
	 * @param int $orderStatusId
	 * @param string $langCode
	 * @return string
	 */
	public static function getOrderStatusName($orderStatusId, $langCode)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('orderstatus_name')
			->from('#__eshop_orderstatusdetails')
			->where('orderstatus_id = ' . intval($orderStatusId))
			->where('language = "' . $langCode . '"');
		$db->setQuery($query);
		return $db->loadResult();
	}
	
	/**
	 *
	 * Function to get unit of a specific length
	 * @param int $lengthId
	 * @param string $langCode
	 * @return string
	 */
	public static function getLengthUnit($lengthId, $langCode)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('length_unit')
			->from('#__eshop_lengthdetails')
			->where('length_id = ' . intval($lengthId))
			->where('language = "' . $langCode . '"');
		$db->setQuery($query);
		return $db->loadResult();
	}
	
	/**
	 *
	 * Function to get unit of a specific weight
	 * @param int $weightId
	 * @param string $langCode
	 * @return string
	 */
	public static function getWeightUnit($weightId, $langCode)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('weight_unit')
			->from('#__eshop_weightdetails')
			->where('weight_id = ' . intval($weightId))
			->where('language = "' . $langCode . '"');
		$db->setQuery($query);
		return $db->loadResult();
	}
	
	/**
	 * 
	 * Function to get payment title
	 * @param string $paymentName
	 * @return string
	 */
	public static function getPaymentTitle($paymentName)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('title')
			->from('#__eshop_payments')
			->where('name = "' . $paymentName . '"');
		$db->setQuery($query);
		return $db->loadResult();
	}
	
	/**
	 *
	 * Function to get shipping title
	 * @param string $shippingName
	 * @return string
	 */
	public static function getShippingTitle($shippingName)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('title')
			->from('#__eshop_shippings')
			->where('name = "' . $shippingName . '"');
		$db->setQuery($query);
		return $db->loadResult();
	}
	
	/**
	 * 
	 * Function to get all available languages
	 * @return languages object list
	 */
	public static function getLanguages()
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('lang_id, lang_code, title')
			->from('#__languages')
			->where('published = 1')
			->order('ordering');
		$db->setQuery($query);
		$languages = $db->loadObjectList();
		return $languages;
	}
	
	/**
	 *
	 * Function to get flags for languages
	 */
	public static function getLanguageData()
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$languageData = array();
		$query->select('image, lang_code, title')
			->from('#__languages')
			->where('published = 1')
			->order('ordering');
		$db->setQuery($query);
		$rows = $db->loadObjectList();
		for ($i = 0; $n = count($rows), $i < $n; $i++)
		{
			$languageData['flag'][$rows[$i]->lang_code] = $rows[$i]->image . '.png';
			$languageData['title'][$rows[$i]->lang_code] = $rows[$i]->title;
		}
		return $languageData;
	}
	
	/**
	 * 
	 * Function to get active language
	 */
	public static function getActiveLanguage()
	{
		$langCode = JFactory::getLanguage()->getTag();
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')
			->from('#__languages')
			->where('lang_code = ' . $db->quote($langCode));
		$db->setQuery($query);
		return $db->loadObject();
	}
	
	/**
	 * 
	 * Function to get attached lang link
	 * @return string
	 */
	public static function getAttachedLangLink()
	{
		$attachedLangLink = '';
		if (JLanguageMultilang::isEnabled())
		{
			$activeLanguage = self::getActiveLanguage();
			$attachedLangLink = '&lang=' . $activeLanguage->sef;
		}
		return $attachedLangLink;
	}
	
	/**
	 *
	 * Function to get attribute groups
	 * @return attribute groups object list
	 */
	public static function getAttributeGroups($langCode = '')
	{
		if ($langCode == '')
		{
			$langCode = JComponentHelper::getParams('com_languages')->get('site', 'en-GB');
		}
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('a.id, b.attributegroup_name')
			->from('#__eshop_attributegroups AS a')
			->innerJoin('#__eshop_attributegroupdetails AS b ON (a.id = b.attributegroup_id)')
			->where('a.published = 1')
			->where('b.language = "' . $langCode . '"')
			->order('a.ordering');
		$db->setQuery($query);
		return $db->loadObjectList();
	}
	
	/**
	 *
	 * Function to get attributes for a specific products
	 * @param int $productId
	 * @param int $attributeGroupId
	 * @return attribute object list
	 */
	public static function getAttributes($productId, $attributeGroupId, $langCode = '')
	{
		if ($langCode == '')
		{
			$langCode = JComponentHelper::getParams('com_languages')->get('site', 'en-GB');
		}
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('ad.attribute_name, pad.value')
			->from('#__eshop_attributes AS a')
			->innerJoin('#__eshop_attributedetails AS ad ON (a.id = ad.attribute_id)')
			->innerJoin('#__eshop_productattributes AS pa ON (a.id = pa.attribute_id)')
			->innerJoin('#__eshop_productattributedetails AS pad ON (pa.id = pad.productattribute_id)')
			->where('a.attributegroup_id = ' . intval($attributeGroupId))
			->where('a.published = 1')
			->where('pa.published = 1')
			->where('pa.product_id = ' . intval($productId))
			->where('ad.language = "' . $langCode . '"')
			->where('pad.language = "' . $langCode . '"')
			->order('a.ordering, pad.value');
		$db->setQuery($query);
		return $db->loadObjectList();
	}
	
	/**
	 *
	 * Function to get attribute group for a specific attribute
	 * @param unknown $attributeId
	 * @return Ambigous <mixed, NULL>
	 */
	public static function getAttributeAttributeGroup($attributeId, $langCode = '')
	{
		if ($langCode == '')
		{
			$langCode = JComponentHelper::getParams('com_languages')->get('site', 'en-GB');
		}
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('a.attributegroup_id, b.attributegroup_name')
			->from('#__eshop_attributes AS a')
			->innerJoin('#__eshop_attributegroupdetails AS b ON (a.attributegroup_id = b.attributegroup_id)')
			->where('a.id = ' . intval($attributeId))
			->where('b.language = "' . $langCode . '"');
		$db->setQuery($query);
		return $db->loadObject();
	}
	
	/**
	 * 
	 * Function to get Categories
	 * @param int $categoryId
	 * @return categories object list
	 */
	public static function getCategories($categoryId = 0, $langCode = '', $checkPermission = false)
	{
		if ($langCode == '')
		{
			$langCode = JComponentHelper::getParams('com_languages')->get('site', 'en-GB');
		}
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('a.id, a.category_parent_id, a.category_image, b.category_name, b.category_desc')
			->from('#__eshop_categories AS a')
			->innerJoin('#__eshop_categorydetails AS b ON (a.id = b.category_id)')
			->where('a.category_parent_id = ' . intval($categoryId))
			->where('a.published = 1')
			->where('b.language = "' . $langCode . '"')
			->order('a.ordering');
		if ($checkPermission)
		{
			//Check viewable of customer groups
			$user = JFactory::getUser();
			if ($user->get('id'))
			{
				$customer = new EshopCustomer();
				$customerGroupId = $customer->getCustomerGroupId();
			}
			else
			{
				$customerGroupId = EshopHelper::getConfigValue('customergroup_id');
			}
			if (!$customerGroupId)
				$customerGroupId = 0;
			$query->where('((a.category_customergroups = "") OR (a.category_customergroups IS NULL) OR (a.category_customergroups = "' . $customerGroupId . '") OR (a.category_customergroups LIKE "' . $customerGroupId . ',%") OR (a.category_customergroups LIKE "%,' . $customerGroupId . ',%") OR (a.category_customergroups LIKE "%,' . $customerGroupId . '"))');
		}
		$db->setQuery($query);
		return $db->loadObjectList();
	}
	
	/**
	 * 
	 * Function to get all child categories levels of a category
	 * @param int $id
	 * @return array
	 */
	public static function getAllChildCategories($id)
	{
		$data = array();
		if ($results = self::getCategories($id, '', true))
		{
			foreach ($results as $result)
			{
				$data[] = $result->id;
				$subCategories = self::getAllChildCategories($result->id);
				if ($subCategories)
				{
					$data = array_merge($data, $subCategories);
				}
			}
		}
		return $data;
	}
	
	/**
	 * 
	 * Function to get number products for a specific category
	 * @param int $categoryId
	 * @return int
	 */
	public static function getNumCategoryProducts($categoryId, $allLevels = false)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		if ($allLevels)
		{
			$categoryIds = array_merge(array($categoryId), EshopHelper::getAllChildCategories($categoryId));
		}
		else
		{
			$categoryIds = array($categoryId);
		}
		$query->select('COUNT(DISTINCT(a.id))')
			->from('#__eshop_products AS a')
			->innerJoin('#__eshop_productcategories AS b ON (a.id = b.product_id)')
			->where('a.published = 1')
			->where('b.category_id IN (' . implode(',', $categoryIds) . ')');
		//Check out of stock
		if (EshopHelper::getConfigValue('hide_out_of_stock_products'))
		{
			$query->where('a.product_quantity > 0');
		}
		$db->setQuery($query);
		return $db->loadResult();
	}
	
	/**
	 *
	 * Function to get list of parent categories
	 * @param int $categoryId
	 * @return array of object
	 */
	public static function getParentCategories($categoryId, $langCode = '')
	{
		if ($langCode == '')
		{
			$langCode = JComponentHelper::getParams('com_languages')->get('site', 'en-GB');
		}
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$parentCategories = array();
		while (true)
		{
			$query->clear();
			$query->select('a.id, a.category_parent_id, b.category_name')
				->from('#__eshop_categories AS a')
				->innerJoin('#__eshop_categorydetails AS b ON (a.id = b.category_id)')
				->where('a.id = ' . intval($categoryId))
				->where('a.published = 1')
				->where('b.language = "' . $langCode . '"');
			$db->setQuery($query);
			$row = $db->loadObject();
			if ($row)
			{				
				$parentCategories[] = $row;
				$categoryId = $row->category_parent_id;
			}
			else
			{
				break;
			}
		}
		return $parentCategories;
	}
	
	/**
	 *
	 * Function to get values for a specific option
	 * @param int $optionId
	 */
	public static function getOptionValues($optionId, $langCode = '', $multipleLanguage = 'true')
	{
		if ($langCode == '')
		{
			$langCode = JComponentHelper::getParams('com_languages')->get('site', 'en-GB');
		}
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$languages = self::getLanguages();
		if (JLanguageMultilang::isEnabled() && count($languages) > 1 && $multipleLanguage)
		{
			$query->select('*')
				->from('#__eshop_optionvalues')
				->where('option_id = ' . intval($optionId))
				->order('ordering');
			$db->setQuery($query);
			$rows = $db->loadObjectList();
			if (count($rows))
			{
				for ($i = 0; $n = count($rows), $i < $n; $i++)
				{
					$query->clear();
					$query->select('*')
						->from('#__eshop_optionvaluedetails')
						->where('option_id = ' . intval($optionId))
						->where('optionvalue_id = ' . intval($rows[$i]->id));
						$db->setQuery($query);
						$detailsRows = $db->loadObjectList('language');
						if (count($detailsRows))
						{
							foreach ($detailsRows as $language => $detailsRow)
							{
								$rows[$i]->{'optionvaluedetails_id_' . $language} = $detailsRow->id;
								$rows[$i]->{'value_' . $language} = $detailsRow->value;
							}
						}
					}
			}
		}
		else
		{
			$query->select('ov.*, ovd.id AS optionvaluedetails_id, ovd.value, ovd.language')
				->from('#__eshop_optionvalues AS ov')
				->innerJoin('#__eshop_optionvaluedetails AS ovd ON (ov.id = ovd.optionvalue_id)')
				->where('ov.option_id = ' . intval($optionId))
				->where('ovd.language = "' . $langCode . '"')
				->order('ov.ordering');
			$db->setQuery($query);
			$rows = $db->loadObjectList();
		}
		return $rows;
	}
	
	/**
	 *
	 * Function to get information for a specific product
	 * @param int $productId
	 */
	public static function getProduct($productId, $langCode = '')
	{
		if ($langCode == '')
		{
			$langCode = JComponentHelper::getParams('com_languages')->get('site', 'en-GB');
		}
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('a.*, b.product_name, b.product_alias, b.product_desc, b.product_short_desc, b.meta_key, b.meta_desc')
			->from('#__eshop_products AS a')
			->innerJoin('#__eshop_productdetails AS b ON (a.id = b.product_id)')
			->where('b.language = "' . $langCode . '"')
			->where('a.id = ' . intval($productId));
		$db->setQuery($query);
		return $db->loadObject();
	}

	/**
	 * 
	 * Function to get categories for a specific product
	 * @param int $productId        	
	 */
	public static function getProductCategories($productId, $langCode = '')
	{
		if ($langCode == '')
		{
			$langCode = JComponentHelper::getParams('com_languages')->get('site', 'en-GB');
		}
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('c.id, cd.category_name')
			->from('#__eshop_categories AS c')
			->innerJoin('#__eshop_categorydetails AS cd ON (c.id = cd.category_id)')
			->innerJoin('#__eshop_productcategories AS pc ON (c.id = pc.category_id)')
			->where('pc.product_id = ' . intval($productId))
			->where('cd.language = "' . $langCode . '"');
		$db->setQuery($query);
		$rows = $db->loadObjectList();
		return $rows;
	}
	
	/**
	 * 
	 * Function to get category id for a specific product
	 * @param int $productId
	 * @return int
	 */
	public static function getProductCategory($productId)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('a.category_id')
			->from('#__eshop_productcategories AS a')
			->innerJoin('#__eshop_categories AS b ON (a.category_id = b.id)')
			->where('a.product_id = ' . intval($productId))
			->where('b.published = 1');
		$db->setQuery($query);
		return $db->loadResult();
	}

	/**
	 *
	 * Function to get manufacturer for a specific product
	 * @param int $productId
	 * @return manufacturer object
	 */
	public static function getProductManufacturer($productId, $langCode = '')
	{
		if ($langCode == '')
		{
			$langCode = JComponentHelper::getParams('com_languages')->get('site', 'en-GB');
		}
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('m.id, m.manufacturer_email, md.manufacturer_name')
			->from('#__eshop_products AS p')
			->innerJoin('#__eshop_manufacturers AS m ON (p.manufacturer_id = m.id)')
			->innerJoin('#__eshop_manufacturerdetails AS md ON (m.id = md.manufacturer_id)')
			->where('p.id = ' . intval($productId))
			->where('md.language = "' . $langCode . '"');
		$db->setQuery($query);
		$row = $db->loadObject();
		return $row;
	}

	/**
	 *
	 * Function to get related products for a specific product
	 * @param int $productId
	 */
	public static function getProductRelations($productId, $langCode = '')
	{
		if ($langCode == '')
		{
			$langCode = JComponentHelper::getParams('com_languages')->get('site', 'en-GB');
		}
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('p.*, pd.product_name, pd.product_alias, pd.product_desc, pd.product_short_desc, pd.meta_key, pd.meta_desc')
			->from('#__eshop_products AS p')
			->innerJoin('#__eshop_productdetails AS pd ON (p.id = pd.product_id)')
			->innerJoin('#__eshop_productrelations AS pr ON (p.id = pr.related_product_id)')
			->where('pr.product_id = ' . intval($productId))
			->where('pd.language = "' . $langCode . '"')
			->order('p.ordering');
		$db->setQuery($query);
		$rows = $db->loadObjectList();
		return $rows;
	}
	
	/**
	 *
	 * Function to get product downloads for a specific product
	 * @param int $productId
	 */
	public static function getProductDownloads($productId, $langCode = '')
	{
		if ($langCode == '')
		{
			$langCode = JComponentHelper::getParams('com_languages')->get('site', 'en-GB');
		}
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('a.id, a.filename, a.total_downloads_allowed, b.download_name')
			->from('#__eshop_downloads AS a')
			->innerJoin('#__eshop_downloaddetails AS b ON (a.id = b.download_id)')
			->innerJoin('#__eshop_productdownloads AS c ON (a.id = c.download_id)')
			->where('c.product_id = ' . intval($productId))
			->where('b.language = "' . $langCode . '"');
		$db->setQuery($query);
		$rows = $db->loadObjectList();
		return $rows;
	}

	/**
	 * 
	 * Function to reviews for a specific product
	 * @param int $productId
	 * @return reviews object list
	 */
	public static function getProductReviews($productId)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')
			->from('#__eshop_reviews')
			->where('product_id = ' . intval($productId))
			->where('published = 1');
		$db->setQuery($query);
		$rows = $db->loadObjectList();
		return $rows;
	}
	
	/**
	 * 
	 * Function to get average rating for a specific product 
	 * @param int $productId
	 * @return average rating
	 */
	public static function getProductRating($productId)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('AVG(rating) as rating')
			->from('#__eshop_reviews')
			->where('product_id = ' . intval($productId))
			->where('published = 1');
		$db->setQuery($query);
		$rating = $db->loadResult();
		return $rating;
	}

	/**
	 *
	 * Function to get attributes for a specific product
	 * @param int $productId
	 */
	public static function getProductAttributes($productId, $langCode = '')
	{
		if ($langCode == '')
		{
			$langCode = JComponentHelper::getParams('com_languages')->get('site', 'en-GB');
		}
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$languages = self::getLanguages();
		if (JLanguageMultilang::isEnabled() && count($languages) > 1)
		{
			$query->select('a.id, pa.id AS productattribute_id, pa.published')
				->from('#__eshop_attributes AS a')
				->innerJoin('#__eshop_productattributes AS pa ON (a.id = pa.attribute_id)')
				->where('pa.product_id = ' . intval($productId))
				->order('a.ordering');
			$db->setQuery($query);
			$rows = $db->loadObjectList();
			if (count($rows))
			{
				for ($i = 0; $n = count($rows), $i < $n; $i++)
				{
					$query->clear();
					$query->select('*')
						->from('#__eshop_productattributedetails')
						->where('productattribute_id = ' . intval($rows[$i]->productattribute_id));
					$db->setQuery($query);
					$detailsRows = $db->loadObjectList('language');
					if (count($detailsRows))
					{
						foreach ($detailsRows as $language => $detailsRow)
						{
							$rows[$i]->{'productattributedetails_id_' . $language} = $detailsRow->id;
							$rows[$i]->{'value_' . $language} = $detailsRow->value;
						}
					}
				}
			}
		}
		else
		{
			$query->select('a.id, pa.id AS productattribute_id, pa.published, pad.id AS productattributedetails_id ,pad.value')
				->from('#__eshop_attributes AS a')
				->innerJoin('#__eshop_productattributes AS pa ON (a.id = pa.attribute_id)')
				->innerJoin('#__eshop_productattributedetails AS pad ON (pa.id = pad.productattribute_id)')
				->where('pa.product_id = ' . intval($productId))
				->where('pad.language = "' . $langCode . '"')
				->order('a.ordering');
			$db->setQuery($query);
			$rows = $db->loadObjectList();
		}
		return $rows;
	}

	/**
	 *
	 * Function to get options for a specific product
	 * @param int $productId
	 */
	public static function getProductOptions($productId, $langCode = '')
	{
		if ($langCode == '')
		{
			$langCode = JComponentHelper::getParams('com_languages')->get('site', 'en-GB');
		}
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('o.id, o.option_type, o.option_image, od.option_name, od.option_desc, po.required, po.id AS product_option_id')
			->from('#__eshop_options AS o')
			->innerJoin('#__eshop_optiondetails AS od ON (o.id = od.option_id)')
			->innerJoin('#__eshop_productoptions AS po ON (o.id = po.option_id)')
			->where('po.product_id = ' . intval($productId))
			->where('od.language = "' . $langCode . '"')
			->order('o.ordering');
		$db->setQuery($query);
		$rows = $db->loadObjectList();
		return $rows;
	}

	/**
	 * 
	 * Function to get option values
	 * @param int $productId
	 * @param int $optionId
	 * @return option value object list
	 */
	public static function getProductOptionValues($productId, $optionId)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('pov.*')
			->from('#__eshop_productoptionvalues AS pov')
			->where('product_id = ' . intval($productId))
			->where('option_id = ' . intval($optionId))
			->order('id');
		$db->setQuery($query);
		$rows = $db->loadObjectList();
		//Resize option image
		$imagePath = JPATH_ROOT . '/media/com_eshop/options/';
		$imageSizeFunction = EshopHelper::getConfigValue('option_image_size_function', 'resizeImage');
		return $rows;
	}

	/**
	 *
	 * Function to get images for a specific product
	 * @param int $productId
	 */
	public static function getProductImages($productId)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('pi.*')
			->from('#__eshop_productimages AS pi')
			->where('product_id = ' . intval($productId))
			->order('pi.ordering');
		$db->setQuery($query);
		$rows = $db->loadObjectList();
		return $rows;
	}
	
	/**
	 * 
	 * Function to get tags for a specific product
	 * @param int $productId
	 */
	public static function getProductTags($productId)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('a.*')
			->from('#__eshop_tags AS a')
			->innerJoin('#__eshop_producttags AS b ON (a.id = b.tag_id)')
			->where('a.published = 1')
			->where('b.product_id = ' . intval($productId));
		$db->setQuery($query);
		return $db->loadObjectList();
	}
	
	/**
	 *  
	 * Function to resize image 
	 * @param string $filename
	 * @param string $imagePath
	 * @param int $width
	 * @param int $height
	 * @return void|string
	 */
	public static function resizeImage($filename, $imagePath, $width, $height)
	{
		if (!file_exists($imagePath . $filename) || !is_file($imagePath . $filename))
		{
			return;
		}
		$info = pathinfo($filename);
		$extension = $info['extension'];
		$oldImage = $filename;
		$newImage = substr($filename, 0, strrpos($filename, '.')) . '-' . $width . 'x' . $height . '.' . $extension;
		if (!file_exists($imagePath . '/resized/' . $newImage) || (filemtime($imagePath . $oldImage) > filemtime($imagePath . '/resized/' . $newImage))) 
		{
			list($width_orig, $height_orig) = getimagesize($imagePath . $oldImage);
			if ($width_orig != $width || $height_orig != $height)
			{
				$image = new EshopImage($imagePath . $oldImage);
				$image->resize($width, $height);
				$image->save($imagePath . '/resized/' . $newImage);
			}
			else
			{
				copy($imagePath . $oldImage, $imagePath . '/resized/' . $newImage);
			}
		}
		return $newImage;
	}
	
	/**
	 *
	 * Function to cropsize image
	 * @param string $filename
	 * @param string $imagePath
	 * @param int $width
	 * @param int $height
	 * @return void|string
	 */
	public static function cropsizeImage($filename, $imagePath, $width, $height)
	{
		if (!file_exists($imagePath . $filename) || !is_file($imagePath . $filename))
		{
			return;
		}
		$info = pathinfo($filename);
		$extension = $info['extension'];
		$oldImage = $filename;
		$newImage = substr($filename, 0, strrpos($filename, '.')) . '-cr-' . $width . 'x' . $height . '.' . $extension;
		if (!file_exists($imagePath . '/resized/' . $newImage) || (filemtime($imagePath . $oldImage) > filemtime($imagePath . '/resized/' . $newImage)))
		{
			list($width_orig, $height_orig) = getimagesize($imagePath . $oldImage);
			if ($width_orig != $width || $height_orig != $height)
			{
				$image = new EshopImage($imagePath . $oldImage);
				$image->cropsize($width, $height);
				$image->save($imagePath . '/resized/' . $newImage);
			}
			else
			{
				copy($imagePath . $oldImage, $imagePath . '/resized/' . $newImage);
			}
		}
		return $newImage;
	}
	
	/**
	 *
	 * Function to max size image
	 * @param string $filename
	 * @param string $imagePath
	 * @param int $width
	 * @param int $height
	 * @return void|string
	 */
	public static function maxsizeImage($filename, $imagePath, $width, $height)
	{
		$maxsize = ($width > $height) ? $width : $height;
		if (!file_exists($imagePath . $filename) || !is_file($imagePath . $filename))
		{
			return;
		}
		$info = pathinfo($filename);
		$extension = $info['extension'];
		$oldImage = $filename;
		$newImage = substr($filename, 0, strrpos($filename, '.')) . '-max-' . $width . 'x' . $height . '.' . $extension;
		if (!file_exists($imagePath . '/resized/' . $newImage) || (filemtime($imagePath . $oldImage) > filemtime($imagePath . '/resized/' . $newImage)))
		{
			$image = new EshopImage($imagePath . $oldImage);
			$image->maxsize($maxsize);
			$image->save($imagePath . '/resized/' . $newImage);
		}
		return $newImage;
	}

	/**
	 *
	 * Function to get discount for a specific product
	 * @param int $productId        	
	 */
	public static function getProductDiscounts($productId)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('pd.*')
			->from('#__eshop_productdiscounts AS pd')
			->innerJoin('#__eshop_customergroups AS cg ON (pd.customergroup_id = cg.id)')
			->where('pd.product_id = ' . intval($productId))
			->order('pd.priority');
		$db->setQuery($query);
		$rows = $db->loadObjectList();
		return $rows;
	}

	/**
	 *
	 * Function to get special for a specific product
	 * @param int $productId        	
	 */
	public static function getProductSpecials($productId)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('ps.*')
			->from('#__eshop_productspecials AS ps')
			->innerJoin('#__eshop_customergroups AS cg ON (ps.customergroup_id = cg.id)')
			->where('ps.product_id = ' . intval($productId))
			->order('ps.priority');
		$db->setQuery($query);
		$rows = $db->loadObjectList();
		return $rows;
	}	

	/**
	 * 
	 * Function to get discount price for a specific product
	 * @param int $productId
	 * @return price
	 */
	public static function getDiscountPrice($productId)
	{
		$user = JFactory::getUser();
		if ($user->get('id'))
		{
			$customer = new EshopCustomer();
			$customerGroupId = $customer->getCustomerGroupId();
		}
		else
		{
			$customerGroupId = self::getConfigValue('customergroup_id');
		}
		if (!$customerGroupId)
			$customerGroupId = 0;
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('price')
			->from('#__eshop_productdiscounts')
			->where('product_id = ' . intval($productId))
			->where('published = 1')
			->where('customergroup_id = ' . intval($customerGroupId))
			->where('date_start <= "' . date('Y-m-d H:i:s') . '"')
			->where('date_end >= "' . date('Y-m-d H:i:s') . '"')
			->where('quantity = 1')
			->order('priority');
		$db->setQuery($query);
		$discountPrice = $db->loadResult();
		return $discountPrice;
	}
	
	/**
	 *
	 * Function to get discount prices for a specific product - is used to dipslay product discounts on the product details page
	 * @param int $productId
	 * @return prices
	 */
	public static function getDiscountPrices($productId)
	{
		$user = JFactory::getUser();
		if ($user->get('id'))
		{
			$customer = new EshopCustomer();
			$customerGroupId = $customer->getCustomerGroupId();
		}
		else
		{
			$customerGroupId = self::getConfigValue('customergroup_id');
		}
		if (!$customerGroupId)
			$customerGroupId = 0;
		
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('quantity, price')
			->from('#__eshop_productdiscounts')
			->where('product_id = ' . intval($productId))
			->where('published = 1')
			->where('customergroup_id = ' . intval($customerGroupId))
			->where('(date_start <= "' . date('Y-m-d H:i:s') . '" OR date_start = "0000-00-00 00:00:00")')
			->where('(date_end >= "' . date('Y-m-d H:i:s') . '" OR date_end = "0000-00-00 00:00:00")')
			->where('quantity > 1')
			->order('priority');
		$db->setQuery($query);
		$discountPrices = $db->loadObjectList();
		for ($i = 0; $n = count($discountPrices), $i < $n; $i++)
		{
			if (self::getSpecialPrice($productId, $discountPrices[$i]->price))
			{
				$discountPrices[$i]->price = self::getSpecialPrice($productId, $discountPrices[$i]->price);
			}
		}
		return $discountPrices;
	}
	
	/**
	 * 
	 * Function to get special price
	 * @param int $productId
	 * @return price
	 */
	public static function getSpecialPrice($productId, $productPrice)
	{
		$user = JFactory::getUser();
		if ($user->get('id'))
		{
			$customer = new EshopCustomer();
			$customerGroupId = $customer->getCustomerGroupId();
		}
		else
		{
			$customerGroupId = self::getConfigValue('customergroup_id');
		}
		if (!$customerGroupId)
			$customerGroupId = 0;
		// First, check if there is a special price for the product or not. Special Price has highest priority
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('price')
			->from('#__eshop_productspecials')
			->where('product_id = ' . intval($productId))
			->where('published = 1')
			->where('customergroup_id = ' . intval($customerGroupId))
			->where('(date_start <= "' . date('Y-m-d H:i:s') . '" OR date_start = "0000-00-00 00:00:00")')
			->where('(date_end >= "' . date('Y-m-d H:i:s') . '" OR date_end = "0000-00-00 00:00:00")')
			->order('priority');
		$db->setQuery($query, 0, 1);
		$specialPrice = $db->loadResult();
		if (!$specialPrice)
		{
			// If not, check the global discount
			// Check for product discount first
			$query->clear();
			$query->select('discount_id')
				->from('#__eshop_discountelements')
				->where('element_type = "product" AND (element_id = '.intval($productId).' OR element_id = 0)')
				->order('discount_id DESC');
			$db->setQuery($query, 0, 1);
			$discountId = $db->loadResult();
			if (!$discountId)
			{
				// Check for product categories and manufacturers
				$query->clear();
				$query->select('discount_id')
					->from('#__eshop_discountelements')
					->where('(element_type = "manufacturer" AND (element_id = (SELECT manufacturer_id FROM #__eshop_products WHERE id = '.intval($productId).') OR element_id = 0)) OR (element_type = "category" AND (element_id IN (SELECT category_id FROM #__eshop_productcategories WHERE product_id = '.intval($productId).')  OR element_id = 0))')
					->order('discount_id DESC');
				$db->setQuery($query, 0, 1);
				$discountId = $db->loadResult();
			}
			if ($discountId)
			{
				$query->clear();
				$query->select('*')
					->from('#__eshop_discounts')
					->where('id = ' . intval($discountId))
					->where('published = 1')
					->where('((discount_customergroups = "") OR (discount_customergroups IS NULL) OR (discount_customergroups = "' . $customerGroupId . '") OR (discount_customergroups LIKE "' . $customerGroupId . ',%") OR (discount_customergroups LIKE "%,' . $customerGroupId . ',%") OR (discount_customergroups LIKE "%,' . $customerGroupId . '"))')
					->where('(discount_start_date = "0000-00-00 00:00:00" OR discount_start_date < NOW())')
					->where('(discount_end_date = "0000-00-00 00:00:00" OR discount_end_date > NOW())');
				$db->setQuery($query);
				$row = $db->loadObject();
				if (is_object($row))
				{
					$discountValue = $row->discount_value;
					$discountType = $row->discount_type;
					if ($discountType == 'P')
					{
						$specialPrice = $productPrice * (1 - $discountValue / 100);
					}
					else 
					{
						if ($discountValue >= $productPrice)
						{
							$specialPrice = 0;
						}
						else
						{
							$specialPrice = $productPrice - $discountValue;
						}
					}
				}
			}
		}
		return $specialPrice;
	}

	/**
	 * 
	 * Function to get product price array
	 * @param int $productId
	 * @param float $productPrice
	 * @return array of price
	 */
	public static function getProductPriceArray($productId, $productPrice)
	{
		$specialPrice = self::getSpecialPrice($productId, $productPrice);
		$discountPrice = self::getDiscountPrice($productId);
		if ($specialPrice)
		{
			$salePrice = $specialPrice;
			if ($discountPrice)
			{
				$basePrice = $discountPrice;
			}
			else
			{
				$basePrice = $productPrice;
			}
		}
		else
		{
			$basePrice = $productPrice;
			$salePrice = $discountPrice;
		}
		$productPriceArray = array("basePrice" => $basePrice, "salePrice" => $salePrice);
		return $productPriceArray;
	}

	/**
	 *
	 * Function to get currency format for a specific number
	 * @param float $number        	
	 * @param int $currencyId        	
	 */
	public static function getCurrencyFormat($number, $currencyId = '')
	{
		if (!$currencyId)
		{
			// Use default currency
			$currencyId = 4;
		}
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')
			->from('#__eshop_currencies')
			->where('id = ' . intval($currencyId));
		$db->setQuery($query);
		$row = $db->loadObject();
		$currencyFormat = '';
		$sign = '';
		if ($number < 0)
		{
			$sign = '-';
			$number = abs($number);
		}
		if (is_object($row))
		{
			$currencyFormat = $sign . $row->left_symbol . number_format($number, $row->decimal_place, $row->decimal_symbol, $row->thousands_separator) .
				 $row->right_symbol;
		}
		return $currencyFormat;
	}
	
	/**
	 * 
	 * Function to round out a number
	 * @param float $number
	 * @param int $places
	 * @return float
	 */
	public static function roundOut($number, $places = 0)
	{
		if ($places < 0)
			$places = 0;
		$mult = pow(10, $places);
		return ($number >= 0 ? ceil($number * $mult):floor($number * $mult)) / $mult;
	}
	
	/**
	 * 
	 * Function to round up a number 
	 * @param float $number
	 * @param int $places
	 * @return float
	 */
	public static function roundUp($number, $places=0)
	{
		if ($places < 0)
			$places = 0;
		$mult = pow(10, $places);
		return ceil($number * $mult) / $mult;
	}
	
	/**
	 *
	 * Function to get information for a specific address
	 * @param int $addressId
	 */
	public static function getAddress($addressId)
	{
		$user = JFactory::getUser();
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('a.*, z.zone_name, z.zone_code, c.country_name, c.iso_code_2, c.iso_code_3')
			->from('#__eshop_addresses AS a')
			->leftJoin('#__eshop_zones AS z ON (a.zone_id = z.id)')
			->leftJoin('#__eshop_countries AS c ON (a.country_id = c.id)')
			->where('a.id = ' . intval($addressId))
			->where('a.customer_id = ' . intval($user->get('id')));
		$db->setQuery($query);
		return $db->loadAssoc();
	}
	
	/**
	 *
	 * Function to get information for a specific customer
	 * @param int $customerId
	 * @return customer object
	 */
	public static function getCustomer($customerId)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')
			->from('#__eshop_customers')
			->where('customer_id = ' . intval($customerId));
		$db->setQuery($query);
		return $db->loadObject();
	}
	
	/**
	 *
	 * Function to get information for a specific country
	 * @param int $countryId
	 */
	public static function getCountry($countryId)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')
			->from('#__eshop_countries')
			->where('id = ' . intval($countryId))
			->where('published = 1');
		$db->setQuery($query);
		return $db->loadObject();
	}
	
	/**
	 *
	 * Function to get Zones for a specific Country
	 * @param int $countryId
	 */
	public static function getCountryZones($countryId)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('id, zone_name')
			->from('#__eshop_zones')
			->where('country_id = ' . intval($countryId))
			->where('published = 1')
			->order('zone_name');
		$db->setQuery($query);
		return $db->loadAssocList();
	}
	
	/**
	 *
	 * Function to get information for a specific zone
	 * @param int $zoneId
	 */
	public static function getZone($zoneId)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')
			->from('#__eshop_zones')
			->where('id = ' . intval($zoneId))
			->where('published = 1');
		$db->setQuery($query);
		return $db->loadObject();
	}
	
	/**
	 *
	 * Function to get information for a specific geozone
	 * @param int $geozoneId
	 */
	public static function getGeozone($geozoneId)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')
			->from('#__eshop_geozones')
			->where('id = ' . intval($geozoneId))
			->where('published = 1');
		$db->setQuery($query);
		return $db->loadObject();
	}
	
	/**
	 *
	 * Function to complete an order
	 * @param order object $row
	 */
	public static function completeOrder($row)
	{
		$orderId = intval($row->id);
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')
			->from('#__eshop_orderproducts')
			->where('order_id = ' . intval($orderId));
		$db->setQuery($query);
		$orderProducts = $db->loadObjectList();
		foreach ($orderProducts as $orderProduct)
		{
			//Update product quantity
			$query->clear();
			$query->update('#__eshop_products')
				->set('product_quantity = product_quantity - ' . intval($orderProduct->quantity))
				->where('id = ' . intval($orderProduct->product_id));
			$db->setQuery($query);
			$db->query();
			//Update product options
			$query->clear();
			$query->select('*')
				->from('#__eshop_orderoptions')
				->where('order_id = ' . intval($orderId))
				->where('order_product_id = ' . intval($orderProduct->id));
			$db->setQuery($query);
			$orderOptions = $db->loadObjectList();
			foreach ($orderOptions as $orderOption)
			{
				if ($orderOption->option_type == 'Select' || $orderOption->option_type == 'Radio' || $orderOption->option_type == 'Checkbox')
				{
					$query->clear();
					$query->update('#__eshop_productoptionvalues')
						->set('quantity = quantity - ' . intval($orderProduct->quantity))
						->where('id = ' . intval($orderOption->product_option_value_id));
					$db->setQuery($query);
					$db->query();
				}
			}
		}
		//Add coupon history and voucher history
		self::addCouponHistory($row);
		self::addVoucherHistory($row);
	}
	
	/**
	 * 
	 * Function to add coupon history
	 * @param order object $row
	 */
	public static function addCouponHistory($row)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('value')
			->from('#__eshop_ordertotals')
			->where('order_id = ' . intval($row->id))
			->where('name = "coupon"');
		$db->setQuery($query);
		$amount = $db->loadResult();
		if ($amount)
		{
			$couponId = $row->coupon_id;
			if ($couponId)
			{
				$coupon = new EshopCoupon();
				$coupon->addCouponHistory($couponId, $row->id, $row->customer_id, $amount);
			}
		}
	}
	
	/**
	 *
	 * Function to add voucher history
	 * @param order object $row
	 */
	public static function addVoucherHistory($row)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('value')
			->from('#__eshop_ordertotals')
			->where('order_id = ' . intval($row->id))
			->where('name = "voucher"');
		$db->setQuery($query);
		$amount = $db->loadResult();
		if ($amount)
		{
			$voucherId = $row->voucher_id;
			if ($voucherId)
			{
				$voucher = new EshopVoucher();
				$voucher->addVoucherHistory($voucherId, $row->id, $row->customer_id, $amount);
			}
		}
	}
	
	/**
	 *
	 * Function to send quote emails
	 * @param order object $row
	 */
	public static function sendQuoteEmails($row)
	{
		$jconfig = new JConfig();
		$mailer = JFactory::getMailer();
		$fromName = $jconfig->fromname;
		$fromEmail =  $jconfig->mailfrom;
	
		//Send notification email to admin
		$adminSubject = sprintf(JText::_('ESHOP_ADMIN_QUOTE_EMAIL_SUBJECT'), $row->name);
		$adminBody = self::getAdminQuoteEmailBody($row);
		$adminBody = self::convertImgTags($adminBody);
		$adminEmail = self::getConfigValue('email') ? trim(self::getConfigValue('email')) : $fromEmail;
		$mailer->sendMail($fromEmail, $fromName, $adminEmail, $adminSubject, $adminBody, 1);
	
		//Send notification email to additional emails
		$alertEmails = self::getConfigValue('alert_emails');
		$alertEmails = str_replace(' ', '', $alertEmails);
		$alertEmails = explode(',', $alertEmails);
		for ($i = 0; $n = count($alertEmails), $i < $n; $i++)
		{
			if ($alertEmails[$i] != '')
			{
				$mailer->ClearAllRecipients();
				$mailer->sendMail($fromEmail, $fromName, $alertEmails[$i], $adminSubject, $adminBody, 1);
			}
		}
	
		//Send email to customer
		$customerSubject = sprintf(JText::_('ESHOP_CUSTOMER_QUOTE_EMAIL_SUBJECT'));
		$customerBody = self::getCustomerQuoteEmailBody($row);
		$customerBody = self::convertImgTags($customerBody);
		$mailer->ClearAllRecipients();
		$mailer->sendMail($fromEmail, $fromName, $row->email, $customerSubject, $customerBody, 1);
	}
	
	/**
	 *
	 * Function to get admin quote email body
	 * @param quote object $row
	 * @return string
	 */
	public static function getAdminQuoteEmailBody($row)
	{
		$currency = new EshopCurrency();
		$row->total_price = $currency->format($row->total, $row->currency_code, $row->currency_exchanged_value);
		$adminEmailBody = self::getMessageValue('admin_quote_email');
		// Quote information
		$replaces = array();
		$replaces['name'] = $row->name;
		$replaces['email'] = $row->email;
		$replaces['company'] = $row->company;
		$replaces['telephone'] = $row->telephone;
		$replaces['message'] = $row->message;
		// Products list
		$viewConfig['name'] = 'email';
		$viewConfig['base_path'] = JPATH_ROOT.'/components/com_eshop/emailtemplates';
		$viewConfig['template_path'] = JPATH_ROOT.'/components/com_eshop/emailtemplates';
		$viewConfig['layout'] = 'quoteproducts';
		$view =  new JViewLegacy($viewConfig);
		$quoteProducts = self::getQuoteProducts($row->id);
		$view->assignRef('quoteProducts', $quoteProducts);
		$view->assignRef('row', $row);
		ob_start();
		$view->display();
		$text = ob_get_contents();
		ob_end_clean();
		$replaces['products_list'] = $text;
	
		foreach ($replaces as $key => $value)
		{
			$key = strtoupper($key);
			$adminEmailBody = str_replace("[$key]", $value, $adminEmailBody);
		}
		return $adminEmailBody;
	}
	
	/**
	 *
	 * Function to get customer quote email body
	 * @param quote object $row
	 * @return string
	 */
	public static function getCustomerQuoteEmailBody($row)
	{
		$currency = new EshopCurrency();
		$row->total_price = $currency->format($row->total, $row->currency_code, $row->currency_exchanged_value);
		$customerEmailBody = self::getMessageValue('customer_quote_email');
		// Quote information
		$replaces = array();
		// Products list
		$viewConfig['name'] = 'email';
		$viewConfig['base_path'] = JPATH_ROOT.'/components/com_eshop/emailtemplates';
		$viewConfig['template_path'] = JPATH_ROOT.'/components/com_eshop/emailtemplates';
		$viewConfig['layout'] = 'quoteproducts';
		$view =  new JViewLegacy($viewConfig);
		$quoteProducts = self::getQuoteProducts($row->id);
		$view->assignRef('quoteProducts', $quoteProducts);
		$view->assignRef('row', $row);
		ob_start();
		$view->display();
		$text = ob_get_contents();
		ob_end_clean();
		$replaces['name'] = $row->name;
		$replaces['email'] = $row->email;
		$replaces['company'] = $row->company;
		$replaces['telephone'] = $row->telephone;
		$replaces['message'] = $row->message;
		$replaces['products_list'] = $text;
	
		foreach ($replaces as $key => $value)
		{
			$key = strtoupper($key);
			$customerEmailBody = str_replace("[$key]", $value, $customerEmailBody);
		}
		return $customerEmailBody;
	}

	/**
	 *
	 * Function to send email
	 * @param order object $row
	 */
	public static function sendEmails($row)
	{
		$jconfig = new JConfig();
		$mailer = JFactory::getMailer();
		$fromName = $jconfig->fromname;
		$fromEmail =  $jconfig->mailfrom;
		
		//Send notification email to admin
		$adminSubject = sprintf(JText::_('ESHOP_ADMIN_EMAIL_SUBJECT'), self::getConfigValue('store_name'), $row->id);
		$adminBody = self::getAdminEmailBody($row);
		$adminBody = self::convertImgTags($adminBody);
		$adminEmail = self::getConfigValue('email') ? trim(self::getConfigValue('email')) : $fromEmail;
		$mailer->sendMail($fromEmail, $fromName, $adminEmail, $adminSubject, $adminBody, 1);
		
		//Send notification email to additional emails
		$alertEmails = self::getConfigValue('alert_emails');
		$alertEmails = str_replace(' ', '', $alertEmails);
		$alertEmails = explode(',', $alertEmails);
		for ($i = 0; $n = count($alertEmails), $i < $n; $i++)
		{
			if ($alertEmails[$i] != '')
			{
				$mailer->ClearAllRecipients();
				$mailer->sendMail($fromEmail, $fromName, $alertEmails[$i], $adminSubject, $adminBody, 1);
			}
		}
		
		//Send notification email to manufacturer
		$manufacturers = array();
		$orderProducts = self::getOrderProducts($row->id);
		for ($i = 0; $n = count($orderProducts), $i < $n; $i++)
		{
			$product = $orderProducts[$i];
			$manufacturer = self::getProductManufacturer($product->product_id, JFactory::getLanguage()->getTag());
			if (is_object($manufacturer))
			{
				$manufacturer->product = $orderProducts[$i];
				if (!isset($manufacturers[$manufacturer->id]))
				{
					$manufacturers[$manufacturer->id] = array();
				}
				$manufacturers[$manufacturer->id][] = $manufacturer;
			}
		}
		$manufacturerSubject = JText::_('ESHOP_MANUFACTURER_EMAIL_SUBJECT');
		foreach ($manufacturers as $manufacturerId => $manufacturer)
		{
			if ($manufacturer[0]->manufacturer_email != '')
			{
				$manufacturerBody = self::getManufacturerEmailBody($manufacturer, $row);
				$manufacturerBody = self::convertImgTags($manufacturerBody);
				$mailer->ClearAllRecipients();
				$mailer->sendMail($fromEmail, $fromName, $manufacturer[0]->manufacturer_email, $manufacturerSubject, $manufacturerBody);
			}
		}
		
		//Send email to customer
		$customerSubject = sprintf(JText::_('ESHOP_CUSTOMER_EMAIL_SUBJECT'), self::getConfigValue('store_name'), $row->id);
		$customerBody = self::getCustomerEmailBody($row);
		$customerBody = self::convertImgTags($customerBody);
		$mailer->ClearAllRecipients();
		$attachment = null;
		if (self::getConfigValue('invoice_enable') && self::getConfigValue('send_invoice_to_customer') && $row->order_status_id == self::getConfigValue('complete_status_id'))
		{
			if (!$row->invoice_number)
			{
				$row->invoice_number = self::getInvoiceNumber();
				$row->store();
			}
			self::generateInvoicePDF(array($row->id));
			$attachment = JPATH_ROOT . '/media/com_eshop/invoices/' . self::formatInvoiceNumber($row->invoice_number, $row->created_date) . '.pdf';
		}
		$mailer->sendMail($fromEmail, $fromName, $row->email, $customerSubject, $customerBody, 1, null, null, $attachment);
	}
	
	/**
	 *
	 * Function to get admin email body
	 * @param order object $row
	 * @return string
	 */
	public static function getAdminEmailBody($row)
	{
		$adminEmailBody = self::getMessageValue('admin_notification_email', $row->language);
		// Order information
		$replaces = array();
		$replaces['order_id'] = $row->id;
		$replaces['order_number'] = $row->order_number;
		$replaces['order_status'] = self::getOrderStatusName($row->order_status_id, $row->language);
		$replaces['date_added'] = JHtml::date($row->created_date, self::getConfigValue('date_format', 'm-d-Y'));
		$replaces['store_owner'] = self::getConfigValue('store_owner');
		$replaces['store_name'] = self::getConfigValue('store_name');
		$replaces['store_address'] = str_replace("\r\n", "<br />", self::getConfigValue('address'));
		$replaces['store_telephone'] = self::getConfigValue('telephone');
		$replaces['store_fax'] = self::getConfigValue('fax');
		$replaces['store_email'] = self::getConfigValue('email');
		$replaces['store_url'] = JUri::root();
		if ($row->payment_method == 'os_creditcard')
		{
			$cardNumber = JRequest::getVar('card_number', '');
			if ($cardNumber)
			{
				$last4Digits = substr($cardNumber, strlen($cardNumber) - 4);
				$replaces['payment_method'] = JText::_($row->payment_method_title).' ('.JText::_('ESHOP_LAST_4DIGITS_CREDIT_CARD_NUMBER').': '.$last4Digits.')';
			}
			else 
			{
				$replaces['payment_method'] = JText::_($row->payment_method_title);
			}
			
		}
		else 
		{
			$replaces['payment_method'] = JText::_($row->payment_method_title);
		}		
		$replaces['shipping_method'] = $row->shipping_method_title;
		$replaces['customer_email'] = $row->email;
		$replaces['customer_telephone'] = $row->telephone;
		// Comment
		$replaces['comment'] = $row->comment;
		// Delivery Date
		$replaces['delivery_date'] = JHtml::date($row->delivery_date, self::getConfigValue('date_format', 'm-d-Y'));
		// Payment information
		$replaces['payment_address'] = self::getPaymentAddress($row);
		//Payment custom fields here
		$excludedFields = array('firstname', 'lastname', 'email', 'telephone', 'fax', 'company', 'company_id', 'address_1', 'address_2', 'city', 'postcode', 'country_id', 'zone_id');
		$form = new RADForm(self::getFormFields('B'));
		$fields = $form->getFields();
		foreach ($fields as $field)
		{
			$fieldName = $field->name;
			if (!in_array($fieldName, $excludedFields))
			{
				$fieldValue = $row->{'payment_'.$fieldName};
				if (is_string($fieldValue) && is_array(json_decode($fieldValue)))
				{
					$fieldValue = implode(', ', json_decode($fieldValue));
				}
				$replaces['payment_' . $fieldName] = $fieldValue;
			}
		}
		// Shipping information
		$replaces['shipping_address'] = self::getShippingAddress($row);
		//Shipping custom fields here
		$form = new RADForm(self::getFormFields('S'));
		$fields = $form->getFields();
		foreach ($fields as $field)
		{
			$fieldName = $field->name;
			if (!in_array($fieldName, $excludedFields))
			{
				$fieldValue = $row->{'shipping_'.$fieldName};
				if (is_string($fieldValue) && is_array(json_decode($fieldValue)))
				{
					$fieldValue = implode(', ', json_decode($fieldValue));
				}
				$replaces['shipping_' . $fieldName] = $fieldValue;
			}
		}
		// Products list
		$viewConfig['name'] = 'email';
		$viewConfig['base_path'] = JPATH_ROOT.'/components/com_eshop/emailtemplates';
		$viewConfig['template_path'] = JPATH_ROOT.'/components/com_eshop/emailtemplates';
		$viewConfig['layout'] = 'admin';
		$view =  new JViewLegacy($viewConfig);
		$orderProducts = self::getOrderProducts($row->id);
		$view->assignRef('orderProducts', $orderProducts);
		$orderTotals = self::getOrderTotals($row->id);
		$view->assignRef('orderTotals', $orderTotals);
		$view->assignRef('row', $row);
		ob_start();
		$view->display();
		$text = ob_get_contents();
		ob_end_clean();
		$replaces['products_list'] = $text;
		
		foreach ($replaces as $key => $value)
		{
			$key = strtoupper($key);
			$adminEmailBody = str_replace("[$key]", $value, $adminEmailBody);
		}
		return $adminEmailBody;
	}
	
	/**
	 * 
	 * Function to get manufacturer email body
	 * @param array $manufacturer
	 */
	public static function getManufacturerEmailBody($manufacturer, $row)
	{
		$manufacturerEmailBody = self::getMessageValue('manufacturer_notification_email', $row->language);
		$replaces = array();
		$replaces['manufacturer_name'] = $manufacturer[0]->manufacturer_name;
		$replaces['store_owner'] = self::getConfigValue('store_owner');
		$replaces['store_name'] = self::getConfigValue('store_name');
		$replaces['store_address'] = str_replace("\r\n", "<br />", self::getConfigValue('address'));
		$replaces['store_telephone'] = self::getConfigValue('telephone');
		$replaces['store_fax'] = self::getConfigValue('fax');
		$replaces['store_email'] = self::getConfigValue('email');
		$replaces['store_url'] = JUri::root();
		$replaces['order_id'] = $row->id;
		$replaces['order_number'] = $row->order_number;
		$replaces['date_added'] = JHtml::date($row->created_date, self::getConfigValue('date_format', 'm-d-Y'));
		$replaces['payment_method'] = JText::_($row->payment_method_title);
		$replaces['shipping_method'] = $row->shipping_method_title;
		$replaces['customer_email'] = $row->email;
		$replaces['customer_telephone'] = $row->telephone;
		// Products list
		$viewConfig['name'] = 'email';
		$viewConfig['base_path'] = JPATH_ROOT.'/components/com_eshop/emailtemplates';
		$viewConfig['template_path'] = JPATH_ROOT.'/components/com_eshop/emailtemplates';
		$viewConfig['layout'] = 'manufacturer';
		$view =  new JViewLegacy($viewConfig);
		$view->assignRef('manufacturer', $manufacturer);
		ob_start();
		$view->display();
		$text = ob_get_contents();
		ob_end_clean();
		$replaces['products_list'] = $text;
		// Comment
		$replaces['comment'] = $row->comment;
		// Delivery Date
		$replaces['delivery_date'] = JHtml::date($row->delivery_date, self::getConfigValue('date_format', 'm-d-Y'));
		// Payment information
		$replaces['payment_address'] = self::getPaymentAddress($row);
		//Payment custom fields here
		$excludedFields = array('firstname', 'lastname', 'email', 'telephone', 'fax', 'company', 'company_id', 'address_1', 'address_2', 'city', 'postcode', 'country_id', 'zone_id');
		$form = new RADForm(self::getFormFields('B'));
		$fields = $form->getFields();
		foreach ($fields as $field)
		{
			$fieldName = $field->name;
			if (!in_array($fieldName, $excludedFields))
			{
				$fieldValue = $row->{'payment_'.$fieldName};
				if (is_string($fieldValue) && is_array(json_decode($fieldValue)))
				{
					$fieldValue = implode(', ', json_decode($fieldValue));
				}
				$replaces['payment_' . $fieldName] = $fieldValue;
			}
		}
		// Shipping information
		$replaces['shipping_address'] = self::getShippingAddress($row);
		//Shipping custom fields here
		$form = new RADForm(self::getFormFields('S'));
		$fields = $form->getFields();
		foreach ($fields as $field)
		{
			$fieldName = $field->name;
			if (!in_array($fieldName, $excludedFields))
			{
				$fieldValue = $row->{'shipping_'.$fieldName};
				if (is_string($fieldValue) && is_array(json_decode($fieldValue)))
				{
					$fieldValue = implode(', ', json_decode($fieldValue));
				}
				$replaces['shipping_' . $fieldName] = $fieldValue;
			}
		}
		foreach ($replaces as $key => $value)
		{
			$key = strtoupper($key);
			$manufacturerEmailBody = str_replace("[$key]", $value, $manufacturerEmailBody);
		}
		return $manufacturerEmailBody;
	}
	
	/**
	 *
	 * Function to get customer email body
	 * @param order object $row
	 * @return html string
	 */
	public static function getCustomerEmailBody($row)
	{
		$hasDownload = false;
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('COUNT(*)')
			->from('#__eshop_orderdownloads')
			->where('order_id = ' . intval($row->id));
		$db->setQuery($query);
		if ($db->loadResult())
			$hasDownload = true;
		if ($row->customer_id)
		{
			if (strpos($row->payment_method, 'os_offline') !== false)
			{
				if ($hasDownload)
				{
					$customerEmailBody = self::getMessageValue('offline_payment_customer_notification_email_with_download', $row->language);
				}
				else 
				{
					$customerEmailBody = self::getMessageValue('offline_payment_customer_notification_email', $row->language);
				}
				
			}
			else
			{
				if ($hasDownload)
				{
					$customerEmailBody = self::getMessageValue('customer_notification_email_with_download', $row->language);
				}
				else 
				{
					$customerEmailBody = self::getMessageValue('customer_notification_email', $row->language);
				}
			}
		}
		else
		{
			if (strpos($row->payment_method, 'os_offline') !== false)
			{
				if ($hasDownload)
				{
					$customerEmailBody = self::getMessageValue('offline_payment_guest_notification_email_with_download', $row->language);
				}
				else
				{
					$customerEmailBody = self::getMessageValue('offline_payment_guest_notification_email', $row->language);
				}
			}
			else
			{
				if ($hasDownload)
				{
					$customerEmailBody = self::getMessageValue('guest_notification_email_with_download', $row->language);
				}
				else
				{
					$customerEmailBody = self::getMessageValue('guest_notification_email', $row->language);
				}
			}
		}
		// Order information
		$replaces = array();
		$replaces['customer_name'] = $row->firstname . ' ' . $row->lastname;
		$replaces['payment_firstname'] = $row->payment_firstname;
		$replaces['store_owner'] = self::getConfigValue('store_owner');
		$replaces['store_name'] = self::getConfigValue('store_name');
		$replaces['store_address'] = str_replace("\r\n", "<br />", self::getConfigValue('address'));
		$replaces['store_telephone'] = self::getConfigValue('telephone');
		$replaces['store_fax'] = self::getConfigValue('fax');
		$replaces['store_email'] = self::getConfigValue('email');
		$replaces['store_url'] = JUri::root();
		$replaces['order_link'] = JRoute::_(JUri::root().'index.php?option=com_eshop&view=customer&layout=order&order_id=' . $row->id);
		$replaces['download_link'] = JRoute::_(JUri::root().'index.php?option=com_eshop&view=customer&layout=downloads');
		$replaces['order_id'] = $row->id;
		$replaces['order_number'] = $row->order_number;
		$replaces['order_status'] = self::getOrderStatusName($row->order_status_id, $row->language);
		$replaces['date_added'] = JHtml::date($row->created_date, self::getConfigValue('date_format', 'm-d-Y'));
		$replaces['payment_method'] = JText::_($row->payment_method_title);
		$replaces['shipping_method'] = $row->shipping_method_title;
		$replaces['customer_email'] = $row->email;
		$replaces['customer_telephone'] = $row->telephone;
		// Comment
		$replaces['comment'] = $row->comment;
		// Delivery Date
		$replaces['delivery_date'] = JHtml::date($row->delivery_date, self::getConfigValue('date_format', 'm-d-Y'));
		// Payment information
		$replaces['payment_address'] = self::getPaymentAddress($row);
		//Payment custom fields here
		$excludedFields = array('firstname', 'lastname', 'email', 'telephone', 'fax', 'company', 'company_id', 'address_1', 'address_2', 'city', 'postcode', 'country_id', 'zone_id');
		$form = new RADForm(self::getFormFields('B'));
		$fields = $form->getFields();
		foreach ($fields as $field)
		{
			$fieldName = $field->name;
			if (!in_array($fieldName, $excludedFields))
			{
				$fieldValue = $row->{'payment_'.$fieldName};
				if (is_string($fieldValue) && is_array(json_decode($fieldValue)))
				{
					$fieldValue = implode(', ', json_decode($fieldValue));
				}
				$replaces['payment_' . $fieldName] = $fieldValue;
			}
		}
		// Shipping information
		$replaces['shipping_address'] = self::getShippingAddress($row);
		//Shipping custom fields here
		$form = new RADForm(self::getFormFields('S'));
		$fields = $form->getFields();
		foreach ($fields as $field)
		{
			$fieldName = $field->name;
			if (!in_array($fieldName, $excludedFields))
			{
				$fieldValue = $row->{'shipping_'.$fieldName};
				if (is_string($fieldValue) && is_array(json_decode($fieldValue)))
				{
					$fieldValue = implode(', ', json_decode($fieldValue));
				}
				$replaces['shipping_' . $fieldName] = $fieldValue;
			}
		}
		// Products list
		$viewConfig['name'] = 'email';
		$viewConfig['base_path'] = JPATH_ROOT.'/components/com_eshop/emailtemplates';
		$viewConfig['template_path'] = JPATH_ROOT.'/components/com_eshop/emailtemplates';
		$viewConfig['layout'] = 'customer';
		$view =  new JViewLegacy($viewConfig);
		$orderProducts = self::getOrderProducts($row->id);
		$view->assignRef('orderProducts', $orderProducts);
		$orderTotals = self::getOrderTotals($row->id);
		$view->assignRef('orderTotals', $orderTotals);
		$view->assignRef('row', $row);
		if ($hasDownload && $row->order_status_id == EshopHelper::getConfigValue('complete_status_id'))
		{
			$showDownloadLink = true;
		}
		else 
		{
			$showDownloadLink = false;
		}
		$view->assignRef('showDownloadLink', $showDownloadLink);
		ob_start();
		$view->display();
		$text = ob_get_contents();
		ob_end_clean();
		$replaces['products_list'] = $text;
		
		foreach ($replaces as $key => $value)
		{
			$key = strtoupper($key);
			$customerEmailBody = str_replace("[$key]", $value, $customerEmailBody);
		}
		return $customerEmailBody;
	}
	
	/**
	 *
	 * Function to get notification email body
	 * @param order object $row
	 * @return html string
	 */
	public static function getNotificationEmailBody($row, $orderStatusFrom, $orderStatusTo)
	{
		if ($row->customer_id)
		{
			$notificationEmailBody = self::getMessageValue('order_status_change_customer', $row->language);
		}
		else
		{
			$notificationEmailBody = self::getMessageValue('order_status_change_guest', $row->language);
		}
		// Order information
		$replaces = array();
		$replaces['customer_name'] = $row->firstname . ' ' . $row->lastname;
		$replaces['order_status_from'] = self::getOrderStatusName($orderStatusFrom, $row->language);
		$replaces['order_status_to'] = self::getOrderStatusName($orderStatusTo, $row->language);
		$replaces['payment_firstname'] = $row->payment_firstname;
		$replaces['store_name'] = self::getConfigValue('store_name');
		$replaces['order_link'] = JRoute::_(JUri::root().'index.php?option=com_eshop&view=customer&layout=order&order_id=' . $row->id);
		$replaces['order_id'] = $row->id;
		$replaces['order_number'] = $row->order_number;
		$replaces['date_added'] = JHtml::date($row->created_date, self::getConfigValue('date_format', 'm-d-Y'));
		$replaces['payment_method'] = JText::_($row->payment_method_title);
		$replaces['shipping_method'] = $row->shipping_method_title;
		$replaces['customer_email'] = $row->email;
		$replaces['customer_telephone'] = $row->telephone;
		// Comment
		$replaces['comment'] = $row->comment;
		// Delivery Date
		$replaces['delivery_date'] = JHtml::date($row->delivery_date, self::getConfigValue('date_format', 'm-d-Y'));
		// Payment information
		$replaces['payment_address'] = self::getPaymentAddress($row);
		//Payment custom fields here
		$excludedFields = array('firstname', 'lastname', 'email', 'telephone', 'fax', 'company', 'company_id', 'address_1', 'address_2', 'city', 'postcode', 'country_id', 'zone_id');
		$form = new RADForm(self::getFormFields('B'));
		$fields = $form->getFields();
		foreach ($fields as $field)
		{
			$fieldName = $field->name;
			if (!in_array($fieldName, $excludedFields))
			{
				$fieldValue = $row->{'payment_'.$fieldName};
				if (is_string($fieldValue) && is_array(json_decode($fieldValue)))
				{
					$fieldValue = implode(', ', json_decode($fieldValue));
				}
				$replaces['payment_' . $fieldName] = $fieldValue;
			}
		}
		// Shipping information
		$replaces['shipping_address'] = self::getShippingAddress($row);
		//Shipping custom fields here
		$form = new RADForm(self::getFormFields('S'));
		$fields = $form->getFields();
		foreach ($fields as $field)
		{
			$fieldName = $field->name;
			if (!in_array($fieldName, $excludedFields))
			{
				$fieldValue = $row->{'shipping_'.$fieldName};
				if (is_string($fieldValue) && is_array(json_decode($fieldValue)))
				{
					$fieldValue = implode(', ', json_decode($fieldValue));
				}
				$replaces['shipping_' . $fieldName] = $fieldValue;
			}
		}
		// Products list
		$viewConfig['name'] = 'email';
		$viewConfig['base_path'] = JPATH_ROOT.'/components/com_eshop/emailtemplates';
		$viewConfig['template_path'] = JPATH_ROOT.'/components/com_eshop/emailtemplates';
		$viewConfig['layout'] = 'customer';
		$view =  new JViewLegacy($viewConfig);
		$orderProducts = self::getOrderProducts($row->id);
		$view->assignRef('orderProducts', $orderProducts);
		$view->assignRef('orderTotals', self::getOrderTotals($row->id));
		$view->assignRef('row', $row);
		$hasDownload = false;
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('COUNT(*)')
			->from('#__eshop_orderdownloads')
			->where('order_id = ' . intval($row->id));
		$db->setQuery($query);
		if ($db->loadResult())
			$hasDownload = true;
		if ($hasDownload && $row->order_status_id == EshopHelper::getConfigValue('complete_status_id'))
		{
			$showDownloadLink = true;
		}
		else
		{
			$showDownloadLink = false;
		}
		$view->assignRef('showDownloadLink', $showDownloadLink);
		ob_start();
		$view->display();
		$text = ob_get_contents();
		ob_end_clean();
		$replaces['products_list'] = $text;
	
		foreach ($replaces as $key => $value)
		{
			$key = strtoupper($key);
			$notificationEmailBody = str_replace("[$key]", $value, $notificationEmailBody);
		}
		return $notificationEmailBody;
	}
	
	/**
	 *
	 * Function to get shipping notification email body
	 * @param order object $row
	 * @return html string
	 */
	public static function getShippingNotificationEmailBody($row)
	{
		$shippingNotificationEmailBody = self::getMessageValue('shipping_notification_email', $row->language);
		// Order information
		$replaces = array();
		$replaces['customer_name'] = $row->firstname . ' ' . $row->lastname;
		$replaces['order_id'] = $row->id;
		$replaces['order_number'] = $row->order_number;
		$replaces['shipping_tracking_number'] = $row->shipping_tracking_number;
		$replaces['shipping_tracking_url'] = $row->shipping_tracking_url;
		$replaces['comment'] = $row->comment;
		// Shipping information
		$replaces['shipping_address'] = self::getShippingAddress($row);
		//Shipping custom fields here
		$excludedFields = array('firstname', 'lastname', 'email', 'telephone', 'fax', 'company', 'company_id', 'address_1', 'address_2', 'city', 'postcode', 'country_id', 'zone_id');
		$form = new RADForm(self::getFormFields('S'));
		$fields = $form->getFields();
		foreach ($fields as $field)
		{
			$fieldName = $field->name;
			if (!in_array($fieldName, $excludedFields))
			{
				$fieldValue = $row->{'shipping_'.$fieldName};
				if (is_string($fieldValue) && is_array(json_decode($fieldValue)))
				{
					$fieldValue = implode(', ', json_decode($fieldValue));
				}
				$replaces['shipping_' . $fieldName] = $fieldValue;
			}
		}
		foreach ($replaces as $key => $value)
		{
			$key = strtoupper($key);
			$shippingNotificationEmailBody = str_replace("[$key]", $value, $shippingNotificationEmailBody);
		}
		return $shippingNotificationEmailBody;
	}
	
	/**
	 *
	 * Function to get ask question email body
	 * @param object product
	 */
	public static function getAskQuestionEmailBody($data, $product)
	{		
		// Products list
		$viewConfig['name'] = 'email';
		$viewConfig['base_path'] = JPATH_ROOT.'/components/com_eshop/emailtemplates';
		$viewConfig['template_path'] = JPATH_ROOT.'/components/com_eshop/emailtemplates';
		$viewConfig['layout'] = 'askquestion';
		$view =  new JViewLegacy($viewConfig);
		$view->assignRef('data', $data);
		$view->assignRef('product', $product);
		ob_start();
		$view->display();
		$askQuestionEmailBody = ob_get_contents();
		ob_end_clean();
		return $askQuestionEmailBody;
	}
	
	/**
	 * 
	 * Function to get invoice ouput for a specific order
	 * @param int $orderId
	 * @return string
	 */
	public static function getInvoiceHtml($orderId)
	{
		$viewConfig['name'] = 'invoice';
		$viewConfig['base_path'] = JPATH_ROOT.'/components/com_eshop/invoicetemplates';
		$viewConfig['template_path'] = JPATH_ROOT.'/components/com_eshop/invoicetemplates';
		$viewConfig['layout'] = 'default';
		$view =  new JViewLegacy($viewConfig);
		$view->assignRef('order_id', $orderId);
		ob_start();
		$view->display();
		$text = ob_get_contents();
		ob_end_clean();
		return $text;
	}

	/**
	 *
	 * Function to load jQuery chosen plugin
	 */
	public static function chosen()
	{
		static $chosenLoaded;
		if (!$chosenLoaded)
		{
			$document = JFactory::getDocument();
			if (version_compare(JVERSION, '3.0', 'ge'))
			{
				JHtml::_('formbehavior.chosen', '.chosen');
			}
			else
			{
				$document->addScript(JUri::base(true) . '/components/com_eshop/assets/chosen/chosen.jquery.js');
				$document->addStyleSheet(JUri::base(true) . '/components/com_eshop/assets/chosen/chosen.css');
			}
			$document->addScriptDeclaration(
				"jQuery(document).ready(function(){
	                    jQuery(\".chosen\").chosen();
	                });");
			$chosenLoaded = true;
		}
	}
	
	/**
	 *
	 * Function to load bootstrap library
	 */
	public static function loadBootstrap($loadJs = true)
	{
		$document = JFactory::getDocument();
		if ($loadJs)
		{
			$document->addScript(JUri::root(true) . '/components/com_eshop/assets/bootstrap/js/jquery.min.js');
			$document->addScript(JUri::root(true) . '/components/com_eshop/assets/bootstrap/js/jquery-noconflict.js');
			$document->addScript(JUri::root(true) . '/components/com_eshop/assets/bootstrap/js/bootstrap.min.js');
		}
		$document->addStyleSheet(JUri::root(true) . '/components/com_eshop/assets/bootstrap/css/bootstrap.css');
		$document->addStyleSheet(JUri::root(true) . '/components/com_eshop/assets/bootstrap/css/bootstrap.min.css');
	}
	
	/**
	 *
	 * Function to load bootstrap css
	 */
	public static function loadBootstrapCss()
	{
		$document = JFactory::getDocument();
		$document->addStyleSheet(JUri::root(true) . '/components/com_eshop/assets/bootstrap/css/bootstrap.css');
		$document->addStyleSheet(JUri::root(true) . '/components/com_eshop/assets/bootstrap/css/bootstrap.min.css');
	}
	
	/**
	 *
	 * Function to load bootstrap javascript
	 */
	public static function loadBootstrapJs($loadJs = true)
	{
		if (version_compare(JVERSION, '3.0', 'ge'))
		{
			JHtml::_('bootstrap.framework');
		}
		else
		{
			$document = JFactory::getDocument();
			$document->addScript(JUri::root(true) . '/components/com_eshop/assets/bootstrap/js/jquery.min.js');
			$document->addScript(JUri::root(true) . '/components/com_eshop/assets/bootstrap/js/jquery-noconflict.js');
			$document->addScript(JUri::root(true) . '/components/com_eshop/assets/bootstrap/js/bootstrap.min.js');
		}
	}
	
	/**
	 * 
	 * Function to load scripts for share product
	 */
	public static function loadShareScripts($product)
	{
		$document = JFactory::getDocument();
		
		//Add script for Twitter
		if (self::getConfigValue('show_twitter_button'))
		{
			$script = '!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="//platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");';
			$document->addScriptDeclaration($script);
		}
		
		//Add script for PinIt
		if (self::getConfigValue('show_pinit_button'))
		{
			$script = '(function() {
				window.PinIt = window.PinIt || { loaded:false };
				if (window.PinIt.loaded) return;
				window.PinIt.loaded = true;
				function async_load(){
					var s = document.createElement("script");
					s.type = "text/javascript";
					s.async = true;
					s.src = "http://assets.pinterest.com/js/pinit.js";
					var x = document.getElementsByTagName("script")[0];
					x.parentNode.insertBefore(s, x);
				}
				if (window.attachEvent)
					window.attachEvent("onload", async_load);
				else
					window.addEventListener("load", async_load, false);
			})();';
			$document->addScriptDeclaration($script);
		}
		
		// Add script for LinkedIn
		if (self::getConfigValue('show_linkedin_button'))
			$document->addScript('//platform.linkedin.com/in.js');

		// Add script for Google
		if (self::getConfigValue('show_google_button'))
		{
			$script = '(function() {
				var po = document.createElement("script"); po.type = "text/javascript"; po.async = true;
				po.src = "https://apis.google.com/js/plusone.js";
				var s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(po, s);
			})();';
			$document->addScriptDeclaration($script);
		}
		
		// Add script for Facebook
		if (self::getConfigValue('show_facebook_button') || self::getConfigValue('show_facebook_comment'))
		{
			$script = '(function(d, s, id) {
				var js, fjs = d.getElementsByTagName(s)[0];
				if (d.getElementById(id)) return;
				js = d.createElement(s); js.id = id;
				js.src = "//connect.facebook.net/' . self::getConfigValue('button_language', 'en_US') . '/all.js#xfbml=1&appId=' . self::getConfigValue('app_id', '372958799407679') . '";
				fjs.parentNode.insertBefore(js, fjs);
			}(document, "script","facebook-jssdk"));';
			$document->addScriptDeclaration($script);
			$uri = JUri::getInstance();
			$conf = JFactory::getConfig();
			$document->addCustomTag('<meta property="og:title" content="'.$product->product_name.'"/>');
			$document->addCustomTag('<meta property="og:image" content="'.self::getSiteUrl().$product->thumb_image.'"/>');
			$document->addCustomTag('<meta property="og:url" content="'.$uri->toString().'"/>');
			$document->addCustomTag('<meta property="og:description" content="'.$product->product_name.'"/>');
			$document->addCustomTag('<meta property="og:site_name" content="'.$conf->get('sitename').'"/>');
		}
	}
	
	/**
	 * 
	 * Function to get Itemid of Eshop component
	 * @return int
	 */
	public static function getItemid()
	{
		$user = JFactory::getUser();
		$db = JFactory::getDBO();
		$query = $db->getQuery(true);
		$query->select('id')
			->from('#__menu')
			->where('link LIKE "%index.php?option=com_eshop%"')
			->where('published = 1')
			->where('`access` IN ("' . implode(',', $user->getAuthorisedViewLevels()) . '")')
			->order('access');
		$db->setQuery($query);
		$itemId = $db->loadResult();
		if (!$itemId)
		{
			$Itemid = JRequest::getInt('Itemid');
			if ($Itemid == 1)
				$itemId = 999999;
			else
				$itemId = $Itemid;
		}
		return $itemId;
	}
	
	/**
	 *
	 * Function to get a list of the actions that can be performed.
	 * @return JObject
	 * @since 1.6
	 */
	public static function getActions()
	{
		$user = JFactory::getUser();
		$result = new JObject();
		$actions = array('core.admin', 'core.manage', 'core.create', 'core.edit', 'core.edit.own', 'core.edit.state', 'core.delete');
		$assetName = 'com_eshop';
		foreach ($actions as $action)
		{
			$result->set($action, $user->authorise($action, $assetName));
		}
	
		return $result;
	}

	/**
	 * 
	 * Function to display copy right information
	 */
	public static function displayCopyRight()
	{
		echo '<div class="copyright" style="text-align:center;margin-top: 5px;"><a href="http://joomdonation.com" target="_blank"><strong>EShop</strong></a> version 2.0.0, Copyright (C) 2012-2013 <a href="http://joomdonation.com" target="_blank"><strong>Ossolution Team</strong></a></div>';
	}

	/**
	 *
	 * Function to add dropdown menu
	 * @param string $vName        	
	 */
	public static function renderSubmenu($vName = 'dashboard')
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')
			->from('#__eshop_menus')
			->where('published = 1')
			->where('menu_parent_id = 0')
			->order('ordering');
		$db->setQuery($query);
		$menus = $db->loadObjectList();
		$html = '';
		$html .= '<ul class="nav nav-tabs">';
		for ($i = 0; $n = count($menus), $i < $n; $i++)
		{
			$menu = $menus[$i];
			$showCondition = true;
			if ($menu->menu_name == 'ESHOP_PLUGINS')
			{
				if (!JFactory::getUser()->authorise('eshop.payments', 'com_eshop') && !JFactory::getUser()->authorise('eshop.shippings', 'com_eshop') && !JFactory::getUser()->authorise('eshop.themes', 'com_eshop'))
					$showCondition = false;
			}
			elseif ($menu->menu_name == 'ESHOP_SALES') 
			{
				if (!JFactory::getUser()->authorise('eshop.orders', 'com_eshop') && !JFactory::getUser()->authorise('eshop.customers', 'com_eshop') && !JFactory::getUser()->authorise('eshop.customergroups', 'com_eshop') && !JFactory::getUser()->authorise('eshop.coupons', 'com_eshop') && !JFactory::getUser()->authorise('eshop.vouchers', 'com_eshop'))
					$showCondition = false;
			}
			elseif ($menu->menu_name == 'ESHOP_REPORTS')
			{
				if (!JFactory::getUser()->authorise('eshop.reports', 'com_eshop'))
					$showCondition = false;
			}
			
			if ($showCondition)
			{
				$query->clear();
				$query->select('*')
					->from('#__eshop_menus')
					->where('published = 1')
					->where('menu_parent_id = ' . intval($menu->id))
					->order('ordering');
				$db->setQuery($query);
				$subMenus = $db->loadObjectList();
				if (!count($subMenus))
				{
					$class = '';
					if ($menu->menu_view == $vName)
					{
						$class = ' class="active"';
					}
					$html .= '<li' . $class . '><a href="index.php?option=com_eshop&view=' . $menu->menu_view . '"><span class="icon-'.$menu->menu_class.'"></span> ' . JText::_($menu->menu_name) . '</a></li>';
				}
				else
				{
					$class = ' class="dropdown"';
					for ($j = 0; $m = count($subMenus), $j < $m; $j++)
					{
						$subMenu = $subMenus[$j];
						$lName = JRequest::getVar('layout');
						if ((!$subMenu->menu_layout && $vName == $subMenu->menu_view ) || ($lName != '' && $lName == $subMenu->menu_layout))
						{
							$class = ' class="dropdown active"';
							break;
						}
					}
					$html .= '<li' . $class . '>';
					$html .= '<a id="drop_' . $menu->id . '" href="#" data-toggle="dropdown" role="button" class="dropdown-toggle"><span class="icon-'.$menu->menu_class.'"></span> ' .
						 JText::_($menu->menu_name) . ' <b class="caret"></b></a>';
					$html .= '<ul aria-labelledby="drop_' . $menu->id . '" role="menu" class="dropdown-menu" id="menu_' . $menu->id . '">';
					for ($j = 0; $m = count($subMenus), $j < $m; $j++)
					{
						$subMenu = $subMenus[$j];
						$showSubCondition = true;
						if ($subMenu->menu_view == 'reviews' && !JFactory::getUser()->authorise('eshop.reviews', 'com_eshop'))
							$showSubCondition = false;
						elseif ($subMenu->menu_view == 'taxclasses' && !JFactory::getUser()->authorise('eshop.taxclasses', 'com_eshop'))
							$showSubCondition = false;
						elseif ($subMenu->menu_view == 'taxrates' && !JFactory::getUser()->authorise('eshop.taxrates', 'com_eshop'))
							$showSubCondition = false;
						elseif ($subMenu->menu_view == 'configuration' && !JFactory::getUser()->authorise('eshop.configuration', 'com_eshop'))
							$showSubCondition = false;
						elseif ($subMenu->menu_view == 'tools' && !JFactory::getUser()->authorise('eshop.tools', 'com_eshop'))
							$showSubCondition = false;
						if ($showSubCondition)
						{
							$layoutLink = '';
							if ($subMenu->menu_layout)
							{
								$layoutLink	= '&layout=' . $subMenu->menu_layout;
							}
							$class = '';
							$lName = JRequest::getVar('layout');
							if ((!$subMenu->menu_layout && $vName == $subMenu->menu_view ) || ($lName != '' && $lName == $subMenu->menu_layout))
							{
								$class = ' class="active"';
							}
							$html .= '<li' . $class . '><a href="index.php?option=com_eshop&view=' .
								 $subMenu->menu_view . $layoutLink . '" tabindex="-1"><span class="icon-'.$subMenu->menu_class.'"></span> ' . JText::_($subMenu->menu_name) . '</a></li>';
						}
					}
					$html .= '</ul>';
					$html .= '</li>';
				}
			}
		}
		$html .= '</ul>';
		if (version_compare(JVERSION, '3.0', 'le'))
		{
			JFactory::getDocument()->setBuffer($html, array('type' => 'modules', 'name' => 'submenu'));
		}
		else
		{
			echo $html;
		}
	}
	
	/**
	 * 
	 * Function to get value for a message
	 * @param string $messageName
	 * @return string
	 */
	public static function getMessageValue($messageName, $langCode = '')
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$language = JFactory::getLanguage();
		if (!$langCode)
			$langCode = $language->getTag();
		if (!$langCode)
			$langCode = 'en-GB';
		$language->load('com_eshop', JPATH_ROOT, $langCode);
		$query->select('a.message_value')
			->from('#__eshop_messagedetails AS a')
			->innerJoin('#__eshop_messages AS b ON a.message_id = b.id')
			->where('a.language = ' . $db->quote($langCode))
			->where('b.message_name = ' . $db->quote($messageName));
		$db->setQuery($query);
		$messageValue = $db->loadResult();
		if (!$messageValue)
		{
			$query->clear();
			$query->select('a.message_value')
				->from('#__eshop_messagedetails AS a')
				->innerJoin('#__eshop_messages AS b ON a.message_id = b.id')
				->where('a.language = "en-GB"')
				->where('b.message_name = ' . $db->quote($messageName));
			$db->setQuery($query);
			$messageValue = $db->loadResult();
		}
		return $messageValue;
	}
		
	/**
	 *
	 * Function to get information for a specific order
	 * @param int $orderId
	 * @return order Object
	 */
	public static function getOrder($orderId)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')
			->from('#__eshop_orders')
			->where('id = ' . (int) $orderId);
		$db->setQuery($query);
		return $db->loadObject();
	}
	
	/**
	 * 
	 * Function to get products for a specific order
	 * @param int $orderId
	 */
	public static function getOrderProducts($orderId)
	{
		$order = self::getOrder($orderId);
		$currency = new EshopCurrency();
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')
			->from('#__eshop_orderproducts')
			->where('order_id = ' . intval($orderId));
		$db->setQuery($query);
		$orderProducts = $db->loadObjectList();
		for ($i = 0; $n = count($orderProducts), $i < $n; $i++)
		{
			$orderProducts[$i]->orderOptions = self::getOrderOptions($orderProducts[$i]->id);
			$orderProducts[$i]->price = $currency->format($orderProducts[$i]->price, $order->currency_code, $order->currency_exchanged_value);
			$orderProducts[$i]->total_price = $currency->format($orderProducts[$i]->total_price, $order->currency_code, $order->currency_exchanged_value);
			//Get downloads for each order product
			$query->clear();
			$query->select('*')
				->from('#__eshop_orderdownloads')
				->where('order_id = ' . intval($orderId))
				->where('order_product_id = ' . $orderProducts[$i]->id);
			$db->setQuery($query);
			$orderProducts[$i]->downloads = $db->loadObjectList();
		}
		return $orderProducts;
	}
	
	/**
	 * 
	 * Function to get totals for a specific order
	 * @param int $orderId
	 * @return total object list
	 */
	public static function getOrderTotals($orderId)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')
			->from('#__eshop_ordertotals')
			->where('order_id = ' . intval($orderId))
			->order('id');
		$db->setQuery($query);
		return  $db->loadObjectList();
	}
	
	/**
	 * 
	 * Function to get options for a specific order product
	 * @param unknown $orderProductId
	 */
	public static function getOrderOptions($orderProductId)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')
			->from('#__eshop_orderoptions')
			->where('order_product_id = ' . (int) $orderProductId);
		$db->setQuery($query);
		return  $db->loadObjectList();
	}
	
	/**
	 *
	 * Function to get information for a specific quote
	 * @param int $quoteId
	 * @return quote Object
	 */
	public static function getQuote($quoteId)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')
			->from('#__eshop_quotes')
			->where('id = ' . (int) $quoteId);
		$db->setQuery($query);
		return $db->loadObject();
	}
	
	/**
	 *
	 * Function to get products for a specific quote
	 * @param int $quoteId
	 */
	public static function getQuoteProducts($quoteId)
	{
		$quote = self::getQuote($quoteId);
		$currency = new EshopCurrency();
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')
			->from('#__eshop_quoteproducts')
			->where('quote_id = '.(int)$quoteId);
		$db->setQuery($query);
		$quoteProducts = $db->loadObjectList();
		for ($i = 0; $n = count($quoteProducts), $i < $n; $i++)
		{
			$quoteProducts[$i]->quoteOptions = self::getQuoteOptions($quoteProducts[$i]->id);
			$quoteProducts[$i]->price = $currency->format($quoteProducts[$i]->price, $quote->currency_code, $quote->currency_exchanged_value);
			$quoteProducts[$i]->total_price = $currency->format($quoteProducts[$i]->total_price, $quote->currency_code, $quote->currency_exchanged_value);
		}
		return $quoteProducts;
	}
	
	/**
	 *
	 * Function to get options for a specific quote product
	 * @param unknown $quoteProductId
	 */
	public static function getQuoteOptions($quoteProductId)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')
		->from('#__eshop_quoteoptions')
		->where('quote_product_id = ' . (int) $quoteProductId);
		$db->setQuery($query);
		return  $db->loadObjectList();
	}
	
	/**
	 * 
	 * Function to get invoice output for products
	 * @param int $orderId
	 * @return string
	 */
	public static function getInvoiceProducts($orderId)
	{
		$viewConfig['name'] = 'invoice';
		$viewConfig['base_path'] = JPATH_ROOT.'/components/com_eshop/invoicetemplates';
		$viewConfig['template_path'] = JPATH_ROOT.'/components/com_eshop/invoicetemplates';
		$viewConfig['layout'] = 'default';
		$view =  new JViewLegacy($viewConfig);
		$orderProducts = self::getOrderProducts($orderId);
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		for ($i = 0; $n = count($orderProducts), $i < $n; $i++)
		{
			$query->clear();
			$query->select('*')
				->from('#__eshop_orderoptions')
				->where('order_product_id = ' . intval($orderProducts[$i]->id));
			$db->setQuery($query);
			$orderProducts[$i]->options = $db->loadObjectList();
		}
		$orderTotals = self::getOrderTotals($orderId);
		$view->assignRef('order_id', $orderId);
		$view->assignRef('order_products', $orderProducts);
		$view->assignRef('order_total',$orderTotals);
		ob_start();
		$view->display();
		$text = ob_get_contents();
		ob_end_clean();
		return $text;
	}
	
	/**
	 * Generate invoice PDF
	 * @param array $cid
	 */
	public static function generateInvoicePDF($cid)
	{
		$mainframe = JFactory::getApplication();
		$sitename = $mainframe->getCfg("sitename");
		require_once JPATH_ROOT . "/components/com_eshop/tcpdf/tcpdf.php";
		require_once JPATH_ROOT . "/components/com_eshop/tcpdf/config/lang/eng.php";
		JTable::addIncludePath(JPATH_ROOT . '/administrator/components/com_eshop/tables');
		$invoiceOutputs = '';
		$filename = '';
		for ($i = 0; $n = count($cid), $i< $n; $i++)
		{
			$id = $cid[$i];
			$row = JTable::getInstance('Eshop', 'Order');
			$row->load($id);
			// Initial pdf object
			$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
			$pdf->SetCreator(PDF_CREATOR);
			$pdf->SetAuthor($sitename);
			$pdf->SetTitle('Invoice');
			$pdf->SetSubject('Invoice');
			$pdf->SetKeywords('Invoice');
			$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
			$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
			$pdf->SetMargins(PDF_MARGIN_LEFT, 0, PDF_MARGIN_RIGHT);
			$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
			$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
			// Set auto page breaks
			$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
			// Set image scale factor
			$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
			$pdf->SetFont('times', '', 8);
			$pdf->AddPage();
			$invoiceOutput = self::getMessageValue('invoice_layout', $row->language);
			
			// Store information
			$replaces = array();
			$replaces['customer_name'] = $row->firstname . ' ' . $row->lastname;
			$replaces['invoice_number'] = self::formatInvoiceNumber($row->invoice_number, $row->created_date);
			$replaces['store_owner'] = self::getConfigValue('store_owner');
			$replaces['store_name'] = self::getConfigValue('store_name');
			$replaces['store_address'] = str_replace("\r\n", "<br />", self::getConfigValue('address'));
			$replaces['store_telephone'] = self::getConfigValue('telephone');
			$replaces['store_fax'] = self::getConfigValue('fax');
			$replaces['store_email'] = self::getConfigValue('email');
			$replaces['store_url'] = JUri::root();
			$replaces['date_added'] = JHtml::date($row->created_date, self::getConfigValue('date_format', 'm-d-Y'));
			$replaces['order_id'] = $row->id;
			$replaces['order_number'] = $row->order_number;
			$replaces['order_status'] = self::getOrderStatusName($row->order_status_id, $row->language);
			$replaces['payment_method'] = JText::_($row->payment_method_title);
			$replaces['shipping_method'] = $row->shipping_method_title;
				
			// Payment information
			$replaces['payment_address'] = self::getPaymentAddress($row);
			//Payment custom fields here
			$excludedFields = array('firstname', 'lastname', 'email', 'telephone', 'fax', 'company', 'company_id', 'address_1', 'address_2', 'city', 'postcode', 'country_id', 'zone_id');
			$form = new RADForm(self::getFormFields('B'));
			$fields = $form->getFields();
			foreach ($fields as $field)
			{
				$fieldName = $field->name;
				if (!in_array($fieldName, $excludedFields))
				{
					$fieldValue = $row->{'payment_'.$fieldName};
					if (is_string($fieldValue) && is_array(json_decode($fieldValue)))
					{
						$fieldValue = implode(', ', json_decode($fieldValue));
					}
					$replaces['payment_' . $fieldName] = $fieldValue;
				}
			}
			// Shipping information
			$replaces['shipping_address'] = self::getShippingAddress($row);
			//Shipping custom fields here
			$form = new RADForm(self::getFormFields('S'));
			$fields = $form->getFields();
			foreach ($fields as $field)
			{
				$fieldName = $field->name;
				if (!in_array($fieldName, $excludedFields))
				{
					$fieldValue = $row->{'shipping_'.$fieldName};
					if (is_string($fieldValue) && is_array(json_decode($fieldValue)))
					{
						$fieldValue = implode(', ', json_decode($fieldValue));
					}
					$replaces['shipping_' . $fieldName] = $fieldValue;
				}
			}
			// Products list
			$replaces['products_list'] = self::getInvoiceProducts($row->id);
			// Comment
			$replaces['comment'] = $row->comment;
			// Delivery Date
			$replaces['delivery_date'] = JHtml::date($row->delivery_date, self::getConfigValue('date_format', 'm-d-Y'));
			foreach ($replaces as $key => $value)
			{
				$key = strtoupper($key);
				$invoiceOutput = str_replace("[$key]", $value, $invoiceOutput);
			}
			$invoiceOutput = self::convertImgTags($invoiceOutput);
			if ($n > 1 && $i < ($n - 1))
				$invoiceOutput = '<div style="page-break-after: always;">' . $invoiceOutput . '</div>';
			$invoiceOutputs .= $invoiceOutput;
			$filename .= self::formatInvoiceNumber($row->invoice_number, $row->created_date);
			if ($i < ($n - 1))
				$filename .= '-';
		}
		$v = $pdf->writeHTML($invoiceOutputs, true, false, false, false, '');
		
		$filePath = JPATH_ROOT . '/media/com_eshop/invoices/' . $filename . '.pdf';
		$pdf->Output($filePath, 'F');
	}
	
	/**
	 * 
	 * Function to download invoice
	 * @param array $cid
	 */
	public static function downloadInvoice($cid)
	{
		JTable::addIncludePath(JPATH_ROOT . '/administrator/components/com_eshop/tables');
		$invoiceStorePath = JPATH_ROOT . '/media/com_eshop/invoices/';
		$filename = '';
		for ($i = 0; $n = count($cid), $i< $n; $i++)
		{
			$id = $cid[$i];
			$row = JTable::getInstance('Eshop', 'Order');
			$row->load($id);
			$filename .= self::formatInvoiceNumber($row->invoice_number, $row->created_date);
			if ($i < ($n - 1))
				$filename .= '-';
		}
		$filename .= '.pdf';
		self::generateInvoicePDF($cid);
		$invoicePath = $invoiceStorePath . $filename;
		while (@ob_end_clean());
		self::processDownload($invoicePath, $filename, true);
	}

	/**
	 * 
	 * Function to process download
	 * @param string $filePath
	 * @param string $filename
	 * @param boolean $download
	 */
	public static function processDownload($filePath, $filename, $download = false)
	{
		jimport('joomla.filesystem.file') ;						
		$fsize = @filesize($filePath);
		$mod_date = date('r', filemtime($filePath) );		
		if ($download) {
		    $cont_dis ='attachment';   
		} else {
		    $cont_dis ='inline';
		}		
		$ext = JFile::getExt($filename) ;
		$mime = self::getMimeType($ext);
		// required for IE, otherwise Content-disposition is ignored
		if(ini_get('zlib.output_compression'))  {
			ini_set('zlib.output_compression', 'Off');
		}
	    header("Pragma: public");
	    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	    header("Expires: 0");		
	    header("Content-Transfer-Encoding: binary");
		header('Content-Disposition:' . $cont_dis .';'
			. ' filename="' . JFile::getName($filename) . '";' 
			. ' modification-date="' . $mod_date . '";'
			. ' size=' . $fsize .';'
			); //RFC2183
	    header("Content-Type: "    . $mime );			// MIME type
	    header("Content-Length: "  . $fsize);
	
	    if( ! ini_get('safe_mode') ) { // set_time_limit doesn't work in safe mode
		    @set_time_limit(0);
	    }
	    self::readfile_chunked($filePath);
	}
	
	/**
	 * 
	 * Function to get mimetype of file
	 * @param string $ext
	 * @return string
	 */
	public static function getMimeType($ext)
	{
		require_once JPATH_ROOT . "/components/com_eshop/helpers/mime.mapping.php";
		foreach ($mime_extension_map as $key => $value)
		{
			if ($key == $ext)
			{
				return $value;
			}
		}
		return "";
	}
	
	/**
	 * 
	 * Function to read file
	 * @param string $filename
	 * @param boolean $retbytes
	 * @return boolean|number
	 */
	public static function readfile_chunked($filename, $retbytes = true)
	{
		$chunksize = 1 * (1024 * 1024); // how many bytes per chunk
		$buffer = '';
		$cnt = 0;
		$handle = fopen($filename, 'rb');
		if ($handle === false)
		{
			return false;
		}
		while (!feof($handle))
		{
			$buffer = fread($handle, $chunksize);
			echo $buffer;
			@ob_flush();
			flush();
			if ($retbytes)
			{
				$cnt += strlen($buffer);
			}
		}
		$status = fclose($handle);
		if ($retbytes && $status)
		{
			return $cnt; // return num. bytes delivered like readfile() does.
		}
		return $status;
	}
	
	/**
	 * Convert all img tags to use absolute URL
	 * @param string $html_content
	 */
	public static function convertImgTags($html_content)
	{
		$patterns = array();
		$replacements = array();
		$i = 0;
		$src_exp = "/src=\"(.*?)\"/";
		$link_exp = "[^http:\/\/www\.|^www\.|^https:\/\/|^http:\/\/]";
		$siteURL = JUri::root();
		preg_match_all($src_exp, $html_content, $out, PREG_SET_ORDER);
		foreach ($out as $val)
		{
			$links = preg_match($link_exp, $val[1], $match, PREG_OFFSET_CAPTURE);
			if ($links == '0')
			{
				$patterns[$i] = $val[1];
				$patterns[$i] = "\"$val[1]";
				$replacements[$i] = $siteURL . $val[1];
				$replacements[$i] = "\"$replacements[$i]";
			}
			$i++;
		}
		$mod_html_content = str_replace($patterns, $replacements, $html_content);
	
		return $mod_html_content;
	}
	
	/**
	 *
	 * Function to get order number product
	 * @param int $orderId
	 */
	public static function getNumberProduct($orderId)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('COUNT(id)')
		->from('#__eshop_orderproducts')
		->where('order_id=' . intval($orderId));
		$db->setQuery($query);
		return $db->loadResult();
	}
	
	/**
	 * 
	 * Function to get substring
	 * @param string $text
	 * @param int $length
	 * @param string $replacer
	 * @param boolean $isAutoStripsTag
	 * @return string
	 */
	public static function substring($text, $length = 100, $replacer = '...', $isAutoStripsTag = true )
	{
		$string = $isAutoStripsTag ? strip_tags($text) : $text;
		return JString::strlen($string) > $length ? JHtml::_('string.truncate', $string, $length ) : $string;
	}
	
	/**
	 * 
	 * Function to get alement alias
	 * @param int $id
	 * @param string $element
	 * @param string $langCode
	 * @return string
	 */
	public static function getElementAlias($id, $element, $langCode = '')
	{
		if (!$langCode)
			$langCode = JFactory::getLanguage()->getTag();
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select($element . '_name, ' . $element . '_alias')
			->from('#__eshop_' . $element . 'details')
			->where($element . '_id = ' . (int)$id )
			->where('language = "' . $langCode . '"');
		$db->setQuery($query);
		$row = $db->loadObject();
		if ($row->{$element . '_alias'} != '')
			return $row->{$element . '_alias'};
		else
			return $row->{$element . '_name'};
	}
	
	/**
	 * 
	 * Function to get categories navigation 
	 * @param int $id
	 */
	public static function getCategoriesNavigation($id)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		//Find previous/next categories
		$query->select('a.id, b.category_id, b.category_name, b.category_page_title')
			->from('#__eshop_categories AS a')
			->innerJoin('#__eshop_categorydetails AS b ON (a.id = b.category_id)')
			->where('a.published = 1')
			->where('a.category_parent_id = (SELECT category_parent_id FROM #__eshop_categories WHERE id = ' . intval($id) . ')')
			->where('b.language = "' . JFactory::getLanguage()->getTag() . '"')
			->order('a.ordering');
		$db->setQuery($query);
		$categories = $db->loadObjectList();
		for ($i = 0; $n = count($categories), $i < $n; $i++)
		{
			if ($categories[$i]->id == $id)
				break;
		}
		$categoriesNavigation = array(isset($categories[$i - 1]) ? $categories[$i - 1] : '', isset($categories[$i + 1]) ? $categories[$i + 1] : '');
		return $categoriesNavigation;
	}
	
	/**
	 *
	 * Function to get products navigation
	 * @param int $id
	 */
	public static function getProductsNavigation($id)
	{
		$mainframe = JFactory::getApplication();
		$fromView = $mainframe->getUserState('from_view');
		$sortOptions = $mainframe->getUserState('sort_options');
		$allowedSortArr = array('a.ordering', 'b.product_name', 'a.product_sku', 'a.product_price', 'a.product_length', 'a.product_width', 'a.product_height', 'a.product_weight', 'a.product_quantity', 'b.product_short_desc', 'b.product_desc', 'product_rates', 'product_reviews');
		$allowedDirectArr = array('ASC', 'DESC');
		$sort = 'a.ordering';
		$direct = 'ASC';
		if ($sortOptions != '')
		{
			$sortOptions = explode('-', $sortOptions);
			if (isset($sortOptions[0]) && in_array($sortOptions[0], $allowedSortArr))
			{
				$sort = $sortOptions[0];
			}
			if (isset($sortOptions[1]) && in_array($sortOptions[1], $allowedDirectArr))
			{
				$direct = $sortOptions[1];
			}
		}
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		if ($fromView == 'manufacturer')
		{
			//Find previous/next products
			$query->select('a.id, b.product_id, b.product_name, b.product_page_title')
				->from('#__eshop_products AS a')
				->innerJoin('#__eshop_productdetails AS b ON (a.id = b.product_id)')
				->where('a.published = 1')
				->where('a.manufacturer_id = (SELECT manufacturer_id FROM #__eshop_products WHERE id = ' . intval($id) . ')')
				->where('b.language = "' . JFactory::getLanguage()->getTag() . '"')
				->order($sort . ' ' . $direct)
				->order('a.ordering');
		}
		else
		{
			$categoryId = JRequest::getInt('catid');
			if (!$categoryId)
				$categoryId = 0;
			//Find previous/next products
			$query->select('a.id, b.product_id, b.product_name, b.product_page_title, pc.category_id')
				->from('#__eshop_products AS a')
				->innerJoin('#__eshop_productdetails AS b ON (a.id = b.product_id)')
				->innerJoin('#__eshop_productcategories AS pc ON (a.id = pc.product_id)')
				->where('a.published = 1')
				->where('pc.category_id = ' . intval($categoryId))
				->where('b.language = "' . JFactory::getLanguage()->getTag() . '"')
				->order($sort . ' ' . $direct)
				->order('a.ordering');
		}
		$db->setQuery($query);
		$products = $db->loadObjectList();
		for ($i = 0; $n = count($products), $i < $n; $i++)
		{
			if ($products[$i]->id == $id)
				break;
		}
		$productsNavigation = array(isset($products[$i - 1]) ? $products[$i - 1] : '', isset($products[$i + 1]) ? $products[$i + 1] : '');
		return $productsNavigation;
	}
	
	/**
	 * 
	 * Function to get category id/alias path
	 * @param int $id
	 * @param string $type
	 * @param string $langCode
	 * @param int $parentId
	 * @return array
	 */
	public static function getCategoryPath($id, $type, $langCode = '', $parentId = 0)
	{
		static $categories;
		if (!$langCode)
			$langCode = JFactory::getLanguage()->getTag();
		$db = JFactory::getDbo();
		if (empty($categories))
		{
			$query = $db->getQuery(true);
			$query->select('a.id, a.category_parent_id, b.category_alias')
				->from('#__eshop_categories AS a')
				->innerJoin('#__eshop_categorydetails AS b ON (a.id = b.category_id)')
				->where('b.language = "' . $langCode . '"');
			$db->setQuery($query);
			$categories = $db->loadObjectList('id');
		}
		$alias = array();
		$ids = array();
		do
		{
			if (!isset($categories[$id]))
			{
				break;
			}
			$alias[] = $categories[$id]->category_alias;
			$ids[] = $categories[$id]->id;
			$id = $categories[$id]->category_parent_id;
		}
		while ($id != $parentId);
		if ($type == 'id')
			return array_reverse($ids);
		else 
			return array_reverse($alias);
	}
	
	/**
	 * 
	 * Function to get categories bread crumb
	 * @param int $id
	 * @param int $parentId
	 * @param string $langCode
	 * @return array
	 */
	public static function getCategoriesBreadcrumb($id, $parentId, $langCode = '')
	{
		$db = JFactory::getDbo();
		if (!$langCode)
			$langCode = JFactory::getLanguage()->getTag();
		$query = $db->getQuery(true);
		$query->select('a.id, a.category_parent_id, b.category_name')
			->from('#__eshop_categories AS a')
			->innerJoin('#__eshop_categorydetails AS b ON (a.id = b.category_id)')
			->where('a.published = 1')
			->where('b.language = "' . $langCode . '"');
		$db->setQuery($query);
		$categories = $db->loadObjectList('id');
		$paths = array();
		while($id != $parentId)
		{
			if (isset($categories[$id]))
			{
				$paths[] = $categories[$id];
				$id = $categories[$id]->category_parent_id;
			}
			else
			{
				break;
			}
		}
		return $paths;
	}
	
	/**
	 * 
	 * Function to get category name path
	 * @param int $id
	 * @param string $langCode
	 * @return string
	 */
	public static function getCategoryNamePath($id, $langCode = '')
	{
		if (!$langCode)
			$langCode = JFactory::getLanguage()->getTag();
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('a.id, a.category_parent_id, b.category_name')
			->from('#__eshop_categories AS a')
			->innerJoin('#__eshop_categorydetails AS b ON (a.id = b.category_id)')
			->where('b.language = "' . $langCode . '"');
		$db->setQuery($query);
		$categories = $db->loadObjectList('id');
		$names = array();
		do
		{
			$names[] = $categories[$id]->category_name;
			$id = $categories[$id]->category_parent_id;
		}
		while ($id != 0);
		return array_reverse($names);
	}
	
	/**
	 * 
	 * Function to identify if price will be showed or not
	 * @return boolean
	 */
	public static function showPrice()
	{
		$displayPrice = EshopHelper::getConfigValue('display_price', 'public');
		if ($displayPrice == 'public')
		{
			$showPrice = true;
		}
		elseif ($displayPrice == 'hide')
		{
			$showPrice = false;
		}
		else 
		{
			$user = JFactory::getUser();
			if ($user->get('id'))
				$showPrice = true;
			else
				$showPrice = false;
		}
		return $showPrice;
	}

	/**
	 * 
	 * Function to get default address id
	 * @param int $id
	 * @return int
	 */
	public static function getDefaultAddressId($id)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('address_id')
			->from('#__eshop_customers')
			->where('customer_id = '.(int)$id);
		$db->setQuery($query);
		return $db->loadResult();
	}
	
	/**
	 * 
	 * Function to count address for current user
	 * @return int
	 */
	public static function countAddress()
	{
		$user = JFactory::getUser();
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('COUNT(id)')
			->from('#__eshop_addresses')
			->where('customer_id='.(int)$user->get('id'));
		$db->setQuery($query);
		return $db->loadResult();
	}
	
	/**
	 * 
	 * Function to get continue shopping url
	 * @return string
	 */
	public static function getContinueShopingUrl()
	{
		$session = JFactory::getSession();
		if ($session->get('continue_shopping_url'))
		{
			$url = $session->get('continue_shopping_url');
		}
		else 
		{
			$url = JUri::root();
		}
		return $url;
	}
	
	/**
	 * 
	 * Function to get coupon
	 * @param string $couponCode
	 * @return object
	 */
	public static function getCoupon($couponCode)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')
			->from('#__eshop_coupons')
			->where('coupon_code = "' . $couponCode . '"');
		$db->setQuery($query);
		return $db->loadObject();
	}
	
	/**
	 *
	 * Function to get voucher
	 * @param string $voucherCode
	 * @return object
	 */
	public static function getVoucher($voucherCode)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')
			->from('#__eshop_vouchers')
			->where('voucher_code = "' . $voucherCode . '"');
		$db->setQuery($query);
		return $db->loadObject();
	}
	
	public static function getProductLabels($productId, $langCode = '')
	{
		if (!$langCode)
			$langCode = JFactory::getLanguage()->getTag();
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('DISTINCT(label_id)')
			->from('#__eshop_labelelements')
			->where('(element_type = "product" AND element_id = '.intval($productId).') OR (element_type = "manufacturer" AND element_id = (SELECT manufacturer_id FROM #__eshop_products WHERE id = '.intval($productId).')) OR (element_type = "category" AND element_id IN (SELECT category_id FROM #__eshop_productcategories WHERE product_id = '.intval($productId).'))');
		$db->setQuery($query);
		$labelIds = $db->loadColumn();
		if (count($labelIds))
		{
			$query->clear();
			$query->select('a.*, b.label_name')
				->from('#__eshop_labels AS a')
				->innerJoin('#__eshop_labeldetails AS b ON (a.id = b.label_id)')
				->where('a.id IN (' . implode(',', $labelIds) . ')')
				->where('a.published = 1')
				->where('(label_start_date = "0000-00-00 00:00:00" OR label_start_date < NOW())')
				->where('(label_end_date = "0000-00-00 00:00:00" OR label_end_date > NOW())')
				->where('b.language = "' . $langCode . '"')
				->order('a.ordering');
			$db->setQuery($query);
			$rows = $db->loadObjectList();
			$imagePath = JPATH_ROOT . '/media/com_eshop/labels/';
			$imageSizeFunction = EshopHelper::getConfigValue('label_image_size_function', 'resizeImage');
			for ($i = 0; $n = count($rows), $i < $n; $i++)
			{
				$row = $rows[$i];
				if ($row->label_image)
				{
					//Do the resize
					$imageWidth = $row->label_image_width > 0 ? $row->label_image_width : EshopHelper::getConfigValue('image_label_width');
					if (!$imageWidth)
						$imageWidth = 50;
					$imageHeight = $row->label_image_height > 0 ? $row->label_image_height : EshopHelper::getConfigValue('image_label_height');
					if (!$imageHeight)
						$imageHeight = 50;
					if (!JFile::exists($imagePath . 'resized/' . JFile::stripExt($row->label_image).'-'.$imageWidth.'x'.$imageHeight.'.'.JFile::getExt($row->label_image)))
					{
						$rows[$i]->label_image = JUri::base(true) . '/media/com_eshop/labels/resized/' . call_user_func_array(array('EshopHelper', $imageSizeFunction), array($row->label_image, $imagePath, $imageWidth, $imageHeight));
					}
					else 
					{
						$rows[$i]->label_image = JUri::base(true) . '/media/com_eshop/labels/resized/' . JFile::stripExt($row->label_image).'-'.$imageWidth.'x'.$imageHeight.'.'.JFile::getExt($row->label_image);
					}
				}
			}
			return $rows;
		}
		else
		{
			return array();
		}
	}
	
	/**
	 * Get URL of the site, using for Ajax request
	 */
	public static function getSiteUrl()
	{
		$uri = JUri::getInstance();
		$base = $uri->toString(array('scheme', 'host', 'port'));
		if (strpos(php_sapi_name(), 'cgi') !== false && !ini_get('cgi.fix_pathinfo') && !empty($_SERVER['REQUEST_URI']))
		{
			$script_name = $_SERVER['PHP_SELF'];
		}
		else
		{
			$script_name = $_SERVER['SCRIPT_NAME'];
		}
		$path = rtrim(dirname($script_name), '/\\');
		if ($path)
		{
			$siteUrl = $base.$path.'/';
		}
		else
		{
			$siteUrl = $base.'/';
		}
		if (JFactory::getApplication()->isAdmin())
		{
			$adminPos         = strrpos($siteUrl, 'administrator/');
			$siteUrl = substr_replace($siteUrl, '', $adminPos, 14);
		}
		return $siteUrl;
	}
	
	/**
	 * 
	 * Function to get checkout type
	 * @return string
	 */
	public static function getCheckoutType()
	{
		$cart = new EshopCart();
		if (EshopHelper::getConfigValue('display_price') == 'registered')
		{
			//Only registered
			$checkoutType = 'registered_only';
		}
		else
		{
			$checkoutType = EshopHelper::getConfigValue('checkout_type');
		}
		return $checkoutType;
	}
	
	/**
	 * Get form billing or shopping form fields
	 * @param string $addressType
	 * @return array
	 */
	public static function getFormFields($addressType, $excludedFields = array())
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')
			->from('#__eshop_fields AS a')
			->innerJoin('#__eshop_fielddetails AS b ON a.id=b.field_id')
			->where('a.published = 1')
			->where('(address_type='.$db->quote($addressType).' OR address_type="A")')
			->where('b.language = "' . JFactory::getLanguage()->getTag() . '"')
			->order('a.ordering');
		if (count($excludedFields) > 0)
		{
			foreach ($excludedFields as $fieldName)
			{
				$query->where('name != "' . $fieldName . '"');
			}
		}
		$db->setQuery($query);
		return $db->loadObjectList();
	}
		
	/**
	 * Check if the country has zones or not
	 * @param int $countryId
	 * @return boolean
	 */
	public static function hasZone($countryId)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('COUNT(*)')
			->from('#__eshop_zones')
			->where('country_id='.(int)$countryId)
			->where('published=1');
		$db->setQuery($query);
		$total = (int) $db->loadResult();
		if ($total)
		{
			return TRUE;
		}
		else 
		{
			return FALSE;	
		}
	}

	/**
	 * 
	 * Function to get Shipping Address Format
	 * @param order object $row
	 * @return string
	 */
	public static function getShippingAddress($row)
	{
		$shippingAddress = self::getConfigValue('shipping_address_format', '[SHIPPING_FIRSTNAME] [SHIPPING_LASTNAME]<br /> [SHIPPING_ADDRESS_1], [SHIPPING_ADDRESS_2]<br /> [SHIPPING_CITY], [SHIPPING_POSTCODE] [SHIPPING_ZONE_NAME]<br /> [SHIPPING_EMAIL]<br /> [SHIPPING_TELEPHONE]<br /> [SHIPPING_FAX]');
		$shippingAddress = str_replace('[SHIPPING_FIRSTNAME]', $row->shipping_firstname, $shippingAddress);
		if (EshopHelper::isFieldPublished('lastname') && $row->shipping_lastname != '')
		{
			$shippingAddress = str_replace('[SHIPPING_LASTNAME]', $row->shipping_lastname, $shippingAddress);
		}
		else 
		{
			$shippingAddress = str_replace('[SHIPPING_LASTNAME]', '', $shippingAddress);
		}
		$shippingAddress = str_replace('[SHIPPING_ADDRESS_1]', $row->shipping_address_1, $shippingAddress);
		if (EshopHelper::isFieldPublished('address_2') && $row->shipping_address_2 != '')
		{
			$shippingAddress = str_replace(', [SHIPPING_ADDRESS_2]', ', ' . $row->shipping_address_2, $shippingAddress);
			$shippingAddress = str_replace('[SHIPPING_ADDRESS_2]', $row->shipping_address_2, $shippingAddress);
		}
		else 
		{
			$shippingAddress = str_replace(', [SHIPPING_ADDRESS_2]', '', $shippingAddress);
			$shippingAddress = str_replace('[SHIPPING_ADDRESS_2]', '', $shippingAddress);
		}
		if (EshopHelper::isFieldPublished('city'))
		{
			$shippingAddress = str_replace('<br /> [SHIPPING_CITY]', '<br />' . $row->shipping_city, $shippingAddress);
			$shippingAddress = str_replace('[SHIPPING_CITY]', $row->shipping_city, $shippingAddress);
		}
		else 
		{
			$shippingAddress = str_replace('<br /> [SHIPPING_CITY]', '', $shippingAddress);
			$shippingAddress = str_replace('[SHIPPING_CITY]', '', $shippingAddress);
		}
		if (EshopHelper::isFieldPublished('postcode') && $row->shipping_postcode != '')
		{
			$shippingAddress = str_replace(', [SHIPPING_POSTCODE]', ', ' . $row->shipping_postcode, $shippingAddress);
			$shippingAddress = str_replace('[SHIPPING_POSTCODE]', $row->shipping_postcode, $shippingAddress);
		}
		else 
		{
			$shippingAddress = str_replace(', [SHIPPING_POSTCODE]', '', $shippingAddress);
			$shippingAddress = str_replace('[SHIPPING_POSTCODE]', '', $shippingAddress);
		}
		$shippingAddress = str_replace('[SHIPPING_EMAIL]', $row->shipping_email, $shippingAddress);
		if (EshopHelper::isFieldPublished('telephone') && $row->shipping_telephone != '')
		{
			$shippingAddress = str_replace('<br /> [SHIPPING_TELEPHONE]', '<br /> ' . $row->shipping_telephone, $shippingAddress);
			$shippingAddress = str_replace('[SHIPPING_TELEPHONE]', $row->shipping_telephone, $shippingAddress);
		}
		else
		{
			$shippingAddress = str_replace('<br /> [SHIPPING_TELEPHONE]', '', $shippingAddress);
			$shippingAddress = str_replace('[SHIPPING_TELEPHONE]', '', $shippingAddress);
		}
		if (EshopHelper::isFieldPublished('fax') && $row->shipping_fax != '')
		{
			$shippingAddress = str_replace('<br /> [SHIPPING_FAX]', '<br /> ' . $row->shipping_fax, $shippingAddress);
			$shippingAddress = str_replace('[SHIPPING_FAX]', $row->shipping_fax, $shippingAddress);
		}
		else 
		{
			$shippingAddress = str_replace('<br /> [SHIPPING_FAX]', '', $shippingAddress);
			$shippingAddress = str_replace('[SHIPPING_FAX]', '', $shippingAddress);
		}
		if (EshopHelper::isFieldPublished('company') && $row->shipping_company != '')
		{
			$shippingAddress = str_replace('[SHIPPING_COMPANY]', $row->shipping_company, $shippingAddress);
		}
		else 
		{
			$shippingAddress = str_replace('[SHIPPING_COMPANY]', '', $shippingAddress);
		}
		if (EshopHelper::isFieldPublished('company_id') && $row->shipping_company_id != '')
		{
			$shippingAddress = str_replace('[SHIPPING_COMPANY_ID]', $row->shipping_company_id, $shippingAddress);
		}
		else 
		{
			$shippingAddress = str_replace('[SHIPPING_COMPANY_ID]', '', $shippingAddress);
		}
		if (EshopHelper::isFieldPublished('zone_id') && $row->shipping_zone_name != '')
		{
			$shippingAddress = str_replace('[SHIPPING_ZONE_NAME]', $row->shipping_zone_name, $shippingAddress);
		}
		else 
		{
			$shippingAddress = str_replace('[SHIPPING_ZONE_NAME]', '', $shippingAddress);
		}
		if (EshopHelper::isFieldPublished('country_id') && $row->shipping_country_name != '')
		{
			$shippingAddress = str_replace('[SHIPPING_COUNTRY_NAME]', $row->shipping_country_name, $shippingAddress);
		}
		else
		{
			$shippingAddress = str_replace('[SHIPPING_COUNTRY_NAME]', '', $shippingAddress);
		}
		return $shippingAddress;
	}
	
	/**
	 *
	 * Function to get Payment Address Format
	 * @param order object $row
	 * @return string
	 */
	public static function getPaymentAddress($row)
	{
		$paymentAddress = self::getConfigValue('payment_address_format', '[PAYMENT_FIRSTNAME] [PAYMENT_LASTNAME]<br /> [PAYMENT_ADDRESS_1], [PAYMENT_ADDRESS_2]<br /> [PAYMENT_CITY], [PAYMENT_POSTCODE] [PAYMENT_ZONE_NAME]<br /> [PAYMENT_EMAIL]<br /> [PAYMENT_TELEPHONE]<br /> [PAYMENT_FAX]');
		$paymentAddress = str_replace('[PAYMENT_FIRSTNAME]', $row->payment_firstname, $paymentAddress);
		if (EshopHelper::isFieldPublished('lastname') && $row->payment_lastname != '')
		{
			$paymentAddress = str_replace('[PAYMENT_LASTNAME]', $row->payment_lastname, $paymentAddress);
		}
		else 
		{
			$paymentAddress = str_replace('[PAYMENT_LASTNAME]', '', $paymentAddress);
		}
		$paymentAddress = str_replace('[PAYMENT_ADDRESS_1]', $row->payment_address_1, $paymentAddress);
		if (EshopHelper::isFieldPublished('address_2') && $row->payment_address_2 != '')
		{
			$paymentAddress = str_replace(', [PAYMENT_ADDRESS_2]', ', ' . $row->payment_address_2, $paymentAddress);
			$paymentAddress = str_replace('[PAYMENT_ADDRESS_2]', $row->payment_address_2, $paymentAddress);
		}
		else 
		{
			$paymentAddress = str_replace(', [PAYMENT_ADDRESS_2]', '', $paymentAddress);
			$paymentAddress = str_replace('[PAYMENT_ADDRESS_2]', '', $paymentAddress);
		}
		if (EshopHelper::isFieldPublished('city'))
		{
			$paymentAddress = str_replace('<br /> [PAYMENT_CITY]', '<br /> ' . $row->payment_city, $paymentAddress);
			$paymentAddress = str_replace('[PAYMENT_CITY]', $row->payment_city, $paymentAddress);
		}
		else 
		{
			$paymentAddress = str_replace('<br /> [PAYMENT_CITY]', '', $paymentAddress);
			$paymentAddress = str_replace('[PAYMENT_CITY]', '', $paymentAddress);
		}
		if (EshopHelper::isFieldPublished('postcode') && $row->payment_postcode != '')
		{
			$paymentAddress = str_replace(', [PAYMENT_POSTCODE]', ', ' . $row->payment_postcode, $paymentAddress);
			$paymentAddress = str_replace('[PAYMENT_POSTCODE]', $row->payment_postcode, $paymentAddress);
		}
		else 
		{
			$paymentAddress = str_replace(', [PAYMENT_POSTCODE]', '', $paymentAddress);
			$paymentAddress = str_replace('[PAYMENT_POSTCODE]', '', $paymentAddress);
		}
		$paymentAddress = str_replace('[PAYMENT_EMAIL]', $row->payment_email, $paymentAddress);
		if (EshopHelper::isFieldPublished('telephone') && $row->payment_telephone != '')
		{
			$paymentAddress = str_replace('<br /> [PAYMENT_TELEPHONE]', '<br /> ' . $row->payment_telephone, $paymentAddress);
			$paymentAddress = str_replace('[PAYMENT_TELEPHONE]', $row->payment_telephone, $paymentAddress);
		}
		else
		{
			$paymentAddress = str_replace('<br /> [PAYMENT_TELEPHONE]', '', $paymentAddress);
			$paymentAddress = str_replace('[PAYMENT_TELEPHONE]', '', $paymentAddress);
		}
		if (EshopHelper::isFieldPublished('fax') && $row->payment_fax != '')
		{
			$paymentAddress = str_replace('<br /> [PAYMENT_FAX]', '<br /> ' . $row->payment_fax, $paymentAddress);
			$paymentAddress = str_replace('[PAYMENT_FAX]', $row->payment_fax, $paymentAddress);
		}
		else 
		{
			$paymentAddress = str_replace('<br /> [PAYMENT_FAX]', '', $paymentAddress);
			$paymentAddress = str_replace('[PAYMENT_FAX]', '', $paymentAddress);
		}
		if (EshopHelper::isFieldPublished('company') && $row->payment_company != '')
		{
			$paymentAddress = str_replace('[PAYMENT_COMPANY]', $row->payment_company, $paymentAddress);
		}
		else 
		{
			$paymentAddress = str_replace('[PAYMENT_COMPANY]', '', $paymentAddress);
		}
		if (EshopHelper::isFieldPublished('company_id') && $row->payment_company_id != '')
		{
			$paymentAddress = str_replace('[PAYMENT_COMPANY_ID]', $row->payment_company_id, $paymentAddress);
		}
		else 
		{
			$paymentAddress = str_replace('[PAYMENT_COMPANY_ID]', '', $paymentAddress);
		}
		if (EshopHelper::isFieldPublished('zone_id') && $row->payment_zone_name != '')
		{
			$paymentAddress = str_replace('[PAYMENT_ZONE_NAME]', $row->payment_zone_name, $paymentAddress);
		}
		else 
		{
			$paymentAddress = str_replace('[PAYMENT_ZONE_NAME]', '', $paymentAddress);
		}
		if (EshopHelper::isFieldPublished('country_id') && $row->payment_country_name != '')
		{
			$paymentAddress = str_replace('[PAYMENT_COUNTRY_NAME]', $row->payment_country_name, $paymentAddress);
		}
		else
		{
			$paymentAddress = str_replace('[PAYMENT_COUNTRY_NAME]', '', $paymentAddress);
		}
		return $paymentAddress;
	}
	
	/**
	 * 
	 * Function to identify if cart mode is available for a specific product or not
	 * @param object $product
	 * @return boolean
	 */
	public static function isCartMode($product)
	{
		$isCartMode = true;
		if (EshopHelper::getConfigValue('catalog_mode'))
		{
			$isCartMode = false;
		}
		else
		{
			if (!EshopHelper::showPrice() || $product->product_call_for_price || ($product->product_quantity <= 0 && !EshopHelper::getConfigValue('stock_checkout')))
			{
				$isCartMode = false;
			}
		}
		return $isCartMode;		
	}
	
	/**
	 *
	 * Function to identify if quote mode is available for a specific product or not
	 * @param object $product
	 * @return boolean
	 */
	public static function isQuoteMode($product)
	{
		if (EshopHelper::getConfigValue('quote_cart_mode') && $product->product_quote_mode)
		{
			$isQuoteMode = true;
		}
		else
		{
			$isQuoteMode = false;
		}
		return $isQuoteMode;
	}
	
	/**
	 * 
	 * Function to integrate with iDevAffiliate
	 * @param order object $order
	 */
	public static function iDevAffiliate($order)
	{
		$orderNumber = $order->order_number;
		$orderTotal = $order->total;
		$ipAddress      = $_SERVER['REMOTE_ADDR'];
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, self::getSiteUrl() . self::getConfigValue('idevaffiliate_path') . "/sale.php?profile=72198&idev_saleamt=" . $orderTotal . "&idev_ordernum=" . $orderNumber . "&ip_address=" . $ipAddress);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_exec($ch);
		curl_close($ch);
	}
	
	/**
	 * 
	 * Function to check if a field is published or not
	 * @param string $fieldName
	 * @return boolean
	 */
	public static function isFieldPublished($fieldName)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('id')
			->from('#__eshop_fields')
			->where('name = "' . $fieldName . '"')
			->where('published = 1');
		$db->setQuery($query);
		if ($db->loadResult())
		{
			return true;
		}
		else
		{
			return false;
		}
	}
	
	public static function sendNotify($numberEmails, $bccEmail = NULL)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('a.*, b.product_name')
			->from('#__eshop_notify AS a')
			->innerJoin('#__eshop_productdetails AS b ON a.product_id = b.product_id')
			->innerJoin('#__eshop_products AS c ON (a.product_id = c.id AND c.product_quantity > 0)')
			->where('a.sent_email = 0')
			->where('b.language = "' . JFactory::getLanguage()->getTag() . '"')
			->order('a.id');
		$db->setQuery($query, 0, $numberEmails);
		$rows = $db->loadObjectList();
		if (count($rows))
		{
			$mailer = JFactory::getMailer();
			if ($bccEmail)
			{
				$mailer->addBcc($bccEmail);
			}
			$fromName = JFactory::getConfig()->get('from_name');
			$fromEmail = JFactory::getConfig()->get('mailfrom');
			for ($i = 0; $n = count($rows), $i < $n; $i++)
			{
				$row = $rows[$i];
				// Send email first
				$subject = sprintf(JText::_('ESHOP_NOTIFY_SUBJECT'), $row->product_name);
				$body = self::getNotifyEmailBody($row);
				$mailer->sendMail($fromEmail, $fromName, $row->notify_email, $subject, $body, 1);
				$mailer->clearAddresses();
				// Then update to notify table
				$query->clear();
				$query->update('#__eshop_notify')
					->set('sent_email = 1')
					->set('sent_date = NOW()')
					->where('id = ' . (int) $row->id);
				$db->setQuery($query);
				$db->execute();
			}
		}
	}
	
	/**
	 *
	 * Function to get notify email body
	 * @param array $row
	 */
	public static function getNotifyEmailBody($row)
	{
		$notifyEmailBody = self::getMessageValue('notify_email');
		$replaces = array();
		$replaces['product_name'] = $row->product_name;
		$replaces['product_link'] = JRoute::_(EshopRoute::getProductRoute($row->product_id, self::getProductCategory($row->product_id)));
		foreach ($replaces as $key => $value)
		{
			$key = strtoupper($key);
			$notifyEmailBody = str_replace("[$key]", $value, $notifyEmailBody);
		}
		return $notifyEmailBody;
	}
	
	/**
	 * 
	 * Function to get installed version
	 * @return string
	 */
	public static function getInstalledVersion()
	{
		return '2.0.0';
	}
}