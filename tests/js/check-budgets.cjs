const fs = require("node:fs");

const budgets = {
  "assets/dist/editor.js": 20 * 1024,
  "assets/dist/parser-worker.js": 12 * 1024,
  "assets/dist/frontend.js": 8 * 1024,
  "assets/dist/editor.css": 8 * 1024,
  "assets/dist/frontend.css": 8 * 1024,
};

for (const [file, limit] of Object.entries(budgets)) {
  const bytes = fs.statSync(file).size;
  if (bytes > limit) {
    throw new Error(`${file} is ${bytes} bytes; budget is ${limit} bytes`);
  }
  process.stdout.write(`${file}: ${bytes}/${limit} bytes\n`);
}
