<?php
namespace FrontCart\Controller;

use FrontCart\Event\FrontCartDataEvent;
use FrontCart\Event\FrontCartResponseEvent;
use FrontCart\FrontCart;
use FrontCart\Service\CartPostageService;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\DefaultActionEvent;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Base\AttributeCombination;
use Thelia\Model\Base\AttributeI18nQuery;
use Thelia\Model\Base\CartItemQuery;
use Thelia\Model\Base\CouponQuery;
use Thelia\Model\Base\ProductSaleElementsQuery;
use Thelia\Model\Cart;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\MoneyFormat;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\JsonResponse;
use Thelia\Form\CartAdd;
use Thelia\Form\Definition\FrontForm;
use Thelia\Model\AddressQuery;
use Thelia\Model\AttributeAvI18nQuery;
use FrontCart\Service\ImageService;
use Thelia\Model\ProductImageQuery;
use Thelia\Tools\URL;

class CartController extends BaseFrontController
{
    public function getCart()
   {
       // Generate Json from cartItems
       $dataCartItems = $this -> creatDataCartItems();
       $dataCart = $this->createDataCart();

       $response = ["error"=>null,
                    "Data"=>[
                             "DataCart"=>$dataCart,
                             "DataCartItems"=>$dataCartItems
                            ]
                   ];

       $this->createResponseEvent($response);
       return new JsonResponse($response);
   }

    public function addItem()
    {
       try {
           $request = $this->getRequest();
           $cartAdd = $this->getAddCartForm($request);
           $form = $this->validateForm($cartAdd);
           $cartEvent = $this->getCartEvent();
           $cartEvent->bindForm($form);

           // Get PSE Quantity and throw error if requested quantity exceed stock
           $availableQuantity = ProductSaleElementsQuery::create()
               ->filterById($cartEvent->getProductSaleElementsId())
               ->findOne()
               ->getQuantity();

           if ($cartEvent->getQuantity() > $availableQuantity)
           {
               throw new \Exception('Desired quantity exceed available stock');
           }

           // Get actual cart_item_quantity, requested quantity and stock
           $cartQuantity = CartItemQuery::create()
               ->filterByCartId($cartEvent->getCart()->getId())
               ->filterByProductSaleElementsId($cartEvent->getProductSaleElementsId())
               ->findOne();

           if (null !== $cartQuantity)
           {
               if ($cartQuantity->getQuantity() + $cartEvent->getQuantity() >= $availableQuantity)
               {
                   throw new \Exception('Stock is too low');
               }
           }

           $this->getDispatcher()->dispatch(TheliaEvents::CART_ADDITEM, $cartEvent);
           $this->afterModifyCart();

           // Generate Json from cartItems
           $dataCart = $this->createDataCart();
           $dataCartItems = $this->creatDataCartItems();

               $response = ["error"=>null,
                            "msg"=>"successfully added",
                            "Data"=>[
                                     "DataCart"=>$dataCart,
                                     "DataCartItems"=>$dataCartItems
                                    ]
                           ];

           $this->createResponseEvent($response);
           return new JsonResponse($response);
       }
       catch (\Exception $e)
       {
           $error = $e->getMessage();

           $response = ["error"=> "400",
                        "msg" => $error
                       ];

           $this->createResponseEvent($response);
           return new JsonResponse($response);
       }
    }

