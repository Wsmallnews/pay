<?php

namespace Wsmallnews\Pay\Commands;

use Illuminate\Console\Command;

class PayCommand extends Command
{
    public $signature = 'pay';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
