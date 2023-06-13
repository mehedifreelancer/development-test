<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use League\Csv\Reader;
use Carbon\Carbon;
use DB;

class ProductImportController extends Controller
{
    private $message;
    private $processedCount = 0;
    private $skippedCount = 0;
    private $failedImports = [];
    private $successfullyImported = 0;
    private $commandMode;


    public function importProducts($command_mode)
    {
        $this->commandMode = $command_mode;
        $csvFile = 'public/files/stock.csv';

        if (!file_exists($csvFile) || !filesize($csvFile)) {
            // CSV file does not exist or is empty
            return "CSV file not found or empty.";
        }

        // Validate CSV file columns
        $requiredColumns = ['Product Name', 'Product Description', 'Product Code'];
        $csvColumns = $this->getCsvColumns($csvFile);

        if (count(array_diff($requiredColumns, $csvColumns)) > 0) {
            // Required columns do not exist in the CSV file
            return "Required columns not found in the CSV file.";
        }

        // Read and process the CSV file
        $progress = 0;
        $parsingProgress = 0;
        $totalProducts = $this->getTotalProducts($csvFile);

        $csv = Reader::createFromPath($csvFile, 'r');
        $csv->setHeaderOffset(0);

        echo "\nReading all CSV file's data --------------------\n";

        foreach ($csv as $row) {
            // Process each product row
            $productData = $row;
            $parsingProgress++;
            $this->displayProgress($parsingProgress, $totalProducts, $productData['Product Name'], $productData['Product Code']);
        }

        echo "\nFiltering data to import based on business logic  --------------------\n";

        foreach ($csv as $row) {
            // Process each product row
            $productData = $row;

            // Perform additional validation or data manipulation if needed
            if ($this->shouldSkipProduct($productData)) {
                // Skip the product if it meets the skipping criteria
                $this->skippedCount++;
                continue;
            }

            if (!$this->insertProduct($productData)) {
                // Failed to insert the product
                $this->failedImports[] = $productData;
            } 

            $progress++;
            $this->displayProgress($progress, $totalProducts, $productData['Product Name'], $productData['Product Code']);
            $this->processedCount++;
        }

        // Reprocess the failed imports
        $this->reprocessFailedImports();

        $this->printReport();

        return "Import completed. Processed {$this->processedCount} products. Skipped {$this->skippedCount} products.";
    }

    private function getCsvColumns($csvFile)
    {
        $csv = Reader::createFromPath($csvFile, 'r');
        $csv->setHeaderOffset(0);

        return $csv->getHeader();
    }

    private function getTotalProducts($csvFile)
    {
        $csv = Reader::createFromPath($csvFile, 'r');
        $csv->setHeaderOffset(0);

        // Subtract 1 to exclude the header row from the count
        return iterator_count($csv) - 1;
    }

    private function insertProduct($data)
    {
        // Perform the database insertion logic here using query builder
        try {
            if ($this->commandMode === "dry-run") {
                // Do not change to DB if given dry run with command
                $this->successfullyImported++;

                return true;
            } else {
                $discontinuedDate = null;
                if ($data['Discontinued'] === 'yes') {
                    $discontinuedDate = Carbon::now();
                }

                $exists = DB::table('tbl_product_data')->where('product_code', $data['Product Code'])->exists();

                if (!$exists && !$this->shouldSkipProduct($data)) {
                    $inserted = DB::table('tbl_product_data')->insert([
                        'product_name' => $data['Product Name'],
                        'product_code' => $data['Product Code'],
                        'product_desc' => $data['Product Description'],
                        'discontinued' => $discontinuedDate,
                        'price' => $data['Cost in GBP'],
                        'stock' => $data['Stock'],
                        'added' => Carbon::now(),
                        'created_at' => Carbon::now()
                    ]);

                    if ($inserted) {
                        $this->successfullyImported++;
                    }
                }

                return true;
            }
        } catch (Exception $e) {
            $this->failedImports[] = [
                'Product Name' => $data['Product Name'],
                'Product Code' => $data['Product Code'],
                'Error' => $e->getMessage()
            ];

            return false;
        }
    }

    private function displayProgress($current, $total, $productName, $productCode)
    {
        // Display the progress information or update progress bar
        $total = $total + 1;
        echo "Processing product: $productCode - $productName [$current/$total]\n";
    }

    private function shouldSkipProduct($productData)
    {
        $productName = $productData['Product Name'];
        $stock = intval($productData['Stock']);
        $price = $productData['Cost in GBP'];
        $discontinued = $productData['Discontinued'];

        // Skip if any of the required fields are empty
        if (empty($productName) || empty($stock) || empty($price)) {
            return true;
        }

        if (strpos($price, '$') !== false) {
            // The string contains the dollar sign
            return true;
        }

        $exchangeRate = 1.39; // Example exchange rate GBP to USD
        $amountInGBP = intval($price); // Amount in GBP you want to convert

        $price = $amountInGBP * $exchangeRate;

        // Skip if the price is less than $5 and stock is less than 10
        if ($price < 5 && $stock < 10) {
            return true;
        }

        // Skip if the price is over $1000
        if ($price > 1000) {
            return true;
        }

        // Set discontinued date as the current date if the product is marked as discontinued
        if ($discontinued) {
            $productData['DiscontinuedDate'] = Carbon::now()->toDateString();
        }

        return false;
    }

    private function reprocessFailedImports()
    {
        echo "\nReprocessing failed imports...\n";

        foreach ($this->failedImports as $failedImport) {
            if ($this->insertProduct($failedImport)) {
                $this->successfullyImported++;
            }
        }
    }

    private function printReport()
    {
        echo "\n*****************Import Report***********************\n";
        echo "Processed: {$this->processedCount} products\n";
        echo "Skipped: {$this->skippedCount} products\n";
        echo "Successfully Imported: {$this->successfullyImported} products\n";
        echo "Failed Imports: " . count($this->failedImports) . " products\n";

        if (!empty($this->failedImports)) {
            echo "Failed Import Details:\n";
            foreach ($this->failedImports as $failedImport) {
                echo "- Product Name: {$failedImport['Product Name']}, Product Code: {$failedImport['Product Code']}\n";
            }
        }

        echo "\n*********************************************************\n";
    }
}