    public function updateItem()
    {
       try {
           $request = $this->getRequest();
           $cart = $request->getSession()->getSessionCart($this->getDispatcher());
           $cartEvent = $this->getCartEvent();

           $cartEvent->setCartItemId($request->get("cart_item"));
           $cartEvent->setQuantity($request->get("quantity"));

           // Check if cart item exist and belong to user cart
           $cartItem = CartItemQuery::create()
               ->filterById($cartEvent->getCartItemId())
               ->findOne();

           if ($cartItem->getCartId() !== $cart->getId())
           {
               throw new \Exception('This cart_item doesn\'t belong to this cart');
           }
           if (null === $cartItem)
           {
               throw  new  \Exception('Can\'t update non-existent cart item : '. $request->get("cart_item"));
           }

           // Get PSE Quantity and throw error if requested quantity exceed stock
           $availableQuantity = ProductSaleElementsQuery::create()
               ->leftJoinCartItem()
               ->where('cart_item.id = ?', $request->get("cart_item"))
               ->findOne()
               ->getQuantity();

           if ($request->get("quantity") >= $availableQuantity)
           {
               throw new \Exception('Desired quantity exceed available stock');
           }

           $this->dispatch(TheliaEvents::CART_UPDATEITEM, $cartEvent);
           $this->afterModifyCart();

           $dataCart = $this->createDataCart();
           $dataCartItems = $this->creatDataCartItems();
           $response = ["error"=>null,
                        "msg"=>"successfully added",
                        "Data"=>[
                                 "DataCart"=>$dataCart,
                                 "DataCartItems"=>$dataCartItems
                                ]
                       ];

           $this->createResponseEvent($response);
           return new JsonResponse($response);
       }
       catch (\Exception $e)
       {
           $error = $e->getMessage();

           $response = ["error"=> "400",
               "msg" => $error
           ];

           $this->createResponseEvent($response);
           return new JsonResponse($response);
       }
    }

    public function deleteItem()
    {
       try {
           $request = $this->getRequest();
           $cart = $request->getSession()->getSessionCart($this->getDispatcher());

           // Check if cart item exist and belong to user cart
           $cartItem = CartItemQuery::create()
               ->filterByCartId($cart->getId())
               ->filterById($request->get("cart_item"))
               ->findOne();

           if (null === $cartItem)
           {
               throw new \Exception('Cant delete non-existent cart item');
           }

           $cartEvent = $this->getCartEvent();
           $cartEvent->setCartItemId($request->get("cart_item"));

           $this->getDispatcher()->dispatch(TheliaEvents::CART_DELETEITEM, $cartEvent);
           $this->afterModifyCart();

           // Generate Json from cartItems
           $dataCart = $this->createDataCart();
           $dataCartItems = $this -> creatDataCartItems();
           $response = ["error"=>null,
                        "msg"=>"Item successfully deleted",
                        "Data"=>[
                                 "DataCart"=>$dataCart,
                                 "DataCartItems"=>$dataCartItems
                                ]
                       ];

           $this->createResponseEvent($response);
           return new JsonResponse($response);
       }
       catch (\Exception $e)
       {
           $error = $e->getMessage();

           $response = ["error"=> "400",
               "msg" => $error
           ];

           $this->createResponseEvent($response);
           return new JsonResponse($response);
       }
    }

    public function clearCart()
    {
       try {
           $cartEvent = $this->getCartEvent();
           $this->getDispatcher()->dispatch(TheliaEvents::CART_CLEAR, $cartEvent);

           // Check if cart was successfully cleared
           $cartitems = CartItemQuery::create()
               ->filterByCartId($cartEvent->getCart()->getId())
               ->findOne();

           if (null !== $cartitems)
           {
               throw new \Exception("Cart didn't clear successfully");
           }
       }
       catch (\Exception $e)
       {
           $error = $e->getMessage();

           $response = ["error"=> "400",
               "msg" => $error
           ];
           return new JsonResponse($response);
       }

       $response = ["error"=> null,
                    "msg" => "Cart successfully clear"
                   ];

       return new JsonResponse($response);
    }

    public function changeCountry()
    {
        $redirectUrl = URL::getInstance()->absoluteUrl("/cart");
        $deliveryId = $this->getRequest()->get("country");
        $cookieName = ConfigQuery::read('front_cart_country_cookie_name', 'fcccn');
        $cookieExpires = ConfigQuery::read('front_cart_country_cookie_expires', 2592000);
        $cookieExpires = intval($cookieExpires) ?: 2592000;

        $cookie = new Cookie($cookieName, $deliveryId, time() + $cookieExpires, '/');

        $response = $this->generateRedirect($redirectUrl);
        $response->headers->setCookie($cookie);

        return $response;
    }

    /**
    * Dispatch a Thelia event
    *
    * @param string $eventName a TheliaEvent name, as defined in TheliaEvents class
    * @param ActionEvent  $event     the action event, or null (a DefaultActionEvent will be dispatched)
    */
    protected function dispatch($eventName, ActionEvent $event = null)
    {
        if ($event == null) {
            $event = new DefaultActionEvent();
        }

        $this->getDispatcher()->dispatch($eventName, $event);
    }

