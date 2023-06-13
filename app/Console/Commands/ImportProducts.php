<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\ProductImportController;
class ImportProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-products {param1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import products from CSV';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $param1 = $this->argument('param1');
        if ($param1 == 'dry-run' ||  $param1 == 'save-run'){
            $controller = new ProductImportController();
            $message = $controller->importProducts($param1);
            $this->info($message);
        }else{
            $this->info(" \n Please write correct command ----- Available command is : \n \n php artisan app:import-products dry-run (Do not effect DB) \n php artisan app:import-products save-run (It will effect DB)");
        }
        
    }
}
