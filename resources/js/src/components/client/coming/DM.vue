<template>
  <div id="importXLS">
    <v-snackbar v-model="snackbar" location="top" timeout="6000" color="yellow">
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
        <v-col cols="2" v-if="IDWarehouse && tranzit_warehouse != null">
          <v-file-input
            v-model="files"
            ref="fileupload"
            label="przesyłanie Excel"
            show-size
            truncate-length="24"
            @change="onFileChange"
          ></v-file-input>
        </v-col>
        <v-col v-if="table.length && files">
          <p>Loaded {{ table.length }} rows</p>
          <p v-if="table.length > 0">Columns: {{ table[0].length }}</p>
          <p class="text-info mb-2">
            <strong>Obowiązkowe pola:</strong> Nazwa, EAN, jednostka (towar,
            karton, paleta) (oznaczone *)
          </p>
        </v-col>
      </v-row>
      <v-progress-linear
        :active="loading"
        indeterminate
        color="purple"
      ></v-progress-linear>
      <v-row v-if="table.length && files">
        <v-col cols="12">
          <div class="d-flex gap-2">
            <v-btn
              color="orange"
              @click="validateProducts"
              :disabled="!canValidate || validating"
              :loading="validating"
            >
              Sprawdź produkty
            </v-btn>
            <v-btn
              v-if="canCreateDocument"
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

        <v-col cols="12">
          <v-table id="loadedTable">
            <thead>
              <tr>
                <th v-for="el in table[0].length" :key="el">
                  <v-select
                    v-model="headerSelection[el - 1]"
                    :items="[
                      '',
                      'Nazwa *',
                      'SKU',
                      'EAN *',
                      'Ilość',
                      'jednostka *',
                      'Cena',
                      'Waga (kg)',
                      'Długość (cm)',
                      'Szerokość (cm)',
                      'Wysokość (cm)',
                      'm3',
                      'Informacje dodatkowe ',
                      ...(this.tranzit_warehouse == 0
                        ? ['Numer kartonu', 'Numer palety']
                        : []),
                    ]"
                    outlined
                    @update:modelValue="makeHeader"
                  >
                  </v-select>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(item, ix) in table"
                :key="ix"
                :class="getRowClass(ix)"
              >
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
  props: ["user", "IDWarehouse", "tranzit_warehouse", "numerDokumentu"],
  data() {
    return {
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

      warehouses: [],

      validationResults: null,
      documentCreated: null,
      rowHighlights: [], // Array to store row highlighting info
    };
  },

  mounted() {
    this.getWarehouse();
  },

  watch: {
    IDWarehouse() {
      this.validationResults = null;
      this.documentCreated = null;
      this.rowHighlights = []; // Clear highlights when warehouse changes
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
        this.validationResults.status === "success" &&
        this.hasRequiredFields()
      );
    },
  },

  methods: {
    async validateProducts() {
      if (!this.canValidate) {
        this.message =
          "Wybierz magazyn i wczytaj plik Excel z prawidłowymi nagłówkami";
        this.snackbar = true;
        return;
      }

      // Check required fields first
      if (!this.validateRequiredFields()) {
        return;
      }

      this.validating = true;
      this.validationResults = null;

      try {
        const products = this.getProductsFromTable();

        if (products.length === 0) {
          this.message = "Brak danych do walidacji";
          this.snackbar = true;
          this.validating = false;
          return;
        }

        const response = await axios.post("/api/checkDMProducts", {
          IDWarehouse: this.IDWarehouse,
          products: products,
        });

        if (response.data.status === "success") {
          this.validationResults = response.data;

          if (response.data.errors.length > 0) {
            this.message = `Walidacja zakończona z ${response.data.errors.length} błędami. Sprawdź podświetlone wiersze.`;
          } else {
            this.message = "Walidacja zakończona pomyślnie";
          }

          // Process validation results for row highlighting
          this.processValidationResults();
        } else {
          this.message = response.data.message || "Błąd podczas walidacji";
        }

        this.snackbar = true;
      } catch (error) {
        console.error("Validation error:", error);
        this.message = "Błąd podczas walidacji produktów";
        if (
          error.response &&
          error.response.data &&
          error.response.data.message
        ) {
          this.message = error.response.data.message;
        } else if (error.message) {
          this.message += ": " + error.message;
        }
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

      // Double check required fields
      if (!this.validateRequiredFields()) {
        return;
      }

      this.creating = true;

      try {
        const products = this.getProductsFromTable();

        console.log("Sending products for document creation:", products);

        const response = await axios.post("/api/createDMDocument", {
          IDWarehouse: this.IDWarehouse,
          products: products,
          existing_products: this.validationResults.existing_products,
          new_products: this.validationResults.new_products,
          missing_units: this.validationResults.missing_units,
          user_id: this.user?.id || 1,
          tranzit_warehouse: this.tranzit_warehouse || 0, // Use prop or default to 0
          numer_dokumentu: this.numerDokumentu || "", // Use prop or empty string
        });

        if (response.data.status === "success") {
          this.documentCreated = response.data;
          this.message = `Dokument DM ${response.data.document_number} został utworzony pomyślnie`;

          // Clear data after successful creation
          this.clear();
          this.validationResults = null;

          // Emit success event for parent component
          this.$emit("import-success", response.data);
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

            // Ensure all values are properly converted to strings
            if (cellValue !== null && cellValue !== undefined) {
              cellValue = String(cellValue).trim();
            } else {
              cellValue = "";
            }

            row[this.header[j]] = cellValue;
          }
        }
        products.push(row);
      }

      return products;
    },

    hasRequiredFields() {
      // Check if all required fields are selected in headers
      const requiredFields = ["Nazwa", "EAN", "jednostka"];
      return requiredFields.every((field) => this.header.includes(field));
    },

    validateRequiredFields() {
      const requiredFields = ["Nazwa", "EAN", "jednostka"];
      const missingFields = requiredFields.filter(
        (field) => !this.header.includes(field)
      );

      if (missingFields.length > 0) {
        this.message = `Brakujące obowiązkowe pola: ${missingFields.join(
          ", "
        )}. Wybierz te pola w nagłówkach tabeli.`;
        this.snackbar = true;
        return false;
      }

      return true;
    },

    processValidationResults() {
      // Initialize highlights array
      this.rowHighlights = new Array(this.table.length).fill("");

      if (!this.validationResults) return;

      // Process existing products (green/salad color)
      if (this.validationResults.existing_products) {
        this.validationResults.existing_products.forEach((product) => {
          const rowIndex = product.row_number; // row_number is 1-based, but we need 0-based for array
          if (rowIndex && rowIndex < this.rowHighlights.length) {
            this.rowHighlights[rowIndex] = "existing-product";
          }
        });
      }

      // Process new products (pink color)
      if (this.validationResults.new_products) {
        this.validationResults.new_products.forEach((product) => {
          const rowIndex = product.row_number;
          if (rowIndex && rowIndex < this.rowHighlights.length) {
            this.rowHighlights[rowIndex] = "new-product";
          }
        });
      }

      // Process error products (yellow color)
      // We need to determine which rows have errors by checking the error messages
      if (
        this.validationResults.errors &&
        this.validationResults.errors.length > 0
      ) {
        this.validationResults.errors.forEach((error) => {
          // Extract row number from error message (format: "Wiersz X: ...")
          const match = error.match(/Wiersz (\d+):/);
          if (match) {
            const rowNumber = parseInt(match[1]);
            if (rowNumber && rowNumber < this.rowHighlights.length) {
              this.rowHighlights[rowNumber] = "error-product";
            }
          }
        });
      }
    },

    getRowClass(rowIndex) {
      // Skip header row (index 0)
      if (rowIndex === 0) return "";

      const highlight = this.rowHighlights[rowIndex];
      return highlight || "";
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

      const cleanedData = data.map((row, rowIndex) => {
        return row.map((cell, cellIndex) => {
          if (typeof cell === "string") {
            return cell.trim();
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
                fullNumber = String(fullNumber).trim();
              } catch (e) {
                console.warn(
                  `Failed to convert scientific notation ${cellStr}:`,
                  e
                );
                fullNumber = cellStr.trim();
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
              return cellStr.trim();
            }

            return cell.toString().trim();
          }

          // For any other type, convert to string
          return String(cell || "").trim();
        });
      });

      // Filter out rows with less than 3 filled columns (but keep header row)
      return cleanedData.filter((row, rowIndex) => {
        // Always keep the header row (index 0)
        if (rowIndex === 0) return true;

        // Count filled cells in this row
        const filledCells = row.filter((cell) => {
          if (cell === null || cell === undefined) return false;
          const cellStr = String(cell).trim();
          return cellStr !== "";
        });

        // Keep row only if it has at least 3 filled columns
        const shouldKeep = filledCells.length >= 3;

        if (!shouldKeep) {
          console.log(
            `Removing row ${rowIndex + 1} from table: only ${
              filledCells.length
            } filled columns`
          );
        }

        return shouldKeep;
      });
    },

    clear() {
      this.files = null;
      this.table = [];
      this.header = [];
      this.headerSelection = [];
      this.validationResults = null;
      this.documentCreated = null;
      this.rowHighlights = []; // Clear highlights
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
      // Remove asterisks from selected headers to get clean field names
      this.header = [...this.headerSelection].map((header) => {
        if (typeof header === "string" && header.endsWith(" *")) {
          return header.slice(0, -2); // Remove ' *' from the end
        }
        return header;
      });
      console.log("Header:", this.header);
    },

    makeHeader() {
      this.getheader();
    },

    autoMapHeaders() {
      if (!this.table || this.table.length === 0 || !this.table[0]) return;

      // Define mapping between Excel headers and our field options
      const headerMappings = {
        // Basic mappings (case insensitive)
        nazwa: "Nazwa *",
        name: "Nazwa *",
        "product name": "Nazwa *",
        product: "Nazwa *",

        sku: "SKU",
        kod: "SKU",
        code: "SKU",

        ean: "EAN *",
        barcode: "EAN *",
        "kod kreskowy": "EAN *",

        ilość: "Ilość",
        ilosc: "Ilość",
        quantity: "Ilość",
        qty: "Ilość",

        jednostka: "jednostka *",
        unit: "jednostka *",
        "jednostka miary": "jednostka *",

        cena: "Cena",
        price: "Cena",
        cost: "Cena",

        waga: "Waga (kg)",
        weight: "Waga (kg)",
        masa: "Waga (kg)",

        długość: "Długość (cm)",
        dlugosc: "Długość (cm)",
        length: "Długość (cm)",

        szerokość: "Szerokość (cm)",
        szerokosc: "Szerokość (cm)",
        width: "Szerokość (cm)",

        wysokość: "Wysokość (cm)",
        wysokosc: "Wysokość (cm)",
        height: "Wysokość (cm)",

        m3: "m3",
        volume: "m3",
        objętość: "m3",
        objetosc: "m3",

        "informacje dodatkowe": "Informacje dodatkowe ",
        uwagi: "Informacje dodatkowe ",
        notes: "Informacje dodatkowe ",
        comments: "Informacje dodatkowe ",

        "numer kartonu": "Numer kartonu",
        "n karton": "Numer kartonu",
        "box number": "Numer kartonu",

        "numer palety": "Numer palety",
        "n paleta": "Numer palety",
        "pallet number": "Numer palety",
      };

      // Get available items based on current tranzit_warehouse setting
      const availableItems = [
        "",
        "Nazwa *",
        "SKU",
        "EAN *",
        "Ilość",
        "jednostka *",
        "Cena",
        "Waga (kg)",
        "Długość (cm)",
        "Szerokość (cm)",
        "Wysokość (cm)",
        "m3",
        "Informacje dodatkowe ",
        ...(this.tranzit_warehouse == 0
          ? ["Numer kartonu", "Numer palety"]
          : []),
      ];

      // Process each column header
      this.table[0].forEach((header, index) => {
        if (header && typeof header === "string") {
          const normalizedHeader = header.toLowerCase().trim();

          // Try to find exact match first
          let mappedField = headerMappings[normalizedHeader];

          // If no exact match, try partial matches
          if (!mappedField) {
            for (const [key, value] of Object.entries(headerMappings)) {
              if (
                normalizedHeader.includes(key) ||
                key.includes(normalizedHeader)
              ) {
                mappedField = value;
                break;
              }
            }
          }

          // Set the mapping if found and available
          if (mappedField && availableItems.includes(mappedField)) {
            this.headerSelection[index] = mappedField;
            console.log(`Auto-mapped "${header}" to "${mappedField}"`);
          }
        }
      });

      // Update the header array after auto-mapping
      this.getheader();
    },

    onFileChange(event) {
      const fileList = event && event.target ? event.target.files : null;
      let file = fileList && fileList.length ? fileList[0] : null;
      if (!file) return;

      // Clear validation results when new file is selected
      this.validationResults = null;
      this.rowHighlights = [];

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
              vm.rowHighlights = new Array(vm.table.length).fill(""); // Initialize highlights

              // Auto-map headers if they match available options
              vm.autoMapHeaders();

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
.text-info {
  color: #1976d2;
}
.d-flex {
  display: flex;
}
.gap-2 {
  gap: 8px;
}

/* Row highlighting styles */
.existing-product {
  background-color: #c8e6c9 !important; /* Light green/salad */
}

.new-product {
  background-color: #f8bbd9 !important; /* Pink */
}

.error-product {
  background-color: #fff9c4 !important; /* Yellow */
}
</style>
