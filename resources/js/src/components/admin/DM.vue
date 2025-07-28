<template>
  <div id="importXLS">
    <v-snackbar v-model="snackbar" top right timeout="-1">
      <v-card-text>
        {{ message }}
      </v-card-text>
      <template v-slot:action="{ attrs }">
        <v-btn color="pink" text v-bind="attrs" @click="snackbar = false">
          X
        </v-btn>
      </template>
    </v-snackbar>

    <v-container fluid>
      <v-row>
        <v-col cols="2">
          <v-select
            label="Magazyn"
            v-model="IDWarehouse"
            :items="warehouses"
            item-title="Nazwa"
            item-value="IDMagazynu"
            @update:modelValue="clear()"
            hide-details="auto"
          ></v-select>
        </v-col>
        <v-col cols="2" v-if="IDWarehouse">
          <v-file-input
            v-model="files"
            ref="fileupload"
            label="przesyłanie Excel"
            show-size
            truncate-length="24"
            @change="onFileChange"
          ></v-file-input>
        </v-col>
      </v-row>
      <v-progress-linear
        :active="loading"
        indeterminate
        color="purple"
      ></v-progress-linear>
      <v-row v-if="table.length && files">
        <v-col cols="12">
          <p>Loaded {{ table.length }} rows</p>
          <p v-if="table.length > 0">Columns: {{ table[0].length }}</p>
          <div class="d-flex gap-2">
            <v-btn color="primary" @click="makeJson"> Make JSON </v-btn>
            <v-btn
              color="orange"
              @click="validateProducts"
              :disabled="!canValidate || validating"
              :loading="validating"
            >
              Sprawdź produkty
            </v-btn>
            <v-btn
              color="green"
              @click="createDocument"
              :disabled="!canCreateDocument || creating"
              :loading="creating"
            >
              Utwórz dokument DM
            </v-btn>
          </div>
        </v-col>

        <!-- Validation Results -->
        <v-col cols="12" v-if="validationResults">
          <v-card>
            <v-card-title>Wyniki walidacji</v-card-title>
            <v-card-text>
              <div v-if="validationResults.errors.length > 0" class="mb-4">
                <h4 class="text-red">Błędy:</h4>
                <ul>
                  <li
                    v-for="error in validationResults.errors"
                    :key="error"
                    class="text-red"
                  >
                    {{ error }}
                  </li>
                </ul>
              </div>

              <div v-if="validationResults.warnings.length > 0" class="mb-4">
                <h4 class="text-orange">Ostrzeżenia:</h4>
                <ul>
                  <li
                    v-for="warning in validationResults.warnings"
                    :key="warning"
                    class="text-orange"
                  >
                    {{ warning }}
                  </li>
                </ul>
              </div>

              <div class="mb-2">
                <strong>Istniejące produkty:</strong>
                {{ validationResults.existing_products.length }}
              </div>
              <div class="mb-2">
                <strong>Nowe produkty do utworzenia:</strong>
                {{ validationResults.new_products.length }}
              </div>
              <div
                class="mb-2"
                v-if="validationResults.missing_units.length > 0"
              >
                <strong>Brakujące jednostki miary:</strong>
                {{ validationResults.missing_units.join(", ") }}
              </div>
            </v-card-text>
          </v-card>
        </v-col>

        <v-col cols="9">
          <v-table id="loadedTable">
            <thead>
              <tr>
                <th v-for="el in table[0].length" :key="el">
                  <v-select
                    v-model="headerSelection[el - 1]"
                    :items="[
                      '',
                      'Nazwa',
                      'SKU',
                      'EAN',
                      'Ilość',
                      'jednostka',
                      'Cena',
                      'Waga (kg)',
                      'Długość (cm)',
                      'Szerokość (cm)',
                      'Wysokość (cm)',
                      'Informacje dodatkowe ',
                    ]"
                    outlined
                    @update:modelValue="makeHeader"
                  >
                  </v-select>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, ix) in table" :key="ix">
                <td v-for="(it, i) in item" :key="i">{{ it }}</td>
              </tr>
            </tbody>
          </v-table>
        </v-col>
      </v-row>
    </v-container>
  </div>
