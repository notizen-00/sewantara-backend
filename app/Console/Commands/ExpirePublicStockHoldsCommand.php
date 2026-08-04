<?php

namespace App\Console\Commands;

use App\Modules\PublicApi\Application\ExpirePublicStockHolds;
use Illuminate\Console\Command;

class ExpirePublicStockHoldsCommand extends Command
{
    protected $signature = 'public:expire-stock-holds {--limit=100}';

    protected $description = 'Release expired public booking stock holds for the initialized tenant';

    public function handle(ExpirePublicStockHolds $holds): int
    {
        if (! tenancy()->initialized) {
            $this->error('Perintah harus dijalankan di dalam konteks tenant.');

            return self::FAILURE;
        }

        $expired = $holds->execute((int) $this->option('limit'));
        $this->info("{$expired} stock hold kedaluwarsa diproses.");

        return self::SUCCESS;
    }
}
