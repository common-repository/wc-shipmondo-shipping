<?php

/*********************************************************************/
/*  PROGRAM          FlexRC                                          */
/*  PROPERTY         604-1097 View St                                 */
/*  OF               Victoria BC   V8V 0G9                          */
/*  				 Voice 604 800-7879                              */
/*                                                                   */
/*  Any usage / copying / extension or modification without          */
/*  prior authorization is prohibited                                */
/*********************************************************************/

namespace OneTeamSoftware\WooCommerce\Shipping\Adapter;

defined('ABSPATH') || exit;

if (!class_exists(__NAMESPACE__ . '\\Shipmondo')):

require_once(__DIR__ . '/AbstractAdapter.php');

class Shipmondo extends AbstractAdapter
{
	protected $apiUser;
	protected $apiKey;

	public function __construct($id, array $settings = array())
	{
		$this->apiUser = null;
		$this->apiKey = null;

		parent::__construct($id, $settings);

		$this->currencies = array(
			'DKK' => __('DKK', $this->id),
			'NOK' => __('NOK', $this->id),
			'GBP' => __('GBP', $this->id),
			'CHF' => __('CHF', $this->id),
			'EUR' => __('EUR', $this->id),
			'ALL' => __('ALL', $this->id),
			'BAM' => __('BAM', $this->id),
			'BGN' => __('BGN', $this->id),
			'HRK' => __('HRK', $this->id),
			'CZK' => __('CZK', $this->id),
			'ISK' => __('ISK', $this->id),
			'CHF' => __('CHF', $this->id),
			'MDL' => __('MDL', $this->id),
			'MKD' => __('MKD', $this->id),
			'PLN' => __('PLN', $this->id),
			'RON' => __('RON', $this->id),
			'RSD' => __('RSD', $this->id),
		);

		$this->statuses = array(
			'NEUTRAL' => __('Shipping Label Created', $this->id),
			'GENERAL' => __('General', $this->id),
			'INFORMED' => __('Informed', $this->id),
			'EN_ROUTE' => __('In Transit', $this->id),
			'AVAILABLE_FOR_DELIVERY' => __('Available for Delivery', $this->id),
			'DELIVERED' => __('Delivered', $this->id),
			'PICKUP_REQUESTED' => __('Pickup Requested', $this->id),
			'PROGRESSING' => __('Processing', $this->id),
			'WARNING' => __('Warning', $this->id),
			'DANGER' => __('Danger', $this->id),
			'SUCCESS' => __('Success', $this->id),
		);

		$this->completedStatuses = array(
			'DELIVERED',
			'DANGER'
		);

		$this->contentTypes = array(
			'other' => __('Other', $this->id),
			'gift' => __('Gift', $this->id),
			'documents' => __('Documents', $this->id),
			'commercial_samples' => __('Commercial Samples', $this->id),
			'returned_goods' => __('Returned Goods', $this->id),
		);

		$this->initCarriers();
		$this->initServices();
		$this->initPackageTypes();
	}

	public function getName()
	{
		return 'Shipmondo';
	}

	public function hasCustomItemsFeature()
	{
		return true;
	}

	public function hasTariffFeature()
	{
		return true;
	}

	public function hasUseSellerAddressFeature()
	{
		return true;
	}

	public function hasReturnLabelFeature()
	{
		return true;
	}

	public function hasLinkFeature()
	{
		return true;
	}

	public function hasOriginFeature()
	{
		return true;
	}

	public function hasUpdateShipmentsFeature()
	{
		return true;
	}

	public function hasCreateManifestsFeature()
	{
		return true;
	}

	public function hasCarriersFeature()
	{
		return true;
	}

	public function validate(array $settings)
	{
		$errors = array();

		$this->setSettings($settings);

		$response = $this->sendRequest('shipments?per_page=1&page=1', 'GET');
		if (!empty($response['error']['message'])) {
			$errors[] = sprintf('<strong>%s</strong>', $response['error']['message']);
		}

		return $errors;
	}

	public function getIntegrationFormFields()
	{
		$formFields = array(
			'apiUser' => array(
				'title' => __('API User', $this->id),
				'type' => 'text',
				'description' => sprintf(
					'%s <a href="%s" target="_blank">%s</a>', 
					__('You can find it at', $this->id), 
					'https://app.shipmondo.com/main/app/#/setting/api', 
					__('Settings -> API -> Access', $this->id)
				),
			),
			'apiKey' => array(
				'title' => __('API Key', $this->id),
				'type' => 'text',
				'description' => sprintf(
					'%s <a href="%s" target="_blank">%s</a>', 
					__('You can find it at', $this->id), 
					'https://app.shipmondo.com/main/app/#/setting/api', 
					__('Settings -> API -> Access', $this->id)
				),
			),
		);

		return $formFields;
	}

	public function getRates(array $params)
	{
		$this->logger->debug(__FILE__, __LINE__, 'getRates');

		$cacheKey = $this->getRatesCacheKey($params);
		$response = $this->getCacheValue($cacheKey);
		if (empty($response)) {		
			$params['function'] = __FUNCTION__;
			$response = $this->sendRequest('quotes/list', 'POST', $params);
	
			if (!empty($response['shipment'])) {
				$this->logger->debug(__FILE__, __LINE__, 'Cache shipment for the future');
		
				$this->setCacheValue($cacheKey, $response);
			}
		} else {
			$this->logger->debug(__FILE__, __LINE__, 'Found previously returned rates, so return them');
		}

		return $response;
	}

