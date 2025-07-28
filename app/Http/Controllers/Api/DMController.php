<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class DMController extends Controller
{
    /**
     * Check products from Excel data and validate them against database
     */
    public function checkProducts(Request $request)
    {
        try {
            $data = $request->all();
            $IDWarehouse = $data['IDWarehouse'];
            $products = $data['products']; // Array of products from Excel

            // Add debugging to see what we're receiving
            Log::info('Raw request data received', [
                'data_keys' => array_keys($data),
                'products_count' => count($products ?? []),
                'sample_product' => $products[0] ?? null
            ]);

            // Check for encoding issues in raw data
            $rawJson = $request->getContent();
            if (!mb_check_encoding($rawJson, 'UTF-8')) {
                Log::warning('Request content is not valid UTF-8');
                throw new Exception('Request data contains invalid UTF-8 characters');
            }

            // Clean and validate UTF-8 encoding for all product data
            $products = $this->cleanUtf8Data($products);

            $response = [
                'status' => 'success',
                'existing_products' => [],
                'new_products' => [],
                'missing_units' => [],
                'errors' => [],
                'warnings' => []
            ];

            // Get or create default product group for warehouse
            $defaultGroupId = $this->getOrCreateDefaultGroup($IDWarehouse);

            foreach ($products as $index => $product) {
                // Skip empty rows
                if (empty($product['Nazwa']) && empty($product['EAN']) && empty($product['SKU'])) {
                    continue;
                }

                $productCheck = $this->validateProduct($product, $IDWarehouse, $index + 1);

                if (!empty($productCheck['errors'])) {
                    $response['errors'] = array_merge($response['errors'], $productCheck['errors']);
                }

                if (!empty($productCheck['warnings'])) {
                    $response['warnings'] = array_merge($response['warnings'], $productCheck['warnings']);
                }

                if ($productCheck['exists']) {
                    $response['existing_products'][] = $productCheck;
                } else {
                    $productCheck['IDGrupyTowarow'] = $defaultGroupId;
                    $response['new_products'][] = $productCheck;
                }

                // Check if unit exists, if not add to missing units
                if (!empty($product['jednostka'])) {
                    $unitExists = DB::table('JednostkaMiary')
                        ->where('Nazwa', $product['jednostka'])
                        ->exists();

                    if (!$unitExists && !in_array($product['jednostka'], $response['missing_units'])) {
                        $response['missing_units'][] = $product['jednostka'];
                    }
                }
            }

            return response()->json($response, 200, [
                'Content-Type' => 'application/json; charset=utf-8'
            ]);
        } catch (Exception $e) {
            Log::error('Error in checkProducts: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Błąd podczas sprawdzania produktów: ' . $e->getMessage()
            ], 500, [
                'Content-Type' => 'application/json; charset=utf-8'
            ]);
        }
    }

    /**
     * Create DM document with products
     */
    public function createDocument(Request $request)
    {
        try {
            $data = $request->all();
            $IDWarehouse = $data['IDWarehouse'];
            $products = $data['products'];
            $userId = $data['user_id'] ?? 1;

            // Clean and validate UTF-8 encoding for all product data
            $products = $this->cleanUtf8Data($products);

            DB::beginTransaction();

            // Create missing units first
            $this->createMissingUnits($data['missing_units'] ?? []);

            // Get or create default product group
            $defaultGroupId = $this->getOrCreateDefaultGroup($IDWarehouse);

            // Create new products
            $createdProducts = [];
            foreach ($data['new_products'] ?? [] as $productData) {
                $createdProduct = $this->createProduct($productData, $IDWarehouse, $defaultGroupId);
                $createdProducts[$productData['original_index']] = $createdProduct;
            }

            // Create DM document
            $documentNumber = $this->generateDocumentNumber($IDWarehouse);

            $documentId = DB::table('RuchMagazynowy')->insertGetId([
                'Data' => Carbon::now(),
                'Uwagi' => 'Dostawa do magazynu - import z Excel',
                'IDRodzajuRuchuMagazynowego' => 200, // DM document type
                'IDMagazynu' => $IDWarehouse,
                'NrDokumentu' => $documentNumber,
                'IDUzytkownika' => $userId,
                'Utworzono' => Carbon::now(),
                'Zmodyfikowano' => Carbon::now(),
                'Operator' => 0
            ]);

            // Add products to document
            foreach ($products as $index => $product) {
                // Skip empty rows
                if (empty($product['Nazwa']) && empty($product['EAN']) && empty($product['SKU'])) {
                    continue;
                }

                $productId = null;

                // Find product ID
                if (isset($createdProducts[$index])) {
                    $productId = $createdProducts[$index]['IDTowaru'];
                } else {
                    // Find existing product
                    $existingProduct = $this->findProductByEanOrSku($product, $IDWarehouse);
                    if ($existingProduct) {
                        $productId = $existingProduct->IDTowaru;
                    }
                }

                if ($productId) {
                    DB::table('ElementRuchuMagazynowego')->insert([
                        'Ilosc' => floatval($product['Ilość'] ?? 0),
                        'Uwagi' => $product['Informacje dodatkowe'] ?? '',
                        'CenaJednostkowa' => floatval($product['Cena'] ?? 0),
                        'IDRuchuMagazynowego' => $documentId,
                        'IDTowaru' => $productId,
                        'Utworzono' => Carbon::now(),
                        'Zmodyfikowano' => Carbon::now(),
                        'Uzytkownik' => $userId
                    ]);
                }
            }

            DB::commit();

            Log::info('DM Document created successfully', [
                'document_id' => $documentId,
                'document_number' => $documentNumber,
                'warehouse_id' => $IDWarehouse,
                'products_count' => count($products)
            ]);

            return response()->json([
                'status' => 'success',
                'document_id' => $documentId,
                'document_number' => $documentNumber,
                'message' => 'Dokument DM został utworzony pomyślnie'
            ], 200, [
                'Content-Type' => 'application/json; charset=utf-8'
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error in createDocument: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Błąd podczas tworzenia dokumentu: ' . $e->getMessage()
            ], 500, [
                'Content-Type' => 'application/json; charset=utf-8'
            ]);
        }
    }

    /**
     * Validate individual product
     */
    private function validateProduct($product, $IDWarehouse, $rowNumber)
    {
        $result = [
            'original_index' => $rowNumber - 1,
            'row_number' => $rowNumber,
            'product_data' => $product,
            'exists' => false,
            'product_id' => null,
            'errors' => [],
            'warnings' => []
        ];

        // Validate required fields
        if (empty($product['Nazwa'])) {
            $result['errors'][] = "Wiersz {$rowNumber}: Brak nazwy produktu";
        }

        if (empty($product['EAN']) && empty($product['SKU'])) {
            $result['errors'][] = "Wiersz {$rowNumber}: Brak EAN i SKU - wymagany przynajmniej jeden";
        }

        if (empty($product['Ilość']) || !is_numeric($product['Ilość'])) {
            $result['errors'][] = "Wiersz {$rowNumber}: Nieprawidłowa ilość";
        }

        if (empty($product['Cena']) || !is_numeric($product['Cena'])) {
            $result['errors'][] = "Wiersz {$rowNumber}: Nieprawidłowa cena";
        }

        // Check if product exists
        $existingProduct = $this->findProductByEanOrSku($product, $IDWarehouse);
        if ($existingProduct) {
            $result['exists'] = true;
            $result['product_id'] = $existingProduct->IDTowaru;
            $result['existing_product'] = $existingProduct;
        }

        return $result;
    }

    /**
     * Find product by EAN or SKU
     */
    private function findProductByEanOrSku($product, $IDWarehouse)
    {
        $query = DB::table('Towar')
            ->where('IDMagazynu', $IDWarehouse);

        // Check by EAN first (primary key)
        if (!empty($product['EAN'])) {
            $byEan = $query->where('KodKreskowy', $product['EAN'])->first();
            if ($byEan) {
                return $byEan;
            }
        }

        // Check by SKU
        if (!empty($product['SKU'])) {
            return $query->where('_TowarTempString1', $product['SKU'])->first();
        }

        return null;
    }

    /**
     * Create new product
     */
    private function createProduct($productData, $IDWarehouse, $defaultGroupId)
    {
        $product = $productData['product_data'];

        // Get unit ID
        $unitId = null;
        if (!empty($product['jednostka'])) {
            $unit = DB::table('JednostkaMiary')
                ->where('Nazwa', $product['jednostka'])
                ->first();
            if ($unit) {
                $unitId = $unit->IDJednostkiMiary;
            }
        }

        $productId = DB::table('Towar')->insertGetId([
            'Nazwa' => $product['Nazwa'],
            'KodKreskowy' => $product['EAN'] ?? '',
            'IDJednostkiMiary' => $unitId,
            'IDMagazynu' => $IDWarehouse,
            'IDGrupyTowarow' => $defaultGroupId,
            'CenaZakupu' => floatval($product['Cena'] ?? 0),
            'CenaSprzedazy' => floatval($product['Cena'] ?? 0),
            'StanMinimalny' => 1,
            'StanPoczatkowy' => 0,
            'CenaPoczatkowa' => 0,
            'Usluga' => 0,
            'Archiwalny' => 0,
            'Utworzono' => Carbon::now(),
            'Zmodyfikowano' => Carbon::now(),
            'Produkt' => 1,
            '_TowarTempString1' => $product['SKU'] ?? '', // SKU
            '_TowarTempDecimal1' => floatval($product['Waga (kg)'] ?? 0), // Weight
            '_TowarTempDecimal3' => floatval($product['Długość (cm)'] ?? 0), // Length
            '_TowarTempDecimal4' => floatval($product['Szerokość (cm)'] ?? 0), // Width
            '_TowarTempDecimal5' => floatval($product['Wysokość (cm)'] ?? 0), // Height
        ]);

        // Calculate volume (m3)
        $length = floatval($product['Długość (cm)'] ?? 0);
        $width = floatval($product['Szerokość (cm)'] ?? 0);
        $height = floatval($product['Wysokość (cm)'] ?? 0);

        if ($length > 0 && $width > 0 && $height > 0) {
            $volume = ($length * $width * $height) / 1000000; // Convert cm³ to m³
            DB::table('Towar')
                ->where('IDTowaru', $productId)
                ->update(['_TowarTempDecimal2' => $volume]);
        }

        return [
            'IDTowaru' => $productId,
            'product_data' => $product
        ];
    }

    /**
     * Get or create default product group
     */
    private function getOrCreateDefaultGroup($IDWarehouse)
    {
        $defaultGroup = DB::table('GrupyTowarow')
            ->where('IDMagazynu', $IDWarehouse)
            ->where('Nazwa', 'default')
            ->first();

        if (!$defaultGroup) {
            $groupId = DB::table('GrupyTowarow')->insertGetId([
                'Nazwa' => 'default',
                'IDMagazynu' => $IDWarehouse,
                'Utworzono' => Carbon::now(),
                'Zmodyfikowano' => Carbon::now()
            ]);
            return $groupId;
        }

        return $defaultGroup->IDGrupyTowarow;
    }

    /**
     * Create missing units
     */
    private function createMissingUnits($units)
    {
        foreach ($units as $unitName) {
            $exists = DB::table('JednostkaMiary')
                ->where('Nazwa', $unitName)
                ->exists();

            if (!$exists) {
                DB::table('JednostkaMiary')->insert([
                    'Nazwa' => $unitName,
                    'Utworzono' => Carbon::now(),
                    'Zmodyfikowano' => Carbon::now(),
                    'CzyDomyslna' => 0,
                    'IsWholeNumber' => 0
                ]);
            }
        }
    }

    /**
     * Generate document number
     */
    private function generateDocumentNumber($IDWarehouse)
    {
        $symbol = DB::table('Magazyn')
            ->where('IDMagazynu', $IDWarehouse)
            ->value('Symbol');

        $year = Carbon::now()->format('y');
        $pattern = 'DM%/' . $year . ' - ' . $symbol;
        $patternIndex = 2; // Length of "DM"
        $patternToEndLen = strlen($symbol) + 6; // 6 characters: " - " + year (2 characters) + "/"

        $res = DB::table('RuchMagazynowy')
            ->select(DB::raw('MAX(CAST(SUBSTRING(NrDokumentu, ' . ($patternIndex + 1) . ', LEN(NrDokumentu) - ' . ($patternToEndLen + $patternIndex) . ') AS INT)) as max_number'))
            ->whereRaw('RTRIM(NrDokumentu) LIKE ?', [$pattern])
            ->whereRaw('ISNUMERIC(SUBSTRING(NrDokumentu, ' . ($patternIndex + 1) . ', LEN(NrDokumentu) - ' . ($patternToEndLen + $patternIndex) . ')) <> 0')
            ->value('max_number');

        if ($res === null) {
            return str_replace('%', '1', $pattern);
        }
        return str_replace('%', $res + 1, $pattern);
    }

    /**
     * Clean UTF-8 data and handle encoding issues
     */
    private function cleanUtf8Data($data)
    {
        if (is_array($data)) {
            $cleaned = [];
            foreach ($data as $key => $value) {
                $cleanedKey = $this->cleanUtf8String($key);
                $cleanedValue = $this->cleanUtf8Data($value);
                $cleaned[$cleanedKey] = $cleanedValue;
            }
            return $cleaned;
        } elseif (is_string($data)) {
            return $this->cleanUtf8String($data);
        } else {
            return $data;
        }
    }

    /**
     * Clean individual UTF-8 string
     */
    private function cleanUtf8String($string)
    {
        if (!is_string($string)) {
            return $string;
        }

        // More aggressive UTF-8 cleaning to prevent malformed errors
        try {
            // First try to detect and fix encoding
            if (!mb_check_encoding($string, 'UTF-8')) {
                Log::warning('Non-UTF-8 string detected, attempting conversion', ['original' => $string]);

                // Try to convert from common encodings
                $encodings = ['Windows-1252', 'ISO-8859-1', 'CP1252', 'UTF-8'];
                foreach ($encodings as $encoding) {
                    $converted = @iconv($encoding, 'UTF-8//IGNORE', $string);
                    if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
                        $string = $converted;
                        break;
                    }
                }
            }

            // Remove or replace invalid UTF-8 sequences
            $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');

            // Remove null bytes and other problematic characters
            $string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $string);

            // Remove BOM and other problematic characters
            $string = str_replace(["\0", "\x00", "\xEF\xBB\xBF"], '', $string);

            // Trim whitespace
            $string = trim($string);

            // Final validation
            if (!mb_check_encoding($string, 'UTF-8')) {
                // Last resort: remove all non-printable characters
                $string = preg_replace('/[^\x20-\x7E\x{00A0}-\x{FFFF}]/u', '', $string);

                // If still invalid, return empty string
                if (!mb_check_encoding($string, 'UTF-8')) {
                    Log::warning('Could not fix UTF-8 string, returning empty', ['original' => $string]);
                    return '';
                }
            }

            return $string;
        } catch (Exception $e) {
            Log::warning('Exception during UTF-8 cleaning', ['error' => $e->getMessage(), 'string' => $string]);
            // Return safe fallback
            return preg_replace('/[^\x20-\x7E]/', '', $string);
        }
    }
}
