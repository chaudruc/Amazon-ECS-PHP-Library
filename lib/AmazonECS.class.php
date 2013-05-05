<?php
/**
 * Amazon ECS Class
 * http://www.amazon.com
 * =====================
 *
 * This class fetchs productinformation via the Product Advertising API by Amazon (formerly ECS).
 * It supports three basic operations: ItemSearch, ItemLookup and BrowseNodeLookup.
 * These operations could be expanded with extra prarmeters to specialize the query.
 *
 * Requirement is the PHP extension SOAP.
 *
 * @package      AmazonECS
 * @license      http://www.gnu.org/licenses/gpl.txt GPL
 * @version      1.3.4-DEV
 * @author       Exeu <exeu65@googlemail.com>
 * @contributor  Julien Chaumond <chaumond@gmail.com>
 * @link         http://github.com/Exeu/Amazon-ECS-PHP-Library/wiki Wiki
 * @link         http://github.com/Exeu/Amazon-ECS-PHP-Library Source
 */
class AmazonECS
{
  const RETURN_TYPE_ARRAY  = 1;
  const RETURN_TYPE_OBJECT = 2;

  /**
   * Baseconfigurationstorage
   *
   * @var array
   */
  private $requestConfig = array(
    'requestDelay' => false
  );

  /**
   * Responseconfigurationstorage
   *
   * @var array
   */
  private $responseConfig = array(
    'returnType'          => self::RETURN_TYPE_OBJECT,
    'responseGroup'       => 'Small',
    'optionalParameters'  => array()
  );

  /**
   * All possible locations
   *
   * @var array
   */
  private $possibleLocations = array('de', 'com', 'co.uk', 'ca', 'fr', 'co.jp', 'it', 'cn', 'es');

  /**
   * The WSDL File
   *
   * @var string
   */
  protected $webserviceWsdl = 'http://webservices.amazon.com/AWSECommerceService/AWSECommerceService.wsdl';

  /**
   * The SOAP Endpoint
   *
   * @var string
   */
  protected $webserviceEndpoint = 'https://webservices.amazon.%%COUNTRY%%/onca/soap?Service=AWSECommerceService';

  /**
   * @param string $accessKey
   * @param string $secretKey
   * @param string $country
   * @param string $associateTag
   */
  public function __construct($accessKey, $secretKey, $country, $associateTag)
  {
    if (empty($accessKey) || empty($secretKey))
    {
      throw new Exception('No Access Key or Secret Key has been set');
    }

    $this->requestConfig['accessKey']     = $accessKey;
    $this->requestConfig['secretKey']     = $secretKey;
    $this->associateTag($associateTag);
    $this->country($country);
  }

  /**
   * execute search
   *
   * @param string $pattern
   *
   * @return array|object return type depends on setting
   *
   * @see returnType()
   */
  public function search($pattern, $nodeId = null)
  {
    if (false === isset($this->requestConfig['category']))
    {
      throw new Exception('No Category given: Please set it up before');
    }

    $browseNode = array();
    if (null !== $nodeId && true === $this->validateNodeId($nodeId))
    {
      $browseNode = array('BrowseNode' => $nodeId);
    }

    $params = $this->buildRequestParams('ItemSearch', array_merge(
      array(
        'Keywords' => $pattern,
        'SearchIndex' => $this->requestConfig['category']
      ),
      $browseNode
    ));

    return $this->returnData(
      $this->performSoapRequest("ItemSearch", $params)
    );
  }

  /**
   * execute ItemLookup request
   *
   * @param string $asin
   *
   * @return array|object return type depends on setting
   *
   * @see returnType()
   */
  public function lookup($asin)
  {
    $params = $this->buildRequestParams('ItemLookup', array(
      'ItemId' => $asin,
    ));

    return $this->returnData(
      $this->performSoapRequest("ItemLookup", $params)
    );
  }

  /**
   * Implementation of BrowseNodeLookup
   * This allows to fetch information about nodes (children anchestors, etc.)
   *
   * @param integer $nodeId
   */
  public function browseNodeLookup($nodeId)
  {
    $this->validateNodeId($nodeId);

    $params = $this->buildRequestParams('BrowseNodeLookup', array(
      'BrowseNodeId' => $nodeId
    ));

    return $this->returnData(
      $this->performSoapRequest("BrowseNodeLookup", $params)
    );
  }

  /**
   * Implementation of SimilarityLookup
   * This allows to fetch information about product related to the parameter product
   *
   * @param string $asin
   */
  public function similarityLookup($asin)
  {
    $params = $this->buildRequestParams('SimilarityLookup', array(
      'ItemId' => $asin
    ));

    return $this->returnData(
      $this->performSoapRequest("SimilarityLookup", $params)
    );
  }

  /**
   * Builds the request parameters
   *
   * @param string $function
   * @param array  $params
   *
   * @return array
   */
  protected function buildRequestParams($function, array $params)
  {
    $associateTag = array();

    if(false === empty($this->requestConfig['associateTag']))
    {
      $associateTag = array('AssociateTag' => $this->requestConfig['associateTag']);
    }

    return array_merge(
      $associateTag,
      array(
        'AWSAccessKeyId' => $this->requestConfig['accessKey'],
        'Request' => array_merge(
          array('Operation' => $function),
          $params,
          $this->responseConfig['optionalParameters'],
          array('ResponseGroup' => $this->prepareResponseGroup())
    )));
  }

