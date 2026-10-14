/**
 * Rulează pașii Update Manager-ului secvențial — fiecare pas = request separat (proces PHP nou).
 */
export function updateRunner(config) {
    return {
        running: false,
        done: false,
        failed: false,
        current: null,
        message: '',
        log: [],
        statuses: Object.fromEntries(config.steps.map((s) => [s, config.completed.includes(s) ? 'done' : 'pending'])),

        async run() {
            if (this.running) return;
            if (!confirm(config.labels.confirm)) return;
            this.running = true;
            this.failed = false;
            let step = config.next;
            while (step) {
                this.current = step;
                this.statuses[step] = 'running';
                const r = await window.amFetch(config.stepUrl.replace('__STEP__', step), { method: 'POST', body: JSON.stringify({}) });
                const ok = r.ok && r.data?.ok;
                this.statuses[step] = ok ? 'done' : 'failed';
                this.log.push({ step, ok, message: r.data?.message || (r.status + ' ' + config.labels.httpError) });
                if (!ok) {
                    this.failed = true;
                    this.message = r.data?.message || config.labels.httpError;
                    break;
                }
                step = r.data.next;
            }
            this.running = false;
            this.current = null;
            if (!this.failed) {
                this.done = true;
                this.message = config.labels.success;
                setTimeout(() => window.location.reload(), 2500);
            }
        },
    };
}
