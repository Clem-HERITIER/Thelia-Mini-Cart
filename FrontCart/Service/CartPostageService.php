<?php

namespace FrontCart\Service;

use FrontCart\FrontCart;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\Customer;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Module\DeliveryModuleInterface;
use Thelia\Module\Exception\DeliveryException;

class CartPostageService
{
    /** @var EventDispatcher  */
    protected $eventDispatcher;

    /** @var Request */
    protected $request;

    /** @var RequestStack */
    protected $requestStack;

    /** @var ContainerInterface Service Container */
    protected $container = null;

    /** @var  */
    protected $state;

    /** @var integer $countryId the id of country */
    protected $countryId = null;

    /** @var integer $deliveryId the id of the cheapest delivery */
    protected $deliveryId = null;

    /** @var  string $deliveryCode the name of the cheapest delivery*/
    protected $deliveryCode = null;

    /** @var float $postage the postage amount with taxes */
    protected $postage = null;

    /** @var float $postageTax the postage tax amount */
    protected $postageTax = null;

    /** @var string $postageTaxRuleTitle the postage tax rule title */
    protected $postageTaxRuleTitle = null;

    /** @var boolean $isCustomizable indicate if customer can change the country */
    protected $isCustomizable = true;


    public function __construct(EventDispatcher $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function getPostage($request, $container)
    {
        $this->request = $request;
        $this->container = $container;

        $customer = $this->request->getSession()->getCustomerUser();

        list($address, $country, $state) = $this->getDeliveryInformation($customer);

        $this->$address = $address;
        $this->$country = $country;
        $this->$state = $state;

        /** @var Country $country */
        if (null !== $country)
        {
            $this->countryId = $country->getId();
            // try to get the cheapest delivery for this country
            $this->getCheapestDelivery($address, $country);
        }

        // If the cart weight exceed the max weight from delivery module, set delivery at Impossible
        if ($this->postage === null)
        {
            $this->postage = Translator::getInstance()->trans('Impossible delivery',
                [],
                FrontCart::DOMAIN_NAME);
        }
        // If delivery module is configured with "free shipping", set delivery at Offered
        elseif ($this->postage === 0)
        {
            $this->postage = Translator::getInstance()->trans('Offered',
                [],
                FrontCart::DOMAIN_NAME);
        }

        return ["Postage" => $this->postage, "PostageTax" => $this->postageTax, "PostageTaxRuleTitle"=>$this->postageTaxRuleTitle, "DeliveryCode" => $this->deliveryCode, "DeliveryId" => $this->deliveryId, "CountryId" => $this->countryId ];
    }

    /**
     * Retrieve the delivery country for a customer
     *
     * The rules :
     *  - the country of the delivery address of the customer related to the
     *      cart if it exists
     *  - the country saved in cookie if customer have changed
     *      the default country
     *  - the default country for the shop if it exists
     *
     *
     * @param  \Thelia\Model\Customer $customer
     * @return \Thelia\Model\Country
     */
    protected function getDeliveryInformation(Customer $customer = null)
    {
        $address = null;
        // get the selected delivery address
        if (null !== $addressId = $this->request->getSession()->getOrder()->getChoosenDeliveryAddress()) {
            if (null !== $address = AddressQuery::create()->findPk($addressId)) {
                $this->isCustomizable = false;
                return [$address, $address->getCountry(), null];
            }
        }

        // get country from customer addresses
        if (null !== $customer) {
            $address = AddressQuery::create()
                ->filterByCustomerId($customer->getId())
                ->filterByIsDefault(1)
                ->findOne()
            ;

            if (null !== $address) {
                $this->isCustomizable = false;

                return [$address, $address->getCountry(), null];
            }
        }

        // get country from cookie
        $cookieName = ConfigQuery::read('front_cart_country_cookie_name', 'fcccn');
        if ($this->request->cookies->has($cookieName)) {
            $cookieVal = $this->request->cookies->getInt($cookieName, 0);
            if (0 !== $cookieVal) {
                $country = CountryQuery::create()->findPk($cookieVal);
                if (null !== $country) {
                    return [null, $country, null];
                }
            }
        }

        // get default country for store.
        try {
            $country = Country::getDefaultCountry();

            return [null, $country, null];
        } catch (\LogicException $e) {
            ;
        }

        return [null, null, null];
    }

    /**
     * Retrieve the cheapest delivery for country
     *
     * @param Address $address
     * @param \Thelia\Model\Country $country
     * @return DeliveryModuleInterface
     */
    protected function getCheapestDelivery(Address $address = null, Country $country = null)
    {
        $cart = $this->request->getSession()->getSessionCart();

        $deliveryModules = ModuleQuery::create()
            ->filterByActivate(1)
            ->filterByType(BaseModule::DELIVERY_MODULE_TYPE, Criteria::EQUAL)
            ->find();

        $virtual = $cart->isVirtual();

        /** @var \Thelia\Model\Module $deliveryModule */
        foreach ($deliveryModules as $deliveryModule)
        {
            $areaDeliveryModule = AreaDeliveryModuleQuery::create()
                ->findByCountryAndModule($country, $deliveryModule, $this->state);

            if (null === $areaDeliveryModule && false === $virtual) {
                continue;
            }

            $moduleInstance = $deliveryModule->getDeliveryModuleInstance($this->container);

            if (true === $virtual
                && false === $moduleInstance->handleVirtualProductDelivery()
            ) {
                continue;
            }

            try {
                $deliveryPostageEvent = new DeliveryPostageEvent($moduleInstance, $cart, $address, $country, $this->state);
                $this->eventDispatcher->dispatch(
                    TheliaEvents::MODULE_DELIVERY_GET_POSTAGE,
                    $deliveryPostageEvent
                );

                if ($deliveryPostageEvent->isValidModule()) {
                    $postage = $deliveryPostageEvent->getPostage();

                    if (null === $this->postage || $this->postage > $postage->getAmount()) {
                        $this->postage = $postage->getAmount();
                        $this->postageTax = $postage->getAmountTax();
                        $this->postageTaxRuleTitle = $postage->getTaxRuleTitle();
                        $this->deliveryId = $deliveryModule->getId();
                        $this->deliveryCode = $deliveryModule->getCode();
                    }
                }
            } catch (DeliveryException $ex) {
                // Module is not available
            }
        }
    }
}