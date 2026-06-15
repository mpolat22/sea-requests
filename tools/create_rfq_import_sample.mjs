import fs from "node:fs/promises";
import path from "node:path";
import { SpreadsheetFile, Workbook } from "@oai/artifact-tool";

const workspaceRoot = process.cwd();
const outputDir = path.join(workspaceRoot, "outputs");
const outputPath = path.join(outputDir, "rfq-import-sample.xlsx");

await fs.mkdir(outputDir, { recursive: true });

const workbook = Workbook.create();
const sheet = workbook.worksheets.add("RFQ Import Sample");

sheet.getRange("A1:F9").values = [
  ["Reference / Requisition No", "RFQ-20260517-001", "", "Vessel", "MV Horizon", ""],
  ["Company", "Blue Ocean Shipping", "", "Req Date", new Date("2026-05-20"), ""],
  ["Priority", "High", "", "Nearest Port", "Singapore", ""],
  ["Due Date", new Date("2026-05-22"), "", "", "", ""],
  ["", "", "", "", "", ""],
  ["No", "Description", "Mfg Part", "Qty", "UOM", "Maker"],
  [1, "Charge air cooler seal kit", "322.10.135", 2, "PCS", "MAN"],
  [2, "Fuel oil filter element", "FO-7781", 12, "PCS", "Alfa Laval"],
  [3, "Hydraulic pump overhaul service", "", 1, "LOT", "Bosch Rexroth"],
];

sheet.getRange("G6:M9").values = [
  ["Model Type", "Serial No", "IMPA", "ROB", "Drawing No", "Quality", "Remarks"],
  ["12V32/40", "1065038", "222.333.444", 0, "DRW-204", "OEM", "Side B"],
  ["MAB-103", "FO88411", "551.201.009", 4, "DRW-778", "Genuine", "For stock replenishment"],
  ["A10VSO", "HP-2209", "", 0, "", "Serviceable", "Attend onboard if needed"],
];

sheet.getRange("A1:M1").format = {
  font: { bold: true, color: "#FFFFFF" },
  fill: "#0F4C81",
};
sheet.getRange("A2:M4").format = {
  font: { color: "#04151F" },
  fill: "#F8FAFB",
};
sheet.getRange("A6:M6").format = {
  font: { bold: true, color: "#FFFFFF" },
  fill: "#17324D",
};
sheet.getRange("A7:M9").format = {
  borders: {
    bottom: { style: "thin", color: "#D6DEE6" },
  },
};
sheet.getRange("B2:B4").format.numberFormat = "yyyy-mm-dd";
sheet.getRange("D7:D9").format.numberFormat = "0.00";
sheet.getRange("J7:J9").format.numberFormat = "0.00";
sheet.getRange("A1:M9").format.autofitColumns();

const file = await SpreadsheetFile.exportXlsx(workbook);
await file.save(outputPath);

console.log(outputPath);
