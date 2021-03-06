<?php

namespace AlibabaCloud\Objectdet\V20191230;

use AlibabaCloud\Client\Resolver\ApiResolver;

/**
 * @method DetectMainBody detectMainBody(array $options = [])
 * @method DetectVehicle detectVehicle(array $options = [])
 */
class ObjectdetApiResolver extends ApiResolver
{
}

class Rpc extends \AlibabaCloud\Client\Resolver\Rpc
{
    /** @var string */
    public $product = 'objectdet';

    /** @var string */
    public $version = '2019-12-30';

    /** @var string */
    public $method = 'POST';

    /** @var string */
    public $serviceCode = 'objectdet';
}

/**
 * @method string getImageURL()
 * @method $this withImageURL($value)
 */
class DetectMainBody extends Rpc
{
}

/**
 * @method string getImageType()
 * @method string getImageURL()
 */
class DetectVehicle extends Rpc
{

    /**
     * @param string $value
     *
     * @return $this
     */
    public function withImageType($value)
    {
        $this->data['ImageType'] = $value;
        $this->options['form_params']['ImageType'] = $value;

        return $this;
    }

    /**
     * @param string $value
     *
     * @return $this
     */
    public function withImageURL($value)
    {
        $this->data['ImageURL'] = $value;
        $this->options['form_params']['ImageURL'] = $value;

        return $this;
    }
}