	public function getCacheKey(array $params)
	{
		$cacheKey = parent::getCacheKey($params);
		$cacheKey .= '_' . $this->getApiKey();

		return md5($cacheKey);
	}

	protected function getRatesCacheKey(array $params)
	{
		if (isset($params['service'])) {
			unset($params['service']);
		}

		if (isset($params['function'])) {
			unset($params['function']);
		}

		return $this->getCacheKey($params) . '_rates';
	}

	protected function getRatesParams(array $inParams)
	{
		$this->logger->debug(__FILE__, __LINE__, 'getRatesParams');

		$params = array();

		if (empty($inParams['origin']) && !empty($this->origin)) {
			$inParams['origin'] = $this->origin;
		}

		if (!empty($inParams['origin'])) {
			$this->logger->debug(__FILE__, __LINE__, 'From Address: ' . print_r($inParams['origin'], true));

			$params['sender'] = $this->prepareAddress($inParams['origin']);
		}
		
		if (!empty($inParams['destination'])) {
			$this->logger->debug(__FILE__, __LINE__, 'To Address: ' . print_r($inParams['destination'], true));

			$params['receiver'] = $this->prepareAddress($inParams['destination']);
		}

		if (!empty($inParams['return'])) {
			$sender = $params['sender'];
			$params['sender'] = $params['receiver'];
			$params['receiver'] = $sender;
		}

		$params['parcels'][0] = $this->prepareParcel($inParams);

		return $params;
	}

	protected function prepareParcel(array $inParams)
	{
		$this->logger->debug(__FILE__, __LINE__, 'prepareParcel');

		$parcel = array();

		if (!empty($inParams['weight'])) {
			$fromWeightUnit = $this->weightUnit;
			if (isset($inParams['weight_unit'])) {
				$fromWeightUnit = $inParams['weight_unit'];
			}

			$parcel['weight'] = round(wc_get_weight($inParams['weight'], 'g', $fromWeightUnit), 2);
		}

		if (!empty($inParams['length']) && !empty($inParams['width']) && !empty($inParams['height'])) {
			$fromDimensionUnit = $this->dimensionUnit;
			if (isset($inParams['dimension_unit'])) {
				$fromDimensionUnit = $inParams['dimension_unit'];
			}

			$parcel['length'] = round(wc_get_dimension($inParams['length'], 'cm', $fromDimensionUnit), 2);
			$parcel['width'] = round(wc_get_dimension($inParams['width'], 'cm', $fromDimensionUnit), 2);
			$parcel['height'] = round(wc_get_dimension($inParams['height'], 'cm', $fromDimensionUnit), 2);
		}

		if (!empty($inParams['type']) && $inParams['type'] != 'parcel' && isset($this->packageTypes[$inParams['type']])) {
			$parcel['packaging'] = $inParams['type'];
		}

		return $parcel;
	}

	protected function prepareAddress($options)
	{
		$addr = array();

		if (!empty($options['name'])) {
			$addr['name'] = $options['name'];
		} else {
			$addr['name'] = 'Resident';
		}

		if (!empty($options['company']) && empty($options['name'])) {
			$addr['name'] = $options['company'];
		}

		if (!empty($addr['name'])) {
			$addr['attention'] = $addr['name'];
		}

		if (!empty($options['email'])) {
			$addr['email'] = $options['email'];
		}

		if (!empty($options['phone'])) {
			if (is_array($options['phone'])) {
				$options['phone'] = current($options['phone']);
			}
			
			$addr['mobile'] = $options['phone'];
		}

		if (!empty($options['country'])) {
			$addr['country_code'] = strtoupper($options['country']);
		}

		if (!empty($options['postcode'])) {
			$addr['zipcode'] = $options['postcode'];
		}

		if (!empty($options['city'])) {
			$addr['city'] = $options['city'];
		}

		if (!empty($options['address'])) {
			$addr['address1'] = $options['address'];
		}

		if (!empty($options['state'])) {
			$addr['address2'] = $options['state'];
		}

		if (!empty($options['address_2'])) {
			if (!empty($addr['address2'])) {
				$addr['address2'] .= ', ';
			} else {
				$addr['address2'] = '';
			}

			$addr['address2'] .= $options['address_2'];
		}
		
		return $addr;
	}

	protected function getRequestBody(&$headers, &$params)
	{
		$headers['Content-Type'] = 'application/json';

		return json_encode($params);
	}

	protected function getRequestParams(array $inParams)
	{
		$this->logger->debug(__FILE__, __LINE__, 'getRequestParams: ' . print_r($inParams, true));

		$params = array();

		if (!empty($inParams['function']) && $inParams['function'] == 'getRates') {
			$params = $this->getRatesParams($inParams);
		}

		return $params;
	}

	protected function getRatesResponse($response, array $params)
	{
		$this->logger->debug(__FILE__, __LINE__, 'getRatesResponse');

		if (!empty($response['error'])) {
			return array();
		}

		$rates = array();

		foreach ($response as $entry) {
			if (empty($entry['product_code']) || empty($entry['description']) || 
				empty($entry['carrier_code']) || !isset($entry['price'])) {
				continue;
			}

			$serviceId = $entry['product_code'];

			$serviceName = trim(sprintf('%s %s',
				$this->getCarrierName($entry['carrier_code']),
				$entry['description']
			));

			$rate = array();
			$rate['service'] = $serviceId;
			$rate['postage_description'] = apply_filters($this->id . '_service_name', $serviceName, $serviceId);
			$rate['cost'] = $entry['price'];

			$rates[$serviceId] = $rate;
		}

		$rates = $this->sortRates($rates);

		$newResponse = array();
		$newResponse['shipment']['rates'] = $rates;

		return $newResponse;
	}

