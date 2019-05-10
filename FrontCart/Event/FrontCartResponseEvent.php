<?php

namespace FrontCart\Event;

use Thelia\Core\Event\ActionEvent;

class FrontCartResponseEvent extends ActionEvent
{
    const CART_RESPONSE = "action.return.cart.response";

    protected $response;

    public function __construct($response)
    {
        $this->response = $response;

    }

    public function getResponse()
    {
        return $this->response;
    }

    public function setResponse($response)
    {
        $this->response = $response;

        return $this;
    }

}
