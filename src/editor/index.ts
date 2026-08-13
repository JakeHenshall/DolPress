type Boot = {
  postId: number;
  source: string;
  modifiedGmt: string;
  mode: "source" | "rendered" | "split";
  commands: Array<{
    code: string;
    label: string;
    help: string;
    group: string;
    examples?: string[];
    schema: {
      flags?: string[];
      positional?: Array<{ name: string; type: string; required?: boolean; choices?: string[]; default?: unknown }>;
      named?: Array<{ name: string; type: string; required?: boolean; choices?: string[]; default?: unknown }>;
    };
  }>;
  rest: { root: string; nonce: string; preview: string; schema: string; post: string };
  safeModeUrl: string;
  previewUrl: string;
  workerUrl: string;
  capabilities: { edit: boolean; publish: boolean };
  settings: { strictDiagnostics: boolean; maxSourceBytes: number };
  strings: Record<string, string>;
};

type Diag = { severity: string; code: string; message: string; line: number; column: number };

declare global {
  interface Window {
    dolpressEditor?: Boot;
    wp?: { apiFetch: (args: Record<string, unknown>) => Promise<unknown> };
  }
}

const boot = window.dolpressEditor;
const root = document.getElementById("dolpress-editor-root");
const fallback = document.getElementById("content") as HTMLTextAreaElement | null;

if (!boot || !root) {
  // Editor assets loaded on a non-DolPress screen.
} else {
  void startEditor(boot, root, fallback);
}

