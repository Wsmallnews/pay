<?php

namespace Wsmallnews\Pay\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Wsmallnews\Pay\Pay
 */
class Pay extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Wsmallnews\Pay\Pay::class;
    }
}
