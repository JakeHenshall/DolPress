import { parse, highlight, type ParseResult } from "../parser/index";

self.onmessage = (event: MessageEvent<{ id: number; source: string }>) => {
  const { id, source } = event.data;
  const result: ParseResult = parse(source);
  const spans = highlight(source);
  (self as unknown as Worker).postMessage({ id, result, spans });
};