    /**
    * Return the event dispatcher,
    *
    * @return \Symfony\Component\EventDispatcher\EventDispatcher
    */
    public function getDispatcher()
    {
        return $this->container->get('event_dispatcher');
    }

    /**
    * @return \Thelia\Core\Event\Cart\CartEvent
    */
    protected function getCartEvent()
    {
        $cart = $this->getSession()->getSessionCart($this->getDispatcher());

        return new CartEvent($cart);
    }

    /**
    * Find the good way to construct the cart form
    *
    * @param  Request $request
    * @return CartAdd
    */
    private function getAddCartForm(Request $request)
    {
        /** @var CartAdd $cartAdd */
        if ($request->isMethod("post")) {
            $cartAdd = $this->createForm(FrontForm::CART_ADD);
        } else {
            $cartAdd = $this->createForm(
                FrontForm::CART_ADD,
                "form",
                array(),
                array(
                    'csrf_protection'   => false,
                )
            );
        }

        return $cartAdd;
    }

    /**
    * @throws PropelException
    */
    protected function afterModifyCart()
    {
        /* recalculate postage amount */
        $order = $this->getSession()->getOrder();
        if (null !== $order) {

            $deliveryModule = $order->getModuleRelatedByDeliveryModuleId();
            $deliveryAddress = AddressQuery::create()->findPk($order->getChoosenDeliveryAddress());

            if (null !== $deliveryModule && null !== $deliveryAddress) {
                $moduleInstance = $deliveryModule->getDeliveryModuleInstance($this->container);


                $orderEvent = new OrderEvent($order);

                try {
                    $deliveryPostageEvent = new DeliveryPostageEvent(
                        $moduleInstance,
                        $this->getSession()->getSessionCart($this->getDispatcher()),
                        $deliveryAddress
                    );

                    $this->getDispatcher()->dispatch(
                        TheliaEvents::MODULE_DELIVERY_GET_POSTAGE,
                        $deliveryPostageEvent
                    );

                    $postage = $deliveryPostageEvent->getPostage();

                    $orderEvent->setPostage($postage->getAmount());
                    $orderEvent->setPostageTax($postage->getAmountTax());
                    $orderEvent->setPostageTaxRuleTitle($postage->getTaxRuleTitle());

                    $this->getDispatcher()->dispatch(TheliaEvents::ORDER_SET_POSTAGE, $orderEvent);
                } catch (\Exception $ex) {
                    // The postage has been chosen, but changes in the cart causes an exception.
                    // Reset the postage data in the order
                    $orderEvent->setDeliveryModule(0);

                    $this->getDispatcher()->dispatch(TheliaEvents::ORDER_SET_DELIVERY_MODULE, $orderEvent);
                }
            }
        }
    }

    /**
     * Get cart postage
     */
    protected function getDelivery()
    {
        $request = $this->getRequest();
        $container = $this->getContainer();
        $cart = $request->getSession()->getSessionCart($this->getDispatcher());

        // Get the cart postage info
        /** @var CartPostageService $cartPostage*/
        $cartPostage = $this->getContainer()->get('frontCart.cart.postage.service');

        $delivery = $cartPostage->getPostage($request, $container);

        if (is_numeric($delivery['Postage']))
        {
            $delivery['PostageFormatted'] = MoneyFormat::getInstance($request)->formatByCurrency(($delivery['Postage']), 2,"."," ", $cart->getCurrencyId());
        }
        else
        {
            $delivery['PostageFormatted'] = $delivery['Postage'];
        }

        return $delivery;
    }

    /**
     * Get the Cart total price and include delivery cost
     */
    protected function getTotalIncludeDelivery($request, $cart, $delivery)
    {
        $postage = $delivery['Postage'];

        if (is_nan($postage) || null === $postage)
        {
            /** @var Cart $cart*/
            $total = MoneyFormat::getInstance($request)->formatByCurrency(($cart->getTaxedAmount($taxCountry = $this->container->get('thelia.taxEngine')->getDeliveryCountry())), 2,"."," ", $cart->getCurrencyId());

            return $total;
        }
        else
        {
            /** @var Cart $cart*/
            $total = MoneyFormat::getInstance($request)->formatByCurrency(($cart->getTaxedAmount($taxCountry = $this->container->get('thelia.taxEngine')->getDeliveryCountry())) + ($this->getDelivery()['Postage']), 2,"."," ", $cart->getCurrencyId());

            return $total;
        }
    }

