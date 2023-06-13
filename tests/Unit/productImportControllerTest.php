<?php


namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\ProductImportController;
use Illuminate\Http\Request;
use League\Csv\Reader;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;
use DB;


class productImportControllerTest extends TestCase
{
  
    public function testCsvFileExistsAndNotEmpty()
    {
        $this->assertTrue(file_exists(public_path('files/stock.csv')));
        $this->assertGreaterThan(0, filesize(public_path('files/stock.csv')));
    }




public function testRequiredColumnsExist()
{
    $csv = \League\Csv\Reader::createFromPath(public_path('files/stock.csv'), 'r');
    $csv->setHeaderOffset(0);
    $csvColumns = $csv->getHeader();

    $requiredColumns = ['Product Name', 'Product Description', 'Product Code'];
    foreach ($requiredColumns as $column) {
        $this->assertContains($column, $csvColumns);
    }
}

public function testProductImport()
{
    // Run the import command with dry-run option
    Artisan::call('app:import-products save-run');

    // Assert the expected output or behavior
    $output = Artisan::output();
    $this->assertStringContainsString('Import completed. Processed 20 products. Skipped 9 products.', $output);
}

}