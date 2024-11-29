<?php

namespace App\DTO;

class ProductMovementDTO
{
    public $product_id;
    public $warehouse_id;
    public $quantity;
    public $type;

    public function __construct($product_id, $warehouse_id, $quantity, $type)
    {
        $this->product_id = $product_id;
        $this->warehouse_id = $warehouse_id;
        $this->quantity = $quantity;
        $this->type = $type;
    }

    // Вы можете добавить дополнительные методы для валидации данных, если нужно.
}