	protected function getResponse($response, array $params)
	{
		$this->logger->debug(__FILE__, __LINE__, 'getResponse');

		$newResponse = array('response' => $response, 'params' => $params);

		if (!empty($response['error'])) {
			$newResponse['error']['message'] = $response['error'];
		}

		$function = null;
		if (!empty($params['function'])) {
			$function = $params['function'];
		}

		if ($function == 'getRates') {
			$newResponse = array_replace_recursive($newResponse, $this->getRatesResponse($response, $params));
		}

		return $newResponse;
	}

	protected function getRouteUrl($route)
	{
		$routeUrl = sprintf('https://app.shipmondo.com/api/public/v3/%s', $route);

		return $routeUrl;
	}

	protected function getApiKey()
	{
		return base64_encode($this->apiUser . ':' . $this->apiKey);
	}

	protected function addHeadersAndParams(&$headers, &$params)
	{
		$headers['Authorization'] = 'Basic ' . $this->getApiKey();
	}

	protected function initCarriers()
	{
		$this->_carriers = array(
			'b2c_europe' => __('B2C Europe', $this->id),
			'best' => __('Best Transport', $this->id),
			'bring' => __('Bring', $this->id),
			'brink' => __('Brink Transport', $this->id),
			'budbee' => __('Budbee', $this->id),
			'burd' => __('Burd', $this->id),
			'bws' => __('Blue Water Shipping', $this->id),
			'dachser' => __('DACHSER', $this->id),
			'dao' => __('dao', $this->id),
			'deutsche_post' => __('Deutsche Post', $this->id),
			'dfm' => __('Danske Fragtmænd', $this->id),
			'dhl_express' => __('DHL Express', $this->id),
			'dhl_freight_se' => __('DHL Freight', $this->id),
			'dhl_parcel' => __('DHL Parcel', $this->id),
			'doorhub' => __('Doorhub', $this->id),
			'dpd' => __('DPD', $this->id),
			'dsv' => __('DSV Road', $this->id),
			'early_bird' => __('Early Bird', $this->id),
			'fed_ex' => __('FedEx', $this->id),
			'freja' => __('FREJA', $this->id),
			'geodis' => __('GEODIS', $this->id),
			'gls' => __('GLS Denmark', $this->id),
			'gls_e' => __('GLS Express', $this->id),
			'gls_pl' => __('GLS Poland', $this->id),
			'helthjem' => __('helthjem', $this->id),
			'interfjord' => __('Interfjord', $this->id),
			'mover' => __('Mover', $this->id),
			'pdk' => __('PostNord', $this->id),
			'post_nord' => __('PostNord', $this->id),
			'posten_norge' => __('Posten Norge', $this->id),
			'posti' => __('Posti', $this->id),
			'srt' => __('SRT Transport', $this->id),
			'tnt' => __('TNT', $this->id),
			'ub_db_schenker_se' => __('DB Schenker Sverige', $this->id),
			'unspecified' => __('Unspecified carrier', $this->id),
			'ups' => __('UPS', $this->id),
			'xpressen' => __('Xpressen', $this->id),
		);
	}

