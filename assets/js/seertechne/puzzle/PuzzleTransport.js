export class PuzzleTransport {
  constructor({ endpoint, token }) {
    this.endpoint = endpoint;
    this.token = token;
  }

  /**
   * @returns {Promise<{ok:boolean, step:number, done?:boolean, reward?:string,
   *                     locked?:boolean, retryAfter?:number}>}
   */
  async pick(index) {
    const res = await fetch(this.endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'fetch' },
      body: JSON.stringify({ n: index, t: this.token }),
    });
    if (res.status === 429) {
      const body = await res.json().catch(() => ({}));
      return { ok: false, step: 0, locked: true, retryAfter: body.retryAfter || 60 };
    }
    if (!res.ok) throw new Error(`puzzle transport ${res.status}`);
    return res.json();
  }
}
