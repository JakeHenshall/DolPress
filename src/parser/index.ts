export type Diagnostic = {
  severity: string;
  code: string;
  message: string;
  line: number;
  column: number;
  offset: number;
  length: number;
  help?: string | null;
};

export type Argument = { name: string; value: string | number | boolean; kind: string };

export type Node =
  | { type: "text"; value: string; offset: number; length: number; line: number; column: number }
  | {
      type: "command";
      code: string;
      flags: string[];
      arguments: Argument[];
      children: Node[];
      unknown: boolean;
      malformed: boolean;
      raw: string;
      offset: number;
      length: number;
      line: number;
      column: number;
    };

export type ParseResult = {
  grammar: string;
  document: { type: "document"; children: Node[] };
  diagnostics: Diagnostic[];
};

const CORE_KNOWN = [
  "TX", "CR", "FG", "BG", "UL", "IV", "HL", "LK", "BT", "TR", "IM", "HR",
  "WS", "WG", "WT", "WA", "WN", "WL", "WP", "WC", "WX", "WM", "WB",
  "SR", "TB", "PB", "PL", "LM", "RM", "HD", "FO", "ID", "FD", "BD", "WW", "BK",
  "SX", "SY", "CM", "AN", "MK", "CU", "PT", "CL",
  "DA", "CB", "LS", "MU", "HX", "MA", "SP", "SO", "HC",
];
let KNOWN = new Set(CORE_KNOWN);
const PAIRED = new Set(["TR"]);

export function setKnownCodes(codes: string[]): void {
  KNOWN = new Set(codes.length ? codes.map((c) => c.toUpperCase()) : CORE_KNOWN);
}