  /**
   * Prepares the responsegroups and returns them as array
   *
   * @return array|prepared responsegroups
   */
  protected function prepareResponseGroup()
  {
    if (false === strstr($this->responseConfig['responseGroup'], ','))
      return $this->responseConfig['responseGroup'];

    return explode(',', $this->responseConfig['responseGroup']);
  }

  /**
   * @param string $function Name of the function which should be called
   * @param array $params Requestparameters 'ParameterName' => 'ParameterValue'
   *
   * @return array The response as an array with stdClass objects
   */
  protected function performSoapRequest($function, $params)
  {
    if (true ===  $this->requestConfig['requestDelay']) {
      sleep(1);
    }

    $soapClient = new SoapClient(
      $this->webserviceWsdl,
      array('exceptions' => 1)
    );

    $soapClient->__setLocation(str_replace(
      '%%COUNTRY%%',
      $this->responseConfig['country'],
      $this->webserviceEndpoint
    ));

    $soapClient->__setSoapHeaders($this->buildSoapHeader($function));

    return $soapClient->__soapCall($function, array($params));
  }

  /**
   * Provides some necessary soap headers
   *
   * @param string $function
   *
   * @return array Each element is a concrete SoapHeader object
   */
  protected function buildSoapHeader($function)
  {
    $timeStamp = $this->getTimestamp();
    $signature = $this->buildSignature($function . $timeStamp);

    return array(
      new SoapHeader(
        'http://security.amazonaws.com/doc/2007-01-01/',
        'AWSAccessKeyId',
        $this->requestConfig['accessKey']
      ),
      new SoapHeader(
        'http://security.amazonaws.com/doc/2007-01-01/',
        'Timestamp',
        $timeStamp
      ),
      new SoapHeader(
        'http://security.amazonaws.com/doc/2007-01-01/',
        'Signature',
        $signature
      )
    );
  }

  /**
   * provides current gm date
   *
   * primary needed for the signature
   *
   * @return string
   */
  final protected function getTimestamp()
  {
    return gmdate("Y-m-d\TH:i:s\Z");
  }

  /**
   * provides the signature
   *
   * @return string
   */
  final protected function buildSignature($request)
  {
    return base64_encode(hash_hmac("sha256", $request, $this->requestConfig['secretKey'], true));
  }

  /**
   * Basic validation of the nodeId
   *
   * @param integer $nodeId
   *
   * @return boolean
   */
  final protected function validateNodeId($nodeId)
  {
    if (false === is_numeric($nodeId) || $nodeId <= 0)
    {
      throw new InvalidArgumentException(sprintf('Node has to be a positive Integer.'));
    }

    return true;
  }

  /**
   * Returns the response either as Array or Array/Object
   *
   * @param object $object
   *
   * @return mixed
   */
  protected function returnData($object)
  {
    switch ($this->responseConfig['returnType'])
    {
      case self::RETURN_TYPE_OBJECT:
        return $object;
      break;

      case self::RETURN_TYPE_ARRAY:
        return $this->objectToArray($object);
      break;

      default:
        throw new InvalidArgumentException(sprintf(
          "Unknwon return type %s", $this->responseConfig['returnType']
        ));
      break;
    }
  }

  /**
   * Transforms the responseobject to an array
   *
   * @param object $object
   *
   * @return array An arrayrepresentation of the given object
   */
  protected function objectToArray($object)
  {
    $out = array();
    foreach ($object as $key => $value)
    {
      switch (true)
      {
        case is_object($value):
          $out[$key] = $this->objectToArray($value);
        break;

        case is_array($value):
          $out[$key] = $this->objectToArray($value);
        break;

        default:
          $out[$key] = $value;
        break;
      }
    }

    return $out;
  }

  /**
   * set or get optional parameters
   *
   * if the argument params is null it will reutrn the current parameters,
   * otherwise it will set the params and return itself.
   *
   * @param array $params the optional parameters
   *
   * @return array|AmazonECS depends on params argument
   */
  public function optionalParameters($params = null)
  {
    if (null === $params)
    {
      return $this->responseConfig['optionalParameters'];
    }

    if (false === is_array($params))
    {
      throw new InvalidArgumentException(sprintf(
        "%s is no valid parameter: Use an array with Key => Value Pairs", $params
      ));
    }

    $this->responseConfig['optionalParameters'] = $params;

    return $this;
  }

  /**
   * Set or get the country
   *
   * if the country argument is null it will return the current
   * country, otherwise it will set the country and return itself.
   *
   * @param string|null $country
   *
   * @return string|AmazonECS depends on country argument
   */
  public function country($country = null)
  {
    if (null === $country)
    {
      return $this->responseConfig['country'];
    }

    if (false === in_array(strtolower($country), $this->possibleLocations))
    {
      throw new InvalidArgumentException(sprintf(
        "Invalid Country-Code: %s! Possible Country-Codes: %s",
        $country,
        implode(', ', $this->possibleLocations)
      ));
    }

    $this->responseConfig['country'] = strtolower($country);

    return $this;
  }

