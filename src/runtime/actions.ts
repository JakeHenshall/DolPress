declare global {
  interface Window {
    wp?: { apiFetch: (args: Record<string, unknown>) => Promise<unknown> };
  }
}

export function bindDocumentActions(
  root: HTMLElement,
  opts: { toggleSource?: () => void; postId?: number; formUrl?: string; macroUrl?: string }
): void {
  const scrollBehavior = window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth";
  root.addEventListener("click", (event) => {
    const button = (event.target as HTMLElement).closest<HTMLElement>("[data-dolpress-action], [data-dolpress-song]");
    if (!button) return;
    const song = button.getAttribute("data-dolpress-song");
    if (song) {
      playSong(song);
      return;
    }
    const action = button.getAttribute("data-dolpress-action");
    if (action === "top") window.scrollTo({ top: 0, behavior: scrollBehavior });
    if (action === "print") window.print();
    if (action === "toggle-source") opts.toggleSource?.();
    if (action === "preview") window.open(window.location.href, "_blank", "noopener");
    if (action === "jump") {
      const target = button.getAttribute("data-dolpress-target");
      if (target) document.getElementById(target)?.scrollIntoView({ behavior: scrollBehavior });
    }
    if (action === "url") {
      const href = button.getAttribute("data-dolpress-url");
      if (href && /^(https?:|mailto:)/i.test(href)) window.location.href = href;
    }
    if (action === "toggle-tree") {
      const details = button.closest("details");
      if (details) details.open = !details.open;
    }
    if (action === "submit-form") {
      const formEl = button.closest("form");
      if (formEl instanceof HTMLFormElement) {
        event.preventDefault();
        void submitForm(formEl, opts.postId, opts.formUrl);
      }
    }
    const named = button.getAttribute("data-dolpress-lc");
    if (named && opts.macroUrl && opts.postId) {
      void window.wp?.apiFetch?.({ url: opts.macroUrl, method: "POST", data: { postId: opts.postId, name: named } });
    }
  });

  root.addEventListener("submit", (event) => {
    const formEl = event.target;
    if (!(formEl instanceof HTMLFormElement) || !formEl.classList.contains("dolpress-form")) return;
    event.preventDefault();
    void submitForm(formEl, opts.postId, opts.formUrl);
  });
}

async function submitForm(formEl: HTMLFormElement, postId?: number, formUrl?: string) {
  if (!postId || !formUrl) return;
  const values: Record<string, string> = {};
  new FormData(formEl).forEach((value, key) => {
    if (typeof value === "string") values[key] = value;
  });
  formEl.querySelectorAll<HTMLInputElement>('input[type="checkbox"]').forEach((el) => {
    values[el.name] = el.checked ? "1" : "0";
  });
  if (window.wp?.apiFetch) {
    await window.wp.apiFetch({ url: formUrl, method: "POST", data: { postId, values } });
    return;
  }
  const nonce = (window as unknown as { dolpressFront?: { nonce: string } }).dolpressFront?.nonce;
  await fetch(formUrl, {
    method: "POST",
    credentials: "same-origin",
    headers: {
      "Content-Type": "application/json",
      "X-WP-Nonce": nonce || "",
    },
    body: JSON.stringify({ postId, values }),
  });
}

function playSong(notes: string) {
  const AudioCtx = window.AudioContext || (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext;
  if (!AudioCtx) return;
  const ctx = new AudioCtx();
  const map: Record<string, number> = { C: 261.63, D: 293.66, E: 329.63, F: 349.23, G: 392.0, A: 440.0, B: 493.88 };
  let t = ctx.currentTime;
  for (const ch of notes.toUpperCase()) {
    const freq = map[ch];
    if (!freq) continue;
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.frequency.value = freq;
    gain.gain.value = 0.08;
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start(t);
    osc.stop(t + 0.18);
    t += 0.2;
  }
}
