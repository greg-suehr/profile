
export default class IntroOrchestrator {
  constructor({ bootstrap, mode = 'video', selectors = {}, hooks = {} } = {}) {
    if (!bootstrap) throw new Error('IntroOrchestrator requires bootstrap');
    this.bootstrap = bootstrap;
    this.mode = mode; // 'video' | 'sprite' | 'none'
    this.sel = {
      splash: '[data-role="splash"]',
      enter: '[data-action="enter"]',
      video: '[data-intro="video"]',
      ...selectors,
    };
    this.hooks = hooks;
    this.$ = (s) => document.querySelector(s);
    this.$$ = (s) => Array.from(document.querySelectorAll(s));
    this.dispatch = (type, detail={}) =>
      window.dispatchEvent(new CustomEvent(type, { detail }));
  }

  async init() {
    // Initialize bootstrap once.
    await this.bootstrap.init?.();
    // The scene can render idle (e.g., particles) behind splash if you want:
    this.bootstrap.startScene?.();
    // Prepare UI
    this.splash = this.$(this.sel.splash);
    this.enters = this.$$(this.sel.enter);
    if (this.splash) this.splash.hidden = false;
    this.enters.forEach(btn => btn.addEventListener('click', () => this.begin()));
    this.dispatch('intro:ready', { mode: this.mode });
    this.hooks.onReady?.(this.mode);
  }

  async begin() {
    this.dispatch('intro:begin', { mode: this.mode });
    this.hooks.onBegin?.(this.mode);
    if (this.splash) this.splash.hidden = true;

    try {
      if (this.mode === 'video')      await this.#playVideo();
      else if (this.mode === 'sprite') await this.#playSprite();
      else                             await this.#finish();
    } catch (err) {
      this.dispatch('intro:error', { error: err });
      this.hooks.onError?.(err);
      // Fail-safe: reveal site even if intro fails
      await this.#finish();
    }
  }

  async #playVideo() {
    const video = this.$(this.sel.video);
    if (!video) throw new Error('Video element not found for video mode');

    // Show and attempt playback with user gesture
    video.hidden = false;
    video.currentTime = 0;
    // Optional: if you run background music, duck it here
    this.hooks.onBackgroundDuck?.();

    const endPromise = new Promise((resolve) => {
      const onEnded = () => {
        video.removeEventListener('ended', onEnded);
        resolve();
      };
      video.addEventListener('ended', onEnded);
    });

    try {
      await video.play();
    } catch {
      // Expose controls if autoplay rules block playback
      video.controls = true;
      await video.play().catch(() => {/* user will press play */});
    }

    await endPromise;
    // Fade out video (CSS or inline)
    video.style.transition = 'opacity 1.0s ease';
    video.style.opacity = '0';
    await new Promise(r => setTimeout(r, 1000));
    video.pause();
    video.src = '';
    video.hidden = true;

    await this.#finish();
  }

  async #playSprite() {
    // If your sprite intro is defined as a sequence in the scene JSON (e.g., "ignite"),
    // you can request it via a conventional method or event.
    // We’ll prefer an event so projects without that sequence can no-op.
    this.dispatch('intro:sprite:request', {});
    // Provide a 12s safety timeout in case the sequence never calls back.
    const timeout = 12000;

    const finished = await new Promise((resolve) => {
      let done = false;
      const onDone = () => { if (!done) { done = true; cleanup(); resolve(true);} };
      const onTimeout = setTimeout(onDone, timeout);
      const cleanup = () => {
        clearTimeout(onTimeout);
        window.removeEventListener('intro:sprite:ended', onDone);
      };
      window.addEventListener('intro:sprite:ended', onDone, { once: true });
    });

    if (!finished) {
      // fallthrough; but the promise above will resolve regardless
    }
    await this.#finish();
  }

  async #finish() {
    this.dispatch('intro:ended', { mode: this.mode });
    this.hooks.onEnded?.(this.mode);
    await this.bootstrap.revealSite?.();
  }
}
