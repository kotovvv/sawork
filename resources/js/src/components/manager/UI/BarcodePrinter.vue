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
    print(barcode, sku, nazwa, uwagi, doc) {
      const barcodeElement = document.createElement("svg");
      JsBarcode(barcodeElement, barcode, {
        format: "CODE128",
        width: 2,
        height: 40,
        displayValue: true,
      });
      let info = "";

      if (Object.keys(doc).length !== 0) {
        info += nazwa + "<br>";
        if (uwagi !== "") {
          info += "Product Uwagi: " + uwagi + "<br>";
        }
        info += "Number: " + doc.Number + "<br>";
        info += "Uwagi: " + doc.Uwagi + "<br>";
      }
      this.$refs.printArea.innerHTML = "";
      this.$refs.printArea.appendChild(barcodeElement);

      const printWindow = window.open("", "_blank");
      printWindow.document.write(`
        <html>
          <head>
            <title>Drukowanie kodów kreskowych</title>
          </head>
          <body>
            ${this.$refs.printArea.innerHTML}
            <br>
            <div style="text-align:center">${sku}</div>
            <br>
            ${info}
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