export function parse(source: string, limits = { maxBytes: 102400, maxTokens: 20000, maxCommands: 200, maxNest: 8 }, known?: string[]): ParseResult {
  if (known?.length) setKnownCodes(known);
  const diagnostics: Diagnostic[] = [];
  let input = source;
  if (input.length > limits.maxBytes) {
    diagnostics.push(diag("error", "E_SOURCE_TOO_LARGE", "Source exceeds the maximum size.", 1, 1, 0, input.length));
    input = input.slice(0, limits.maxBytes);
  }

  const children: Node[] = [];
  const stack: { code: string; node: Extract<Node, { type: "command" }>; children: Node[] }[] = [];
  let i = 0;
  let line = 1;
  let column = 1;
  let tokens = 0;
  let commands = 0;

  const loc = () => ({ line, column, offset: i });

  const advance = (n = 1) => {
    for (let k = 0; k < n; k += 1) {
      if (input[i] === "\n") {
        line += 1;
        column = 1;
      } else {
        column += 1;
      }
      i += 1;
    }
  };

  const append = (node: Node) => {
    if (stack.length === 0) children.push(node);
    else stack[stack.length - 1].children.push(node);
  };

  while (i < input.length && tokens < limits.maxTokens) {
    const start = loc();
    if (input[i] !== "$") {
      let value = "";
      const off = i;
      const l = line;
      const c = column;
      while (i < input.length && input[i] !== "$") {
        value += input[i];
        advance();
      }
      if (value) append({ type: "text", value, offset: off, length: value.length, line: l, column: c });
      tokens += 1;
      continue;
    }

    if (input[i + 1] === "$") {
      append({ type: "text", value: "$", offset: i, length: 2, line, column });
      advance(2);
      tokens += 1;
      continue;
    }

    const cmdStart = i;
    const cmdLine = line;
    const cmdCol = column;
    advance();

    if (input[i] === "/") {
      advance();
      const code = readCode();
      skipSpaces();
      if (input[i] === "$") advance();
      const top = stack[stack.length - 1];
      if (!top || top.code !== code) {
        diagnostics.push(diag("error", "E_UNMATCHED_CLOSE", `Closing $/${code}$ does not match an open command.`, cmdLine, cmdCol, cmdStart, i - cmdStart));
      } else {
        stack.pop();
        const length = i - top.node.offset;
        append({ ...top.node, children: top.children, length, raw: input.slice(top.node.offset, i) });
      }
      tokens += 1;
      continue;
    }

    const code = readCode().toUpperCase();
    if (code.length !== 2) {
      diagnostics.push(diag("error", "E_BAD_CODE", "Commands must start with a two-character alphabetic code.", cmdLine, cmdCol, cmdStart, 1, "Use a command such as $CR$ or $TX,\"text\"$."));
      while (i < input.length && input[i] !== "$") advance();
      if (input[i] === "$") advance();
      append({
        type: "command",
        code: "??",
        flags: [],
        arguments: [],
        children: [],
        unknown: true,
        malformed: true,
        raw: input.slice(cmdStart, i),
        offset: cmdStart,
        length: i - cmdStart,
        line: cmdLine,
        column: cmdCol,
      });
      tokens += 1;
      continue;
    }

    const flags: string[] = [];
    while (input[i] === "+") {
      advance();
      flags.push(readIdent().toUpperCase());
    }
    skipSpaces();
    const args: Argument[] = [];
    if (input[i] === ",") {
      advance();
      skipSpaces();
      while (i < input.length && input[i] !== "$") {
        skipSpaces();
        if (input[i] === ",") {
          advance();
          continue;
        }
        if (input[i] === "$") break;
        const arg = readArgument();
        if (arg) args.push(arg);
        else {
          diagnostics.push(diag("error", "E_BAD_ARGUMENT", "Could not parse command argument.", line, column, i, 1));
          while (i < input.length && input[i] !== "," && input[i] !== "$") advance();
        }
      }
    }

    if (input[i] === "$") advance();
    else diagnostics.push(diag("error", "E_UNTERMINATED", "Command is missing a closing dollar.", cmdLine, cmdCol, cmdStart, 1));

    commands += 1;
    const unknown = !KNOWN.has(code);
    if (unknown) diagnostics.push(diag("warning", "W_UNKNOWN_COMMAND", `Unknown command $${code}$.`, cmdLine, cmdCol, cmdStart, i - cmdStart));

    const node: Extract<Node, { type: "command" }> = {
      type: "command",
      code,
      flags,
      arguments: args,
      children: [],
      unknown,
      malformed: false,
      raw: input.slice(cmdStart, i),
      offset: cmdStart,
      length: i - cmdStart,
      line: cmdLine,
      column: cmdCol,
    };

    if (PAIRED.has(code)) {
      if (stack.length >= limits.maxNest) {
        diagnostics.push(diag("error", "E_NESTING_LIMIT", "Nesting exceeds the maximum depth.", cmdLine, cmdCol, cmdStart, i - cmdStart));
        append(node);
      } else {
        stack.push({ code, node, children: [] });
      }
    } else {
      append(node);
    }
    tokens += 1;
    void start;
  }

  while (stack.length) {
    const open = stack.pop()!;
    if (open.code === "TR") {
      append(open.node);
      for (const child of open.children) append(child);
      continue;
    }
    diagnostics.push(diag("error", "E_UNCLOSED", `Command $${open.code}$ was not closed.`, open.node.line, open.node.column, open.node.offset, open.node.length));
    append({ ...open.node, children: open.children, malformed: true });
  }

  const normalised = normalizeTrees(children);

  function readCode(): string {
    let out = "";
    for (let n = 0; n < 2 && /[A-Za-z]/.test(input[i] || ""); n += 1) {
      out += input[i];
      advance();
    }
    return out;
  }

  function readIdent(): string {
    let out = "";
    while (/[A-Za-z0-9_]/.test(input[i] || "")) {
      out += input[i];
      advance();
    }
    return out;
  }

  function skipSpaces() {
    while (input[i] === " " || input[i] === "\t") advance();
  }

  function readString(): string {
    advance();
    let out = "";
    while (i < input.length) {
      if (input[i] === '"') {
        advance();
        return out;
      }
      if (input[i] === "\\") {
        const next = input[i + 1] || "";
        advance(2);
        out += next === "n" ? "\n" : next === "t" ? "\t" : next;
        continue;
      }
      out += input[i];
      advance();
    }
    diagnostics.push(diag("error", "E_UNTERMINATED_STRING", "String is missing a closing quote.", line, column, i, 1));
    return out;
  }

  function readNumber(): number {
    const startOff = i;
    if (input[i] === "-") advance();
    while (/\d/.test(input[i] || "")) advance();
    if (input[i] === "." && /\d/.test(input[i + 1] || "")) {
      advance();
      while (/\d/.test(input[i] || "")) advance();
      return parseFloat(input.slice(startOff, i));
    }
    return parseInt(input.slice(startOff, i), 10);
  }

  function readValue(): Argument {
    if (input[i] === '"') return { name: "", value: readString(), kind: "string" };
    if (/\d/.test(input[i] || "") || (input[i] === "-" && /\d/.test(input[i + 1] || ""))) {
      return { name: "", value: readNumber(), kind: "number" };
    }
    const ident = readIdent();
    const upper = ident.toUpperCase();
    if (upper === "TRUE" || upper === "FALSE") return { name: "", value: upper === "TRUE", kind: "boolean" };
    return { name: "", value: ident, kind: "ident" };
  }

  function readArgument(): Argument | null {
    skipSpaces();
    if (!input[i] || input[i] === "$") return null;
    if (input[i] === '"') return { name: "", value: readString(), kind: "string" };
    if (/\d/.test(input[i]) || (input[i] === "-" && /\d/.test(input[i + 1] || ""))) {
      return { name: "", value: readNumber(), kind: "number" };
    }
    const ident = readIdent();
    if (!ident) return null;
    skipSpaces();
    if (input[i] === "=") {
      advance();
      skipSpaces();
      const value = readValue();
      return { name: ident.toUpperCase(), value: value.value, kind: value.kind };
    }
    const upper = ident.toUpperCase();
    if (upper === "TRUE" || upper === "FALSE") return { name: "", value: upper === "TRUE", kind: "boolean" };
    return { name: "", value: ident, kind: "ident" };
  }

  return {
    grammar: "0.2",
    document: { type: "document", children: normalised },
    diagnostics,
  };
}

