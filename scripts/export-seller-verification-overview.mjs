import path from "node:path";
import { pathToFileURL } from "node:url";

const playwrightModulePath = pathToFileURL(
  "C:/Users/rmust/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs"
).href;
const { chromium } = await import(playwrightModulePath);

const root = process.cwd();
const htmlPath = path.join(root, "docs", "seller-verification-overview.html");
const pdfPath = path.join(root, "docs", "seller-verification-overview.pdf");
const pngPath = path.join(root, "docs", "seller-verification-overview.png");

const browser = await chromium.launch({
  headless: true,
  executablePath: "C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe",
});
const page = await browser.newPage({
  viewport: { width: 1180, height: 1580 },
  deviceScaleFactor: 2,
});

await page.goto(pathToFileURL(htmlPath).href, { waitUntil: "networkidle" });
await page.screenshot({ path: pngPath, fullPage: true });
await page.pdf({
  path: pdfPath,
  width: "1120px",
  height: "1480px",
  printBackground: true,
  margin: { top: "0", right: "0", bottom: "0", left: "0" },
});

await browser.close();

console.log(pdfPath);
