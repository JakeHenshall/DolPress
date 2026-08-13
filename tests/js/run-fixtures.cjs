const fs = require("fs");
const path = require("path");
const { parse } = require("./parser.cjs");

const fixtures = JSON.parse(fs.readFileSync(path.join(__dirname, "../../grammar/fixtures.json"), "utf8"));
let failed = 0;

for (const fixture of fixtures) {
  const result = parse(fixture.source);
  const codes = result.diagnostics.map((d) => d.code);
  for (const expected of fixture.diagnostics) {
    if (!codes.includes(expected.code)) {
      console.error(fixture.name, "missing diagnostic", expected.code, "got", codes);
      failed += 1;
    }
  }
  if (!matchTree(result.document.children, fixture.tree)) {
    console.error(fixture.name, "tree mismatch");
    failed += 1;
  }
}

if (failed) {
  console.error(failed, "fixture failures");
  process.exit(1);
}

console.log("parser fixtures ok", fixtures.length);

function matchTree(nodes, expected) {
  if (nodes.length !== expected.length) return false;
  return expected.every((want, i) => {
    const node = nodes[i];
    if (!node) return false;
    if (want.type && want.type !== node.type) return false;
    if (want.value && want.value !== node.value) return false;
    if (want.code && want.code !== node.code) return false;
    if (want.unknown && !node.unknown) return false;
    if (want.malformed && !node.malformed) return false;
    if (want.flags && JSON.stringify(want.flags) !== JSON.stringify(node.flags)) return false;
    if (want.children && !matchTree(node.children || [], want.children)) return false;
    return true;
  });
}
