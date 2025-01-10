<template>
  <div>
    <div ref="printArea" style="display: none">
      <div id="barcode"></div>
    </div>
  </div>
</template>

<script>
import JsBarcode from "jsbarcode";

export default {
  methods: {
    print(barcode, sku) {
      const barcodeElement = document.createElement("svg");
      JsBarcode(barcodeElement, barcode, {
        format: "CODE128",
        width: 2,
        height: 40,
        displayValue: true,
      });

      this.$refs.printArea.innerHTML = "";
      this.$refs.printArea.appendChild(barcodeElement);

      const printWindow = window.open("", "_blank");
      printWindow.document.write(`
        <html>
          <head>
            <title>Drukowanie kodów kreskowych</title>
          </head>
          <body>
            <p>SKU: ${sku}</p>
            ${this.$refs.printArea.innerHTML}
          </body>
        </html>
      `);
      printWindow.document.close();
      printWindow.print();
      printWindow.onafterprint = () => {
        printWindow.close();
      };
    },
  },
};
</script>
