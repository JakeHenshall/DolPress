declare global {
  interface Window {
    wp?: { apiFetch: (args: Record<string, unknown>) => Promise<unknown> };
  }
}

let sharedAudioCtx: AudioContext | null = null;

function audioContext(): AudioContext | null {
  const Ctor = window.AudioContext || (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext;
  if (!Ctor) return null;
  if (!sharedAudioCtx) sharedAudioCtx = new Ctor();
  if (sharedAudioCtx.state === "suspended") void sharedAudioCtx.resume();
  return sharedAudioCtx;
}

let liveRegion: HTMLElement | null = null;

function announceFront(text: string): void {
  if (!liveRegion || !liveRegion.isConnected) {
    liveRegion = document.createElement("div");
    liveRegion.className = "screen-reader-text dolpress-live";
    liveRegion.setAttribute("role", "status");
    liveRegion.setAttribute("aria-live", "polite");
    document.body.appendChild(liveRegion);
  }
  liveRegion.textContent = "";
  window.setTimeout(() => {
    if (liveRegion) liveRegion.textContent = text;
  }, 30);
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
      playSong(song, button);
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
  try {
    if (window.wp?.apiFetch) {
      await window.wp.apiFetch({ url: formUrl, method: "POST", data: { postId, values } });
    } else {
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
    announceFront("Saved.");
  } catch {
    announceFront("Save failed.");
  }
}

function flashButton(button: HTMLElement): void {
  button.classList.add("dolpress-playing");
  window.setTimeout(() => button.classList.remove("dolpress-playing"), 900);
}

function playSong(notes: string, button: HTMLElement): void {
  const ctx = audioContext();
  if (!ctx) return;
  flashButton(button);
  const map: Record<string, number> = { C: 261.63, D: 293.66, E: 329.63, F: 349.23, G: 392.0, A: 440.0, B: 493.88 };
  let t = ctx.currentTime;
  let played = 0;
  for (const ch of notes.toUpperCase()) {
    const freq = map[ch];
    if (!freq) continue;
    ++played;
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
  announceFront(played > 0 ? "Playing song." : "Song has no playable notes.");
}
