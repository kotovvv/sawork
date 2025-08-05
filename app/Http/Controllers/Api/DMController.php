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

            // Check if first row contains headers or data
            $hasHeaders = $this->checkIfFirstRowIsHeaders($products);
            $startIndex = $hasHeaders ? 1 : 0; // Start from index 1 if headers present, 0 if not

            foreach ($products as $index => $product) {
                // Skip header row if present
                if ($index < $startIndex) {
                    continue;
                }

                // Skip empty rows
                if (empty(trim($product['Nazwa'])) && empty(trim($product['EAN'])) && empty(trim($product['jednostka']))) {
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

                // Note: jednostka validation is now handled in validateProduct method
                // Only towar, karton, paleta are allowed - no new units created
            }


            return response()->json($response);
        } catch (Exception $e) {
            Log::error('Error in checkProducts: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Błąd podczas sprawdzania produktów: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create DM document with products
     */
    public function createDocument(Request $request)
    {
        $userId = $request->user()->IDUzytkownika ?? 1; // Default to 1 if not authenticated
        try {
            $data = $request->all();
            $IDWarehouse = $data['IDWarehouse'];
            $products = $data['products'];
            $tranzit_warehouse = $data['tranzit_warehouse'] ?? 0;
            $numerDokumentu = $data['numer_dokumentu'] ?? '';
            $uwagi_dokumentu = $data['uwagi_dokumentu'] ?? '';
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

            DB::table('RuchMagazynowy')->insert([
                'Data' => Carbon::now(),
                'Uwagi' => $tranzit_warehouse == 1 ? 'tranzit ' . $numerDokumentu . ' Uwagi: ' . $uwagi_dokumentu : 'Dostawa do magazynu - ' . $numerDokumentu . ' Uwagi: ' . $uwagi_dokumentu,
                'IDRodzajuRuchuMagazynowego' => 200, // DM document type
                'IDMagazynu' => $IDWarehouse,
                'NrDokumentu' => $documentNumber,
                'IDUzytkownika' => $userId,
                'Utworzono' => Carbon::now(),
                'Zmodyfikowano' => Carbon::now(),
                'IDCompany' => 1,
                'IDRodzajuTransportu' => 0,
                'Operator' => 0,
                '_RuchMagazynowyTempBool1' => $tranzit_warehouse == 1 ? 1 : 0, // Set to 1 if tranzit warehouse, 0 otherwise
                '_RuchMagazynowyTempString8' => $numerDokumentu
            ]);
            $documentId = DB::table('RuchMagazynowy')
                ->where('NrDokumentu', $documentNumber)
                ->value('IDRuchuMagazynowego');
            if (!$documentId) {
                throw new Exception("Nie udało się utworzyć dokumentu DM");
            }

            // Check if first row contains headers or data
            $hasHeaders = $this->checkIfFirstRowIsHeaders($products);
            $startIndex = $hasHeaders ? 1 : 0; // Start from index 1 if headers present, 0 if not

            // Add products to document
            foreach ($products as $index => $product) {
                // Skip header row if present
                if ($index < $startIndex) {
                    continue;
                }

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
                    $insertData = [
                        'Ilosc' => floatval($product['Ilość'] ?? 0),
                        'Uwagi' => $product['Informacje dodatkowe'] ?? '',
                        'CenaJednostkowa' => floatval($product['Cena'] ?? 0),
                        'IDRuchuMagazynowego' => $documentId,
                        'IDTowaru' => $productId,
                        'Utworzono' => Carbon::now(),
                        'Zmodyfikowano' => Carbon::now(),
                        'Uzytkownik' => $userId,
                    ];
                    //TODO: поля показать везде
                    if ($tranzit_warehouse == 0) {
                        $insertData['NumerSerii'] = json_encode([
                            'k' => $product['Numer kartonu'] ?? '',
                            'p' => $product['Numer palety'] ?? ''
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }

                    DB::table('ElementRuchuMagazynowego')->insert($insertData);
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
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error in createDocument: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Błąd podczas tworzenia dokumentu: ' . $e->getMessage()
            ], 500);
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

        if (empty($product['EAN'])) {
            $result['errors'][] = "Wiersz {$rowNumber}: Brak EAN - pole obowiązkowe";
        }

        if (empty($product['jednostka'])) {
            $result['errors'][] = "Wiersz {$rowNumber}: Brak jednostki miary - pole obowiązkowe";
        } else {
            // Validate that jednostka is one of allowed values
            $allowedUnits = ['towar', 'karton', 'paleta'];
            if (!in_array(strtolower(trim($product['jednostka'])), $allowedUnits)) {
                $result['errors'][] = "Wiersz {$rowNumber}: Nieprawidłowa jednostka '{$product['jednostka']}'. Dozwolone: towar, karton, paleta";
            }
        }

        if (empty($product['Ilość'])) {
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
            ->select('IDTowaru', 'Nazwa', 'KodKreskowy', '_TowarTempString1', '_TowarTempDecimal1', '_TowarTempDecimal3', '_TowarTempDecimal4', '_TowarTempDecimal5')
            ->where('IDMagazynu', $IDWarehouse);

        // Check by EAN first (primary key)
        if (!empty($product['EAN'])) {
            $byEan = $query->where('KodKreskowy', $product['EAN'])->first();
            if ($byEan) {
                return $byEan;
            }
        }

        return null;
    }

    /**
     * Create new product
     */
    private function createProduct($productData, $IDWarehouse, $defaultGroupId)
    {
        $product = $productData['product_data'];

        // Check if product with this EAN already exists
        $existingProductByEan = DB::table('Towar')
            ->where('KodKreskowy', trim($product['EAN']))
            ->where('IDMagazynu', $IDWarehouse)
            ->first();

        if ($existingProductByEan) {
            // Return existing product instead of throwing error
            return [
                'IDTowaru' => $existingProductByEan->IDTowaru,
                'product_data' => $product,
                'existing' => true
            ];
        }

        // Get unit ID
        $unitId = null;
        if (!empty($product['jednostka'])) {
            $unit = DB::table('JednostkaMiary')
                ->where('Nazwa', $product['jednostka'])
                ->first();
            if ($unit) {
                $unitId = $unit->IDJednostkiMiary;
            }
        } else {
            throw new Exception("Brak jednostki miary dla produktu: {$product['Nazwa']}");
        }


        DB::table('Towar')->insert([
            'Nazwa' => $product['Nazwa'],
            'KodKreskowy' => trim($product['EAN']),
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
            'Uwagi' => $product['Informacje dodatkowe'] ?? '',
        ]);
        $productId = DB::table('Towar')
            ->where('KodKreskowy', $product['EAN'])
            ->where('IDMagazynu', $IDWarehouse)
            ->value('IDTowaru');
        if (!$productId) {
            throw new Exception("Nie udało się utworzyć produktu: {$product['Nazwa']} (EAN: {$product['EAN']})");
        }

        // Handle volume (m3) - priority: direct m3 value, then calculated from dimensions
        $volumeToSet = null;

        // First check if m3 is directly provided
        if (!empty($product['m3']) && is_numeric($product['m3'])) {
            $volumeToSet = floatval($product['m3']);
        } else {
            // Calculate volume from dimensions if m3 is not provided
            $length = floatval($product['Długość (cm)'] ?? 0);
            $width = floatval($product['Szerokość (cm)'] ?? 0);
            $height = floatval($product['Wysokość (cm)'] ?? 0);

            if ($length > 0 && $width > 0 && $height > 0) {
                $volumeToSet = ($length * $width * $height) / 1000000; // Convert cm³ to m³
            }
        }

        // Update volume if we have a value to set
        if ($volumeToSet !== null) {
            DB::table('Towar')
                ->where('IDTowaru', $productId)
                ->where('IDMagazynu', $IDWarehouse)
                ->update(['_TowarTempDecimal2' => number_format($volumeToSet, 6, '.', '')]);
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
            DB::table('GrupyTowarow')->insert([
                'Nazwa' => 'default',
                'IDMagazynu' => $IDWarehouse,
                'Utworzono' => Carbon::now(),
                'Zmodyfikowano' => Carbon::now()
            ]);
            $groupId = DB::table('GrupyTowarow')
                ->where('IDMagazynu', $IDWarehouse)
                ->where('Nazwa', 'default')
                ->value('IDGrupyTowarow');
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
     * Check if first row contains headers or actual data
     */
    private function checkIfFirstRowIsHeaders($products)
    {
        if (empty($products) || !isset($products[0])) {
            return false;
        }

        $firstRow = $products[0];

        // Common header indicators
        $headerIndicators = [
            'Nazwa',
            'nazwa',
            'NAZWA',
            'EAN',
            'ean',
            'SKU',
            'sku',
            'Ilość',
            'ilosc',
            'ILOŚĆ',
            'ILOSC',
            'jednostka',
            'Jednostka',
            'JEDNOSTKA',
            'Cena',
            'cena',
            'CENA',
            'Waga',
            'waga',
            'WAGA',
            'Długość',
            'dlugosc',
            'DŁUGOŚĆ',
            'DLUGOSC',
            'Szerokość',
            'szerokosc',
            'SZEROKOŚĆ',
            'SZEROKOSC',
            'Wysokość',
            'wysokosc',
            'WYSOKOŚĆ',
            'WYSOKOSC',
            'm3',
            'M3',
            'Informacje',
            'informacje',
            'INFORMACJE'
        ];

        // Check if any field in first row matches header indicators
        $headerMatches = 0;
        $totalFields = 0;

        foreach ($firstRow as $field) {
            $totalFields++;
            if (is_string($field)) {
                $field = trim($field);
                foreach ($headerIndicators as $indicator) {
                    if (stripos($field, $indicator) !== false) {
                        $headerMatches++;
                        break;
                    }
                }
            }
        }

        // If more than 50% of fields look like headers, consider it a header row
        return ($totalFields > 0 && ($headerMatches / $totalFields) > 0.5);
    }


    /**
     * Get product groups for warehouse
     */
    public function getProductGroups(Request $request)
    {
        try {
            $groups = DB::table('GrupyTowarow')
                ->select('IDGrupyTowarow', 'Nazwa')
                ->where('IDMagazynu', $request->input('IDWarehouse'))
                ->orderBy('Nazwa')
                ->get();

            return response()->json([
                'status' => 'success',
                'groups' => $groups
            ]);
        } catch (Exception $e) {
            Log::error('Error loading product groups: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Błąd podczas ładowania grup towarowych'
            ]);
        }
    }

    /**
     * Get units for products
     */
    public function getUnits(Request $request)
    {
        try {
            $units = DB::table('JednostkaMiary')
                ->select('IDJednostkiMiary', 'Nazwa')
                ->distinct()
                ->orderBy('Nazwa')
                ->get();

            return response()->json([
                'status' => 'success',
                'units' => $units
            ]);
        } catch (Exception $e) {
            Log::error('Error loading units: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Błąd podczas ładowania jednostek'
            ]);
        }
    }

    /**
     * Add product to database only (simplified version)
     */
    public function addProductToDatabase(Request $request)
    {
        try {
            $data = $request->all();
            $IDWarehouse = $data['IDWarehouse'];
            $product = $data['product'];

            Log::info('Adding product to database', [
                'IDWarehouse' => $IDWarehouse,
                'product' => $product
            ]);

            // Validate required fields
            $requiredFields = ['Nazwa', 'EAN', 'IDGrupyTowarowej', 'jednostka', 'ilosc'];
            foreach ($requiredFields as $field) {
                if (empty($product[$field])) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Pole '{$field}' jest wymagane"
                    ]);
                }
            }

            // Validate quantity
            if (!is_numeric($product['ilosc']) || floatval($product['ilosc']) <= 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Ilość musi być liczbą większą od 0'
                ]);
            }

            DB::beginTransaction();

            // Check if product with this EAN already exists
            $existingProduct = DB::table('Towar')
                ->where('KodKreskowy', trim($product['EAN']))
                ->where('IDMagazynu', $IDWarehouse)
                ->first();

            if ($existingProduct) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Towar z kodem kreskowym (EAN) '{$product['EAN']}' już istnieje w bazie danych. Kod kreskowy musi być unikalny."
                ]);
            }

            // Create new product
            DB::table('Towar')->insert([
                'Nazwa' => $product['Nazwa'],
                'KodKreskowy' => trim($product['EAN']),
                'IDGrupyTowarowej' => $product['IDGrupyTowarowej'],
                'CenaZakupu' => $product['cena'] ?? 0,
                'IDMagazynu' => $IDWarehouse,
                'm3' => $product['m3'] ?? null,
                '_TowarTempString1' => $product['SKU'] ?? null,
                '_TowarTempDecimal1' => $product['waga'] ?? null,
                '_TowarTempDecimal3' => $product['dlugosc'] ?? null,
                '_TowarTempDecimal4' => $product['szerokosc'] ?? null,
                '_TowarTempDecimal5' => $product['wysokosc'] ?? null,
                'Uwagi' => $product['uwagi'] ?? '',
                'Aktywny' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ]);            // Check if unit exists for this product
            $IDTowaru = DB::table('Towar')
                ->where('KodKreskowy', trim($product['EAN']))
                ->where('IDMagazynu', $IDWarehouse)
                ->value('IDTowaru');
            $existingUnit = DB::table('JednostkaMiary')

                ->where('Nazwa', $product['jednostka'])
                ->first();

            if (!$existingUnit) {
                // Create unit for product
                DB::table('JednostkaMiary')->insert([

                    'Nazwa' => $product['jednostka'],

                    'Utworzono' => now(),
                    'Zmodyfikowano' => now()
                ]);
            }

            DB::commit();

            Log::info('Product added to database successfully', [
                'product_id' => $IDTowaru,
                'product_name' => $product['Nazwa']
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Towar został dodany do bazy danych pomyślnie',
                'product_id' => $IDTowaru,
                'product_name' => $product['Nazwa']
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Product addition to database error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Błąd podczas dodawania towaru: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Create DM document from API data with validation
     * Requires API key authentication, warehouse is determined by API key
     */
    public function createDMApi(Request $request)
    {
        try {
            // Get warehouse ID by API key
            $IDWarehouse = $this->getWarehouseByApiKey($request);

            if (!$IDWarehouse) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Nieprawidłowy klucz API'
                ], 401);
            }

            $data = $request->all();

            // Validate required fields
            $requiredFields = ['products'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Pole '{$field}' jest wymagane"
                    ], 400);
                }
            }

            $products = $data['products'];
            $tranzit_warehouse = $data['tranzit_warehouse'] ?? 0;
            $numerDokumentu = $data['numer_dokumentu'] ?? '';
            $uwagi_dokumentu = $data['uwagi_dokumentu'] ?? '';

            // Validate warehouse exists
            $warehouse = DB::table('Magazyn')->where('IDMagazynu', $IDWarehouse)->first();
            if (!$warehouse) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Magazyn o ID {$IDWarehouse} nie istnieje"
                ], 400);
            }

            // First, validate all products
            $validationResponse = [
                'status' => 'validation',
                'existing_products' => [],
                'new_products' => [],
                'errors' => [],
                'warnings' => []
            ];

            // Get or create default product group for warehouse
            $defaultGroupId = $this->getOrCreateDefaultGroup($IDWarehouse);

            // Check if first row contains headers or data
            $hasHeaders = $this->checkIfFirstRowIsHeaders($products);
            $startIndex = $hasHeaders ? 1 : 0;

            foreach ($products as $index => $product) {
                // Skip header row if present
                if ($index < $startIndex) {
                    continue;
                }

                // Skip empty rows
                if (empty(trim($product['Nazwa'] ?? '')) && empty(trim($product['EAN'] ?? '')) && empty(trim($product['jednostka'] ?? ''))) {
                    continue;
                }

                $productCheck = $this->validateProduct($product, $IDWarehouse, $index + 1);

                if (!empty($productCheck['errors'])) {
                    $validationResponse['errors'] = array_merge($validationResponse['errors'], $productCheck['errors']);
                }

                if (!empty($productCheck['warnings'])) {
                    $validationResponse['warnings'] = array_merge($validationResponse['warnings'], $productCheck['warnings']);
                }

                if ($productCheck['exists']) {
                    $validationResponse['existing_products'][] = $productCheck;
                } else {
                    $productCheck['IDGrupyTowarow'] = $defaultGroupId;
                    $validationResponse['new_products'][] = $productCheck;
                }
            }

            // If there are validation errors, return them without creating document
            if (!empty($validationResponse['errors'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Błędy walidacji danych',
                    'errors' => $validationResponse['errors'],
                    'warnings' => $validationResponse['warnings']
                ], 422);
            }

            // No validation errors, proceed with document creation
            $userId = 1; // Default user for API requests

            DB::beginTransaction();

            // Create new products
            $createdProducts = [];
            foreach ($validationResponse['new_products'] as $productData) {
                $createdProduct = $this->createProduct($productData, $IDWarehouse, $defaultGroupId);
                $createdProducts[$productData['original_index']] = $createdProduct;
            }

            // Create DM document
            $documentNumber = $this->generateDocumentNumber($IDWarehouse);

            DB::table('RuchMagazynowy')->insert([
                'Data' => Carbon::now(),
                'Uwagi' => $tranzit_warehouse == 1 ? 'tranzit ' . $numerDokumentu . ' Uwagi: ' . $uwagi_dokumentu : 'Dostawa do magazynu - ' . $numerDokumentu . ' Uwagi: ' . $uwagi_dokumentu,
                'IDRodzajuRuchuMagazynowego' => 200, // DM document type
                'IDMagazynu' => $IDWarehouse,
                'NrDokumentu' => $documentNumber,
                'IDUzytkownika' => $userId,
                'Utworzono' => Carbon::now(),
                'Zmodyfikowano' => Carbon::now(),
                'IDCompany' => 1,
                'IDRodzajuTransportu' => 0,
                'Operator' => 0,
                '_RuchMagazynowyTempBool1' => $tranzit_warehouse == 1 ? 1 : 0,
                '_RuchMagazynowyTempString8' => $numerDokumentu
            ]);

            $documentId = DB::table('RuchMagazynowy')
                ->where('NrDokumentu', $documentNumber)
                ->value('IDRuchuMagazynowego');

            if (!$documentId) {
                throw new Exception("Nie udało się utworzyć dokumentu DM");
            }

            // Add products to document
            foreach ($products as $index => $product) {
                // Skip header row if present
                if ($index < $startIndex) {
                    continue;
                }

                // Skip empty rows
                if (empty($product['Nazwa'] ?? '') && empty($product['EAN'] ?? '') && empty($product['SKU'] ?? '')) {
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
                    $insertData = [
                        'Ilosc' => floatval($product['Ilość'] ?? 0),
                        'Uwagi' => $product['Informacje dodatkowe'] ?? '',
                        'CenaJednostkowa' => floatval($product['Cena'] ?? 0),
                        'IDRuchuMagazynowego' => $documentId,
                        'IDTowaru' => $productId,
                        'Utworzono' => Carbon::now(),
                        'Zmodyfikowano' => Carbon::now(),
                        'Uzytkownik' => $userId,
                    ];

                    if ($tranzit_warehouse == 0) {
                        $insertData['NumerSerii'] = json_encode([
                            'k' => $product['Numer kartonu'] ?? '',
                            'p' => $product['Numer palety'] ?? ''
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }

                    DB::table('ElementRuchuMagazynowego')->insert($insertData);
                }
            }

            DB::commit();

            Log::info('DM Document created successfully via API', [
                'document_id' => $documentId,
                'document_number' => $documentNumber,
                'warehouse_id' => $IDWarehouse,
                'products_count' => count($products),
                'tranzit_warehouse' => $tranzit_warehouse,
                'numer_dokumentu' => $numerDokumentu
            ]);

            return response()->json([
                'status' => 'success',
                'document_id' => $documentId,
                'document_number' => $documentNumber,
                'warehouse_id' => $IDWarehouse,
                'warehouse_name' => $warehouse->Nazwa ?? '',
                'tranzit_warehouse' => $tranzit_warehouse,
                'numer_dokumentu' => $numerDokumentu,
                'created_products_count' => count($createdProducts),
                'total_products_count' => count($products) - $startIndex,
                'message' => 'Dokument DM został utworzony pomyślnie przez API',
                'warnings' => $validationResponse['warnings'] ?? []
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error in createDMApi: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Błąd podczas tworzenia dokumentu: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get warehouse ID by API key
     */
    private function getWarehouseByApiKey(Request $request)
    {
        $apiKey = $request->header('X-API-Key') ?? $request->input('api_key');

        if (empty($apiKey)) {
            return null;
        }

        // Check API key in config first
        $apiKeysConfig = config('app.api_keys', []);

        if (!empty($apiKeysConfig)) {
            // Check if API key exists in config and return warehouse ID
            if (array_key_exists($apiKey, $apiKeysConfig)) {
                return intval($apiKeysConfig[$apiKey]);
            }
            return null;
        }

        // If no config keys, check database
        $apiKeyRecord = DB::table('api_keys')
            ->where('key', $apiKey)
            ->where('active', 1)
            ->first();

        if ($apiKeyRecord) {
            return intval($apiKeyRecord->warehouse_id);
        }

        return null;
    }

    /**
     * Validate API key and warehouse access
     */
    private function validateApiKey(Request $request, $IDWarehouse = null)
    {
        $apiKey = $request->header('X-API-Key') ?? $request->input('api_key');

        if (empty($apiKey)) {
            return false;
        }

        // Check API key in config first
        $apiKeysConfig = config('app.api_keys', []);

        if (!empty($apiKeysConfig)) {
            // Check if API key exists in config
            if (!array_key_exists($apiKey, $apiKeysConfig)) {
                return false;
            }

            // If warehouse ID is provided, check if it matches the key's warehouse
            if ($IDWarehouse !== null) {
                $keyWarehouse = intval($apiKeysConfig[$apiKey]);
                return $keyWarehouse === intval($IDWarehouse);
            }

            return true; // Valid API key
        }

        // If no config keys, check database
        $query = DB::table('api_keys')
            ->where('key', $apiKey)
            ->where('active', 1);

        if ($IDWarehouse !== null) {
            // Check if API key has access to specific warehouse
            $validKey = $query->where('warehouse_id', $IDWarehouse)->first();
            return !empty($validKey);
        }

        // Just check if API key exists and is active
        return $query->exists();
    }
}