    /**
     * Get consumed Coupon and return details
     */
    protected function getCoupon()
    {
        $session = $this->getRequest()->getSession();
        $consumedCoupons = $session->getConsumedCoupons();

        $coupons = [];
        foreach ($consumedCoupons as $consumedCoupon)
        {
            $couponDatas = CouponQuery::create()
                ->filterByCode($consumedCoupon)
                ->findOne();

            $coupon = ["Id"=>$couponDatas->getId(),
                       "Code"=>$couponDatas->getCode(),
                       "Title"=>$couponDatas->getTitle(),
                       "StartDate"=>$couponDatas->getStartDate()->getTimestamp(),
                       "ExpirationDate"=>$couponDatas->getExpirationDate()->getTimestamp(),
                       "Enabled"=>$couponDatas->getIsEnabled(),
                       "Cumulative"=>$couponDatas->getIsCumulative(),
                       "RemovePostage"=>$couponDatas->getIsRemovingPostage(),
                       "Discount"=>json_decode($couponDatas->getSerializedEffects())
                      ];

            array_push($coupons, $coupon);
        }

        if(empty($coupons))
        {
            $coupons = Translator::getInstance()->trans('No coupons',
                [],
                FrontCart::DOMAIN_NAME);
        }
        return $coupons;
    }

    /**
     *  Return an array for Cart
     */
    protected function createDataCart()
    {
        $request = $this->getRequest();
        $cart = $request->getSession()->getSessionCart($this->getDispatcher());

        $frontCartDataEvent = new FrontCartDataEvent($cart, $this->getCoupon(), $this->getDelivery());
        $this->dispatch(FrontCartDataEvent::CART_DATA, $frontCartDataEvent);

        $dataCart = ["CartId"=>$cart->getId(),
                "TotalPrice"=>MoneyFormat::getInstance($request)->formatByCurrency($cart->getTotalAmount(), 2,"."," ", $cart->getCurrencyId()),
                "TotalTaxedPrice"=>MoneyFormat::getInstance($request)->formatByCurrency($cart->getTaxedAmount($taxCountry = $this->container->get('thelia.taxEngine')->getDeliveryCountry()), 2,"."," ", $cart->getCurrencyId()),
                "TaxesAmount"=>MoneyFormat::getInstance($request)->formatByCurrency($cart->getTotalVAT($taxCountry = $this->container->get('thelia.taxEngine')->getDeliveryCountry()), 2,"."," ", $cart->getCurrencyId()),
                "Delivery"=>$this->getDelivery(),
                "TotalIncludeDelivery"=>$this->getTotalIncludeDelivery($request, $cart, $this->getDelivery()),
                "Discount"=>MoneyFormat::getInstance($request)->formatByCurrency($cart->getDiscount(), 2,"."," ", $cart->getCurrencyId()),
                "Coupon"=>$this->getCoupon()
            ];

        return $dataCart;
    }