</template>

<script>
import * as XLSX from "xlsx";
import axios from "axios";
import _ from "lodash";

export default {
  name: "FulstorImportXLS",
  props: ["user"],
  data: () => ({
    loading: false,
    validating: false,
    creating: false,

    errorMessages: [],
    message: "",
    snackbar: false,

    files: null,
    table: [],
    header: [],
    headerSelection: [],
    IDWarehouse: null,
    warehouses: [],

    validationResults: null,
    documentCreated: null,
  }),

  mounted() {
    this.getWarehouse();
  },

  watch: {
    IDWarehouse() {
      this.validationResults = null;
      this.documentCreated = null;
    },
  },

  computed: {
    canValidate() {
      return (
        this.IDWarehouse && this.table.length > 1 && this.header.length > 0
      );
    },
    canCreateDocument() {
      return (
        this.validationResults &&
        this.validationResults.errors.length === 0 &&
        this.validationResults.status === "success"
      );
    },
  },

  methods: {
    makeJson() {
      if (this.table.length) {
        const result = [];
        for (let i = 1; i < this.table.length; i++) {
          const row = {};
          for (let j = 0; j < this.header.length; j++) {
            if (this.header[j]) {
              row[this.header[j]] = this.table[i][j];
            }
          }
          result.push(row);
        }
        console.log(result);
        this.message = "JSON created in console";
        this.snackbar = true;
      }
    },

    async validateProducts() {
      if (!this.canValidate) {
        this.message =
          "Wybierz magazyn i wczytaj plik Excel z prawidłowymi nagłówkami";
        this.snackbar = true;
        return;
      }

      this.validating = true;
      this.validationResults = null;

      try {
        const products = this.getProductsFromTable();

        if (products.length === 0) {
          this.message = "Brak danych do walidacji";
          this.snackbar = true;
          return;
        }

        // Use robust sanitization for JSON serialization
        const cleanedProducts = products.map((product, index) => {
          try {
            return this.sanitizeForJson(product);
          } catch (error) {
            console.warn(`Error sanitizing product ${index}:`, error);
            // Fallback to ultra cleaning
            const fallbackProduct = {};
            Object.keys(product).forEach((key) => {
              fallbackProduct[key] = this.ultraCleanString(
                String(product[key] || "")
              );
            });
            return fallbackProduct;
          }
        });

        console.log("Sending sanitized products to API:", cleanedProducts);

        // Test JSON serialization before sending
        let finalCleanedProducts = cleanedProducts;
        try {
          JSON.stringify(finalCleanedProducts);
          console.log("JSON serialization test passed");
        } catch (jsonError) {
          console.error("JSON serialization failed:", jsonError);
          console.warn("Applying emergency super cleaning to all data");

          // Emergency fallback: apply super cleaning to everything
          finalCleanedProducts = products.map((product, index) => {
            const emergencyProduct = {};
            Object.keys(product).forEach((key) => {
              const cleanKey = this.superCleanString(key);
              const cleanValue = this.superCleanString(
                String(product[key] || "")
              );
              if (cleanKey) {
                emergencyProduct[cleanKey] = cleanValue;
              }
            });
            return emergencyProduct;
          });

          // Test emergency cleaned data
          try {
            JSON.stringify(finalCleanedProducts);
            console.log(
              "Emergency cleaning successful, using super cleaned data"
            );
          } catch (emergencyError) {
            console.error("Even emergency cleaning failed:", emergencyError);
            throw new Error(
              "Data contains characters that cannot be serialized to JSON even after aggressive cleaning"
            );
          }
        }

        const response = await axios.post("/api/checkDMProducts", {
          IDWarehouse: this.IDWarehouse,
          products: finalCleanedProducts,
        });

        if (response.data.status === "success") {
          this.validationResults = response.data;
          this.message = "Walidacja zakończona pomyślnie";
        } else {
          this.message = response.data.message || "Błąd podczas walidacji";
        }

        this.snackbar = true;
      } catch (error) {
        console.error("Validation error:", error);
        let errorMessage = "Błąd podczas walidacji produktów";

        if (error.message && error.message.includes("cannot be serialized")) {
          errorMessage =
            "Dane zawierają nieprawidłowe znaki. Sprawdź czy plik Excel zawiera tylko prawidłowe znaki tekstowe.";
        } else if (
          error.response &&
          error.response.data &&
          error.response.data.message
        ) {
          errorMessage = error.response.data.message;

          // Handle specific UTF-8 error
          if (
            errorMessage.includes("Malformed UTF-8") ||
            errorMessage.includes("incorrectly encoded")
          ) {
            errorMessage =
              "Błąd kodowania znaków. Spróbuj zapisać plik Excel ponownie lub usuń specjalne znaki.";
          }
        } else if (error.message) {
          errorMessage += ": " + error.message;
        }

        this.message = errorMessage;
        this.snackbar = true;
      } finally {
        this.validating = false;
      }
    },

    async createDocument() {
      if (!this.canCreateDocument) {
        this.message = "Najpierw przeprowadź walidację bez błędów";
        this.snackbar = true;
        return;
      }

      this.creating = true;

      try {
        const products = this.getProductsFromTable();

        // Use robust sanitization for JSON serialization
        const cleanedProducts = products.map((product, index) => {
          try {
            return this.sanitizeForJson(product);
          } catch (error) {
            console.warn(`Error sanitizing product ${index}:`, error);
            // Fallback to ultra cleaning
            const fallbackProduct = {};
            Object.keys(product).forEach((key) => {
              fallbackProduct[key] = this.ultraCleanString(
                String(product[key] || "")
              );
            });
            return fallbackProduct;
          }
        });

        console.log(
          "Sending sanitized products for document creation:",
          cleanedProducts
        );

        // Test JSON serialization before sending
        let finalCleanedProducts = cleanedProducts;
        try {
          JSON.stringify(finalCleanedProducts);
          console.log("JSON serialization test passed");
        } catch (jsonError) {
          console.error("JSON serialization failed:", jsonError);
          console.warn("Applying emergency super cleaning to all data");

          // Emergency fallback: apply super cleaning to everything
          finalCleanedProducts = products.map((product, index) => {
            const emergencyProduct = {};
            Object.keys(product).forEach((key) => {
              const cleanKey = this.superCleanString(key);
              const cleanValue = this.superCleanString(
                String(product[key] || "")
              );
              if (cleanKey) {
                emergencyProduct[cleanKey] = cleanValue;
              }
            });
            return emergencyProduct;
          });

          // Test emergency cleaned data
          try {
            JSON.stringify(finalCleanedProducts);
            console.log(
              "Emergency cleaning successful, using super cleaned data"
            );
          } catch (emergencyError) {
            console.error("Even emergency cleaning failed:", emergencyError);
            throw new Error(
              "Data contains characters that cannot be serialized to JSON even after aggressive cleaning"
            );
          }
        }

        const response = await axios.post("/api/createDMDocument", {
          IDWarehouse: this.IDWarehouse,
          products: finalCleanedProducts,
          existing_products: this.validationResults.existing_products,
          new_products: this.validationResults.new_products,
          missing_units: this.validationResults.missing_units,
          user_id: this.user?.id || 1,
        });

        if (response.data.status === "success") {
          this.documentCreated = response.data;
          this.message = `Dokument DM ${response.data.document_number} został utworzony pomyślnie`;

          // Clear data after successful creation
          this.clear();
          this.validationResults = null;
        } else {
          this.message =
            response.data.message || "Błąd podczas tworzenia dokumentu";
        }

        this.snackbar = true;
      } catch (error) {
        console.error("Document creation error:", error);
        this.message = "Błąd podczas tworzenia dokumentu DM";
        this.snackbar = true;
      } finally {
        this.creating = false;
      }
    },

    getProductsFromTable() {
      const products = [];
      for (let i = 1; i < this.table.length; i++) {
        const row = {};
        for (let j = 0; j < this.header.length; j++) {
          if (this.header[j]) {
            let cellValue = this.table[i][j];

            // Ensure all values are properly cleaned and converted to strings
            if (cellValue !== null && cellValue !== undefined) {
              // Convert to string and clean
              cellValue = String(cellValue).trim();

              // Additional UTF-8 cleaning
              cellValue = this.ultraCleanString(cellValue);
            } else {
              cellValue = "";
            }

            row[this.header[j]] = cellValue;
          }
        }
        products.push(row);
      }

      console.log("Products prepared for API:", products);
      return products;
    },

    // Ultra-aggressive UTF-8 cleaning - fallback to ASCII only if needed
    ultraCleanString(str) {
      if (typeof str !== "string") {
        str = String(str);
      }

      try {
        // First attempt: standard cleaning
        let cleaned = this.cleanUtf8String(str);

        // Test if it can be JSON serialized
        JSON.stringify(cleaned);
        return cleaned;
      } catch (e) {
        console.warn(
          "Standard UTF-8 cleaning failed, using ASCII-only fallback:",
          e
        );

        // Fallback: Keep only basic ASCII characters and common symbols
        // Allow letters, numbers, spaces, and basic punctuation
        return str
          .replace(/[^\x20-\x7E]/g, "")
          .replace(/[^\w\s\-.,!?()]/g, "")
          .trim();
      }
    },

    // Most aggressive cleaning - removes everything except alphanumeric and spaces
    superCleanString(str) {
      if (typeof str !== "string") {
        str = String(str);
      }

      // Keep only letters, numbers, spaces, and basic punctuation
      return str
        .replace(/[^a-zA-Z0-9\s\-.,]/g, "")
        .replace(/\s+/g, " ")
        .trim();
    },

    cleanUtf8String(str) {
      if (typeof str !== "string") {
        str = String(str);
      }

      try {
        // First, try to encode and decode to catch encoding issues
        str = decodeURIComponent(encodeURIComponent(str));
      } catch (e) {
        // If that fails, use more aggressive cleaning
        console.warn(
          "UTF-8 encoding issue detected, applying aggressive cleaning:",
          e
        );
      }

      return (
        str
          // Remove null bytes and control characters
          .replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, "")
          // Remove invalid UTF-8 sequences (surrogate pairs)
          .replace(/[\uD800-\uDFFF]/g, "")
          // Remove zero-width characters
          .replace(/[\u200B-\u200D\uFEFF]/g, "")
          // Remove BOM characters
          .replace(/[\uFEFF\uFFFE]/g, "")
          // Replace any remaining problematic characters with empty string
          .replace(/\uFFFD/g, "") // Replacement character
          // Normalize whitespace
          .replace(/\s+/g, " ")
          .trim()
      );
    },

    // Robust JSON-safe data cleaning
    sanitizeForJson(obj) {
      if (obj === null || obj === undefined) {
        return "";
      }

      if (typeof obj === "string") {
        try {
          const cleaned = this.ultraCleanString(obj);
          // Test JSON serialization
          JSON.stringify(cleaned);
          return cleaned;
        } catch (e) {
          console.warn("Ultra clean failed, using super clean:", e);
          return this.superCleanString(obj);
        }
      }

      if (typeof obj === "number") {
        if (isNaN(obj) || !isFinite(obj)) {
          return "";
        }
        try {
          const cleaned = this.ultraCleanString(obj.toString());
          JSON.stringify(cleaned);
          return cleaned;
        } catch (e) {
          return this.superCleanString(obj.toString());
        }
      }

      if (typeof obj === "boolean") {
        return obj.toString();
      }

      if (Array.isArray(obj)) {
        return obj.map((item) => this.sanitizeForJson(item));
      }

      if (typeof obj === "object") {
        const cleaned = {};
        Object.keys(obj).forEach((key) => {
          const cleanKey = this.superCleanString(key); // Use super clean for keys
          if (cleanKey) {
            // Only include keys that aren't empty after cleaning
            cleaned[cleanKey] = this.sanitizeForJson(obj[key]);
          }
        });
        return cleaned;
      }

      try {
        const cleaned = this.ultraCleanString(String(obj));
        JSON.stringify(cleaned);
        return cleaned;
      } catch (e) {
        return this.superCleanString(String(obj));
      }
    },

    cleanTableData(data) {
      // First, try to identify which columns might contain EAN codes
      const headerRow = data[0] || [];
      const eanColumnIndexes = [];

      headerRow.forEach((header, index) => {
        if (typeof header === "string") {
          const headerLower = header.toLowerCase();
          if (
            headerLower.includes("ean") ||
            headerLower.includes("barcode") ||
            (headerLower.includes("kod") && headerLower.includes("kresk"))
          ) {
            eanColumnIndexes.push(index);
          }
        }
      });

      return data.map((row, rowIndex) => {
        return row.map((cell, cellIndex) => {
          if (typeof cell === "string") {
            // Remove null bytes and other problematic characters
            return this.ultraCleanString(cell);
          } else if (typeof cell === "number") {
            const cellStr = cell.toString();

            // Handle scientific notation (e.g., "4.06328E+12")
            if (
              cellStr.includes("E+") ||
              cellStr.includes("e+") ||
              cellStr.includes("E-") ||
              cellStr.includes("e-")
            ) {
              // Convert scientific notation back to full number string
              let fullNumber;
              try {
                if (cellStr.includes("E+") || cellStr.includes("e+")) {
                  // Special handling for EAN codes that got converted to scientific notation
                  // EAN "4063276039316" becomes "4.06328E+12" in Excel
                  if (cellStr.match(/^\d\.\d+E\+\d+$/i)) {
                    // This is likely an EAN code, reconstruct it properly
                    const parts = cellStr.toLowerCase().split("e+");
                    if (parts.length === 2) {
                      const base = parseFloat(parts[0]);
                      const exp = parseInt(parts[1]);
                      // Use more precise calculation to avoid floating point errors
                      const multiplier = Math.pow(10, exp);
                      fullNumber = Math.round(base * multiplier).toString();
                    }
                  } else {
                    // For other positive exponents, use toFixed(0) to get integer
                    fullNumber = cell.toFixed(0);
                  }
                } else {
                  // For negative exponents, preserve decimal places but convert to string
                  fullNumber = cell.toString();
                }

                console.log(
                  `Converted scientific notation ${cellStr} to ${fullNumber}`
                );

                // Ensure the result is a clean string
                fullNumber = this.ultraCleanString(String(fullNumber));
              } catch (e) {
                console.warn(
                  `Failed to convert scientific notation ${cellStr}:`,
                  e
                );
                fullNumber = this.ultraCleanString(cellStr);
              }

              // Check if it looks like an EAN (8-14 digits) or if it's in an EAN column
              if (
                (fullNumber.length >= 8 &&
                  fullNumber.length <= 14 &&
                  /^\d+$/.test(fullNumber)) ||
                eanColumnIndexes.includes(cellIndex)
              ) {
                return fullNumber;
              }

              return fullNumber;
            }

            // For other long numbers that might be codes (like SKU, EAN)
            // or if this column is identified as containing EAN codes
            if (
              (cellStr.length >= 8 && /^\d+$/.test(cellStr)) ||
              eanColumnIndexes.includes(cellIndex)
            ) {
              return this.ultraCleanString(cellStr);
            }

            return this.ultraCleanString(cell.toString());
          }

          // For any other type, convert to string and clean
          return this.ultraCleanString(String(cell || ""));
        });
      });
    },

    clear() {
      this.files = null;
      this.table = [];
      this.header = [];
      this.headerSelection = [];
      this.validationResults = null;
      this.documentCreated = null;
    },

    getWarehouse() {
      const vm = this;
      axios
        .get("/api/getWarehouse")
        .then((res) => {
          if (res.status == 200) {
            vm.warehouses = res.data;
            vm.warehouses = vm.warehouses.map((w) => {
              w.koef = parseFloat(w.koef);
              return w;
            });
          }
        })
        .catch((error) => console.log(error));
    },

    getheader() {
      this.header = [...this.headerSelection];
      console.log("Header:", this.header);
    },

    makeHeader() {
      this.getheader();
    },

    onFileChange(event) {
      const fileList = event && event.target ? event.target.files : null;
      let file = fileList && fileList.length ? fileList[0] : null;
      if (!file) return;

      // Check file extension
      const allowedExtensions = [".xlsx", ".xls"];
      const fileName = file.name ? file.name.toLowerCase() : "";
      const hasValidExtension = allowedExtensions.some((ext) =>
        fileName.endsWith(ext)
      );

      // Check MIME type
      const allowedTypes = [
        "application/vnd.ms-excel",
        "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
      ];
      const hasValidType = allowedTypes.includes(file.type);

      if (hasValidExtension || hasValidType) {
        this.files = file;
        this.createInput(file);
      } else {
        this.files = null;
        this.message =
          "Nieprawidłowy format pliku. Pobierz plik Excel (.xlsx lub .xls)";
        this.snackbar = true;
      }
    },

    createInput(f) {
      let vm = this;
      var reader = new FileReader();

      reader.onload = function (e) {
        try {
          var data = e.target.result,
            fixedData = vm.fixdata(data),
            workbook = XLSX.read(btoa(fixedData), {
              type: "base64",
              codepage: 65001, // UTF-8
              cellText: false,
              cellDates: true,
              cellNF: false, // Don't format numbers
              cellStyles: true, // Keep cell formatting info
            }),
            firstSheetName = workbook.SheetNames[0],
            worksheet = workbook.Sheets[firstSheetName];

          vm.loading = true;
          setTimeout(() => {
            try {
              vm.table = XLSX.utils.sheet_to_json(worksheet, {
                header: 1,
                raw: true, // Keep original format to prevent scientific notation
                defval: "",
                blankrows: false,
              });

              // Clean the data from encoding issues and fix EAN formatting
              vm.table = vm.cleanTableData(vm.table);

              vm.headerSelection = new Array(vm.table[0]?.length || 0).fill("");
              console.log("Loaded table data:", vm.table);
              console.log("Table length:", vm.table.length);

              // Debug: Log potential EAN issues
              if (vm.table.length > 1) {
                const sampleRow = vm.table[1];
                console.log("Sample data row:", sampleRow);
                sampleRow.forEach((cell, index) => {
                  if (
                    typeof cell === "string" &&
                    cell.length > 10 &&
                    /^\d+$/.test(cell)
                  ) {
                    console.log(
                      `Column ${index} contains potential EAN: ${cell}`
                    );
                  }
                });
              }

              if (vm.table.length === 0) {
                vm.message = "Plik Excel jest pusty lub nie zawiera danych";
                vm.snackbar = true;
              }
            } catch (error) {
              console.error("Error processing Excel file:", error);
              vm.message =
                "Błąd podczas przetwarzania pliku Excel: " + error.message;
              vm.snackbar = true;
              vm.table = [];
              vm.headerSelection = [];
            }
            vm.loading = false;
          }, 100);
        } catch (error) {
          console.error("Error reading Excel file:", error);
          vm.message = "Błąd podczas odczytu pliku Excel: " + error.message;
          vm.snackbar = true;
          vm.loading = false;
        }
      };

      reader.onerror = function (error) {
        console.error("FileReader error:", error);
        vm.message = "Błąd podczas wczytywania pliku";
        vm.snackbar = true;
        vm.loading = false;
      };

      reader.readAsArrayBuffer(f);
    },

    fixdata(data) {
      var o = "",
        l = 0,
        w = 10240;
      for (; l < data.byteLength / w; ++l)
        o += String.fromCharCode.apply(
          null,
          new Uint8Array(data.slice(l * w, l * w + w))
        );
      o += String.fromCharCode.apply(null, new Uint8Array(data.slice(l * w)));
      return o;
    },
  },
};
</script>

<style>
#inspire #importXLS .v-text-field__details {
  display: initial;
}
#loadedTable .v-data-table__wrapper {
  overflow: auto;
  height: 80vh;
}
.text-red {
  color: red;
}
.text-orange {
  color: orange;
}
.d-flex {
  display: flex;
}
.gap-2 {
  gap: 8px;
}
</style>
