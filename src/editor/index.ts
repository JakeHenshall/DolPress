import { bindDocumentActions } from "../runtime/actions";

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
  rest: { root: string; nonce: string; preview: string; schema: string; post: string; autosave: string; form?: string; bins?: string; macro?: string };
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
  let previewRequest = 0;
  let modalOpener: HTMLElement | null = null;

  root.innerHTML = `
    <div class="dp-shell">
      <div class="dp-toolbar">
        <div class="dp-modes" role="tablist" aria-label="Editor view">
          <button type="button" role="tab" id="dp-tab-source" data-mode="source" aria-selected="false" aria-controls="dp-workspace">${esc(boot.strings.source)}</button>
          <button type="button" role="tab" id="dp-tab-rendered" data-mode="rendered" aria-selected="false" aria-controls="dp-workspace">${esc(boot.strings.rendered)}</button>
          <button type="button" role="tab" id="dp-tab-split" data-mode="split" aria-selected="false" aria-controls="dp-workspace">${esc(boot.strings.split)}</button>
        </div>
        <button type="button" class="dp-palette-btn" data-action="palette">${esc(boot.strings.palette)}</button>
        <button type="button" data-action="insert-tree">Tree</button>
        <button type="button" data-action="insert-field">Field</button>
        <button type="button" data-action="insert-sprite">Sprite</button>
        <a class="dp-safe" href="${esc(boot.safeModeUrl)}">${esc(boot.strings.recovery)}</a>
      </div>
      <div class="dp-workspace" id="dp-workspace">
        <label class="dp-source-wrap" for="dolpress-source">
          <span class="screen-reader-text">${esc(boot.strings.source)}</span>
          <textarea id="dolpress-source" class="dp-source" spellcheck="false" aria-label="${esc(boot.strings.source)}"></textarea>
        </label>
        <div class="dp-rendered" role="region" tabindex="0" aria-label="${esc(boot.strings.rendered)}"></div>
      </div>
      <div class="dp-status" role="status" aria-atomic="true">
        <span data-stat="mode"></span>
        <span data-stat="cursor"></span>
        <span data-stat="save"></span>
        <span data-stat="parse"></span>
        <span data-stat="size"></span>
      </div>
      <div class="dp-diags" aria-live="polite" aria-atomic="false"></div>
      <div class="dp-live screen-reader-text" role="status" aria-live="polite"></div>
    </div>
    <div class="dp-modal" hidden>
      <div class="dp-modal__panel" role="dialog" aria-modal="true" aria-labelledby="dp-palette-title">
        <h2 id="dp-palette-title">${esc(boot.strings.palette)}</h2>
        <label class="screen-reader-text" for="dp-command-search">Search commands</label>
        <input id="dp-command-search" type="search" class="dp-search" placeholder="Search commands" autocomplete="off" />
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
  const known = boot.commands.map((c) => c.code);

  const worker = boot.workerUrl ? new Worker(boot.workerUrl) : null;
  worker?.addEventListener("message", (event: MessageEvent<{ id: number; result: { diagnostics: Diag[] } }>) => {
    if (event.data.id !== parseId) return;
    diagnostics = event.data.result.diagnostics;
    renderStatus();
    renderDiags();
  });

  const announce = (text: string) => {
    const live = root.querySelector(".dp-live");
    if (live) {
      live.textContent = "";
      window.setTimeout(() => {
        if (live) live.textContent = text;
      }, 30);
    }
  };

  const setMode = (next: Boot["mode"]) => {
    mode = next;
    root.querySelector(".dp-shell")?.setAttribute("data-mode", mode);
    root.querySelectorAll<HTMLElement>(".dp-modes [role=tab]").forEach((btn) => {
      const active = btn.getAttribute("data-mode") === mode;
      btn.classList.toggle("is-active", active);
      btn.setAttribute("aria-selected", active ? "true" : "false");
      btn.tabIndex = active ? 0 : -1;
    });
    void window.wp?.apiFetch?.({ path: "/dolpress/v1/mode", method: "POST", data: { mode } });
    if (mode !== "source") void refreshPreview();
    renderStatus();
  };

  const syncFallback = () => {
    if (fallback) fallback.value = source;
    sourceEl.value = source;
  };

  const insertAtCursor = (snippet: string) => {
    const start = sourceEl.selectionStart ?? source.length;
    const end = sourceEl.selectionEnd ?? start;
    source = source.slice(0, start) + snippet + source.slice(end);
    syncFallback();
    sourceEl.selectionStart = sourceEl.selectionEnd = start + snippet.length;
    sourceEl.dispatchEvent(new Event("input"));
  };

  const scheduleParse = () => {
    window.clearTimeout(previewTimer);
    previewTimer = window.setTimeout(() => {
      parseId += 1;
      worker?.postMessage({ id: parseId, source, known });
      if (mode !== "source") void refreshPreview();
    }, 180);
  };

  const refreshPreview = async () => {
    const request = ++previewRequest;
    try {
      const res = (await window.wp?.apiFetch?.({
        url: boot.rest.preview,
        method: "POST",
        data: { postId: boot.postId, source },
      })) as { html?: string; diagnostics?: Diag[] };
      if (request !== previewRequest) return;
      if (res?.html) renderedEl.innerHTML = res.html;
      if (res?.diagnostics) {
        diagnostics = res.diagnostics;
        renderDiags();
      }
      announce("Preview updated.");
    } catch {
      if (request !== previewRequest) return;
      renderedEl.innerHTML = `<p class="dp-error">${esc("Preview failed. Use safe mode if the editor cannot recover.")}</p><p><a href="${esc(boot.safeModeUrl)}">${esc(boot.strings.recovery)}</a></p>`;
      announce("Preview failed.");
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
        announce("Save blocked: the document changed elsewhere. Review changes before saving.");
        return;
      }
      const saved = (await window.wp?.apiFetch?.({
        url: asAutosave ? boot.rest.autosave : boot.rest.post,
        method: "POST",
        data: { content: source },
      })) as { modified_gmt?: string };
      if (!asAutosave && saved?.modified_gmt) boot.modifiedGmt = saved.modified_gmt;
      saveState = asAutosave ? "autosaved" : "saved";
    } catch {
      saveState = "error";
      announce("Save failed.");
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

  const modesEl = root.querySelector(".dp-modes");
  if (modesEl) {
    modesEl.addEventListener("keydown", (event) => {
      if (!("key" in event)) return;
      const key = (event as KeyboardEvent).key;
      if (key !== "ArrowLeft" && key !== "ArrowRight" && key !== "Home" && key !== "End") return;
      const tabs = Array.from(root.querySelectorAll<HTMLElement>(".dp-modes [role=tab]"));
      const current = tabs.indexOf(document.activeElement as HTMLElement);
      if (-1 === current) return;
      event.preventDefault();
      let next = current;
      if ("ArrowLeft" === key) next = (current + tabs.length - 1) % tabs.length;
      if ("ArrowRight" === key) next = (current + 1) % tabs.length;
      if ("Home" === key) next = 0;
      if ("End" === key) next = tabs.length - 1;
      tabs[next]?.focus();
      setMode((tabs[next]?.getAttribute("data-mode") as Boot["mode"]) || "source");
    });
  }

  root.querySelector("[data-action=palette]")?.addEventListener("click", () => openPalette());
  root.querySelector("[data-action=insert-tree]")?.addEventListener("click", () => insertAtCursor('$TR,"Branch"$\n$ID,2$\n\n$ID,-2$\n'));
  root.querySelector("[data-action=insert-field]")?.addEventListener("click", () => insertAtCursor('$DA,KEY="subtitle"$\n'));
  root.querySelector("[data-action=insert-sprite]")?.addEventListener("click", () => {
    insertAtCursor("$SP,BI=1$\n");
    void window.wp?.apiFetch?.({
      url: boot.rest.bins,
      method: "POST",
      data: {
        postId: boot.postId,
        bins: [{ num: 1, tag: "line", data: btoa(JSON.stringify({ ops: [{ t: "line", x1: 0, y1: 0, x2: 40, y2: 24, c: 4 }] })) }],
      },
    });
  });

  document.addEventListener("keydown", (event) => {
    const meta = event.metaKey || event.ctrlKey;
    const target = event.target instanceof HTMLElement ? event.target : null;
    // Shortcuts only apply when focus is inside the editor surface; otherwise
    // browser-native combos (Cmd+T new tab, Cmd+L address bar, Cmd+S save page) win.
    if (!target || (!target.closest(".dp-shell") && !target.closest(".dp-modal"))) return;
    if (meta && "s" === event.key.toLowerCase()) {
      event.preventDefault();
      void save(false);
    }
    if (meta && "t" === event.key.toLowerCase()) {
      event.preventDefault();
      setMode(mode === "source" ? "rendered" : "source");
    }
    if (meta && "l" === event.key.toLowerCase()) {
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
    modalOpener = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    modal.hidden = false;
    const shell = root.querySelector(".dp-shell") as HTMLElement | null;
    if (shell) shell.inert = true;
    search.value = "";
    renderList();
    search.focus();
  };

  const closePalette = () => {
    modal.hidden = true;
    const shell = root.querySelector(".dp-shell") as HTMLElement | null;
    if (shell) shell.inert = false;
    form.innerHTML = "";
    (modalOpener || sourceEl).focus();
    modalOpener = null;
  };

  const insertSelected = () => {
    const insert = snippet();
    insertAtCursor(insert);
    closePalette();
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
    if (event.key === "Tab") {
      const focusable = Array.from(
        modal.querySelectorAll<HTMLElement>('button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href]')
      ).filter((element) => element.offsetParent !== null);
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (first && last && event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (first && last && !event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
      return;
    }
    if (event.key !== "Enter") return;
    const target = event.target as HTMLElement;
    if (target.closest("[data-cancel], .dp-cmd, textarea")) return;
    event.preventDefault();
    insertSelected();
  });
  modal.querySelector("[data-insert]")?.addEventListener("click", insertSelected);
  modal.querySelector("[data-cancel]")?.addEventListener("click", closePalette);

  bindDocumentActions(renderedEl, {
    toggleSource: () => setMode(mode === "source" ? "rendered" : "source"),
    postId: boot.postId,
    formUrl: boot.rest.form,
    macroUrl: boot.rest.macro,
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