  /**
   * Setting/Getting the amazon category
   *
   * @param string $category
   *
   * @return string|AmazonECS depends on category argument
   */
  public function category($category = null)
  {
    if (null === $category)
    {
      return isset($this->requestConfig['category']) ? $this->requestConfig['category'] : null;
    }

    $this->requestConfig['category'] = $category;

    return $this;
  }

  /**
   * Setting/Getting the responsegroup
   *
   * @param string $responseGroup Comma separated groups
   *
   * @return string|AmazonECS depends on responseGroup argument
   */
  public function responseGroup($responseGroup = null)
  {
    if (null === $responseGroup)
    {
      return $this->responseConfig['responseGroup'];
    }

    $this->responseConfig['responseGroup'] = $responseGroup;

    return $this;
  }

  /**
   * Setting/Getting the returntype
   * It can be an object or an array
   *
   * @param integer $type Use the constants RETURN_TYPE_ARRAY or RETURN_TYPE_OBJECT
   *
   * @return integer|AmazonECS depends on type argument
   */
  public function returnType($type = null)
  {
    if (null === $type)
    {
      return $this->responseConfig['returnType'];
    }

    $this->responseConfig['returnType'] = $type;

    return $this;
  }

  /**
   * Setter/Getter of the AssociateTag.
   * This could be used for late bindings of this attribute
   *
   * @param string $associateTag
   *
   * @return string|AmazonECS depends on associateTag argument
   */
  public function associateTag($associateTag = null)
  {
    if (null === $associateTag)
    {
      return $this->requestConfig['associateTag'];
    }

    $this->requestConfig['associateTag'] = $associateTag;

    return $this;
  }

  /**
   * @deprecated use returnType() instead
   */
  public function setReturnType($type)
  {
    return $this->returnType($type);
  }

  /**
   * Setting the resultpage to a specified value.
   * Allows to browse resultsets which have more than one page.
   *
   * @param integer $page
   *
   * @return AmazonECS
   */
  public function page($page)
  {
    if (false === is_numeric($page) || $page <= 0)
    {
      throw new InvalidArgumentException(sprintf(
        '%s is an invalid page value. It has to be numeric and positive',
        $page
      ));
    }

    $this->responseConfig['optionalParameters'] = array_merge(
      $this->responseConfig['optionalParameters'],
      array("ItemPage" => $page)
    );

    return $this;
  }

  /**
   * Enables or disables the request delay.
   * If it is enabled (true) every request is delayed one second to get rid of the api request limit.
   *
   * Reasons for this you can read on this site:
   * https://affiliate-program.amazon.com/gp/advertising/api/detail/faq.html
   *
   * By default the requestdelay is disabled
   *
   * @param boolean $enable true = enabled, false = disabled
   *
   * @return boolean|AmazonECS depends on enable argument
   */
  public function requestDelay($enable = null)
  {
    if (false === is_null($enable) && true === is_bool($enable))
    {
      $this->requestConfig['requestDelay'] = $enable;

      return $this;
    }

    return $this->requestConfig['requestDelay'];
  }
  
  
//////////////////////////////
// CART METHODS //////////////
/////////////////////////////
  /*%******************************************************************************************%*/
	// CART METHODS

