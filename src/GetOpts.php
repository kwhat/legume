<?php

namespace Legume;

use GetOpt\GetOpt;

class GetOpts extends GetOpt
{
    public function __construct($options, array $settings)
    {
        parent::__construct($options, $settings);
    }
}