	protected function initServices()
	{
		$this->_services = array(
			'B2C_PPL' => __('B2C Europe Parcel Plus', $this->id),
			'BEST_DK' => __('Best Transport Direkte Kørsel', $this->id),
			'BEST_H' => __('Best Transport Hemleverans Small', $this->id),
			'BEST_HLA' => __('Best Transport Hjemmelevering Aften', $this->id),
			'BEST_TKL' => __('Best Transport Tidsdefineret Kurer Levering', $this->id),
			'BRINK_COURIER' => __('Brink Transport Kurér', $this->id),
			'BRINK_DAY' => __('Brink Transport Dag', $this->id),
			'BRINK_NIGHT' => __('Brink Transport Nat', $this->id),
			'BRI_BP' => __('Bring Business Parcel', $this->id),
			'BRI_BPB' => __('Bring Business Parcel Bulk', $this->id),
			'BRI_BPL' => __('Bring Business Pallet', $this->id),
			'BRI_BPR' => __('Bring Business Parcel Return', $this->id),
			'BRI_END' => __('Bring Ekspress neste dag', $this->id),
			'BRI_HDP' => __('Bring Home Delivery Parcel', $this->id),
			'BRI_HDPR' => __('Bring Home Delivery Parcel Return', $this->id),
			'BRI_IL' => __('Bring Urban Home Delivery', $this->id),
			'BRI_MBP' => __('Bring Pakke i postkassen', $this->id),
			'BRI_MBPT' => __('Bring Pakke i postkassen med RFID', $this->id),
			'BRI_PLH' => __('Bring Pakke levert hjem', $this->id),
			'BRI_PP' => __('Bring Pickup Parcel', $this->id),
			'BRI_PPB' => __('Bring PickUp Parcel Bulk', $this->id),
			'BRI_PPR' => __('Bring PickUp Parcel Return', $this->id),
			'BRI_PPRB' => __('Bring Pickup Parcel Return Bulk', $this->id),
			'BRI_PTB' => __('Bring Pakke til bedrift', $this->id),
			'BRI_REND' => __('Bring Retur ekspress neste dag', $this->id),
			'BRI_RFH' => __('Bring Retur fra hentested', $this->id),
			'BRI_RL' => __('Bring Routing Label', $this->id),
			'BRI_RPFB' => __('Bring Retur pakke fra bedrift', $this->id),
			'BRI_SP' => __('Bring Pakke til hentested', $this->id),
			'BRI_SPG' => __('Bring Stykk- og partigods', $this->id),
			'BUD_P' => __('Budbee Home', $this->id),
			'BURD_DIST' => __('Burd Distribution', $this->id),
			'BWS_BAPS' => __('Blue Water Shipping Blue Air Pack - Standard', $this->id),
			'BWS_OFS' => __('Blue Water Shipping Ocean Freight - Standard', $this->id),
			'BWS_RS' => __('Blue Water Shipping Road - Standard', $this->id),
			'DAO_EH' => __('dao Export Home', $this->id),
			'DAO_EHS' => __('dao Export Home Small', $this->id),
			'DAO_ER' => __('dao Export Return', $this->id),
			'DAO_ES' => __('dao Export Shop', $this->id),
			'DAO_F' => __('dao Food', $this->id),
			'DAO_H' => __('dao Home', $this->id),
			'DAO_HSD' => __('dao Hjemmelevering - Samme dag', $this->id),
			'DAO_I' => __('dao International', $this->id),
			'DAO_P' => __('dao Pakkeshop', $this->id),
			'DAO_R' => __('dao Return', $this->id),
			'DAO_STH' => __('dao Shop to Home', $this->id),
			'DAO_STS' => __('dao Shop to Shop', $this->id),
			'DCH_TF' => __('DACHSER targofix', $this->id),
			'DCH_TF10' => __('DACHSER targofix 10', $this->id),
			'DCH_TF12' => __('DACHSER targofix 12', $this->id),
			'DCH_TFL' => __('DACHSER targoflex', $this->id),
			'DCH_TS' => __('DACHSER targospeed', $this->id),
			'DCH_TS12' => __('DACHSER targospeed 12', $this->id),
			'DCH_VF' => __('DACHSER vengofix', $this->id),
			'DCH_VFL' => __('DACHSER vengoflex', $this->id),
			'DCH_VS' => __('DACHSER vengospeed', $this->id),
			'DFM_BP' => __('Danske Fragtmænd Bilpakke', $this->id),
			'DFM_BPR' => __('Danske Fragtmænd Bilpakke return', $this->id),
			'DFM_E' => __('Danske Fragtmænd Enhedsforsendelse', $this->id),
			'DFM_EBP' => __('Danske Fragtmænd Erhvervsbilpakke', $this->id),
			'DFM_EBPR' => __('Danske Fragtmænd Erhvervsbilpakke return', $this->id),
			'DFM_ER' => __('Danske Fragtmænd Enhedsforsendelse return', $this->id),
			'DFM_NP' => __('Danske Fragtmænd Nat Privat', $this->id),
			'DFM_OE' => __('Danske Fragtmænd Omdeling til erhverv', $this->id),
			'DFM_OP' => __('Danske Fragtmænd Omdeling til privat', $this->id),
			'DFM_PS' => __('Danske Fragtmænd Pakkeshop', $this->id),
			'DFM_SG' => __('Danske Fragtmænd Stykgods', $this->id),
			'DFM_SGR' => __('Danske Fragtmænd Stykgods Return', $this->id),
			'DFM_VP' => __('Danske Fragtmænd Volumenpakke', $this->id),
			'DFM_VPR' => __('Danske Fragtmænd Volumenpakke return', $this->id),
			'DHLE_ED' => __('DHL Express Express Domestic', $this->id),
			'DHLE_ES' => __('DHL Express Economy Select', $this->id),
			'DHLE_EW' => __('DHL Express Express Worldwide', $this->id),
			'DHLE_EWD' => __('DHL Express Express Worldwide Document', $this->id),
			'DHLE_P1030' => __('DHL Express Express 10:30', $this->id),
			'DHLE_P12' => __('DHL Express Express 12:00', $this->id),
			'DHLE_P9' => __('DHL Express Express 9:00', $this->id),
			'DHLFSE_EUC' => __('DHL Freight Euroconnect', $this->id),
			'DHLFSE_EUCP' => __('DHL Freight Euroconnect Plus', $this->id),
			'DHLFSE_HD' => __('DHL Freight Home Delivery', $this->id),
			'DHLFSE_P' => __('DHL Freight Paket', $this->id),
			'DHLFSE_PC' => __('DHL Freight Parcel Connect', $this->id),
			'DHLFSE_PCSP' => __('DHL Freight Parcel Connect Service Point', $this->id),
			'DHLFSE_PE' => __('DHL Freight Paket Export', $this->id),
			'DHLFSE_PL' => __('DHL Freight Pall', $this->id),
			'DHLFSE_PRC' => __('DHL Freight Parcel Return Connect', $this->id),
			'DHLFSE_PT' => __('DHL Freight Parti', $this->id),
			'DHLFSE_S' => __('DHL Freight Stycke', $this->id),
			'DHLFSE_SPB2C' => __('DHL Freight Service Point B2C', $this->id),
			'DHLFSE_SPR' => __('DHL Freight Service Point Retur', $this->id),
			'DHLP_ER' => __('DHL Parcel Easy Return', $this->id),
			'DHLP_EUP' => __('DHL Parcel Europaket', $this->id),
			'DHLP_P' => __('DHL Parcel Paket', $this->id),
			'DHLP_PC' => __('DHL Parcel Parcel Connect', $this->id),
			'DHLP_PCPF' => __('DHL Parcel Parcel Connect Postfiliale', $this->id),
			'DHLP_PI' => __('DHL Parcel DHL Paket International', $this->id),
			'DHLP_PPF' => __('DHL Parcel Paket Postfiliale', $this->id),
			'DHLP_PPS' => __('DHL Parcel Paket Packstation', $this->id),
			'DHLP_PRC' => __('DHL Parcel Parcel Return Connect', $this->id),
			'DHLP_WP' => __('DHL Parcel Warenpost', $this->id),
			'DOH_BI' => __('Doorhub Bulk import', $this->id),
			'DOH_HD' => __('Doorhub Hub-delivery', $this->id),
			'DOH_OD' => __('Doorhub On-demand', $this->id),
			'DOH_ODL' => __('Doorhub On-demand light', $this->id),
			'DOH_ODR' => __('Doorhub Ondemand retur', $this->id),
			'DOH_SD' => __('Doorhub Same-day', $this->id),
			'DOH_SDF' => __('Doorhub Same-day flexible', $this->id),
			'DOH_SDM' => __('Doorhub Same-day multiple pick up & drop off', $this->id),
			'DPD_CL' => __('DPD Classic', $this->id),
			'DPD_E' => __('DPD Express', $this->id),
			'DPD_E10' => __('DPD DPD 10:00', $this->id),
			'DPD_E12' => __('DPD DPD 12:00', $this->id),
			'DPD_E18' => __('DPD DPD 18:00', $this->id),
			'DPD_E830' => __('DPD DPD 8:30', $this->id),
			'DP_PP' => __('Deutsche Post Packet Priority', $this->id),
			'DP_PPL' => __('Deutsche Post Packet Plus', $this->id),
			'DP_PR' => __('Deutsche Post Packet Return', $this->id),
			'DP_PT' => __('Deutsche Post Packet Tracked', $this->id),
			'DP_WPIS' => __('Deutsche Post Warenpost International Signature', $this->id),
			'DP_WPIT' => __('Deutsche Post Warenpost International Tracked', $this->id),
			'DP_WPIU' => __('Deutsche Post Warenpost International Untracked', $this->id),
			'DSV_MXDE' => __('DSV Road Courier - Document', $this->id),
			'DSV_MXEE' => __('DSV Road Courier - Envelope', $this->id),
			'DSV_PB' => __('DSV Road Parcel Business', $this->id),
			'DSV_PH' => __('DSV Road Parcel Home', $this->id),
			'DSV_RDPE' => __('DSV Road Daily Pallet', $this->id),
			'DSV_RFLE' => __('DSV Road Full Loads', $this->id),
			'DSV_RGE' => __('DSV Road Groupage', $this->id),
			'DSV_RPE' => __('DSV Road Parcel', $this->id),
			'DSV_RPLE' => __('DSV Road Part Load', $this->id),
			'DSV_RXPE' => __('DSV Road Courier - Parcel', $this->id),
			'EB_E' => __('Early Bird Express', $this->id),
			'EB_P' => __('Early Bird Postlådepaket', $this->id),
			'FDX_IE' => __('FedEx International Economy', $this->id),
			'FDX_IP' => __('FedEx International Priority', $this->id),
			'FDX_PO' => __('FedEx Priority Overnight', $this->id),
			'FRJ_STD' => __('FREJA Standard', $this->id),
			'GEO_ECO' => __('GEODIS Economy', $this->id),
			'GEO_EXP' => __('GEODIS Express', $this->id),
			'GLSDK_BP' => __('GLS Denmark Business Parcel', $this->id),
			'GLSDK_CC' => __('GLS Denmark Click & Collect', $this->id),
			'GLSDK_DS' => __('GLS Denmark Deposit Service', $this->id),
			'GLSDK_E10' => __('GLS Denmark Express 10:00', $this->id),
			'GLSDK_E12' => __('GLS Denmark Express 12:00', $this->id),
			'GLSDK_EBP' => __('GLS Denmark Euro Business Parcel', $this->id),
			'GLSDK_EBPC' => __('GLS Denmark Euro Business Parcel (Customs)', $this->id),
			'GLSDK_GBP' => __('GLS Denmark Global Business Parcel', $this->id),
			'GLSDK_HD' => __('GLS Denmark Private Delivery Parcel', $this->id),
			'GLSDK_PR' => __('GLS Denmark Pick&ReturnService', $this->id),
			'GLSDK_PS' => __('GLS Denmark Pick&ShipService', $this->id),
			'GLSDK_SD' => __('GLS Denmark Shop Delivery', $this->id),
			'GLSDK_SR' => __('GLS Denmark Shop Return Service', $this->id),
			'GLSE_DK' => __('GLS Express Der Kurier', $this->id),
			'GLSPL_BP' => __('GLS Poland Business Parcel', $this->id),
			'GLSPL_EBP' => __('GLS Poland Euro Business Parcel', $this->id),
			'HHJ_A' => __('helthjem abonnement', $this->id),
			'HHJ_E' => __('helthjem ekspress', $this->id),
			'HHJ_EHB' => __('helthjem ekspress m/Hent i butikk', $this->id),
			'HHJ_ES' => __('helthjem ekspress/standard', $this->id),
			'HHJ_ETEP' => __('helthjem en-til-en m/post', $this->id),
			'HHJ_FP' => __('helthjem frekvent m/post', $this->id),
			'HHJ_HB' => __('helthjem Hent i butikk', $this->id),
			'HHJ_HE' => __('helthjem 100% ekspress', $this->id),
			'HHJ_HS' => __('helthjem 100% standard', $this->id),
			'HHJ_IN' => __('helthjem B2B inNight', $this->id),
			'HHJ_P' => __('helthjem Post', $this->id),
			'HHJ_PH' => __('helthjem Personlig hjemlevering', $this->id),
			'HHJ_R' => __('helthjem retur', $this->id),
			'HHJ_RB' => __('helthjem Retur via butik', $this->id),
			'HHJ_S' => __('helthjem standard', $this->id),
			'HHJ_SHB' => __('helthjem standard m/ Hent i butikk', $this->id),
			'HHJ_SS' => __('helthjem samlesending', $this->id),
			'HHJ_VB' => __('helthjem varebrev', $this->id),
			'HHJ_VBHB' => __('helthjem varebrev m/Hent i butikk', $this->id),
			'IF_AIR' => __('Interfjord Air', $this->id),
			'IF_DP' => __('Interfjord Domestic Parcel', $this->id),
			'IF_DR' => __('Interfjord Domestic Road', $this->id),
			'IF_ECO' => __('Interfjord Economy', $this->id),
			'IF_EXPRESS' => __('Interfjord Express', $this->id),
			'IF_RAIL' => __('Interfjord Rail', $this->id),
			'IF_ROAD' => __('Interfjord Road', $this->id),
			'IF_SE' => __('Interfjord Special Express', $this->id),
			'IF_SEA' => __('Interfjord Sea', $this->id),
			'MOVER_CAR' => __('Mover Bil', $this->id),
			'MOVER_LIFT' => __('Mover Liftvogn', $this->id),
			'MOVER_VAN' => __('Mover Varevogn', $this->id),
			'PDK_BP' => __('PostNord Parcel', $this->id),
			'PDK_BPDI' => __('PostNord PostNord Parcel Direct Injection', $this->id),
			'PDK_BPE' => __('PostNord Parcel Economy', $this->id),
			'PDK_BPR' => __('PostNord Business Priority', $this->id),
			'PDK_CC' => __('PostNord Click & Collect', $this->id),
			'PDK_CR' => __('PostNord Pickup Request', $this->id),
			'PDK_DPDC' => __('PostNord DPD Classic', $this->id),
			'PDK_EMS' => __('PostNord EMS', $this->id),
			'PDK_G' => __('PostNord Groupage', $this->id),
			'PDK_IN' => __('PostNord InNight', $this->id),
			'PDK_INND' => __('PostNord InNight Next Day', $this->id),
			'PDK_MC' => __('PostNord MyPack Collect', $this->id),
			'PDK_MCIS' => __('PostNord MyPack Collect in Store', $this->id),
			'PDK_MH' => __('PostNord MyPack Home', $this->id),
			'PDK_MHE' => __('PostNord MyPack Home Economy', $this->id),
			'PDK_PL' => __('PostNord Pallet', $this->id),
			'PDK_PPL' => __('PostNord PP label', $this->id),
			'PDK_PPR' => __('PostNord Private Priority', $this->id),
			'PDK_PUR' => __('PostNord Pickup Request', $this->id),
			'PDK_RDO' => __('PostNord Return Drop Off', $this->id),
			'PDK_RDO_DPD' => __('PostNord Return Drop Off (DPD)', $this->id),
			'PDK_RPU' => __('PostNord Return Pickup', $this->id),
			'PDK_SL' => __('PostNord Servicelogistik', $this->id),
			'PDK_TM' => __('PostNord Tracked Maxibrev', $this->id),
			'PDK_TS' => __('PostNord Tracked Storbrev', $this->id),
			'PDK_UTM' => __('PostNord Untracked Maxibrev', $this->id),
			'PDK_UTS' => __('PostNord Untracked Storbrev', $this->id),
			'PDK_VB' => __('PostNord Varebrev', $this->id),
			'PNNO_MHS' => __('PostNord MyPack Home Small', $this->id),
			'PNRG_PPL' => __('Posten Norge Varebrev', $this->id),
			'PNSE_MHS' => __('PostNord MyPack Home Small', $this->id),
			'PNSE_VB1K' => __('PostNord Varubrev 1:a-klass', $this->id),
			'PNSE_VBE' => __('PostNord Varubrev Ekonomi', $this->id),
			'PNSE_VBNO' => __('PostNord Varubrev Norge', $this->id),
			'PNSE_VBR' => __('PostNord Varubrev Retur', $this->id),
			'PN_G' => __('PostNord Groupage', $this->id),
			'PN_MC' => __('PostNord MyPack Collect', $this->id),
			'PN_MH' => __('PostNord MyPack Home', $this->id),
			'PN_P' => __('PostNord Parcel', $this->id),
			'PN_RDO' => __('PostNord Return Drop Off', $this->id),
			'PTI_EP' => __('Posti Express-paketti', $this->id),
			'PTI_EPM' => __('Posti Express-paketti Aamuksi', $this->id),
			'PTI_EPSD' => __('Posti Express-paketti Samana Päivänä', $this->id),
			'PTI_HP' => __('Posti Kotipaketti', $this->id),
			'PTI_PP' => __('Posti Postipaketti', $this->id),
			'PTI_R' => __('Posti Palautus', $this->id),
			'PTI_SP' => __('Posti Pikkupaketti', $this->id),
			'SRT_EL' => __('SRT Transport Business delivery', $this->id),
			'SRT_HP' => __('SRT Transport Half pallet', $this->id),
			'SRT_KP' => __('SRT Transport Quarter pallet', $this->id),
			'SRT_P' => __('SRT Transport Pallet', $this->id),
			'SRT_PL' => __('SRT Transport Private delivery', $this->id),
			'TNT_EC' => __('TNT Economy Express', $this->id),
			'TNT_EX' => __('TNT Express', $this->id),
			'TNT_EX09' => __('TNT 9:00 Express', $this->id),
			'TNT_EX10' => __('TNT 10:00 Express', $this->id),
			'TNT_EX12' => __('TNT 12:00 Express', $this->id),
			'TNT_EX_D' => __('TNT Express Document', $this->id),
			'UDBSE_PI' => __('DB Schenker Sverige Parcel Inrikes', $this->id),
			'UDBSE_PO' => __('DB Schenker Sverige Parcel Ombud', $this->id),
			'UNI_AL' => __('Unspecified carrier Address label', $this->id),
			'UNI_ALP' => __('Unspecified carrier Address label w/pro forma', $this->id),
			'UNI_PL' => __('Unspecified carrier Pickup label', $this->id),
			'UPS_EPD' => __('UPS Expedited', $this->id),
			'UPS_EPDAP' => __('UPS Expedited Access Point', $this->id),
			'UPS_EXD' => __('UPS Express Document', $this->id),
			'UPS_EXPRESS' => __('UPS Express', $this->id),
			'UPS_EXPRESSAP' => __('UPS Express Access Point', $this->id),
			'UPS_EXSD' => __('UPS UPS Express Saver Document', $this->id),
			'UPS_SAVER' => __('UPS Express Saver', $this->id),
			'UPS_SAVERAP' => __('UPS Express Saver Access Point', $this->id),
			'UPS_STANDARD' => __('UPS Standard', $this->id),
			'UPS_STANDARDAP' => __('UPS Standard Access Point', $this->id),
			'XPR_FD' => __('Xpressen Food Distribution', $this->id),
			'XPR_ND' => __('Xpressen National Distribution', $this->id),
		);
	}

