<?php

namespace App\DTO;

class OrderDTO
{
    private $customer;
    private $warehouseId;
    private $items;

    public function __construct($customer, $warehouseId, $items)
    {
        $this->customer = $customer;
        $this->warehouseId = $warehouseId;
        $this->items = $items;
    }

    public function getCustomer()
    {
        return $this->customer;
    }

    public function getWarehouseId()
    {
        return $this->warehouseId;
    }

    public function getItems()
    {
        return $this->items;
    }
}