	/**
	 * Method: cart_add()
	 * 	Enables you to add items to an existing remote shopping cart. <cart_add()> can only be used to place a new item in a shopping cart. It cannot be used to increase the quantity of an item already in the cart. If you would like to increase the quantity of an item that is already in the cart, you must use the <cart_modify()> operation.
	 * 
	 * 	You add an item to a cart by specifying the item's OfferListingId, or ASIN and ListItemId. Once in a cart, an item can only be identified by its CartItemId. That is, an item in a cart cannot be accessed by its ASIN or OfferListingId. CartItemId is returned by <cart_create()>, <cart_get()>, and <cart_add()>.
	 * 
	 * 	To add items to a cart, you must specify the cart using the CartId and HMAC values, which are returned by the <cart_create()> operation.
	 * 
	 * 	If you added your Associates ID to the config.inc.php file, or you passed it into the AmazonAAWS() constructor, it will be passed along in this request automatically.
	 * 
	 * Access:
	 * 	public
	 * 
	 * Parameters:
	 * 	cart_id - _string_ (Required) Alphanumeric token returned by <cart_create()> that identifies a cart.
	 * 	hmac - _string_ (Required) Encrypted alphanumeric token returned by <cart_create()> that authorizes access to a cart.
	 * 	offer_listing_id - _string|array_ (Required) Either a string containing the Offer ID to add, or an associative array where the Offer ID is the key and the quantity is the value. An offer listing ID is an alphanumeric token that uniquely identifies an item. Use the OfferListingId instead of an item's ASIN to add the item to the cart.
	 * 	opt - _array_ (Optional) Associative array of parameters which can have the following keys:
	 * 	locale - _string_ (Optional) Which Amazon-supported locale do we use? Defaults to United States.
	 * 
	 * Keys for the $opt parameter:
	 * 	ContentType - _string_ (Optional) Specifies the format of the content in the response. Generally, ContentType should only be changed for REST requests when the Style parameter is set to an XSLT stylesheet. For example, to transform your Amazon Associates Web Service response into HTML, set ContentType to text/html. Allows 'text/xml' and 'text/html'. Defaults to 'text/xml'.
	 * 	MergeCart - _boolean_ (Optional) A boolean value that when True specifies that the items in a customer's remote shopping cart are added to the customer's Amazon retail shopping cart. This occurs when the customer elects to purchase the items in their remote shopping cart. When the value is False the remote shopping cart contents are not added to the retail shopping cart. Instead, the customer is sent directly to the Order Pipeline when they elect to purchase the items in their cart. This parameter is valid only in the US locale. In all other locales, the parameter is invalid but the request behaves as though the value were set to True.
	 * 	MerchantId - _string_ (Optional) An alphanumeric token distributed by Amazon that uniquely identifies a merchant. Allows 'All', 'Amazon', 'FeaturedBuyBoxMerchant', or a specific Merchant ID. Defaults to 'Amazon'.
	 * 	ResponseGroup - _string_ (Optional) Specifies the types of values to return. You can specify multiple response groups in one request by separating them with commas.
	 * 	returnCurlHandle - _boolean_ (Optional) A private toggle that will return the CURL handle for the request rather than actually completing the request. This is useful for MultiCURL requests.
	 * 	Style - _string_ (Optional) Controls the format of the data returned in Amazon Associates Web Service responses. Set this parameter to "XML," the default, to generate a pure XML response. Set this parameter to the URL of an XSLT stylesheet to have Amazon Associates Web Service transform the XML response. See ContentType.
	 * 	Validate - _boolean_ (Optional) Prevents an operation from executing. Set the Validate parameter to True to test your request without actually executing it. When present, Validate must equal True; the default value is False. If a request is not actually executed (Validate=True), only a subset of the errors for a request may be returned because some errors (for example, no_exact_matches) are only generated during the execution of a request. Defaults to FALSE.
	 * 	XMLEscaping - _string_ (Optional) Specifies whether responses are XML-encoded in a single pass or a double pass. By default, XMLEscaping is Single, and Amazon Associates Web Service responses are encoded only once in XML. For example, if the response data includes an ampersand character (&), the character is returned in its regular XML encoding (&). If XMLEscaping is Double, the same ampersand character is XML-encoded twice (&amp;). The Double value for XMLEscaping is useful in some clients, such as PHP, that do not decode text within XML elements. Defaults to 'Single'.
	 * 
	 * Returns:
	 * 	<TarzanHTTPResponse> object
	 * 
	 * See Also:
	 * 	AWS Method - http://docs.amazonwebservices.com/AWSECommerceService/2008-08-19/DG/CartAdd.html
	 */
	public function cart_add($cart_id, $hmac, $offer_listing_id, $opt = null, $locale = AAWS_LOCALE_US)
	{
		if (!$opt) $opt = array();
		$opt['CartId'] = $cart_id;
		$opt['HMAC'] = $hmac;

		if (is_array($offer_listing_id))
		{
			$count = 1;
			foreach ($offer_listing_id as $offer => $quantity)
			{
				$opt['Item.' . $count . '.OfferListingId'] = $offer;
				$opt['Item.' . $count . '.Quantity'] = $quantity;

				$count++;
			}
		}
		else
		{
			$opt['Item.1.OfferListingId'] = $offer_listing_id;
			$opt['Item.1.Quantity'] = 1;
		}

		if (isset($this->assoc_id) && !empty($this->assoc_id))
		{
			$opt['AssociateTag'] = $this->assoc_id;
		}

		return $this->authenticate('CartAdd', $opt, $locale);
	}

