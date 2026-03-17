import { cpSync, mkdirSync, rmSync, writeFileSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const rootDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const nodeModulesDir = path.join(rootDir, "node_modules");
const vendorDir = path.join(rootDir, "laravel", "public", "vendor");

function ensureDir(targetPath) {
  mkdirSync(targetPath, { recursive: true });
}

function copyAsset(sourcePath, targetPath) {
  ensureDir(path.dirname(targetPath));
  cpSync(sourcePath, targetPath);
}

function buildFontFace({ family, weight, fileBasename }) {
  return `@font-face {
  font-family: "${family}";
  font-style: normal;
  font-display: swap;
  font-weight: ${weight};
  src:
    url("./${fileBasename}.woff2") format("woff2"),
    url("./${fileBasename}.woff") format("woff");
}`;
}

function syncFonts() {
  const fontsDir = path.join(vendorDir, "fonts");
  const plusWeights = [400, 500, 600, 700, 800];
  const cormorantWeights = [600, 700];
  const cssBlocks = [];

  ensureDir(fontsDir);

  for (const weight of plusWeights) {
    const fileBasename = `plus-jakarta-sans-latin-${weight}-normal`;
    copyAsset(
      path.join(nodeModulesDir, "@fontsource", "plus-jakarta-sans", "files", `${fileBasename}.woff2`),
      path.join(fontsDir, `${fileBasename}.woff2`)
    );
    copyAsset(
      path.join(nodeModulesDir, "@fontsource", "plus-jakarta-sans", "files", `${fileBasename}.woff`),
      path.join(fontsDir, `${fileBasename}.woff`)
    );
    cssBlocks.push(
      buildFontFace({
        family: "Plus Jakarta Sans",
        weight,
        fileBasename
      })
    );
  }

  for (const weight of cormorantWeights) {
    const fileBasename = `cormorant-garamond-latin-${weight}-normal`;
    copyAsset(
      path.join(nodeModulesDir, "@fontsource", "cormorant-garamond", "files", `${fileBasename}.woff2`),
      path.join(fontsDir, `${fileBasename}.woff2`)
    );
    copyAsset(
      path.join(nodeModulesDir, "@fontsource", "cormorant-garamond", "files", `${fileBasename}.woff`),
      path.join(fontsDir, `${fileBasename}.woff`)
    );
    cssBlocks.push(
      buildFontFace({
        family: "Cormorant Garamond",
        weight,
        fileBasename
      })
    );
  }

  writeFileSync(path.join(fontsDir, "fonts.css"), `${cssBlocks.join("\n\n")}\n`);
}

function syncPhosphorIcons() {
  for (const variant of ["regular", "fill"]) {
    const sourceDir = path.join(nodeModulesDir, "@phosphor-icons", "web", "src", variant);
    const targetDir = path.join(vendorDir, "phosphor", variant);
    const files =
      variant === "regular"
        ? ["style.css", "Phosphor.woff2", "Phosphor.woff", "Phosphor.ttf", "Phosphor.svg"]
        : [
            "style.css",
            "Phosphor-Fill.woff2",
            "Phosphor-Fill.woff",
            "Phosphor-Fill.ttf",
            "Phosphor-Fill.svg"
          ];

    ensureDir(targetDir);
    for (const fileName of files) {
      copyAsset(path.join(sourceDir, fileName), path.join(targetDir, fileName));
    }
  }
}

function syncPlyr() {
  const sourceDir = path.join(nodeModulesDir, "plyr", "dist");
  const targetDir = path.join(vendorDir, "plyr");

  ensureDir(targetDir);
  for (const fileName of ["plyr.css", "plyr.min.js", "plyr.svg"]) {
    copyAsset(path.join(sourceDir, fileName), path.join(targetDir, fileName));
  }
  copyAsset(path.join(rootDir, "assets", "plyr", "blank.mp4"), path.join(targetDir, "blank.mp4"));
}

function syncHls() {
  const targetDir = path.join(vendorDir, "hls");

  ensureDir(targetDir);
  copyAsset(path.join(nodeModulesDir, "hls.js", "dist", "hls.min.js"), path.join(targetDir, "hls.min.js"));
}

function syncColcade() {
  const targetDir = path.join(vendorDir, "colcade");

  ensureDir(targetDir);
  copyAsset(path.join(nodeModulesDir, "colcade", "colcade.js"), path.join(targetDir, "colcade.js"));
}

rmSync(vendorDir, { recursive: true, force: true });
ensureDir(vendorDir);
syncFonts();
syncPhosphorIcons();
syncPlyr();
syncColcade();
syncHls();

console.log(`Synced vendor assets to ${vendorDir}`);
