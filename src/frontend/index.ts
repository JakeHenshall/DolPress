import { bindDocumentActions } from "../runtime/actions";

const root = document.querySelector(".dolpress-document");
if (root instanceof HTMLElement) {
  const postId = Number(root.querySelector<HTMLElement>("[data-dolpress-post]")?.getAttribute("data-dolpress-post") || "0");
  bindDocumentActions(root, {
    postId,
    formUrl: (window as unknown as { dolpressFront?: { rest: string } }).dolpressFront
      ? `${(window as unknown as { dolpressFront: { rest: string } }).dolpressFront.rest}form`
      : undefined,
    macroUrl: (window as unknown as { dolpressFront?: { rest: string } }).dolpressFront
      ? `${(window as unknown as { dolpressFront: { rest: string } }).dolpressFront.rest}macro`
      : undefined,
  });
}