	/**
	 * Method: cart_clear()
	 * 	Enables you to remove all of the items in a remote shopping cart, including SavedForLater items. To remove only some of the items in a cart or to reduce the quantity of one or more items, use <cart_modify()>.
	 * 
	 * 	To delete all of the items from a remote shopping cart, you must specify the cart using the CartId and HMAC values, which are returned by the <cart_create()> operation. A value similar to the HMAC, URLEncodedHMAC, is also returned. This value is the URL encoded version of the HMAC. This encoding is necessary because some characters, such as + and /, cannot be included in a URL. Rather than encoding the HMAC yourself, use the URLEncodedHMAC value for the HMAC parameter.
	 * 
	 * 	<cart_clear()> does not work after the customer has used the PurchaseURL to either purchase the items or merge them with the items in their Amazon cart. Carts exist even though they have been emptied. The lifespan of a cart is 7 days since the last time it was acted upon. For example, if a cart created 6 days ago is modified, the cart lifespan is reset to 7 days.
	 * 
	 * 	If you added your Associates ID to the config.inc.php file, or you passed it into the AmazonAAWS() constructor, it will be passed along in this request automatically.
	 * 
	 * Access:
	 * 	public
	 * 
	 * Parameters:
	 * 	cart_id - _string_ (Required) Alphanumeric token returned by <cart_create()> that identifies a cart.
	 * 	hmac - _string_ (Required) Encrypted alphanumeric token returned by <cart_create()> that authorizes access to a cart.
	 * 	opt - _array_ (Optional) Associative array of parameters which can have the following keys:
	 * 	locale - _string_ (Optional) Which Amazon-supported locale do we use? Defaults to United States.
	 * 
	 * Keys for the $opt parameter:
	 * 	ContentType - _string_ (Optional) Specifies the format of the content in the response. Generally, ContentType should only be changed for REST requests when the Style parameter is set to an XSLT stylesheet. For example, to transform your Amazon Associates Web Service response into HTML, set ContentType to text/html. Allows 'text/xml' and 'text/html'. Defaults to 'text/xml'.
	 * 	MergeCart - _boolean_ (Optional) A boolean value that when True specifies that the items in a customer's remote shopping cart are added to the customer's Amazon retail shopping cart. This occurs when the customer elects to purchase the items in their remote shopping cart. When the value is False the remote shopping cart contents are not added to the retail shopping cart. Instead, the customer is sent directly to the Order Pipeline when they elect to purchase the items in their cart. This parameter is valid only in the US locale. In all other locales, the parameter is invalid but the request behaves as though the value were set to True.
	 * 	MerchantId - _string_ (Optional) An alphanumeric token distributed by Amazon that uniquely identifies a merchant. Allows 'All', 'Amazon', 'FeaturedBuyBoxMerchant', or a specific Merchant ID. Defaults to 'Amazon'.
	 * 	ResponseGroup - _string_ (Optional) Specifies the types of values to return. You can specify multiple response groups in one request by separating them with commas.
	 * 	returnCurlHandle - _boolean_ (Optional) A private toggle that will return the CURL handle for the request rather than actually completing the request. This is useful for MultiCURL requests.
	 * 	Style - _string_ (Optional) Controls the format of the data returned in Amazon Associates Web Service responses. Set this parameter to "XML," the default, to generate a pure XML response. Set this parameter to the URL of an XSLT stylesheet to have Amazon Associates Web Service transform the XML response. See ContentType.
	 * 	Validate - _boolean_ (Optional) Prevents an operation from executing. Set the Validate parameter to True to test your request without actually executing it. When present, Validate must equal True; the default value is False. If a request is not actually executed (Validate=True), only a subset of the errors for a request may be returned because some errors (for example, no_exact_matches) are only generated during the execution of a request. Defaults to FALSE.
	 * 	XMLEscaping - _string_ (Optional) Specifies whether responses are XML-encoded in a single pass or a double pass. By default, XMLEscaping is Single, and Amazon Associates Web Service responses are encoded only once in XML. For example, if the response data includes an ampersand character (&), the character is returned in its regular XML encoding (&). If XMLEscaping is Double, the same ampersand character is XML-encoded twice (&amp;). The Double value for XMLEscaping is useful in some clients, such as PHP, that do not decode text within XML elements. Defaults to 'Single'.
	 * 
	 * Returns:
	 * 	<TarzanHTTPResponse> object
	 * 
	 * See Also:
	 * 	AWS Method - http://docs.amazonwebservices.com/AWSECommerceService/2008-08-19/DG/CartClear.html
	 */
	public function cart_clear($cart_id, $hmac, $opt = null, $locale = AAWS_LOCALE_US)
	{
		if (!$opt) $opt = array();
		$opt['CartId'] = $cart_id;
		$opt['HMAC'] = $hmac;

		if (isset($this->assoc_id) && !empty($this->assoc_id))
		{
			$opt['AssociateTag'] = $this->assoc_id;
		}

		return $this->authenticate('CartClear', $opt, $locale);
	}

