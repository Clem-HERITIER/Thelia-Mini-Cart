<?php

namespace FrontCart\Event;

use Thelia\Core\Event\ActionEvent;

class FrontCartDataEvent extends ActionEvent
{
    const CART_DATA = "action.create.cart.data";

    protected $cart;
    protected $coupon;
    protected $delivery;

    public function __construct($cart, $coupon, $delivery)
    {
        $this->cart = $cart;
        $this->coupon = $coupon;
        $this->delivery = $delivery;
    }

    public function getCart()
    {
        return $this->cart;
    }

    public function setCart($cart)
    {
        $this->cart = $cart;

        return $this;
    }

    public function getCoupon()
    {
        return  $this->coupon;
    }

    public function setCoupon($coupon)
    {
        $this->coupon = $coupon;

        return $this;
    }

    public function getDelivery()
    {
        return $this->delivery;
    }

    public function setDelivery($delivery)
    {
        $this->delivery = $delivery;

        return $this;
    }

}