	public function initPackageTypes()
	{
		$this->packageTypes = array(
			'' => __('Parcel', $this->id),
			'BRINK_300' => __('Brink Package', $this->id),
			'BRINK_373' => __('Brink ½ pll. 0-200 Kg', $this->id),
			'BRINK_374' => __('Brink 1 pll. 0-400 Kg', $this->id),
			'BRINK_372' => __('Brink ¼ pll. 0-100 Kg', $this->id),
			'hd_eur' => __('Bring EUR Pallet', $this->id),
			'hd_half' => __('Bring Half Pallet', $this->id),
			'hd_quarter' => __('Bring Quarter Pallet', $this->id),
			'hd_loose' => __('Bring Special Pallet', $this->id),
			'PARCEL' => __('Bring Pakke', $this->id),
			'PALLET' => __('Bring Palle', $this->id),
			'QPL' => __('Blue Quarter pallet', $this->id),
			'Parcel' => __('Blue Parcel', $this->id),
			'PLL' => __('Interfjord PLL', $this->id),
			'IPL' => __('Blue Special pallet', $this->id),
			'HPL' => __('Interfjord HPL', $this->id),
			'KH' => __('DACHSER Customer sterile pallet', $this->id),
			'KV' => __('DACHSER Composite packaging', $this->id),
			'KT' => __('Deutsche KT', $this->id),
			'RL' => __('DACHSER Roll', $this->id),
			'EU' => __('DACHSER Euro pallet', $this->id),
			'E' => __('DACHSER Pail', $this->id),
			'BX' => __('DACHSER Box', $this->id),
			'E2' => __('DACHSER Euro-Meat-Tray 2', $this->id),
			'F' => __('DACHSER Barrel', $this->id),
			'CT' => __('PostNord Carton', $this->id),
			'B' => __('DACHSER Bundle', $this->id),
			'KK' => __('DACHSER Plastic Jerri can', $this->id),
			'C1' => __('DACHSER Chep-Standard-Pallet', $this->id),
			'DG' => __('DACHSER Pressure receptacle', $this->id),
			'VE' => __('DACHSER Quarter EU-pallet', $this->id),
			'B3' => __('DACHSER E-Performance Box 3', $this->id),
			'SP' => __('DACHSER Shrink-wrapped pallet', $this->id),
			'LP' => __('DACHSER Large Package', $this->id),
			'C4' => __('DACHSER Chep-Quarter-Pallet', $this->id),
			'PA' => __('DACHSER Parcel/Packet', $this->id),
			'H1' => __('DACHSER Sterile pallet 0', $this->id),
			'E4' => __('DACHSER Euro-Meat-Tray 4', $this->id),
			'B1' => __('DACHSER E-Performance Box 1', $this->id),
			'HE' => __('DACHSER Half EU pallet', $this->id),
			'B2' => __('DACHSER E-Performance Box 2', $this->id),
			'CR' => __('DACHSER Crate', $this->id),
			'IB' => __('DACHSER IBC-Container', $this->id),
			'EW' => __('DACHSER One way pallet', $this->id),
			'DD' => __('DACHSER Düsseldorfer pallet', $this->id),
			'E3' => __('DACHSER Euro-Meat-Tray 3', $this->id),
			'TY' => __('DACHSER Tray', $this->id),
			'HB' => __('DACHSER Hobbock', $this->id),
			'S' => __('Deutsche S', $this->id),
			'E1' => __('DACHSER Euro-Meat-Tray 1', $this->id),
			'KA' => __('DACHSER Can', $this->id),
			'ZK' => __('DACHSER Customs envelope', $this->id),
			'DR' => __('DACHSER Drum', $this->id),
			'KE' => __('DACHSER Customer Euro-Meat Tray 2', $this->id),
			'FL' => __('DACHSER Bottle', $this->id),
			'ST' => __('DACHSER Piece', $this->id),
			'IP' => __('DACHSER Industrial pallet', $this->id),
			'UP' => __('DACHSER Unpacked', $this->id),
			'FB' => __('DACHSER Light gauge metal packaging', $this->id),
			'SD' => __('DACHSER Protection lid', $this->id),
			'GB' => __('DACHSER Lattice pallet', $this->id),
			'AR' => __('DACHSER Frame', $this->id),
			'KI' => __('DACHSER Case', $this->id),
			'DI' => __('DACHSER Display', $this->id),
			'C2' => __('DACHSER Chep-Half-Pallet', $this->id),
			'TR' => __('DACHSER Drum', $this->id),
			'P4' => __('DACHSER 1.4 pallet – Paki', $this->id),
			'KN' => __('DACHSER Jerri can', $this->id),
			'PL2' => __('Danske PL2', $this->id),
			'PL1' => __('Danske PL1', $this->id),
			'PL' => __('DHL Pallet', $this->id),
			'CLL' => __('Interfjord CLL', $this->id),
			'PK' => __('DSV Package', $this->id),
			'701' => __('DHL DHL Helpall', $this->id),
			'702' => __('DHL DHL Halvpall', $this->id),
			'XS' => __('Deutsche XS', $this->id),
			'L' => __('Deutsche L', $this->id),
			'M' => __('Deutsche M', $this->id),
			'PXL' => __('Interfjord XL PLL', $this->id),
			'KPL' => __('Interfjord KPL', $this->id),
			'FEDEX_PAK' => __('FedEx FedEx Pak', $this->id),
			'FEDEX_BOX' => __('FedEx FedEx Box', $this->id),
			'YOUR_PACKAGING' => __('FedEx Your packaging', $this->id),
			'FEDEX_TUBE' => __('FedEx FedEx Tube', $this->id),
			'FEDEX_ENVELOPE' => __('FedEx FedEx Envelope', $this->id),
			'HPLL' => __('FREJA Half pallet', $this->id),
			'EUR' => __('FREJA EUR-pallet', $this->id),
			'KPLL' => __('FREJA Quarter pallet', $this->id),
			'IPLL' => __('FREJA Industrial pallet', $this->id),
			'KRT' => __('FREJA Carton', $this->id),
			'DOK' => __('Interfjord DOC', $this->id),
			'STK' => __('Interfjord Styk', $this->id),
			'PRT' => __('Interfjord Parti', $this->id),
			'SCO' => __('Interfjord Scooter', $this->id),
			'CYK' => __('Interfjord Cykel', $this->id),
			'LÆS' => __('Interfjord LÆS', $this->id),
			'ELC' => __('Interfjord Elcykel', $this->id),
			'OA' => __('PostNord Pallet', $this->id),
			'PC' => __('Posti Parcel', $this->id),
			'OF' => __('PostNord Special pallet', $this->id),
			'AF' => __('PostNord Half pallet', $this->id),
			'PE' => __('PostNord EUR pallet', $this->id),
			'EN' => __('PostNord Envelope', $this->id),
			'CW' => __('PostNord Cage roll', $this->id),
			'ZPE' => __('Posti EUR Pallet, 1200x800', $this->id),
			'ZPF' => __('Posti FIN Pallet, 1200x1000', $this->id),
			'PU' => __('Posti Rolltainer', $this->id),
			'ZPT' => __('Posti Half Pallet, 800x600', $this->id),
			'ZPX' => __('Posti Flex Pallet, 2000x800', $this->id),
			'CG' => __('Posti Cage', $this->id),
			'02' => __('UPS Own packaging', $this->id),
			'04' => __('UPS Express Pak', $this->id),
			'21' => __('UPS Express Box', $this->id),
			'2b' => __('UPS Express Box Medium', $this->id),
			'2c' => __('UPS Express Box Large', $this->id),
			'03' => __('UPS Express Tube', $this->id),
			'2a' => __('UPS Express Box Small', $this->id),
			'01' => __('UPS Express Envelope', $this->id),
		);
	}
}

endif;