	/**
	 * Method: cart_create()
	 * 	Enables you to create a remote shopping cart. A shopping cart is the metaphor used by most e-commerce solutions. It is a temporary data storage structure that resides on Amazon servers. The structure contains the items a customer wants to buy. In Amazon Associates Web Service, the shopping cart is considered remote because it is hosted by Amazon servers. In this way, the cart is remote to the vendor's web site where the customer views and selects the items they want to purchase.
	 * 
	 * 	Once you add an item to a cart by specifying the item's ListItemId and ASIN, or OfferListing ID, the item is assigned a CartItemId and accessible only by that value. That is, in subsequent requests, an item in a cart cannot be accessed by its ListItemId and ASIN, or OfferListingId.
	 * 
	 * 	Because the contents of a cart can change for different reasons, such as item availability, you should not keep a copy of a cart locally. Instead, use the other cart operations to modify the cart contents. For example, to retrieve contents of the cart, which are represented by CartItemIds, use <cart_get()>.
	 * 
	 * 	Available products are added as cart items. Unavailable items, for example, items out of stock, discontinued, or future releases, are added as SaveForLaterItems. No error is generated. The Amazon database changes regularly. You may find a product with an offer listing ID but by the time the item is added to the cart the product is no longer available. The checkout page in the Order Pipeline clearly lists items that are available and those that are SaveForLaterItems.
	 * 
	 * 	It is impossible to create an empty shopping cart. You have to add at least one item to a shopping cart using a single <cart_create()> request. You can add specific quantities (up to 999) of each item. <cart_create()> can be used only once in the life cycle of a cart. To modify the contents of the cart, use one of the other cart operations.
	 * 
	 * 	Carts cannot be deleted. They expire automatically after being unused for 7 days. The lifespan of a cart restarts, however, every time a cart is modified. In this way, a cart can last for more than 7 days. If, for example, on day 6, the customer modifies a cart, the 7 day countdown starts over.
	 * 
	 * 	If you added your Associates ID to the config.inc.php file, or you passed it into the AmazonAAWS() constructor, it will be passed along in this request automatically.
	 * 
	 * Access:
	 * 	public
	 * 
	 * Parameters:
	 * 	offer_listing_id - _string|array_ (Required) Either a string containing the Offer ID to add, or an associative array where the Offer ID is the key and the quantity is the value. An offer listing ID is an alphanumeric token that uniquely identifies an item. Use the OfferListingId instead of an item's ASIN to add the item to the cart.
	 * 	opt - _array_ (Optional) Associative array of parameters which can have the following keys:
	 * 	locale - _string_ (Optional) Which Amazon-supported locale do we use? Defaults to United States.
	 * 
	 * Keys for the $opt parameter:
	 * 	ContentType - _string_ (Optional) Specifies the format of the content in the response. Generally, ContentType should only be changed for REST requests when the Style parameter is set to an XSLT stylesheet. For example, to transform your Amazon Associates Web Service response into HTML, set ContentType to text/html. Allows 'text/xml' and 'text/html'. Defaults to 'text/xml'.
	 * 	MergeCart - _boolean_ (Optional) A boolean value that when True specifies that the items in a customer's remote shopping cart are added to the customer's Amazon retail shopping cart. This occurs when the customer elects to purchase the items in their remote shopping cart. When the value is False the remote shopping cart contents are not added to the retail shopping cart. Instead, the customer is sent directly to the Order Pipeline when they elect to purchase the items in their cart. This parameter is valid only in the US locale. In all other locales, the parameter is invalid but the request behaves as though the value were set to True.
	 * 	MerchantId - _string_ (Optional) An alphanumeric token distributed by Amazon that uniquely identifies a merchant. Allows 'All', 'Amazon', 'FeaturedBuyBoxMerchant', or a specific Merchant ID. Defaults to 'Amazon'.
	 * 	ResponseGroup - _string_ (Optional) Specifies the types of values to return. You can specify multiple response groups in one request by separating them with commas.
	 * 	returnCurlHandle - _boolean_ (Optional) A private toggle that will return the CURL handle for the request rather than actually completing the request. This is useful for MultiCURL requests.
	 * 	Style - _string_ (Optional) Controls the format of the data returned in Amazon Associates Web Service responses. Set this parameter to "XML," the default, to generate a pure XML response. Set this parameter to the URL of an XSLT stylesheet to have Amazon Associates Web Service transform the XML response. See ContentType.
	 * 	Validate - _boolean_ (Optional) Prevents an operation from executing. Set the Validate parameter to True to test your request without actually executing it. When present, Validate must equal True; the default value is False. If a request is not actually executed (Validate=True), only a subset of the errors for a request may be returned because some errors (for example, no_exact_matches) are only generated during the execution of a request. Defaults to FALSE.
	 * 	XMLEscaping - _string_ (Optional) Specifies whether responses are XML-encoded in a single pass or a double pass. By default, XMLEscaping is Single, and Amazon Associates Web Service responses are encoded only once in XML. For example, if the response data includes an ampersand character (&), the character is returned in its regular XML encoding (&). If XMLEscaping is Double, the same ampersand character is XML-encoded twice (&amp;). The Double value for XMLEscaping is useful in some clients, such as PHP, that do not decode text within XML elements. Defaults to 'Single'.
	 * 
	 * Returns:
	 * 	<TarzanHTTPResponse> object
	 * 
	 * See Also:
	 * 	AWS Method - http://docs.amazonwebservices.com/AWSECommerceService/2008-08-19/DG/CartCreate.html
	 */
	public function cart_create($offer_listing_id, $opt = null, $locale = AAWS_LOCALE_US)
	{
		if (!$opt) $opt = array();

		if (is_array($offer_listing_id))
		{
			$count = 1;
			foreach ($offer_listing_id as $offer => $quantity)
			{
				$opt['Item.' . $count . '.OfferListingId'] = $offer;
				$opt['Item.' . $count . '.Quantity'] = $quantity;

				$count++;
			}
		}
		else
		{
			$opt['Item.1.OfferListingId'] = $offer_listing_id;
			$opt['Item.1.Quantity'] = 1;
		}

		if (isset($this->assoc_id) && !empty($this->assoc_id))
		{
			$opt['AssociateTag'] = $this->assoc_id;
		}

		return $this->authenticate('CartCreate', $opt, $locale);
	}

