import { parse, highlight, setKnownCodes, type ParseResult } from "../parser/index";

self.onmessage = (event: MessageEvent<{ id: number; source: string; known?: string[] }>) => {
  const { id, source, known } = event.data;
  if (known?.length) setKnownCodes(known);
  const result: ParseResult = parse(source);
  const spans = highlight(source);
  (self as unknown as Worker).postMessage({ id, result, spans });
};