async function startEditor(boot: Boot, root: HTMLElement, fallback: HTMLTextAreaElement | null) {
  let source = boot.source;
  let mode = boot.mode;
  let saveState = "saved";
  let diagnostics: Diag[] = [];
  let cursor = { line: 1, column: 1 };
  let parseId = 0;
  let autosaveTimer = 0;
  let previewTimer = 0;

  root.innerHTML = `
    <div class="dp-shell" role="application" aria-label="DolPress editor">
      <div class="dp-toolbar">
        <div class="dp-modes" role="tablist" aria-label="Editor view">
          <button type="button" data-mode="source" role="tab">${esc(boot.strings.source)}</button>
          <button type="button" data-mode="rendered" role="tab">${esc(boot.strings.rendered)}</button>
          <button type="button" data-mode="split" role="tab">${esc(boot.strings.split)}</button>
        </div>
        <button type="button" class="dp-palette-btn" data-action="palette">${esc(boot.strings.palette)}</button>
        <a class="dp-safe" href="${esc(boot.safeModeUrl)}">${esc(boot.strings.recovery)}</a>
      </div>
      <div class="dp-workspace">
        <label class="dp-source-wrap" for="dolpress-source">
          <span class="screen-reader-text">${esc(boot.strings.source)}</span>
          <textarea id="dolpress-source" class="dp-source" spellcheck="false" aria-label="${esc(boot.strings.source)}"></textarea>
        </label>
        <div class="dp-rendered" tabindex="0" aria-label="${esc(boot.strings.rendered)}"></div>
      </div>
      <div class="dp-status" role="status">
        <span data-stat="mode"></span>
        <span data-stat="cursor"></span>
        <span data-stat="save"></span>
        <span data-stat="parse"></span>
        <span data-stat="size"></span>
      </div>
      <div class="dp-diags" aria-live="polite"></div>
    </div>
    <div class="dp-modal" hidden>
      <div class="dp-modal__panel" role="dialog" aria-modal="true" aria-labelledby="dp-palette-title">
        <h2 id="dp-palette-title">${esc(boot.strings.palette)}</h2>
        <input type="search" class="dp-search" placeholder="Search commands" />
        <div class="dp-cmd-list"></div>
        <div class="dp-cmd-form"></div>
        <pre class="dp-preview-src"></pre>
        <div class="dp-modal__actions">
          <button type="button" data-insert>Insert</button>
          <button type="button" data-cancel>Cancel</button>
        </div>
      </div>
    </div>
  `;

  const sourceEl = root.querySelector(".dp-source") as HTMLTextAreaElement;
  const renderedEl = root.querySelector(".dp-rendered") as HTMLElement;
  const modal = root.querySelector(".dp-modal") as HTMLElement;
  const search = root.querySelector(".dp-search") as HTMLInputElement;
  const list = root.querySelector(".dp-cmd-list") as HTMLElement;
  const form = root.querySelector(".dp-cmd-form") as HTMLElement;
  const previewSrc = root.querySelector(".dp-preview-src") as HTMLElement;
  sourceEl.value = source;

  const worker = boot.workerUrl ? new Worker(boot.workerUrl) : null;
  worker?.addEventListener("message", (event: MessageEvent<{ id: number; result: { diagnostics: Diag[] } }>) => {
    if (event.data.id !== parseId) return;
    diagnostics = event.data.result.diagnostics;
    renderStatus();
    renderDiags();
  });

  const setMode = (next: Boot["mode"]) => {
    mode = next;
    root.querySelector(".dp-shell")?.setAttribute("data-mode", mode);
    root.querySelectorAll(".dp-modes [data-mode]").forEach((btn) => {
      const active = btn.getAttribute("data-mode") === mode;
      btn.classList.toggle("is-active", active);
      btn.setAttribute("aria-selected", active ? "true" : "false");
    });
    void window.wp?.apiFetch?.({ path: "/dolpress/v1/mode", method: "POST", data: { mode } });
    renderStatus();
  };

  const syncFallback = () => {
    if (fallback) fallback.value = source;
    sourceEl.value = source;
  };

  const scheduleParse = () => {
    window.clearTimeout(previewTimer);
    previewTimer = window.setTimeout(() => {
      parseId += 1;
      worker?.postMessage({ id: parseId, source });
      void refreshPreview();
    }, 120);
  };

  const refreshPreview = async () => {
    try {
      const res = (await window.wp?.apiFetch?.({
        url: boot.rest.preview,
        method: "POST",
        data: { postId: boot.postId, source },
      })) as { html?: string; diagnostics?: Diag[] };
      if (res?.html) renderedEl.innerHTML = res.html;
      if (res?.diagnostics) {
        diagnostics = res.diagnostics;
        renderDiags();
      }
    } catch {
      renderedEl.innerHTML = `<p class="dp-error">${esc("Preview failed. Use safe mode if the editor cannot recover.")}</p><p><a href="${esc(boot.safeModeUrl)}">${esc(boot.strings.recovery)}</a></p>`;
    }
  };

  const save = async (asAutosave = false) => {
    if (!boot.capabilities.edit) return;
    saveState = "saving";
    renderStatus();
    try {
      const current = (await window.wp?.apiFetch?.({ url: boot.rest.post, method: "GET" })) as { modified_gmt?: string; content?: { raw?: string } };
      if (current?.modified_gmt && current.modified_gmt !== boot.modifiedGmt && current.content?.raw !== source) {
        saveState = "conflict";
        renderStatus();
        return;
      }
      const body: Record<string, unknown> = { content: source };
      if (asAutosave) body.status = "draft";
      const saved = (await window.wp?.apiFetch?.({ url: boot.rest.post, method: "POST", data: body })) as { modified_gmt?: string };
      if (saved?.modified_gmt) boot.modifiedGmt = saved.modified_gmt;
      saveState = asAutosave ? "autosaved" : "saved";
    } catch {
      saveState = "error";
    }
    renderStatus();
  };

  const renderStatus = () => {
    const set = (key: string, text: string) => {
      const el = root.querySelector(`[data-stat="${key}"]`);
      if (el) el.textContent = text;
    };
    set("mode", mode);
    set("cursor", `L${cursor.line} C${cursor.column}`);
    set("save", saveState);
    set("parse", diagnostics.some((d) => d.severity === "error") ? "errors" : "ok");
    set("size", `${new Blob([source]).size} B`);
  };

  const renderDiags = () => {
    const box = root.querySelector(".dp-diags");
    if (!box) return;
    box.innerHTML = diagnostics
      .slice(0, 20)
      .map((d) => `<div class="dp-diag dp-diag--${esc(d.severity)}">${esc(d.severity)} ${esc(d.code)} L${d.line}:${d.column} ${esc(d.message)}</div>`)
      .join("");
  };

  sourceEl.addEventListener("input", () => {
    source = sourceEl.value;
    saveState = "unsaved";
    syncFallback();
    scheduleParse();
    window.clearTimeout(autosaveTimer);
    autosaveTimer = window.setTimeout(() => void save(true), 2500);
    renderStatus();
  });

  sourceEl.addEventListener("keyup", () => {
    const before = sourceEl.value.slice(0, sourceEl.selectionStart);
    const lines = before.split(/\n/);
    cursor = { line: lines.length, column: (lines[lines.length - 1] || "").length + 1 };
    renderStatus();
  });

  root.querySelectorAll(".dp-modes [data-mode]").forEach((btn) => {
    btn.addEventListener("click", () => setMode((btn.getAttribute("data-mode") as Boot["mode"]) || "source"));
  });

  root.querySelector("[data-action=palette]")?.addEventListener("click", () => openPalette());

  document.addEventListener("keydown", (event) => {
    const meta = event.metaKey || event.ctrlKey;
    if (meta && event.key.toLowerCase() === "s") {
      event.preventDefault();
      void save(false);
    }
    if (meta && event.key.toLowerCase() === "t") {
      event.preventDefault();
      setMode(mode === "source" ? "rendered" : "source");
    }
    if (meta && event.key.toLowerCase() === "l") {
      event.preventDefault();
      openPalette();
    }
    if (meta && event.shiftKey && event.key.toLowerCase() === "p") {
      event.preventDefault();
      window.open(boot.previewUrl, "_blank", "noopener");
    }
    if (event.key === "Escape" && !modal.hidden) {
      closePalette();
    }
  });

  let selectedCode = boot.commands[0]?.code || "CR";

  const openPalette = () => {
    modal.hidden = false;
    search.value = "";
    renderList();
    search.focus();
  };

  const closePalette = () => {
    modal.hidden = true;
    form.innerHTML = "";
    sourceEl.focus();
  };

  const insertSelected = () => {
    const insert = snippet();
    const start = sourceEl.selectionStart ?? source.length;
    const end = sourceEl.selectionEnd ?? start;
    source = source.slice(0, start) + insert + source.slice(end);
    syncFallback();
    sourceEl.selectionStart = sourceEl.selectionEnd = start + insert.length;
    closePalette();
    sourceEl.dispatchEvent(new Event("input"));
  };

  const renderList = () => {
    const q = search.value.toLowerCase();
    const items = boot.commands.filter((c) => `${c.code} ${c.label} ${c.help}`.toLowerCase().includes(q));
    list.innerHTML = items
      .map(
        (c) =>
          `<button type="button" class="dp-cmd ${c.code === selectedCode ? "is-active" : ""}" data-code="${esc(c.code)}"><strong>${esc(c.code)}</strong> ${esc(c.label)}<span>${esc(c.help)}</span></button>`
      )
      .join("");
    renderForm();
  };

  const currentCommand = () => boot.commands.find((c) => c.code === selectedCode);

  const snippet = () => {
    const cmd = currentCommand();
    if (!cmd) return "";
    const flags = Array.from(form.querySelectorAll<HTMLInputElement>("[data-flag]"))
      .filter((el) => el.checked)
      .map((el) => "+" + el.value)
      .join("");
    const parts: string[] = [];
    form.querySelectorAll<HTMLInputElement | HTMLSelectElement>("[data-arg]").forEach((el) => {
      if (!el.value) return;
      const name = el.getAttribute("data-arg") || "";
      const positional = el.getAttribute("data-pos") === "1";
      const value = /^-?\d+(\.\d+)?$/.test(el.value) ? el.value : `"${el.value.replace(/"/g, '\\"')}"`;
      parts.push(positional ? value : `${name}=${value}`);
    });
    return `$${cmd.code}${flags}${parts.length ? "," + parts.join(",") : ""}$`;
  };

  const renderForm = () => {
    const cmd = currentCommand();
    if (!cmd) return;
    const flags = (cmd.schema.flags || [])
      .map((flag) => `<label><input type="checkbox" data-flag value="${esc(flag)}" /> +${esc(flag)}</label>`)
      .join("");
    const fields = [
      ...(cmd.schema.positional || []).map((field) => fieldHtml(field, true)),
      ...(cmd.schema.named || []).map((field) => fieldHtml(field, false)),
    ].join("");
    form.innerHTML = `${flags}${fields}<p>${esc(cmd.help)}</p>${(cmd.examples || []).map((ex) => `<code>${esc(ex)}</code>`).join(" ")}`;
    previewSrc.textContent = snippet();
  };

  const fieldHtml = (
    field: { name: string; type: string; required?: boolean; choices?: string[]; default?: unknown },
    positional: boolean
  ) => {
    if (field.choices?.length) {
      return `<label>${esc(field.name)} <select data-arg="${esc(field.name)}" data-pos="${positional ? "1" : "0"}">${field.choices
        .map((c) => `<option ${String(field.default) === c ? "selected" : ""}>${esc(c)}</option>`)
        .join("")}</select></label>`;
    }
    return `<label>${esc(field.name)} <input data-arg="${esc(field.name)}" data-pos="${positional ? "1" : "0"}" value="${esc(String(field.default ?? ""))}" /></label>`;
  };

  list.addEventListener("click", (event) => {
    const btn = (event.target as HTMLElement).closest("[data-code]");
    if (!btn) return;
    selectedCode = btn.getAttribute("data-code") || selectedCode;
    renderList();
  });
  search.addEventListener("input", renderList);
  search.addEventListener("keydown", (event) => {
    if (event.key === "Enter") event.preventDefault();
  });
  form.addEventListener("input", () => {
    previewSrc.textContent = snippet();
  });
  modal.addEventListener("keydown", (event) => {
    if (event.key !== "Enter") return;
    const target = event.target as HTMLElement;
    if (target.closest("[data-cancel], .dp-cmd, textarea")) return;
    event.preventDefault();
    insertSelected();
  });
  modal.querySelector("[data-insert]")?.addEventListener("click", insertSelected);
  modal.querySelector("[data-cancel]")?.addEventListener("click", closePalette);

  renderedEl.addEventListener("click", (event) => {
    const button = (event.target as HTMLElement).closest<HTMLElement>("[data-dolpress-action]");
    if (!button) return;
    const action = button.getAttribute("data-dolpress-action");
    if (action === "top") window.scrollTo({ top: 0, behavior: "smooth" });
    if (action === "print") window.print();
    if (action === "toggle-source") setMode(mode === "source" ? "rendered" : "source");
  });

  setMode(mode);
  syncFallback();
  scheduleParse();
  renderStatus();
}

function esc(value: string): string {
  return value
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}
