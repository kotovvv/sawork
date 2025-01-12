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
    print(barcode, sku, uwagi, order) {
      const barcodeElement = document.createElement("svg");
      JsBarcode(barcodeElement, barcode, {
        format: "CODE128",
        width: 2,
        height: 40,
        displayValue: true,
      });
      let info = "";
      if (Object.keys(order).length !== 0) {
        info += "<p>Order Number: " + order.Number + "</p>";
        info += "<p>Order Uwagi: " + order.Uwagi + "</p>";
        info += "<p>Product Uwagi: " + uwagi + "</p>";
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
            <p style="text-align:center">${sku}</p>
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
