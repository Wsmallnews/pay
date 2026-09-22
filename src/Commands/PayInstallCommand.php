<?php

namespace Wsmallnews\Pay\Commands;

use Wsmallnews\Support\Commands\PackageInstallCommand;

class PayInstallCommand extends PackageInstallCommand
{
    protected string $packageName = 'sn-pay';
}