	/**
	 * Method: cart_get()
	 * 	Enables you to retrieve the IDs, quantities, and prices of all of the items, including SavedForLater items in a remote shopping cart.
	 * 
	 * 	Because the contents of a cart can change for different reasons, such as availability, you should not keep a copy of a cart locally. Instead, use <cart_get()> to retrieve the items in a remote shopping cart. To retrieve the items in a cart, you must specify the cart using the CartId and HMAC values, which are returned in the <cart_create()> operation. A value similar to HMAC, URLEncodedHMAC, is also returned.
	 * 
	 * 	This value is the URL encoded version of the HMAC. This encoding is necessary because some characters, such as + and /, cannot be included in a URL. Rather than encoding the HMAC yourself, use the URLEncodedHMAC value for the HMAC parameter.
	 * 
	 * 	<cart_get()> does not work after the customer has used the PurchaseURL to either purchase the items or merge them with the items in their Amazon cart.
	 * 
	 * Access:
	 * 	public
	 * 
	 * Parameters:
	 * 	cart_id - _string_ (Required) Alphanumeric token returned by <cart_create()> that identifies a cart.
	 * 	hmac - _string_ (Required) Encrypted alphanumeric token returned by <cart_create()> that authorizes access to a cart.
	 * 	cart_item_id - _string_ (Required) Alphanumeric token that uniquely identifies an item in a cart. Once an item, specified by an ASIN or OfferListingId, has been added to a cart, you must use the CartItemId to refer to it. The other identifiers will not work.
	 * 	opt - _array_ (Optional) Associative array of parameters which can have the following keys:
	 * 	locale - _string_ (Optional) Which Amazon-supported locale do we use? Defaults to United States.
	 * 
	 * Keys for the $opt parameter:
	 * 	ContentType - _string_ (Optional) Specifies the format of the content in the response. Generally, ContentType should only be changed for REST requests when the Style parameter is set to an XSLT stylesheet. For example, to transform your Amazon Associates Web Service response into HTML, set ContentType to text/html. Allows 'text/xml' and 'text/html'. Defaults to 'text/xml'.
	 * 	MergeCart - _boolean_ (Optional) A boolean value that when True specifies that the items in a customer's remote shopping cart are added to the customer's Amazon retail shopping cart. This occurs when the customer elects to purchase the items in their remote shopping cart. When the value is False the remote shopping cart contents are not added to the retail shopping cart. Instead, the customer is sent directly to the Order Pipeline when they elect to purchase the items in their cart. This parameter is valid only in the US locale. In all other locales, the parameter is invalid but the request behaves as though the value were set to True.
	 * 	MerchantId - _string_ (Optional) An alphanumeric token distributed by Amazon that uniquely identifies a merchant. Allows 'All', 'Amazon', 'FeaturedBuyBoxMerchant', or a specific Merchant ID. Defaults to 'Amazon'.
	 * 	ResponseGroup - _string_ (Optional) Specifies the types of values to return. You can specify multiple response groups in one request by separating them with commas.
	 * 	returnCurlHandle - _boolean_ (Optional) A private toggle that will return the CURL handle for the request rather than actually completing the request. This is useful for MultiCURL requests.
	 * 	Style - _string_ (Optional) Controls the format of the data returned in Amazon Associates Web Service responses. Set this parameter to "XML," the default, to generate a pure XML response. Set this parameter to the URL of an XSLT stylesheet to have Amazon Associates Web Service transform the XML response. See ContentType.
	 * 	Validate - _boolean_ (Optional) Prevents an operation from executing. Set the Validate parameter to True to test your request without actually executing it. When present, Validate must equal True; the default value is False. If a request is not actually executed (Validate=True), only a subset of the errors for a request may be returned because some errors (for example, no_exact_matches) are only generated during the execution of a request. Defaults to FALSE.
	 * 	XMLEscaping - _string_ (Optional) Specifies whether responses are XML-encoded in a single pass or a double pass. By default, XMLEscaping is Single, and Amazon Associates Web Service responses are encoded only once in XML. For example, if the response data includes an ampersand character (&), the character is returned in its regular XML encoding (&). If XMLEscaping is Double, the same ampersand character is XML-encoded twice (&amp;). The Double value for XMLEscaping is useful in some clients, such as PHP, that do not decode text within XML elements. Defaults to 'Single'.
	 * 
	 * Returns:
	 * 	<TarzanHTTPResponse> object
	 * 
	 * See Also:
	 * 	AWS Method - http://docs.amazonwebservices.com/AWSECommerceService/2008-08-19/DG/CartGet.html
	 */
	public function cart_get($cart_id, $hmac, $cart_item_id, $opt = null, $locale = AAWS_LOCALE_US)
	{
		if (!$opt) $opt = array();
		$opt['CartId'] = $cart_id;
		$opt['CartItemId'] = $cart_item_id;
		$opt['HMAC'] = $hmac;

		if (isset($this->assoc_id) && !empty($this->assoc_id))
		{
			$opt['AssociateTag'] = $this->assoc_id;
		}

		return $this->authenticate('CartGet', $opt, $locale);
	}