function indentDelta(node: Extract<Node, { type: "command" }>): number {
  for (const arg of node.arguments) {
    if (arg.name === "DELTA" || arg.name === "N" || arg.name === "") {
      if (typeof arg.value === "number") return arg.value;
      if (typeof arg.value === "string" && /^-?\d+$/.test(arg.value)) return parseInt(arg.value, 10);
    }
  }
  return 0;
}

function withChildren(node: Extract<Node, { type: "command" }>, children: Node[]): Extract<Node, { type: "command" }> {
  return { ...node, children };
}

function normalizeTrees(nodes: Node[]): Node[] {
  const out: Node[] = [];
  let i = 0;
  while (i < nodes.length) {
    const node = nodes[i];
    if (node.type === "command" && node.code === "TR" && node.children.length === 0) {
      const { body, consumed } = collectBody(nodes, i + 1);
      out.push(withChildren(node, normalizeTrees(body)));
      i += 1 + consumed;
      continue;
    }
    out.push(normalizeNode(node));
    i += 1;
  }
  return out;
}

function collectBody(nodes: Node[], start: number): { body: Node[]; consumed: number } {
  const body: Node[] = [];
  let indent = 0;
  let started = false;
  let i = start;
  while (i < nodes.length) {
    const node = nodes[i];
    if (node.type === "command" && node.code === "ID") {
      const delta = indentDelta(node);
      if (!started) {
        if (delta <= 0) return { body: [], consumed: 0 };
        started = true;
        indent += delta;
        body.push(normalizeNode(node));
        i += 1;
        continue;
      }
      indent += delta;
      body.push(normalizeNode(node));
      i += 1;
      if (indent <= 0) return { body, consumed: i - start };
      continue;
    }
    if (!started) return { body: [], consumed: 0 };
    if (node.type === "command" && node.code === "TR" && node.children.length === 0) {
      const inner = collectBody(nodes, i + 1);
      body.push(withChildren(node, normalizeTrees(inner.body)));
      i += 1 + inner.consumed;
      continue;
    }
    body.push(normalizeNode(node));
    i += 1;
  }
  return started ? { body, consumed: i - start } : { body: [], consumed: 0 };
}

function normalizeNode(node: Node): Node {
  if (node.type === "command" && node.children.length) return withChildren(node, normalizeTrees(node.children));
  return node;
}

function diag(severity: string, code: string, message: string, line: number, column: number, offset: number, length: number, help?: string): Diagnostic {
  return { severity, code, message, line, column, offset, length, help };
}

export function highlight(source: string): { offset: number; length: number; cls: string }[] {
  const parsed = parse(source);
  const spans: { offset: number; length: number; cls: string }[] = [];
  const walk = (nodes: Node[]) => {
    for (const node of nodes) {
      if (node.type === "text") spans.push({ offset: node.offset, length: node.length, cls: "dp-text" });
      else {
        spans.push({ offset: node.offset, length: node.length, cls: node.malformed ? "dp-error" : "dp-cmd" });
        walk(node.children);
      }
    }
  };
  walk(parsed.document.children);
  return spans;
}