    /**
    * Return an array of CartItems
    */
    protected function creatDataCartItems()
    {
        try{
            $request = $this->getRequest();
            $cart = $request->getSession()->getSessionCart($this->getDispatcher());
            $cartItems = $cart->getCartItems();
            $taxCountry = $this->container->get('thelia.taxEngine')->getDeliveryCountry();
            $locale = $request->getSession()->getLang()->getLocale();

            //--------------------------------------------------------------------------
            // Generate Json from cartItems
            $datas = [];
            foreach($cartItems as $cartItem)
            {
                $product = $cartItem->getProduct(null, $locale);

                // Get the product_image & Generate a new new one for cart
                /** @var ImageService $imageService */
                $imageService = $this->getContainer()->get('frontCart.image.service');

                $productImage = ProductImageQuery::create()
                    ->useProductSaleElementsProductImageQuery()
                    ->filterByProductSaleElementsId($cartItem->getProductSaleElementsId())
                    ->endUse()
                    ->findOne();

                if (null == $productImage)
                {
                    $productImage = ProductImageQuery::create()
                        ->filterByProductId($cartItem->getProductId())
                        ->findOne();
                }

                $productImage = $imageService->getImageData($productImage,'product');

                //--------------------------------------------------------------------------

                // Get the PSE Attributes
                $attributeCombinations = ProductSaleElementsQuery::create()
                    ->filterById($cartItem->getProductSaleElementsId())
                    ->findOne()
                    ->getAttributeCombinations()
                    ->getData();

                // For each Attributes, get the Title and the Value
                $attributesDatas = [];
                foreach ($attributeCombinations as $attributeCombination)
                {
                    /** @var AttributeCombination $attributeCombination */
                    $attributeTitle = AttributeI18nQuery::create()
                        ->filterById($attributeCombination->getAttributeId())
                        ->filterByLocale($locale)
                        ->findOne();

                    $attributeValue = AttributeAvI18nQuery::create()
                        ->filterById($attributeCombination->getAttributeAvId())
                        ->filterByLocale($locale)
                        ->findOne();


                    $attributesData = ["AttributeTitle"=>$attributeTitle->getTitle(),
                                       "AttributeValue"=>$attributeValue->getTitle()];

                    array_push($attributesDatas, $attributesData);
                }

                //--------------------------------------------------------------------------

                // Set Cart_Items values for the Json cart
                $data = ["Id"=>$cartItem->getId(),
                    "ProductName"=>$product->getTitle(),
                    "ProductRef"=>$product->getRef(),
                    "ProductId"=>$cartItem->getProductId(),
                    "ProductSaleElementsId"=>$cartItem->getProductSaleElementsId(),
                    "ProductSaleElementRef"=>$cartItem->getProductSaleElements()->getRef(),
                    "Attributes"=>$attributesDatas,
                    "Quantity"=>$cartItem->getQuantity(),
                    "Price"=> MoneyFormat::getInstance($request)->formatByCurrency($cartItem->getPrice(), 2,'.',' ', $cart->getCurrencyId()),
                    "TotalPrice"=>MoneyFormat::getInstance($request)->formatByCurrency(($cartItem->getPrice()*$cartItem->getQuantity()), 2,'.',' ', $cart->getCurrencyId()),
                    "TaxedPrice"=>MoneyFormat::getInstance($request)->formatByCurrency($cartItem->getTaxedPrice($taxCountry), 2,'.',' ', $cart->getCurrencyId()),
                    "TotalTaxedPrice"=>MoneyFormat::getInstance($request)->formatByCurrency(($cartItem->getTaxedPrice($taxCountry)*$cartItem->getQuantity()), 2,'.',' ', $cart->getCurrencyId()),
                    "Promo"=>$cartItem->getPromo(),
                    "PromoPrice"=>MoneyFormat::getInstance($request)->formatByCurrency($cartItem->getPromoPrice(), 2,'.',' ', $cart->getCurrencyId()),
                    "TotalPromoPrice"=>MoneyFormat::getInstance($request)->formatByCurrency(($cartItem->getPromoPrice()*$cartItem->getQuantity()), 2,'.',' ', $cart->getCurrencyId()),
                    "TaxedPromoPrice"=>MoneyFormat::getInstance($request)->formatByCurrency($cartItem->getTaxedPromoPrice($taxCountry), 2,'.',' ', $cart->getCurrencyId()),
                    "TotalTaxedPromoPrice"=>MoneyFormat::getInstance($request)->formatByCurrency(($cartItem->getTaxedPromoPrice($taxCountry)*$cartItem->getQuantity()), 2,'.',' ', $cart->getCurrencyId()),
                    "ProductUrl"=>$cartItem->getProduct()->getUrl(),
                    "productImage"=>$productImage
                ];

                    array_push($datas, $data);
            }

            if(empty($datas))
            {
                $datas = Translator::getInstance()->trans('Your cart is empty',
                    [],
                    FrontCart::DOMAIN_NAME);
            }

            return $datas;

        }
        catch (\Exception $e)
        {
            $error = $e->getMessage();

            $response = ["error"=> "400",
                "msg" => $error
            ];
            return new JsonResponse($response);
        }
    }

    /**
     *  Create an ResponseEvent 
     */
    protected function createResponseEvent($response)
    {
        $frontCartResponseEvent = new FrontCartResponseEvent($response);
        $this->dispatch(FrontCartResponseEvent::CART_RESPONSE, $frontCartResponseEvent);

    }

}

?>