	/**
	 * Method: cart_modify()
	 * 	Enables you to change the quantity of items that are already in a remote shopping cart, move items from the active area of a cart to the SaveForLater area or the reverse, and change the MergeCart setting.
	 * 
	 * 	To modify the number of items in a cart, you must specify the cart using the CartId and HMAC values that are returned in the <cart_create()> operation. A value similar to HMAC, URLEncodedHMAC, is also returned. This value is the URL encoded version of the HMAC. This encoding is necessary because some characters, such as + and /, cannot be included in a URL. Rather than encoding the HMAC yourself, use the URLEncodedHMAC value for the HMAC parameter.
	 * 
	 * 	You can use <cart_modify()> to modify the number of items in a remote shopping cart by setting the value of the Quantity parameter appropriately. You can eliminate an item from a cart by setting the value of the Quantity parameter to zero. Or, you can double the number of a particular item in the cart by doubling its Quantity. You cannot, however, use <cart_modify()> to add new items to a cart.
	 * 
	 * Access:
	 * 	public
	 * 
	 * Parameters:
	 * 	cart_id - _string_ (Required) Alphanumeric token returned by <cart_create()> that identifies a cart.
	 * 	hmac - _string_ (Required) Encrypted alphanumeric token returned by <cart_create()> that authorizes access to a cart.
	 * 	cart_item_id - _string|array_ (Required) Specifies an item to be modified in the cart where N is a positive integer between 1 and 10, inclusive. Up to ten items can be modified at a time.CartItemId is neither an ASIN nor an OfferListingId. It is, instead, an alphanumeric token returned by <cart_create()> and <cart_add()>. This parameter is used in conjunction with Item.N.Quantity to modify the number of items in a 
	 * 	opt - _array_ (Optional) Associative array of parameters which can have the following keys:
	 * 	locale - _string_ (Optional) Which Amazon-supported locale do we use? Defaults to United States.
	 * 
	 * Keys for the $opt parameter:
	 * 	Action - _string_ (Optional) Change cart items to move items to the Saved-For-Later area, or change Saved-For- Later (SaveForLater) items to the active cart area (MoveToCart).
	 * 	ContentType - _string_ (Optional) Specifies the format of the content in the response. Generally, ContentType should only be changed for REST requests when the Style parameter is set to an XSLT stylesheet. For example, to transform your Amazon Associates Web Service response into HTML, set ContentType to text/html. Allows 'text/xml' and 'text/html'. Defaults to 'text/xml'.
	 * 	ListItemId - _string_ (Optional) The ListItemId parameter is returned by the ListItems response group. The parameter identifies an item on a list, such as a wishlist. To add this item to a cart, you must include in the <cart_create()> request the item's ASIN and ListItemId. The ListItemId includes the name and address of the list owner, which the ASIN alone does not.
	 * 	MergeCart - _boolean_ (Optional) A boolean value that when True specifies that the items in a customer's remote shopping cart are added to the customer's Amazon retail shopping cart. This occurs when the customer elects to purchase the items in their remote shopping cart. When the value is False the remote shopping cart contents are not added to the retail shopping cart. Instead, the customer is sent directly to the Order Pipeline when they elect to purchase the items in their cart. This parameter is valid only in the US locale. In all other locales, the parameter is invalid but the request behaves as though the value were set to True.
	 * 	MerchantId - _string_ (Optional) An alphanumeric token distributed by Amazon that uniquely identifies a merchant. Allows 'All', 'Amazon', 'FeaturedBuyBoxMerchant', or a specific Merchant ID. Defaults to 'Amazon'.
	 * 	ResponseGroup - _string_ (Optional) Specifies the types of values to return. You can specify multiple response groups in one request by separating them with commas.
	 * 	returnCurlHandle - _boolean_ (Optional) A private toggle that will return the CURL handle for the request rather than actually completing the request. This is useful for MultiCURL requests.
	 * 	Style - _string_ (Optional) Controls the format of the data returned in Amazon Associates Web Service responses. Set this parameter to "XML," the default, to generate a pure XML response. Set this parameter to the URL of an XSLT stylesheet to have Amazon Associates Web Service transform the XML response. See ContentType.
	 * 	Validate - _boolean_ (Optional) Prevents an operation from executing. Set the Validate parameter to True to test your request without actually executing it. When present, Validate must equal True; the default value is False. If a request is not actually executed (Validate=True), only a subset of the errors for a request may be returned because some errors (for example, no_exact_matches) are only generated during the execution of a request. Defaults to FALSE.
	 * 	XMLEscaping - _string_ (Optional) Specifies whether responses are XML-encoded in a single pass or a double pass. By default, XMLEscaping is Single, and Amazon Associates Web Service responses are encoded only once in XML. For example, if the response data includes an ampersand character (&), the character is returned in its regular XML encoding (&). If XMLEscaping is Double, the same ampersand character is XML-encoded twice (&amp;). The Double value for XMLEscaping is useful in some clients, such as PHP, that do not decode text within XML elements. Defaults to 'Single'.
	 * 
	 * Returns:
	 * 	<TarzanHTTPResponse> object
	 * 
	 * See Also:
	 * 	AWS Method - http://docs.amazonwebservices.com/AWSECommerceService/2008-08-19/DG/CartModify.html
	 */
	public function cart_modify($cart_id, $hmac, $cart_item_id, $opt = null, $locale = AAWS_LOCALE_US)
	{
		if (!$opt) $opt = array();
		$opt['CartId'] = $cart_id;
		$opt['HMAC'] = $hmac;

		if (is_array($cart_item_id))
		{
			$count = 1;
			foreach ($cart_item_id as $offer => $quantity)
			{
				$opt['Item.' . $count . '.CartItemId'] = $offer;
				$opt['Item.' . $count . '.Quantity'] = $quantity;

				$count++;
			}
		}
		else
		{
			$opt['Item.1.CartItemId'] = $offer_listing_id;
			$opt['Item.1.Quantity'] = 1;
		}

		if (isset($this->assoc_id) && !empty($this->assoc_id))
		{
			$opt['AssociateTag'] = $this->assoc_id;
		}

		return $this->authenticate('CartModify', $opt, $locale);
	}
  
  
}
