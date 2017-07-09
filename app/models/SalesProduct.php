<?php

class SalesProduct extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $salesProductID;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $productID;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $salesID;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $status;

    /**
     *
     * @var string
     * @Column(type="string", nullable=false)
     */
    public $createdAt;

    /**
     *
     * @var string
     * @Column(type="string", nullable=false)
     */
    public $updatedAt;

    /**
     * Returns table name mapped in the model.
     *
     * @return string
     */
    public function getSource()
    {
        return 'sales_product';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return SalesProduct[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return SalesProduct
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
